<?php
/**
 * Participant messages: a form line wins, then the site line, then the English sentence.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormMessageI18n;
use humhub\modules\thiscoveryForms\services\ParticipantMessageCatalog;
use humhub\modules\thiscoveryForms\services\ParticipantMessages;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$stub = new class {
    public function getTranslation(
        string $objectType,
        string|int $objectId,
        string $field,
        string $sourceText,
        string $targetLanguage,
        string $sourceLanguage = '',
        string $context = 'generic',
        bool $allowAmazon = true,
        ?string $module = null
    ): string {
        return '';
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV participant messages', []);
$form->already_submitted_message = 'You already sent {formName}.';
$form->save(false);

$keys = ['next', 'back', 'checkbox_max', 'author.already_submitted'];
FormMessageI18n::deleteAll(['language' => 'cy', 'message_key' => $keys, 'form_id' => [0, (int)$form->id]]);

$write = static function (int $formId, string $key, string $text, bool $locked, string $source): void {
    $row = new FormMessageI18n([
        'form_id' => $formId,
        'language' => 'cy',
        'message_key' => $key,
    ]);
    $row->text = $text;
    $row->locked = $locked ? 1 : 0;
    $row->source_hash = hash('sha256', $source);
    $row->updated_at = date('Y-m-d H:i:s');
    $row->save(false);
};

$write((int)$form->id, 'next', 'Ymlaen', true, 'Next');
$write(0, 'next', 'Nesaf', false, 'Next');
$write(0, 'checkbox_max', 'Dewiswch {max}.', false, 'Select at most {max} options.');
$write((int)$form->id, 'author.already_submitted', 'Wedi anfon {formName}.', true, 'You already sent {formName}.');

try {
    $view = (string)file_get_contents(dirname(__DIR__) . '/views/form/_field_fill.php');
    $prompt = ParticipantMessageCatalog::entries()['select_prompt']['source'];
    $check(str_contains($view, $prompt), 'The select prompt in the catalogue matches the fill page.');

    ParticipantMessages::bind($form, 'cy');
    $check(Yii::t('ThiscoveryFormsModule.base', 'Next') === 'Ymlaen', 'A form line is used before the site line.');
    $check(
        Yii::t('ThiscoveryFormsModule.base', 'Select at most {max} options.', ['max' => 3]) === 'Dewiswch 3.',
        'A site line keeps the number placeholder.'
    );
    $check(
        $form->getAlreadySubmittedMessage() !== '' && str_contains($form->getAlreadySubmittedMessage(), 'Wedi anfon'),
        'The already-submitted message uses the form translation.'
    );

    FormMessageI18n::deleteAll(['form_id' => (int)$form->id, 'language' => 'cy', 'message_key' => 'next']);
    ParticipantMessages::bind($form, 'cy');
    $check(Yii::t('ThiscoveryFormsModule.base', 'Next') === 'Nesaf', 'With no form line, the site line is used.');

    FormMessageI18n::deleteAll(['form_id' => 0, 'language' => 'cy', 'message_key' => 'next']);
    ParticipantMessages::bind($form, 'cy');
    $check(Yii::t('ThiscoveryFormsModule.base', 'Next') === 'Next', 'With no translation, the English sentence is used.');

    $write((int)$form->id, 'back', 'Cadw', true, 'Back');
    $write(0, 'back', 'Yn ol', false, 'Back');
    ParticipantMessages::generate($form, 'en-GB', 'cy', $stub);
    ParticipantMessages::bind($form, 'cy');
    $check(Yii::t('ThiscoveryFormsModule.base', 'Back') === 'Cadw', 'Generate leaves a line the form has edited.');

    FormMessageI18n::deleteAll(['form_id' => (int)$form->id, 'language' => 'cy', 'message_key' => 'back']);
    ParticipantMessages::generate($form, 'en-GB', 'cy', $stub);
    ParticipantMessages::bind($form, 'cy');
    $check(Yii::t('ThiscoveryFormsModule.base', 'Back') === 'Yn ol', 'Generate copies the site line onto the form.');

$check(Yii::t('ThiscoveryFormsModule.base', 'Edit') === 'Edit', 'Staff links on the page stay in the staff language.');
    $check(
        ParticipantMessages::swapLeakedTokens('Page % %0%% of % %1%%', 'Page {current} of {total}') === 'Page {current} of {total}',
        'A split placeholder marker is put back.'
    );
    $check(
        ParticipantMessages::swapLeakedTokens('पेज% %0%% या %1%%', 'Page {current} of {total}') === 'पेज {current} या {total}',
        'A split second placeholder is put back.'
    );
} finally {
    ParticipantMessages::clear();
    FormMessageI18n::deleteAll(['language' => 'cy', 'message_key' => $keys, 'form_id' => [0, (int)$form->id]]);
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
