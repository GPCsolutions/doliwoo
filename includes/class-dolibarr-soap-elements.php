<?php
/* Copyright (C) 2015 Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2015 Maxime Lafourcade <rdoursenaud@gpcsolutions.fr>
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
 * SOAP reprentations hinting classes.
 *
 * Just hinting pseudo classes to ease development.
 * These element can be used for or returned by Dolibarr SOAP requests.
 *
 * @package DoliWoo
 */

/**
 * SOAP orders representation.
 */
class Dolibarr_Order {
	/** @var string */
	public $id;

	/** @var int */
	public $thirdparty_id;

	/** @var string ISO 8601 */
	public $date;

	/** @var int */
	public $status;

	/** @var Dolibarr_Order_Line[] */
	public $lines;
}

/**
 * SOAP order lines representation
 */
class Dolibarr_Order_Line {
	/** @var int */
	public $type;

	/** @var boolean */
	public $desc;

	/** @var int */
	public $product_id;

	/** @var float|int */
	public $vat_rate;

	/** @var int */
	public $qty;

	/** @var float|int */
	public $price;

	/** @var float|int */
	public $unitprice;

	/** @var float|int */
	public $total_net;

	/** @var float|int */
	public $total;

	/** @var float|int */
	public $total_vat;
}

/**
 * SOAP third parties representation
 */
class Dolibarr_Thirdparty {
	/** @var string */
	public $id;

	/** @var string */
	public $ref;

	/** @var string */
	public $status;

	/** @var string */
	public $client;

	/** @var string */
	public $supplier;

	/** @var string */
	public $address;

	/** @var string */
	public $zip;

	/** @var string */
	public $town;

	/** @var string */
	public $country_code;

	/** @var string */
	public $supplier_code;

	/** @var string */
	public $phone;

	/** @var string */
	public $email;

	/** @var int */
	public $individual;

	/** @var string */
	public $firstname;
}

/**
 * SOAP products representation
 */
class Dolibarr_Product {
	/** @var string */
	public $id;

	/** @var string */
	public $ref;

	/** @var string */
	public $type;

	/** @var string */
	public $label;

	/** @var string */
	public $description;

	/** @var string */
	public $date_creation;

	/** @var string */
	public $date_modification;

	/** @var string */
	public $note;

	/** @var string */
	public $status_tobuy;

	/** @var string */
	public $status_tosell;

	/** @var string */
	public $barcode_type;

	/** @var string */
	public $country_id;

	/** @var string */
	public $country_code;

	/** @var string */
	public $price_net;

	/** @var string */
	public $price;

	/** @var string */
	public $price_min_net;

	/** @var string */
	public $price_min;

	/** @var string */
	public $price_base_type;

	/** @var string */
	public $vat_rate;

	/** @var string */
	public $vat_npr;

	/** @var string */
	public $localtax1_tx;

	/** @var string */
	public $localtax2_tx;

	/** @var string */
	public $stock_real;

	/** @var string */
	public $dir;

	/** @var array */
	public $images;
}
