<?php

/**
 * The Performance Monitor dashboard widget.
 *
 * @var string  $class_prefix                       Prefix all CSS classes should have
 * @var string  $overview_performance_progress_ring SVG object representing Performance score.
 * @var int     $overview_performance_score         Score value from 0 to 100.
 * @var string  $overview_performance_warning       SVG object reprensenting a warning icon.
 * @var int     $overview_load_score                Average load time in miliseconds.
 * @var array[] $insights                           Insight items, each with a "title" and "date" keys.
 * @var string  $more_title                         The "More" link text.
 * @var string  $more_url                           The "More" link href value.
 */

$allowed_svg_tags = [
	'svg'    => [
		'class'           => true,
		'aria-hidden'     => true,
		'aria-labelledby' => true,
		'role'            => true,
		'xmlns'           => true,
		'width'           => true,
		'height'          => true,
		'viewbox'         => true,
	],
	'circle' => [
		'cx'                => true,
		'cy'                => true,
		'r'                 => true,
		'fill'              => true,
		'stroke'            => true,
		'stroke-width'      => true,
		'stroke-dasharray'  => true,
		'stroke-dashoffset' => true,
	],
	'g'      => [ 'fill' => true ],
	'title'  => [ 'title' => true ],
	'path'   => [
		'd'    => true,
		'fill' => true,
	],
];

$performance_monitor_url = menu_page_url( 'mapps-performance-monitor', false );
?>

<div class="pm_widget_widget">
	<div class="pm_widget_overview">
		<div class="pm_widget_overview_title">
			<?php esc_html_e( 'Overview', 'nexcess-mapps' ); ?>
		</div>
		<div class="pm_widget_overview_items">
			<div class="pm_widget_overview_item">
				<div class="pm_widget_overview_item_title">
					<?php esc_html_e( 'Performance score', 'nexcess-mapps' ); ?>
				</div>
				<div class="pm_widget_overview_item_value">
					<?php
					echo wp_kses( $overview_performance_progress_ring, $allowed_svg_tags );
					echo intval( $overview_performance_score );
					echo wp_kses( $overview_performance_warning, $allowed_svg_tags );
					?>
				</div>
			</div>
			<div class="pm_widget_overview_item">
				<div class="pm_widget_overview_item_title">
					<?php esc_html_e( 'Avg. load time', 'nexcess-mapps' ); ?>
				</div>
				<div class="pm_widget_overview_item_value"><?php echo intval( $overview_load_score ); ?> ms</div>
			</div>
		</div>
	</div>

	<div class="pm_widget_insights">
		<div class="pm_widget_insights_title"><?php esc_html_e( 'Recent Insights', 'nexcess-mapps' ); ?></div>
		<?php if ( empty( $insights ) ) : ?>
			<div class="pm_widget_no_insights">
				<?php esc_html_e( 'There are no recent insights to be displayed', 'nexcess-mapps' ); ?>
			</div>
		<?php else : ?>
			<?php foreach ( $insights as $insight ) : ?>
			<div class="pm_widget_insight">
				<div class="pm_widget_insight_title"><?php echo esc_html( $insight['title'] ); ?></div>
				<div class="pm_widget_insight_date"><?php echo esc_html( $insight['date'] ); ?></div>
			</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<a href="<?php echo esc_url( $performance_monitor_url ); ?>" class="pm_widget_more">
		<?php echo esc_html_e( 'More Details', 'nexcess-mapps' ); ?>
	</a>
</div>
