<?php

require_once 'vendor/autoload.php';

use Aitum\CustomCode\Client\SwooleClientAdapter;
use Aitum\CustomCode\Command\RegisterActionsCommand;
use Aitum\CustomCode\Service\ActionCollector;
use Aitum\Dispatcher\ApiCommandDispatcher;
use Colors\Color;
use FastRoute\RouteCollector;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

$dispatcher = FastRoute\simpleDispatcher(function(RouteCollector $r) {
  $r->addRoute('GET', '/hc', 'Aitum\CustomCode\Controller\Core::heartbeat');
  $r->addRoute('POST', '/rules/{id}', 'Aitum\CustomCode\Controller\Action::trigger');
});

try {
  $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
  $dotenv->load();
} catch (\Exception $exception) {
  $io = new Color();
  echo $io('Config not found! Run "composer aitum-cc:setup" to get started!')->cyan()->bold()->bg_white() . PHP_EOL;
  exit;
}

$server = new Server("127.0.0.1", 7252);

$actionCollector = new ActionCollector();
$actionCollection = $actionCollector->collect();

$server->on('start', function ($server) use ($actionCollection){
  $io = new Color();
  echo $io('AitumPHP CC: Webserver started.')->cyan()->bold() . PHP_EOL;

  $apiCommandDispatcher = new ApiCommandDispatcher(new SwooleClientAdapter());

  $apiCommandDispatcher->dispatch(
    new RegisterActionsCommand($actionCollection)
  );
});

$server->on('request', function (Request $request, Response $response) use ($dispatcher, $actionCollection) {
  $routeInfo = $dispatcher->dispatch($request->server['request_method'], $request->server['request_uri']);

  switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::FOUND:
      [$controller, $controllerAction] = explode('::', $routeInfo[1]);
      $controllerInstance = new $controller;

      if (!empty($routeInfo[2])) {
        $action = $actionCollection->getActionById($routeInfo[2]['id']);
        $response->end($controllerInstance->$controllerAction($action, $request->getContent()));
      } else {
        $response->end($controllerInstance->$controllerAction($routeInfo[2], $request->getContent()));
      }
      break;

    case FastRoute\Dispatcher::NOT_FOUND:
      $response->status(404);
      $response->end('Not Found');
      break;

    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
      $response->status(405);
      $response->end('Method Not Allowed');
      break;

    default:
      $response->status(500);
      $response->end('Internal Server Error');
  }
});

$server->start();