<?php

namespace Drupal\e_invoice\Service;

use Drupal\e_invoice\InvoiceProvidersPluginManager;

/**
 * Invoice get config invoice.
 */
class HandleInvoice {

  /**
   * {@inheritDoc}
   */
  protected InvoiceProvidersPluginManager $providers;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    InvoiceProvidersPluginManager $providers,
  ) {
    $this->providers = $providers;
  }

  /**
   * {@inheritdoc}
   */
  protected function getProvider(array $config): object|null {
    $provider_id = $config["invoice_provider"];

    if (empty($provider_id) || !$this->providers->hasDefinition($provider_id)) {
      return NULL;
    }

    return $this->providers->createInstance($provider_id);
  }

  /**
   * Lấy token.
   */
  public function getToken(array $config): array {
    if (!$provider = $this->getProvider($config)) {
      return [
        "success" => FALSE
      ];
    }

    $organizationData = $subscribersID = '';

    $token = $provider->token($config);
    if (empty($token["success"]) || $token["HttpCode"] !== 200) {
      return [
        "success" => FALSE
      ];
    }

    $secure = $provider->secureToken($config);
    if (empty($secure["Success"]) || $secure["HttpCode"] != 200) {
      return [
        "success" => FALSE
      ];
    }

    $jwt = $provider->jwtToken($config, $secure["Data"]);
    if (empty($jwt["Success"]) || $jwt["HttpCode"] != 200) {
      return [
        "success" => FALSE
      ];
    }

    if (!empty($config["invoice_appurl"])) {
      $subscribers = $provider->subscribers($config);
      if (empty($subscribers["Success"]) || $subscribers["HttpCode"] != 200) {
        return [
          "success" => FALSE
        ];
      }

      $subscribersID = $subscribers["Data"]["Id"];

      $organization = $provider->organization($config, $jwt["Data"], $subscribersID);
      if (empty($organization["Success"]) || $organization["HttpCode"] != 200) {
        return [
          "success" => FALSE
        ];
      }

      $organizationData = reset($organization["Data"]);
    }

    return [
      "success" => TRUE,
      "token" => $token["data"] ?? '',
      "jwtToken" => $jwt["Data"]["AccessToken"] ?? '',
      "subscribers" => $subscribersID,
      "organization" => $organizationData,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function issueInvoice(array $config, array $data = []): mixed {
    if ($provider = $this->getProvider($config)) {
      return $provider->issue($config, $data);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function replaceInvoice(array $config, array $data = []): mixed {
    if ($provider = $this->getProvider($config)) {
      return $provider->replace($config, $data);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function previewInvoice(array $config, array $data = []): mixed {
    if ($provider = $this->getProvider($config)) {
      return $provider->preview($config, $data);
    }
    return NULL;
  }

}
