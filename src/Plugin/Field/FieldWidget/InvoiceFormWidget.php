<?php

namespace Drupal\e_invoice\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\e_invoice\InvoiceProvidersPluginManager;
use Drupal\Component\Utility\NestedArray;

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
      '#title' => $this->t('Provider'),
      '#options' => $this->providers(),
      '#default_value' => $value['invoice_provider'] ?? '',
    ];

    $element['container']['invoice_host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host url'),
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

    $element['container']['invoice_appid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App id'),
      '#default_value' => $value['invoice_appid'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name$="[invoice_provider]"]' => ['value' => 'misa'],
        ],
      ],
    ];

    $element['container']['invoice_token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Token'),
      '#default_value' => $value['invoice_token'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name$="[invoice_provider]"]' => ['value' => 'misa'],
        ],
      ],
    ];

    $templates = $value['invoice_templates'] ?? [];

    $form_values = $form_state->getValues();
    if (!empty($form_values)) {
      $field_name = $items->getName();
      $templates = $form_values[$field_name][$delta]['container']['invoice_templates'];
    }

    $element['container']['invoice_templates'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        $this->t('Pattern'),
        $this->t('Serial'),
        $this->t('Operations'),
      ],
      '#prefix' => '<div id="invoice-templates-wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($templates as $key => $row) {
      $element['container']['invoice_templates'][$key]['pattern'] = [
        '#type' => 'textfield',
        '#default_value' => $row['pattern'] ?? '',
        '#required' => TRUE,
      ];

      $element['container']['invoice_templates'][$key]['serial'] = [
        '#type' => 'textfield',
        '#default_value' => $row['serial'] ?? '',
        '#required' => TRUE,
      ];

      $element['container']['invoice_templates'][$key]['remove'] = [
        '#type' => 'submit',
        '#name' => 'remove_' . $key,
        '#value' => $this->t('Remove'),
        '#limit_validation_errors' => [],
        '#submit' => [[static::class, 'removeRow']],
        '#ajax' => [
          'callback' => [static::class, 'ajaxRefresh'],
          'wrapper' => 'invoice-templates-wrapper',
        ],
      ];
    }

    $element['container']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add row'),
      '#limit_validation_errors' => [],
      '#submit' => [[static::class, 'addRow']],
      '#ajax' => [
        'callback' => [static::class, 'ajaxRefresh'],
        'wrapper' => 'invoice-templates-wrapper',
      ],
    ];

    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      $templates = empty($value['container']['invoice_templates']) ? [] : $value['container']['invoice_templates'];
      $value = [
        'value' => json_encode([
          'enable' => $value['container']['enable'],
          'invoice_provider' => $value['container']['invoice_provider'],
          'invoice_host' => $value['container']['invoice_host'],
          'invoice_username' => $value['container']['invoice_username'],
          'invoice_password' => $value['container']['invoice_password'],
          'invoice_taxcode' => $value['container']['invoice_taxcode'],
          'invoice_appid' => $value['container']['invoice_appid'],
          'invoice_token' => $value['container']['invoice_token'],
          'invoice_templates' => array_values($templates),
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

  /**
   * {@inheritdoc}
   */
  public static function ajaxRefresh(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#array_parents'];
    array_pop($parents);

    if (!in_array(end($parents), ['container', 'invoice_templates'])) {
      array_pop($parents);
    }

    if (end($parents) === 'container') {
      $parents[] = 'invoice_templates';
    }
    
    return NestedArray::getValue($form, $parents);
  }

  /**
   * {@inheritdoc}
   */
  public static function addRow(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#parents'];
    array_pop($parents);
    $parents[] = 'invoice_templates';

    $templates = NestedArray::getValue($form_state->getUserInput(), $parents) ?? [];
    $templates[bin2hex(random_bytes(4))] = ['pattern' => '', 'serial' => ''];

    NestedArray::setValue($form_state->getUserInput(), $parents, $templates);
    $form_state->setValue($parents, $templates);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public static function removeRow(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#parents'];
    $row_key = $parents[count($parents) - 2];
    array_pop($parents);
    array_pop($parents);

    $user_input = $form_state->getUserInput();
    $templates = NestedArray::getValue($user_input, $parents) ?? [];
    unset($templates[$row_key]);

    NestedArray::setValue($user_input, $parents, $templates);
    $form_state->setUserInput($user_input);
    $form_state->setValue($parents, $templates);
    $form_state->setRebuild();
  }

}
