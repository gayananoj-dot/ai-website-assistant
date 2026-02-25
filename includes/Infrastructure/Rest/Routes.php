<?php
namespace AIWA\Infrastructure\Rest;

use AIWA\Application\AnalyzePost;
use AIWA\Application\SuggestImprovements;
use AIWA\Application\ApplySuggestion;
use AIWA\Infrastructure\Security\RateLimiter;
use AIWA\Infrastructure\Observability\Logger;
use WP_REST_Request;
use WP_REST_Response;

final class Routes {
  private const NS = 'aiwa/v1';

  public function register(): void {
    add_action('rest_api_init', [$this, 'routes']);
  }

  public function routes(): void {
    register_rest_route(self::NS, '/analyze', [
      'methods' => 'POST',
      'permission_callback' => function (WP_REST_Request $req) {
        $postId = (int) $req->get_param('post_id');
        return $postId > 0 && current_user_can('edit_post', $postId);
      },
      'args' => [
        'post_id' => ['type' => 'integer', 'required' => true],
      ],
      'callback' => function (WP_REST_Request $req) {
        $rl = RateLimiter::check('analyze', 20, 300);
        if (!$rl['ok']) {
          Logger::warn('Rate limit hit', ['action' => 'analyze', 'user' => get_current_user_id()]);
          return new WP_REST_Response($rl, 429);
        }

        $postId = (int) $req->get_param('post_id');
        $res = (new AnalyzePost())->run($postId);
        return new WP_REST_Response($res, isset($res['error']) ? 400 : 200);
      }
    ]);

    register_rest_route(self::NS, '/suggest', [
      'methods' => 'POST',
      'permission_callback' => function (WP_REST_Request $req) {
        $postId = (int) $req->get_param('post_id');
        return $postId > 0 && current_user_can('edit_post', $postId);
      },
      'args' => [
        'post_id' => ['type' => 'integer', 'required' => true],
      ],
      'callback' => function (WP_REST_Request $req) {
        $rl = RateLimiter::check('suggest', 10, 300);
        if (!$rl['ok']) {
          Logger::warn('Rate limit hit', ['action' => 'suggest', 'user' => get_current_user_id()]);
          return new WP_REST_Response($rl, 429);
        }

        $postId = (int) $req->get_param('post_id');
        $res = (new SuggestImprovements())->run($postId);
        return new WP_REST_Response($res, isset($res['error']) ? 400 : 200);
      }
    ]);

    register_rest_route(self::NS, '/apply', [
      'methods' => 'POST',
      'permission_callback' => function (WP_REST_Request $req) {
        $postId = (int) $req->get_param('post_id');
        return $postId > 0 && current_user_can('edit_post', $postId);
      },
      'args' => [
        'post_id' => ['type' => 'integer', 'required' => true],
        'suggestion' => ['required' => true],
      ],
      'callback' => function (WP_REST_Request $req) {
        $rl = RateLimiter::check('apply', 20, 300);
        if (!$rl['ok']) {
          Logger::warn('Rate limit hit', ['action' => 'apply', 'user' => get_current_user_id()]);
          return new WP_REST_Response($rl, 429);
        }

        $postId = (int) $req->get_param('post_id');
        $suggestion = $req->get_param('suggestion');
        $res = (new ApplySuggestion())->run($postId, is_array($suggestion) ? $suggestion : []);
        return new WP_REST_Response($res, isset($res['error']) ? 400 : 200);
      }
    ]);
  }
}
