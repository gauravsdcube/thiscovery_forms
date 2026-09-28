<?php

use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;

function review_space(): Space
{
    $space = Space::findOne(['name' => 'Review Sandbox']);
    if (!$space) {
        throw new RuntimeException('Review Sandbox space is not on this database.');
    }
    return $space;
}

function review_user(string $username): User
{
    $user = User::findOne(['username' => $username]);
    if (!$user) {
        throw new RuntimeException('Missing user ' . $username);
    }
    return $user;
}
