<?php
/**
 * Provide a admin area view with a simple ui for the plugin.
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
<div class="simple-ui">

	<h1 class="simple-ui">Jetpack Connection Package</h1>
	<h2>Calypso Auth Flow</h2>
	<br>

	<?php
		$text = $this->connection_admin->get_default_text();

		// Show the post action button if a connection action needs to be completed.
		if ( $this->connection_admin->get_post_action() ) {
			$action = $this->connection_admin->get_post_action();
			?>

			<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
				<input type="hidden" name="action" value="<?php echo $action; ?>">
				<?php wp_nonce_field( $action ); ?>
				<input type="submit" value="<?php echo $text; ?>" class="button button-primary">
			</form>

		<?php
		// The connection is complete; show some text.
		} else {
		?>
			<p><?php echo $text; ?></p>
		<?php
		}
	//}

	// Show the disconnect user button if possible.
	if ( $this->connection_admin->get_disconnect_user_action() ) {
		$action = $this->connection_admin->get_disconnect_user_action();
		?>

		<br>
		<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo $action; ?>">
			<?php wp_nonce_field( $action ); ?>
			<input type="submit" value="Disconnect User" class="button">
		</form>

	<?php
	}

	// Showt the disconnect site user button if possible.
	if ( $this->connection_admin->get_disconnect_site_action() ) {
		$action = $this->connection_admin->get_disconnect_site_action();
		?>

		<br>
		<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo $action; ?>">
			<?php wp_nonce_field( $action ); ?>
			<input type="submit" value="Disconnect Site" class="button">
		</form>

	<?php
	}
	?>

</div>
