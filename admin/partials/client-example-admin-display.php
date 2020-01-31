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
$auth_url = $this->manager->get_authorization_url( null, admin_url( '?page=client-example' ) );
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->

<h1>Client Example plugin</h1>

<p>This page shows you debugging data. <strong>Keep in mind that this data is sensitive, do not share it without cleaning up the token values first.</strong></p>

<h2>Site Registration / Blog token</h2>
<hr />
<p>This is the first step and prerequisite for any Jetpack connection. "Registering" the site basically means creating a blog token,
	and "registering" the site with wpcom. It is required before any user authentication can proceed.</p>
<strong>Current site registration status: </strong>
<?php if ( ! $blog_token ) : ?>
	<p>Unregistered :(</p>
	<form action="/wp-admin/admin-post.php" method="post">
		<input type="hidden" name="action" value="register_site">
		<?php wp_nonce_field( 'register-site' ); ?>
		<input type="submit" value="Register this site">
	</form>
<?php else: ?>
	<p>Woohoo! This site is registered with wpcom, and has a functioning blog token for authenticated site requests!
		You should be able to see the token value in the Private Options dump lower in this page.</p>

	<form action="/wp-admin/admin-post.php" method="post">
		<strong>Disconnect / deregister</strong>
		<p>Now that the site is registered, you may de-register (disconnect) it! Be weary though,
			it will also delete any and all user tokens with it, since those rely on the blog token too!</p>
		<input type="hidden" name="action" value="disconnect_site">
		<?php wp_nonce_field( 'disconnect-site' ); ?>
		<input type="submit" value="Disconnect site">
	</form>
<?php endif; ?>
<br>
<h2>User auth / user token creation.</h2>
<hr />
<?php if ( $blog_token ) : ?>
	<p>Now that we have a registered site, we can authenticate users!</p>
<?php else: ?>
	<p>Wait! Before we do any user authentication, we need to register the site above! You can try it if you want, but you'll get some errors :)</p>
<?php endif; ?>

<?php if ( $user_token ) : ?>
	<form action="/wp-admin/admin-post.php" method="post">
		<p>Awesome! You are connected as an authenticated user! You even have your own token! much wow. Now you may destroy it :)</p>
		<p><strong>Unless...</strong> you are also the "master user", in which case it will fail (we could use some error handling instead)</p>
		<input type="hidden" name="action" value="disconnect_user">
		<?php wp_nonce_field( 'disconnect-user' ); ?>
		<input type="submit" value="Disconnect current user">
	</form>
<?php else: ?>
	<form action="/wp-admin/admin-post.php" method="post">
		<input type="hidden" name="action" value="connect_user">
		<?php wp_nonce_field( 'connect-user' ); ?>
		<input type="submit" value="Connect current user">
	</form>
<?php endif; ?>

<br>
<h2>Jetpack options dump</h2>
<hr />

<p>When a Jetpack-powered site is registered, it should be assigned an ID, which should be present in the list below.</p>

<pre>
<?php print_r( get_option( 'jetpack_options', array() ) ); ?>
</pre>

<h2>Jetpack private options dump</h2>
<p>Even though Jetpack is not installed on your site, the dump below should display the blog_token for your site if you have pressed the Register button. </p>
<pre>
<?php print_r( get_option( 'jetpack_private_options', array() ) ); ?>
</pre>

<iframe class="jp-jetpack-connect__iframe" /></iframe>
<script type="application/javascript">
jQuery( function( $ ) {
	var authorize_url = <?php echo wp_json_encode( $auth_url ); ?>;
	$( '.jp-jetpack-connect__iframe' ).attr( 'src', authorize_url );
} );
</script>
