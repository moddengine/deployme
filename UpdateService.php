<?php
namespace UpdateME;

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


class UpdateService {

  const SITES_DIR = '../sites.me';
  const MODDENG_COMMENT = '# Managed by moddengine updateme';

  public $siteId = null;
  public $siteHost = null;
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
    $sitesDir = realpath(__DIR__."/".self::SITES_DIR);
    if(!is_dir($sitesDir)) {
      echo "Creating sites dir: $sitesDir\n";
      mkdir($sitesDir);
    }
    $this->getSiteInfo();
    $this->createSiteDir();
    $this->updateDbConf();
    $this->installModdEngine();
    $this->updateAndFetch();
    $this->writeLive();
    $this->updateWebRoot();
  }

  function installModdEngine() {
    echo "Installaing a moddengine.{$this->meVer}";
    $installDir = realpath(__DIR__ . '/..');
    chdir($installDir);
    $update = is_dir("moddengine.{$this->meVer}");
    if(!$update)
    system("git clone git@github.com:moddross/plugin.git moddengine.{$this->meVer}");
    chdir("moddengine.{$this->meVer}");
    if($update)
      system('git pull');
    system("php install.php");
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
      }
    }
  }

  function loadSiteInfo() {
    if(is_file($this->webRoot . "/index.php")) {
      $indexphp = file_get_contents($this->webRoot . "/index.php");
      if(strpos($indexphp, self::MODDENG_COMMENT) !== false &&
        preg_match('/ define\("ME_SITE", "([a-zA-Z0-9]+)"\);/', $indexphp, $m)) {
        $this->siteId = $m[1];
        preg_match('/ define\("ME_VER", "([a-zA-Z0-9]+)"\);/', $indexphp, $m);
        $this->meVer = $m[1];
      }
    }
  }

  function getSiteDirPath() {
    return self::absPath(__DIR__ . "/" . self::SITES_DIR . "/{$this->siteId}");
  }

  function getWebRootDirPath() {
    return self::absPath(__DIR__ . "/" . $this->webRoot);
  }


  function updateDbConf($forceUpdate = false) {
    $this->getSiteInfo();
    $localConfFile = __DIR__ . "/" . self::SITES_DIR . "/_local/conf.json";
    $siteConfFile = __DIR__ . "/" . self::SITES_DIR . "/{$this->siteId}/conf.json";
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
        $v = trim(readline("Database Name ({$this->siteDbName}): "));
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
      } while($ok != 'y' && $ok != 'yes');
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
    echo "Checking Site Dir:: $dir";
    if(!is_dir($dir)) mkdir($dir);
    if(!is_dir("$dir/data")) mkdir("$dir/data");
    if(!is_dir("$dir/attach")) mkdir("$dir/attach");
    if(!is_file("$dir/plug.{$this->meVer}.json"))
      copy(__DIR__ . "/template/plug.json", "$dir/plug.{$this->meVer}.json");
    echo "Site directory created: $dir\n";
  }

  function updateAndFetch() {
    echo "Creating updated.{$this->meVer} file\n";
    touch($this->getSiteDirPath()."/updated.{$this->meVer}");
    echo "Fetching Admin Base\n";
    file_get_contents('https://'.str_replace('.','_',$this->siteHost).".myudo.net/admin/");
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
    if(strpos($livehtaccess, self::MODDENG_COMMENT) !== false) {
      //Move Old Webroot - not moddengine by updateme
      rename("$root", "$root.old");
      mkdir($root);
    }
    $htaccess = file_get_contents(__DIR__."/template/.htaccess");
    file_put_contents("$root/.htaccess", $this->applyTemplate($htaccess));
    $indexphp = file_get_contents(__DIR__."/template/index.php");
    file_put_contents("$root/index.php", $this->applyTemplate($indexphp));
    file_put_contents($this->getSiteDirPath()."/live", $this->meVer);
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
        realpath(__DIR__."../moddengine.{$this->meVer}"), $this->meVer],
      $template);
  }


  static function absPath() {
    $path = implode(DIRECTORY_SEPARATOR, func_get_args());
    $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
    $absolutes = array();
    foreach ($parts as $part) {
      if ('.' == $part) continue;
      if ('..' == $part) {
        array_pop($absolutes);
      } else {
        $absolutes[] = $part;
      }
    }
    return implode(DIRECTORY_SEPARATOR, $absolutes);
  }
}