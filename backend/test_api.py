import requests
import json

API_KEY = "e8fd1c2aba7d8551320a0e8047b70eba"
BASE_URL = "https://v3.football.api-sports.io"

HEADERS = {
    'x-rapidapi-host': "v3.football.api-sports.io",
    'x-rapidapi-key': API_KEY
}

def get_league_id(country="Italy", name="Serie A"):
    url = f"{BASE_URL}/leagues"
    params = {"country": country, "name": name}
    response = requests.get(url, headers=HEADERS, params=params)
    data = response.json()
    if data.get("response"):
        return data["response"][0]["league"]["id"]
    return None

def get_teams(league_id, season=2025): # Using 2025 as current season
    url = f"{BASE_URL}/teams"
    params = {"league": league_id, "season": season}
    response = requests.get(url, headers=HEADERS, params=params)
    return response.json()

if __name__ == "__main__":
    league_id = get_league_id()
    print(f"League ID for Serie A: {league_id}")
    if league_id:
        teams = get_teams(league_id)
        print(json.dumps(teams, indent=2))
        with open("serie_a_teams.json", "w") as f:
            json.dump(teams, f, indent=4)
        print(f"Saved {len(teams.get('response', []))} teams to serie_a_teams.json")
