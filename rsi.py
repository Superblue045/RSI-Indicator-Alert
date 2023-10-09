import requests

def get_cryptocurrency_data(api_key):
    
    url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest'

    parameters = {
        'start': '1',
        'limit': '1',
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

def get_ohlc_data(symbol, timeframe):
    
    base_url = f'https://www.mexc.com/open/api/v2/market/kline?symbol={symbol}_USDT&interval={timeframe}m&limit=70'

    response = requests.get(base_url)
    all_data = response.json()

    close_price_list = []

    if all_data['code'] == 200:
        for i in range(len(all_data['data'])):
            close_price = all_data['data'][i][2]
            close_price_list.append(close_price)

        return close_price_list

def calculate_rsi(data, period=14):

    data = [float(price) for price in data]

    changes = []
    gains = []
    losses = []

    for i in range(1, len(data)):
        changes.append(data[i] - data[i - 1])
        if changes[i - 1] >= 0:
            gains.append(changes[i - 1])
            losses.append(0)
        else:
            gains.append(0)
            losses.append(abs(changes[i - 1]))

    avg_gain = sum(gains[:period]) / period
    avg_loss = sum(losses[:period]) / period

    for i in range(period, len(data) - 1):
        avg_gain = (avg_gain * (period - 1) + gains[i]) / period
        avg_loss = (avg_loss * (period - 1) + losses[i]) / period

    rs = avg_gain / avg_loss if avg_loss != 0 else 0
    rsi = 100 - (100 / (1 + rs))

    return rsi

if __name__ == '__main__':

    api_key = '37b4a617-9609-48c6-8a41-d48db5b2ed44'

    try:

        cryptocurrencies = get_cryptocurrency_data(api_key)

        symbol_list = []

        for crypto in cryptocurrencies:

            symbol = crypto['symbol']
            symbol_list.append(symbol)

        for symbol in symbol_list:

            timeframes = [5, 15, 30]
            rsi_list = []

            try:
                for timeframe in timeframes:

                    close_price_list = get_ohlc_data(symbol, timeframe)
                    rsi = calculate_rsi(close_price_list)
                    rsi_list.append(rsi)

                print(f"{symbol}: {rsi_list}")

                if len(rsi_list) == 3:
                    if rsi_list[0] < 30 and rsi_list[1] < 30 and rsi_list[2] < 30:
                        print(f"{symbol}: Oversold")
                    elif rsi_list[0] > 70 and rsi_list[1] > 70 and rsi_list[2] > 70:
                        print(f"{symbol}: Overbought")
                    else:
                        pass
                else:
                    pass

            except Exception as e:
                print(f"This {symbol} cannot be used in API calls.: {e}")
                continue

    except Exception as e:
        print(str(e))