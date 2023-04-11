<?php

namespace Drupal\pagedesigner_tmgmt\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Element;
use Drupal\pagedesigner_content\PagedesignerItemProcessor;
use Drupal\tmgmt\Entity\JobItem;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 *
 */
class PagedesignerTranslationController extends ControllerBase {

  /**
   * Renders the email argument display of an past event.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Throws an exception in case of invalid event.
   */
  public function formatDisplay($job_item) {
    $job_item = JobItem::load($job_item);
    $plugin = \Drupal::service('plugin.manager.tmgmt.source')
      ->createInstance($job_item->get('plugin')->value);
    $plugin->saveTranslation($job_item, $job_item->getJob()
      ->getTargetLangcode());
    $data = $job_item->getData();
    $store = \Drupal::service('tempstore.shared')
      ->get('pagedesigner.tmgmt_data');

    /** @var \Drupal\tmgmt\Entity\JobItem $entity */
    $entity = \Drupal::entityTypeManager()
      ->getStorage($job_item->getItemType())
      ->load($job_item->getItemId());
    if ($entity == NULL) {
      $job_id = $store->get('deepl_job_id');
      return new RedirectResponse('/admin/tmgmt/jobs/' . $job_id);
    }
    $target_langcode = $job_item->getJob()->getTargetLangcode();
    if (!$entity->hasTranslation($target_langcode)) {
      $entity->addTranslation($target_langcode, $entity->toArray());
    }

    $translation = $entity->getTranslation($target_langcode);
    $manager = \Drupal::service('content_translation.manager');
    $manager->getTranslationMetadata($translation)
      ->setSource($entity->language()->getId());

    foreach (Element::children($data) as $field_name) {
      $field_data = $data[$field_name];

      if (!$translation->hasField($field_name)) {
        throw new \Exception("Field '$field_name' does not exist on entity " . $translation->getEntityTypeId() . '/' . $translation->id());
      }

      $field = $translation->get($field_name);
      $definition = \Drupal::service('plugin.manager.field.field_type')
        ->getDefinition($field->getFieldDefinition()->getType());
      $field_processor = \Drupal::service('class_resolver')
        ->getInstanceFromDefinition($definition['tmgmt_field_processor']);
      if ($field_processor instanceof PagedesignerItemProcessor) {
        $batch = $field_processor->setTranslations($field_data, $field);
        batch_set($batch);
        return batch_process();
      }
    }

    // If the translation provider is auto accepting, redirect to translate
    // the next job item.
    if ($store->get('deepl_translator_auto_accept')) {
      $job_item_last_id = $job_item->id();
      $next = FALSE;
      $job_items = $store->get('deepl_tmgmt_job_items');
      $job_items = array_reverse($job_items);
      foreach ($job_items as $job_item) {
        $job_item = JobItem::load($job_item);
        if ($next) {
          return new RedirectResponse('/accept/translation/' . $job_item->id());
        }
        if ($job_item->id() == $job_item_last_id) {
          $next = TRUE;
        }
      }
      $store->set('deepl_tmgmt_job_items', NULL);
      $store->set('deepl_translator_auto_accept', NULL);
    }

    $job_id = $store->get('deepl_job_id');
    return new RedirectResponse('/admin/tmgmt/jobs/' . $job_id);
  }

  /**
   * Batch function to handle the redirect after the translation.
   *
   * @param bool $success
   *   Indicates whether the batch was successful.
   * @param array $results
   *   Context for passing parameters from the batch processing.
   * @param array $operations
   *   The operations from the batch processing.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public static function batchFinished(bool $success, array $results, array $operations) {
    $store = \Drupal::service('tempstore.shared')
      ->get('pagedesigner.tmgmt_data');
    $job_items = $store->get('deepl_tmgmt_job_items');
    if ($job_items && (is_countable($job_items) ? count($job_items) : 0) == 1) {
      $job_id = $store->get('deepl_job_id');
      return new RedirectResponse('/admin/tmgmt/jobs/' . $job_id);
    }

    $next = FALSE;
    $last_job_entity_id = $results['job_entity_id'];
    // If the translation provider is auto accepting, redirect to translate
    // the next job item.
    if ($store->get('deepl_translator_auto_accept')) {

      $job_items = array_reverse($job_items);
      // Check each job item that the user was translating.
      foreach ($job_items as $job_item) {
        $job_item = JobItem::load($job_item);
        // If the next job item is set to be translated, redirect to do so.
        if ($next && $job_item->getItemId() != $last_job_entity_id) {
          return new RedirectResponse('/accept/translation/' . $job_item->id());
        }
        // If the last job item is found, the next would need to be translated.
        if ($job_item->getItemId() == $last_job_entity_id) {
          $next = TRUE;
          $entity = \Drupal::entityTypeManager()
            ->getStorage($job_item->getItemType())
            ->load($job_item->getItemId());
          $store->delete($entity->id());
        }
      }
      $store->delete('deepl_tmgmt_job_items');
      $store->delete('deepl_translator_auto_accept');
    }
    $job_id = $store->get('deepl_job_id');
    return new RedirectResponse('/admin/tmgmt/jobs/' . $job_id);
  }

}
