<?php

namespace StellarWP\PluginFramework\Concerns;

use StellarWP\PluginFramework\Exceptions\TemplateException;

trait RendersTemplates
{
    /**
     * Possibile directories where templates may live.
     *
     * @var Array<string> A collection of prioritized, absolute filepaths.
     */
    private $templateDirectories = [];

    /**
     * Locate a template, looking through registered paths before falling back to the framework.
     *
     * Templates may optionally omit the ".php" extension.
     *
     * @param string $template The template filename.
     *
     * @throws TemplateException If a matching template cannot be found.
     *
     * @return string The absolute filepath for the matching template file.
     */
    protected function locateTemplate($template)
    {
        // We've been given an absolute filepath.
        if (file_exists($template)) {
            return $template;
        }

        // Iterate through possible sources, including the framework.
        $directories   = array_unique(array_map('trailingslashit', $this->templateDirectories));
        $directories[] = dirname(dirname(__DIR__)) . '/resources/views/';

        foreach ($directories as $dir) {
            // Exact match!
            if (file_exists($dir . $template)) {
                return $dir . $template;
            }

            // Try again without the .php extension, if present.
            if (file_exists($dir . $template . '.php')) {
                return $dir . $template . '.php';
            }
        }

        throw new TemplateException(sprintf(
            'Unable to find a template matching the name "%s"',
            $template
        ));
    }

    /**
     * Render a template.
     *
     * @param string              $template The template filename or system filepath; if given a filename, it will
     *                                      attempt to locate the appropriate template (via `locateTemplate()`).
     * @param Array<string,mixed> $data     Optional. An array of data to pass to the template, where the keys
     *                                      correspond to variable names. Default is empty.
     *
     * @throws TemplateException If the given template cannot be found.
     *
     * @return void
     */
    protected function renderTemplate($template, array $data = [])
    {
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($data, EXTR_OVERWRITE);
        include $this->locateTemplate($template);
    }

    /**
     * Define possible directories where templates may be located.
     *
     * The locateTemplate() method will then loop through these (in order) until it finds a suitable match.
     *
     * Note that the framework's resources/views/ directory will always be appended to this list!
     *
     * @param Array<string> $directories Absolute system paths to possible template locations, ordered
     *                                   by priority.
     *
     * @return $this
     */
    protected function setTemplateDirectories(array $directories)
    {
        $this->templateDirectories = $directories;

        return $this;
    }
}
