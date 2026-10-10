"""
Fetch audience demographic breakdowns (ad-reached) for a Meta ad account.

Usage:
    python fetch_demographics.py --account-id act_XXXXXXXXX --since 2026-03-01 --until 2026-03-31
    python fetch_demographics.py --account-id act_XXXXXXXXX --preset last_30d --breakdown platform
"""

try:
    import truststore
    truststore.inject_into_ssl()
except Exception:
    pass

import argparse
import requests
import json
import csv
import os
import sys

API_VERSION = "v21.0"
BASE_URL = f"https://graph.facebook.com/{API_VERSION}"
TOKEN = os.environ.get("META_ACCESS_TOKEN", "")
OUTPUT_DIR = os.environ.get("META_OUTPUT_DIR", "output")

BREAKDOWN_CONFIGS = {
    "age_gender": {"breakdowns": "age,gender", "fields": ["age", "gender"]},
    "platform": {"breakdowns": "publisher_platform", "fields": ["publisher_platform"]},
    "placement": {"breakdowns": "publisher_platform,platform_position", "fields": ["publisher_platform", "platform_position"]},
    "country": {"breakdowns": "country", "fields": ["country"]},
    "device": {"breakdowns": "device_platform", "fields": ["device_platform"]},
}


def api_get_all(url, params, max_pages=20):
    for _ in range(max_pages):
        resp = requests.get(url, params=params).json()
        if "error" in resp:
            msg = resp["error"].get("message", "")
            if "expired" in msg.lower() or resp["error"].get("code") == 190:
                print(f"Token expired: {msg[:100]}", file=sys.stderr)
                sys.exit(1)
            print(f"  API Error: {msg[:120]}", file=sys.stderr)
            return
        for item in resp.get("data", []):
            yield item
        nxt = resp.get("paging", {}).get("next")
        if not nxt:
            return
        url, params = nxt, {}


def write_csv(filename, rows, fieldnames):
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    path = os.path.join(OUTPUT_DIR, filename)
    with open(path, "w", newline="", encoding="utf-8-sig") as f:
        w = csv.DictWriter(f, fieldnames=fieldnames, extrasaction="ignore")
        w.writeheader()
        w.writerows(rows)
    print(f"  -> {path} ({len(rows)} rows)")


def main():
    parser = argparse.ArgumentParser(description="Fetch Meta ad audience demographics to CSV")
    parser.add_argument("--account-id", required=True, help="Ad account ID (e.g. act_XXXXXXXXX)")
    parser.add_argument("--since", help="Start date YYYY-MM-DD")
    parser.add_argument("--until", help="End date YYYY-MM-DD")
    parser.add_argument("--preset", help="Date preset (last_7d, last_30d, etc.)")
    parser.add_argument("--breakdown", default="age_gender",
                        choices=list(BREAKDOWN_CONFIGS.keys()))
    args = parser.parse_args()

    if not TOKEN:
        print("Set META_ACCESS_TOKEN environment variable first.")
        sys.exit(1)

    if args.since and args.until:
        time_params = {"time_range": json.dumps({"since": args.since, "until": args.until})}
    elif args.preset:
        time_params = {"date_preset": args.preset}
    else:
        time_params = {"date_preset": "last_30d"}

    config = BREAKDOWN_CONFIGS[args.breakdown]
    metric_fields = "impressions,reach,clicks,spend,ctr,cpc,cpm"

    print(f"Fetching {args.breakdown} breakdown...")
    rows = []
    for item in api_get_all(f"{BASE_URL}/{args.account_id}/insights", {
        "access_token": TOKEN, "fields": metric_fields,
        "breakdowns": config["breakdowns"], "level": "account",
        "limit": 500, **time_params,
    }):
        row = {}
        for bf in config["fields"]:
            row[bf] = item.get(bf, "")
        for mf in metric_fields.split(","):
            row[mf] = item.get(mf, "")
        row["date_start"] = item.get("date_start", "")
        row["date_stop"] = item.get("date_stop", "")
        rows.append(row)

    print(f"  {len(rows)} rows")
    if rows:
        csv_fields = config["fields"] + metric_fields.split(",") + ["date_start", "date_stop"]
        label = (args.since or "")[:7].replace("-", "") or "latest"
        write_csv(f"{label}_demographics_{args.breakdown}.csv", rows, csv_fields)

        if args.breakdown == "age_gender":
            total_reach = sum(int(r["reach"]) for r in rows if r["reach"])
            print(f"\n  Gender Summary:")
            gender_totals = {}
            for r in rows:
                g = r["gender"]
                gender_totals[g] = gender_totals.get(g, 0) + (int(r["reach"]) if r["reach"] else 0)
            for g, reach in sorted(gender_totals.items(), key=lambda x: -x[1]):
                pct = reach / total_reach * 100 if total_reach else 0
                print(f"    {g:<10s} {reach:>10,}  ({pct:.1f}%)")

    print(f"\nDone!")


if __name__ == "__main__":
    main()
