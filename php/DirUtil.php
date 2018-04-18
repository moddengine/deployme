<?php


namespace ModdEngine\Deploy;


class DirUtil {

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