# Vulcatic

Roadside tire help for tourists and locals. Find a nearby vulcanizing shop or a
skilled local ("vulcator"), request on-site repair, or walk to the nearest shop
when you have no signal.

**Stack:** PHP 8 + MySQL, Tailwind CSS, plain JavaScript. Hosted on InfinityFree.

```
database/            SQL for MySQL Workbench / phpMyAdmin (see database/README.md)
htdocs/              everything that gets uploaded to the web server
  config/            config.example.php -> copy to config.php
  includes/          bootstrap, database, auth, shared layout parts
  assets/            css (built), js, images
  index.php          welcome page
  login.php  register.php  logout.php
  home.php           customer home
  dashboard.php      vulcator dashboard (stats, online toggle, recent jobs)
  admin.php          approve / reject new vulcators
tailwind/input.css   Tailwind source -> builds htdocs/assets/css/app.css
tailwind.config.js
```

## Run it locally (XAMPP + MySQL Workbench)

- **MySQL Workbench** is where you build, view and test the database.
- **XAMPP** is only used for **Apache**, which runs the PHP pages. Workbench cannot
  run the website by itself.
- The database runs on the same MySQL server your Workbench connection uses.
  If that's your own MySQL Server install, **don't start MySQL in the XAMPP panel**:
  both use port 3306 and would conflict.

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** only.
2. Create the database in MySQL Workbench by running the files in `database/` in order. See `database/README.md`.
3. Copy the **contents** of `htdocs/` into `C:\xampp\htdocs\vulcatic\`.
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

## Changing styles (Tailwind)

The site uses a pre-built `htdocs/assets/css/app.css`, so the server needs nothing extra.
If you add Tailwind classes that weren't used before, rebuild the CSS. You don't need Node.js:

1. Download the standalone CLI for your OS from
   <https://github.com/tailwindlabs/tailwindcss/releases/tag/v3.4.16>
   (e.g. `tailwindcss-windows-x64.exe`) and put it in the project root.
2. From the project root, run:

```
tailwindcss-windows-x64.exe -i tailwind/input.css -o htdocs/assets/css/app.css --minify --watch
```

`--watch` rebuilds every time you save. Put custom (non-Tailwind) CSS in `tailwind/input.css`.

## Deploy to InfinityFree

1. Create the database in the control panel, then import `database/01_schema.sql`
   in phpMyAdmin (see `database/README.md`).
2. Upload the **contents** of `htdocs/` into the account's `htdocs/` folder
   (File Manager or FTP with FileZilla).
3. Create `config/config.php` on the server with the InfinityFree database
   host/name/user/password, `base_url` set to `''` and `debug` set to `false`.
4. Turn on the free SSL certificate. The "Use my current location" button only
   works on `https://` (or `localhost`).
