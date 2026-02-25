# AI Website Assistant (WordPress Plugin)

AI Website Assistant (AIWA) analyzes WordPress posts/pages and generates AI-powered suggestions for:
- SEO (Yoast title + meta description)
- Accessibility (missing image alt text)
- CTAs (insert a simple CTA block)
- Optional: block-level rewrites (paragraphs/headings) with safety checks

This is a BYO-LLM plugin: **you provide your own API key** (OpenAI implemented; Anthropic/Gemini can be added).

## Features (MVP)
- Admin settings: Provider + model + API key (encrypted-at-rest)
- Gutenberg sidebar panel:
  - Analyze
  - Suggest improvements (JSON schema enforced)
  - Apply supported suggestion types
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

## Installation (local)
1. Copy the plugin folder to:
   `wp-content/plugins/ai-website-assistant/`
2. Activate **AI Website Assistant**
3. Configure:
   - **Settings → AI Website Assistant**
   - Set provider/model and paste your API key (leave blank to keep existing)
4. Use:
   - In the block editor, open **AI Website Assistant** in the sidebar

## Security & Privacy Notes
- API keys are **encrypted-at-rest** using WP salts (mitigates accidental DB dump exposure).
- API keys are never returned in REST responses.
- All endpoints require `edit_post` capability for the target post.
- Rate limiting is enabled (per-user) to reduce spam and surprise billing.
- Suggested rewrites are **recommend-first** and only applied when requested.
- Rewrite applies are protected by content hashes to avoid applying stale changes.

## Development
- PHP 8.0+ recommended
- No composer required (simple PSR-4-ish autoloader in `includes/Autoloader.php`).

### Folder structure
- `includes/Application`: core use-cases (analyze, suggest, apply)
- `includes/Infrastructure`: WordPress integration (admin, REST, provider clients)
- `assets/`: editor/admin JavaScript

## Roadmap
- Add Anthropic + Gemini implementations
- Better CTA placement (after specific headings)
- Site-wide dashboard and scheduled audits
- Deeper accessibility checks (links, forms, contrast warnings)
- Integrations: RankMath, AIOSEO

## License
MIT (or choose GPLv2+ if you plan to publish on WordPress.org)
