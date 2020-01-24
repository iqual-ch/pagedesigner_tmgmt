<?php

namespace Drupal\pagedesigner_tmgmt\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\pagedesigner_content\PagedesignerItemProcessor;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplProTranslatorHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PagedesignerTranslationController extends ControllerBase {
  /**
   * Renders the email argument display of an past event.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Throws an exception in case of invalid event.
   */
  public function formatDisplay($job_item) {
    $job_item = JobItem::load($job_item);
    $plugin = \Drupal::service('plugin.manager.tmgmt.source')->createInstance($job_item->get('plugin')->value);
    $plugin->saveTranslation($job_item, $job_item->getJob()->getTargetLangcode());
    $data = $job_item->getData();


    $entity = \Drupal::entityTypeManager()->getStorage($job_item->getItemType())->load($job_item->getItemId());
    $target_langcode = $job_item->getJob()->getTargetLangcode();
    if (!$entity->hasTranslation($target_langcode)) {
      $entity->addTranslation($target_langcode, $entity->toArray());
    }

    $translation = $entity->getTranslation($target_langcode);
    $manager = \Drupal::service('content_translation.manager');
    $manager->getTranslationMetadata($translation)->setSource($entity->language()->getId());

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
  }
  public static function batchFinished($success, $results, $operations) {
    return new RedirectResponse('/admin/tmgmt/jobs');
  }
}