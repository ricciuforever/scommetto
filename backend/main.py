from fastapi import FastAPI, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
import json
import os
import requests
import time
from threading import Thread
from dotenv import load_dotenv

# Load variables from .env file
load_dotenv()

app = FastAPI()

# Enable CORS for frontend
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

API_KEY = os.getenv("FOOTBALL_API_KEY")
BASE_URL = "https://v3.football.api-sports.io"
HEADERS = {
    'x-rapidapi-host': "v3.football.api-sports.io",
    'x-rapidapi-key': API_KEY
}

# Global tracker for API usage
api_usage_info = {"used": 0, "remaining": 100}

def update_usage_from_response(response):
    global api_usage_info
    try:
        # Check various header variants
        used = response.headers.get("x-ratelimit-requests-used") or response.headers.get("X-RateLimit-Requests-Used")
        rem = response.headers.get("x-ratelimit-requests-remaining") or response.headers.get("X-RateLimit-Remaining")
        limit = response.headers.get("x-ratelimit-requests-limit") or response.headers.get("X-RateLimit-Limit")
        
        if rem is not None:
            api_usage_info["remaining"] = int(rem)
        if used is not None:
            api_usage_info["used"] = int(used)
        elif limit is not None and rem is not None:
            api_usage_info["used"] = int(limit) - int(rem)
            
        print(f"API Usage Updated: {api_usage_info['used']} used, {api_usage_info['remaining']} left")
    except Exception as e:
        print(f"Error updating usage: {e}")

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
LIVE_DATA_FILE = os.path.join(BASE_DIR, "live_matches.json")
TEAMS_FILE = os.path.join(BASE_DIR, "serie_a_teams.json")
SQUADS_FILE = os.path.join(BASE_DIR, "serie_a_squads.json")
BETS_HISTORY_FILE = os.path.join(BASE_DIR, "bets_history.json")

def fetch_live_data():
    print("Updating live data...")
    try:
        url = f"{BASE_URL}/fixtures?live=all"
        response = requests.get(url, headers=HEADERS)
        update_usage_from_response(response)
        data = response.json()
        with open(LIVE_DATA_FILE, "w") as f:
            json.dump(data, f)
        print("Live data updated.")
    except Exception as e:
        print(f"Error updating live data: {e}")

from get_match_intelligence import get_fixture_details
from gemini_analyzer import analyze_match_with_gemini
from check_bet_results import check_bets

@app.get("/api/intelligence/{fixture_id}")
async def get_intelligence(fixture_id: int):
    # Warning: Consumes ~6 API requests
    data = get_fixture_details(fixture_id)
    return data

@app.get("/api/analyze/{fixture_id}")
async def get_gemini_analysis(fixture_id: int):
    # Step 1: Gather raw data
    intelligence = get_fixture_details(fixture_id)
    
    # Step 2: Send to Gemini
    prediction = analyze_match_with_gemini(intelligence)
    
    # Step 3: AUTO-PLACE BET if valid JSON found
    auto_bet_status = "no_bet"
    try:
        import re
        json_match = re.search(r'```json\n([\s\S]*?)\n```', prediction)
        if json_match:
            bet_info = json.loads(json_match.group(1))
            bet_data = {
                "fixture_id": fixture_id,
                "match": f"{intelligence['fixture']['teams']['home']['name']} vs {intelligence['fixture']['teams']['away']['name']}",
                **bet_info
            }
            result = await place_bet(bet_data)
            auto_bet_status = result["status"]
    except Exception as e:
        print(f"Auto-bet error: {e}")
        auto_bet_status = f"error: {str(e)}"
    
    return {
        "fixture_id": fixture_id,
        "raw_data": intelligence,
        "prediction": prediction,
        "auto_bet_status": auto_bet_status
    }

@app.get("/api/history")
async def get_history():
    if os.path.exists(BETS_HISTORY_FILE):
        with open(BETS_HISTORY_FILE, "r") as f:
            return json.load(f)
    return []

@app.post("/api/place_bet")
async def place_bet(bet_data: dict):
    history = []
    if os.path.exists(BETS_HISTORY_FILE):
        with open(BETS_HISTORY_FILE, "r") as f:
            history = json.load(f)
    
    # DUPLICATE CHECK: Don't place the same bet for the same fixture twice
    existing = next((b for b in history if b["fixture_id"] == bet_data["fixture_id"] and b["advice"] == bet_data["advice"]), None)
    if existing:
        return {"status": "already_exists", "bet": existing}
    
    # Simple ID generation
    bet_data["id"] = str(len(history) + 1)
    bet_data["status"] = "pending"
    bet_data["timestamp"] = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())
    
    history.append(bet_data)
    with open(BETS_HISTORY_FILE, "w") as f:
        json.dump(history, f, indent=4)
        
    return {"status": "success", "bet": bet_data}

@app.get("/api/usage")
async def get_usage():
    return api_usage_info

def fetch_initial_data():
    if not os.path.exists(TEAMS_FILE):
        print("Seeding initial teams data...")
        try:
            url = f"{BASE_URL}/teams?league=135&season=2024"
            response = requests.get(url, headers=HEADERS)
            update_usage_from_response(response)
            data = response.json()
            with open(TEAMS_FILE, "w") as f:
                json.dump(data, f)
            print("Initial teams data seeded.")
        except Exception as e:
            print(f"Error seeding teams: {e}")

def auto_scanner_logic():
    print("--- 🤖 BOT SCANNER: Searching for value bets across ALL live matches ---")
    try:
        if not os.path.exists(LIVE_DATA_FILE): return
        with open(LIVE_DATA_FILE, "r") as f:
            data = json.load(f)
        
        matches = data.get("response", [])
        
        for m in matches:
            # SAFETY: Don't start a new analysis if we have less than 15 API calls left
            if api_usage_info["remaining"] < 15:
                print("--- 🤖 BOT PAUSED: API Quota too low to continue scanning ---")
                break

            fix_id = m["fixture"]["id"]
            
            # Check if already in history to avoid multiple AI calls for the same match/logic
            history = []
            if os.path.exists(BETS_HISTORY_FILE):
                with open(BETS_HISTORY_FILE, "r") as f:
                    history = json.load(f)
            
            # We only analyze a match once per 15-minute fetch cycle if it's not already logged
            if not any(b["fixture_id"] == fix_id for b in history):
                print(f"🤖 BOT: Automatic Analysis for {m['teams']['home']['name']} vs {m['teams']['away']['name']}")
                
                # Intelligence gathering (approx 6-7 calls)
                intelligence = get_fixture_details(fix_id)
                prediction = analyze_match_with_gemini(intelligence)
                
                # Auto-place if JSON found
                import re
                json_match = re.search(r'```json\n([\s\S]*?)\n```', prediction)
                if json_match:
                    try:
                        bet_info = json.loads(json_match.group(1))
                        bet_data = {
                            "fixture_id": fix_id,
                            "match": f"{m['teams']['home']['name']} vs {m['teams']['away']['name']}",
                            **bet_info,
                            "status": "pending",
                            "timestamp": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
                            "id": str(len(history) + 1)
                        }
                        history.append(bet_data)
                        with open(BETS_HISTORY_FILE, "w") as f:
                            json.dump(history, f, indent=4)
                        print(f"🤖 BOT: AUTO-BET PLACED for {bet_data['match']}")
                    except Exception as e:
                        print(f"Error parsing Gemini JSON: {e}")
                
                # Small delay to respect rate limit and not burst too much
                time.sleep(2)
                    
    except Exception as e:
        print(f"🤖 BOT ERROR: {e}")

def update_loop():
    fetch_initial_data() 
    while True:
        try:
            print("--- STARTING UPDATE CYCLE ---")
            fetch_live_data()
            print("Checking bet results...")
            check_bets()
            print("Running Auto-Scanner...")
            auto_scanner_logic()
            print("--- UPDATE CYCLE COMPLETE ---")
        except Exception as e:
            print(f"Error in update loop: {e}")
        time.sleep(900) # Every 15 minutes

# Start background thread for updates
Thread(target=update_loop, daemon=True).start()

@app.get("/api/live")
async def get_live():
    if os.path.exists(LIVE_DATA_FILE):
        with open(LIVE_DATA_FILE, "r") as f:
            return json.load(f)
    return {"response": []}

@app.get("/api/teams")
async def get_teams():
    if os.path.exists(TEAMS_FILE):
        with open(TEAMS_FILE, "r") as f:
            return json.load(f)
    return {"response": []}

@app.get("/api/squads")
async def get_squads():
    if os.path.exists(SQUADS_FILE):
        with open(SQUADS_FILE, "r") as f:
            return json.load(f)
    return {}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
