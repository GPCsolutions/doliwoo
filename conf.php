<?php
/**
 * Template configuration file for the doliwoo module
 *
 * HOW TO USE:
 * Create a copy of that file named conf.php and fill the parameters according to your Dolibarr installation
 */

require_once 'nusoap/lib/nusoap.php';

// Dolibarr webservices URL without the endpoint. Don't forget the trailing slash.
$webservs_url   = 'https://mydolibarr.example.com/webservices/';
$ns             = 'http://www.dolibarr.org/ns/';
$authentication = array(
	// The Dolibarr webservice module authentication key
	'dolibarrkey'       => '',
	// Source application for the webservice requests. Can be customized if you have multiple shops.
	'sourceapplication' => 'WooCommerce',
	// The impersonnated Dolibarr user login. We recommend creating a user dedicated to webservices usage.
	'login'             => '',
	// The impersonnated Dolibarr user password. Make sure you use HTTPS to your Dolibarr!
	'password'          => '',
	// Only needed if using the Multicompany module, leaving it to 1 does no harm.
	'entity'            => '1'
);
// The ID of the Dolibarr Category to create products from.
$category_id = 1;
// The ID of the Dolibarr ThirdParty to use for generic transactions.
$generic_id = 1;
