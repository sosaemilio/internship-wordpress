<?php

namespace StellarWP\PluginFramework\Services;

use StellarWP\PluginFramework\Concerns\MakesHttpRequests;
use StellarWP\PluginFramework\Settings;
use StellarWP\PluginFramework\Support\CacheRemember;
use StellarWP\PluginFramework\Support\GroupedOption;

class FeatureFlags
{
    use MakesHttpRequests;

    /**
     * A cache of the current flags.
     *
     * @var string[]|null
     */
    protected $currentFlags;

    /**
     * @var \StellarWP\PluginFramework\Settings
     */
    protected $settings;

    /**
     * Construct the FeatureFlags instance.
     *
     * @param \StellarWP\PluginFramework\Settings $settings
     */
    public function __construct(Settings $settings)
    {
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
    public function disable($flag)
    {
        $option = $this->getOption();
        $option->set('cohorts', array_merge((array) $option->get('cohorts'), [
            $flag => 100,
        ]))->save();

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
    public function enable($flag)
    {
        $option = $this->getOption();
        $option->set('cohorts', array_merge((array) $option->get('cohorts'), [
            $flag => 0,
        ]))->save();

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
    public function enabled($flag)
    {
        return in_array($flag, $this->getActive(), true);
    }

    /**
     * Collect all feature flags that are enabled for this site.
     *
     * @return string[] An array of feature flag IDs.
     */
    public function getActive()
    {
        if (is_array($this->currentFlags)) {
            return $this->currentFlags;
        }

        $all    = (array) $this->getCurrentFeatureFlags();
        $active = array_keys(array_filter($all, function ($value, $flag) {
            return $this->getCohortId($flag) <= (int) $value; // @phpstan-ignore-line
        }, ARRAY_FILTER_USE_BOTH));

        /*
         * In case a flag has been removed from currentFeatureFlags(), treat any that we already
         * have a defined cohort for as active (e.g. assume general availability).
         */
        $keys = array_diff_key($this->getCohorts(), $all);

        if (! empty($keys)) {
            $active = array_merge($active, array_keys($keys));
        }

        // Filter out any duplicates.
        $active = array_unique($active, SORT_STRING);
        sort($active);

        $this->currentFlags = $active;

        return $this->currentFlags;
    }

    /**
     * Retrieve the current feature flag settings.
     *
     * @return GroupedOption
     */
    public function getOption()
    {
        return new GroupedOption(
            (
                ! empty($this->settings->platform_prefix)
                ? $this->settings->platform_prefix
                : 'stellarwp'
            ) . '_feature_flags'
        );
    }

    /**
     * Collect the cohort assignments which are available for this site.
     *
     * @return array<mixed>
     */
    public function getCohorts()
    {
        return (array) $this->getOption()->get('cohorts', []);
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
    protected function getCohortId($flag)
    {
        if (! isset($this->getOption()->cohorts[ $flag ])) {
            $this->getOption()->set('cohorts', array_merge((array) $this->getOption()->get('cohorts', []), [
                $flag => random_int(1, 100),
            ]))->save();
        }

        $cohorts = (array) $this->getOption()->get('cohorts');
        return intval($cohorts[ $flag ]);
    }

    /**
     * Retrieve and cache the current feature flag settings.
     *
     * @return mixed
     */
    protected function getCurrentFeatureFlags()
    {
        try {
            $flags = CacheRemember::rememberTransient($this->settings->platform_prefix . '_feature_flags', function () {
                $response = wp_remote_get($this->settings->feature_flags_url);
                $json     = json_decode($this->validateHttpResponse($response, 200), true);
                $flags    = isset($json['flags']) ? $json['flags'] : []; // @phpstan-ignore-line

                // Cache the flags in the option in case future requests fail.
                update_option($this->settings->platform_prefix . '_feature_flags', $flags);

                return $flags;
            }, 6 * HOUR_IN_SECONDS);
        } catch (\Exception $e) {
            $flags = $this->getOption()->get('flags', []);

            // Seed the cache with our backup value for a short time.
            set_transient($this->settings->platform_prefix . '_feature_flags', $flags, 15 * MINUTE_IN_SECONDS);
        }

        return $flags;
    }
}
