<?php

namespace StellarWP\PluginFramework\Services\Nexcess;

/**
 * A representation of an extension (plugin, theme) that may be installed via the Nexcess MAPPS API.
 *
 * @property-read string $author        The extension author.
 * @property-read string $description   The extension description.
 * @property-read string $group         The grouping for this extension.
 * @property-read bool   $has_licensing Whether or not this extension has licensing steps available.
 * @property-read int    $id            The extension ID within the MAPPS API.
 * @property-read string $name          The extension name.
 * @property-read string $slug          The extension slug.
 * @property-read string $url           The extension URL.
 */
class Extension
{
    /**
     * The parsed attributes array.
     *
     * @var Array<string,mixed>
     */
    protected $attributes = [];

    /**
     * Construct a new plugin instance.
     *
     * @param Array<string,mixed> $attributes The extension attributes.
     */
    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Magic getter for extension attributes.
     *
     * @param string $attribute The attribute name.
     *
     * @return mixed The attribute value, or null if the attribute does not exist.
     */
    public function __get($attribute)
    {
        return $this->getAttribute($attribute);
    }

    /**
     * Retrieve an attribute by name.
     *
     * @param string $name The attribute name.
     * @param mixed  $default Optional. The value to return if $name does not exist in the attributes
     *                        array. Default is null.
     *
     * @return mixed Either the attribute value or $default.
     */
    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[ $name ] : $default;
    }

    /**
     * Retrieve the type of extension.
     *
     * @return string The extension type (e.g. "plugin" or "theme").
     */
    public function getType()
    {
        return 'theme' === $this->getAttribute('group') ? 'theme' : 'plugin';
    }

    /**
     * Create an extension based on an API response from the MAPPS API.
     *
     * @param Array<mixed> $response The JSON-decoded body from a MAPPS API response.
     *
     * @return Extension
     */
    public static function fromApiResponse(array $response)
    {
        $mapping    = [
            'author'      => 'vendor',
            'description' => 'information_tip',
            'group'       => 'group',
            'id'          => 'id',
            'name'        => 'identity',
            'slug'        => 'name',
            'url'         => 'link',
        ];
        $attributes = [
            'group' => '',
            'has_licensing' => isset($response['license_type']) && 'none' !== $response['license_type'],
        ];

        // Map API values to our extension attributes.
        foreach ($mapping as $attr => $prop) {
            if (isset($response[ $prop ])) {
                $attributes[ $attr ] = $response[ $prop ];
            }
        }

        return new self($attributes);
    }
}
