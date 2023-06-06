<?php

namespace Drupal\pagedesigner_tmgmt\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\tmgmt\TMGMTException;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Check offers for reminders.
 */
class AcceptForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current Request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new AcceptForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Request $request, AccountInterface $current_user, LanguageManagerInterface $language_manager, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request;
    $this->currentUser = $current_user;
    $this->languageManager = $language_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('current_user'),
      $container->get('language_manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pagedesigner_tmgmt_accept_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $build = [];
    $page = $this->entityTypeManager->getStorage('node')->load($this->request->query->get('nid'));
    if (isset($page) && $page) {
      $langcode = @reset(explode('-', $this->request->query->get('languages')));
      $job = tmgmt_job_create($page->language()->getId(), $langcode, $this->currentUser->id());
      try {
        $job->addItem('content', 'node', $page->id());
      }
      catch (TMGMTException $e) {
        $this->messenger()->addMessage($e->getMessage(), 'warning');
        return $form;
      }
      $text = count_chars_recursively($job->getData());
      $text = strip_tags($text);
      $text = str_replace(['`', '~', '!', '@', '"', '#', '$', ';', '%', '^', ':', '?', '&', '*', '(', ')', '-', '_', '+', '=', '{', '}', '[', ']', '\\', '|', '/', '\'', '<', '>', ',', '.'], ' ', $text);
      // Remove duplicate spaces.
      $text = trim(preg_replace('/ {2,}/', ' ', $text));
      // Turn into an array.
      $words = ($text) ? explode(' ', $text) : [];
      $letters = strlen(preg_replace('![^a-z]+!', '', strtolower($text)));
      $price = $letters * 0.00002;
      $build['date'] = [
        '#type' => 'label',
        '#title' => $this->t('Words: %word_count, letters: %letters, price: %price', [
          '%word_count' => count($words),
          '%letters' => $letters,
          '%price' => $price,
        ]),
      ];
      $build['actions']['submit'] = [
        '#type'     => 'submit',
        '#value'    => $this->t('Submit'),
        '#width' => 16,
      ];
      return $build;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->entityTypeManager->getStorage('node')->load($this->request->query->get('nid'));
    $values = explode('-', $this->request->query->get('languages'));
    $batch = [
      'title' => $this->t('Translating'),
      'operations' => [],
      'finished' => 'pagedesigner_content_finished_translation',
    // 'file' => 'path_to_file_containing_myfunctions',
    ];
    foreach ($values['languages'] as $langcode) {
      // Create the job object.
      $job = tmgmt_job_create($entity->language()->getId(), $langcode, $this->currentUser->id());
      try {
        // Add the job item.
        $job->addItem('content', $entity->getEntityTypeId(), $entity->id());
        $job->translator = 'deepl_pro';
        $job->save();
        $batch['operations'][] = [
          '\Drupal\pagedesigner_tmgmt\Form\AcceptForm::pagedesigner_content_process_translation',
          [$job],
        ];

      }
      catch (TMGMTException $e) {
        watchdog_exception('tmgmt', $e);
        $languages = $this->languageManager->getLanguages();
        $target_lang_name = $languages[$langcode]->getName();
        $this->messenger()->addError($this->t('Unable to add job item for target language %name. Make sure the source content is not empty.', ['%name' => $target_lang_name]));
      }
    }
    batch_set($batch);
  }

  /**
   * Pagedesigner Content Process Translation.
   */
  public function pagedesigner_content_process_translation($job) {
    $job->requestTranslation();
    $job->acceptTranslation();
    $job->save();
  }

}
