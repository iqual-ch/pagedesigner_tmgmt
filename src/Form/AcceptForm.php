<?php

namespace Drupal\pagedesigner_tmgmt\Form;

use Drupal\Core\Form\FormBase;
use Drupal\tmgmt\TMGMTException;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Check offers for reminders.
 */
class AcceptForm extends FormBase {

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
    $user = \Drupal::currentUser();
    $page = \Drupal::entityTypeManager()->getStorage('node')->load($_GET['nid']);
    if (isset($page) && $page) {
      $langcode = @reset(explode('-', $_GET['languages']));
      $job = tmgmt_job_create($page->language()->getId(), $langcode, \Drupal::currentUser()->id());
      try {
        $job->addItem('content', 'node', $page->id());
      }
      catch (TMGMTException $e) {
        \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
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
        '#title' => $this->t('Words: ' . count($words) . ', letters: ' . $letters . ', price: ' . $price),
      ];
      $build['actions']['submit'] = [
        '#type'     => 'submit',
        '#value'    => t('Submit'),
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
    $entity = \Drupal::entityTypeManager()->getStorage('node')->load($_GET['nid']);
    $values = explode('-', $_GET['languages']);
    $batch = [
      'title' => t('Translating'),
      'operations' => [],
      'finished' => 'pagedesigner_content_finished_translation',
    // 'file' => 'path_to_file_containing_myfunctions',
    ];
    foreach ($values['languages'] as $langcode) {
      // Create the job object.
      $job = tmgmt_job_create($entity->language()->getId(), $langcode, \Drupal::currentUser()->id());
      try {
        // Add the job item.
        $job->addItem('content', $entity->getEntityTypeId(), $entity->id());
        $job->translator = 'deepl_pro';
        $job->save();
        $batch['operations'][] = ['\Drupal\pagedesigner_tmgmt\Form\AcceptForm::pagedesigner_content_process_translation', [$job]];

      }
      catch (TMGMTException $e) {
        watchdog_exception('tmgmt', $e);
        $languages = \Drupal::languageManager()->getLanguages();
        $target_lang_name = $languages[$langcode]->getName();
        $this->messenger()->addError(t('Unable to add job item for target language %name. Make sure the source content is not empty.', ['%name' => $target_lang_name]));
      }
    }
    batch_set($batch);
  }

  /**
   *
   */
  public function pagedesigner_content_process_translation($job) {
    $job->requestTranslation();
    $job->acceptTranslation();
    $job->save();
  }

}
