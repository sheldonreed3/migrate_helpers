<?php

namespace Drupal\migrate_helpers;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\MigrateSkipRowException;

/**
 * Class MigrateHelpers.
 *
 * @package Drupal\migrate_helpers
 */
class MigrateHelpers {

  /**
   * @param $migration_id
   *
   * @return int
   * @throws \Drupal\migrate\MigrateException
   */
  public static function countSourceRows($migration_id) {
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = \Drupal::service('plugin.manager.migration')
      ->createInstance($migration_id);
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $source = $migration->getSourcePlugin();
    $count = $source->count();
    return $count;
  }

//  /**
//   * WIP - non-functional
//   *
//   * @param $migration_id
//   * @param $nid
//   *
//   * @throws \Drupal\migrate\MigrateException
//   */
//  public static function findRow($migration_id, $nid) {
//    /** @var \Drupal\migrate\Plugin\Migration $migration */
//    $migration = \Drupal::service('plugin.manager.migration')
//      ->createInstance($migration_id);
//    $executable = new MigrateExecutable($migration, new MigrateMessage());
//    $source = $migration->getSourcePlugin();
//    $row = $source->current();
//  }

  public static function saveEntities($rows) {
    $s = \Drupal::entityTypeManager()->getStorage('content_moderation_state');
    foreach ($rows as $row) {
      $values = $row->getDestination();
      $e = $s->create($row->getDestination());
      $e->setNewRevision(FALSE);
      $e->save();

      $e->set('revision_id', $values['content_entity_revision_id']);
      foreach ($values as $name => $value) {
        if ($e->hasField($name)) {
          ksm($name . ' --- ' . $e->hasField($name) . ' ::: ' . $value);
          $e->set($name, $value);
        }
      }
      $e->save();
    }
  }

  public static function import($migration_id) {
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = \Drupal::service('plugin.manager.migration')
      ->createInstance($migration_id);
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $source = $migration->getSourcePlugin();
    $executable->import();
  }

    /**
     * Returns the processed source and destination row data.
     *
     * @param string $migration_id
     *   The migration to test.
     * @param $items
     *   How many items to process, or NULL for all.
     *
     * @return array
     *   The processed row values.
     */
  public static function processRow($migration_id, $items = NULL, $process_row = TRUE, $import = FALSE) {
    $results = [];

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = \Drupal::service('plugin.manager.migration')
      ->createInstance($migration_id);
    if (!is_null($migration)) {
      $executable = new MigrateExecutable($migration, new MigrateMessage());
    }
    $source = $migration->getSourcePlugin();

    $source->rewind();
    // ksm($source);
    while ($source->valid()) {
      $row = $source->current();

      if ($process_row) {
        try {
          if ($import) {
            $executable->saveRow($row);
          }
          else {
            $executable->processRow($row);
          }
          $results[] = $row;
        }
        catch (MigrateException $e) {
          $results[] = $e->getMessage();
        }
        catch (MigrateSkipRowException $e) {
          if ($message = trim($e->getMessage())) {
            $results[] = $message;
          }
        }
      }
      else {
        $results[] = $row;
      }
      if ($items && count($results) >= $items) {
        break;
      }
      $source->next();
    }

    return $results;
  }

}
