# MoMoCycle UG

PHP (PDO) + MySQL web system for plastic-waste collection and simulated mobile-money payouts.
Database name: `momocycle1`

## Install on XAMPP (Windows)
1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Import `database/momocycle1.sql` in phpMyAdmin (Import tab). It creates the database, tables, procedures, triggers, views and sample data.
3. Copy this whole folder to `C:\xampp\htdocs\momocycle-ug`.
4. Open `http://localhost/momocycle-ug/`.

If you use a different folder name, change `BASE_URL` in `config/db.php`.
If your MySQL user is not `root` with an empty password, change `DB_USER` and `DB_PASS` there too.

## Demo logins (password for all: Pass1234)
| Role | Phone |
|---|---|
| Admin | 0700000001 |
| Agent | 0700000002 |
| Collector | 0700000003 or 0700000004 |

## Pages
| Role | Pages |
|---|---|
| Agent | Log deposit, Payouts (pay or reject), Register collectors |
| Admin | Dashboard, Reports (views, ledger, audit log), Rates, Hubs, Users, plus all agent pages |
| Collector | My wallet, deposits and payment history |
| Anyone | USSD simulator (`ussd-simulator.php`, gateway endpoint `ussd.php`) |

## Where the database programming is used
| Feature | Where |
|---|---|
| Stored procedures with their own transactions and row locks | `sp_log_deposit`, `sp_process_payout`, `sp_reject_deposit`, called from `agent/log-deposit.php` and `agent/pending.php` |
| Triggers | hub stock update, status audit log, delete protection |
| Views | `vw_daily_collection_summary`, `vw_hub_inventory`, `vw_pending_payouts`, `vw_collector_earnings` (used in admin pages) |
| Constraints | CHECK (weight, rates, wallet, stock, ledger), UNIQUE ledger per deposit, foreign keys, ENUMs |
| PDO security | prepared statements, no emulation, `password_hash`, CSRF tokens, output escaping, role checks on every page |

PHP never opens its own transaction around the procedure calls, because each procedure runs its own `START TRANSACTION`.

## Limits
Mobile money is simulated: a ledger entry stands in for the transfer. The USSD page is a simulator, not a live shortcode.
