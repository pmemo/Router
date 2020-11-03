<?php

require_once 'Route.php';

Route::get('/<name>', function($data){
    echo 'Hello ' . $data['name'];
});

Route::run();