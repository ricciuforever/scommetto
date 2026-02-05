import os
import json
import requests
import time
from dotenv import load_dotenv

load_dotenv()

API_KEY = os.getenv("FOOTBALL_API_KEY")
BASE_URL = "https://v3.football.api-sports.io"
HEADERS = {
    'x-rapidapi-host': "v3.football.api-sports.io",
    'x-rapidapi-key': API_KEY
}

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
BETS_HISTORY_FILE = os.path.join(BASE_DIR, "bets_history.json")

def check_bets():
    if not os.path.exists(BETS_HISTORY_FILE):
        return

    with open(BETS_HISTORY_FILE, "r") as f:
        history = json.load(f)

    updated = False
    for bet in history:
        if bet.get("status") == "pending":
            fixture_id = bet.get("fixture_id")
            match_name = bet.get("match", "Unknown")
            print(f"DEBUG: Checking {match_name} (ID: {fixture_id})...")
            
            try:
                res = requests.get(f"{BASE_URL}/fixtures", headers=HEADERS, params={"id": fixture_id})
                data = res.json()
                
                if data.get("response"):
                    fixture = data["response"][0]
                    f_status = fixture["fixture"]["status"]["short"]
                    goals = fixture["goals"]
                    score = fixture.get("score", {})
                    
                    market = bet.get("market", "").lower()
                    advice = bet.get("advice", "").lower()
                    
                    print(f"DEBUG: Status={f_status}, Score={score.get('fulltime')}, HT={score.get('halftime')}")

                    # 1. 1X2 (Full Time)
                    if f_status == "FT":
                        h, a = goals["home"], goals["away"]
                        # Logic for WIN/LOSS
                        is_win = False
                        if ("vittoria" in advice or "1" in advice or "home" in advice) and h > a: is_win = True
                        elif ("2" in advice or "away" in advice) and a > h: is_win = True
                        elif ("x" in advice or "draw" in advice or "pareggio" in advice) and h == a: is_win = True
                        
                        bet["status"] = "win" if is_win else "lost"
                        bet["result"] = f"{h}-{a}"
                        updated = True

                    # 2. 1X2 (1st Half)
                    elif ("1st half" in market or "primo tempo" in market) and f_status in ["HT", "2H", "FT"]:
                        ht_score = score.get("halftime", {})
                        h, a = ht_score.get("home"), ht_score.get("away")
                        
                        if h is not None and a is not None:
                            is_win = False
                            if ("vittoria" in advice or "1" in advice or "home" in advice) and h > a: is_win = True
                            elif ("2" in advice or "away" in advice) and a > h: is_win = True
                            elif ("x" in advice or "draw" in advice or "pareggio" in advice) and h == a: is_win = True
                            
                            bet["status"] = "win" if is_win else "lost"
                            bet["result"] = f"(HT) {h}-{a}"
                            updated = True
                
                time.sleep(1) 
            except Exception as e:
                print(f"DEBUG: Error {fixture_id}: {e}")

    if updated:
        with open(BETS_HISTORY_FILE, "w") as f:
            json.dump(history, f, indent=4)
        print("Bets history updated with new results.")

if __name__ == "__main__":
    check_bets()
