<?php
class CFCP_Purger
{
	/**
	 * Máximo de itens (URLs ou tags) por chamada de purge. 30 é o menor
	 * limite entre os planos da Cloudflare, então funciona em qualquer um
	 * sem o plugin precisar saber qual é o plano.
	 */
	const CHUNK_SIZE = 30;

	private $cfcp_email;
	private $cfcp_api;
	private $cfcp_zone;
	private $cfcp_token;

	public function __construct($cfcp_email, $cfcp_api, $cfcp_zone, $cfcp_token = '')
	{
		$this->cfcp_email = $cfcp_email;
		$this->cfcp_api   = $cfcp_api;
		$this->cfcp_zone  = $cfcp_zone;
		$this->cfcp_token = $cfcp_token;
	}

	/**
	 * API Token (só permissão de purge) tem preferência sobre a Global API Key.
	 */
	private function auth_headers()
	{
		$headers = ['Content-Type' => 'application/json'];

		if (!empty($this->cfcp_token)) {
			$headers['Authorization'] = 'Bearer ' . $this->cfcp_token;
		} else {
			$headers['X-Auth-Email'] = $this->cfcp_email;
			$headers['X-Auth-Key']   = $this->cfcp_api;
		}

		return $headers;
	}

	/**
	 * Método genérico para purge na Cloudflare.
	 * Retorna true (ok), false (erro) ou 429 quando a Cloudflare limitou
	 * a taxa, para quem chama parar de insistir.
	 */
	private function request($payload = [])
	{
		if (empty($payload) || !is_array($payload)) {
			return false;
		}

		cfcp_metric('purge_calls');

		$response = wp_remote_post(
			"https://api.cloudflare.com/client/v4/zones/{$this->cfcp_zone}/purge_cache",
			[
				'method'  => 'POST',
				'timeout' => 20,
				'headers' => $this->auth_headers(),
				'body'    => wp_json_encode($payload),
			]
		);

		if (is_wp_error($response)) {
			error_log('[CLOUDFLARE] Erro ao limpar cache Cloudflare: ' . $response->get_error_message());
			$this->remember_error($response->get_error_message());
			return false;
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		if ($code === 429) {
			error_log('[CLOUDFLARE] Limite de purge atingido (HTTP 429). Body: ' . $body);
			cfcp_metric('purge_429');
			$this->remember_error('HTTP 429: limite de purge da Cloudflare atingido');
			return 429;
		}

		if ($code < 200 || $code >= 300) {
			error_log('[CLOUDFLARE] Falha ao limpar cache Cloudflare. HTTP ' . $code . ' | Body: ' . $body);
			$this->remember_error('HTTP ' . $code . ' ' . substr(wp_strip_all_tags($body), 0, 200));
			return false;
		}

		error_log('[CLOUDFLARE] Purge executado com sucesso. Payload: ' . wp_json_encode($payload));
		return true;
	}

	/**
	 * Guarda o último erro para o Diagnóstico mostrar (só em falha, então
	 * o custo de gravar no banco não pesa).
	 */
	private function remember_error($message)
	{
		cfcp_metric('purge_errors');
		update_option('cfcp_last_purge_error', ['time' => time(), 'message' => (string) $message], false);
	}

	/**
	 * Confere as credenciais sem alterar nada na Cloudflare (só leitura).
	 * Devolve ['ok' => bool, 'message' => string, 'plan' => string|null].
	 */
	public function verify()
	{
		$args = ['timeout' => 15, 'headers' => $this->auth_headers()];

		if (!empty($this->cfcp_token)) {
			$response = wp_remote_get('https://api.cloudflare.com/client/v4/user/tokens/verify', $args);

			if (is_wp_error($response)) {
				return ['ok' => false, 'message' => $response->get_error_message(), 'plan' => null];
			}

			$data = json_decode(wp_remote_retrieve_body($response), true);

			if (empty($data['success']) || ($data['result']['status'] ?? '') !== 'active') {
				return ['ok' => false, 'message' => 'Token recusado pela Cloudflare (HTTP ' . wp_remote_retrieve_response_code($response) . ').', 'plan' => null];
			}
		}

		$response = wp_remote_get("https://api.cloudflare.com/client/v4/zones/{$this->cfcp_zone}", $args);

		if (is_wp_error($response)) {
			return ['ok' => false, 'message' => $response->get_error_message(), 'plan' => null];
		}

		$code = wp_remote_retrieve_response_code($response);
		$data = json_decode(wp_remote_retrieve_body($response), true);

		if ($code === 200 && !empty($data['success'])) {
			return [
				'ok'      => true,
				'message' => 'Credenciais válidas. Zona: ' . ($data['result']['name'] ?? '?'),
				'plan'    => isset($data['result']['plan']['name']) ? $data['result']['plan']['name'] : null,
			];
		}

		if (!empty($this->cfcp_token) && $code === 403) {
			return [
				'ok'      => true,
				'message' => 'Token válido. Ele não tem permissão para ler a zona, o que é esperado quando o token é só de Cache Purge.',
				'plan'    => null,
			];
		}

		return ['ok' => false, 'message' => 'A Cloudflare respondeu HTTP ' . $code . '. Confira a zona e as credenciais.', 'plan' => null];
	}

	/**
	 * Envia $items em lotes de CHUNK_SIZE sob a chave $key ('files' ou 'tags').
	 * Retorna true só se todos os lotes passaram.
	 */
	private function request_chunked($key, $items)
	{
		$all_ok = true;

		foreach (array_chunk($items, self::CHUNK_SIZE) as $chunk) {
			$result = $this->request([$key => $chunk]);

			if ($result === 429) {
				return false;
			}

			if ($result !== true) {
				$all_ok = false;
			}
		}

		return $all_ok;
	}

	/**
	 * Purge por URLs
	 */
	public function cacheConnection($urls = [])
	{
		$urls = array_values(array_unique(array_filter((array) $urls)));

		if (empty($urls)) {
			return false;
		}

		return $this->request_chunked('files', $urls);
	}

	/**
	 * Purge por Cache-Tags
	 */
	public function cacheConnectionTags($tags = [])
	{
		$tags = array_values(array_unique(array_filter((array) $tags)));

		if (empty($tags)) {
			return false;
		}

		return $this->request_chunked('tags', $tags);
	}
}
