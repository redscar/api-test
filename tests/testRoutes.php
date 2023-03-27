<?php

use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once __DIR__ . '/../src/routes.php';
require_once __DIR__ . '/../src/config.php';

class testRoutes extends TestCase
{
    private static App $app;

    public static function setUpBeforeClass(): void
    {
        // Initialize app
        self::$app = AppFactory::create();

        // Register routes
        registerUsersRoutes(self::$app);
    }

    public function setUp(): void
    {

        // Truncate the database before our tests.
        // Normally I would recomend a test database instead of truncating our "live" database.

        // Disable foreign key check
        DB::query('SET FOREIGN_KEY_CHECKS=0');

        // Truncate loyalty_members and transactions tables
        DB::query('TRUNCATE TABLE loyalty_members');
        DB::query('TRUNCATE TABLE transactions');

        // Enable foreign key check
        DB::query('SET FOREIGN_KEY_CHECKS=1');

        // Mock database data
        DB::insert('loyalty_members', [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'points_balance' => 0
        ]);
        DB::insert('loyalty_members', [
            'name' => 'Jane Smith',
            'email' => 'janesmith@example.com',
            'points_balance' => 100
        ]);
        DB::insert('transactions', [
            'user_id' => 2,
            'points' => 50
        ]);
    }

    public function tearDown(): void
    {
        // Disable foreign key check
        DB::query('SET FOREIGN_KEY_CHECKS=0');

        // Truncate loyalty_members and transactions tables
        DB::query('TRUNCATE TABLE loyalty_members');
        DB::query('TRUNCATE TABLE transactions');

        // Enable foreign key check
        DB::query('SET FOREIGN_KEY_CHECKS=1');
    }

    public function testGetUsers(): void
    {
        // Create mock request with query parameters
        $queryParams = ['page' => 1, 'per_page' => 10];
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/users')->withQueryParams($queryParams);

        // Invoke route callback
        $response = self::$app->handle($request);

        // Assert response status code
        $this->assertSame(200, $response->getStatusCode());

        // Assert response content type
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        // Assert response body
        $expectedBody = [
            'data' => [
                [
                    'id' => "1",
                    'name' => 'John Doe',
                    'email' => 'johndoe@example.com',
                    'points_balance' => "0"
                ],
                [
                    'id' => "2",
                    'name' => 'Jane Smith',
                    'email' => 'janesmith@example.com',
                    'points_balance' => "100"
                ]
            ],
            'total_records' => "2",
            'page' => 1,
            'per_page' => 10
        ];
        $this->assertSame(json_encode($expectedBody), (string)$response->getBody());
    }

    public function testEarnPoints(): void
    {
        // Create mock request with required parameters
        $params = [
            'points' => 50,
            'description' => 'Earned 50 points'
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/users/2/earn')
            ->withParsedBody($params);

        // Invoke route callback
        $response = self::$app->handle($request);

        // Assert response status code
        $this->assertSame(201, $response->getStatusCode());

        // Assert response content type
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        // Assert response body
        $expectedBody = [
            'points_balance' => "150"
        ];
        $this->assertSame(json_encode($expectedBody), (string)$response->getBody());

        // Check the loyalty_members table to ensure the points balance has been updated correctly
        $pointsBalance = DB::queryFirstField("SELECT points_balance FROM loyalty_members WHERE id = %i", 2);
        $this->assertSame("150", $pointsBalance);
    }
    public function testRedeemPoints(): void
    {
        //This test is done on user 2

        // Create mock request with request body
        $requestBody = [
            'points' => 10,
            'description' => 'Test redemption'
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/users/2/redeem')
            ->withParsedBody($requestBody);

        // Invoke route callback
        $response = self::$app->handle($request);

        // Assert response status code
        $this->assertSame(200, $response->getStatusCode());

        // Assert response content type
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        // Assert response body
        $expectedBody = [
            'points_balance' => "90"
        ];
        $this->assertSame(json_encode($expectedBody), (string)$response->getBody());

        // Assert that the loyalty_members table has been updated with the new points balance
        $updatedPointsBalance = DB::queryFirstField("SELECT points_balance FROM loyalty_members WHERE id = %i", 1);
        $this->assertSame("0", $updatedPointsBalance);
    }
}