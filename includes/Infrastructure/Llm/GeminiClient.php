<?php
namespace AIWA\Infrastructure\Llm;

final class GeminiClient {
  public function __construct(
    private readonly string $apiKey,
    private readonly string $model
  ) {}

  public function generate(array $payload): array {
    return ['error' => 'Gemini client not implemented yet (stub).'];
  }
}
