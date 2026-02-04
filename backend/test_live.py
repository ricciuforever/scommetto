import requests
import json

API_KEY = "e8fd1c2aba7d8551320a0e8047b70eba"
BASE_URL = "https://v3.football.api-sports.io"
HEADERS = {
    'x-rapidapi-host': "v3.football.api-sports.io",
    'x-rapidapi-key': API_KEY
}

def get_live_fixtures():
    url = f"{BASE_URL}/fixtures"
    params = {"live": "all"}
    response = requests.get(url, headers=HEADERS, params=params)
    return response.json()

if __name__ == "__main__":
    live = get_live_fixtures()
    print(json.dumps(live, indent=2))
