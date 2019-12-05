<?php

namespace Drupal\pagedesigner_content;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\tmgmt_content\DefaultFieldProcessor;

/**
 * Field processor for the metatags field.
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

		foreach ($field[0]->getTranslationContent($field[0]->getValue()['target_id'], $language, false) as $key => $value) {
			$element = \Drupal::entityTypeManager()->getStorage('pagedesigner_element')->load($key);
			$data['pagedesigner_item'][$key] =
				[
					'#translate' => true,
					'#text' => $value, //$element->field_content->value,
					'#label' => $element->get('name')->getValue()[0]['value']
				];
		}

		return $data;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setTranslations($field_data, FieldItemListInterface $field) {

		self::$sourceLanguage = $field[0]->getParent()->getEntity()->getUntranslated()->language()->getId();


		$language = $field[0]->getParent()->getEntity()->language()->getId();
		$container = \Drupal\pagedesigner\Entity\Element::load($field[0]->getValue()['target_id']);

		if ($container->hasTranslation(self::$sourceLanguage)) {
			$sourceContainer = 	$container->getTranslation(self::$sourceLanguage);
		}
		if (!$container->hasTranslation($language)) {
			$sourceContainer->addTranslation($language)->save();
		}
		$targetContainer = 	$container->getTranslation($language);

		self::$translationData = $field_data;
		\Drupal::service('pagedesigner.service.statechanger')->copyContainer($sourceContainer, $targetContainer);




		//$stateChanger = \Drupal::service('pagedesigner.service.statechanger');

		//$clone = $stateChanger->copy($entity, $container)->getOutput();
		/*$meta_tags_values = [];

		// Loop over the groups and tags, either use the translated text or the
		// original and then serialize the whole structure again.
		foreach (Element::children($field_data) as $group_name) {
			foreach (Element::children($field_data[$group_name]) as $tag_name) {

				$property_data = $field_data[$group_name][$tag_name];
				if (isset($property_data['#translation']['#text']) && $property_data['#translate']) {
					$meta_tags_values[$tag_name] = $property_data['#translation']['#text'];
				}
				else {
					$meta_tags_values[$tag_name] =$property_data['#text'];
				}
			}
		}

		$field->value = serialize($meta_tags_values);*/
	}


}
