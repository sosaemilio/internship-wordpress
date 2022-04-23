<?php

/**
 * A generic widget for rendering icons widget icon links in a grid.
 *
 * @var mixed[] $icon_links An array of items with 'icon', 'href', and 'text' keys, plus optionally a '_blank' boolean
 */

// Output any passed in HTML.
if ( isset( $before ) ) {
	echo wp_kses_post( $before );
}
?>
<ul class="mapps-icon-link-list <?php printf( '%1$s', isset( $list_class ) ? esc_attr( $list_class ) : '' ); ?>">
	<?php
	foreach ( $icon_links as $icon_link ) {
		// If the the icon link contains /wp-admin in it, then we want to
		// strip it out so we can pass this to admin_url() to make the correct
		// admin link. This is a bit of a workaround to avoid rewriting the api.
		if ( false !== strpos( $icon_link['href'], '/wp-admin/' ) ) {
			$link_parts = explode( '/wp-admin/', $icon_link['href'], 2 );

			if ( isset( $link_parts[1] ) ) {
				$icon_link['href'] = admin_url( $link_parts[1] );
			}
		}

		printf(
			'<li>
				<a href="%1$s" %2$s>
					<div class="dashicons-before %3$s"></div>
					<span>%4$s</span>
				</a>
			</li>',
			esc_url_raw( $icon_link['href'] ),
			( isset( $icon_link['_blank'] ) && $icon_link['_blank'] ) ? 'target="_blank"' : '',
			esc_attr( $icon_link['icon'] ),
			esc_html( $icon_link['text'] )
		);
	}
	?>
</ul>

<?php
// Output any passed in HTML.
if ( isset( $after ) ) {
	echo wp_kses_post( $after );
}
