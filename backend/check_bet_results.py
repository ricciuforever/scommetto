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
LOG_FILE = os.path.join(BASE_DIR, "agent_log.txt")

def log_message(msg):
    timestamp = time.ctime()
    full_msg = f"{timestamp}: {msg}"
    print(full_msg)
    try:
        with open(LOG_FILE, "a") as f:
            f.write(full_msg + "\n")
    except:
        pass

def check_bets(usage_callback=None):
    if not os.path.exists(BETS_HISTORY_FILE):
        return

    try:
        with open(BETS_HISTORY_FILE, "r") as f:
            history = json.load(f)
    except:
        return

    pending_bets = [b for b in history if b.get("status") == "pending"]
    if not pending_bets:
        return

    ids_to_check = [str(b["fixture_id"]) for b in pending_bets if b.get("fixture_id")]
    updated = False

    # Process in batches of 20
    for i in range(0, len(ids_to_check), 20):
        chunk = ids_to_check[i:i + 20]
        try:
            ids_param = ",".join(chunk)
            log_message(f"Checking batch of {len(chunk)} bets...")

            res = requests.get(f"{BASE_URL}/fixtures", headers=HEADERS, params={"ids": ids_param})
            if usage_callback:
                usage_callback(res)
            
            data = res.json()
            fixtures_data = {f["fixture"]["id"]: f for f in data.get("response", [])}

            for bet in history:
                if bet.get("status") == "pending":
                    f_id = int(bet.get("fixture_id", 0))
                    if f_id not in fixtures_data: continue
                    
                    fixture = fixtures_data[f_id]
                    f_status = fixture["fixture"]["status"]["short"]
                    goals = fixture["goals"]
                    score = fixture.get("score", {})
                    match_name = bet.get("match", "Unknown")
                    advice = str(bet.get("advice", "")).lower()
                    market = str(bet.get("market", "")).lower()
                    
                    home_name = fixture["teams"]["home"]["name"].lower()
                    away_name = fixture["teams"]["away"]["name"].lower()

                    # Handle Void/Cancelled matches
                    if f_status in ["PST", "CANC", "ABD", "WO"]:
                        bet["status"] = "void"
                        bet["result"] = f_status
                        updated = True
                        log_message(f"🚫 VOIDED {match_name}: Match {f_status}")
                        continue
                    
                    # Determine if it's a Half Time market
                    is_ht_market = any(x in market for x in ["1st half", "primo tempo", "first half", "1°", "1t"])

                    target_h, target_a = None, None
                    settle_now = False
                    res_prefix = ""

                    if is_ht_market:
                        if f_status in ["HT", "2H", "FT", "AET", "PEN"]:
                            ht_score = score.get("halftime", {})
                            target_h = ht_score.get("home") if ht_score.get("home") is not None else goals["home"]
                            target_a = ht_score.get("away") if ht_score.get("away") is not None else goals["away"]
                            settle_now = (target_h is not None and target_a is not None)
                            res_prefix = "(HT) "
                    else:
                        if f_status in ["FT", "AET", "PEN"]:
                            target_h, target_a = goals["home"], goals["away"]
                            settle_now = (target_h is not None and target_a is not None)
                            res_prefix = ""

                    if settle_now:
                        h, a = target_h, target_a
                        is_win = False

                        # Logic: Home Win
                        if (any(x in advice for x in ["vittoria casa", "vince casa", " 1 ", "1-0", "2-0", "2-1"]) or (home_name in advice and "vince" in advice)) and h > a:
                            is_win = True
                        # Logic: Away Win
                        elif (any(x in advice for x in ["vittoria ospite", "vince trasferta", " 2 ", "0-1", "0-2", "1-2"]) or (away_name in advice and "vince" in advice)) and a > h:
                            is_win = True
                        # Logic: Draw
                        elif (any(x in advice for x in ["pareggio", " draw ", " segno x", " x "])) and h == a:
                            is_win = True
                        # Logic: Over/Under
                        elif "over" in advice and (h + a) > 2.5: is_win = True
                        elif "under" in advice and (h + a) < 2.5: is_win = True
                        # Fallback simple team name
                        elif home_name in advice and h > a: is_win = True
                        elif away_name in advice and a > h: is_win = True
                        
                        bet["status"] = "win" if is_win else "lost"
                        bet["result"] = f"{res_prefix}{h}-{a}"
                        updated = True
                        log_message(f"💰 DECIDED {match_name}: {bet['result']} ({bet['status'].upper()})")

            time.sleep(1) 
        except Exception as e:
            log_message(f"❌ Settlement Error: {e}")

    if updated:
        try:
            with open(BETS_HISTORY_FILE, "w") as f:
                json.dump(history, f, indent=4)
        except Exception as e:
            log_message(f"❌ Error saving history: {e}")

if __name__ == "__main__":
    check_bets()
