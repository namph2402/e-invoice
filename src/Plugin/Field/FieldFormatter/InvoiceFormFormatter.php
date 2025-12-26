<?php

namespace Drupal\e_invoice\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * {@inheritDoc}
 *
 * @FieldFormatter(
 *   id = "invoice_form_formatter",
 *   label = @Translation("Invoice formatter"),
 *   field_types = {
 *     "invoice_form"
 *   }
 * )
 */
class InvoiceFormFormatter extends FormatterBase {

  /**
   * {@inheritDoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $data = json_decode($item->value, TRUE);
      if (!$data || empty($data['enable'])) {
        continue;
      }

      $elements[$delta] = [
        '#theme' => 'item_list',
        '#items' => [
          $data['invoice_provider'],
        ],
      ];
    }

    return $elements;
  }

}
