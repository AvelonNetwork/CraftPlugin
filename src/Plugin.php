<?php

namespace avelonnetwork\craftavelon;

use Craft;
use avelonnetwork\craftavelon\models\Settings;
use avelonnetwork\craftavelon\services\AvelonService;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\commerce\elements\Order;
use craft\events\TemplateEvent;
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
 * @property-read settings $settings
 * @property-read SettingsService $settingsService
 * @property-read AvelonService $avelonService
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => ['settings' => settings::class, 'settingsService' => SettingsService::class, 'avelonService' => AvelonService::class],
        ];
    }

    public function init()
    {
        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function () {
            $this->attachEventHandlers();
            // ...
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('avelon/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_TEMPLATE,
            function (TemplateEvent $event) {
                $accountId = $this->getSettings()->accountId;

                if ($accountId) {
                    Craft::$app->view->registerJsFile('https://' . $accountId . '.avln.me/t.js', ['position' => Craft::$app->view::POS_HEAD]);
                }
            }
        );

        Event::on(
            Order::class,
            Order::EVENT_AFTER_COMPLETE_ORDER,
            function (Event $event) {
                $accountId = $this->getSettings()->accountId;

                // get the avln_cid cookie value if it exists
                $avlnCid = $this->avelonService->getAvelonCookie();

                // format the order data into required schema
                $formatedOrder = $this->avelonService->formatOrder($event);


                // if ($avlnCid || $promo_code) {
                //     # code...
                // }


                if ($accountId) {
                    # post to avelon api endpoint
                }

                // if respsonse is anything but a 201, log the error
                Craft::info('My first log message!', 'Avelon Plugin Message');
            }
        );
    }
}
