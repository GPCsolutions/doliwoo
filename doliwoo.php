<?php
/**
 * Plugin Name: Doliwoo
 * Description: Interface bewteen WooCommerce and Dolibarr
 * Version: 0.1
 * Author: Cédric Salvador <csalvador@gpcsolutions.fr>
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

require_once 'nusoap/lib/nusoap.php';

 if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    if (!class_exists('Doliwoo')) {
        class Doliwoo
        {
            public function __construct()
            {
                // Hook on woocommerce_checkout_process to create a Dolibarr order using WooCommerce order data
                add_action('woocommerce_checkout_process', array(&$this, 'dolibarr_create_order'));

                // Schedule the import of product data from Dolibarr
                add_action('wp', array(&$this, 'schedule_import_products'));
                add_action('import_products', array(&$this, 'import_dolibarr_products'));

                // Dolibarr ID custom field
                add_filter('manage_users_columns', array(&$this, 'doliwoo_user_columns'));
                add_action('show_user_profile', array(&$this, 'doliwoo_customer_meta_fields'));
                add_action('edit_user_profile', array(&$this, 'doliwoo_customer_meta_fields'));
                add_action('personal_options_update', array(&$this, 'doliwoo_save_customer_meta_fields'));
                add_action('edit_user_profile_update', array(&$this, 'doliwoo_save_customer_meta_fields'));
                add_action('manage_users_custom_column', array(&$this, 'doliwoo_user_column_values'), 10, 3);

                // Schedule the creation of Dolibarr thirdparties using WooCommerce usera data
                add_action('wp', array(&$this, 'schedule_create_thirdparties'));
                add_action('create_thirdparties', array(&$this, 'create_dolibarr_thirdparties'));
            }

             /**
             * Define columns to show on the users page.
             *
             * @access public
             * @param array $columns Columns on the manage users page
             * @return array The modified columns
             */

            function doliwoo_user_columns($columns)
            {
                $columns['dolibarr_id'] = __('Dolibarr ID', 'doliwoo');
                return $columns;
            }

            /**
             * Get Dolibarr ID for the edit user pages.
             *
             * @access public
             * @return array fields to display
             */

            function doliwoo_get_customer_meta_fields()
            {
                $show_fields = apply_filters('doliwoo_customer_meta_fields', array(
                    'dolibarr' => array(
                        'title' => __('Dolibarr', 'doliwoo'),
                        'fields' => array(
                            'dolibarr_id' => array(
                                    'label' => __('Dolibarr ID', 'doliwoo'),
                                    'description' => ''
                                )
                        )
                    ),
                ));
                return $show_fields;
            }

            /**
             * Show the Dolibarr ID field on edit user pages.
             *
             * @access public
             * @param mixed $user User (object) being displayed
             * @return void
             */

            function doliwoo_customer_meta_fields($user)
            {
                $show_fields = $this->doliwoo_get_customer_meta_fields();
                foreach( $show_fields as $fieldset ) :
                    ?>
                    <h3><?php echo $fieldset['title']; ?></h3>
                    <table class="form-table">
                        <?php
                        foreach( $fieldset['fields'] as $key => $field ) :
                            ?>
                            <tr>
                                <th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
                                <td>
                                    <input type="text" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_user_meta( $user->ID, $key, true ) ); ?>" class="regular-text" /><br/>
                                    <span class="description"><?php echo wp_kses_post( $field['description'] ); ?></span>
                                </td>
                            </tr>
                            <?php
                        endforeach;
                        ?>
                    </table>
                    <?php
                endforeach;
            }

            /**
             * Save Dolibarr ID field on edit user pages
             *
             * @access public
             * @param mixed $user_id User ID of the user being saved
             * @return void
             */

            function doliwoo_save_customer_meta_fields($user_id)
            {
                $save_fields = $this->doliwoo_get_customer_meta_fields();
                foreach($save_fields as $fieldset) {
                    foreach($fieldset['fields'] as $key => $field) {
                        if (isset($_POST[$key])) {
                            update_user_meta($user_id, $key, woocommerce_clean($_POST[$key]));
                        }
                    }
                }
            }

            /**
             * Define value for the Dolibarr ID column.
             *
             * @access public
             * @param mixed $value The value of the column being displayed
             * @param mixed $column_name The name of the column being displayed
             * @param mixed $user_id The ID of the user being displayed
             * @return string Value for the column
             */

            function doliwoo_user_column_values($value, $column_name, $user_id)
            {
                return get_user_meta($user_id, 'dolibarr_id', true);
            }

            /**
             * Hooks on process_checkout()
             *
             * While the order is processed, use the data to create a Dolibarr order via webservice
             * @access public
             * @return void
             */

            public function dolibarr_create_order()
            {
                global $woocommerce;
                require_once 'conf.php';
                $WS_DOL_URL = $webservs_url . 'server_order.php';

                // Set the WebService URL
                $soapclient = new nusoap_client($WS_DOL_URL);
                if ($soapclient) {
                    $soapclient->soap_defencoding = 'UTF-8';
                    $soapclient->decodeUTF8(false);
                }

                $order = array();
                //fill this array with all data required to create an order in Dolibarr
                $order['thirdparty_id'] = get_user_meta(get_current_user_id(), 'dolibarr_id', true);
                $order['date'] = time();
                $order['status'] = 1;
                $order['lines'] = array();

                $_tax  = new WC_Tax(); //use this object to get the tax rates
                foreach($woocommerce->cart->cart_contents as $product) {
                    $line = array();
                    $line['type'] = get_post_meta($product['product_id'], 'type', 1);
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
                $soapclient->call('createOrder', $parameters, $ns, '');
            }

             /**
             * Schedules the daily import of Dolibarr products
             *
             * @access public
             * @return void
             */

            public function schedule_import_products()
            {
                if (!wp_next_scheduled('import_products')) {
                    wp_schedule_event(time(), 'daily', 'import_products');
                }
            }

             /**
             * Schedules the daily creation of Dolibarr thirdparties using WooCommerce users data
             *
             * @access public
             * @return void
             */

            public function schedule_create_thirdparties()
            {
                if (!wp_next_scheduled('create_thirdparties')) {
                    wp_schedule_event(time(), 'daily', 'create_thirdparties');
                }
            }

            /**
             * Checks for the existence of a product in Wordpress database
             *
             * @access public
             * @return int $exists      0 if the product doesn't exists, else >0
             */

            public function dolibarr_product_exists($dolibarr_id)
            {
                global $wpdb;
                $sql = 'SELECT count(post_id) as nb from ' . $wpdb->prefix . 'postmeta WHERE meta_key = "dolibarr_id" AND meta_value = ' . $dolibarr_id;
                $result = $wpdb->query($sql);
                $exists = $wpdb->last_result[0]->nb;
                return $exists;
            }

             /**
             * Pull products data from Dolibarr via webservice and save it in Wordpress
             *
             * @access public
             * @return void
             */

            public function import_dolibarr_products()
            {
                global $woocommerce;
                require_once 'conf.php';
                $WS_DOL_URL = $webservs_url . 'server_productorservice.php';

                // Set the WebService URL
                $soapclient = new nusoap_client($WS_DOL_URL);
                if ($soapclient) {
                    $soapclient->soap_defencoding = 'UTF-8';
                    $soapclient->decodeUTF8(false);
                }

                $parameters = array('authentication' => $authentication, 'id' => 1);
                $result = $soapclient->call('getProductsForCategory', $parameters, $ns, '');
                if ($result['result']['result_code'] == 'OK') {
                    $products = $result['products'];
                    foreach($products as $product) {
                        if ($this->dolibarr_product_exists($product['id'])) {
                            $post_id = 0;
                        } else {
                            $post = array(
                                'post_title'    => $product['label'],
                                'post_content'  => $product['description'],
                                'post_status'   => 'publish',
                                'post_type'     => 'product',
                            );
                            $post_id = wp_insert_post($post);
                        }
                        if ($post_id > 0) {
                            add_post_meta($post_id, 'total_sales', '0', true);
                            add_post_meta($post_id, 'dolibarr_id', $product['id'], true);
                            add_post_meta($post_id, 'type', $product['type'], true);
                            // Gallery Images
                            //$attachment_ids = array_filter( explode( ',', woocommerce_clean( $_POST['product_image_gallery'] ) ) );
                            //update_post_meta( $post_id, '_product_image_gallery', implode( ',', $attachment_ids ) );
                            // Update post meta
                            update_post_meta($post_id, '_regular_price', $product['price_net']);
                            update_post_meta($post_id, '_sale_price', $product['price_net']);
                            update_post_meta($post_id, '_price', $product['price_net']);
                            update_post_meta($post_id, '_visibility', 'visible');
                            /*if ( isset( $_POST['_tax_status'] ) )
                                update_post_meta( $post_id, '_tax_status', stripslashes( $_POST['_tax_status'] ) );

                            if ( isset( $_POST['_tax_class'] ) )
                                update_post_meta( $post_id, '_tax_class', stripslashes( $_POST['_tax_class'] ) );
                            } */
                            //TODO FIND A WAY TO GET THE TAX
                            update_post_meta($post_id, '_tax_class', 'tva');
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

            /**
             * Pull thirdparties data from Dolibarr via webservice
             *
             * @access public
             * @return array $result['thirdparties'] if success, else null   the array contains all thirdparties data
             */

            public function get_dolibarr_thirdparties()
            {
                require_once 'conf.php';
                $WS_DOL_URL = $webservs_url . 'server_thirdparty.php';
                // Set the WebService URL
                $soapclient = new nusoap_client($WS_DOL_URL);
                if ($soapclient) {
                    $soapclient->soap_defencoding='UTF-8';
                    $soapclient->decodeUTF8(false);
                }

                // Get all thirdparties
                $parameters = array('authentication' => $authentication, null);
                $result = $soapclient->call('getListOfThirdParties',$parameters,$ns,'');
                if($result['result']['result_code'] == 'OK') {
                    return $result['thirdparties'];
                } else {
                    return null;
                }
            }

            /**
             * Checks for the existence of a tirdparty in Dolibarr
             * @access public
             * @return boolean $found      true if the thirdparty exists, else false
             */
            public function exists_thirdparty($user_id, $thirdparties) {
                $found = false;
                $i = 0;
                if ($thirdparties) {
                    while ($i < count($thirdparties) && !$found) {
                        $found = ($thirdparties[$i]['id'] == get_user_meta($user_id, 'dolibarr_id', true));
                        $i++;
                    }
                    return $found;
                } else {
                    return null;
                }
            }

            /**
             * Creates a thirdparty in Dolibarr via webservice using WooCommerce user data
             * @param int $user_id      a Wordpress user id
             * @return mixed $result    the SOAP response
             */
            public function create_dolibarr_thirdparty($user_id) {
                require 'conf.php';
                $WS_DOL_URL = $webservs_url . 'server_thirdparty.php';	// If not a page, should end with /
                // Set the WebService URL
                $soapclient = new nusoap_client($WS_DOL_URL);
                if ($soapclient)
                {
                    $soapclient->soap_defencoding='UTF-8';
                    $soapclient->decodeUTF8(false);
                }
                $new_thirdparty = array(
                                    'ref'=> get_user_meta($user_id, 'billing_company', true),
                                    //'ref_ext'=>'WS0001',
                                    'status'=>'1',
                                    'client'=>'1',
                                    'supplier'=>'0',
                                    'address'=>get_user_meta($user_id, 'billing_address', true),
                                    'zip'=>get_user_meta($user_id, 'billing_postcode', true),
                                    'town'=>get_user_meta($user_id, 'billing_city', true),
                                    'country_code'=>get_user_meta($user_id, 'billing_country', true),
                                    'supplier_code'=>'0',
                                    'phone'=>get_user_meta($user_id, 'billing_phone', true),
                                    'email'=>get_user_meta($user_id, 'billing_email', true)
                );
                $parameters = array('authentication'=>$authentication,'thirdparty'=>$new_thirdparty);

                $result = $soapclient->call('createThirdParty',$parameters,$ns,'');
                return $result;
            }

            /**
             * Creates a thirdparty in Dolibarr via webservice using WooCommerce user data, if it doesn't already exists
             * @param  int   $user_id         a Wordpress user id
             * @param  mixed $thirdparties    array filled by get_dolibarr_thirdparties()
             * @return void
             */
            public function create_dolibarr_thirdparty_if_not_exists($user_id, $thirdparties) {
                $exists = $this->exists_thirdparty($user_id, $thirdparties);
                if (!$exists && !is_null($exists)) {
                    $result = $this->create_dolibarr_thirdparty($user_id);
                    if ($result['result']['result_code'] == 'OK') {
                        update_user_meta($user_id, 'dolibarr_id', $result['id'] );
                    }
                }
            }

            /**
             * Creates the missing thirdparties in Dolibarr via webservice using WooCommerce user data
             * @return void
             */
            public function create_dolibarr_thirdparties() {
                $thirdparties = $this->get_dolibarr_thirdparties();
                $users = get_users('blog_id='.$GLOBALS['blog_id']);
                foreach ($users as $user) {
                    $this->create_dolibarr_thirdparty_if_not_exists($user->data->ID, $thirdparties);  //TODO optimize
                }
            }

        }
        // Plugin instanciation
        $GLOBALS['doliwoo'] = new Doliwoo();
    }
}