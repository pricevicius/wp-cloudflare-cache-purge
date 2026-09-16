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
?>
		<div class="wrap">
			<div class="header">
				<h3>WP Cloudflare Cache Purge - Configurações</h3>
				<p>Configurações para o plugin WP Cloudflare Cache Purge</p>
			</div>
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
}
add_action('admin_init', 'cfcp_register_settings');