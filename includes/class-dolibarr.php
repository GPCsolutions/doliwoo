<?php
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
 * Dolibarr interactions.
 *
 * All Dolibarr interactions triggerred by WooCommerce are managed here.
 *
 * @package DoliWoo
 */

/**
 * Dolibarr interactions.
 */
class Doliwoo_Dolibarr {
	/** @var WC_Logger Logging */
	public $logger;

	/** @var Doliwoo_WC_Integration Settings */
	public $settings;

	/** @var Doliwoo_WC_Tax WooCommerce taxes informations */
	public $taxes;

	/** @var string Webservice endpoint */
	private $ws_endpoint;

	/** @var array Webservice authentication parameters */
	private $ws_auth;

	/**
	 * Dolibarr webservices endpoints
	 */
	const ORDER_ENDPOINT      = 'server_order.php';
	const THIRDPARTY_ENDPOINT = 'server_thirdparty.php';
	const PRODUCT_ENDPOINT    = 'server_productorservice.php';
	const OTHER_ENDPOINT      = 'server_other.php';
	const WSDL_MODE           = '?wsdl';

	/**
	 * Init parameters
	 */
	public function __construct() {
		require_once 'class-dolibarr-soap-elements.php';
	}

	/**
	 * Called when woocommerce is ready
	 */
	public function set_woocommerce() {
		$this->logger = new WC_Logger();
	}

	/**
	 * Hooks on process_checkout()
	 *
	 * While the order is processed, use the data to create a Dolibarr order via webservice
	 *
	 * @return void
	 */
	public function dolibarr_create_order() {
		/*
		 * We use non WSDL mode to workaround Dolibarr broken declaration marking all the fields as required
		 * when they're not.
		 */
		try {
			$soap_client = new SoapClient(
				null,
				array(
					'location' => $this->ws_endpoint . self::ORDER_ENDPOINT,
					'uri'      => 'http://www.dolibar.org/ns/',
				)
			);
		} catch ( SoapFault $exception ) {
			$this->logger->add( 'doliwoo', $exception->getMessage() );

			// Do nothing.
			return;
		}

		$order = new Dolibarr_Order();

		// Fill this array with all data required to create an order in Dolibarr
		$user_id = get_current_user_id();
		if ( '' == $user_id ) {
			// default to the generic user
			$thirdparty_id = $this->settings->dolibarr_generic_id;
		} else {
			$thirdparty_id = get_user_meta( $user_id, 'dolibarr_id', true );
		}
		if ( '' != $thirdparty_id ) {
			$order->thirdparty_id = intval( $thirdparty_id );
		} else {
			if ( get_user_meta( $user_id, 'billing_company', true ) == '' ) {
				update_user_meta( $user_id, 'billing_company', $_POST['billing_company'] );
			}
			$this->dolibarr_create_thirdparty_if_not_exists( $user_id );
			$order->thirdparty_id = intval( get_user_meta( $user_id, 'dolibarr_id', true ) );
		}
		$order->date   = date( 'Ymd' );
		$order->status = 1;

		$this->create_order_lines( $order );

		try {
			$result = $soap_client->createOrder( $this->ws_auth, $order );
		} catch ( SoapFault $exception ) {
			$this->logger->add(
				'doliwoo',
				'createOrder request:' . $exception->getMessage()
			);

			// Do nothing.
			return;
		}

		if ( ! ( 'OK' == $result['result']->result_code ) ) {
			$this->logger->add(
				'doliwoo',
				'createOrder response: ' . $result['result']->result_code . ': ' . $result['result']->result_label
			);

			// Do nothing
			return;
		}
	}

	/**
	 * Creates a thirdparty in Dolibarr via webservice using WooCommerce user data, if it doesn't already exists
	 *
	 * @param  int $user_id a Wordpress user id
	 *
	 * @return void
	 */
	private function dolibarr_create_thirdparty_if_not_exists(
		$user_id
	) {
		$result = $this->dolibarr_thirdparty_exists( $user_id );

		if ( null === $result ) {
			// Does not exist, create it
			$result = $this->dolibarr_create_thirdparty( $user_id );
		}
		update_user_meta( $user_id, 'dolibarr_id', $result['id'] );
	}

	/**
	 * Checks if a thirdparty exists in Dolibarr
	 *
	 * @param int $user_id Wordpress ID of an user
	 *
	 * @return int $result Array with the request results if it succeeds, null if there's an error
	 */
	private function dolibarr_thirdparty_exists( $user_id ) {
		try {
			$soap_client = new SoapClient(
				$this->ws_endpoint . self::THIRDPARTY_ENDPOINT . self::WSDL_MODE
			);
		} catch ( SoapFault $exception ) {
			$this->logger->add( 'doliwoo', $exception->getMessage() );

			// Do nothing.
			return null;
		}

		$dol_id = get_user_meta( $user_id, 'dolibarr_id', true );
		$dol_ref = get_user_meta( $user_id, 'billing_company', true );

		// If the user has a Dolibarr ID, use it, else search his company name
		if ( $dol_id ) {
			$dol_ref = null;
		} else {
			$dol_id = '';
		}

		try {
			$result = $soap_client->getThirdParty( $this->ws_auth, $dol_id, $dol_ref );
		} catch ( SoapFault $exception ) {
			$this->logger->add(
				'doliwoo',
				'getThirdParty request: ' . $exception->getMessage()
			);

			// Do nothing.
			return null;
		}

		if ( ! ( 'OK' == $result['result']->result_code ) ) {
			$this->logger->add(
				'doliwoo',
				'getThirdParty response: ' . $result['result']->result_code . ': ' . $result['result']->result_label
			);

			// Do nothing
			return null;
		}

		return $result;
	}

	/**
	 * Creates a thirdparty in Dolibarr via webservice using WooCommerce user data
	 *
	 * @param int $user_id A Wordpress user ID
	 *
	 * @return array() $result The SOAP response
	 */
	public function dolibarr_create_thirdparty( $user_id ) {
		/*
		 * We use non WSDL mode to workaround Dolibarr broken declaration marking all the fields as required
		 * when they're not.
		 */
		try {
			$soap_client = new SoapClient(
				null,
				array(
					'location' => $this->ws_endpoint . self::THIRDPARTY_ENDPOINT,
					'uri'      => 'http://www.dolibar.org/ns/',
				)
			);
		} catch ( SoapFault $exception ) {
			$this->logger->add( 'doliwoo', $exception->getMessage() );

			// Do nothing.
			return null;
		}

		$ref        = get_user_meta( $user_id, 'billing_company', true );
		$individual = 0;
		if ( '' == $ref ) {
			// We could not find a company, let's get an indivual
			$ref        = get_user_meta( $user_id, 'billing_last_name', true );
			$individual = 1;
		}

		$new_thirdparty = new Dolibarr_Thirdparty();

		$new_thirdparty->ref        = $ref; // Company name or individual last name
		$new_thirdparty->individual = $individual; // Individual
		$new_thirdparty->firstname = get_user_meta( $user_id, 'billing_first_name', true );
		$new_thirdparty->status    = '1'; // Active
		$new_thirdparty->client    = '1'; // Is a client
		$new_thirdparty->supplier  = '0'; // Is not a supplier
		$new_thirdparty->address = get_user_meta( $user_id, 'billing_address', true );
		$new_thirdparty->zip = get_user_meta( $user_id, 'billing_postcode', true );
		$new_thirdparty->town = get_user_meta( $user_id, 'billing_city', true );
		$new_thirdparty->country_code = get_user_meta( $user_id, 'billing_country', true );
		$new_thirdparty->phone = get_user_meta( $user_id, 'billing_phone', true );
		$new_thirdparty->email = get_user_meta( $user_id, 'billing_email', true );

		try {
			$result = $soap_client->createThirdParty( $this->ws_auth, $new_thirdparty );
		} catch ( SoapFault $exception ) {
			$this->logger->add(
				'doliwoo',
				'createThirdParty request: ' . $exception->getMessage()
			);

			// Do nothing.
			return null;
		}

		if ( ! ( 'OK' == $result['result']->result_code ) ) {
			$this->logger->add(
				'doliwoo',
				'createThirdParty response: ' . $result['result']->result_code . ': ' . $result['result']->result_label
			);

			// Do nothing
			return null;
		}

		return $result;
	}

	/**
	 * Create order lines
	 *
	 * @param Dolibarr_Order $order The order to add lines to
	 */
	private function create_order_lines( $order ) {
		$order->lines = array();

		foreach ( WC()->cart->cart_contents as $product ) {
			/** @var WC_Product $woocommerce_product */
			$woocommerce_product = $product['data'];

			$line             = new Dolibarr_Order_Line();
			$line->type       = intval( get_post_meta( $product['product_id'], 'dolibarr_type', true ) );
			$line->desc       = $woocommerce_product->post->post_content;
			$line->product_id = intval( get_post_meta( $product['product_id'], 'dolibarr_id', true ) );

			$rates = $this->taxes->get_rates( $woocommerce_product->get_tax_class() );
			$rates = array_values( $rates );
			// We get the first one
			$line->vat_rate = $rates[0]['rate'];

			$line->qty       = $product['quantity'];
			$line->price     = floatval( $woocommerce_product->get_price() );
			$line->unitprice = floatval( $woocommerce_product->get_price() );
			$line->total_net = floatval( $woocommerce_product->get_price_excluding_tax( $line->qty ) );
			$line->total     = floatval( $woocommerce_product->get_price_including_tax( $line->qty ) );
			$line->total_vat = $line->total - $line->total_net;
			$order->lines[]  = $line;
		}
	}

	/**
	 * Pull products data from Dolibarr via webservice and save it in Wordpress
	 *
	 * @return void
	 */
	public function dolibarr_import_products() {
		try {
			$soap_client = new SoapClient(
				$this->ws_endpoint . self::PRODUCT_ENDPOINT . self::WSDL_MODE
			);
		} catch ( SoapFault $exception ) {
			$this->logger->add( 'doliwoo', $exception->getMessage() );

			// Do nothing.
			return;
		}

		// Get all products that are meant to be displayed on the website
		try {
			$result = $soap_client->getProductsForCategory(
				$this->ws_auth,
				$this->settings->dolibarr_category_id
			);
		} catch ( SoapFault $exception ) {
			$this->logger->add(
				'doliwoo',
				'getProductsForCategory request: ' . $exception->getMessage()
			);

			// Do nothing.
			return;
		}

		if ( ! ( 'OK' == $result['result']->result_code ) ) {
			$this->logger->add(
				'doliwoo',
				'getProductsForCategory response: ' . $result['result']->result_code . ': ' . $result['result']->result_label
			);

			// Do nothing
			return;
		}

		/** @var Dolibarr_Product[] $dolibarr_products */
		$dolibarr_products = $result['products'];

		if ( ! empty( $dolibarr_products ) ) {
			foreach ( $dolibarr_products as $dolibarr_product ) {
				if ( 0 == $dolibarr_product->status_tosell ) {
					// This product is not for sale, let's skip it.
					continue;
				}

				$existing_product = $this->dolibarr_product_exists( $dolibarr_product->id );
				if ( $existing_product ) {
					// Update the product
					$post = array(
						'ID'           => $existing_product->ID,
						'post_title'   => $dolibarr_product->label,
						'post_content' => $dolibarr_product->description,
					);
					$post_id = wp_update_post( $post );
				} else {
					// Create a new product
					$post    = array(
						'post_title'   => $dolibarr_product->label,
						'post_content' => $dolibarr_product->description,
						'post_status'  => 'publish',
						'post_type'    => 'product',
					);
					$post_id = wp_insert_post( $post );
				}

				// Error management (logging)
				if ( is_wp_error( $post_id ) ) {
					/** @var WP_Error $post_id */
					$this->logger->add( 'doliwoo', $post_id->get_error_message() );
				}

				if ( 0 < $post_id && ! is_wp_error( $post_id ) ) {
					/** @var int $post_id */
					$this->update_product_attributes( $dolibarr_product, $post_id );
				}
			}
		}
	}

	/**
	 * Checks for the existence of a product in Wordpress database
	 *
	 * @param  int $dolibarr_id ID of a product in Dolibarr
	 *
	 * @return WP_POST
	 */
	private function dolibarr_product_exists( $dolibarr_id ) {
		$args  = array(
			'post_type'  => 'product',
			'meta_key'   => 'dolibarr_id',
			'meta_value' => $dolibarr_id,
		);
		$query = new WP_Query( $args );

		return $query->post;
	}

	/**
	 * Update the product attributes
	 *
	 * @param Dolibarr_Product $dolibarr_product The dolibarr product
	 * @param int $post_id The woocommerce product ID
	 */
	private function update_product_attributes( $dolibarr_product, $post_id ) {

		/** @var int $post_id */

		// Post metas management
		add_post_meta( $post_id, 'dolibarr_id', $dolibarr_product->id, true );
		add_post_meta( $post_id, 'dolibarr_type', $dolibarr_product->type, true );
		update_post_meta( $post_id, '_sku', $dolibarr_product->ref );
		update_post_meta( $post_id, '_purchase_note', $dolibarr_product->note );
		update_post_meta( $post_id, '_regular_price', $dolibarr_product->price_net );
		update_post_meta( $post_id, '_sale_price', $dolibarr_product->price_net );
		update_post_meta( $post_id, '_price', $dolibarr_product->price_net );
		update_post_meta( $post_id, '_visibility', 'visible' );
		update_post_meta(
			$post_id,
			'_tax_class',
			$this->taxes->get_tax_class( $dolibarr_product->vat_rate )
		);
		update_post_meta( $post_id, '_manage_stock', 'no' );

		// Stock management
		if ( 'yes' == get_option( 'woocommerce_manage_stock' ) ) {
			if ( 0 < $dolibarr_product->stock_real ) {
				update_post_meta( $post_id, '_stock_status', 'instock' );
				update_post_meta( $post_id, '_stock', $dolibarr_product->stock_real );
				update_post_meta( $post_id, '_manage_stock', 'yes' );
			}
		}

		// Product images management
		if ( ! empty( $dolibarr_product->images ) ) {
			$this->import_product_images( $dolibarr_product, $post_id );
		}

		// Cleanup
		wc_delete_product_transients( $post_id );
	}

	/**
	 * Import Dolibarr product images
	 *
	 * @param Dolibarr_Product $dolibarr_product The Dolibarr product
	 * @param int $post_id The WooCommerce product
	 */
	private function import_product_images( $dolibarr_product, $post_id ) {
		$image_attachment_ids = $this->get_product_image( $dolibarr_product, $post_id );

		// Use the first image as the product thumbnail
		update_post_meta( $post_id, '_thumbnail_id', array_shift( $image_attachment_ids ) );

		// Fill the image gallery
		update_post_meta( $post_id, '_product_image_gallery', implode( ',', $image_attachment_ids ) );
	}

	/**
	 * Webservice calls to get the product's images
	 *
	 * @param Dolibarr_Product $dolibarr_product SOAP product object
	 * @param int $post_id WooCommerce product ID
	 *
	 * @return int[] Attachment IDs
	 */
	private function get_product_image( $dolibarr_product, $post_id ) {
		try {
			$soap_client = new SoapClient(
				$this->ws_endpoint . self::OTHER_ENDPOINT . self::WSDL_MODE
			);
		} catch ( SoapFault $exception ) {
			$this->logger->add( 'doliwoo', $exception->getMessage() );

			// Do nothing.
			return null;
		}

		$file_array = array();
		$attach_ids = array();

		foreach ( $dolibarr_product->images as $images ) {
			// Get the image from Dolibarr
			try {
				$result = $soap_client->getDocument(
					$this->ws_auth,
					'product',
					$dolibarr_product->dir . $images->photo
				);
			} catch ( SoapFault $exception ) {
				$this->logger->add(
					'doliwoo',
					'getDocument request:' . $exception->getMessage()
				);

				// Do nothing.
				continue;
			}

			if ( ! ( 'OK' == $result['result']->result_code ) ) {
				$this->logger->add(
					'doliwoo',
					'getDocument response: ' . $result['result']->result_code . ': ' . $result['result']->result_label
				);

				// Do nothing
				continue;
			}

			$file_array['name']     = $images->photo;
			$file_array['tmp_name'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $images->photo;
			file_put_contents(
				$file_array['tmp_name'],
				base64_decode( $result['document']->content )
			);

			$res = media_handle_sideload( $file_array, $post_id );

			// Handle errors nicely ( logging )
			if ( is_wp_error( $res ) ) {
				$message = $res->get_error_message();
				$this->logger->add( 'doliwoo', $message );
			} else {
				$attach_ids[] = $res;
			}
		}

		return $attach_ids;
	}

	/**
	 * Set settings in a more useable form
	 */
	public function update_settings() {
		$this->ws_endpoint = $this->settings->dolibarr_ws_endpoint;
		$this->ws_auth = array(
			'sourceapplication' => $this->settings->sourceapplication,
			'dolibarrkey' => $this->settings->dolibarr_key,
			'login' => $this->settings->dolibarr_login,
			'password' => $this->settings->dolibarr_password,
			'entity' => $this->settings->dolibarr_entity,
		);
	}
}
