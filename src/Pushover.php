<?php

namespace Ampersa\Pushover;

use GuzzleHttp\Client;
use Ampersa\Pushover\Exceptions\PushoverException;
use Ampersa\Pushover\Exceptions\PushoverApiException;

class Pushover
{
	const PRIORITY_EMERGENCY = 2;
	const PRIORITY_HIGH = 1;
	const PRIORITY_NORMAL = 0;
	const PRIORITY_LOW = -1;
	const PRIORITY_LOWEST= -2;

	const SECONDS_RETRY_MIN = 30;
	const SECONDS_EXPIRES_MAX = 86400;

	const API_MESSAGES_ENDPOINT = 'https://api.pushover.net/1/messages.json';
	const API_RECEIPT_ENDPOINT = 'https://api.pushover.net/1/receipts/%s.json';
	const API_CANCEL_ENDPOINT = 'https://api.pushover.net/1/receipts/%s/cancel.json';

	/** @var GuzzleHttp\Client HTTP Client for sending requests */
	protected $client;

	protected $params = [
		'html' => 0,
		'priority' => 0,
		'retry' => 120,
		'expire' => 600,
		'sound' => 'pushover',
	];

	protected $rateLimit = [];
	
	public function __construct()
	{
		$this->params['token'] = env('PUSHOVER_APP_KEY');
		$this->client = new Client;
	}

	/**
	 * Set the user key to send the Pushover message to
	 * @param  string $key
	 * @return self
	 */
	public function user($key)
	{
		$this->params['user'] = $key;

		return $this;
	}

	/**
	 * Set a device to send the Pushover message to on the users account
	 * @param  string $device
	 * @return self
	 */
	public function device($device)
	{
		$this->params['device'] = $device;

		return $this;
	}

	/**
	 * Set the message title
	 * @param  string $title
	 * @return self
	 */
	public function title($title)
	{
		$this->params['title'] = $title;

		return $this;
	}

	/**
	 * Set the message content
	 * @param  string $title
	 * @return self
	 */
	public function message($message)
	{
		$this->params['message'] = $message;

		return $this;
	}

	/**
	 * Set the priority level for the message
	 * Options:
	 *   self::PRIORITY_LOWEST
	 *   self::PRIORITY_LOW
	 *   self::PRIORITY_NORMAL
	 *   self::PRIORITY_HIGH
	 *   self::PRIORITY_EMERGENCY
	 * @param  integer $level
	 * @return self
	 */
	public function priority($level)
	{
		if ($level > 2 or $level < -2) {
			throw new PushoverException('Priority must be an integer between -2 and 2');
		}

		$this->params['priority'] = $level;

		return $this;
	}

	/**
	 * The number of seconds before a PRIORITY_EMERGENCY message expires
	 * @param  int $seconds
	 * @return self
	 */
	public function expires($seconds)
	{
		if ($seconds > self::SECONDS_EXPIRES_MAX) {
			throw new PushoverException(sprintf('The maximum number of seconds until expiry is %d', self::SECONDS_EXPIRES_MAX));
		}

		$this->params['expires'] = $seconds;

		return $this;
	}

	/**
	 * The number of seconds between retries for PRIORITY_EMERGENCY messages
	 * @param  int $seconds
	 * @return self
	 */
	public function retry($seconds)
	{
		if ($seconds < self::SECONDS_RETRY_MIN) {
			throw new PushoverException(sprintf('The minimum number of seconds between retries is %d', self::SECONDS_RETRY_MIN));
		}

		$this->params['retry'] = $seconds;

		return $this;
	}

	/**
	 * Set a supplementary URL for the message
	 * @param  string $url   The URL to provide a clickable link to
	 * @param  string $title The title of the URL to display
	 * @return self
	 */
	public function url($url, $title = null)
	{
		$this->params['url'] = $url;
		$this->params['url_title'] = $title;

		return $this;
	}

	/**
	 * Set HTML flag on or off
	 * @param  boolean $status Whether HTML formatting should be parsed
	 * @return self
	 */
	public function html($status = false)
	{
		$this->params['html'] = $status ? '1' : '0';

		return $this;
	}

	/**
	 * Set the sound for the alert once received
	 * @param  string $sound
	 * @return self
	 */
	public function sound($sound)
	{
		$this->params['sound'] = $sound;

		return $this;
	}

	/**
	 * Send a Pushover message
	 * @param  string|null $userKey The user key to send to, if not already provided
	 * @return array|bool
	 */
	public function send($userKey = null)
	{
		// Set the user key, if provided
		if (!empty($userKey)) {
			$this->params['user'] = $userKey;
		}

		// Validate the message
		if (empty($this->params['token'])) {
			throw new PushoverException('No application key was provided');
		}

		if (empty($this->params['message'])) {
			throw new PushoverException('No message was provided to send');
		}

		// Check retry and expires are given if Emergency priority is selected
		if ($this->params['priority'] == self::PRIORITY_EMERGENCY and (empty($this->params['retry']) or empty($this->params['expires']))) {
			throw new PushoverException('Both retry and expires MUST be set to use Emergency priority');
		}

		if (isset($this->params['title']) and strlen($this->params['title']) > 250) {
			throw new PushoverException('Title must be 250 characters or less');
		}

		if (isset($this->params['url']) and strlen($this->params['message']) > 1024) {
			throw new PushoverException('Message must be 1024 characters or less');
		}

		if (isset($this->params['url']) and strlen($this->params['url']) > 512) {
			throw new PushoverException('Supplementary URL must be 512 characters or less');
		}

		if (isset($this->params['url']) and strlen($this->params['url_title']) > 100) {
			throw new PushoverException('Supplementary URL Title must be 100 characters or less');
		}

		try {
			$resource = $this->client->request('POST', self::API_MESSAGES_ENDPOINT, [
				'form_params' => $this->params,
			]);
		} catch (GuzzleHttp\Exception\ClientException $e) {
			// 429 Codes refer Rate-Limiting
			if ($resource->getStatusCode() == 429) {
				throw new PushoverApiException(sprintf('Pushover rate-limit reached - will reset on %s', date('d/m/Y', $resource->getHeader('X-Limit-App-Reset'))));
			}

			$response = json_decode((string) $resource->getBody());

			// Any other error, pass the errors array through
			throw new PushoverApiException(implode(', ', $response->errors));
		} catch (\GuzzleHttp\Exception\ServerException $e) {
			throw new PushoverApiException('Received a 5XX error from Pushover. Try again in 5 seconds.');
		} catch (\GuzzleHttp\Exception\ConnectException $e) {
			throw new PushoverApiException('Could not connect to Pushover API.');
		} catch (\Exception $e) {
			throw new PushoverApiException($e->getMessage());
		}

		$response = json_decode((string) $resource->getBody());

		// Set Rate-Limit headers for info
		$this->rateLimit['Limit'] = (int) $resource->getHeader('X-Limit-App-Limit')[0];
		$this->rateLimit['Remaining'] = (int) $resource->getHeader('X-Limit-App-Remaining')[0];
		$this->rateLimit['Reset'] = new \DateTime(date('Y-m-d\TH:i:s', $resource->getHeader('X-Limit-App-Reset')[0]));

		// Store the last receipt, if provided, to allow utility receipt() function.
		if ($response->receipt) {
			$this->lastReceipt = $response->receipt;
		}

		return $response;
	}

	/**
	 * Retrieve a receipt from the Pushover API
	 * @param  string|null $receipt A receipt string to check. 
	 *                              If null, will attempt to use the last receipt provided
	 * @return object
	 */
	public function receipt($receipt = null)
	{
		if (empty($receipt) and !empty($this->lastReceipt)) {
			$receipt = $this->lastReceipt;
		}

		// Check the receipt is 30 alphanumerical characters
		if (!preg_match('/[A-Za-z0-9]{30}/', $receipt)) {
			throw new PushoverException('Receipt string in incorrect format.');
		}

		try {
			$resource = $this->client->request('GET', sprintf(self::API_RECEIPT_ENDPOINT, $receipt), [
				'query' => [
					'token' => $this->params['token'],
				],
			]);
		} catch (GuzzleHttp\Exception\ClientException $e) {
			// 429 Codes refer Rate-Limiting
			if ($resource->getStatusCode() == 429) {
				throw new PushoverApiException(sprintf('Pushover rate-limit reached - will reset on %s', date('d/m/Y', $resource->getHeader('X-Limit-App-Reset'))));
			}

			$response = json_decode((string) $resource->getBody());

			// Any other error, pass the errors array through
			throw new PushoverApiException(implode(', ', $response->errors));
		} catch (\GuzzleHttp\Exception\ServerException $e) {
			throw new PushoverApiException('Received a 5XX error from Pushover. Try again in 5 seconds.');
		} catch (\GuzzleHttp\Exception\ConnectException $e) {
			throw new PushoverApiException('Could not connect to Pushover API.');
		} catch (\Exception $e) {
			throw new PushoverApiException($e->getMessage());
		}

		$response = json_decode((string) $resource->getBody());

		return $response;
	}

	/**
	 * Cancel an Emergency priority message by receipt string
	 * @param  string|null $receipt A receipt string to cancel. 
	 *                              If null, will attempt to use the last receipt provided
	 * @return object
	 */
	public function cancel($receipt = null)
	{
		if (empty($receipt) and !empty($this->lastReceipt)) {
			$receipt = $this->lastReceipt;
		}

		// Check the receipt is 30 alphanumerical characters
		if (!preg_match('/[A-Za-z0-9]{30}/', $receipt)) {
			throw new PushoverException('Receipt string in incorrect format.');
		}

		try {
			$resource = $this->client->request('POST', sprintf(self::API_CANCEL_ENDPOINT, $receipt), [
				'form_params' => [
					'token' => $this->params['token'],
				],
			]);
		} catch (GuzzleHttp\Exception\ClientException $e) {
			// 429 Codes refer Rate-Limiting
			if ($resource->getStatusCode() == 429) {
				throw new PushoverApiException(sprintf('Pushover rate-limit reached - will reset on %s', date('d/m/Y', $resource->getHeader('X-Limit-App-Reset'))));
			}

			$response = json_decode((string) $resource->getBody());

			// Any other error, pass the errors array through
			throw new PushoverApiException(implode(', ', $response->errors));
		} catch (\GuzzleHttp\Exception\ServerException $e) {
			throw new PushoverApiException('Received a 5XX error from Pushover. Try again in 5 seconds.');
		} catch (\GuzzleHttp\Exception\ConnectException $e) {
			throw new PushoverApiException('Could not connect to Pushover API.');
		} catch (\Exception $e) {
			throw new PushoverApiException($e->getMessage());
		}

		$response = json_decode((string) $resource->getBody());

		return $response;
	}

	/**
	 * Return the rate limits data from the last response
	 * @return array|bool
	 */
	public function rateLimits()
	{
		return $this->rateLimit ?: false;
	}

	/**
	 * Set the App Key
	 * @param string $key
	 */
	public function setAppKey($key)
	{
		$this->params['token'] = $key;

		return $this;
	}
}
