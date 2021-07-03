<?php

require_once 'Router.php';

Router::use('/user', function(){
    Router::get('/error', function($req, $res){
        $res->status(500);
    });

    Router::get('/:name', function($req, $res){
        $res->status(200)->json('hello '.$req->params('name'));
    });
});

Router::run();