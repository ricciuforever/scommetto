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
            try:
                # Ensure fixture_id is valid
                raw_id = bet.get("fixture_id")
                if raw_id is None:
                    print(f"DEBUG: Skipping {bet.get('match')} - Missing Fixture ID")
                    continue
                fixture_id = int(raw_id)
                match_name = bet.get("match", "Unknown")
                print(f"DEBUG: Processing {match_name} (ID: {fixture_id})")
                
                res = requests.get(f"{BASE_URL}/fixtures", headers=HEADERS, params={"id": fixture_id})
                data = res.json()
                
                if data.get("response"):
                    fixture = data["response"][0]
                    f_status = fixture["fixture"]["status"]["short"]
                    goals = fixture["goals"] # Current goals
                    score = fixture.get("score", {})
                    
                    market = bet.get("market", "").lower()
                    advice = bet.get("advice", "").lower()
                    
                    print(f"DEBUG: {match_name} status is {f_status}. Goals: {goals}")

                    # 1. 1X2 (Full Time)
                    if f_status == "FT":
                        h, a = goals["home"], goals["away"]
                        is_win = False
                        if any(x in advice for x in ["vittoria", "1", "home", "casa"]) and h > a: is_win = True
                        elif any(x in advice for x in ["2", "away", "ospite", "trasferta"]) and a > h: is_win = True
                        elif any(x in advice for x in ["x", "draw", "pareggio", "n"]) and h == a: is_win = True
                        
                        bet["status"] = "win" if is_win else "lost"
                        bet["result"] = f"{h}-{a}"
                        updated = True
                        print(f"DEBUG: Settle FT for {match_name} -> {bet['status']}")

                    # 2. 1X2 (1st Half) / First Half Winner
                    else:
                        is_1st_half = any(x in market for x in ["1st half", "primo tempo", "first half", "1°", "1t"])
                        if is_1st_half and f_status in ["HT", "2H", "FT"]:
                            # Use halftime score if available, otherwise current goals if it's HT
                            ht_score = score.get("halftime", {})
                            h = ht_score.get("home") if ht_score.get("home") is not None else goals["home"]
                            a = ht_score.get("away") if ht_score.get("away") is not None else goals["away"]
                            
                            if h is not None and a is not None:
                                is_win = False
                                if any(x in advice for x in ["vittoria", "1", "home", "casa"]) and h > a: is_win = True
                                elif any(x in advice for x in ["2", "away", "ospite", "trasferta"]) and a > h: is_win = True
                                elif any(x in advice for x in ["x", "draw", "pareggio", "n"]) and h == a: is_win = True
                                
                                bet["status"] = "win" if is_win else "lost"
                                bet["result"] = f"(HT) {h}-{a}"
                                updated = True
                                print(f"DEBUG: Settle HT for {match_name} -> {bet['status']}")

                time.sleep(1) 
            except Exception as e:
                print(f"DEBUG: ERROR on fixture {bet.get('fixture_id')}: {e}")

    if updated:
        with open(BETS_HISTORY_FILE, "w") as f:
            json.dump(history, f, indent=4)
        print("Bets history updated with new results.")

if __name__ == "__main__":
    check_bets()
