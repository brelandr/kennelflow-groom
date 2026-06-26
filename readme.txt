=== KennelFlow Groom ===
Contributors: brelandr
Tags: pets, grooming, kennel, salon, calendar
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.1
Text Domain: kennelflow-groom
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GroomPress for KennelFlow: grooming calendar, groomer pay, commissions, and salon settings. Requires KennelFlow Core.

**Developer:** Randy Breland ([brelandr](https://profiles.wordpress.org/brelandr/)), [Land Tech Web Designs](https://landtechwebdesigns.com). Contact: sales@landtechwebdesigns.com

== Description ==

**GroomPress** (KennelFlow Groom) is the grooming companion for the KennelFlow stack. It adds a **GroomPress** admin menu with a weekly grooming calendar, groomer earnings and commissions, and salon-wide settings — all built on shared **KennelFlow Core** pet records.

**What you get**

* **GroomPress Home** — quick links to earnings and the grooming calendar.
* **Groomer Earnings** — see pending and paid commission totals by groomer for any date range; mark commissions as paid.
* **Grooming Schedule** — weekly calendar filtered to grooming appointments, with groomers as staff rows (uses the KennelFlow Hub calendar bundle).
* **GroomPress Settings** — default commission percentage for WooCommerce grooming products; optional groomer access to KennelFlow Vet medical records when Vet is installed.
* **Groomer role** — staff who appear on the grooming calendar and can view their own earnings.
* **Optional pickup SMS** — when Twilio credentials are configured site-wide, owners can receive a text when a grooming appointment is marked complete.

GroomPress does **not** register a public shortcode. Customer-facing pages use **KennelFlow Core** shortcodes such as `[ltkf_dashboard]` for the owner portal.

Data stays on your site except for optional SMS delivery through your own Twilio account when enabled.

== Try It Live - Preview This Plugin Instantly ==

Preview GroomPress in WordPress Playground: the blueprint installs **KennelFlow Core** and **KennelFlow Groom** from WordPress.org, seeds demo pets and the owner portal, and opens the **Grooming Schedule** calendar in wp-admin. Log in as **admin** / **password** (demo owner: **demoowner** / **password**).

[Preview on WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/brelandr/kennelflow-groom/main/blueprint.json)

The blueprint ships as `blueprint.json` and `assets/blueprints/blueprint.json`. WordPress.org also serves a copy from plugin SVN for directory live preview.

== Installation ==

1. Install and activate **KennelFlow Core** (required).
2. Install KennelFlow Groom through the WordPress.org plugin directory or by uploading the zip under **Plugins → Add New → Upload Plugin**.
3. Activate KennelFlow Groom through the **Plugins** screen.
4. Assign the **Groomer** role to staff who should appear on the calendar.
5. Open **GroomPress** in the admin menu to view earnings or open **Grooming Schedule**.

= Compatibility =

**KennelFlow Groom** is a companion plugin. It requires **KennelFlow Core** for pets, locations, and the calendar API. It works alongside **KennelFlow Boarding** and other KennelFlow add-ons. WooCommerce is optional and used for grooming product commissions. **KennelFlow Vet** is optional and unlocks groomer medical-access settings.

== Frequently Asked Questions ==

= Does GroomPress include a customer booking page? =

Not directly. GroomPress manages grooming in wp-admin. Use KennelFlow Core shortcodes (for example `[ltkf_dashboard]` for the owner portal) on public pages. Boarding or clinic booking wizards come from KennelFlow Core or KennelFlow Vet.

= Does this version include scheduling and commissions? =

Yes. GroomPress includes the grooming calendar, groomer role, commission tracking, earnings reports, and salon settings.

= Does Groom work without KennelFlow Core? =

No. Activate KennelFlow Core first. WordPress lists Core as a required plugin when supported by your site.

= Does the plugin send data to third parties? =

Core grooming features run entirely on your site. Optional completion SMS uses **your** Twilio account when configured; no other third-party APIs are required.

== Screenshots ==

1. Groomer Earnings — commission totals by groomer with pending, paid, and mark-as-paid actions.
2. Grooming Schedule — weekly calendar with groomers as rows and grooming appointments in purple.
3. GroomPress Settings — default commission rate and optional groomer medical access when KennelFlow Vet is active.
4. Booking wizard — example public booking flow from KennelFlow Core (companion shortcode, not registered by GroomPress).
5. My Account — WooCommerce account area often paired with the KennelFlow owner portal.
6. KennelFlow Dashboard — owner portal tabs for boarding, vaccinations, medications, and waivers (`[ltkf_dashboard]`).
7. Today (Pro) — daily grooming list with recipe snippets (KennelFlow Groom Pro add-on).
8. Pet grooming profile — coat notes and digital recipe card (KennelFlow Groom Pro add-on).

== Changelog ==

= 0.2.1 =
* Release grooming calendar, groomer earnings, commissions, salon settings, groomer role, and optional completion SMS.
* WordPress.org assets: banner, icons, and screenshots.
* Readme aligned with current GroomPress admin features.

= 0.2.0 =
* Readme: **Tested up to: 7.0**; formal `readme.txt` for WordPress.org (aligns with plugin header).

= 0.1.0 =
* Initial WordPress.org release: requires KennelFlow Core, Hub entry screen and link to Pets.
