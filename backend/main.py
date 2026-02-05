from starlette.concurrency import run_in_threadpool
from fastapi import FastAPI, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
import json
import os
import requests
import time
import threading
from threading import Thread
from datetime import datetime
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

# Global lock for file operations
file_lock = threading.Lock()

# Global tracker for API usage
api_usage_info = {"used": 0, "remaining": 7500}

def update_usage_from_response(response):
    global api_usage_info
    try:
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
        # Added timeout to prevent hanging
        response = requests.get(url, headers=HEADERS, timeout=10)
        update_usage_from_response(response)
        data = response.json()
        with file_lock:
            with open(LIVE_DATA_FILE, "w") as f:
                json.dump(data, f)
        print("Live data updated.")
    except Exception as e:
        print(f"Error updating live data: {e}")

from get_match_intelligence import get_fixture_details
from gemini_analyzer import analyze_match_with_gemini
from check_bet_results import check_bets

@app.get("/api/intelligence/{fixture_id}")
def get_intelligence(fixture_id: int):
    data = get_fixture_details(fixture_id, usage_callback=update_usage_from_response)
    return data

@app.get("/api/analyze/{fixture_id}")
def get_gemini_analysis(fixture_id: int):
    intelligence = get_fixture_details(fixture_id, usage_callback=update_usage_from_response)
    prediction = analyze_match_with_gemini(intelligence)
    
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
            result = internal_place_bet(bet_data)
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
def get_history():
    with file_lock:
        if os.path.exists(BETS_HISTORY_FILE):
            with open(BETS_HISTORY_FILE, "r") as f:
                return json.load(f)
    return []

def internal_place_bet(bet_data: dict):
    """Internal function to place a bet, with file locking and strict duplicate prevention."""
    with file_lock:
        history = []
        if os.path.exists(BETS_HISTORY_FILE):
            with open(BETS_HISTORY_FILE, "r") as f:
                try:
                    history = json.load(f)
                except:
                    history = []

        try:
            f_id = int(bet_data.get("fixture_id", 0))
        except:
            f_id = 0

        advice = bet_data.get("advice", "")

        # Prevent duplicate pending bets for the same fixture.
        # Using .get() ensures compatibility with historical records.
        existing_pending = next((b for b in history if int(b.get("fixture_id", 0)) == f_id and b.get("status") == "pending"), None)
        if existing_pending:
            return {"status": "already_exists", "bet": existing_pending}

        bet_data["id"] = str(len(history) + 1)
        bet_data["status"] = "pending"
        bet_data["fixture_id"] = f_id
        if "timestamp" not in bet_data:
            bet_data["timestamp"] = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())

        history.append(bet_data)
        with open(BETS_HISTORY_FILE, "w") as f:
            json.dump(history, f, indent=4)

        return {"status": "success", "bet": bet_data}

@app.post("/api/place_bet")
def place_bet(bet_data: dict):
    return internal_place_bet(bet_data)

@app.get("/api/usage")
async def get_usage():
    return api_usage_info

def fetch_initial_data():
    if not os.path.exists(TEAMS_FILE):
        print("Seeding initial teams data...")
        try:
            url = f"{BASE_URL}/teams?league=135&season=2024"
            # Added timeout
            response = requests.get(url, headers=HEADERS, timeout=10)
            update_usage_from_response(response)
            data = response.json()
            with file_lock:
                with open(TEAMS_FILE, "w") as f:
                    json.dump(data, f)
            print("Initial teams data seeded.")
        except Exception as e:
            print(f"Error seeding teams: {e}")

def auto_scanner_logic():
    print("--- 🤖 BOT SCANNER: Searching for value bets ---")
    try:
        if not os.path.exists(LIVE_DATA_FILE): return
        with file_lock:
            with open(LIVE_DATA_FILE, "r") as f:
                data = json.load(f)
        
        matches = data.get("response", [])
        
        # Load history to check for existing bets
        history = []
        with file_lock:
            if os.path.exists(BETS_HISTORY_FILE):
                with open(BETS_HISTORY_FILE, "r") as f:
                    try:
                        history = json.load(f)
                    except:
                        history = []

        for m in matches:
            fix_id = int(m["fixture"]["id"])
            if api_usage_info["remaining"] < 15:
                print("--- 🤖 BOT PAUSED: API Quota low ---")
                break

            # Check for existing pending bets for this fixture to avoid duplicates.
            # Using .get() maintains compatibility with older history records.
            existing_bet = next((b for b in history if int(b.get("fixture_id", 0)) == fix_id and b.get("status") == "pending"), None)
            
            should_analyze = False
            if not existing_bet:
                # Check for recent analysis or non-pending bets for this match to avoid repetition.
                recent = next((b for b in reversed(history) if int(b.get("fixture_id", 0)) == fix_id), None)
                if not recent:
                    should_analyze = True
                else:
                    try:
                        last_time = datetime.strptime(recent["timestamp"], "%Y-%m-%dT%H:%M:%SZ")
                        now_time = datetime.utcnow()
                        diff = (now_time - last_time).total_seconds() / 60
                        if diff > 45: # Re-analyze every 45 mins if not pending
                            should_analyze = True
                    except:
                        should_analyze = True

            if should_analyze:
                elapsed = m.get("fixture", {}).get("status", {}).get("elapsed")
                if elapsed and elapsed > 80:
                    continue

                print(f"🤖 BOT: Analyzing {m['teams']['home']['name']} vs {m['teams']['away']['name']}")
                intelligence = get_fixture_details(fix_id, fixture_data=m, usage_callback=update_usage_from_response)
                prediction = analyze_match_with_gemini(intelligence)
                
                import re
                json_match = re.search(r'```json\n([\s\S]*?)\n```', prediction)
                if json_match:
                    try:
                        bet_info = json.loads(json_match.group(1))
                        bet_data = {
                            "fixture_id": fix_id,
                            "match": f"{m['teams']['home']['name']} vs {m['teams']['away']['name']}",
                            **bet_info
                        }
                        res = internal_place_bet(bet_data)
                        if res["status"] == "success":
                            print(f"🤖 BOT: AUTO-BET PLACED for {bet_data['match']}")
                            history.append(res["bet"])
                    except Exception as e:
                        print(f"Error parsing Gemini JSON: {e}")
                
                time.sleep(1)
                    
    except Exception as e:
        print(f"🤖 BOT ERROR: {e}")

def single_update_loop():
    fetch_initial_data()
    cycle_count = 0
    while True:
        try:
            nowStr = time.strftime("%H:%M:%S")
            print(f"[{nowStr}] --- STARTING SYNC CYCLE ---")
            fetch_live_data()
            
            # Settlement run every 3 minutes (cycle 0, 3, 6...)
            if cycle_count % 3 == 0:
                print(f"[{nowStr}] Running Bet Settlement...")
                check_bets(usage_callback=update_usage_from_response)
            
            # Auto-Scanner every 2 minutes
            if cycle_count % 2 == 0:
                auto_scanner_logic()
                
            cycle_count += 1
            print(f"[{nowStr}] --- SYNC CYCLE COMPLETE ---")
        except Exception as e:
            msg = f"LOOP ERROR: {e}"
            print(msg)
            log_path = os.path.join(BASE_DIR, "agent_log.txt")
            with open(log_path, "a") as f:
                f.write(f"{time.ctime()}: {msg}\n")
        
        time.sleep(60)

# Start background thread
Thread(target=single_update_loop, daemon=True).start()

@app.get("/api/health")
async def health_check():
    mtime = os.path.getmtime(LIVE_DATA_FILE) if os.path.exists(LIVE_DATA_FILE) else 0
    return {
        "status": "alive",
        "last_sync_seconds_ago": int(time.time() - mtime),
        "usage": api_usage_info
    }

@app.get("/api/logs")
async def get_logs():
    try:
        log_path = os.path.join(BASE_DIR, "agent_log.txt")
        if os.path.exists(log_path):
            with open(log_path, "r") as f:
                lines = f.readlines()
                return {"logs": lines[-20:]}
        return {"logs": ["Log file empty."]}
    except Exception as e:
        return {"logs": [f"Error reading logs: {e}"]}

@app.get("/api/live")
def get_live():
    with file_lock:
        if os.path.exists(LIVE_DATA_FILE):
            with open(LIVE_DATA_FILE, "r") as f:
                data = json.load(f)
                data["server_time"] = os.path.getmtime(LIVE_DATA_FILE)
                return data
    return {"response": []}

@app.get("/api/teams")
def get_teams():
    with file_lock:
        if os.path.exists(TEAMS_FILE):
            with open(TEAMS_FILE, "r") as f:
                return json.load(f)
    return {"response": []}

@app.get("/api/squads")
def get_squads():
    with file_lock:
        if os.path.exists(SQUADS_FILE):
            with open(SQUADS_FILE, "r") as f:
                return json.load(f)
    return {}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
