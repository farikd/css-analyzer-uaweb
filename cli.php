<?php

require('includes/analyzer.php');
require('includes/querypath/src/qp.php');

function autoloader($class)
{
    $file = 'includes' . DIRECTORY_SEPARATOR . 'php-css-parser' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, explode('\\', $class)) . '.php';
    include($file);
}

spl_autoload_register('autoloader');


$a = new analyzer();

//$a->analyze('http://ya.ru');
//$a->analyze('http://aleksandriya.com.ua');
$a->analyze('http://uawebchallenge.com/', 2);

