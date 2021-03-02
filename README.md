## Description

A simple one-file PHP routing library.

## Setup

Create .htaccess file and redirect all to index.php

```htaccess
Options -Indexes

Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Methods "POST, GET, PATCH, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Auth"

RewriteEngine On
RewriteCond %{REQUEST_METHOD} !OPTIONS
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]

# protect db file if you need
RewriteCond %{REQUEST_URI} (.*).sqlite3 [NC]
RewriteRule ^(.*)$ [R=404,L]

DirectoryIndex index.php
```

Define routes in index.php and run the router

```php
require 'Route.php';

Router::get('/:name', function($req, $res) {
  echo 'Hello '.$req->param('name');
});

Router::run();
```

## Documentation

### Defining routes

Methods: **get**, **post**, **put**, **patch**, **delete**
Arguments: **$req**, **res** - *optional*

```php
Router::get('/url', function() { ... });
Router::post('/url', function($req, $res) { ... });
```

Req methods:
- header(name) - http headers
- param(name) - in URL :param
- body(name) - $_POST etc. body
- query(name) - $_GET
- file(name) - $_FILES
- set(name, value) - provide value through middleware
- get(name) - get provided value

Res methods:
- send(code, message_array)
code is http status code, message_array is json encoded array

example
```php
Router::get('/api/hello/:name', function($req, $res) {
  $res->send(200, ['message' => 'Hello '.$req->param('name')]);
});
```

Using classes

```php
Router::get('/url', 'Path/To/Class/File@dynamicMethodName');
Router::post('/url', 'Path/To/Class/File@staticMethodName');
```

```php
Router::get('/hello/:name/:money', function($req, $res) {
  echo 'Hello '.$req->param('name').' - you have '. $req->param('money').'$!';
});

Router::post('/earn/<money>', 'MoneyClass@earn');
```

### Groups

You can separate logic using routes grouping method

```php
Router::group('/base', function(){
  Router::get('/:url', function($req, $res) {
    echo 'URL: /base/'.$data['url'];
  });

  Router::post('', 'SomeClass@someMethod');
});
```

### Status

Handle http request status

```php
Router::status(404, function(){
  echo 'Page not found!';
});
```

### Middlewares

Protect access to routes by adding middlewares to route or group. If false then returns http status code 403.
Or to make changes in requests and providing values to next middlewares or routes.

```php
Router::get('/', 'SomeClass@someMethod')->middleware(false); // 403
Router::post('/', 'SomeClass@someMethod')->middleware(function(){ return true; });
Router::get('/', 'SomeClass@someMethod')->middleware('SomeClass1@someMethod')->middleware('SomeClass2@someMethod');

Router::group('/url', function() { ... })->middleware(true);
```

Handle custom status if access denied

```php
Router::get('/', function() { ... })->middleware(false, 401);
Router::status(401, function(){ ... });
```

### Redirect

Simple redirect function

```php
Router::redirect('/url');
```
