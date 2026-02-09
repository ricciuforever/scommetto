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
                        {"id": 3, "name": "Betfair"}
                    ]
                })
            elif "/api/odds/live" in url:
                if "bookmaker=3" in url:
                    # Mocking a response where fixture ID is a string to test robust matching
                    await route.fulfill(json={
                        "response": [
                            {
                                "fixture": {"id": "100"},
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
                else:
                    await route.fulfill(json={"response": []})
            else:
                await route.continue_()

        await page.route("**/*", handle_route)

        await page.goto("http://localhost:8000/")
        await page.wait_for_selector("text=Milan")
        print("Page loaded.")

        # Select Betfair
        await page.select_option("select:near(:text('Bookmaker'))", label="Betfair")
        await page.wait_for_load_state("networkidle")
        await asyncio.sleep(1)

        # Verify Milan is still visible (robust ID matching worked)
        milan_visible = await page.locator("text=Milan").is_visible()
        print(f"Milan visible after filter: {milan_visible}")

        # Verify odds are displayed
        odds_visible = await page.locator("text=2.10").is_visible()
        print(f"Odds (2.10) visible: {odds_visible}")

        await page.screenshot(path="verification/robust_matching.png")

        # Test Error Reporting
        async def handle_error_route(route):
            url = route.request.url
            if "/api/odds/live" in url:
                await route.fulfill(json={
                    "error": "Rate limit exceeded",
                    "response": []
                })
            elif "/api/intelligence/live" in url:
                # Keep this mock!
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
                 await route.fulfill(json={"response": [{"id": 3, "name": "Betfair"}]})
            else:
                await route.continue_()

        await page.unroute("**/*")
        await page.route("**/*", handle_error_route)

        await page.goto("http://localhost:8000/?bookmaker=3")
        await page.wait_for_selector("text=Rate limit exceeded")
        print("Error message 'Rate limit exceeded' is visible.")

        await page.screenshot(path="verification/error_reporting.png")

        await browser.close()

if __name__ == "__main__":
    if not os.path.exists("verification"):
        os.makedirs("verification")
    asyncio.run(run())
