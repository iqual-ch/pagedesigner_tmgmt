<?php

namespace Drupal\pagedesigner_tmgmt\EventSubscriber;

use Drupal\pagedesigner\ElementEvents;
use Drupal\pagedesigner\Event\ElementEvent;
use Drupal\pagedesigner_content\PagedesignerItemProcessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class EntityTypeSubscriber.
 *
 * @package Drupal\custom_events\EventSubscriber
 */
class PagedesignerTranslateEventsSubscriber implements EventSubscriberInterface {

	/**
	 * {@inheritdoc}
	 *
	 * @return array
	 *   The event names to listen for, and the methods that should be executed.
	 */
	public static function getSubscribedEvents() {
		return [
			ElementEvents::COPY_AFTER => 'pagedesignerItemCopy',
		];
	}

	public $translation_data = null;

	/**
	 * React to a pagedesigner item being copied.
	 *
	 * @param \Drupal\pagedesigner\Event\ElementEvent $event
	 *   Pagedesigner copy event.
	 */
	public function pagedesignerItemCopy(ElementEvent $event) {
		$clone = $event->getData()[2];
		$entity = $event->getData()[0];

		if ($this->translation_data == null)
			$this->translation_data = PagedesignerItemProcessor::$translationData;

		if(isset($this->translation_data['pagedesigner_item'][$entity->id()])) {
			if($this->translation_data['pagedesigner_item'][$entity->id()]['#translate']) {
				$handler = \Drupal::service('pagedesigner.service.element_handler');
				if (isset($this->translation_data['pagedesigner_item'][$entity->id()]['#translation']) && isset($this->translation_data['pagedesigner_item'][$entity->id()]['#translation']["#text"]))
					$handler->patch($clone, [$this->translation_data['pagedesigner_item'][$entity->id()]['#translation']["#text"]]);
			}
		}
		$clone->save();
		/*var_dump('Dojdovne');
		echo "</br></br>";
		var_dump($clone );
		die();*/
	}

}