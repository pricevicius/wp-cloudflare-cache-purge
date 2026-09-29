<?php

/**
 * DIAGNÓSTICO
 *
 * Reúne num só lugar o que normalmente exige terminal: se a Cloudflare está
 * de fato cacheando, se o IP do visitante chega certo, se a busca está sendo
 * protegida e o quanto. Os testes que falam com a rede só rodam quando alguém
 * clica no botão; a página em si só lê dados locais.
 */

function cfcp_diag_opcache()
{
	if (!function_exists('opcache_get_status')) {
		return null;
	}

	$status = @opcache_get_status(false);

	if (!is_array($status) || empty($status['opcache_enabled'])) {
		return ['enabled' => false];
	}

	$used   = (int) $status['memory_usage']['used_memory'];
	$free   = (int) $status['memory_usage']['free_memory'];
	$wasted = (int) $status['memory_usage']['wasted_memory'];
	$stats  = $status['opcache_statistics'];

	return [
		'enabled'            => true,
		'used_mb'            => round($used / 1048576, 1),
		'free_mb'            => round($free / 1048576, 1),
		'wasted_pct'         => round((float) $status['memory_usage']['current_wasted_percentage'], 1),
		'used_pct'           => $used + $free > 0 ? round($used / ($used + $free) * 100, 1) : 0,
		'hit_rate'           => round((float) $stats['opcache_hit_rate'], 1),
		'cached_scripts'     => (int) $stats['num_cached_scripts'],
		'max_scripts'        => (int) $stats['max_cached_keys'],
		'oom_restarts'       => (int) $stats['oom_restarts'],
		'validate_timestamps' => (bool) ini_get('opcache.validate_timestamps'),
		'revalidate_freq'    => (int) ini_get('opcache.revalidate_freq'),
	];
}

function cfcp_diag_search_stats()
{
	$rules = [
		'search_term'   => 'Termo inválido (curto ou longo demais)',
		'search_pages'  => 'Página de resultados acima do teto',
		'search_rate'   => 'Limite de buscas por IP',
		'search_busy'   => 'Todas as vagas de busca ocupadas',
		'search_native' => 'Busca nativa do WordPress (bloqueada)',
		'deep_page'     => 'Paginação profunda acima do teto',
	];

	$rows = [];

	foreach ($rules as $key => $label) {
		$rows[] = [
			'label'    => $label,
			'would_1'  => cfcp_metric_sum('would_' . $key, 1),
			'would_7'  => cfcp_metric_sum('would_' . $key, 7),
			'blocked_1' => cfcp_metric_sum('blocked_' . $key, 1),
			'blocked_7' => cfcp_metric_sum('blocked_' . $key, 7),
		];
	}

	$done_1 = cfcp_metric_sum('search_done', 1);
	$done_7 = cfcp_metric_sum('search_done', 7);

	return [
		'rows'      => $rows,
		'requests_1' => cfcp_metric_sum('search_requests', 1),
		'requests_7' => cfcp_metric_sum('search_requests', 7),
		'avg_ms_1'  => $done_1 > 0 ? (int) round(cfcp_metric_sum('search_ms', 1) / $done_1) : null,
		'avg_ms_7'  => $done_7 > 0 ? (int) round(cfcp_metric_sum('search_ms', 7) / $done_7) : null,
		'slow_1'    => cfcp_metric_sum('search_slow', 1),
		'slow_7'    => cfcp_metric_sum('search_slow', 7),
	];
}

function cfcp_diag_purge_stats()
{
	return [
		'calls_1'  => cfcp_metric_sum('purge_calls', 1),
		'calls_7'  => cfcp_metric_sum('purge_calls', 7),
		'errors_1' => cfcp_metric_sum('purge_errors', 1),
		'errors_7' => cfcp_metric_sum('purge_errors', 7),
		'r429_7'   => cfcp_metric_sum('purge_429', 7),
		'last'     => get_option('cfcp_last_purge_error', null),
	];
}

/**
 * Situação do IP do visitante nesta requisição.
 */
function cfcp_diag_ip()
{
	$remote = isset($GLOBALS['cfcp_remote_addr_original']) ? $GLOBALS['cfcp_remote_addr_original'] : (isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');
	$header = 'HTTP_' . strtoupper(str_replace('-', '_', (string) cfcp_get('cfcp_real_ip_header')));

	return [
		'remote'        => $remote,
		'resolved'      => cfcp_client_ip(),
		'header_name'   => cfcp_get('cfcp_real_ip_header'),
		'header_value'  => isset($_SERVER[$header]) ? (string) $_SERVER[$header] : '',
		'xff'           => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : '',
		'trusted_set'   => !empty(cfcp_parse_list(cfcp_get('cfcp_trusted_proxies'))),
		'remote_is_trusted' => $remote !== '' && cfcp_ip_in_list($remote, cfcp_parse_list(cfcp_get('cfcp_trusted_proxies'))),
		'remote_private' => $remote !== '' && filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false,
	];
}

/**
 * Lista de verificação: cada item é [nível, título, explicação], com nível
 * ok, warn ou bad.
 */
function cfcp_diag_checklist()
{
	$items = [];
	$mode  = cfcp_guard_mode();

	if ($mode === 'enforce') {
		$items[] = ['ok', 'Proteção da origem ativa', 'Os limites estão bloqueando de verdade.'];
	} elseif ($mode === 'observe') {
		$items[] = ['warn', 'Proteção em modo observação', 'Os limites só contam o que teriam bloqueado. Depois de alguns dias sem falsos positivos na tabela abaixo, mude para "Bloquear".'];
	} else {
		$items[] = ['bad', 'Proteção da origem desligada', 'Nenhum limite de busca ou paginação está sendo aplicado.'];
	}

	$backend = cfcp_store_backend();

	if ($backend === 'object-cache') {
		$items[] = ['ok', 'Contadores no cache de objetos (Redis/Memcached)', 'Os limites valem para todos os processos e servidores.'];
	} elseif ($backend === 'apcu') {
		$items[] = ['ok', 'Contadores em memória compartilhada (APCu)', 'Os limites valem para todos os processos deste servidor. Com mais de um servidor, use Redis.'];
	} else {
		$items[] = ['warn', 'Contadores em arquivos temporários', 'Funciona e é seguro entre processos, mas é mais lento e vale só para este servidor. Redis ou APCu são melhores para muito acesso.'];
	}

	$ip = cfcp_diag_ip();

	if (!$ip['trusted_set'] && $ip['remote_private']) {
		$items[] = ['bad', 'Todos os visitantes aparecem com o mesmo IP', 'O IP que chega ao WordPress (' . $ip['remote'] . ') é interno, de um proxy. Sem informar o proxy em "Proxies confiáveis", o limite por IP trataria o site inteiro como um único visitante.'];
	} elseif ($ip['remote_is_trusted'] && $ip['header_value'] === '') {
		$items[] = ['warn', 'O proxy não enviou o cabeçalho ' . $ip['header_name'], 'A conexão vem de um proxy confiável, mas o cabeçalho com o IP do visitante não chegou. Acesso feito sem passar pela Cloudflare, ou o proxy não repassa esse cabeçalho.'];
	} else {
		$items[] = ['ok', 'IP do visitante resolvido', 'IP nesta requisição: ' . ($ip['resolved'] !== '' ? $ip['resolved'] : 'desconhecido') . '.'];
	}

	if (defined('CFCP_ZONE')) {
		if (defined('CFCP_TOKEN') && CFCP_TOKEN !== '') {
			$items[] = ['ok', 'Purge com API Token', 'Usando um token de permissão restrita.'];
		} else {
			$items[] = ['warn', 'Purge com a Global API Key', 'Funciona, mas essa chave dá acesso total à conta. Prefira um API Token só com "Zone > Cache Purge".'];
		}
	} else {
		$items[] = ['bad', 'Credenciais da Cloudflare não configuradas', 'Sem elas o cache não é limpo quando uma matéria é publicada ou corrigida.'];
	}

	if (!defined('CFCP_TAG_PREFIX') && cfcp_tag_prefix() === 'root') {
		$items[] = ['warn', 'Prefixo de tags padrão (root)', 'Se este site divide a zona da Cloudflare com outro, defina CFCP_TAG_PREFIX no wp-config.php para eles não limparem o cache um do outro.'];
	}

	if (!get_option('cfcp_cache_control_enabled', '1')) {
		$items[] = ['bad', 'Controle de cache desligado', 'O plugin não está enviando os cabeçalhos de cache.'];
	}

	if (file_exists(ABSPATH . 'robots.txt')) {
		$items[] = ['warn', 'Existe um robots.txt físico', 'O WordPress não gera o robots.txt, então a regra que bloqueia a busca para robôs não é aplicada. Adicione as linhas de Disallow nesse arquivo.'];
	}

	$celer = get_option('celersearch_settings');

	if (is_array($celer) && !empty($celer['enable_search'])) {
		$fallback = !isset($celer['fallback_to_native']) || $celer['fallback_to_native'];

		if ($fallback && cfcp_get('cfcp_search_native_policy') !== 'block') {
			$items[] = ['warn', 'CelerSearch com retorno para a busca nativa', 'Se o Meilisearch falhar, o WordPress passa a buscar direto no banco (LIKE), a consulta mais pesada possível. Em "Proteção da origem", a política "Bloquear busca nativa" evita isso.'];
		} else {
			$items[] = ['ok', 'Busca externa protegida contra retorno à busca nativa', ''];
		}
	}

	return $items;
}

/**
 * Testes com rede, só sob demanda. Resultado fica salvo para a página.
 */
function cfcp_diag_run()
{
	$result = ['time' => time(), 'edge' => [], 'api' => null];

	$url = home_url('/');

	for ($i = 1; $i <= 2; $i++) {
		$t0       = microtime(true);
		$response = wp_remote_get($url, [
			'timeout'     => 20,
			'redirection' => 3,
			'headers'     => ['User-Agent' => 'CFCP-Diagnostic/1.0', 'Accept-Encoding' => 'identity'],
		]);
		$ms = (int) round((microtime(true) - $t0) * 1000);

		if (is_wp_error($response)) {
			$result['edge'][] = ['error' => $response->get_error_message(), 'ms' => $ms];
			continue;
		}

		$h   = wp_remote_retrieve_headers($response);
		$get = function ($name) use ($h) {
			return isset($h[$name]) ? (is_array($h[$name]) ? implode(', ', $h[$name]) : (string) $h[$name]) : '';
		};

		$result['edge'][] = [
			'status'       => wp_remote_retrieve_response_code($response),
			'ms'           => $ms,
			'cf_cache'     => $get('cf-cache-status'),
			'age'          => $get('age'),
			'cache_control' => $get('cache-control'),
			'cdn_cache'    => $get('cdn-cache-control'),
			'cfcp'         => $get('x-cfcp-cache-rule'),
			'server'       => $get('server'),
			'cf_ray'       => $get('cf-ray'),
		];
	}

	if (defined('CFCP_ZONE') && function_exists('cfcp_get_purger')) {
		$result['api'] = cfcp_get_purger()->verify();
	}

	update_option('cfcp_diag_last', $result, false);

	return $result;
}

function cfcp_diag_handle_post()
{
	if (empty($_POST['cfcp_run_diag'])) {
		return;
	}

	if (!current_user_can('manage_options')) {
		return;
	}

	check_admin_referer('cfcp_diag');
	cfcp_diag_run();

	wp_safe_redirect(add_query_arg(['page' => 'cfcp-plugin-settings', 'tab' => 'diagnostics', 'ran' => 1], admin_url('admin.php')));
	exit;
}
add_action('admin_init', 'cfcp_diag_handle_post');
