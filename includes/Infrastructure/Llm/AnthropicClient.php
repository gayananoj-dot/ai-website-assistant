<?php
namespace AIWA\Infrastructure\Llm;

final class AnthropicClient {
  public function __construct(
    private readonly string $apiKey,
    private readonly string $model
  ) {}

  public function generate(array $payload): array {
    return ['error' => 'Anthropic client not implemented yet (stub).'];
  }
}
