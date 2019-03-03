<?php
/**
 * Plugin settings page
 */

function wcqbi_plugin_create_menu() {
	add_menu_page( 'QuickBooks Connect', 'QuickBooks', 'administrator', 'quickbooks-connect', 'wcqbi_plugin_settings_page' );
	add_action( 'admin_init', 'wcqbi_plugin_settings' );
}
add_action( 'admin_menu', 'wcqbi_plugin_create_menu' );

function wcqbi_plugin_settings() {
	$defaults = [
		'auth_mode'    => 'oauth2',
		'scope'        => 'com.intuit.quickbooks.accounting',
		'RedirectURI'  => esc_url( get_site_url( null, '/wp-admin/admin.php?page=quickbooks-connect' ) ),
		'ClientID'     => qbo_client_id(), // Just for local dev.
		'ClientSecret' => qbo_client_secret(), // Just for local dev.
		'baseUrl'      => 'development',
	];

	register_setting( 'wcqbi-plugin-settings-group', 'wcqbi_qbo', [ 'default' => $defaults ] );
}

function wcqbi_plugin_settings_page() {
	$options = get_option( 'wcqbi_qbo' );
	?>

	<div class="wrap">
		<h1>QuickBooks Connect</h1>

		<form method="post" action="options.php">
			<?php settings_fields( 'wcqbi-plugin-settings-group' ); ?>
			<?php do_settings_sections( 'wcqbi-plugin-settings-group' ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Client ID</th>
					<td>
						<input type="text" name="wcqbi_qbo[ClientID]" value="<?php echo esc_attr( $options['ClientID'] ); ?>" />
					</td>
				</tr>

				<tr valign="top">
					<th scope="row">Client Secret</th>
					<td>
						<input type="text" name="wcqbi_qbo[ClientSecret]" value="<?php echo esc_attr( $options['ClientSecret'] ); ?>" />
					</td>
				</tr>

				<tr valign="top">
					<th scope="row">Base URL</th>
					<td>
						<input type="text" name="wcqbi_qbo[baseUrl]" value="<?php echo esc_attr( $options['baseUrl'] ); ?>" />
					</td>
				</tr>
			</table>

			<input type="hidden" name="wcqbi_qbo[auth_mode]" value="oauth2" />
			<input type="hidden" name="wcqbi_qbo[RedirectURI]" value="<?php echo esc_url( get_site_url( null, '/wp-admin/admin.php?page=quickbooks-connect' ) ); ?>" />
			<input type="hidden" name="wcqbi_qbo[scope]" value="com.intuit.quickbooks.accounting" />
			<?php submit_button(); ?>
		</form>

		<form action="/wp-admin/admin-post.php" method="post">
			<input type="hidden" name="action" value="wcqbi_qbo_connect">
			<input type="hidden" name="wcqbi_connection_status" value="<?php echo esc_attr( get_option( 'wcqbi_connection_status', false ) ); ?>">
			<button type="submit" class="button button-primary">Connect to QuickBooks</button>
		</form>
	</div>
	<?php
}
