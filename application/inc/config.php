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
// Renderiza a página de configurações do plugin
function cfcp_plugin_settings_page() {
	$tabs = [
		'general' => 'Conexão Cloudflare',
		'cache'   => 'Controle de Cache',
	];

	$active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
	if (!array_key_exists($active_tab, $tabs)) {
		$active_tab = 'general';
	}
?>
		<div class="wrap">
			<div class="header">
				<h3>WP Cloudflare Cache Purge - Configurações</h3>
				<p>Configurações para o plugin WP Cloudflare Cache Purge</p>
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
			<form method="post" action="options.php">
				<?php settings_fields('cfcp-settings-group'); ?>
				<?php do_settings_sections('cfcp-settings-group'); ?>
				<?php include plugin_dir_path(__DIR__).'tpml/fields.php'; ?>
				<?php submit_button(); ?>
			</form>
		</div>
<?php
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

    // Controle de cache (Cache-Control / CDN-Cache-Control)
    register_setting(
        'cfcp-settings-group',
        'cfcp_cache_control_enabled',
        [
            'sanitize_callback' => 'cfcp_sanitize_checkbox',
            'default'           => '1',
        ]
    );
    register_setting(
        'cfcp-settings-group',
        'cfcp_cache_ttl_default',
        [
            'sanitize_callback' => 'absint',
            'default'           => 2592000,
        ]
    );
    register_setting(
        'cfcp-settings-group',
        'cfcp_cache_ttl_home',
        [
            'sanitize_callback' => 'absint',
            'default'           => 900,
        ]
    );
    register_setting(
        'cfcp-settings-group',
        'cfcp_cache_ttl_404',
        [
            'sanitize_callback' => 'absint',
            'default'           => 2592000,
        ]
    );
    register_setting(
        'cfcp-settings-group',
        'cfcp_cache_ttl_feed',
        [
            'sanitize_callback' => 'absint',
            'default'           => 300,
        ]
    );
}
add_action('admin_init', 'cfcp_register_settings');

/**
 * Checkboxes não enviam nada no POST quando desmarcados, então
 * normalizamos para '1'/'0' em vez de depender do valor bruto.
 */
function cfcp_sanitize_checkbox($value)
{
    return $value ? '1' : '0';
}