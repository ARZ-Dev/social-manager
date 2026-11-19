# Social Media (Facebook, Instagram, Boosts) — setup

> Google Ads, BSB (SMS & WhatsApp) and the Messages tab's response times are described in
> [google-ads-and-bsb-setup.md](google-ads-and-bsb-setup.md).

Each company connects through **its own Meta app**. The App ID and App Secret are
entered per company in **Companies → Edit → Meta app**. The same instructions are
shown in the form under "How do I get the App ID and App Secret?".

## 1. Create the company's Meta app

1. Go to <https://developers.facebook.com/apps> and log in with the Facebook account
   that manages the company's Page (register as a developer if asked, it's free).
2. **Create app** → use case **Other** → app type **Business**. Name it and pick the
   company's Business portfolio if it has one.
3. Add products: **Facebook Login for Business**, **Instagram** (API setup with
   Facebook login), **Messenger**, **Marketing API**.
4. **Facebook Login for Business → Settings → Valid OAuth Redirect URIs:** add
   `{APP_URL}/social/callback` (the company form shows the exact URL with a Copy
   button). Meta requires HTTPS except for `localhost`.
5. **App settings → Basic:** copy the **App ID** and the **App Secret** (click *Show*)
   and paste them into the company form. Also fill in Privacy Policy URL, icon and
   category. Meta needs these before going live.
6. **Development mode:** only people listed under **App roles → Roles** can connect.
   Add whoever will click *Connect*.
7. **Going live:** request these permissions in **App Review**, complete **Business
   verification**, then switch the app to **Live**:
   `business_management, pages_show_list, pages_read_engagement, pages_read_user_content, read_insights, pages_messaging, instagram_basic, instagram_manage_insights, instagram_manage_comments, instagram_manage_messages, ads_read`

The secret is stored encrypted and never shown again. Leave the field blank when
editing to keep it, and clear the App ID to remove both.

## 2. Optional `.env` settings (apply to all companies)

```
META_GRAPH_VERSION=v23.0
META_HISTORY_DAYS=90
```

## 3. Permissions in the app

Assign these from **Roles** to any role:

| Permission          | Allows                                   |
|---------------------|------------------------------------------|
| `social-list`       | See the Social Media tab and its data     |
| `social-connect`    | Link Facebook Pages / Instagram / ad accounts |
| `social-disconnect` | Unlink an account (deletes its fetched data) |
| `social-sync`       | Use the **Fetch data** button             |

Editing a company's Meta app needs `company-edit`.

## 4. How it works

- Super Admins choose the company at the top of **Social Media**. Other users always see their own company.
- **Connect** → Facebook login (using the company's app) → choose Pages (their Instagram business account is linked automatically) and ad accounts.
- **Fetch data** pulls the last 90 days of posts, comments, insights, Messenger / Instagram conversations and boosts.
- It also pulls a snapshot of each account's **audience** (shown in the **Audience** tab, replaced on every fetch):
  - **Facebook Pages:** followers by country and city. Meta removed age and gender for Pages in 2024.
  - **Instagram:** followers and the engaged audience (people who interacted this month) by age, gender, country and city.
    Meta only returns these for accounts with at least 100 followers (or 100 engagements), and lists at most 45 countries / cities.
- For ad accounts it also splits each ad's spend, impressions, reach and clicks by **age & gender, country, placement and device**
  (the **Breakdown** card in the **Boosts** tab; click the chart icon on an ad to see that ad alone).
  The breakdowns and audience settings are in `config/meta.php` (`demographics`, `ad_breakdowns`).
- If Meta rejects something, the fetch continues and the account shows **"Last fetch had warnings"**.

## 5. Token lifetimes

- Page / Instagram access doesn't expire unless the person changes their password, removes the app, or loses access to the Page.
- **Ad account access expires after ~60 days.** Click **Connect** again to renew it.
- **Changing a company's App ID/Secret** invalidates existing connections. Click **Connect** again afterwards.
