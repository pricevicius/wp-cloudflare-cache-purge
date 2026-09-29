<?php

/**
 * PROTEÇÃO DA ORIGEM
 *
 * Em site de muito acesso, o que derruba o servidor quase nunca é a página
 * que está no cache: é o que o cache não segura. A busca é o caso clássico:
 * cada termo é uma URL diferente, então um robô que varia o termo passa por
 * cima de qualquer cache e cada requisição ocupa um processo do PHP-FPM.
 * Se a busca depende de um serviço externo (ex: Meilisearch) e ele fica
 * lento, os processos ficam presos esperando e o site inteiro para.
 *
 * Tudo aqui roda dentro do WordPress, antes da consulta pesada, e funciona
 * com a busca nativa ou com um motor externo. Todo controle respeita o modo:
 *
 *  off      não faz nada.
 *  observe  só conta o que teria bloqueado (o Diagnóstico mostra os números).
 *  enforce  bloqueia de verdade.
 *
 * Quem tem permissão de editar posts (a redação) nunca é limitado, nem os
 * IPs da lista de liberados.
 */

function cfcp_guard_mode()
{
	$mode = cfcp_get('cfcp_guard_mode');

	return in_array($mode, ['off', 'observe', 'enforce'], true) ? $mode : 'observe';
}

function cfcp_guard_skip()
{
	if ((defined('WP_CLI') && WP_CLI) || php_sapi_name() === 'cli' || wp_doing_cron() || is_admin()) {
		return true;
	}

	if (cfcp_guard_mode() === 'off') {
		return true;
	}

	if (is_user_logged_in() && current_user_can('edit_posts')) {
		return true;
	}

	$ip = cfcp_client_ip();

	if ($ip !== '' && cfcp_ip_in_list($ip, cfcp_parse_list(cfcp_get('cfcp_guard_allow_ips')))) {
		return true;
	}

	return false;
}

/**
 * Resposta curta de bloqueio. Nunca é guardada no navegador; na Cloudflare
 * só quando $opts['cache_ttl'] pede (ex: página de arquivo que não existe).
 */
function cfcp_guard_respond($status, $message, $opts = [])
{
	$rule  = isset($opts['rule']) ? $opts['rule'] : '';
	$retry = isset($opts['retry_after']) ? (int) $opts['retry_after'] : 0;
	$cache = isset($opts['cache_ttl']) ? (int) $opts['cache_ttl'] : 0;
	$json  = !empty($GLOBALS['cfcp_guard_json']);

	if (!headers_sent()) {
		status_header($status);
		header_remove('CDN-Cache-Control');
		header_remove('Cache-Control');
		header_remove('Expires');
		header_remove('Last-Modified');
		header_remove('ETag');

		if ($cache > 0) {
			header('CDN-Cache-Control: public, max-age=' . $cache);
			header('Cache-Control: max-age=0');
		} else {
			header('CDN-Cache-Control: no-store');
			header('Cache-Control: no-store, max-age=0');
		}

		if ($retry > 0) {
			header('Retry-After: ' . $retry);
		}

		header('X-Robots-Tag: noindex, nofollow');
		header('X-CFCP-Guard: ' . $rule);
		header('Content-Type: ' . ($json ? 'application/json' : 'text/html') . '; charset=utf-8');
	}

	if ($json) {
		echo wp_json_encode(['code' => 'cfcp_guard', 'message' => $message]);
	} else {
		echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<meta name="robots" content="noindex,nofollow"><title>' . esc_html($message) . '</title></head>'
			. '<body style="font-family:sans-serif;max-width:32em;margin:15vh auto;padding:0 1em;text-align:center">'
			. '<h1 style="font-size:1.4em">' . esc_html($message) . '</h1>'
			. '<p><a href="' . esc_url(home_url('/')) . '">Voltar para a página inicial</a></p></body></html>';
	}

	exit;
}

/**
 * Registra uma violação. No modo enforce responde e encerra; no observe só
 * conta ("teria bloqueado") e deixa a requisição seguir.
 */
function cfcp_guard_violation($rule, $status, $message, $opts = [])
{
	if (cfcp_guard_mode() === 'enforce') {
		cfcp_metric('blocked_' . $rule);
		$opts['rule'] = $rule;
		cfcp_guard_respond($status, $message, $opts);
	}

	cfcp_metric('would_' . $rule);

	return false;
}

/**
 * BUSCA
 */
function cfcp_search_guard_run($term, $paged, $check_term = true, $check_pages = true)
{
	cfcp_metric('search_requests');

	$GLOBALS['cfcp_in_search']  = true;
	$GLOBALS['cfcp_search_t0']  = microtime(true);
	add_action('shutdown', 'cfcp_search_finish', 1);

	if ($check_term) {
		$term = trim((string) $term);
		$len  = function_exists('mb_strlen') ? mb_strlen($term) : strlen($term);
		$min  = cfcp_int('cfcp_search_min_len');
		$max  = cfcp_int('cfcp_search_max_len');

		if (($min > 0 && $len < $min) || ($max > 0 && $len > $max)) {
			cfcp_guard_violation('search_term', 400, 'Termo de busca inválido.');
		}
	}

	if ($check_pages) {
		$max_pages = cfcp_int('cfcp_search_max_pages');

		if ($max_pages > 0 && $paged > $max_pages) {
			cfcp_guard_violation('search_pages', 404, 'Página de resultados inexistente.', ['cache_ttl' => 300]);
		}
	}

	$limit  = cfcp_int('cfcp_search_rate_limit');
	$window = max(1, cfcp_int('cfcp_search_rate_window'));
	$ip     = cfcp_client_ip();

	if ($limit > 0 && $ip !== '') {
		$bucket = (int) floor(time() / $window);
		$count  = cfcp_store_incr('rl:search:' . md5($ip) . ':' . $bucket, $window * 2);

		if ($count > $limit) {
			cfcp_guard_violation('search_rate', 429, 'Muitas buscas em pouco tempo. Aguarde alguns instantes.', ['retry_after' => $window]);
		}
	}

	cfcp_search_acquire_slot();
}

/**
 * Limite de buscas simultâneas: N "vagas". Uma busca ocupa uma vaga até
 * terminar; se todas estão ocupadas, a seguinte recebe 503 na hora, sem
 * prender um processo do PHP-FPM. É esse limite que isola o site quando o
 * motor de busca fica lento. A vaga tem prazo de validade, então se o
 * processo morrer no meio ela se libera sozinha.
 */
function cfcp_search_acquire_slot()
{
	$max = cfcp_int('cfcp_search_max_concurrent');

	if ($max <= 0) {
		return;
	}

	$cap = cfcp_int('cfcp_search_http_timeout');
	$cap = (cfcp_guard_mode() === 'enforce' && $cap > 0) ? $cap : 30;
	$ttl = $cap + 15;

	for ($i = 0; $i < $max; $i++) {
		if (cfcp_store_add('slot:search:' . $i, $ttl)) {
			$GLOBALS['cfcp_search_slot'] = $i;
			return;
		}
	}

	cfcp_guard_violation('search_busy', 503, 'A busca está com muitos acessos no momento. Tente novamente em instantes.', ['retry_after' => 5]);
}

/**
 * No fim da requisição: libera a vaga e registra o tempo da busca.
 */
function cfcp_search_finish()
{
	if (isset($GLOBALS['cfcp_search_slot'])) {
		cfcp_store_delete('slot:search:' . $GLOBALS['cfcp_search_slot']);
		unset($GLOBALS['cfcp_search_slot']);
	}

	if (!empty($GLOBALS['cfcp_search_t0'])) {
		$ms = (int) round((microtime(true) - $GLOBALS['cfcp_search_t0']) * 1000);
		$GLOBALS['cfcp_search_t0'] = 0;

		cfcp_metric('search_done');
		cfcp_metric('search_ms', $ms);

		if ($ms >= 3000) {
			cfcp_metric('search_slow');
		}
	}
}

/**
 * Busca do site (?s=)
 */
function cfcp_search_guard($query)
{
	if (is_admin() || !$query->is_main_query() || !$query->is_search() || cfcp_guard_skip()) {
		return;
	}

	cfcp_search_guard_run($query->get('s'), max(1, (int) $query->get('paged')));
}
add_action('pre_get_posts', 'cfcp_search_guard', 0);

/**
 * Rotas REST de busca/autocompletar (lista editável no admin). O
 * autocompletar dispara uma consulta por tecla digitada, então é onde o
 * limite por IP mais importa.
 */
function cfcp_rest_search_guard($result, $server, $request)
{
	if ($result !== null) {
		return $result;
	}

	$route = $request->get_route();
	$match = false;

	foreach (cfcp_parse_list(cfcp_get('cfcp_search_rest_routes')) as $prefix) {
		if (strpos($route, $prefix) === 0) {
			$match = true;
			break;
		}
	}

	if (!$match || cfcp_guard_skip()) {
		return $result;
	}

	$GLOBALS['cfcp_guard_json'] = true;

	$term = '';

	foreach (['q', 'query', 's', 'search', 'term'] as $param) {
		$value = $request->get_param($param);

		if (is_string($value) && $value !== '') {
			$term = $value;
			break;
		}
	}

	// Só valida o termo quando a rota recebeu algum (o chat, por exemplo, não).
	cfcp_search_guard_run($term, 1, $term !== '', false);

	return $result;
}
add_filter('rest_pre_dispatch', 'cfcp_rest_search_guard', 0, 3);

/**
 * Prazo máximo das chamadas externas durante uma busca. O cliente do
 * Meilisearch espera até 30 s; aqui isso cai para o valor configurado, então
 * um motor lento não segura o processo do PHP-FPM por meio minuto.
 * Só vale no modo enforce e só durante requisições de busca.
 */
function cfcp_search_http_args($args, $url)
{
	if (empty($GLOBALS['cfcp_in_search']) || cfcp_guard_mode() !== 'enforce') {
		return $args;
	}

	$cap = cfcp_int('cfcp_search_http_timeout');

	if ($cap > 0 && (!isset($args['timeout']) || $args['timeout'] > $cap)) {
		$args['timeout'] = $cap;
	}

	return $args;
}
add_filter('http_request_args', 'cfcp_search_http_args', 10, 2);

/**
 * Busca nativa do WordPress (LIKE no banco). Quando o motor externo falha,
 * alguns plugins voltam para ela, e é justamente a pior consulta possível
 * num banco já sob pressão. Com a política "block" a busca nativa nunca roda:
 * o leitor recebe "busca indisponível" (503), o banco é poupado.
 * Só ative se a busca do site depende de um motor externo.
 */
function cfcp_search_native_guard($posts, $query)
{
	if ($posts !== null || empty($GLOBALS['cfcp_in_search']) || !$query->is_main_query() || !$query->is_search()) {
		return $posts;
	}

	// O que decide é se o LIKE do WordPress está prestes a rodar: só roda com um
	// termo de busca na consulta. Um motor externo que atendeu a busca troca
	// o termo por uma lista de IDs e zera o 's'; se falhou, o termo continua
	// ali e a busca nativa assume. Assim resultado vazio do motor não conta.
	if (trim((string) $query->get('s')) === '') {
		return $posts;
	}

	if (cfcp_get('cfcp_search_native_policy') !== 'block') {
		return $posts;
	}

	cfcp_guard_violation('search_native', 503, 'A busca está temporariamente indisponível. Tente novamente em instantes.', ['retry_after' => 10]);

	return $posts;
}
add_filter('posts_pre_query', 'cfcp_search_native_guard', 999, 2);

/**
 * Busca não é conteúdo: não indexar, não rastrear.
 */
function cfcp_search_noindex()
{
	if (cfcp_get('cfcp_search_noindex') === '1' && is_search() && !headers_sent()) {
		header('X-Robots-Tag: noindex, follow');
	}
}
add_action('template_redirect', 'cfcp_search_noindex', 0);

function cfcp_robots_txt($output, $public)
{
	if (cfcp_get('cfcp_search_noindex') !== '1') {
		return $output;
	}

	global $wp_rewrite;

	$search_base = isset($wp_rewrite->search_base) && $wp_rewrite->search_base !== '' ? $wp_rewrite->search_base : 'search';

	return rtrim($output) . "\n\n# Busca do site: não rastrear (WP Cloudflare Cache Purge)\n"
		. "User-agent: *\n"
		. "Disallow: /?s=\n"
		. "Disallow: /*?s=\n"
		. "Disallow: /*&s=\n"
		. "Disallow: /" . $search_base . "/\n";
}
add_filter('robots_txt', 'cfcp_robots_txt', 20, 2);

/**
 * PAGINAÇÃO PROFUNDA
 *
 * /page/9999/ é o alvo clássico de robô: cada página fundo no arquivo
 * exige uma consulta grande e nunca está no cache (e numa home estática
 * devolve a home inteira, uma cópia nova por URL). Passou do teto, a
 * resposta é um 404 curto (que a Cloudflare pode guardar por alguns minutos).
 */
function cfcp_current_paged($query)
{
	$paged = (int) $query->get('paged');

	// Numa home estática o WordPress zera o 'paged' da consulta, mas o
	// número pedido na URL continua em $wp->query_vars.
	if ($paged === 0 && $query->is_paged() && isset($GLOBALS['wp']->query_vars['paged'])) {
		$paged = (int) $GLOBALS['wp']->query_vars['paged'];
	}

	return $paged;
}

function cfcp_archive_guard($query)
{
	if (is_admin() || !$query->is_main_query() || $query->is_search() || $query->is_feed()) {
		return;
	}

	$max = cfcp_int('cfcp_archive_max_pages');

	if ($max <= 0 || cfcp_current_paged($query) <= $max) {
		return;
	}

	// Vale para qualquer página do site, não só listagens: numa home estática o
	// WordPress devolve a home inteira para /page/9999/, e cada URL dessas é
	// uma renderização completa que nunca está no cache.
	if (cfcp_guard_skip()) {
		return;
	}

	cfcp_guard_violation('deep_page', 404, 'Página inexistente.', ['cache_ttl' => 300]);
}
add_action('pre_get_posts', 'cfcp_archive_guard', 0);
