<?php $mode = cfcp_guard_mode(); ?>
<div class="cfcp-protection cfcp-section" id="cfcp-protection" <?php echo $active_tab === 'protection' ? '' : 'style="display:none"'; ?>>

	<div class="cfcp-box info">
		<strong>Para que serve esta aba.</strong>
		O cache da Cloudflare segura as páginas que se repetem. Ele <em>não</em> segura a busca, porque cada termo
		é uma URL diferente: um robô que varia o termo passa por cima do cache e cada pedido ocupa um processo do
		servidor. Se a busca depende de um serviço externo (ex: Meilisearch) e ele fica lento, os processos ficam
		presos esperando e o <strong>site inteiro</strong> para, inclusive o painel da redação.
		Os controles abaixo impedem isso. Funcionam com a busca nativa do WordPress e com motores externos.
	</div>

	<div class="cfcp-box <?php echo $mode === 'enforce' ? 'ok' : ($mode === 'observe' ? 'warn' : 'bad'); ?>">
		<strong>Modo atual:</strong>
		<?php echo esc_html(['off' => 'Desligado', 'observe' => 'Observação (só conta, não bloqueia)', 'enforce' => 'Bloqueando'][$mode]); ?>.
		Quem pode editar posts (a redação) nunca é limitado por nenhum controle desta aba.
	</div>

	<table class="form-table">
		<?php
		cfcp_row_select(
			'cfcp_guard_mode',
			'Modo da proteção',
			[
				'off'     => 'Desligado: não faz nada',
				'observe' => 'Observação: só conta o que teria bloqueado',
				'enforce' => 'Bloquear: aplica os limites de verdade',
			],
			'Comece em <strong>Observação</strong>: nada muda para o leitor e a aba <strong>Diagnóstico</strong> mostra quantas requisições teriam sido bloqueadas por cada regra. Se os números fizerem sentido (robôs e picos, não leitores de verdade), mude para <strong>Bloquear</strong>.',
			'Observação por alguns dias, depois Bloquear.'
		);

		cfcp_row_textarea(
			'cfcp_guard_allow_ips',
			'IPs sempre liberados',
			'Um IP ou bloco (CIDR) por linha. Esses endereços nunca são limitados. Útil para a rede da empresa, monitoramento e ferramentas de teste.',
			'',
			3
		);
		?>
	</table>

	<h3>IP real do visitante</h3>
	<div class="cfcp-box info">
		Atrás de um proxy (Cloudflare, nginx proxy manager), o WordPress só enxerga o IP do proxy. Sem corrigir isso,
		o limite por IP trataria <strong>todos os leitores como uma pessoa só</strong>. Informe abaixo o IP dos proxies
		que ficam entre a Cloudflare e o WordPress; só a partir deles o cabeçalho com o IP real é aceito (de qualquer outra
		origem ele poderia ser forjado).
	</div>
	<table class="form-table">
		<?php
		cfcp_row_textarea(
			'cfcp_trusted_proxies',
			'Proxies confiáveis',
			'Um IP ou bloco (CIDR) por linha: o IP que aparece como origem da conexão no servidor, ou seja, o do proxy imediatamente à frente do WordPress. Veja em <strong>Diagnóstico</strong> qual IP chega hoje.',
			'O IP interno do proxy reverso (ex: 192.168.100.200).',
			3
		);

		cfcp_row_text(
			'cfcp_real_ip_header',
			'Cabeçalho com o IP do visitante',
			'Nome do cabeçalho HTTP em que o proxy informa o IP real. Com a Cloudflare, é <code>CF-Connecting-IP</code>. Se houver vários IPs na cadeia (como em <code>X-Forwarded-For</code>), vale o primeiro da direita que não seja um proxy confiável.',
			'CF-Connecting-IP'
		);

		cfcp_row_checkbox(
			'cfcp_override_remote_addr',
			'Aplicar no WordPress inteiro',
			'Substituir o IP do proxy pelo IP do visitante em todo o WordPress',
			'Além dos limites deste plugin, comentários, formulários e outros plugins também passam a ver o IP real. Só marque depois de conferir no Diagnóstico que o IP resolvido está correto.',
			'Marcado, depois de validar.'
		);
		?>
	</table>

	<h3>Busca</h3>
	<div class="cfcp-box info">
		Regras aplicadas à busca do site (<code>?s=</code>) e às rotas REST de busca e autocompletar.
		Em <strong>Observação</strong> só contam; em <strong>Bloquear</strong> respondem na hora, sem ocupar o servidor.
	</div>
	<table class="form-table">
		<?php
		cfcp_row_number(
			'cfcp_search_max_concurrent',
			'Buscas simultâneas (máximo)',
			'Quantas buscas podem estar em andamento ao mesmo tempo neste servidor. A busca seguinte recebe uma página de "muitos acessos" (503) <strong>imediatamente</strong>, sem prender um processo do servidor. É o controle que isola o site quando o motor de busca fica lento. 0 desliga.',
			'Bem abaixo do total de processos do PHP-FPM (por exemplo, 4 de 20).',
			'buscas'
		);

		cfcp_row_number(
			'cfcp_search_rate_limit',
			'Buscas por IP',
			'Quantas buscas um mesmo IP pode fazer dentro da janela abaixo. Passou disso, recebe "muitas buscas" (429). Impede que um único robô ou pessoa sature a busca. 0 desliga.',
			'10 por janela de 60 segundos.',
			'buscas'
		);

		cfcp_row_number(
			'cfcp_search_rate_window',
			'Janela do limite por IP',
			'Duração da janela usada no limite acima.',
			'60',
			'segundos'
		);

		cfcp_row_number(
			'cfcp_search_http_timeout',
			'Prazo máximo do motor de busca',
			'Durante uma busca, chamadas a serviços externos (como o Meilisearch) esperam no máximo isto. Sem este limite, o cliente do Meilisearch espera até 30 segundos por resposta, e cada busca lenta prende um processo do servidor por esse tempo todo. Só vale no modo <strong>Bloquear</strong>. 0 desliga.',
			'3 segundos.',
			'segundos'
		);

		cfcp_row_select(
			'cfcp_search_native_policy',
			'Se o motor de busca falhar',
			[
				'allow' => 'Permitir a busca nativa do WordPress',
				'block' => 'Bloquear a busca nativa e mostrar "busca indisponível"',
			],
			'Alguns plugins de busca, quando o motor externo falha, voltam para a busca nativa do WordPress, que procura direto no banco (<code>LIKE</code>). Com muitos posts essa é a consulta mais pesada possível, e ela roda justamente quando o sistema já está sob pressão. <strong>Bloquear</strong> poupa o banco: o leitor vê "busca temporariamente indisponível" (503). <strong>Só use Bloquear se a busca do site depende de um motor externo</strong>; num site que usa a busca nativa como busca normal, deixe em Permitir.',
			'Bloquear, se a busca usa Meilisearch ou similar. Permitir, se usa a busca nativa.'
		);

		cfcp_row_number(
			'cfcp_search_min_len',
			'Tamanho mínimo do termo',
			'Buscas com menos caracteres que isto (inclusive buscas vazias) são recusadas (400). 0 desliga.',
			'2',
			'caracteres'
		);

		cfcp_row_number(
			'cfcp_search_max_len',
			'Tamanho máximo do termo',
			'Termos maiores que isto são recusados (400). Textos enormes na busca costumam ser tentativa de abuso. 0 desliga.',
			'100',
			'caracteres'
		);

		cfcp_row_number(
			'cfcp_search_max_pages',
			'Páginas de resultados (máximo)',
			'Ninguém lê a página 50 dos resultados de uma busca, mas robôs pedem. Acima disto a resposta é 404. 0 desliga.',
			'10',
			'páginas'
		);

		cfcp_row_textarea(
			'cfcp_search_rest_routes',
			'Rotas REST de busca',
			'Rotas da API (uma por linha, por prefixo) que também recebem os limites de busca. O autocompletar é o mais importante: ele faz uma consulta a cada tecla digitada.',
			'/celersearch/v1/search, /celersearch/v1/autocomplete, /celersearch/v1/chat (com o CelerSearch). Esvazie se não usa.',
			4
		);

		cfcp_row_checkbox(
			'cfcp_search_noindex',
			'Não indexar a busca',
			'Pedir aos buscadores que não indexem nem rastreiem a busca',
			'Acrescenta <code>X-Robots-Tag: noindex</code> nas páginas de busca e regras <code>Disallow</code> no robots.txt. Robôs de buscadores respeitam; robôs mal-intencionados não, por isso os limites acima continuam necessários. Se existir um <code>robots.txt</code> físico no site, o WordPress não o gera e essas linhas precisam ser acrescentadas nele à mão (o Diagnóstico avisa).',
			'Marcado.'
		);
		?>
	</table>

	<h3>Paginação profunda</h3>
	<table class="form-table">
		<?php
		cfcp_row_number(
			'cfcp_archive_max_pages',
			'Páginas de arquivo (máximo)',
			'Categorias, tags, autores e a página de posts acima deste número de página respondem 404 curto (que a Cloudflare guarda por alguns minutos). <code>/page/9999/</code> é alvo clássico de robô e cada uma dessas páginas é uma consulta grande que nunca está no cache. 0 desliga.',
			'200 (ou o número real de páginas que o site tem, com folga).',
			'páginas'
		);
		?>
	</table>
</div>
