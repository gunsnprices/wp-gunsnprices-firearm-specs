=== GunsNPrices Firearm Specs ===
Contributors: gunsnprices
Tags: woocommerce, firearms, ammo, caliber, gun store
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reads caliber, model, magazine capacity, barrel length and rounds per box from gun and ammo titles. Adds cost per round to WooCommerce ammo.

== Description ==

Gun and ammo product titles already contain the specs, just not in a form a store can filter or compare: `Glock 19 Gen 5 9mm 4.02" 15rd Pistol`, `G19 GEN5 9MM 15RD FS`, `Federal American Eagle 9mm 115gr FMJ 50 Rounds`. This plugin reads them and saves the specs on your products.

From a title (and the SKU or part number, if you have one) it reads:

* **Caliber**, normalized to one name per cartridge: `9mm`, `9MM LUGER` and `9x19` all become `9×19mm Parabellum` (293 calibers, handgun, rifle, rimfire, shotgun).
* **Brand and model** for 50 firearm brands, down to the generation and configuration: `Glock 19 Gen 5`, `Sig Sauer P365 XL`, `Ruger 10/22 Carbine`. Accessories, parts, airguns and airsoft are not mistaken for guns.
* **Magazine capacity**: `15rd`, `17+1`, `10-shot`.
* **Barrel length** in inches: `4.02"`, `16 1/2"`, `22 BBL`, `O/U 12/28`. Chamber length (`3"` on shotguns) is not mistaken for the barrel.
* **Rounds per box** for ammunition: `50/Box`, `Box of 20`, `1,000 Rounds`.

= With WooCommerce =

* A **Firearm & ammo specs** tab on the product screen, with a "Fill from title" button. You can correct any value by hand.
* **Bulk actions** on the Products list: "Fill specs from title" (fills empty fields) and "Re-read specs from title (replace)".
* **Fill specs for all products** in one click on the settings page, in small batches, or `wp gunsnprices fill` on the command line.
* New products are filled automatically the first time they are saved, including products created by CSV import or the REST API (can be turned off).
* **Cost per round** next to the price of ammo, on product pages and in shop lists, for example "$21.99 ($0.44 per round)". It follows your currency and tax display settings.
* The specs in the **Additional information** tab of the product page.
* Optional: copy the specs into **global product attributes** (`pa_caliber`, `pa_capacity`, `pa_barrel-length`, `pa_rounds-per-box`, `pa_brand`, `pa_model`) so shoppers can filter by caliber or capacity with WooCommerce's filter blocks. Existing attributes with the same slug are reused, and attributes used for variations are never touched.

= Without WooCommerce =

* **Cost per round block** and the `[cost_per_round]` shortcode for reviews, blogs and deal posts: `[cost_per_round price="21.99" rounds="50"]` shows "$0.44 per round". The round count can also be read from a title: `[cost_per_round price="21.99" title="Federal 9mm 115gr FMJ 50 Rounds"]`.
* **Tools → Firearm Specs**: paste up to 500 product titles or caliber names and get a table of specs, plus a tab-separated copy for spreadsheets. Handy for cleaning up a product feed or a price list before an import.

= Private by design =

The parser runs on your server. The plugin makes no requests to other sites, sends no data anywhere, has no tracking and adds no links to your store unless you turn on the optional credit link (off by default).

The parser is the one behind [GunsNPrices](https://gunsnprices.com), a price comparison site for US gun and ammo stores, where it reads about 64,000 store listings. It is also available as a PHP library (`composer require gunsnprices/gunsnprices`), and for JavaScript and Python.

= Accuracy =

Measured on 63,755 gun and ammo listings from 40+ US stores. When a title contains the value, the parser gets it right 94 to 98% of the time (caliber 97.8% for firearms, rounds per box 96.6%, magazine capacity 95.6%, barrel length 94.4%), and the model name matches in 99.8% of cases. Many titles simply do not mention the caliber or the barrel length; those fields stay empty and you can fill them in by hand.

Titles in English from US retailers work best.

== Installation ==

1. Install the plugin from Plugins → Add New, or upload the zip, and activate it.
2. With WooCommerce: open Settings → Firearm Specs, choose where to show the cost per round and whether to create product attributes, then click "Fill specs now" to fill your existing products.
3. Without WooCommerce: add the Cost per round block or the `[cost_per_round]` shortcode to a post, or use Tools → Firearm Specs.

== Frequently Asked Questions ==

= Does it need WooCommerce? =

No. The block, the shortcode and the Tools page work on any WordPress site. The product tab, bulk actions, cost per round on prices and product attributes need WooCommerce 8.0 or later.

= Does it send my product data anywhere? =

No. Everything runs locally on your server. The plugin makes no external requests at all.

= Will it overwrite specs I typed in by hand? =

No. "Fill specs from title" and automatic filling only fill empty fields. Values are replaced only when you choose "Re-read specs from title (replace)" or tick "Replace values that are already filled in", and then only with values found in the title.

= Why is the cost per round not showing? =

It is shown for simple products whose product type is Ammunition and that have a price and a rounds-per-box value. Open the product's "Firearm & ammo specs" tab to check both. Variable products are not supported yet.

= Can shoppers filter products by caliber? =

Yes. Turn on "Copy the specs into global product attributes" in Settings → Firearm Specs, fill the specs, then add WooCommerce's "Filter by attribute" (or "Product Filters") block to your shop page and pick Caliber, Magazine capacity, Barrel length or Rounds per box.

= A title was read wrong. What can I do? =

Correct the value on the product; it will not be changed again unless you ask for it. If you think the parser should have read the title, write to app@gunsnprices.com with the title. Fixes go into the next release.

= Where are the specs stored? =

In product meta: `_gnpfs_kind`, `_gnpfs_caliber`, `_gnpfs_brand`, `_gnpfs_model`, `_gnpfs_mpn`, `_gnpfs_capacity`, `_gnpfs_barrel_length` and `_gnpfs_rounds`. They are kept when you deactivate or delete the plugin, unless you tick "Delete the specs saved on products when the plugin is deleted" in the settings.

= Is there a way to change the output? =

Developers can use the filters `gnpfs_parsed_specs`, `gnpfs_product_hints`, `gnpfs_attribute_taxonomies` and `gnpfs_cost_per_round_text`, and the action `gnpfs_product_filled`.

= What does the credit link do? =

If you turn it on, a "Specs: GunsNPrices" row with a link to gunsnprices.com is added under the specs table on product pages. It is off by default and nothing depends on it.

== Third-party code and data ==

The plugin bundles the gunsnprices PHP library 0.1.0 (https://github.com/gunsnprices/gunsnprices-php, MIT License) in `lib/gunsnprices/`, with its namespace renamed so it cannot clash with other plugins. Its model dictionaries and caliber list (`lib/gunsnprices/data/models.json` and `calibers.json`) are © GunsNPrices (https://gunsnprices.com) under the Creative Commons Attribution 4.0 license, which is compatible with the GPL. The source of the block's editor script is the shipped `blocks/cost-per-round/index.js`; there is no build step.

== Changelog ==

= 0.1.0 - 2026-11-10 =

* First release: specs tab, bulk actions and batch fill for WooCommerce products, cost per round on ammo prices, specs in the Additional information tab, optional global attributes, Cost per round block and shortcode, caliber normalizer under Tools, WP-CLI commands.

== Upgrade Notice ==

= 0.1.0 =

First release.
