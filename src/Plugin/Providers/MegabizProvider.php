<?php

namespace Drupal\e_invoice\Plugin\Providers;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\e_invoice\InvoiceProvidersAttribute;
use Drupal\e_invoice\InvoiceProvidersInterface;
use Drupal\e_invoice\Service\GetNumberToWords;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\ClientInterface;

/**
 * Provider for invoicing integration using megabiz.
 */
#[InvoiceProvidersAttribute(
  id: "megabiz",
  label: new TranslatableMarkup("Megabiz"),
)]

class MegabizProvider extends PluginBase implements InvoiceProvidersInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  protected GetNumberToWords $getNumberToWords;

  /**
   * {@inheritDoc}
   */
  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritDoc}
   */
  protected ClientInterface $httpClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    GetNumberToWords $getNumberToWords,
    FileSystemInterface $fileSystem,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->getNumberToWords = $getNumberToWords;
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("e_invoice.get_number_to_words"),
      $container->get('file_system'),
    );
  }

  /**
   * {@inheritDoc}
   */
  private function getAuthen(array $config) {
    $nonce = bin2hex(random_bytes(16));
    return base64_encode("{$config["invoice_username"]}:{$config["invoice_password"]}:{$nonce}");
  }

  /**
   * {@inheritDoc}
   */
  private function getData(array $data, string $type = "create") {

    $invoice_amount = $data["invoice_amount"] ?? 0;
    $invoice_amount_words = $this->getNumberToWords->handle($invoice_amount);

    $dom = new \DOMDocument("1.0", "UTF-8");
    $dom->formatOutput = TRUE;

    $invoice = $dom->createElement("Invoice");

    $invoice->appendChild($dom->createElement("CusCode", $data["invoice_customer_id"] ?? ""));

    $buyer = $dom->createElement("Buyer");
    $buyer->appendChild($dom->createCDATASection($data["invoice_buyer_name"] ?? ""));
    $invoice->appendChild($buyer);

    $cusName = $dom->createElement("CusName");
    $cusName->appendChild($dom->createCDATASection($data["invoice_customer_name"] ?? ""));
    $invoice->appendChild($cusName);

    $invoice->appendChild($dom->createElement("CusPhone", $data["invoice_customer_phone"] ?? ""));
    $invoice->appendChild($dom->createElement("CusAddress", $data["invoice_customer_address"] ?? ""));
    $invoice->appendChild($dom->createElement("CusTaxCode", $data["invoice_customer_taxcode"] ?? ""));
    $invoice->appendChild($dom->createElement("MCHang", $data["invoice_store_id"] ?? ""));
    $invoice->appendChild($dom->createElement("TCHang", $data["invoice_store_name"] ?? ""));
    $invoice->appendChild($dom->createElement("PaymentMethod", $data["invoice_payment"] ?? ""));
    $invoice->appendChild($dom->createElement("ArisingDate", date("d/m/Y")));

    $productsNode = $dom->createElement("Products");
    foreach ($data["products"] ?? [] as $p) {
      $productNode = $dom->createElement("Product");
      $productNode->appendChild($dom->createElement("IsSum", $p["type"] ?? 1));
      $productNode->appendChild($dom->createElement("Code", $p["code"] ?? ""));
      $productNode->appendChild($dom->createElement("ProdName", $p["name"] ?? ""));
      $productNode->appendChild($dom->createElement("ProdUnit", $p["unit"] ?? ""));
      $productNode->appendChild($dom->createElement("ProdQuantity", $p["quantity"] ?? ""));
      $productNode->appendChild($dom->createElement("ProdPrice", $p["price"] ?? ""));
      $productNode->appendChild($dom->createElement("Discount", $p["discount"] ?? ""));
      $productNode->appendChild($dom->createElement("DiscountAmount", $p["discount_amount"] ?? ""));
      $productNode->appendChild($dom->createElement("VATRate", $p["vat"] ?? ""));
      $productNode->appendChild($dom->createElement("VATAmount", $p["vat_amount"] ?? ""));
      $productNode->appendChild($dom->createElement("Total", $p["total"] ?? ""));
      $productNode->appendChild($dom->createElement("Amount", $p["amount"] ?? ""));
      $productsNode->appendChild($productNode);
    }
    $invoice->appendChild($productsNode);

    $invoice->appendChild($dom->createElement("Total", $data["invoice_total"] ?? ""));
    $invoice->appendChild($dom->createElement("VATRate", $data["invoice_vat"] ?? ""));
    $invoice->appendChild($dom->createElement("VATAmount", $data["invoice_vat_amount"] ?? ""));
    $invoice->appendChild($dom->createElement("DiscountAmount", $data["invoice_discount_amount"] ?? ""));
    $invoice->appendChild($dom->createElement("Amount", $invoice_amount));
    $invoice->appendChild($dom->createElement("AmountInWords", $invoice_amount_words));

    if ($type === 'replace') {
      $replaceInv = $dom->createElement("ReplaceInv");
      $dom->appendChild($replaceInv);

      $replaceInv->appendChild(
        $dom->createElement("Fkey", $data["invoice_code"] ?? "")
      );

      while ($invoice->firstChild) {
        $replaceInv->appendChild($invoice->firstChild);
      }
    }
    else {
      $invoices = $dom->createElement("Invoices");
      $dom->appendChild($invoices);

      $inv = $dom->createElement("Inv");
      $invoices->appendChild($inv);

      $inv->appendChild(
        $dom->createElement("key", $data["invoice_code"] ?? "")
      );

      $inv->appendChild($invoice);
    }

    return $dom->saveXML();
  }

  /**
   * {@inheritDoc}
   */
  private function nodeInvoice(string $code, array $data = [], $pdf = NULL, $type = 'add', $invoice_entity = NULL) {
    $invoice_storage = $this->entityTypeManager->getStorage('invoice');

    $attribute = [
      'label' => $code,
      'invoice_key' => $code,
      'invoice_serial' => $data['serial'],
      'invoice_pattern' => $data['pattern'],
    ];

    if ($pdf) {
      $attribute['invoice_pdf'] = [
        'target_id' => $pdf->id(),
        'display' => 1,
      ];
    }

    if ($type == 'add') {
      $invoice = $invoice_storage->create($attribute);
      $invoice->save();
    }
    else {
      foreach ($attribute as $field => $value) {
        $invoice_entity->set($field, $value);
      }
      $invoice_entity->save();
    }

    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  private function callApi(string $url, array $headers, array $payload = [], string $method = "POST"): array {
    $curl = curl_init();

    $option = [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_CUSTOMREQUEST => strtoupper($method),
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_CONNECTTIMEOUT => 10,
    ];

    if (!empty($payload)) {
      $option[CURLOPT_POSTFIELDS] = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      );
    }

    curl_setopt_array($curl, $option);

    $response = curl_exec($curl);

    if ($response === FALSE) {
      $error = curl_error($curl);
      curl_close($curl);
      throw new \RuntimeException("cURL error: " . $error);
    }

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode >= 400) {
      throw new \RuntimeException(
        "HTTP error {$httpCode}: {$response}"
      );
    }

    $decoded = json_decode($response, associative: TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException("Invalid JSON response");
    }

    return [
      "http_code" => $httpCode,
      "data" => $decoded,
    ];
  }

  /**
   * Xem pdf nháp.
   */
  public function pdfDraftInv(array $config, array $data): array {
    $endPoint = "/api/tt78/hoadon/xemhoadon";
    $authen = $this->getAuthen($config);
    $xmlData = $this->getData($data);

    return $this->callApi(
      $config["invoice_host"] . $endPoint,
      [
        "Content-Type: application/json",
        "Authentication: {$authen}",
        "taxcode: {$config["invoice_taxcode"]}",
      ],
      [
        "xmlData" => $xmlData,
        "pattern" => $config["invoice_pattern"],
        "serial"  => $config["invoice_serial"],
      ]
    );
  }

  /**
   * Lưu file pdf hóa đơn đã phát hành.
   */
  public function pdfInv(array $config, string $code) {
    $endPoint = "/api/tt78/business/invoicebykey";
    $authen = $this->getAuthen($config);

    $query = http_build_query([
      "pattern" => $config["invoice_pattern"],
      "serial"  => $config["invoice_serial"],
      "fkey" => $code,
    ]);

    $response = $this->callApi(
      $config["invoice_host"] . $endPoint . "?" . $query,
      [
        "Content-Type: application/json",
        "Authentication: {$authen}",
        "taxcode: {$config["invoice_taxcode"]}",
      ],
      [],
      "GET"
    );

    if (empty($response["data"]["success"])) {
      return NULL;
    }

    $pdf_binary = base64_decode($response["data"]["data"]);

    if ($pdf_binary === FALSE) {
      return NULL;
    }

    $directory = "public://invoices/" . date('Y/m');
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    $uri = "{$directory}/{$code}.pdf";
    $this->fileSystem->saveData($pdf_binary, $uri, FileSystemInterface::EXISTS_REPLACE);

    $file = File::create([
      'uri' => $uri,
      'status' => 1,
    ]);

    $file->save();

    if (empty($file)) {
      return NULL;
    }

    return $file;
  }

  /**
   * Phát hành hóa đơn loại thường.
   */
  public function issueNormalInv(array $config, array $data) {
    $endPoint = "/api/tt78/hoadon/xuathoadon";
    $authen = $this->getAuthen($config);
    $xmlData = $this->getData($data);

    return $this->callApi(
      $config["invoice_host"] . $endPoint,
      [
        "Content-Type: application/json",
        "Authentication: {$authen}",
        "taxcode: {$config["invoice_taxcode"]}",
      ],
      [
        "xmlData" => $xmlData,
        "pattern" => $config["invoice_pattern"],
        "serial"  => $config["invoice_serial"],
      ]
    );
  }

  /**
   * Phát hành hóa đơn loại máy tính tiền.
   */
  public function createInv(array $config, array $data) {
    $endPoint = "/api/tt78/hoadonmtt/xuathoadon";
    $authen = $this->getAuthen($config);

    if (empty($data["invoice_code"])) {
      throw new \RuntimeException(
        "Bad Request"
      );
    }

    $dataInv = $this->getData($data);

    $response = $this->callApi(
      $config["invoice_host"] . $endPoint,
      [
        "Content-Type: application/json",
        "Authentication: {$authen}",
        "taxcode: {$config["invoice_taxcode"]}",
      ],
      [
        "xmlData" => $dataInv,
        "pattern" => $config["invoice_pattern"],
        "serial"  => $config["invoice_serial"],
      ]
    );

    if ($response && !empty($response["data"]["success"])) {
      $arr_data = reset($response["data"]["data"]);
      $file = $this->pdfInv($config, $arr_data["fkey"]);
      $this->nodeInvoice($data["invoice_code"], $arr_data, $file);
    }

    return $response;
  }

  /**
   * Thay thế hóa đơn.
   */
  public function replaceInv(array $config, array $data): array {
    $endPoint = "/api/tt78/business/replaceInv";

    if (empty($data["invoice_code"])) {
      throw new \RuntimeException(
        "Bad Request"
      );
    }

    $invoice_storage = $this->entityTypeManager->getStorage('invoice');
    $invoice_id = $invoice_storage->getQuery()
      ->condition('label', $data["invoice_code"])
      ->accessCheck(FALSE)
      ->execute();

    if (empty($invoice_id)) {
      throw new \RuntimeException(
        "Not found invoice"
      );
    }

    $invoices = $invoice_storage->loadMultiple($invoice_id);
    /** @var \Drupal\e_invoice\Entity\Invoice $invoice */
    $invoice = reset($invoices);

    $dataInv = $this->getData($data, 'replace');

    $response = $this->callApi(
      $config["invoice_host"] . $endPoint,
      [
        "Content-Type: application/json",
        "Authorization: Bearer {$config["invoice_token"]}",
      ],
      [
        "SignType" => 2,
        "InvoiceData" => $dataInv,
        "PublishInvoiceData" => NULL,
      ]
    );

    if ($response && !empty($response["data"]["success"])) {
      $data_json = json_decode($response["data"]["publishInvoiceResult"], TRUE);
      $arr_data = reset($data_json);
      if (empty($arr_data["ErrorCode"]) && empty($arr_data["DescriptionErrorCode"])) {
        $file = $this->pdfInv($config, $arr_data["TransactionID"]);
        $this->nodeInvoice($data["invoice_code"], $arr_data, $file, 'update', $invoice);
      }
    }

    return $response;
  }

  /**
   * Hủy hóa đơn đã phát hành.
   */
  public function cancelIssuedInvoices(array $config, string $fkey) {
    $endPoint = "/api/tt78/business/cancelInv";
    $authen = $this->getAuthen($config);

    return $this->callApi(
      $config["invoice_host"] . $endPoint,
      [
        "Content-Type: application/json",
        "Authentication: {$authen}",
        "taxcode: {$config["invoice_taxcode"]}",
      ],
      [
        "pattern" => $config["invoice_pattern"],
        "serial"  => $config["invoice_serial"],
        "fkey" => $fkey,
      ]
    );
  }

  /**
   * Lấy danh sách hóa đơn phát hành.
   */
  public function listIssuedInv(array $config) {
    $endPoint = "/api/tt78/hoadon/invoicesbydate";
    $authen = $this->getAuthen($config);

    return $this->callApi(
      $config["invoice_host"] . $endPoint,
      [
        "Content-Type: application/json",
        "Authentication: {$authen}",
        "taxcode: {$config["invoice_taxcode"]}",
      ],
      [
        "pattern" => $config["invoice_pattern"],
        "serial"  => $config["invoice_serial"],
      ]
    );
  }

  /**
   * Lấy thông tin hóa đơn phát hành.
   */
  public function getIssuedInv(array $config, string $fkey) {
    $endPoint = "/api/tt78/business/invoiceinfo";
    $authen = $this->getAuthen($config);

    $query = http_build_query([
      "pattern" => $config["invoice_pattern"],
      "serial"  => $config["invoice_serial"],
      "fkey" => $fkey,
    ]);

    return $this->callApi(
      $config["invoice_host"] . $endPoint . "?" . $query,
      [
        "Content-Type: application/json",
        "Authentication: {$authen}",
        "taxcode: {$config["invoice_taxcode"]}",
      ],
      [],
      "GET"
    );
  }

}
