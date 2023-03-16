<?php

namespace Drupal\pagedesigner_content\Service;

use Drupal\pagedesigner\ElementEvents;
use Drupal\pagedesigner\Entity\Element;
use Drupal\pagedesigner\Event\ElementEvent;
use Drupal\pagedesigner\Service\StateChanger;
use Drupal\pagedesigner_tmgmt\Controller\PagedesignerTranslationController;

/**
 * Extends Pagedesigner StateChanger with methods for TMGMT.
 */
class PagedesignerTMGMTStateChanger extends StateChanger {

  /**
   * @param \Drupal\pagedesigner\Entity\Element $entity
   *   The entity to copy.
   * @param \Drupal\pagedesigner\Entity\Element $container
   *   The container its belong to.
   * @param $data
   *   The data to copy.
   *
   * @return \Drupal\pagedesigner\Entity\Element|void
   *   The entity to copy or nothing.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function copyReferenceData(Element $entity, Element $container, $data) {
    if ($entity->id() == $container->id() || !isset($data['parent'])) {
      return;
    }
    $parent = Element::load($data['parent']);
    if ($parent->hasTranslation($data['langcode'])) {
      $parent = $parent->getTranslation($data['langcode']);
    }
    $entity->parent->entity = $parent;
    $entity->langcode->value = $data['langcode'];
    $entity->entity->entity = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($data['entity']);
    $entity->save();
    if ($data['reference_field']) {
      if ($parent->hasField($data['reference_field'])) {
        $parent->get($data['reference_field'])->appendItem($entity);
      }
    }
    else {
      $parent->children->appendItem($entity);
    }
    $parent->save();
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public static function copyFromData(Element $entity, Element $container, $data, &$structure) {
    $clone = $entity->createDuplicate();
    $clone->setPublished(FALSE);
    $clone->container->entity = $container;
    $clone->langcode->value = $entity->langcode->value;
    if ($entity->id() == $container->id()) {
      $structure[$entity->id()]['original'] = $entity->id();

      return $container;
    }

    if ($container != NULL) {
      $clone->entity->entity = $container->entity->entity;
      $clone->langcode->value = $container->langcode->value;
    }
    else {
      $clone->entity->entity = NULL;
    }
    if ($clone->children) {
      $clone->children->setValue([]);
    }
    if ($clone->hasField('field_styles')) {
      $clone->field_styles->setValue([]);
    }
    $clone->save();

    $structure[$clone->id()] = $structure[$entity->id()];
    $structure[$clone->id()]['original'] = $entity->id();
    // Adjust referencing items.
    foreach ($structure as $key => $item) {
      if ($item['parent'] == $entity->id()) {
        $structure[$key]['parent'] = $clone->id();
      }
    }
    unset($structure[$entity->id()]);

    return $clone;
  }

  /**
   * {@inheritdoc}
   */
  public function copyStructure(Element $entity, Element $container = NULL) {
    if ($entity == NULL) {
      return $this;
    }
    $structure = [];
    $this->getHandler()->copyStructure($entity, $container, $structure);
    $this->_output = $structure;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function copyContainer(Element $sourceContainer, Element $targetContainer, $translationData, $clear = FALSE) {
    if ($sourceContainer == NULL) {
      return $this;
    }
    if ($targetContainer == NULL) {
      return $this;
    }

    $this->copyStructure($sourceContainer, $targetContainer);
    $structureCopy = $this->_output;
    foreach ($this->_output as $key => $value) {
      if ($value['parent'] == $sourceContainer->id()) {
        $structureCopy[$key]['parent'] = $targetContainer->id();
      }
    }
    $this->_output = $structureCopy;

    $batch = [
      'title' => 'Processing Pagedesigner content',
      'operations' => [],
      'finished' => [PagedesignerTranslationController::class, 'batchFinished'],
    ];

    $operations = [];

    if ($clear) {
      foreach ($targetContainer->children as $item) {
        if ($item->entity != NULL) {
          $arg_array = [$item->entity, $clear];
          $operations[] = [
            '\Drupal\pagedesigner_content\Service\PagedesignerTMGMTself::deleteEntityBatch',
            $arg_array,
          ];
          if ($targetContainer->children) {
            $targetContainer->children->setValue([]);
          }
          $targetContainer->save();

          /*$this->getHandler()->delete($item->entity, $clear);
          \Drupal::entityTypeManager()->getStorage('pagedesigner_element')->resetCache();*/
        }
      }
    }

    $fifty_array = [];
    $j = 0;
    foreach ($this->_output as $key => $item) {
      $j++;
      $fifty_array[$key] = $item;
      if ($j == 50) {
        $arg_array2 = [$fifty_array, $targetContainer, &$structureCopy];
        $operations[] = [
          '\Drupal\pagedesigner_content\Service\PagedesignerTMGMTself::copyFromDataBatch',
          $arg_array2,
        ];
        $j = 0;
        $fifty_array = [];
      }
    }
    if ($j > 0) {
      $arg_array2 = [$fifty_array, $targetContainer, &$structureCopy];
      $operations[] = [
        '\Drupal\pagedesigner_content\Service\PagedesignerTMGMTself::copyFromDataBatch',
        $arg_array2,
      ];
      $j = 0;
      $fifty_array = [];
    }

    foreach ($structureCopy as $key => $item) {
      $j++;
      $fifty_array[$key] = $item;
      if ($j == 50) {
        $arg_array2 = [$fifty_array, $targetContainer];
        $operations[] = [
          '\Drupal\pagedesigner_content\Service\PagedesignerTMGMTself::copyReferenceDataBatch',
          $arg_array2,
        ];
        $j = 0;
        $fifty_array = [];
      }
    }
    if ($j > 0) {
      $arg_array2 = [$fifty_array, $targetContainer];
      $operations[] = [
        '\Drupal\pagedesigner_content\Service\PagedesignerTMGMTself::copyReferenceDataBatch',
        $arg_array2,
      ];
    }

    $operations[] = [
      '\Drupal\pagedesigner_content\Service\PagedesignerTMGMTself::beforeBatchFinished',
      [$targetContainer],
    ];
    $batch['operations'] = $operations;
    return $batch;

  }

  /**
   * Batch function to remove any previous referenced pagedesigner elements
   *  from the container that is being translated.
   *
   * @param $entity
   *   The pagedesigner element that is being deleted.
   * @param $clear
   *   Flag to indicate whether the element should be deleted or not.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function deleteEntityBatch($entity, $clear) {
    \Drupal::service('pagedesigner.service.element_handler')
      ->delete($entity, $clear);
    \Drupal::entityTypeManager()
      ->getStorage('pagedesigner_element')
      ->resetCache();
  }

  /**
   * @param $fifty_array
   *   The array of pagedesigner elements.
   * @param $targetContainer
   *   The target container to which the elements need to be copied.
   * @param $item
   *   The item that needs to be copied.
   * @param $structureCopy
   *   The array with the data to copy from.
   * @param $context
   *   Batch processing context.
   */
  public static function copyFromDataBatch($fifty_array, $targetContainer, &$structureCopy, &$context) {
    foreach ($fifty_array as $key => $item) {
      if (!isset($context['results']['structure'])) {
        $context['results']['structure'] = $structureCopy;
      }
      $eventData = [];
      $entity = Element::load($key);
      $eventData[] = &$entity;
      $eventData[] = &$targetContainer;
      \Drupal::service('event_dispatcher')
        ->dispatch(ElementEvents::COPY_BEFORE, new ElementEvent(ElementEvents::COPY_BEFORE, $eventData));
      $clone = self::copyFromData($entity, $targetContainer, $item, $context['results']['structure']);
      $context['results']['structure']['originals'][$entity->id()] = $clone->id();
    }
  }

  /**
   * Batch function to copy and re-reference the elements from the container.
   *
   * @param $key
   *   The id of the pagedesigner element.
   * @param $targetContainer
   *   The target container to which the elements need to be copied.
   * @param $context
   *   Batch processing context.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function copyReferenceDataBatch($fifty_array, $targetContainer, &$context) {
    foreach ($fifty_array as $key => $item) {
      $key = $context['results']['structure']['originals'][$key];
      $item = $context['results']['structure'][$key];
      if (!isset($item['original'])) {
        return;
      }
      $eventData = [];
      $entity = Element::load($item['original']);
      $eventData[] = &$entity;
      $eventData[] = &$targetContainer;
      $clone = Element::load($key);
      self::copyReferenceData($clone, $targetContainer, $item);
      $eventData[] = &$clone;
      \Drupal::service('event_dispatcher')
        ->dispatch(ElementEvents::COPY_AFTER, new ElementEvent(ElementEvents::COPY_AFTER, $eventData));
    }
  }

  /**
   * Batch function to save the last entity id.
   *
   * @param $targetContainer
   *   The container that is being translated.
   * @param $context
   *   Batch processing context.
   */
  public static function beforeBatchFinished($targetContainer, &$context) {
    $context['results']['job_entity_id'] = $targetContainer->entity->target_id;
  }

}
