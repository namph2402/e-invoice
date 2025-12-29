<?php

namespace Drupal\e_invoice\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\e_invoice\Service\HandleInvoice;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 */
class HandleForm extends FormBase {

  /**
   * {@inheritDoc}
   */
  protected HandleInvoice $handleInvoice;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    HandleInvoice $handleInvoice,
  ) {
    $this->handleInvoice = $handleInvoice;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('e_invoice.handle_invoice'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'invoice_issue_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['e_invoice.issue'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $options = [];
    $invoice_config = $form_state->get('invoice_config');
    foreach ($invoice_config['invoice_templates'] as $key => $template) {
      $options[$key] = $template['pattern'] . ' | ' . $template['serial'];
    }

    $form['invoice_template'] = [
      '#type' => 'select',
      '#title' => $this->t('Invoice template'),
      '#options' => $options,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select template -'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Issue invoice'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $invoice_config = $form_state->get('invoice_config');
    $invoice_data = $form_state->get('invoice_data');

    $template_key = $form_state->getValue(key: 'invoice_template');
    $template = $invoice_config['invoice_templates'][$template_key];

    $payload = [
      'invoice_provider' => $invoice_config['invoice_provider'],
      'invoice_host' => $invoice_config['invoice_host'],
      'invoice_username' => $invoice_config['invoice_username'],
      'invoice_password' => $invoice_config['invoice_password'],
      'invoice_taxcode' => $invoice_config['invoice_taxcode'],
      'invoice_appid' => $invoice_config['invoice_appid'],
      'invoice_token' => $invoice_config['invoice_token'],
      'invoice_template' => $template,
    ];

    // Gọi service issue.
    if ($form_state->get('form_type') === 'issue') {
      $this->handleInvoice->issueInvoice($payload, $invoice_data);
    }
    elseif ($form_state->get('form_type') === 'replace') {
      $this->handleInvoice->replaceInvoice($payload, $invoice_data);
    }
    elseif ($form_state->get('form_type') === 'preview') {
      $this->handleInvoice->previewInvoice($payload, $invoice_data);
    }
    else {
      // Không biết loại form -> Lỗi.
    }

    // xử lý xong gọi form đi đâu ???
  }

}
