# 🧠 Laravel Debug Monitor

A lightweight **Laravel package** that runs **SQL-based health checks** on your data, logs every result, and emails you when something goes wrong.

You write a query that finds problem data (for example, users without an email) and say how many rows you expect (usually `0`). Debug Monitor runs it on a schedule, records each run, and alerts you when the data stops matching.

---

## 📑 Contents

- [Features](#-features)
- [Requirements](#-requirements)
- [Quick Start](#-quick-start)
- [Access Control](#-access-control)
- [Creating a Rule](#-creating-a-rule)
- [Using the Web UI](#-using-the-web-ui)
- [Running Rules](#-running-rules)
- [Email Alerts](#-email-alerts)
- [Version-Controlled Rule Files](#-version-controlled-rule-files)
- [Log Cleanup](#-log-cleanup)
- [Query Safety & Reliability](#-query-safety--reliability)
- [Configuration Reference](#-configuration-reference)
- [Artisan Commands](#-artisan-commands)
- [Customizing](#-customizing)
- [Troubleshooting](#-troubleshooting)
- [Upgrading from 1.0.x](#-upgrading-from-10x)

---

## 🚀 Features

- 🔍 Create SQL **debug rules** from the web UI
- 🕒 Rules run **automatically on a schedule** (as queued jobs), or on demand
- ✉️ **Email alerts** once when a rule starts failing, and again when it **recovers**: no inbox flooding
- 📊 **Dashboard** of rules failing in the last 24 hours, plus full, paginated **run logs** per rule
- ✅ See each rule's **last result** (Passed / Failed) and filter the list to **failing rules**
- ☑️ **Bulk actions**: run, export, activate, deactivate, suppress, unsuppress or delete many rules at once
- 🔁 **One-click status toggle** (Active ↔ Inactive) from the rules list
- 📁 Ship **version-controlled rule files** and sync them into the database (migration-style)
- 🛡️ **Read-only, streamed queries** with timeouts, so a rule can never change your data
- 🧹 Automatic cleanup of old logs (configurable retention)
- 🔒 Open in `local` by default, email allowlist everywhere else
- ⚙️ Works with **SQLite** and **MySQL**

---

## 📋 Requirements

- PHP 8.0+
- A Laravel application (tested with Laravel 13)
- A **queue worker** (`php artisan queue:work`): scheduled rules run on the queue and send their alert emails from there. You can also run rules without a worker, see [Running Rules](#-running-rules).
- Mail configured in your app, for alerts

---

## ⚡ Quick Start

**1. Install the package**

```bash
composer require webmavens/debug-monitor
```

**2. Create the tables**

The package's migrations are loaded automatically, so you don't need to publish them:

```bash
php artisan migrate
```

**3. Set who receives alerts** (in `.env`; make sure your app's mail settings work)

```
DEBUG_MONITOR_NOTIFY_EMAIL=alerts@your-company.com
```

**4. Start the scheduler and a queue worker**

Locally, run each in its own terminal:

```bash
php artisan schedule:work
php artisan queue:work
```

On a server, add Laravel's usual cron entry and keep a queue worker running (for example with Supervisor):

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

**5. Open the dashboard**

Visit **`/debug-monitor/dashboard`** (or `/debug-monitor/rules`), click **New Rule**, then use **Run now** on it to check it works.

> In any environment other than `local`, you must also allow your login email. See [Access Control](#-access-control).

---

## 🔑 Access Control

By default, Debug Monitor is open to everyone in the `local` environment and closed everywhere else.

To allow access outside `local`, list the email addresses of users who may open it. They must be logged in to your app:

```
DEBUG_MONITOR_ALLOWED_EMAILS=admin@example.com,dev@example.com
```

To require the allowlist in `local` too:

```
DEBUG_MONITOR_ALLOW_IN_LOCAL=false
```

You don't need to publish or register anything for this.

---

## 📝 Creating a Rule

A rule is a query plus what you expect it to return. The easiest pattern: **write a query that finds bad data, and expect `= 0` rows.**

| Field | What it means |
| --- | --- |
| **Rule Name** | A label shown in the list, logs and alert emails. |
| **SQL Query** | A single read-only query (`SELECT`, `WITH`, `SHOW` or `EXPLAIN`). Anything that changes data is rejected. |
| **Expected Rows** | An operator (`=`, `>`, `<`, `>=`, `<=`) and a number. The rule **passes** when the query's row count matches, e.g. `= 0` means "this query should find nothing". |
| **Expected Data (JSON)** | Optional. A JSON object of column → value, e.g. `{"status": "active"}`. When the query returns rows, at least one row must match **all** of these values. Leave empty to skip. |
| **Frequency (minutes)** | How often the scheduler runs the rule. `15` = every 15 minutes. |
| **Importance Level** | `high`, `medium` or `low`, for filtering the list and shown in alert emails. |
| **Notification Level** | `none`: log only, never email. `default` / `urgent`: send email alerts. Both currently send the same email; use `urgent` to flag your most important rules. |
| **Status** | `active`: runs on schedule. `inactive`: paused, does not run on schedule. |

### Example rules

| Name | SQL Query | Frequency | Expected Rows |
| --- | --- | --- | --- |
| Users without email | `SELECT id FROM users WHERE email IS NULL` | 15 minutes | `= 0` |
| Stuck orders | `SELECT id FROM orders WHERE status = 'pending' AND created_at < NOW() - INTERVAL 2 HOUR` | 10 minutes | `= 0` |
| Admin account exists | `SELECT id FROM users WHERE role = 'admin'` | 60 minutes | `>= 1` |

> 💡 Select only the columns you need (`SELECT id ...`). Each run stores a sample of the result rows in its log.

### Suppress vs. Inactive

- **Suppress**: the rule **keeps running and logging**, but sends **no emails**. Use it while you're fixing a known issue.
- **Inactive**: the rule **does not run** on schedule at all.

---

## 💻 Using the Web UI

### Rules list (`/debug-monitor/rules`)

- **Search** by name (starts with) and **filter** by status (Active, Inactive, Suppressed, **Failing**) and importance. **Failing** shows active rules whose latest run failed.
- **Last Run** shows the latest result (**Passed** / **Failed**) and when it ran. Rules that never ran show **Never**.
- Click a rule's **Status** badge to switch it between Active and Inactive.
- Row actions: **Run now** (runs immediately, no email), **Show**, **Edit**, **Export** (to a rule file), **Delete**, and **Suppress / Unsuppress**.
- **Bulk actions**: tick the checkboxes (the header checkbox selects the whole page) and choose **Run**, **Export**, **Activate**, **Deactivate**, **Suppress**, **Unsuppress** or **Delete**. Bulk **Run** happens in the background on the queue. Selection covers the current page.
- After any action, edit or delete, you return to the **same page with the same filters**.

### Dashboard (`/debug-monitor/dashboard`)

- Totals for all, active, inactive and suppressed rules.
- **Recent Errors (Last 24 Hours)**: one row per failing rule, with how many times it failed and its latest error.

### Rule page

- The rule's settings and its last result.
- **Logs** of every run, newest first, 20 per page. Long results show a short preview; click **Show full** to load the complete result.
- Delete individual logs, or suppress the rule with a note.

### Sidebar

**Failing Rules** lists up to 8 active rules whose latest run failed, with a link to see them all. Deactivated rules are paused, so they don't appear here.

---

## 🕒 Running Rules

### On a schedule (default)

Every minute, the scheduler queues **one job per rule that is due** (active, and at least *Frequency* minutes since its last run). A slow query only delays its own rule, and a rule is never queued twice while its job is waiting or running.

This needs both the scheduler and a **queue worker** running (see [Quick Start](#-quick-start)).

**No queue worker?** Run due rules inside the scheduler process instead, one after another:

```
DEBUG_MONITOR_QUEUE_RUNS=false
```

### Manually

```bash
php artisan debug-monitor:run          # queue all due rules
php artisan debug-monitor:run --sync   # run all due rules now, in this process
```

From the UI, **Run now** runs one rule immediately and bulk **Run** queues the selected rules. Manual runs never send emails.

---

## 📧 Email Alerts

Alerts go to `DEBUG_MONITOR_NOTIFY_EMAIL` using your app's mail settings. Each scheduled run sends its own alert, so with the default setup the **queue worker** sends them.

Alerts are sent per **failing streak**, not for every failed run:

| What happened | Email |
| --- | --- |
| A rule **starts failing** (wrong row count or data) | One **"Debug Rule Failed: …"** email with the details |
| A rule's query **can't run** (e.g. a renamed table or column) | One **"Debug Rule Error: …"** email |
| The rule **keeps failing** | No further emails (every run is still logged) |
| The rule **passes again** | One **"Debug Rule Recovered: …"** email |

- Only **scheduled** runs send alerts. **Run now** and bulk **Run** are silent and don't affect alerts.
- **Suppressed** rules and rules with notification level **`none`** never send email. If you unsuppress a rule that is still failing, its next scheduled run sends an alert.
- If an email can't be sent, it is retried on the rule's next run.

---

## 📁 Version-Controlled Rule Files

Rules can also live as **JSON files in your repository** and be synced into the database, like migrations. Rules always **run from the database**; files are never executed directly.

Create the rules directory (`debug-monitor-rules/`, with an example file):

```bash
php artisan vendor:publish --provider="Webmavens\DebugMonitor\DebugMonitorServiceProvider" --tag=debug-monitor-rules
```

A rule file, e.g. `debug-monitor-rules/missing-user-emails.json`:

```json
{
    "name": "Users missing email",
    "sql_query": "SELECT * FROM users WHERE email IS NULL",
    "frequency_minutes": 15,
    "importance_level": "high",
    "expected_rows_operator": "=",
    "expected_rows": 0,
    "expected_json": null,
    "notification_level": "default",
    "status": "active"
}
```

You can write files by hand, or build a rule in the UI and **Export** it.

Import new and changed files with the **Sync from files** button on the rules list (it shows how many files are pending), or with Artisan, which suits deploy pipelines:

```bash
php artisan debug-monitor:sync      # apply new + changed files
php artisan debug-monitor:export    # export DB rules to files (--id=1 --id=2 for specific rules)
```

How files and database rules stay in step:

- **Sync** adds new files and **updates rules whose file changed** (detected by checksum).
- If a database rule with the same name already exists and isn't linked to the file, the file is **skipped**: the database wins.
- **Editing** a linked rule in the UI (including its status) **rewrites its file**.
- **Exporting** a UI-created rule turns it into a linked file. Rules already linked to a file are marked **file** in the list.
- **Deleting** a linked rule removes its file too.

---

## 🧹 Log Cleanup

Logs older than the retention period are deleted automatically every day. To change the retention period (default 30 days):

```
DEBUG_MONITOR_LOG_RETENTION_DAYS=30
```

Run it manually:

```bash
php artisan debug-monitor:clean            # use the configured retention
php artisan debug-monitor:clean --days=7   # keep only the last 7 days
```

---

## 🔐 Query Safety & Reliability

Rules run against your real database, so execution is guarded:

- **Read-only only**: a rule must be a single `SELECT` / `WITH` / `SHOW` / `EXPLAIN` statement. Anything that could change data, or a second stacked statement, is rejected.
- **Streamed, not buffered**: results are streamed and counted, so a query returning millions of rows won't exhaust memory. Only a sample (`max_rows`) is kept for logs and JSON matching; the row count is always exact.
- **Per-query timeout**: a runaway query is stopped instead of hanging the run (MySQL/MariaDB and PostgreSQL).
- **No pile-ups**: the schedule never overlaps itself, and each rule has at most one scheduled job queued or running.

---

## 🔧 Configuration Reference

Set these in `.env`. To change them in code instead, [publish the config file](#-customizing).

| Variable | Default | What it does |
| --- | --- | --- |
| `DEBUG_MONITOR_NOTIFY_EMAIL` | `admin@example.com` | Who receives alert emails. |
| `DEBUG_MONITOR_ALLOWED_EMAILS` | *(empty)* | Comma-separated emails of logged-in users allowed in outside `local`. |
| `DEBUG_MONITOR_ALLOW_IN_LOCAL` | `true` | Open to everyone in the `local` environment. |
| `DEBUG_MONITOR_QUEUE_RUNS` | `true` | Run scheduled rules as queued jobs. `false` = run them inside the scheduler. |
| `DEBUG_MONITOR_LOG_RETENTION_DAYS` | `30` | Days of logs to keep. |
| `DEBUG_MONITOR_READ_ONLY` | `true` | Only allow single, read-only queries. |
| `DEBUG_MONITOR_QUERY_TIMEOUT` | `30` | Per-query timeout in seconds (`0` = no limit). |
| `DEBUG_MONITOR_MAX_ROWS` | `1000` | Max result rows kept per run (for logs and JSON matching). |
| `DEBUG_MONITOR_RULES_PATH` | `base_path('debug-monitor-rules')` | Folder for version-controlled rule files. |

---

## 🧰 Artisan Commands

| Command | What it does |
| --- | --- |
| `php artisan debug-monitor:run` | Queue all active rules that are due (runs every minute via the scheduler). |
| `php artisan debug-monitor:run --sync` | Run all due rules now, one after another, without the queue. |
| `php artisan debug-monitor:sync` | Import new and changed rule files into the database. |
| `php artisan debug-monitor:export` | Export database rules to rule files (`--id=1 --id=2` for specific rules). |
| `php artisan debug-monitor:clean` | Delete old logs (runs daily via the scheduler; `--days=7` to override). |

---

## 🎨 Customizing

All of these are optional.

```bash
# Config file (config/debug-monitor.php)
php artisan vendor:publish --provider="Webmavens\DebugMonitor\DebugMonitorServiceProvider" --tag=config

# Views, to change the UI (resources/views/vendor/debug-monitor)
php artisan vendor:publish --provider="Webmavens\DebugMonitor\DebugMonitorServiceProvider" --tag=views

# Migrations, if you want to change them
php artisan vendor:publish --provider="Webmavens\DebugMonitor\DebugMonitorServiceProvider" --tag=migrations
```

> ⚠️ Published views are not updated when you update the package. Re-publish them (or remove your copies) after upgrading to get new UI features.

---

## 🩺 Troubleshooting

**Scheduled rules never run (Last Run stays "Never")**
- Is the scheduler running? (`php artisan schedule:work` locally, or the cron entry on a server)
- Is a queue worker running? (`php artisan queue:work`) If you don't want one, set `DEBUG_MONITOR_QUEUE_RUNS=false`.
- Is the rule **Active**? Inactive rules don't run on schedule.

**No alert emails**
- Alerts are sent **once per failing streak**. A rule that was already failing won't email again until it recovers and fails again.
- **Run now** and bulk **Run** never send email; wait for a scheduled run.
- Check the rule isn't **suppressed** and its notification level isn't **`none`**.
- Check `DEBUG_MONITOR_NOTIFY_EMAIL`, your app's mail settings, and that a queue worker is running.

**403 Forbidden on `/debug-monitor/...`**
- Outside `local`, log in with an email listed in `DEBUG_MONITOR_ALLOWED_EMAILS`.

**A rule always fails with "Only read-only queries … are allowed"**
- The query must be a single `SELECT` / `WITH` / `SHOW` / `EXPLAIN` without a trailing `;`.

**New UI features don't show after an update**
- You probably published the views. Re-publish them with `--tag=views --force`, or delete `resources/views/vendor/debug-monitor`.

---

## 🔄 Upgrading from 1.0.x

This release adds columns and indexes to the package's **existing** migrations instead of adding new ones. If you installed 1.0.x and already ran its migrations, recreate the package's tables:

```bash
php artisan migrate:refresh --path=vendor/webmavens/debug-monitor/database/migrations
```

> ⚠️ This **deletes all Debug Monitor rules and logs** (your other tables are untouched). Export any rules you want to keep first (`php artisan debug-monitor:export`), then bring them back with `php artisan debug-monitor:sync`.

Also make sure a queue worker is running (or set `DEBUG_MONITOR_QUEUE_RUNS=false`), since scheduled rules now run on the queue.

---

## 💬 Support

For any issues, please open an issue in the [GitHub repository](https://github.com/webmavens/laravel-debug).

## 🤝 Contributing

Pull requests are welcome! Fork the repo and open a PR.

## 🧠 License

This package is open-source software licensed under the MIT license.
