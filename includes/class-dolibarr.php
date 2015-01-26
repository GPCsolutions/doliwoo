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
 * Class Dolibarr
 */
class Dolibarr {
	/**
	 * @var Doliwoo()
	 */
	public $Doliwoo;

	/**
	 * @var WC_Tax_Doliwoo() WooCommerce taxes informations
	 */
	public $taxes;

	/**
	 * Hooks on process_checkout()
	 *
	 * While the order is processed, use the data to create a Dolibarr order via webservice
	 *
	 * @access public
	 * @return void
	 */
	public function dolibarr_create_order() {

		$this->taxes = new WC_Tax_Doliwoo();

		$this->Doliwoo  = new Doliwoo();
		$this->Doliwoo->get_settings();

		$dolibarr_ws_url = $this->Doliwoo->settings->webservs_url . 'server_order.php?wsdl';

		// Set the WebService URL
		$soap_client = new SoapClient( $dolibarr_ws_url );
		$order       = array();

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
			$order['thirdparty_id'] = $thirdparty_id;
		} else {
			if ( get_user_meta( $user_id, 'billing_company', true )
			     == ''
			) {
				update_user_meta( $user_id, 'billing_company',
					$_POST['billing_company'] );
			}
			$this->dolibarr_create_thirdparty_if_not_exists( $user_id );
			$order['thirdparty_id'] = get_user_meta( $user_id,
				'dolibarr_id', true );
		}
		$order['date']   = date( 'Ymd' );
		$order['status'] = 1;
		$order['lines']  = array();

		// TODO: test me
		foreach ( WC()->cart->cart_contents as $product ) {
			$line = array();
			$line['type']
			                   = get_post_meta( $product['product_id'],
				'dolibarr_type', 1 );
			$line['desc']
			                   = $product['data']->post->post_content;
			$line['product_id']
			                   = get_post_meta( $product['product_id'],
				'dolibarr_id', 1 );
			$line['vat_rate']
			                   = $this->taxes->get_rates( $product['data']->get_tax_class() );
			$line['qty']       = $product['quantity'];
			$line['price']     = $product['data']->get_price();
			$line['unitprice'] = $product['data']->get_price();
			$line['total_net']
			                   = $product['data']->get_price_excluding_tax( $line['qty'] );
			$line['total']
			                   = $product['data']->get_price_including_tax( $line['qty'] );
			$line['total_vat']
			                   = $line['total'] - $line['total_net'];
			$order['lines'][]  = $line;
		}

		$soap_client->createOrder( $this->Doliwoo->ws_auth, $order );
	}

	/**
	 * Creates a thirdparty in Dolibarr via webservice using WooCommerce user data, if it doesn't already exists
	 *
	 * @param  int $user_id a Wordpress user id
	 *
	 * @return void
	 */
	public function dolibarr_create_thirdparty_if_not_exists(
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
			} elseif ( is_null( $result['thirdparty'] ) ) {
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
	 * @return mixed $result  array with the request results if it succeeds, null if there's an error
	 */
	public function dolibarr_thirdparty_exists( $user_id ) {

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
	 * @return mixed $result    the SOAP response
	 */
	public function dolibarr_create_thirdparty( $user_id ) {

		$this->Doliwoo = new Doliwoo();
		$this->Doliwoo->get_settings();

		$dolibarr_ws_url = $this->Doliwoo->settings->webservs_url
		                   . 'server_thirdparty.php?wsdl';
		// Set the WebService URL
		$soap_client = new SoapClient( $dolibarr_ws_url );

		$ref        = get_user_meta( $user_id, 'billing_company',
			true );
		$individual = 0;
		if ( '' == $ref ) {
			$ref        = get_user_meta( $user_id,
				'billing_last_name', true );
			$individual = 1;
		}
		$new_thirdparty = array(
			'ref'           => $ref,
			//'ref_ext'=>'WS0001',
			'status'        => '1',
			'client'        => '1',
			'supplier'      => '0',
			'address'       => get_user_meta( $user_id,
				'billing_address', true ),
			'zip'           => get_user_meta( $user_id,
				'billing_postcode', true ),
			'town'          => get_user_meta( $user_id,
				'billing_city', true ),
			'country_code'  => get_user_meta( $user_id,
				'billing_country', true ),
			'supplier_code' => '0',
			'phone'         => get_user_meta( $user_id,
				'billing_phone', true ),
			'email'         => get_user_meta( $user_id,
				'billing_email', true ),
			'individual'    => $individual,
			'firstname'     => get_user_meta( $user_id,
				'billing_first_name', true )
		);

		$result = $soap_client->createThirdParty( $this->Doliwoo->ws_auth,
			$new_thirdparty );

		return $result;
	}

	/**
	 * Pull products data from Dolibarr via webservice and save it in Wordpress
	 *
	 * @access public
	 * @return void
	 */
	public function dolibarr_import_products() {
		$this->taxes = new WC_Tax_Doliwoo();
		$this->Doliwoo = new Doliwoo();
		$this->Doliwoo->get_settings();

		// Set the WebService URL
		$soap_client = new SoapClient(
			$this->Doliwoo->settings->webservs_url
			. 'server_productorservice.php?wsdl'
		);

		// Get all products that are meant to be displayed on the website
		$result
			= $soap_client->getProductsForCategory( $this->Doliwoo->ws_auth,
			$this->Doliwoo->settings->dolibarr_category_id );

		if ( $result['result']->result_code == 'OK' ) {
			$products = $result['products'];
			foreach ( $products as $product ) {
				if ( $this->dolibarr_product_exists( $product->id ) ) {
					$post_id = 0;
				} else {
					$post    = array(
						'post_title'   => $product->label,
						'post_content' => $product->description,
						'post_status'  => 'publish',
						'post_type'    => 'product',
					);
					$post_id = wp_insert_post( $post );
				}

				if ( 0 < $post_id ) {
					add_post_meta( $post_id, 'dolibarr_id',
						$product->id, true );
					add_post_meta( $post_id, 'dolibarr_type', $product->type,
						true );
					update_post_meta( $post_id, '_regular_price',
						$product->price_net );
					update_post_meta( $post_id, '_sale_price',
						$product->price_net );
					update_post_meta( $post_id, '_price',
						$product->price_net );
					update_post_meta( $post_id, '_visibility',
						'visible' );
					update_post_meta( $post_id, '_tax_class',
						$this->taxes->get_tax_class( $product->vat_rate ) );
					if ( get_option( 'woocommerce_manage_stock' )
					     == 'yes'
					) {
						if ( 0 < $product->stock_real ) {
							update_post_meta( $post_id,
								'_stock_status', 'instock' );
							update_post_meta( $post_id, '_stock',
								$product->stock_real );
						}
					}
					$image_attachment_ids
						= $this->get_product_image( $product,
						$post_id );

					// Use the first image as the product thumbnail, fill the image gallery
					update_post_meta( $post_id, '_thumbnail_id',
						$image_attachment_ids[0] );
					update_post_meta( $post_id,
						'_product_image_gallery',
						implode( ',', $image_attachment_ids ) );
					wc_delete_product_transients( $post_id );
				}
			}
		}
	}

	/**
	 * Checks for the existence of a product in Wordpress database
	 *
	 * @access public
	 *
	 * @param  int $dolibarr_id ID of a product in Dolibarr
	 *
	 * @return bool $exists
	 */
	public function dolibarr_product_exists( $dolibarr_id ) {
		$args  = array(
			'post_type'  => 'product',
			'meta_key'   => 'dolibarr_id',
			'meta_value' => $dolibarr_id
		);
		$query = new WP_Query( $args );

		return $query->have_posts();
	}

	/**
	 * Creates the missing thirdparties in Dolibarr via webservice using WooCommerce user data
	 *
	 * @fixme: use for future batch creation feature
	 *
	 * @return void
	 */
	public function dolibarr_create_multiple_thirdparties() {
		$users = get_users( 'blog_id=' . $GLOBALS['blog_id'] );
		foreach ( $users as $user ) {
			$this->dolibarr_create_thirdparty_if_not_exists( $user->data->ID );
		}
	}
		/**
		 * Webservice calls to get the product's images
		 *
		 * @param $product
		 * @param $post_id
		 *
		 * @return array
		 * @internal param $soap_client
		 */
		public function get_product_image(
			$product, $post_id
		) {
			// FIXME: Get rid of inclusions and use WordPress provided tooling
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH
			             . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH
			             . 'wp-admin/includes/class-wp-filesystem-direct.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$this->Doliwoo = new Doliwoo();
			$this->Doliwoo->get_settings();

			WP_Filesystem();
			$filesystem = new WP_Filesystem_Direct( 'arg' );

			$soap_client = new SoapClient(
				$this->Doliwoo->settings->webservs_url
				. 'server_other.php?wsdl'
			);
			$upload_dir  = wp_upload_dir();
			$path        = $upload_dir['path'];
			$attach_ids  = array();
			foreach ( $product->images as $image ) {
				foreach ( $image as $filename ) {
					// as we know what images are associated with the product, we can retrieve them via webservice
					$result
						= $soap_client->getDocument( $this->Doliwoo->ws_auth,
						'product',
						$product->dir . $filename );

					if ( 'OK' ==
					     $result['result']->result_code
					) {
						// copy the image to the wordpress uploads folder

						// FIXME: Rewrite using Wordpress framework
						$res = $filesystem->put_contents(
							$path . '/'
							. $result['document']->filename,
							base64_decode( $result['document']->content )
						);
						if ( $res ) {
							// attach the new image to the product post
							$filename
								        = $result['document']->filename;
							$wp_filetype
								        = wp_check_filetype( basename( $filename ),
								null );
							$wp_upload_dir
								        = wp_upload_dir();
							$attachment = array(
								'guid'           =>
									$wp_upload_dir['url']
									. '/'
									. basename( $filename ),
								'post_mime_type' => $wp_filetype['type'],
								'post_title'     => preg_replace( '/\.[^.]+$/',
									'',
									basename( $filename ) ),
								'post_content'   => '',
								'post_status'    => 'inherit'
							);
							$attach_id
								        = wp_insert_attachment(
								$attachment,
								$wp_upload_dir['path'] . '/'
								. $filename, $post_id
							);
							$attach_data
								        = wp_generate_attachment_metadata(
								$attach_id,
								$wp_upload_dir['path'] . '/'
								. $filename
							);
							wp_update_attachment_metadata( $attach_id,
								$attach_data );
							$attach_ids[] = $attach_id;
						}
					}
				}
			}

			return $attach_ids;
		}
}