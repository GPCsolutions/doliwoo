<?php
/**
 * Plugin Name: Woocommerce-Dolibarr
 * Plugin URI:
 * Description:
 * Version:
 * Author: Cédric Salvador
 * Author URI:
 * License: GPL3
 */

/* Copyright (C) 2013 Cédric Salvador  <csalvador@gpcsolutions.fr>
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

require_once '/var/www/wp-content/plugins/woocommerce/classes/class-wc-cart.php';

 if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    if ( ! class_exists( 'WooCommerceDolibarr' ) ) {
        class WooCommerceDolibarr {
            public function __construct() {
                // called only after woocommerce has finished loading
                add_action( 'woocommerce_checkout_process', array( &$this, 'dolibarr_create_order' ) );
                add_action( 'init', array( &$this, 'import_dolibarr_products' ) );

                // take care of anything else that needs to be done immediately upon plugin instantiation, here in the constructor
            }

            /**
             *  Hooks on process_checkout()
             *  While the order is processed, use the data to create a Dolibarr order via webservice
             */
            public function dolibarr_create_order() {
                global $woocommerce;
                require_once 'conf.php';

                $WS_DOL_URL = $webservs_url . 'server_order.php';

                // Set the WebService URL
                $soapclient = new nusoap_client($WS_DOL_URL);
                if ($soapclient)
                {
                    $soapclient->soap_defencoding='UTF-8';
                    $soapclient->decodeUTF8(false);
                }

                $order = array();
                //fill this array with all data required to create an order in Dolibarr
                $order['thirdparty_id'] = '1'; //we'll need to get that from WooCommerce and make sure it's the same in Dolibarr
                //$order['ref_ext']; Bullshit?
                $order['date'] = time();
                //$order['date_due'] = ; Needed?
                //$order['note_private'] = ; Needed?
                // $order['note_public'] = ; Needed?
                $order['status'] = 1;
                //$order['facturee'] = ; Needed?
                //$order['project_id'] = ; Needed?
                //$order['cond_reglement_id'] = ; Needed?
                //$order['demand_reason_id'] = ; Needed?
                $order['lines'] = array();
                //go through the product list and fill this array. Or just cheat, for now
                $_tax  = new WC_Tax(); //use this object to get the tax rates
                foreach($woocommerce->cart->cart_contents as $product) {
                    $line = array();
                    $line['type'] = get_post_meta($product['product_id'], 'type', 1);//    //How do we get this?
                    $line['desc'] = $product['data']->post->post_content;
                    $line['product_id'] = get_post_meta($product['product_id'], 'dolibarr_id', 1);
                    $line['vat_rate'] = $_tax->get_rates($product['data']->get_tax_class())[1]['rate'];
                    $line['qty'] = $product['quantity'];
                    $line['price'] = $product['data']->get_price();
                    $line['unitprice'] = $product['data']->get_price();
                    $line['total_net'] = $product['data']->get_price_excluding_tax($line['qty']);
                    $line['total'] = $product['data']->get_price_including_tax($line['qty']);
                    $line['total_vat'] = $line['total'] - $line['total_net'];
                    $order['lines'][] = $line;
                }

                $parameters = array($authentication, $order);
                $soapclient->call('createOrder',$parameters,$ns,'');
            }

            public function dolibarr_product_exists($dolibarr_id) {
                global $wpdb;
                $sql = 'SELECT count(post_id) as nb from ' . $wpdb->prefix . 'postmeta WHERE meta_key = "dolibarr_id" AND meta_value = ' . $dolibarr_id;
                $result = $wpdb->query($sql);
                $exists = $wpdb->last_result[0]->nb;
                return $exists;
            }

            public function import_dolibarr_products() {
                global $woocommerce;
                require_once 'conf.php';

                $WS_DOL_URL = $webservs_url . 'server_productorservice.php';

                // Set the WebService URL
                $soapclient = new nusoap_client($WS_DOL_URL);
                if ($soapclient)
                {
                    $soapclient->soap_defencoding='UTF-8';
                    $soapclient->decodeUTF8(false);
                }

                // Get all thirdparties
                $parameters = array('authentication'=>$authentication,'id'=>1);
                $result = $soapclient->call('getProductsForCategory',$parameters,$ns,'');
                if (! $result) {
                    echo $soapclient->error_str,
                         '<br>',
                         $soapclient->request,
                         '<br>',
                         $soapclient->response;
                    exit;
                }
                if ($result['result']['result_code'] == 'OK') {
                    $products = $result['products'];
                    foreach($products as $product) {
                        if ($this->dolibarr_product_exists($product['id'])) {
                            $post_id = 0;
                        } else {
                            $post = array(
                                'post_title' 	=> $product['label'],
                                'post_content' 	=> $product['description'],
                                'post_status' 	=> 'publish',
                                'post_type' 	=> 'product',
                            );
                            $post_id = wp_insert_post($post);
                        }
                        if ($post_id > 0) {
                            add_post_meta($post_id, 'total_sales', '0', true );
                            add_post_meta($post_id, 'dolibarr_id', $product['id'], true );
                            add_post_meta($post_id, 'type', $product['type'], true );
                            //$is_downloadable 	= isset( $_POST['_downloadable'] ) ? 'yes' : 'no';
                            //$is_virtual 		= isset( $_POST['_virtual'] ) ? 'yes' : 'no';
                            // Gallery Images
                            //$attachment_ids = array_filter( explode( ',', woocommerce_clean( $_POST['product_image_gallery'] ) ) );
                            //update_post_meta( $post_id, '_product_image_gallery', implode( ',', $attachment_ids ) );
                            // Update post meta
                            update_post_meta($post_id, '_regular_price', $product['price_net']);
                            update_post_meta($post_id, '_sale_price', $product['price_net']);
                            update_post_meta($post_id, '_price', $product['price_net']);
                            update_post_meta($post_id, '_visibility', 'visible' );
                            /*if ( isset( $_POST['_tax_status'] ) )
                                update_post_meta( $post_id, '_tax_status', stripslashes( $_POST['_tax_status'] ) );

                            if ( isset( $_POST['_tax_class'] ) )
                                update_post_meta( $post_id, '_tax_class', stripslashes( $_POST['_tax_class'] ) );
                            } */
                            //TODO FIND A WAY TO GET THE TAX
                            update_post_meta( $post_id, '_tax_class', 'tva');
                            if (get_option('woocommerce_manage_stock') == 'yes') {
                                if ($product['stock_real'] > 0) {
                                   update_post_meta($post_id, '_stock_status', 'instock');
                                   update_post_meta($post_id, '_stock', $product['stock_real']);
                                }
                            }
                            $woocommerce->clear_product_transients($post_id);
                        }
                    }
                }
            }
        }
        $GLOBALS['woocommerce-dolibarr'] = new WooCommerceDolibarr();
    }
}