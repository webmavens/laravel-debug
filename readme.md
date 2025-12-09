# 🧠 Laravel Debug Monitor

A lightweight **Laravel package** that helps developers and administrators automatically **run SQL-based health checks**, detect data anomalies, and get notified when something goes wrong.

---

## 🚀 Features

- 🔍 Define SQL-based **debug rules** directly from the web UI  
- 🕒 Run rules automatically via scheduler or manually using an Artisan command  
- 📊 Store and view detailed **execution logs**
- 🧹 Automatically clean old logs (configurable retention)
- ✉️ Send email notifications for failed rules  
- ⚙️ Supports **SQLite** and **MySQL**  
- 🔒 Secure access using a secret key
- 🧱 Easy to extend and customize  

---

## 📦 Installation

Require the package via Composer:

```bash
composer require webmavens/debug-monitor
```

## ⚙️ Configuration

Publish the configuration and view files:

```bash
php artisan vendor:publish --provider="Webmavens\DebugMonitor\DebugMonitorServiceProvider" --tag=config

php artisan vendor:publish --provider="Webmavens\DebugMonitor\DebugMonitorServiceProvider" --tag=views
```

#### Run the migrations:

```bash
php artisan migrate
```

## 🔑 Authentication

Access to the Debug Monitor dashboard is restricted for safety.

**1. Local Environment** : 
In local environment, access is open by default.

**2. Production / Staging** :
In `.env`, set a secure access key:

`DEBUG_MONITOR_AUTH_KEY=your-secret-monitor-key`

You can then visit:

`https://your-app.com/debug-monitor/rules?key=your-secret-monitor-key`

## 🧭 Usage
### 🖥️ Web Dashboard

Visit `/debug-monitor/rules` to:

- View all rules
- Create new rules
- Edit or delete rules
- Suppress temporarily
- Review logs

### ⚡ Run Scheduler
```
php artisan schedule:work
```

### ⚡ Run Manually

Run all active rules manually via Artisan:
```
php artisan debug-monitor:run
```

### 🧹 Log Cleanup (Automatic Maintenance)
Old logs can be automatically deleted using the built-in cleanup command.

#### Run Manually:
```
php artisan debug-monitor:clean
or
php artisan debug-monitor:clean --days=7

```
#### Configure Retention Period:
In config/debug-monitor.php:
```
'log_retention_days' => env('DEBUG_MONITOR_LOG_RETENTION_DAYS', 30),`
```

## 🧰 Example Debug Rule

You can define rules such as:

| Name          | SQL Query                                                                              | Frequency  | Expected Rows |
| ------------- | -------------------------------------------------------------------------------------- | ---------- | ------------- |
| Missing Users | `SELECT * FROM users WHERE email IS NULL`                                              | 15 minutes | 0             |
| Stuck Orders  | `SELECT * FROM orders WHERE status='pending' AND created_at < NOW() - INTERVAL 2 HOUR` | 10 minutes | 0             |


When a rule fails (unexpected result), the system:

- Logs it in the debug_rule_logs table
- Updates its last run time
- Sends an alert (if notifications are enabled)

### 📬 Notifications
You can configure the email notification in your config/debug-monitor.php file:

```php
'notify_email' => env('DEBUG_MONITOR_NOTIFY_EMAIL', 'admin@example.com'),
```

Failed rules will trigger an email with detailed information.

## Support

For any issues, feel free to create an issue in the [GitHub repository](https://github.com/webmavens/laravel-debug).

## 🤝 Contributing

Pull requests are welcome!
If you’d like to improve or extend this package, please fork the repo and create a PR.

## 🧠 License

This package is open-source software licensed under the MIT license.