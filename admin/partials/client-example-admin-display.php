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

$token = $this->manager->get_access_token( get_current_user_id() );

?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->

<h1>Client Example plugin</h1>

<p> This page shows you debugging data. <strong>Keep in mind that this data is sensitive, do not share it without cleaning up the token values first.</strong></p>

<h2>Registration</h2>

<form action="/wp-admin/admin-post.php" method="post">
	<input type="hidden" name="action" value="register_site">
	<?php wp_nonce_field( 'register-site' ); ?>
	<input type="submit" value="Register this site">
</form>
<?php if ( $token ) : ?>
<form action="/wp-admin/admin-post.php" method="post">
	<input type="hidden" name="action" value="disconnect_user">
	<?php wp_nonce_field( 'disconnect-user' ); ?>
	<input type="submit" value="Disconnect current user">
</form>
<?php endif; ?>

<h2>Current user token</h2>

<pre>
<?php
print_r( $token ? $token : 'The current user is not connected' );
?>
</pre>

<h2>Jetpack options dump</h2>

<p>When a Jetpack-powered site is registered, it should be assigned an ID, which should be present in the list below.</p>

<pre>
<?php print_r( get_option( 'jetpack_options', array() ) ); ?>
</pre>

<h2>Jetpack private options dump</h2>
								<p>Even though Jetpack is not installed on your site, the dump below should display the blog_token for your site if you have pressed the Register button. </p>
<pre>
<?php print_r( get_option( 'jetpack_private_options', array() ) ); ?>
</pre>
