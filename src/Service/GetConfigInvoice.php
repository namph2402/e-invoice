<?php

namespace Drupal\e_invoice\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Invoice get config invoice.
 */
class GetConfigInvoice {

  /**
   * {@inheritDoc}
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(array $data = []): array {
    $config = $this->configFactory->get('e_invoice.settings');

    return [
      'invoice_provider' => $config->get('invoice_provider') ?? '',
      'invoice_host' => $config->get('invoice_host') ?? '',
      'invoice_username' => $config->get('invoice_username') ?? '',
      'invoice_password' => $config->get('invoice_password') ?? '',
      'invoice_taxcode' => $config->get('invoice_taxcode') ?? '',
      'invoice_appid' => $config->get('invoice_appid') ?? '',
      'invoice_token' => $config->get('invoice_token') ?? '',
      'invoice_templates' => $config->get('invoice_templates') ?? [],
    ];
  }

}
