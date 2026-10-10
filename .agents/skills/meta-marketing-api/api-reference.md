# Meta Marketing API — Endpoint Reference

Base URL: `https://graph.facebook.com/v21.0`

## Ad Account Endpoints

### List Ad Accounts
```
GET /me/adaccounts
  ?fields=id,name,account_status,business_name,currency,timezone_name
  &limit=50
```
Token: User

### Ad Insights (Campaign / Ad Set / Ad level)
```
GET /{ad-account-id}/insights
  ?fields=campaign_name,campaign_id,adset_name,adset_id,ad_name,ad_id,
          impressions,reach,clicks,spend,ctr,cpc,cpm,cpp,frequency,
          actions,cost_per_action_type,date_start,date_stop
  &level=campaign          # or: adset, ad
  &time_range={"since":"2026-03-01","until":"2026-03-31"}
  &limit=500
```
Token: User
Alternative: use `&date_preset=last_30d` instead of `time_range`

Date presets: `today`, `yesterday`, `last_7d`, `last_14d`, `last_28d`, `last_30d`, `last_90d`, `last_month`, `this_month`, `this_quarter`, `last_quarter`

### Audience Breakdowns
```
GET /{ad-account-id}/insights
  ?fields=impressions,reach,clicks,spend,ctr,cpc,cpm
  &breakdowns=age,gender        # age x gender matrix
  &level=account
  &time_range={"since":"...","until":"..."}
```
Token: User

Available breakdowns:
- `age,gender` — age x gender matrix
- `publisher_platform` — Facebook vs Instagram vs Audience Network
- `platform_position` — Feed, Stories, Reels, etc.
- `country` — by country code
- `device_platform` — mobile, desktop, etc.

Can combine: `publisher_platform,platform_position`

### Campaign List
```
GET /{ad-account-id}/campaigns
  ?fields=name,status,objective,insights.date_preset(last_30d){impressions,reach,clicks,spend}
  &filtering=[{"field":"effective_status","operator":"IN","value":["ACTIVE","PAUSED"]}]
  &limit=100
```
Token: User

### Ad Creatives & Post Links
```
GET /{ad-account-id}/ads
  ?fields=name,status,campaign_name,adset_name,
          creative{effective_object_story_id,thumbnail_url},
          preview_shareable_link,
          insights.date_preset(last_30d){impressions,reach,clicks,spend,ctr,actions}
  &filtering=[{"field":"effective_status","operator":"IN","value":["ACTIVE","PAUSED"]}]
  &limit=200
```
Token: User

`effective_object_story_id` format: `{page_id}_{post_id}`
Convert to URL: `https://www.facebook.com/{page_id}/posts/{post_id}`

## Facebook Page Endpoints

### List Pages (with tokens)
```
GET /me/accounts
  ?fields=id,name,fan_count,access_token,
          instagram_business_account{id,username,followers_count}
  &limit=50
```
Token: User
Returns Page Access Tokens for each page in `access_token` field.

### Page Fan Count
```
GET /{page-id}
  ?fields=name,fan_count
```
Token: User or Page

### Published Posts
```
GET /{page-id}/feed
  ?fields=message,created_time,permalink_url,type,shares
  &since=2026-03-01
  &until=2026-04-01
  &limit=100
```
Token: **Page** (get from `/me/accounts`)

## Instagram Endpoints

### IG Profile Info
```
GET /{ig-user-id}
  ?fields=id,username,name,followers_count,follows_count,
          media_count,biography,profile_picture_url,website
```
Token: Page (for managed accounts)
Permission: `instagram_basic`

### IG Audience Demographics
```
GET /{ig-user-id}/insights
  ?metric=follower_demographics
  &period=lifetime
  &metric_type=total_value
  &breakdown=age,gender       # or: city, country
```
Token: Page
Permission: `instagram_manage_insights`

Response structure:
```json
{
  "data": [{
    "total_value": {
      "breakdowns": [{
        "results": [
          {"dimension_values": ["F", "25-34"], "value": 1651},
          {"dimension_values": ["M", "25-34"], "value": 222}
        ]
      }]
    }
  }]
}
```
Note: dimension_values order is `[gender_letter, age_range]` where F=Female, M=Male, U=Unknown.

### IG Follower Count (Daily Change)
```
GET /{ig-user-id}/insights
  ?metric=follower_count
  &period=day
  &since=2026-03-16
  &until=2026-04-15
```
Token: Page
Limitation: max 30-day window, only last 30 days of data available.

### IG Media (Posts)
```
GET /{ig-user-id}/media
  ?fields=id,caption,media_type,media_url,thumbnail_url,
          timestamp,permalink,like_count,comments_count
  &limit=50
```
Token: Page

### IG Post-Level Insights
```
GET /{media-id}/insights
  ?metric=impressions,reach,saved,shares,total_interactions
```
Token: Page
Note: Some metrics may not be available for all media types (Reels use different metrics).

### Business Discovery (Competitor Lookup)
```
GET /{your-ig-id}
  ?fields=business_discovery.fields(
      username,name,followers_count,media_count,biography,
      media.limit(10){id,caption,like_count,comments_count,timestamp,permalink,media_type}
    )
  &username={target_username}
```
Token: User
Permission: `instagram_basic` (must be **unrestricted** — no `target_ids` in granular scopes)
Requirement: App must be in Live mode with Instagram Graph API product added

Only works for public Business/Creator IG accounts. Returns public metrics (followers, likes, comments) but NOT private metrics (impressions, reach, saves).

## Token Endpoints

### Debug Token
```
GET /debug_token
  ?input_token={token_to_check}
  &access_token={token_to_check}
```
Returns: scopes, expiry, app_id, granular_scopes (including target_ids restrictions)

### Exchange for Long-Lived Token
```
GET /oauth/access_token
  ?grant_type=fb_exchange_token
  &client_id={APP_ID}
  &client_secret={APP_SECRET}
  &fb_exchange_token={SHORT_LIVED_TOKEN}
```
Converts ~1h token to ~60 day token.

### List Businesses
```
GET /me/businesses
  ?fields=id,name
```
Token: User
