<?php

/**
 * The WP QuickStart welcome screen.
 *
 * @see \Nexcess\MAPPS\Integrations\QuickStart\renderWelcomePanel()
 * @see wp_welcome_panel()
 *
 * @var string   $title   The main title to use when welcoming a new customer
 * @var string[] $columns An array of one or more columns' contents.
 */

// Don't render anything if we don't have a title or columns.
if ( empty( $title ) && empty( $columns ) ) {
	return;
}

?>

<div class="welcome-panel-content mapps-welcome-panel">
	<h2><?php echo esc_html( $title ); ?></h2>

	<div class="welcome-panel-column-container">
		<?php foreach ( $columns as $column ) : ?>
			<div class="welcome-panel-column">
				<?php echo wp_kses_post( $column ); ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
