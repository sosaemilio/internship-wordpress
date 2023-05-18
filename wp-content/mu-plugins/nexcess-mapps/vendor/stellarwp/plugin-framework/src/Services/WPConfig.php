<?php

namespace StellarWP\PluginFramework\Services;

use StellarWP\PluginFramework\Exceptions\WPConfigException;
use WPConfigTransformer;

/**
 * A clean way of managing a site's wp-config.php file.
 *
 * Under the hood, this uses the wp-cli/wp-config-transformer class.
 */
class WPConfig
{
    /**
     * The underlying WPConfigTransformer from WP-CLI.
     *
     * @var WPConfigTransformer
     */
    protected $transformer;

    /**
     * Indicate that the service is currently trying to repair the wp-config.php
     *
     * @var bool
     */
    private $repairingConfig = false;

    /**
     * Create a new instance of the WPConfig service.
     *
     * @param WPConfigTransformer $transformer
     */
    public function __construct(WPConfigTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * Add or update an existing configuration.
     *
     * This is a wrapper around WPConfigTransformer::update() with better error handling.
     *
     * @param string               $type    The type of configuration.
     * @param string               $name    The configuration name.
     * @param scalar               $value   The configuration value.
     * @param Array<string,scalar> $options Optional. Adjustments to write behavior. Default is empty.
     *
     * @throws WPConfigException If the configuration cannot be written.
     *
     * @return $this
     */
    public function setConfig($type, $name, $value, array $options = [])
    {
        try {
            $this->transformer->update($type, $name, (string) $value, $options);
        } catch (\Exception $e) {
            if ('Unable to locate placement anchor.' !== $e->getMessage()) {
                throw new WPConfigException($e->getMessage(), $e->getCode(), $e);
            }

            // If the problem was a missing anchor, try to remedy the situation.
            try {
                if ($this->repairingConfig) {
                    throw new WPConfigException('Recursive repair attempt detected!');
                }

                $this->repairingConfig = true;
                $this->restoreMissingAnchor();
            } catch (\Exception $e) {
                throw new WPConfigException(sprintf(
                    'Unable to add missing anchor to wp-config.php file: %s',
                    $e->getMessage()
                ), $e->getCode(), $e);
            }

            $this->setConfig($type, $name, $value, $options);
        }

        // Reset the flag if it's been tripped.
        $this->repairingConfig = false;

        return $this;
    }

    /**
     * Determine whether or not the given constant exists in wp-config.php.
     *
     * @param string $constant The constant name.
     *
     * @return bool True if the constant is defined, false otherwise.
     */
    public function hasConstant($constant)
    {
        try {
            $exists = $this->transformer->exists('constant', $constant);
        } catch (\Exception $e) {
            $exists = false;
        }

        return $exists;
    }

    /**
     * Add (or update) the given constant in wp-config.php.
     *
     * @param string $constant The constant name.
     * @param mixed  $value    The constant value.
     *
     * @throws WPConfigException If the configuration cannot be written.
     *
     * @return $this
     */
    public function setConstant($constant, $value)
    {
        if (! is_scalar($value) && ! is_array($value)) {
            throw new WPConfigException(
                'Only scalar values and arrays may be assigned to constants in PHP; see '
                . 'https://www.php.net/manual/en/language.constants.syntax.php'
            );
        }

        if (! is_scalar($value) && version_compare(PHP_VERSION, '7.0', '<')) {
            throw new WPConfigException(
                'PHP < 7.0 cannot define constants with non-scalar values; see '
                . 'https://www.php.net/manual/en/migration70.new-features.php#migration70.new-features.define-array'
            );
        }

        $options = [
            'add'       => true,
            'raw'       => ! is_string($value),
            'normalize' => true,
        ];

        if (is_array($value)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
            $value = var_export($value, true);
        }

        return $this->setConfig('constant', $constant, $value, $options);
    }

    /**
     * Remove the given constant from wp-config.php.
     *
     * @param string $constant The constant name.
     *
     * @throws WPConfigException If the configuration cannot be written.
     *
     * @return $this
     */
    public function removeConstant($constant)
    {
        try {
            $this->transformer->remove('constant', $constant);
        } catch (\Exception $e) {
            throw new WPConfigException($e->getMessage(), $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Determine whether or not the given variable exists in wp-config.php.
     *
     * @param string $variable The variable name.
     *
     * @return bool True if the variable is defined, false otherwise.
     */
    public function hasVariable($variable)
    {
        try {
            $exists = $this->transformer->exists('variable', $variable);
        } catch (\Exception $e) {
            $exists = false;
        }

        return $exists;
    }

    /**
     * Add (or update) the given variable in wp-config.php.
     *
     * @param string $variable The variable name.
     * @param mixed  $value    The variable value.
     *
     * @throws WPConfigException If the configuration cannot be written.
     *
     * @return $this
     */
    public function setVariable($variable, $value)
    {
        $options = [
            'add'       => true,
            'raw'       => ! is_string($value),
            'normalize' => true,
        ];

        if (! is_scalar($value)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
            $value = var_export($value, true);
        }

        return $this->setConfig('variable', $variable, $value, $options);
    }

    /**
     * Remove the given variable from wp-config.php.
     *
     * @param string $variable The variable name.
     *
     * @throws WPConfigException If the configuration cannot be written.
     *
     * @return $this
     */
    public function removeVariable($variable)
    {
        try {
            $this->transformer->remove('variable', $variable);
        } catch (\Exception $e) {
            throw new WPConfigException($e->getMessage(), $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Attempt to restore a missing anchor in the wp-config.php file.
     *
     * The WPConfigTransformer class relies on an "anchor", a particular string, in order to adjust
     * configuration; by default, the anchor is a newline character followed by the comment
     * "/* That's all, stop editing!".
     *
     * The transformer will not add anything after the anchor, as this is reserved for defining
     * ABSPATH and loading the "wp-settings.php" file.
     *
     * @throws WPConfigException If the anchor cannot be added.
     *
     * @return bool True if the anchor was added, false if it already exists.
     */
    protected function restoreMissingAnchor()
    {
        $path   = ABSPATH . 'wp-config.php';
        $anchor = PHP_EOL . '/* That\'s all, stop editing!';
        $insert = $anchor . ' Happy publishing. */' . PHP_EOL;

        try {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $config = (string) file_get_contents($path);

            // The anchor already exists, nothing to do.
            if (false !== mb_strpos($config, $anchor)) {
                return false;
            }

            // Find the ABSPATH definition.
            $pattern = '/(?:if\s*\(\s*!\s*)?(?:defined\(.+\))?\s*\{?(?:\|\|)?\s*define\(\s*["\']ABSPATH["\']/';

            if (! preg_match($pattern, $config, $abspath)) {
                throw new WPConfigException('Unable to find the ABSPATH definition');
            }

            // Insert the anchor just before the ABSPATH definition.
            $config = str_replace($abspath[0], $insert . $abspath[0], $config);

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
            if (false === file_put_contents($path, $config)) {
                throw new WPConfigException('Unable to write to wp-config.php file');
            }
        } catch (\Exception $e) {
            throw new WPConfigException($e->getMessage(), $e->getCode(), $e);
        }

        return true;
    }
}
