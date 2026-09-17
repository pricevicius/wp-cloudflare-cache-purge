<div class="firstconfig" id="firstconfig" <?php echo $active_tab === 'general' ? '' : 'style="display:none"'; ?>>
	<table class="form-table">
		<tr valign="top">
			<th scope="row">E-mail de conexao:</th>
			<td>
				<input type="text" name="cfcp_email" id="cfcp_email"
					value="<?php echo esc_attr(get_option('cfcp_email')); ?>" class="regular-text">
				<p class="description">
					Insira o e-mail de conexão com a API do Cloudflare
				</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">ZONA:</th>
			<td>
				<input type="text" name="cfcp_zone" id="cfcp_zone"
					value="<?php echo esc_attr(get_option('cfcp_zone')); ?>" class="regular-text">
				<p class="description">
					Insira a zona (zone ID) do Cloudflare
				</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">API:</th>
			<td>
				<input type="password" name="cfcp_api" id="cfcp_api"
					value="<?php echo esc_attr(get_option('cfcp_api')); ?>" class="regular-text">

				<p class="description">
					Insira a chave da API do Cloudflare
				</p>
			</td>
		</tr>

	</table>
</div>

<div class="cachecontrol" id="cachecontrol" <?php echo $active_tab === 'cache' ? '' : 'style="display:none"'; ?>>
	<h3>Controle de Cache (Cache-Control / CDN-Cache-Control)</h3>
	<p class="description">
		Define os headers de cache enviados ao navegador e à Cloudflare para visitantes não logados.
		Usuários logados, prévias e feeds continuam sempre sem cache ou com TTL curto, independente das opções abaixo.
	</p>
	<p class="description">
		<strong>Double-check:</strong> toda resposta do site inclui o header <code>X-CFCP-Cache-Rule</code>,
		indicando se o controle está ativo, qual regra foi aplicada (<code>scope</code>) e o TTL usado.
		Confira com <code>curl -I sua-url</code> ou na aba Network do navegador.
	</p>
	<table class="form-table">
		<tr valign="top">
			<th scope="row">Ativar controle de cache:</th>
			<td>
				<input type="hidden" name="cfcp_cache_control_enabled" value="0">
				<label>
					<input type="checkbox" name="cfcp_cache_control_enabled" id="cfcp_cache_control_enabled"
						value="1" <?php checked(get_option('cfcp_cache_control_enabled', '1'), '1'); ?>>
					Habilitar o envio dos headers de cache pelo plugin
				</label>
				<p class="description">
					Desative caso já tenha uma regra de cache configurada em outro lugar (ex: tema, servidor, page rule na Cloudflare).
				</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">TTL padrão (posts e páginas):</th>
			<td>
				<input type="number" min="0" step="1" name="cfcp_cache_ttl_default" id="cfcp_cache_ttl_default"
					value="<?php echo esc_attr(get_option('cfcp_cache_ttl_default', 2592000)); ?>" class="regular-text">
				<p class="description">
					Tempo de cache em segundos para posts e páginas (padrão: 2592000 = 30 dias).
				</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">TTL da home:</th>
			<td>
				<input type="number" min="0" step="1" name="cfcp_cache_ttl_home" id="cfcp_cache_ttl_home"
					value="<?php echo esc_attr(get_option('cfcp_cache_ttl_home', 900)); ?>" class="regular-text">
				<p class="description">
					Tempo de cache em segundos para a página inicial / blog (padrão: 900 = 15 minutos).
				</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">TTL de páginas 404:</th>
			<td>
				<input type="number" min="0" step="1" name="cfcp_cache_ttl_404" id="cfcp_cache_ttl_404"
					value="<?php echo esc_attr(get_option('cfcp_cache_ttl_404', 2592000)); ?>" class="regular-text">
				<p class="description">
					Tempo de cache em segundos para páginas não encontradas (padrão: 2592000 = 30 dias).
				</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">TTL de feeds:</th>
			<td>
				<input type="number" min="0" step="1" name="cfcp_cache_ttl_feed" id="cfcp_cache_ttl_feed"
					value="<?php echo esc_attr(get_option('cfcp_cache_ttl_feed', 300)); ?>" class="regular-text">
				<p class="description">
					Tempo de cache em segundos para feeds RSS/Atom (padrão: 300 = 5 minutos).
				</p>
			</td>
		</tr>
	</table>
</div>