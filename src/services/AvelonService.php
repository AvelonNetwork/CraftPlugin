<?php

namespace avelonnetwork\craftavelon\services;

use yii\base\Event;

use Craft;
use yii\base\Component;

/**
 * Order Complete Service service
 */
class AvelonService extends Component
{
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
        $cartItems = $order->getLineItems();
        $items = [];

        for ($i = 0; $i < count($cartItems); $i++) {
            array_push($items, [
                "item_price" => $cartItems[$i]->salePrice,
                "item_id" => $cartItems[$i]->id,
                "item_name" => $cartItems[$i]->description,
                "item_quantity" => $cartItems[$i]->qty,
                "item_metadata" => "{}",
                "item_category" => $cartItems[$i]->purchasable->product->type->name,
            ]);
        };

        $formatedOrder = [
            "transaction_id" => $order->id ?? null,
            "currency" => $order->paymentCurrency ?? null,
            "promo_code" => [$order->couponCode] ?? null,
            "items" => $items,
        ];

        return $formatedOrder;
    }
}
