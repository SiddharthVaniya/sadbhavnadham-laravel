"""
Fetch campaign, ad set, and ad level insights for a Meta ad account.

Usage:
    python fetch_campaigns.py --account-id act_XXXXXXXXX --since 2026-03-01 --until 2026-03-31
    python fetch_campaigns.py --account-id act_XXXXXXXXX --since 2026-03-01 --until 2026-03-31 --prefix 202603
    python fetch_campaigns.py --account-id act_XXXXXXXXX --preset last_30d --level ad
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
from datetime import datetime

API_VERSION = "v21.0"
BASE_URL = f"https://graph.facebook.com/{API_VERSION}"
TOKEN = os.environ.get("META_ACCESS_TOKEN", "")
OUTPUT_DIR = os.environ.get("META_OUTPUT_DIR", "output")


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


def parse_actions(actions_list):
    wanted = {"link_click", "post_engagement", "video_view",
              "landing_page_view", "purchase", "lead"}
    return {a["action_type"]: int(a.get("value", 0))
            for a in (actions_list or []) if a.get("action_type") in wanted}


def parse_cpa(cpa_list):
    wanted = {"link_click", "landing_page_view", "post_engagement", "video_view"}
    return {f"cost_per_{c['action_type']}": c.get("value", "")
            for c in (cpa_list or []) if c.get("action_type") in wanted}


def write_csv(filename, rows, fieldnames):
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    path = os.path.join(OUTPUT_DIR, filename)
    with open(path, "w", newline="", encoding="utf-8-sig") as f:
        w = csv.DictWriter(f, fieldnames=fieldnames, extrasaction="ignore")
        w.writeheader()
        w.writerows(rows)
    print(f"  -> {path} ({len(rows)} rows)")


INSIGHT_FIELDS = (
    "campaign_name,campaign_id,adset_name,adset_id,ad_name,ad_id,"
    "objective,impressions,reach,clicks,spend,ctr,cpc,cpm,cpp,frequency,"
    "actions,cost_per_action_type,date_start,date_stop"
)
ACTION_COLS = [
    "link_click", "post_engagement", "video_view", "landing_page_view", "purchase", "lead",
    "cost_per_link_click", "cost_per_landing_page_view", "cost_per_post_engagement", "cost_per_video_view",
]
METRIC_COLS = ["impressions", "reach", "clicks", "spend", "ctr", "cpc", "cpm", "cpp", "frequency"]


def fetch_level(account_id, level, time_params, prefix):
    rows = []
    for item in api_get_all(f"{BASE_URL}/{account_id}/insights", {
        "access_token": TOKEN, "fields": INSIGHT_FIELDS,
        "level": level, "limit": 500, **time_params,
    }):
        cname = item.get("campaign_name", "")
        if prefix and not cname.startswith(prefix):
            continue
        actions = parse_actions(item.get("actions"))
        cpa = parse_cpa(item.get("cost_per_action_type"))
        row = {k: item.get(k, "") for k in INSIGHT_FIELDS.split(",")
               if k not in ("actions", "cost_per_action_type")}
        row.update(actions)
        row.update(cpa)
        rows.append(row)
    return rows


def fetch_ads_with_creatives(account_id, campaign_ids, time_params, prefix):
    since = json.loads(time_params.get("time_range", '{}') ).get("since", "")
    until = json.loads(time_params.get("time_range", '{}')).get("until", "")
    if not since:
        return []

    rows = []
    for ad in api_get_all(f"{BASE_URL}/{account_id}/ads", {
        "access_token": TOKEN,
        "fields": (
            "name,status,campaign_id,campaign_name,adset_name,adset_id,"
            f"creative{{effective_object_story_id,thumbnail_url}},"
            f"preview_shareable_link,"
            f'insights.time_range({{"since":"{since}","until":"{until}"}})'
            f"{{impressions,reach,clicks,spend,ctr,cpc,cpm,frequency,actions,cost_per_action_type}}"
        ),
        "limit": 200,
        "filtering": json.dumps([{
            "field": "effective_status", "operator": "IN",
            "value": ["ACTIVE", "PAUSED"],
        }]),
    }):
        if campaign_ids and ad.get("campaign_id", "") not in campaign_ids:
            continue
        creative = ad.get("creative", {})
        story_id = creative.get("effective_object_story_id", "")
        fb_url = ""
        if story_id and "_" in story_id:
            parts = story_id.split("_")
            fb_url = f"https://www.facebook.com/{parts[0]}/posts/{parts[1]}"
        ins_data = ad.get("insights", {}).get("data", [{}])
        ins = ins_data[0] if ins_data else {}
        actions = parse_actions(ins.get("actions"))
        cpa = parse_cpa(ins.get("cost_per_action_type"))
        row = {
            "campaign_name": ad.get("campaign_name", ""),
            "campaign_id": ad.get("campaign_id", ""),
            "adset_name": ad.get("adset_name", ""),
            "adset_id": ad.get("adset_id", ""),
            "ad_name": ad.get("name", ""),
            "status": ad.get("status", ""),
            "fb_post_url": fb_url,
            "preview_link": ad.get("preview_shareable_link", ""),
        }
        for mf in METRIC_COLS + ["frequency"]:
            row[mf] = ins.get(mf, "")
        row.update(actions)
        row.update(cpa)
        rows.append(row)
    return rows


def main():
    parser = argparse.ArgumentParser(description="Fetch Meta ad campaign data to CSV")
    parser.add_argument("--account-id", required=True, help="Ad account ID (e.g. act_XXXXXXXXX)")
    parser.add_argument("--since", help="Start date YYYY-MM-DD")
    parser.add_argument("--until", help="End date YYYY-MM-DD")
    parser.add_argument("--preset", help="Date preset (last_7d, last_30d, last_month, etc.)")
    parser.add_argument("--prefix", default="", help="Campaign name prefix filter (e.g. 202603)")
    parser.add_argument("--level", default="all", choices=["campaign", "adset", "ad", "all"],
                        help="Which levels to export (default: all)")
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

    label = args.prefix or datetime.now().strftime("%Y%m%d")

    levels_to_run = [args.level] if args.level != "all" else ["campaign", "adset"]

    campaign_ids = set()
    for level in levels_to_run:
        print(f"Fetching {level} level...")
        rows = fetch_level(args.account_id, level, time_params, args.prefix)
        print(f"  {len(rows)} rows")
        if rows:
            csv_fields = [
                "campaign_name", "campaign_id", "adset_name", "adset_id",
                "ad_name", "ad_id", "objective", *METRIC_COLS, *ACTION_COLS,
                "date_start", "date_stop",
            ]
            write_csv(f"{label}_{level}s.csv", rows, csv_fields)
            if level == "campaign":
                campaign_ids = {r["campaign_id"] for r in rows}

    if args.level in ("ad", "all"):
        print("Fetching ads with creatives...")
        ad_rows = fetch_ads_with_creatives(args.account_id, campaign_ids, time_params, args.prefix)
        print(f"  {len(ad_rows)} ads")
        if ad_rows:
            ad_fields = [
                "campaign_name", "campaign_id", "adset_name", "adset_id",
                "ad_name", "status", "fb_post_url", "preview_link",
                *METRIC_COLS, "frequency", *ACTION_COLS,
            ]
            write_csv(f"{label}_ads.csv", ad_rows, ad_fields)

    print(f"\nDone! Files in {OUTPUT_DIR}/")


if __name__ == "__main__":
    main()
