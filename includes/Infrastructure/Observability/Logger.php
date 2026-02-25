<?php
namespace AIWA\Infrastructure\Observability;

/**
 * Minimal logger using error_log. Never logs secrets.
 */
final class Logger {
  /** @param array<string,mixed> $context */
  public static function info(string $msg, array $context = []): void {
    self::write('INFO', $msg, $context);
  }

  /** @param array<string,mixed> $context */
  public static function warn(string $msg, array $context = []): void {
    self::write('WARN', $msg, $context);
  }

  /** @param array<string,mixed> $context */
  public static function error(string $msg, array $context = []): void {
    self::write('ERROR', $msg, $context);
  }

  /** @param array<string,mixed> $context */
  private static function write(string $level, string $msg, array $context): void {
    foreach (['apiKey','api_key','Authorization','authorization'] as $k) {
      if (isset($context[$k])) $context[$k] = '[redacted]';
    }
    error_log('[AIWA][' . $level . '] ' . $msg . ' ' . wp_json_encode($context));
  }
}
