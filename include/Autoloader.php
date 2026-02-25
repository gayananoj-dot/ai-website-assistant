<?php
namespace AIWA;

final class Autoloader {
  public static function register(): void {
    spl_autoload_register(function (string $class): void {
      // Only load our namespace
      if (strpos($class, 'AIWA\\') !== 0) return;

      $relative = substr($class, strlen('AIWA\\'));
      $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
      $path = AIWA_PLUGIN_DIR . '/includes/' . $relative . '.php';

      if (file_exists($path)) require_once $path;
    });
  }
}
?>