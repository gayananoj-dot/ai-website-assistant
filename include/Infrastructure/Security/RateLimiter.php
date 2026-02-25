<?php
namespace AIWA\Infrastructure\Security;

/**
 * Simple per-user rate limiter using transients.
 * This prevents accidental spam + surprise billing on BYO keys.
 */
final class RateLimiter {
  /**
   * @param string $action Logical action name: 'suggest', 'analyze', 'apply'
   * @param int $limit Max requests per window
   * @param int $windowSeconds Window size
   */
  public static function check(string $action, int $limit = 10, int $windowSeconds = 300): array {
    $userId = get_current_user_id();
    if ($userId <= 0) return ['ok' => false, 'error' => 'Not authenticated'];

    $key = 'aiwa_rl_' . $action . '_' . $userId;

    $state = get_transient($key);
    if (!is_array($state)) {
      $state = ['count' => 0, 'reset_at' => time() + $windowSeconds];
    }

    $now = time();
    if (!isset($state['reset_at']) || $now >= (int)$state['reset_at']) {
      $state = ['count' => 0, 'reset_at' => $now + $windowSeconds];
    }

    $count = (int)($state['count'] ?? 0);
    if ($count >= $limit) {
      return [
        'ok' => false,
        'error' => 'Rate limit exceeded. Please try again later.',
        'retry_after_seconds' => max(1, (int)$state['reset_at'] - $now),
      ];
    }

    $state['count'] = $count + 1;
    set_transient($key, $state, $windowSeconds);

    return ['ok' => true, 'remaining' => max(0, $limit - $state['count']), 'reset_at' => (int)$state['reset_at']];
  }
}