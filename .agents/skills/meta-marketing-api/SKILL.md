---
name: meta-marketing-api
description: Fetch ad performance, audience demographics, page data, and Instagram insights from Meta Marketing API / Graph API for any business client. Use when the user wants to pull Meta/Facebook/Instagram ad metrics, campaign data, audience breakdowns, page fan counts, IG followers, post links, or generate monthly reports from Meta ad accounts.
---

# Meta Marketing API Data Extraction

Pull ad performance data, audience demographics, page stats, Instagram insights, and post links from the Meta Marketing API for any business client.

## Prerequisites

Before running any scripts, ensure the user has completed the setup in [README.md](README.md). The key requirements:

1. A Meta Developer App (Live mode, with Instagram Graph API product added)
2. A valid User Access Token with required permissions
3. The `META_ACCESS_TOKEN` environment variable set

If the user hasn't set this up yet, direct them to [README.md](README.md) first.

## What Data Can Be Fetched

| Data | API Endpoint | Notes |
|------|-------------|-------|
| Ad performance (campaign/adset/ad) | `/{ad-account}/insights` | Impressions, reach, clicks, spend, CTR, CPC, actions |
| Audience demographics (ad-reached) | `/{ad-account}/insights` with `breakdowns=age,gender` | Also: platform, country, device, placement |
| FB page fan count | `/{page-id}?fields=fan_count` | Current count only; historical deprecated |
| Published posts | `/{page-id}/feed` | Requires Page Access Token |
| Ad post links & creatives | `/{ad-account}/ads` with `creative{effective_object_story_id}` | FB post URLs + preview links |
| IG profile & followers | `/{ig-id}?fields=followers_count,...` | Needs `instagram_basic` + IG asset shared |
| IG audience demographics | `/{ig-id}/insights?metric=follower_demographics` | Needs `instagram_manage_insights` |
| IG posts & engagement | `/{ig-id}/media` | Likes, comments, media type, permalinks |
| IG daily follower change | `/{ig-id}/insights?metric=follower_count&period=day` | Last 30 days only |

## What CANNOT Be Fetched

| Data | Reason | Workaround |
|------|--------|------------|
| FB organic fan demographics | `page_fans_gender_age` deprecated in API v18+ | Screenshot from Meta Business Suite |
| Content calendar (visual) | No calendar API | Screenshot from Business Suite |
| IG daily follower count >30 days ago | API only keeps 30 days | Record periodically |
| Competitor IG data (Business Discovery) | Requires unrestricted `instagram_basic` scope + app review | See setup-guide.md |
| KPI targets | Internal business data | Manual input |

## Workflow: Monthly Client Report

Follow this workflow to extract all data for a client's monthly report.

### Step 1: Identify the client's assets

Find the client's ad account ID, FB page ID, and IG account ID:

```python
# List all ad accounts
GET /me/adaccounts?fields=id,name,account_status

# List all pages (includes fan count)
GET /me/accounts?fields=id,name,fan_count,instagram_business_account{id,username,followers_count}
```

### Step 2: Run the extraction scripts

The scripts in `scripts/` handle the full extraction. Adapt the account IDs, dates, and campaign prefix for each client.

```bash
export META_ACCESS_TOKEN="your_token_here"

# 1. Campaign, ad set, and ad level metrics → CSV
python scripts/fetch_campaigns.py \
  --account-id act_XXXXXXXXX \
  --since 2026-03-01 --until 2026-03-31 \
  --prefix 202603

# 2. Audience demographics (age x gender + platform) → CSV
python scripts/fetch_demographics.py \
  --account-id act_XXXXXXXXX \
  --since 2026-03-01 --until 2026-03-31

# 3. Page fans, published posts, post links → CSV
python scripts/fetch_page_and_posts.py \
  --page-id 1234567890 \
  --account-id act_XXXXXXXXX \
  --since 2026-03-01 --until 2026-03-31 \
  --prefix 202603

# 4. IG profile, audience, posts (if IG access available)
python scripts/fetch_ig_data.py \
  --ig-id 17841XXXXXXXXXX \
  --page-id 1234567890 \
  --since 2026-03-16 --until 2026-04-15
```

### Step 3: Verify outputs

All CSVs are saved to `output/`. Typical deliverables:

| Report Item | CSV File | Script |
|------------|----------|--------|
| Ad performance | `{prefix}_campaigns.csv`, `{prefix}_adsets.csv`, `{prefix}_ads.csv` | `fetch_campaigns.py` |
| FB fan count | `{prefix}_fb_fans.csv` | `fetch_page_and_posts.py` |
| Audience age x gender | `{prefix}_demographics_age_gender.csv` | `fetch_demographics.py` |
| Platform split (FB vs IG) | `{prefix}_demographics_platform.csv` | `fetch_demographics.py` |
| Post links per campaign | `{prefix}_post_links.csv` | `fetch_page_and_posts.py` |
| Published posts | `{prefix}_published_posts.csv` | `fetch_page_and_posts.py` |
| IG follower count | `{prefix}_ig_profile.csv` | `fetch_ig_data.py` |
| IG audience | `{prefix}_ig_demographics.csv` | `fetch_ig_data.py` |
| IG posts | `{prefix}_ig_posts.csv` | `fetch_ig_data.py` |

## Token Management

Tokens expire frequently. Handle this in scripts:

```python
# Check token validity
GET /debug_token?input_token={token}

# Exchange short-lived (1-2h) for long-lived (60 days)
GET /oauth/access_token
  ?grant_type=fb_exchange_token
  &client_id={APP_ID}
  &client_secret={APP_SECRET}
  &fb_exchange_token={SHORT_LIVED_TOKEN}
```

If a script fails with error code 190 ("Session has expired"), the user needs to regenerate their token. Direct them to the setup guide.

## Key API Patterns

### Pagination
All list endpoints return paginated results. Always follow `paging.next`:

```python
while url:
    data = requests.get(url, params=params).json()
    for item in data.get("data", []):
        yield item
    url = data.get("paging", {}).get("next")
    if url:
        params = {}  # params are embedded in next URL
```

### Date Filtering
Two approaches:
```python
# Date preset
params = {"date_preset": "last_30d"}  # also: last_7d, last_month, this_month

# Custom range
params = {"time_range": json.dumps({"since": "2026-03-01", "until": "2026-03-31"})}
```

### Page Access Tokens
Some endpoints (published posts, page insights) require a Page token, not a User token:
```python
# Get page tokens from user token
pages = GET /me/accounts?fields=id,access_token
# Each page object includes its access_token
```

### Action Parsing
Ad insights return actions as an array. Parse into a flat dict:
```python
actions = {a["action_type"]: int(a["value"])
           for a in item.get("actions", [])
           if a["action_type"] in ("link_click", "post_engagement", "video_view", ...)}
```

## Additional Resources

- Full setup instructions: [README.md](README.md)
- Detailed setup for app, tokens, and permissions: [setup-guide.md](setup-guide.md)
- API endpoint reference: [api-reference.md](api-reference.md)
