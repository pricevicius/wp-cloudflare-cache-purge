<?php
// Adiciona uma página de configurações para o plugin
function cfcp_plugin_settings() {
    // Ícone do menu (dica: PNG é mais seguro no admin; SVG pode não renderizar)
    $icon_url = plugin_dir_url(dirname(__DIR__)) . 'assets/img/favicon.svg';
    // 1) Cria o menu e captura o hook da tela
    $hook = add_menu_page(
        'Configurações do WP Cloudflare Cache Purge', // Título da página
        'Cloudflare Cache Purge',                  // Título do menu
        'manage_options',                  // Capability
        'cfcp-plugin-settings',          // Slug
        'cfcp_plugin_settings_page',     // Callback que renderiza a página
        $icon_url,                         // Ícone
        99                                 // Posição
    );
}
add_action('admin_menu', 'cfcp_plugin_settings');

/**
 * Ajudas de tela: cada linha mostra o campo, o que ele faz e o valor
 * recomendado, para quem configura entender o efeito sem abrir o código.
 */
function cfcp_help_html($html)
{
	return wp_kses($html, ['code' => [], 'strong' => [], 'em' => [], 'br' => []]);
}

function cfcp_row_start($name, $label)
{
	echo '<tr valign="top"><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
}

function cfcp_row_end($desc, $recommended = '')
{
	echo '<p class="description">' . cfcp_help_html($desc);

	if ($recommended !== '') {
		echo '<br><strong>Recomendado:</strong> ' . cfcp_help_html($recommended);
	}

	echo '</p></td></tr>';
}

function cfcp_row_number($name, $label, $desc, $recommended = '', $unit = '')
{
	cfcp_row_start($name, $label);
	echo '<input type="number" min="0" step="1" class="small-text" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr(cfcp_get($name)) . '"> ' . esc_html($unit);
	cfcp_row_end($desc, $recommended);
}

function cfcp_row_text($name, $label, $desc, $recommended = '')
{
	cfcp_row_start($name, $label);
	echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr(cfcp_get($name)) . '">';
	cfcp_row_end($desc, $recommended);
}

function cfcp_row_textarea($name, $label, $desc, $recommended = '', $rows = 4)
{
	cfcp_row_start($name, $label);
	echo '<textarea class="large-text code" rows="' . (int) $rows . '" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">' . esc_textarea(cfcp_get($name)) . '</textarea>';
	cfcp_row_end($desc, $recommended);
}

function cfcp_row_select($name, $label, $options, $desc, $recommended = '')
{
	cfcp_row_start($name, $label);
	echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';

	foreach ($options as $value => $text) {
		echo '<option value="' . esc_attr($value) . '"' . selected((string) cfcp_get($name), (string) $value, false) . '>' . esc_html($text) . '</option>';
	}

	echo '</select>';
	cfcp_row_end($desc, $recommended);
}

function cfcp_row_checkbox($name, $label, $checkbox_text, $desc, $recommended = '')
{
	cfcp_row_start($name, $label);
	echo '<input type="hidden" name="' . esc_attr($name) . '" value="0"><label><input type="checkbox" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="1"' . checked(cfcp_get($name), '1', false) . '> ' . esc_html($checkbox_text) . '</label>';
	cfcp_row_end($desc, $recommended);
}

// Renderiza a página de configurações do plugin
function cfcp_plugin_settings_page() {
	$tabs = [
		'general'     => 'Conexão Cloudflare',
		'cache'       => 'Controle de Cache',
		'protection'  => 'Proteção da origem',
		'diagnostics' => 'Diagnóstico',
	];

	$active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
	if (!array_key_exists($active_tab, $tabs)) {
		$active_tab = 'general';
	}
?>
		<style>
			.cfcp-box{background:#fff;border:1px solid #c3c4c7;border-left-width:4px;padding:10px 14px;margin:14px 0;max-width:900px}
			.cfcp-box.info{border-left-color:#2271b1}.cfcp-box.warn{border-left-color:#dba617}.cfcp-box.bad{border-left-color:#d63638}.cfcp-box.ok{border-left-color:#00a32a}
			.cfcp-badge{display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;font-weight:600;color:#fff}
			.cfcp-badge.ok{background:#00a32a}.cfcp-badge.warn{background:#b8860b}.cfcp-badge.bad{background:#d63638}
			.cfcp-table{border-collapse:collapse;max-width:900px;width:100%;background:#fff}
			.cfcp-table th,.cfcp-table td{border:1px solid #dcdcde;padding:6px 10px;text-align:left;font-size:13px}
			.cfcp-table th{background:#f6f7f7}.cfcp-table td.n{text-align:right;font-variant-numeric:tabular-nums}
			.cfcp-section h3{margin-top:28px}
		</style>
		<div class="wrap">
			<div class="header">
				<h3>WP Cloudflare Cache Purge - Configurações</h3>
				<p>Cache na Cloudflare, limpeza automática ao publicar e proteção da origem contra picos e robôs.</p>
			</div>
			<h2 class="nav-tab-wrapper">
				<?php foreach ($tabs as $tab_slug => $tab_label) : ?>
					<a href="<?php echo esc_url(add_query_arg(['page' => 'cfcp-plugin-settings', 'tab' => $tab_slug], admin_url('admin.php'))); ?>"
						class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html($tab_label); ?>
					</a>
				<?php endforeach; ?>
			</h2>
			<?php settings_errors(); ?>
			<?php if ($active_tab === 'diagnostics') : ?>
				<?php include plugin_dir_path(__DIR__).'tpml/diagnostics.php'; ?>
			<?php else : ?>
				<form method="post" action="options.php">
					<?php settings_fields('cfcp-settings-group'); ?>
					<?php do_settings_sections('cfcp-settings-group'); ?>
					<?php include plugin_dir_path(__DIR__).'tpml/fields.php'; ?>
					<?php submit_button(); ?>
				</form>
			<?php endif; ?>
		</div>
<?php
}

/**
 * Sanitizadores dos campos novos
 */
function cfcp_sanitize_checkbox($value)
{
    return $value ? '1' : '0';
}

function cfcp_sanitize_guard_mode($value)
{
    return in_array($value, ['off', 'observe', 'enforce'], true) ? $value : 'observe';
}

function cfcp_sanitize_native_policy($value)
{
    return $value === 'block' ? 'block' : 'allow';
}

function cfcp_sanitize_ip_list($value)
{
    return trim(preg_replace('/[^0-9a-fA-F:\.\/\r\n,#\- ]/', '', (string) $value));
}

function cfcp_sanitize_route_list($value)
{
    return trim(preg_replace('/[^A-Za-z0-9_\-\.\/\r\n,#]/', '', (string) $value));
}

function cfcp_sanitize_header_name($value)
{
    $value = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $value);

    return $value !== '' ? $value : 'CF-Connecting-IP';
}

// Registra as opções de configuração do plugin
function cfcp_register_settings() {
    register_setting(
        'cfcp-settings-group',              // Nome do grupo de opções
        'cfcp_email',                            // Nome da opção de URL
    );
    register_setting(
        'cfcp-settings-group',              // Nome do grupo de opções
        'cfcp_zone',                            // Nome da opção de URL
    );
    register_setting(
        'cfcp-settings-group',              // Nome do grupo de opções
        'cfcp_api',                            // Nome da opção de URL
    );
    register_setting(
        'cfcp-settings-group',
        'cfcp_api_token',
        [
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );

    // Controle de cache (Cache-Control / CDN-Cache-Control)
    register_setting(
        'cfcp-settings-group',
        'cfcp_cache_control_enabled',
        [
            'sanitize_callback' => 'cfcp_sanitize_checkbox',
            'default'           => '1',
        ]
    );

    // TTLs em segundos e demais números. Os padrões vêm de cfcp_defaults().
    $numeric = [
        'cfcp_cache_ttl_default', 'cfcp_cache_ttl_home', 'cfcp_cache_ttl_404', 'cfcp_cache_ttl_feed',
        'cfcp_cache_ttl_browser', 'cfcp_cache_ttl_redirect', 'cfcp_cache_ttl_search', 'cfcp_swr', 'cfcp_sie',
        'cfcp_search_min_len', 'cfcp_search_max_len', 'cfcp_search_max_concurrent', 'cfcp_search_rate_limit',
        'cfcp_search_rate_window', 'cfcp_search_http_timeout', 'cfcp_search_max_pages', 'cfcp_archive_max_pages',
    ];

    $defaults = cfcp_defaults();

    foreach ($numeric as $option) {
        register_setting('cfcp-settings-group', $option, [
            'sanitize_callback' => 'absint',
            'default'           => $defaults[$option],
        ]);
    }

    register_setting('cfcp-settings-group', 'cfcp_guard_mode', ['sanitize_callback' => 'cfcp_sanitize_guard_mode', 'default' => $defaults['cfcp_guard_mode']]);
    register_setting('cfcp-settings-group', 'cfcp_search_native_policy', ['sanitize_callback' => 'cfcp_sanitize_native_policy', 'default' => $defaults['cfcp_search_native_policy']]);
    register_setting('cfcp-settings-group', 'cfcp_guard_allow_ips', ['sanitize_callback' => 'cfcp_sanitize_ip_list', 'default' => '']);
    register_setting('cfcp-settings-group', 'cfcp_trusted_proxies', ['sanitize_callback' => 'cfcp_sanitize_ip_list', 'default' => '']);
    register_setting('cfcp-settings-group', 'cfcp_search_rest_routes', ['sanitize_callback' => 'cfcp_sanitize_route_list', 'default' => $defaults['cfcp_search_rest_routes']]);
    register_setting('cfcp-settings-group', 'cfcp_real_ip_header', ['sanitize_callback' => 'cfcp_sanitize_header_name', 'default' => $defaults['cfcp_real_ip_header']]);
    register_setting('cfcp-settings-group', 'cfcp_override_remote_addr', ['sanitize_callback' => 'cfcp_sanitize_checkbox', 'default' => '0']);
    register_setting('cfcp-settings-group', 'cfcp_search_noindex', ['sanitize_callback' => 'cfcp_sanitize_checkbox', 'default' => '1']);
}
add_action('admin_init', 'cfcp_register_settings');
