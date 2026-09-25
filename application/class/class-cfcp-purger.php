<?php
class CFCP_Purger
{
	private $cfcp_email;
	private $cfcp_api;
	private $cfcp_zone;

	public function __construct($cfcp_email, $cfcp_api, $cfcp_zone)
	{
		$this->cfcp_email = $cfcp_email;
		$this->cfcp_api   = $cfcp_api;
		$this->cfcp_zone  = $cfcp_zone;
	}

	/**
	 * Método genérico para purge na Cloudflare
	 */
	private function request($payload = [])
	{
		if (empty($payload) || !is_array($payload)) {
			return false;
		}

		$response = wp_remote_post(
			"https://api.cloudflare.com/client/v4/zones/{$this->cfcp_zone}/purge_cache",
			[
				'method'  => 'POST',
				'timeout' => 20,
				'headers' => [
					'X-Auth-Email' => $this->cfcp_email,
					'X-Auth-Key'   => $this->cfcp_api,
					'Content-Type' => 'application/json',
				],
				'body' => wp_json_encode($payload),
			]
		);

		if (is_wp_error($response)) {
			error_log('[CLOUDFLARE] Erro ao limpar cache Cloudflare: ' . $response->get_error_message());
			return false;
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		if ($code < 200 || $code >= 300) {
			error_log('[CLOUDFLARE] Falha ao limpar cache Cloudflare. HTTP ' . $code . ' | Body: ' . $body);
			return false;
		}

		error_log('[CLOUDFLARE] Purge executado com sucesso. Payload: ' . wp_json_encode($payload));
		return true;
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

		return $this->request([
			'files' => $urls,
		]);
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

		return $this->request([
			'tags' => $tags,
		]);
	}
}