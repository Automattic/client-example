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
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->

<h1>Client Example plugin</h1>

<p> This page shows you debugging data. </p>

<h2>Registration</h2>

<form action="/wp-admin/admin-post.php" method="post">
	<input type="hidden" name="action" value="register_site">
	<?php wp_nonce_field( 'register-site' ); ?>
	<input type="submit" value="Register this site">
</form>

<h2>Jetpack options dump</h2>

<p>When a Jetpack-powered site is registered, it should be assigned an ID, which should be present in the list below.</p>

<pre>
<?php print_r( get_option( 'jetpack_options', array() ) ); ?>
</pre>

<h2>Jetpack private options dump</h2>
								<p>Even though Jetpack is not installed on your site, the dump below should display the blog_token for your site if you have pressed the Register button. Be careful, this information is sensitive and should not be </p>
<pre>
<?php print_r( get_option( 'jetpack_private_options', array() ) ); ?>
</pre>
