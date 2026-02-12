from playwright.sync_api import sync_playwright, expect
import time

def verify_gianik_live():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        # Use a larger viewport to see the sidebars
        page = browser.new_page(viewport={'width': 1280, 'height': 800})

        print("Navigating to GiaNik Live...")
        page.goto("http://localhost:8000/gianik-live")

        # Wait for HTMX to load content
        print("Waiting for skipped matches to load...")
        # The skipped matches are injected into #left-sidebar-extra-content
        page.wait_for_selector("#left-sidebar-extra-content div", timeout=10000)

        # Wait a bit more for icons and styles
        time.sleep(2)

        print("Taking screenshot...")
        page.screenshot(path="/home/jules/verification/gianik_live_verification.png", full_page=True)

        browser.close()

if __name__ == "__main__":
    verify_gianik_live()
