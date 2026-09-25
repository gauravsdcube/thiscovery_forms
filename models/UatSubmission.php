<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\file\models\File;
use humhub\modules\user\models\User;
use Yii;
use yii\db\ActiveQuery;

/**
 * UAT test result or proposed new scenario from a tester.
 *
 * @property int $id
 * @property string $kind
 * @property string|null $test_id
 * @property string|null $feature
 * @property string|null $scenario
 * @property string|null $explanation
 * @property string|null $preconditions
 * @property string|null $steps
 * @property string|null $expected_behaviour
 * @property string|null $priority
 * @property string|null $roles
 * @property string|null $result
 * @property string|null $comments
 * @property string|null $tester_name
 * @property string|null $tester_email
 * @property string|null $environment_url
 * @property string|null $module_version
 * @property string|null $evidence_guid
 * @property string $status
 * @property string|null $admin_notes
 * @property string $created_at
 * @property int|null $created_by
 * @property string|null $updated_at
 * @property int|null $updated_by
 *
 * @property-read User|null $user
 * @property-read File|null $evidenceFile
 */
class UatSubmission extends ActiveRecord
{
    public const KIND_RESULT = 'result';
    public const KIND_PROPOSAL = 'proposal';

    public const RESULT_PASS = 'pass';
    public const RESULT_FAIL = 'fail';
    public const RESULT_BLOCKED = 'blocked';

    public const STATUS_NEW = 'new';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_CLOSED = 'closed';

    /** @var \yii\web\UploadedFile|null */
    public $evidenceUpload;

    public static function tableName()
    {
        return 'custom_form_uat_submission';
    }

    public function rules()
    {
        return [
            [['kind'], 'required'],
            [['kind'], 'in', 'range' => [self::KIND_RESULT, self::KIND_PROPOSAL]],
            [['result'], 'required',
                'when' => fn ($m) => $m->kind === self::KIND_RESULT,
                'whenClient' => 'function(){return $("input[name=\\"UatSubmission[kind]\\"]:checked").val()==="result";}',
            ],
            [['result'], 'in', 'range' => [self::RESULT_PASS, self::RESULT_FAIL, self::RESULT_BLOCKED], 'skipOnEmpty' => true],
            [['test_id'], 'required',
                'when' => fn ($m) => $m->kind === self::KIND_RESULT,
                'whenClient' => 'function(){return $("input[name=\\"UatSubmission[kind]\\"]:checked").val()==="result";}',
            ],
            [['feature', 'scenario'], 'required',
                'when' => fn ($m) => $m->kind === self::KIND_PROPOSAL,
                'whenClient' => 'function(){return $("input[name=\\"UatSubmission[kind]\\"]:checked").val()==="proposal";}',
            ],
            [['tester_name'], 'required',
                'when' => fn () => Yii::$app->user->isGuest,
                'whenClient' => Yii::$app->user->isGuest ? 'function(){return true;}' : 'function(){return false;}',
            ],
            [['tester_email'], 'email', 'skipOnEmpty' => true],
            [['test_id'], 'string', 'max' => 32],
            [['feature', 'roles'], 'string', 'max' => 128],
            [['scenario'], 'string', 'max' => 255],
            [['priority', 'result', 'status', 'kind'], 'string', 'max' => 16],
            [['module_version'], 'string', 'max' => 32],
            [['tester_name'], 'string', 'max' => 120],
            [['tester_email'], 'string', 'max' => 255],
            [['environment_url'], 'string', 'max' => 512],
            [['evidence_guid'], 'string', 'max' => 45],
            [['explanation', 'preconditions', 'steps', 'expected_behaviour', 'comments', 'admin_notes'], 'string'],
            [['status'], 'default', 'value' => self::STATUS_NEW],
            [['status'], 'in', 'range' => [self::STATUS_NEW, self::STATUS_REVIEWED, self::STATUS_CLOSED]],
            [['created_by', 'updated_by'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['evidenceUpload'], 'file', 'skipOnEmpty' => true, 'maxSize' => 10 * 1024 * 1024, 'extensions' => 'png, jpg, jpeg, gif, webp, pdf, txt, csv, log'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'kind' => Yii::t('ThiscoveryFormsModule.base', 'Submission type'),
            'test_id' => Yii::t('ThiscoveryFormsModule.base', 'Test ID'),
            'feature' => Yii::t('ThiscoveryFormsModule.base', 'Feature'),
            'scenario' => Yii::t('ThiscoveryFormsModule.base', 'Scenario'),
            'explanation' => Yii::t('ThiscoveryFormsModule.base', 'Explanation'),
            'preconditions' => Yii::t('ThiscoveryFormsModule.base', 'Preconditions'),
            'steps' => Yii::t('ThiscoveryFormsModule.base', 'Steps'),
            'expected_behaviour' => Yii::t('ThiscoveryFormsModule.base', 'Expected behaviour'),
            'priority' => Yii::t('ThiscoveryFormsModule.base', 'Priority'),
            'roles' => Yii::t('ThiscoveryFormsModule.base', 'Roles'),
            'result' => Yii::t('ThiscoveryFormsModule.base', 'Result'),
            'comments' => Yii::t('ThiscoveryFormsModule.base', 'Comments'),
            'tester_name' => Yii::t('ThiscoveryFormsModule.base', 'Tester name'),
            'tester_email' => Yii::t('ThiscoveryFormsModule.base', 'Tester email'),
            'environment_url' => Yii::t('ThiscoveryFormsModule.base', 'Environment URL'),
            'module_version' => Yii::t('ThiscoveryFormsModule.base', 'Module version'),
            'evidenceUpload' => Yii::t('ThiscoveryFormsModule.base', 'Evidence file'),
            'status' => Yii::t('ThiscoveryFormsModule.base', 'Review status'),
            'admin_notes' => Yii::t('ThiscoveryFormsModule.base', 'Admin notes'),
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    public function getEvidenceFile(): ?File
    {
        if (!$this->evidence_guid) {
            return null;
        }
        return File::findOne(['guid' => $this->evidence_guid]);
    }

    public static function resultLabels(): array
    {
        return [
            self::RESULT_PASS => Yii::t('ThiscoveryFormsModule.base', 'Pass'),
            self::RESULT_FAIL => Yii::t('ThiscoveryFormsModule.base', 'Fail'),
            self::RESULT_BLOCKED => Yii::t('ThiscoveryFormsModule.base', 'Blocked'),
        ];
    }

    public static function kindLabels(): array
    {
        return [
            self::KIND_RESULT => Yii::t('ThiscoveryFormsModule.base', 'Test result'),
            self::KIND_PROPOSAL => Yii::t('ThiscoveryFormsModule.base', 'Propose new test'),
        ];
    }

    public function beforeValidate()
    {
        if (!parent::beforeValidate()) {
            return false;
        }
        if ($this->kind === self::KIND_PROPOSAL) {
            $this->result = null;
        }
        return true;
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        if ($insert) {
            $this->created_at = $now;
            if (!Yii::$app->user->isGuest) {
                $this->created_by = (int)Yii::$app->user->id;
                if (!$this->tester_name) {
                    $this->tester_name = Yii::$app->user->identity->displayName ?? '';
                }
                if (!$this->tester_email && Yii::$app->user->identity) {
                    $this->tester_email = Yii::$app->user->identity->email ?? null;
                }
            }
        }
        $this->updated_at = $now;
        if (!Yii::$app->user->isGuest) {
            $this->updated_by = (int)Yii::$app->user->id;
        }
        return true;
    }
}
