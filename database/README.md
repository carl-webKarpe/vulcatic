# Vulcatic database

MySQL database for Vulcatic. Build and test it in **MySQL Workbench**, then
move it to **InfinityFree** with **phpMyAdmin**.

| File | What it does | Workbench | InfinityFree phpMyAdmin |
|---|---|---|---|
| `00_create_database.sql` | Creates the `vulcatic` database | Run | **Skip** (InfinityFree creates it) |
| `01_schema.sql` | Creates all tables (drops them first) | Run | Import |
| `02_seed.sql` | Sample users, vulcators and two weeks of job history on Siargao | Run | Import only for testing |
| `02_production.sql` | Settings, services and one admin (no sample people). Use **instead of** `02_seed.sql` when publishing | Optional | **Import this** when going live |
| `03_test_queries.sql` | The queries each screen will use | Run to test | Not needed |
| `04_upgrade.sql` | Brings an **existing** database up to date after a `git pull`, keeping your data. Safe to run any time | Run after updates | Run after updates (remove the `USE` line) |

All sample accounts use the password `password123`. Sample vulcators and shops are on
Siargao Island (General Luna, Dapa, Pilar, Del Carmen, San Isidro); positions are approximate.

## Run it in MySQL Workbench

1. Open Workbench and connect to your local MySQL server.
2. **File → Open SQL Script** → `00_create_database.sql` → click the ⚡ (Execute) button.
3. Do the same for `01_schema.sql`, then `02_seed.sql`.
4. Click the refresh icon in the **Schemas** panel; you should see `vulcatic` with 10 tables.
5. Open `03_test_queries.sql`. Put the cursor on one query and press **Ctrl+Enter**
   to run just that query.
6. To see the table diagram: **Database → Reverse Engineer…** → choose `vulcatic`.

Running `01_schema.sql` again wipes the tables, so you can always start fresh.

## After a `git pull`

(The latest update added user types, shop employees, provider ETA and chat; `04_upgrade.sql` adds those too.)


If a page shows *Unknown column* or *Table … doesn't exist*, your database is older than the code.
Run `04_upgrade.sql`: it adds whatever is missing and keeps your data. The last result should say **OK** on every row.

## Move it to InfinityFree

1. In the InfinityFree control panel: **MySQL Databases** → create a database
   (it will be named something like `if0_12345678_vulcatic`).
2. Open **phpMyAdmin** for that database.
3. **Import** → `01_schema.sql` → Go. Then import **`02_production.sql`** for the live site
   (or `02_seed.sql` only for a test copy).
4. Write down the host (`sqlXXX.infinityfree.com`), database name, user and password
   for the PHP config file.

You can also export from Workbench (**Server → Data Export → Export to Self-Contained
File**) and import that file. **Remove the `CREATE DATABASE` and `USE` lines first.**

## InfinityFree limits we designed around

- **No stored procedures, triggers or views.** All logic (for example, updating
  `providers.rating_avg` after a rating) lives in the PHP code.
- **No remote MySQL access.** Only PHP files hosted on InfinityFree can connect to the
  database; Workbench cannot connect to it directly.
- **PHP + MySQL only, no Node.js or WebSockets.** Live status and the moving vulcator pin
  use AJAX polling (every 3–5 seconds).
- **Payment webhooks are blocked** by InfinityFree's bot protection. Start with
  Cash plus manual GCash (reference number), and add online payments on better
  hosting later.

## Tables

- `users`: customers, providers and admins (one login table, `role` column)
- `providers`: shop or individual repairer, location, online toggle, rating
- `services` / `provider_services`: catalog and each provider's price
- `jobs`: a repair request and its status
  (`searching → matched → accepted → on_the_way → arrived → in_progress → completed → paid`, or `cancelled`)
- `job_offers`: request sent to nearby providers; the first to accept gets it
- `job_status_logs`: timeline for the "Request status" / "Job checklist" panels
- `payments`: cash, GCash or card
- `ratings`: 1–5 stars per job
- `app_settings`: platform fee (5%), search radius, etc.
