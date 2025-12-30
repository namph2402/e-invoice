<?php

namespace Drupal\e_invoice\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\e_invoice\InvoiceProvidersPluginManager;
use Drupal\e_invoice\Service\HandleInvoice;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * {@inheritdoc}
 */
class InvoiceSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected InvoiceProvidersPluginManager $providers,
    protected HandleInvoice $handleInvoice,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.invoice_providers'),
      $container->get('e_invoice.handle_invoice'),
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

    $form['invoice_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Provider'),
      '#options' => $this->providers(),
      '#default_value' => $config->get('invoice_provider'),
      '#required' => TRUE,
    ];

    $form['invoice_host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host'),
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
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $config->get('invoice_password') ?? '',
      '#required' => TRUE,
    ];

    $form['invoice_taxcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tax code'),
      '#default_value' => $config->get('invoice_taxcode') ?? '',
      '#required' => TRUE,
    ];

    $form['invoice_appid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App ID'),
      '#default_value' => $config->get('invoice_appid') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="invoice_provider"]' => ['value' => 'misa'],
        ],
      ],
    ];

    $form['invoice_appurl'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App URL'),
      '#default_value' => $config->get('invoice_appurl') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="invoice_provider"]' => ['value' => 'misa'],
        ],
      ],
    ];

    $form['invoice_client'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client'),
      '#default_value' => $config->get('invoice_client') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="invoice_provider"]' => ['value' => 'misa'],
        ],
      ],
    ];

    $templates = $form_state->get('invoice_templates');
    if ($templates === NULL) {
      $templates = $config->get('invoice_templates') ?? [];
      $form_state->set('invoice_templates', $templates);
    }

    $form['invoice_templates'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#title' => $this->t('Templates'),
      '#header' => [
        $this->t('Name'),
        $this->t('Pattern'),
        $this->t('Serial'),
        $this->t('Operations'),
      ],
      '#prefix' => '<div id="invoice-templates-wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($templates as $key => $row) {
      $form['invoice_templates'][$key]['name'] = [
        '#type' => 'textfield',
        '#default_value' => $row['name'] ?? '',
        '#required' => TRUE,
      ];

      $form['invoice_templates'][$key]['pattern'] = [
        '#type' => 'textfield',
        '#default_value' => $row['pattern'] ?? '',
        '#required' => TRUE,
      ];

      $form['invoice_templates'][$key]['serial'] = [
        '#type' => 'textfield',
        '#default_value' => $row['serial'] ?? '',
        '#required' => TRUE,
      ];

      $form['invoice_templates'][$key]['remove'] = [
        '#type' => 'submit',
        '#name' => 'remove_' . $key,
        '#value' => $this->t('Remove'),
        '#limit_validation_errors' => [],
        '#submit' => ['::removeRow'],
        '#ajax' => [
          'callback' => '::ajaxRefresh',
          'wrapper' => 'invoice-templates-wrapper',
        ],
      ];
    }

    $form['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add row'),
      '#limit_validation_errors' => [],
      '#submit' => ['::addRow'],
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => 'invoice-templates-wrapper',
      ],
    ];

    $form['invoice_expiration'] = [
      '#type' => 'date',
      '#title' => $this->t('Expiration'),
      '#default_value' => $config->get('invoice_expiration'),
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
    $templates = $data_token = [];
    $data = $form_state->getValues();
    $config = $this->configFactory()->getEditable('e_invoice.settings');

    foreach ($data['invoice_templates'] as $row) {
      if (!empty($row['name']) && !empty($row['pattern']) && !empty($row['serial'])) {
        $templates[] = [
          'name' => trim($row['name']),
          'pattern' => trim($row['pattern']),
          'serial' => trim($row['serial']),
        ];
      }
    }

    if (!empty($data["invoice_appid"]) && !empty($data["invoice_client"])) {
      $data_token = $this->handleInvoice->getToken($data);
    }

    $config->set("invoice_provider", $data["invoice_provider"])
      ->set("invoice_host", $data["invoice_host"])
      ->set("invoice_username", $data["invoice_username"])
      ->set("invoice_password", $data["invoice_password"])
      ->set("invoice_taxcode", $data["invoice_taxcode"])
      ->set("invoice_appid", $data["invoice_appid"])
      ->set("invoice_appurl", $data["invoice_appurl"])
      ->set("invoice_client", $data["invoice_client"])
      ->set('invoice_templates', $templates);

    if (!empty($data_token["success"])) {
      $date = new DrupalDateTime('now');
      $date->modify('+7 days');

      $config->set("invoice_token", $data_token["token"]);
      $config->set("invoice_jwt_token", $data_token["jwt_token"]);
      $config->set("invoice_subscribers", $data_token["subscribers"] ?? '');
      $config->set("invoice_organization", $data_token["organization"]["Id"] ?? '');
      $config->set("invoice_expiration", $date->format('Y-m-d'));
      \Drupal::messenger()->addStatus($this->t('Get token successfully.'));
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

  /**
   * {@inheritdoc}
   */
  public function ajaxRefresh(array &$form, FormStateInterface $form_state) {
    return $form['invoice_templates'];
  }

  /**
   * {@inheritdoc}
   */
  public function addRow(array &$form, FormStateInterface $form_state) {
    $templates = $form_state->get('invoice_templates') ?? [];
    $key = bin2hex(random_bytes(4));
    $templates[$key] = [
      'name' => '',
      'pattern' => '',
      'serial' => '',
    ];

    $form_state->set('invoice_templates', $templates);
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function removeRow(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $templates = $form_state->get('invoice_templates') ?? [];
    unset($templates[str_replace('remove_', '', $trigger['#name'])]);

    $form_state->set('invoice_templates', $templates);
    $form_state->setRebuild(TRUE);
  }

}
