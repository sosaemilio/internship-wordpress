<?php

namespace StellarWP\PluginFramework\Concerns;

trait HasFlags
{
    /**
     * A cache of the current flags.
     *
     * @var bool[]|null
     */
    protected $flags;

    /**
     * The underlying option name for flags.
     *
     * @var string
     */
    protected $option = '_stellarwp_flags';

    /**
     * Delete an existing flag.
     *
     * @param string $key The flag name.
     *
     * @return bool True if the flag was deleted (or did not exist), false otherwise.
     */
    public function deleteFlag($key)
    {
        $flags = $this->getFlags();

        // If the flag doesn't exist, there's nothing to do.
        if (! isset($flags[ $key ])) {
            return true;
        }

        unset($flags[ $key ]);
        $updated = update_site_option($this->option, $flags);

        // Reset the cache.
        if ($updated) {
            $this->flags = null;
        }

        return $updated;
    }

    /**
     * Retrieve a single flag by key.
     *
     * @param string $key     The flag name.
     * @param bool   $default Optional. The default flag value. Default is false.
     *
     * @return bool The boolean value of the flag, or $default if the flag was not found.
     */
    public function getFlag($key, $default = false)
    {
        $flags = $this->getFlags();

        return isset($flags[ $key ])
            ? (bool) $flags[ $key ]
            : (bool) $default;
    }

    /**
     * Retrieve all flags.
     *
     * @return bool[] All flags stored for the current option name.
     */
    public function getFlags()
    {
        if (! is_array($this->flags)) {
            $this->flags = array_map('boolval', get_site_option($this->option, []));
        }

        return $this->flags;
    }

    /**
     * Determine whether or not the given flag exists.
     *
     * @param string $key The flag name.
     *
     * @return bool True if the flag has explicitly been set, false otherwise.
     */
    public function hasFlag($key)
    {
        return isset($this->getFlags()[ $key ]);
    }

    /**
     * Set or overwrite a value for a flag.
     *
     * @param string $key   The flag name.
     * @param bool   $value The flag value.
     *
     * @return bool True if the underlying option was updated, false otherwise.
     */
    public function setFlag($key, $value)
    {
        $updated = update_site_option($this->option, array_merge($this->getFlags(), [
            (string) $key => (bool) $value,
        ]));

        // Reset the cache.
        if ($updated) {
            $this->flags = null;
        }

        return $updated;
    }
}
