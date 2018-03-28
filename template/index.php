<?php
# Managed by moddengine updateme

define("ME_SITE", "__SITEID__");
define("ME_SITE_DIR", "__SITEDIR__");

if(isset($_REQUEST['MODDENGINE_VERSION_OVERRIDE']) &&
  preg_match('/^([a-z])$/', $_REQUEST['MODDENGINE_VERSION_OVERRIDE'], $m)) {
  $dir = realpath(__DIR__ . "/../moddengine.{$m[1]}");
  define("ME_DIR", "$dir");
  define("ME_VER", $m[1]);
}
if(!defined("ME_DIR"))
  define("ME_DIR", "__MEDIR__");
if(!defined("ME_VER"))
  define("ME_VER", "__MEVER__");

if(!is_file(ME_DIR . "/www/index.php"))
  die("<h2>ModdEngine Version does not exist on this server</h2>");

require(ME_DIR . "/www/index.php");
