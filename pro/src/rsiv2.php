<?php

require '../vendor/autoload.php';
function get_cryptocurrency_data($api_key)
{
    $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';

    $parameters = [
        'start' => '1',
        'limit' => '1',
        'sort' => 'market_cap',
        'convert' => 'USD',
    ];

    $headers = [
        'Accepts: application/json',
        'X-CMC_PRO_API_KEY: ' . $api_key,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($parameters));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['status']['error_code']) && $data['status']['error_code'] == 0) {
        return $data['data'];
    } else {
        $error_message = $data['status']['error_message'];
        throw new Exception("API request failed. Error: $error_message");
    }
}

function get_ohlc_data($symbol, $api_key, $timeframe)
{
    $url = "https://rest.coinapi.io/v1/ohlcv/BINANCE_SPOT_{$symbol}_USDT/latest?period_id={$timeframe}MIN";
    $headers = [
        'X-CoinAPI-Key: ' . $api_key,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    $ohlc_price_list = [];
    $date_list = [];

    if (count($data) < 14) {
        throw new Exception("Not enough data points for the specified timeframe.");
    }

    // for ($i = 0; $i < 14; $i++) {
    //     $close_price = $data[13 - $i]['price_close'];
    //     $date = $data[13 - $i]['time_period_start'];
    //     $ohlc_price_list[] = $close_price;
    //     $date_list[] = $date;
    // }
    for ($i = 0; $i < count($data); $i++) {
        $ohlc_price_list[] = $data[count($data) - 1 - $i]['price_close'];
        $date_list[] = $data[count($data) - 1 - $i]['time_period_start'];
    }

    var_dump($date_list[0], $date_list[count($data) - 1]);

    return [$ohlc_price_list, $date_list];
}

function calculate_rsi($data, $period = 14)
{
    $changes = [];
    $gains = [];
    $losses = [];

    // Calculate price changes and separate gains and losses
    for ($i = 1; $i < count($data); $i++) {
        $changes[] = $data[$i] - $data[$i - 1];
        if ($changes[$i - 1] >= 0) {
            $gains[] = $changes[$i - 1];
            $losses[] = 0;
        } else {
            $gains[] = 0;
            $losses[] = abs($changes[$i - 1]);
        }
    }

    // Calculate average gains and losses over the given period
    $avg_gain = array_sum(array_slice($gains, 0, $period)) / $period;
    $avg_loss = array_sum(array_slice($losses, 0, $period)) / $period;

    for ($i = $period; $i < count($data) - 1; $i++) {
        $avg_gain = ($avg_gain * ($period - 1) + $gains[$i]) / $period;
        $avg_loss = ($avg_loss * ($period - 1) + $losses[$i]) / $period;
    }

    // Calculate Relative Strength (RS) and Relative Strength Index (RSI)
    $rs = ($avg_gain > 0) ? $avg_gain / $avg_loss : 0;
    $rsi = 100 - (100 / (1 + $rs));

    return $rsi;
}

$api_key_1 = '37b4a617-9609-48c6-8a41-d48db5b2ed44';
$api_key_2 = 'AA08B0F0-3B4F-4FD2-A8BC-E9822FD36AD5';

try {
    $cryptocurrencies = get_cryptocurrency_data($api_key_1);

    $symbol_list = [];

    $telegram = new \TelegramBot\Api\BotApi('6359501815:AAFV51Ogex8qP-p3Cl7AUW14vAc6FsE59ok');

    function send_telegram_message($chatId, $message)
    {
        global $telegram;
        $telegram->sendMessage($chatId, $message, 'HTML');
    }

    $message = "";
    $chatId = '5629632710';
    $send_chk = 0;

    foreach ($cryptocurrencies as $crypto) {
        $symbol = $crypto['symbol'];
        $timeframes = [5, 15, 30];
        $rsi = [];

        foreach ($timeframes as $timeframe) {
            try {
                $ohlc_price_list_symbol = get_ohlc_data($symbol, $api_key_2, $timeframe);

                $rsi_timeframe = calculate_rsi($ohlc_price_list_symbol[0]);
                $rsi[] = $rsi_timeframe;
            } catch (Exception $e) {
                echo "Error occurred while processing symbol $symbol and timeframe $timeframe: " . $e->getMessage() . "\n";
                continue;
            }
        }
        var_dump($symbol, $rsi);

        if (count($rsi) === 3) {
            if ($rsi[0] < 30 && $rsi[1] < 30 && $rsi[2] < 30) {
                $send_chk = 1;
                echo "$symbol: Oversold\n";
                $message .= "<br>" . $symbol . " : Oversold" . "</br>";
            } elseif ($rsi[0] > 70 && $rsi[1] > 70 && $rsi[2] > 70) {
                $send_chk = 1;
                echo "$symbol: Overbought\n";
                $message .= "<br>" . $symbol . " : Overbought" . "</br>";
            }
            $message .= chr(10);
        }
    }
    var_dump($message);
    if ($send_chk) {
        send_telegram_message($chatId, $message);
    }
} catch (Exception $e) {
    echo $e->getMessage() . "<br>";
}
