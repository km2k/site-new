#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Generate SQL INSERT statements for services table from duties_3.csv

CSV format (semicolon-delimited):
  service_date;day_of_week;priest

Table schema (from schema.sql):
  id, title, description, service_date, start_time, end_time,
  day_of_week, priest, feast, created_at, updated_at

Fixed values:
  title       = 'Литургия'
  start_time  = '09:00'
  end_time    = '10:00'
"""

import csv
from datetime import datetime

INPUT_FILE  = 'duties_3.csv'
OUTPUT_FILE = 'liturgy_2026_from_duties3.sql'

def parse_date(date_str):
    """Convert DD.MM.YYYY to YYYY-MM-DD"""
    d, m, y = date_str.strip().split('.')
    return f"{y}-{m.zfill(2)}-{d.zfill(2)}"

def esc(text):
    """Escape single quotes for SQL"""
    if not text:
        return 'NULL'
    return "'" + text.replace("'", "''") + "'"

def main():
    lines = []
    lines.append("-- ============================================================")
    lines.append("--  SERVICES 2026 - Generated from " + INPUT_FILE)
    lines.append("--  Auto-generated on " + datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
    lines.append("-- ============================================================")
    lines.append("")
    lines.append("-- Remove old services before importing")
    lines.append("DELETE FROM services;")
    lines.append("")
    lines.append("INSERT INTO services (title, service_date, start_time, end_time, day_of_week, priest) VALUES")

    values = []

    with open(INPUT_FILE, 'r', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f, delimiter=';')

        for row in reader:
            service_date = row.get('service_date', '').strip()
            day_of_week  = row.get('day_of_week', '').strip()
            priest       = row.get('priest', '').strip()

            if not service_date or not day_of_week or not priest:
                continue

            try:
                sql_date = parse_date(service_date)
            except (ValueError, IndexError):
                print(f"Warning: skipping invalid date: {service_date}")
                continue

            try:
                day_num = int(day_of_week)
                if not 1 <= day_num <= 7:
                    raise ValueError
            except ValueError:
                print(f"Warning: skipping invalid day_of_week: {day_of_week}")
                continue

            values.append(
                f"  ('Литургия', '{sql_date}', '09:00', '10:00', {day_num}, {esc(priest)})"
            )

    lines.append(',\n'.join(values) + ';')
    lines.append("")
    lines.append(f"-- Total records: {len(values)}")

    sql = '\n'.join(lines)

    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        f.write(sql)

    print(f"✓ Generated: {OUTPUT_FILE}")
    print(f"✓ Records:   {len(values)}")
    print()
    print("--- Preview ---")
    for line in sql.split('\n')[:12]:
        print(line)
    print("...")

if __name__ == '__main__':
    main()

