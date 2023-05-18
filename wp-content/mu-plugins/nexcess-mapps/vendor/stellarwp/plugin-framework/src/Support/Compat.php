<?php

/**
 * Add useful functions that have yet to ship in WordPress core.
 *
 * Note that this file is intentionally in the global namespace.
 */

/**
 * WordPress may never define time constants based in minutes, but this makes expressing time
 * much cleaner.
 *
 * Borrowed from https://github.com/stevegrunwell/time-constants
 */
if (! defined('HOUR_IN_MINUTES')) {
    define('HOUR_IN_MINUTES', 60);
}

if (! defined('DAY_IN_MINUTES')) {
    define('DAY_IN_MINUTES', 24 * HOUR_IN_MINUTES);
}
