<?php

namespace Drupal\e_invoice\Plugin\Providers;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\e_invoice\Service\GetNumberToWords;
use Drupal\e_invoice\InvoiceProvidersAttribute;
use Drupal\e_invoice\InvoiceProvidersInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

/**
 * Provider for invoicing integration using Misa.
 */
#[InvoiceProvidersAttribute(
  id: "misa",
  label: new TranslatableMarkup("Misa"),
)]

class MisaProvider extends PluginBase implements InvoiceProvidersInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  protected UuidInterface $uuid;

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
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    UuidInterface $uuid,
    EntityTypeManagerInterface $entityTypeManager,
    GetNumberToWords $getNumberToWords,
    FileSystemInterface $fileSystem,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->uuid = $uuid;
    $this->entityTypeManager = $entityTypeManager;
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
      $container->get('uuid'),
      $container->get('entity_type.manager'),
      $container->get("e_invoice.get_number_to_words"),
      $container->get('file_system'),
    );
  }

  /**
   * {@inheritDoc}
   */
  private function getData(array $data, array $replace = [], string $type = 'create') {

    $invoice_amount = $data["invoice_amount"] ?? 0;
    $invoice_amount_words = $this->getNumberToWords->handle($invoice_amount);

    $base = [
      "RefID" => $this->uuid->generate(),
      "InvSeries" => "1C25TAA",
      "InvoiceName" => "Hóa đơn giá trị gia tăng",
      "IsInvoiceCalculatingMachine" => TRUE,
      "InvDate" => date('Y-m-d'),
      "CurrencyCode" => $data["invoice_currency_code"] ?? "VND",
      "ExchangeRate" => 1,
      "PaymentMethodName" => $data["invoice_payment"] ?? NULL,
      "BuyerLegalName" => $data["invoice_buyer_name"] ?? NULL,
      "BuyerTaxCode" => $data["invoice_customer_taxcode"] ?? NULL,
      "BuyerAddress" => $data["invoice_customer_address"] ?? NULL,
      "BuyerCode" => $data["invoice_customer_code"] ?? NULL,
      "BuyerFullName" => $data["invoice_customer_name"] ?? NULL,
      "BuyerPhoneNumber" => $data["invoice_customer_phone"] ?? NULL,
      "BuyerEmail" => $data["invoice_customer_email"] ?? NULL,
      "ContactName" => $data["invoice_customer_name"] ?? NULL,
      "TotalSaleAmountOC" => $data["invoice_total"] ?? 0,
      "TotalSaleAmount" => $data["invoice_total"] ?? 0,
      "TotalDiscountAmountOC" => $data["invoice_discount_amount"] ?? 0,
      "TotalDiscountAmount" => $data["invoice_discount_amount"] ?? 0,
      "TotalAmountWithoutVATOC" => $data["invoice_total"] ?? 0,
      "TotalAmountWithoutVAT" => $data["invoice_total"] ?? 0,
      "TotalVATAmountOC" => $data["invoice_vat_amount"] ?? 0,
      "TotalVATAmount" => $data["invoice_vat_amount"] ?? 0,
      "TotalAmountOC" => $invoice_amount,
      "TotalAmount" => $invoice_amount,
      "TotalAmountInWords" => $invoice_amount_words,
      "IsTaxReduction43" => FALSE,
      "OriginalInvoiceDetail" => $this->buildProducts($data['products']),
      "TaxRateInfo" => [
          [
            "VATRateName" => $data["invoice_vat"] . '%',
            "AmountWithoutVATOC" => $data["invoice_vat_amount"],
            "VATAmountOC" => $data["invoice_vat_amount"],
          ],
      ],
    ];

    if ($type == 'replace') {
      $base += $this->buildReplace($replace);
    }

    return [$base];
  }

  /**
   * {@inheritDoc}
   */
  private function buildProducts(array $products): array {
    /*
     * Dữ liệu chung về hàng hóa:
     *
     * - ItemType (int): Loại hàng hóa.
     *     1: Hàng hóa thường
     *     2: Khuyến mại
     *     3: Dòng hàng Chiết khấu thương mại
     *     4: Ghi chú/diễn giải
     *     5: Hàng hóa đặc thù vận tải
     * - SortOrder (int): STT của dòng hàng (bắt đầu từ 1)
     *      (NULL với ItemType = 3 hoặc ItemType = 4)
     * - LineNumber (int): Vị trí của dòng hàng (bắt đầu từ 1)
     * - ItemCode (string):Mã hàng hóa
     * - ItemName (string): Tên hàng hóa
     * - UnitName (string): Đơn vị tính
     * - Quantity (decimal): Số lượng
     * - UnitPrice (decimal): Đơn giá trước thuế, trước chiết khấu
     * - AmountOC (decimal): Thành tiền trước thuế, trước chiết khấu - nguyên tệ
     *      (Quantity * UnitPrice)
     * - Amount (decimal): Thành tiền trước thuế, trước chiết khấu - quy đổi
     * - DiscountRate (decimal): Tỷ lệ chiết khấu
     * - DiscountAmountOC (decimal): Tiền chiết khấu - nguyên tệ
     *      (AmountOC * DiscountRate / 100)
     * - DiscountAmount (decimal): Tiền chiết khấu - quy đổi
     * - AmountWithoutVATOC (decimal): Thành tiền sau chiết khấu - nguyên tệ
     *      (AmountOC - DiscountAmountOC)
     * - AmountWithoutVAT (decimal) :Thành tiền sau chiết khấu - quy đổi
     * - VATRateName (string): Loại thuế suất
     *      KCT: chịu thuế
     *      KKKNT: kê khai nộp thuế
     *      0%: thuế suất 0%
     *      5%: thuế suất 5%
     *      8%: thuế suất 8%
     *      10%: thuế suất 10%
     *      x%: loại thuế suất khác
     * - VATAmountOC (decimal): Tiền thuế - nguyên tệ
     *      (AmountWithoutVATOC * VATRate / 100)
     * - VATAmount (decimal): Tiền thuế - quy đổi
     */

    $result = [];
    $index = 1;

    foreach ($products as $p) {
      $type = (int) ($p['type'] ?? 1);

      $result[] = [
        'ItemType' => $type,
        'LineNumber' => $index,
        'SortOrder' => in_array($type, [1, 2], TRUE) ? $index : NULL,
        'ItemCode' => $p['code'] ?? NULL,
        'ItemName' => $p['name'] ?? NULL,
        'UnitName' => $p['unit'] ?? NULL,
        'Quantity' => (float) ($p['quantity'] ?? 0),
        'UnitPrice' => (float) ($p['price'] ?? 0),
        'DiscountRate' => (int) ($p['discount'] ?? 0),
        'DiscountAmountOC' => (float) ($p['discount_amount'] ?? 0),
        'DiscountAmount' => (float) ($p['discount_amount'] ?? 0),
        'AmountOC' => (float) ($p['total'] ?? 0),
        'Amount' => (float) ($p['total'] ?? 0),
        'AmountWithoutVATOC' => (float) ($p['amount'] ?? 0),
        'VATRateName' => isset($p['vat']) ? $p['vat'] . '%' : '0%',
        'VATAmountOC' => (float) ($p['vat_amount'] ?? 0),
        'VATAmount' => (float) ($p['vat_amount'] ?? 0),
      ];

      $index++;
    }

    return $result;
  }

  /**
   * {@inheritDoc}
   */
  private function buildReplace(array $replace): array {
    return [
      "ReferenceType" => 1,
      "OrgInvoiceType" => 1,
      "OrgInvTemplateNo" => $replace["inv_template_no"] ?? NULL,
      "OrgInvSeries" => $replace["inv_template_series"] ?? NULL,
      "OrgInvNo" => $replace["inv_no"] ?? NULL,
      "OrgInvDate" => date('Y-m-d'),
    ];
  }

  /**
   * Tạo hóa đơn.
   */
  private function nodeInvoice(string $code, array $data = [], $pdf = NULL, $type = 'add', $invoice_entity = NULL) {
    $invoice_storage = $this->entityTypeManager->getStorage('invoice');

    $attribute = [
      'label' => $code,
      'invoice_key' => $data['RefID'],
      'invoice_transaction' => $data['TransactionID'],
      'invoice_template' => $data['InvTemplateNo'],
      'invoice_series' => $data['InvSeries'],
      'invoice_no' => $data['InvNo'],
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
  private function callApi(string $url, array $headers, array $payload = [], string $method = 'POST', int $timeout = 30): array {
    try {
      $options = [
        'headers' => $headers,
        'json' => $payload,
        'timeout' => $timeout,
      ];
      
      $response = $this->client->request($method, $url, $options);
      $data = json_decode($response->getBody()->getContents(), TRUE);
      $data['HttpCode'] = $response->getStatusCode();
      return $data;
    }
    catch (\Throwable $e) {
      throw new \RuntimeException(
        'API error: ' . $e->getMessage(),
        $e->getCode(),
        $e
      );
    }
  }

  /**
   * Lấy token.
   */
  public function token(array $config): array {
    $endPoint = "/api/integration/auth/token";

    return $this->callApi(
      $config["invoice_host"] . $endPoint,
      [
        "Content-Type" => "application/json",
      ],
      [
        "appid" => $config["invoice_appid"],
        "taxcode" => $config["invoice_taxcode"],
        "username" => $config["invoice_username"],
        "password" => $config["invoice_password"],
      ]
    );
  }

  /**
   * Lấy secure token.
   */
  public function secureToken(array $config): array {
    $endPoint = "/api2/validateuser";

    return $this->callApi(
      $config["invoice_host"] . $endPoint,
      [
        "Content-Type" => "application/json",
        "appid" => $config["invoice_appid"],
        "companytaxcode" => $config["invoice_taxcode"],
        "username" => $config["invoice_username"],
      ],
      [
        "password" => $config["invoice_password"],
      ]
    );
  }

  /**
   * Lấy jwt token.
   */
  public function jwtToken(array $config, string $secureToken): array {
    $endPoint = "/api2/auth/jwttoken";
    $secure = substr(
      $secureToken, 
      strpos($secureToken, ';') + 1
    );

    return $this->callApi(
      $config["invoice_host"] . $endPoint,
      [
        "Content-Type" => "application/json",
        "appid" => $config["invoice_appid"],
        "companytaxcode" => $config["invoice_taxcode"],
        "username" => $config["invoice_username"],
        "securetoken" => $secure,
      ],
      []
    );
  }

  /**
   * Lấy subscribers.
   */
  public function subscribers(array $config): array {
    $endPoint = "/inbot/api/subscribers/code/";
    return $this->callApi(
      $config["invoice_appurl"] . $endPoint . $config["invoice_taxcode"],
      [
        "Content-Type" => "application/json",
        "ClientId" => $config["invoice_client"],
      ],
      [],
      "GET"
    );
  }

  /**
   * Lấy organization.
   */
  public function organization(array $config, array $jwt, string $subscribers): array {
    $endPoint = "/inbot/api/{$subscribers}/organizations";
    return $this->callApi(
      $config["invoice_appurl"] . $endPoint,
      [
        "Content-Type" => "application/json",
        "ClientId" => $config["invoice_client"],
        "Authorization" => "Bearer {$jwt["AccessToken"]}",
      ],
      [],
      "GET"
    );
  }

  /**
   * Lấy trạng thái hóa đơn đã phát hành.
   */
  public function getStatusInv(array $config, string $code) {
    $endPoint = "/api/integration/invoice/status";

    return $this->callApi(
      $config["invoice_host"] . $endPoint,
      [
        "Content-Type: application/json",
        "Authorization: Bearer {$config["invoice_token"]}",
      ],
      [
        $code,
      ],
    );
  }

  /**
   * Xem pdf nháp.
   */
  public function pdfDraftInv(array $config, array $data): array {
    $endPoint = "/api/integration/invoice/unpublishview";
    $dataInv = $this->getData($data);

    return $this->callApi(
      $config["invoice_host"] . $endPoint,
      [
        "Content-Type: application/json",
        "Authorization: Bearer {$config["invoice_token"]}",
      ],
      reset($dataInv),
    );
  }

  /**
   * Lưu file pdf hóa đơn đã phát hành.
   */
  public function pdfInv(array $config, string $code) {
    $endPoint = "/api/integration/invoice/Download";

    $query = http_build_query([
      "invoiceWithCode" => "true",
      "downloadDataType" => 'pdf',
    ]);

    $response = $this->callApi(
      $config["invoice_host"] . $endPoint . "?" . $query,
      [
        "Content-Type: application/json",
        "Authorization: Bearer {$config["invoice_token"]}",
      ],
      [
        $code,
      ],
    );

    if (empty($response["data"]["success"])) {
      return NULL;
    }

    $arr_data = json_decode($response["data"]["data"], TRUE);
    $data = reset($arr_data);

    $pdf_binary = base64_decode($data["Data"]);

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
   * Phát hành hóa đơn.
   */
  public function createInv(array $config, array $data): array {
    $endPoint = "/api/integration/invoice";

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
        $this->nodeInvoice($data["invoice_code"], $arr_data, $file);
      }
    }

    return $response;
  }

  /**
   * Thay thế hóa đơn.
   */
  public function replaceInv(array $config, array $data): array {
    $endPoint = "/api/integration/invoice";

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

    $series = $invoice->get("invoice_series")->value ? substr($invoice->get("invoice_series")->value, 1) : "";

    $data_replace = [
      "inv_template_no" => $invoice->get("invoice_template")->value ?? "1",
      "inv_template_series" => $series,
      "inv_no" => $invoice->get("invoice_no")->value ?? "",
    ];

    $dataInv = $this->getData($data, $data_replace, 'replace');

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

}
