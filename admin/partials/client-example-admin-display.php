<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://automattic.com
 * @since      1.0.0
 *
 * @package    Client_Example
 * @subpackage Client_Example/admin/partials
 */

$user_token = $this->manager->get_access_token( get_current_user_id() );
$blog_token = $this->manager->get_access_token();
$is_plugin_enabled = $this->manager->is_plugin_enabled();
add_filter( 'jetpack_use_iframe_authorization_flow', '__return_true' );
$auth_url = $this->manager->get_authorization_url( null, admin_url( '?page=client-example' ) );
remove_filter( 'jetpack_use_iframe_authorization_flow', '__return_true' );
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->

<h1>Client Example plugin</h1>

<p>This page shows you debugging data. <strong>Keep in mind that this data is sensitive, do not share it without cleaning up the token values first.</strong></p>

<h2>Plugins requesting the connection (except explicitly disconnected)</h2>
<hr />
<ul>
	<?php foreach ( $this->manager->get_connected_plugins() as $plugin_slug => $plugin_data ) : ?>
		<li><?php echo esc_html( $plugin_data['name'] ); ?></li>
	<?php endforeach ?>
</ul>

<br>
<h2>Site Registration / Blog token</h2>
<hr />
<p>This is the first step and prerequisite for any Jetpack connection. "Registering" the site basically means creating a blog token,
	and "registering" the site with wpcom. It is required before any user authentication can proceed.</p>
<strong>Current site registration status: </strong>

<?php if ( ! $blog_token ) : ?>
	<p>Unregistered (Hard Disconnect) :(</p>
	<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
		<input type="hidden" name="action" value="register_site">
		<?php wp_nonce_field( 'register-site' ); ?>
		<input type="submit" value="Register this site" class="button button-primary">
	</form>
<?php elseif ( ! $is_plugin_enabled ) : ?>
	<p>Softly Disconnected ¯\_(ツ)_/¯</p>
	<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
		<input type="hidden" name="action" value="connect_site_softly">
		<?php wp_nonce_field( 'connect-site-user-initiated' ); ?>
		<input type="submit" value="Reconnect this site" class="button button-primary">
	</form>
<?php else: ?>
	<p>Woohoo! This site is registered with wpcom, and has a functioning blog token for authenticated site requests!
		You should be able to see the token value in the Private Options dump lower in this page.</p>

	<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
		<strong>Disconnect / deregister</strong>
		<p>Now that the site is registered, you may de-register (disconnect) it! Be weary though,
			it will also delete any and all user tokens with it, since those rely on the blog token too!</p>
		<p><em>Soft Disconnect: </em> No tokens are removed, other plugins can still use the connection.</p>
		<p><em>Hard Disconnect: </em> The connection gets severed for all the plugins, tokens are removed.
			To reconnect, you will need to register the website on WordPress.com once again.</p>
		<input type="hidden" name="action" value="disconnect_site">
		<?php wp_nonce_field( 'disconnect-site' ); ?>
		<input type="submit" value="Disconnect site (soft/hard, it depends)" class="button">
		<input type="submit" value="Soft Disconnect" class="button" name="disconnect_soft">
		<input type="submit" value="Hard Disconnect" class="button" name="disconnect_hard">
	</form>
<?php endif; ?>

<br>
<h2>User auth / user token creation.</h2>
<hr />
<?php if ( $blog_token && $is_plugin_enabled ) : ?>
	<p>Now that we have a registered site, we can authenticate users!</p>

	<?php if ( $user_token ) : ?>
		<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
			<p>Awesome! You are connected as an authenticated user! You even have your own token! much wow. Now you may destroy it :)</p>
			<p><strong>Unless...</strong> you are also the "master user", in which case it will fail (we could use some error handling instead)</p>
			<input type="hidden" name="action" value="disconnect_user">
			<?php wp_nonce_field( 'disconnect-user' ); ?>
			<input type="submit" value="Disconnect current user" class="button">
		</form>
	<?php else: ?>
		<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
			<input type="hidden" name="action" value="connect_user">
			<?php wp_nonce_field( 'connect-user' ); ?>
			<input type="submit" value="Authorize current user" class="button button-primary">
			<label for="connect_user">Classic flow through wp.com</label>
		</form>

		<br>
		<p>OR! You can try this fancy in-place authorize flow in an iframe. But remember, you need to register the site first.</p>
		<iframe
				class="jp-jetpack-connect__iframe"
				style="
					width: 100%;
					background: white;
					height: 250px;
					padding-top: 30px;
				"
		/></iframe>
	<?php endif; ?>
<?php else: ?>
	<p><strong>Wait! Before we do any user authentication, we need to register the site above!</strong></p>
<?php endif; ?>

<br>
<h2>Jetpack options dump</h2>
<hr />

<p>When a Jetpack-powered site is registered, it should be assigned an ID, which should be present in the list below.</p>

<pre>
<?php print_r( get_option( 'jetpack_options', array() ) ); ?>
</pre>

<h2>Jetpack private options dump</h2>
<hr />
<p>Even though Jetpack is not installed on your site, the dump below should display the blog_token for your site if you have pressed the Register button. </p>
<pre>
<?php print_r( get_option( 'jetpack_private_options', array() ) ); ?>
</pre>

<h2>Explicitly disconnected plugins</h2>
<hr />
<p>These plugins were explicitly disconnected by calling `$manager->disable_plugin()`.</p>
<pre>
<?php print_r( get_option( 'jetpack_connection_disabled_plugins', array() ) ); ?>
</pre>

<script type="application/javascript">
jQuery( function( $ ) {

	var authorize_url = decodeURIComponent( '<?php echo rawurlencode( (string) $auth_url ); ?>' );
	$( '.jp-jetpack-connect__iframe' ).attr( 'src', authorize_url );

	window.addEventListener('message', (event) => {
		if ( 'close' === event.data ) {
			location.reload(true);
		}
	} );
} );
</script>
