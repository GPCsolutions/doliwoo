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
 * Dolibarr interactions
 */

/**
 * Class Dolibarr
 */
class Dolibarr {
	/** @var WC_Logger Logging */
	public $logger;

	/** @var Doliwoo */
	public $Doliwoo;

	/** @var WC_Tax_Doliwoo WooCommerce taxes informations */
	public $taxes;

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
		$this->taxes = new WC_Tax_Doliwoo();

		$this->Doliwoo = new Doliwoo();
		$this->Doliwoo->get_settings();

		$dolibarr_ws_url = $this->Doliwoo->settings->webservs_url . 'server_order.php?wsdl';

		// Set the WebService URL
		$soap_client = new SoapClient(
			null,
			array(
				'location' => $dolibarr_ws_url,
				'uri'      => "http://www.dolibar.org/ns/"
			)
		);
		$order       = new DolibarrOrder();

		// Fill this array with all data required to create an order in Dolibarr
		$user_id = get_current_user_id();
		if ( '' == $user_id ) {
			// default to the generic user
			$thirdparty_id = $this->Doliwoo->settings->dolibarr_generic_id;
		} else {
			$thirdparty_id = get_user_meta( $user_id, 'dolibarr_id',
				true );
		}
		if ( '' != $thirdparty_id ) {
			$order->thirdparty_id = $thirdparty_id;
		} else {
			if ( get_user_meta( $user_id, 'billing_company', true )
			     == ''
			) {
				update_user_meta( $user_id, 'billing_company',
					$_POST['billing_company'] );
			}
			$this->dolibarr_create_thirdparty_if_not_exists( $user_id );
			$order->thirdparty_id = get_user_meta( $user_id,
				'dolibarr_id', true );
		}
		$order->date   = date( 'Ymd' );
		$order->status = 1;

		$this->create_order_lines( $order );

		$soap_client->createOrder( $this->Doliwoo->ws_auth, $order );
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
		if ( $result ) {
			if (
				$result['thirdparty']
				&& get_user_meta( $user_id, 'dolibarr_id', true )
				   != $result['thirdparty']->id
			) {
				update_user_meta( $user_id, 'dolibarr_id',
					$result['thirdparty']->id );
			} elseif ( null === $result['thirdparty'] ) {
				$res
					= $this->dolibarr_create_thirdparty( $user_id );
				if ( 'OK' == $res['result']->result_code ) {
					update_user_meta( $user_id, 'dolibarr_id',
						$res->id );
				}
			}
		}
	}

	/**
	 * Checks if a thirdparty exists in Dolibarr
	 *
	 * @param int $user_id wordpress ID of an user
	 *
	 * @return int $result  array with the request results if it succeeds, null if there's an error
	 */
	private function dolibarr_thirdparty_exists( $user_id ) {
		$this->Doliwoo = new Doliwoo();
		$this->Doliwoo->get_settings();
		$dolibarr_ws_url = $this->Doliwoo->settings->webservs_url
		                   . 'server_thirdparty.php?wsdl';

		// Set the WebService URL
		$soap_client = new SoapClient( $dolibarr_ws_url );

		$dol_id = get_user_meta( $user_id, 'dolibarr_id', true );

		// if the user has a Dolibarr ID, use it, else use his company name
		if ( $dol_id ) {
			$result = $soap_client->getThirdParty( $this->Doliwoo->ws_auth,
				$dol_id );
		} else {
			$result = $soap_client->getThirdParty( $this->Doliwoo->ws_auth,
				'', get_user_meta( $user_id, 'billing_company',
					true ) );
		}
		if ( $result ) {
			return $result;
		} else {
			return null;
		}
	}

	/**
	 * Creates a thirdparty in Dolibarr via webservice using WooCommerce user data
	 *
	 * @param int $user_id a Wordpress user id
	 *
	 * @return int $result    the SOAP response
	 */
	public function dolibarr_create_thirdparty( $user_id ) {
		$this->Doliwoo = new Doliwoo();
		$this->Doliwoo->get_settings();

		$dolibarr_ws_url = $this->Doliwoo->settings->webservs_url
		                   . 'server_thirdparty.php?wsdl';
		// Set the WebService URL
		$soap_client = new SoapClient(
			null,
			array(
				'location' => $dolibarr_ws_url,
				'uri'      => "http://www.dolibar.org/ns/"
			)
		);

		$ref        = get_user_meta( $user_id, 'billing_company',
			true );
		$individual = 0;
		if ( '' == $ref ) {
			$ref        = get_user_meta( $user_id,
				'billing_last_name', true );
			$individual = 1;
		}

		$new_thirdparty = new DolibarrThirdparty();

		$new_thirdparty->ref      = $ref;
		$new_thirdparty->status   = '1';
		$new_thirdparty->client   = '1';
		$new_thirdparty->supplier = '0';

		$new_thirdparty->address       = get_user_meta(
			$user_id, 'billing_address', true );
		$new_thirdparty->zip           = get_user_meta(
			$user_id, 'billing_postcode', true );
		$new_thirdparty->town          = get_user_meta(
			$user_id, 'billing_city', true );
		$new_thirdparty->country_code  = get_user_meta(
			$user_id, 'billing_country', true );
		$new_thirdparty->supplier_code = '0';
		$new_thirdparty->phone         = get_user_meta(
			$user_id, 'billing_phone', true );
		$new_thirdparty->email         = get_user_meta(
			$user_id, 'billing_email', true );
		$new_thirdparty->individual    = $individual;
		$new_thirdparty->firstname     = get_user_meta(
			$user_id, 'billing_first_name', true );

		$result = $soap_client->createThirdParty( $this->Doliwoo->ws_auth, $new_thirdparty );

		return $result;
	}

	/**
	 * Create order lines
	 *
	 * @param DolibarrOrder $order The order to add lines to
	 */
	private function create_order_lines( $order ) {
		$order->lines = array();

		foreach ( WC()->cart->cart_contents as $product ) {
			/** @var WC_Product $woocommerce_product */
			$woocommerce_product = $product['data'];

			$line = new DolibarrOrderLine();
			$line->type
			      = get_post_meta( $product['product_id'],
				'dolibarr_type', 1 );
			$line->desc
			      = $woocommerce_product->post->post_content;
			$line->product_id
			      = get_post_meta( $product['product_id'],
				'dolibarr_id', 1 );

			$rates = $this->taxes->get_rates( $woocommerce_product->get_tax_class() );
			// We get the first one
			$line->vat_rate = array_values( $rates )[0]['rate'];

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
		$this->taxes   = new WC_Tax_Doliwoo();
		$this->Doliwoo = new Doliwoo();
		$this->Doliwoo->get_settings();

		// Set the WebService URL
		try {
			$soap_client = new SoapClient(
				$this->Doliwoo->settings->webservs_url
				. 'server_productorservice.php?wsdl'
			);
		} catch ( SoapFault $exception ) {
			$this->logger->add( 'doliwoo', $exception->getMessage() );

			// Do nothing.
			return;
		}

		// Get all products that are meant to be displayed on the website
		$result
			= $soap_client->getProductsForCategory( $this->Doliwoo->ws_auth,
			$this->Doliwoo->settings->dolibarr_category_id );

		if ( $result['result']->result_code == 'OK' ) {
			$dolibarr_products = $result['products'];
			foreach ( $dolibarr_products as $dolibarr_product ) {
				if ( $this->dolibarr_product_exists( $dolibarr_product->id ) ) {
					$post_id = 0;
				} else {
					$post    = array(
						'post_title'   => $dolibarr_product->label,
						'post_content' => $dolibarr_product->description,
						'post_status'  => 'publish',
						'post_type'    => 'product',
					);
					$post_id = wp_insert_post( $post );
				}

				if ( 0 < $post_id ) {
					// Post metas management
					add_post_meta( $post_id, 'dolibarr_id',
						$dolibarr_product->id, true );
					add_post_meta( $post_id, 'dolibarr_type', $dolibarr_product->type,
						true );
					update_post_meta( $post_id, '_regular_price',
						$dolibarr_product->price_net );
					update_post_meta( $post_id, '_sale_price',
						$dolibarr_product->price_net );
					update_post_meta( $post_id, '_price',
						$dolibarr_product->price_net );
					update_post_meta( $post_id, '_visibility',
						'visible' );
					update_post_meta( $post_id, '_tax_class',
						$this->taxes->get_tax_class( $dolibarr_product->vat_rate ) );

					// Stock management
					if ( get_option( 'woocommerce_manage_stock' )
					     == 'yes'
					) {
						if ( 0 < $dolibarr_product->stock_real ) {
							update_post_meta( $post_id,
								'_stock_status', 'instock' );
							update_post_meta( $post_id, '_stock',
								$dolibarr_product->stock_real );
						}
					}

					// Product images management
					if ( $dolibarr_product->images ) {
						$this->import_product_images( $dolibarr_product, $post_id );
					}

					// Cleanup
					wc_delete_product_transients( $post_id );
				}
			}
		}
	}

	/**
	 * Checks for the existence of a product in Wordpress database
	 *
	 * @param  int $dolibarr_id ID of a product in Dolibarr
	 *
	 * @return bool $exists
	 */
	private function dolibarr_product_exists( $dolibarr_id ) {
		$args  = array(
			'post_type'  => 'product',
			'meta_key'   => 'dolibarr_id',
			'meta_value' => $dolibarr_id
		);
		$query = new WP_Query( $args );

		return $query->have_posts();
	}

	/**
	 * Import Dolibarr product images
	 *
	 * @param StdClass $dolibarr_product The Dolibarr product
	 * @param int $post_id The WooCommerce product
	 */
	private function import_product_images( $dolibarr_product, $post_id ) {
		$image_attachment_ids = $this->get_product_image( $dolibarr_product,
			$post_id );

		// Fill the image gallery
		update_post_meta( $post_id,
			'_product_image_gallery',
			implode( ',', $image_attachment_ids ) );

		// Use the first image as the product thumbnail
		update_post_meta( $post_id, '_thumbnail_id',
			$image_attachment_ids[0] );
	}

	/**
	 * Webservice calls to get the product's images
	 *
	 * @param stdClass $dolibarr_product SOAP product object
	 * @param int $post_id WooCommerce product ID
	 *
	 * @return int[] Attachment IDs
	 */
	private function get_product_image(
		stdClass $dolibarr_product, $post_id
	) {
		$this->Doliwoo = new Doliwoo();
		$this->Doliwoo->get_settings();

		$soap_client = new SoapClient(
			$this->Doliwoo->settings->webservs_url
			. 'server_other.php?wsdl'
		);

		$file_array = array();
		$attach_ids = array();

		foreach ( $dolibarr_product->images as $images ) {
			// Get the image from Dolibarr
			$result = $soap_client->getDocument(
				$this->Doliwoo->ws_auth,
				'product',
				$dolibarr_product->dir . $images->photo
			);

			if ( 'OK' == $result['result']->result_code ) {
				$file_array['name'] = $images->photo;
				$file_array['tmp_name']
				                    =
					sys_get_temp_dir() . DIRECTORY_SEPARATOR . $images->photo;
				file_put_contents( $file_array['tmp_name'],
					base64_decode( $result['document']->content ) );

				$res = media_handle_sideload( $file_array, $post_id );

				// handle errors nicely ( logging )
				if ( true === is_wp_error( $res ) ) {
					$message = $res->get_error_message();
					$this->logger->add( 'doliwoo', $message );
				} else {
					$attach_ids[] = $res;
				}
			}
		}

		return $attach_ids;
	}
}
