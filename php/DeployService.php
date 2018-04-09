<?php

namespace ModdEngine\Deploy;

ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);

if(!function_exists('readline')) {
  global $hSTD_IN;
  $hSTD_IN = fopen('php://stdin', 'r');
  function readline($prompt) {
    global $hSTD_IN;
    echo "\n$prompt ";
    return rtrim(fgets($hSTD_IN));
  }
}


class DeployService {

  const SITES_DIR = '../../sites.me';
  const MODDENG_COMMENT = '# Managed by moddengine updateme';

  public $siteId = null;
  public $siteHost = null;
  public $siteDbUser = null;
  public $siteDbPass = null;
  public $siteDbName = null;
  public $siteDbConf = 'local';

  public $webRootDir = null;

  public $meVer = 'a';
  public $meBranch = 'udo16-webpack2';

  /** @var Database */
  public $db;

  public function __construct($webRootDir = '../../public_html') {
    $this->webRoot = $webRootDir;
    $this->db = new Database;
  }


  function installModdEngine() {
    echo "Installaing a moddengine.{$this->meVer}\n";
    $installDir = self::absPath(__DIR__ . '/www');
    chdir($installDir);
    $update = is_dir("moddengine.{$this->meVer}");
    if(!$update)
      system("git clone git@github.com:moddross/moddengine.git moddengine.{$this->meVer}");
    chdir("moddengine.{$this->meVer}");
    system("git checkout {$this->meBranch}");
    if($update)
      system('git pull');
    system("php install.php --skip-config --branch {$this->meBranch}");
  }


  function getSiteInfo() {
    if($this->siteId === null) {
      $this->loadSiteInfo();
      if(!$this->siteId) {
        do {
          echo "\n";
          $site = trim(readline("Enter ModdEngine site id:"));
        } while(strlen($site) == 0);
        $this->siteId = $site;
      } else {
        echo "Configuring Site: {$this->siteId}\n";
      }
    }
  }

  function loadSiteInfo() {
    $indexFile = self::absPath($this->getWebRootDirPath(), "index.php");
    if(is_file($indexFile)) {
      $indexphp = file_get_contents($indexFile);
      if(strpos($indexphp, self::MODDENG_COMMENT) !== false &&
        preg_match('/define\("ME_SITE", "([a-zA-Z0-9]+)"\);/', $indexphp, $m)) {
        $this->siteId = $m[1];
        preg_match('/define\("ME_VER", "([a-zA-Z0-9]+)"\);/', $indexphp, $m);
        $this->meVer = $m[1];
      }
    }
  }

  function getSiteDirPath() {
    return self::absPath(__DIR__, self::SITES_DIR, $this->siteId);
  }

  function getWebRootDirPath() {
    return self::absPath(__DIR__, $this->webRoot);
  }


  function updateDbConf($forceUpdate = false) {
    $this->getSiteInfo();
    $localConfFile = __DIR__ . "UpdateService.php/" . self::SITES_DIR . "/_local/conf.json";
    $siteConfFile = __DIR__ . "UpdateService.php/" . self::SITES_DIR . "/{$this->siteId}/conf.json";
    $localConf = is_file($localConfFile) ?
      json_decode(file_get_contents($localConfFile), true) : [];
    $siteConf = is_file($siteConfFile) ?
      json_decode(file_get_contents($siteConfFile), true) : [];
    if(isset($localConf['db']['user'])) {
      $this->siteDbUser = $localConf['db']['user'];
    } elseif(isset($siteConf['db']['user'])) {
      $this->siteDbUser = $siteConf['db']['user'];
      $this->siteDbConf = 'site';
    }
    if($this->siteDbConf == 'local' && isset($localConf['db']['pass'])) {
      $this->siteDbPass = $localConf['db']['pass'];
    } elseif(isset($siteConf['db']['pass'])) {
      $this->siteDbPass = $siteConf['db']['pass'];
    }
    if(isset($siteConf['db']['db'])) {
      $this->siteDbName = $siteConf['db']['db'];
    }
    if($forceUpdate || !$this->siteDbUser || !$this->siteDbPass || $this->siteDbName) {
      $populate = false;
      do {
        echo "MySQL Database Configuration\n";
        echo "============================\n";
        $v = trim(readline("Username ({$this->siteDbUser}): "));
        if(strlen($v) !== 0) $this->siteDbUser = $v;
        $v = trim(readline("Password ({$this->siteDbPass}): "));
        if(strlen($v) !== 0) $this->siteDbPass = $v;
        $v = trim(readline("Database Name ({$this->siteDbName}): "));
        if(strlen($v) !== 0) $this->siteDbName = $v;
        $connect = $this->db->connect($this->siteDbUser, $this->siteDbPass);
        $select = $this->db->switchTo($this->siteDbName);
        if(!$connect) {
          echo "MySQL Connection: FAILED - Bad user/pass\n";
        } elseif(!$select) {
          echo "MySQL Connection: FAILED - Bad database name\n";
        } else {
          echo "MySQL Connection: Ok\n";
          $populate = $this->db->isEmpty();
        }
        $ok = trim(strtolower(readline("Apply Database Settings (yes/no):")));
      } while($ok != 'y' && $ok != 'yes');
      if($populate)
        $this->db->createBaseTables();
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



  function getSiteHost() {
    if($host = $this->db->getSiteHost())
      $this->siteHost = $host;
    $v = trim(readline("Live Hostname ($this->siteHost): "));
    if(strlen($v) > 0) $this->siteHost = $v;
    if($this->db) {
      $this->db->setRobotsHost($this->siteHost);
    }
  }

  function createSiteDir() {
    $dir = $this->getSiteDirPath();
    echo "Checking Site Dir:: $dir\n";
    if(!is_dir($dir)) mkdir($dir);
    if(!is_dir("$dir/data")) mkdir("$dir/data");
    if(!is_dir("$dir/attach")) mkdir("$dir/attach");
    if(!is_file("$dir/plug.{$this->meVer}.json"))
      copy(__DIR__ . "/../template/plug.json", "$dir/plug.{$this->meVer}.json");
    file_put_contents("$dir/webroot.txt", $this->getWebRootDirPath());
    echo "Site directory created: $dir\n";
  }

  function updateAndFetch() {
    echo "Creating updated.{$this->meVer} file\n";
    touch($this->getSiteDirPath() . "/updated.{$this->meVer}");
    echo "Fetching Admin Login Page:";
    if(file_get_contents('https://' . str_replace('.', '_', $this->siteHost) . ".myudo.net/admin/")) {
      echo " Done.\n";
      echo "Compile Javascript for Site\n";
      chdir($installDir = self::absPath(__DIR__ . '/../moddengine.' . $this->meVer));
      putenv("ME_DIR={$installDir}");
      putenv("ME_SITE={$this->siteId}");
      putenv("ME_SITE_DIR={$this->getSiteDirPath()}");
      putenv("ME_VER={$this->meVer}");
      system('yarn run build');
    } else {
      die("Failed.\n");
    }
  }

  function writeLive() {
    $dir = $this->getSiteDirPath();
    file_put_contents("$dir/live.txt", $this->meVer);
  }

  function updateWebRoot() {
    // Move WebRoot to WebRoot.old or just update inplace
    $root = $this->getWebRootDirPath();
    $livehtaccess = is_file("$root/.htaccess") ?
      file_get_contents("$root/.htaccess") : "";
    if(strpos($livehtaccess, self::MODDENG_COMMENT) === false) {
      //Move Old Webroot - not moddengine by updateme
      $oldRoot = "$root.old";
      $oldId = 1;
      while(is_dir($oldRoot))
        $oldRoot = "$root.old." . $oldId++;
      echo "Relocating old $root to $oldRoot\n";
      rename("$root", $oldRoot);
      mkdir($root);
    }
    $htaccess = file_get_contents(__DIR__ . "/../.htaccess");
    file_put_contents("$root/.htaccess", $this->applyTemplate($htaccess));
    $indexphp = file_get_contents(__DIR__ . "/../template/index.php");
    file_put_contents("$root/index.php", $this->applyTemplate($indexphp));
    file_put_contents($this->getSiteDirPath() . "/live", $this->meVer);
  }

  /**
   * Replace placeholders in template with values
   *
   * @param string $template
   * @return string
   */
  function applyTemplate($template) {
    return str_replace(['__SITEID__', '__SITEDIR__', '__MEDIR__', '__MEVER__'],
      [$this->siteId, $this->getSiteDirPath(),
        self::absPath(__DIR__, "../moddengine.{$this->meVer}"), $this->meVer],
      $template);
  }


  static function absPath() {
    $path = implode(DIRECTORY_SEPARATOR, func_get_args());
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
    $absolutes = $path[0] == DIRECTORY_SEPARATOR ? [''] : [];
    foreach($parts as $part) {
      if('.' == $part) continue;
      if('..' == $part) {
        array_pop($absolutes);
      } else {
        $absolutes[] = $part;
      }
    }
    return implode(DIRECTORY_SEPARATOR, $absolutes);
  }
}