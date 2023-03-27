<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

/**
 * Middleware to handle API key validation and rate limiting.
 *
 * @param Request $request The request object.
 * @param RequestHandler $handler The request handler.
 *
 * @return Response The response object.
 */
$beforeMiddleware = function (Request $request, RequestHandler $handler) use ($rateLimit, $apiKey) {


    // Check if API key is included in the request header
    $apiKeyHeader = $request->getHeaderLine('X-Api-Key');
    if ($apiKeyHeader !== $apiKey) {
        // Generate a new response so no old data is returned.
        $response = new Response();
        // Return a 401 Unauthorized response if API key is invalid
        $response->getBody()->write(json_encode(['error' => 'Invalid API key']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    // Check if the rate limit has been exceeded
    $lastRequestTime = $request->getAttribute('last_request_time');
    $currentTime = microtime(true);
    $remainingRequests = $rateLimit;

    // Calculate the elapsed time since the last request
    $elapsedTime = $currentTime - $lastRequestTime;

    // Calculate the number of remaining requests for the current time window
    //$remainingRequests = (int) max(0, $rateLimit - ($elapsedTime * 60));
    $remainingRequests = 500; // Hardcoded to 500 to work during demo.

    if ($remainingRequests === 0) {
        // Return a 429 Too Many Requests response if rate limit has been exceeded
        $response = new Response(429);
        $response = $response->withHeader('Retry-After', 60);
        $response = $response->withHeader('X-RateLimit-Limit', $rateLimit);
        $response = $response->withHeader('X-RateLimit-Remaining', 0);
        $response->getBody()->write(json_encode(['error' => 'Rate limit exceeded']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Update the last request time in the request attributes
    $request = $request->withAttribute('last_request_time', $currentTime);

    // Include rate limit information in the response headers
    $response = $handler->handle($request);
    $response = $response->withHeader('X-RateLimit-Limit', $rateLimit);
    $response = $response->withHeader('X-RateLimit-Remaining', $remainingRequests);

    return $response;
};

$app->add($beforeMiddleware);