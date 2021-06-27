<?php
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Create Container using PHP-DI
$container = new Container();

// Set container to create App with on AppFactory
AppFactory::setContainer($container);

$container->set(modX::class, function () {
    $working_context = 'web';
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $query_string = $_SERVER['QUERY_STRING']; 
    $modx = new modX();
    $_SERVER['QUERY_STRING'] = $query_string;
    //echo '<pre>' . print_r($_SERVER,1) . '</pre>';
    $modx->initialize($working_context);
    return $modx;
});

$app = AppFactory::create();

$base_path = $container->get(modX::class)->getOption('assets_url') . 'components/slimtest';
$app->setBasePath($base_path);

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    print_r($request->getQueryParams());
    return $response;
});

$app->run();