<?php

namespace Drupal\pagedesigner_tmgmt\EventSubscriber;

use Drupal\pagedesigner\ElementEvents;
use Drupal\pagedesigner\Event\ElementEvent;
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

  public $translation_data = NULL;

  /**
   * React to a pagedesigner item being copied.
   *
   * @param \Drupal\pagedesigner\Event\ElementEvent $event
   *   Pagedesigner copy event.
   */
  public function pagedesignerItemCopy(ElementEvent $event) {
    $clone = $event->getData()[2];
    $entity = $event->getData()[0];
    $container = $event->getData()[1];
    $handler = \Drupal::service('pagedesigner.service.element_handler');
    $store = \Drupal::service('user.shared_tempstore')->get('pagedesigner.tmgmt_data');
    $this->translation_data = $store->get($container->id());

    // Check for translation data.
    if (isset($this->translation_data['pagedesigner_item'][$entity->id()])) {
      $itemData = $this->translation_data['pagedesigner_item'][$entity->id()];
      if ($itemData['#translate'] && isset($itemData['#translation']) && isset($itemData['#translation']["#text"])) {

        $translatedText = $itemData['#translation']["#text"];

        // Replace title attributes with translation.
        if (isset($itemData['titles'])) {
          foreach ($itemData['titles'] as $origTitle => $titleData) {
            $translatedText = str_replace('title="' . $origTitle . '"', 'title="' . $titleData['#translation']['#text'] . '"', $translatedText);
          }
        }

        // Update links to point to correct translation.
        $hrefMatches = [];
        preg_match('/\/href="(.*?)"/', $translatedText, $hrefMatches);
        foreach ($hrefMatches as $hrefMatch) {
          // Get id.
          $id = getNodeId();
          $translatedText = str_replace('href="' . $hrefMatch[1] . '"', 'href="' . $lang . '/node/' . $id . '"', $translatedText);
        }

        // Update the content of the element.
        $handler->patch($clone, [$translatedText]);
      }
    }

  }

}
