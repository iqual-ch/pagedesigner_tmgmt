<?php

namespace Drupal\pagedesigner_tmgmt\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;

use Drupal\pagedesigner\Event\PagedesignerCopyEvent;
use Drupal\pagedesigner\StateChangerEvents;
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
			StateChangerEvents::COPY => 'pagedesignerItemCopy',
		];
	}

	public $translation_data = null;

	/**
	 * React to a pagedesigner item being copied.
	 *
	 * @param \Drupal\pagedesigner\Event\PagedesignerCopyEvent $event
	 *   Pagedesigner copy event.
	 */
	public function pagedesignerItemCopy(PagedesignerCopyEvent $event) {
		$clone = $event->getClone();
		$entity = $event->getEntity();

		if ($this->translation_data == null)
			$this->translation_data = PagedesignerItemProcessor::$translationData;
		if(isset($this->translation_data['pagedesigner_item'][$entity->id()])) {
			if($this->translation_data['pagedesigner_item'][$entity->id()]['#translate']) {
				$handler = \Drupal::service('plugin.manager.pagedesigner_handler')->createInstance($clone->bundle());
				if (isset($this->translation_data['pagedesigner_item'][$entity->id()]['#translation']) && isset($this->translation_data['pagedesigner_item'][$entity->id()]['#translation']["#text"]))
					$handler->patch($clone, $this->translation_data['pagedesigner_item'][$entity->id()]['#translation']["#text"]);
			}
		}
	}

}