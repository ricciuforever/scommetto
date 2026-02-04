import requests
import json
import time

API_KEY = "e8fd1c2aba7d8551320a0e8047b70eba"
BASE_URL = "https://v3.football.api-sports.io"
HEADERS = {
    'x-rapidapi-host': "v3.football.api-sports.io",
    'x-rapidapi-key': API_KEY
}

def fetch_all_players(league_id=135, season=2024):
    all_players = []
    page = 1
    
    while True:
        print(f"Fetching page {page}...")
        url = f"{BASE_URL}/players"
        params = {"league": league_id, "season": season, "page": page}
        response = requests.get(url, headers=HEADERS, params=params)
        data = response.json()
        
        if not data.get("response"):
            break
            
        all_players.extend(data["response"])
        
        # Check if there's a next page
        paging = data.get("paging", {})
        if paging.get("current") >= paging.get("total"):
            break
            
        page += 1
        time.sleep(1) # Be nice to API (though not strictly necessary here)
        
        if page > 50: # Safety break
            break
            
    return all_players

if __name__ == "__main__":
    players = fetch_all_players()
    with open("serie_a_players.json", "w") as f:
        json.dump(players, f, indent=4)
    print(f"Saved {len(players)} players to serie_a_players.json")
