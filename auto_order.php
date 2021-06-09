<?php
// Please set ACCESS_KEY and SECRET_ACCESS_KEY
require_once('coincheck_key.php');

// proxy settings
$proxy      = "";
$proxy_port = "";

// BTC/JPY only
// 取引する通貨
$currency_pair = "btc_jpy";

// DB接続
require_once('db_connection.php');

// 現在の取引価格
require_once('rate.php');
// $btc_jpy_rate;

// 最新の取引価格の履歴
require_once('transactions.php');
// $transaction_rate_btc; 取引時のレート
// $transaction_bill_jpy; 売却時に得た日本円

// 自身の残高
// $nonce に+1をしている
require_once('balance.php');
// $my_jpy;
// $my_btc;

// DBのデータを取得
$select_sql = 'SELECT * FROM coincheck where id = 1';
$select_stmt = $dbh->prepare($select_sql);
$select_stmt->execute();
$coincheck_info = $select_stmt->fetch(PDO::FETCH_ASSOC);

// アルゴリズム
// 損切時の緊急停止
$order['bool'] = '';

if ($coincheck_info['stop_flg'] === 1) {
	//メール送りたいね
	$to = 'hxh.feitan@gmail.com';
	$subject = 'Stop';
	$message = 'Emergency';
	$headers = 'From: hxh.feitan@gmail.com';
	mail($to, $subject, $message, $headers);
	exit();
}

if ($my_btc > 0) {
	// BTCが存在する時
	if ($coincheck_info['up_count'] > 1) {
		//レートが上がった時
		if ($coincheck_info['rate'] < $btc_jpy_rate) {
			$update_sql = 'UPDATE coincheck set up_count = :up_count, down_count = :down_count where id = 1';
			$update_stmt = $dbh->prepare($update_sql);
			$update_stmt->bindvalue('up_count', $coincheck_info['up_count'] + 1, PDO::PARAM_INT);
			$update_stmt->bindvalue('down_count', 0, PDO::PARAM_INT);
			$update_stmt->execute();
		//レートが下がったとき
		} else {
			// 取引履歴より現在のレートの方が高い
			if ($transaction_rate_btc < $btc_jpy_rate) {

				if ($coincheck_info['up_count'] > 3) {
					//BTC売り
					$order['bool'] = 'btc_sell';
					$update_sql = 'UPDATE coincheck set up_count = :up_count, down_count = :down_count where id = 1';
					$update_stmt = $dbh->prepare($update_sql);
					$update_stmt->bindvalue('up_count', 0, PDO::PARAM_INT);
					$update_stmt->bindvalue('down_count', 0, PDO::PARAM_INT);
					$update_stmt->execute();
				} elseif ($coincheck_info['down_count'] >= 1) {
					//BTC売り
					$order['bool'] = 'btc_sell';
					$update_sql = 'UPDATE coincheck set up_count = :up_count, down_count = :down_count where id = 1';
					$update_stmt = $dbh->prepare($update_sql);
					$update_stmt->bindvalue('up_count', 0, PDO::PARAM_INT);
					$update_stmt->bindvalue('down_count', 0, PDO::PARAM_INT);
					$update_stmt->execute();
				} else {
					$update_sql = 'UPDATE coincheck set down_count = :down_count where id = 1';
					$update_stmt = $dbh->prepare($update_sql);
					$update_stmt->bindvalue('down_count', 1, PDO::PARAM_INT);
					$update_stmt->execute();
				}
			} else {
				// 取引履歴より現在のレートの方が低い
				$loss_cut_rate = $transaction_rate_btc * 0.9;
				if ($loss_cut_rate >= $btc_jpy_rate) {
					// 損切
					$order['bool'] = 'lost_cut';
					//stop_flg変更
					$update_sql = 'UPDATE coincheck set up_count = :up_count, down_count = :down_count, stop_flg = :stop_flg where id = 1';
					$update_stmt = $dbh->prepare($update_sql);
					$update_stmt->bindvalue('up_count', 0, PDO::PARAM_INT);
					$update_stmt->bindvalue('down_count', 0, PDO::PARAM_INT);
					$update_stmt->bindvalue('stop_flg', 1, PDO::PARAM_INT);
					$update_stmt->execute();
				}
				// 何もしない
				// 上がるのを待つ
			}
		}
	} else {
		//up_countが0か1の時
		//かつレートが上がっている
		if ($coincheck_info['rate'] < $btc_jpy_rate) {
			//up_countを上げる
			$update_sql = 'UPDATE coincheck set up_count = :up_count, down_count = :down_count where id = 1';
			$update_stmt = $dbh->prepare($update_sql);
			$update_stmt->bindvalue('up_count', $coincheck_info['up_count'] + 1, PDO::PARAM_INT);
			$update_stmt->bindvalue('down_count', 0, PDO::PARAM_INT);
			$update_stmt->execute();
		// レートが下がっているとき
		} else {
			//急落した場合
			$loss_cut_rate = $transaction_rate_btc * 0.9;
			if ($loss_cut_rate >= $btc_jpy_rate) {
				// 損切
				$order['bool'] = 'lost_cut';
				//stop_flg変更
				$update_sql = 'UPDATE coincheck set up_count = :up_count, down_count = :down_count, stop_flg = :stop_flg where id = 1';
				$update_stmt = $dbh->prepare($update_sql);
				$update_stmt->bindvalue('up_count', 0, PDO::PARAM_INT);
				$update_stmt->bindvalue('down_count', 0, PDO::PARAM_INT);
				$update_stmt->bindvalue('stop_flg', 1, PDO::PARAM_INT);
				$update_stmt->execute();
			}
		}
	}
} else {
	// BTCが存在しない時つまりJPYが存在する
	if ($coincheck_info['down_count'] > 2) {
		//3連続下がっていて、現在のレートの方がDBレートより低い時買い
		if ($coincheck_info['down_count'] >= 3 && $coincheck_info['rate'] > $btc_jpy_rate) {
			$order['bool'] = 'btc_buy';
			//リセット
			$update_sql = 'UPDATE coincheck set up_count = :up_count, down_count = :down_count where id = 1';
			$update_stmt = $dbh->prepare($update_sql);
			$update_stmt->bindvalue('up_count', 0, PDO::PARAM_INT);
			$update_stmt->bindvalue('down_count', 0, PDO::PARAM_INT);
			$update_stmt->execute();
			var_dump('0');
		//まだ変更の余地がある
		//下がりきって上がった時
		} elseif ($coincheck_info['rate'] < $btc_jpy_rate) {
			//2連続で上がった時
			if ($coincheck_info['up_count'] >= 1) {
				//BTC買い
				$order['bool'] = 'btc_buy';
				//リセット
				$update_sql = 'UPDATE coincheck set up_count = :up_count, down_count = :down_count where id = 1';
				$update_stmt = $dbh->prepare($update_sql);
				$update_stmt->bindvalue('up_count', 0, PDO::PARAM_INT);
				$update_stmt->bindvalue('down_count', 0, PDO::PARAM_INT);
				$update_stmt->execute();
				var_dump('1');
			} else {
				// upカウントを上げる
				$update_sql = 'UPDATE coincheck set up_count = :up_count where id = 1';
				$update_stmt = $dbh->prepare($update_sql);
				$update_stmt->bindvalue('up_count', 1, PDO::PARAM_INT);
				$update_stmt->execute();
				var_dump('1.2');
			}
		//下がりきってまだ下がってる時
		} else {
			//downカウントを上げる
			//upカウントのリセッしない
			$update_sql = 'UPDATE coincheck set down_count = :down_count where id = 1';
			$update_stmt = $dbh->prepare($update_sql);
			$update_stmt->bindvalue('down_count', $coincheck_info['down_count'] + 1, PDO::PARAM_INT);
			$update_stmt->execute();
			var_dump('2');
		}
	//down_countが低い
	} else {
		if ($coincheck_info['rate'] < $btc_jpy_rate) {
			// レートが上がってしまっている
			//down_countをリセット
			$update_sql = 'UPDATE coincheck set down_count = :down_count where id = 1';
			$update_stmt = $dbh->prepare($update_sql);
			$update_stmt->bindvalue('down_count', 0, PDO::PARAM_INT);
			$update_stmt->execute();
			var_dump('3');
		} else {
			//レートが下がっている
			//down_countを上げる
			$update_sql = 'UPDATE coincheck set down_count = :down_count where id = 1';
			$update_stmt = $dbh->prepare($update_sql);
			$update_stmt->bindvalue('down_count', $coincheck_info['down_count'] + 1, PDO::PARAM_INT);
			$update_stmt->execute();
			var_dump('4');
		}
	}
}

/*
 *-----------------------------------------------------------
 * order
 *-----------------------------------------------------------
 */

if ($order['bool'] !== '') {
	// coincheck order api url
	$url_orders = "https://coincheck.com/api/exchange/orders";

	// BTC買い
	if ($order['bool'] === 'btc_buy') {
		// BTCのレート
		$order["rate"] = $btc_jpy_rate;
		// 取引量BTC
		$btc_amount = $coincheck_info['bill'] / $btc_jpy_rate;
		$order["amount"] = $btc_amount;
		// sell or buy
		$order["order_type"] = 'buy';
	}
	// BTC売り
	if ($order['bool'] === 'btc_sell') {
		// BTCのレート
		$order["rate"] = $btc_jpy_rate;
		// 取引量BTC
		$order["amount"] = $my_btc;
		// sell or buy
		$order["order_type"] = 'sell';
		$my_bill = $btc_jpy_rate * $my_btc;
	}
	// 損切
	if ($order['bool'] === 'lost_cut') {
		// BTCのレート
		$order["rate"] = $btc_jpy_rate;
		// 取引量BTC
		$order["amount"] = $my_btc;
		// sell or buy
		$order["order_type"] = 'sell';
		$my_bill = $btc_jpy_rate * $my_btc;
	}
	// Post data type

	// post data
	$postdata_array = array(
		"rate" => $order["rate"],
		"amount" => $order["amount"],
		"order_type" => $order["order_type"],
		"pair" => $currency_pair
	);

	$postdata = http_build_query($postdata_array);  // POST data is Query string

	// create signature
	$nonce = time() + 2;
	$message = $nonce . $url_orders . $postdata;
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
	curl_setopt($curl, CURLOPT_URL, $url_orders);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);

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

	$to = 'hxh.feitan@gmail.com';
	$subject = 'Order';
	$message = 'rate:' . $order['rate'] . "\r\n";
	$message .= 'amount:' . $order['amount'] . "\r\n";
	$message .= 'type:' . $order['order_type'] . "\r\n";
	$headers = 'From: hxh.feitan@gmail.com';
	mail($to, $subject, $message, $headers);
	var_dump($json_decode);
}

// 最新の取引価格を保存
$update_sql = 'update coincheck set rate = :rate where id = 1';
$update_stmt = $dbh->prepare($update_sql);
$update_stmt->bindvalue('rate', $btc_jpy_rate, PDO::PARAM_INT);
$update_stmt->execute();

if (isset($my_bill)) {
	$update_sql = 'update coincheck set bill = :bill where id = 1';
	$update_stmt = $dbh->prepare($update_sql);
	$update_stmt->bindvalue('bill', $my_bill, PDO::PARAM_INT);
	$update_stmt->execute();
}

exit(0);
