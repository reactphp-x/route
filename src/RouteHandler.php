<?php

namespace ReactphpX\Route;

use FastRoute\DataGenerator\GroupCountBased as RouteGenerator;
use FastRoute\Dispatcher\GroupCountBased as RouteDispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Psr\Container\ContainerInterface;
use FrameworkX\ErrorHandler;
use FrameworkX\Io\MiddlewareHandler;
use FrameworkX\App;

/**
 * @internal
 */
class RouteHandler
{
    /** @var RouteCollector */
    private $routeCollector;

    /** @var ?RouteDispatcher */
    private $routeDispatcher;

    /** @var ErrorHandler */
    private $errorHandler;

    private ContainerInterface $container;

    public function __construct(ContainerInterface $container = null)
    {
        $this->routeCollector = new RouteCollector(new RouteParser(), new RouteGenerator());
        $this->container = $container;
        $this->errorHandler = new ErrorHandler();

    }

    /**
     * @param string[] $methods
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function map(array $methods, string $route, $handler, ...$handlers): void
    {
        if ($handlers) {
            \array_unshift($handlers, $handler);
            \end($handlers);
        } else {
            $handlers = [$handler];
        }
        $container = $this->container;
        foreach ($handlers as $i => $handler) {
            if (!\is_callable($handler)) {
                $handlers[$i] = new class ($handler, $container) {
                    private $handler;
                    private $container;
                    public function __construct($handler, $container)
                    {
                        $this->handler = $handler;
                        $this->container = $container;
                    }
                    public function __invoke(ServerRequestInterface $request, callable $next = null) {
                        
                        $class = $this->handler;

                        $at = '__invoke';
                        $lastAtPosition = strrpos($class, '@');
                        if ($lastAtPosition !== false && ($_class = substr($class, 0, $lastAtPosition)) && class_exists($_class, true)) {
                            $at = substr($class, $lastAtPosition + 1);
                            $class = $_class;
                        }
            
                        // Check `$class` references a valid class name that can be autoloaded
                        if (\is_array($this->container) && !\class_exists($class, true) && !interface_exists($class, false) && !trait_exists($class, false)) {
                            throw new \BadMethodCallException('Request handler class ' . $class . ' not found');
                        }
            
                        try {
                            if ($this->container instanceof ContainerInterface) {
                                $handler = $this->container->get($class);
                            } else {
                                throw new \BadMethodCallException('Container not found');
                            }
                        } catch (\Throwable $e) {
                            throw new \BadMethodCallException(
                                'Request handler class ' . $class . ' failed to load: ' . $e->getMessage(),
                                0,
                                $e
                            );
                        }
            
                        $handler = [$handler, $at];
                        // Check `$handler` references a class name that is callable, i.e. has an `__invoke()` method.
                        // This initial version is intentionally limited to checking the method name only.
                        // A follow-up version will likely use reflection to check request handler argument types.
                        if (!is_callable($handler)) {
                            throw new \BadMethodCallException('Request handler class "' . $class . '" has no public ' . $at . '() method');
                        }
            
                        // invoke request handler as middleware handler or final controller
                        if ($next === null) {
                            return call_user_func($handler, $request);
                        }
            
                        return call_user_func($handler, $request, $next);
                    }
                };
            }
        }

        /** @var non-empty-array<callable> $handlers */
        $handler = \count($handlers) > 1 ? new MiddlewareHandler(array_values($handlers)) : \reset($handlers);
        $this->routeDispatcher = null;
        $this->routeCollector->addRoute($methods, $route, $handler);
    }

    /**
     * @return ResponseInterface|PromiseInterface<ResponseInterface>|\Generator
     */
    public function __invoke(ServerRequestInterface $request)
    {
        $target = $request->getRequestTarget();
        if ($target[0] !== '/' && $target !== '*') {
            return $this->errorHandler->requestProxyUnsupported();
        } elseif ($target !== '*') {
            $target = $request->getUri()->getPath();
        }

        if ($this->routeDispatcher === null) {
            $this->routeDispatcher = new RouteDispatcher($this->routeCollector->getData());
        }

        $routeInfo = $this->routeDispatcher->dispatch($request->getMethod(), $target);
        assert(\is_array($routeInfo) && isset($routeInfo[0]));

        // happy path: matching route found, assign route attributes and invoke request handler
        if ($routeInfo[0] === \FastRoute\Dispatcher::FOUND) {
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];

            foreach ($vars as $key => $value) {
                $request = $request->withAttribute($key, rawurldecode($value));
            }

            return $handler($request);
        }

        // no matching route found: report error `404 Not Found`
        if ($routeInfo[0] === \FastRoute\Dispatcher::NOT_FOUND) {
            return $this->errorHandler->requestNotFound();
        }

        // unexpected request method for route: report error `405 Method Not Allowed`
        assert($routeInfo[0] === \FastRoute\Dispatcher::METHOD_NOT_ALLOWED);
        assert(\is_array($routeInfo[1]) && \count($routeInfo[1]) > 0);

        return $this->errorHandler->requestMethodNotAllowed($routeInfo[1]);
    }
}
