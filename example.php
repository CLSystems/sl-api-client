<?php

require_once('src/Client.php');

use CLSystems\SkimLinks\Client;

$clientId     = 'c03948d94f5b82d7fde5ba26c63e9426';
$clientSecret = '581de99f4783f31e09398b7393700bc3';
$publisherId  = 190750;

// Approved sites
$sites = [
	1661924 => 'example.com',
];

$api = (new Client())->login($clientId, $clientSecret, $publisherId);
foreach ($sites as $siteId => $siteName)
{
    print_r('Fetching programs for ' . $siteName);
    $params = [
        'publisher_domain_id' => $siteId,
    ];

    $advertisers = $api->getMerchants($siteId, $params);
    print_r('ADVERTISERS OK - Got ' . count($advertisers) . ' advertisers');
    foreach ($advertisers as $advertiser)
    {
        // Build the data array to process
        if (!isset($processData[$advertiser['merchant_id']]))
        {
            print_r('Adding  ' . $advertiser['name'] . ' (' . $advertiser['merchant_id'] . ')');
            $processData[$advertiser['merchant_id']] = $advertiser;
        }
    }
}
