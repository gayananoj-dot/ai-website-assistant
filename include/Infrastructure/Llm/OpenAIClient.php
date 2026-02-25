<?php
namespace AIWA\Infrastructure\Llm;

final class OpenAIClient {
  public function __construct(
    private readonly string $apiKey,
    private readonly string $model
  ) {}

  /** @param array{prompt:string} $payload */
  public function generate(array $payload): array {
    $url = 'https://api.openai.com/v1/responses';

    $body = [
      'model' => $this->model,
      'input' => (string)($payload['prompt'] ?? ''),
      // Keep deterministic-ish output for JSON
      'temperature' => 0.2,
    ];

    $resp = wp_remote_post($url, [
      'timeout' => 45,
      'headers' => [
        'Authorization' => 'Bearer ' . $this->apiKey,
        'Content-Type' => 'application/json',
      ],
      'body' => wp_json_encode($body),
    ]);

    if (is_wp_error($resp)) {
      return ['error' => $resp->get_error_message()];
    }

    $code = wp_remote_retrieve_response_code($resp);
    $rawBody = (string) wp_remote_retrieve_body($resp);
    $json = json_decode($rawBody, true);

    if ($code < 200 || $code >= 300 || !is_array($json)) {
      return ['error' => 'OpenAI request failed', 'status' => $code, 'raw' => $rawBody];
    }

    // Responses API: often provides output_text convenience
    $text = $json['output_text'] ?? '';

    // If not present, try to derive from output array (best effort)
    if (!is_string($text) || $text === '') {
      $text = '';
      if (isset($json['output']) && is_array($json['output'])) {
        foreach ($json['output'] as $item) {
          if (!is_array($item)) continue;
          $content = $item['content'] ?? [];
          if (!is_array($content)) continue;
          foreach ($content as $c) {
            if (is_array($c) && ($c['type'] ?? '') === 'output_text' && isset($c['text'])) {
              $text .= (string)$c['text'];
            }
          }
        }
      }
    }

    return ['text' => (string)$text, 'raw' => $json];
  }
}