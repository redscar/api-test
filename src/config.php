<?php

declare(strict_types=1);

// Set database credentials
// Never commit credentials to Git. However, these are fake credentials for testing.
DB::$user = 'apiUser';
DB::$password = 'Password123#@!';
DB::$dbName = 'loyalty_program';
// DB::$host = 'localhost'; // Defaults to localhost.
// DB::$port = '3306'; // Defaults to 3306
