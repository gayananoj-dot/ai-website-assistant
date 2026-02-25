<?php
namespace AIWA\Application;

use AIWA\Application\Schema\SuggestionValidator;
use AIWA\Infrastructure\Llm\LlmFactory;

final class SuggestImprovements {
  /** @return array<string,mixed> */
  public function run(int $postId): array {
    $analysis = (new AnalyzePost())->run($postId);
    if (isset($analysis['error'])) return $analysis;

    $post = get_post($postId);
    if (!$post) return ['error' => 'Post not found'];

    $prompt = $this->buildPrompt($postId, $analysis, (string)$post->post_content, (string)$post->post_title);

    $client = LlmFactory::make();
    if (!$client) return ['error' => 'LLM provider not configured. Set provider/model/key in Settings.'];

    $resp = $client->generate(['prompt' => $prompt]);
    if (isset($resp['error'])) return $resp;

    $text = (string)($resp['text'] ?? '');
    $decoded = json_decode($text, true);

    // Retry once with a stricter “JSON only” instruction if decode fails
    if (!is_array($decoded)) {
      $retryPrompt = $prompt . "\n\nIMPORTANT: Your previous output was invalid. Output ONLY valid JSON matching the schema.";
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

    // Return normalized output
    return [
      'post_id' => $postId,
      'scores' => $decoded['scores'] ?? $analysis['scores'],
      'suggestions' => $decoded['suggestions'],
      'raw' => $decoded,
    ];
  }

  private function buildPrompt(int $postId, array $analysis, string $content, string $title): string {
    $contentText = wp_strip_all_tags($content);
    // Keep prompt size bounded for MVP
    if (mb_strlen($contentText) > 8000) {
      $contentText = mb_substr($contentText, 0, 8000) . "\n…(truncated)";
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
    }
  ]
}
JSON;

    $missingAlt = $analysis['missing_alt'] ?? [];
    $missingAltJson = wp_json_encode($missingAlt);

    $headingsJson = wp_json_encode($analysis['headings'] ?? []);

    return
"YOU ARE AN AI WEBSITE OPTIMIZATION ASSISTANT FOR WORDPRESS.
Return ONLY valid JSON. No markdown. No commentary.

Goal: Improve SEO, accessibility, UX, and CTAs without changing factual meaning.

Constraints:
- Keep brand tone professional and clear.
- Do not invent claims, prices, or legal promises.
- For SEO title: aim ~50-60 chars if possible.
- For meta description: aim ~140-160 chars.
- For CTA URL: prefer existing site paths like /contact or /book (safe guess if unknown).
- For image_alt: describe image contextually; avoid keyword stuffing.

OUTPUT MUST MATCH THIS EXACT JSON SHAPE:
$schema

Context:
Post ID: {$postId}
Current title: " . $title . "
Headings: $headingsJson
Missing alt images: $missingAltJson
Deterministic scores: " . wp_json_encode($analysis['scores'] ?? []) . "

Post content (plain text):
\"\"\"\n{$contentText}\n\"\"\"";
  }
}