<?php

namespace ReactphpX\Route;

use FrameworkX\App;

final class FrameworkXRoute 
{
    use FriendlyRouteTrait;

    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Create a route group with a common prefix.
     *
     * All routes created in the passed callback will have the given group prefix prepended.
     *
     * @param string | array $prefix
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */

    /**
     * Create a route group with a common prefix.
     *
     * All routes created in the passed callback will have the given group prefix prepended.
     *
     * @param string | array $prefix
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */

    public function map(array $methods, string $route, $handler, ...$handlers): void
    {
        $this->app->map($methods, $route, $handler, ...$handlers);
    }
}
