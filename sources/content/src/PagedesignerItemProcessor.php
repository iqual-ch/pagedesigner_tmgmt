<?php

namespace Drupal\pagedesigner_content;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\tmgmt_content\DefaultFieldProcessor;

/**
 * Field processor for the metatags field.
 */
class PagedesignerItemProcessor extends DefaultFieldProcessor {

	/**
	 * {@inheritdoc}
	 */
	public function extractTranslatableData(FieldItemListInterface $field) {

		$data['random'] = [
			'#translate' => true,
			'#text' => 'Text to translate',
			'#label' => 'Working'
		];

		return $data;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setTranslations($field_data, FieldItemListInterface $field) {
		$meta_tags_values = [];

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

		$field->value = serialize($meta_tags_values);
	}

}
