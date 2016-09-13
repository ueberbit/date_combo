<?php

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function date_combo_field_views_data(FieldStorageConfigInterface $field_storage) {
  module_load_include('inc', 'datetime', 'datetime.views');
  $data = datetime_field_views_data($field_storage);
  return $data;
}
