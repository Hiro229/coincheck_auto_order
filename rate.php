<?php
// rate api url
$coincheck_api_url = "https://coincheck.com/api/rate";
// レートを見たい通過を選択
$cryptocurrencies = "btc_jpy";

// proxy settings
$proxy      = "";
$proxy_port = "";

$curl = curl_init();
if ($curl == FALSE) {
    fputs(STDERR, "[ERR] curl_init(): " . curl_error($curl) . PHP_EOL);
    die(1);
}

$pair = $cryptocurrencies;
    // curl set options
    curl_setopt($curl, CURLOPT_URL, $coincheck_api_url . "/" . $pair);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    // set proxy server settings
    if (!empty($proxy) && !empty($proxy_port)) {
        curl_setopt($curl, CURLOPT_HTTPPROXYTUNNEL, 1);
        curl_setopt($curl, CURLOPT_PROXY, $proxy . ":" . $proxy_port);
        curl_setopt($curl, CURLOPT_PROXYPORT, $proxy_port);
    }

    // call order book api
    $response = curl_exec($curl);
    if ($response == FALSE) {
        fputs(STDERR, "[ERR] curl_exec(): " . curl_error($curl) . PHP_EOL);
        die(1);
    }
    // json decode
    $json_decode = json_decode($response, true);
    if ($json_decode == NULL) {
        fputs(STDERR, "[ERR] json_decode(): " . json_last_error_msg() . PHP_EOL);
        die(1);
    }
	// BTCのレート
    $btc_jpy_rate = $json_decode["rate"];

curl_close($curl);

//var_dump($btc_jpy_rate);
