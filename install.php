<?php


namespace UpdateME;


function install() {
  chdir(__DIR__);
  system("git pull");
  require_once('UpdateService.php');
  $us = new UpdateService();
  $us->installNew();
}

install();
