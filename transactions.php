<?php
// Please set ACCESS_KEY and SECRET_ACCESS_KEY
require_once('coincheck_key.php');

// coincheck transactions api url
$url = "https://coincheck.com/api/exchange/orders/transactions_pagination";
$http_query = array('pair' => 'btc_jpy', 'limit' => '3');
$url = $url . "?" . http_build_query($http_query);

// proxy settings
$proxy      = "";
$proxy_port = "";

// create signature
$nonce = time();
$message = $nonce . $url;
$signature = hash_hmac("sha256", $message, $SECRET_ACCESS_KEY);

// header
$headers = array(
    "ACCESS-KEY: {$ACCESS_KEY}",
    "ACCESS-SIGNATURE: {$signature}",
    "ACCESS-NONCE: {$nonce}",
    );

$curl = curl_init();
if ($curl == FALSE) {
    fputs(STDERR, "[ERR] curl_init(): " . curl_error($curl) . PHP_EOL);
    die(1);
}
// set proxy server settings
if (!empty($proxy) && !empty($proxy_port)) {
    curl_setopt($curl, CURLOPT_HTTPPROXYTUNNEL, 1);
    curl_setopt($curl, CURLOPT_PROXY, $proxy . ":" . $proxy_port);
    curl_setopt($curl, CURLOPT_PROXYPORT, $proxy_port);
}
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($curl);
if ($response == FALSE) {
    fputs(STDERR, "[ERR] curl_exec(): " . curl_error($curl) . PHP_EOL);
    die(1);
}
curl_close($curl);

// json decode
$json_decode = json_decode($response, true);
if ($json_decode == NULL) {
    fputs(STDERR, "[ERR] json_decode(): " . json_last_error_msg() . PHP_EOL);
    die(1);
}

//取引時のレート
$transaction_rate_btc = $json_decode['data'][0]['rate'];
$transaction_bill_jpy = $json_decode['data'][0]['funds']['jpy'];
$transaction_bill_btc = $json_decode['data'][0]['funds']['btc'];
//var_dump($json_decode);
//var_dump($transaction_bill_jpy);
//var_dump($transaction_bill_btc);
