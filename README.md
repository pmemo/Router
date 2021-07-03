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
require 'Router.php';

Router::get('/:name', function($req, $res) {
  echo 'Hello '.$req->params('name');
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
- params(name) - in URL :param
- body(name) - $_POST etc. body
- query(name) - $_GET
- file(name) - $_FILES
- set(name, value) - provide value through middleware
- get(name) - get provided value

Res methods:
- status(code)
- json(message)
code is http status code, message_array is json encoded array

example
```php
Router::get('/api/hello/:name', function($req, $res) {
  $res->status(200)->json(['message' => 'Hello '.$req->param('name')]);
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

Router::post('/earn/:money', 'MoneyClass@earn');
```

### Using middlewares

You can separate logic using routes grouping method

```php
Router::use(function() {
  echo 'First root level middleware';
});

Router::use(function() {
  echo 'First root level middleware';
  return true; // required if you want to call next middleware
}, function() {
  echo 'Second root level middleware';
});

Router::use('/base', function(){
  Router::get('/:url', function($req, $res) {
    echo 'URL: /base/'.$data['url'];
  });

  Router::post('', 'SomeClass@someMethod');
});

Router::get('/user/:id', 'MiddlewareClass@middlewareMethod', 'ControllerClass@ControllerMethod');
```
You can use multiple middlewares separated by coma. 
**IMPORTANT** you need `return true` if you want call next middleware 