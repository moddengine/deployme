<?php


namespace ModdEngine\Deploy;


class SourceUpdater {

  protected $root = '/udo-server';

  protected $binDir = 'moddengine';

  protected $repoDir = 'git';

  protected $gitSource = [
    'moddengine' => 'git@github.com:moddross/moddengine.git',
    'plugin' => 'git@github.com:moddross/plugin.git',
    'theme' => 'git@github.com:moddross/theme.git',
  ];

  protected $destDir = [
    'moddengine' => '/',
    'plugin' => 'plugin/',
    'theme' => 'plugin/theme/',
  ];

  protected $branch;

  function __construct($branch) {
    $this->branch = $branch;
  }

  function pull() {
    foreach($this->gitSource as $name => $gitUrl) {
      $repoDir = DirUtil::absPath($this->root, $this->repoDir, $name);
      if(is_dir($repoDir)) {
        chdir($repoDir);
        system("git pull");
        system("git checkout {$this->branch}");
        system("git pull");
      } else {
        system("git clone -b {$this->branch} $gitUrl $repoDir");
      }
    }
  }

  function getHash() {
    $gitHashes = [];
    foreach($this->gitSource as $name => $gitUrl) {
      $repoDir = DirUtil::absPath($this->root, $name);
      chdir($repoDir);
      $gitHashes[] = trim(`git rev-parse HEAD`);
    }
    if(strlen(implode('', $gitHashes)) !== 120)
      throw new \RuntimeException("Unable to calculate hash");
    return substr(sha1(implode(';',$gitHashes)), 0, 20);
  }

  function deployLatest() {
    $hash = $this->getHash();
    $dir = DirUtil::absPath($this->root, $this->binDir, $hash);
    if(!is_dir($dir)) {
      foreach($this->gitSource as $name => $gitUrl) {
        chdir(DirUtil::absPath($this->root, $this->repoDir, $name));
        $destDir = DirUtil::absPath($dir, $this->destDir[$name]);
        system("git checkout-index --prefix $destDir -a");
      }
      chdir($dir);
      system("composer install --no-dev -o");
      system("yarn --production");
      $geoDB = "GeoLite2-City.mmdb";
      link(
        DirUtil::absPath($this->root, 'lib', '$geoDB'),
        DirUtil::absPath($dir, 'lib', $geoDB)
      );
    }
    return $hash;
  }

}
