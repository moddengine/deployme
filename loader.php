<?php
require_once(__DIR__."/vendor/autoload.php");

$loader = new Aura\Autoload\Loader();
$loader->addPrefix("ModdEngine\\Deploy", "/php/");
$loader->register();
