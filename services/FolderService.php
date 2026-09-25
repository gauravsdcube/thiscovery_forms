<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormFolder;
use humhub\modules\thiscoveryForms\models\FormFolderAcl;
use humhub\modules\thiscoveryForms\permissions\CreateForm;
use humhub\modules\thiscoveryForms\permissions\CreateGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ManageForm;
use humhub\modules\thiscoveryForms\permissions\ManageGlobalForm;
use humhub\modules\content\models\Content;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;
use Yii;
use yii\db\Expression;

class FolderService
{
    public const ACCESS_VIEW = 'view';
    public const ACCESS_CREATE = 'create';
    public const ACCESS_MANAGE = 'manage';

    public static function isFolderAdmin($container = null, ?User $user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return false;
        }
        if (Yii::$app->user->isAdmin() || Yii::$app->user->can(ManageModules::class)) {
            return true;
        }
        if ($container instanceof Space) {
            return $container->getPermissionManager($user)->can(ManageForm::class);
        }
        return Yii::$app->user->can(ManageGlobalForm::class);
    }

    public static function canCreateFolders($container = null, ?User $user = null): bool
    {
        if (self::isFolderAdmin($container, $user)) {
            return true;
        }
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return false;
        }
        if ($container instanceof Space) {
            return $container->getPermissionManager($user)->can(CreateForm::class);
        }
        return Yii::$app->user->can(CreateGlobalForm::class);
    }

    public static function can($folder, string $access, ?User $user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user || !$folder instanceof FormFolder) {
            return false;
        }
        $container = self::containerFromFolder($folder);
        if (self::isFolderAdmin($container, $user)) {
            return true;
        }

        $effective = self::effectiveAclFolder($folder);
        if ($effective === null) {
            return $access === self::ACCESS_VIEW
                || ($access === self::ACCESS_CREATE && self::canCreateFolders($container, $user));
        }

        $groupIds = [];
        foreach ($user->groups as $group) {
            $groupIds[] = (int)$group->id;
        }
        foreach ($effective->acls as $acl) {
            $match = ((int)$acl->user_id && (int)$acl->user_id === (int)$user->id)
                || ((int)$acl->group_id && in_array((int)$acl->group_id, $groupIds, true));
            if (!$match) {
                continue;
            }
            if ($access === self::ACCESS_VIEW && (int)$acl->can_view) {
                return true;
            }
            if ($access === self::ACCESS_CREATE && ((int)$acl->can_create || (int)$acl->can_manage)) {
                return true;
            }
            if ($access === self::ACCESS_MANAGE && (int)$acl->can_manage) {
                return true;
            }
        }
        return false;
    }

    public static function canView($folder, ?User $user = null): bool
    {
        return self::can($folder, self::ACCESS_VIEW, $user);
    }

    public static function canCreateIn($folder, ?User $user = null): bool
    {
        if ($folder === null) {
            return self::canCreateFolders(null, $user);
        }
        return self::can($folder, self::ACCESS_CREATE, $user) || self::can($folder, self::ACCESS_MANAGE, $user);
    }

    public static function canManage($folder, ?User $user = null): bool
    {
        return self::can($folder, self::ACCESS_MANAGE, $user);
    }

    /**
     * Folder whose ACL actually applies (walk inherit chain). Null means open/inherited to root.
     */
    public static function effectiveAclFolder(FormFolder $folder): ?FormFolder
    {
        $current = $folder;
        $guard = 0;
        while ($current && $guard < FormFolder::MAX_DEPTH + 2) {
            if (!(int)$current->inherit_acl) {
                return $current;
            }
            if (!$current->parent_id) {
                return null;
            }
            $current = $current->parent;
            $guard++;
        }
        return null;
    }

    public static function containerFromFolder(FormFolder $folder)
    {
        if (!$folder->contentcontainer_id) {
            return null;
        }
        $space = Space::find()->where(['contentcontainer_id' => $folder->contentcontainer_id])->one();
        return $space ?: null;
    }

    /**
     * @return int[]
     */
    public static function visibleFolderIds($container = null, ?User $user = null): array
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        $ids = [];
        foreach (FormFolder::findForContainer($container)->with(['acls', 'parent'])->all() as $folder) {
            if (self::canView($folder, $user)) {
                $ids[] = (int)$folder->id;
            }
        }
        return $ids;
    }

    /**
     * @return FormFolder[]
     */
    public static function visibleChildren($container, $parentId, ?User $user = null): array
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        $query = FormFolder::findForContainer($container);
        if ($parentId) {
            $query->andWhere(['parent_id' => (int)$parentId]);
        } else {
            $query->andWhere(['parent_id' => null]);
        }
        $out = [];
        foreach ($query->all() as $folder) {
            if (self::canView($folder, $user)) {
                $out[] = $folder;
            }
        }
        return $out;
    }

    /**
     * @return array<int, array{folder: FormFolder, depth: int}>
     */
    public static function walkVisible($container = null, ?User $user = null, $parentId = null, int $depth = 0): array
    {
        $out = [];
        foreach (self::visibleChildren($container, $parentId, $user) as $folder) {
            $out[] = ['folder' => $folder, 'depth' => $depth];
            $out = array_merge($out, self::walkVisible($container, $user, (int) $folder->id, $depth + 1));
        }
        return $out;
    }

    /**
     * Live form counts keyed by folder id (0 = unfiled).
     * @return array<int, int>
     */
    public static function liveCountsByFolder($container = null): array
    {
        $query = (new \yii\db\Query())
            ->from(['custom_form' => CustomForm::tableName()])
            ->innerJoin(
                ['content' => Content::tableName()],
                'content.object_id = custom_form.id AND content.object_model = :model',
                [':model' => CustomForm::class]
            )
            ->andWhere(['custom_form.is_template' => 0])
            ->andWhere(['<>', 'content.state', Content::STATE_DELETED])
            ->select(['custom_form.folder_id', 'cnt' => new Expression('COUNT(*)')])
            ->groupBy(['custom_form.folder_id']);
        if ($container) {
            $query->andWhere(['content.contentcontainer_id' => $container->contentcontainer_id]);
        } else {
            $query->andWhere(['content.contentcontainer_id' => null]);
        }
        $out = [];
        try {
            foreach ($query->all() as $row) {
                $out[(int) ($row['folder_id'] ?? 0)] = (int) $row['cnt'];
            }
        } catch (\Throwable $e) {
            Yii::error($e->getMessage(), 'thiscovery-forms');
        }
        return $out;
    }

    /**
     * Nested options for <select>: id => "Parent / Child"
     * @return array<int,string>
     */
    public static function treeOptions($container = null, ?User $user = null, string $access = self::ACCESS_VIEW, ?int $excludeId = null): array
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        $options = [];
        $walk = function (array $folders, string $prefix) use (&$walk, &$options, $user, $access, $excludeId) {
            foreach ($folders as $folder) {
                /** @var FormFolder $folder */
                if ($excludeId && ((int)$folder->id === $excludeId || $folder->isDescendantOf($excludeId))) {
                    continue;
                }
                if (!self::can($folder, $access, $user) && $access !== self::ACCESS_VIEW) {
                    if ($access === self::ACCESS_CREATE && !self::canView($folder, $user)) {
                        continue;
                    }
                    if ($access === self::ACCESS_MANAGE) {
                        $walk($folder->children, $prefix . $folder->name . ' / ');
                        continue;
                    }
                }
                if ($access === self::ACCESS_VIEW && !self::canView($folder, $user)) {
                    continue;
                }
                if ($access === self::ACCESS_CREATE && !self::canCreateIn($folder, $user) && !self::canManage($folder, $user)) {
                    $walk($folder->children, $prefix . $folder->name . ' / ');
                    continue;
                }
                if ($access === self::ACCESS_MANAGE && !self::canManage($folder, $user)) {
                    $walk($folder->children, $prefix . $folder->name . ' / ');
                    continue;
                }
                $options[(int)$folder->id] = $prefix . $folder->name;
                $walk($folder->children, $prefix . $folder->name . ' / ');
            }
        };
        $roots = FormFolder::findForContainer($container)->andWhere(['parent_id' => null])->with('children')->all();
        $walk($roots, '');
        return $options;
    }

    /**
     * @return array{folder: ?FormFolder, children: FormFolder[], crumbs: FormFolder[], tree: array, canManageFolders: bool, folderId: int}
     */
    public static function browse($container, array $params, ?User $user = null): array
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        $folderId = (int)($params['folder'] ?? 0);
        $folder = null;
        if ($folderId > 0) {
            $folder = FormFolder::findForContainer($container)->andWhere(['id' => $folderId])->one();
            if ($folder && !self::canView($folder, $user)) {
                $folder = null;
                $folderId = 0;
            } elseif (!$folder) {
                $folderId = 0;
            }
        }

        return [
            'folder' => $folder,
            'folderId' => $folder ? (int)$folder->id : 0,
            'children' => self::visibleChildren($container, $folder ? (int)$folder->id : 0, $user),
            'crumbs' => $folder ? array_merge($folder->getAncestors(), [$folder]) : [],
            'tree' => self::treeOptions($container, $user, self::ACCESS_CREATE),
            'viewTree' => self::treeOptions($container, $user, self::ACCESS_VIEW),
            'moveTree' => self::treeOptions($container, $user, self::ACCESS_CREATE),
            'canManageFolders' => $folder
                ? self::canManage($folder, $user)
                : self::isFolderAdmin($container, $user) || self::canCreateFolders($container, $user),
            'canCreateFolder' => $folder
                ? self::canManage($folder, $user)
                : self::isFolderAdmin($container, $user) || self::canCreateFolders($container, $user),
        ];
    }

    public static function applyListFilter($query, $container, array $filters, ?User $user = null)
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return $query;
        }

        if (!self::isFolderAdmin($container, $user)) {
            $visible = self::visibleFolderIds($container, $user);
            $query->andWhere([
                'or',
                ['custom_form.folder_id' => null],
                ['custom_form.folder_id' => $visible ?: [0]],
                ['content.created_by' => (int)$user->id],
            ]);
        }

        $folderId = (int)($filters['folder'] ?? 0);
        if ($folderId > 0) {
            $query->andWhere(['custom_form.folder_id' => $folderId]);
        } else {
            $query->andWhere(['custom_form.folder_id' => null]);
        }

        return $query;
    }

    /**
     * @param array<int,array{view?:int,create?:int,manage?:int}> $groupFlags groupId => flags
     */
    public static function saveAcl(FormFolder $folder, array $groupFlags): void
    {
        FormFolderAcl::deleteAll(['folder_id' => $folder->id]);
        if ((int)$folder->inherit_acl) {
            return;
        }
        foreach ($groupFlags as $groupId => $flags) {
            $groupId = (int)$groupId;
            if ($groupId < 1 || !Group::find()->where(['id' => $groupId])->exists()) {
                continue;
            }
            $view = !empty($flags['view']);
            $create = !empty($flags['create']);
            $manage = !empty($flags['manage']);
            if (!$view && !$create && !$manage) {
                continue;
            }
            $acl = new FormFolderAcl([
                'folder_id' => $folder->id,
                'group_id' => $groupId,
                'can_view' => $view || $create || $manage ? 1 : 0,
                'can_create' => $create || $manage ? 1 : 0,
                'can_manage' => $manage ? 1 : 0,
            ]);
            $acl->save(false);
        }
    }

    /**
     * @return Group[]
     */
    public static function permissionGroups(): array
    {
        return Group::find()->orderBy(['name' => SORT_ASC])->all();
    }

    public static function formCount(FormFolder $folder): int
    {
        return (int)CustomForm::find()->where(['folder_id' => $folder->id, 'is_template' => 0])->count();
    }

    public static function childCount(FormFolder $folder): int
    {
        return (int)$folder->getChildren()->count();
    }
}
