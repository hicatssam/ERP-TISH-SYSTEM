#!/usr/bin/env python3
"""Create a disposable compatibility copy of the QA SQLite fixture.

This is strictly test scaffolding. It does not run Laravel migrations, and it
must not be used to infer or alter the production MySQL schema.
"""
import sqlite3
from pathlib import Path

ROOT = Path("/tmp/tish-finance-qa-20261125/database")
SOURCE = ROOT / "tish-procurement-e2e.sqlite"
DEST = ROOT / "tish-production-schema-qa.sqlite"

if not SOURCE.is_file() or SOURCE.stat().st_size == 0:
    raise RuntimeError("QA source database not found")
if DEST.exists():
    raise RuntimeError(f"Refusing to overwrite existing QA copy: {DEST}")

db = sqlite3.connect(f"file:{SOURCE}?mode=ro", uri=True)
if db.execute("PRAGMA integrity_check").fetchone()[0] != "ok":
    raise RuntimeError("Source QA integrity check failed")

try:
    with sqlite3.connect(DEST) as target:
        db.backup(target)
finally:
    db.close()

updates = {
    "production_orders": {
        "quality_required": "INTEGER NOT NULL DEFAULT 0",
        "materials_consumed_at": "datetime",
        "submitted_quality_at": "datetime",
        "rejected_at": "datetime",
    },
    "production_order_items": {
        "planned_quantity": "numeric NOT NULL DEFAULT 0",
        "reserved_quantity": "numeric NOT NULL DEFAULT 0",
        "actual_quantity": "numeric",
        "actual_unit_cost": "numeric NOT NULL DEFAULT 0",
        "consumed_at": "datetime",
        "waste_percent": "numeric NOT NULL DEFAULT 0",
    },
}

try:
    with sqlite3.connect(DEST) as target:
        for table, desired in updates.items():
            existing = {
                row[1] for row in target.execute(f'PRAGMA table_info("{table}")')
            }
            if not existing:
                raise RuntimeError(f"Missing table: {table}")
            for column, definition in desired.items():
                if column not in existing:
                    target.execute(
                        f'ALTER TABLE "{table}" ADD COLUMN "{column}" {definition}'
                    )
                    print(f"QA COPY COLUMN ADDED: {table}.{column}")

        integrity = target.execute("PRAGMA integrity_check").fetchone()[0]
        if integrity != "ok":
            raise RuntimeError(f"Copy integrity check failed: {integrity}")
        fk = target.execute("PRAGMA foreign_key_check").fetchall()
        if fk:
            raise RuntimeError(f"Copy FK check failed: {fk[:3]}")

    print("SOURCE UNCHANGED:", SOURCE)
    print("QA COMPATIBILITY COPY:", DEST)
    print("=== QA PRODUCTION SCHEMA COPY READY ===")
except BaseException:
    DEST.unlink(missing_ok=True)
    raise
