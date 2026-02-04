import requests
import json
import time

API_KEY = "e8fd1c2aba7d8551320a0e8047b70eba"
BASE_URL = "https://v3.football.api-sports.io"
HEADERS = {
    'x-rapidapi-host': "v3.football.api-sports.io",
    'x-rapidapi-key': API_KEY
}

def get_teams(league_id=135, season=2024):
    url = f"{BASE_URL}/teams"
    params = {"league": league_id, "season": season}
    response = requests.get(url, headers=HEADERS, params=params)
    data = response.json()
    return [t["team"]["id"] for t in data.get("response", [])]

def get_squad(team_id):
    url = f"{BASE_URL}/players/squads"
    params = {"team": team_id}
    response = requests.get(url, headers=HEADERS, params=params)
    return response.json()

if __name__ == "__main__":
    team_ids = get_teams()
    print(f"Found {len(team_ids)} teams. Fetching squads...")
    
    all_squads = {}
    for tid in team_ids:
        print(f"Fetching squad for team {tid}...")
        squad = get_squad(tid)
        if squad.get("response"):
            team_name = squad["response"][0]["team"]["name"]
            all_squads[team_name] = squad["response"][0]["players"]
        time.sleep(1) # Rate limit safety
        
    with open("serie_a_squads.json", "w") as f:
        json.dump(all_squads, f, indent=4)
        
    print(f"Saved squads for {len(all_squads)} teams.")
