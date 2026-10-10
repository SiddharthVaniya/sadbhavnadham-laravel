"""
Fetch FB page fan count, published posts, and one post link per campaign.

Usage:
    python fetch_page_and_posts.py --page-id 1234567890 --account-id act_XXXXXXXXX \
        --since 2026-03-01 --until 2026-03-31 --prefix 202603
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


def get_page_token(page_id):
    pages = requests.get(f"{BASE_URL}/me/accounts", params={
        "access_token": TOKEN, "fields": "id,access_token", "limit": 50,
    }).json()
    for p in pages.get("data", []):
        if p["id"] == page_id:
            return p["access_token"]
    return None


def main():
    parser = argparse.ArgumentParser(description="Fetch FB page data and post links")
    parser.add_argument("--page-id", required=True, help="Facebook Page ID")
    parser.add_argument("--account-id", help="Ad account ID for post links (e.g. act_XXXXXXXXX)")
    parser.add_argument("--since", help="Start date YYYY-MM-DD")
    parser.add_argument("--until", help="End date YYYY-MM-DD")
    parser.add_argument("--prefix", default="", help="Campaign name prefix for post links")
    args = parser.parse_args()

    if not TOKEN:
        print("Set META_ACCESS_TOKEN environment variable first.")
        sys.exit(1)

    label = args.prefix or "latest"

    # 1. Fan count
    print("[1/3] FB Fan Count")
    r = requests.get(f"{BASE_URL}/{args.page_id}", params={
        "access_token": TOKEN, "fields": "name,fan_count",
    }).json()
    if "error" in r:
        print(f"  Error: {r['error']['message'][:100]}")
    else:
        fans = r.get("fan_count", "?")
        name = r.get("name", "?")
        print(f"  {name} — {fans:,} fans")
        write_csv(f"{label}_fb_fans.csv",
                  [{"page_name": name, "page_id": args.page_id,
                    "fan_count": fans, "snapshot_date": "current"}],
                  ["page_name", "page_id", "fan_count", "snapshot_date"])

    # 2. Published posts
    print("\n[2/3] Published Posts")
    page_token = get_page_token(args.page_id)
    post_rows = []
    if page_token and args.since:
        params = {
            "access_token": page_token,
            "fields": "message,created_time,permalink_url,type,shares",
            "limit": 100,
        }
        if args.since:
            params["since"] = args.since
        if args.until:
            params["until"] = args.until
        for post in api_get_all(f"{BASE_URL}/{args.page_id}/feed", params):
            msg = (post.get("message") or "")[:200].replace("\n", " ")
            post_rows.append({
                "created_time": post.get("created_time", ""),
                "type": post.get("type", ""),
                "message": msg,
                "permalink_url": post.get("permalink_url", ""),
                "shares": post.get("shares", {}).get("count", 0),
            })
        print(f"  {len(post_rows)} posts")
        if post_rows:
            write_csv(f"{label}_published_posts.csv", post_rows,
                      ["created_time", "type", "message", "permalink_url", "shares"])
    else:
        print("  Skipped (no page token or no date range)")

    # 3. Post links (one per campaign)
    if args.account_id:
        print("\n[3/3] Post Links (one per campaign)")
        time_params = {}
        if args.since and args.until:
            time_params = {"time_range": json.dumps({"since": args.since, "until": args.until})}

        campaign_ids = set()
        if time_params:
            for item in api_get_all(f"{BASE_URL}/{args.account_id}/insights", {
                "access_token": TOKEN, "fields": "campaign_name,campaign_id",
                "level": "campaign", "limit": 500, **time_params,
            }):
                if not args.prefix or item.get("campaign_name", "").startswith(args.prefix):
                    campaign_ids.add(item["campaign_id"])

        seen = set()
        link_rows = []
        for ad in api_get_all(f"{BASE_URL}/{args.account_id}/ads", {
            "access_token": TOKEN,
            "fields": "name,campaign_id,campaign_name,creative{effective_object_story_id},preview_shareable_link",
            "limit": 200,
            "filtering": json.dumps([{
                "field": "effective_status", "operator": "IN",
                "value": ["ACTIVE", "PAUSED"],
            }]),
        }):
            cid = ad.get("campaign_id", "")
            if (campaign_ids and cid not in campaign_ids) or cid in seen:
                continue
            seen.add(cid)
            story_id = ad.get("creative", {}).get("effective_object_story_id", "")
            fb_url = ""
            if story_id and "_" in story_id:
                parts = story_id.split("_")
                fb_url = f"https://www.facebook.com/{parts[0]}/posts/{parts[1]}"
            link_rows.append({
                "campaign_name": ad.get("campaign_name", ""),
                "campaign_id": cid,
                "ad_name": ad.get("name", ""),
                "fb_post_url": fb_url,
                "preview_link": ad.get("preview_shareable_link", ""),
            })

        print(f"  {len(link_rows)} campaign links")
        if link_rows:
            write_csv(f"{label}_post_links.csv", link_rows,
                      ["campaign_name", "campaign_id", "ad_name", "fb_post_url", "preview_link"])

    print(f"\nDone!")


if __name__ == "__main__":
    main()
