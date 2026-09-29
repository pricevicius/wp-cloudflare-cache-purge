# WP Cloudflare Cache Purge

Plugin para WordPress que limpa (purge) o cache do Cloudflare automaticamente sempre que um post ou página é publicado, atualizado, movido para a lixeira ou excluído.

## Problema que resolve

Sites que usam Cloudflare como CDN/cache costumam servir conteúdo desatualizado depois de uma edição, até o cache expirar ou alguém limpar manualmente no painel da Cloudflare. Este plugin automatiza esse processo, disparando o purge das URLs relevantes (home, paginação, post, categorias, tags, página de posts) toda vez que o conteúdo muda.

Também é possível usar purge por **Cache-Tag** (se o seu plano/configuração Cloudflare suportar), enviando automaticamente o header `Cache-Tag` nas respostas do front-end.

Além do purge, o plugin também controla os headers `Cache-Control` / `CDN-Cache-Control` enviados pelo WordPress, direto pelo admin — sem precisar editar arquivos do tema.

## Como funciona

- Ao salvar, mover para lixeira ou excluir definitivamente um post publicado, o plugin monta a lista de URLs afetadas e chama a API de purge da Cloudflare.
- As credenciais (e-mail, API Key e Zone ID da Cloudflare) são configuradas na aba **Conexão Cloudflare** do admin do WordPress.
- Os TTLs de cache são configurados na aba **Controle de Cache**, sem precisar mexer em código.

## Instalação

1. Copie a pasta do plugin para `wp-content/plugins/`.
2. Ative o plugin em **Plugins** no admin do WordPress.
3. Acesse o menu **Cloudflare Cache Purge**:
   - Na aba **Conexão Cloudflare**, informe e-mail, Zone ID e API Key.
   - Na aba **Controle de Cache**, ajuste os TTLs (ou desative, se já tiver outra regra de cache).
4. Pronto — o purge e os headers de cache passam a ser gerenciados automaticamente.

## Controle de Cache (Cache-Control / CDN-Cache-Control)

Na aba **Controle de Cache** você configura:

| Opção | O que faz | Padrão |
|---|---|---|
| Ativar controle de cache | Liga/desliga o envio dos headers pelo plugin | Ativado |
| TTL padrão | TTL **na Cloudflare** (`CDN-Cache-Control`) para posts, categorias e páginas | 3600s (1 hora) |
| TTL da home | TTL na Cloudflare para a página inicial / blog | 900s (15 min) |
| TTL de páginas 404 | TTL na Cloudflare para páginas não encontradas | 300s (5 min) |
| TTL de redirecionamentos | TTL na Cloudflare para redirects 301 (no navegador nunca são guardados) | 300s (5 min) |
| TTL de feeds | TTL para feeds RSS/Atom | 300s (5 min) |
| TTL do navegador | `Cache-Control` que o leitor recebe; nunca passa do TTL da Cloudflare da mesma página | 120s (2 min) |
| Servir cópia antiga enquanto atualiza | `stale-while-revalidate`: na expiração só uma requisição vai à origem | 60s |
| Servir cópia antiga se a origem falhar | `stale-if-error`: a Cloudflare serve a última cópia se a origem cair | 86400s (1 dia) |
| TTL da busca (micro-cache) | Por padrão a busca **não** é cacheada. Um valor curto ajuda em termos em alta | 0 (desligado) |

**Por que o TTL do navegador é separado.** A Cloudflare pode ser limpa quando uma matéria é publicada ou corrigida; o navegador do leitor não. Por isso o navegador recebe um prazo curto, e quando ele expira o pedido cai no cache da Cloudflare, não na origem.

Usuários logados e prévias sempre recebem `no-cache` (`nocache_headers()`), independente da configuração — isso não é ajustável no admin por segurança.

**Double-check:** toda resposta do site inclui o header `X-CFCP-Cache-Rule` (ex.: `enabled=1;scope=home;ttl=900`), indicando se o controle está ativo, qual regra foi aplicada e o TTL usado. Útil pra confirmar que a configuração do admin realmente chegou até a resposta HTTP:

```bash
curl -I https://seusite.com/ | grep -i "x-cfcp-cache-rule\|cache-control\|cf-cache-status"
```

Se você já tinha um `cache-control.php` incluído manualmente no `header.php` do tema, remova esse include — a partir desta versão o plugin assume esse controle.

## Requisitos

- **WordPress:** 5.0 ou superior
- **PHP:** 7.4 ou superior (recomendado 8.0+)
- Uma conta Cloudflare com o domínio configurado e uma API Key válida
- **Opcional:** [Advanced Custom Fields](https://www.advancedcustomfields.com/) — se o plugin estiver ativo e existir o campo `autor_relacionado`, o purge e as Cache-Tags também consideram a página do autor relacionado. Sem o ACF, essa parte é simplesmente ignorada.

## Licença

MIT — veja o arquivo [LICENSE](LICENSE).

## Proteção da origem

Em site de muito acesso, o que derruba o servidor quase nunca é a página que está no cache, e sim o que o cache não segura. A busca é o caso clássico: cada termo é uma URL diferente, então um robô que varia o termo passa por cima de qualquer cache, e cada requisição ocupa um processo do PHP-FPM. Se a busca depende de um serviço externo (Meilisearch, Elasticsearch) e ele fica lento, os processos ficam presos esperando e **o site inteiro para**, incluindo o painel da redação.

A aba **Proteção da origem** aplica limites dentro do WordPress, antes da consulta pesada. Funciona com a busca nativa e com motores externos.

Cada controle respeita o **modo**: *Desligado*, *Observação* (só conta o que teria bloqueado; é o padrão) ou *Bloquear*. Quem pode editar posts nunca é limitado, e há uma lista de IPs sempre liberados.

| Controle | O que faz | Padrão |
|---|---|---|
| Buscas simultâneas | No máximo N buscas em andamento; a seguinte recebe 503 na hora, sem prender um processo | 4 |
| Buscas por IP | Limite por janela de tempo; excedeu, 429 | 10 por 60 s |
| Prazo máximo do motor de busca | Limita chamadas externas durante uma busca (o cliente do Meilisearch espera até 30 s) | 3 s |
| Se o motor de busca falhar | Permitir ou **bloquear a busca nativa** (`LIKE` no banco), a pior consulta possível num banco sob pressão | Permitir |
| Tamanho do termo | Recusa termos vazios, curtos ou longos demais (400) | 2 a 100 caracteres |
| Páginas de resultados | Teto de páginas da busca (404 acima) | 10 |
| Rotas REST de busca | Aplica os mesmos limites ao autocompletar e à busca por API | `/celersearch/v1/...` |
| Não indexar a busca | `X-Robots-Tag: noindex` e `Disallow` no robots.txt | Ligado |
| Páginas de arquivo | Teto de `/page/N/` em qualquer listagem, inclusive home estática (404 curto acima) | 200 |

**Política de busca nativa.** Alguns plugins de busca voltam para a busca nativa do WordPress quando o motor externo falha. Com a política *Bloquear*, a busca nativa nunca roda (o leitor vê "busca indisponível", 503). O plugin detecta que o `LIKE` está prestes a rodar pela presença do termo na consulta: um motor que atendeu a busca troca o termo por uma lista de IDs. Só use *Bloquear* se a busca do site depende de um motor externo.

**IP real do visitante.** Atrás de um proxy (Cloudflare, nginx proxy manager), o WordPress só enxerga o IP do proxy, e o limite por IP trataria todos os leitores como uma pessoa. Informe os proxies confiáveis e o cabeçalho com o IP real (`CF-Connecting-IP`). O cabeçalho só é aceito quando a conexão vem de um proxy da lista, porque de qualquer outra origem ele poderia ser forjado. A janela do limite por IP é fixa, então em torno da virada de cada janela um IP pode fazer até cerca de 2x o limite.

**Onde ficam contadores e travas.** O plugin usa, nesta ordem, o cache de objetos externo (Redis/Memcached), o APCu e, se não houver nenhum, arquivos temporários com `flock`. Todos são atômicos entre processos; só o primeiro vale para vários servidores. A aba **Diagnóstico** mostra qual está em uso.

## Diagnóstico

A aba **Diagnóstico** reúne o que normalmente exige terminal:

- Uma lista de verificação com o que está certo e o que precisa de atenção.
- **Testar a Cloudflare agora**: busca a home duas vezes pela URL pública e mostra `cf-cache-status` (`MISS`/`HIT`/`DYNAMIC`), `age` e os TTLs; confere as credenciais da API sem alterar nada.
- Contadores de hoje e dos últimos 7 dias: buscas, tempo médio, buscas lentas, o que cada regra teria bloqueado ou bloqueou, chamadas de purge, erros e 429.
- IP do visitante como o plugin o enxerga, uso do OPcache e onde ficam os contadores.

## Purge

- O purge é **assíncrono**: a lista de URLs e tags é montada ao salvar, mas as chamadas à API só saem depois de responder ao editor. Vários posts na mesma requisição (ex: importador) viram uma fila única, sem repetição.
- Envio em **lotes de 30**, que funciona em qualquer plano da Cloudflare; o primeiro 429 interrompe os lotes seguintes.
- Aceita **API Token** com permissão só de *Zone > Cache Purge* (recomendado) ou e-mail + Global API Key. Também aceita as constantes `CFCP_CF_TOKEN` e `CFCP_CF_ZONE_ID` no `wp-config.php`.
- As cache tags levam um prefixo por site (`CFCP_TAG_PREFIX`, ou o caminho do site, ou `root`), para dois sites na mesma zona da Cloudflare não limparem o cache um do outro.

