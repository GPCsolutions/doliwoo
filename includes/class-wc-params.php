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
 * Parameters management.
 *
 * We store some properties to link imported Dolibarr elements to their WooCommerce counterparts.
 * This class ties everything together.
 *
 * @package DoliWoo
 */

/**
 * Parameters management
 */
Class Doliwoo_WC_Params {

	/**
	 * Save Dolibarr ID field on edit user pages
	 *
	 * @param mixed $user_id User ID of the user being saved
	 *
	 * @return void
	 */
	public function save_customer_meta_fields( $user_id ) {
		$save_fields = $this->get_customer_meta_fields();
		foreach ( $save_fields as $fieldset ) {
			foreach ( $fieldset['fields'] as $key => $field ) {
				if ( isset( $_POST[ $key ] ) ) {
					update_user_meta( $user_id, $key, wc_clean( $_POST[ $key ] )
					);
				}
			}
		}
	}

	/**
	 *
	 * Get Dolibarr ID for the edit user pages
	 *
	 * @return array fields to display
	 */
	public function get_customer_meta_fields() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return null;
		}
		$show_fields = apply_filters(
			'customer_meta_fields',
			array(
				'dolibarr' => array(
					'title'  => __( 'Dolibarr', 'doliwoo' ),
					'fields' => array(
						'dolibarr_id' => array(
							'label'       => __( 'User ID', 'doliwoo' ),
							'description' => __( 'The Dolibarr ID for this user', 'doliwoo' ),
						)
					)
				)
			)
		);

		return $show_fields;
	}

	/**
	 * Show the Dolibarr ID field on edit user pages
	 *
	 * @param WP_User $user being displayed
	 *
	 * @return void
	 */
	public function customer_meta_fields( WP_User $user ) {
		// Only allow WooCommerce managers
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$show_fields = $this->get_customer_meta_fields();
		foreach ( $show_fields as $fieldset ) {
			echo '<h3>', $fieldset['title'], '</h3>',
			'<table class="form-table">';
			foreach ( $fieldset['fields'] as $key => $field ) {
				echo '<tr>',
				'<th><label for="', esc_attr( $key ), '">', esc_html( $field['label'] ), '</label></th>',
				'<td>',
				'<input type="text" name="', esc_attr( $key ), '"',
				'" id="', esc_attr( $key ),
				'" value="', esc_attr( get_user_meta( $user->ID, $key, true ) ),
				'" class="regular-text"/><br/>',
				'<span class="description">', wp_kses_post( $field['description'] ), '</span>',
				'</td>',
				'</tr>';
			}
			echo '</table>';
		}
	}

	/**
	 * Define value for the Dolibarr ID column
	 *
	 * @param string $value The current value
	 * @param string $column_name The curent custom column
	 * @param mixed $user_id The ID of the user being displayed
	 *
	 * @return string Value for the column
	 */
	public function user_column_values( $value, $column_name, $user_id ) {
		$user = get_userdata( $user_id );
		if ( 'dolibarr_id' == $column_name ) {
			return get_user_meta( $user->ID, 'dolibarr_id', true );
		}
		return $value;
	}

	/**
	 * Define columns to show on the users page
	 *
	 * @param array $columns Columns on the manage users page
	 *
	 * @return array The modified columns
	 */
	public function user_columns( $columns ) {
		$columns['dolibarr_id'] = __( 'Dolibarr User ID', 'doliwoo' );

		return $columns;
	}
}
