<?php
/**
 * Integration Doliwoo Settings.
 *
 * @package  WC_Integration_Doliwoo_Settings
 * @category Integration
 * @author   WooThemes
 */

if ( ! class_exists( 'WC_Integration_Doliwoo_Settings' ) ) :

	/**
	 * Doliwoo settings WooCommerce integration
	 */
	class WC_Integration_Doliwoo_Settings extends WC_Integration {
		/**
		 * Init and hook in the integration.
		 */
		public function __construct() {
			$this->id                 = 'doliwoo-settings';
			$this->method_title       = __( 'Doliwoo Settings', 'doliwoo' );
			$this->method_description = __( 'Dolibarr webservices access', 'doliwoo' );

			// Load the settings
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->webservs_url = $this->get_option( 'webservs_url' );
			$this->dolibarr_key = $this->get_option( 'dolibarr_key' );
			$this->sourceapplication
			                      = $this->get_option( 'sourceapplication' );
			$this->dolibarr_login = $this->get_option( 'dolibarr_login' );
			$this->dolibarr_password
			                      = $this->get_option( 'dolibarr_password' );
			$this->dolibarr_entity
			                      = $this->get_option( 'dolibarr_entity' );
			$this->dolibarr_category_id
			                      = $this->get_option( 'dolibarr_category_id' );
			$this->dolibarr_generic_id
			                      = $this->get_option( 'dolibarr_generic_id' );

			// Actions
			add_action( 'woocommerce_update_options_integration_' . $this->id,
				array( $this, 'process_admin_options' ) );
		}

		/**
		 * Initialize integration settings form fields.
		 *
		 * @return void
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'sourceapplication'    => array(
					'title'       => __( 'Source application', 'doliwoo' ),
					'type'        => 'text',
					'description' => __( 'How this application will identify itself to the webservice.', 'doliwoo' ),
					'desc_tip'    => false,
					'default'     => 'WooCommerce'
				),
				'webservs_url'         => array(
					'title'       => __( 'URL', 'doliwoo' ),
					'type'        => 'text',
					'description' => __( 'Enter Dolibarr webservices root URL (i.e. https://mydolibarr.com/webservices/)',
						'doliwoo' ),
					'desc_tip'    => false,
					'default'     => ''
				),
				'dolibarr_key'         => array(
					'title'       => __( 'Key', 'doliwoo' ),
					'description' => __( 'Enter your Dolibarr webservices key', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => ''
				),
				'dolibarr_login'       => array(
					'title'       => __( 'User login', 'doliwoo' ),
					'description' => __( 'Dolibarr actions will be done as this user', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => ''
				),
				'dolibarr_password'    => array(
					'title'    => __( 'User password', 'doliwoo' ),
					'type'     => 'password',
					'desc_tip' => false,
					'default'  => ''
				),
				// TODO: do a 2 step configuration procedure and try to get these parameters in a human readable form from the SOAP webservice
				'dolibarr_entity'      => array(
					'title'       => __( 'Entity', 'doliwoo' ),
					'description' => __( 'If you\'re using ulticompany: the ID of the entity you want to integrate. Leave to 1 otherwise.', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => 1
				),
				'dolibarr_category_id' => array(
					'title'       => __( 'Product category', 'doliwoo' ),
					'description' => __( 'The ID of the product category you want to automatically import products from.', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => ''
				),
				'dolibarr_generic_id'  => array(
					'title'       => __( 'Generic thirdparty', 'doliwoo' ),
					'description' => __( 'The ID of the thirdparty that\'ll be used for anonymous orders.', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => ''
				),
			);
		}
	}

endif;
