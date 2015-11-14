<?php

session_start();

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'classes.php';

$http = new Http();

$validator = new Validator();
if (!$validator->validIps($validIps)) {
    $http->forbidden();
}

$admin = new Admin(new Database($dbConfig), new Uploader(), new Render(), $http, new FlashMessages());
$admin
    ->handleRequest()
    ->renderPage();
