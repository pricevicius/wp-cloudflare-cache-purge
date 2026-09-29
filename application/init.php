<?php
require "inc/common.php";
require "inc/store.php";
require "inc/config.php";
require "inc/cache-headers.php";
require "inc/guard.php";
require "inc/diagnostics.php";

// Constantes no wp-config.php têm preferência sobre as opções do admin, para
// que vários sites (ex: raiz e /pe) usem as mesmas credenciais sem repetir.
$cfcp_email = get_option('cfcp_email');
$cfcp_api   = get_option('cfcp_api');
$cfcp_token = defined('CFCP_CF_TOKEN') ? CFCP_CF_TOKEN : get_option('cfcp_api_token');
$cfcp_zone  = defined('CFCP_CF_ZONE_ID') ? CFCP_CF_ZONE_ID : get_option('cfcp_zone');

$cfcp_has_token = !empty($cfcp_token);
$cfcp_has_key   = !empty($cfcp_email) && !empty($cfcp_api);

if (!empty($cfcp_zone) && ($cfcp_has_token || $cfcp_has_key)) {
	define('CFCP_URL', (string) $cfcp_email);
	define('CFCP_SECRETKEY', (string) $cfcp_api);
	define('CFCP_ZONE', (string) $cfcp_zone);
	define('CFCP_TOKEN', (string) $cfcp_token);
	require 'class/class-cfcp-purger.php';
	require "inc/functions.php";
}
