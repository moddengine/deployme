<?php


use ModdEngine\Deploy\UpdateService;


function update() {
  chdir(__DIR__);
  system('git pull');
  system('composer install');
  $us = new UpdateService();
  $us->update();
}

update();
