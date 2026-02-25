(function () {
  const { registerPlugin } = window.wp.plugins;
  const { PluginSidebar } = window.wp.editPost;
  const { PanelBody, Button, Notice } = window.wp.components;
  const { useSelect } = window.wp.data;
  const { useState } = window.wp.element;

  const apiFetch = window.wp.apiFetch;
  apiFetch.use(apiFetch.createNonceMiddleware(AIWA.restNonce));

  function Sidebar() {
    const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);
    const [busy, setBusy] = useState(false);
    const [analysis, setAnalysis] = useState(null);
    const [suggestions, setSuggestions] = useState(null);
    const [error, setError] = useState(null);

    async function call(path, data) {
      setBusy(true);
      setError(null);
      try {
        return await apiFetch({ path, method: 'POST', data });
      } catch (e) {
        setError(String(e?.message || e));
        return null;
      } finally {
        setBusy(false);
      }
    }

    async function doAnalyze() {
      const res = await call('/aiwa/v1/analyze', { post_id: postId });
      if (res) setAnalysis(res);
    }

    async function doSuggest() {
      const res = await call('/aiwa/v1/suggest', { post_id: postId });
      if (res) setSuggestions(res);
    }

    async function doApply(type) {
      if (!suggestions?.suggestions) {
        setError('Run Suggest first.');
        return;
      }
      const s = suggestions.suggestions.find((x) => x.type === type);
      if (!s) {
        setError(`No suggestion "${type}" found.`);
        return;
      }
      const res = await call('/aiwa/v1/apply', { post_id: postId, suggestion: s });
      if (res) {
        // Re-run analysis after apply
        await doAnalyze();
      }
    }

    return window.wp.element.createElement(
      PluginSidebar,
      { name: 'aiwa-sidebar', title: 'AI Website Assistant' },
      window.wp.element.createElement(
        PanelBody,
        { title: 'Actions', initialOpen: true },
        error && window.wp.element.createElement(Notice, { status: 'error', isDismissible: true }, error),
        window.wp.element.createElement(Button, { isPrimary: true, isBusy: busy, onClick: doAnalyze }, 'Analyze'),
        window.wp.element.createElement(Button, { style: { marginLeft: 8 }, isBusy: busy, onClick: doSuggest }, 'Suggest'),
        window.wp.element.createElement('div', { style: { marginTop: 12 } },
          window.wp.element.createElement(Button, { isBusy: busy, onClick: () => doApply('seo_meta') }, 'Apply Yoast SEO Meta'),
          window.wp.element.createElement(Button, { style: { marginLeft: 8 }, isBusy: busy, onClick: () => doApply('image_alt') }, 'Apply Alt Text'),
          window.wp.element.createElement(Button, { style: { marginLeft: 8 }, isBusy: busy, onClick: () => doApply('cta') }, 'Apply CTA')
        )
      ),
      window.wp.element.createElement(
        PanelBody,
        { title: 'Latest result', initialOpen: false },
        window.wp.element.createElement('pre', { style: { whiteSpace: 'pre-wrap' } },
          JSON.stringify(suggestions || analysis || {}, null, 2)
        )
      )
    );
  }

  registerPlugin('aiwa-plugin', { render: Sidebar });
})();