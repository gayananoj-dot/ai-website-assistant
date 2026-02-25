<?php
namespace AIWA\Application;

/**
 * Deterministic analyzer (fast, no AI).
 * Extracts headings, images missing alt text, simple heuristic scores, and rewrite candidates.
 */
final class AnalyzePost {
  /** @return array<string,mixed> */
  public function run(int $postId): array {
    $post = get_post($postId);
    if (!$post) return ['error' => 'Post not found'];

    $content = (string) $post->post_content;
    $blocks = function_exists('parse_blocks') ? parse_blocks($content) : [];

    $headings = $this->extractHeadings($blocks, $content);
    $images = $this->extractImages($blocks);

    $missingAlt = [];
    foreach ($images as $img) {
      $alt = $img['attachment_id'] ? get_post_meta($img['attachment_id'], '_wp_attachment_image_alt', true) : '';
      if (trim((string)$alt) === '') $missingAlt[] = $img;
    }

    $rewriteCandidates = $this->extractRewriteCandidates($blocks);

    $title = (string) $post->post_title;
    $titleLen = mb_strlen($title);
    $wordCount = str_word_count(wp_strip_all_tags($content));

    // Read Yoast (if present) for context; analyzer does not require Yoast to be active.
    $yoastTitle = get_post_meta($postId, '_yoast_wpseo_title', true);
    $yoastDesc  = get_post_meta($postId, '_yoast_wpseo_metadesc', true);

    // Simple heuristic scoring (MVP)
    $seoScore = 100;
    if ($titleLen < 20 || $titleLen > 65) $seoScore -= 15;
    if ($wordCount < 250) $seoScore -= 10;
    if (count($headings) === 0) $seoScore -= 15;
    $seoScore = max(0, min(100, $seoScore));

    $a11yScore = 100;
    if (count($missingAlt) > 0) $a11yScore -= min(40, count($missingAlt) * 10);
    $a11yScore = max(0, min(100, $a11yScore));

    $uxScore = 100;
    if ($wordCount > 1600) $uxScore -= 10;
    if (count($headings) < 2) $uxScore -= 10;
    $uxScore = max(0, min(100, $uxScore));

    return [
      'post' => [
        'id' => (int) $postId,
        'title' => $title,
        'type' => (string) $post->post_type,
      ],
      'yoast' => [
        'active' => $this->isYoastActive(),
        'title' => is_string($yoastTitle) ? $yoastTitle : '',
        'metadesc' => is_string($yoastDesc) ? $yoastDesc : '',
      ],
      'metrics' => [
        'title_length' => $titleLen,
        'word_count' => $wordCount,
        'headings_count' => count($headings),
        'images_count' => count($images),
        'missing_alt_count' => count($missingAlt),
        'rewrite_candidates_count' => count($rewriteCandidates),
      ],
      'headings' => $headings,
      'missing_alt' => array_slice($missingAlt, 0, 30),
      'rewrite_candidates' => $rewriteCandidates, // capped in extractor
      'scores' => [
        'seo' => $seoScore,
        'a11y' => $a11yScore,
        'ux' => $uxScore,
      ],
    ];
  }

  private function isYoastActive(): bool {
    return defined('WPSEO_VERSION') || class_exists('WPSEO_Options') || class_exists('Yoast\\WP\\SEO\\Main');
  }

  /** @return array<int,array{level:int,text:string}> */
  private function extractHeadings(array $blocks, string $fallbackHtml): array {
    $out = [];

    $walk = function (array $b) use (&$walk, &$out) {
      foreach ($b as $blk) {
        if (!is_array($blk)) continue;
        if (($blk['blockName'] ?? '') === 'core/heading') {
          $level = (int)($blk['attrs']['level'] ?? 2);
          $text = trim(wp_strip_all_tags($blk['innerHTML'] ?? ''));
          if ($text !== '') $out[] = ['level' => $level, 'text' => $text];
        }
        if (!empty($blk['innerBlocks']) && is_array($blk['innerBlocks'])) {
          $walk($blk['innerBlocks']);
        }
      }
    };

    if (!empty($blocks)) $walk($blocks);

    if (empty($out)) {
      if (preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $fallbackHtml, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
          $out[] = ['level' => (int)$hit[1], 'text' => trim(wp_strip_all_tags($hit[2]))];
        }
      }
    }

    return array_slice($out, 0, 50);
  }

  /** @return array<int,array{attachment_id:int, url:string}> */
  private function extractImages(array $blocks): array {
    $imgs = [];

    $walk = function (array $b) use (&$walk, &$imgs) {
      foreach ($b as $blk) {
        if (!is_array($blk)) continue;
        if (($blk['blockName'] ?? '') === 'core/image') {
          $id = (int)($blk['attrs']['id'] ?? 0);
          $url = '';
          if ($id) $url = (string) wp_get_attachment_url($id);
          $imgs[] = ['attachment_id' => $id, 'url' => $url];
        }
        if (!empty($blk['innerBlocks']) && is_array($blk['innerBlocks'])) {
          $walk($blk['innerBlocks']);
        }
      }
    };

    if (!empty($blocks)) $walk($blocks);
    return array_slice($imgs, 0, 60);
  }

  /**
   * Extracts safe rewrite candidates for MVP: core/paragraph + core/heading.
   * Uses a stable-ish client_id derived from content if not present.
   *
   * @return array<int,array{client_id:string,type:string,text:string,before_hash:string}>
   */
  private function extractRewriteCandidates(array $blocks): array {
    $out = [];

    $walk = function (array $b) use (&$walk, &$out) {
      foreach ($b as $blk) {
        if (!is_array($blk)) continue;

        $name = (string)($blk['blockName'] ?? '');
        $clientId = (string)($blk['attrs']['__aiwaClientId'] ?? '');
        $inner = trim((string)($blk['innerHTML'] ?? ''));

        if (in_array($name, ['core/paragraph', 'core/heading'], true)) {
          if ($clientId === '') {
            $clientId = substr(hash('sha256', $name . '|' . $inner), 0, 16);
          }
          $text = trim(wp_strip_all_tags($inner));
          if ($text !== '') {
            $hash = hash('sha256', $text);
            $out[] = [
              'client_id' => $clientId,
              'type' => $name,
              'text' => $text,
              'before_hash' => $hash,
            ];
          }
        }

        if (!empty($blk['innerBlocks']) && is_array($blk['innerBlocks'])) {
          $walk($blk['innerBlocks']);
        }
      }
    };

    if (!empty($blocks)) $walk($blocks);

    // Keep prompts small and suggestions manageable.
    return array_slice($out, 0, 12);
  }
}
