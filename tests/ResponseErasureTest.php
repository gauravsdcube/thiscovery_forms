<?php
/**
 * GOV-4. One response can be pseudonymised or deleted, a panel member can be erased with or
 * without their answers, and each erasure is logged with its mode, actor and reason.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\services\ErasureService;
use humhub\modules\thiscoveryForms\services\PanelService;
use yii\db\Query;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$admin = review_user('review_netadmin');
ReviewLib::asUser($admin);
$form = ReviewLib::form(review_space(), 'EV GOV4 erasure', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$q = ReviewLib::field($form, FormField::TYPE_TEXT, 'Note', ['variable' => 'g4_note']);
$form = ReviewLib::publishOpen($form);
$log = static fn(int $answerId) => (new Query())->from('{{%custom_form_erasure}}')->where(['answer_id' => $answerId])->orderBy(['id' => SORT_DESC])->one();

$doomed = ReviewLib::submit($form, [(int)$q->id => 'delete me']);
$doomedId = (int)$doomed->id;
(new ErasureService())->deleteAnswer($doomed, (int)$admin->id, 'participant asked');
$check(FormAnswer::findOne($doomedId) === null, 'the deleted response still exists');
$check(!FormAnswerField::find()->where(['answer_id' => $doomedId])->exists(), 'the deleted response left answers behind');
$row = $log($doomedId);
$check($row && $row['mode'] === 'delete' && (int)$row['actor_id'] === (int)$admin->id && $row['reason'] === 'participant asked', 'the deletion was not logged with mode, actor and reason: ' . json_encode($row));

$kept = ReviewLib::submit($form, [(int)$q->id => 'keep me']);
$kept->updateAttributes(['created_by' => (int)$admin->id]);
(new ErasureService())->pseudonymiseAnswer($kept, (int)$admin->id, 'identity only');
$kept->refresh();
$check($kept->created_by === null, 'the pseudonymised response kept its account');
$check(FormAnswerField::find()->where(['answer_id' => (int)$kept->id])->exists(), 'pseudonymising deleted the research answers');
$check(($log((int)$kept->id)['mode'] ?? '') === 'pseudonymise', 'the pseudonymisation was not logged');

$panel = (new PanelService())->ensurePanel($form);
$member = (new PanelService())->upsertMember($panel, ['email' => 'gov4-member@example.test', 'first' => 'Erase', 'last' => 'Me']);
$linked = ReviewLib::submit($form, [(int)$q->id => 'member answer']);
$linked->updateAttributes(['panel_member_id' => (int)$member->id]);
$n = (new ErasureService())->eraseMember($member, false, (int)$admin->id, 'withdrew, delete data');
$member = FormPanelMember::findOne((int)$member->id);
$check($n === 1, 'the member\'s response was not handled');
$check($member && $member->email === null && $member->first_name === null, 'the member\'s contact details were kept');
$check(FormAnswer::findOne((int)$linked->id) === null, 'the member\'s response was not deleted');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
