<?php

namespace ReactphpX\Route\Tests;

use FrameworkX\AccessLogHandler;
use FrameworkX\App;
use FrameworkX\Container;
use FrameworkX\ErrorHandler;
use FrameworkX\Io\MiddlewareHandler;
use FrameworkX\Io\ReactiveHandler;
use FrameworkX\Io\RouteHandler;
use FrameworkX\Tests\Fixtures\InvalidAbstract;
use FrameworkX\Tests\Fixtures\InvalidConstructorInt;
use FrameworkX\Tests\Fixtures\InvalidConstructorIntersection;
use FrameworkX\Tests\Fixtures\InvalidConstructorPrivate;
use FrameworkX\Tests\Fixtures\InvalidConstructorProtected;
use FrameworkX\Tests\Fixtures\InvalidConstructorSelf;
use FrameworkX\Tests\Fixtures\InvalidConstructorUnion;
use FrameworkX\Tests\Fixtures\InvalidConstructorUnknown;
use FrameworkX\Tests\Fixtures\InvalidConstructorUntyped;
use FrameworkX\Tests\Fixtures\InvalidInterface;
use FrameworkX\Tests\Fixtures\InvalidTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use ReflectionProperty;
use function React\Async\async; // @phpstan-ignore-line
use function React\Async\await;
use function React\Promise\reject;
use function React\Promise\resolve;
use ReactphpX\Route\Route;

class RouteTest extends TestCase
{
    
    public function testInvokeWithMatchingGroupRouteReturnsResponseFromMatchingRouteHandler(): void
    {

        $route = $this->createRoute();

        $app = $this->createAppWithoutLogger($route);
        $route->addGroup('/users', [], function ($app) {
            $app->get('', function () {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK\n"
                );
            });
        });
        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingFriendlyGroupRouteReturnsResponseFromMatchingRouteHandler(): void
    {
        $route = $this->createRoute();
        $app = $this->createAppWithoutLogger($route);
        $route->group('/users', function ($app) {
            $app->get('', function () {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK\n"
                );
            });
        });
        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingChildGroupRouteReturnsResponseFromMatchingRouteHandler(): void
    {
        $route = $this->createRoute();
        $app = $this->createAppWithoutLogger($route);
        $route->addGroup('/users', [], function ($app) {
            $app->addGroup('/{name}', [], function ($app) {
                $app->get('/posts/{post}', function (ServerRequestInterface $request) {
                    $name = $request->getAttribute('name');
                    $post = $request->getAttribute('post');
                    assert(is_string($name));
                    assert(is_string($post));

                    return new Response(
                        200,
                        [
                            'Content-Type' => 'text/html'
                        ],
                        "OK $name $post\n"
                    );
                });
            });
        });
        $request = new ServerRequest('GET', 'http://localhost/users/alice/posts/first');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK alice first\n", (string) $response->getBody());
    }
    public function testInvokeWithMatchingFriendlyChildGroupRouteReturnsResponseFromMatchingRouteHandler(): void
    {
        $route = $this->createRoute();
        $app = $this->createAppWithoutLogger($route);
        $route->group('/users', function ($app) {
            $app->group('/{name}', function ($app) {
                $app->get('/posts/{post}', function (ServerRequestInterface $request) {
                    $name = $request->getAttribute('name');
                    $post = $request->getAttribute('post');
                    assert(is_string($name));
                    assert(is_string($post));

                    return new Response(
                        200,
                        [
                            'Content-Type' => 'text/html'
                        ],
                        "OK $name $post\n"
                    );
                });
            });
        });
        $request = new ServerRequest('GET', 'http://localhost/users/alice/posts/first');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK alice first\n", (string) $response->getBody());
    }

    public function testConstractAndGroupRouteWithMiddlewareReturnContructMiddleware(): void
    {
        $route = $this->createRoute();
        $middleware1 = function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK1\n"
            );
        };
        $middleware2 = function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK2\n"
            );
        };

        $app = $this->createAppWithoutLogger(
            $middleware1,$route
        );
        $route->addGroup('/users', [
            $middleware2
        ], function ($app) {
            $app->get('', function () {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK3\n"
                );
            });
        });
        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK1\n", (string) $response->getBody());
    }
    public function testConstractAndFriendlyGroupRouteWithMiddlewareReturnContructMiddleware(): void
    {

        $route = $this->createRoute();
        $middleware1 = function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK1\n"
            );
        };
        $middleware2 = function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK2\n"
            );
        };

        $app = $this->createAppWithoutLogger(
            $middleware1,$route
        );
        $route->group('/users', $middleware2, function ($app) {
            $app->get('', function () {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK3\n"
                );
            });
        });
        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK1\n", (string) $response->getBody());
    }

    public function testGroupRouteWithMiddlewareReturnGroupMiddleware(): void
    {
        $route = $this->createRoute();
        $middleware2 = function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK2\n"
            );
        };

        $app = $this->createAppWithoutLogger($route);
        $route->addGroup('/users', [
            $middleware2
        ], function ($app) {
            $app->get('', function () {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK3\n"
                );
            });
        });
        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK2\n", (string) $response->getBody());
    }
    public function testFriendlyGroupRouteWithMiddlewareReturnGroupMiddleware(): void
    {
        $route = $this->createRoute();
        $middleware2 = function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK2\n"
            );
        };

        $app = $this->createAppWithoutLogger($route);
        $route->group('/users', $middleware2, function ($app) {
            $app->get('', function () {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK3\n"
                );
            });
        });
        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK2\n", (string) $response->getBody());
    }

    public function testGroupRouteWithMiddlewareReturnRouteMiddleware(): void
    {
        $route = $this->createRoute();
        $middleware2 = function ($request, $next) {
            return $next($request);
        };

        $app = $this->createAppWithoutLogger($route);
        $route->addGroup('/users', [
            $middleware2
        ], function ($app) {
            $app->get('', function () {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK3\n"
                );
            });
        });
        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK3\n", (string) $response->getBody());
    }
    public function testFriendlyGroupRouteWithMiddlewareReturnRouteMiddleware(): void
    {
        $route = $this->createRoute();
        $middleware2 = function ($request, $next) {
            return $next($request);
        };

        $app = $this->createAppWithoutLogger($route);
        $route->group('/users', $middleware2, function ($app) {
            $app->get('', function () {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK3\n"
                );
            });
        });
        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK3\n", (string) $response->getBody());
    }

    public function testSetRequestGroupRouteWithMiddlewareAndReturnRouteMiddleware(): void
    {
        $route = $this->createRoute();
        $middleware2 = function ($request, $next) {
            return $next($request->withAttribute('group', 'group'));
        };

        $app = $this->createAppWithoutLogger($route);
        $route->addGroup('/users', [
            $middleware2
        ], function ($app) {
            $app->get('', function ($request) {
                $group = $request->getAttribute('group');
                assert(is_string($group));
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK {$group}\n"
                );
            });
        });
        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK group\n", (string) $response->getBody());
    }
    public function testSetRequestFriendlyGroupRouteWithMiddlewareAndReturnRouteMiddleware(): void
    {
        $route = $this->createRoute();
        $middleware2 = function ($request, $next) {
            return $next($request->withAttribute('group', 'group'));
        };

        $app = $this->createAppWithoutLogger($route);
        $route->group('/users', $middleware2, function ($app) {
            $app->get('', function ($request) {
                $group = $request->getAttribute('group');
                assert(is_string($group));
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK {$group}\n"
                );
            });
        });
        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK group\n", (string) $response->getBody());
    }

    public function testGroupRouteMiddlewareSort(): void
    {
        $route = $this->createRoute();
        $app = $this->createAppWithoutLogger($route);

        $fn1 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn1'));
        };

        $fn2 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn2'));
        };

        $fn3 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn3'));
        };

        $fn4 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn4'));
        };

        $fn5 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn5'));
        };

        $fn6 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn6'));
        };


        $route->addGroup('/sorts', [
            $fn1
        ], function ($app) use ($fn2, $fn3, $fn4, $fn5, $fn6) {
            $app->get('/sort1', function ($request) {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK {$request->getHeaderLine('fn')}\n"
                );
            });
            $app->addGroup('', [
                $fn2,
                $fn3,
                $fn4,
            ], function ($app) use ($fn5, $fn6) {
                $app->get('/sort2', function ($request) {
                    return new Response(
                        200,
                        [
                            'Content-Type' => 'text/html'
                        ],
                        "OK {$request->getHeaderLine('fn')}\n"
                    );
                });

                $app->addGroup('', [
                    $fn5
                ], function ($app) {
                    $app->get('/sort3', function ($request) {
                        return new Response(
                            200,
                            [
                                'Content-Type' => 'text/html'
                            ],
                            "OK {$request->getHeaderLine('fn')}\n"
                        );
                    });
                    
                });
                $app->addGroup('', [
                    $fn6
                ], function ($app) {
                    $app->get('/sort4', function ($request) {
                        return new Response(
                            200,
                            [
                                'Content-Type' => 'text/html'
                            ],
                            "OK {$request->getHeaderLine('fn')}\n"
                        );
                    });
                });
            });
        });

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort1');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1\n", (string) $response->getBody());

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort2');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1, fn2, fn3, fn4\n", (string) $response->getBody());

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort3');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1, fn2, fn3, fn4, fn5\n", (string) $response->getBody());

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort4');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1, fn2, fn3, fn4, fn6\n", (string) $response->getBody());       
    }
    public function testFriendlyGroupRouteMiddlewareSort(): void
    {
        $route = $this->createRoute();
        $app = $this->createAppWithoutLogger($route);

        $fn1 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn1'));
        };

        $fn2 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn2'));
        };

        $fn3 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn3'));
        };

        $fn4 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn4'));
        };

        $fn5 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn5'));
        };

        $fn6 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn6'));
        };


        $route->group('/sorts', $fn1, function ($app) use ($fn2, $fn3, $fn4, $fn5, $fn6) {
            $app->get('/sort1', function ($request) {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK {$request->getHeaderLine('fn')}\n"
                );
            });
            $app->group('', $fn2, $fn3, $fn4, function ($app) use ($fn5, $fn6) {
                $app->get('/sort2', function ($request) {
                    return new Response(
                        200,
                        [
                            'Content-Type' => 'text/html'
                        ],
                        "OK {$request->getHeaderLine('fn')}\n"
                    );
                });

                $app->group('', $fn5, function ($app) {
                    $app->get('/sort3', function ($request) {
                        return new Response(
                            200,
                            [
                                'Content-Type' => 'text/html'
                            ],
                            "OK {$request->getHeaderLine('fn')}\n"
                        );
                    });
                    
                });
                $app->group('', $fn6, function ($app) {
                    $app->get('/sort4', function ($request) {
                        return new Response(
                            200,
                            [
                                'Content-Type' => 'text/html'
                            ],
                            "OK {$request->getHeaderLine('fn')}\n"
                        );
                    });
                });
            });
        });

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort1');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1\n", (string) $response->getBody());

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort2');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1, fn2, fn3, fn4\n", (string) $response->getBody());

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort3');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1, fn2, fn3, fn4, fn5\n", (string) $response->getBody());

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort4');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1, fn2, fn3, fn4, fn6\n", (string) $response->getBody());       
    }
    public function testFriendlyPrexfiGroupRouteMiddlewareSort(): void
    {
        $route = $this->createRoute();
        $app = $this->createAppWithoutLogger($route);

        $fn1 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn1'));
        };

        $fn2 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn2'));
        };

        $fn3 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn3'));
        };

        $fn4 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn4'));
        };

        $fn5 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn5'));
        };

        $fn6 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn6'));
        };


        $route->group([
            'prefix' => '/sorts',
            'middleware' => $fn1
        ], function ($app) use ($fn2, $fn3, $fn4, $fn5, $fn6) {
            $app->get('/sort1', function ($request) {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK {$request->getHeaderLine('fn')}\n"
                );
            });
            $app->group([
                'middleware' => [$fn2, $fn3, $fn4]
            ], function ($app) use ($fn5, $fn6) {
                $app->get('/sort2', function ($request) {
                    return new Response(
                        200,
                        [
                            'Content-Type' => 'text/html'
                        ],
                        "OK {$request->getHeaderLine('fn')}\n"
                    );
                });

                $app->group([
                    'middleware' => $fn5
                ], function ($app) {
                    $app->get('/sort3', function ($request) {
                        return new Response(
                            200,
                            [
                                'Content-Type' => 'text/html'
                            ],
                            "OK {$request->getHeaderLine('fn')}\n"
                        );
                    });
                    
                });
                $app->group($fn6, function ($app) {
                    $app->get('/sort4', function ($request) {
                        return new Response(
                            200,
                            [
                                'Content-Type' => 'text/html'
                            ],
                            "OK {$request->getHeaderLine('fn')}\n"
                        );
                    });
                });
            });
        });

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort1');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1\n", (string) $response->getBody());

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort2');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1, fn2, fn3, fn4\n", (string) $response->getBody());

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort3');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1, fn2, fn3, fn4, fn5\n", (string) $response->getBody());

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort4');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1, fn2, fn3, fn4, fn6\n", (string) $response->getBody());       
    }
    public function testMiddlewarePrexfiGroupRouteMiddlewareSort(): void
    {
        $route = $this->createRoute();
        $app = $this->createAppWithoutLogger($route);

        $fn1 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn1'));
        };

        $fn2 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn2'));
        };

        $fn3 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn3'));
        };

        $fn4 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn4'));
        };

        $fn5 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn5'));
        };

        $fn6 = function ($request, $next) {
            return $next($request->withAddedHeader('fn', 'fn6'));
        };


        $route->middleware($fn1)->group([
            'prefix' => '/sorts',
        ], function ($app) use ($fn2, $fn3, $fn4, $fn5, $fn6) {
            $app->get('/sort1', function ($request) {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK {$request->getHeaderLine('fn')}\n"
                );
            });
            $app->middleware([$fn2, $fn3, $fn4])->group(function ($app) use ($fn5, $fn6) {
                $app->get('/sort2', function ($request) {
                    return new Response(
                        200,
                        [
                            'Content-Type' => 'text/html'
                        ],
                        "OK {$request->getHeaderLine('fn')}\n"
                    );
                });

                $app->middleware($fn5)->group(function ($app) {
                    $app->get('/sort3', function ($request) {
                        return new Response(
                            200,
                            [
                                'Content-Type' => 'text/html'
                            ],
                            "OK {$request->getHeaderLine('fn')}\n"
                        );
                    });
                    
                });
                $app->group($fn6, function ($app) {
                    $app->get('/sort4', function ($request) {
                        return new Response(
                            200,
                            [
                                'Content-Type' => 'text/html'
                            ],
                            "OK {$request->getHeaderLine('fn')}\n"
                        );
                    });
                });
            });
        });

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort1');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1\n", (string) $response->getBody());

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort2');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1, fn2, fn3, fn4\n", (string) $response->getBody());

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort3');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1, fn2, fn3, fn4, fn5\n", (string) $response->getBody());

        $request = new ServerRequest('GET', 'http://localhost/sorts/sort4');
        $response = $app($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK fn1, fn2, fn3, fn4, fn6\n", (string) $response->getBody());       
    }

    public function testAtMethod()
    {
        $route = $this->createRoute();
        $middleware2 = function ($request, $next) {
            return $next($request->withAttribute('group', 'group'));
        };

        $class = new class {
            public function index(ServerRequestInterface $request)
            {
                $group = $request->getAttribute('group');
                assert(is_string($group));
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK {$group}\n"
                );
            }
        };
        $app = $this->createAppWithoutLogger($route);
        $route->group('/users', $middleware2, function ($app) use ($class) {
            $app->get('', get_class($class) . '@index');
        });
        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK group\n", (string) $response->getBody());
    }

    private function createRoute()
    {
        $container = new \DI\Container();
        return new Route($container);
    }

    private function createAppWithoutLogger(callable ...$middleware): App
    {
        return new App(
            new AccessLogHandler(DIRECTORY_SEPARATOR !== '\\' ? '/dev/null' : __DIR__ . '\\nul'),
            new ErrorHandler(),
            ...$middleware
        );
    }
}
