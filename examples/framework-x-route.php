<?php

require __DIR__ . '/../vendor/autoload.php';

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