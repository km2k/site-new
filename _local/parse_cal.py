#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Parse cal.txt and produce a CSV with date and description.

cal.txt format:
  Month header line: "Януари - 2026 година"
  Then repeating blocks:
    DD<tab>dayabbrev
    description text
    (blank line)
"""

import re

INPUT  = 'cal.txt'
OUTPUT = 'calendar_2026.csv'

MONTHS = {
    'Януари': '01', 'Февруари': '02', 'Март': '03', 'Април': '04',
    'Май': '05', 'Юни': '06', 'Юли': '07', 'Август': '08',
    'Септември': '09', 'Октомври': '10', 'Ноември': '11', 'Декември': '12',
}

with open(INPUT, 'r', encoding='utf-8') as f:
    lines = f.readlines()

rows = []
current_month = None

i = 0
while i < len(lines):
    line = lines[i].rstrip('\n\r')

    # Check for month header
    month_match = re.match(r'^(\w+)\s*-\s*(\d{4})\s*година', line)
    if month_match:
        month_name = month_match.group(1)
        current_month = MONTHS.get(month_name)
        i += 1
        continue

    # Check for date line: DD<tab>abbrev
    date_match = re.match(r'^(\d{2})\s+\w', line)
    if date_match and current_month:
        day = date_match.group(1)
        date_str = f"{day}.{current_month}.2026"

        # Next line is the description
        desc = ''
        if i + 1 < len(lines):
            desc = lines[i + 1].rstrip('\n\r').strip()

        # Escape quotes for CSV
        desc = desc.replace('"', '""')
        rows.append(f'{date_str},"{desc}"')
        i += 2
        continue

    i += 1

with open(OUTPUT, 'w', encoding='utf-8') as f:
    f.write('date,description\n')
    f.write('\n'.join(rows) + '\n')

print(f"✓ Written {OUTPUT} with {len(rows)} rows")

# Verify a sample
print("\n--- Sample (Apr 12-20) ---")
for r in rows:
    if ',\"' in r and r.startswith('1') and '.04.2026' in r:
        d = int(r.split('.')[0])
        if 12 <= d <= 20:
            print(f"  {r[:80]}...")

