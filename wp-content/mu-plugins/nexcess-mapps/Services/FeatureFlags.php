<?php

namespace Nexcess\MAPPS\Services;

use Nexcess\MAPPS\Concerns\MakesHttpRequests;
use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\CacheRemember;

class FeatureFlags {
	use MakesHttpRequests;
	use ManagesGroupedOptions;

	/**
	 * A cache of the current flags.
	 *
	 * @var string[]|null
	 */
	protected $currentFlags;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * The cache key used for settings from the Nexcess API.
	 */
	const CACHE_KEY = 'nexcess_mapps_feature_flags';

	/**
	 * The option key that holds the backup of the latest successful configuration.
	 */
	const OPTION_NAME = 'nexcess_mapps_feature_flags';

	/**
	 * Construct the FeatureFlags instance.
	 *
	 * @param \Nexcess\MAPPS\Settings $settings
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Explicitly disable a feature flag for a site.
	 *
	 * This works by setting the cohort for this flag to 100, meaning it's unavailable until the
	 * feature is generally-available.
	 *
	 * @param string $flag The flag name.
	 *
	 * @return self
	 */
	public function disable( $flag ) {
		$this->getOption()->set( 'cohorts', array_merge( (array) $this->getOption()->cohorts, [
			$flag => 100,
		] ) )->save();

		// Reset the cache.
		$this->currentFlags = null;

		return $this;
	}

	/**
	 * Explicitly enable a feature flag for a site.
	 *
	 * This works by setting the cohort for this flag to 0, meaning it's available regardless
	 * of the current configuration for this feature.
	 *
	 * @param string $flag The flag name.
	 *
	 * @return self
	 */
	public function enable( $flag ) {
		$this->getOption()->set( 'cohorts', array_merge( (array) $this->getOption()->cohorts, [
			$flag => 0,
		] ) )->save();

		// Reset the cache.
		$this->currentFlags = null;

		return $this;
	}

	/**
	 * Determine whether or not the current flag is enabled for this site.
	 *
	 * @param string $flag The flag name to check.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function enabled( $flag ) {
		return in_array( $flag, $this->getActive(), true );
	}

	/**
	 * Collect all feature flags that are enabled for this site.
	 *
	 * @return string[] An array of feature flag IDs.
	 */
	public function getActive() {
		if ( is_array( $this->currentFlags ) ) {
			return $this->currentFlags;
		}

		$all    = $this->getCurrentFeatureFlags();
		$active = array_keys( array_filter( $all, function ( $value, $flag ) {
			return $this->getCohortId( $flag ) <= (int) $value;
		}, ARRAY_FILTER_USE_BOTH ) );

		/*
		 * In case a flag has been removed from currentFeatureFlags(), treat any that we already
		 * have a defined cohort for as active (e.g. assume general availability).
		 */
		$keys = array_diff_key( (array) $this->getOption()->cohorts, $all );

		if ( ! empty( $keys ) ) {
			$active = array_merge( $active, array_keys( $keys ) );
		}

		// Filter out any duplicates.
		$active = array_unique( $active, SORT_STRING );
		sort( $active );

		$this->currentFlags = $active;

		return $this->currentFlags;
	}

	/**
	 * Collect the cohort assignments which are available for this site.
	 *
	 * @return array
	 */
	public function getCohorts() {
		return (array) $this->getOption()->get( 'cohorts', [] );
	}

	/**
	 * Get the cohort ID for this site for the given $flag.
	 *
	 * For the first version of feature flags, each site will roll a D100 for each flag, then store
	 * the result; this determines at which threshold a feature will be considered active.
	 *
	 * For example, if a feature is active for 35% of sites but the cohort ID for this flag on this
	 * site is 40, the feature will not yet be active.
	 *
	 * @param string $flag The flag for which to retrieve the cohort ID.
	 *
	 * @return int
	 */
	protected function getCohortId( $flag ) {
		if ( ! isset( $this->getOption()->cohorts[ $flag ] ) ) {
			$this->getOption()->set( 'cohorts', array_merge( (array) $this->getOption()->get( 'cohorts', [] ), [
				$flag => random_int( 1, 100 ),
			] ) )->save();
		}

		return $this->getOption()->cohorts[ $flag ];
	}

	/**
	 * Retrieve and cache the current feature flag settings.
	 *
	 * @return Array<string,int>
	 */
	protected function getCurrentFeatureFlags() {
		try {
			$flags = CacheRemember::remember_transient( self::CACHE_KEY, function () {
				$response = wp_remote_get( $this->settings->feature_flags_url );
				$json     = json_decode( $this->validateHttpResponse( $response, 200 ), true );
				$flags    = isset( $json['flags'] ) ? $json['flags'] : [];

				// Cache the flags in the option in case future requests fail.
				$this->getOption()->set( 'flags', $flags )->save();

				return $flags;
			}, 6 * HOUR_IN_SECONDS );
		} catch ( \Exception $e ) {
			$flags = $this->getOption()->get( 'flags', [] );

			// Seed the cache with our backup value for a short time.
			set_transient( self::CACHE_KEY, $flags, 15 * MINUTE_IN_SECONDS );
		}

		return $flags;
	}
}
