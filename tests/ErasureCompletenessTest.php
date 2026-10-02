<?php
/**
 * V3-30. Erasing a user reaches token and invite answers, and clears the consent
 * signature, email log, client hashes, repair log and audit history around them.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\services\ErasureService;
use humhub\modules\user\models\User;
use yii\db\Query;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$db = Yii::$app->db;

// A person who is only known through a panel token: no created_by on the answer.
$ghost = new User();
$ghost->id = 900000 + random_int(1, 99999);
$ghost->email = 'erase-' . $ghost->id . '@example.org';

$panel = FormPanel::findOne(['title' => 'EV erasure panel']) ?: new FormPanel();
$panel->title = 'EV erasure panel';
$panel->contentcontainer_id = (int)$space->contentcontainer_id;
$panel->save(false);
$member = new FormPanelMember();
$member->panel_id = (int)$panel->id;
$member->user_id = (int)$ghost->id;
$member->first_name = 'Grace';
$member->email = $ghost->email;
$member->token = 'erase' . $ghost->id;
$member->status = FormPanelMember::STATUS_ACTIVE;
$member->save(false);

$form = ReviewLib::form($space, 'EV erasure completeness', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$note = ReviewLib::field($form, FormField::TYPE_TEXT, 'Note', ['variable' => 'erase_note', 'required' => 0]);
$form = ReviewLib::publishOpen($form);
$answer = ReviewLib::submit($form, [(int)$note->id => 'research']);
$check($answer instanceof FormAnswer, 'fixture answer did not save');
if (!$answer) {
    exit(1);
}
$answer->updateAttributes(['created_by' => null, 'panel_member_id' => (int)$member->id]);
$aid = (int)$answer->id;

$db->createCommand()->insert('{{%custom_form_consent_record}}', [
    'form_id' => (int)$form->id, 'answer_id' => $aid, 'user_id' => (int)$ghost->id, 'panel_member_id' => (int)$member->id,
    'document_id' => 0, 'signature_method' => 'typed', 'signature_name' => 'Grace Hopper', 'witness_name' => 'W',
    'signed_at' => date('Y-m-d H:i:s'), 'ip_hash' => str_repeat('c', 64),
])->execute();
$recordId = (int)$db->getLastInsertID();
$db->createCommand()->insert('{{%custom_form_consent_withdrawal}}', [
    'record_id' => $recordId, 'panel_member_id' => (int)$member->id, 'scope' => 'stop_contact',
    'reason' => 'I am Grace', 'created_at' => date('Y-m-d H:i:s'),
])->execute();
$db->createCommand()->insert('{{%form_email_send}}', [
    'form_id' => (int)$form->id, 'member_id' => (int)$member->id, 'answer_id' => $aid, 'kind' => 'completion',
    'email' => $ghost->email, 'created_at' => date('Y-m-d H:i:s'),
])->execute();
$db->createCommand()->insert('{{%custom_form_answer_audit}}', [
    'answer_id' => $aid, 'field_id' => (int)$note->id, 'old_value' => 'my phone is 07700 900000',
    'new_value' => 'research', 'actor_id' => (int)$ghost->id, 'reason' => 'edit', 'created_at' => date('Y-m-d H:i:s'),
])->execute();
$db->createCommand()->insert('custom_form_identity_repair_log', [
    'run_id' => 'erase' . $ghost->id, 'ran_at' => date('Y-m-d H:i:s'), 'answer_id' => $aid,
    'table_name' => 'custom_form_answer', 'row_id' => $aid, 'column_name' => 'created_by', 'old_value' => (string)$ghost->id,
])->execute();
$db->createCommand()->update('custom_form_integrity_meta', ['ip_hash' => str_repeat('d', 64)], ['answer_id' => $aid])->execute();

$count = (new ErasureService())->pseudonymiseUser($ghost);
$check($count === 1, 'the token answer was not found (count ' . $count . ')');
$answer->refresh();
$check($answer->panel_member_id === null, 'the answer kept its panel member');
$kept = (new Query())->from('custom_form_answer_field')->where(['answer_id' => $aid, 'field_id' => (int)$note->id])->one();
$check(is_array($kept) && str_contains((string)$kept['value'], 'research'), 'the research answer was removed');
$rec = (new Query())->from('{{%custom_form_consent_record}}')->where(['id' => $recordId])->one();
$check($rec && $rec['signature_name'] === null && $rec['witness_name'] === null && $rec['user_id'] === null
    && $rec['panel_member_id'] === null && $rec['ip_hash'] === null, 'the consent record kept identity');
$wd = (new Query())->from('{{%custom_form_consent_withdrawal}}')->where(['record_id' => $recordId])->one();
$check($wd && $wd['panel_member_id'] === null && $wd['reason'] === null, 'the withdrawal kept identity');
$send = (new Query())->from('{{%form_email_send}}')->where(['answer_id' => $aid])->one();
$check($send && $send['email'] === null && $send['member_id'] === null, 'the email log kept the address');
$audit = (new Query())->from('{{%custom_form_answer_audit}}')->where(['answer_id' => $aid])->one();
$check($audit && $audit['old_value'] === null && $audit['actor_id'] === null, 'the audit history kept old values or the actor');
$check(!(new Query())->from('custom_form_identity_repair_log')->where(['run_id' => 'erase' . $ghost->id])->exists(), 'the repair log kept the identity');
$meta = (new Query())->from('custom_form_integrity_meta')->where(['answer_id' => $aid])->one();
$check(!$meta || $meta['ip_hash'] === null, 'the integrity hash was kept');
$member->refresh();
$check($member->email === null && $member->user_id === null, 'the panel member kept identity');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
