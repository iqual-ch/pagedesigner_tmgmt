<?php

namespace Drupal\pagedesigner_content;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\tmgmt_content\DefaultFieldProcessor;

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
			}*/
			if ($element->get('name')->getValue()[0]['value'] == 'image') {
				continue;
			}
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

			/*var_dump(self::$sourceLanguage);
			var_dump($language);
			var_dump($container->id());
			var_dump($sourceContainer->id());
			var_dump($targetContainer->id());
			\Drupal::messenger()->addMessage(self::$sourceLanguage . $language . $container->id() . $sourceContainer->id() . $targetContainer->id());
			die();*/
			\Drupal::service('pagedesigner.service.statechanger')->copyContainer($sourceContainer, $targetContainer, $field_data, true);
			$targetContainer->save();
		}
	}


}
