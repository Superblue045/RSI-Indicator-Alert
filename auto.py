import os
import requests
import csv
import numpy as np
import time
import threading
import pandas as pd

def create_data_folder(directory):
    if not os.path.exists(directory):
        os.makedirs(directory)

def get_cryptocurrency_list(api_key, id):
    url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest'

    parameters = {
        'start': id,
        'limit': '100',
        'sort': 'market_cap',
        'convert': 'USD',
    }

    headers = {
        'Accepts': 'application/json', 
        'X-CMC_PRO_API_KEY': api_key
    }

    response = requests.get(url, headers=headers, params=parameters)

    data = response.json()

    if response.status_code == 200 and data['status']['error_code'] == 0:
        return data['data']
    else:
        error_message = data['status']['error_message']
        raise Exception(f"API request failed. Error: {error_message}")

def get_ohlcv_price(api_key, symbols):

    symbols_str = ','.join(symbols)

    base_url = 'https://pro-api.coinmarketcap.com/v2/cryptocurrency/ohlcv/latest'

    parameters = {
        'symbol': symbols_str,
        'convert': 'USD'
    }

    headers = {
        'Accepts': 'application/json',
        'X-CMC_PRO_API_KEY': api_key
    }

    response = requests.get(base_url, headers=headers, params=parameters)
    data = response.json()

    if response.status_code == 200 and data['status']['error_code'] == 0:
        prices = data['data']
        result = {}

        for symbol in symbols:

            upper_symbol = symbol.upper()
            close_price = prices[upper_symbol][0]['quote']['USD']['close']
            updated_time = prices[upper_symbol][0]['quote']['USD']['last_updated']
            result[upper_symbol] = {'close_price': close_price, 'updated_time': updated_time}

        return result
    else:
        error_message = data['status']['error_message']
        raise Exception(f"API request failed. Error: {error_message}")

def save_prices_to_csv(symbol, close_prices, root_directory, crypto):

        price = close_prices[symbol]['close_price']
        # updated_time = close_prices[symbol]['updated_time']
        date = crypto['quote']['USD']['last_updated']

        row_data = [symbol, price, date]
        filename = os.path.join(root_directory, symbol + ".csv")
        try:
            file_exists = os.path.isfile(filename)
            with open(filename, 'a', newline='') as csvfile:
                writer = csv.writer(csvfile)

                if not file_exists:
                    writer.writerow(['symbol', 'price', 'Date', 'RSI'])

                writer.writerow(row_data + [crypto.get('rsi', '')])
        except IOError:
            print(f'Error occurred while saving price and RSI to {filename}.')

def calculate_rsi(ohlc: pd.DataFrame, period: int = 14, round_rsi: bool = True):
    
    delta = ohlc["price"].diff()
    up = delta.copy()
    up[up < 0] = 0
    up = pd.Series.ewm(up, alpha=1/period).mean()
    down = delta.copy()
    down[down > 0] = 0
    down *= -1
    down = pd.Series.ewm(down, alpha=1/period).mean()
    rsi = np.where(up == 0, 0, np.where(down == 0, 100, 100 - (100 / (1 + up / down))))
    return np.round(rsi, 2) if round_rsi else rsi

def run_script():

    root_directory = "./data"

    create_data_folder(root_directory)

    api_key = '8c122a49-8f79-49cb-aea0-c3f3f4acacbf'

    try:

        ids = [1, 101]

        for id in ids:

            cryptocurrencies = get_cryptocurrency_list(api_key, id)

            symbols = []

            for crypto in cryptocurrencies:
                symbol = crypto['symbol']
                symbols.append(symbol)

            close_prices = get_ohlcv_price(api_key, symbols)
            # print(close_prices)

            for crypto in cryptocurrencies:

                symbol = crypto['symbol']
                if symbol not in close_prices:
                    continue

                filename = os.path.join(root_directory, symbol + ".csv")
                file_exists = os.path.isfile(filename)
                if not file_exists:
                    save_prices_to_csv(symbol, close_prices, root_directory, crypto)
                else:
                    pass

                with open(filename, 'r') as csvfile:
                    reader = csv.reader(csvfile)
                    next(reader)
                    prices = [float(row[1]) for row in reader]

                ohlc = pd.read_csv(filename, index_col = "Date")

                rsi = calculate_rsi(ohlc)[-1]

                print(f"{symbol}: {rsi}")

                crypto['rsi'] = rsi

                save_prices_to_csv(symbol, close_prices, root_directory, crypto)


    except Exception as e:
        print(str(e))

    print("Running script...")

def run_script_every_10_minutes():
    run_script()

    interval = 1 * 60
    threading.Timer(interval, run_script_every_10_minutes).start()

run_script_every_10_minutes()

while True:
    time.sleep(1)
