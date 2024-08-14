# reactphp-x-route

"Framework X has provided a wealth of inspiration." - [Framework X](https://framework-x.org/)


Like Laravel Route support

* middleware
* group

## install

```
composer require reactphp-x/route -vvv
```

## usage

### in http server

```php
use ReactphpX\Route\Route;
use FrameworkX\AccessLogHandler;
use FrameworkX\ErrorHandler;

$container = new DI\Container();

$route = new Route($container);

$route->get('/', function () {
    return new React\Http\Message\Response(
        200,
        ['Content-Type' => 'text/plain'],
        "Hello, World!\n"
    );
});
$route->group('/api', function ($route) {
    $route->get('/hello', function () {
        return new React\Http\Message\Response(
            200,
            ['Content-Type' => 'text/plain'],
            "Hello, API!\n"
        );
    });
    $route->get('/world', function () {
        return new React\Http\Message\Response(
            200,
            ['Content-Type' => 'text/plain'],
            "World, API!\n"
        );
    });
});

$class = new class {
    public function index() 
    {
        return new React\Http\Message\Response(
            200,
            ['Content-Type' => 'text/plain'],
            "Hello, Class!\n"
        );
    }
};

$route->get('/at', get_class($class).'@index');


$http = new React\Http\HttpServer(
    new AccessLogHandler(),
    new ErrorHandler(),
    $route
);

$socket = new React\Socket\SocketServer(getenv('X_LISTEN'));
$http->listen($socket);

echo "Server running at http://".getenv('X_LISTEN'). PHP_EOL;
```

### in FrameworkX

```php
use ReactphpX\Route\FrameworkXRoute;


$app = new FrameworkX\App();

$route = new FrameworkXRoute($app);

$route->get('/', function () {
    return new React\Http\Message\Response(
        200,
        ['Content-Type' => 'text/plain'],
        "Hello, World!\n"
    );
});
$route->group('/api', function ($route) {
    $route->get('/hello', function () {
        return new React\Http\Message\Response(
            200,
            ['Content-Type' => 'text/plain'],
            "Hello, API!\n"
        );
    });
    $route->get('/world', function () {
        return new React\Http\Message\Response(
            200,
            ['Content-Type' => 'text/plain'],
            "World, API!\n"
        );
    });
});

// 不支持 @ 
// $class = new class {
//     public function index() 
//     {
//         return new React\Http\Message\Response(
//             200,
//             ['Content-Type' => 'text/plain'],
//             "Hello, Class!\n"
//         );
//     }
// };

// $route->get('/at', get_class($class).'@index');

$app->run();

echo "Server running at http://".getenv('X_LISTEN'). PHP_EOL;
```

### in FrameworkX and support @

```php
use ReactphpX\Route\Route;

$container = new DI\Container();
$route = new Route($container);
$app = new FrameworkX\App(
    $route
);


//$app->any('{path:.*}', $route);

$route->get('/', function () {
    return new React\Http\Message\Response(
        200,
        ['Content-Type' => 'text/plain'],
        "Hello, World!\n"
    );
});
$route->group('/api', function ($route) {
    $route->get('/hello', function () {
        return new React\Http\Message\Response(
            200,
            ['Content-Type' => 'text/plain'],
            "Hello, API!\n"
        );
    });
    $route->get('/world', function () {
        return new React\Http\Message\Response(
            200,
            ['Content-Type' => 'text/plain'],
            "World, API!\n"
        );
    });
});

$class = new class {
    public function index() 
    {
        return new React\Http\Message\Response(
            200,
            ['Content-Type' => 'text/plain'],
            "Hello, Class!\n"
        );
    }
};

$route->get('/at', get_class($class).'@index');

$app->run();

echo "Server running at http://".getenv('X_LISTEN'). PHP_EOL;
```

## License

MIT