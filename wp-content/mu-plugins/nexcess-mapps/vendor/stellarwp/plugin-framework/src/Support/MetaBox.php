<?php

namespace StellarWP\PluginFramework\Support;

use StellarWP\PluginFramework\Exceptions\ValidationException;
use WP_Screen;

/**
 * A representation of a WordPress meta box.
 */
class MetaBox
{
    /**
     * The meta box body callback.
     *
     * @var callable
     */
    protected $body;

    /**
     * The meta box context.
     *
     * @var self::CONTEXT_*
     */
    protected $context;

    /**
     * The meta box ID.
     *
     * @var string
     */
    protected $id;

    /**
     * The page(s) that should include this meta box.
     *
     * @var string|Array<string>|WP_Screen|null
     */
    protected $page;

    /**
     * The meta box priority.
     *
     * @var self::PRIORITY_*
     */
    protected $priority;

    /**
     * The widget title
     *
     * @var string
     */
    protected $title;

    /** Valid meta box contexts. */
    const CONTEXT_NORMAL  = 'normal';
    const CONTEXT_SIDE    = 'side';
    const CONTEXT_COLUMN3 = 'column3';
    const CONTEXT_COLUMN4 = 'column4';

    /** Valid meta box prorities. */
    const PRIORITY_CORE    = 'core';
    const PRIORITY_DEFAULT = 'default';
    const PRIORITY_HIGH    = 'high';
    const PRIORITY_LOW     = 'low';

    /**
     * Construct a new meta box instance.
     *
     * @param Array<string,scalar|callable> $atts {
     *   Optional. Attributes to set for the meta box. These may also be set via the explicit setter methods.
     *   Default is empty.
     *
     *   @type callable                            $body     The callback to output the meta box body.
     *   @type string                              $id       A unique ID for the meta box.
     *   @type string|Array<string>|WP_Screen|null $page     The page(s) on which the meta box should display.
     *                                                       Default is null, which corresponds to the current page.
     *   @type string                              $priority How to prioritize the meta box. Must be one of "high",
     *                                                       "core", "default", or "low". Default is "default".
     *   @type string                              $title    The meta box title.
     * }
     */
    public function __construct(array $atts = [])
    {
        /*
         * While it seems counterintuitive that we're setting the priority to "core" by default
         * (as opposed to "default"), this is because WordPress's {@see wp_add_dashboard_widget()}
         * uses "core" as its default.
         *
         * @link https://developer.wordpress.org/reference/functions/wp_add_dashboard_widget/
         */
        $atts = wp_parse_args($atts, [
            'body'     => '__return_empty_string',
            'context'  => self::CONTEXT_NORMAL,
            'page'     => null,
            'priority' => self::PRIORITY_DEFAULT,
            'title'    => 'Widget',
        ]);

        if (empty($atts['id'])) {
            $atts['id'] = sanitize_title($atts['title'], uniqid('stellarwp-metabox-'));
        }

        $this->setId($atts['id'])
            ->setTitle($atts['title'])
            ->setBody($atts['body'])
            ->setPage($atts['page'])
            ->setPriority($atts['priority'])
            ->setContext($atts['context']);
    }

    /**
     * Retrieve the meta box body callback.
     *
     * @return callable The callable that will produce the meta box body.
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Retrieve the meta box context.
     *
     * @return self::CONTEXT_* The meta box context.
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Retrieve the meta box ID.
     *
     * @return string The meta box ID.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Retrieve the page(s) on which the meta box should be rendered.
     *
     * @return string|Array<string>|WP_Screen|null The page(s) or null, which represents the current screen.
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Retrieve the meta box priority.
     *
     * @return self::PRIORITY_* One of the valid priority levels.
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Retrieve the meta box title.
     *
     * @return string The meta box title.
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set the meta box body.
     *
     * @param callable $body The callback that will render the meta box body.
     *
     * @throws ValidationException If the given $body is not callable.
     *
     * @return $this
     */
    public function setBody($body)
    {
        if (! is_callable($body)) {
            throw new ValidationException(sprintf(
                'A widget body callback must be callable, %s provided.',
                gettype($body)
            ));
        }

        $this->body = $body;

        return $this;
    }

    /**
     * Set the meta box context.
     *
     * @param self::CONTEXT_* $context The meta box context.
     *
     * @throws ValidationException If given an unknown context.
     *
     * @return $this
     */
    public function setContext($context)
    {
        $contexts = [
            self::CONTEXT_NORMAL,
            self::CONTEXT_SIDE,
            self::CONTEXT_COLUMN3,
            self::CONTEXT_COLUMN4,
        ];

        if (! in_array($context, $contexts, true)) {
            throw new ValidationException(sprintf(
                'Invalid widget context "%s". Valid contexts are: "%s"',
                $context,
                implode('", "', $contexts)
            ));
        }
        $this->context = $context;

        return $this;
    }

    /**
     * Set the meta box ID.
     *
     * @param string $id The meta box ID.
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set the page(s) for the meta box.
     *
     * @param string|Array<string>|WP_Screen|null $page The page(s) on which the meta box should display.
     *
     * @return $this
     */
    public function setPage($page)
    {
        if (is_string($page) || $page instanceof WP_Screen) {
            $this->page = $page;
        } elseif (is_array($page)) {
            $this->page = array_filter($page, 'is_string');
        } else {
            $this->page = null;
        }

        return $this;
    }

    /**
     * Set the meta box priority.
     *
     * @param self::PRIORITY_* $priority The meta box priority.
     *
     * @throws ValidationException If given an unknown priority level.
     *
     * @return $this
     */
    public function setPriority($priority)
    {
        $levels = [
            self::PRIORITY_HIGH,
            self::PRIORITY_CORE,
            self::PRIORITY_DEFAULT,
            self::PRIORITY_LOW,
        ];

        if (! in_array($priority, $levels, true)) {
            throw new ValidationException(sprintf(
                'Invalid meta box priority "%s". Valid levels are: "%s"',
                $priority,
                implode('", "', $levels)
            ));
        }
        $this->priority = $priority;

        return $this;
    }

    /**
     * Set the meta box title.
     *
     * @param string $title The meta box title.
     *
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }
}
