<?php

namespace StellarWP\PluginFramework\Console\Commands;

use StellarWP\PluginFramework\Console\WPCommand;
use WP_Rewrite;

/**
 * Commands to handle the initial setup of a site.
 */
abstract class Setup extends WPCommand
{
    /**
     * The default permalink structure to use for sites during setup.
     */
    const DEFAULT_PERMALINK_STRUCTURE = '/%postname%/';

    /**
     * Run the initial setup of the site.
     *
     * ## OPTIONS
     *
     * [--provision]
     * : Signal that this is being run as part of the initial site provisioning.
     *
     * @synopsis [--provision]
     *
     * @param Array<int,scalar>     $args    Positional arguments.
     * @param Array<string,?scalar> $options Options passed to the command.
     *
     * @return void
     */
    abstract public function __invoke(array $args, array $options);

    /**
     * When the command is being run with `--provision`, ensure logs are written someplace accessible.
     *
     * @return $this
     */
    protected function enableProvisioningLogs()
    {
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput
        $dir = ! empty($_SERVER['HOME']) && is_writable(strval($_SERVER['HOME']))
            ? realpath(strval(wp_unslash($_SERVER['HOME'])))
            : WP_CONTENT_DIR;
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput

        $log = sprintf('%s/provision.log', untrailingslashit($dir));

        // phpcs:ignore WordPress.PHP.IniSet.Risky
        if (false !== ini_set('error_log', $log)) {
            $this->success(sprintf('Provisioning logs will be written to %s', $log));
        }

        return $this;
    }

    /**
     * Set a default permalink structure for the site.
     *
     * @return $this
     */
    protected function setDefaultPermalinkStructure()
    {
        /** @var WP_Rewrite $wp_rewrite */
        global $wp_rewrite;

        $this->step('Ensuring a default permalink structure is set');

        if (! $wp_rewrite->using_permalinks()) {
            $wp_rewrite->set_permalink_structure(static::DEFAULT_PERMALINK_STRUCTURE);
            $this->success('Default permalink structure set');
        } else {
            $this->success('Existing permalink structure detected');
        }

        return $this;
    }

    /**
     * Update WordPress core, plugins, and themes.
     *
     * @return $this
     */
    protected function updateWordPress()
    {
        $this->step('Ensuring WordPress core and all existing plugins and themes are up-to-date');
        $this->wp('core update');
        $this->wp('core update-db', [
            'launch' => true,
        ]);
        $this->wp('plugin update --all --format=summary');
        $this->wp('theme update --all --format=summary');

        return $this;
    }
}
