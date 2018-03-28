<?php


namespace UpdateME;

if(!function_exists('readline')) {
  $hSTD_IN = fopen('php://stdin', 'r');
  function readline($prompt) {
    global $hSTD_IN;
    echo "\n$prompt ";
    return rtrim(fgets($hSTD_IN));
  }
}


class UpdateService {

  CONST SITES_DIR = '../sites.me';

  public $siteId = null;
  public $siteDbUser = null;
  public $siteDbPass = null;
  public $siteDbName = null;
  public $siteDbConf = 'local';

  public $webRootDir = null;

  public $meVer = 'a';

  public function __construct($webRootDir = '../public_html') {
    $this->webRoot = realpath(__DIR__ . "/$webRootDir");
  }

  function installNew() {
    if(!is_dir(self::SITES_DIR)) mkdir(self::SITES_DIR);
    $this->getSiteName();
    $this->createSiteDir();
    $this->updateDbConf();
    $this->defaultPlugs();
  }

  function installModdEngine() {
    $installDir = realpath(__DIR__ . '/..');
    chdir($installDir);
    system("git clone https://github.com/moddross/moddengine moddengine.{$this->meVer}");
    chdir("moddengine.$newVer");
    system("php install.php");
  }


  function getSiteName() {
    if($this->siteId === null) {
      if(is_file($this->webRoot . "/index.php")) {
        $indexphp = file_get_contents($this->webRoot . "/index.php");
        if(strpos('# Update ModdEngine') !== false &&
          preg_match('/define\("ME_SITE", "([a-zA-Z0-9]+)"\);/', $indexphp, $m)) {
          $this->siteId = $m[1];
        }
      }
      if($this->siteId) $default = $this->siteId;
      do {
        echo "\n";
        $site = trim(readline("Enter ModdEngine site id ($default):"));
        if(strlen($site) == 0) $site = $default;
      } while(strlen($site) >= 0);
      $this->siteId = $site;
    }
  }

  function getSiteDirPath() {
    return realpath($siteDir = __DIR__ . "/" . self::SITES_DIR . "/{$this->siteId}");
  }


  function updateDbConf($forceUpdate = false) {
    $this->getSiteName();
    $localConfFile = __DIR__ . "/" . self::SITES_DIR . "/_local/conf.json";
    $siteConfFile = __DIR__ . "/" . self::SITES_DIR . "/{$this->siteId}/conf.json";
    $localConf = is_file($localConfFile) ?
      json_decode(file_get_contents($localConfFile), true) : [];
    $siteConf = is_file($siteConfFile) ?
      json_decode(file_get_contents($siteConfFile), true): [];
    if(isset($localConf['db']['user'])) {
      $this->siteDbUser = $localConf['db']['user'];
    } elseif(isset($siteConf['db']['user'])) {
      $this->siteDbUser = $siteConf['db']['user'];
      $this->siteDbConf = 'site';
    }
    if($this->siteDbConf == 'local' && isset($localConf['db']['user'])) {
      $this->siteDbUser = $localConf['db']['user'];
    } elseif(isset($siteConf['db']['user'])) {
      $this->siteDbUser = $siteConf['db']['user'];
    }
    if(isset($siteConf['db']['db'])) {
      $this->siteDbName = $siteConf['db']['db'];
    }
    if($forceUpdate || !$this->siteDbUser || !$this->siteDbPass || $this->siteDbName) {
      do {
        echo "MySQL Database Configuration\n";
        echo "============================\n";
        $v = trim(readline("Username ({$this->siteDbUser}): "));
        if(strlen($v) !== 0) $this->siteDbUser = $v;
        $v = trim(readline("Password ({$this->siteDbPass}): "));
        if(strlen($v) !== 0) $this->siteDbPass = $v;
        $v = trim(readline("Password ({$this->siteDbName}): "));
        if(strlen($v) !== 0) $this->siteDbName = $v;
        $mysql = new \mysqli('localhost', $this->siteDbUser, $this->siteDbPass, $this->siteDbName);
        if($mysql->connect_error) {
          echo "MySQL Connection: FAILED - {$mysql->connect_error}\n";
        } else {
          $res = $mysql->query('SELECT folderid FROM folder WHERE folderid = 0');
          if(!$res || $res->num_rows == 0)
            $this->createFolderTable($mysql);
          echo "MySQL Connection: Ok\n";
        }
        $ok = trim(strtolower(readline("Apply Database Settions (yes/no):")));
      } while($ok != 'y' || $ok != 'yes');
      $siteConf['db']['db'] = $this->siteDbUser;
      if($this->siteDbConf == 'local') {
        $localConf['db']['user'] = $this->siteDbUser;
        $localConf['db']['pass'] = $this->siteDbPass;
      } else {
        $siteConf['db']['user'] = $this->siteDbUser;
        $siteConf['db']['pass'] = $this->siteDbPass;
      }
      file_put_contents($siteConfFile, json_encode($siteConf, JSON_PRETTY_PRINT));
      file_put_contents($localConfFile, json_encode($localConf, JSON_PRETTY_PRINT));
    }
  }

  function createFolderTable(\mysqli $db) {
    echo 'FIXME: Create folder table, common folder, and admin folder\n';
    echo 'FIXME: Create folder Perms, every can read bldpage\n';
    echo 'FIXME: Create immix table, with new home bldpage\n';
  }

  function createSiteDir() {
    $dir = $this->getSiteDirPath();
    if(!is_dir($dir)) mkdir($dir);
    if(!is_dir("$dir/data")) mkdir("$dir/data");
    if(!is_dir("$dir/attach")) mkdir("$dir/attach");
    if(!is_file("$dir/plug.{$this->meVer}.json"))
      copy(__DIR__ . "/template/plug.json", "$dir/plug.{$this->meVer}.json");
    echo "Site directory setup: $dir\n";
  }


}