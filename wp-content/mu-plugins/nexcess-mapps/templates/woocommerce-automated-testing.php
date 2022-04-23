<?php

/**
 * The WooCommerce Automated Testing tab of the MAPPS Dashboard.
 *
 * @var array<string,array> $results The latest results, grouped by test name.
 */

use Nexcess\MAPPS\Integrations\WooCommerceAutomatedTesting;

?>

<div class="mapps-layout-fluid">
	<div class="mapps-primary">
		<p>
		<?php
			echo wp_kses_post( sprintf(
				/* Translators: %1$s is the help article. */
				__( 'In order to make sure your WooCommerce store is running in tip-top shape, we run a series of tests on your site nightly. <a href="%1$s">Read more about the WooCommerce Automated Testing Feature</a>.', 'nexcess-mapps' ),
				esc_url( 'https://help.nexcess.net/79236-woocommerce/woocommerce-automated-testing' )
			) );
			?>
		</p>

		<?php if ( empty( $results ) ) : ?>

			<p><?php esc_html_e( 'We haven\'t had a chance to run checks quite yet, please check back tomorrow!', 'nexcess-mapps' ); ?></p>

		<?php else : ?>

			<p><?php esc_html_e( 'Here are the results of the latest runs:', 'nexcess-mapps' ); ?></p>

			<table class="widefat striped mapps-w-auto">
				<thead>
					<tr>
						<th><?php echo esc_html_x( 'Test Name', 'table header', 'nexcess-mapps' ); ?></th>
						<?php foreach ( (array) current( $results ) as $date => $result ) : ?>
							<?php $date = new \DateTimeImmutable( $date ); ?>
							<th>
								<time datetime="<?php echo esc_attr( $date->format( 'c' ) ); ?>" title="<?php echo esc_attr( $date->format( 'c' ) ); ?>">
									<?php echo esc_html( $date->format( 'n/j' ) ); ?>
								</time>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( (array) $results as $check => $result ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $check ); ?></th>
							<?php foreach ( (array) $result as $entry ) : ?>
								<td><?php echo wp_kses_post( WooCommerceAutomatedTesting::getStatusIcon( $entry['result'] ) ); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

		<?php endif; ?>
	</div>
</div>
