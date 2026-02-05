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

    pending_bets = [b for b in history if b.get("status") == "pending"]
    if not pending_bets:
        return

    # API-Football supports up to 20 IDs per call. Let's chunk them.
    ids_to_check = [str(b["fixture_id"]) for b in pending_bets if b.get("fixture_id")]
    updated = False

    for i in range(0, len(ids_to_check), 20):
        chunk = ids_to_check[i:i + 20]
        try:
            ids_param = ",".join(chunk)
            msg = f"Checking batch of {len(chunk)} bets..."
            print(msg)
            with open("agent_log.txt", "a") as f:
                f.write(f"{time.ctime()}: {msg}\n")

            res = requests.get(f"{BASE_URL}/fixtures", headers=HEADERS, params={"ids": ids_param})
            data = res.json()
            
            fixtures_data = {f["fixture"]["id"]: f for f in data.get("response", [])}

            for bet in history:
                if bet.get("status") == "pending":
                    raw_id = bet.get("fixture_id")
                    if raw_id is None: continue
                    f_id = int(raw_id)
                    
                    if f_id not in fixtures_data: continue
                    
                    fixture = fixtures_data[f_id]
                    f_status = fixture["fixture"]["status"]["short"]
                    goals = fixture["goals"]
                    score = fixture.get("score", {})
                    match_name = bet.get("match", "Unknown")
                    advice = bet.get("advice", "").lower()
                    market = bet.get("market", "").lower()
                    
                    # 1. Full Time Settlement
                    if f_status in ["FT", "AET", "PEN"]:
                        h, a = goals["home"], goals["away"]
                        is_win = False
                        if any(x in advice for x in ["vittoria", "1", "home", "casa"]) and h > a: is_win = True
                        elif any(x in advice for x in ["2", "away", "ospite", "trasferta"]) and a > h: is_win = True
                        elif any(x in advice for x in ["x", "draw", "pareggio", "n"]) and h == a: is_win = True
                        
                        bet["status"] = "win" if is_win else "lost"
                        bet["result"] = f"{h}-{a}"
                        updated = True
                        with open("agent_log.txt", "a") as f:
                            f.write(f"{time.ctime()}: ✅ SETTLED FT: {match_name} ({h}-{a}) -> {bet['status'].upper()}\n")
                    
                    # 2. Half Time Settlement
                    elif any(x in market for x in ["1st half", "primo tempo", "first half", "1°", "1t"]):
                        if f_status in ["HT", "2H", "FT", "AET", "PEN"]:
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
                                with open("agent_log.txt", "a") as f:
                                    f.write(f"{time.ctime()}: 🕒 SETTLED HT: {match_name} ({h}-{a}) -> {bet['status'].upper()}\n")
                    else:
                        # Log why we are waiting
                        pass
            time.sleep(1) 
        except Exception as e:
            with open("agent_log.txt", "a") as f:
                f.write(f"{time.ctime()}: ❌ Error: {e}\n")

    if updated:
        with open(BETS_HISTORY_FILE, "w") as f:
            json.dump(history, f, indent=4)
        print("Bets settled.")

if __name__ == "__main__":
    check_bets()
