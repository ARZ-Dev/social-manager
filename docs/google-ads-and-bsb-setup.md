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
(`DELIVERED`, `SENT`, `PENDING`, `FAILED`, and `READ` for WhatsApp, though BSB doesn't
document it), plus the SMS and WhatsApp balance. It has no message history, text or sender ID.
Customers' WhatsApp messages come through a separate webhook: see **WhatsApp chats** below.

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

### WhatsApp chats (BSB webhook)

BSB's **WhatsApp Messaging API** (in BSB: Developers → WhatsApp Messaging API) posts every
WhatsApp message a customer sends, and every status change of the messages you send, to a URL
of ours. The chats then appear in the **Messages** tab next to Messenger and Instagram, and
count in the response times.

**Setup**, per BSB account (needs `social-connect`): SMS & WhatsApp tab → the account's card →
**WhatsApp chats: Set up**. The dialog shows the account's webhook URL
(`{APP_URL}/api/webhooks/bsb/{key}`; each account has its own, because BSB's payload doesn't
say which account it's for). Then, in BSB:

1. Paste the URL as **Webhook URL** (it must be the live site's public HTTPS address).
2. Tick **Enable webhook delivery**, **Notify on incoming messages** and **Notify on message
   status changes**, and save.
3. Copy the **Webhook signing secret** BSB generates into the dialog and save. From then on
   only deliveries with a valid `X-BSB-Signature: sha256=<HMAC-SHA256 of the body>` are
   accepted (401 otherwise). Before a secret is saved, the unguessable URL is the only check.
   After **Rotate secret** in BSB, paste the new one.
4. **Send test** in BSB: the dialog's "Last delivery" updates.

**What is stored**

- `message.received`: the customer's message, keyed on its WhatsApp ID (repeated deliveries
  are stored once), in a conversation per number (`social_conversations.external_id` =
  `wa:<number>`). Photos, voice notes, videos and documents are downloaded right after the
  webhook answers (BSB's link expires after 7 days) to `storage/app/bsb-media/<account id>/`,
  up to `BSB_MEDIA_MAX_MB` (16 MB) each, and served only to people who can see the company.
  Only links on `bestsmsbulk.com` are downloaded.
- `message.status`: the status of an outgoing message (sent → delivered → read, or failed with
  BSB's reason). Statuses never go backwards. **BSB doesn't include the text** of messages
  you send, so they show as "Message sent from BSB (text not shared)". Messages to numbers
  that never wrote to you (order confirmations, campaigns) get a chat too, named by the number
  until the customer writes. One-way chats aren't part of the response times. A big campaign
  adds one chat per number.
- Reactions (👍 on a message) are kept as just the emoji. They don't start a wait in the
  response times: a reaction doesn't ask for an answer.
- There is no history: chats start from the moment the webhook is on. BSB has no API to read
  older chats, and **Fetch data** doesn't touch chats (it only reads today's delivery statuses).

**Turning chats off**: the switch next to **Settings** on the account's card (needs
`social-connect`). While off, the webhook answers BSB with `{"ok":true,"result":"disabled"}`
and stores nothing, and the account's chats, their files and their response times are hidden.
Nothing is deleted: turning it back on shows the chats again, but what BSB sent while it was off
is lost. To stop BSB sending at all, also untick **Enable webhook delivery** in BSB.

The Messages tab is read-only; reply from BSB's Unified Inbox. Disconnecting the BSB account
deletes its chats and their files.

## Response times (Messages tab)

For Facebook Messenger, Instagram Direct and BSB WhatsApp conversations, the Messages tab shows,
for the last 7 / 30 / 90 days: median first response, average response time, reply rate,
share answered within an hour, a distribution, one row per account, and which
conversations are still waiting for a reply (with an **Awaiting reply** filter).

A response runs from a customer's first unanswered message to the next message from the
Page / account. Meta conversations use the latest 50 messages fetched per conversation;
WhatsApp chats use every message received by the webhook. A WhatsApp reply that failed
doesn't count as an answer.
