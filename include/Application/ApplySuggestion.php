<?php
namespace AIWA\Application;

final class ApplySuggestion {
  /** @param array<string,mixed> $suggestion */
  public function run(int $postId, array $suggestion): array {
    $post = get_post($postId);
    if (!$post) return ['error' => 'Post not found'];

    $type = isset($suggestion['type']) ? (string)$suggestion['type'] : '';
    if ($type === '') return ['error' => 'Missing suggestion.type'];

    switch ($type) {
      case 'seo_meta':
        return $this->applySeoMeta($postId, $suggestion);

      case 'image_alt':
        return $this->applyImageAlt($suggestion);

      case 'cta':
        return $this->applyCta($postId, $suggestion);

      default:
        return ['error' => 'Unsupported suggestion type: ' . $type];
    }
  }

    /** @param array<string,mixed> $s */
  private function applySeoMeta(int $postId, array $s): array {
    $newTitle = isset($s['title']) ? sanitize_text_field((string)$s['title']) : '';
    $newDesc  = isset($s['meta_description']) ? sanitize_text_field((string)$s['meta_description']) : '';

    if ($newTitle === '' && $newDesc === '') {
      return ['error' => 'No title or meta_description to apply'];
    }

    // Always create a WP revision by updating the post (WP will revision on title/excerpt/content changes).
    $update = ['ID' => $postId];
    if ($newTitle !== '') $update['post_title'] = $newTitle;

    // Fallback meta description storage for non-Yoast sites (kept for compatibility)
    if ($newDesc !== '') $update['post_excerpt'] = $newDesc;

    $res = wp_update_post($update, true);
    if (is_wp_error($res)) return ['error' => $res->get_error_message()];

    $yoastUpdated = ['title' => false, 'metadesc' => false];

    // If Yoast is active, persist into Yoast post meta keys too.
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
    // Common, stable detection patterns for Yoast SEO plugin.
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

    // MVP: append a paragraph block with a link (safe and reversible via revisions)
    $ctaBlock = "\n<!-- wp:paragraph -->\n<p><a href=\"" . esc_url($url) . "\">" . esc_html($text) . "</a></p>\n<!-- /wp:paragraph -->\n";
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
}