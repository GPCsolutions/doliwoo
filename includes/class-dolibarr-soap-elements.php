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

class DolibarrOrder {
	/** @var int */
	public $thirdparty_id;
	/** @var ? */
	public $date;
	/** @var int */
	public $status;
	/** @var DolibarrOrderLine[] */
	public $lines;
}

class DolibarrOrderLine {
	/**
	 * @var int
	 */
	public $type;

	/**
	 * @var boolean
	 */
	public $desc;

	/**
	 * @var int
	 */
	public $product_id;

	/**
	 * @var int
	 */
	public $vat_rate;

	/**
	 * @var int
	 */
	public $qty;

	/**
	 * @var float
	 */
	public $price;

	/**
	 * @var float
	 */
	public $unitprice;

	/**
	 * @var float
	 */
	public $total_net;

	/**
	 * @var float
	 */
	public $total;

	/**
	 * @var int
	 */
	public $total_vat;

}

class DolibarrThirdparty {
	/**
	 * @var string
	 */
	public $ref;

	/**
	 * @var string
	 */
	public $status;

	/**
	 * @var string
	 */
	public $client;

	/**
	 * @var string
	 */
	public $supplier;

	/***
	 * @var
	 */
	public $address ;

	/**
	 * @var string
	 */
	public $zip;

	/**
	 * @var string
	 */
	public $town;

	/**
	 * @var string
	 */
	public $country_code;

	/**
	 * @var string
	 */
	public $supplier_code;

	/**
	 * @var string
	 */
	public $phone;

	/**
	 * @var string
	 */
	public $email;

	/**
	 * @var string
	 */
	public $individual;

	/**
	 * @var string
	 */
	public $firstname;

}