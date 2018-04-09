<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);


use ModdEngine\Deploy\UpdateService;


function update() {
  chdir(__DIR__);
  system('git pull');
  system('composer install');
  require_once(__DIR__.'/loader.php');
  $us = new UpdateService();
  $us->update();
}

update();
