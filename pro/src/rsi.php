<?php

require '../vendor/autoload.php';

function get_cryptocurrency_data($api_key)
{
    $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
    $parameters = array(
        'start' => '1',
        'limit' => '200',
        'sort' => 'market_cap',
        'convert' => 'USD',
    );
    $headers = array(
        'Accepts: application/json',
        'X-CMC_PRO_API_KEY: ' . $api_key
    );

    $url_with_params = $url . '?' . http_build_query($parameters);
    $ch = curl_init($url_with_params);
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

function get_ohlc_data($symbol, $timeframe)
{
    $base_url = "https://www.mexc.com/open/api/v2/market/kline?symbol={$symbol}_USDT&interval={$timeframe}&limit=50";

    $ch = curl_init($base_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $all_data = json_decode($response, true);

    $close_price_list = [];

    if (isset($all_data['code']) && $all_data['code'] == 200) {
        foreach ($all_data['data'] as $item) {
            $close_price = $item[2];
            $close_price_list[] = $close_price;
        }

        return $close_price_list;
    }
}

function calculate_rsi($data, $period = 14)
{

    if (!is_array($data)) {
        throw new Exception("Invalid data format. Expected an array.");
    }

    $data = array_map('floatval', $data);

    $changes = $gains = $losses = [];

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

    $avg_gain = array_sum(array_slice($gains, 0, $period)) / $period;
    $avg_loss = array_sum(array_slice($losses, 0, $period)) / $period;

    for ($i = $period; $i < count($data) - 1; $i++) {
        $avg_gain = ($avg_gain * ($period - 1) + $gains[$i]) / $period;
        $avg_loss = ($avg_loss * ($period - 1) + $losses[$i]) / $period;
    }

    $rs = ($avg_loss != 0) ? ($avg_gain / $avg_loss) : 0;
    $rsi = 100 - (100 / (1 + $rs));

    return $rsi;
}

$api_key = '8c122a49-8f79-49cb-aea0-c3f3f4acacbf';

try {

    $telegram = new \TelegramBot\Api\BotApi('6349402772:AAHU4nMst0YFEyoxaogG5Nmnh8Q_kCQa9qY');
    function send_telegram_message($chatId, $message)
    {
        global $telegram;
        $telegram->sendMessage($chatId, $message, 'HTML');
    }
    $chatId = '469915416';

    $cryptocurrencies = get_cryptocurrency_data($api_key);
    $symbol_list = [];

    foreach ($cryptocurrencies as $crypto) {
        $symbol = $crypto['symbol'];
        $symbol_list[] = $symbol;
    }
    
    $message = "";

    foreach ($symbol_list as $symbol) {
        $timeframes = ['5m', '15m', '30m'];
        $rsi_list = [];
        
        try {
            foreach ($timeframes as $timeframe) {
                $close_price_list = get_ohlc_data($symbol, $timeframe);
                $rsi = calculate_rsi($close_price_list);
                $rsi_list[] = $rsi;
            }

            // echo "$symbol: " . implode(', ', $rsi_list) . "\n";
            
            if (count($rsi_list) === 3 && $rsi_list[0] !== 0 && $rsi_list[1] !== 0 && $rsi_list[2] !== 0) {
                if ($rsi_list[0] < 30 && $rsi_list[1] < 30 && $rsi_list[2] < 30) {
                    echo "$symbol: Oversold\n";
                    $message .= "<b>" . $symbol . ": Oversold" . "</b>";
                    $message.=chr(10);
                } elseif ($rsi_list[0] > 70 && $rsi_list[1] > 70 && $rsi_list[2] > 70) {
                    echo "$symbol: Overbought\n";
                    $message .= "<i>" . $symbol . ": Overbought" . "</i>";
                    $message.=chr(10);
                }
            }

        } catch (Exception $e) {
            echo "$symbol cannot be used in API calls.: " . $e->getMessage() . "\n";
            continue;
        }
    }

    if (!empty($message)) {
        send_telegram_message($chatId, $message);
        var_dump($message);
    }

} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
?>