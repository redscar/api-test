Casino Loyalty API
===================

Introduction
------------

The Casino Loyalty API is a RESTful API that allows users to create and manage loyalty points for their customers.

Installation
------------

To install the Casino Loyalty API, please follow these steps:

1.  Clone the repository to your local machine.


`git clone https://github.com/your_username/casino_loyalty.git`

2.  Install dependencies using Composer.

`composer install`

3. Create the database structure. The Schema files are located in `db_structure`

4.  Configure the database settings in the `src/config.php` file.
```php
return [
    'db_host' => 'localhost',
    'db_name' => 'database_name',
    'db_user' => 'database_user',
    'db_pass' => 'database_password'
];
```

5.  Start the server.

`php -S localhost:8080 -t public/`

Tests
------
All tests are located within`tests/testRoutes.php`. To run the tests use the following command 
`./vendor/bin/phpunit --verbose tests/testRoutes.php`

Routes
------

`GET /users`
------------

### Description

This endpoint retrieves a list of users with pagination support.

### Query Parameters

*   `page` (optional): the page number to retrieve. Defaults to 1.
*   `per_page` (optional): the number of users to retrieve per page. Defaults to 10.

### Response

Returns a JSON array of user objects with the following properties:

*   `id` (integer): the unique ID of the user
*   `name` (string): the name of the user
*   `email` (string): the email address of the user
*   `points` (integer): the number of loyalty points the user has accumulated

### Examples

#### Request

`GET /users?page=2&per_page=20`

#### Response

`HTTP/1.1 200 OK Content-Type: application/json`
```json
[
  {
    "id": 21,
    "name": "John Doe",
    "email": "johndoe@example.com",
    "points": 3500
  },
  {
    "id": 22,
    "name": "Jane Doe",
    "email": "janedoe@example.com",
    "points": 1200
  },
  ...
]

```
`POST /users`
-------------

### Description

This endpoint creates a new user with the provided name and email.

### Request Parameters

*   `name` (required): the name of the user
*   `email` (required): the email address of the user

### Response

Returns a JSON object with the following properties:

*   `id` (integer): the unique ID of the new user
*   `name` (string): the name of the new user
*   `email` (string): the email address of the new user
*   `points` (integer): the initial number of loyalty points the new user has accumulated (always 0)

### Examples

#### Request

`POST /users Content-Type: application/x-www-form-urlencoded  name=John+Doe&email=johndoe@example.com`

#### Response

```
POST /users
Content-Type: application/x-www-form-urlencoded

name=John+Doe&email=johndoe@example.com
```
```json
{
  "id": 23,
  "name": "John Doe",
  "email": "johndoe@example.com",
  "points": 0
}
```


Earn and Redeem Points API
--------------------------

This API provides the ability for users to earn and redeem points. The following endpoints are available:

### POST /users/{id}/earn

This endpoint allows users to earn points. The following parameters are required:

*   `id`: the ID of the user who will be earning points
*   `points`: the number of points to be earned
*   `description`: a brief description of why the user is earning points

#### Example Request

```http request
POST /users/123/earn HTTP/1.1
Host: example.com
Content-Type: application/x-www-form-urlencoded

points=10&description=Visited+the+casino+today

```
#### Example Response

`HTTP/1.1 200 OK Content-Type: application/json`
```json
{
    "message": "Points earned successfully",
    "data": {
        "user_id": 123,
        "points": 10,
        "description": "Visited the casino today"
    }
}

```
#### Possible Outcomes

*   `200 OK`: The points were successfully earned.
*   `400 Bad Request`: The request was missing required parameters or had invalid values.
*   `404 Not Found`: The user ID provided does not exist in the system.

### POST /users/{id}/redeem

This endpoint allows users to redeem points. The following parameters are required:

*   `id`: the ID of the user who will be redeeming points
*   `points`: the number of points to be redeemed
*   `description`: a brief description of why the user is redeeming points

#### Example Request

```http request
POST /users/123/redeem HTTP/1.1
Host: example.com
Content-Type: application/x-www-form-urlencoded

points=5&description=Redeemed+points+for+prize
```
#### Example Response

`HTTP/1.1 200 OK Content-Type: application/json`
```json
{
    "message": "Points redeemed successfully",
    "data": {
        "user_id": 123,
        "points": 5,
        "description": "Redeemed points for prize"
    }
}

```
#### Possible Outcomes

*   `200 OK`: The points were successfully redeemed.
*   `400 Bad Request`: The request was missing required parameters or had invalid values.
*   `403 Forbidden`: The user does not have enough points to redeem.
*   `404 Not Found`: The user ID provided does not exist in the system.

Sure! Here's an example README for the DELETE /users/{id} endpoint:

DELETE /users/{id}
==================

Deletes the user with the specified ID from the database.

Request
-------

*   HTTP Method: DELETE
*   URI: `/users/{id}`
*   URL Parameters:
    *   `id` (required): The ID of the user to delete.
*   Request Body: None

Response
--------

### Success

*   HTTP Status Code: `204 No Content`
*   Response Body: None

### Errors

*   HTTP Status Code: `404 Not Found`
    *   The specified user ID does not exist in the database.
*   HTTP Status Code: `500 Internal Server Error`
    *   The server encountered an error while processing the request.

Example
-------

### Request

`DELETE /users/1234`

### Response

`HTTP/1.1 204 No Content`