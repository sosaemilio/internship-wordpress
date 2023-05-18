/**
 * Start post grid tab widget script
 */

 ( function( $, elementor ) {

	'use strict';

	var widgetStaticPostTab = function( $scope, $ ) {

		var $postGridTab = $scope.find( '.bdt-static-grid-tab' ),
			gridTab      = $postGridTab.find('.gridtab');

		if ( ! $postGridTab.length ) {
			return;
		}

		$(gridTab).gridtab($postGridTab.data('settings'));

	};


	jQuery(window).on('elementor/frontend/init', function() {
		elementorFrontend.hooks.addAction( 'frontend/element_ready/bdt-static-grid-tab.default', widgetStaticPostTab );
	});

}( jQuery, window.elementorFrontend ) );

/**
 * End post grid tab widget script
 */

 