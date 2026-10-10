"""
Fetch Instagram profile, audience demographics, follower trends, and posts.

Usage:
    python fetch_ig_data.py --ig-id 17841XXXXXXXXXX --page-id 1234567890
    python fetch_ig_data.py --ig-id 17841XXXXXXXXXX --page-id 1234567890 \
        --since 2026-03-16 --until 2026-04-15

Requires: instagram_basic, instagram_manage_insights permissions.
The IG account must be shared with your Business as an asset.
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
    parser = argparse.ArgumentParser(description="Fetch Instagram data to CSV")
    parser.add_argument("--ig-id", required=True, help="Instagram User ID (e.g. 17841XXXXXXXXXX)")
    parser.add_argument("--page-id", required=True, help="Facebook Page ID linked to the IG account")
    parser.add_argument("--since", help="Start date YYYY-MM-DD (for follower trend, max 30 days)")
    parser.add_argument("--until", help="End date YYYY-MM-DD")
    parser.add_argument("--label", default="", help="Output file prefix (default: auto from dates)")
    args = parser.parse_args()

    if not TOKEN:
        print("Set META_ACCESS_TOKEN environment variable first.")
        sys.exit(1)

    pt = get_page_token(args.page_id)
    if not pt:
        print(f"Could not get page token for {args.page_id}. Check page access.")
        sys.exit(1)

    label = args.label or (args.since or "")[:7].replace("-", "") or "latest"

    # 1. Profile
    print("[1/4] IG Profile")
    r = requests.get(f"{BASE_URL}/{args.ig_id}", params={
        "access_token": pt,
        "fields": "id,username,name,followers_count,follows_count,media_count,biography,website",
    }).json()
    if "username" in r:
        print(f"  @{r['username']} — {r.get('followers_count',0):,} followers, {r.get('media_count',0)} posts")
        write_csv(f"{label}_ig_profile.csv", [{
            "username": r.get("username", ""),
            "name": r.get("name", ""),
            "followers_count": r.get("followers_count", ""),
            "follows_count": r.get("follows_count", ""),
            "media_count": r.get("media_count", ""),
            "biography": r.get("biography", ""),
        }], ["username", "name", "followers_count", "follows_count", "media_count", "biography"])
    elif "error" in r:
        print(f"  Error: {r['error']['message'][:120]}")

    # 2. Audience demographics
    print("\n[2/4] IG Audience Demographics")
    demo_rows = []
    for breakdown_type in ["age,gender", "city", "country"]:
        r2 = requests.get(f"{BASE_URL}/{args.ig_id}/insights", params={
            "access_token": pt,
            "metric": "follower_demographics",
            "period": "lifetime",
            "metric_type": "total_value",
            "breakdown": breakdown_type,
        }).json()
        if "data" in r2 and r2["data"]:
            for metric in r2["data"]:
                for bd in metric.get("total_value", {}).get("breakdowns", []):
                    for row in bd.get("results", []):
                        dims = row.get("dimension_values", [])
                        val = int(row.get("value", 0))
                        demo_rows.append({
                            "breakdown_type": breakdown_type,
                            "dimension_1": dims[0] if dims else "",
                            "dimension_2": dims[1] if len(dims) > 1 else "",
                            "count": val,
                        })
        elif "error" in r2:
            print(f"  {breakdown_type}: {r2['error']['message'][:100]}")

    if demo_rows:
        print(f"  {len(demo_rows)} demographic rows")
        write_csv(f"{label}_ig_demographics.csv", demo_rows,
                  ["breakdown_type", "dimension_1", "dimension_2", "count"])

        age_gender = [r for r in demo_rows if r["breakdown_type"] == "age,gender"]
        if age_gender:
            total = sum(r["count"] for r in age_gender)
            gender_map = {"F": "Female", "M": "Male", "U": "Unknown"}
            gender_totals = {}
            for r in age_gender:
                g = r["dimension_1"]
                gender_totals[g] = gender_totals.get(g, 0) + r["count"]
            print(f"\n  Gender:")
            for g in ["F", "M", "U"]:
                if g in gender_totals:
                    c = gender_totals[g]
                    print(f"    {gender_map.get(g,g):<10s} {c:>6,}  ({c/total*100:.1f}%)")

    # 3. Follower count trend
    print("\n[3/4] IG Follower Count (daily)")
    if args.since and args.until:
        r3 = requests.get(f"{BASE_URL}/{args.ig_id}/insights", params={
            "access_token": pt,
            "metric": "follower_count",
            "period": "day",
            "since": args.since,
            "until": args.until,
        }).json()
        follower_rows = []
        if "data" in r3 and r3["data"]:
            total_change = 0
            for v in r3["data"][0].get("values", []):
                change = v["value"]
                total_change += change
                follower_rows.append({"date": v["end_time"][:10], "net_change": change})
            print(f"  {len(follower_rows)} days, total change: {total_change:+,}")
            write_csv(f"{label}_ig_follower_daily.csv", follower_rows, ["date", "net_change"])
        elif "error" in r3:
            print(f"  Error: {r3['error']['message'][:120]}")
    else:
        print("  Skipped (provide --since and --until, max 30-day window)")

    # 4. Recent posts
    print("\n[4/4] IG Posts")
    r4 = requests.get(f"{BASE_URL}/{args.ig_id}/media", params={
        "access_token": pt,
        "fields": "id,caption,media_type,timestamp,permalink,like_count,comments_count",
        "limit": 50,
    }).json()
    post_rows = []
    if "data" in r4:
        cutoff = None
        if args.since:
            try:
                cutoff = datetime.strptime(args.since, "%Y-%m-%d")
            except ValueError:
                pass
        for post in r4["data"]:
            ts = post.get("timestamp", "")
            if cutoff:
                try:
                    dt = datetime.fromisoformat(ts.replace("Z", "+00:00"))
                    if dt.replace(tzinfo=None) < cutoff:
                        continue
                except Exception:
                    pass
            post_rows.append({
                "date": ts[:10],
                "media_type": post.get("media_type", ""),
                "likes": post.get("like_count", 0),
                "comments": post.get("comments_count", 0),
                "caption": (post.get("caption") or "")[:200].replace("\n", " "),
                "permalink": post.get("permalink", ""),
            })
        print(f"  {len(post_rows)} posts")
        if post_rows:
            write_csv(f"{label}_ig_posts.csv", post_rows,
                      ["date", "media_type", "likes", "comments", "caption", "permalink"])
    elif "error" in r4:
        print(f"  Error: {r4['error']['message'][:120]}")

    print(f"\nDone!")


if __name__ == "__main__":
    main()
