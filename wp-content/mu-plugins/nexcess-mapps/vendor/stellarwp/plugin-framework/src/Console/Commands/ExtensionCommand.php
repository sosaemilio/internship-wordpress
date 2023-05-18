<?php

namespace StellarWP\PluginFramework\Console\Commands;

use StellarWP\PluginFramework\Console\WPCommand;
use StellarWP\PluginFramework\Exceptions\InstallationException;
use StellarWP\PluginFramework\Exceptions\LicensingException;
use StellarWP\PluginFramework\Services\Nexcess\Extension;
use StellarWP\PluginFramework\Services\Nexcess\MappsApiClient;

/**
 * Install and license plugins and themes from StellarWP partners.
 */
class ExtensionCommand extends WPCommand
{
    /**
     * The MappsApiClient instance.
     *
     * @var MappsApiClient
     */
    protected $client;

    /**
     * Construct a new instance of the command.
     *
     * @param MappsApiClient $client The MappsApiClient instance.
     */
    public function __construct(MappsApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * List all extensions available to the current site.
     *
     * @subcommand list
     *
     * @return void
     */
    public function all()
    {
        /** @var Array<string,Array<string,scalar>> $rows */
        $rows = array_map(function (Extension $extension) {
            return [
                'ID'   => $extension->id,
                'Name' => $extension->name,
                'Slug' => $extension->slug,
                'Type' => $extension->getType(),
                'URL'  => $extension->url,
            ];
        }, $this->client->getAvailableExtensions());

        if (empty($rows)) {
            $this->warning('There are no extensions currently available!');
            return;
        }

        $this->table($rows, ['ID', 'Name', 'Slug', 'Type', 'URL']);
    }

    /**
     * Install and/or license an extension on this site.
     *
     * ## OPTIONS
     *
     * <slug>...
     * : The extension slug(s).
     *
     * [--license]
     * : Automatically attempt to license the extension(s) (when applicable).
     *
     * ## EXAMPLES
     *
     *   # Install and license WP101
     *   $ wp stellarwp install --license wp101
     *
     * @synopsis [--license] <slug>...
     *
     * @param Array<int,scalar>     $args Positional arguments.
     * @param Array<string,?scalar> $opts Options passed to the command.
     *
     * @throws InstallationException If unable to install one or more extensions.
     *
     * @return void
     */
    public function install(array $args, array $opts)
    {
        $extensions = $this->client->getAvailableExtensions();
        $opts       = wp_parse_args($opts, [
            'license' => false,
        ]);
        $hasFailed  = false;

        foreach ($args as $extension_slug) {
            $extension_slug = (string) $extension_slug;

            if (! array_key_exists($extension_slug, $extensions)) {
                $this->warning(sprintf('Extension "%s" could not be found, skipping', $extension_slug));
                continue;
            }

            $extension = $extensions[ $extension_slug ];

            try {
                $this->step(sprintf('Installing %s "%s"...', $extension->getType(), $extension->name));

                foreach ($this->client->getInstallationSteps($extension_slug) as $command) {
                    $this->debug(sprintf('Running `%s`', $command->getShellCommand()));
                    $response = $command->execute();

                    if (! $response->wasSuccessful()) {
                        throw new InstallationException($response->getErrors(), $response->getExitCode());
                    }
                }

                $this->success(sprintf('%s was installed successfully!', $extension->name));
            } catch (\Exception $e) {
                $hasFailed = true;
                $this->error($e->getMessage(), false);
                continue;
            }

            if ($opts['license']) {
                try {
                    $this->license([$extension_slug], []);
                } catch (\Exception $e) {
                    $hasFailed = true;
                    $this->error(sprintf(
                        'An error occurred while trying to license "%s": %s',
                        $extension->name,
                        $e->getMessage()
                    ), false);
                }
            }
        }

        if ($hasFailed) {
            $this->error('One or more extensions could not be fully installed', 2);
        }
    }

    /**
     * Attempt to license an extension.
     *
     * ## OPTIONS
     *
     * <slug>...
     * : The extension slug(s).
     *
     * ## EXAMPLES
     *
     *   # License WP101
     *   $ wp stellarwp license wp101
     *
     * @synopsis <slug>...
     *
     * @param Array<int,scalar>     $args Positional arguments.
     * @param Array<string,?scalar> $opts Options passed to the command.
     *
     * @throws LicensingException If unable to license one or more extensions.
     *
     * @return void
     */
    public function license($args, $opts)
    {
        $extensions = $this->client->getAvailableExtensions();
        $hasFailed  = false;

        foreach ($args as $extension_slug) {
            $extension_slug = (string) $extension_slug;

            if (! array_key_exists($extension_slug, $extensions)) {
                $this->warning(sprintf('Extension "%s" could not be found, skipping', $extension_slug));
                continue;
            }

            $extension = $extensions[ $extension_slug ];

            try {
                $this->step(sprintf('Licensing %s "%s"...', $extension->getType(), $extension->name));

                if (! $extension->has_licensing) {
                    $this->line(sprintf('%s does not require licensing, skipping', $extension->name));
                    continue;
                }

                foreach ($this->client->getLicensingSteps($extension_slug) as $command) {
                    $this->debug(sprintf('Running `%s`', $command->getShellCommand()));
                    $response = $command->execute();

                    if (! $response->wasSuccessful()) {
                        throw new LicensingException(sprintf(
                            'An error occurred licensing %s: %s',
                            $extension->name,
                            $response->getErrors()
                        ));
                    }
                }

                $this->success(sprintf('%s was licensed successfully!', $extension->name));
            } catch (\Exception $e) {
                $hasFailed = true;
                $this->error($e->getMessage(), false);
                continue;
            }
        }

        if ($hasFailed) {
            $this->error('Licensing of one or more extensions has failed', 2);
        }
    }
}
