<?php
namespace AIWA\Application;

use AIWA\Application\Schema\SuggestionValidator;
use AIWA\Infrastructure\Llm\LlmFactory;
use AIWA\Infrastructure\Observability\Logger;

/**
 * Calls the configured LLM provider to generate structured suggestions (JSON-only).
 */
final class SuggestImprovements {
  /** @return array<string,mixed> */
  public function run(int $postId): array {
    $analysis = (new AnalyzePost())->run($postId);
    if (isset($analysis['error'])) return $analysis;

    // Hard caps to keep prompts bounded
    $analysis['headings'] = array_slice($analysis['headings'] ?? [], 0, 30);
    $analysis['missing_alt'] = array_slice($analysis['missing_alt'] ?? [], 0, 30);
    $analysis['rewrite_candidates'] = array_slice($analysis['rewrite_candidates'] ?? [], 0, 12);

    $post = get_post($postId);
    if (!$post) return ['error' => 'Post not found'];

    $prompt = $this->buildPrompt($postId, $analysis, (string)$post->post_content, (string)$post->post_title);

    $client = LlmFactory::make();
    if (!$client) return ['error' => 'LLM provider not configured. Set provider/model/key in Settings.'];

    $resp = $client->generate(['prompt' => $prompt]);
    if (isset($resp['error'])) return $resp;

    $text = (string)($resp['text'] ?? '');
    $decoded = json_decode($text, true);

    // Retry once with stricter JSON-only instruction
    if (!is_array($decoded)) {
      Logger::warn('First decode failed; retrying JSON-only', ['post_id' => $postId]);
      $retryPrompt = $prompt . "\n\nIMPORTANT: Your previous output was invalid. Output ONLY valid JSON matching the schema. No markdown.";
      $resp2 = $client->generate(['prompt' => $retryPrompt]);
      if (isset($resp2['error'])) return $resp2;
      $text2 = (string)($resp2['text'] ?? '');
      $decoded = json_decode($text2, true);
      $text = $text2;
    }

    $valid = SuggestionValidator::validate($decoded);
    if (!$valid['ok']) {
      return [
        'error' => 'Model output failed schema validation: ' . $valid['error'],
        'raw_text' => $text,
      ];
    }

    return [
      'post_id' => $postId,
      'scores' => $decoded['scores'] ?? ($analysis['scores'] ?? []),
      'suggestions' => $decoded['suggestions'],
      'raw' => $decoded,
    ];
  }

  private function buildPrompt(int $postId, array $analysis, string $content, string $title): string {
    $contentText = wp_strip_all_tags($content);

    // Hard max prompt content size (MVP)
    $max = 9000;
    if (mb_strlen($contentText) > $max) {
      $contentText = mb_substr($contentText, 0, $max) . "\n…(truncated)";
    }

    $schema = <<<JSON
{
  "version": "1.0",
  "scores": { "seo": 0-100, "a11y": 0-100, "ux": 0-100 },
  "suggestions": [
    {
      "type": "seo_meta",
      "title": "string",
      "meta_description": "string",
      "rationale": "string"
    },
    {
      "type": "image_alt",
      "items": [ { "attachment_id": 123, "alt": "string" } ],
      "rationale": "string"
    },
    {
      "type": "cta",
      "placements": [ { "cta_text": "string", "cta_url": "string", "location": "string" } ],
      "rationale": "string"
    },
    {
      "type": "rewrite_blocks",
      "items": [
        {
          "client_id": "string",
          "before_hash": "sha256(string)",
          "after": "string",
          "rationale": "string"
        }
      ]
    }
  ]
}
JSON;

    $missingAltJson = wp_json_encode($analysis['missing_alt'] ?? []);
    $headingsJson = wp_json_encode($analysis['headings'] ?? []);
    $rewriteCandidatesJson = wp_json_encode($analysis['rewrite_candidates'] ?? []);
    $yoastJson = wp_json_encode($analysis['yoast'] ?? []);

    return
"YOU ARE AN AI WEBSITE OPTIMIZATION ASSISTANT FOR WORDPRESS.
Return ONLY valid JSON. No markdown. No commentary.

Goal: Improve SEO, accessibility, UX, and CTAs without changing factual meaning.
Return compact JSON. Keep rationales under 2 sentences each.

Constraints:
- Keep brand tone professional and clear.
- Do not invent claims, prices, or legal promises.
- For SEO title: aim ~50-60 chars if possible.
- For meta description: aim ~140-160 chars.
- For CTA URL: prefer existing site paths like /contact or /book (safe guess if unknown).
- For image_alt: describe image contextually; avoid keyword stuffing.
- For rewrite_blocks: ONLY rewrite the provided candidates. Choose at most 3. Preserve meaning.

OUTPUT MUST MATCH THIS EXACT JSON SHAPE:
$schema

Context:
Post ID: {$postId}
Current WP title: {$title}
Yoast context: {$yoastJson}
Headings: {$headingsJson}
Missing alt images: {$missingAltJson}
Rewrite candidates (ONLY these blocks may be rewritten): {$rewriteCandidatesJson}
Deterministic scores: " . wp_json_encode($analysis['scores'] ?? []) . "

Post content (plain text):
"""\n{$contentText}\n"""";
  }
}
