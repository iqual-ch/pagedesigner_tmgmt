<?php

namespace Drupal\pagedesigner_content;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\tmgmt_content\DefaultFieldProcessor;
use Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplProTranslator;

/**
 * Field processor for the pagedesigner field.
 */
class PagedesignerItemProcessor extends DefaultFieldProcessor {

  public static $sourceLanguage = NULL;

  public static $translationData = NULL;

  /**
   * {@inheritdoc}
   */
  public function extractTranslatableData(FieldItemListInterface $field) {

    $language = $field[0]->getParent()->getEntity()->language()->getId();

    self::$sourceLanguage = $language;
    //var_dump($field[0]->getTranslationContent($field[0]->getValue()['target_id'], $language));
    $data['pagedesigner_item']['#label'] = 'Pagedesigner container';
    $data['pagedesigner_item']['#container_id'] = $field[0]->getValue()['target_id'];

    $text = "";
    $i = 0;
    foreach ($field[0]->getTranslationContent($field[0]->getValue()['target_id'], $language, FALSE) as $key => $value) {
      $i++;
      $element = \Drupal::entityTypeManager()
        ->getStorage('pagedesigner_element')
        ->load($key);
      $text .= $value;

      $data['pagedesigner_item'][$key] =
        [
          '#translate' => TRUE,
          '#text' => $value, //$element->field_content->value,
          '#label' => $element != NULL ? $element->get('name')
            ->getValue()[0]['value'] : 'pd_item',
        ];
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslations($field_data, FieldItemListInterface $field) {

    self::$translationData = $field_data;
    /** @var SharedTempStore $store */
    $store = \Drupal::service('user.shared_tempstore')
      ->get('pagedesigner.tmgmt_data');
    $store->set($field[0]->getValue()['target_id'], $field_data);
    if ($field[0]) {

      self::$sourceLanguage = $field[0]->getParent()
        ->getEntity()
        ->getUntranslated()
        ->language()
        ->getId();

      $language = $field[0]->getParent()->getEntity()->language()->getId();
      $container = \Drupal\pagedesigner\Entity\Element::load($field[0]->getValue()['target_id']);

      if ($container->hasTranslation(self::$sourceLanguage)) {
        $sourceContainer = $container->getTranslation(self::$sourceLanguage);
      }
      if (!$container->hasTranslation($language)) {
        $container->addTranslation($language)->save();
      }
      $targetContainer = $container->getTranslation($language);
      $batch = \Drupal::service('pagedesigner.service.statechanger')
        ->copyContainer($sourceContainer, $targetContainer, $field_data, TRUE);
      $store = \Drupal::service('user.shared_tempstore')
        ->get('pagedesigner.tmgmt_data');
      if (!$store->get('deepl_translator_auto_accept')) {
        batch_set($batch);
      }
      return $batch;
    }
  }
}
