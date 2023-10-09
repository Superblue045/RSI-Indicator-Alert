<?php

require '../vendor/autoload.php';
// use Telegram\Bot\Api as teleAPI;

function create_data_folder($directory)
{
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }
}

function get_cryptocurrency_data($api_key)
{
    $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';

    $parameters = array(
        'start' => '1',
        'limit' => '200',
        'sort' => 'market_cap',
        'convert' => 'USD'
    );

    $headers = array(
        'Accepts: application/json',
        'X-CMC_PRO_API_KEY: ' . $api_key
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($data['status']['error_code'] == 0) {
        return $data['data'];
    } else {
        $error_message = $data['status']['error_message'];
        throw new Exception("API request failed. Error: $error_message");
    }
}

function compare_symbol_list($cryptocurrencies, $root_directory)
{
    $symbol_list = array_map(function ($crypto) {
        return $crypto['symbol'];
    }, $cryptocurrencies);

    $file_name_list = scandir($root_directory);

    foreach ($file_name_list as $filename) {
        if ($filename !== '.' && $filename !== '..') {
            $symbol_file_name = pathinfo($filename, PATHINFO_FILENAME);

            $file_path = $root_directory . '/' . $filename;
            // echo $file_path . "\n";

            if (in_array($symbol_file_name, $symbol_list)) {
                //echo "The $symbol_file_name is exist.\n";
            } else {
                echo "The $symbol_file_name is not present in the symbol_list.\n";
                try {
                    unlink($file_path);
                    echo "$file_path removed successfully\n";
                } catch (FileNotFoundError $e) {
                    echo "The $file_path does not exist\n";
                } catch (Exception $e) {
                    echo "Error occurred while removing the file: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}

function save_prices_to_csv($crypto, $root_directory, $avgs)
{

    $symbol = $crypto['symbol'];
    $price = $crypto['quote']['USD']['price'];
    $date = $crypto['quote']['USD']['last_updated'];

    $row_data = [$symbol, $price, $date];

    $filename = $root_directory . '/' . $symbol . ".csv";
    try {
        $file_exists = file_exists($filename);
        $csvfile = fopen($filename, 'a');

        if (!$file_exists) {
            fputcsv($csvfile, ['symbol', 'price', 'Date', 'RSI', 'avgGain', 'avgLoss']);
        }

        fputcsv($csvfile, array_merge($row_data, [$crypto['rsi']], $avgs));

        fclose($csvfile);
    } catch (Exception $e) {
        echo "Error occurred while saving price and RSI to $filename.";
    }
}
function calculate_avg($data, $period = 14, $pavg_gain = 0, $pavg_loss = 0)
{
    $changes = [];
    $gains = [];
    $losses = [];

    if (count($data) == $period + 1) {
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
        $avg_gain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avg_loss = array_sum(array_slice($losses, 0, $period)) / $period;
        return [$avg_gain, $avg_loss];
    } else if (count($data) > $period + 1) {
        $change = $data[count($data) - 1] - $data[count($data) - 2];
        $gain = 0;
        $loss = 0;
        if ($change >= 0) {
            $gain = $change;
            $loss = 0;
        } else {
            $gain = 0;
            $loss = abs($change);
        }
        $avg_gain = ($pavg_gain * ($period - 1) + $gain) / $period;
        $avg_loss = ($pavg_loss * ($period - 1) + $loss) / $period;
        return [$avg_gain, $avg_loss];
    } else {
        return [0, 0];
    }
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
function get_rsi($avgs)
{
    $rs = ($avgs[0] > 0 && $avgs[1] > 0) ? $avgs[0] / $avgs[1] : 0;
    $rsi = 100 - (100 / (1 + $rs));
    return $rsi;
}

$root_directory = "./data";

create_data_folder($root_directory);

$api_key = '37b4a617-9609-48c6-8a41-d48db5b2ed44';

try {
    $cryptocurrencies = get_cryptocurrency_data($api_key);
    compare_symbol_list($cryptocurrencies, $root_directory);

    $telegram = new \TelegramBot\Api\BotApi('6359501815:AAFV51Ogex8qP-p3Cl7AUW14vAc6FsE59ok');

    function send_telegram_message($chatId, $message)
    {
        global $telegram;
        $telegram->sendMessage($chatId, $message, 'HTML');
    }

    $message = "";
    $chatId = '5629632710';

    $crypto_rsi = [];

    foreach ($cryptocurrencies as $crypto) {
        $symbol = $crypto['symbol'];
        $date = $crypto['quote']['USD']['last_updated'];
        $filename = $root_directory . '/' . $symbol . ".csv";
        $file_exists = file_exists($filename);
        // if (!$file_exists) {
        //     save_prices_to_csv($crypto, $root_directory);
        // }


        $prices = array();
        $avg_gain = 0;
        $avg_loss = 0;

        if ($file_exists) {
            $csvfile = fopen($filename, 'r');
            fgetcsv($csvfile);
            while (($row = fgetcsv($csvfile)) !== false) {
                $prices[] = floatval($row[1]);
                $avg_gain = floatval($row[4]);
                $avg_loss = floatval($row[5]);
            }
            fclose($csvfile);
        }

        $prices[] = $crypto['quote']['USD']['price'];

        $avgs = calculate_avg($prices, 14, $avg_gain, $avg_loss);

        $avg_gain = $avgs[0];
        $avg_loss = $avgs[1];

        $rsi = get_rsi($avgs);

        $crypto['rsi'] = $rsi;

        save_prices_to_csv($crypto, $root_directory, $avgs);


        if ($rsi != 0 && ($rsi < 30 || $rsi > 70)) {
            $crypto_rsi[] = ["title" => $symbol, "value" => $rsi];
        }
    }

    // usort($crypto_rsi, function($a, $b) {
    //     return $a['value'] <=> $b['value'];
    // });
    // foreach ($crypto_rsi as $key => $crypt) {
    //     // if($key<20){
    //         if($crypt['value']>70){
    //             $message .= "<b>". $crypt['title']." - RSI: ".round($crypt['value'],2) ."</b>";

    //         }else{
    //             $message .= "<i>". $crypt['title']." - RSI: ".round($crypt['value'],2) ."</i>";
    //         }
    //         $message.=chr(10);
    //     // }
    // }


    // send_telegram_message($chatId, $message);
    // var_dump($message);
} catch (Exception $e) {
    echo $e->getMessage() . "<br>";
}
