<?php

namespace StellarWP\PluginFramework\Services\Managers;

use StellarWP\PluginFramework\Exceptions\ConfigurationException;
use StellarWP\PluginFramework\Support\MetaBox;

class MetaBoxManager
{
    /**
     * All registered metaboxes.
     *
     * @var Array<int,MetaBox>
     */
    protected $metaboxes = [];

    /**
     * Retrieve all registered metaboxes.
     *
     * @return Array<int,MetaBox>
     */
    public function all()
    {
        return $this->metaboxes;
    }

    /**
     * Register a new meta box.
     *
     * @param MetaBox $metabox The meta box to be registered.
     *
     * @return $this
     */
    public function register(MetaBox $metabox)
    {
        $this->metaboxes[] = $metabox;

        return $this;
    }

    /**
     * Register metaboxes within WordPress.
     *
     * @throws ConfigurationException If called before a screen context has been set.
     *
     * @return $this
     */
    public function registerMetaboxes()
    {
        if (! function_exists('get_current_screen') || ! get_current_screen()) {
            throw new ConfigurationException(
                'Meta boxes may not be registered outside of WP-Admin or before a screen context has been set.'
            );
        }

        foreach ($this->metaboxes as $metabox) {
            add_meta_box(
                $metabox->getId(),
                $metabox->getTitle(),
                $metabox->getBody(),
                $metabox->getPage(),
                $metabox->getContext(),
                $metabox->getPriority()
            );
        }

        return $this;
    }
}
