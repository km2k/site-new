"""
Scrape the Bulgarian Orthodox Church calendar from bg-patriarshia.bg for 2026.
Extracts date and saint/holiday text for each day of the year.

Requirements:
    pip install playwright
    playwright install chromium

Usage:
    python scrape_calendar.py

Output:
    calendar_2026.json  – raw JSON with all scraped data
    calendar_2026.csv   – CSV with columns: date, day_of_week, text
"""

import asyncio
import json
import csv
import re
from datetime import datetime
from playwright.async_api import async_playwright

BASE_URL = "https://bg-patriarshia.bg/calendar/2026"
YEAR = 2026


async def scrape_month(page, month: int) -> list[dict]:
    """Scrape all day entries from a single month page."""
    url = f"{BASE_URL}-{month}"
    print(f"  Navigating to {url}")
    await page.goto(url, wait_until="networkidle", timeout=30000)

    # Wait for calendar content to render
    await page.wait_for_timeout(2000)

    entries = []

    # Strategy 1: Look for day links/entries in the calendar grid or list
    # The site typically renders days as links like /calendar/2026-1-1, /calendar/2026-1-2, etc.
    # or as list items with date and saint text

    # Try to find calendar day entries - common patterns on this site:
    # - <a> tags linking to individual days
    # - <tr> or <div> elements with day info
    # - A list/table of days with their descriptions

    # First, let's try extracting from the page content directly
    day_elements = await page.query_selector_all(
        "table.calendar-table tr, "
        ".calendar-day, "
        ".calendar-content tr, "
        "ul.calendar-list li, "
        ".day-row, "
        "a[href*='/calendar/2026']"
    )

    if day_elements:
        print(f"  Found {len(day_elements)} elements with primary selectors")
        for el in day_elements:
            text = (await el.inner_text()).strip()
            href = await el.get_attribute("href") if await el.get_attribute("href") else ""
            if text:
                entries.append({"raw_text": text, "href": href, "month": month})
    
    # Strategy 2: If no structured elements found, get all text content and parse it
    if not entries:
        print("  Primary selectors didn't match, trying broad content extraction...")
        
        # Look for the main content area
        content_selectors = [
            "main", ".content", "#content", ".calendar",
            ".page-content", "article", ".container .row"
        ]
        
        content_el = None
        for sel in content_selectors:
            content_el = await page.query_selector(sel)
            if content_el:
                break
        
        if not content_el:
            content_el = await page.query_selector("body")
        
        # Get all links that look like day links
        day_links = await page.query_selector_all("a[href*='/calendar/2026-']")
        if day_links:
            print(f"  Found {len(day_links)} day links")
            for link in day_links:
                href = await link.get_attribute("href") or ""
                text = (await link.inner_text()).strip()
                # Only include links to specific days (not month links)
                # Day links: /calendar/2026-1-15 or /calendar/2026-01-15
                if re.search(r'/calendar/2026-\d{1,2}-\d{1,2}', href):
                    entries.append({"raw_text": text, "href": href, "month": month})

    # Strategy 3: If still nothing, try to get all visible text rows
    if not entries:
        print("  Trying full page text extraction...")
        full_text = await page.inner_text("body")
        # Look for lines that match "DD month_name YYYY, day_of_week · text" pattern
        # e.g. "1 януари 2026, четвъртък · Обрезание Господне..."
        lines = full_text.split("\n")
        for line in lines:
            line = line.strip()
            if re.match(r'^\d{1,2}\s+\w+\s+2026', line):
                entries.append({"raw_text": line, "href": "", "month": month})

    print(f"  Extracted {len(entries)} raw entries for month {month}")
    return entries


async def scrape_individual_day(page, month: int, day: int) -> dict | None:
    """Scrape a single day page as fallback."""
    url = f"{BASE_URL}-{month}-{day}"
    try:
        await page.goto(url, wait_until="networkidle", timeout=15000)
        await page.wait_for_timeout(1000)

        # Try to find the day's content
        content_selectors = [
            ".calendar-day-content", ".day-info", ".calendar-text",
            "main", ".content", "article", ".page-content"
        ]

        text = ""
        for sel in content_selectors:
            el = await page.query_selector(sel)
            if el:
                text = (await el.inner_text()).strip()
                if text and len(text) > 10:
                    break

        if not text:
            text = await page.inner_text("body")

        # Clean up navigation / header / footer noise
        # Keep only calendar-relevant content
        return {"date": f"{YEAR}-{month:02d}-{day:02d}", "raw_text": text}

    except Exception as e:
        print(f"    Error fetching {url}: {e}")
        return None


async def main():
    print(f"Starting calendar scrape for {YEAR}...")

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            locale="bg-BG",
            user_agent=(
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/120.0.0.0 Safari/537.36"
            ),
        )
        page = await context.new_page()

        all_entries = []

        # ── Phase 1: Try month-level pages ──
        print("\n=== Phase 1: Scraping month pages ===")
        for month in range(1, 13):
            print(f"\nMonth {month}/12")
            month_entries = await scrape_month(page, month)
            all_entries.extend(month_entries)

        # ── Phase 2: If month pages didn't yield structured data, ──
        # ──          try the main calendar page (year view)       ──
        if not all_entries:
            print("\n=== Phase 2: Trying year page ===")
            await page.goto(BASE_URL, wait_until="networkidle", timeout=30000)
            await page.wait_for_timeout(3000)

            # Debug: save a screenshot and page source to help diagnose structure
            await page.screenshot(path="calendar_debug.png", full_page=True)
            html = await page.content()
            with open("calendar_debug.html", "w", encoding="utf-8") as f:
                f.write(html)
            print("  Saved debug screenshot and HTML for inspection")

            # Try clicking through each day on the year calendar
            day_links = await page.query_selector_all("a[href*='/calendar/2026-']")
            unique_hrefs = set()
            for link in day_links:
                href = await link.get_attribute("href")
                if href and re.search(r'/calendar/2026-\d{1,2}-\d{1,2}', href):
                    unique_hrefs.add(href)

            if unique_hrefs:
                print(f"  Found {len(unique_hrefs)} unique day links, scraping each...")
                for href in sorted(unique_hrefs):
                    full_url = href if href.startswith("http") else f"https://bg-patriarshia.bg{href}"
                    try:
                        await page.goto(full_url, wait_until="networkidle", timeout=15000)
                        await page.wait_for_timeout(800)
                        text = await page.inner_text("body")
                        # Parse date from URL
                        m = re.search(r'/calendar/2026-(\d{1,2})-(\d{1,2})', href)
                        if m:
                            mon, dy = int(m.group(1)), int(m.group(2))
                            all_entries.append({
                                "raw_text": text,
                                "href": href,
                                "month": mon,
                                "day": dy,
                            })
                    except Exception as e:
                        print(f"    Error: {e}")

        # ── Phase 3: If we still have no entries, try individual day URLs ──
        if not all_entries:
            print("\n=== Phase 3: Scraping individual day pages ===")
            import calendar as cal
            for month in range(1, 13):
                _, days_in_month = cal.monthrange(YEAR, month)
                for day in range(1, days_in_month + 1):
                    print(f"  {YEAR}-{month:02d}-{day:02d}")
                    result = await scrape_individual_day(page, month, day)
                    if result:
                        all_entries.append(result)

        await browser.close()

    # ── Parse and structure the results ──
    print(f"\n=== Processing {len(all_entries)} raw entries ===")

    parsed = []
    bg_months = {
        "януари": 1, "февруари": 2, "март": 3, "април": 4,
        "май": 5, "юни": 6, "юли": 7, "август": 8,
        "септември": 9, "октомври": 10, "ноември": 11, "декември": 12,
    }
    bg_days = {
        "понеделник": "Понеделник", "вторник": "Вторник",
        "сряда": "Сряда", "четвъртък": "Четвъртък",
        "петък": "Петък", "събота": "Събота", "неделя": "Неделя",
    }

    seen_dates = set()

    for entry in all_entries:
        text = entry.get("raw_text", "")

        # Try to parse "DD месец YYYY, ден · описание"
        m = re.match(
            r'(\d{1,2})\s+(\w+)\s+(\d{4}),?\s*(\w+)?\s*[·•\-–]?\s*(.*)',
            text, re.DOTALL
        )
        if m:
            day_num = int(m.group(1))
            month_name = m.group(2).lower()
            year_str = int(m.group(3))
            day_name = m.group(4) or ""
            description = m.group(5).strip()

            month_num = bg_months.get(month_name)
            if month_num and year_str == YEAR:
                date_str = f"{YEAR}-{month_num:02d}-{day_num:02d}"
                if date_str not in seen_dates:
                    seen_dates.add(date_str)
                    parsed.append({
                        "date": date_str,
                        "day_of_week": day_name.capitalize() if day_name else "",
                        "text": description,
                    })
                continue

        # Try parsing from href
        href = entry.get("href", "")
        m2 = re.search(r'/calendar/2026-(\d{1,2})-(\d{1,2})', href)
        if m2:
            mon, dy = int(m2.group(1)), int(m2.group(2))
            date_str = f"{YEAR}-{mon:02d}-{dy:02d}"
            if date_str not in seen_dates:
                seen_dates.add(date_str)
                # Clean the text (remove nav/header/footer noise)
                clean = text[:500] if text else ""
                parsed.append({
                    "date": date_str,
                    "day_of_week": "",
                    "text": clean,
                })

    # Sort by date
    parsed.sort(key=lambda x: x["date"])

    # Fill in day_of_week if missing
    for entry in parsed:
        if not entry["day_of_week"]:
            dt = datetime.strptime(entry["date"], "%Y-%m-%d")
            bg_day_names = [
                "Понеделник", "Вторник", "Сряда", "Четвъртък",
                "Петък", "Събота", "Неделя"
            ]
            entry["day_of_week"] = bg_day_names[dt.weekday()]

    # ── Save outputs ──
    with open("calendar_2026.json", "w", encoding="utf-8") as f:
        json.dump(parsed, f, ensure_ascii=False, indent=2)
    print(f"Saved {len(parsed)} entries to calendar_2026.json")

    with open("calendar_2026.csv", "w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=["date", "day_of_week", "text"])
        writer.writeheader()
        writer.writerows(parsed)
    print(f"Saved {len(parsed)} entries to calendar_2026.csv")

    # Preview first 10
    print("\n=== Preview (first 10 entries) ===")
    for entry in parsed[:10]:
        print(f"  {entry['date']} ({entry['day_of_week']}): {entry['text'][:80]}")


if __name__ == "__main__":
    asyncio.run(main())
