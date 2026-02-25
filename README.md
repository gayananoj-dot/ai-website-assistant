# AI Website Assistant (WordPress Plugin)

AI Website Assistant (AIWA) analyzes WordPress posts/pages and generates AI-powered suggestions for:
- SEO (Yoast title + meta description)
- Accessibility (missing image alt text)
- CTAs (insert a simple CTA block)
- Block-level rewrites (paragraphs/headings) with safety checks

This is a BYO-LLM plugin: **you provide your own API key** (OpenAI implemented; Anthropic/Gemini stubs included).

## Features
- Settings: Provider + model + API key (encrypted-at-rest)
- Gutenberg sidebar panel:
  - Analyze
  - Suggest improvements (JSON schema enforced)
  - Apply supported suggestion types
- Tools page (fallback) under **Tools → AI Website Assistant**
- REST API:
  - `POST /wp-json/aiwa/v1/analyze`
  - `POST /wp-json/aiwa/v1/suggest`
  - `POST /wp-json/aiwa/v1/apply`

## Supported Providers
- ✅ OpenAI (Responses API)
- ⏳ Anthropic (stub)
- ⏳ Gemini (stub)

## Yoast SEO Support
If Yoast SEO is active, applying `seo_meta` updates:
- `_yoast_wpseo_title`
- `_yoast_wpseo_metadesc`

If Yoast is not active, meta description falls back to the WordPress excerpt.

## Security & Safety
- API keys are **encrypted-at-rest** using WP salts (mitigates accidental DB dump exposure).
- API keys are never returned in REST responses.
- Endpoints require `edit_post` capability for the target post.
- Per-user rate limiting on analyze/suggest/apply reduces spam and surprise billing.
- Rewrites are **recommend-first** and only applied when requested.
- Rewrite apply uses content hashes to avoid applying stale changes.
- CTA apply is idempotent via an AIWA marker attribute.

## Installation
1. Copy this folder to: `wp-content/plugins/ai-website-assistant/`
2. Activate **AI Website Assistant**
3. Configure: **Settings → AI Website Assistant**
4. Use in editor: open **AI Website Assistant** sidebar in the block editor.

## Development Notes
- PHP 8.0+
- No Composer required (simple autoloader).
- Folder structure:
  - `includes/Application`: core use-cases (analyze, suggest, apply)
  - `includes/Infrastructure`: WP integration (admin, REST, provider clients, security)
  - `assets/`: JS for tools/editor UI

## License
Choose MIT for private/internal use, or GPLv2+ if planning to publish on WordPress.org.
