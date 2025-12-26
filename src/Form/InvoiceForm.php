<?php

declare(strict_types=1);

namespace Drupal\e_invoice\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the invoice entity edit forms.
 */
final class InvoiceForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $label = ['%label' => $this->entity->toLink()->toString()];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New invoice %label has been created.', $label));
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The invoice %label has been updated.', $label));
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    $form_state->setRedirect('entity.invoice.collection');

    return $result;
  }

}
