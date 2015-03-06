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
 * DoliWoo settings.
 *
 * WooCommerce settings integration.
 *
 * @package DoliWoo
 */

if ( ! class_exists( 'Doliwoo_WC_Integration' ) ) :

	/**
	 * Doliwoo settings WooCommerce integration
	 *
	 * @see WC_Integration
	 */
	class Doliwoo_WC_Integration extends WC_Integration {
		/** @var string The Dolibarr webservice URL */
		public $dolibarr_ws_endpoint;

		/** @var string WordPress pseudo cron update delay */
		public $delay_update;

		/** @var string The Dolibarr webservice key */
		public $dolibarr_key;

		/** @var string The application name declared when using the Dolibarr webservice */
		public $sourceapplication;

		/** @var string Username to connect to Dolibarr webservice */
		public $dolibarr_login;

		/** @var string Password to connect to Dolibarr webservice */
		public $dolibarr_password;

		/** @var string Dolibarr entity we want webservice responses from */
		public $dolibarr_entity;

		/** @var string ID of the Dolibarr category we sync products from */
		public $dolibarr_category_id;

		/** @var string ID of the Dolibarr thirdparty to use when we make a sale without a user logged in */
		public $dolibarr_generic_id;

		/** @var int[] The distant Dolibarr version */
		private $dolibarr_version;

		/**
		 * Init and hook in the integration.
		 */
		public function __construct() {
			$this->id                 = 'doliwoo';
			$this->method_title       = __( 'DoliWoo', 'doliwoo' );
			$this->method_description = __( 'Dolibarr webservices access', 'doliwoo' );

			// Load the settings
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->dolibarr_ws_endpoint = $this->get_option( 'dolibarr_ws_endpoint' );
			$this->delay_update         = $this->get_option( 'delay_update' );
			$this->dolibarr_key         = $this->get_option( 'dolibarr_key' );
			$this->sourceapplication    = $this->get_option( 'sourceapplication' );
			$this->dolibarr_login       = $this->get_option( 'dolibarr_login' );
			$this->dolibarr_password    = $this->get_option( 'dolibarr_password' );
			$this->dolibarr_entity      = $this->get_option( 'dolibarr_entity' );
			$this->dolibarr_category_id = $this->get_option( 'dolibarr_category_id' );
			$this->dolibarr_generic_id  = $this->get_option( 'dolibarr_generic_id' );

			// Actions
			add_action(
				'woocommerce_update_options_integration_' . $this->id,
				array( $this, 'process_admin_options' )
			);

			// Filters.
			add_filter(
				'woocommerce_settings_api_sanitized_fields_' . $this->id,
				array( $this, 'sanitize_settings' )
			);
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
					'description' => __( 'How this application will identify itself to the webservice.', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => 'WooCommerce',
				),
				'dolibarr_ws_endpoint' => array(
					'title'       => __( 'URL', 'doliwoo' ),
					'description' => __(
						'Enter Dolibarr webservices root URL (i.e. https://mydolibarr.com/webservices)',
						'doliwoo'
					),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => '',
				),
				'delay_update'         => array(
					'title'       => __( 'Delay', 'doliwoo' ),
					'description' => __( 'Choose the automatic update frequency', 'doliwoo' ),
					'type'        => 'select',
					'desc_tip'    => false,
					'options'     => array(
						'hourly'     => __( 'Once Hourly' ),
						'twicedaily' => __( 'Twice Daily' ),
						'daily'      => __( 'Once Daily' ),
					),
					'default'     => 'daily',
				),
				'dolibarr_key'         => array(
					'title'       => __( 'Key', 'doliwoo' ),
					'description' => __( 'Enter your Dolibarr webservices key', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => '',
				),
				'dolibarr_login'       => array(
					'title'       => __( 'User login', 'doliwoo' ),
					'description' => __( 'Dolibarr actions will be done as this user', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => '',
				),
				'dolibarr_password'    => array(
					'title'    => __( 'User password', 'doliwoo' ),
					'type'     => 'password',
					'desc_tip' => false,
					'default'  => '',
				),
				'dolibarr_entity'      => array(
					'title'       => __( 'Entity', 'doliwoo' ),
					'description' => __( 'If you\'re using multicompany, the ID of the entity you want to integrate. Leave to 1 otherwise.', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => 1,
				),
				'dolibarr_category_id' => array(
					'title'       => __( 'Products category', 'doliwoo' ),
					'description' => __( 'The ID of the products category you want to automatically import products from.', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => '',
				),
				'dolibarr_generic_id'  => array(
					'title'       => __( 'Generic thirdparty', 'doliwoo' ),
					'description' => __( 'The ID of the thirdparty that\'ll be used for anonymous orders.', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => '',
				),
				'dolibarr_version'     => array(
					'title'       => __( 'Dolibarr version', 'doliwoo' ),
					'description' => __( 'If the webservice communication is OK, it displays your Dolibarr version', 'doliwoo' ),
					'type'        => 'info',
					'desc_tip'    => false,
				),
			);
		}

		/**
		 * Display Dolibarr version and compatibility
		 *
		 * @param string $key Settings key
		 * @param array $data Setting values
		 *
		 * @return string HTML to display
		 */
		protected function generate_info_html( $key, $data ) {
			// Get Webservice infos
			$this->test_webservice( );

			$field = $this->plugin_id . $this->id . '_' . $key;

			if ( empty ( $this->dolibarr_version ) ) {
				$message = __( 'Please configure the plugin.', 'doliwoo' );
			} else {
				if (
					// Is version > 3.4.0
					4 <= $this->dolibarr_version[0]
					|| ( 3 <= $this->dolibarr_version[0] && 4 <= $this->dolibarr_version[1] )
				) {
					$message = __( 'OK!', 'doliwoo' );
				} else {
					$message = __( 'Not compatible! Please use at least Dolibarr v3.4.0.', 'doliwoo' );
				}
				$message .= '&nbsp;' . sprintf( __( '(Detected v%s)', 'doliwoo' ), implode( '.', $this->dolibarr_version ) );
			}

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
					<?php esc_html_e( $this->get_tooltip_html( $data ) ); ?>
				</th>
				<td class="forminp">
					<?php
					esc_html_e( $message );
					?>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		/**
		 * Check if the fields URL Webservs is HTTPS
		 * @see validate_settings_fields()
		 *
		 * @param string $key The form setting
		 *
		 * @return string The form value
		 */
		public function validate_dolibarr_ws_endpoint_field( $key ) {
			$value = $_POST[ $this->plugin_id . $this->id . '_' . $key ];

			// Make sure we use HTTPS
			if ( 'https://' !== ( substr( $value, 0, 8 ) ) ) {
				$this->errors[] = __( 'The protocol to use is https://', 'doliwoo' );
			}

			// Make sure we have the trailing slash
			$value = trailingslashit( $value );

			// Check that the server is available
			try {
				new SoapClient( $value . Doliwoo_Dolibarr::OTHER_ENDPOINT . Doliwoo_Dolibarr::WSDL_MODE );
			} catch ( SoapFault $exc ) {
				$this->errors[] = __( 'The webservice is not available. Please check the URL.', 'doliwoo' );
			}

			return $value;
		}

		/**
		 * Sanitize settings.
		 * Executed after validations.
		 * @see process_admin_options()
		 *
		 * @param array $settings Validated settings
		 *
		 * @return array Sanitized settings
		 */
		public function sanitize_settings( $settings ) {
			// Check Dolibarr version and compatibility

			$endpoint = $settings['dolibarr_ws_endpoint'];
			$ws_auth  = array(
				'dolibarrkey'       => $settings['dolibarr_key'],
				'sourceapplication' => $settings['sourceapplication'],
				'login'             => $settings['dolibarr_login'],
				'password'          => $settings['dolibarr_password'],
				'entity'            => $settings['dolibarr_entity'],
			);

			$this->test_webservice( $endpoint, $ws_auth );

			return $settings;
		}

		/**
		 * Display HTTPS is needed
		 * @see WC_Integration::display_errors()
		 *
		 * @return void
		 */
		public function display_errors( ) {
			if ( empty( $this->errors ) ) {
				// Nothing to do
				return;
			}

			foreach ( $this->errors as $key => $value ) {
				?>
				<div class="error">
					<p><b>
						<?php
						esc_html_e( $value );
						?>
					</b></p>
				</div>
			<?php
			}

			// Errors have been displayed. Let's clear them to avoid weird corner case.
			unset( $this->errors );
		}

		/**
		 * Check that the webservice works.
		 * Tests endpoint, authentication and actual response
		 *
		 * @param string $webservice The webservice URL
		 * @param string[] $ws_auth The webservice authentication array
		 */
		private function test_webservice(
			$webservice = '',
			$ws_auth = array()
		) {
			if ( empty ( $webservice ) && ! empty ( $this->dolibarr_ws_endpoint ) ) {
				$webservice = $this->dolibarr_ws_endpoint;
			}
			if ( empty ( $webservice ) ) {
				// We don't want to check unconfigured plugin
				return;
			}

			if ( empty ( $ws_auth ) ) {
				$ws_auth  = array(
					'dolibarrkey'       => $this->dolibarr_key,
					'sourceapplication' => $this->sourceapplication,
					'login'             => $this->dolibarr_login,
					'password'          => $this->dolibarr_password,
					'entity'            => $this->dolibarr_entity,
				);
			}

			// Check that the server is available
			try {
				$soap_client = new SoapClient( $webservice . Doliwoo_Dolibarr::OTHER_ENDPOINT . Doliwoo_Dolibarr::WSDL_MODE );
			} catch ( SoapFault $exc ) {
				$this->errors[] = __( 'The webservice is not available. Please check the URL.', 'doliwoo' );
				$this->display_errors();

				// No point in doing the next test
				return;
			}

			try {
				$response = $soap_client->getVersions( $ws_auth );
			} catch ( SoapFault $exc ) {
				$this->errors[] = 'Webservice error:' . $exc->getMessage();
				$this->display_errors();

				// No point in doing the next test
				return;
			}

			if ( 'OK' == $response['result']->result_code ) {
				$this->dolibarr_version = explode( '.', $response['dolibarr'] );
			} else {
				$this->errors[] = 'Webservice error:' . $response['result']->result_label;
				$this->display_errors();
			}
		}
	}
endif;
