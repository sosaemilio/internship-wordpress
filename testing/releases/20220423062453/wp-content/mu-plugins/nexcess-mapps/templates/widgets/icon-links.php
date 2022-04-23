<?php

/**
 * Renders a widget with an icon link list inside.
 *
 * @var mixed[] $icon_links An array of items with 'icon', 'href', and 'text' keys, plus optionally a '_blank' boolean
 */

?>

<nav class="nx-widget-nav-list">
	<?php $this->renderTemplate( 'icon-link-list', compact( 'icon_links' ) ); ?>
</nav>
