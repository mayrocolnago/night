# NIGHT Framework

<img align="left" border="0" src="https://raw.githubusercontent.com/mayrocolnago/night/refs/heads/master/assets/www/img/logo.png" width="70" height="auto">
NIGHT is a lightweight, flexible PHP framework designed for rapid application development with a focus on API creation, resource management, and asynchronous processing. This framework provides a simple yet powerful structure for building web applications and APIs with minimal configuration.


## Table of Contents

- [Getting Started](#getting-started)
- [Core Concepts](#core-concepts)
- [Routing System](#routing-system)
- [API Development](#api-development)
- [API Responses Format](#api-responses-format)
- [Database Operations](#database-operations)
- [Dynamic Content Loading](#dynamic-content-loading)
- [Storage Module](#storage-module)
- [Queue Processing](#queue-processing)
- [Utility Variables](#utility-variables)
- [Utility Functions](#utility-functions)
- [Project Configuration](#project-configuration)
- [Runtime Configuration](#runtime-configuration)

## Getting Started

### Tech stack ready-to-go

- Docker
- PHP 7.4 or higher
- MySQL/MariaDB database
- Apache web server with mod_rewrite enabled

### Installation

1. Clone the repository to your web server directory
2. Create a configuration file `.config.json` in the project's root directory
3. Set up your database connection in the configuration file
4. Use docker to speed up the server setup

### Basic Configuration Example

```json
{
  "dbstring": "mysql:host=localhost;dbname=yourdb;charset=utf8",
  "dbuser": "username",
  "dbpass": "password",
  "DEVELOPMENT": true
}
```

## Core Concepts

NIGHT is built around a few core concepts:

1. **Class-based routing**: URLs map to classes and methods (just like Laravel)
2. **Autoloading**: Classes are automatically loaded from the resources directory
3. **API-first design**: Built-in support for JSON API responses
4. **Resource management**: Dynamic loading of HTML, CSS, and JS content
5. **Asynchronous processing**: Queue-based handling of tasks like emails and notifications

## Routing System

The framework uses a simple routing system based on class and function parameters in the URL:

```
https://yourdomain.com/{class}/{function}?param1=value1&param2=value2
```

For example:
- `https://site.com/` calls the `index()` method of the `site` class (ak.a. `\site::index()`)
- `https://site.com/page` calls the `index()` method of the `page` class (ak.a. `\page::index()`)
- `https://site.com/app/api` calls the `api()` method of the `app` class (ak.a. `\app::api()`)
- `https://site.com/app/other/api` calls the `api()` method of the `other` class on the `app` namespace (ak.a. `\app\other::api()`)
- `https://site.com/app/more/another/func` calls the `func()` method of the `another` class on the `app\more` namespace (ak.a. `\app\more\another::func()`)

If no function is specified, `index()` method is called by default. And if nothing is called on the root, `site` class is called by default.

### Creating a Basic Route

Create a new PHP file `test.php` in the `resources` directory:

```php
class test {
    use \openapi;

    public static function index($data=[]) {
        ?><div>
            Hello World
        </div><?php
        // It will print out a page with "Hello World" on a div
    }

    public static function api($data=[]) {
        return ["message" => "Hello", "from" => "test", "params" => $data];
        // This will return a JSON response like:
        // {"result":3,"data":{"message":"Hello","from":"test","params":{"param":"value"}}}
        // (3 as of the amount of information on `data`. Useful to count results of a database query dump)
    }
}
```

Now you can access:
- `https://yourdomain.com/test` - Returns HTML "Hello World"
- `https://yourdomain.com/test/api?param=value` - Returns JSON response

No need to configure extra route files or any sort of thing. It all works automaticly.

## API Development

NIGHT makes it easy to create APIs using the `openapi` trait:

```php
class api {
    use \openapi;

    public static function string($data=[]) { 
        return 'Hello World'; // Returns {"result":"Hello World"}
    }

    public static function number($data=[]) { 
        return 42; // Returns {"result":42}
    }

    public static function array($data=[]) { 
        return ["name" => "John", "age" => 30]; // Returns {"result":2,"data":{"name":"John","age":30}}
    }

    public static function test($data=[]) { 
        return ["result" => 2, "something" => 3]; // Returns {"result":2,"something":3} on result level 
        //(because there is "result" key on the array)
    }

    public static function params($data=[]) { 
        return $data; // Returns all parameters passed in the request {"result":1,"data":[...data]}
    }
}
```

### API Responses Format

The framework automatically formats API responses as JSON with the following structure:

```json
{
  "result": integer,  // return result
  "state":  1,        // standard connection result
  "header": null,     // Content-Type header return
  "policy": null,     // CORS header return
  "page":   integer,  // current page of listing/pagination
  "data":   ...       // returned data (array boolean or text)
}
```

- `result`: Can be a number (count of items), boolean, or string
- `data`: Contains the data of a result if it is an array or object
- `elapsed`: Time taken to process the request in seconds
- `http`: HTTP status code
- `state`: Always 1 for successful connection responses

**result** Parameter followup guide:

| **result** | Description |
| ------ | ------ |
| -400 | Missing parameters |
| -401 | Authentication failed/unavailable |
| -403 | No access to this area |
| -404 | Method not found |
| -405 | Method not allowed |
| -406 | Method not accepted in this context |
| -407 | Missing authentication token |
| -408 | Timeout/Execution time expired |
| -409 | Conflict/Method not completed |
| -411 | Invalid parameters |
| -500 | Internal communication error |
| -501 | Method not implemented |
| -502 | Static function cannot be reached |
| -503 | Client database cannot be reached |
| -504 | External resource took too long to respond |
| -505 | Problems with data storage |
| [-2 ... < -9] | Method/API specific errors, useful for identifying cutoff point |
| -1 | Failed to execute action, various reasons |
| 0 | Null return |
| 1 | Successful return |
| [2 ... > 10] | Successful return with the number of results returned |


Other parameters:

| Parameter | Description |
| ------ | ------ |
| state | Default: 1. To identify that the connection was successful with the server/API |
| header | Identifier for applying `header("Content-Type: application/json")`. Typically null or false if not applied |
| policy | Identifier for applying `header("Access-Control-Allow-Origin: *")`. Typically null or false if not applied |
| error | This key will appear as `true` if result has a negative value |
| page | Current page which the API is filtering the results `?page=1` |
| data | Data return parameter |

## Database Operations

### PDO Class

The framework includes a PDO wrapper class for database operations:

```php
// Create a table
$fields = pdo_create("users",[
    "id" => "int NOT NULL AUTO_INCREMENT",
    "name" => "varchar(255) NOT NULL",
    "email" => "varchar(255) NOT NULL"
]); // returns an array with the fields keys ['id','name','email']

// Execute a query
$result = pdo_query("SELECT * FROM users WHERE id = ?", [1]);

// Fetch results
$users = pdo_fetch_array("SELECT * FROM users");

// Insert data
$id = pdo_insert("users", ["name" => "John", "email" => "john@example.com"]); //returns last insert id

// Get last insert ID
$lastId = pdo_insert_id();
```

It opcionally also allows you to instantiate multiple connections if you need:

```php
// Default connection
$users = pdo_fetch_array("SELECT * FROM database1.users");

// Other connection
$another = new pdoclass('mysql:host=localhost:3306;dbname=database2','user','pass');
$another->pdo_query("SELECT * FROM database2.users");

// Default will still continue working
```

### CRUD Operations

The `crud` trait provides a simple way to implement CRUD operations:

```php
class cart {
    use \crud;
    use \openapi;
    
    public static $crudTable = "cart";

    // Method `create` will be available because of `crud` trait
    // Along with all others from `crud` trait

    // To limit the available APIs, set: `public static $openapiOnly = ['create','read'];`
    
    public static function js($data=[]) {
        ?><script>
            function add_to_cart(productid) {
                /* We create an error handler to deal with unsuccessful requests */
                let errorhandler = function(obj){
                    console.log('Could not add to cart',obj);
                };
                /* We can use `post` function from `\globals` module */            
                post("cart/create",{"product_id":productid},function(data){
                    /* We always check first if there is no errors */
                    if(!data || data.error) return errorhandler(data);
                    /* Then we do everything we need to do */
                    console.log('Product added to cart',data);
                },function(error){
                    /* In case of a connection error */
                    return errorhandler(error);
                },function(always){
                    /* Always execute after running the above functions */
                });

                /* Learn more about permission handling by looking at `crud.php` file */
            }
        </script><?php
    }
}
```

Another usages:
```php
// Creates a row with columns `name` and `email` filled with `John` and `john@example.com` respectively
$userId = \module::create(["name" => "John", "email" => "john@example.com"]);
// Returns {"result":1, ...} meaning how much rows were inserted

// Gets a row where `name` is `John`
$users = \module::read([":name" => "John"]);
// Returns {"result":1,"data":{"id":1,"name":"John","email":"john@example.com"}, ...}

// Gets a set of rows where `name` is `John`
$users = \module::list([":name" => "John%"]);
// Returns {"result":2,"data":[{"id":1,"name":"John Doe","email":"john@example.com"},{"id":2,"name":"John Cena","email":"cena@example.com"}], ...}

// Updates `name` to `John Doe` where `id` is `1`
\module::update([":id" => 1, "name" => "John Doe"]);
// Returns {"result":1, ...} meaning how much rows were affected

// Delete something
\module::delete([":id" => 1]);
// Returns {"result":1, ...} meaning how much rows were deleted
```

Use ":" for searching and "|" for `OR` conditioning

### Dynamic Content Loading

The resources module allows you to dynamically load HTML, CSS, and JavaScript content from a entire folder:

File: `/resources/app.php`

```php
class app {
    use \openapi;

    public static $openapiOnly = ['index']; /* opcional */

    public static function index($data=[]) {
        // This will load all CSS, HTML, and JS from the "app" namespace.
        exit(\resources::show(__CLASS__)); /* __CLASS__ being "app", so it will load all files from the folder "app" if it exists */
    }
}
```

File: `/resources/app/main.php`

```php
namespace app; /* namespace required in this subfolder case */

class main {

    public static function css($data=[]) {
        ?><style>
            #main { background-color: #f0f0f0; }
        </style><?php
    }

    public static function html($data=[]) {
        ?><div id="main" class="screen homescreen">
            <h1>My Application</h1>
            <a href="#" onclick="main_goanotherscreen();">Go another screen</a>
        </div><?php
    }

    public static function js($data=[]) {
        ?><script>
            function main_goanotherscreen() {
                switchtab('#anotherscreen');
            }

            $(window).on('screen_onload',function(state){ /* The detection of screen changing */
                if(state.to !== '#home') return;
                switchtab('#main'); /* Start by bringing the user to the main screen */
            });

            $(window).on('onload',function() { /* The load event of the app */
                console.log("Application loaded");
            });
        </script><?php
    }
}
```

File: `/resources/app/anotherscreen.php`

```php
namespace app;

class anotherscreen {

    public static function html($data=[]) {
        ?><div id="anotherscreen" class="screen">
            <h1>This screen</h1>
            <a href="#" onclick="anotherscreen_goback();">Go back main</a>
        </div><?php
    }

    public static function js($data=[]) {
        ?><script>
            function anotherscreen_goback() {
                switchtab('#main',true); /* passing true will effect as going back */
            }
        </script><?php
    }
}
```

All the resources will be brought together and cached on the user's browser so that it can be loaded **faster** on the next visit and run **offline**. All this from a simple static HTML powerful interface.

You can also replicate the structure to create different apps and panels in the same project.

This feature is particularly useful for creating modular applications where different components can provide their own CSS, HTML, and JavaScript.

## Storage Module

The storage module provides a simple way to handle file uploads and storage:

```php
// Upload a file
$fileId = \storage::send([
    'f' => 'profile', // File prefix
    'p' => 'users/', // Path
    'e' => 'jpg' // Extension can be auto detected
]);

// Get file contents
$fileContents = \storage::get_contents('/storage/users/profile_123.jpg');

// Get file URL
$fileUrl = \storage::url() . 'users/profile_123.jpg';
```

### Upload Methods

The storage module supports various upload methods:

1. **Form upload**:
```html
<form method="post" action="/storage/send" enctype="multipart/form-data">
    <input type="file" name="file">
    <input type="hidden" name="f" value="profile">
    <input type="hidden" name="p" value="users/">
    <button type="submit">Upload</button>
</form>
```

2. **Base64 upload**:
```php
$base64Data = "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD...";
$result = post('storage/send', [
    'base64' => 1,
    'file' => $base64Data,
    'f' => 'profile',
    'p' => 'users/'
]);
```

3. **URL upload**:
```php
$result = post('storage/send', [
    'fromurl' => 1,
    'file' => 'https://example.com/image.jpg',
    'f' => 'profile',
    'p' => 'users/'
]);
```

### JavaScript Upload Helper

The framework includes a JavaScript helper for file uploads:

```html
<input type="file" id="fileUploader">

<script>
    bindupload('#fileUploader',{ 
            f: 'profile', 
            p: 'users/' 
        },function(onstart) {
            console.log("Upload started", onstart);
        },function(ondone) {
            console.log("Upload completed", ondone);
            if(ondone.result)
                console.log("File URL:", ondone.url);
        });
</script>
```

## Queue Processing

NIGHT includes several handlers for asynchronous processing of tasks:

### Email

```php
// Send an email
\email::send(
    'recipient@example.com',
    'Subject',
    '<p>Email content</p>',
    null, // Attachments
    strtotime('+5 minutes') // If suppressed the email is sent instantly using a thread mechanism to execute the queue
);
```

### Push Notifications

```php
// Send a push notification
\push::send(
    'user123', // Recipient ID
    'Notification body',
    'action:view', // Command
    'Notification title',
    strtotime('now'), // If suppressed the push is sent instantly using a thread mechanism to execute the queue
    ['tag' => 'value'] // Additional tags
);
```

### Telegram

```php
// Send a Telegram message
\telegram::send(
    'Hello',
    null, // Chat ID (uses default if null)
    'sendMessage', // Method
    ['parse_mode' => 'HTML'], // Parameters
    strtotime('now') // If suppressed the message is sent instantly using a thread mechanism to execute the queue
);
```

### SMS

```php
// Send an SMS
\sms::send(
    '+1234567890', // Phone number
    'Hello', // Message
    null, // Tags
    strtotime('now') // If suppressed the SMS is sent instantly using a thread mechanism to execute the queue
);
```

## Utility Variables

NIGHT includes a set of useful variables on the environment:

- *CONSTANT* `REPODIR` - Returns the path of the project root directory.
- *CONSTANT* `THISURL` - Returns the URL of the project.
- *GLOBAL* `$_SERVER['DEVELOPMENT']` - Returns `true` if the project is in development mode.
- *GLOBAL* `$_SERVER['PRODUCTION']` - Returns `true` if the project is in production mode.

## Utility Functions

NIGHT includes a wide range of utility functions in the `globals` class:

### HTTP Requests

```php
// Send a POST request
$response = curlsend(
    'https://api.example.com/endpoint',
    ['param' => 'value'],
    30, // Timeout
    'json_encode' // Content type - opcional - default is multipart/form-data
);
```

### Date and Time

```php
// Format a date
$formattedDate = datetostrtotime('01/12/2023'); //2023-12-01

// Calculate remaining time
$timeString = remainingstr(strtotime('tomorrow'), strtotime('now')); //1 day
```

### String Manipulation

```php
// Remove accents
$cleanString = rmA('áéíóú'); //aeiou

// Mask middle of string
$maskedString = str_maskmiddle('1234567890'); //123****890

// Format CPF/CNPJ
$formattedDoc = formatar_cpf_cnpj('12345678901'); // 123.456.789-01
```

### Validation

```php
// Validate CPF/CNPJ for Brazilian documents
$isValid = valida_cpf_cnpj('12345678901'); // true or false
```

## Project Configuration

NIGHT uses a simple JSON-based configuration system.

Create a file named `.config.json` in the root directory:

```json
{
  "dbstring": "mysql:host=localhost;dbname=yourdb;charset=utf8",
  "dbuser": "username",
  "dbpass": "password",
  "DEVELOPMENT": true,
  "push_project_id": "your-firebase-project-id",
  "push_client_email": "firebase-adminsdk-email@project.iam.gserviceaccount.com",
  "push_private_key_id": "private-key-id",
  "push_private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
  "telegram_botapi": "your-telegram-bot-api-token",
  "telegram_chatlog": "your-telegram-chat-id"
}
```

Other possible names for the configuration files are:

- `config/.config.{project}.json` can be on upper levels of the directory tree
- `config/config.{project}.json` can be on upper levels of the directory tree
- `.config.{project}.json` can be on upper levels of the directory tree
- `config.{project}.json` can be on upper levels of the directory tree
- `.config.json` must be on the same directory as the project
- `config.json` must be on the same directory as the project

> **Note:** The framework is gonna search for the configuration file up to 5 levels up in the directory tree.

## Runtime Configuration

You can also set and get configuration values at runtime:

**PHP Functions** for global configurations

```php
// Set a configuration value
setconfig('default_site_currency', 'USD');

// Get a configuration value
$currency = getconfig('default_site_currency', 'USD'); // 'USD' is the default value is none was set
```

**JS Functions** for user configurations

```js
// Set a configuration value
setitem('myconf', '1');

// Get a configuration value
let conf = getitem('myconf');
```

> To save global configurations with `setitem` use `@` as prefix, e.g. `@myconf`.

---

This README provides an overview of the NIGHT framework. For more detailed information, refer to the source code and examples in the repository.
