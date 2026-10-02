<?php
/**
 * Prepare a fresh HumHub test database for the module tests (NEW-22): the two personas the
 * tests sign in as and the "Review Sandbox" space with this module enabled. Idempotent.
 *
 *   THISCOVERY_FORMS_TEST_DB=1 php protected/modules/thiscovery-forms/tests/support/seed.php
 */
require __DIR__ . '/bootstrap.php';

use humhub\modules\space\models\Space;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\Password;
use humhub\modules\user\models\User;

$ensureUser = static function (string $username, string $first, bool $admin): User {
    $user = User::findOne(['username' => $username]);
    if (!$user) {
        $user = new User();
        $user->scenario = 'editAdmin';
        $user->username = $username;
        $user->email = $username . '@example.test';
        $user->status = User::STATUS_ENABLED;
        $user->language = 'en-GB';
        if (!$user->save()) {
            throw new RuntimeException('Could not create ' . $username . ': ' . json_encode($user->getErrors()));
        }
        $user->profile->firstname = $first;
        $user->profile->lastname = 'Review';
        $user->profile->save(false);
        $password = new Password();
        $password->user_id = $user->id;
        $password->setPassword(Yii::$app->security->generateRandomString(24));
        $password->save(false);
    }
    if ($admin) {
        $group = Group::getAdminGroup();
        if ($group && !$group->isMember($user)) {
            $group->addUser($user);
        }
    }
    return $user;
};

$admin = $ensureUser('review_netadmin', 'Netadmin', true);
$respondent = $ensureUser('review_respondent', 'Respondent', false);

$space = Space::findOne(['name' => 'Review Sandbox']);
if (!$space) {
    Yii::$app->user->setIdentity($admin);
    $space = new Space();
    $space->name = 'Review Sandbox';
    $space->description = 'Module tests';
    $space->visibility = Space::VISIBILITY_REGISTERED_ONLY;
    $space->join_policy = Space::JOIN_POLICY_APPLICATION;
    $space->created_by = $admin->id;
    if (!$space->save()) {
        throw new RuntimeException('Could not create the space: ' . json_encode($space->getErrors()));
    }
}
if (!$space->isMember($admin->id)) {
    $space->addMember($admin->id);
}
if (!$space->isMember($respondent->id)) {
    $space->addMember($respondent->id);
}
if (!$space->moduleManager->isEnabled('thiscovery-forms')) {
    $space->moduleManager->enable('thiscovery-forms');
}

echo "Seeded: review_netadmin, review_respondent, Review Sandbox (space #{$space->id}).\n";
