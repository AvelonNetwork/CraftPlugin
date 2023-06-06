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

	/**
	 * Get avln_cid cookie
	 * @return array
	 */
	public function getAvelonCookie()
	{
		$cookie = $_COOKIE['avln_cid'] ?? null;
		if ($cookie) {
			return $cookie;
		}
		return null;
	}

	/**
	 * Get formated order
	 * @return array
	 */
	public function formatOrder(Event $event)
	{
		$formatedOrder = [];

		/** @var Order $order */
		$order = $event->sender;

		if ($order) {
			// get cart items
			$cartItems = $order->getLineItems();
			$items = [];

			// API required [item_price, item_id, item_name, item_quantity]
			// client required [item_category, item_metadata]

			// if there are items in the cart
			if (count($cartItems) > 0) {

				// format cart items and push to array
				foreach ($cartItems as $item) {

					// check the API required fields exist
					if ($item->salePrice && $item->id && $item->description && $item->qty) {
						array_push($items, [
							"item_price" => floatval(round($item->salePrice, 2)),
							"item_id" => $item->id,
							"item_name" => $item->description,
							"item_category" => $item->purchasable->product->type->name ?? null,
							"item_quantity" => intval($item->qty),
							"item_metadata" => "{}",
						]);
					}
				};

				$formatedOrder = [
					"transaction_id" => $order->id,
					"currency" => $order->paymentCurrency,
					"items" => $items,
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
	 * Get plugin settings
	 * @return array $record
	 */
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


	/**
	 * Set plugin settings
	 * @param array $params
	 * @return array
	 */
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


	/**
	 * post to the avelon api
	 * @param array $data
	 *
	 */
	public function postToApi($data)
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

					$repsonse = $client->request(
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
		} else {
			$this->logErrors('info', ['accountId' => $accountId, 'bearer_token' => $bearer_token, 'dataJson' => $dataJson]);
		}
	}


	// Private functions

	/**
	 * Get bearer token from settings
	 * @return array
	 */
	private function getBearerToken()
	{
		$settings = $this->getSettings();
		return $settings['bearerToken'];
	}

	/**
	 * Get account id from settings
	 * @return array
	 */
	private function getAccountId()
	{
		$settings = $this->getSettings();
		return $settings['accountId'];
	}


	/**
	 * Get json encode data
	 * @param array $data
	 * @return array
	 */
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


	/**
	 * log errors
	 */
	private function logErrors($type, $message)
	{
		if ($type == 'info') {
			Craft::info($message, 'Avelon Plugin Message');
		} else if ($type == 'error') {
			Craft::error($message, 'Avelon Plugin Error');
		}
	}


	/**
	 * Get a DB row for the plugin settings
	 * @return object
	 */
	private function getSettingsRow()
	{
		return (new SettingsRecord())->findOne(['handle' => 'avelon-settings']);
	}
}
