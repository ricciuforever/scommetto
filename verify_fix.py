import asyncio
from playwright.async_api import async_playwright
import json
import os

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        context = await browser.new_context()
        page = await context.new_page()

        # Mock API responses
        async def handle_route(route):
            url = route.request.url
            if "/api/intelligence/live" in url:
                await route.fulfill(json={
                    "response": [
                        {
                            "fixture": {"id": 100, "status": {"short": "1H", "elapsed": 25}},
                            "league": {"id": 135, "name": "Serie A", "country": "Italy", "logo": "https://media.api-sports.io/football/leagues/135.png"},
                            "teams": {
                                "home": {"id": 1, "name": "Milan", "logo": "https://media.api-sports.io/football/teams/1.png"},
                                "away": {"id": 2, "name": "Napoli", "logo": "https://media.api-sports.io/football/teams/2.png"}
                            },
                            "goals": {"home": 1, "away": 0}
                        }
                    ]
                })
            elif "/api/odds/active-bookmakers" in url:
                await route.fulfill(json={
                    "response": [
                        {"id": 3, "name": "Betfair"},
                        {"id": 8, "name": "Bet365"}
                    ]
                })
            elif "/api/odds/live" in url:
                # Mocking a response with MULTIPLE bookmakers
                # If backend filtering works, we only see the one requested.
                # If not, the frontend should still pick the right one.
                bookmaker_id = 3 if "bookmaker=3" in url else 8
                await route.fulfill(json={
                    "response": [
                        {
                            "fixture": {"id": 100},
                            "bookmakers": [
                                {
                                    "id": 3, "name": "Betfair",
                                    "bets": [{"id": 1, "name": "Match Winner", "values": [{"value": "Home", "odd": "2.10"}]}]
                                },
                                {
                                    "id": 8, "name": "Bet365",
                                    "bets": [{"id": 1, "name": "Match Winner", "values": [{"value": "Home", "odd": "2.25"}]}]
                                }
                            ]
                        }
                    ]
                })
            else:
                await route.continue_()

        await page.route("**/*", handle_route)

        await page.goto("http://localhost:8000/")
        await page.wait_for_selector("text=Milan")
        print("Page loaded.")

        # Select Betfair (ID 3)
        await page.select_option("select:near(:text('Bookmaker'))", label="Betfair")
        await page.wait_for_load_state("networkidle")
        await asyncio.sleep(1)

        # Verify Betfair odds (2.10) are visible
        odds_3_visible = await page.locator("text=2.10").is_visible()
        print(f"Betfair odds (2.10) visible: {odds_3_visible}")

        # Select Bet365 (ID 8)
        await page.select_option("select:near(:text('Bookmaker'))", label="Bet365")
        await page.wait_for_load_state("networkidle")
        await asyncio.sleep(1)

        # Verify Bet365 odds (2.25) are visible
        odds_8_visible = await page.locator("text=2.25").is_visible()
        print(f"Bet365 odds (2.25) visible: {odds_8_visible}")

        await page.screenshot(path="verification/multi_bookmaker_filtering.png")

        await browser.close()

if __name__ == "__main__":
    if not os.path.exists("verification"):
        os.makedirs("verification")
    asyncio.run(run())
