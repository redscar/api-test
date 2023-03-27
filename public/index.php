<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/config.php';

// Create Slim app instance
$app = AppFactory::create();

// To make things simple, we hard code it in the file.
$apiKey = "abc123";

$rateLimit = 500; // set the maximum number of requests allowed per minute


require __DIR__ . '/../src/Middleware.php';
// Enable error middleware
$app->addErrorMiddleware(true, true, false);

// Register routes
require __DIR__ . '/../src/routes.php';
registerUsersRoutes($app);


$app->run();
