<?php

namespace StellarWP\PluginFramework\Contracts;

/**
 * Indicates that the implementing class leverages the StellarWP\AdminNotice library.
 *
 * This is really only necessary to ensure the necessary listeners are queued for dismissible notices.
 */
interface PublishesAdminNotices
{
}
