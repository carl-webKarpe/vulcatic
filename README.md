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

Log in as admin (`09170000000`) to see, for the last 7, 14 or 30 days:
requests, completion rate, customer payments, platform earnings, time to accept and to arrive,
requests per day, a per-town table and map, vulcators (approve, reject, suspend, reactivate),
and recent jobs filtered by town and status. Suspending a vulcator blocks their login.

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

## Deploy to InfinityFree

1. Create the database in the control panel, then import `database/01_schema.sql`
   in phpMyAdmin (see `database/README.md`).
2. Upload `index.php`, the other `.php` pages, `.htaccess`, `assets/`, `config/` and
   `includes/` into the account's `htdocs/` folder (File Manager or FTP with FileZilla).
   You don't need to upload `database/`, `tailwind/` or the `.md` files.
3. Create `config/config.php` on the server with the InfinityFree database
   host/name/user/password and `debug` set to `false` (leave `base_url` as `'auto'`).
4. Turn on the free SSL certificate. The "Use my current location" button only
   works on `https://` (or `localhost`).
