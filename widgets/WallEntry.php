<?php

namespace humhub\modules\thiscoveryForms\widgets;

use humhub\modules\content\widgets\stream\WallStreamModuleEntryWidget;
use humhub\modules\thiscoveryForms\models\CustomForm;

/**
 * @property CustomForm $model
 */
class WallEntry extends WallStreamModuleEntryWidget
{
    public $editRoute = '/thiscovery-forms/form/edit';
    public $createFormSortOrder = 250;

    public function renderContent()
    {
        return $this->render('@thiscovery-forms/views/widgets/wall-entry', [
            'formModel' => $this->model,
        ]);
    }

    protected function getTitle()
    {
        return $this->model->title;
    }

    public function getEditUrl()
    {
        if ($this->model->isGlobal()) {
            return null;
        }
        if (!$this->model->canManage()) {
            return null;
        }
        return $this->model->content->container->createUrl($this->editRoute, ['id' => $this->model->id]);
    }
}
