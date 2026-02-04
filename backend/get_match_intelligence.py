import requests
import json
import os

API_KEY = "e8fd1c2aba7d8551320a0e8047b70eba"
BASE_URL = "https://v3.football.api-sports.io"
HEADERS = {
    'x-rapidapi-host': "v3.football.api-sports.io",
    'x-rapidapi-key': API_KEY
}

def get_fixture_details(fixture_id):
    """Gathers everything needed for Gemini to analyze a match."""
    print(f"Gathering intelligence for fixture {fixture_id}...")
    
    intelligence = {
        "fixture_id": fixture_id,
        "stats": None,
        "events": None,
        "lineups": None,
        "h2h": None,
        "standings": None,
        "odds_pre": None,
        "odds_live": None
    }

    # 1. LIVE STATISTICS & EVENTS
    # Endpoint: /fixtures/statistics?fixture=...
    # Endpoint: /fixtures/events?fixture=...
    stats_res = requests.get(f"{BASE_URL}/fixtures/statistics", headers=HEADERS, params={"fixture": fixture_id})
    events_res = requests.get(f"{BASE_URL}/fixtures/events", headers=HEADERS, params={"fixture": fixture_id})
    
    intelligence["stats"] = stats_res.json().get("response", [])
    intelligence["events"] = events_res.json().get("response", [])

    # 2. MATCH INFO (To get teams and league)
    fix_res = requests.get(f"{BASE_URL}/fixtures", headers=HEADERS, params={"id": fixture_id})
    fix_data = fix_res.json().get("response", [])
    if not fix_data:
        return {"error": "Fixture not found"}
    
    fixture = fix_data[0]
    home_id = fixture["teams"]["home"]["id"]
    away_id = fixture["teams"]["away"]["id"]
    league_id = fixture["league"]["id"]
    season = fixture["league"]["season"]

    # 3. HEAD TO HEAD (H2H)
    # Endpoint: /fixtures/headtohead?h2h=ID1-ID2
    h2h_res = requests.get(f"{BASE_URL}/fixtures/headtohead", headers=HEADERS, params={"h2h": f"{home_id}-{away_id}", "last": 5})
    intelligence["h2h"] = h2h_res.json().get("response", [])

    # 4. STANDINGS
    # Endpoint: /standings?league=...&season=...
    stand_res = requests.get(f"{BASE_URL}/standings", headers=HEADERS, params={"league": league_id, "season": season})
    intelligence["standings"] = stand_res.json().get("response", [])

    # 5. PRE-MATCH ODDS (Context)
    # Endpoint: /odds?fixture=...
    odds_pre_res = requests.get(f"{BASE_URL}/odds", headers=HEADERS, params={"fixture": fixture_id})
    intelligence["odds_pre"] = odds_pre_res.json().get("response", [])

    # 6. IN-PLAY ODDS (Real-time betting options)
    # Endpoint: /odds/live?fixture=...
    odds_live_res = requests.get(f"{BASE_URL}/odds/live", headers=HEADERS, params={"fixture": fixture_id})
    intelligence["odds_live"] = odds_live_res.json().get("response", [])

    return intelligence

def hex_to_gemini_prompt(intelligence):
    """Converts the JSON intelligence into a structured text prompt for Gemini."""
    prompt = f"Analyze this football match for potential betting value.\n"
    prompt += f"Match Data: {json.dumps(intelligence, indent=2)}\n"
    prompt += "\nTask:\n"
    prompt += "1. Evaluate the momentum using live statistics (shots, dangerous attacks).\n" 
    prompt += "2. Compare current live odds with pre-match odds and the flow of the game.\n"
    prompt += "3. Identify if there's a 'Value Bet' (e.g., strong favorite losing but statistics show they are dominating).\n"
    prompt += "4. Suggest a specific bet (Market, Outcome, Stake %) and reasoning.\n"
    return prompt

if __name__ == "__main__":
    # Example ID (needs to be a live or recent fixture to have data)
    # You can get one from the live dashboard
    test_id = 123456 # Placeholder
    # intel = get_fixture_details(test_id)
    # print(json.dumps(intel, indent=2))
    print("Script ready. Use get_fixture_details(id) to gather data for Gemini.")
