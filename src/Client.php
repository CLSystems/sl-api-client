<?php

namespace CLSystems\SkimLinks;

use Exception;
use function urlencode;

/**
 * SkimLinks API client library {@link http://developers.skimlinks.com}.
 *
 * <code>
 * ...
 *
 * $credentials = ['user' => 'your_login_username', 'password' => 'your_password'];
 * $client = (new \CLSystems\SkimLinks\Client())->login(string $clientId, string $clientSecret);
 *
 * $merchants = $client->getMerchants(int $siteId)
 *
 * ...
 * </code>
 */
class Client
{
	/**
	 * @var array
	 */
	protected array $sitesAllowed = [];

	/**
	 * @var Client|null
	 */
	protected ?Client $client;

	/**
	 * @var string|null
	 */
	protected ?string $dateFormat = null;

	/**
	 * @var string
	 */
	protected string $apiAuthUrl = 'https://authentication.skimapis.com';

	/**
	 * @var string
	 */
	protected string $apiMerchantUrl = 'https://merchants.skimapis.com';

	/**
	 * @var string
	 */
	private string $grantType = 'client_credentials';

	/**
	 * @var string
	 */
	private string $token;

	/**
	 * @var string
	 */
	private string $clientId;

	/**
	 * @var string
	 */
	private string $clientSecret;

	/**
	 * @var int
	 */
	private int $publisherId;

	/**
	 * Login to SkimLinks Connect API
	 * @see https://jsapi.apiary.io/apis/skimlinksmerchantapi/introduction/authentication.html
	 *
	 * @param string $clientId
	 * @param string $clientSecret
	 * @throws Exception
	 * @return Client
	 */
	public function login(string $clientId, string $clientSecret, int $publisherId) : Client
	{
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->publisherId = $publisherId;
		$this->grantType = 'client_credentials';
		$this->getToken();
		return $this;
	}

	/**
	 * Fetch all programs from SkimLinks Merchant API
	 * @see http://developers.skimlinks.com/merchant.html
	 *
	 * @param int $siteId
	 * @param array|null $filters
	 * @return array
	 * @throws Exception
	 */
	public function getMerchants(int $siteId, array $filters = null): array
	{
		$results = [];
		try
		{
			$limit = 200;
			$offset = 0;
			$loop = true;
			while (true === $loop)
			{
				// https://merchants.skimapis.com/v4/publisher/publisher_id/merchants
				// ?access_token=123%3A123456789%3A00112233445566778899aabbccddeeff
				// &publisher_domain_id=
				// &a_id=
				// &search=
				// &vertical=
				// &country=
				// &favourite_type=
				// &limit=25
				// &offset=
				// &sort_by=name
				// &sort_dir=asc
				$urlMerchants = $this->apiMerchantUrl
					. '/v4/publisher/' . $this->publisherId
					. '/merchants?access_token=' . $this->token
					. '&publisher_domain_id=' . $siteId
					. '&limit=' . $limit
					. '&offset=' . $offset;
				if (false === empty($filters))
				{
					foreach ($filters as $key => $value)
					{
						$urlMerchants .= '&' . urlencode($key) . '=' . urlencode($value);
					}
				}
				$merchants = self::callApi($urlMerchants);
				if (true === isset($merchants['description']))
				{
					// Some error occurred
					throw new Exception('[SkimLinks][getMerchants] ' . $merchants['description']);
				}
				else if (true === isset($merchants['merchants']))
				{
					foreach ($merchants['merchants'] as $merchant)
					{
						$results[$merchant['advertiser_id']] = $merchant;
					}
					if ($merchants['has_more'] === false)
					{
						$loop = false;
					}
					$offset = (int)($limit + $offset);
					usleep(1500000); // Max 40 calls per minute per Api key
				}
				else
				{
					echo '[SkimLinks][getMerchants] invalid response ';
					var_dump($merchants);
					$loop = false;
				}
			}
		}
		catch (Exception $exception)
		{
			throw new Exception('[SkimLinks][getMerchants][Exception] ' . $exception->getMessage());
		}
		return $results;
	}

	/**
	 * Get offers from SkimLinks API
	 *
	 * https://merchants.skimapis.com/v4/publisher/{publisher_id}/offers
	 * ?access_token=123%3A123456789%3A00112233445566778899aabbccddeeff
	 * &search=
	 * &merchant_id=
	 * &a_id=
	 * &vertical=
	 * &country=
	 * &period=
	 * &favourite_type=
	 * &limit=
	 * &offset=
	 * &sort_by=
	 * &sort_dir=asc
	 *
	 * @param array|null $filters
	 * @return array
	 * @throws Exception
	 */
	public function getOffers(array $filters = null): array
	{
		$results = [];
		try
		{
			$limit = 200;
			$offset = 0;
			$loop = true;
			while (true === $loop)
			{
				$urlOffers = $this->apiMerchantUrl
					. '/v4/publisher/' . $this->publisherId
					. '/offers?access_token=' . $this->token
					. '&limit=' . $limit
					. '&offset=' . $offset;
				if (false === empty($filters))
				{
					foreach ($filters as $key => $value)
					{
						$urlOffers .= '&' . urlencode($key) . '=' . urlencode($value);
					}
				}
				$offers = self::callApi($urlOffers);
				if (true === isset($offers['description']))
				{
					// Some error occurred
					throw new Exception('[SkimLinks][getOffers] ' . $offers['description']);
				}
				else if (true === isset($offers['offers']))
				{
					foreach ($offers['offers'] as $offer)
					{
						$results[] = $offer;
					}

					if ($offers['has_more'] === false)
					{
						$loop = false;
					}
					$offset = (int)($limit + $offset);
					usleep(1500000); // Max 40 calls per minute per Api key
				}
				else
				{
					echo '[SkimLinks][getOffers] invalid response ';
					var_dump($offers);
					$loop = false;
				}
			}
		}
		catch (Exception $exception)
		{
			throw new Exception('[SkimLinks][getOffers][Exception] ' . $exception->getMessage());
		}
		return $results;
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	private function getToken(): void
	{
		try
		{
			if (false === empty($this->token))
			{
				return;
			}
			// Retrieve token
			$loginUrl = $this->apiAuthUrl . '/access_token';
			$params = [
				'client_id'     => $this->clientId,
				'client_secret' => $this->clientSecret,
				'grant_type'    => $this->grantType,
			];

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $loginUrl);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

			$curl_results = curl_exec($ch);
			curl_close($ch);
			$response = json_decode($curl_results, false, 512, JSON_THROW_ON_ERROR);
			if ($response)
			{
				if (isset($response->description))
				{
					throw new Exception('[SkimLinks][getToken] FAILED: ' . $response->description);
				}
				if (isset($response->access_token))
				{
					$this->token = $response->access_token;
				}
			}
			return;
		}
		catch (Exception $exception)
		{
			throw new Exception($exception->getMessage());
		}
	}

	/**
	 * Call the SkimLinks API
	 *
	 * @param string $url
	 * @param bool $auth
	 * @param bool $post
	 * @param array $postData
	 * @return array
	 * @throws Exception
	 */
	private function callApi(string $url, bool $auth = false, bool $post = false, array $postData = []): array
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		if (true === $auth)
		{
			curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
		}
		if (true === $post)
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
		}

		$curlResults = curl_exec($ch);
		$curlInfo = curl_getinfo($ch);
		curl_close($ch);

		if ((int)$curlInfo['http_code'] === 200 && !empty($curlResults))
		{
			return json_decode($curlResults, true, 512, JSON_PARTIAL_OUTPUT_ON_ERROR);
		}
		elseif ((int)$curlInfo['http_code'] === 200)
		{
			return [];
		}
		else
		{
			throw new Exception('Error: ' . var_export($curlResults));
		}
	}
}
