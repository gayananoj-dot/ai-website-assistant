<?php
namespace AIWA\Infrastructure\Crypto;

/**
 * Encrypts secrets at-rest using WP salts as key material.
 * Note: This mitigates accidental leakage (e.g., DB dumps) but cannot protect from a fully-compromised admin+filesystem.
 */
final class Secrets {
  private static function key(): string {
    $material =
      (defined('AUTH_KEY') ? (string) AUTH_KEY : 'auth_key') . '|' .
      (defined('SECURE_AUTH_KEY') ? (string) SECURE_AUTH_KEY : 'secure_auth_key');
    return hash('sha256', $material, true); // 32 bytes
  }

  public static function encrypt(string $plain): string {
    if ($plain === '') return '';
    if (!function_exists('openssl_encrypt')) return $plain; // fallback (not ideal)
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) return '';
    return base64_encode($iv . $tag . $cipher);
  }

  public static function decrypt(string $enc): string {
    if ($enc === '') return '';
    if (!function_exists('openssl_decrypt')) return $enc; // fallback
    $raw = base64_decode($enc, true);
    if ($raw === false || strlen($raw) < 28) return '';
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
  }
}
