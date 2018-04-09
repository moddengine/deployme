<?php


use ModdEngine\DeployME\UpdateService;


function install() {
  chdir(__DIR__);
  system('git pull');
  system('composer install');
  require_once('loader.php');
  $us = new UpdateService();
  $us->installNew();
}

install();
