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

def check_bets(usage_callback=None, lock=None):
    if not os.path.exists(BETS_HISTORY_FILE):
        return

    history = []
    try:
        if lock:
            with lock:
                with open(BETS_HISTORY_FILE, "r") as f:
                    history = json.load(f)
        else:
            with open(BETS_HISTORY_FILE, "r") as f:
                history = json.load(f)
    except Exception as e:
        log_message(f"Error loading history: {e}")
        return

    # We want to settle everything that isn't already decided (win/lost/void)
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
            log_message(f"Settling backlog: Checking batch of {len(chunk)} matches (IDs: {ids_param})")
            res = requests.get(f"{BASE_URL}/fixtures", headers=HEADERS, params={"ids": ids_param}, timeout=15)
            if usage_callback: usage_callback(res)

            data = res.json()
            response_list = data.get("response", [])
            for f in response_list:
                fixtures_data[int(f["fixture"]["id"])] = f

            # Diagnostic: check if some IDs were not returned by the API
            for requested_id in chunk:
                if int(requested_id) not in fixtures_data:
                    log_message(f"❓ API Warning: Fixture {requested_id} not returned in response.")

            time.sleep(1)
        except Exception as e:
            log_message(f"❌ Batch API Error: {e}")

    for bet in history:
        status = bet.get("status")
        if status not in ["pending", "duplicate", "stale"]:
            continue

        f_id = int(bet.get("fixture_id", 0))
        match_name = bet.get("match", "Unknown")

        # Determine how long ago the bet was placed
        try:
            bet_time = datetime.strptime(bet["timestamp"], "%Y-%m-%dT%H:%M:%SZ")
            hours_since_bet = (now_utc - bet_time).total_seconds() / 3600
        except:
            hours_since_bet = 0

        # If we have data for this fixture
        if f_id in fixtures_data:
            fixture = fixtures_data[f_id]
            status_info = fixture["fixture"]["status"]
            f_status = status_info["short"]
            elapsed = status_info["elapsed"] or 0
            goals = fixture["goals"]
            score = fixture.get("score", {})
            advice = str(bet.get("advice", "")).lower()
            market = str(bet.get("market", "")).lower()

            home_name = fixture["teams"]["home"]["name"].lower()
            away_name = fixture["teams"]["away"]["name"].lower()

            # Force FT if match is clearly over but API status is lagging
            # If match started > 3 hours ago, it's definitely over.
            if f_status not in ["FT", "AET", "PEN"] and (elapsed > 105 or hours_since_bet > 3):
                log_message(f"⏰ Force-settling {match_name} as FT (Status: {f_status}, Elapsed: {elapsed}, Hours since bet: {hours_since_bet:.1f})")
                f_status = "FT"

            # 1. VOIDED
            if f_status in ["PST", "CANC", "ABD", "WO"]:
                bet["status"] = "void"
                bet["result"] = f_status
                updated = True
                log_message(f"🚫 {match_name} VOIDED (Status: {f_status})")
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
                found_pattern = False
                match_reason = ""

                # 1. Home Win - Stricter pattern
                if re.search(r'\b(vittoria casa|vince casa|^1$|casa vince|home win)\b', advice) or (home_name in advice and any(x in advice for x in ["vince", "vittoria"])):
                    found_pattern = True
                    match_reason = "Home Win Pattern"
                    if h > a: is_win = True
                # 2. Away Win - Stricter pattern
                elif re.search(r'\b(vittoria ospite|vince trasferta|^2$|trasferta vince|away win)\b', advice) or (away_name in advice and any(x in advice for x in ["vince", "vittoria"])):
                    found_pattern = True
                    match_reason = "Away Win Pattern"
                    if a > h: is_win = True
                # 3. Draw - Stricter pattern (must not have numbers near X)
                elif re.search(r'\b(x|draw|pareggio)\b', advice) and not re.search(r'\d', advice.replace('x', '')):
                    found_pattern = True
                    match_reason = "Draw Pattern"
                    if h == a: is_win = True
                # 4. Over/Under - Standard
                elif "over" in advice:
                    found_pattern = True
                    threshold = float(re.search(r'(\d+\.\d+)', advice).group(1)) if re.search(r'(\d+\.\d+)', advice) else 2.5
                    match_reason = f"Over {threshold} Pattern"
                    if (h + a) > threshold: is_win = True
                elif "under" in advice:
                    found_pattern = True
                    threshold = float(re.search(r'(\d+\.\d+)', advice).group(1)) if re.search(r'(\d+\.\d+)', advice) else 2.5
                    match_reason = f"Under {threshold} Pattern"
                    if (h + a) < threshold: is_win = True

                if found_pattern:
                    bet["status"] = "win" if is_win else "lost"
                    bet["result"] = f"{res_prefix}{h}-{a}"
                    updated = True
                    log_message(f"💰 SETTLED: {match_name} -> {bet['status'].upper()} ({bet['result']}) | Reason: {match_reason}")
                else:
                    log_message(f"⚠️ Manual check needed for {match_name}: Advice '{advice}' is ambiguous.")
                    if f_status == "FT":
                        bet["status"] = "lost" # Default to lost if we can't confirm a win
                        bet["result"] = f"{res_prefix}{h}-{a} (Ambig)"
                        updated = True
            else:
                # If match is very old but not FT yet in API, check if it's actually over
                if hours_since_bet > 3:
                     log_message(f"⏳ {match_name} stale/pending for {hours_since_bet:.1f}h. Forcing closure.")
                     bet["status"] = "stale"
                     updated = True

        else:
            # Fixture not found in API response - Lower threshold to 3h
            if hours_since_bet > 3:
                log_message(f"💀 Fixture {f_id} ({match_name}) missing from API for {hours_since_bet:.1f}h. Marking as stale.")
                bet["status"] = "stale"
                updated = True

        # 3. SECONDARY CLEANUP: If still pending/stale but match is very old (>12h), just close it
        if bet["status"] in ["pending", "duplicate", "stale"]:
            if hours_since_bet > 12:
                log_message(f"🧹 Expiring very old bet: {match_name} (Status: {bet['status']})")
                bet["status"] = "void"
                bet["result"] = "Expired"
                updated = True

    if updated:
        try:
            if lock:
                with lock:
                    with open(BETS_HISTORY_FILE, "w") as f:
                        json.dump(history, f, indent=4)
            else:
                with open(BETS_HISTORY_FILE, "w") as f:
                    json.dump(history, f, indent=4)
        except Exception as e:
            log_message(f"❌ Save Error: {e}")

if __name__ == "__main__":
    check_bets()
