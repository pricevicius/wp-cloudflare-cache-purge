<?php

/**
 * Base compartilhada: padrões das opções, prefixo de tags, listas de IP.
 * Carregada antes de tudo e não depende das credenciais da Cloudflare.
 */

/**
 * Valor padrão de cada opção de proteção e de cache. É a única fonte dos
 * padrões: a tela do admin e o código de execução leem daqui, então os dois
 * nunca divergem.
 */
function cfcp_defaults()
{
	return [
		// Cache
		'cfcp_cache_ttl_default'    => 3600,
		'cfcp_cache_ttl_home'       => 900,
		'cfcp_cache_ttl_404'        => 300,
		'cfcp_cache_ttl_feed'       => 300,
		'cfcp_cache_ttl_browser'    => 120,
		'cfcp_cache_ttl_redirect'   => 300,
		'cfcp_cache_ttl_search'     => 0,
		'cfcp_swr'                  => 60,
		'cfcp_sie'                  => 86400,

		// Proteção da origem
		'cfcp_guard_mode'           => 'observe',
		'cfcp_guard_allow_ips'      => '',
		'cfcp_trusted_proxies'      => '',
		'cfcp_real_ip_header'       => 'CF-Connecting-IP',
		'cfcp_override_remote_addr' => '0',

		// Busca
		'cfcp_search_min_len'        => 2,
		'cfcp_search_max_len'        => 100,
		'cfcp_search_max_concurrent' => 4,
		'cfcp_search_rate_limit'     => 10,
		'cfcp_search_rate_window'    => 60,
		'cfcp_search_http_timeout'   => 3,
		'cfcp_search_max_pages'      => 10,
		'cfcp_search_native_policy'  => 'allow',
		'cfcp_search_rest_routes'    => "/celersearch/v1/search\n/celersearch/v1/autocomplete\n/celersearch/v1/chat",
		'cfcp_search_noindex'        => '1',

		// Paginação profunda
		'cfcp_archive_max_pages'    => 200,
	];
}

function cfcp_get($key)
{
	$defaults = cfcp_defaults();
	$default  = isset($defaults[$key]) ? $defaults[$key] : '';
	$value    = get_option($key, $default);

	// Campo numérico em branco no admin volta para o padrão.
	if ($value === '' && is_int($default)) {
		return $default;
	}

	return $value;
}

function cfcp_int($key)
{
	return max(0, (int) cfcp_get($key));
}

/**
 * Prefixo das cache tags deste site. Sites que dividem a mesma zona da
 * Cloudflare (ex: raiz e /pe) precisam de prefixos diferentes, senão purgar
 * 'post-123' limpa o post 123 dos dois. Também prefixa as chaves de
 * contadores e travas, para os sites não dividirem limites entre si.
 */
function cfcp_tag_prefix()
{
	static $prefix = null;

	if ($prefix !== null) {
		return $prefix;
	}

	if (defined('CFCP_TAG_PREFIX') && CFCP_TAG_PREFIX !== '') {
		$prefix = sanitize_key(CFCP_TAG_PREFIX);
	} else {
		$path   = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
		$prefix = $path !== '' ? sanitize_title(str_replace('/', '-', $path)) : 'root';
	}

	$prefix = (string) apply_filters('cfcp_tag_prefix', $prefix);

	return $prefix;
}

function cfcp_tag($tag)
{
	return cfcp_tag_prefix() . '-' . $tag;
}

/**
 * Texto de textarea (uma entrada por linha, ou separadas por vírgula) em array.
 */
function cfcp_parse_list($text)
{
	$items = preg_split('/[\r\n,]+/', (string) $text);
	$items = array_map('trim', $items);

	return array_values(array_filter($items, function ($item) {
		return $item !== '' && $item[0] !== '#';
	}));
}

/**
 * IP contra um IP exato ou um bloco CIDR (IPv4 e IPv6).
 */
function cfcp_ip_match($ip, $rule)
{
	$rule = trim((string) $rule);
	$ipb  = @inet_pton((string) $ip);

	if ($rule === '' || $ipb === false) {
		return false;
	}

	if (strpos($rule, '/') === false) {
		return $ipb === @inet_pton($rule);
	}

	list($net, $bits) = explode('/', $rule, 2);
	$netb = @inet_pton($net);
	$bits = (int) $bits;

	if ($netb === false || strlen($ipb) !== strlen($netb) || $bits < 0 || $bits > strlen($ipb) * 8) {
		return false;
	}

	$bytes = intdiv($bits, 8);
	$rest  = $bits % 8;

	if ($bytes > 0 && substr($ipb, 0, $bytes) !== substr($netb, 0, $bytes)) {
		return false;
	}

	if ($rest > 0) {
		$mask = (0xFF << (8 - $rest)) & 0xFF;

		if ((ord($ipb[$bytes]) & $mask) !== (ord($netb[$bytes]) & $mask)) {
			return false;
		}
	}

	return true;
}

function cfcp_ip_in_list($ip, array $rules)
{
	foreach ($rules as $rule) {
		if (cfcp_ip_match($ip, $rule)) {
			return true;
		}
	}

	return false;
}

/**
 * IP do visitante. O cabeçalho configurado (ex: CF-Connecting-IP) só é
 * confiado quando a conexão vem de um proxy da lista de proxies confiáveis;
 * de qualquer outra origem ele poderia ser forjado por quem acessa direto.
 * Em cadeias com mais de um IP (X-Forwarded-For), vale o primeiro da direita
 * que não é um proxy confiável.
 */
function cfcp_client_ip()
{
	static $ip = null;

	if ($ip !== null) {
		return $ip;
	}

	$remote  = isset($GLOBALS['cfcp_remote_addr_original']) ? $GLOBALS['cfcp_remote_addr_original'] : (isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');
	$found   = $remote;
	$trusted = cfcp_parse_list(cfcp_get('cfcp_trusted_proxies'));

	if ($remote !== '' && !empty($trusted) && cfcp_ip_in_list($remote, $trusted)) {
		$server_key = 'HTTP_' . strtoupper(str_replace('-', '_', (string) cfcp_get('cfcp_real_ip_header')));

		if (!empty($_SERVER[$server_key])) {
			$chain = array_reverse(array_map('trim', explode(',', (string) $_SERVER[$server_key])));

			foreach ($chain as $candidate) {
				if (!filter_var($candidate, FILTER_VALIDATE_IP) || cfcp_ip_in_list($candidate, $trusted)) {
					continue;
				}

				$found = $candidate;
				break;
			}
		}
	}

	$ip = (string) apply_filters('cfcp_client_ip', $found);

	return $ip;
}

/**
 * Opcional: faz o WordPress inteiro (comentários, formulários, outros
 * plugins) enxergar o IP do visitante em REMOTE_ADDR, e não o do proxy.
 */
function cfcp_maybe_override_remote_addr()
{
	if (cfcp_get('cfcp_override_remote_addr') !== '1') {
		return;
	}

	$GLOBALS['cfcp_remote_addr_original'] = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

	$ip = cfcp_client_ip();

	if ($ip !== '' && $ip !== $GLOBALS['cfcp_remote_addr_original']) {
		$_SERVER['REMOTE_ADDR'] = $ip;
	}
}
cfcp_maybe_override_remote_addr();
