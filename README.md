# Vulcatic

Roadside tire help on **Siargao Island** (Surigao del Norte) for tourists and locals. Find a nearby vulcanizing shop or a
skilled local ("vulcator"), request on-site repair, or walk to the nearest shop
when you have no signal.

**Stack:** PHP 8 + MySQL, Tailwind CSS, plain JavaScript. Hosted on InfinityFree.

```
config/              config.example.php -> copy to config.php
includes/            bootstrap, database, auth, shared layout parts
assets/              css (built), js, images, vendor/leaflet (map library)
index.php            welcome page
login.php  register.php  logout.php
home.php             customer: live map of nearby help + "Find a vulcator" request
job.php              customer: track the request, pay, rate
dashboard.php        vulcator: stats, online toggle, new-job popup
provider_job.php     vulcator: navigate, job checklist, collect payment, earnings
history.php          customer: past requests and receipts / vulcator: earnings, chart, ratings
profile.php          your details and password; vulcators also edit shop, services and prices, GCash, location
admin.php            admin dashboard: KPIs, requests per day, towns, vulcator management, jobs
offline.html         shop finder that works with no signal (kept on the phone)
forgot.php           "Forgot password?" request; an admin sets a temporary password
change_password.php  forced new password after logging in with a temporary one
terms.php privacy.php  Terms of Use and Privacy Policy (Data Privacy Act of 2012)
404.php              friendly "Lost on the road?" page for wrong addresses
storage/             login sessions (kept 30 days; not in git)
sw.js                service worker for offline mode
manifest.webmanifest lets phones install Vulcatic like an app
api/                 JSON endpoints the live pages call every few seconds
database/            SQL for MySQL Workbench / phpMyAdmin (see database/README.md)
tailwind/input.css   Tailwind source -> builds assets/css/app.css
tailwind.config.js
```

`.htaccess` blocks the browser from opening `database/`, `tailwind/`, `config/`,
`includes/`, `.git` and the `.md` / `.sql` files.

## Run it locally (XAMPP + MySQL Workbench)

- **MySQL Workbench** is where you build, view and test the database.
- **XAMPP** is only used for **Apache**, which runs the PHP pages. Workbench cannot
  run the website by itself.
- The database runs on the same MySQL server your Workbench connection uses.
  If that's your own MySQL Server install, **don't start MySQL in the XAMPP panel**:
  both use port 3306 and would conflict.

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** only.
2. Create the database in MySQL Workbench by running the files in `database/` in order. See `database/README.md`.
3. Clone this repo into XAMPP's `htdocs` folder, so the project is at
   `<xampp folder>\htdocs\vulcatic\`. Click **Explorer** in the XAMPP Control Panel
   to see which XAMPP folder your Apache uses.
4. In that folder, copy `config/config.example.php` to `config/config.php` and
   set `user` / `password` (and `port` if it isn't 3306) to the same values as
   your Workbench connection.
5. Open <http://localhost/vulcatic/>.

Test accounts (password `password123`):

| Role | Login |
|---|---|
| Customer | `09171111111` (Juan Cruz) |
| Vulcator | `09174444444` (Jose Dela Cruz) |
| Admin | `09170000000` |

New vulcators start as **pending**. Log in as admin to approve them before they can go online.

### Try a full job (two browsers)

You need two separate logins at once, so use a normal window **and** an incognito window
(or two different browsers).

1. **Window A:** log in as Jose (`09174444444`). Make sure he's **Currently online**.
2. **Window B:** log in as Juan (`09171111111`). Allow location, or tap the map near Jose's
   pin on Tourism Road, General Luna. If you're not on Siargao, drag the orange pin there.
   Choose a service and vehicle, then **Find a vulcator**.
3. **Window A:** the *New job request nearby!* popup appears within a few seconds. **Accept**.
4. **Window A:** tap **Start navigation**, **I've arrived**, then **Tire fixed**. Window B
   follows along: accepted, on the way, arrived.
5. **Window B:** pick **Cash** or **GCash** (enter any 8+ digit reference) and confirm.
6. **Window A:** **Mark as payment received** to see *Earnings confirmed*.
7. **Window B:** see *Payment successful*, then rate Jose.

How it works without WebSockets: the pages ask the server for updates every 3–4 seconds
(`api/job_status.php`, `api/provider_poll.php`). A request goes to the 5 nearest free,
verified, online vulcators within the town's search radius. The first to accept gets it. Unanswered requests
time out after 60 s and are sent again. These numbers are in the `app_settings` table.

## Service area: Siargao Island

`includes/siargao.php` holds the towns, their approximate centres and search radius, and the
box around the island. Requests and new vulcators outside Siargao are refused.

| Phase | Towns | Search radius |
|---|---|---|
| 1 | General Luna, Dapa | 5 km |
| 2 | Pilar, Del Carmen | 10 km |
| 3 | San Isidro, San Benito, Burgos, Santa Monica | 12 km |

Socorro (Bucas Grande) is left out because it's a separate island. The centres and sample
locations are approximate, so check them on the map and adjust them in `includes/siargao.php`
and `database/02_seed.sql`.

## Offline mode

Vulcatic can be installed on a phone (browser menu → **Add to Home Screen**) and keeps working
with weak or no signal:

- `sw.js` keeps the app's files on the phone. With no signal, opening any page shows
  `offline.html`, the **Nearby shops** finder.
- While logged in and online, the list of every approved shop and vulcator on Siargao
  (`api/offline_pack.php`) is saved on the phone and refreshed every 6 hours.
- The finder uses the phone's GPS (works without data) to sort shops by distance, with a
  direction arrow, open/closed status, and **Call / Text** buttons. Calls and SMS often work
  when mobile data doesn't.
- Map tiles you've already looked at are kept. Vulcatic never bulk-downloads tiles, because
  OpenStreetMap's usage policy doesn't allow it.

Offline mode needs `https://` or `localhost`. When you change `offline.html` or the files
listed at the top of `sw.js`, bump `VERSION` in `sw.js` so phones pick up the new copy.

## Admin dashboard

Log in as admin (`09170000000`). A sidebar (a slide-in menu on phones) organises everything:

| Section | What's there |
|---|---|
| **Overview** | What needs attention, 8 headline numbers (7/14/30 days), requests per day, requests by town, latest requests |
| **Action needed → Approvals** | New vulcators with full details (owner, phone, pin, services and prices, hours); approve or reject |
| **Action needed → Password resets** | Open requests; set a temporary password (shown once, with Text/Call) or dismiss; recent history |
| **Operations → Live jobs** | Every job in progress, grouped by stage; flags requests with no taker after 3 minutes; refreshes every 20 s |
| **Operations → All jobs** | Search, filter by period/town/status, 25 per page, **Export CSV**; click a row for the job's full detail |
| **Job detail** | Customer and vulcator, location, money breakdown, full timeline, which vulcators were offered it, rating |
| **Operations → Towns** | Per-town requests, completion, time to accept, payments and vulcator coverage, plus a map |
| **People → Vulcators** | Filter (online, pending, suspended, rejected), town, search; rating, accept rate, jobs, earnings; suspend or reactivate |
| **People → Customers** | Requests, cancellations (flags heavy cancellers), spending; suspend or reactivate |
| **People → Reviews** | Average, star breakdown, latest or low (3★ or less) reviews |
| **System → Settings** | Platform fee, convenience fee, time to accept, fallback radius, service base prices and on/off |

Badges in the sidebar show what's waiting. Suspended accounts can't log in.

## Changing styles (Tailwind)

The site uses a pre-built `assets/css/app.css`, so the server needs nothing extra.
If you add Tailwind classes that weren't used before, rebuild the CSS. You don't need Node.js:

1. Download the standalone CLI for your OS from
   <https://github.com/tailwindlabs/tailwindcss/releases/tag/v3.4.16>
   (e.g. `tailwindcss-windows-x64.exe`) and put it in the project root.
2. From the project root, run:

```
tailwindcss-windows-x64.exe -i tailwind/input.css -o assets/css/app.css --minify --watch
```

`--watch` rebuilds every time you save. Put custom (non-Tailwind) CSS in `tailwind/input.css`.

## Accounts and security

- **Stay logged in:** logins last 30 days, even after closing the browser.
- **Forgot password:** the user sends a request from the login page. It appears on the
  admin dashboard. The admin calls the number to check it's really them, taps
  **Set temporary password**, and reads or texts it to them. The user must choose a new
  password right after logging in. (InfinityFree can't send email or SMS reliably.)
- **Password guessing:** 5 wrong tries for one account, or 20 from one device, locks login
  for 15 minutes.
- **Consent:** sign-up requires agreeing to the Terms of Use and Privacy Policy.
- **Security headers** (in `.htaccess`) block framing, MIME sniffing and unexpected scripts.
- With `debug` off, errors show a friendly page and the details go to the server's error log.

## Publishing checklist

Before going live:

1. In Workbench / phpMyAdmin, import `01_schema.sql` and then **`02_production.sql`**
   (not `02_seed.sql`). It adds the services and one admin: phone `09170000000`,
   password `ChangeMe2026`. Log in right away. You'll be asked to set your own password.
   Then change the admin phone number on the Profile page.
2. In `config/config.php` on the server: real database details, `'debug' => false`,
   and your `contact_email` / `contact_phone` (shown on the Privacy and Terms pages).
3. Turn on HTTPS (free SSL on InfinityFree). Location, offline mode and alerts need it.
4. Make sure the `storage/` folder can be written by PHP (it's created automatically).
5. Have someone familiar with Philippine law review `terms.php` and `privacy.php`.
   They're a solid plain-language starting point, not legal advice.
6. Approve your first vulcators from the admin dashboard.

## Deploy to InfinityFree

1. Create the database in the control panel, then import `database/01_schema.sql`
   in phpMyAdmin (see `database/README.md`).
2. Upload `index.php`, the other `.php` pages, `.htaccess`, `assets/`, `config/` and
   `includes/` into the account's `htdocs/` folder (File Manager or FTP with FileZilla).
   Also upload `api/`, `storage/` (with its `.htaccess`), `offline.html`, `sw.js`,
   `manifest.webmanifest`, `404.php`. You don't need to upload `database/`, `tailwind/` or the `.md` files.
3. Create `config/config.php` on the server with the InfinityFree database
   host/name/user/password and `debug` set to `false` (leave `base_url` as `'auto'`).
4. Turn on the free SSL certificate. The "Use my current location" button only
   works on `https://` (or `localhost`).
