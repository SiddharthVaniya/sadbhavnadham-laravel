# Meta Marketing API — Skill Setup Guide

This Cursor skill enables an AI agent to fetch ad performance data, audience demographics, page stats, Instagram insights, and post links from the Meta Marketing API for any business client.

## Quick Start

If you've already completed the one-time setup below, just run:

```bash
export META_ACCESS_TOKEN="your_token_here"

python ~/.cursor/skills/meta-marketing-api/scripts/fetch_campaigns.py \
  --account-id act_XXXXXXXXX --since 2026-03-01 --until 2026-03-31 --prefix 202603
```

## One-Time Setup

### Step 1: Create a Meta Developer App

1. Go to [developers.facebook.com](https://developers.facebook.com/) → **My Apps** → **Create App**
2. Choose **"Other"** → **"Business"**
3. Name it (e.g. "API Data Exporter"), select your Business Portfolio
4. Click **Create App**

### Step 2: Add Instagram Graph API

1. In the app sidebar, go to **Use cases**
2. Find the **Instagram** use case → click **Add** / **Customize**
3. Ensure these permissions are enabled inside:
   - `instagram_basic`
   - `instagram_manage_insights`

### Step 3: Set Privacy Policy & Go Live

1. Go to **Settings → Basic**
2. Enter a **Privacy Policy URL** — options:
   - Your company's existing privacy policy page
   - A free generated one from [privacypolicies.com](https://www.privacypolicies.com/)
   - A simple hosted page stating: *"This app is used internally for accessing Meta Marketing API data. No personal user data is collected or shared."*
3. Save, then toggle the app from **Development → Live**

### Step 4: Generate Access Token

1. Go to [Graph API Explorer](https://developers.facebook.com/tools/explorer)
2. Select **your app** from the top-left dropdown
3. Add these permissions:

   ```
   ads_read
   ads_management
   business_management
   pages_show_list
   pages_read_engagement
   instagram_basic
   instagram_manage_insights
   ```

4. Click **Generate Access Token**
5. In the authorization dialog:
   - Grant access to **all pages** listed
   - Grant access to **ALL Instagram accounts** (not just one — selecting only one restricts the token's scope)

### Step 5: Set Environment Variable

```bash
export META_ACCESS_TOKEN="EAAxxxxxxx..."
```

Add to your `~/.zshrc` or `~/.bashrc` to persist across sessions.

## Token Management

| Token Type | Duration | How to Get |
|-----------|----------|------------|
| Short-lived | ~1-2 hours | Graph API Explorer |
| Long-lived | ~60 days | Exchange via API (see below) |

### Exchange for 60-Day Token

```bash
curl "https://graph.facebook.com/v21.0/oauth/access_token\
?grant_type=fb_exchange_token\
&client_id=YOUR_APP_ID\
&client_secret=YOUR_APP_SECRET\
&fb_exchange_token=$META_ACCESS_TOKEN"
```

Find App ID and Secret at: **Settings → Basic** in the app dashboard.

## Client Asset Access

For agency use, clients must share their assets with your Business:

### Ad Accounts
Client: **Business Settings → Ad accounts → Partners → Add Partner** → enter your Business ID

### Instagram Accounts (for IG follower/audience data)
Client: **Business Settings → Instagram accounts → select account → Partners → Add Partner** → enter your Business ID

Without sharing the IG account, you can still get ad-level audience demographics but not organic IG insights.

## Available Scripts

All scripts are in `~/.cursor/skills/meta-marketing-api/scripts/`:

| Script | What it fetches |
|--------|----------------|
| `fetch_campaigns.py` | Campaign, ad set, ad level metrics + creatives + post links |
| `fetch_demographics.py` | Audience breakdowns: age×gender, platform, country, device |
| `fetch_page_and_posts.py` | FB fan count, published posts, post links per campaign |
| `fetch_ig_data.py` | IG profile, audience demographics, follower trend, posts |

### Example: Full monthly report for a client

```bash
export META_ACCESS_TOKEN="EAAxxxxxxx..."

# Ad performance (campaigns, ad sets, ads)
python ~/.cursor/skills/meta-marketing-api/scripts/fetch_campaigns.py \
  --account-id act_965535620739283 \
  --since 2026-03-01 --until 2026-03-31 --prefix 202603

# Audience demographics
python ~/.cursor/skills/meta-marketing-api/scripts/fetch_demographics.py \
  --account-id act_965535620739283 \
  --since 2026-03-01 --until 2026-03-31

# Page data + post links
python ~/.cursor/skills/meta-marketing-api/scripts/fetch_page_and_posts.py \
  --page-id 1737601956456316 \
  --account-id act_965535620739283 \
  --since 2026-03-01 --until 2026-03-31 --prefix 202603

# Instagram data
python ~/.cursor/skills/meta-marketing-api/scripts/fetch_ig_data.py \
  --ig-id 17841426960183702 \
  --page-id 1737601956456316 \
  --since 2026-03-01 --until 2026-03-31
```

All CSVs are saved to `output/` in the current directory.

## Finding Your IDs

```bash
# List ad accounts
curl "https://graph.facebook.com/v21.0/me/adaccounts?fields=id,name&access_token=$META_ACCESS_TOKEN"

# List pages with IG accounts
curl "https://graph.facebook.com/v21.0/me/accounts?fields=id,name,fan_count,instagram_business_account{id,username,followers_count}&access_token=$META_ACCESS_TOKEN"

# Check token permissions and restrictions
curl "https://graph.facebook.com/v21.0/debug_token?input_token=$META_ACCESS_TOKEN&access_token=$META_ACCESS_TOKEN"
```

## Dependencies

```bash
pip install requests truststore
```

`truststore` is optional — scripts will work without it (falls back to default SSL).

## Troubleshooting

See [setup-guide.md](setup-guide.md) for detailed troubleshooting of common errors including token expiration, permission issues, and asset access problems.
