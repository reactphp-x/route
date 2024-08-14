<?php

namespace ReactphpX\Route;

use React\Http\Message\Response;
use FrameworkX\Io\RedirectHandler;

trait FriendlyRouteTrait
{
    /** @var string */
    protected $currentGroupPrefix;

    /** @var array<callable|class-string> */
    protected $currentGroupHandlers = [];

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function get(string $route, $handler, ...$handlers): void
    {
        $this->_map(['GET'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function head(string $route, $handler, ...$handlers): void
    {
        $this->_map(['HEAD'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function post(string $route, $handler, ...$handlers): void
    {
        $this->_map(['POST'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function put(string $route, $handler, ...$handlers): void
    {
        $this->_map(['PUT'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function patch(string $route, $handler, ...$handlers): void
    {
        $this->_map(['PATCH'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function delete(string $route, $handler, ...$handlers): void
    {
        $this->_map(['DELETE'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function options(string $route, $handler, ...$handlers): void
    {
        // backward compatibility: `OPTIONS * HTTP/1.1` can be matched with empty path (legacy)
        if ($route === '') {
            $route = '*';
        }

        $this->_map(['OPTIONS'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function any(string $route, $handler, ...$handlers): void
    {
        $this->_map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $route, $handler, ...$handlers);
    }

    /**
     * Create a route group with a common prefix.
     *
     * All routes created in the passed callback will have the given group prefix prepended.
     *
     * @param string $prefix
     * @param array<callable|class-string> $handlers
     * @param callable $callback
     */
    public function addGroup(string $prefix, array $handlers, callable $callback): void
    {
        $previousGroupPrefix = $this->currentGroupPrefix;
        $previousGroupHandlers = $this->currentGroupHandlers;
        $this->currentGroupPrefix = $previousGroupPrefix . $prefix;
        $this->currentGroupHandlers = array_merge($previousGroupHandlers, $handlers);
        $callback($this);
        $this->currentGroupPrefix = $previousGroupPrefix;
        $this->currentGroupHandlers = $previousGroupHandlers;
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
    public function group(string | array | callable $prefix, ...$handlers): void
    {
        if (\is_array($prefix)) {
            $midlleware = $prefix['middleware'] ?? '';
            if ($midlleware) {
                $handlers = array_merge((array) $midlleware, $handlers);
            }
            $prefix = $prefix['prefix'] ?? '';
        } else if (\is_callable($prefix)) {
            $handlers = array_merge([$prefix], $handlers);
            $prefix = '';
        }

        $handler = array_pop($handlers);
        $this->addGroup($prefix, $handlers, $handler);

    }

    /**
     * Add middleware to the current group.
     *
     * @param callable | class-string | string | array ...$middlewares
     */

    public function middleware(...$middlewares)
    {
        if (count($middlewares) == 1 && \is_array($middlewares[0])) {
            $middlewares = $middlewares[0];
        }

       return new class($this, $middlewares) {
            private $route;
            private $middlewares;

            public function __construct($route, $middlewares)
            {
                $this->route = $route;
                $this->middlewares = $middlewares;
            }

            public function group(...$handlers)
            {
                $this->route->group(['middleware' => $this->middlewares], fn ($app) => $app->group(...$handlers));
            }
        };
    }

    /**
     *
     * @param string[] $methods
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    private function _map(array $methods, string $route, $handler, ...$handlers): void
    {
        if (!empty($this->currentGroupHandlers)) {
            \array_unshift($handlers, $handler);
            $currentGroupHandlers = $this->currentGroupHandlers;
            $handler = \array_shift($currentGroupHandlers);
            $handlers = \array_merge($currentGroupHandlers, $handlers);
        }
        $this->map($methods, $this->currentGroupPrefix . $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param string $target
     * @param int $code
     */
    public function redirect(string $route, string $target, int $code = Response::STATUS_FOUND): void
    {
        $this->any($route, new RedirectHandler($target, $code));
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
    abstract public function map(array $methods, string $route, $handler, ...$handlers): void;
    
}