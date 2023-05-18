<?php

namespace StellarWP\PluginFramework\Services;

use StellarWP\PluginFramework\Exceptions\FilesystemException;
use StellarWP\PluginFramework\Support\RegExp;

/**
 * Service for interacting with Apache, namely the site's Htaccess file.
 *
 * Note that several of these methods use direct filesystem access instead of WP_Filesystem; this is
 * due to our use of file locks when writing to the Htaccess file, which WordPress' API doesn't
 * appear to support.
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fopen
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fwrite
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fclose
 */
class Apache
{
    /**
     * The absolute system path to the Htaccess file.
     *
     * @var string
     */
    protected $htaccess;

    /**
     * @param ?string $htaccess Optional. The absolute path to the Htaccess file. If null, this will
     *                          fall back to `ABSPATH . '.htaccess'`. Default is null.
     */
    public function __construct($htaccess = null)
    {
        $this->htaccess = $htaccess ?: ABSPATH . '.htaccess';
    }

    /**
     * Get the current contents of the Htaccess file.
     *
     * @return string The current contents of the Htaccess file, or an empty string if the file
     *                does not exist.
     */
    public function getHtaccessContents()
    {
        if (! is_readable($this->htaccess)) {
            return '';
        }

        return trim((string) file_get_contents($this->htaccess));
    }

    /**
     * Retrieve the contents of an individual section.
     *
     * @param string $section The section identifier.
     *
     * @return string|false The inner contents of the given section, or false if the given section
     *                      cannot be found.
     */
    public function getHtaccessSection($section)
    {
        preg_match($this->getSectionRegExp($section), $this->getHtaccessContents(), $matched);

        return $matched ? trim((string) $matched[1]) : false;
    }

    /**
     * Check to see whether or not a section exists within the Htaccess file.
     *
     * @param string $section The section identifier.
     *
     * @return bool True if the section exists, false otherwise.
     */
    public function hasHtaccessSection($section)
    {
        return false !== $this->getHtaccessSection($section);
    }

    /**
     * Remove a section from the Htaccess file.
     *
     * @param string $section The section identifier.
     *
     * @return bool True if the section was removed (or didn't exist to begin with) or false if the
     *              section was found but not removed.
     */
    public function removeHtaccessSection($section)
    {
        return $this->writeHtaccessSection($section, '');
    }

    /**
     * Write the contents of the Htaccess file.
     *
     * If the file does not yet exist, this method will attempt to create it.
     *
     * @param string $contents The contents to write to the Htaccess file.
     *
     * @throws FilesystemException If the Htaccess file cannot be written.
     *
     * @return bool True if the file was written successfully, false otherwise.
     */
    public function writeHtaccessContents($contents)
    {
        $exists  = file_exists($this->htaccess);

        // File exists, but is not writable.
        if ($exists && ! is_writable($this->htaccess)) {
            throw new FilesystemException(sprintf(
                'Unable to write to %s: permission denied.',
                $this->htaccess
            ));
        }

        // File doesn't yet exist, but we can't write to the directory.
        if (! $exists && ! is_writable(dirname($this->htaccess))) {
            throw new FilesystemException(sprintf(
                'Unable to create new %s file in %s: permission denied.',
                basename($this->htaccess),
                dirname($this->htaccess)
            ));
        }

        // Trim excess whitespace.
        $contents = (string) preg_replace('/[\r\n]{3,}/m', "\n\n", $contents);
        $contents = trim($contents);

        // If the file doesn't exist and we're not writing anything, simply return true.
        if (! $exists && empty($contents)) {
            return true;
        }

        // Explicitly add a closing newline character.
        if (! empty($contents)) {
            $contents .= PHP_EOL;
        }

        // If the contents are the same, there's nothing we need to do.
        if ($exists && $this->getHtaccessContents() === $contents) {
            return true;
        }

        /*
         * Attempt to retrieve a lock on the file.
         *
         * We don't want to risk other processes trying to modify the file, so we'll open the resource
         * using "c+" (read + write, without truncating, creating if it does not yet exist), then
         * truncate + write the file *after* we've successfully obtained an exclusive lock.
         */
        $fh = fopen($this->htaccess, 'c+b');

        if (false === $fh) {
            throw new FilesystemException(sprintf('Unable to open file %s for writing.', $this->htaccess));
        }

        if (! flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            throw new FilesystemException(sprintf('Unable to obtain a lock on file %s.', $this->htaccess));
        }

        ftruncate($fh, 0);
        if (false === fwrite($fh, $contents)) {
            fclose($fh);
            throw new FilesystemException(sprintf('Unable to write to %s.', $this->htaccess));
        }

        fclose($fh);

        return true;
    }

    /**
     * Write a section to the site's Htaccess file.
     *
     * @param string $section The section identifier.
     * @param string $content The content to write into this section. Passing an empty string will
     *                        remove this section.
     * @param bool   $before  Optional. Whether the section should be before or after the main
     *                        WordPress rewrite rules. Default is true.
     *
     * @throws FilesystemException If unable to write to the Htaccess file.
     *
     * @return bool True if the section was written, false otherwise.
     */
    public function writeHtaccessSection($section, $content, $before = true)
    {
        $contents = $this->getHtaccessContents();

        // Find the section and/or WordPress rules (if either exist).
        preg_match($this->getSectionRegExp($section), $contents, $existingSection);
        preg_match($this->getSectionRegExp('WordPress'), $contents, $wpSection);

        // Normalize whitespace.
        $content = (string) preg_replace('/[\r\n]\s+[\r\n]/', "\n\n", $content);
        $content = (string) preg_replace('/[\r\n]{3,}/', "\n\n", $content);

        // If the section exists but $content is empty, remove the section.
        if (empty($content) && $existingSection) {
            return $this->writeHtaccessContents(str_replace($existingSection[0], '', $contents));
        }

        // If both sections are present, make sure they're in the right order.
        if ($existingSection && $wpSection) {
            $anchor    = preg_quote($section, '/');
            $placement = $before
                ? '/# END ' . $anchor . '\s*[\r\n]+.*?# BEGIN WordPress\s*?[\r\n]/ms'
                : '/# END WordPress\s*?[\r\n].*?[\r\n]\s*# BEGIN ' . $anchor . '\s*[\r\n]/ms';

            // The section is in the wrong place, so remove it and we'll add it later.
            if (! preg_match($placement, $contents)) {
                $contents = str_replace($existingSection[0], '', $contents);
                $existingSection = false;
            }
        }

        if ($existingSection) {
            // The section exists already, so update it in-place.
            $contents = str_replace($existingSection[0], $this->wrapSection($section, $content), $contents);
        } elseif (! $wpSection && ! empty($content)) {
            // Without WordPress rewrite rules, we can put it anywhere.
            $contents .= PHP_EOL . $this->wrapSection($section, $content) . PHP_EOL;
        } elseif ($before) {
            $contents = str_replace(
                '# BEGIN WordPress',
                $this->wrapSection($section, $content) . PHP_EOL . '# BEGIN WordPress',
                $contents
            );
        } else {
            $contents = str_replace(
                '# END WordPress',
                '# END WordPress' . PHP_EOL . $this->wrapSection($section, $content),
                $contents
            );
        }

        return $this->writeHtaccessContents($contents);
    }

    /**
     * Get a regular expression pattern for matching sections within an Htaccess file.
     *
     * @param string $section The section identifier.
     *
     * @return string A regular expression for matching the given section.
     */
    protected function getSectionRegExp($section)
    {
        $section = preg_quote($section, '/');

        /*
         * - Zero or more whitespace characters
         * - "# BEGIN {$section}", followed by zero or more whitespace characters (non-greedy)
         *   and at least one newline or carraige return character.
         * - Capture group 1: Contents of the section
         * - "# END {$section}" followed by zero or more whitespace characters (non-greedy)
         *   and at least one newline or carraige return character.
         */
        return "/\s*# BEGIN {$section}\s*?[\r\n]+(.*?)# END {$section}\s*?[\r\n]*/ms";
    }

    /**
     * Wrap a section in the appropriate BEGIN and END markers.
     *
     * @param string $section The section identifier.
     * @param string $content The section content.
     *
     * @return string A block, properly wrapped in BEGIN and END markers.
     */
    protected function wrapSection($section, $content)
    {
        return sprintf("\n\n# BEGIN %1\$s\n%2\$s\n# END %1\$s\n\n", $section, trim($content));
    }

    /**
     * Attempt to convert a PCRE regular expression pattern into an Apache rewrite condition.
     *
     * @param string $pattern The regular expression pattern.
     *
     * @return string A regex pattern suitable for Apache RewriteCond directives. An empty string
     *                will be returned if the pattern cannot be parsed or is invalid.
     */
    public static function regExpToRewriteCond($pattern)
    {
        if (! RegExp::validate($pattern)) {
            return '';
        }

        /*
         * Extract modifiers and determine the delimiter being used.
         *
         * Capture groups:
         * 1: The delimiter (non-alphanumeric character)
         * 2: The contents of the pattern
         * 3: Regex modifiers
         */
        if (! preg_match('/^([^A-Za-z0-9]{1})(.+)\1([ADSUXJimsux]*)/', $pattern, $parts)) {
            return '';
        }

        /*
         * Undo any preg_quote() escaping.
         *
         * The preg_quote() function will escape the following characters and, optionally, the
         * pattern delimiter. We want to escape all of these characters, then replace any of their
         * replaced versions:
         *
         *     . \ + * ? [ ^ ] $ ( ) { } = ! < > | : - #
         *
         * Then, we'll replace any escaped version of the characters (including the original delimiter,
         * e.g. $parts[1]) with their unescaped counterparts.
         *
         * @link https://www.php.net/manual/en/function.preg-quote.php
         */
        $escaped = preg_quote('.\\\+*?[^]$(){}=!<>|:-#', $parts[1]);
        $pattern = preg_replace('/\\\([\\' . $parts[1] . $escaped . '])/', '$1', $parts[2]);

        // Don't allow any whitespace in the pattern or Apache will break!
        $pattern = (string) preg_replace('/\s+/', '\\s+', (string) $pattern);

        // If the "i" (case-insensitive) modifier was present, add the "NC" flag.
        if (false !== mb_strpos($parts[3], 'i')) {
            $pattern .= ' [NC]';
        }

        return $pattern;
    }
}
