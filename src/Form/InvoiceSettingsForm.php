<?php

namespace Drupal\e_invoice\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\e_invoice\InvoiceProvidersPluginManager;

/**
 * {@inheritdoc}
 */
class InvoiceSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected InvoiceProvidersPluginManager $providers,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.invoice_providers'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'e_invoice_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['e_invoice.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('e_invoice.settings');
    $required_password = empty($config->get('invoice_password')) ? TRUE : FALSE;

    // Global settings.
    $form['invoice_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Invoice provider'),
      '#options' => $this->providers(),
      '#default_value' => $config->get('invoice_provider'),
      '#required' => TRUE,
    ];

    $form['invoice_host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invoice host'),
      '#default_value' => $config->get('invoice_host') ?? '',
      '#required' => TRUE,
    ];

    $form['invoice_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $config->get('invoice_username') ?? '',
      '#required' => TRUE,
    ];

    $form['invoice_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#default_value' => $config->get('invoice_password') ?? '',
      '#required' => $required_password,
    ];

    $form['invoice_taxcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tax code'),
      '#default_value' => $config->get('invoice_taxcode') ?? '',
      '#required' => TRUE,
    ];

    $form['invoice_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invoice pattern'),
      '#default_value' => $config->get('invoice_pattern') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="invoice_provider"]' => ['value' => 'megabiz'],
        ],
      ],
    ];

    $form['invoice_serial'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invoice serial'),
      '#default_value' => $config->get('invoice_serial') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="invoice_provider"]' => ['value' => 'megabiz'],
        ],
      ],
    ];

    $form['invoice_appid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invoice appid'),
      '#default_value' => $config->get('invoice_appid') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="invoice_provider"]' => ['value' => 'misa'],
        ],
      ],
    ];

    $form['invoice_token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Invoice token'),
      '#default_value' => $config->get('invoice_token') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="invoice_provider"]' => ['value' => 'misa'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $data = $form_state->getValues();
    $config = $this->configFactory()->getEditable('e_invoice.settings');

    $config->set("invoice_provider", $data["invoice_provider"])
      ->set("invoice_host", $data["invoice_host"])
      ->set("invoice_username", $data["invoice_username"])
      ->set("invoice_taxcode", $data["invoice_taxcode"])
      ->set("invoice_pattern", $data["invoice_pattern"])
      ->set("invoice_serial", $data["invoice_serial"])
      ->set("invoice_appid", $data["invoice_appid"])
      ->set("invoice_token", $data["invoice_token"]);

    if (!empty($data["invoice_password"])) {
      $config->set("invoice_password", $data["invoice_password"]);
    }

    $config->save();
    parent::submitForm($form, form_state: $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function providers() {
    $definitions = $this->providers->getDefinitions();
    $provides = [];
    foreach ($definitions as $definition) {
      $provides[$definition['id']] = $definition['label'];
    }
    return $provides;
  }

}
