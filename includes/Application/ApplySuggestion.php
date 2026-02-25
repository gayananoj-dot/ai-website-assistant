<?php
namespace AIWA\Application;

/**
 * Applies supported suggestion types.
 * All apply operations are initiated by an editor/admin and should create WP revisions when modifying posts.
 */
final class ApplySuggestion {
  /** @param array<string,mixed> $suggestion */
  public function run(int $postId, array $suggestion): array {
    $post = get_post($postId);
    if (!$post) return ['error' => 'Post not found'];

    $type = isset($suggestion['type']) ? (string)$suggestion['type'] : '';
    if ($type === '') return ['error' => 'Missing suggestion.type'];

    return match ($type) {
      'seo_meta' => $this->applySeoMeta($postId, $suggestion),
      'image_alt' => $this->applyImageAlt($suggestion),
      'cta' => $this->applyCta($postId, $suggestion),
      'rewrite_blocks' => $this->applyRewriteBlocks($postId, $suggestion),
      default => ['error' => 'Unsupported suggestion type: ' . $type],
    };
  }

  /** @param array<string,mixed> $s */
  private function applySeoMeta(int $postId, array $s): array {
    $newTitle = isset($s['title']) ? sanitize_text_field((string)$s['title']) : '';
    $newDesc  = isset($s['meta_description']) ? sanitize_text_field((string)$s['meta_description']) : '';

    if ($newTitle === '' && $newDesc === '') {
      return ['error' => 'No title or meta_description to apply'];
    }

    // Ensure WP revision by updating post (title/excerpt triggers revisions when enabled).
    $update = ['ID' => $postId];
    if ($newTitle !== '') $update['post_title'] = $newTitle;

    // Fallback storage for non-Yoast sites (excerpt)
    if ($newDesc !== '') $update['post_excerpt'] = $newDesc;

    $res = wp_update_post($update, true);
    if (is_wp_error($res)) return ['error' => $res->get_error_message()];

    $yoastUpdated = ['title' => false, 'metadesc' => false];

    if ($this->isYoastActive()) {
      if ($newTitle !== '') {
        update_post_meta($postId, '_yoast_wpseo_title', $newTitle);
        $yoastUpdated['title'] = true;
      }
      if ($newDesc !== '') {
        update_post_meta($postId, '_yoast_wpseo_metadesc', $newDesc);
        $yoastUpdated['metadesc'] = true;
      }
    }

    return [
      'ok' => true,
      'applied' => 'seo_meta',
      'post_id' => $postId,
      'updated_post' => $update,
      'yoast' => [
        'active' => $this->isYoastActive(),
        'updated' => $yoastUpdated,
        'meta_keys' => [
          'title' => '_yoast_wpseo_title',
          'metadesc' => '_yoast_wpseo_metadesc',
        ],
      ],
    ];
  }

  private function isYoastActive(): bool {
    return defined('WPSEO_VERSION') || class_exists('WPSEO_Options') || class_exists('Yoast\\WP\\SEO\\Main');
  }

  /** @param array<string,mixed> $s */
  private function applyImageAlt(array $s): array {
    $items = $s['items'] ?? [];
    if (!is_array($items) || empty($items)) return ['error' => 'No image_alt items'];

    $updated = 0;
    foreach ($items as $it) {
      if (!is_array($it)) continue;
      $id = (int)($it['attachment_id'] ?? 0);
      $alt = sanitize_text_field((string)($it['alt'] ?? ''));
      if ($id <= 0 || $alt === '') continue;

      update_post_meta($id, '_wp_attachment_image_alt', $alt);
      $updated++;
    }

    return [
      'ok' => true,
      'applied' => 'image_alt',
      'updated_count' => $updated,
    ];
  }

  /** @param array<string,mixed> $s */
  private function applyCta(int $postId, array $s): array {
    $placements = $s['placements'] ?? [];
    if (!is_array($placements) || empty($placements)) return ['error' => 'No CTA placements'];

    $first = is_array($placements[0]) ? $placements[0] : [];
    $text = sanitize_text_field((string)($first['cta_text'] ?? 'Contact us'));
    $url  = esc_url_raw((string)($first['cta_url'] ?? '/contact'));

    $post = get_post($postId);
    if (!$post) return ['error' => 'Post not found'];

    // Idempotency: prevent duplicate CTA apply
    if (str_contains((string)$post->post_content, 'data-aiwa="cta"')) {
      return ['error' => 'CTA already applied (AIWA marker detected).'];
    }

    // MVP: append a paragraph block with a link (safe and reversible via revisions)
    $ctaBlock =
      "\n<!-- wp:paragraph -->\n" .
      "<p data-aiwa=\"cta\"><a href=\"" . esc_url($url) . "\">" . esc_html($text) . "</a></p>\n" .
      "<!-- /wp:paragraph -->\n";

    $newContent = (string)$post->post_content . $ctaBlock;

    $res = wp_update_post(['ID' => $postId, 'post_content' => $newContent], true);
    if (is_wp_error($res)) return ['error' => $res->get_error_message()];

    return [
      'ok' => true,
      'applied' => 'cta',
      'post_id' => $postId,
      'cta' => ['text' => $text, 'url' => $url],
    ];
  }

  /** @param array<string,mixed> $s */
  private function applyRewriteBlocks(int $postId, array $s): array {
    $items = $s['items'] ?? [];
    if (!is_array($items) || empty($items)) return ['error' => 'No rewrite items'];

    $post = get_post($postId);
    if (!$post) return ['error' => 'Post not found'];

    $blocks = function_exists('parse_blocks') ? parse_blocks((string)$post->post_content) : [];
    if (empty($blocks)) return ['error' => 'No blocks found (rewrite requires block editor content).'];

    // Build lookup by client_id -> rewrite item
    $map = [];
    foreach ($items as $it) {
      if (!is_array($it)) continue;
      $cid = (string)($it['client_id'] ?? '');
      $after = (string)($it['after'] ?? '');
      $beforeHash = (string)($it['before_hash'] ?? '');
      if ($cid === '' || $after === '' || $beforeHash === '') continue;
      $map[$cid] = ['after' => $after, 'before_hash' => $beforeHash];
    }
    if (empty($map)) return ['error' => 'No valid rewrite items'];

    $applied = 0;

    $walk = function (array &$b) use (&$walk, &$applied, $map) {
      foreach ($b as &$blk) {
        if (!is_array($blk)) continue;

        $name = (string)($blk['blockName'] ?? '');
        if (!in_array($name, ['core/paragraph', 'core/heading'], true)) {
          if (!empty($blk['innerBlocks']) && is_array($blk['innerBlocks'])) $walk($blk['innerBlocks']);
          continue;
        }

        $inner = trim((string)($blk['innerHTML'] ?? ''));
        $text = trim(wp_strip_all_tags($inner));
        if ($text === '') {
          if (!empty($blk['innerBlocks']) && is_array($blk['innerBlocks'])) $walk($blk['innerBlocks']);
          continue;
        }

        $cid = (string)($blk['attrs']['__aiwaClientId'] ?? '');
        if ($cid === '') {
          $cid = substr(hash('sha256', $name . '|' . $inner), 0, 16);
        }

        if (!isset($map[$cid])) {
          if (!empty($blk['innerBlocks']) && is_array($blk['innerBlocks'])) $walk($blk['innerBlocks']);
          continue;
        }

        $currentHash = hash('sha256', $text);
        if (!hash_equals($map[$cid]['before_hash'], $currentHash)) {
          // Content changed since suggestion; skip for safety
          continue;
        }

        $afterText = wp_kses_post($map[$cid]['after']);

        if ($name === 'core/paragraph') {
          $blk['innerHTML'] = '<p>' . $afterText . '</p>';
          $blk['innerContent'] = [$blk['innerHTML']];
          $applied++;
        } elseif ($name === 'core/heading') {
          $level = (int)($blk['attrs']['level'] ?? 2);
          $level = max(1, min(6, $level));
          $blk['innerHTML'] = '<h' . $level . '>' . $afterText . '</h' . $level . '>';
          $blk['innerContent'] = [$blk['innerHTML']];
          $applied++;
        }

        // Persist stable ID on the block for future suggestions.
        $blk['attrs']['__aiwaClientId'] = $cid;

        if (!empty($blk['innerBlocks']) && is_array($blk['innerBlocks'])) $walk($blk['innerBlocks']);
      }
    };

    $walk($blocks);

    if ($applied === 0) {
      return ['error' => 'No rewrites applied (content may have changed since suggestion).'];
    }

    $newContent = function_exists('serialize_blocks') ? serialize_blocks($blocks) : (string)$post->post_content;

    $res = wp_update_post(['ID' => $postId, 'post_content' => $newContent], true);
    if (is_wp_error($res)) return ['error' => $res->get_error_message()];

    return ['ok' => true, 'applied' => 'rewrite_blocks', 'count' => $applied, 'post_id' => $postId];
  }
}
