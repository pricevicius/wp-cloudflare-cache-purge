<?php

/**
 * CONFIG OPCIONAL
 *
 * Se quiser controlar facilmente, pode definir no wp-config.php:
 *
 * define('CFCP_USE_CACHE_TAGS', true);
 * define('CFCP_CF_TOKEN', 'SEU_TOKEN');      // API Token com permissão só de Cache Purge
 * define('CFCP_CF_ZONE_ID', 'SEU_ZONE_ID');
 * define('CFCP_TAG_PREFIX', 'es');           // prefixo das cache tags deste site
 *
 * Sites que dividem a mesma zona da Cloudflare (ex: raiz e /pe) precisam de
 * prefixos diferentes, senão purgar 'post-123' limpa o post 123 dos dois.
 * Sem CFCP_TAG_PREFIX, o prefixo sai do caminho do site ('pe' para /pe) ou
 * 'root' quando o site está na raiz do domínio.
 */

function cfcp_get_purger()
{
	return new CFCP_Purger(
		CFCP_URL,
		CFCP_SECRETKEY,
		CFCP_ZONE,
		defined('CFCP_TOKEN') ? CFCP_TOKEN : ''
	);
}

/**
 * Adiciona URL base + primeiras páginas paginadas
 */
function cfcp_add_paginated_urls(&$urls, $base_url, $max_pages = 5)
{
	if (empty($base_url) || is_wp_error($base_url)) {
		return;
	}

	$base_url = trailingslashit($base_url);
	$urls[]   = $base_url;

	for ($i = 2; $i <= (int) $max_pages; $i++) {
		$urls[] = $base_url . 'page/' . $i . '/';
	}
}

/**
 * Monta a lista de URLs para purge
 */
function cfcp_build_urls_to_purge($post_id, $post_url = '')
{
	$urls = [];

	// Home
	$home_url = home_url('/');
	cfcp_add_paginated_urls($urls, $home_url, 1);

	// URL do post
	if (!empty($post_url) && !is_wp_error($post_url)) {
		$urls[] = $post_url;
	}

	// Autor relacionado (ACF)
	$autorrelated = function_exists('get_field') ? get_field('autor_relacionado', $post_id) : null;
	if (!empty($autorrelated)) {
		$autor_url = get_permalink($autorrelated);
		if (!is_wp_error($autor_url) && !empty($autor_url)) {
			cfcp_add_paginated_urls($urls, $autor_url, 10);
		}
	}

	// Página definida como "Página de posts"
	$page_for_posts_id = (int) get_option('page_for_posts');
	if ($page_for_posts_id) {
		$page_for_posts_url = get_permalink($page_for_posts_id);
		if (!is_wp_error($page_for_posts_url) && !empty($page_for_posts_url)) {
			cfcp_add_paginated_urls($urls, $page_for_posts_url, 10);
		}
	}

	// Categorias e ancestrais + paginação
	$categories = get_the_category($post_id);
	if (!empty($categories) && !is_wp_error($categories)) {
		foreach ($categories as $category) {
			$term_ids_seen = [];
			$term          = $category;

			while ($term && !is_wp_error($term)) {
				if (in_array((int) $term->term_id, $term_ids_seen, true)) {
					break;
				}

				$term_ids_seen[] = (int) $term->term_id;

				$link = get_category_link($term);
				if (!is_wp_error($link) && !empty($link)) {
					cfcp_add_paginated_urls($urls, $link, 10);
				}

				$term = !empty($term->parent) ? get_term($term->parent, 'category') : null;
			}
		}
	}

	// // Tags + paginação
	// $post_tags = get_the_tags($post_id);
	// if (!empty($post_tags) && !is_wp_error($post_tags)) {
	// 	foreach ($post_tags as $tag) {
	// 		$link = get_tag_link($tag->term_id);
	// 		if (!is_wp_error($link) && !empty($link)) {
	// 			cfcp_add_paginated_urls($urls, $link, 5);
	// 		}
	// 	}
	// }

	return array_values(array_unique(array_filter($urls)));
}

/**
 * Monta as cache tags para purge
 */
function cfcp_build_cache_tags_to_purge($post_id)
{
	$tags = [
		'home',
		'blog',
		'post-' . (int) $post_id,
	];

	$page_for_posts_id = (int) get_option('page_for_posts');
	if ($page_for_posts_id) {
		$tags[] = 'page-for-posts-' . $page_for_posts_id;
	}

	$categories = get_the_category($post_id);
	if (!empty($categories) && !is_wp_error($categories)) {
		foreach ($categories as $category) {
			$term_ids_seen = [];
			$term          = $category;

			while ($term && !is_wp_error($term)) {
				if (in_array((int) $term->term_id, $term_ids_seen, true)) {
					break;
				}

				$term_ids_seen[] = (int) $term->term_id;

				$tags[] = 'cat-' . (int) $term->term_id;
				$tags[] = 'cat-' . sanitize_title($term->slug);

				$term = !empty($term->parent) ? get_term($term->parent, 'category') : null;
			}
		}
	}

	$post_tags = get_the_tags($post_id);
	if (!empty($post_tags) && !is_wp_error($post_tags)) {
		foreach ($post_tags as $tag) {
			$tags[] = 'tag-' . (int) $tag->term_id;
			$tags[] = 'tag-' . sanitize_title($tag->slug);
		}
	}

	$autorrelated = function_exists('get_field') ? get_field('autor_relacionado', $post_id) : null;
	if (!empty($autorrelated)) {
		$tags[] = 'author-related-' . (int) $autorrelated;
	}

	return array_map('cfcp_tag', array_values(array_unique(array_filter($tags))));
}

/**
 * Purge por URLs
 */
function cfcp_purge_urls(array $urls_to_purge)
{
	$urls_to_purge = array_values(array_unique(array_filter($urls_to_purge)));

	if (empty($urls_to_purge)) {
		return false;
	}

	return cfcp_get_purger()->cacheConnection($urls_to_purge);
}

/**
 * Purge por Cache-Tag
 * Requer suporte da Cloudflare/API para purge por tag.
 */
function cfcp_purge_tags(array $tags_to_purge)
{
	$tags_to_purge = array_values(array_unique(array_filter($tags_to_purge)));

	if (empty($tags_to_purge)) {
		return false;
	}

	return cfcp_get_purger()->cacheConnectionTags($tags_to_purge);
}

/**
 * Guarda a última URL pública real
 */
function cfcp_store_last_public_url($post_id)
{
	$url = get_permalink($post_id);

	if ($url && !is_wp_error($url)) {
		update_post_meta($post_id, '_cfcp_last_public_url', esc_url_raw($url));
	}
}

/**
 * Purge assíncrono: as listas de URLs e tags são montadas na hora do save
 * (precisam do estado do post), mas as chamadas à API da Cloudflare só
 * saem no fim da requisição, depois de responder ao editor. Vários posts
 * salvos na mesma requisição (ex: importador) viram uma fila única, sem
 * URL ou tag repetida.
 */
function cfcp_run_full_purge($post_id, $post_url = '')
{
	global $cfcp_purge_queue;

	if (!is_array($cfcp_purge_queue)) {
		$cfcp_purge_queue = ['urls' => [], 'tags' => []];
	}

	$cfcp_purge_queue['urls'] = array_merge($cfcp_purge_queue['urls'], cfcp_build_urls_to_purge($post_id, $post_url));
	$cfcp_purge_queue['tags'] = array_merge($cfcp_purge_queue['tags'], cfcp_build_cache_tags_to_purge($post_id));

	if (!has_action('shutdown', 'cfcp_flush_purge_queue')) {
		add_action('shutdown', 'cfcp_flush_purge_queue', 100);
	}
}

function cfcp_flush_purge_queue()
{
	global $cfcp_purge_queue;

	if (empty($cfcp_purge_queue) || !is_array($cfcp_purge_queue)) {
		return;
	}

	$urls = array_values(array_unique($cfcp_purge_queue['urls']));
	$tags = array_values(array_unique($cfcp_purge_queue['tags']));

	$cfcp_purge_queue = null;

	ignore_user_abort(true);

	// Libera a resposta ao editor antes de falar com a API da Cloudflare.
	if (function_exists('fastcgi_finish_request')) {
		fastcgi_finish_request();
	}

	if (!empty($urls)) {
		cfcp_purge_urls($urls);
	}

	if (!empty($tags)) {
		cfcp_purge_tags($tags);
	}
}

/**
 * SAVE POST
 */
function cfcp_savepost($post_id, $post, $update)
{
	if (wp_is_post_revision($post_id)) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (wp_doing_ajax()) {
		return;
	}

	if (!$post || !isset($post->post_status)) {
		return;
	}

	if ($post->post_status !== 'publish') {
		return;
	}

	$lock = 'cfcp_savepost_' . $post_id;
	if (get_transient($lock)) {
		return;
	}

	set_transient($lock, true, 10);

	cfcp_store_last_public_url($post_id);

	$post_url = get_permalink($post_id);
	cfcp_run_full_purge($post_id, $post_url);
}
add_action('save_post', 'cfcp_savepost', 10, 3);

/**
 * LIXEIRA
 */
function cfcp_on_wp_trash_post($post_id)
{
	if (wp_is_post_revision($post_id)) {
		return;
	}

	$post = get_post($post_id);
	if (!$post) {
		return;
	}

	if ($post->post_status !== 'publish') {
		return;
	}

	$lock = 'cfcp_trash_' . $post_id;
	if (get_transient($lock)) {
		return;
	}

	set_transient($lock, true, 10);

	$post_url = get_permalink($post_id);

	if ($post_url && !is_wp_error($post_url)) {
		update_post_meta($post_id, '_cfcp_last_public_url', esc_url_raw($post_url));
	}

	cfcp_run_full_purge($post_id, $post_url);
}
add_action('wp_trash_post', 'cfcp_on_wp_trash_post', 10, 1);

/**
 * DELETE DEFINITIVO
 */
function cfcp_on_before_delete_post($post_id)
{
	if (wp_is_post_revision($post_id)) {
		return;
	}

	$lock = 'cfcp_delete_' . $post_id;
	if (get_transient($lock)) {
		return;
	}

	set_transient($lock, true, 10);

	$post_url = get_post_meta($post_id, '_cfcp_last_public_url', true);

	if (empty($post_url)) {
		$post = get_post($post_id);
		if ($post && $post->post_status === 'publish') {
			$post_url = get_permalink($post_id);
		}
	}

	cfcp_run_full_purge($post_id, $post_url);
}
add_action('before_delete_post', 'cfcp_on_before_delete_post', 10, 1);

/**
 * Header Cache-Tag
 */
function cfcp_send_cache_tag_headers()
{
	if (is_admin() || is_feed() || is_preview() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
		return;
	}

	$use_cache_tags = defined('CFCP_USE_CACHE_TAGS') ? (bool) CFCP_USE_CACHE_TAGS : true;
	if (!$use_cache_tags) {
		return;
	}

	$tags = [];

	// Home / blog
	if (is_front_page() || is_home()) {
		$tags[] = 'home';
		$tags[] = 'blog';
	}

	// Página de posts
	$page_for_posts_id = (int) get_option('page_for_posts');
	if ($page_for_posts_id && is_page($page_for_posts_id)) {
		$tags[] = 'blog';
		$tags[] = 'page-for-posts-' . $page_for_posts_id;
	}

	// Post individual
	if (is_single()) {
		$post_id = get_queried_object_id();

		if ($post_id) {
			$tags[] = 'post-' . (int) $post_id;

			$categories = get_the_category($post_id);
			if (!empty($categories) && !is_wp_error($categories)) {
				foreach ($categories as $category) {
					$tags[] = 'cat-' . (int) $category->term_id;
					$tags[] = 'cat-' . sanitize_title($category->slug);
				}
			}

			$post_tags = get_the_tags($post_id);
			if (!empty($post_tags) && !is_wp_error($post_tags)) {
				foreach ($post_tags as $tag) {
					$tags[] = 'tag-' . (int) $tag->term_id;
					$tags[] = 'tag-' . sanitize_title($tag->slug);
				}
			}

			$autorrelated = function_exists('get_field') ? get_field('autor_relacionado', $post_id) : null;
			if (!empty($autorrelated)) {
				$tags[] = 'author-related-' . (int) $autorrelated;
			}
		}
	}

	// Arquivo de categoria
	if (is_category()) {
		$term = get_queried_object();
		if ($term && !is_wp_error($term)) {
			$tags[] = 'cat-' . (int) $term->term_id;
			$tags[] = 'cat-' . sanitize_title($term->slug);
			$tags[] = 'taxonomy-category';
			$tags[] = 'blog';
		}
	}

	// Arquivo de tag
	if (is_tag()) {
		$term = get_queried_object();
		if ($term && !is_wp_error($term)) {
			$tags[] = 'tag-' . (int) $term->term_id;
			$tags[] = 'tag-' . sanitize_title($term->slug);
			$tags[] = 'taxonomy-post_tag';
			$tags[] = 'blog';
		}
	}

	$tags = array_map('cfcp_tag', array_values(array_unique(array_filter($tags))));

	if (!empty($tags) && !headers_sent()) {
		header('Cache-Tag: ' . implode(',', $tags));
	}
}
add_action('send_headers', 'cfcp_send_cache_tag_headers', 20);