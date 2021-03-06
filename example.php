<?php

require_once 'Router.php';

Router::get('/:name', function($req){
    echo 'Hello ' . $req->param('name');
});

Router::run();
