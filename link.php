<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'classes.php';

$link = new Link(new Database($dbConfig), new Http());
$link->redirectToLink();
