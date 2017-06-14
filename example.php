<?php

include_once __DIR__.'/vendor/autoload.php';
include_once __DIR__.'/src/func.php';

var_dump($_ENV);
var_dump(secEnv('XXX'));
var_dump(secEnv('DB_PASSWORD'));
