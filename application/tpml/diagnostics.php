<?php
$checks = cfcp_diag_checklist();
$search = cfcp_diag_search_stats();
$purge  = cfcp_diag_purge_stats();
$ip     = cfcp_diag_ip();
$opc    = cfcp_diag_opcache();
$last   = get_option('cfcp_diag_last', null);
$labels = ['ok' => 'OK', 'warn' => 'Atenção', 'bad' => 'Problema'];
$backend_names = ['object-cache' => 'Cache de objetos externo (Redis/Memcached)', 'apcu' => 'APCu (memória compartilhada)', 'file' => 'Arquivos temporários'];
?>
<div class="cfcp-section">

	<h3>Resumo</h3>
	<table class="cfcp-table">
		<?php foreach ($checks as $check) : ?>
			<tr>
				<td style="width:90px"><span class="cfcp-badge <?php echo esc_attr($check[0]); ?>"><?php echo esc_html($labels[$check[0]]); ?></span></td>
				<td><strong><?php echo esc_html($check[1]); ?></strong><?php echo $check[2] !== '' ? '<br>' . esc_html($check[2]) : ''; ?></td>
			</tr>
		<?php endforeach; ?>
	</table>

	<h3>Testar a Cloudflare agora</h3>
	<p class="description" style="max-width:900px">
		O plugin busca a home deste site duas vezes, pela URL pública, e mostra o que a Cloudflare respondeu.
		Se o cache estiver funcionando, a primeira resposta costuma ser <code>MISS</code> e a segunda <code>HIT</code>.
		<code>DYNAMIC</code> significa que a Cloudflare não considera a página elegível para cache: falta uma Cache Rule na zona.
		O teste também confere as credenciais da API, sem alterar nada na Cloudflare.
	</p>
	<form method="post" action="<?php echo esc_url(add_query_arg(['page' => 'cfcp-plugin-settings', 'tab' => 'diagnostics'], admin_url('admin.php'))); ?>">
		<?php wp_nonce_field('cfcp_diag'); ?>
		<input type="hidden" name="cfcp_run_diag" value="1">
		<?php submit_button('Executar teste', 'secondary', 'submit', false); ?>
	</form>

	<?php if (is_array($last)) : ?>
		<p><em>Último teste: <?php echo esc_html(wp_date('d/m/Y H:i:s', $last['time'])); ?></em></p>
		<table class="cfcp-table">
			<tr><th>#</th><th>Status</th><th>Tempo</th><th>cf-cache-status</th><th>age</th><th>Cache-Control</th><th>CDN-Cache-Control</th><th>X-CFCP</th></tr>
			<?php foreach ((array) $last['edge'] as $i => $row) : ?>
				<tr>
					<td><?php echo (int) $i + 1; ?></td>
					<?php if (isset($row['error'])) : ?>
						<td colspan="7"><?php echo esc_html($row['error']); ?></td>
					<?php else : ?>
						<td><?php echo (int) $row['status']; ?></td>
						<td class="n"><?php echo (int) $row['ms']; ?> ms</td>
						<td><strong><?php echo esc_html($row['cf_cache'] !== '' ? $row['cf_cache'] : 'sem Cloudflare'); ?></strong></td>
						<td><?php echo esc_html($row['age']); ?></td>
						<td><code><?php echo esc_html($row['cache_control']); ?></code></td>
						<td><code><?php echo esc_html($row['cdn_cache']); ?></code></td>
						<td><code><?php echo esc_html($row['cfcp']); ?></code></td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php if (is_array($last['api'])) : ?>
			<div class="cfcp-box <?php echo $last['api']['ok'] ? 'ok' : 'bad'; ?>">
				<strong>Credenciais da API:</strong> <?php echo esc_html($last['api']['message']); ?>
				<?php echo !empty($last['api']['plan']) ? ' Plano da zona: <strong>' . esc_html($last['api']['plan']) . '</strong>.' : ''; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<h3>Proteção da busca e das páginas de arquivo</h3>
	<p class="description" style="max-width:900px">
		Hoje e nos últimos 7 dias. "Teria bloqueado" é o que aconteceria no modo <strong>Bloquear</strong>;
		"Bloqueadas" é o que já foi bloqueado. Números altos em "teria bloqueado" vindos de leitores reais indicam limite apertado demais.
	</p>
	<table class="cfcp-table">
		<tr><th>Regra</th><th>Teria bloqueado (hoje)</th><th>Teria bloqueado (7 dias)</th><th>Bloqueadas (hoje)</th><th>Bloqueadas (7 dias)</th></tr>
		<?php foreach ($search['rows'] as $row) : ?>
			<tr>
				<td><?php echo esc_html($row['label']); ?></td>
				<td class="n"><?php echo (int) $row['would_1']; ?></td>
				<td class="n"><?php echo (int) $row['would_7']; ?></td>
				<td class="n"><?php echo (int) $row['blocked_1']; ?></td>
				<td class="n"><?php echo (int) $row['blocked_7']; ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
	<table class="cfcp-table" style="margin-top:10px">
		<tr><th></th><th>Hoje</th><th>7 dias</th></tr>
		<tr><td>Buscas recebidas</td><td class="n"><?php echo (int) $search['requests_1']; ?></td><td class="n"><?php echo (int) $search['requests_7']; ?></td></tr>
		<tr><td>Tempo médio de uma busca</td><td class="n"><?php echo $search['avg_ms_1'] === null ? '-' : (int) $search['avg_ms_1'] . ' ms'; ?></td><td class="n"><?php echo $search['avg_ms_7'] === null ? '-' : (int) $search['avg_ms_7'] . ' ms'; ?></td></tr>
		<tr><td>Buscas lentas (3 s ou mais)</td><td class="n"><?php echo (int) $search['slow_1']; ?></td><td class="n"><?php echo (int) $search['slow_7']; ?></td></tr>
	</table>

	<h3>Limpeza de cache (purge)</h3>
	<table class="cfcp-table">
		<tr><th></th><th>Hoje</th><th>7 dias</th></tr>
		<tr><td>Chamadas à API da Cloudflare</td><td class="n"><?php echo (int) $purge['calls_1']; ?></td><td class="n"><?php echo (int) $purge['calls_7']; ?></td></tr>
		<tr><td>Chamadas com erro</td><td class="n"><?php echo (int) $purge['errors_1']; ?></td><td class="n"><?php echo (int) $purge['errors_7']; ?></td></tr>
		<tr><td>Limite da Cloudflare atingido (429)</td><td class="n">-</td><td class="n"><?php echo (int) $purge['r429_7']; ?></td></tr>
	</table>
	<?php if (is_array($purge['last'])) : ?>
		<p><strong>Último erro de purge</strong> (<?php echo esc_html(wp_date('d/m/Y H:i:s', $purge['last']['time'])); ?>): <?php echo esc_html($purge['last']['message']); ?></p>
	<?php endif; ?>

	<h3>IP do visitante nesta requisição</h3>
	<table class="cfcp-table">
		<tr><td style="width:280px">IP da conexão (proxy)</td><td><code><?php echo esc_html($ip['remote']); ?></code> <?php echo $ip['remote_is_trusted'] ? '(proxy confiável)' : ($ip['remote_private'] ? '(IP interno, provavelmente um proxy: preencha "Proxies confiáveis")' : ''); ?></td></tr>
		<tr><td>Cabeçalho <?php echo esc_html($ip['header_name']); ?></td><td><code><?php echo esc_html($ip['header_value'] !== '' ? $ip['header_value'] : 'não recebido'); ?></code></td></tr>
		<tr><td>X-Forwarded-For</td><td><code><?php echo esc_html($ip['xff'] !== '' ? $ip['xff'] : 'não recebido'); ?></code></td></tr>
		<tr><td><strong>IP que os limites usam</strong></td><td><strong><code><?php echo esc_html($ip['resolved']); ?></code></strong></td></tr>
	</table>

	<h3>Servidor</h3>
	<table class="cfcp-table">
		<tr><td style="width:280px">Onde ficam contadores e travas</td><td><?php echo esc_html($backend_names[cfcp_store_backend()]); ?></td></tr>
		<tr><td>Cache de objetos externo</td><td><?php echo wp_using_ext_object_cache() ? 'Ativo' : 'Não ativo'; ?></td></tr>
		<?php if ($opc === null || empty($opc['enabled'])) : ?>
			<tr><td>OPcache</td><td><?php echo $opc === null ? 'Extensão não disponível' : 'Desligado'; ?></td></tr>
		<?php else : ?>
			<tr><td>OPcache: memória</td><td><?php echo esc_html($opc['used_mb']); ?> MB usados, <?php echo esc_html($opc['free_mb']); ?> MB livres (<?php echo esc_html($opc['used_pct']); ?>% cheio). <?php echo $opc['used_pct'] > 90 ? '<strong>Quase cheio: aumente opcache.memory_consumption.</strong>' : ''; ?></td></tr>
			<tr><td>OPcache: acertos</td><td><?php echo esc_html($opc['hit_rate']); ?>%. Reinícios por falta de memória: <?php echo (int) $opc['oom_restarts']; ?>. <?php echo $opc['oom_restarts'] > 0 ? '<strong>Houve falta de memória.</strong>' : ''; ?></td></tr>
			<tr><td>OPcache: scripts</td><td><?php echo (int) $opc['cached_scripts']; ?> de até <?php echo (int) $opc['max_scripts']; ?></td></tr>
			<tr><td>OPcache: atualização de arquivos</td><td><?php echo $opc['validate_timestamps'] ? 'Confere a cada ' . (int) $opc['revalidate_freq'] . ' s se algum arquivo mudou (deploys valem sozinhos)' : 'Não confere (depois de um deploy é preciso recarregar o PHP-FPM)'; ?></td></tr>
		<?php endif; ?>
	</table>
</div>
