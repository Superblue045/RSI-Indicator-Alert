#! C:\Users\RobinHood\AppData\Local\Programs\Python\Python39\python.exe
print('Content-type: application/json')  
print('Access-Control-Allow-Origin: *') 
print('Access-Control-Allow-Methods: GET, POST, OPTIONS') 
print('Access-Control-Allow-Headers: Content-Type') 
print()

import csv
import json
import os

def csv_to_json(csv_file):
    data = []
    with open(csv_file, 'r') as file:
        csv_reader = csv.DictReader(file)
        for row in csv_reader:
            data.append(row)
    return json.dumps(data)

root_directory = "./data"
csv_file = os.path.join(root_directory,  "APE.csv")

json_data = csv_to_json(csv_file)
print(json_data)