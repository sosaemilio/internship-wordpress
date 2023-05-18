<?php

namespace Nexcess\MAPPS\Support;

use StellarWP\PluginFramework\Exceptions\InvalidUrlException;

class VisualRegressionUrl implements \JsonSerializable {

	/**
	 * A description of the URL.
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * The path relative to the site root.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Whether or not to include the ID in JSON representations.
	 *
	 * @var bool
	 */
	protected $withId = true;

	/**
	 * Construct a new instance of the class.
	 *
	 * @param string $path        The site path to be inspected.
	 * @param string $description Optional. A description of the URL. Default is empty.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\InvalidUrlException When the path cannot be parsed.
	 */
	public function __construct( $path, $description = '' ) {
		$this->path        = self::normalizeUrl( $path );
		$this->description = $description;
	}

	/**
	 * Define what the JSON-serialized representation of this object looks like.
	 *
	 * @return string[]
	 */
	public function jsonSerialize() {
		$values = [
			'description' => $this->description,
			'path'        => $this->path,
		];

		if ( $this->withId ) {
			$values['id'] = $this->getId();
		}

		return $values;
	}

	/**
	 * Retrieve the URL description.
	 *
	 * @return string The URL description.
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * Get a URL-safe ID for this URL.
	 *
	 * @return string The URL ID.
	 */
	public function getId() {
		return ! empty( $this->description )
			? sanitize_title( $this->description )
			: substr( md5( $this->path ), 0, 8 );
	}

	/**
	 * Retrieve the URL permalink.
	 *
	 * @return string The URL permalink.
	 */
	public function getPermalink() {
		return site_url( $this->path );
	}

	/**
	 * Retrieve the relative URL.
	 *
	 * @return string The relative URL.
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * Include the ID in JSON representations.
	 *
	 * @return self
	 */
	public function withId() {
		$this->withId = true;

		return $this;
	}

	/**
	 * Exclude the ID in JSON representations.
	 *
	 * @return self
	 */
	public function withoutId() {
		$this->withId = false;

		return $this;
	}

	/**
	 * Normalize a URL, stripping it down to just the path (and query string, when present).
	 *
	 * @param mixed $url The URL to normalize.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\InvalidUrlException When the path cannot be parsed.
	 *
	 * @return string The normalized path + query string.
	 */
	public static function normalizeUrl( $url ) {
		$url = (string) filter_var( $url, FILTER_SANITIZE_URL ) ?: '';

		// Convert all protocols to HTTPS, if they exist.
		$url = (string) preg_replace( '/^.*:\/\//', 'https://', $url );

		// Look for domains embedded without a protocol.
		if ( preg_match( '/^[^\/]+\.[^\/]+/', $url ) ) {
			if ( ! empty( wp_parse_url( 'https://' . $url, PHP_URL_HOST ) ) ) {
				$url = 'https://' . $url;
			}
		}

		// Finally, pass the URLs to wp_parse_url() and assemble the meaningful parts.
		$parts = wp_parse_url( $url );

		if ( false === $parts ) {
			throw new InvalidUrlException( sprintf( 'Unable to parse URL "%1$s".', $url ) );
		}

		// Build the normalized path.
		$path = ! empty( $parts['path'] ) ? $parts['path'] : '/';

		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}

		// Always start paths with leading slashes.
		if ( 0 !== strpos( $path, '/' ) ) {
			$path = '/' . $path;
		}

		return $path;
	}
}
