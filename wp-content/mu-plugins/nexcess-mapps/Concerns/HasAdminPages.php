<?php

namespace Nexcess\MAPPS\Concerns;

use StellarWP\PluginFramework\Exceptions\TemplateException;

trait HasAdminPages {

	/**
	 * Callback to render the page.
	 *
	 * By default, this will use the integration name (lowercased).
	 */
	public function renderMenuPage() {
		$this->renderTemplate( strtolower( ( new \ReflectionClass( $this ) )->getShortName() ) );
	}

	/**
	 * Get the contents of a template file, using the provided $data array.
	 *
	 * @param string  $template The template name, which should be relative to the
	 *                          nexcess-mapps/templates/ directory without a file extension.
	 * @param mixed[] $data     Optional. Data to pass to the template. Default is empty.
	 *
	 * @return string The output of the template.
	 */
	protected function getTemplateContent( $template, $data = [] ) {
		try {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $data );

			// Get the filepath *after* extract() but before opening the output buffer.
			$filepath = $this->locateTemplateFile( $template );

			ob_start();
			include $filepath;
			$content = ob_get_clean();
		} catch ( TemplateException $e ) {
			$content = defined( 'WP_DEBUG' ) && WP_DEBUG
				? sprintf( '<!-- %s -->', $e->getMessage() )
				: '';
		}

		return trim( (string) $content );
	}

	/**
	 * Render a template from within the templates directory.
	 *
	 * @param string  $template The template name, which should be relative to the
	 *                          nexcess-mapps/templates/ directory without a file extension.
	 * @param mixed[] $data     Optional. Data to pass to the template. Default is empty.
	 */
	protected function renderTemplate( $template, $data = [] ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->getTemplateContent( $template, $data );
	}

	/**
	 * Retrieve the path to a template file.
	 *
	 * @param string $template The template name, which should be relative to the
	 *                         nexcess-mapps/templates/ directory without a file extension.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\TemplateException If the given template cannot
	 *                                                                 be found.
	 *
	 * @return string Either the system path to the template or an empty string if the template
	 *                could not be found.
	 */
	protected function locateTemplateFile( $template ) {
		$file = sprintf( '%1$s/templates/%2$s.php', dirname( __DIR__ ), $template );

		/**
		 * Override the individual template location.
		 *
		 * @param string $file     The existing file path for the template file.
		 * @param string $template The template name that was used in the original call.
		 */
		$file = apply_filters( 'nexcess_mapps_branding_template_file', $file, $template );

		if ( ! file_exists( $file ) ) {
			throw new TemplateException( sprintf(
				'The MAPPS template file at %1$s was not found.',
				$file
			) );
		}

		return $file;
	}

	/**
	 * Inject data into the global `window.MAPPS` object before loading a script.
	 *
	 * The passed $data will be JSON-encoded and added to `window.MAPPS.$property`, making it
	 * accessible from within our JavaScript.
	 *
	 * This is a wrapper around {@see wp_add_inline_script()}.
	 *
	 * @param string $handle   The script handle to attach this data to.
	 * @param string $property The property name to write on the global `window.MAPPS` object.
	 * @param mixed  $data     The data to make available via `window.MAPPS.$property`.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function injectScriptData( $handle, $property, $data ) {
		$script = sprintf(
			'window.MAPPS.%1$s = %2$s;',
			$property,
			wp_json_encode( $data )
		);

		return wp_add_inline_script( $handle, $script, 'before' );
	}
}
