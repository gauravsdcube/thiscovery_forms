<?php
/**
 * Form for the NEW-16 mobile grid check.
 * Usage: setup_new16.php create
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV R16 mobile grid', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$grid = ReviewLib::field($form, FormField::TYPE_GRID_MULTI, 'Grid', [
    'variable' => 'r16_grid',
    'required' => 0,
    'sort_order' => 1,
]);
$grid->setGridConfig([
    'rows' => ['Apple'],
    'columns' => ['Good'],
    'mobile_layout' => 'stack',
]);
$grid->save(false);
ReviewLib::field($form, FormField::TYPE_RADIO, 'Choice', [
    'variable' => 'r16_choice',
    'required' => 0,
    'sort_order' => 2,
    'options' => ['Yes', 'Other'],
]);
$form = ReviewLib::publishOpen($form);
echo 'form=' . (int)$form->id . "\n";
