# DoliWoo #
**Contributors:**       GPC.solutions  
**Tags:**               dolibarr, woocommerce, ecommerce, erp, integration  
**Author URI:**         https://gpcsolutions.fr  
**Plugin URI:**         https://gpcsolutions.github.io/doliwoo  
**Requires at least:**  3.7.1  
**Tested up to:**       3.7.1  
**Stable tag:**         master  
**License:**            GPL-3.0+  
**License URI:**        http://www.gnu.org/licenses/gpl-3.0.html  

Integrate Dolibarr with a WooCommerce shop.

## Description ##
Doliwoo allows integration of Dolibarr into WooCommerce.

It leverages Dolibarr webservices feature to exchange data.

### Features ###

**Dolibarr to WooCommerce**

* Periodic sync of products informations including images and stock informations
* Link customers to existing thirdparties

**WooCommerce to Dolibarr**

* Create thirdparties
* Create customer orders

**Known missing (TODO)**

* International VAT rates management
* Products stock informations resync on orders
* Invoicing
* Payments
* Multiple languages products management

**Known issues**

WooCommerce VAT management vastly differs from Dolibarr and we need equivalence tables.  
Only French equivalence table is shipped at the moment.

### Requirements ###

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

## Installation ##

1. Make sure the WooCommerce plugin is installed into your WordPress
2. Extract the zip file to the 'wp-content/plugins/' directory of your WordPress installation
3. Activate the plugin from 'Plugins' WordPress settings page
4. Go to 'WooCommerce' 'Settings' under the 'Integration' tab and configure the 'Doliwoo' section

## Frequently Asked Questions ##

### Is this plugin stable and useable in a production environment? ###

NO! This is beta code. This project started as an internal proof of concept and has just been reviewed.  
But you're very welcome to test it on a pre-production environment.

### OK, so how can I make it happen then? ###

You can help by testing, providing detailed bug reports, documentation or even code.  
**Alternatively, you can buy paid support and/or development services from us:** [GPC.solutions](https://gpcsolutions.fr).  

### Why do I need to use HTTPS with a good known SSL certificate? ###

Otherwise SOAP requests will fail.  
This is a security feature to make sure your important data is properly encrypted in transit between WooCommerce and Dolibarr.  
You may allow insecure requests by tweaking the source code if you know what you're doing but we don't recommend that.  

## Screenshots ##

**1. FIXME:** Placeholder  

## Changelog ##

### 0.0.1 ###

* First beta release
* Periodically sync products from a Dolibarr category
* Use a generic thirdparty for sales without user creation
* Create or reuse a thirdparty for sales with a logged in user
* Create an order into Dolibarr for each sale

## Upgrade Notice ##

### 0.0.1 ###

N.A.
