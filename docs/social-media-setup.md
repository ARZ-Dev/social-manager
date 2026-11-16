# Social Media (Facebook, Instagram, Boosts) — setup

## 1. Meta app settings (developers.facebook.com → your app)

1. **Use cases / products:** add *Facebook Login for Business*, *Instagram* (Graph API, via Facebook Login), *Messenger*, and *Marketing API*.
2. **Facebook Login → Settings → Valid OAuth Redirect URIs:** add
   `{APP_URL}/social/callback` (e.g. `https://yourdomain.com/social/callback`).
   Meta requires HTTPS for anything except `localhost`.
3. **Permissions** the app asks for (all read-only, listed in `config/meta.php`):
   `business_management, pages_show_list, pages_read_engagement, pages_read_user_content, read_insights, pages_messaging, instagram_basic, instagram_manage_insights, instagram_manage_comments, instagram_manage_messages, ads_read`.
   While the app is in **Development** mode only people with a role on the app (admins, developers, testers) can connect. To let any company connect, submit these permissions for **App Review** and switch the app to **Live**.

## 2. `.env`

```
META_APP_ID=your-app-id
META_APP_SECRET=your-app-secret
META_GRAPH_VERSION=v23.0
META_HISTORY_DAYS=90
```

Then run `php artisan config:clear`.

## 3. Permissions in the app

Assign these from **Roles** to any role:

| Permission          | Allows                                   |
|---------------------|------------------------------------------|
| `social-list`       | See the Social Media tab and its data     |
| `social-connect`    | Link Facebook Pages / Instagram / ad accounts |
| `social-disconnect` | Unlink an account (deletes its fetched data) |
| `social-sync`       | Use the **Fetch data** button             |

## 4. How it works

- Each company links its own accounts. Super Admins choose the company with the selector at the top; other users always see their own company.
- **Connect** → Facebook login → choose Pages (their Instagram business account is linked automatically) and ad accounts.
- **Fetch data** pulls the last 90 days of posts, comments, post and account insights, Messenger / Instagram conversations, and boosts (ads), and stores them. It runs while you wait.
- If Meta rejects something (missing permission, deprecated metric), the fetch continues and the account shows **"Last fetch had warnings"** with details. Metric names can be adjusted in `config/meta.php`.

## 5. Token lifetimes

- Page / Instagram access does not expire unless the person changes their password, removes the app, or loses access to the Page.
- **Ad account access expires after ~60 days.** The accounts list shows the expiry; click **Connect** again to renew it.
