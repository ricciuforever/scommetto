import requests
import json

API_KEY = "e8fd1c2aba7d8551320a0e8047b70eba"
BASE_URL = "https://v3.football.api-sports.io"
HEADERS = {
    'x-rapidapi-host': "v3.football.api-sports.io",
    'x-rapidapi-key': API_KEY
}

def get_seasons(league_id=135):
    url = f"{BASE_URL}/leagues"
    params = {"id": league_id}
    response = requests.get(url, headers=HEADERS, params=params)
    return response.json()

if __name__ == "__main__":
    seasons = get_seasons()
    print(json.dumps(seasons, indent=2))
