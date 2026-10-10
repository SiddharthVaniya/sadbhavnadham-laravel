# Meta Marketing API — Detailed Setup Guide

## 1. Create a Meta Developer App

1. Go to [developers.facebook.com](https://developers.facebook.com/) and log in
2. Click **"My Apps"** → **"Create App"**
3. Choose **"Other"** as the app type, then **"Business"**
4. Give it a name (e.g. "API Data Exporter") and select your Business Portfolio
5. Click **Create App**

## 2. Add Instagram Graph API Product

In your app dashboard:

1. Look at the left sidebar for **"Use cases"**
2. Find the **Instagram** use case → click **"Add"** or **"Customize"**
3. Inside, ensure these permissions are listed/enabled:
   - `instagram_basic` (or `instagram_business_basic`)
   - `instagram_manage_insights` (or `instagram_business_manage_insights`)
4. If not listed, click **"Add permissions"** to add them

## 3. Set a Privacy Policy URL & Go Live

The app must be in **Live** mode for full API access.

1. Go to **Settings → Basic**
2. Fill in **Privacy Policy URL** — options:
   - Use your company's existing privacy policy page
   - Generate a free one at [privacypolicies.com](https://www.privacypolicies.com/)
   - Host a minimal page on Google Sites / GitHub Pages stating:
     > "This app is used internally for accessing Meta Marketing API data on behalf of managed client accounts. No personal user data is collected, stored, or shared with third parties."
3. **Save Changes**
4. Toggle the app from **Development → Live** at the top of the dashboard

## 4. Required Permissions

### For Ad Account Data
| Permission | What it enables |
|-----------|----------------|
| `ads_read` | Read ad insights (impressions, reach, spend, etc.) |
| `ads_management` | Access campaign/adset/ad structure |
| `business_management` | List ad accounts and business assets |

### For Facebook Page Data
| Permission | What it enables |
|-----------|----------------|
| `pages_show_list` | List pages you have access to |
| `pages_read_engagement` | Read page fan count, post engagement |

### For Instagram Data
| Permission | What it enables |
|-----------|----------------|
| `instagram_basic` | Read IG profile (username, followers, bio) |
| `instagram_manage_insights` | Read IG audience demographics (age/gender/city), follower trends |

### Summary: All Permissions to Select
```
ads_read
ads_management
business_management
pages_show_list
pages_read_engagement
instagram_basic
instagram_manage_insights
```

## 5. Generate an Access Token

1. Go to [Graph API Explorer](https://developers.facebook.com/tools/explorer)
2. **Top-left dropdown**: select **your app** (not "Graph API Explorer")
3. Click the **"Generate Access Token"** button
4. Check all the permissions listed above
5. When the Facebook authorization dialog appears:
   - Grant access to **all pages** listed
   - Grant access to **all Instagram accounts** listed (do NOT select just one — this restricts the token's scope)
6. Copy the generated token

### Set the token as an environment variable

```bash
export META_ACCESS_TOKEN="EAAxxxxxxx..."
```

### Token Expiration

| Token Type | Duration | How to Get |
|-----------|----------|------------|
| Short-lived | ~1-2 hours | Graph API Explorer "Generate Token" |
| Long-lived | ~60 days | Exchange via API (see below) |
| Page token (from long-lived) | Never expires | Derived from long-lived user token |

### Exchange for Long-Lived Token (60 days)

```bash
curl "https://graph.facebook.com/v21.0/oauth/access_token\
?grant_type=fb_exchange_token\
&client_id={YOUR_APP_ID}\
&client_secret={YOUR_APP_SECRET}\
&fb_exchange_token={SHORT_LIVED_TOKEN}"
```

Find your App ID and App Secret at: **Settings → Basic** in the app dashboard.

## 6. Client Asset Access

### Ad Accounts
If you're an agency, clients share ad accounts via Business Portfolio:
- Client goes to **Business Settings → Ad accounts → Partners → Add Partner**
- They enter your Business ID

### Instagram Accounts
For IG data (followers, audience demographics), the IG account must be shared as a separate asset:
- Client goes to **Business Settings → Instagram accounts → select account → Partners → Add Partner**
- They enter your Business ID

Without this, you can only access IG data for accounts directly linked to pages you manage.

### Verifying Access

```bash
# List all accessible ad accounts
curl "https://graph.facebook.com/v21.0/me/adaccounts?fields=id,name,account_status&access_token=$META_ACCESS_TOKEN"

# List all pages with IG accounts
curl "https://graph.facebook.com/v21.0/me/accounts?fields=id,name,fan_count,instagram_business_account{id,username,followers_count}&access_token=$META_ACCESS_TOKEN"

# Check token permissions
curl "https://graph.facebook.com/v21.0/debug_token?input_token=$META_ACCESS_TOKEN&access_token=$META_ACCESS_TOKEN"
```

## 7. Troubleshooting

| Error | Cause | Fix |
|-------|-------|-----|
| "Session has expired" (code 190) | Token expired | Regenerate at Graph API Explorer |
| "The parameter username is required" | `instagram_basic` scoped to specific accounts | Regenerate token, select ALL IG accounts in auth dialog |
| "This method must be called with a Page Access Token" | Using user token on page-level endpoint | Get page token via `/me/accounts` (scripts handle this automatically) |
| "The value must be a valid insights metric" | Deprecated metric (e.g. `page_fans_gender_age`) | Use ad-level demographics instead; screenshot organic from Business Suite |
| "Application does not have permission" | App not in Live mode, or missing product | Go Live + add Instagram Graph API product |
| No IG data for client account | IG account not shared as asset | Ask client to share IG account with your Business ID |

## 8. Granular Scope Restriction (Common Gotcha)

When generating a token, Meta's Facebook Login for Business dialog asks which specific assets to authorize. If you only select one IG account, the token's `instagram_basic` permission gets **restricted** to that single account:

```json
{
  "scope": "instagram_basic",
  "target_ids": ["17841426960183702"]  // restricted to one account!
}
```

This prevents Business Discovery (looking up competitor IG accounts) and limits IG access. To fix:
- Regenerate the token
- In the auth dialog, select **ALL** Instagram accounts (or look for "opt in to all")
- Verify with `debug_token` that `instagram_basic` has no `target_ids` restriction
