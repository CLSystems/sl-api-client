<?php

namespace CLSystems\SkimLinks;

use Exception;
use function urlencode;

/**
 * SkimLinks API client library {@link http://developers.skimlinks.com}.
 *
 * API Authentication credentials
 * Client ID :     c03948d94f5b82d7fde5ba26c63e9426
 * Client Secret : 581de99f4783f31e09398b7393700bc3
 * Publisher Id :  190750
 * User Id :       153197
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
	protected $sitesAllowed = [];

	/**
	 * @var Client|null
	 */
	protected ?Client $client;

	/**
	 * @var null
	 */
	protected $dateFormat = null;

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
	 * @see https://jsapi.apiary.io/apis/skimlinksmerchantapi/reference/0.html
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
			$limit = 100;
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
				$urlMerchants = $this->apiMerchantUrl . '/v4/publisher/' . $this->publisherId . 'merchants?access_token=' . $this->token . '&limit=' . $limit . '&offset=' .
					$offset;
				if (false === empty($filters))
				{
					foreach ($filters as $key => $value)
					{
						$urlMerchants .= '&' . urlencode($key) . '=' . urlencode($value);
					}
				}
				$merchants = self::callApi($urlMerchants);
				if (true === isset($merchants[0]['code']) && true === isset($merchants[0]['message']))
				{
					// Some error occurred
					throw new Exception('[SkimLinks][getMerchants] ' . $merchants[0]['message']);
				}
				else if (true === isset($merchants['items']) && true === isset($merchants['total']))
				{
					foreach ($merchants['items'] as $merchantJson)
					{
						$obj = [];
						$obj['cid'] = $merchantJson['id'];
						$obj['name'] = $merchantJson['name'];
						$obj['status_id'] = $merchantJson['statusId'];
						//Possible values: 0: Not Applied, 1: Under Consideration, 2: On-hold while under consideration, 3: Accepted, 4: Ended, 5: Denied, 6: On Hold while Accepted, 7: Final Denied, 8: Written Off
						switch ($merchantJson['statusId'])
						{
							case 0:
								$obj['status'] = 'Not Applied';
							break;
							case 1:
								$obj['status'] = ' Under Consideration';
							break;
							case 2:
								$obj['status'] = 'On-hold while under consideration';
							break;
							case 3:
								$obj['status'] = 'Accepted';
							break;
							case 4:
								$obj['status'] = 'Ended';
							break;
							case 5:
								$obj['status'] = 'Denied';
							break;
							case 6:
								$obj['status'] = 'On Hold while Accepted';
							break;
							case 7:
								$obj['status'] = 'Final Denied';
							break;
							case 8:
								$obj['status'] = 'Written Off';
							break;
							default:
								$obj['status'] = 'Unknown';
								echo '[SkimLinks][getProgramList] Merchant status unexpected ' . $merchantJson['statusId'];
							break;
						}
						$obj['launch_date'] = $merchantJson['startDate'];
						$obj['application_date'] = $merchantJson['applicationDate'];
						$obj += $merchantJson;
						$results[] = $obj;
					}
					if ((int)$merchants['total'] <= $offset)
					{
						$loop = false;
					}
					$offset = (int)($limit + $offset);
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
	 * Get program details from TradeDoubler Connect API
	 * @see https://tradedoubler.docs.apiary.io/#/reference/programs/program/program-detail/200?mc=reference%2Fprograms%2Fprogram%2Fprogram-detail%2F200
	 *
	 * @param int $programId
	 * @return array
	 * @throws Exception
	 */
	public function getProgramDetails(int $siteId, int $programId): array
	{
		$program = [];
		try
		{
			$urlProgramDetails = $this->apiConnectUrl . '/publisher/programs/detail?sourceId=' . $siteId . '&programId=' . $programId ;
			$programJson = self::callApi($urlProgramDetails);
			if (true === isset($programJson['code']) && true === isset($programJson['message']))
			{
				// Some error occurred
				throw new Exception('[SkimLinks][getProgramDetails] ' . $programJson['message']);
			}
			else if (false === empty($programJson['id']))
			{
				$program = $programJson;
			}
			else
			{
				echo '[SkimLinks][getProgramDetails] invalid response for program ' . $programId . ': ';
				var_dump($programJson);
			}
		}
		catch (Exception $exception)
		{
			throw new Exception('[SkimLinks][getProgramDetails][Exception] ' . $exception->getMessage());
		}
		return $program;
	}

	/**
	 * Apply for a program through TradeDoubler Connect API
	 * @see https://tradedoubler.docs.apiary.io/#/reference/programs/program/apply-to-program/200?mc=reference%2Fprograms%2Fprogram%2Fapply-to-program%2F200
	 *
	 * @param int $siteId
	 * @param int $programId
	 * @return bool
	 * @throws Exception
	 */
	public function applyProgram(int $siteId, int $programId) : bool
	{
		try
		{
			$urlProgram = $this->apiConnectUrl . '/publisher/programs/apply';
			$postData = [
				'sourceId'  => $siteId,
				'programId' => $programId,
			];
			$programJson = self::callApi($urlProgram, true, true, $postData);
		}
		catch (Exception $exception)
		{
			throw new Exception('[SkimLinks][getProgramDetails][Exception] ' . $exception->getMessage());
		}
		return true;
	}

	/**
	 * Get ads from SkimLinks API
	 *
	 * @see https://tradedoubler.docs.apiary.io/#/reference/ads/list-all-ads/ads/200?mc=reference%2Fads%2Flist-all-ads%2Fads%2F200
	 *
	 * @param int $siteId
	 * @param int $programId
	 * @param array|null $filters
	 * @return array
	 * @throws Exception
	 */
	public function getAds(int $siteId, int $programId, array $filters = null): array
	{
		$results = [];
		try
		{
			$limit = 100;
			$offset = 0;
			$loop = true;
			while (true === $loop)
			{
				$urlAds = $this->apiConnectUrl . '/publisher/ads?sourceId=' . $siteId . '&programId=' . $programId. '&limit=' . $limit . '&offset=' . $offset;
				if (false === empty($filters))
				{
					foreach ($filters as $key => $value)
					{
						$urlAds .= '&' . urlencode($key) . '=' . urlencode($value);
					}
				}
				$ads = self::callApi($urlAds);
				if (true === isset($ads[0]['code']) && true === isset($ads[0]['message']))
				{
					// Some error occurred
					throw new Exception('[SkimLinks][getAds] ' . $ads[0]['message']);
				}
				else if (true === isset($ads['items']) && true === isset($ads['total']))
				{
					foreach ($ads['items'] as $ad)
					{
						$results[] = $ad;
					}
					if ((int)$ads['total'] <= $offset)
					{
						$loop = false;
					}
					$offset = (int)($limit + $offset);
				}
				else
				{
					echo '[SkimLinks][getAds] invalid response ';
					var_dump($ads);
					$loop = false;
				}
			}
		}
		catch (Exception $exception)
		{
			throw new Exception('[SkimLinks][getAds][Exception] ' . $exception->getMessage());
		}
		return $results;
	}

	/**
	 * Fetch vouchers from TradeDoubler Open API
	 * @see http://dev.tradedoubler.com/vouchers/publisher/#Get_vouchers_service
	 *
	 * @param string $token
	 * @param array|null $filters
	 * @return array
	 */
	public function getVoucherList(string $token, array $filters = null): array {

		$results = [];
		try
		{
			$urlVouchers = $this->apiMerchantUrl . '/1.0/vouchers.json;dateOutputFormat=iso8601?token=' . $token;
			$vouchers = self::callApi($urlVouchers, false);
			if (true === isset($vouchers[0]['code']) && true === isset($vouchers[0]['message']))
			{
				// Some error occurred
				throw new Exception('[SkimLinks][getVoucherList] ' . $vouchers[0]['message']);
			}
			else if (!empty($vouchers))
			{
				foreach ($vouchers as $voucher)
				{
					$results[$voucher['id']] = $voucher;
				}
			}
		}
		catch (Exception $exception)
		{
			throw new Exception('[SkimLinks][getVoucherList][Exception] ' . $exception->getMessage());
		}
		return $results;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	private function getToken(): string
	{
		try
		{
			if (false === empty($this->token))
			{
				return $this->token;
			}
			// Retrieve token
			$loginUrl = $this->apiAuthUrl . '/access_token';
			$params = [
				'grant_type'    => $this->grantType,
				'client_id'     => $this->clientId,
				'client_secret' => $this->clientSecret,
			];

			$p = [];
			foreach ($params as $key => $value)
			{
				$p[] = $key . '=' . urlencode($value);
			}
			$post_params = implode('&', $p);

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $loginUrl);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_params);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

			$curl_results = curl_exec($ch);
			curl_close($ch);
			$response = json_decode($curl_results, false, 512, JSON_THROW_ON_ERROR);
			if ($response)
			{
				if (isset($response->error))
				{
					if (isset($response->error_description))
					{
						throw new Exception('[SkimLinks][getToken] ' . $response->error_description);
					}
				}
				if (isset($response->access_token))
				{
					$this->token = $response->access_token;
				}

			}
			return $this->token;
		}
		catch (Exception $exception)
		{
			throw new Exception('[SkimLinks][getToken][Exception] ' . $exception->getMessage());
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
