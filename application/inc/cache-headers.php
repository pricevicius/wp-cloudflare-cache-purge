<?php

/**
 * Envia os headers de Cache-Control/CDN-Cache-Control com base nas
 * configurações do admin, substituindo o antigo cache-control.php
 * incluído manualmente no header do tema.
 */
function cfcp_get_cache_ttl_option($option_name, $default)
{
	$value = get_option($option_name, '');

	if ($value === '' || $value === false) {
		return $default;
	}

	return max(0, (int) $value);
}

function cfcp_send_cache_control_headers()
{
	if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
		return;
	}

	if (headers_sent()) {
		return;
	}

	if (!get_option('cfcp_cache_control_enabled', '1')) {
		return;
	}

	if (is_preview() || is_user_logged_in()) {
		nocache_headers();
		header_remove('CDN-Cache-Control');
		header('CDN-Cache-Control: max-age=0');
		header_remove('Cache-Control');
		header('Cache-Control: max-age=0');
		return;
	}

	if (is_feed()) {
		$ttl_feed = cfcp_get_cache_ttl_option('cfcp_cache_ttl_feed', 300);

		header_remove('Set-Cookie');
		header_remove('Expires');
		header('Cache-Control: public, max-age=' . $ttl_feed);
		return;
	}

	$ttl = cfcp_get_cache_ttl_option('cfcp_cache_ttl_default', 2592000);

	if (is_404()) {
		$ttl = cfcp_get_cache_ttl_option('cfcp_cache_ttl_404', 2592000);
	} elseif (is_home() || is_front_page()) {
		$ttl = cfcp_get_cache_ttl_option('cfcp_cache_ttl_home', 900);
	}

	header_remove('Last-Modified');
	header_remove('ETag');
	header_remove('Expires');
	header('CDN-Cache-Control: public, max-age=' . $ttl);
	header('Cache-Control: public, max-age=' . $ttl);
}
add_action('send_headers', 'cfcp_send_cache_control_headers', 5);
