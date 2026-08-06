<?php

namespace avelonnetwork\craftavelon\services;

use avelonnetwork\craftavelon\records\SettingsRecord;
use craft\commerce\elements\Order;
use yii\base\Event;

use Craft;
use yii\base\Component;


/**
 * Order Complete Service service
 */
class AvelonService extends Component
{

    // public functions

    /**
     * Get avln_cid cookie
     * @return string|null
     */
    public function getAvelonCookie(): ?string
    {
        $cookie = $_COOKIE['avln_cid'] ?? null;
        if ($cookie) {
            return $cookie;
        }
        return null;
    }

    /**
     * Get formated order
     * @return array|null
     */
    public function formatOrder(Event $event): ?array
    {
        $formatedOrder = [];

        /** @var Order $order */
        $order = $event->sender;

        if ($order) {
            // get cart items
            $cartItems = $order->getLineItems();
            $items = [];

            // API required [item_price, item_id, item_name, item_quantity]
            // client required [item_category, item_categories, item_metadata]

            // if there are items in the cart
            if (count($cartItems) > 0) {

                // format cart items and push to array
                foreach ($cartItems as $item) {

                    // check the API required fields exist
                    if (
                        $item->salePrice !== null &&
                        $item->id &&
                        $item->description !== '' &&
                        $item->qty > 0
                    ) {

                        // Future-proof for Commerce 5: Variants use `owner` instead of `product`.
                        // We check for `owner` first, then fall back to `product` for Commerce 4 compatibility.
                        $product = $item->purchasable?->owner ?? $item->purchasable?->product ?? null;

                        // Safely get the product type name using PHP 8 nullsafe operators
                        $categoryName = $product?->type?->name ?? null;

                        // NOTE: If you use a custom category field in Craft (e.g., 'productCategories'),
                        // you would query it here instead. Example:
                        // $customCategories = $product?->productCategories->all() ?? [];
                        // $categoryNamesArray = array_map(fn($cat) => $cat->title, $customCategories);

                        array_push($items, [
                            "item_price" => floatval(round($item->salePrice, 2)),
                            "item_id" => $item->id,
                            "item_name" => $item->description,
                            "item_category" => $categoryName,
                            "item_categories" => $categoryName ? [$categoryName] : [],
                            "item_quantity" => intval($item->qty),
                            "item_metadata" => "{}",
                        ]);
                    }
                }

                $formatedOrder = [
                    "transaction_id" => $order->id,
                    "currency" => $order->paymentCurrency,
                    "items" => $items,
                    "version" => "1.0.5",
                    "is_first_order" => $this->isFirstOrder($order),
                ];

                if ($order->couponCode) {
                    $formatedOrder['promo_codes'] = [$order->couponCode];
                }

                return $formatedOrder;
            } else {
                // if there are no items in the cart, return null
                return null;
            }
        } else {
            // if there is no order, return null
            return null;
        }
    }

    /**
     * Determine whether this is the customer's first completed order.
     */
    private function isFirstOrder(Order $order): bool
    {
        $previousOrders = Order::find()
            ->isCompleted(true)
            ->id(['not', $order->id]);

        if ($order->customerId) {
            $previousOrders->customerId($order->customerId);
        } elseif ($order->email) {
            // Fallback for orders without an attached customer ID.
            $previousOrders->email($order->email);
        } else {
            return false;
        }

        return !$previousOrders->exists();
    }


    /**
     * Get plugin settings
     * @return array|null $record
     */
    public function getSettings(): ?array
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


    /**
     * Set plugin settings
     * @param array $params
     */
    public function setSettings(array $params): void
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


    /**
     * post to the avelon api
     * @param array $data
     *
     */
    public function postToApi(array $data): void
    {
        // get the bearer token
        $bearer_token = $this->getBearerToken();

        // get account id
        $accountId = $this->getAccountId();

        // get the avln_cid cookie
        $avlnCid = $this->getAvelonCookie();

        // if the avln_cid cookie exists, add the value to the data
        if ($avlnCid) {
            $data['avln_cid'] = $avlnCid;
        }

        $promoCodes = $data['promo_codes'] ?? null;

        // encode the data to json with correct precision
        $dataJson = $this->jsonEncode($data);

        // if the account id and bearer token
        if ($accountId && $bearer_token) {

            // if the avln_cid cookie exists or there are promo codes, post to the api
            if ($avlnCid || $promoCodes) {
                try {
                    $client = new \GuzzleHttp\Client();

                    $response = $client->request(
                        'POST',
                        "https://{$accountId}.avln.me/purchase",
                        [
                            'headers' =>
                                [
                                    'Authorization' => "Bearer {$bearer_token}",
                                    "Content-Type" => "application/json"
                                ],
                            'body' => $dataJson,
                        ]
                    );

                    $statusCode = $response->getStatusCode();

                    // if the status code is not 201, log the info
                    if ($statusCode < 200 || $statusCode >= 300) {
                        $this->logErrors('info', [
                            'status' => $response->getStatusCode(),
                            'reason-phrase' => $response->getReasonPhrase(),
                        ]);
                    }
                } catch (\Throwable $th) {
                    $this->logErrors('error', $th);
                }
            }
        } else {
            $this->logErrors('info', [
                'message' => 'Avelon API credentials are incomplete.',
                'hasAccountId' => !empty($accountId),
                'hasBearerToken' => !empty($bearer_token),
            ]);
        }
    }


    // Private functions

    /**
     * Get bearer token from settings
     * @return string|null
     */
    private function getBearerToken(): ?string
    {
        $settings = $this->getSettings();
        return $settings['bearerToken'] ?? null;
    }

    /**
     * Get account id from settings
     * @return string|null
     */
    private function getAccountId(): ?string
    {
        $settings = $this->getSettings();
        return $settings['accountId'] ?? null;
    }


    /**
     * Get json encode data
     * @param array $data
     * @return string
     */
    private function jsonEncode(array $data): string
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
        ini_set('serialize_precision', (string)$precision);

        return $dataJson ?: '{}';
    }


    /**
     * log errors
     */
    private function logErrors(string $type, mixed $message): void
    {
        if ($type == 'info') {
            Craft::info($message, 'Avelon Plugin Message');
        } else if ($type == 'error') {
            Craft::error($message, 'Avelon Plugin Error');
        }
    }


    /**
     * Get a DB row for the plugin settings
     * @return SettingsRecord|null
     */
    private function getSettingsRow(): ?SettingsRecord
    {
        return SettingsRecord::findOne(['handle' => 'avelon-settings']);
    }
}