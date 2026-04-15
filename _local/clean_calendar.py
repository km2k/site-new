#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Clean calendar_2026.csv descriptions:
  1. Remove substrings starting with " (Тип." up to and including the closing ")"
  2. Remove everything from ". Гл." to the end of the string
  3. Strip trailing whitespace/punctuation
"""

import re
import csv

INPUT  = 'calendar_2026.csv'
OUTPUT = 'calendar_2026_clean.csv'

def clean_description(text):
    # 1. Remove all "(Тип. ...)" blocks
    text = re.sub(r'\s*\(Тип\.[^)]*\)', '', text)

    # 2. Remove from ". Гл." to end of string
    text = re.sub(r'\.\s*Гл\..*$', '', text)

    # 3. Clean up trailing/leading whitespace and stray punctuation
    text = text.strip().rstrip('.,;: ')

    return text

with open(INPUT, 'r', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    rows = list(reader)

for row in rows:
    row['description'] = clean_description(row['description'])

with open(OUTPUT, 'w', encoding='utf-8', newline='') as f:
    writer = csv.DictWriter(f, fieldnames=['date', 'description'])
    writer.writeheader()
    writer.writerows(rows)

print(f"✓ Written {OUTPUT} with {len(rows)} rows")
print()

# Show samples
print("--- Before / After samples ---")
with open(INPUT, 'r', encoding='utf-8') as f:
    orig = list(csv.DictReader(f))

samples = [0, 3, 4, 5, 6, 52, 53]
for i in samples:
    if i < len(rows):
        print(f"\n  [{orig[i]['date']}]")
        print(f"  BEFORE: {orig[i]['description'][:100]}...")
        print(f"  AFTER:  {rows[i]['description'][:100]}")

