<?php

namespace Drupal\clc_create_plan\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'CreatePlanBlock' block.
 *
 * @Block(
 *  id = "create_plan_block",
 *  admin_label = @Translation("Formulario crear plan"),
 * )
 */
class CreatePlanBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\clc_create_plan\Form\CreatePlanForm');

    return [
      '#markup' => render($form),
      '#attached' => [
        'library' => [
          'clc_create_plan/main',
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
