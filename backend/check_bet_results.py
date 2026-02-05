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
            print(f"Checking results for fixture {fixture_id} ({bet.get('match')})...")
            
            try:
                res = requests.get(f"{BASE_URL}/fixtures", headers=HEADERS, params={"id": fixture_id})
                data = res.json()
                
                if data.get("response"):
                    fixture = data["response"][0]
                    status = fixture["fixture"]["status"]["short"]
                    goals = fixture["goals"]
                    score_half = fixture.get("score", {}).get("halftime", {})
                    
                    # Logica per decidere se la scommessa è conclusa
                    market = bet.get("market", "").lower()
                    advice = bet.get("advice", "").lower()
                    
                    # 1. 1X2 (Full Time)
                    if status == "FT":
                        home_win = goals["home"] > goals["away"]
                        away_win = goals["away"] > goals["home"]
                        draw = goals["home"] == goals["away"]
                        
                        outcome = "lost"
                        if "vittoria" in advice or "1" in advice:
                            if "casa" in advice or "home" in advice or (not "2" in advice and not "x" in advice):
                                outcome = "win" if home_win else "lost"
                        elif "2" in advice or "away" in advice or "ospite" in advice:
                            outcome = "win" if away_win else "lost"
                        elif "x" in advice or "draw" in advice or "pareggio" in advice:
                            outcome = "win" if draw else "lost"
                            
                        bet["status"] = outcome
                        bet["result"] = f"{goals['home']}-{goals['away']}"
                        updated = True

                    # 2. 1X2 (1st Half)
                    elif "1st half" in market or "primo tempo" in market:
                        # Se il primo tempo è finito o la partita è oltre
                        if status in ["HT", "2H", "FT"]:
                            h_goals = score_half.get("home")
                            a_goals = score_half.get("away")
                            
                            if h_goals is not None and a_goals is not None:
                                home_win = h_goals > a_goals
                                away_win = a_goals > h_goals
                                draw = h_goals == a_goals
                                
                                outcome = "lost"
                                if "vittoria" in advice or "1" in advice or "home" in advice:
                                    outcome = "win" if home_win else "lost"
                                elif "2" in advice or "away" in advice:
                                    outcome = "win" if away_win else "lost"
                                elif "x" in advice or "draw" in advice:
                                    outcome = "win" if draw else "lost"
                                    
                                bet["status"] = outcome
                                bet["result"] = f"(HT) {h_goals}-{a_goals}"
                                updated = True
                
                # Rispetta il rate limit (1 call per scommessa è pesante, facciamo piano)
                time.sleep(1) 
                
            except Exception as e:
                print(f"Error checking fixture {fixture_id}: {e}")

    if updated:
        with open(BETS_HISTORY_FILE, "w") as f:
            json.dump(history, f, indent=4)
        print("Bets history updated with new results.")

if __name__ == "__main__":
    check_bets()
