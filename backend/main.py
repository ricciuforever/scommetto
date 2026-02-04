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

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
LIVE_DATA_FILE = os.path.join(BASE_DIR, "live_matches.json")
TEAMS_FILE = os.path.join(BASE_DIR, "serie_a_teams.json")
SQUADS_FILE = os.path.join(BASE_DIR, "serie_a_squads.json")

def fetch_live_data():
    print("Updating live data...")
    try:
        url = f"{BASE_URL}/fixtures?live=all"
        response = requests.get(url, headers=HEADERS)
        data = response.json()
        with open(LIVE_DATA_FILE, "w") as f:
            json.dump(data, f)
        print("Live data updated.")
    except Exception as e:
        print(f"Error updating live data: {e}")

from get_match_intelligence import get_fixture_details

@app.get("/api/intelligence/{fixture_id}")
async def get_intelligence(fixture_id: int):
    # Warning: Consumes ~6 API requests
    data = get_fixture_details(fixture_id)
    return data

def fetch_initial_data():
    if not os.path.exists(TEAMS_FILE):
        print("Seeding initial teams data...")
        try:
            url = f"{BASE_URL}/teams?league=135&season=2024"
            response = requests.get(url, headers=HEADERS)
            data = response.json()
            with open(TEAMS_FILE, "w") as f:
                json.dump(data, f)
            print("Initial teams data seeded.")
        except Exception as e:
            print(f"Error seeding teams: {e}")

def update_loop():
    fetch_initial_data() # Seed once at startup
    while True:
        fetch_live_data()
        time.sleep(900) # Every 15 minutes (96 requests/day)

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
