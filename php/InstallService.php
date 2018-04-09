<?php

namespace ModdEngine\Deploy;
class InstallService {

  /** @var DeployService */
  public $deploy;

  function __construct() {
    $this->deploy = new DeployService();
  }

  function installNew() {
    $sitesDir = DeployService::absPath(__DIR__, DeployService::SITES_DIR);
    if(!is_dir($sitesDir)) {
      echo "Creating sites dir: $sitesDir\n";
      mkdir($sitesDir);
    }
    $sitesDir = DeployService::absPath(__DIR__, DeployService::SITES_DIR, '_local');
    if(!is_dir($sitesDir)) {
      echo "Creating local config dir: $sitesDir\n";
      mkdir($sitesDir);
    }
    $this->deploy->getSiteInfo();
    $this->deploy->createSiteDir();
    $this->deploy->updateDbConf();
    $this->deploy->getSiteHost();
    $this->deploy->installModdEngine();
    $this->deploy->updateWebRoot();
    $this->deploy->updateAndFetch();
    $this->deploy->writeLive();
  }


}