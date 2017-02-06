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

	/** @var string Base URL for Pushover API */
	protected $apiUrl = 'https://api.pushover.net/1/messages.json';

	/** @var string The Application Key */
	protected $appKey;

	/** @var GuzzleHttp\Client HTTP Client for sending requests */
	protected $client;

	protected $user;
	protected $device;
	protected $title;
	protected $message;

	protected $html = false;
	protected $priority = 0;
	protected $retry = 120;
	protected $expire = 600;
	protected $callback;
	protected $sound = 'pushover';

	protected $url;
	protected $urlTitle;

	public function __construct()
	{
		$this->appKey = env('PUSHOVER_APP_KEY');
		$this->client = new Client;
	}

	/**
	 * Set the user key to send the Pushover message to
	 * @param  string $key
	 * @return self
	 */
	public function user($key)
	{
		$this->user = $key;

		return $this;
	}

	/**
	 * Set a device to send the Pushover message to on the users account
	 * @param  string $device
	 * @return self
	 */
	public function device($device)
	{
		$this->device = $device;

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

		$this->priority = $level;

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

		$this->expires = $seconds;

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

		$this->retry = $seconds;

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
		$this->url = $url;
		$this->urlTitle = $title;

		return $this;
	}

	/**
	 * Set HTML flag on or off
	 * @param  boolean $status Whether HTML formatting should be parsed
	 * @return self
	 */
	public function html($status = false)
	{
		$this->html = $status;

		return $this;
	}

	/**
	 * Set the sound for the alert once received
	 * @param  string $sound
	 * @return self
	 */
	public function sound($sound)
	{
		$this->sound = $sound;

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
			$this->user = $userKey;
		}

		// Validate the message
		if (empty($this->message)) {
			throw new PushoverException('No message was provided to send');
		}

		// Check retry and expires are given if Emergency priority is selected
		if ($this->priority == self::PRIORITY_EMERGENCY and (empty($this->retry) or empty($this->expires))) {
			throw new PushoverException('Both retry and expires MUST be set to use Emergency priority');
		}

		if (strlen($this->title) > 250) {
			throw new PushoverException('Title must be 250 characters or less');
		}

		if (strlen($this->message) > 1024) {
			throw new PushoverException('Message must be 1024 characters or less');
		}

		if (strlen($this->url) > 512) {
			throw new PushoverException('Supplementary URL must be 512 characters or less');
		}

		if (strlen($this->urlTitle) > 100) {
			throw new PushoverException('Supplementary URL Title must be 100 characters or less');
		}

		try {
			$resource = $this->client->request('POST', self::API_MESSAGES_ENDPOINT, [

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
	 * Set the App Key
	 * @param string $key
	 */
	public function setAppKey($key)
	{
		$this->appKey = $key;

		return $this;
	}
}
