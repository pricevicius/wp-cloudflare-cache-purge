<?php
require "inc/config.php";
require "inc/cache-headers.php";

$cfcp_email = get_option('cfcp_email');
$cfcp_api = get_option('cfcp_api');
$cfcp_zone = get_option('cfcp_zone');
if (!empty($cfcp_email) && !empty($cfcp_api) && !empty($cfcp_zone)) {
	define('CFCP_URL', $cfcp_email);
	define('CFCP_SECRETKEY', $cfcp_api);
	define('CFCP_ZONE', $cfcp_zone);
	require 'class/class-cfcp-purger.php';
	require "inc/functions.php";
}