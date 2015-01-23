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
}