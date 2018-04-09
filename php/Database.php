<?php

namespace ModdEngine\Deploy;

use mysqli;

class Database {

  /** @var mysqli */
  protected $link;

  protected $name;

  function __construct() {
  }

  /**
   * Connect to database
   * @param $user
   * @param $pass
   * @return bool
   */
  function connect($user, $pass) {
    $link = @new mysqli('localhost',$user, $pass);
    if(!$link->connect_error) {
      $this->link = $link;
      return true;
    } else {
      return false;
    }
  }

  /**
   * Switch to database
   * @param $db
   * @return bool
   */
  function switchTo($db) {
    if($this->link) {
      $this->name = $db;
      return $this->link->select_db($db);
    }
    return false;
  }

  function isEmpty() {
    if($this->link) {
     return $this->link->query("SELECT * FROM `information_schema`.`tables` 
    WHERE `table_schema` = '{$this->name}';")->num_rows == 0;
      }
      return false;
  }

  function createBaseTables() {
    if(!$this->link) return false;
    $db = $this->link;
    $db->query('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";');
    $db->query(<<<END_CREATE
CREATE TABLE `folder` (
 `folderid` INT(32) UNSIGNED NOT NULL AUTO_INCREMENT,
 `alias` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL,
 `name` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL,
 `parentid` INT(31) UNSIGNED NOT NULL DEFAULT '0',
 `inherit` TINYINT(2) UNSIGNED NOT NULL DEFAULT '1',
 `folderpath` VARCHAR(250) COLLATE utf8_unicode_ci NOT NULL,
 `hostalias` VARCHAR(250) COLLATE utf8_unicode_ci NOT NULL,
 `shared` TINYINT(2) UNSIGNED NOT NULL DEFAULT '0',
 `attr` TEXT COLLATE utf8_unicode_ci NOT NULL,
 `style` TINYINT(2) UNSIGNED NOT NULL DEFAULT '0',
 `search` TINYINT(2) UNSIGNED NOT NULL DEFAULT '0',
 `searchpath` VARCHAR(250) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
 PRIMARY KEY (`folderid`),
 KEY `parentid` (`parentid`),
 KEY `alias` (`alias`),
 KEY `folderpath` (`folderpath`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
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
 `level` TINYINT(8) UNSIGNED NOT NULL,
 PRIMARY KEY (`folderid`,`groupid`,`typeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
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
 KEY `namespace` (`namespace`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
END_CREATE
    );
    echo "Created conf (config) table\n";
  }

  function setRobotsHost($host) {
    if(!$this->link) return false;
    $host = $this->mysql->escape_string($host);
    $this->mysql->query("INSERT INTO `conf` (`namespace`,`folder`,`key`,`value`) " .
      "VALUES ('siteconfig', '0','robotshost', '$host') ON DUPLICATE KEY UPDATE `value`='$value';");
    return true;
  }

}