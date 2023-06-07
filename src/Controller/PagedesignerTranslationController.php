<?php

namespace Drupal\pagedesigner_tmgmt\Controller;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\pagedesigner_tmgmt\PagedesignerItemProcessor;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\SourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Pagedesigner Translation Controller.
 */
class PagedesignerTranslationController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The source manager.
   *
   * @var \Drupal\tmgmt\SourceManager
   */
  protected $sourceManager;

  /**
   * Tempstore Factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempstore;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslationManager;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Constructs a new AcceptForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\tmgmt\SourceManager $source_manager
   *   The source manager.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempstore
   *   The tempstore factory.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager
   *   The content translation manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, SourceManager $source_manager, SharedTempStoreFactory $tempstore, ContentTranslationManagerInterface $content_translation_manager, FieldTypePluginManagerInterface $field_type_manager, ClassResolverInterface $class_resolver) {
    $this->entityTypeManager = $entity_type_manager;
    $this->sourceManager = $source_manager;
    $this->tempstore = $tempstore;
    $this->contentTranslationManager = $content_translation_manager;
    $this->fieldTypeManager = $field_type_manager;
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.tmgmt.source'),
      $container->get('tempstore.shared'),
      $container->get('content_translation.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('class_resolver')
    );
  }

  /**
   * Renders the email argument display of an past event.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Throws an exception in case of invalid event.
   */
  public function formatDisplay($job_item) {
    $job_item = JobItem::load($job_item);
    $plugin = $this->sourceManager
      ->createInstance($job_item->get('plugin')->value);
    $plugin->saveTranslation($job_item, $job_item->getJob()
      ->getTargetLangcode());
    $data = $job_item->getData();
    $store = $this->tempstore->get('pagedesigner.tmgmt_data');

    /** @var \Drupal\tmgmt\Entity\JobItem $entity */
    $entity = $this->entityTypeManager
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
    $this->contentTranslationManager
      ->getTranslationMetadata($translation)
      ->setSource($entity->language()->getId());

    foreach (Element::children($data) as $field_name) {
      $field_data = $data[$field_name];

      if (!$translation->hasField($field_name)) {
        throw new \Exception("Field '$field_name' does not exist on entity " . $translation->getEntityTypeId() . '/' . $translation->id());
      }

      $field = $translation->get($field_name);
      $definition = $this->fieldTypeManager
        ->getDefinition($field->getFieldDefinition()->getType());
      $definition = $this->fieldTypeManager
        ->getDefinition($field->getFieldDefinition()->getType());
      $field_processor = $this->classResolver
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
   *   The Redirect Response.
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
