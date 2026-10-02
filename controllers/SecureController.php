<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\components\Controller;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\SecureCode;
use humhub\modules\thiscoveryForms\models\SecureRelease;
use humhub\modules\thiscoveryForms\services\SecureSendService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * Prepared files for a named contact. The public actions do not require an account.
 */
class SecureController extends Controller
{
    protected function getAccessRules()
    {
        return [
            ['login' => [
                'lifetime', 'prepare', 'prepare-draft', 'upload', 'contact',
                'send-code', 'revoke-code', 'rotate-link', 'revoke',
            ]],
            ['guestAccess' => ['open', 'request-code', 'redeem', 'download']],
        ];
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (in_array($action->id, ['open', 'request-code', 'redeem', 'download'], true)) {
            $headers = Yii::$app->response->headers;
            $headers->set('Referrer-Policy', 'no-referrer');
            $headers->set('Cache-Control', 'no-store, private');
            $headers->set('X-Content-Type-Options', 'nosniff');
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        return true;
    }

    public function actionLifetime($id)
    {
        $form = $this->managedForm($id);
        $this->requirePost();
        $minutes = (int)Yii::$app->request->post('minutes', SecureSendService::DEFAULT_MINUTES);
        SecureSendService::saveMinutes($form, $minutes);
        $this->view->success(Yii::t('ThiscoveryFormsModule.base', 'The code lifetime was saved.'));
        return $this->back($form);
    }

    public function actionPrepareDraft($id)
    {
        $form = $this->managedForm($id);
        $this->requirePost();
        Yii::$app->session->set('cfSecureDraft', [
            'formId' => (int)$form->id,
            'params' => SecureSendService::exportParams(Yii::$app->request->post()),
        ]);
        return $this->back($form);
    }

    public function actionPrepare($id)
    {
        $form = $this->managedForm($id);
        $this->requirePost();
        $svc = new SecureSendService();
        $created = $svc->createFromExport(
            $form,
            (string)Yii::$app->request->post('label', ''),
            (string)Yii::$app->request->post('contact_name', ''),
            (string)Yii::$app->request->post('contact_email', ''),
            SecureSendService::exportParams(Yii::$app->request->post()),
            $this->actorId(),
            $this->meta()
        );
        if ($created === null) {
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'The file could not be prepared. Check the name, the email address, and try again.'));
            return $this->back($form);
        }
        Yii::$app->session->remove('cfSecureDraft');
        $svc->rememberLink((int)$created['release']->id, $created['link']);
        $this->view->success(Yii::t('ThiscoveryFormsModule.base', 'The file is ready. Copy the link now. It will not be shown again.'));
        return $this->back($form);
    }

    public function actionUpload($id)
    {
        $form = $this->managedForm($id);
        $this->requirePost();
        $file = UploadedFile::getInstanceByName('secure_file');
        if (!$file || !is_uploaded_file($file->tempName)) {
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'Choose a file to prepare. CSV, Excel, PDF, JSON, text, and zip files up to 20 MB are accepted.'));
            return $this->back($form);
        }
        $svc = new SecureSendService();
        $created = $svc->createFromUpload(
            $form,
            (string)Yii::$app->request->post('label', ''),
            (string)Yii::$app->request->post('contact_name', ''),
            (string)Yii::$app->request->post('contact_email', ''),
            $file->tempName,
            $file->name,
            $this->actorId(),
            $this->meta()
        );
        if ($created === null) {
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'The file could not be prepared. Check the name, the email address, and that the file is an allowed type under 20 MB.'));
            return $this->back($form);
        }
        $svc->rememberLink((int)$created['release']->id, $created['link']);
        $this->view->success(Yii::t('ThiscoveryFormsModule.base', 'The file is ready. Copy the link now. It will not be shown again.'));
        return $this->back($form);
    }

    public function actionContact($id, $releaseId)
    {
        $form = $this->managedForm($id);
        $this->requirePost();
        $release = $this->release($form, (int)$releaseId);
        $svc = new SecureSendService();
        $result = $svc->changeContact(
            $release,
            (string)Yii::$app->request->post('contact_name', ''),
            (string)Yii::$app->request->post('contact_email', ''),
            $this->actorId(),
            $this->meta()
        );
        if ($result === null) {
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'The contact could not be saved. Check the name and email address.'));
            return $this->back($form);
        }
        if (!empty($result['link'])) {
            $svc->rememberLink((int)$release->id, (string)$result['link']);
            $this->view->success(Yii::t('ThiscoveryFormsModule.base', 'The contact was updated. Copy the new link. The previous link and code no longer work.'));
        } else {
            $this->view->success(Yii::t('ThiscoveryFormsModule.base', 'The contact name was saved.'));
        }
        return $this->back($form);
    }

    public function actionSendCode($id, $releaseId)
    {
        $form = $this->managedForm($id);
        $this->requirePost();
        $release = $this->release($form, (int)$releaseId);
        $result = (new SecureSendService())->sendCode($release, SecureCode::SOURCE_MANAGER, $this->actorId(), $this->meta());
        $this->flashSend($result);
        return $this->back($form);
    }

    public function actionRevokeCode($id, $releaseId)
    {
        $form = $this->managedForm($id);
        $this->requirePost();
        $release = $this->release($form, (int)$releaseId);
        (new SecureSendService())->revokeCodes($release, $this->actorId(), $this->meta());
        $this->view->success(Yii::t('ThiscoveryFormsModule.base', 'The current code was revoked. The file is still available when you send a new code.'));
        return $this->back($form);
    }

    public function actionRotateLink($id, $releaseId)
    {
        $form = $this->managedForm($id);
        $this->requirePost();
        $release = $this->release($form, (int)$releaseId);
        $svc = new SecureSendService();
        $link = $svc->rotateLink($release, $this->actorId(), $this->meta());
        if ($link === null) {
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'A new link could not be issued.'));
            return $this->back($form);
        }
        $svc->rememberLink((int)$release->id, $link);
        $this->view->success(Yii::t('ThiscoveryFormsModule.base', 'Copy the new link. The previous link and code no longer work. Send a new code when the contact is ready.'));
        return $this->back($form);
    }

    public function actionRevoke($id, $releaseId)
    {
        $form = $this->managedForm($id);
        $this->requirePost();
        $release = $this->release($form, (int)$releaseId);
        (new SecureSendService())->revokeRelease($release, $this->actorId(), $this->meta());
        $this->view->success(Yii::t('ThiscoveryFormsModule.base', 'The file was revoked. The link no longer works. The audit is kept.'));
        return $this->back($form);
    }

    public function actionOpen($t = '')
    {
        $release = (new SecureSendService())->findByToken((string)$t);
        if (!$release) {
            return $this->render('message', [
                'message' => Yii::t('ThiscoveryFormsModule.base', 'This link is not available.'),
            ]);
        }
        return $this->render('open', [
            'token' => (string)$t,
            'masked' => SecureSendService::maskEmail((string)$release->contact_email),
        ]);
    }

    public function actionRequestCode()
    {
        $this->requirePost();
        $token = (string)Yii::$app->request->post('t', '');
        $svc = new SecureSendService();
        $release = $svc->findByToken($token);
        if (!$release) {
            return $this->unavailable();
        }
        $result = $svc->sendCode($release, SecureCode::SOURCE_CONTACT, null, $this->meta());
        if ($result === 'sent') {
            $this->view->success(Yii::t(
                'ThiscoveryFormsModule.base',
                'A code was sent to {email}. It works once.',
                ['email' => SecureSendService::maskEmail((string)$release->contact_email)]
            ));
        } elseif ($result === 'cooldown') {
            $this->view->warn(Yii::t('ThiscoveryFormsModule.base', 'A code was sent recently. Wait a few minutes before asking for another.'));
        } else {
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'The code could not be sent. Try again shortly.'));
        }
        return $this->redirect(['open', 't' => $token]);
    }

    public function actionRedeem()
    {
        $this->requirePost();
        $token = (string)Yii::$app->request->post('t', '');
        $svc = new SecureSendService();
        $release = $svc->findByToken($token);
        if (!$release) {
            return $this->unavailable();
        }
        $result = $svc->redeem($release, (string)Yii::$app->request->post('code', ''), $this->meta());
        if ($result === 'ok') {
            SecureSendService::grant((int)$release->id);
            return $this->redirect(['download']);
        }
        if ($result === 'locked') {
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'Too many attempts. Request a new code, or ask the sender to send one.'));
        } else {
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'That code is not valid. You can request a new code.'));
        }
        return $this->redirect(['open', 't' => $token]);
    }

    public function actionDownload()
    {
        $id = SecureSendService::takeGrant();
        $release = $id ? SecureRelease::findOne($id) : null;
        $svc = new SecureSendService();
        $path = $release instanceof SecureRelease && SecureSendService::enabled() && $release->isActive()
            ? $svc->storedPath($release)
            : null;
        if (!$path || !$release instanceof SecureRelease) {
            return $this->render('message', [
                'message' => Yii::t('ThiscoveryFormsModule.base', 'This download has finished. Open your link and request a new code.'),
            ]);
        }
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return $this->unavailable();
        }
        $svc->recordDownload($release, $this->meta());
        $name = (string)$release->original_name;
        $response = Yii::$app->response->sendStreamAsFile($fh, $name !== '' ? $name : 'download', [
            'mimeType' => 'application/octet-stream',
            'inline' => false,
        ]);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }

    private function managedForm($id): CustomForm
    {
        if (!SecureSendService::enabled()) {
            throw new NotFoundHttpException();
        }
        $form = CustomForm::findOne((int)$id);
        CustomForm::assertNotTrashed($form);
        if (!$form || !$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        return $form;
    }

    private function release(CustomForm $form, int $releaseId): SecureRelease
    {
        $release = SecureRelease::findOne(['id' => $releaseId, 'form_id' => (int)$form->id]);
        if (!$release) {
            throw new NotFoundHttpException();
        }
        return $release;
    }

    private function requirePost(): void
    {
        if (!Yii::$app->request->isPost) {
            throw new NotFoundHttpException();
        }
    }

    private function back(CustomForm $form)
    {
        return $this->redirect(Url::toEdit($form, ['tab' => 'settings', 'section' => 'secure']));
    }

    private function unavailable()
    {
        return $this->render('message', [
            'message' => Yii::t('ThiscoveryFormsModule.base', 'This link is not available.'),
        ]);
    }

    private function flashSend(string $result): void
    {
        if ($result === 'sent') {
            $this->view->success(Yii::t('ThiscoveryFormsModule.base', 'A new code was sent to the contact. The previous code no longer works.'));
            return;
        }
        if ($result === 'mail') {
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'The code could not be emailed. Nothing was changed.'));
            return;
        }
        $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'A code could not be sent for this file.'));
    }

    private function actorId(): ?int
    {
        return Yii::$app->user->isGuest ? null : (int)Yii::$app->user->id;
    }

    /**
     * @return array{ip:string,user_agent:string,accept_language:string}
     */
    private function meta(): array
    {
        $request = Yii::$app->request;
        return [
            'ip' => (string)$request->userIP,
            'user_agent' => (string)$request->userAgent,
            'accept_language' => (string)$request->headers->get('Accept-Language', ''),
        ];
    }
}
