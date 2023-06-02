<?php

namespace avelonnetwork\craftavelon;

use Craft;
use avelonnetwork\craftavelon\services\AvelonService;
use craft\base\Plugin as BasePlugin;
use craft\commerce\elements\Order;
use craft\events\RegisterUrlRulesEvent;
use craft\events\TemplateEvent;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;

/**
 * Avelon plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Avelon Network <roland@avelonnetwork.com>
 * @copyright Avelon Network
 * @license MIT
 * @property-read SettingsService $settingsService
 * @property-read AvelonService $avelonService
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = false;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => ['settingsService' => SettingsService::class, 'avelonService' => AvelonService::class],
        ];
    }

    public function init()
    {
        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function () {
            $this->attachEventHandlers();
        });

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['avelon'] = 'avelon/settings/get-settings';
            }
        );
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_TEMPLATE,
            function (TemplateEvent $event) {
                if (!Craft::$app->getRequest()->getIsCpRequest()) {
                    $settings = $this->avelonService->getSettings();
                    if ($settings) {
                        $accountId = $settings['accountId'];

                        if ($accountId) {
                            Craft::$app->view->registerJsFile('https://' . $accountId . '.avln.me/t.js', ['position' => Craft::$app->view::POS_HEAD, 'async' => true, 'defer' => true]);
                        }
                    }
                }
            }
        );

        Event::on(
            Order::class,
            Order::EVENT_AFTER_COMPLETE_ORDER,
            function (Event $event) {
                // get and format order data
                $data = $this->avelonService->formatOrder($event);

                if ($data && $data !== []) {
                    // post the data to the api
                    $this->avelonService->postToApi($data);
                } else {
                    Craft::error('Avelon: Order data could not be formatted', 'Avelon Plugin Error');
                }
            }
        );
    }
}
