<?php
namespace AIWA\Infrastructure\WP;

final class Options {
  public const KEY = 'aiwa_settings';

  /** @return array{provider:string, model:string, api_key_enc:string} */
  public static function get(): array {
    $defaults = [
      'provider' => 'openai',
      'model' => 'gpt-5.2',
      'api_key_enc' => '',
    ];
    $v = get_option(self::KEY, []);
    $v = is_array($v) ? $v : [];
    return array_merge($defaults, $v);
  }

  /** @param array<string,mixed> $settings */
  public static function update(array $settings): bool {
    return update_option(self::KEY, $settings, false);
  }
}