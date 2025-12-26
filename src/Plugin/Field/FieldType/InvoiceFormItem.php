<?php

namespace Drupal\e_invoice\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * {@inheritDoc}
 *
 * @FieldType(
 *   id = "invoice_form",
 *   label = @Translation("Invoice config"),
 *   description = @Translation("Field to store invoice config."),
 *   default_widget = "invoice_form_widget",
 *   default_formatter = "invoice_form_formatter"
 * )
 */
class InvoiceFormItem extends FieldItemBase {

  /**
   * {@inheritDoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('JSON value'));

    return $properties;
  }

  /**
   * {@inheritDoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'text',
          'size' => 'big',
        ],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function isEmpty() {
    return empty($this->value);
  }

}
