<?php

namespace Nexcess\MAPPS\Concerns;

use Nexcess\MAPPS\Support\AdminNotice;

trait HasDashboardNotices {

	/**
	 * Add a notice to the top of the MAPPS dashboard.
	 *
	 * @param \Nexcess\MAPPS\Support\AdminNotice $notice   The AdminNotice to render.
	 * @param int                                $priority Optional. The priority at which to
	 *                                                     render the notice. Default is 10.
	 */
	protected function addDashboardNotice( AdminNotice $notice, $priority = 10 ) {
		if ( $notice->userHasDismissedNotice() || $notice->noticeIsDelayed() ) {
			return;
		}

		$notice->setInline( true );

		add_action( 'Nexcess\\MAPPS\\dashboard_notices', [ $notice, 'output' ], $priority );
	}

	/**
	 * Add a notice to the top of *all* admin pages.
	 *
	 * @param \Nexcess\MAPPS\Support\AdminNotice $notice   The AdminNotice to render.
	 * @param int                                $priority Optional. The priority at which to
	 *                                                     render the notice. Default is 10.
	 */
	protected function addGlobalNotice( AdminNotice $notice, $priority = 10 ) {
		if ( $notice->userHasDismissedNotice() || $notice->noticeIsDelayed() ) {
			return;
		}

		$notice->setInline( false );

		add_action( 'admin_notices', [ $notice, 'output' ], $priority );
	}
}
