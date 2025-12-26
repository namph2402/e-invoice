<?php

namespace Drupal\e_invoice\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\e_invoice\InvoiceProvidersPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritDoc}
 *
 * @FieldWidget(
 *   id = "invoice_form_widget",
 *   label = @Translation("Invoice widget"),
 *   field_types = {
 *     "invoice_form"
 *   }
 * )
 */
class InvoiceFormWidget extends WidgetBase {

  /**
   * Constructs a new setting form object.
   */
  protected InvoiceProvidersPluginManager $providers;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, InvoiceProvidersPluginManager $providers) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings
    );
    $this->providers = $providers;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.invoice_providers'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $value = [];

    if (!empty($items[$delta]->value)) {
      $value = json_decode($items[$delta]->value, TRUE) ?? [];
    }

    $element['container'] = [
      '#type' => 'details',
      '#title' => $this->t('Invoice config'),
      '#open' => (!empty($value) && $value['enable']) ? TRUE : FALSE,
    ];

    $element['container']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use config'),
      '#default_value' => $value['enable'] ?? 0,
    ];

    $element['container']['invoice_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Invoice provider'),
      '#options' => $this->providers(),
      '#default_value' => $value['invoice_provider'] ?? '',
    ];

    $element['container']['invoice_host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invoice host'),
      '#default_value' => $value['invoice_host'] ?? '',
    ];

    $element['container']['invoice_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $value['invoice_username'] ?? '',
    ];

    $element['container']['invoice_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $value['invoice_password'] ?? '',
    ];

    $element['container']['invoice_taxcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tax code'),
      '#default_value' => $value['invoice_taxcode'] ?? '',
    ];

    $element['container']['invoice_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invoice pattern'),
      '#default_value' => $value['invoice_pattern'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name$="[invoice_provider]"]' => ['value' => 'megabiz'],
        ],
      ],
    ];

    $element['container']['invoice_serial'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invoice serial'),
      '#default_value' => $value['invoice_serial'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name$="[invoice_provider]"]' => ['value' => 'megabiz'],
        ],
      ],
    ];

    $element['container']['invoice_appid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invoice appid'),
      '#default_value' => $value['invoice_appid'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name$="[invoice_provider]"]' => ['value' => 'misa'],
        ],
      ],
    ];

    $element['container']['invoice_token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Invoice token'),
      '#default_value' => $value['invoice_token'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name$="[invoice_provider]"]' => ['value' => 'misa'],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      $value = [
        'value' => json_encode([
          'enable' => $value['container']['enable'],
          'invoice_provider' => $value['container']['invoice_provider'],
          'invoice_host' => $value['container']['invoice_host'],
          'invoice_username' => $value['container']['invoice_username'],
          'invoice_password' => $value['container']['invoice_password'],
          'invoice_taxcode' => $value['container']['invoice_taxcode'],
          'invoice_pattern' => $value['container']['invoice_pattern'],
          'invoice_serial' => $value['container']['invoice_serial'],
          'invoice_appid' => $value['container']['invoice_appid'],
          'invoice_token' => $value['container']['invoice_token'],
        ]),
      ];
    }
    return $values;
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
