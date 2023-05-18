<?php

/**
 * A clean way of managing a site's wp-config.php file.
 */

namespace Nexcess\MAPPS\Services;

use Nexcess\Vendor\WPConfigTransformer;
use StellarWP\PluginFramework\Exceptions\WPConfigException;

class WPConfig {

	/**
	 * @var \Nexcess\Vendor\WPConfigTransformer
	 */
	protected $transformer;

	/**
	 * Create a new instance of the WpConfig service.
	 *
	 * @param \Nexcess\Vendor\WPConfigTransformer $transformer
	 */
	public function __construct( WPConfigTransformer $transformer ) {
		$this->transformer = $transformer;
	}

	/**
	 * Add or update an existing configuration.
	 *
	 * This is a wrapper around WPConfigTransformer::update() with better error handling.
	 *
	 * @param string  $type    The type of configuration.
	 * @param string  $name    The configuration name.
	 * @param string  $value   The configuration value.
	 * @param mixed[] $options Optional. Adjustments to write behavior. Default is empty.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\WPConfigException If the configuration cannot be written.
	 */
	public function setConfig( $type, $name, $value, array $options = [] ) {
		try {
			$this->transformer->update( $type, $name, (string) $value, $options );
		} catch ( \Exception $e ) {
			if ( 'Unable to locate placement anchor.' !== $e->getMessage() ) {
				throw new WPConfigException( $e->getMessage(), $e->getCode(), $e );
			}

			// If the problem was a missing anchor, try to remedy the situation.
			try {
				$this->restoreMissingAnchor();
			} catch ( \Exception $e ) {
				throw new WPConfigException( sprintf(
					'Unable to add missing anchor to wp-config.php file: %s',
					$e->getMessage()
				), $e->getCode(), $e );
			}

			$this->setConfig( $type, $name, $value, $options );
		}
	}

	/**
	 * Determine whether or not the given constant exists in wp-config.php.
	 *
	 * @param string $constant The constant name.
	 *
	 * @return bool True if the constant is defined, false otherwise.
	 */
	public function hasConstant( $constant ) {
		try {
			$exists = $this->transformer->exists( 'constant', $constant );
		} catch ( \Exception $e ) {
			$exists = false;
		}

		return $exists;
	}

	/**
	 * Retrieve the constant value in wp-config.php.
	 *
	 * @param string $constant The constant name.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\WPConfigException If the configuration cannot be written.
	 *
	 * @return mixed Constant value
	 */
	public function getConstant( $constant ) {
		try {
			$value = $this->transformer->get_value( 'constant', $constant );
		} catch ( \Exception $e ) {
			throw new WPConfigException( $e->getMessage(), $e->getCode(), $e );
		}

		return $value;
	}

	/**
	 * Add (or update) the given constant in wp-config.php.
	 *
	 * @param string $constant The constant name.
	 * @param mixed  $value    The constant value.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\WPConfigException If the configuration cannot be written.
	 */
	public function setConstant( $constant, $value ) {
		$options = [
			'add'       => true,
			'raw'       => false,
			'normalize' => true,
		];

		if ( is_bool( $value ) ) {
			$options['raw'] = true;
			$value          = $value ? 'true' : 'false';
		}

		$this->setConfig( 'constant', $constant, $value, $options );
	}

	/**
	 * Remove the given constant from wp-config.php.
	 *
	 * @param string $constant The constant name.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\WPConfigException If the configuration cannot be written.
	 */
	public function removeConstant( $constant ) {
		try {
			$this->transformer->remove( 'constant', $constant );
		} catch ( \Exception $e ) {
			throw new WPConfigException( $e->getMessage(), $e->getCode(), $e );
		}
	}

	/**
	 * Determine whether or not the given variable exists in wp-config.php.
	 *
	 * @param string $variable The variable name.
	 *
	 * @return bool True if the variable is defined, false otherwise.
	 */
	public function hasVariable( $variable ) {
		try {
			$exists = $this->transformer->exists( 'variable', $variable );
		} catch ( \Exception $e ) {
			$exists = false;
		}

		return $exists;
	}

	/**
	 * Retrieve the variable value in wp-config.php.
	 *
	 * @param string $variable The variable name.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\WPConfigException If the configuration cannot be read.
	 *
	 * @return mixed Variable value
	 */
	public function getVariable( $variable ) {
		try {
			$value = $this->transformer->get_value( 'variable', $variable );
		} catch ( \Exception $e ) {
			throw new WPConfigException( $e->getMessage(), $e->getCode(), $e );
		}

		return $value;
	}

	/**
	 * Add (or update) the given variable in wp-config.php.
	 *
	 * @param string $variable The variable name.
	 * @param mixed  $value    The variable value.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\WPConfigException If the configuration cannot be written.
	 */
	public function setVariable( $variable, $value ) {
		$options = [
			'add'       => true,
			'raw'       => false,
			'normalize' => true,
		];

		if ( is_bool( $value ) ) {
			$options['raw'] = true;
			$value          = $value ? 'true' : 'false';
		} elseif ( is_array( $value ) ) {
			$options['raw'] = true;
			$value          = var_export( $value, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}

		$this->setConfig( 'variable', $variable, $value, $options );
	}

	/**
	 * Remove the given variable from wp-config.php.
	 *
	 * @param string $variable The variable name.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\WPConfigException If the configuration cannot be written.
	 */
	public function removeVariable( $variable ) {
		try {
			$this->transformer->remove( 'variable', $variable );
		} catch ( \Exception $e ) {
			throw new WPConfigException( $e->getMessage(), $e->getCode(), $e );
		}
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
	 * @throws \StellarWP\PluginFramework\Exceptions\WPConfigException If the anchor cannot be added.
	 *
	 * @return bool True if the anchor was added, false if it already exists.
	 */
	protected function restoreMissingAnchor() {
		$path   = ABSPATH . 'wp-config.php';
		$anchor = PHP_EOL . '/* That\'s all, stop editing!';
		$insert = $anchor . ' Happy publishing. */' . PHP_EOL;

		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$config = (string) file_get_contents( $path );

			// The anchor already exists, nothing to do.
			if ( false !== mb_strpos( $config, $anchor ) ) {
				return false;
			}

			// Find the ABSPATH definition.
			$pattern = '/(?:if\s*\(\s*!\s*)?(?:defined\(.+\))?\s*\{?(?:\|\|)?\s*define\(\s*["\']ABSPATH["\']/';

			if ( ! preg_match( $pattern, $config, $abspath ) ) {
				throw new WPConfigException( 'Unable to find the ABSPATH definition' );
			}

			// Insert the anchor just before the ABSPATH definition.
			$config = str_replace( $abspath[0], $insert . $abspath[0], $config );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			if ( false === file_put_contents( $path, $config ) ) {
				throw new WPConfigException( 'Unable to write to wp-config.php file' );
			}
		} catch ( \Exception $e ) {
			throw new WPConfigException( $e->getMessage(), $e->getCode(), $e );
		}

		return true;
	}
}
