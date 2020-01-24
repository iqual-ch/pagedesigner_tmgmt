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

	public static $sourceLanguage = null;

	public static $translationData = null;

	/**
	 * {@inheritdoc}
	 */
	public function extractTranslatableData(FieldItemListInterface $field) {
//getTranslationFromContext()

		$language = $field[0]->getParent()->getEntity()->language()->getId();

		self::$sourceLanguage = $language;
		//var_dump($field[0]->getTranslationContent($field[0]->getValue()['target_id'], $language));
		$data['pagedesigner_item']['#label'] = 'Pagedesigner container';
		$data['pagedesigner_item']['#container_id'] = $field[0]->getValue()['target_id'];

		$text = "";
		$i = 0;
		foreach ($field[0]->getTranslationContent($field[0]->getValue()['target_id'], $language, false) as $key => $value) {
			$i++;
			$element = \Drupal::entityTypeManager()->getStorage('pagedesigner_element')->load($key);
			$text .= $value;
			/*if ($i > 1) {
				continue;
			}
			if ($element->get('name')->getValue()[0]['value'] == 'image') {
				continue;
			}*/
			$data['pagedesigner_item'][$key] =
				[
					'#translate' => true,
					'#text' => $value, //$element->field_content->value,
					'#label' => $element != NULL ? $element->get('name')->getValue()[0]['value'] : 'pd_item',
				];
		}

		/*var_dump($field[0]->getTranslationContent($field[0]->getValue()['target_id'], $language, false));
		die();*/
		/*var_dump($data);
		die();*/
    return $data;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setTranslations($field_data, FieldItemListInterface $field) {

    self::$translationData = $field_data;
    /** @var SharedTempStore $store */
    $store = \Drupal::service('user.shared_tempstore')->get('pagedesigner.tmgmt_data');
    $store->set($field[0]->getValue()['target_id'], $field_data);
    if ($field[0]) {

      self::$sourceLanguage = $field[0]->getParent()->getEntity()->getUntranslated()->language()->getId();


			$language = $field[0]->getParent()->getEntity()->language()->getId();
			$container = \Drupal\pagedesigner\Entity\Element::load($field[0]->getValue()['target_id']);

			if ($container->hasTranslation(self::$sourceLanguage)) {
				$sourceContainer = 	$container->getTranslation(self::$sourceLanguage);
			}
			// $container->removeTranslation($language);
			if (!$container->hasTranslation($language))
				$container->addTranslation($language)->save();
			$targetContainer = 	$container->getTranslation($language);
      $batch =\Drupal::service('pagedesigner.service.statechanger')->copyContainer($sourceContainer, $targetContainer, $field_data, true);
      $store = \Drupal::service('user.shared_tempstore')->get('pagedesigner.tmgmt_data');
      if (!$store->get('deepl_translator_auto_accept')) {
        batch_set($batch);
      }
      return $batch;
		}
	}


}
