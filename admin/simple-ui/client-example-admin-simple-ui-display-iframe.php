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
	<h2>Iframe Auth Flow</h2>
	<br>

	<?php
	// Show the user authorization iframe if it's time for user authorization.
	if ( $this->connection_admin->get_iframe_url() ) {
		$iframe_url = $this->connection_admin->get_iframe_url();
		?>

		<iframe
			class="jp-jetpack-connect__iframe"
				style="
					width: 100%;
					background: white;
					height: 250px;
					padding-top: 30px;
					"
		/></iframe>

		<script type="application/javascript">
			jQuery( function( $ ) {
				var authorize_url = decodeURIComponent( '<?php echo rawurlencode( (string) $iframe_url ); ?>' );
				$( '.jp-jetpack-connect__iframe' ).attr( 'src', authorize_url );

					window.addEventListener('message', (event) => {
					if ( 'close' === event.data ) {
						location.reload(true);
					}
				} );
			} );
		</script>

	<?php
	} else {
		$text = $this->connection_admin->get_default_text();

		// Show the post action button if registration needs to be completed.
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
	}

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
