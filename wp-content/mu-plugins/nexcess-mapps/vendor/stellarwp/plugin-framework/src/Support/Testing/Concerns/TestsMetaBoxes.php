<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use PHPUnit\Framework\Assert;
use StellarWP\PluginFramework\Services\Managers\MetaBoxManager;
use StellarWP\PluginFramework\Support\MetaBox;
use WP_Screen;

/**
 * Utilities for testing metaboxes within WP-Admin.
 */
trait TestsMetaBoxes
{
    /**
     * A cache of registered meta boxes.
     *
     * @var Array<MetaBox>
     */
    private $registeredMetaBoxes = [];

    /**
     * @before
     */
    protected function resetWpMetaBoxesGlobal()
    {
        unset($GLOBALS['wp_meta_boxes']);
    }

    /**
     * Assert that the given meta box has been registered.
     *
     * @param string $id The metabox ID.
     * @param string $message Optional. Additional context if the assertion fails. Default is empty.
     */
    protected function assertMetaBoxIsRegistered($id, $message = '')
    {
        Assert::assertTrue(
            $this->getMetaBox($id) instanceof MetaBox,
            $message ?: sprintf('Unable to find a registered meta box with ID "%s".', $id)
        );
    }

    /**
     * Assert that the given meta box has been registered.
     *
     * @param string $id The metabox ID.
     * @param string $message Optional. Additional context if the assertion fails. Default is empty.
     */
    protected function assertMetaBoxIsNotRegistered($id, $message = '')
    {
        Assert::assertTrue(
            null === $this->getMetaBox('id'),
            $message ?: sprintf('Did not expect to find a registered meta box with ID "%s".', $id)
        );
    }

    /**
     * Ensure that meta boxes registered within the MetaBoxManager have been registered.
     *
     * @return $this
     */
    protected function ensureMetaBoxesAreRegistered()
    {
        $this->container->get(MetaBoxManager::class)->registerMetaBoxes();

        return $this;
    }

    /**
     * Flush the cache of registered meta boxes.
     *
     * @return $this
     */
    protected function flushMetaBoxCache()
    {
        $this->registeredMetaBoxes = [];

        return $this;
    }

    /**
     * Retrieve a meta box by ID.
     *
     * @param string $id The meta box ID.
     *
     * @return ?MetaBox The matching meta box, or null if no such meta box exists.
     */
    protected function getMetaBox($id)
    {
        $metaboxes = $this->getRegisteredMetaBoxes();

        return isset($metaboxes[$id]) ? $metaboxes[$id] : null;
    }

    /**
     * Retrieve an array of all registered meta boxes.
     *
     * @return Array<MetaBox> An array of meta boxes
     */
    protected function getRegisteredMetaBoxes()
    {
        if (empty($this->registeredMetaBoxes)) {
            if (empty($GLOBALS['wp_meta_boxes'])) {
                return [];
            }

            foreach ((array) $GLOBALS['wp_meta_boxes'] as $page_id => $page_boxes) {
                foreach ($page_boxes as $context_id => $context_boxes) {
                    foreach ($context_boxes as $priority_id => $priority_boxes) {
                        foreach ($priority_boxes as $box) {
                            $metabox = new MetaBox([
                                'id'       => $box['id'],
                                'title'    => $box['title'],
                                'body'     => $box['callback'],
                                'page'     => $page_id,
                                'context'  => $context_id,
                                'priority' => $priority_id,
                            ]);

                            // We already have a box with this ID, so attempt to merge the pages.
                            if (isset($this->registeredMetaBoxes[$metabox->getId()])) {
                                $existing = $this->registeredMetaBoxes[$metabox->getId()]->getPage();
                                $current  = $metabox->getPage();

                                /*
                                 * If this ID was previously registered using a WP_Screen object, coerce
                                 * it into an array with a single string (the ID).
                                 */
                                if ($existing instanceof WP_Screen) {
                                    $existing = [$existing->id];
                                }

                                if ($current instanceof WP_Screen) {
                                    $current = [$current->id];
                                }

                                $metabox->setPage(array_merge((array) $existing, (array) $current));
                            }

                            $this->registeredMetaBoxes[$metabox->getId()] = $metabox;
                        }
                    }
                }
            }
        }

        return $this->registeredMetaBoxes;
    }
}
