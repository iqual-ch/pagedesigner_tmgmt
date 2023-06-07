<?php

/**
 * @file
 * Script to run.
 */

use Drupal\pagedesigner\Entity\Element;

$store = \Drupal::service('user.shared_tempstore')->get('pagedesigner.tmgmt_data');
$store->set(70760, [
  'pagedesigner_item' => [
    '70764' => [
      '#translate' => TRUE,
      '#translation' => [
        '#text' => 'Now we are testing.',
      ],
    ],
  ],
]);
$container = Element::load(70760);
if (!$container->hasTranslation('en')) {
  $targetContainer = $container->addTranslation('en');
}
else {
  $targetContainer = $container->getTranslation('en');
}
$structure = \Drupal::service('pagedesigner.service.statechanger')->copyContainer($container, $targetContainer, [])->getOutput();
die();
$pids = [];

$numFields = [];
$patterns = \Drupal::service('plugin.manager.ui_patterns')->getDefinitions();

$elements = \Drupal::database()
  ->query("SELECT d.id, p.field_pattern_value, COUNT(d.id) as fieldCount FROM `pagedesigner_element_field_data` d JOIN pagedesigner_element__field_pattern p ON (d.deleted IS NULL OR d.deleted = 0) AND d.id = p.entity_id JOIN pagedesigner_element__children c ON c.entity_id = d.id GROUP BY d.id, p.field_pattern_value")->fetchAll();

$count = 0;

foreach ($elements as $element) {
  $toDelete = [];
  // var_dump($element->field_pattern_value);.
  if (!empty($element->field_pattern_value) && !empty($patterns[$element->field_pattern_value])) {
    $pattern = $patterns[$element->field_pattern_value];
    if ((is_countable($pattern->getFields()) ? count($pattern->getFields()) : 0) < $element->fieldCount) {
      $entity = Element::load($element->id);
      $i = 0;
      foreach ($entity->children as $delta => $item) {
        if ($item->entity == NULL) {
          // $i++;
          continue;
        }
        if (!$item->entity->hasField('field_placeholder')) {
          $toDelete[$item->entity->id()] = $item->entity;
          echo 'delete child ' . $item->entity->id() . "\n";
        }
        elseif (!$pattern->hasField($item->entity->field_placeholder->value)) {
          $toDelete[$item->entity->id()] = $item->entity;
          echo 'unknown field ' . $item->entity->field_placeholder->value . ' on ' . $item->entity->id() . "\n";
        }
        else {
          $foundDuplicate = FALSE;
          $attach = [];
          foreach ($entity->children as $deltaInner => $itemInner) {
            if ($item->entity->id() == $itemInner->entity->id()) {
              $attach[] = $item->entity->id();
              break;
            }
            if ($delta <= $deltaInner) {
              $attach[] = $item->entity->id();
              continue;
            }
            if ($itemInner->entity->field_placeholder->value === $item->entity->field_placeholder->value) {
              $toDelete[$item->entity->id()] = $item->entity;
              echo 'duplicate field on ' . $item->entity->id() . "\n";
              break;
            }
            else {
              $attach[] = $item->entity->id();
            }
          }
          /*$entity->children->setValue($attach);
          $entity->save();*/
          if (!$foundDuplicate) {
            // Echo 'something else on ' . $item->entity->id() . "\n";.
          }
        }
        $i++;
      }

      if (!$entity->entity->isEmpty() && $entity->entity->entity != NULL && $entity->entity->entity->getType() != 'campaign_landingpage' && !in_array($pattern->id(), ['img']) && (is_countable($pattern->getFields()) ? count($pattern->getFields()) : 0) != (is_countable($entity->children) ? count($entity->children) : 0) - count($toDelete)) {
        echo 'Error: Deleting too many fields on ' . $entity->id() . ' with pattern ' . $pattern->id() . "\n";
        echo 'Target: ' . (is_countable($pattern->getFields()) ? count($pattern->getFields()) : 0) . "\n";
        echo 'Current: ' . (is_countable($entity->children) ? count($entity->children) : 0) . "\n";
        echo 'Deleting: ' . count($toDelete) . ' - ' . print_r(array_keys($toDelete), TRUE) . "\n";
        echo 'Result: ' . ((is_countable($entity->children) ? count($entity->children) : 0) - count($toDelete)) . "\n";
        break;
      }
      else {
        echo 'Deleting ' . count($toDelete) . ' fields on ' . $entity->id() . "\n";
        foreach ($toDelete as $item) {
          echo "deleting " . $item->id() . "\n";
          // $item->delete();
        }
        // \Drupal::entityTypeManager()->getStorage('pagedesigner_element')->load($entity->id())->save();
        // $entity->save();
      }
    }
  }
}
die();
// $pids = \Drupal::database()->query("SELECT id FROM `pagedesigner_element_field_data` WHERE entity = 22722 AND id in (select children_target_id FROM pagedesigner_element__children) AND `deleted` = 1 ORDER BY `pagedesigner_element_field_data`.`id` ASC")->fetchAll();
// Echo count($pids) . "\n";
// Do {
//     $invariant = count($pids);
// $pids = \Drupal::database()->query("SELECT id FROM `pagedesigner_element_field_data` WHERE id in (select children_target_id FROM pagedesigner_element__children c JOIN pagedesigner_element_field_data d ON c.entity_id = d.id AND COALESCE(d.deleted, 0) = 0) AND `type` NOT like 'style' and `type` not like 'container' AND type not like 'layout' and type not like 'gallery_gallery' ORDER BY `pagedesigner_element_field_data`.`id` ASC")->fetchAll();
//     echo 'restoring ' . count($pids) . ' elements ' . "\n";
// foreach ($pids as $pid) {
//         $element = Element::load($pid->id);
//         $element->deleted = 0;
//         $element->save();
//         // $element->delete();
//     }
// } while (count($pids) > 0 && $invariant != count($pids));
// die();
$invariant = is_countable($pids) ? count($pids) : 0;
$sum = 0;

$pids = \Drupal::database()->query("SELECT id FROM `pagedesigner_element_field_data` WHERE id NOT in (select children_target_id FROM pagedesigner_element__children c JOIN pagedesigner_element_field_data d ON c.entity_id = d.id AND COALESCE(d.deleted, 0) = 0) AND `type` NOT like 'style' and `type` not like 'container' AND type not like 'layout' and type not like 'gallery_gallery' ORDER BY `pagedesigner_element_field_data`.`id` ASC")->fetchAll();

do {
  $sum += is_countable($pids) ? count($pids) : 0;
  echo 'cleaning ' . (is_countable($pids) ? count($pids) : 0) . ' elements ' . "\n";
  $parents = [];
  foreach ($pids as $pid) {
    $parents[] = $pid->id;
  }

  $selection = random_int(0, is_countable($pids) ? count($pids) : 0);
  $element = Element::load($pids[$selection]->id);
  $node = $element->entity->entity;
  $entity_type = 'node';
  $view_mode = 'default';

  $render_controller = \Drupal::entityTypeManager()->getViewBuilder($node->getEntityTypeId());
  $render_output = $render_controller->view($node, $view_mode, $element->language()->getId());
  print_r($render_output);
  if (stripos($render_output, (string) $pids[$selection]->id) != FALSE) {
    echo 'found element in render output' . "\n";
    exit(0);
  }

  $pids = \Drupal::database()->query("SELECT id FROM `pagedesigner_element_field_data` WHERE id in (select children_target_id FROM pagedesigner_element__children WHERE entity_id IN (:pids[])) AND `type` NOT like 'style' and `type` not like 'container' AND type not like 'layout' and type not like 'gallery_gallery' ORDER BY `pagedesigner_element_field_data`.`id` ASC",
    [
      ':pids[]' => $parents,
    ]
  )->fetchAll();

  // Foreach ($pids as $pid) {
  //     $element = Element::load($pid->id);
  //     $element->deleted = 1;
  //     $element->save();
  //     // $element->delete();
  // }
} while (count($pids) > 0 && $invariant != count($pids));

echo 'total ' . $sum . "\n";

// Do {
//     $invariant = count($pids);
// $pids = \Drupal::database()->query("SELECT id FROM `pagedesigner_element` WHERE id not in (select field_styles_target_id FROM pagedesigner_element__field_styles s JOIN pagedesigner_element_field_data d ON s.entity_id = d.id AND COALESCE(d.deleted, 0) != 1 GROUP BY children_target_id) AND `type` like 'style' ORDER BY `pagedesigner_element`.`id` ASC")->fetchAll();
//     echo 'cleaning ' . count($pids) . ' styles ' . "\n";
//     foreach ($pids as $pid) {
//         $element = Element::load($pid->id);
//         $element->deleted = 1;
//         $element->save();
// // $element->delete();
//     }
// } while (count($pids) > 0 && $invariant != count($pids));
// $layouts = \Drupal::database()->query("SELECT id FROM `pagedesigner_element_field_data` WHERE type like 'layout' and `deleted` is NULL")->fetchAll();
// Foreach ($layouts as $layout) {
//     $element = Element::load($layout->id);
//     clearEntity($element);
// }
// Function clearEntity($element)
// {
//     if ($element->entity->entity) {
//         $element->entity->entity = null;
//         $element->save();
//     }
//     foreach ($element->children as $item) {
//         clearEntity($item->entity);
//     }
//     if ($element->hasField('field_styles')) {
//         foreach ($element->field_styles as $item) {
//             clearEntity($item->entity);
//         }
//     }
// }.
