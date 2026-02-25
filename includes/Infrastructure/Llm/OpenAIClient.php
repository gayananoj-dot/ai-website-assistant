<?php
namespace AIWA\Infrastructure\Llm;

use AIWA\Infrastructure\Observability\Logger;

/**
 * OpenAI Responses API client using WordPress HTTP API.
 */
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
      Logger::error('OpenAI request error', ['msg' => $resp->get_error_message()]);
      return ['error' => $resp->get_error_message()];
    }

    $code = wp_remote_retrieve_response_code($resp);
    $rawBody = (string) wp_remote_retrieve_body($resp);
    $json = json_decode($rawBody, true);

    if ($code < 200 || $code >= 300 || !is_array($json)) {
      Logger::error('OpenAI non-2xx', ['status' => $code, 'raw' => mb_substr($rawBody, 0, 4000)]);
      return ['error' => 'OpenAI request failed', 'status' => $code, 'raw' => $rawBody];
    }

    $text = $json['output_text'] ?? '';

    // Best-effort fallback parse
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
