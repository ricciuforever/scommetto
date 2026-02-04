import requests
import json

API_KEY = "e8fd1c2aba7d8551320a0e8047b70eba"
BASE_URL = "https://v3.football.api-sports.io"
HEADERS = {
    'x-rapidapi-host': "v3.football.api-sports.io",
    'x-rapidapi-key': API_KEY
}

def get_current_leagues():
    url = f"{BASE_URL}/leagues"
    params = {"current": "true"}
    response = requests.get(url, headers=HEADERS, params=params)
    return response.json()

if __name__ == "__main__":
    leagues = get_current_leagues()
    with open("current_leagues.json", "w") as f:
        json.dump(leagues, f, indent=4)
    print(f"Found {len(leagues.get('response', []))} current leagues.")
    # Print first 5
    for l in leagues.get("response", [])[:5]:
        print(f"ID: {l['league']['id']}, Name: {l['league']['name']}, Country: {l['country']['name']}")
