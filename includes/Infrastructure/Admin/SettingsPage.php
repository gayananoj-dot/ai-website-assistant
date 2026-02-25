<?php
namespace AIWA\Infrastructure\Admin;

use AIWA\Infrastructure\Crypto\Secrets;
use AIWA\Infrastructure\WP\Options;

final class SettingsPage {
  public function register(): void {
    add_action('admin_menu', [$this, 'menu']);
    add_action('admin_init', [$this, 'settings']);
  }

  public function menu(): void {
    add_options_page(
      'AI Website Assistant',
      'AI Website Assistant',
      'manage_options',
      'ai-website-assistant',
      [$this, 'render']
    );
  }

  public function settings(): void {
    register_setting(Options::KEY, Options::KEY, [
      'type' => 'array',
      'sanitize_callback' => [$this, 'sanitize'],
      'default' => Options::get(),
    ]);

    add_settings_section('aiwa_main', 'Provider Settings', function () {
      echo '<p>Choose a provider and enter your API key. The key is encrypted before saving.</p>';
    }, 'ai-website-assistant');

    add_settings_field('provider', 'Provider', [$this, 'fieldProvider'], 'ai-website-assistant', 'aiwa_main');
    add_settings_field('model', 'Model', [$this, 'fieldModel'], 'ai-website-assistant', 'aiwa_main');
    add_settings_field('api_key', 'API Key', [$this, 'fieldApiKey'], 'ai-website-assistant', 'aiwa_main');
  }

  /** @param mixed $input */
  public function sanitize($input): array {
    $input = is_array($input) ? $input : [];
    $existing = Options::get();

    $provider = isset($input['provider']) ? sanitize_key((string)$input['provider']) : $existing['provider'];
    $model = isset($input['model']) ? sanitize_text_field((string)$input['model']) : $existing['model'];

    // If empty, keep existing encrypted key
    $apiKeyPlain = isset($input['api_key']) ? trim((string)$input['api_key']) : '';
    $apiKeyEnc = $existing['api_key_enc'];
    if ($apiKeyPlain !== '') {
      $apiKeyEnc = Secrets::encrypt($apiKeyPlain);
    }

    return [
      'provider' => in_array($provider, ['openai','anthropic','gemini'], true) ? $provider : 'openai',
      'model' => $model !== '' ? $model : 'gpt-5.2',
      'api_key_enc' => $apiKeyEnc,
    ];
  }

  public function fieldProvider(): void {
    $s = Options::get();
    $providers = [
      'openai' => 'OpenAI',
      'anthropic' => 'Anthropic (stub)',
      'gemini' => 'Gemini (stub)',
    ];

    echo '<select name="'.esc_attr(Options::KEY).'[provider]">';
    foreach ($providers as $k => $label) {
      printf('<option value="%s" %s>%s</option>',
        esc_attr($k),
        selected($s['provider'], $k, false),
        esc_html($label)
      );
    }
    echo '</select>';
  }

  public function fieldModel(): void {
    $s = Options::get();
    printf(
      '<input class="regular-text" name="%s[model]" value="%s" placeholder="e.g. gpt-5.2" />',
      esc_attr(Options::KEY),
      esc_attr($s['model'])
    );
    echo '<p class="description">Model name depends on your provider.</p>';
  }

  public function fieldApiKey(): void {
    echo '<input class="regular-text" type="password" name="'.esc_attr(Options::KEY).'[api_key]" value="" autocomplete="off" />';
    echo '<p class="description">Leave blank to keep the existing key.</p>';
  }

  public function render(): void {
    if (!current_user_can('manage_options')) return;

    echo '<div class="wrap"><h1>AI Website Assistant</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields(Options::KEY);
    do_settings_sections('ai-website-assistant');
    submit_button('Save Settings');
    echo '</form></div>';
  }
}
