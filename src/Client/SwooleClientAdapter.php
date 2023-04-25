<?php

namespace Aitum\CustomCode\Client;

use Aitum\Client\HttpClientInterface;
use Aitum\Model\ApiCommandRequest;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Swoole\Coroutine\Http\Client;

class SwooleClientAdapter implements HttpClientInterface {

  public function request(ApiCommandRequest $request): ResponseInterface {
    $client = new Client('127.0.0.1', 7777);
    $client->setHeaders([
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . $_ENV['API_KEY'],
    ]);

    match ($request->method) {
      'GET' => $client->get($request->url),
      'POST' => $client->post($request->url, $request->body),
      default => ['status' => 405, 'message' => 'Method Not Allowed'],
    };

    return new Response(
      $client->getStatusCode(),
      $client->getHeaders(),
      $client->getBody(),
    );
  }

}