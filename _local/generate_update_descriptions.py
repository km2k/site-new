#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Generate SQL UPDATE statements to set description in services table
from calendar_2026_clean.csv, matching on service_date.
"""

import csv
from datetime import datetime

INPUT  = 'calendar_2026_clean.csv'
OUTPUT = 'update_descriptions.sql'

def parse_date(date_str):
    """Convert DD.MM.YYYY to YYYY-MM-DD"""
    d, m, y = date_str.strip().split('.')
    return f"{y}-{m.zfill(2)}-{d.zfill(2)}"

def esc(text):
    """Escape single quotes for SQL"""
    return text.replace("'", "''")

with open(INPUT, 'r', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    rows = list(reader)

lines = []
lines.append("-- ============================================================")
lines.append("--  UPDATE descriptions in services from calendar_2026_clean.csv")
lines.append("--  Auto-generated on " + datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
lines.append(f"--  Total updates: {len(rows)}")
lines.append("-- ============================================================")
lines.append("")

for row in rows:
    sql_date = parse_date(row['date'])
    desc = esc(row['description'])
    lines.append(f"UPDATE services SET description = '{desc}' WHERE service_date = '{sql_date}';")

sql = '\n'.join(lines) + '\n'

with open(OUTPUT, 'w', encoding='utf-8') as f:
    f.write(sql)

print(f"✓ Generated {OUTPUT} with {len(rows)} UPDATE statements")

