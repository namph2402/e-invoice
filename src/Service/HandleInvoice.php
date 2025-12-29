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
    $provider_id = $config['invoice_provider'];

    if (empty($provider_id)) {
      return NULL;
    }

    if (!$this->providers->hasDefinition($provider_id)) {
      return NULL;
    }

    return $this->providers->createInstance($provider_id);
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
