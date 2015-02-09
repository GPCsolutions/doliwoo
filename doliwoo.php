<?php
/*
Plugin Name: Doliwoo
Plugin URI: TODO (https://gpcsolutions.fr/doliwoo)
Description: Dolibarr WooCommerce integration
Version: 0.0.1-alpha
Author: GPC.solutions
License: GPL3
Text Domain: doliwoo
Domain Path: /languages
*/

/* Copyright (C) 2013-2014 Cédric Salvador <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015 Maxime Lafourcade <mlafourcade@gpcsolutions.fr>
 * Copyright (C) 2015 Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * DoliWoo plugin
 *
 * Dolibarr WooCommerce integration.
 *
 * @package DoliWoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

load_plugin_textdomain( 'doliwoo',
	false,
	dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

// Check required extensions
if ( false === extension_loaded( 'soap' )
     && false === extension_loaded( 'openssl' )
) {
	echo __( 'You must enable extensions SOAP and OpenSSL' );
	exit;
}
if ( false === extension_loaded( 'soap' )  ) {
	echo __( 'You must enable extension SOAP' );
	exit;
}
if ( false === extension_loaded( 'openssl' )  ) {
	echo __( 'You must enable extension OpenSSL' );
	exit;
}

// Make sure the settings class is available
if ( ! class_exists( 'WC_Integration_Doliwoo_Settings' ) ) :

	// If WooCommerce is active
	if ( in_array( 'woocommerce/woocommerce.php',
		apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		if ( ! class_exists( 'Doliwoo' ) ) {

			/**
			 * Class Doliwoo
			 *
			 * Dolibarr Integration for WooCommerce
			 */
			class Doliwoo {

				/**
				 * @var WC_Integration_Doliwoo_Settings() Doliwoo Settings
				 */
				public $settings;

				/**
				 * @var array SOAP authentication parameters
				 */
				public $ws_auth = array();

				/**
				 * @var WC_Tax_Doliwoo() WooCommerce taxes informations
				 */
				public $taxes;

				/**
				 * @var Woocomerce_Parameters() custom parameters
				 */
				public $woocommerce_parameters;

				/**
				 * @var Dolibarr() external requests
				 */
				public $dolibarr;

				/**
				 * Constructor
				 */
				public function __construct() {
					include_once 'includes/class-doliwoo-parameters.php';
					include_once 'includes/class-dolibarr.php';

					$this->woocommerce_parameters = new Woocomerce_Parameters();
					$this->dolibarr = new Dolibarr();

					// Initialize plugin settings
					add_action( 'plugins_loaded', array( $this, 'init' ) );

					// Create custom tax classes and VAT rates on plugin settings saved
					add_action( 'woocommerce_settings_saved',
						array( &$this->taxes, 'create_custom_tax_classes' ) );

					// Import Dolibarr products on plugin settings saved
					add_action( 'woocommerce_settings_saved',
						array( &$this->dolibarr, 'dolibarr_import_products' ) );

					// Hook on woocommerce_checkout_process to create a Dolibarr order using WooCommerce order data
					add_action( 'woocommerce_checkout_order_processed',
						array( &$this->dolibarr, 'dolibarr_create_order' ) );

					// Schedule the import of product data from Dolibarr
					add_action( 'wp',
						array( &$this, 'schedule_import_products' ) );
					add_action( 'import_products',
						array( &$this->dolibarr, 'dolibarr_import_products' ) );

					// Dolibarr ID custom field
					add_filter( 'manage_users_columns',
						array( &$this->woocommerce_parameters, 'user_columns' ) );
					add_action( 'show_user_profile',
						array( &$this->woocommerce_parameters, 'customer_meta_fields' ) );
					add_action( 'edit_user_profile',
						array( &$this->woocommerce_parameters, 'customer_meta_fields' ) );
					add_action( 'personal_options_update',
						array( &$this->woocommerce_parameters, 'save_customer_meta_fields' ) );
					add_action( 'edit_user_profile_update',
						array( &$this->woocommerce_parameters, 'save_customer_meta_fields' ) );
					add_action( 'manage_users_custom_column',
						array( &$this->woocommerce_parameters, 'user_column_values' ), 10, 3 );
				}

				/**
				 * Initialize the plugin
				 */
				public function init() {

					include_once 'includes/class-tax-doliwoo.php';

					// Checks if WooCommerce is installed.
					if ( class_exists( 'WC_Integration' ) ) {
						// Include our integration class.
						include_once 'includes/settings.php';
						// Register the integration.
						add_filter( 'woocommerce_integrations',
							array( $this, 'add_integration' ) );
					}
					$this->taxes = new WC_Tax_Doliwoo();
				}

				/**
				 * Add a new integration to WooCommerce
				 *
				 * @param array $integrations Existing integrations
				 *
				 * @return string[] WooCommerce integrations
				 */
				public function add_integration( $integrations ) {
					$integrations[] = 'WC_Integration_Doliwoo_Settings';

					return $integrations;
				}

				/**
				 * Schedules the daily import of Dolibarr products
				 *
				 * @access public
				 * @return void
				 */
				public function schedule_import_products() {
					$delay = $this->settings->delay_update;
					if ( ! wp_next_scheduled( 'import_products' ) ) {
						wp_schedule_event( time(), $delay, 'import_products' );
					}
				}

				/**
				 * Extract settings from WooCommerce integration settings
				 */
				public function get_settings() {
					// Load settings
					$this->settings = WC()->integrations->get_integrations()['doliwoo-settings'];
					trailingslashit($this->settings->webservs_url);
					$this->ws_auth = array(
						'dolibarrkey'       => $this->settings->dolibarr_key,
						'sourceapplication' => $this->settings->sourceapplication,
						'login'             => $this->settings->dolibarr_login,
						'password'          => $this->settings->dolibarr_password,
						'entity'            => $this->settings->dolibarr_entity
					);
				}
			}
		}

	} else {
		// WooCommerce is not available
		echo __( 'This extension needs WooCommerce' );
		exit;
	}

	$Doliwoo = new Doliwoo();

endif;
