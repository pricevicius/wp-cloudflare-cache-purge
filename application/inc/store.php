<?php

/**
 * Armazenamento atômico de contadores, travas e métricas, compartilhado
 * entre todos os processos do PHP-FPM. Escolhe sozinho o melhor disponível:
 *
 *  1. object-cache  cache de objetos externo (Redis/Memcached): o melhor,
 *                   vale para vários servidores.
 *  2. apcu          memória compartilhada do servidor.
 *  3. file          arquivos com flock na pasta temporária. Não precisa de
 *                   nada instalado e é atômico, mas vale só para um servidor.
 *
 * O Diagnóstico mostra qual está em uso.
 */
function cfcp_store_backend()
{
	static $backend = null;

	if ($backend !== null) {
		return $backend;
	}

	if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
		$backend = 'object-cache';
	} elseif (function_exists('apcu_add') && function_exists('apcu_enabled') && apcu_enabled()) {
		$backend = 'apcu';
	} else {
		$backend = 'file';
	}

	$backend = (string) apply_filters('cfcp_store_backend', $backend);

	return $backend;
}

function cfcp_store_key($key)
{
	return 'cfcp:' . cfcp_tag_prefix() . ':' . $key;
}

/**
 * Backend de arquivos: cada chave é um arquivo "expira|valor", lido e
 * gravado sempre sob flock exclusivo.
 */
function cfcp_file_open($key)
{
	static $dir = null;

	if ($dir === null) {
		$dir = rtrim(get_temp_dir(), '/\\') . '/cfcp-' . substr(md5(ABSPATH), 0, 8);

		if (!is_dir($dir)) {
			@mkdir($dir, 0700, true);
		}
	}

	$handle = @fopen($dir . '/' . md5($key), 'c+');

	if (!$handle) {
		return [null, null];
	}

	flock($handle, LOCK_EX);
	$raw   = stream_get_contents($handle);
	$parts = $raw !== '' && strpos($raw, '|') !== false ? explode('|', $raw, 2) : [0, 0];

	// Chave expirada vale como inexistente.
	if ((int) $parts[0] !== 0 && (int) $parts[0] < time()) {
		$parts = [0, 0];
	}

	// Estado: [expira em, valor]. Expira em 0 significa "chave não existe".
	return [$handle, [(int) $parts[0], (int) $parts[1]]];
}

function cfcp_file_close($handle, $expire = null, $value = null)
{
	if ($expire !== null) {
		ftruncate($handle, 0);
		rewind($handle);
		fwrite($handle, (int) $expire . '|' . (int) $value);
	}

	flock($handle, LOCK_UN);
	fclose($handle);
}

/**
 * Apaga de vez em quando arquivos abandonados (chaves de janelas antigas).
 */
function cfcp_file_gc()
{
	if (mt_rand(1, 300) !== 1) {
		return;
	}

	$dir = rtrim(get_temp_dir(), '/\\') . '/cfcp-' . substr(md5(ABSPATH), 0, 8);

	foreach ((array) glob($dir . '/*') as $file) {
		if (is_file($file) && filemtime($file) < time() - 10 * DAY_IN_SECONDS) {
			@unlink($file);
		}
	}
}

/**
 * Cria a chave só se ela ainda não existir (trava). true se conseguiu.
 */
function cfcp_store_add($key, $ttl)
{
	$k = cfcp_store_key($key);

	switch (cfcp_store_backend()) {
		case 'object-cache':
			return (bool) wp_cache_add($k, 1, 'cfcp', (int) $ttl);

		case 'apcu':
			return (bool) apcu_add($k, 1, (int) $ttl);
	}

	list($handle, $state) = cfcp_file_open($k);

	if (!$handle) {
		return true; // sem onde travar: não bloqueia ninguém por causa disso
	}

	if ($state[0] !== 0) {
		cfcp_file_close($handle);
		return false;
	}

	cfcp_file_close($handle, time() + (int) $ttl, 1);
	cfcp_file_gc();

	return true;
}

/**
 * Soma $by ao contador e devolve o valor novo. O TTL vale da criação.
 */
function cfcp_store_incr($key, $ttl, $by = 1)
{
	$k = cfcp_store_key($key);

	switch (cfcp_store_backend()) {
		case 'object-cache':
			if (wp_cache_add($k, $by, 'cfcp', (int) $ttl)) {
				return (int) $by;
			}

			$value = wp_cache_incr($k, $by, 'cfcp');

			return $value === false ? (int) $by : (int) $value;

		case 'apcu':
			if (apcu_add($k, $by, (int) $ttl)) {
				return (int) $by;
			}

			$value = apcu_inc($k, $by);

			return $value === false ? (int) $by : (int) $value;
	}

	list($handle, $state) = cfcp_file_open($k);

	if (!$handle) {
		return 0;
	}

	if ($state[0] === 0) {
		$expire = time() + (int) $ttl;
		$value  = (int) $by;
		cfcp_file_gc();
	} else {
		$expire = $state[0];
		$value  = $state[1] + (int) $by;
	}

	cfcp_file_close($handle, $expire, $value);

	return $value;
}

function cfcp_store_get($key)
{
	$k = cfcp_store_key($key);

	switch (cfcp_store_backend()) {
		case 'object-cache':
			$value = wp_cache_get($k, 'cfcp');

			return $value === false ? 0 : (int) $value;

		case 'apcu':
			$value = apcu_fetch($k);

			return $value === false ? 0 : (int) $value;
	}

	list($handle, $state) = cfcp_file_open($k);

	if (!$handle) {
		return 0;
	}

	cfcp_file_close($handle);

	return $state[0] === 0 ? 0 : $state[1];
}

function cfcp_store_delete($key)
{
	$k = cfcp_store_key($key);

	switch (cfcp_store_backend()) {
		case 'object-cache':
			wp_cache_delete($k, 'cfcp');
			return;

		case 'apcu':
			apcu_delete($k);
			return;
	}

	list($handle, $state) = cfcp_file_open($k);

	if ($handle) {
		ftruncate($handle, 0);
		cfcp_file_close($handle);
	}
}

/**
 * Métricas: um contador por nome e por dia, guardado por 9 dias.
 */
function cfcp_metric($name, $by = 1)
{
	cfcp_store_incr('m:' . $name . ':' . wp_date('Ymd'), 9 * DAY_IN_SECONDS, (int) $by);
}

function cfcp_metric_sum($name, $days = 1)
{
	$total = 0;

	for ($i = 0; $i < $days; $i++) {
		$total += cfcp_store_get('m:' . $name . ':' . wp_date('Ymd', time() - $i * DAY_IN_SECONDS));
	}

	return $total;
}
