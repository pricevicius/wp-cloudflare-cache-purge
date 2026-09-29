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

/**
 * Header de diagnóstico enviado em toda resposta (mesmo quando o
 * controle está desativado), pra dar um double-check rápido via
 * `curl -I` ou na aba Network do navegador de qual regra/TTL foi
 * aplicado, sem depender de comentário no HTML (que some em feeds,
 * 404 sem template e é removido por minificadores).
 */
function cfcp_send_cache_debug_header($scope, $ttl = null, $enabled = true)
{
	if (headers_sent()) {
		return;
	}

	$parts = [
		'enabled=' . ($enabled ? '1' : '0'),
		'scope=' . $scope,
	];

	if ($ttl !== null) {
		$parts[] = 'ttl=' . $ttl;
	}

	header('X-CFCP-Cache-Rule: ' . implode(';', $parts));
}

/**
 * Resposta que nunca deve ser guardada (logado, preview, busca).
 */
function cfcp_send_bypass_headers($scope = 'bypass')
{
	nocache_headers();
	header_remove('CDN-Cache-Control');
	header('CDN-Cache-Control: max-age=0');
	header_remove('Cache-Control');
	header('Cache-Control: max-age=0');
	cfcp_send_cache_debug_header($scope, 0);
}

/**
 * O TTL do admin vale para a Cloudflare (CDN-Cache-Control). O navegador
 * do leitor recebe um TTL curto no Cache-Control, porque a Cloudflare dá
 * pra limpar e o navegador não: assim uma correção numa matéria chega ao
 * leitor em poucos minutos, e quem expira no navegador cai no cache da
 * Cloudflare, não na origem.
 */
function cfcp_send_cache_control_headers()
{
	if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
		return;
	}

	if (headers_sent()) {
		return;
	}

	if (!get_option('cfcp_cache_control_enabled', '1')) {
		cfcp_send_cache_debug_header('disabled', null, false);
		return;
	}

	if (is_preview() || is_user_logged_in()) {
		cfcp_send_bypass_headers('bypass');
		return;
	}

	$search_ttl = 0;

	if (is_search()) {
		// Sem cache por padrão: cada termo é uma URL diferente e o cache não
		// protege contra robô. O micro-cache serve para termo em alta.
		$search_ttl = cfcp_get_cache_ttl_option('cfcp_cache_ttl_search', 0);

		if ($search_ttl <= 0) {
			cfcp_send_bypass_headers('search');
			return;
		}
	}

	if (is_feed()) {
		$ttl_feed = cfcp_get_cache_ttl_option('cfcp_cache_ttl_feed', 300);

		header_remove('Set-Cookie');
		header_remove('Expires');
		header('Cache-Control: public, max-age=' . $ttl_feed);
		cfcp_send_cache_debug_header('feed', $ttl_feed);
		return;
	}

	$scope = 'default';
	$ttl   = cfcp_get_cache_ttl_option('cfcp_cache_ttl_default', 3600);

	if ($search_ttl > 0) {
		$scope = 'search';
		$ttl   = $search_ttl;
	} elseif (is_404()) {
		$scope = '404';
		$ttl   = cfcp_get_cache_ttl_option('cfcp_cache_ttl_404', 300);
	} elseif (is_home() || is_front_page()) {
		$scope = 'home';
		$ttl   = cfcp_get_cache_ttl_option('cfcp_cache_ttl_home', 900);
	}

	$ttl_browser = min($ttl, cfcp_get_cache_ttl_option('cfcp_cache_ttl_browser', 120));

	header_remove('Last-Modified');
	header_remove('ETag');
	header_remove('Expires');
	// stale-while-revalidate: quando o cache expira, só UMA requisição vai à
	// origem buscar a versão nova e as demais seguem recebendo a antiga. Sem
	// isso, na expiração de uma página muito acessada todas vão à origem juntas.
	// stale-if-error: se a origem estiver fora do ar ou com erro, a Cloudflare
	// continua servindo a última cópia guardada.
	$cdn = 'public, max-age=' . $ttl;

	if ($ttl > 0 && $scope !== '404') {
		$swr = cfcp_int('cfcp_swr');
		$sie = cfcp_int('cfcp_sie');

		if ($swr > 0) {
			$cdn .= ', stale-while-revalidate=' . $swr;
		}

		if ($sie > 0) {
			$cdn .= ', stale-if-error=' . $sie;
		}
	}

	header('CDN-Cache-Control: ' . $cdn);
	header('Cache-Control: public, max-age=' . $ttl_browser);
	cfcp_send_cache_debug_header($scope, $ttl);
}
add_action('send_headers', 'cfcp_send_cache_control_headers', 5);

/**
 * Redirects (ex: URL antiga -> nova) são decididos depois do send_headers,
 * então herdariam o TTL da página que os originou (na 404, 30 dias no
 * padrão antigo). Aqui eles ganham um TTL próprio e curto na Cloudflare e
 * nenhum cache no navegador. Só redirects permanentes de GET/HEAD são
 * cacheáveis; os demais (login, POST, 302) nunca.
 */
function cfcp_redirect_cache_headers($location, $status = 302)
{
	if (headers_sent() || !get_option('cfcp_cache_control_enabled', '1')) {
		return $location;
	}

	if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
		return $location;
	}

	$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

	if (is_user_logged_in() || !in_array($method, ['GET', 'HEAD'], true) || !in_array((int) $status, [301, 308], true)) {
		cfcp_send_bypass_headers('redirect-bypass');
		return $location;
	}

	$ttl = cfcp_get_cache_ttl_option('cfcp_cache_ttl_redirect', 300);

	header_remove('Last-Modified');
	header_remove('ETag');
	header_remove('Expires');
	header_remove('CDN-Cache-Control');
	header('CDN-Cache-Control: public, max-age=' . $ttl);
	header_remove('Cache-Control');
	header('Cache-Control: max-age=0');
	cfcp_send_cache_debug_header('redirect', $ttl);

	return $location;
}
add_filter('wp_redirect', 'cfcp_redirect_cache_headers', 999, 2);
