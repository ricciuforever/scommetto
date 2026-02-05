import os
import json
import requests
import time
import re
from datetime import datetime, timedelta
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
    except Exception as e:
        log_message(f"Error loading history: {e}")
        return

    pending_bets = [b for b in history if b.get("status") == "pending"]
    if not pending_bets:
        return

    # 1. CLEANUP DUPLICATES & STALE BETS
    updated = False
    new_history = []
    seen_fixtures = {} # fixture_id -> best_pending_bet

    now_utc = datetime.utcnow()

    for bet in history:
        if bet.get("status") == "pending":
            f_id = bet.get("fixture_id")
            # Force settle if older than 5 hours (stale)
            try:
                bet_time = datetime.strptime(bet["timestamp"], "%Y-%m-%dT%H:%M:%SZ")
                if (now_utc - bet_time) > timedelta(hours=5):
                    bet["status"] = "stale"
                    bet["result"] = "Timed Out"
                    updated = True
                    log_message(f"⌛ STALE: Match {bet.get('match')} pending too long, closing.")
            except:
                pass

            # Keep only one pending bet per fixture (the most recent)
            if bet.get("status") == "pending":
                if f_id in seen_fixtures:
                    seen_fixtures[f_id]["status"] = "duplicate"
                    updated = True
                seen_fixtures[f_id] = bet

        new_history.append(bet)

    # 2. FETCH STATUS FOR PENDING
    ids_to_check = list(set([str(b["fixture_id"]) for b in pending_bets if b.get("fixture_id") and b.get("status") == "pending"]))

    if ids_to_check:
        for i in range(0, len(ids_to_check), 20):
            chunk = ids_to_check[i:i + 20]
            try:
                ids_param = ",".join(chunk)
                log_message(f"Checking batch of {len(chunk)} bets...")
                res = requests.get(f"{BASE_URL}/fixtures", headers=HEADERS, params={"ids": ids_param})
                if usage_callback: usage_callback(res)

                data = res.json()
                fixtures_data = {f["fixture"]["id"]: f for f in data.get("response", [])}

                for bet in new_history:
                    if bet.get("status") == "pending":
                        f_id = int(bet.get("fixture_id", 0))
                        if f_id not in fixtures_data: continue

                        fixture = fixtures_data[f_id]
                        status_info = fixture["fixture"]["status"]
                        f_status = status_info["short"]
                        elapsed = status_info["elapsed"] or 0
                        goals = fixture["goals"]
                        score = fixture.get("score", {})
                        match_name = bet.get("match", "Unknown")
                        advice = str(bet.get("advice", "")).lower()
                        market = str(bet.get("market", "")).lower()

                        home_name = fixture["teams"]["home"]["name"].lower()
                        away_name = fixture["teams"]["away"]["name"].lower()

                        # AUTO-FT: If match is in 2nd half and elapsed is very high, treat as finished
                        if f_status in ["2H", "90"] and elapsed > 105:
                            f_status = "FT"

                        # VOIDED
                        if f_status in ["PST", "CANC", "ABD", "WO"]:
                            bet["status"] = "void"
                            bet["result"] = f_status
                            updated = True
                            continue

                        # Settlement Logic
                        is_ht_market = any(x in market for x in ["1st half", "primo tempo", "first half", "1°", "1t"])
                        settle_now = False
                        h, a = None, None
                        res_prefix = ""

                        if is_ht_market:
                            if f_status in ["HT", "2H", "FT", "AET", "PEN"]:
                                ht_score = score.get("halftime", {})
                                h = ht_score.get("home") if ht_score.get("home") is not None else goals["home"]
                                a = ht_score.get("away") if ht_score.get("away") is not None else goals["away"]
                                settle_now = (h is not None and a is not None)
                                res_prefix = "(HT) "
                        else:
                            if f_status in ["FT", "AET", "PEN"]:
                                h, a = goals["home"], goals["away"]
                                settle_now = (h is not None and a is not None)

                        if settle_now:
                            is_win = False
                            # Home
                            if re.search(r'\b(1|home|vittoria casa|vince casa|casa)\b', advice) or (home_name in advice and "vince" in advice):
                                if h > a: is_win = True
                            # Away
                            elif re.search(r'\b(2|away|vittoria ospite|vince trasferta|ospite|trasferta)\b', advice) or (away_name in advice and "vince" in advice):
                                if a > h: is_win = True
                            # Draw (X) - Differentiate from Over 2.5/3.5/etc.
                            elif re.search(r'\b(x|draw|pareggio|segno x)\b', advice):
                                if h == a: is_win = True
                            # Over
                            elif "over" in advice:
                                threshold = 2.5
                                if "0.5" in advice: threshold = 0.5
                                elif "1.5" in advice: threshold = 1.5
                                elif "3.5" in advice: threshold = 3.5
                                if (h + a) > threshold: is_win = True
                            # Under
                            elif "under" in advice:
                                threshold = 2.5
                                if "0.5" in advice: threshold = 0.5
                                elif "1.5" in advice: threshold = 1.5
                                elif "3.5" in advice: threshold = 3.5
                                if (h + a) < threshold: is_win = True
                            # Default team win
                            elif home_name in advice and h > a: is_win = True
                            elif away_name in advice and a > h: is_win = True

                            bet["status"] = "win" if is_win else "lost"
                            bet["result"] = f"{res_prefix}{h}-{a}"
                            updated = True
                            log_message(f"💰 DECIDED: {match_name} -> {bet['status'].upper()} ({bet['result']})")

                time.sleep(1)
            except Exception as e:
                log_message(f"❌ Batch Error: {e}")

    if updated:
        try:
            with open(BETS_HISTORY_FILE, "w") as f:
                json.dump(new_history, f, indent=4)
        except Exception as e:
            log_message(f"❌ Save Error: {e}")

if __name__ == "__main__":
    check_bets()
