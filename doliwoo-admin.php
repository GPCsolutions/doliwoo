<?php
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
if ($_POST['action'] == 'update') {
    $s = '<?php

$webservs_url = \'' . sanitize_text_field($_POST["webservs_url"]) . '\';	// If not a page, should end with /
$ns = \'http://www.dolibarr.org/ns/\';
$authentication = array(
    \'dolibarrkey\'=> \'' . sanitize_text_field($_POST["dolibarr_key"]) . '\',
    \'sourceapplication\'=>\'' . sanitize_text_field($_POST["source_application"]) . '\',
    \'login\'=>\'' . sanitize_text_field($_POST['dolibarr_login']) . '\',
    \'password\'=> \'' . sanitize_text_field($_POST['dolibarr_password']) . '\',
    \'entity\'=>\'' . sanitize_text_field($_POST['dolibarr_entity']) . '\');
$category_id = \'' . sanitize_text_field($_POST['dolibarr_category_id']) . '\';
$generic_id = \'' . sanitize_text_field($_POST['dolibarr_generic_id']) . '\';';
    $fp = fopen(plugin_dir_path(__FILE__) . "conf.php", "w");
    if ($fp) {
        $res = fwrite($fp, $s);
        if (!$res) {
            do_action('admin_notices', 'Could not write in doliwoo/conf.php');
        }
        fclose($fp);
    } else {
        do_action('admin_notices', 'Could not open doliwoo/conf.php');
    }
}
if (file_exists(plugin_dir_path(__FILE__) . 'conf.php')) require_once 'conf.php';
echo '<div class="wrap">',
'<form method="post" action="' . $_SERVER['REQUEST_URI'] . '">',
'<input type="hidden" name="action" value="update">',
'<h2>' . __('Doliwoo settings', 'doliwoo') . '</h2>',
'<table class="wc_tax_rates widefat">',
    '<tr><td>' . __('Dolibarr webservice URL', 'doliwoo') . '</td><td><input type="text" value="' . $webservs_url . '" name="webservs_url" class="regular-text"></td></tr>',
    '<tr><td>' . __('Dolibarr webservice key', 'doliwoo') . '</td><td><input type="text" value="' . $authentication['dolibarrkey'] . '" name="dolibarr_key" class="regular-text"></td></tr>',
    '<tr><td>' . __('Source application', 'doliwoo') . '</td><td><input type="text" value="' . $authentication['sourceapplication'] . '" name="source_application" class="regular-text"></td></tr>',
    '<tr><td>' . __('Dolibarr login', 'doliwoo') . '</td><td><input type="text" value="' . $authentication['login'] . '" name="dolibarr_login" class="regular-text"></td></tr>',
    '<tr><td>' . __('Dolibarr password', 'doliwoo') . '</td><td><input type="text" value="' . $authentication['password'] . '" name="dolibarr_password" class="regular-text"></td></tr>',
    '<tr><td>' . __('Dolibarr entity', 'doliwoo') . '</td><td><input type="text" value="' . $authentication['entity'] . '" name="dolibarr_entity" class="regular-text"></td></tr>',
    '<tr><td>' . __('Dolibarr category ID', 'doliwoo') . '</td><td><input type="text" value="' . $category_id . '" name="dolibarr_category_id" class="regular-text"></td></tr>',
    '<tr><td>' . __('Dolibarr generic user ID', 'doliwoo') . '</td><td><input type="text" value="' . $generic_id . '" name="dolibarr_generic_id" class="regular-text"></td></tr>',
'</table>',
'<input type="submit" class="button-primary" name="save" value="' . __('Save changes', 'woocommerce') . '">',
'</form>',
'</div>';


