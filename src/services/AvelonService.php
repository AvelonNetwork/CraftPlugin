<?php

namespace avelonnetwork\craftavelon\services;

use avelonnetwork\craftavelon\records\SettingsRecord;
use yii\base\Event;

use Craft;
use yii\base\Component;


/**
 * Order Complete Service service
 */
class AvelonService extends Component
{

    // public functions

    public function getAvelonCookie()
    {
        $cookie = $_COOKIE['avln_cid'] ?? null;
        if ($cookie) {
            return $cookie;
        }
        return null;
    }


    public function formatOrder(Event $event)
    {
        $formatedOrder = [];

        /** @var Order $order */
        $order = $event->sender;

        // get cart items
        $cartItems = $order->getLineItems();
        $items = [];

        // format cart items and push to array
        for ($i = 0; $i < count($cartItems); $i++) {
            array_push($items, [
                "item_price" => floatval(round($cartItems[$i]->salePrice, 2)),
                "item_id" => $cartItems[$i]->id,
                "item_name" => $cartItems[$i]->description,
                "item_category" => $cartItems[$i]->purchasable->product->type->name,
                "item_quantity" => intval($cartItems[$i]->qty),
                "item_metadata" => "{}",
            ]);
        };

        $formatedOrder = [
            "transaction_id" => $order->id,
            "currency" => $order->paymentCurrency,
            "promo_codes" => [$order->couponCode],
            "items" => $items,
        ];

        return $formatedOrder;
    }


    public function getSettings()
    {
        $record = $this->getSettingsRow();

        if ($record == null) {
            return null;
        }

        $settingsJson = [
            'accountId' => $record->accountId,
            'bearerToken' => $record->bearerToken,
        ];

        return $settingsJson;
    }


    public function setSettings($params)
    {
        $record = $this->getSettingsRow();

        if ($record == null) {
            $record = new SettingsRecord();
        }

        $record->handle = "avelon-settings";
        $record->accountId = $params['accountId'];
        $record->bearerToken = $params['bearerToken'];
        $record->save();
    }


    public function postToApi($data)
    {
        // get the bearer token
        $bearer_token = $this->getBearerToken();

        // get the avln_cid cookie
        $avlnCid = $this->getAvelonCookie();

        // if the avln_cid cookie exists, add the value to the data
        if ($avlnCid) {
            $data['avln_cid'] = $avlnCid;
        }

        // encode the data to json with correct precision
        $dataJson = $this->jsonEncode($data);

        // if the avln_cid cookie exists or there are promo codes, post to the api
        if ($avlnCid || count($data['promo_codes']) > 0) {
            try {
                $client = new \GuzzleHttp\Client();

                $repsonse = $client->request(
                    'POST',
                    'https://craftplugintest.avln.me/purchase',
                    [
                        'headers' =>
                        [
                            'Authorization' => "Bearer {$bearer_token}",
                            "Content-Type" => "application/json"
                        ],
                        'body' => $dataJson,
                    ]
                );

                // if the status code is not 201, log the info
                if ($repsonse->getStatusCode() != 201) {
                    $this->logErrors('info', [
                        'status' => $repsonse->getStatusCode(),
                        'reason-phrase' => $repsonse->getReasonPhrase(),
                    ]);
                }
            } catch (\Throwable $th) {
                // log the error
                $this->logErrors('error', $th);
            }
        }
    }


    // Private functions

    private function getBearerToken()
    {
        $settings = $this->getSettings();
        return $settings['bearerToken'];
    }


    private function jsonEncode($data)
    {
        // get the serialize_precision
        $precision = ini_get('serialize_precision');

        // set the serialize_precision to -1 to prevent float formatting issues
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set('serialize_precision', -1);
        }
        // encode the data to json
        $dataJson = json_encode($data);

        // set the serialize_precision back to the original value
        ini_set('serialize_precision', $precision);

        return $dataJson;
    }


    private function logErrors($type, $response)
    {
        if ($type == 'info') {
            Craft::info($response, 'Avelon Plugin Message');
        } else if ($type == 'error') {
            Craft::error($response, 'Avelon Plugin Error');
        }
    }


    private function getSettingsRow()
    {
        return (new SettingsRecord())->findOne(['handle' => 'avelon-settings']);
    }
}
