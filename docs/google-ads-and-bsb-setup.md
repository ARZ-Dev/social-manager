# Google Ads and BSB (SMS & WhatsApp): setup

Both appear on **Social Media** next to the Meta accounts: **Connect more → Google Ads**
or **Connect more → BSB (SMS & WhatsApp)**. They use the same permissions as Meta
(`social-connect`, `social-sync`, `social-disconnect`, `social-list`).

## Google Ads

Each company connects through **its own** Google Cloud OAuth client and Google Ads
developer token, entered in **Companies → Edit → Google Ads** (the form has the same
steps under "How do I get the OAuth client and developer token?").

1. **Developer token**: in a Google Ads *manager* account, **Admin → API Center**. A new
   token only works with test accounts: click **Apply for Basic access** to read real
   accounts (Google reviews it in a few days).
2. **Google Cloud project** at <https://console.cloud.google.com/>: create one and enable
   the **Google Ads API**.
3. **Consent screen** (Google Auth Platform → Branding / Audience): set the publishing
   status to **In production**. While it says *Testing*, Google drops the connection
   after 7 days.
4. **OAuth client**: type **Web application**, add `{APP_URL}/social/google/callback`
   under **Authorized redirect URIs** (the company form shows it with a Copy button),
   then paste the client ID and secret into the company form.
5. On Social Media, **Connect more → Google Ads**, sign in with a Google account that can
   open the company's Google Ads accounts (or their manager account), and pick the accounts.
   Client accounts of a manager account are listed under it.

**What a fetch reads** (Ads tab → Google Ads), for the period picked in **Fetch data**:

| Section | Metrics |
|---|---|
| Performance | Cost, impressions, clicks, CTR, average cost per click |
| Conversions | Conversions, conversion rate, cost per conversion, conversion value, return on spend |
| Engagement | Interactions and interaction rate, engagements and engagement rate, video views |
| Per day | Cost, clicks, impressions, conversions (chart) |
| Audience | Results by age, gender, country, device and network, for all campaigns or one |
| Campaigns | Each campaign's status, type and results |

- Age and gender come from Search, Display and Video campaigns; Performance Max
  campaigns don't report them.
- The API version is `GOOGLE_ADS_API_VERSION` (default `v26`). Google retires a version
  about a year after release: raise it then.
- Changing a company's client ID or secret invalidates its connections: connect again.

## BSB (bestsmsbulk.com)

**Connect more → BSB (SMS & WhatsApp)** asks for the API key and secret from BSB's
**MANAGE APIs** page. They are checked by reading the balance, stored encrypted, and
today's messages are fetched straight away.

### What BSB's API can and can't give

BSB's API ([documentation](https://www.bestsmsbulk.com/pro-support/api_documentation_bsb))
reports, for **today's messages only**, each message's ID, recipient and delivery status
(`DELIVERED`, `SENT`, `PENDING`, `FAILED`), plus the SMS and WhatsApp balance. It has no
message history, message text, sender ID or replies.

So the app keeps its own log:

- `php artisan bsb:poll` checks every BSB account: balance, today's messages on both
  routes, and re-checks messages of the last 7 days that aren't delivered or failed yet.
- It is scheduled every 10 minutes (`routes/console.php`, `BSB_POLL_MINUTES`).
  **The scheduler must run**, or messages of days without a check are missed:
  - Linux: cron `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`
  - Windows: a Task Scheduler task every minute running `php artisan schedule:run` in the app folder
  - While developing: `php artisan schedule:work`
- The SMS & WhatsApp tab warns when no account was checked for 30 minutes.

**SMS & WhatsApp tab**: balance per account; the delivery funnel (sent → delivered /
on the way / failed) with the delivery rate; the same per channel (SMS, WhatsApp); messages
per day by status; recipients by country (from the number's calling code); and every
message, searchable by number and filterable by channel and status.

"First seen" is when a check first saw a message: at most one check interval after it was
sent while checks run.

## Response times (Messages tab)

For Facebook Messenger and Instagram Direct conversations, the Messages tab now shows,
for the last 7 / 30 / 90 days: median first response, average response time, reply rate,
share answered within an hour, a distribution, one row per account, and which
conversations are still waiting for a reply (with an **Awaiting reply** filter).

A response runs from a customer's first unanswered message to the next message from the
Page / account. It uses the latest 50 messages fetched per conversation. BSB conversations
aren't included: BSB's API doesn't report replies.
