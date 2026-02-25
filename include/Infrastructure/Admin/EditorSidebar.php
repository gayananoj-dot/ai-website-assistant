<?php
namespace AIWA\Infrastructure\Admin;

final class EditorSidebar {
  public function register(): void {
    add_action('enqueue_block_editor_assets', [$this, 'enqueue']);
  }

  public function enqueue(): void {
    wp_enqueue_script(
      'aiwa-editor-sidebar',
      AIWA_PLUGIN_URL . 'assets/editor-sidebar.js',
      ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch'],
      AIWA_VERSION,
      true
    );

    wp_localize_script('aiwa-editor-sidebar', 'AIWA', [
      'restNonce' => wp_create_nonce('wp_rest'),
    ]);
  }
}