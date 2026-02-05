import os
import requests
import json
import time
from dotenv import load_dotenv

load_dotenv()

API_KEY = os.getenv("FOOTBALL_API_KEY")
BASE_URL = "https://v3.football.api-sports.io"
HEADERS = {
    'x-rapidapi-host': "v3.football.api-sports.io",
    'x-rapidapi-key': API_KEY
}

# Simple in-memory cache
cache = {
    "standings": {}, # league_id_season: {data, timestamp}
    "h2h": {},       # team1_team2: {data, timestamp}
    "pre_odds": {}   # fixture_id: {data, timestamp}
}

def get_fixture_details(fixture_id, fixture_data=None, usage_callback=None):
    """
    Gathers everything needed for Gemini.
    If fixture_data is provided (e.g. from live_matches.json), saves 1 API call.
    """
    print(f"Gathering intelligence for fixture {fixture_id}...")
    
    intelligence = {
        "fixture_id": fixture_id,
        "fixture": None,
        "stats": None,
        "events": None,
        "h2h": None,
        "standings": None,
        "odds_pre": None,
        "odds_live": None
    }

    def safe_get(url, params):
        try:
            res = requests.get(url, headers=HEADERS, params=params)
            if usage_callback:
                usage_callback(res)
            return res.json().get("response", [])
        except Exception as e:
            print(f"API Error in get_fixture_details: {e}")
            return []

    # 1. LIVE STATISTICS & EVENTS (Always fresh)
    intelligence["stats"] = safe_get(f"{BASE_URL}/fixtures/statistics", {"fixture": fixture_id})
    intelligence["events"] = safe_get(f"{BASE_URL}/fixtures/events", {"fixture": fixture_id})

    # 2. MATCH INFO
    if fixture_data:
        fixture = fixture_data
    else:
        fix_res_data = safe_get(f"{BASE_URL}/fixtures", {"id": fixture_id})
        if not fix_res_data:
            return {"error": "Fixture not found"}
        fixture = fix_res_data[0]
    
    intelligence["fixture"] = fixture
    home_id = fixture["teams"]["home"]["id"]
    away_id = fixture["teams"]["away"]["id"]
    league_id = fixture["league"]["id"]
    season = fixture["league"]["season"]

    # 3. HEAD TO HEAD (Cache for 24h)
    h2h_key = f"{min(home_id, away_id)}_{max(home_id, away_id)}"
    now = time.time()
    if h2h_key in cache["h2h"] and (now - cache["h2h"][h2h_key]["timestamp"]) < 86400:
        intelligence["h2h"] = cache["h2h"][h2h_key]["data"]
    else:
        intelligence["h2h"] = safe_get(f"{BASE_URL}/fixtures/headtohead", {"h2h": f"{home_id}-{away_id}", "last": 5})
        cache["h2h"][h2h_key] = {"data": intelligence["h2h"], "timestamp": now}

    # 4. STANDINGS (Cache for 1h)
    stand_key = f"{league_id}_{season}"
    if stand_key in cache["standings"] and (now - cache["standings"][stand_key]["timestamp"]) < 3600:
        intelligence["standings"] = cache["standings"][stand_key]["data"]
    else:
        intelligence["standings"] = safe_get(f"{BASE_URL}/standings", {"league": league_id, "season": season})
        cache["standings"][stand_key] = {"data": intelligence["standings"], "timestamp": now}

    # 5. PRE-MATCH ODDS (Cache forever)
    if fixture_id in cache["pre_odds"]:
        intelligence["odds_pre"] = cache["pre_odds"][fixture_id]
    else:
        intelligence["odds_pre"] = safe_get(f"{BASE_URL}/odds", {"fixture": fixture_id})
        cache["pre_odds"][fixture_id] = intelligence["odds_pre"]

    # 6. IN-PLAY ODDS (Always fresh)
    intelligence["odds_live"] = safe_get(f"{BASE_URL}/odds/live", {"fixture": fixture_id})

    return intelligence

def hex_to_gemini_prompt(intelligence):
    """Converts the JSON intelligence into a structured text prompt for Gemini."""
    prompt = f"Analyze this football match for potential betting value.\n"
    prompt += f"Match Data: {json.dumps(intelligence, indent=2)}\n"
    return prompt
