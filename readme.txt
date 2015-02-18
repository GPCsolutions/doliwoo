=== Doliwoo ===
Contributors: GPC.solutions
Donate Link: TODO (https://gpcsolutions.fr/donate)
Tags: dolibarr, woocommerce, ecommerce, erp, integration
Author URI: https://gpcsolutions.fr
Plugin URI: https://gpcsolutions.github.io/doliwoo
Requires at least: 3.7.1
Tested up to: 3.7.1
Stable tag: master
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Integrate Dolibarr with a WooCommerce shop.

== Description ==
Doliwoo allows integration of Dolibarr into WooCommerce.

It leverages Dolibarr webservices feature to exchange data.

= Features =

**Dolibarr to WooCommerce**
* Periodic sync of product informations including image
* Link to existing thirdparties

**WooCommerce to Dolibarr**
* Create thirdparties
* Create client orders

**Known missing (TODO)**
* International VAT rates management
* Product's stock informations sync
* Invoicing
* Payments
* Multiple languages products management

**Known issues**
WooCommerce VAT management vastly differs from Dolibarr and we need equivalence tables.
Only French equivalence table is shipped at the moment.

= Requirements =

**PHP extensions**
* SOAP
* OpenSSL

**WordPress plugins**
* Woocommerce >= 2.0.0

**Dolibarr**
* HTTPS access with a valid certificate
* Dolibarr >= 3.4.0
* Modules:
    * Webservices
    * Thirdparties
    * Products
    * Categories (Products)
    * Orders

== Installation ==
* Make sure the WooCommerce plugin is installed into your WordPress
* Extract the zip file to the 'wp-content/plugins/' directory of your WordPress installation
* Activate the plugin from 'Plugins' WordPress settings page
* Go to 'WooCommerce' 'Settings' under the 'Integration' tab and configure the 'Doliwoo' section

== Frequently Asked Questions ==

= Is this plugin stable and useable in a production environment? =

NO! This is alpha code. This project started as an internal proof of concept and is not mature yet.

= OK, so how can I make it happen then? =

You can help by testing, providing detailed bug reports, documentation or even code.
Or you can buy paid support and/or development services from [GPC.solutions](https://gpcsolutions.fr).

= Why do I need to use HTTPS with a good known SSL certificate? =

Otherwise SOAP requests will fail.

This is a security feature to make sure your important data is properly encrypted in transit between WooCommerce and Dolibarr.

You may allow insecure requests by tweaking the source code if you know what you're doing.

== Screenshots ==

1. FIXME: Placeholder

== Changelog ==

= 0.0.1 =

* TODO: Unreleased

== Upgrade Notice ==

= 0.0.1 =
TODO: Unreleased
