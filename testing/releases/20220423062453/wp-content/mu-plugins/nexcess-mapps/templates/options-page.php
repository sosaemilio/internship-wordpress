<?php
/**
 * The options tab of the MAPPS Dashboard.
 *
 * @var string $fields The fields to display.
 */

?>
<div class="mapps-primary mapps-options-page-wrap">
	<form method="POST" action="<?php echo esc_attr( admin_url( 'admin.php?page=nexcess-mapps#settings' ) ); ?>">
		<?php echo $fields; // phpcs:ignore ?>
		<?php wp_nonce_field( 'mapps-options-save', '_mapps-options-save-nonce' ); ?>
		<?php submit_button( esc_attr__( 'Save Changes', 'nexcess-mapps' ), 'primary', 'mapps-options-submit' ); ?>
	</form>
</div>
