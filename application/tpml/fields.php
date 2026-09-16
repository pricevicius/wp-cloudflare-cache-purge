<div class="firstconfig" id="firstconfig">
	<table class="form-table">
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
					Insira a chave da API do Cloudflare
				</p>
			</td>
		</tr>

	</table>
</div>