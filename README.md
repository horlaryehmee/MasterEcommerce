# Product Catalog Website

By **[Bakhtech Solutions](https://bakhtech.com.ng)**.

A reusable, lightweight product catalogue website for small businesses. Customers browse products and enquire on **WhatsApp**, or (optionally) add products to a cart and check out as guests. The business owner manages everything from a simple admin dashboard at `/admin`.

Each client gets an **independent installation**: their own hosting, database, admin account, products and images, all running the same core from this one repository. Features for a single client go in an extension (`extensions/`), not a separate copy of the code. This is not a multi-tenant SaaS.

**Stack:** Laravel 12 · Inertia.js 2 · React 19 · Tailwind CSS 4 · MySQL · Vite. Node.js is only needed to compile assets; the live site is plain PHP.

---

## Contents

1. [Features](#features)
2. [Server requirements](#1-server-requirements)
3. [Run locally](#2-run-locally)
4. [Onboarding a new client](#3-onboarding-a-new-client)
5. [Installation on a server](#4-installation-on-a-server)
6. [Deploying on cPanel](#5-deploying-on-cpanel-shared-hosting)
7. [Deploying on a VPS](#6-deploying-on-a-vps)
8. [Configuring the business](#7-configuring-the-business)
9. [Adding products and categories](#8-adding-products-and-categories)
10. [Updating a live site](#9-updating-a-live-site)
11. [Troubleshooting](#10-troubleshooting)
12. [Project structure](#11-project-structure)
13. [Limitations](#12-limitations)

---

## Features

- **Storefront:** homepage with announcement bar, a hero with three designs chosen **separately for desktop and phones** (split, full banner, or a swipeable **banner slider** with separate phone images), featured categories, featured products, new arrivals, promo banner, about and "Why choose us" sections. Product catalogue with search, category filter, six sort orders and pagination. Product pages with an image gallery, a WhatsApp enquiry button and sharing (WhatsApp, Facebook, X, copy link, native share). Also category pages, a designed **About** page (story, how it works, values, collections, contact), a searchable **FAQ** accordion with FAQ structured data, Contact/Shipping/Privacy/Terms pages, and branded 404/500 pages.
- **Optional cart & checkout:** switched on per client in **Website Settings → Shop & checkout** (off by default, so the site stays a WhatsApp catalogue). Session cart, guest checkout (name, phone, optional email, country, state, city, address, postcode), **WooCommerce-style shipping** (see below), and three payment options: **confirm on WhatsApp** (order summary is sent to the business), **pay on delivery** and **bank transfer** (account details shown after ordering). Prices and totals are always recalculated on the server. Customers get a private order page and, if they gave an email, a confirmation; the owner gets a new-order email. Admin **Orders** page with status and payment tracking. Products marked *Out of Stock* or *Available on Request* stay enquiry-only.
- **Shipping (WooCommerce-style):** in **Website Settings → Shipping**, choose the countries you sell to (specific countries, all, or all except some) and your store country. Then create **shipping zones**: each covers countries and/or states (Nigeria, Ghana, Kenya, South Africa, US and Canada have state lists in `config/country_states.php`; others use a free-text field), optionally limited to postcodes (`100*` wildcards, `100001...100099` ranges). Zones are matched top to bottom; "Locations not covered by your other zones" catches the rest. Each zone offers **Flat rate** (cost + optional per-item cost), **Free shipping** (always, or above a minimum order) and **Local pickup** (with pickup address). Checkout shows the options for the customer's address and updates totals live. With no methods set up at all, checkout says the delivery fee will be confirmed.
- **Templates (Admin → Templates):** five complete storefront designs, each with its own header, category section, product cards, footer, section order, fonts and colours: **Essential** (general stores), **Atelier** (fashion: centred logo, tall portrait photos, editorial category tiles), **Glow** (hair & beauty: round category bubbles, soft cards), **Volt** (electronics: marketplace header with a big search bar and category row, compact tiles, trust strip) and **Bloom** (flowers, gifts & cakes: arched category images). Each card shows a live thumbnail of *this* website in that template. **Preview** opens the site in the template without changing anything (a bar offers *Use this template* or *Exit*); **Copy link** gives a signed preview link you can send to anyone. Applying a template can also copy its colours, fonts, button shape and product card into Branding, where they can still be fine-tuned. Products, content and settings are never touched. Templates live in `app/Support/Themes.php`.
- **Variable products (sizes, colours…):** on the product form, **Variations** lets you add up to three options (e.g. Size, Colour, Material) by typing values as chips, with one-tap presets (XS–XXL, common colours with swatches). Every combination is created automatically with its own **price, previous price, SKU, availability, photo** and an on/off switch, plus a bulk editor ("all Red: ₦6,000"). Typing a colour name ("Yello", "navy blue", "burgandy") picks its swatch automatically. Customers pick options as buttons or colour swatches; sold-out or impossible combinations are crossed out, and the price, photo, availability and WhatsApp enquiry follow the choice. Nothing is pre-picked: "Add to cart" reads "Choose size & colour" and points to the missing option. `?variant=ID` links open a specific variation. Cards show "From ₦X" and "Choose options"; search engines get a price range. Cart, checkout, coupons (including "exclude sale items"), orders, emails, the WhatsApp summary and analytics (`item_variant`) all carry the variation; orders keep a snapshot of it.
- **Coupons (WooCommerce-style):** in **Admin → Coupons**, create codes for a **percentage**, **fixed cart** or **fixed per-item** discount, optionally with **free shipping** (unlocks free shipping methods set to require "a valid free shipping coupon", or "a minimum amount OR/AND a coupon"). Restrictions: start and expiry dates (store timezone), minimum/maximum spend, specific or excluded products and categories, exclude sale items, and allowed customer emails (`*@company.com` wildcards). Limits: total uses, uses per customer (matched by email or phone) and the number of items discounted. Customers apply one code per order in the cart or at checkout; it is re-checked on every page and again, with the order locked, when the order is placed. Cancelled orders give their use back. Orders, emails, the WhatsApp summary and purchase tracking show the code and discount; deleting a coupon keeps it on past orders. The coupon field only appears once at least one coupon is switched on.
- **Branding:** brand colours, a **title font** and **body font** (40+ curated Google Fonts in sans, serif, display and script styles, or any Google Font typed by name), title weight, button shape (pill, rounded, square) and product card style, all in **Website Settings → Branding** with a live preview. The built-in font is bundled; other fonts load from Google Fonts on the storefront only (the admin keeps its own look).
- **Scrolling text strip:** in **Website Content → Scrolling text strip**, a moving line of short messages under the hero ("Open Mon – Sun ✱ Same-day delivery ✱ …") with your own messages (up to 12), separator, background/text/accent colours, optional lines above and below, capital letters and speed. It loops seamlessly at any screen width, pauses on hover and stays still for visitors who prefer reduced motion. The hero slider also lets you place the dots left, centre or right (they turn dark on slides with dark text) and choose how strongly the image is shaded behind the text.
- **WhatsApp:** standard `wa.me` click-to-chat links with editable message templates (`{business_name}`, `{product_name}`, `{product_price}`, `{product_url}`). Optional floating button.
- **Admin:** overview stats and setup checklist; product CRUD with multi-image upload, main-image selection, drafts, featured/new flags and availability; category CRUD with safe deletion; homepage content editor; page editor; business, branding, currency, WhatsApp, social and SEO settings; profile and password change.
- **SEO:** server-rendered titles (configurable separator), meta descriptions, self-referencing canonical URLs (including paginated lists), Open Graph/Twitter tags with product price tags, `max-image-preview:large`, Schema.org (Store, WebSite with search, Product with offers, BreadcrumbList, FAQ), `/sitemap.xml` with product and category images, and `/robots.txt`. In **Website Settings → SEO**: a "discourage search engines" switch, verification codes (Google, Bing, Yandex, Pinterest, Facebook; paste the code or the whole meta tag), custom meta tags, and extra robots.txt rules. Products, categories and pages have their own SEO title and description.
- **Tracking & ads:** paste the IDs for Google Analytics 4, Google Tag Manager, Google Ads (with a purchase conversion label), Meta Pixel and TikTok Pixel. Page views (including in-app navigation) and shop events (view item, add to cart, begin checkout, search, purchase when an order is placed) are sent to each in its own format; GTM gets standard GA4 e-commerce data layer events. Nothing is tracked in the admin, or for the logged-in owner unless "Track my own visits" is on.
- **Visitor statistics (Admin → Analytics):** first-party, built in, no third-party scripts. **Overview** with visitors, views, visits, views per visit, average engagement time and bounce rate (Google Analytics 4 definitions), each compared with the previous period; a views/visitors chart (hourly, daily or monthly; also as a table); top and landing pages; channels (GA's default grouping), referrers and UTM campaigns; countries and cities with flags; devices, browsers and systems; clicks to WhatsApp/phone/email/other sites; site searches; most viewed products; and a shop funnel with orders, revenue and conversion rate. **Real-time** view (refreshes every 10 s), a **Visitors** list (filter by country/source, search by city, order number or customer) and a per-visitor timeline of every page and action, linked to their orders. Accuracy: page views come from a JavaScript beacon (bots don't run it), user agents of bots, crawlers, headless browsers and monitors are discarded (Matomo DeviceDetector), prefetches and repeat events aren't counted, sessions end after 30 minutes of inactivity, and the logged-in owner is excluded. Days follow the store timezone (Website Settings → Tracking & ads). IP addresses are never stored; locations come from Cloudflare's country header or the free DB-IP Lite database: run `php artisan stats:geoip-update` once (add `--city` for city-level data, ~130 MB); the scheduler refreshes it monthly and `stats:prune` keeps 25 months. *IP geolocation by [DB-IP](https://db-ip.com) (CC BY 4.0).* The scheduler needs the usual cron entry: `* * * * * php /path/to/artisan schedule:run`.
- **Custom code:** header (`<head>`), body-start and footer code boxes for chat widgets, consent tools and other scripts, added to storefront pages only.
- **Redirects:** changing a published product or category URL creates a 301 redirect automatically (no chains or loops); **Website Settings → Redirects** lists them with visit counts and lets you add your own (301/302), including for addresses that no longer exist.
- **Security:** CSRF, server-side validation, login rate limiting, hashed passwords, safe Markdown rendering (HTML stripped), images re-encoded through GD with random file names, security headers.
- **Automatic image optimisation:** every upload is converted to WebP and resized into full, medium and small versions, typically **90–98% smaller** with no visible quality loss (see [Image optimisation](#image-optimisation)).
- **Performance:** server-side pagination, eager loading (lazy loading is blocked in development), cached settings and navigation, responsive images with `srcset` and lazy loading, and per-page JavaScript chunks.

---

## 1. Server requirements

| Requirement | Version / notes |
|---|---|
| PHP | 8.2 or newer |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `bcmath`, **`gd` (with WebP support)** |
| MySQL | 5.7+ or 8.x (MariaDB 10.4+ also works) |
| Composer | 2.x (on the server, or run locally and upload `vendor/`) |
| Node.js | 20+ **on your computer only**, to build assets |

---

## 2. Run locally

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` for local development:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
SESSION_SECURE_COOKIE=false
DB_DATABASE=catalog
DB_USERNAME=root
DB_PASSWORD=
MAIL_MAILER=log
```

Create the database, then install with demo content:

```bash
npm install
npm run build              # or "npm run dev" while editing React/CSS
php artisan catalog:install --demo
php artisan serve
```

Open http://127.0.0.1:8000 and log in at http://127.0.0.1:8000/admin.

> For a local demo, set the administrator login with `php artisan catalog:create-admin`. The demo seeder generates a random password.

Run the tests:

```bash
php artisan test
```

---

## 3. Onboarding a new client

Each new website is mostly configuration, not development:

1. **Copy the master project** into a new folder (`cp -r master-catalog client-ada-lighting`, or use the master repo as a GitHub template).
2. **Create a separate Git repository** for the client and push the copy to it.
3. **Build the assets** (`npm install && npm run build`) and commit `public/build`. It is committed on purpose so the server never needs Node.js.
4. **Create hosting, a domain and a MySQL database** for the client (see below).
5. **Deploy and install** (sections 4–6) **without** `--demo`.
6. **Log in to `/admin`** and fill in Website Settings and Website Content (section 7).
7. **Add categories and products**, or load the demo first to show the client a preview.
8. **Hand over** the admin URL and credentials. The client can change their password under *Profile*.

Nothing business-specific lives in the code. Name, logo, colours, contact details, WhatsApp number, currency, social links, homepage text and banners are all stored in the database and edited in the admin.

---

## 4. Installation on a server

### Easiest: the setup wizard (like WordPress, no terminal)

1. Download `catalog-vX.Y.Z.zip` from the GitHub release (it already contains everything: PHP packages and compiled assets).
2. cPanel → **File Manager** → upload the zip into `public_html` (or a sub-folder, or a folder your domain points to) → **Extract**.
3. cPanel → **MySQL Databases**: create a database and a user, and add the user to the database with **All Privileges**.
4. Open your website address. The **setup wizard** opens: it checks the server, asks for the database details (and tests them), your business name and your admin login, then installs everything, including the image-upload folder, and locks itself.
5. Optional but recommended: cPanel → **Cron Jobs** → every minute: the command shown at the end of the wizard (needed for automatic updates and statistics upkeep).

Uploading straight into `public_html` is safe: the root `.htaccess` serves only the `public/` folder, so `.env`, `storage/` and the code can't be opened from the web. If the domain's document root points at `public/` (Option A below), that works too.

Already-installed sites are detected automatically and never see the wizard. To reinstall from scratch, delete `.env` and `storage/app/installed.json` and empty the database.

The manual steps below do the same from a terminal.

### 4.1 Create a MySQL database

**cPanel:** *MySQL® Database Wizard* → create a database (e.g. `cpuser_catalog`) → create a user with a strong password → grant **ALL PRIVILEGES** → note the three values.

**VPS:**

```sql
CREATE DATABASE catalog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'catalog'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT ALL PRIVILEGES ON catalog.* TO 'catalog'@'localhost';
FLUSH PRIVILEGES;
```

### 4.2 Configure environment variables

```bash
cp .env.example .env
php artisan key:generate
```

Set at least:

| Variable | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://clientdomain.com`. Must be exact: image links, the sitemap and share previews use it. |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | from step 4.1 |
| `MAIL_*` | Fallback email settings. Usually leave them: email is set up in **Admin → Settings → Email** (SMTP or PHP mail) |

### 4.3 Install Composer dependencies

```bash
composer install --no-dev --optimize-autoloader
```

No Composer on the server? Run the same command locally and upload the `vendor/` folder.

### 4.4 Run migrations and essential setup

```bash
php artisan catalog:install
```

This runs the migrations, creates the settings rows and the six standard pages, links storage, and prompts you to create the administrator. It is safe to run again. Add `--demo` only for a demo or preview site.

The individual steps, if you prefer:

```bash
php artisan migrate --force
php artisan db:seed --force          # essential setup only, never demo data
php artisan storage:link
php artisan catalog:create-admin
```

For the web installer, open the site over HTTPS and enter the generated `INSTALL_SETUP_TOKEN` from the hosting account’s private `.env` file. For an existing environment file, set this value to a random secret of at least 32 characters. The wizard clears the token at completion.

With `TRUSTED_PROXIES` empty (the default, typical cPanel/Cloudflare hosting), only the proxy's "this visit was HTTPS" header is believed, so HTTPS handled by a proxy works, and forwarded client IPs are ignored, so IP-based limits cannot be bypassed. If you operate the reverse proxy, set `TRUSTED_PROXIES` to its IP addresses or CIDRs: only those peers are then believed, including the real visitor IP from `X-Forwarded-For`. Restrict direct origin access and configure the proxy to overwrite forwarded headers. Serve the Laravel `public/` directory. Use production mode, HTTPS-only cookies and encrypted sessions in production. Enabling session encryption on an existing site requires users to sign in again.

To review pre-upgrade visitor statistics, run `php artisan security:review-analytics-links`. It reports affected rows and matching order numbers without displaying tokens. Capture the affected order numbers before running it with `--redact`. Review hosting access logs, analytics exports, third-party analytics and backups separately. For each confirmed exposure, run `php artisan security:rotate-order-link NUMBER` and deliver the replacement from Admin Orders to the customer.

### 4.5 Create (or reset) the administrator account

```bash
php artisan catalog:create-admin
# or non-interactively:
php artisan catalog:create-admin --name="Ada" --email="owner@client.com" --password="StrongPass123"
```

Each installation has **one** administrator. Running the command again with a different email asks to replace the existing login details. This is also how you recover access if the owner loses their password and email.

### 4.6 Configure storage (uploaded images)

Uploads are stored in `storage/app/public` and served through the `public/storage` symlink:

```bash
php artisan storage:link
```

**Host doesn't allow symlinks?** Create a `storage` folder inside the web root and point the upload disk at it in `.env`:

```dotenv
PUBLIC_DISK_ROOT=/home/cpuser/public_html/storage
```

Images then go straight into the public folder, so no symlink is needed.

### 4.7 Compile frontend assets

On your computer, **not** the server:

```bash
npm install
npm run build
```

This writes `public/build/`. Commit it, or upload it with the rest of the project.

### 4.8 Set permissions

```bash
chmod -R 775 storage bootstrap/cache
# VPS only: make the web server user the owner
chown -R www-data:www-data storage bootstrap/cache
```

On cPanel the files already belong to your account; `755` for folders and `644` for files is normally fine, with `storage/` and `bootstrap/cache/` writable.

### 4.9 Clear and cache configuration

After every deploy or `.env` change:

```bash
php artisan optimize:clear     # clear old caches
php artisan optimize           # cache config, routes, views and events
```

---

## 5. Deploying on cPanel (shared hosting)

### Option A (recommended): point the domain at `public/`

1. Upload the project (zip → *File Manager* → *Extract*, or clone with *Git™ Version Control*) to a folder **outside** `public_html`, e.g. `/home/cpuser/catalog`.
2. *Domains* → select the domain → set its **Document Root** to `/home/cpuser/catalog/public`.
3. Open *Terminal* (or SSH) and run steps 4.2–4.9 inside `/home/cpuser/catalog`.

### Option B: the domain must use `public_html`

1. Upload the project to `/home/cpuser/catalog` (outside `public_html`).
2. Move **the contents** of `catalog/public/` into `public_html/` (including `.htaccess` and the `build/` folder).
3. Edit `public_html/index.php` so it points at the project and tells Laravel where the public folder is:

   ```php
   <?php

   use Illuminate\Foundation\Application;
   use Illuminate\Http\Request;

   define('LARAVEL_START', microtime(true));

   if (file_exists($maintenance = '/home/cpuser/catalog/storage/framework/maintenance.php')) {
       require $maintenance;
   }

   require '/home/cpuser/catalog/vendor/autoload.php';

   /** @var Application $app */
   $app = require_once '/home/cpuser/catalog/bootstrap/app.php';
   $app->usePublicPath(__DIR__);

   $app->handleRequest(Request::capture());
   ```

4. Make uploaded images reachable from `public_html`. Artisan commands don't go through `index.php`, so `storage:link` would link into `catalog/public` instead. Use **one** of:
   - `ln -s /home/cpuser/catalog/storage/app/public /home/cpuser/public_html/storage` (Terminal), or
   - if symlinks are blocked: `PUBLIC_DISK_ROOT=/home/cpuser/public_html/storage` in `.env`.
5. After each new build, upload `public/build/` into `public_html/build/`.

### cPanel PHP settings

In *MultiPHP Manager* choose PHP **8.2+**. In *MultiPHP INI Editor* set `upload_max_filesize = 12M`, `post_max_size = 128M` (several photos upload at once), `memory_limit = 512M` (large photos are decoded in memory) and `max_execution_time = 120`.

### HTTPS

Enable *SSL/TLS Status* → AutoSSL, make sure `APP_URL` starts with `https://`, and keep `SESSION_SECURE_COOKIE=true`.

---

## 6. Deploying on a VPS

Example Nginx server block (PHP-FPM 8.3):

```nginx
server {
    listen 80;
    server_name clientdomain.com www.clientdomain.com;
    root /var/www/catalog/public;
    index index.php;
    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~* \.(webp|jpg|jpeg|png|svg|woff2|css|js)$ {
        expires 30d;
        access_log off;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

Then run steps 4.2–4.9 and add HTTPS (`certbot --nginx`). Apache works out of the box with the included `public/.htaccess`.

No queue worker, scheduler or Node process is required.

---

## 7. Configuring the business

Log in at `https://clientdomain.com/admin`. The **Overview** page shows a setup checklist.

| Admin screen | What to set |
|---|---|
| **Website Settings → Business information** | Business name, description, logo, favicon, email, phone, address, opening hours |
| **Website Settings → Email** | How emails are sent (SMTP server, port, encryption, login, or PHP mail), sender name and address, and a test email |
| **→ Branding** | Primary, secondary and accent colours (live preview) |
| **→ Currency** | One-click presets (NGN, USD, GBP, EUR, GHS, KES, ZAR) or a custom code, symbol, position, decimals and separators |
| **→ WhatsApp** | Number in international format (e.g. `2348012345678`), message templates, floating button |
| **→ Social media** | Instagram, Facebook, TikTok, X, YouTube, LinkedIn. Only filled-in links appear. |
| **→ SEO** | Website title, meta description, social sharing image |
| **Website Content** | Announcement bar, hero (see below), featured categories, featured products, new arrivals, promo banner, about, "Why choose us", footer text, and the six informational pages. Each section can be switched off. |

**Hero banner and slider** (*Website Content → Hero banner & slider*):

- Pick a design for **desktop & tablet** and, separately, for **phones**: *Split* (text beside an image), *Full banner* (large image with text on top) or *Banner slider*.
- Each slide has a desktop image (e.g. 1920 × 800) and an optional phone image (e.g. 1080 × 1350), so phones can show a portrait design. A slide can show a heading and buttons over the image, or be **image only** (for banners that already contain text) with a tap-through link.
- Options: boxed or full width, banner height for desktop and phones, text below or on the image on phones, autoplay and time per slide. Slides can be hidden, reordered (up to 8).
- The slider supports swipe, arrows, dots, keyboard and a pause button, pauses on hover and respects reduced-motion settings. Only the design for the visitor's device is loaded.
- If a design has nothing to show (e.g. slider with no active slides), the hero falls back automatically instead of appearing broken.

Button links accept a page path (`/products`, `/categories/phones`), a full `https://` link, or the word `whatsapp` to open the business chat.

Page text supports placeholders: `{business_name}`, `{email}`, `{phone}` and `{address}` are filled in automatically, so the starter pages work for any business.

---

## 8. Adding products and categories

**Categories:** *Categories → Add category*. Name (the URL slug is generated automatically), description, image, display order, *Visible on website* and *Show on homepage*. Deleting a category that still has products asks whether to move them to another category or leave them uncategorised.

**Products:** *Products → Add product*.

- Name, URL slug (automatic, editable), short description, full description (simple formatting toolbar).
- Current price and optional previous price (shown crossed out, with a discount badge).
- Category, availability (In Stock, Limited, On Request, Pre-order, Out of Stock).
- Status (*Published* or *Draft*), *Featured* and *New arrival* toggles.
- Up to 10 images (JPEG/PNG/WebP, 10 MB each; photos straight from a phone are fine). Drag and drop, preview, remove, and choose the **Main** image. After saving, a message shows how much the images were optimised.
- Optional SEO title and description.

The product list has quick buttons to publish/unpublish and feature/unfeature, plus search and filters. Drafts can be previewed on the storefront while logged in.

**About and FAQ pages:** *Website Content → Pages*.

- **About:** edit the story text and optionally upload a page image (otherwise the homepage About image is used). The introduction under the title is the business description, and the values section reuses the homepage "Why choose us" points, so each is edited once.
- **FAQ:** add, edit, reorder or remove questions; answers support the formatting toolbar and placeholders. Customers can search the questions, and the page publishes FAQ structured data built from them.

### Image optimisation

Every uploaded image (products, categories, banners, logo, favicon, sharing image) is optimised automatically on the server, with no extra tools or services:

| What happens | Why it keeps quality |
|---|---|
| Photos become **WebP at quality 90** | Visually indistinguishable from the original (about 40 dB PSNR in testing) |
| Logos, graphics and screenshots become **lossless WebP** | Every pixel kept, so text and sharp edges stay crisp; transparency preserved |
| A full-size version (products 1600px, banners 1920px) plus medium and small versions | Phones download a small file and large screens get full detail; images are **never enlarged** |
| Light sharpening after downsizing | Restores the crispness that resizing removes |
| The photo's **colour profile is kept** (e.g. iPhone "Display P3") | Colours stay as vivid as the original instead of looking washed out |
| Phone rotation is corrected; hidden metadata (GPS location, camera data) is removed | Correct orientation, and customer privacy |

A typical 2–5 MB phone photo becomes about 80–150 KB at full size. Settings live in `config/catalog.php` (`image_quality`, sizes). After changing sizes, run `php artisan catalog:regenerate-images`.

---

## 9. Updating a live site

### Managed updates from your central update server (recommended for many sites)

The separate **Central Update Management** app (`../central-management`, see its `docs/`) manages every client site: you publish signed releases there and deploy them to all, some or one site. Each site's built-in **update agent** (`app/Services/Agent`) does the work:

- **Connect** once: Admin → Updates → Update server, enter the server address and the one-time code from central (or `php artisan agent:connect URL CODE`). Needs the normal Laravel cron (`* * * * * php /path/artisan schedule:run`).
- Every few minutes the site checks in (outgoing HTTPS only); central never logs in to the site or its database, and the shop works normally if central is offline.
- Updates are verified (Ed25519 signature, package and per-file SHA-256, compatibility, client binding), wait while customers are checking out, back up the database, swap files with automatic rollback, migrate in a fresh process, and report the real result.
- **Hand-edited core files are never overwritten**: the update is refused and the changed files listed.
- **Backups on request**: central's "Create backup" makes the site build a zip (database dump, plus uploads and payment receipts if asked; never `.env` or code) and upload it in resumable chunks at its next check-in. Central stores it encrypted. Each backup is written to the site's update log. Set `AGENT_ALLOW_BACKUPS=false` to refuse backups.
- When connected, the GitHub one-click flow below is hidden; updates come from central.

**Modules and client extensions.** Optional features live in `modules/<slug>/`, code for one client in `extensions/<slug>/` (each with a `module.json`; see the READMEs in those folders and `../central-management/docs/examples/`). Core updates never touch them; they have their own releases. Package one with `php artisan release:package module|extension <slug>`.

### One-click updates from GitHub (single site, no central server)

Sites update themselves from **Admin → Updates**: no terminal, Composer, Node.js or Git needed on the server.

**Publishing a new version (developer):**

1. Put the new number in the `VERSION` file (e.g. `1.2.0`) and commit.
2. Tag and push it: `git tag v1.2.0 && git push origin main v1.2.0`.
3. GitHub Actions (`.github/workflows/release.yml`) builds `catalog-v1.2.0.zip` with PHP dependencies and compiled assets, and attaches it to a GitHub release. Edit the release text on GitHub: it's shown to the site owner as "What's new".

**Connecting a site (once per site):** in *Admin → Updates → Update source*, enter the repository (`owner/name`) and, for a private repository, a fine-grained access token with read-only *Contents* access. Or set `UPDATE_GITHUB_REPO` / `UPDATE_GITHUB_TOKEN` in `.env`. A token is recommended even for public repositories: shared hosting IPs quickly hit GitHub's anonymous limit of 60 requests an hour.

**What "Update now" does**, one short request per step so host time limits aren't hit:

1. Downloads the release zip and verifies its SHA-256 checksum.
2. Backs up the database (gzipped SQL that can be imported with phpMyAdmin; downloadable from the Updates page).
3. Unpacks into `storage/app/updates/staging` and checks the package is complete.
4. Turns on maintenance mode and swaps the code folders in. The old ones are kept, and if anything fails they are moved straight back.
5. On the new code: runs migrations, clears caches, creates any new image sizes and brings the site back online.

It never touches `.env`, `storage/` (uploads, logs, sessions), `public/index.php`, `public/.htaccess` or the uploads link. Only `public/build/` is replaced in the public folder, so the *public_html* setup (Option B above) is updated correctly too. The last 3 versions (never fewer than 2; core and each module counted separately, older ones deleted automatically, `updater.keep_backups`) are kept under *Previous versions* with a one-click **Roll back** button. It undoes the database changes of the later update(s) (their migrations' `down()`), then restores the files, newest update first, so going back two versions undoes both. If a database change can't be undone, nothing is rolled back. Each entry also has the database backup taken before that update to download.

Requirements on the host: PHP `zip` and `curl` extensions, and the site's folders writable by PHP (normal on cPanel). The Updates page checks these and explains any fix.

The first version that includes the updater has to be installed manually (below); after that, every update can be done from the admin.

### Manual update

```bash
git pull                                   # or upload the changed files
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan catalog:regenerate-images      # creates any new image sizes for existing uploads (safe to re-run)
php artisan optimize:clear && php artisan optimize
```

Rebuild assets locally (`npm run build`) and deploy `public/build/` whenever React or CSS files change.

The demo seeder **refuses to run** on a database that contains a client's own products, categories or business name, and never overwrites existing records. Client data is safe.

---

## 10. Troubleshooting

| Problem | Fix |
|---|---|
| Images don't show | Check `APP_URL` is the exact domain (with `https://`). Run `php artisan storage:link`, or use `PUBLIC_DISK_ROOT` (4.6). Then `php artisan optimize:clear`. |
| "Vite manifest not found" | `public/build` is missing. Run `npm run build` locally and upload it. |
| 500 error after deploy | Check `storage/logs/laravel.log`. Usually permissions (4.8), a missing `APP_KEY`, or wrong DB details. Run `php artisan optimize:clear`. |
| Admin login loops back to the login page | On plain `http://`, set `SESSION_SECURE_COOKIE=false` (or enable HTTPS). |
| Password reset email never arrives | Check **Settings → Email** and send a test email. Use `php artisan catalog:create-admin` to reset directly. |
| Upload fails for large photos | Raise `upload_max_filesize`/`post_max_size` (section 5). Very large photos are resized automatically. If big batches time out, upload fewer images per save or raise `max_execution_time`. |
| Changes in `.env` ignored | Config is cached: `php artisan optimize:clear && php artisan optimize`. |

---

## 11. Project structure

```
app/
  Console/Commands/        catalog:install, catalog:create-admin, catalog:regenerate-images
  Http/Controllers/
    Storefront/            Home, Product (catalog/details/search), Category, Page, Sitemap
    Admin/                 Dashboard, Product, Category, Content, Page, Settings, Profile, Auth/
  Http/Middleware/         HandleInertiaRequests (shared props), SecurityHeaders
  Models/                  Product, ProductImage, Category, BusinessSetting, HomepageSetting, SocialLink, Page, User
  Services/                ImageStorage (safe upload + automatic optimisation), ColorProfile (keeps photo colours)
  Support/                 Money, WhatsApp, Seo, Media, Markdown, Slug, Color, StorefrontCache
config/catalog.php         Technical options (per page, upload limits, image sizes)
database/
  migrations/              Schema
  seeders/                 EssentialSeeder, DemoSeeder (+ DemoBusiness/Settings/Category/Product/Homepage/Admin)
  seeders/demo-images/     Bundled demo illustrations (original artwork, no brand logos)
resources/
  views/app.blade.php      Root HTML: SEO tags, JSON-LD, brand colour variables
  css/app.css              Tailwind theme, brand tokens
  js/Pages/Storefront/     Home, Catalog, CategoryShow, Categories, ProductShow, Page
  js/Pages/Admin/          Dashboard, Products/, Categories/, Content/, Settings/, Profile, Auth/
  js/Components/Store/     Header, Footer, ProductCard, ProductGallery, WhatsAppButton, Home/* sections…
  js/Components/Admin/ui.jsx  Admin form controls, modal, pagination
  js/Layouts/              StoreLayout, AdminLayout, AuthLayout
tests/                     Feature and unit tests (php artisan test)
```

### Database tables

| Table | Purpose |
|---|---|
| `users` | Administrator account |
| `business_settings` | Single row: profile, branding, currency, WhatsApp, footer, SEO defaults |
| `homepage_settings` | Single row: homepage sections (`why_items` is JSON) |
| `categories` | Name, unique slug, description, image, order, visible/featured, SEO |
| `products` | Category FK (set null on delete), unique slug, prices, availability, flags, status, SEO. Indexed for sorting and filtering. |
| `product_images` | Product FK (cascade), path, order, `is_primary` |
| `social_links` | One row per platform (unique) |
| `pages` | Informational pages (unique slug, Markdown content) |
| `sessions`, `cache`, `password_reset_tokens`, `jobs` | Laravel internals |

### Customising the design for a client

Brand colours come from the admin. For deeper changes, the storefront is plain React and Tailwind. Edit components in `resources/js/Components/Store/` (section components live in `Home/`) and the theme tokens at the top of `resources/css/app.css`. The admin dashboard is styled separately and is unaffected.

---

## 12. Limitations

- **No server-side rendering:** pages render in the browser with React. Titles, descriptions, Open Graph tags and structured data **are** rendered on the server, so search engines and WhatsApp/Facebook previews work, and Google renders the content. Search engines that don't run JavaScript see the metadata but not the page body.
- **Descriptions use simple Markdown** (bold, italic, headings, lists, links) with a toolbar. Raw HTML is intentionally stripped for safety, and there is no WYSIWYG editor.
- **One administrator per installation**, by design. There are no roles or permissions.
- **Password reset needs working email** (Settings → Email). Without it, use `php artisan catalog:create-admin`.
- **The demo images are illustrations**, not photographs. Clients should replace them with real product photos.
- **No online card payments.** Checkout supports WhatsApp confirmation, pay on delivery and manual bank transfer; the owner marks orders as paid. A gateway such as Paystack would need to be added.
- **No stock counts.** Availability is the product's status (In Stock, Out of Stock, ...); quantities are not deducted when orders are placed.
- **Order emails need working email** (Settings → Email: SMTP, or the server's PHP mail). Orders are always saved; a failed email is logged and never blocks checkout.
- **Search uses SQL `LIKE`**, which is ideal for catalogues of hundreds to a few thousand products. It is not a full-text search engine.
