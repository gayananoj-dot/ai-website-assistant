<?php
namespace AIWA\Infrastructure\Llm;

use AIWA\Infrastructure\Crypto\Secrets;
use AIWA\Infrastructure\WP\Options;

/**
 * Creates the configured provider client based on settings.
 */
final class LlmFactory {
  public static function make(): ?object {
    $s = Options::get();
    $provider = (string)$s['provider'];
    $model = (string)$s['model'];
    $key = Secrets::decrypt((string)$s['api_key_enc']);

    if ($key === '' || $model === '') return null;

    return match ($provider) {
      'openai' => new OpenAIClient($key, $model),
      'anthropic' => new AnthropicClient($key, $model),
      'gemini' => new GeminiClient($key, $model),
      default => null,
    };
  }
}
