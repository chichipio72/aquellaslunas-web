(() => {
  'use strict';

  const SCRIPT_ID = 'instagram-embed-script';
  const SCRIPT_URL = 'https://www.instagram.com/embed.js';

  const processEmbeds = () => {
    try {
      globalThis.instgrm?.Embeds?.process?.();
    } catch (_) {
      // El fallback y el enlace directo permanecen visibles.
    }
  };

  const loadInstagramEmbeds = () => {
    if (!document.querySelector('.instagram-media[data-instgrm-permalink]')) return;
    if (globalThis.instgrm?.Embeds?.process) {
      processEmbeds();
      return;
    }

    const existing = document.getElementById(SCRIPT_ID);
    if (existing) {
      existing.addEventListener('load', processEmbeds, { once: true });
      return;
    }

    const script = document.createElement('script');
    script.id = SCRIPT_ID;
    script.src = SCRIPT_URL;
    script.async = true;
    script.addEventListener('load', processEmbeds, { once: true });
    document.body.append(script);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadInstagramEmbeds, { once: true });
  } else {
    loadInstagramEmbeds();
  }
})();
