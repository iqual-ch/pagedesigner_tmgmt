<?php

namespace Drupal\pagedesigner_tmgmt\EventSubscriber;

use Drupal\Core\Url;
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
        $titleMatches = [];
        preg_match_all('/title="(.*?)"/', $translatedText, $titleMatches);

        // Replace title attributes with translation.
        if (isset($titleMatches) && count($titleMatches) > 0 && count($titleMatches[1]) > 0) {
          foreach ($titleMatches[1] as $titleMatch) {
            $key_title = strtolower($titleMatch);
            $key_title = preg_replace('/[^a-z0-9_]+/', '_', $key_title);
            $key_title = preg_replace('/_+/', '_', $key_title);
            if (isset($this->translation_data['pagedesigner_item'][$entity->id(). '_titles_' . $key_title])) {
              $origTitle = $this->translation_data['pagedesigner_item'][$entity->id(). '_titles_' . $key_title]['#text'];
              $translatedTitle = $this->translation_data['pagedesigner_item'][$entity->id(). '_titles_' . $key_title]['#translation']['#text'];
              $translatedText = str_replace('title="' . $origTitle . '"', 'title="' . $translatedTitle . '"', $translatedText);
            }

          }
        }


        // Update links to point to correct translation.
        $hrefMatches = [];
        preg_match_all('/href="(\/.*?)"/', $translatedText, $hrefMatches);

        if (count($hrefMatches) > 0) {
          foreach ($hrefMatches[1] as $key => $hrefMatch) {
            // Get node id from the link and replace it with the current
            // language accordingly.
            $alias = \Drupal::service('path.alias_manager')->getPathByAlias($hrefMatch);

            $params = Url::fromUri("internal:" . $alias)->getRouteParameters();
            $entity_type = key($params);
            if (isset($entity_type) && strlen($entity_type) > 0) {
              $node = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);
              $id = $node->id();
              $lang = $container->language()->getId();
              $translatedText = str_replace($hrefMatches[0][$key], 'href="/' . $lang . '/node/' . $id . '"', $translatedText);
            }
          }
        }

        // Update the content of the element.
        $handler->patch($clone, [$translatedText]);
      }
    }

  }

}
