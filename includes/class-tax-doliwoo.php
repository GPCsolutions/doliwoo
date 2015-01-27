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
 * DoliWoo tax management
 */

/**
 * Class WC_Tax_Doliwoo
 *
 * Extend WC_Tax() to insert and update tax rates
 */
class WC_Tax_Doliwoo extends WC_Tax {

	/**
	 * Save tax rates
	 *
	 * @param array $tax_rate Rate description
	 *
	 * @return int
	 */
	public function insert_tax( $tax_rate ) {
		//TODO : Insert just one time a rate , difficult
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'woocommerce_tax_rates', $tax_rate );

		return $wpdb->insert_id;
	}

	/**
	 * Update tax rates
	 *
	 * @param int $tax_rate_id Element to update
	 * @param array $tax_rate Rate description
	 */
	public function update_tax( $tax_rate_id, $tax_rate ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . "woocommerce_tax_rates",
			$tax_rate,
			array(
				'tax_rate_id' => $tax_rate_id
			)
		);
	}

	/**
	 * Get the tax class associated with a VAT rate
	 *
	 * @param float $tax_rate a product VAT rate
	 *
	 * @return string   the tax class corresponding to the input VAT rate
	 */
	public function get_tax_class( $tax_rate ) {
		// Add missing standard rate
		$nametaxclasses = $this->get_tax_classes();
		$nametaxclasses[] = '';
		foreach($nametaxclasses as $unetaxclass) {
			$lestaxes = $this->get_rates($unetaxclass);
			if (array_values($lestaxes)[0]['rate'] == $tax_rate) {
				return $unetaxclass;
			}
		}
	}

	/**
	 * Create tax classes for Dolibarr tax rates
	 */
	public function create_custom_tax_classes() {
		$tax_name = __( 'VAT', 'doliwoo' );
		//first, create the rates
		$data = array(
			array(
				'tax_rate_country'  => 'FR',
				'tax_rate'          => '20',
				'tax_rate_name'     => $tax_name,
				'tax_rate_priority' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => ''
			),
			array(
				'tax_rate_country'  => 'FR',
				'tax_rate'          => '10',
				'tax_rate_name'     => $tax_name,
				'tax_rate_priority' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => 'reduced'
			),
			array(
				'tax_rate_country'  => 'FR',
				'tax_rate'          => '5',
				'tax_rate_name'     => $tax_name,
				'tax_rate_priority' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => 'super-reduced'
			),
			array(
				'tax_rate_country'  => 'FR',
				'tax_rate'          => '2.1',
				'tax_rate_name'     => $tax_name,
				'tax_rate_priority' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => 'minimum'
			),
			array(
				'tax_rate_country'  => 'FR',
				'tax_rate'          => '0',
				'tax_rate_name'     => $tax_name,
				'tax_rate_priority' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => 'zero'
			)
		);
		foreach ( $data as $entry ) {
			$this->insert_tax( $entry );
		}
		// Now take care of classes
		update_option( 'woocommerce_tax_classes',
			"Reduced\nSuper-reduced\nMinimum\nZero" );
	}
}
