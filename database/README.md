# Vulcatic database

MySQL database for Vulcatic. Build and test it in **MySQL Workbench**, then
move it to **InfinityFree** with **phpMyAdmin**.

| File | What it does | Workbench | InfinityFree phpMyAdmin |
|---|---|---|---|
| `00_create_database.sql` | Creates the `vulcatic` database | Run | **Skip** (InfinityFree creates it) |
| `01_schema.sql` | Creates all tables (drops them first) | Run | Import |
| `02_seed.sql` | Sample users, providers, jobs | Run | Import only for testing |
| `03_test_queries.sql` | The queries each screen will use | Run to test | Not needed |

All sample accounts use the password `password123`. Coordinates are placeholders
in the Manila area; change them to your town.

## Run it in MySQL Workbench

1. Open Workbench and connect to your local MySQL server.
2. **File → Open SQL Script** → `00_create_database.sql` → click the ⚡ (Execute) button.
3. Do the same for `01_schema.sql`, then `02_seed.sql`.
4. Click the refresh icon in the **Schemas** panel; you should see `vulcatic` with 10 tables.
5. Open `03_test_queries.sql`. Put the cursor on one query and press **Ctrl+Enter**
   to run just that query.
6. To see the table diagram: **Database → Reverse Engineer…** → choose `vulcatic`.

Running `01_schema.sql` again wipes the tables, so you can always start fresh.

## Move it to InfinityFree

1. In the InfinityFree control panel: **MySQL Databases** → create a database
   (it will be named something like `if0_12345678_vulcatic`).
2. Open **phpMyAdmin** for that database.
3. **Import** → `01_schema.sql` → Go. Then import `02_seed.sql` if you want test data.
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
