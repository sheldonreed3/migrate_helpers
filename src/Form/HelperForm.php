<?php

namespace Drupal\migrate_helpers\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class HelperForm.
 *
 * @package Drupal\migrate_helpers\Form
 */
class HelperForm extends FormBase {

  /**
   * Helper class definition.
   *
   * @var string
   */
  protected $helperClass = '\Drupal\migrate_helpers\MigrateHelpers';

  /**
   * Helper function to change camelcase to words.
   *
   * @param string $string
   *   Camel cased string.
   *
   * @return string
   *   Word string.
   */
  protected function camelToWords($string) {
    $regex = '/(?<=[a-z])(?=[A-Z])/x';
    $output = preg_split($regex, $string);
    return implode($output, ' ');
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'migrate_helpers.admin_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $helper_methods = get_class_methods($this->helperClass);
    $options = [];
    foreach ($helper_methods as $method) {
      $options[$method] = ucfirst(strtolower($this->camelToWords($method)));
    }

    $form['helper_function'] = [
      '#type' => 'select',
      '#title' => $this->t('Helper Method'),
      '#desciption' => $this->t('Select the helper method you would like to use. See \Drupal\migrate_helpers\MigrateHelpers for more information'),
      '#options' => $options,
    ];
    $form['migration_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Migration'),
      '#description' => $this->t('The machine name for the migration'),
    ];
    $form['process_row_fields'] = [
      '#type' => 'fieldset',
      '#states' => [
        'invisible' => [
          'select[id="edit-helper-function"]' => ['!value' => 'processRow'],
        ],
      ],
    ];
    $form['process_row_fields']['limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('Maximum number of rows to process'),
    ];
    $form['process_row_fields']['process_row'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Process row'),
      '#description' => $this->t('Optional selection to process the row ( gets destination info ).'),
      '#default_value' => 1,
    ];
    $form['process_row_fields']['import_row'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Import row'),
      '#description' => $this->t('Optional selection to import the row ( updates migration status and saves changes ).'),
      '#default_value' => 0,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run method'),
    ];

    return $form;
  }

  public function sqlRows($rows) {
    $fields = array_keys(reset($rows)->getDestination());
    $fields[] = 'revision_id';
    $query = \Drupal::database()->insert('content_moderation_state_field_revision')
      ->fields($fields);
    foreach ($rows as $row) {
      $dest = $row->getDestination();
      $dest['revision_id'] = $dest['id'];
      $query->values($dest);
    }
    $query->execute();
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($method = $form_state->getValue('helper_function')) {
      $callable = $this->helperClass . '::' . $method;
      $rows = $callable($form_state->getValue('migration_id'), $form_state->getValue('limit'), $form_state->getValue('process_row'), $form_state->getValue('import_row'));

      if ($form_state->getValue('sql_insert') === 1) {
        $this->sqlRows($rows);
      }
      ksm($rows);
    }
    $form_state->setRebuild(TRUE);
  }

}
