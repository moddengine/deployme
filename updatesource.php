<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

use ModdEngine\Deploy\SourceUpdater;


function install() {
  chdir(__DIR__);
  system('git pull');
  system('composer install');
  require_once(__DIR__.'/loader.php');
  $ins = new SourceUpdater(isset($argv[1]) ? $argv[1] : 'udo16-webpack2');
  $out = $ins->deployLatest();
  echo "Latest Source hash: $out\n";
}

install();
