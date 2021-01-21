<?php

require __DIR__.'/../vendor/autoload.php';

$app = new \Coeliac\Images\Application(__DIR__.'/../');

$app->bootstrap();

$response = $app->handleRequest(
    \Illuminate\Http\Request::capture()
);

$response->send();
