<?php

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function date_combo_field_views_data(FieldStorageConfigInterface $field_storage) {
  module_load_include('inc', 'datetime', 'datetime.views');
  $data = datetime_field_views_data($field_storage);
  foreach ($data as $table_name => $table_data) {
    // Set the 'datetime' filter type.
    $data[$table_name][$field_storage->getName() . '_value2']['filter']['id'] = 'datetime';
    // Set the 'datetime' sort handler.
    $data[$table_name][$field_storage->getName() . '_value2']['sort']['id'] = 'datetime';
  }
  return $data;
}
