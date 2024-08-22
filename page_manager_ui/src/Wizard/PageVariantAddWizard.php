<?php

namespace Drupal\page_manager_ui\Wizard;

use Drupal\Core\Display\ContextAwareVariantInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ctools\Plugin\PluginWizardInterface;
use Drupal\ctools\Wizard\EntityFormWizardBase;
use Drupal\page_manager_ui\Access\PageManagerPluginAccess;
use Drupal\page_manager_ui\Form\AddVariantContextsForm;
use Drupal\page_manager_ui\Form\AddVariantSelectionForm;
use Drupal\page_manager_ui\Form\PageVariantAddForm;
use Drupal\page_manager_ui\Form\PageVariantConfigureForm;

/**
 * Add Wizard for Page Variants.
 */
class PageVariantAddWizard extends EntityFormWizardBase {

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return 'page_variant';
  }

  /**
   * {@inheritdoc}
   */
  public function exists() {
    return '\Drupal\page_manager\Entity\PageVariant::load';
  }

  /**
   * {@inheritdoc}
   */
  public function getWizardLabel() {
    return $this->t('Page Variant');
  }

  /**
   * {@inheritdoc}
   */
  public function getMachineLabel() {
    return $this->t('Label');
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    return 'entity.page_variant.add_step_form';
  }

  /**
   * {@inheritdoc}
   */
  public function initValues() {
    $cached_values = parent::initValues();
    $cached_values['access'] = new PageManagerPluginAccess();
    return $cached_values;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations($cached_values) {
    $operations = [];
    $operations['type'] = [
      'title' => $this->t('Page variant type'),
      'form' => PageVariantAddForm::class,
    ];
    $operations['contexts'] = [
      'title' => $this->t('Contexts'),
      'form' => AddVariantContextsForm::class,
    ];
    $operations['selection'] = [
      'title' => $this->t('Selection criteria'),
      'form' => AddVariantSelectionForm::class,
    ];
    $operations['configure'] = [
      'title' => $this->t('Configure variant'),
      'form' => PageVariantConfigureForm::class,
    ];

    // Hide any optional steps that aren't selected.
    $optional_steps = ['selection', 'contexts'];
    foreach ($optional_steps as $step_name) {
      if (isset($cached_values['wizard_options']) && empty($cached_values['wizard_options'][$step_name])) {
        unset($operations[$step_name]);
      }
    }

    // Add any wizard operations from the plugin itself.
    if (!empty($cached_values['page_variant']) && !empty($cached_values['variant_plugin_id'])) {
      /** @var \Drupal\page_manager\PageVariantInterface $page_variant */
      $page_variant = $cached_values['page_variant'];
      $variant_plugin = $page_variant->getVariantPlugin();
      if ($variant_plugin instanceof PluginWizardInterface) {
        if ($variant_plugin instanceof ContextAwareVariantInterface) {
          $variant_plugin->setContexts($page_variant->getContexts());
        }
        $cached_values['plugin'] = $variant_plugin;
        foreach ($variant_plugin->getWizardOperations($cached_values) as $name => $operation) {
          $operation['values']['plugin'] = $variant_plugin;
          $operations[$name] = $operation;
        }
      }
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  protected function customizeForm(array $form, FormStateInterface $form_state) {
    $form = parent::customizeForm($form, $form_state);

    // We set the variant id as part of form submission.
    if ($this->step == 'type' && isset($form['name']['id'])) {
      unset($form['name']['id']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $page = NULL) {
    // @todo Change the autogenerated stub.
    $form = parent::buildForm($form, $form_state);

    // Get the page tempstore so we can modify the unsaved page.
    if (!isset($cached_values['page']) || !$cached_values['page']->id()) {
      $cached_values = $form_state->getTemporaryValue('wizard');
      $page_tempstore = $this->tempstore->get('page_manager.page')->get($page);
      $cached_values['page'] = $page_tempstore['page'];
      $form_state->setTemporaryValue('wizard', $cached_values);
    }

    // Hide form elements that are not useful during the add wizard.
    if ($this->step == 'configure') {
      $form['page_variant_label']['#type'] = 'value';
      unset($form['delete']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextParameters($cached_values) {
    $parameters = parent::getNextParameters($cached_values);

    // Add the page to the url parameters.
    $parameters['page'] = $cached_values['page']->id();
    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousParameters($cached_values) {
    $parameters = parent::getPreviousParameters($cached_values);

    // Add the page to the url parameters.
    $parameters['page'] = $cached_values['page']->id();
    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function finish(array &$form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');

    // Add the variant to the parent page tempstore.
    $page_tempstore = $this->tempstore->get('page_manager.page')->get($cached_values['page']->id());
    $page_tempstore['page']->addVariant($cached_values['page_variant']);
    $this->tempstore->get('page_manager.page')->set($cached_values['page']->id(), $page_tempstore);

    $variant_plugin = $cached_values['page_variant']->getVariantPlugin();
    $this->messenger()->addMessage($this->t('The %label @entity_type has been added to the page, but has not been saved. Please save the page to store changes.', [
      '%label' => $cached_values['page_variant']->label(),
      '@entity_type' => $variant_plugin->adminLabel(),
    ]));

    $form_state->setRedirectUrl(new Url('entity.page.edit_form', [
      'machine_name' => $cached_values['page']->id(),
      'step' => 'general',
    ]));
  }

}
