import asyncio
from playwright.async_api import async_playwright
import json

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
                            "fixture": {"id": 1, "status": {"short": "1H", "elapsed": 25}},
                            "league": {"id": 135, "name": "Serie A", "country": "Italy", "logo": "https://media.api-sports.io/football/leagues/135.png"},
                            "teams": {
                                "home": {"id": 1, "name": "Milan", "logo": "https://media.api-sports.io/football/teams/1.png"},
                                "away": {"id": 2, "name": "Napoli", "logo": "https://media.api-sports.io/football/teams/2.png"}
                            },
                            "goals": {"home": 1, "away": 0}
                        },
                        {
                            "fixture": {"id": 2, "status": {"short": "1H", "elapsed": 30}},
                            "league": {"id": 135, "name": "Serie A", "country": "Italy", "logo": "https://media.api-sports.io/football/leagues/135.png"},
                            "teams": {
                                "home": {"id": 3, "name": "Inter", "logo": "https://media.api-sports.io/football/teams/3.png"},
                                "away": {"id": 4, "name": "Juve", "logo": "https://media.api-sports.io/football/teams/4.png"}
                            },
                            "goals": {"home": 0, "away": 0}
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
            elif "/api/odds/live" in url and "bookmaker=3" in url:
                await route.fulfill(json={
                    "response": [
                        {
                            "fixture": {"id": 1},
                            "odds": [
                                {
                                    "id": 1, "name": "Match Winner",
                                    "values": [
                                        {"value": "Home", "odd": "2.10"},
                                        {"value": "Draw", "odd": "3.40"},
                                        {"value": "Away", "odd": "3.80"}
                                    ]
                                }
                            ]
                        }
                    ]
                })
            elif "/api/odds/live" in url and "bookmaker=8" in url:
                await route.fulfill(json={"response": []})
            else:
                await route.continue_()

        await page.route("**/*", handle_route)

        await page.goto("http://localhost:8000/")
        await page.wait_for_selector("text=Milan")
        print("Page loaded with fixtures.")

        # Check initial fixtures
        milan_visible = await page.locator("text=Milan").is_visible()
        inter_visible = await page.locator("text=Inter").is_visible()
        print(f"Initial: Milan visible: {milan_visible}, Inter visible: {inter_visible}")

        # Select Betfair (ID 3)
        async with page.expect_response(lambda response: "/api/odds/live" in response.url):
            await page.select_option("select:near(:text('Bookmaker'))", label="Betfair")

        await page.wait_for_load_state("networkidle")

        # Check filtered fixtures
        # We need to wait a bit for React to re-render
        await asyncio.sleep(1)

        milan_visible = await page.locator("text=Milan").is_visible()
        inter_visible = await page.locator("text=Inter").is_visible()
        print(f"After Betfair: Milan visible: {milan_visible}, Inter visible: {inter_visible}")

        # Milan should have odds displayed
        odds_visible = await page.locator("text=2.10").is_visible()
        print(f"Odds (2.10) visible: {odds_visible}")

        await page.screenshot(path="verification/filtered_betfair.png")

        # Select Bet365 (ID 8)
        async with page.expect_response(lambda response: "/api/odds/live" in response.url):
            await page.select_option("select:near(:text('Bookmaker'))", label="Bet365")

        await page.wait_for_load_state("networkidle")
        await asyncio.sleep(1)

        empty_msg = await page.locator("text=Nessun evento disponibile per il bookmaker selezionato").is_visible()
        print(f"Empty message visible: {empty_msg}")

        await page.screenshot(path="verification/empty_bet365.png")

        await browser.close()

if __name__ == "__main__":
    import os
    if not os.path.exists("verification"):
        os.makedirs("verification")
    asyncio.run(run())
