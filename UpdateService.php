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
  public $meBranch = 'udo16-webpack2';

  /** @var \mysqli|null */
  public $mysql = null;

  public function __construct($webRootDir = '../public_html') {
    $this->webRoot = $webRootDir;
  }

  function installNew() {
    $sitesDir = self::absPath(__DIR__, self::SITES_DIR);
    if(!is_dir($sitesDir)) {
      echo "Creating sites dir: $sitesDir\n";
      mkdir($sitesDir);
    }
    $sitesDir = self::absPath(__DIR__, self::SITES_DIR, '_local');
    if(!is_dir($sitesDir)) {
      echo "Creating local config dir: $sitesDir\n";
      mkdir($sitesDir);
    }
    $this->getSiteInfo();
    $this->createSiteDir();
    $this->updateDbConf();
    $this->getSiteHost();
    $this->installModdEngine();
    $this->updateAndFetch();
    $this->writeLive();
    $this->updateWebRoot();
  }

  function installModdEngine() {
    echo "Installaing a moddengine.{$this->meVer}\n";
    $installDir = realpath(__DIR__ . '/..');
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
    if($this->siteDbConf == 'local' && isset($localConf['db']['pass'])) {
      $this->siteDbPass = $localConf['db']['pass'];
    } elseif(isset($siteConf['db']['pass'])) {
      $this->siteDbPass = $siteConf['db']['pass'];
    }
    if(isset($siteConf['db']['db'])) {
      $this->siteDbName = $siteConf['db']['db'];
    }
    if($forceUpdate || !$this->siteDbUser || !$this->siteDbPass || $this->siteDbName) {
      $res = false;
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
          $this->mysql = $mysql;
          echo "MySQL Connection: Ok\n";
          $res = $mysql->query('SELECT folderid FROM folder WHERE folderid = 0');
        }
        $ok = trim(strtolower(readline("Apply Database Settings (yes/no):")));
      } while($ok != 'y' && $ok != 'yes');
      if($this->mysql && !$res || $res->num_rows == 0)
        $this->createFolderTable($this->mysql);
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
    $db->query('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";');
    $db->query(<<<END_CREATE
CREATE TABLE `folder` (
  `folderid` INT(32) UNSIGNED NOT NULL,
  `alias` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL,
  `name` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL,
  `parentid` INT(31) UNSIGNED NOT NULL DEFAULT '0',
  `inherit` TINYINT(2) UNSIGNED NOT NULL DEFAULT '1',
  `folderpath` VARCHAR(250) COLLATE utf8_unicode_ci NOT NULL,
  `attr` TEXT COLLATE utf8_unicode_ci NOT NULL,
  `hostalias` VARCHAR(250) COLLATE utf8_unicode_ci NOT NULL,
  `shared` TINYINT(2) UNSIGNED NOT NULL DEFAULT '0',
  `search` TINYINT(2) UNSIGNED NOT NULL DEFAULT '0',
  `searchpath` VARCHAR(250) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `style` TINYINT(2) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
END_CREATE
    );
    echo "Created Folder Table\n";
    $db->query(<<<END_INSERT
INSERT INTO `folder` (`folderid`, `alias`, `name`, `parentid`, `inherit`, `folderpath`, `attr`, `hostalias`, `shared`, `search`, `searchpath`, `style`) VALUES
(0, '', 'Common', 0, 0, '', '{}', '', 0, 0, '', 1),
(1, 'admin', 'Admin', 0, 0, 'admin', '', '', 0, 0, '', 0);
END_INSERT
    );
    echo "Created Common & Admin Folders\n";
    $db->query(<<<END_CREATE
CREATE TABLE `folderperm` (
  `folderid` INT(32) UNSIGNED NOT NULL,
  `groupid` BIGINT(64) UNSIGNED NOT NULL,
  `typeid` INT(16) UNSIGNED NOT NULL,
  `level` TINYINT(8) UNSIGNED NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
END_CREATE
    );
    echo "Created FolderPerm Table\n";
    $db->query(<<<END_INSERT
INSERT INTO `folderperm` (`folderid`, `groupid`, `typeid`, `level`) VALUES
(0, 0, 40, 4),
(0, 1, 40, 4),
(0, 0, 41, 4),
(0, 1, 41, 4),
(0, 0, 44, 4),
(0, 1, 44, 4),
(0, 0, 45, 4),
(0, 1, 45, 4),
(0, 0, 47, 4),
(0, 1, 47, 4),
(0, 0, 1001, 4),
(0, 1, 1001, 4),
(0, 0, 1010, 4),
(0, 1, 1010, 4),
(0, 0, 1100, 4),
(0, 1, 1100, 4),
(0, 0, 1101, 4),
(0, 1, 1101, 4);
END_INSERT
    );
    echo "Created basic guest permissions\n";
    $db->query(<<<END_CREATE
CREATE TABLE `conf` (
  `namespace` VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL,
  `folder` INT(64) UNSIGNED NOT NULL DEFAULT '0',
  `key` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL,
  `value` TEXT COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`namespace`,`folder`,`key`),
  INDEX `namespace` (`namespace`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
END_CREATE
    );
    echo "Created conf (config) table\n";
  }

  function getSiteHost() {
    if($this->mysql) {
      $r = $this->mysql->query("SELECT value FROM conf WHERE folder = 0 
          AND namespace = 'siteconfig' AND `key` = 'robotshost'");
      if($r && $row = $r->fetch_assoc())
        $this->siteHost = $row['value'];


    }
    $v = trim(readline("Live Hostname ($this->siteHost): "));
    if(strlen($v) > 0) $this->siteHost = $v;
    if($this->mysql) {
      $value = $this->mysql->escape_string($this->siteHost);
      $this->mysql->query("INSERT INTO `conf` (`namespace`,`folder`,`key`,`value`) " .
        "VALUES ('siteconfig', '0','robotshost', '$value') ON DUPLICATE KEY UPDATE `value`='$value';");
    }
  }

  function createSiteDir() {
    $dir = $this->getSiteDirPath();
    echo "Checking Site Dir:: $dir\n";
    if(!is_dir($dir)) mkdir($dir);
    if(!is_dir("$dir/data")) mkdir("$dir/data");
    if(!is_dir("$dir/attach")) mkdir("$dir/attach");
    if(!is_file("$dir/plug.{$this->meVer}.json"))
      copy(__DIR__ . "/template/plug.json", "$dir/plug.{$this->meVer}.json");
    echo "Site directory created: $dir\n";
  }

  function updateAndFetch() {
    echo "Creating updated.{$this->meVer} file\n";
    touch($this->getSiteDirPath() . "/updated.{$this->meVer}");
    echo "Fetching Admin Base\n";
    file_get_contents('https://' . str_replace('.', '_', $this->siteHost) . ".myudo.net/admin/");
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
    $htaccess = file_get_contents(__DIR__ . "/template/.htaccess");
    file_put_contents("$root/.htaccess", $this->applyTemplate($htaccess));
    $indexphp = file_get_contents(__DIR__ . "/template/index.php");
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