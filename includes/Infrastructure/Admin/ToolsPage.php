<?php
namespace AIWA\Infrastructure\Admin;

final class ToolsPage {
  public function register(): void {
    add_action('admin_menu', [$this, 'menu']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue']);
  }

  public function menu(): void {
    add_management_page(
      'AI Website Assistant',
      'AI Website Assistant',
      'edit_posts',
      'aiwa-tools',
      [$this, 'render']
    );
  }

  public function enqueue(string $hook): void {
    if ($hook !== 'tools_page_aiwa-tools') return;

    wp_enqueue_script(
      'aiwa-admin',
      AIWA_PLUGIN_URL . 'assets/admin.js',
      ['wp-api-fetch'],
      AIWA_VERSION,
      true
    );

    wp_localize_script('aiwa-admin', 'AIWA', [
      'restNonce' => wp_create_nonce('wp_rest'),
    ]);
  }

  public function render(): void {
    if (!current_user_can('edit_posts')) return;

    $posts = get_posts([
      'post_type' => ['post','page'],
      'posts_per_page' => 50,
      'post_status' => ['publish','draft','private'],
      'orderby' => 'modified',
      'order' => 'DESC',
    ]);

    echo '<div class="wrap">';
    echo '<h1>AI Website Assistant</h1>';
    echo '<p>Select a post/page and run an analysis or AI suggestions.</p>';

    echo '<label for="aiwa-post">Post/Page</label><br />';
    echo '<select id="aiwa-post" style="min-width:420px;">';
    foreach ($posts as $p) {
      printf('<option value="%d">%s (#%d)</option>',
        (int)$p->ID,
        esc_html($p->post_title ?: '(no title)'),
        (int)$p->ID
      );
    }
    echo '</select>';

    echo '<p style="margin-top:12px;">';
    echo '<button class="button button-primary" id="aiwa-analyze">Analyze</button> ';
    echo '<button class="button" id="aiwa-suggest">Suggest Improvements</button> ';
    echo '</p>';

    echo '<h2>Result</h2>';
    echo '<pre id="aiwa-output" style="background:#111;color:#eee;padding:12px;max-height:480px;overflow:auto;"></pre>';

    echo '<h2>Apply (safe types)</h2>';
    echo '<p>After running suggestions, you can apply supported types: <code>seo_meta</code>, <code>image_alt</code>, <code>cta</code>, <code>rewrite_blocks</code>.</p>';
    echo '<button class="button" id="aiwa-apply-seo">Apply SEO Meta</button> ';
    echo '<button class="button" id="aiwa-apply-alt">Apply Image Alt</button> ';
    echo '<button class="button" id="aiwa-apply-cta">Apply CTA</button> ';
    echo '<button class="button" id="aiwa-apply-rewrite">Apply Rewrites</button>';

    echo '</div>';
  }
}
