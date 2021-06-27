<?php
use DI\Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

require __DIR__ . '/../vendor/autoload.php';

// Create Container using PHP-DI
$container = new Container();

// Set container to create App with on AppFactory
AppFactory::setContainer($container);

$container->set(modX::class, function () {
    $working_context = 'web';
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    /* MODX does its protect - thing within its constructor at MODX::protect 
       and alters $_SERVER['QUERY_STRING'], so we can't use 
       $request->getQueryParams() later, but need to use the superglobal $_GET instead
       we could hack it by getting the querystring and put it back after constructing MODX
    */
    //$query_string = $_SERVER['QUERY_STRING']; 
    $modx = new modX();
    //$_SERVER['QUERY_STRING'] = $query_string;
    $modx->initialize($working_context);
    return $modx;
});

$container->set(Migx::class, function ($container) {
    $modx = $container->get(modX::class);
    $migxCorePath = realpath($modx->getOption('migx.core_path', null, $modx->getOption('core_path') . 'components/migx')) . '/';
    $migx = $modx->getService('migx', 'Migx', $migxCorePath . 'model/migx/');
    return $migx;    
});

$app = AppFactory::create();

if (isset($base_path)){
    $app->setBasePath($base_path);
}

// Register routes
$routes = require __DIR__ . '/../app/routes.php';
$routes($app);

// Add Routing Middleware
$app->addRoutingMiddleware();

$displayErrorDetails = true;
$logError = true;
$logErrorDetails = true;
$logger = null;
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logError, $logErrorDetails, $logger);

$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->forceContentType('application/json');


$app->run();
