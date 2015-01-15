<?php
/**
 * Integration Doliwoo Settings.
 *
 * @package  WC_Integration_Doliwoo_Settings
 * @category Integration
 * @author   WooThemes
 */

if ( ! class_exists( 'WC_Integration_Doliwoo_Settings' ) ) :

class WC_Integration_Doliwoo_Settings extends WC_Integration {

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->id                 = 'doliwoo-settings';
		$this->method_title       = __( 'Doliwoo Settings', 'doliwoo' );
		$this->method_description = __( 'Dolibarr webservices access', 'doliwoo' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->webservs_url = $this->get_option( 'webservs_url' );
		$this->dolibarr_key = $this->get_option( 'dolibarr_key' );
		$this->sourceapplication = $this->get_option( 'sourceapplication' );
		$this->dolibarr_login = $this->get_option( 'dolibarr_login' );
		$this->dolibarr_password = $this->get_option( 'dolibarr_password' );
		$this->dolibarr_entity = $this->get_option( 'dolibarr_entity' );
		$this->dolibarr_category_id =  $this->get_option( 'dolibarr_category_id' );
		$this->dolibarr_generic_id = $this->get_option( 'dolibarr_generic_id' );

		// Actions.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'sourceapplication'     => array(
				'title'             => __('Source application','doliwoo'),
				'type'              => 'text',
				'desc_tip'          => true,
				'default'           => 'WooCommerce'
			),
			'webservs_url'          => array(
				'title'             => __('URL','doliwoo'),
				'type'              => 'text',
				// TODO
				// 'description'       => __( 'Enter with your API Key. You can find this in "User Profile" drop-down (top right corner) > API Keys.', 'woocommerce-integration-demo' ),
				'desc_tip'          => false,
				'default'           => ''
			),
			'dolibarr_key'          => array(
				'title'             => __('Key','doliwoo'),
				'type'              => 'text',
				'desc_tip'          => true,
				'default'           => ''
			),
			'dolibarr_login'        => array(
				'title'             => __('User login','doliwoo'),
				'type'              => 'text',
				'desc_tip'          => true,
				'default'           => ''
			),
			'dolibarr_password'     => array(
				'title'             => __('User password','doliwoo'),
				'type'              => 'password',
				'default'           => ''
			),
			'dolibarr_entity'       => array(
				'title'             => __('Entity','doliwoo'),
				'type'              => 'text',
				'desc_tip'          => true,
				'default'           => ''
			),
			'dolibarr_category_id'  => array(
				'title'             => __('Category','doliwoo'),
				'type'              => 'text',
				'desc_tip'          => true,
				'default'           => ''
			),
			'dolibarr_generic_id'   => array(
				'title'             => __('Generic thirdparty', 'doliwoo'),
				'type'              => 'text',
				'desc_tip'          => true,
				'default'           => ''
			),
		);
	}
}

endif;
