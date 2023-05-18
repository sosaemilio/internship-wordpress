<?php

namespace StellarWP\PluginFramework\Concerns;

trait ManagesPermalinks
{
    /**
     * Set a default permalink structure if one does not exist.
     *
     * Some plugins (such as Cache Enabler) require a defined permalink structure, but WordPress
     * defaults to an empty string (e.g. index.php?p=<id>).
     *
     * If no permalink structure is set, use "/%postname%/".
     *
     * @global $wp_rewrite
     */
    protected function setDefaultPermalinkStructure()
    {
        global $wp_rewrite;

        if (! empty(get_option('permalink_structure', ''))) {
            return;
        }

        $wp_rewrite->set_permalink_structure('/%postname%/');
    }
}
