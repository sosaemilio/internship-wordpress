<?php

namespace StellarWP\PluginFramework\Support;

/**
 * Helpers for working with strings.
 */
class Str
{
    /**
     * Convert a string to camelCase.
     *
     * @param string $string The string to transform.
     *
     * @return string The string in camelCase.
     */
    public static function camel($string)
    {
        return lcfirst(static::pascal($string));
    }

    /**
     * Retrieve the basename of a class, without any namespaces.
     *
     * @param string $class The class name.
     *
     * @return string The class name without any namespace prefix.
     */
    public static function classBasename($class)
    {
        return basename(str_replace('\\', '/', $class));
    }

    /**
     * Convert a string to kebab-case.
     *
     * @param string $string The string to transform.
     *
     * @return string The string in kebab-case.
     */
    public static function kebab($string)
    {
        return str_replace('_', '-', static::snake($string));
    }

    /**
     * Convert a string to PascalCase (a.k.a. TitleCase).
     *
     * @param string $string The string to transform.
     *
     * @return string The string in PascalCase.
     */
    public static function pascal($string)
    {
        $string = (string) preg_replace('/[^A-Za-z0-9]+/', '_', trim($string));
        $string = explode('_', $string);

        return implode('', array_map('ucfirst', $string));
    }

    /**
     * Convert a string to snake_case.
     *
     * @param string $string The string to transform.
     *
     * @return string The string in snake_case.
     */
    public static function snake($string)
    {
        /*
         * Prefix all capital letters with an underscore *and* replace any non-alphanumeric characters
         * with an underscore.
         */
        $string = (string) preg_replace(
            ['/([A-Z])/', '/[^A-Za-z0-9]+/'],
            ['_$1', '_'],
            trim($string)
        );

        return mb_strtolower(trim($string, '_'));
    }
}
