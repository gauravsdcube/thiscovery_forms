<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerApproval;
use humhub\modules\thiscoveryForms\models\FormApprovalAuthority;
use humhub\modules\thiscoveryForms\models\FormApprovalStage;
use humhub\modules\thiscoveryForms\notifications\ProjectApprovalNeededNotification;
use humhub\modules\thiscoveryForms\notifications\ProjectStatusChangedNotification;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;
use Yii;

/**
 * Configurable multi-stage approval for Project forms.
 *
 * Each stage has named authorities (users and/or groups). require_all = any one
 * matching person can pass the stage; require_all = every listed seat must approve.
 * If a form has no stages, form managers can publish or request changes.
 */
class ApprovalWorkflowService
{
    /**
     * @return FormApprovalStage[]
     */
    public function listStages(CustomForm $form): array
    {
        return $form->getApprovalStages()->with(['authorities.user', 'authorities.group'])->all();
    }

    public function createStage(CustomForm $form, string $name = ''): FormApprovalStage
    {
        $max = (int)$form->getApprovalStages()->max('sort_order');
        $stage = new FormApprovalStage();
        $stage->form_id = $form->id;
        $stage->name = $name !== '' ? $name : Yii::t('ThiscoveryFormsModule.base', 'Stage {n}', [
            'n' => $form->getApprovalStages()->count() + 1,
        ]);
        $stage->sort_order = $max + 10;
        $stage->require_all = 0;
        $stage->save(false);
        return $stage;
    }

    public function saveStage(FormApprovalStage $stage, array $post): bool
    {
        $stage->name = trim((string)($post['name'] ?? $stage->name));
        if ($stage->name === '') {
            $stage->name = Yii::t('ThiscoveryFormsModule.base', 'Untitled stage');
        }
        $stage->require_all = !empty($post['require_all']) ? 1 : 0;
        if (!$stage->save()) {
            return false;
        }

        FormApprovalAuthority::deleteAll(['stage_id' => $stage->id]);

        $userGuids = $post['userGuids'] ?? [];
        if (is_string($userGuids)) {
            $decoded = json_decode($userGuids, true);
            $userGuids = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded
                : ($userGuids === '' ? [] : [$userGuids]);
        }
        if (!is_array($userGuids)) {
            $userGuids = [];
        }
        foreach ($userGuids as $guid) {
            $guid = trim((string)$guid);
            if ($guid === '') {
                continue;
            }
            $user = User::findOne(['guid' => $guid]);
            if (!$user) {
                continue;
            }
            $auth = new FormApprovalAuthority();
            $auth->stage_id = $stage->id;
            $auth->type = FormApprovalAuthority::TYPE_USER;
            $auth->user_id = (int)$user->id;
            $auth->save(false);
        }

        $groupIds = $post['groupIds'] ?? [];
        if (!is_array($groupIds)) {
            $groupIds = $groupIds === '' || $groupIds === null ? [] : [(int)$groupIds];
        }
        foreach ($groupIds as $groupId) {
            $groupId = (int)$groupId;
            if ($groupId < 1) {
                continue;
            }
            if (!Group::find()->where(['id' => $groupId])->exists()) {
                continue;
            }
            $auth = new FormApprovalAuthority();
            $auth->stage_id = $stage->id;
            $auth->type = FormApprovalAuthority::TYPE_GROUP;
            $auth->group_id = $groupId;
            $auth->save(false);
        }

        unset($stage->authorities);
        return true;
    }

    public function deleteStage(FormApprovalStage $stage): void
    {
        $formId = (int)$stage->form_id;
        FormAnswer::updateAll(
            ['current_stage_id' => null],
            ['current_stage_id' => $stage->id]
        );
        $stage->delete();
        $this->resequence($formId);
    }

    public function moveStage(FormApprovalStage $stage, string $direction): void
    {
        $stages = FormApprovalStage::find()
            ->where(['form_id' => $stage->form_id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
        $index = null;
        foreach ($stages as $i => $row) {
            if ((int)$row->id === (int)$stage->id) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($stages[$swapWith])) {
            return;
        }
        $a = $stages[$index];
        $b = $stages[$swapWith];
        $tmp = $a->sort_order;
        $a->sort_order = $b->sort_order;
        $b->sort_order = $tmp;
        $a->save(false, ['sort_order', 'updated_at']);
        $b->save(false, ['sort_order', 'updated_at']);
        $this->resequence((int)$stage->form_id);
    }

    public function resequence(int $formId): void
    {
        $i = 0;
        foreach (FormApprovalStage::find()->where(['form_id' => $formId])->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->all() as $stage) {
            $stage->sort_order = $i * 10;
            $stage->save(false, ['sort_order', 'updated_at']);
            $i++;
        }
    }

    public function copyStages(CustomForm $source, CustomForm $target): void
    {
        foreach ($this->listStages($source) as $sourceStage) {
            $stage = new FormApprovalStage();
            $stage->form_id = $target->id;
            $stage->name = $sourceStage->name;
            $stage->sort_order = $sourceStage->sort_order;
            $stage->require_all = $sourceStage->require_all;
            $stage->save(false);
            foreach ($sourceStage->authorities as $authority) {
                $copy = new FormApprovalAuthority();
                $copy->stage_id = $stage->id;
                $copy->type = $authority->type;
                $copy->user_id = $authority->user_id;
                $copy->group_id = $authority->group_id;
                $copy->save(false);
            }
        }
    }

    public function firstStage(CustomForm $form): ?FormApprovalStage
    {
        return $form->getApprovalStages()->one();
    }

    public function nextStage(FormApprovalStage $stage): ?FormApprovalStage
    {
        return FormApprovalStage::find()
            ->where(['form_id' => $stage->form_id])
            ->andWhere(['>', 'sort_order', $stage->sort_order])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->one();
    }

    /**
     * @return User[]
     */
    public function stageApproverUsers(FormApprovalStage $stage): array
    {
        $users = [];
        $authorities = $stage->authorities;
        if (!$authorities) {
            return $stage->form ? $stage->form->getNotificationTargets() : [];
        }
        foreach ($authorities as $authority) {
            if ($authority->isUser() && $authority->user && $authority->user->status == User::STATUS_ENABLED) {
                $users[$authority->user->id] = $authority->user;
            }
            if ($authority->isGroup() && $authority->group) {
                foreach ($authority->group->getUsers()->all() as $member) {
                    if ((int)$member->status === User::STATUS_ENABLED) {
                        $users[$member->id] = $member;
                    }
                }
            }
        }
        return array_values($users);
    }

    public function userMatchesAuthority(User $user, FormApprovalAuthority $authority): bool
    {
        if ($authority->isUser()) {
            return (int)$authority->user_id === (int)$user->id;
        }
        if ($authority->isGroup() && $authority->group_id) {
            return $user->getGroups()->andWhere(['group.id' => (int)$authority->group_id])->exists();
        }
        return false;
    }

    public function userCanActOnStage(User $user, ?FormApprovalStage $stage, CustomForm $form): bool
    {
        if ($form->canManage($user)) {
            return true;
        }
        if (!$stage) {
            return $form->canManage($user);
        }
        $authorities = $stage->authorities;
        if (!$authorities) {
            return $form->canManage($user);
        }
        foreach ($authorities as $authority) {
            if ($this->userMatchesAuthority($user, $authority)) {
                return true;
            }
        }
        return false;
    }

    public function canActOnAnswer(FormAnswer $answer, ?User $user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user || !$answer->form || !$answer->form->isProject()) {
            return false;
        }
        if (!$answer->isInReview() && !$answer->isChangesRequested()) {
            return $answer->form->canManage($user) && !$answer->isArchived();
        }
        return $this->userCanActOnStage($user, $answer->currentStage, $answer->form);
    }

    public function canViewRecord(FormAnswer $answer, ?User $user = null): bool
    {
        $form = $answer->form;
        if (!$form) {
            return false;
        }
        $user = $user ?: Yii::$app->user->getIdentity();
        if ($answer->isPublished()) {
            if (!$user) {
                return false;
            }
            return $form->canViewAnswers($user) || $form->canAnswerPermissionOnly($user) || $form->canManage($user);
        }
        if (!$user) {
            return false;
        }
        if ($form->canManage($user)) {
            return true;
        }
        if ((int)$answer->created_by === (int)$user->id) {
            return true;
        }
        return $this->userCanActOnStage($user, $answer->currentStage, $form);
    }

    public function submitForReview(FormAnswer $answer): void
    {
        $form = $answer->form;
        if (!$form || !$form->isProject() || $answer->isInProgress() || $answer->isAnonymous()) {
            return;
        }

        $user = Yii::$app->user->getIdentity();
        $isAuthor = $user && (int)$answer->created_by === (int)$user->id;
        if ($answer->isPublished() && $form->canManage($user) && !$isAuthor) {
            return;
        }

        $first = $this->firstStage($form);
        $answer->workflow_status = FormAnswer::WORKFLOW_IN_REVIEW;
        $answer->current_stage_id = $first ? (int)$first->id : null;
        $answer->submitted_at = date('Y-m-d H:i:s');
        $answer->save(false, ['workflow_status', 'current_stage_id', 'submitted_at', 'updated_at', 'updated_by']);

        $this->notifyApprovers($answer, $first);
    }

    public function approve(FormAnswer $answer, User $user, string $comment = ''): bool
    {
        if (!$this->canActOnAnswer($answer, $user) || $answer->isPublished() || $answer->isArchived()) {
            return false;
        }

        $stage = $answer->currentStage;
        $this->recordAction($answer, $stage, $user, FormAnswerApproval::ACTION_APPROVED, $comment);

        if ($stage && !$this->stageIsComplete($answer, $stage)) {
            return true;
        }

        $next = $stage ? $this->nextStage($stage) : null;
        if ($next) {
            $answer->workflow_status = FormAnswer::WORKFLOW_IN_REVIEW;
            $answer->current_stage_id = (int)$next->id;
            $answer->save(false, ['workflow_status', 'current_stage_id', 'updated_at', 'updated_by']);
            $this->notifyApprovers($answer, $next);
            return true;
        }

        $this->publish($answer, $user);
        return true;
    }

    public function requestChanges(FormAnswer $answer, User $user, string $comment = ''): bool
    {
        if (!$this->canActOnAnswer($answer, $user) || $answer->isArchived()) {
            return false;
        }
        $stage = $answer->currentStage;
        $this->recordAction($answer, $stage, $user, FormAnswerApproval::ACTION_CHANGES, $comment);
        $answer->workflow_status = FormAnswer::WORKFLOW_CHANGES_REQUESTED;
        $answer->save(false, ['workflow_status', 'updated_at', 'updated_by']);
        $this->notifyAuthor($answer, FormAnswer::WORKFLOW_CHANGES_REQUESTED, $comment);
        return true;
    }

    public function publish(FormAnswer $answer, ?User $user = null): void
    {
        $answer->workflow_status = FormAnswer::WORKFLOW_PUBLISHED;
        $answer->current_stage_id = null;
        $answer->save(false, ['workflow_status', 'current_stage_id', 'updated_at', 'updated_by']);
        $this->notifyAuthor($answer, FormAnswer::WORKFLOW_PUBLISHED);
    }

    public function archive(FormAnswer $answer, User $user): bool
    {
        if (!$answer->form || !$answer->form->canManage($user)) {
            return false;
        }
        $answer->workflow_status = FormAnswer::WORKFLOW_ARCHIVED;
        $answer->save(false, ['workflow_status', 'updated_at', 'updated_by']);
        return true;
    }

    public function stageIsComplete(FormAnswer $answer, FormApprovalStage $stage): bool
    {
        $since = $answer->submitted_at ?: $answer->created_at;
        $approvals = FormAnswerApproval::find()
            ->where([
                'answer_id' => $answer->id,
                'stage_id' => $stage->id,
                'action' => FormAnswerApproval::ACTION_APPROVED,
            ])
            ->andWhere(['>=', 'created_at', $since])
            ->all();

        if (!$stage->requiresAll()) {
            return count($approvals) > 0;
        }

        $authorities = $stage->authorities;
        if (!$authorities) {
            return count($approvals) > 0;
        }

        foreach ($authorities as $authority) {
            $satisfied = false;
            foreach ($approvals as $approval) {
                $actor = $approval->user;
                if ($actor && $this->userMatchesAuthority($actor, $authority)) {
                    $satisfied = true;
                    break;
                }
            }
            if (!$satisfied) {
                return false;
            }
        }
        return true;
    }

    protected function recordAction(
        FormAnswer $answer,
        ?FormApprovalStage $stage,
        User $user,
        string $action,
        string $comment
    ): void {
        if (!$stage) {
            $stage = $this->firstStage($answer->form);
        }
        if (!$stage) {
            $stage = $this->createStage($answer->form, Yii::t('ThiscoveryFormsModule.base', 'Review'));
        }
        $row = new FormAnswerApproval();
        $row->answer_id = $answer->id;
        $row->stage_id = $stage->id;
        $row->user_id = (int)$user->id;
        $row->action = $action;
        $row->comment = trim($comment) !== '' ? trim($comment) : null;
        $row->save(false);
        if (!$answer->current_stage_id) {
            $answer->current_stage_id = $stage->id;
        }
    }

    protected function notifyApprovers(FormAnswer $answer, ?FormApprovalStage $stage): void
    {
        $originator = Yii::$app->user->getIdentity() ?: $answer->user;
        if (!$originator) {
            return;
        }
        $targets = $stage
            ? $this->stageApproverUsers($stage)
            : ($answer->form ? $answer->form->getNotificationTargets() : []);
        $targets = array_filter(
            $targets,
            static fn(User $user) => (int)$user->id !== (int)$originator->id
                && (int)$user->id !== (int)$answer->created_by
        );
        if (!$targets) {
            return;
        }
        try {
            Yii::createObject(['class' => ProjectApprovalNeededNotification::class])
                ->from($originator)
                ->about($answer)
                ->sendBulk($targets);
        } catch (\Throwable $e) {
            Yii::error('Project approval notify failed: ' . $e->getMessage(), 'thiscovery-forms');
        }
    }

    protected function notifyAuthor(FormAnswer $answer, string $status, string $comment = ''): void
    {
        $author = $answer->user;
        $originator = Yii::$app->user->getIdentity();
        if (!$author || !$originator || (int)$author->id === (int)$originator->id) {
            return;
        }
        try {
            $notification = Yii::createObject(['class' => ProjectStatusChangedNotification::class]);
            $notification->status = $status;
            $notification->moderatorComment = $comment;
            $notification->from($originator)->about($answer)->send($author);
        } catch (\Throwable $e) {
            Yii::error('Project status notify failed: ' . $e->getMessage(), 'thiscovery-forms');
        }
    }
}
