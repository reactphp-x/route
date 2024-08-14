<?php

namespace ReactphpX\Route;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use FrameworkX\App;

final class Route
{
    use FriendlyRouteTrait;

    /** @var RouteHandler */
    private $router;

    public function __construct(ContainerInterface $container)
    {
        $this->router = new RouteHandler($container);
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

    public function map(array $methods, string $route, $handler, ...$handlers): void
    {
        $this->router->map($methods, $route, $handler, ...$handlers);
    }

    public function __invoke(ServerRequestInterface $request)
    {
        return ($this->router)($request);
    }
}
