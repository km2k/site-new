#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Rewrite calendar_2026_clean.csv so every description field is quoted.
"""

import csv

INPUT  = 'calendar_2026_clean.csv'
OUTPUT = 'calendar_2026_clean.csv'  # overwrite in place

with open(INPUT, 'r', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    rows = list(reader)

with open(OUTPUT, 'w', encoding='utf-8', newline='') as f:
    writer = csv.DictWriter(f, fieldnames=['date', 'description'], quoting=csv.QUOTE_ALL)
    writer.writeheader()
    writer.writerows(rows)

print(f"✓ Updated {OUTPUT} — all fields quoted ({len(rows)} rows)")

