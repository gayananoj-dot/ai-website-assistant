(function () {
  const $ = (id) => document.getElementById(id);

  const output = $('aiwa-output');
  const postSelect = $('aiwa-post');

  let lastAnalysis = null;
  let lastSuggestions = null;

  const apiFetch = window.wp.apiFetch;
  apiFetch.use(apiFetch.createNonceMiddleware(AIWA.restNonce));

  function show(obj) {
    output.textContent = JSON.stringify(obj, null, 2);
  }

  async function analyze() {
    const postId = Number(postSelect.value);
    const res = await apiFetch({
      path: '/aiwa/v1/analyze',
      method: 'POST',
      data: { post_id: postId },
    });
    lastAnalysis = res;
    show(res);
  }

  async function suggest() {
    const postId = Number(postSelect.value);
    const res = await apiFetch({
      path: '/aiwa/v1/suggest',
      method: 'POST',
      data: { post_id: postId },
    });
    lastSuggestions = res;
    show(res);
  }

  async function apply(type) {
    const postId = Number(postSelect.value);
    if (!lastSuggestions || !lastSuggestions.suggestions) {
      show({ error: 'Run Suggest Improvements first.' });
      return;
    }
    const suggestion = (lastSuggestions.suggestions || []).find((s) => s.type === type);
    if (!suggestion) {
      show({ error: `No suggestion of type "${type}" found.` });
      return;
    }

    const res = await apiFetch({
      path: '/aiwa/v1/apply',
      method: 'POST',
      data: { post_id: postId, suggestion },
    });
    show(res);
  }

  $('aiwa-analyze').addEventListener('click', (e) => { e.preventDefault(); analyze().catch(err => show({ error: String(err) })); });
  $('aiwa-suggest').addEventListener('click', (e) => { e.preventDefault(); suggest().catch(err => show({ error: String(err) })); });

  $('aiwa-apply-seo').addEventListener('click', (e) => { e.preventDefault(); apply('seo_meta').catch(err => show({ error: String(err) })); });
  $('aiwa-apply-alt').addEventListener('click', (e) => { e.preventDefault(); apply('image_alt').catch(err => show({ error: String(err) })); });
  $('aiwa-apply-cta').addEventListener('click', (e) => { e.preventDefault(); apply('cta').catch(err => show({ error: String(err) })); });
})();