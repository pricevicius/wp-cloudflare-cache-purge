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
| TTL padrão | `Cache-Control`/`CDN-Cache-Control` para posts e páginas | 2592000s (30 dias) |
| TTL da home | TTL para a página inicial / blog | 900s (15 min) |
| TTL de páginas 404 | TTL para páginas não encontradas | 2592000s (30 dias) |
| TTL de feeds | TTL para feeds RSS/Atom | 300s (5 min) |

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
