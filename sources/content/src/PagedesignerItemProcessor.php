<?php

namespace Drupal\pagedesigner_content;

use Drupal\pagedesigner\Entity\Element;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\tmgmt_content\DefaultFieldProcessor;

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
    $data['pagedesigner_item'] = [];
    $data['pagedesigner_item']['#label'] = 'Pagedesigner container';
    $data['pagedesigner_item']['#container_id'] = $field[0]->getValue()['target_id'];

    // Get the translation content.
    $translationContent = $field[0]->getTranslationContent($field[0]->getValue()['target_id'], $language, FALSE);
    foreach ($translationContent as $key => $value) {

      // Set the content translation info.
      $element = \Drupal::entityTypeManager()
        ->getStorage('pagedesigner_element')
        ->load($key);
      $data['pagedesigner_item'][$key] =
        [
          '#translate' => TRUE,
          '#text' => $value,
          '#label' => $element != NULL ? $element->get('name')
            ->getValue()[0]['value'] : 'pd_item',
        ];

      // Get any titles attributes and add them to the list for translation.
      $titleMatches = [];
      preg_match_all('/title="(.*?)"/', $value, $titleMatches);
      if (count($titleMatches) > 0 && count($titleMatches[1]) > 0) {
        foreach ($titleMatches[1] as $titleMatch) {
          $key_title = strtolower($titleMatch);
          $key_title = preg_replace('/[^a-z0-9_]+/', '_', $key_title);
          $key_title = preg_replace('/_+/', '_', $key_title);
          $data['pagedesigner_item'][$key . '_titles_' . $key_title] = [
            '#translate' => TRUE,
            '#text' => $titleMatch,
            '#label' => $element != NULL ? $element->get('name')
              ->getValue()[0]['value'] : 'pd_item',
          ];
        }
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslations($field_data, FieldItemListInterface $field) {

    self::$translationData = $field_data;
    /** @var \Drupal\Core\TempStore\SharedTempStore $store */
    $store = \Drupal::service('user.shared_tempstore')
      ->get('pagedesigner.tmgmt_data');
    if ($field[0]) {
      $store->set($field[0]->getValue()['target_id'], $field_data);

      self::$sourceLanguage = $field[0]->getParent()
        ->getEntity()
        ->getUntranslated()
        ->language()
        ->getId();

      $language = $field[0]->getParent()->getEntity()->language()->getId();
      $container = Element::load($field[0]->getValue()['target_id']);

      if ($container->hasTranslation(self::$sourceLanguage)) {
        $sourceContainer = $container->getTranslation(self::$sourceLanguage);
      }
      if (!$container->hasTranslation($language)) {
        $container->addTranslation($language)->save();
      }
      $targetContainer = $container->getTranslation($language);
      if ($sourceContainer != NULL && $targetContainer != NULL) {
        $batch = \Drupal::service('pagedesigner.service.statechanger')
          ->copyContainer($sourceContainer, $targetContainer, $field_data, TRUE);
        $store = \Drupal::service('user.shared_tempstore')
          ->get('pagedesigner.tmgmt_data');
        if (!$store->get('deepl_translator_auto_accept')) {
          batch_set($batch);
        }

      return $batch;
      }
      return;
    }
  }

}
