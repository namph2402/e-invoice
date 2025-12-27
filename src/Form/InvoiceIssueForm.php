<?php

namespace Drupal\e_invoice\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\e_invoice\Service\HandleInvoice;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 */
class InvoiceIssueForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  protected array $invoiceConfig = [];

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
  public function buildForm(array $form, FormStateInterface $form_state, array $invoice_config = []) {
    $this->invoiceConfig = $invoice_config;

    $options = [];
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
    $template_key = $form_state->getValue('invoice_template');
    $template = $this->invoiceConfig['invoice_templates'][$template_key];

    $payload = [
      'invoice_provider' => $this->invoiceConfig['invoice_provider'],
      'invoice_host' => $this->invoiceConfig['invoice_host'],
      'invoice_username' => $this->invoiceConfig['invoice_username'],
      'invoice_password' => $this->invoiceConfig['invoice_password'],
      'invoice_taxcode' => $this->invoiceConfig['invoice_taxcode'],
      'invoice_appid' => $this->invoiceConfig['invoice_appid'],
      'invoice_token' => $this->invoiceConfig['invoice_token'],
      'invoice_emplate' => $template,
    ];

    // Gọi service issue
    $this->handleInvoice->issueInvoice($payload);
  }

}
