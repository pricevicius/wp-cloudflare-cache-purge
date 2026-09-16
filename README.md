# WP Cloudflare Cache Purge

Plugin para WordPress que limpa (purge) o cache do Cloudflare automaticamente sempre que um post ou página é publicado, atualizado, movido para a lixeira ou excluído.

## Problema que resolve

Sites que usam Cloudflare como CDN/cache costumam servir conteúdo desatualizado depois de uma edição, até o cache expirar ou alguém limpar manualmente no painel da Cloudflare. Este plugin automatiza esse processo, disparando o purge das URLs relevantes (home, paginação, post, categorias, tags, página de posts) toda vez que o conteúdo muda.

Também é possível usar purge por **Cache-Tag** (se o seu plano/configuração Cloudflare suportar), enviando automaticamente o header `Cache-Tag` nas respostas do front-end.

## Como funciona

- Ao salvar, mover para lixeira ou excluir definitivamente um post publicado, o plugin monta a lista de URLs afetadas e chama a API de purge da Cloudflare.
- As credenciais (e-mail, API Key e Zone ID da Cloudflare) são configuradas na tela **Cloudflare Cache Purge** no admin do WordPress.

## Instalação

1. Copie a pasta do plugin para `wp-content/plugins/`.
2. Ative o plugin em **Plugins** no admin do WordPress.
3. Acesse o menu **Cloudflare Cache Purge** e informe:
   - E-mail de conexão da conta Cloudflare
   - Zone ID
   - API Key
4. Pronto — o purge passa a acontecer automaticamente.

## Requisitos

- WordPress 5.0+
- PHP 7.4+
- Uma conta Cloudflare com o domínio configurado e uma API Key válida

## Licença

MIT — veja o arquivo [LICENSE](LICENSE).
