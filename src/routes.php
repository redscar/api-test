<?php

declare(strict_types=1);

use Slim\App;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

function registerUsersRoutes(App $app): void
{

    function getPaginationValues(Request $request): array
    {
        $queryParams = $request->getQueryParams();

        // Set default values for page and per_page parameters
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 10;

        // Calculate the limit and offset values based on the page and per_page parameters
        $limit = $perPage;
        $offset = ($page - 1) * $perPage;

        return [$limit, $offset, $page, $perPage];
    }

    function respondWithJson(Response $response, array $data, int $statusCode = 200): Response
    {
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($data));

        return $response->withStatus($statusCode);
    }


    /**
     * GET /users: Retrieve a list of all users and their current points balance.
     */
    $app->get('/users', function (Request $request, Response $response, $args) {

        [$limit, $offset, $page, $perPage] = getPaginationValues($request);
        // Fetch the total number of records available
        $totalRecords = DB::queryFirstField("SELECT COUNT(*) FROM loyalty_members");

        // Fetch user data from loyalty_members table with limit and offset
        $userData = DB::query("
        SELECT *
        FROM loyalty_members 
        LIMIT %i OFFSET %i
    ", $limit, $offset);

        // Return the user data, the total number of records, and the current page and per_page values as JSON
        return respondWithJson($response, [
            'data' => $userData,
            'total_records' => $totalRecords,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return $response;
    });
    /**
     * POST /users: Create a new user with an initial points balance of 0.
     *
     * Required parameters:
     * - name (string): The name of the user to create.
     * - email (string): The email address of the user to create.
     */
    $app->post('/users', function (Request $request, Response $response, $args) {
        $params = $request->getParsedBody();

        // Check if the required parameters are present in the request body
        if (!isset($params['name']) || !isset($params['email'])) {
            return respondWithJson($response, ['error' => 'Missing required parameters'], 400);
        }

        $name = $params['name'];
        $email = $params['email'];

        // Check if the provided email address is already associated with a user
        $existingUser = DB::queryFirstRow("SELECT * FROM loyalty_members WHERE email = %s", $email);
        if ($existingUser) {
            return respondWithJson($response, ['error' => 'User with this email already exists'], 409);
        }

        // Insert a new user into loyalty_members table
        DB::insert('loyalty_members', [
            'name' => $name,
            'email' => $email,
            'points_balance' => 0,
        ]);

        $newUserId = DB::insertId();

        // Return a 201 status code and the new user ID as JSON
        return respondWithJson($response, ['user_id' => $newUserId], 201);
    });
    /**
     * POST /users/{id}/earn: Earn points for a user.
     * The request should include the number of points to earn and a description of the transaction.
     *
     * Required parameters:
     * - id (integer): The ID of the user to earn points for.
     * - points (integer): The number of points to earn for the user.
     * - description (string): A description of the transaction.
     *
     * Possible outcomes:
     * - If the user with the specified ID exists, add the earned points to their balance,
     * return a 201 status code with the new balance in JSON format.
     * - If the user with the specified ID does not exist, return a 404 status code with no data.
     * - If the provided points value is negative or zero, return a 400 status code with an error message.
     * - If the provided description value is not a string, return a 400 status code with an error message.
     */
    $app->post('/users/{id}/earn', function (Request $request, Response $response, $args) {
        $userId = (int)$args['id'];
        $params = $request->getParsedBody();

        // Check if the user exists in the loyalty_members table
        $userExists = DB::queryFirstField("SELECT COUNT(*) FROM loyalty_members WHERE id = %i", $userId);

        if (!$userExists) {
            // If the user doesn't exist, return a 404 status code
            return $response->withStatus(404);
        }

        // Validate the provided points value
        $points = (int)$params['points'];
        if ($points <= 0) {
            return respondWithJson($response, ['error' => 'Invalid points value'], 400);
        }

        // Validate the provided description value
        $description = $params['description'];
        if (!is_string($description)) {
            return respondWithJson($response, ['error' => 'Invalid description value'], 400);
        }

        // Insert a new earn transaction into transactions table
        DB::insert('transactions', [
            'user_id' => $userId,
            'points' => $points,
            'description' => $description,
            'type' => 'earn'
        ]);

        // Update the user's points balance in loyalty_members table
        DB::query("
UPDATE loyalty_members
SET points_balance = (points_balance + %i)
WHERE id = %i
", $points, $userId);

        // Get the updated points balance
        $updatedPointsBalance = DB::queryFirstField("SELECT points_balance FROM loyalty_members WHERE id = %i", $userId);

        // Return the new points balance in JSON format
        $data = [
            'points_balance' => $updatedPointsBalance
        ];

        return respondWithJson($response, $data, 201);
    });

    /**
     * POST /users/{id}/redeem: Redeem points for a user.
     * The request should include the number of points to redeem and a description of the transaction.
     *
     * Required parameters:
     * - id (integer): The ID of the user to redeem points for.
     * - points (integer): The number of points to redeem for the user.
     * - description (string): A description of the transaction.
     *
     * Possible outcomes:
     * - If the user with the specified ID does not exist, return a 404 status code with no data.
     * - If the user does not have enough points to redeem,
     * return a 400 status code with an error message in the response body.
     * - If the transaction is successful, return a 200 response code with the new points balance in JSON format.
     */
    $app->post('/users/{id}/redeem', function (Request $request, Response $response, $args) {
        $userId = (int)$args['id'];
        $params = $request->getParsedBody();

        $points = (int)$params['points'];
        $description = $params['description'];

        // Check if the user exists in the loyalty_members table
        $userExists = DB::queryFirstField("SELECT COUNT(*) FROM loyalty_members WHERE id = %i", $userId);

        if (!$userExists) {
            // If the user doesn't exist, return a 404 status code
            return $response->withStatus(404);
        }

        // Check if the user has enough points to redeem
        $pointsBalance = DB::queryOneField('points_balance', "SELECT points_balance FROM loyalty_members WHERE id = %i", $userId);

        if ($pointsBalance < $points) {
            // If the user doesn't have enough points to redeem, return a 400 status code
            return respondWithJson($response, ['error' => 'User does not have enough points to redeem.'], 400);
        }

        // Insert a new redeem transaction into transactions table
        DB::insert('transactions', [
            'user_id' => $userId,
            'points' => $points,
            'description' => $description,
            'type' => 'redeem'
        ]);

        // Update the user's points balance in loyalty_members table
        DB::query("
        UPDATE loyalty_members
        SET points_balance = GREATEST( (points_balance - %i), 0 )
        WHERE id = %i
    ", $points, $userId);

        // Return the new points balance in JSON format
        $pointBalance = DB::queryOneField('points_balance', "SELECT points_balance FROM loyalty_members WHERE id = %i", $userId);
        return respondWithJson($response, ['points_balance' => $pointBalance], 200);
    });
    /**
     * DELETE /users/{id}: Delete a user by their ID.
     *
     * Required parameters:
     * - id (integer): The ID of the user to delete.
     *
     * Possible outcomes:
     * - If the user with the specified ID exists, delete the user and all related transactions
     * and return a 204 status code with no data.
     * - If the user with the specified ID does not exist, return a 404 status code with no data.
     * - If the user was not deleted due to an error, roll back the transaction data
     * and return a 500 status code with no data.
     */
    $app->delete('/users/{id}', function (Request $request, Response $response, $args) {
        $userId = (int)$args['id'];

        // Check if the user exists in the loyalty_members table
        $userExists = DB::queryFirstField("SELECT COUNT(*) FROM loyalty_members WHERE id = %i", $userId);

        if (!$userExists) {
            // If the user doesn't exist, return a 404 status code
            return $response->withStatus(404);
        }

        // Begin a transaction to delete the user and related transaction data
        DB::startTransaction();

        try {
            // Delete all transactions related to the user from transactions table
            DB::delete('transactions', 'user_id=%i', $userId);

            // Delete the user from loyalty_members table
            $result = DB::delete('loyalty_members', 'id=%i', $userId);

            if (!$result) {
                // If the user wasn't deleted, roll back the transaction and return a 500 status code
                DB::rollback();
                return respondWithJson($response, [], 500);
            }

            // If everything went well, commit the transaction and return a 204 response code with no data
            DB::commit();
            return respondWithJson($response, [], 204);
        } catch (Exception $e) {
            // If an exception is thrown, roll back the transaction and return a 500 status code
            DB::rollback();
            return respondWithJson($response, [], 500);
        }
    });
}