<?php

namespace StellarWP\PluginFramework\Console\Commands;

use StellarWP\PluginFramework\Console\WPCommand;

/**
 * Commands for managing iThemes licensing.
 */
class iThemesCommand extends WPCommand
{
    /**
     * The name of the option that holds iThemes licenses.
     */
    const OPTION_NAME = 'ithemes-updater-keys';

    /**
     * List current iThemes license keys.
     *
     * @return void
     */
    public function all()
    {
        $licenses = get_option(self::OPTION_NAME, []);

        if (! is_array($licenses)) {
            $this->error(sprintf('Unexpected option value of type %s.', gettype($licenses)), true);
        }

        $rows = [];

        foreach ((array) $licenses as $product => $license) {
            $rows[$product] = [
                'Product'     => $product,
                'License key' => $license,
            ];
        }

        if (empty($rows)) {
            $this->warning('There are no iThemes products currently licensed for this site');
            return;
        }

        $this->table($rows, ['Product', 'License key']);
    }

    /**
     * License an iThemes product.
     *
     * ## OPTIONS
     *
     * <product>
     * : The product to license.
     *
     * <license>
     * : The license key for this product.
     *
     * ## EXAMPLES
     *
     *   # License BackupBuddy
     *   $ wp stellarwp ithemes license backupbuddy abc123
     *
     * @synopsis <product> <license>
     * @alias add
     *
     * @param Array<int,string> $args Positional arguments.
     *
     * @return void
     */
    public function license(array $args)
    {
        list($product, $license) = $args;

        $licenses = get_option(self::OPTION_NAME, []);

        if (! is_array($licenses)) {
            $licenses = [];
        }

        $licenses[$product] = $license;

        update_option(self::OPTION_NAME, $licenses, false);

        $this->success(sprintf('License key for "%s" has been saved successfully!', $product));
    }

    /**
     * Remove licensing for one or more iThemes products.
     *
     * ## OPTIONS
     *
     * <product>...
     * : The product(s) to unlicense.
     *
     * ## EXAMPLES
     *
     *   # Unlicense BackupBuddy
     *   $ wp stellarwp ithemes unlicense backupbuddy
     *
     * @synopsis <product>...
     *
     * @param Array<int,string> $args Positional arguments.
     *
     * @return void
     */
    public function unlicense(array $args)
    {
        $licenses = get_option(self::OPTION_NAME, []);
        $count    = 0;

        if (! is_array($licenses)) {
            $licenses = [];
        }

        foreach ($args as $product) {
            if (isset($licenses[$product])) {
                unset($licenses[$product]);
                $this->log(sprintf('Removed license for "%s"', $product));
                $count++;
            } else {
                $this->log(sprintf('No license was found for "%s", skipping', $product));
            }
        }

        update_option(self::OPTION_NAME, $licenses);

        if ($count) {
            $this->success(sprintf('%d license(s) were removed!', $count));
        } else {
            $this->warning('No licenses were removed.');
        }
    }
}
