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

    $form['invoice_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Provider'),
      '#options' => $this->providers(),
      '#default_value' => $config->get('invoice_provider'),
      '#required' => TRUE,
    ];

    $form['invoice_host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host url'),
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

    $form['invoice_appid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App id'),
      '#default_value' => $config->get('invoice_appid') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="invoice_provider"]' => ['value' => 'misa'],
        ],
      ],
    ];

    $form['invoice_token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Token'),
      '#default_value' => $config->get('invoice_token') ?? '',
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
        $this->t('Pattern'),
        $this->t('Serial'),
        $this->t('Operations'),
      ],
      '#prefix' => '<div id="invoice-templates-wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($templates as $key => $row) {
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $templates = [];
    $data = $form_state->getValues();
    $config = $this->configFactory()->getEditable('e_invoice.settings');

    foreach ($data['invoice_templates'] as $row) {
      if (!empty($row['pattern']) && !empty($row['serial'])) {
        $templates[] = [
          'pattern' => trim($row['pattern']),
          'serial' => trim($row['serial']),
        ];
      }
    }

    $config->set("invoice_provider", $data["invoice_provider"])
      ->set("invoice_host", $data["invoice_host"])
      ->set("invoice_username", $data["invoice_username"])
      ->set("invoice_taxcode", $data["invoice_taxcode"])
      ->set("invoice_appid", $data["invoice_appid"])
      ->set("invoice_token", $data["invoice_token"])
      ->set('invoice_templates', $templates);

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
