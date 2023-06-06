<?php

namespace avelonnetwork\craftavelon\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use avelonnetwork\craftavelon\Plugin;


/**
 * Settings controller
 */
class SettingsController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * avelon/settings action
     * @return Response
     */
    public function actionGetSettings(): Response
    {
        $this->requireLogin();

        $variables = [
            'settings' => Plugin::getInstance()->avelonService->getSettings()
        ];

        return $this->renderTemplate('avelon/settings-index', $variables);
    }

    /**
     * avelon/settings/save-settings action
     *
     */
    public function actionSaveSettings()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $params = Craft::$app->request->getBodyParams();
        Plugin::getInstance()->avelonService->setSettings($params);
        Craft::$app->getSession()->setNotice("Settings saved successfully.");
    }
}
