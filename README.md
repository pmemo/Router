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

Route::get('/:name', function($data) {
  echo 'Hello '.$data['name'];
});

Route::run();
```

## Documentation

### Defining routes

Methods: **get**, **post**, **put**, **patch**, **delete**

```php
Route::get('/url', function() { ... });
Route::post('/url', function($data) { ... });
```

Using classes

```php
Route::get('/url', 'Path/To/Class/File@dynamicMethodName');
Route::post('/url', 'Path/To/Class/File@staticMethodName');
```

```php
Route::get('/hello/:name/:money', function($data) {
  echo 'Hello '.$data['name'].' - you have '. $data['money'].'$!';
});

Route::post('/earn/<money>', 'MoneyClass@earn');
```

### Groups

You can separate logic using routes grouping method

```php
Route::group('/base', function(){
  Route::get('/:url', function($data) {
    echo 'URL: /base/'.$data['url'];
  });

  Route::post('', 'SomeClass@someMethod');
});
```

### Status

Handle http request status

```php
Route::status(404, function(){
  echo 'Page not found!';
});
```

### Middlewares

Protect access to routes by adding middlewares to route or group. If false then returns http status code 403.

```php
Route::get('/', 'SomeClass@someMethod')->middleware(false); // 403
Route::post('/', 'SomeClass@someMethod')->middleware(function(){ return true; });
Route::get('/', 'SomeClass@someMethod')->middleware('SomeClass1@someMethod')->middleware('SomeClass2@someMethod');

Route::group('/url', function() { ... })->middleware(true);
```

Handle custom status if access denied

```php
Route::get('/', function() { ... })->middleware(false, 401);
Route::status(401, function(){ ... });
```

### Redirect

Simple redirect function

```php
Route::redirect('/url');
```
