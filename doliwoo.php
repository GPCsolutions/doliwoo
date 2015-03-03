<?php
/*
Plugin Name: DoliWoo
Plugin URI: http://gpcsolutions.github.io/doliwoo
Description: Dolibarr WooCommerce integration
Version: 0.0.2-alpha
Author: GPC.solutions
Author URI: http://gpcsolutions.fr
License: GPL-3.0+
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

load_plugin_textdomain(
	'doliwoo',
	false,
	dirname( plugin_basename( __FILE__ ) ) . '/languages/'
);

// Check required extensions
if ( false === extension_loaded( 'soap' ) && false === extension_loaded( 'openssl' ) ) {
	esc_html_e( __( 'This plugin needs SOAP and OpenSSL PHP extensions.' ) );
	exit;
}
if ( false === extension_loaded( 'soap' ) ) {
	esc_html_e( __( 'This plugin needs SOAP PHP extension.' ) );
	exit;
}
if ( false === extension_loaded( 'openssl' ) ) {
	esc_html_e( __( 'This plugin needs OpenSSL PHP extension.' ) );
	exit;
}

// Make sure the settings class is available
if ( ! class_exists( 'WC_Integration_Doliwoo_Settings' ) ) :

	// If WooCommerce is active
	if ( in_array(
		'woocommerce/woocommerce.php',
		apply_filters( 'active_plugins', get_option( 'active_plugins' ) )
	) ) {
		if ( ! class_exists( 'Doliwoo' ) ) {

			/**
			 * Class Doliwoo
			 *
			 * Dolibarr Integration for WooCommerce
			 */
			class Doliwoo {

				/** @var WC_Integration_Doliwoo_Settings Doliwoo Settings */
				public $settings;

				/** @var array SOAP authentication parameters */
				public $ws_auth = array();

				/** @var Woocomerce_Parameters custom parameters */
				public $woocommerce_parameters;

				/** @var Dolibarr external requests */
				public $dolibarr;

				/**
				 * Constructor
				 */
				public function __construct() {
					require_once 'includes/class-doliwoo-parameters.php';
					require_once 'includes/class-dolibarr.php';

					$this->woocommerce_parameters = new Woocomerce_Parameters();
					$this->dolibarr               = new Dolibarr();

					// Initialize plugin settings
					add_action( 'plugins_loaded', array( $this, 'init' ) );

					// Add a link to settings
					add_filter(
						'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' )
					);

					// Setup dolibarr environment
					add_action( 'woocommerce_loaded', array( &$this->dolibarr, 'set_woocommerce' ) );
					add_action( 'woocommerce_init', array( $this, 'set_settings' ) );

					// Create custom tax classes and VAT rates on plugin settings saved
					add_action( 'woocommerce_settings_saved', array( &$this->dolibarr->taxes, 'create_custom_tax_classes' ) );

					// Import Dolibarr products on plugin settings saved
					add_action( 'woocommerce_settings_saved', array( &$this->dolibarr, 'dolibarr_import_products' ) );

					// Reschedule products imporrt
					add_action( 'woocommerce_settings_saved', array( $this, 'reschedule_import_products' ) );

					// Create a Dolibarr order on each WooCommerce order
					add_action(
						'woocommerce_checkout_order_processed',
						array( &$this->dolibarr, 'dolibarr_create_order' )
					);

					// Add Dolibarr import products hook
					add_action( 'import_products', array( &$this->dolibarr, 'dolibarr_import_products' ) );

					// Dolibarr ID User admin custom meta
					add_filter( 'manage_users_columns', array( &$this->woocommerce_parameters, 'user_columns' ) );
					add_action( 'show_user_profile', array( &$this->woocommerce_parameters, 'customer_meta_fields' ) );
					add_action( 'edit_user_profile', array( &$this->woocommerce_parameters, 'customer_meta_fields' ) );
					add_action(
						'personal_options_update',
						array( &$this->woocommerce_parameters, 'save_customer_meta_fields' )
					);
					add_action(
						'edit_user_profile_update',
						array( &$this->woocommerce_parameters, 'save_customer_meta_fields' )
					);
					add_action(
						'manage_users_custom_column',
						array( &$this->woocommerce_parameters, 'user_column_values' ),
						10, // Prio
						3 // Args count
					);

					// Schedule the import of product data from Dolibarr
					register_activation_hook( __FILE__, 'activation' );
					register_deactivation_hook( __FILE__, 'deactivation' );
				}

				/**
				 * Initialize the plugin
				 *
				 * @return void
				 */
				public function init() {

					require_once 'includes/class-tax-doliwoo.php';

					// Checks if WooCommerce is installed.
					if ( class_exists( 'WC_Integration' ) ) {
						// Include our integration class.
						require_once 'includes/settings.php';
						// Register the integration.
						add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
					}
					$this->dolibarr->taxes = new WC_Tax_Doliwoo();
				}

				/**
				 * On plugin activation
				 *
				 * @return void
				 */
				public function activation() {
					// Schedule product import with a sensible default
					wp_schedule_event( time(), 'daily', 'import_products' );
				}

				/**
				 * On plugin deactivation
				 *
				 * @return void
				 */
				public function deactivation() {
					// Unschedule product import
					wp_clear_scheduled_hook( 'import_products' );
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
				 * Reschedules the automatic import of Dolibarr products
				 *
				 * @return void
				 */
				public function reschedule_import_products() {
					$delay = $this->settings->delay_update;
					wp_clear_scheduled_hook( 'import_products' );
					wp_schedule_event( time(), $delay, 'import_products' );
				}

				/**
				 * Show action links on the plugin screen.
				 *
				 * @param	mixed $links Plugin Action links
				 * @return	array
				 */
				public static function plugin_action_links( $links ) {
					$action_links = array(
						'settings' => '<a href="' . admin_url(
							'admin.php?page=wc-settings&tab=integration&section=doliwoo'
						) . '" title="' . esc_attr(
							__( 'View DoliWoo Settings', 'doliwoo' )
						) . '">' . esc_attr(
							__( 'Settings', 'doliwoo' )
						) . '</a>',
					);

					return array_merge( $action_links, $links );
				}

				/**
				 * Set Dolibarr settings from WooCommerce integration settings
				 *
				 * @return void
				 */
				public function set_settings() {
					// Load settings
					$integrations = WC()->integrations->get_integrations();
					$this->dolibarr->settings = $integrations['doliwoo'];
					$this->dolibarr->update_settings();
				}
			}
		}
	} else {
		// WooCommerce is not available
		esc_html_e( __( 'This extension needs WooCommerce' ) );
		exit;
	}

	$Doliwoo = new Doliwoo();

	endif;
