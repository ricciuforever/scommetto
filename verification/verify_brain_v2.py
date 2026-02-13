import asyncio
from playwright.async_api import async_playwright

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        page = await browser.new_page()
        await page.goto("http://localhost:8000/gianik/brain")
        await page.wait_for_timeout(2000)
        await page.screenshot(path="/home/jules/verification/brain_dashboard_v2.png", full_page=True)
        await browser.close()

asyncio.run(run())
