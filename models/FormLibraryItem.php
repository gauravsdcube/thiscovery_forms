<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\thiscoveryForms\permissions\CreateForm;
use humhub\modules\thiscoveryForms\permissions\CreateGlobalForm;
use humhub\modules\space\models\Space;
use humhub\modules\user\components\PermissionManager;
use Yii;
use yii\db\ActiveQuery;

/**
 * Reusable question, block, or form-template payload for the builder library.
 *
 * @property int $id
 * @property int|null $contentcontainer_id
 * @property int|null $created_by
 * @property string $type
 * @property string $title
 * @property string $payload_json
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class FormLibraryItem extends ActiveRecord
{
    public const TYPE_QUESTION = 'question';
    public const TYPE_BLOCK = 'block';
    public const TYPE_FORM_TEMPLATE = 'form_template';

    public static function tableName()
    {
        return 'form_library_item';
    }

    public function rules()
    {
        return [
            [['type', 'title', 'payload_json'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['payload_json'], 'string'],
            [['contentcontainer_id', 'created_by'], 'integer'],
            [['type'], 'in', 'range' => array_keys(self::getTypeLabels())],
        ];
    }

    public function attributeLabels()
    {
        return [
            'title' => Yii::t('ThiscoveryFormsModule.base', 'Title'),
            'type' => Yii::t('ThiscoveryFormsModule.base', 'Type'),
        ];
    }

    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_QUESTION => Yii::t('ThiscoveryFormsModule.base', 'Question template'),
            self::TYPE_BLOCK => Yii::t('ThiscoveryFormsModule.base', 'Question block'),
            self::TYPE_FORM_TEMPLATE => Yii::t('ThiscoveryFormsModule.base', 'Form template'),
        ];
    }

    public function getPayload(): array
    {
        $decoded = json_decode((string)$this->payload_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setPayload(array $payload): void
    {
        $this->payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Field post-rows stored in the payload.
     * @return array<string,array>
     */
    public function getFieldRows(): array
    {
        $payload = $this->getPayload();
        $fields = $payload['fields'] ?? [];
        return is_array($fields) ? $fields : [];
    }

    public function isGlobal(): bool
    {
        return empty($this->contentcontainer_id);
    }

    public function canManage($user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return false;
        }
        if ((int)$this->created_by === (int)$user->id) {
            return true;
        }
        return self::canCreateInContainer($this->contentcontainer_id, $user);
    }

    public static function canCreateInContainer($containerId, $user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return false;
        }
        if (empty($containerId)) {
            return (new PermissionManager(['subject' => $user]))->can(CreateGlobalForm::class);
        }
        $space = Space::findOne(['contentcontainer_id' => (int)$containerId]);
        if (!$space) {
            return false;
        }
        return $space->getPermissionManager($user)->can(CreateForm::class);
    }

    /**
     * Library items visible while editing a form in this container (space items + global).
     * @return static[]
     */
    public static function findAvailable(?int $containerId): array
    {
        $query = static::find()->orderBy(['title' => SORT_ASC, 'id' => SORT_ASC]);
        if ($containerId) {
            $query->andWhere([
                'or',
                ['contentcontainer_id' => $containerId],
                ['contentcontainer_id' => null],
            ]);
        } else {
            $query->andWhere(['contentcontainer_id' => null]);
        }
        return $query->all();
    }
}
