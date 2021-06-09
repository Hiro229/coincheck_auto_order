<?php
// Please set ACCESS_KEY and SECRET_ACCESS_KEY
require_once('coincheck_key.php');

// proxy settings
$proxy      = "";
$proxy_port = "";

// coincheck balance api url
$url = "https://coincheck.com/api/accounts/balance";

// create signature
$nonce = time() + 1;
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
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
// set proxy server settings
if (!empty($proxy) && !empty($proxy_port)) {
    curl_setopt($curl, CURLOPT_HTTPPROXYTUNNEL, 1);
    curl_setopt($curl, CURLOPT_PROXY, $proxy . ":" . $proxy_port);
    curl_setopt($curl, CURLOPT_PROXYPORT, $proxy_port);
}

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
// output json_decode
//print_r($json_decode);
if (!$json_decode["success"]) {
    fputs(STDERR,"[ERROR] : " . $json_decode["error"] . PHP_EOL);
    die(1);
}
$my_jpy = $json_decode['jpy'];
$my_btc = $json_decode['btc'];
//var_dump($my_jpy);
//var_dump($my_btc);
