<?php
/**
* Plugin Name: WP Cloudflare Cache Purge
* Description: Limpa o cache diretamente na Cloudflare sempre que posts ou páginas são atualizados no WordPress.
* Version: 1.0.0
* Author: Pricevicius
* Text Domain: wp-cloudflare-cache-purge
* License: MIT
* Requires at least: 5.0
* Requires PHP: 7.4
*/

if (!defined('ABSPATH')) exit;
if(WP_DEBUG === true){
    error_reporting(E_ALL);
    ini_set('display_errors',1);
}
require 'application/init.php';