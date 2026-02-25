<?php
namespace AIWA\Application\Schema;

/**
 * Minimal schema validator (MVP).
 * Ensures we have a suggestions array with typed entries.
 */
final class SuggestionValidator {
  /** @param mixed $json */
  public static function validate($json): array {
    if (!is_array($json)) return ['ok' => false, 'error' => 'Response is not JSON object'];

    if (!isset($json['suggestions']) || !is_array($json['suggestions'])) {
      return ['ok' => false, 'error' => 'Missing suggestions array'];
    }

    foreach ($json['suggestions'] as $s) {
      if (!is_array($s) || empty($s['type']) || !is_string($s['type'])) {
        return ['ok' => false, 'error' => 'Invalid suggestion entry'];
      }
    }

    return ['ok' => true];
  }
}
