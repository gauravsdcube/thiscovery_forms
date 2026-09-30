<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\thiscoveryForms\services\formula\Context;
use humhub\modules\thiscoveryForms\services\formula\Evaluator;
use humhub\modules\thiscoveryForms\services\formula\FormulaException;
use humhub\modules\thiscoveryForms\services\formula\FormulaRuntime;
use humhub\modules\thiscoveryForms\services\formula\Parser;
use humhub\modules\thiscoveryForms\services\formula\Value;
use Yii;
use yii\web\Response;

/**
 * Studio preview. The stored result is still calculated when the response is saved.
 */
class FormulaController extends ContentContainerController
{
    public function getAccessRules()
    {
        return [
            ['login' => ['preview']],
        ];
    }

    public function actionPreview()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
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
            foreach ($samples as $name => $raw) {
                if (!is_scalar($raw)) {
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
