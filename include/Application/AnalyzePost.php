<?php
namespace AIWA\Application;

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

    $title = (string) $post->post_title;
    $titleLen = mb_strlen($title);
    $wordCount = str_word_count(wp_strip_all_tags($content));

    // Very simple scoring heuristics (MVP)
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
      'metrics' => [
        'title_length' => $titleLen,
        'word_count' => $wordCount,
        'headings_count' => count($headings),
        'images_count' => count($images),
        'missing_alt_count' => count($missingAlt),
      ],
      'headings' => $headings,
      'missing_alt' => $missingAlt,
      'scores' => [
        'seo' => $seoScore,
        'a11y' => $a11yScore,
        'ux' => $uxScore,
      ],
    ];
  }

  /** @return array<int,array{level:int,text:string}> */
  private function extractHeadings(array $blocks, string $fallbackHtml): array {
    $out = [];

    // Prefer block extraction
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

    // Fallback: regex headings in HTML if blocks unavailable
    if (empty($out)) {
      if (preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $fallbackHtml, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
          $out[] = ['level' => (int)$hit[1], 'text' => trim(wp_strip_all_tags($hit[2]))];
        }
      }
    }

    return $out;
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
          if ($id) {
            $url = (string) wp_get_attachment_url($id);
          }
          $imgs[] = ['attachment_id' => $id, 'url' => $url];
        }
        if (!empty($blk['innerBlocks']) && is_array($blk['innerBlocks'])) {
          $walk($blk['innerBlocks']);
        }
      }
    };

    if (!empty($blocks)) $walk($blocks);
    return $imgs;
  }
}