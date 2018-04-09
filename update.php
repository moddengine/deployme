<?php


namespace DeployME;


function update() {
  chdir(__DIR__);
  system("git pull");
  require_once('UpdateService.php');
  $us = new UpdateService();
  $us->installNew();
}

update();
