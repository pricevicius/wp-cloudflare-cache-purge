<div class="firstconfig" id="firstconfig" <?php echo $active_tab === 'general' ? '' : 'style="display:none"'; ?>>
	<table class="form-table">
		<tr valign="top">
			<th scope="row">API Token (recomendado):</th>
			<td>
				<input type="password" name="cfcp_api_token" id="cfcp_api_token"
					value="<?php echo esc_attr(get_option('cfcp_api_token')); ?>" class="regular-text">
				<p class="description">
					Token da Cloudflare com permissão apenas de <strong>Zone &rsaquo; Cache Purge</strong> nesta zona.
					Se preenchido, tem preferência sobre o e-mail e a chave global abaixo.
					Também pode ser definido no wp-config.php com <code>CFCP_CF_TOKEN</code> e <code>CFCP_CF_ZONE_ID</code>.
				</p>
			</td>
		</tr>
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
					Insira a chave da API do Cloudflare (Global API Key). Só é usada se não houver API Token.
				</p>
			</td>
		</tr>

	</table>
</div>

<div class="cachecontrol" id="cachecontrol" <?php echo $active_tab === 'cache' ? '' : 'style="display:none"'; ?>>
	<h3>Controle de Cache (Cache-Control / CDN-Cache-Control)</h3>
	<p class="description">
		Define os headers de cache enviados ao navegador e à Cloudflare para visitantes não logados.
		Usuários logados, prévias e buscas continuam sempre sem cache, independente das opções abaixo.
	</p>
	<p class="description">
		Os TTLs abaixo valem para a <strong>Cloudflare</strong> (<code>CDN-Cache-Control</code>). O navegador do leitor
		recebe o <strong>TTL do navegador</strong>, que é curto de propósito: a Cloudflare pode ser limpa quando uma matéria
		é publicada ou corrigida, o navegador não.
	</p>
	<p class="description">
		<strong>Como confirmar que está a funcionar:</strong> abra o site numa nova aba, prima <code>F12</code>
		para abrir as ferramentas de programador, vá ao separador <strong>Network</strong>, recarregue a página
		e clique no primeiro pedido da lista. Nos headers de resposta deve aparecer <code>X-CFCP-Cache-Rule</code>,
		que indica se o controlo está ativo e qual o TTL aplicado.
	</p>
	<p class="description">
		<em>Nota para utilizadores mais técnicos:</em> o mesmo resultado pode ser confirmado com
		<code>curl -I sua-url</code> na linha de comandos.
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
					value="<?php echo esc_attr(get_option('cfcp_cache_ttl_default', 3600)); ?>" class="regular-text">
				<p class="description">
					Tempo de cache na Cloudflare, em segundos, para posts, categorias e páginas (padrão: 3600 = 1 hora).
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
					value="<?php echo esc_attr(get_option('cfcp_cache_ttl_404', 300)); ?>" class="regular-text">
				<p class="description">
					Tempo de cache em segundos para páginas não encontradas (padrão: 300 = 5 minutos).
				</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">TTL de redirecionamentos:</th>
			<td>
				<input type="number" min="0" step="1" name="cfcp_cache_ttl_redirect" id="cfcp_cache_ttl_redirect"
					value="<?php echo esc_attr(get_option('cfcp_cache_ttl_redirect', 300)); ?>" class="regular-text">
				<p class="description">
					Tempo de cache na Cloudflare, em segundos, para redirecionamentos permanentes (301) de leitores.
					No navegador nunca são guardados (padrão: 300 = 5 minutos).
				</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">TTL do navegador:</th>
			<td>
				<input type="number" min="0" step="1" name="cfcp_cache_ttl_browser" id="cfcp_cache_ttl_browser"
					value="<?php echo esc_attr(get_option('cfcp_cache_ttl_browser', 120)); ?>" class="regular-text">
				<p class="description">
					Por quanto tempo o navegador do leitor guarda a página antes de perguntar de novo à Cloudflare.
					Nunca passa do TTL da Cloudflare da mesma página (padrão: 120 = 2 minutos).
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
		<?php
		cfcp_row_number(
			'cfcp_swr',
			'Servir cópia antiga enquanto atualiza',
			'Quando o cache de uma página expira, <strong>uma</strong> requisição vai à origem buscar a versão nova e todas as outras continuam recebendo a cópia antiga por até este tempo (<code>stale-while-revalidate</code>). Sem isso, na expiração de uma página muito acessada todos os leitores vão à origem ao mesmo tempo. 0 desliga.',
			'60 segundos.',
			'segundos'
		);

		cfcp_row_number(
			'cfcp_sie',
			'Servir cópia antiga se a origem falhar',
			'Se a origem estiver fora do ar ou devolvendo erro, a Cloudflare continua servindo a última cópia guardada por até este tempo (<code>stale-if-error</code>). O leitor vê a página, e não um erro. 0 desliga.',
			'86400 (1 dia).',
			'segundos'
		);

		cfcp_row_number(
			'cfcp_cache_ttl_search',
			'TTL da busca (micro-cache)',
			'Por padrão a busca <strong>não</strong> é guardada em cache. Ligando um valor curto aqui, buscas repetidas do mesmo termo (uma notícia em alta, por exemplo) são atendidas pela Cloudflare por esse tempo. Isso <strong>não</strong> protege contra robôs que variam o termo; para isso servem os limites da aba Proteção da origem. Se ligar, remova a busca (<code>s=</code>) da regra de Bypass na Cloudflare. 0 desliga.',
			'0 (sem cache), ou 60 se quiser o micro-cache.',
			'segundos'
		);
		?>
	</table>
</div>

<?php include __DIR__ . '/protection.php'; ?>
