<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

use ModdEngine\Deploy\DeployService;


function install() {
  chdir(__DIR__);
  system('git pull');
  system('composer install');
  require_once(__DIR__.'/loader.php');
  $ins = new \ModdEngine\Deploy\InstallService();
  $ins->installNew();
}

install();
