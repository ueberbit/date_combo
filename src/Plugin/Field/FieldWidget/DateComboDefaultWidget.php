<?php
/**
 * @file
 * Contains \Drupal\date_combo\Plugin\Field\FieldWidget\DateComboDefaultWidget.
 */

namespace Drupal\date_combo\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime\Plugin\Field\FieldWidget\DateTimeWidgetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'datetime_default' widget.
 *
 * @FieldWidget(
 *   id = "date_combo_default",
 *   label = @Translation("Date Combo: Date and time"),
 *   field_types = {
 *     "date_combo"
 *   }
 * )
 */
class DateComboDefaultWidget extends DateTimeWidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The date format storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $dateStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityStorageInterface $date_storage) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->dateStorage = $date_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('date_format')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#element_validate'][] = [$this, 'validateStartEnd'];

    // Identify the type of date and time elements to use.
    switch ($this->getFieldSetting('datetime_type')) {
      case DateTimeItem::DATETIME_TYPE_DATE:
        $date_type = 'date';
        $time_type = 'none';
        $date_format = $this->dateStorage->load('html_date')->getPattern();
        $time_format = '';
        break;

      default:
        $date_type = 'date';
        $time_type = 'time';
        $date_format = $this->dateStorage->load('html_date')->getPattern();
        $time_format = $this->dateStorage->load('html_time')->getPattern();
        break;
    }
    $element['value2'] = $element['value'];

    $element['value'] += array(
      '#title' => t('Start'),
      '#date_date_format'=>  $date_format,
      '#date_date_element' => $date_type,
      '#date_date_callbacks' => array(),
      '#date_time_format' => $time_format,
      '#date_time_element' => $time_type,
      '#date_time_callbacks' => array(),
    );

    $element['value2'] += array(
      '#title' => t('End'),
      '#date_date_format'=>  $date_format,
      '#date_date_element' => $date_type,
      '#date_date_callbacks' => array(),
      '#date_time_format' => $time_format,
      '#date_time_element' => $time_type,
      '#date_time_callbacks' => array(),

    );
    $element['value2']['#required'] = $this->fieldDefinition->getSetting('require_enddate');

    if ($items[$delta]->date2) {
      /** @var \Drupal\Component\Datetime\DateTimePlus $date */
      $date = $items[$delta]->date2;
      // The date was created and verified during field_load(), so it is safe to
      // use without further inspection.
      if ($this->getFieldSetting('datetime_type') == DateTimeItem::DATETIME_TYPE_DATE) {
        // A date without time will pick up the current time, use the default
        // time.
        $date->setDefaultDateTime();
      }
      $date->setTimezone(new \DateTimeZone($element['value2']['#date_timezone']));
      $element['value2']['#default_value'] = $date;
    }
    $element['#type'] = 'fieldset';
    unset($element['#theme_wrappers']);

    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // The widget form element type has transformed the value to a
    // DrupalDateTime object at this point. We need to convert it back to the
    // storage timezone and format.
    foreach ($values as &$item) {
      if (isset($item['value']) && $item['value'] instanceof DrupalDateTime) {
        $item['value'] = $this->massageDateValue($item['value']);
      }
      if (isset($item['value2']) && $item['value2'] instanceof DrupalDateTime) {
        $item['value2'] = $this->massageDateValue($item['value2']);
      }
    }
    return $values;
  }

  protected function massageDateValue(DrupalDateTime $date) {
    switch ($this->getFieldSetting('datetime_type')) {
      case DateTimeItem::DATETIME_TYPE_DATE:
        // If this is a date-only field, set it to the default time so the
        // timezone conversion can be reversed.
        $date->setDefaultDateTime();
        $format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
        break;

      default:
        $format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
        break;
    }
    // Adjust the date for storage.
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    return $date->format($format);
  }

  /**
   * #element_validate callback to ensure that the start date <= the end date.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateStartEnd(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $start_date = $element['value']['#value']['object'];
    $end_date = $element['value2']['#value']['object'];

    if ($start_date instanceof DrupalDateTime && $end_date instanceof DrupalDateTime) {
      if ($start_date->getTimestamp() !== $end_date->getTimestamp()) {
        $interval = $start_date->diff($end_date);
        if ($interval->invert === 1) {
          $form_state->setError($element, $this->t('The @title end date cannot be before the start date.', ['@title' => $element['#title']]));
        }
      }
    }
  }

}
