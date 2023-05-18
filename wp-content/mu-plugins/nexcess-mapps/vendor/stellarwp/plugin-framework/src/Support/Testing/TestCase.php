<?php

namespace StellarWP\PluginFramework\Support\Testing;

use StellarWP\PluginFramework\Support\Str;
use StellarWP\PluginFramework\Support\Testing\Concerns\InteractsWithContainer;
use StellarWP\PluginFramework\Support\Testing\Concerns\UsesMockery;
use StellarWP\PluginFramework\Support\Testing\Concerns\UsesReflection;
use WP_UnitTestCase;

class TestCase extends WP_UnitTestCase
{
    use InteractsWithContainer;
    use UsesMockery;
    use UsesReflection;

    /**
     * Extend the default WordPress Core Test Suite's set_up() method.
     */
    public function set_up()
    {
        parent::set_up();

        /*
         * Enable traits to define their own setup methods that will be run *after* WordPress has
         * been fully loaded by defining a method named "setUp{TraitName}".
         */
        foreach (class_uses($this) as $trait) {
            $method = 'setUp' . Str::classBasename($trait);

            if (method_exists($this, $method)) {
                call_user_func([$this, $method]);
            }
        }
    }
}
