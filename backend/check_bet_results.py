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

    # We want to settle everything that isn't already decided (win/lost/void)
    # This includes 'pending', but also 'duplicate' or 'stale' from previous runs
    targets = [b for b in history if b.get("status") in ["pending", "duplicate", "stale"]]

    if not targets:
        return

    updated = False
    now_utc = datetime.utcnow()

    # Group unique IDs to minimize API calls
    ids_to_check = list(set([str(b["fixture_id"]) for b in targets if b.get("fixture_id")]))

    fixtures_data = {}
    for i in range(0, len(ids_to_check), 20):
        chunk = ids_to_check[i:i + 20]
        try:
            ids_param = ",".join(chunk)
            log_message(f"Settling backlog: Checking batch of {len(chunk)} matches...")
            res = requests.get(f"{BASE_URL}/fixtures", headers=HEADERS, params={"ids": ids_param})
            if usage_callback: usage_callback(res)

            data = res.json()
            for f in data.get("response", []):
                fixtures_data[f["fixture"]["id"]] = f
            time.sleep(1)
        except Exception as e:
            log_message(f"❌ Batch API Error: {e}")

    for bet in history:
        status = bet.get("status")
        if status not in ["pending", "duplicate", "stale"]:
            continue

        f_id = int(bet.get("fixture_id", 0))

        # If we have data for this fixture
        if f_id in fixtures_data:
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

            # Force FT if match is clearly over but API status is lagging
            if f_status in ["2H", "90"] and elapsed > 105:
                f_status = "FT"

            # 1. VOIDED
            if f_status in ["PST", "CANC", "ABD", "WO"]:
                bet["status"] = "void"
                bet["result"] = f_status
                updated = True
                continue

            # 2. SETTLEMENT LOGIC
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
                # Home Win
                if re.search(r'\b(1|home|vittoria casa|vince casa|casa)\b', advice) or (home_name in advice and any(x in advice for x in ["vince", "vittoria", "segna"])):
                    if h > a: is_win = True
                # Away Win
                elif re.search(r'\b(2|away|vittoria ospite|vince trasferta|ospite|trasferta)\b', advice) or (away_name in advice and any(x in advice for x in ["vince", "vittoria", "segna"])):
                    if a > h: is_win = True
                # Draw (X)
                elif re.search(r'\b(x|draw|pareggio|segno x)\b', advice) and not re.search(r'\d', advice.replace('x', '')):
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
                log_message(f"💰 BACKLOG SETTLED: {match_name} -> {bet['status'].upper()} ({bet['result']})")

        # 3. SECONDARY CLEANUP: If still pending/stale but match is very old (>12h), just close it
        if bet["status"] in ["pending", "duplicate", "stale"]:
            try:
                bet_time = datetime.strptime(bet["timestamp"], "%Y-%m-%dT%H:%M:%SZ")
                if (now_utc - bet_time) > timedelta(hours=12):
                    bet["status"] = "void"
                    bet["result"] = "Expired"
                    updated = True
            except:
                pass

    if updated:
        try:
            with open(BETS_HISTORY_FILE, "w") as f:
                json.dump(history, f, indent=4)
        except Exception as e:
            log_message(f"❌ Save Error: {e}")

if __name__ == "__main__":
    check_bets()
