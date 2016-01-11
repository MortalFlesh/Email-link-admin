<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'classes.php';

$image = new Image(new Database($dbConfig), new Http());
$image->renderImage((int) $_GET['p']);
