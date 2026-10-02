<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\components\Controller;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\formula\Context;
use humhub\modules\thiscoveryForms\services\formula\Evaluator;
use humhub\modules\thiscoveryForms\services\formula\FormulaException;
use humhub\modules\thiscoveryForms\services\formula\FormulaRuntime;
use humhub\modules\thiscoveryForms\services\formula\Parser;
use humhub\modules\thiscoveryForms\services\formula\Value;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Studio preview. The stored result is still calculated when the response is saved.
 */
class FormulaController extends Controller
{
    protected function getAccessRules()
    {
        return [
            ['login'],
        ];
    }

    /** Preview calls allowed per user per minute (V3-4). */
    private const RATE_PER_MINUTE = 60;
    /** Sample values accepted per call, and the longest sample value. */
    private const MAX_SAMPLES = 200;
    private const MAX_SAMPLE_LENGTH = 200;

    public function actionPreview()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return ['ok' => false, 'error' => Yii::t('ThiscoveryFormsModule.base', 'Invalid request.')];
        }
        $formId = (int)Yii::$app->request->get('id', Yii::$app->request->post('form_id', 0));
        if ($formId > 0) {
            $form = CustomForm::findOne($formId);
            if (!$form || !$form->canManage()) {
                throw new ForbiddenHttpException();
            }
        }
        if ($this->rateLimited()) {
            return ['ok' => false, 'error' => Yii::t('ThiscoveryFormsModule.base', 'Too many previews. Please wait a minute and try again.')];
        }
        $text = trim((string)Yii::$app->request->post('formula', ''));
        if (mb_strlen($text) > 4000) {
            return ['ok' => false, 'error' => Yii::t('ThiscoveryFormsModule.base', 'A formula can be at most 4,000 characters.')];
        }
        $samples = json_decode((string)Yii::$app->request->post('values', '{}'), true);
        if (!is_array($samples)) {
            $samples = [];
        }
        try {
            $tree = (new Parser())->parse($text);
            $context = new Context();
            $context->today = FormulaRuntime::today();
            $samples = array_slice($samples, 0, self::MAX_SAMPLES, true);
            foreach ($samples as $name => $raw) {
                if (!is_scalar($raw) || mb_strlen((string)$raw) > self::MAX_SAMPLE_LENGTH) {
                    continue;
                }
                $context->fields[(string)$name] = Context::scalar($raw);
            }
            $value = (new Evaluator($context))->evaluate($tree);
            return [
                'ok' => true,
                'today' => $context->today,
                'result' => self::present($value),
            ];
        } catch (FormulaException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function rateLimited(): bool
    {
        $key = 'cf-formula-preview-' . (int)Yii::$app->user->id . '-' . intdiv(time(), 60);
        $cache = Yii::$app->cache;
        $count = (int)$cache->get($key) + 1;
        $cache->set($key, $count, 120);
        return $count > self::RATE_PER_MINUTE;
    }

    private static function present(Value $value): string
    {
        if ($value->isEmpty()) {
            return '';
        }
        if ($value->type === 'bool') {
            return $value->data ? 'true' : 'false';
        }
        return (string)$value->data;
    }
}
