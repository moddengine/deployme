<?php
# Update ModdEngine

define("ME_SITE", "__SITEID__");
define("ME_SITE_DIR", "__SITEDIR__");

if(isset($_REQUEST['MODDENGINE_VERSION_OVERRIDE']) &&
  preg_match('/^[a-z]$/', $_REQUEST['MODDENGINE_VERSION_OVERRIDE'])) {
  $dir = realpath(__DIR__."/../moddengine.{$_REQUEST['MODDENGINE_VERSION_OVERRIDE']}");
  define("ME_DIR", "$dir");
}
if(!defined("ME_DIR"))
  define("ME_DIR", "__MEDIR__");

if(!is_file(ME_DIR."/www/index.php"))
  die("<h2>ModdEngine Version does not exist on this server</h2>");

require(ME_DIR."/www/index.php");
