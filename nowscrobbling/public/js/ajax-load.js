/**
 * NowScrobbling AJAX Loader
 * Handles dynamic content updates with hash comparison and polling
 */
document.addEventListener('DOMContentLoaded', () => {
  const API_URL = nowscrobbling_ajax && nowscrobbling_ajax.ajax_url;
  if (!API_URL) return;

  const $ = (sel, root = document) => root.querySelector(sel);

  /**
   * Extract wrapper from HTML response
   */
  function extractWrapper(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const wrapper = tmp.querySelector('.nowscrobbling');
    return wrapper || null;
  }

  /**
   * Update element from AJAX response with hash comparison
   */
  function updateElementFromResponse(el, payload) {
    const wrapper = payload && payload.html ? extractWrapper(payload.html) : null;
    const newHash = (payload && payload.hash) || (wrapper && wrapper.getAttribute('data-ns-hash')) || null;
    const newNowPlaying = wrapper && wrapper.getAttribute('data-ns-nowplaying');

    // If no wrapper came back, abort quietly
    if (!wrapper) return { changed: false };

    const currentHash = el.dataset.nsHash || null;
    const changed = !newHash || !currentHash || newHash !== currentHash;

    if (changed) {
      // Store current scroll position if element is visible
      const rect = el.getBoundingClientRect();
      const wasVisible = rect.top < window.innerHeight && rect.bottom > 0;
      const scrollTop = wasVisible ? window.pageYOffset : null;

      // Height-animate and blur during swap
      const prevHeight = el.offsetHeight;
      el.style.height = prevHeight + 'px';
      el.style.transition = 'height 250ms cubic-bezier(0.25, 0.1, 0.25, 1)';
      el.classList.add('nowscrobbling-updating');

      // Replace only inner content, keep existing wrapper & dataset attributes consistent
      el.innerHTML = wrapper.innerHTML;
      if (newHash) el.dataset.nsHash = newHash;
      if (newNowPlaying === '1') {
        el.setAttribute('data-ns-nowplaying', '1');
      } else {
        el.removeAttribute('data-ns-nowplaying');
      }

      // Measure new content height and animate
      const nextHeight = el.scrollHeight;
      requestAnimationFrame(() => {
        el.style.height = nextHeight + 'px';
      });

      // After transition, cleanup height and show a brief highlight
      setTimeout(() => {
        el.style.height = '';
        el.style.transition = '';
        el.classList.remove('nowscrobbling-updating');
        el.classList.add('nowscrobbling-updated');
        setTimeout(() => el.classList.remove('nowscrobbling-updated'), 250);
      }, 280);

      // Restore scroll position if element was visible
      if (wasVisible && scrollTop !== null) {
        window.scrollTo(0, scrollTop);
      }
    }

    el.classList.remove('nowscrobbling-loading');
    return { changed: !!changed };
  }

  /**
   * Fetch update from server
   */
  async function fetchUpdate(shortcode, currentHash = null, forceRefresh = false, attrs = null) {
    const params = new URLSearchParams({
      action: 'nowscrobbling_render_shortcode',
      shortcode,
      _wpnonce: nowscrobbling_ajax.nonce,
    });

    if (currentHash) {
      params.append('current_hash', currentHash);
    }

    if (forceRefresh) {
      params.append('force_refresh', 'true');
    }

    if (attrs && typeof attrs === 'object') {
      try { params.append('attrs', JSON.stringify(attrs)); } catch (_) {}
    }

    const res = await fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params
    });

    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    }

    const json = await res.json();
    if (!json || !json.success || !json.data || !json.data.html) {
      const message = (json && json.data && json.data.message) || 'Unknown error';
      throw new Error('NowScrobbling AJAX failed: ' + message);
    }

    return json.data; // { html, hash?, ts?, changed? }
  }

  /**
   * Schedule polling for now playing content
   */
  function scheduleNowPlayingPoll(el, intervalMs = 20000, maxIntervalMs = 300000) {
    // Keep per-element state
    if (!el._nsState) el._nsState = { fails: 0, timer: null };

    const run = async () => {
      try {
        const currentHash = el.dataset.nsHash || null;
        // During polling we force-refresh so server can fetch fresh data (ETag-aware)
        const rawAttrs = el.getAttribute('data-ns-attrs');
        const parsedAttrs = rawAttrs ? JSON.parse(rawAttrs) : null;
        // Respect server-side cache TTLs when idle; force-refresh only while now playing
        const isNowPlaying = el.getAttribute('data-ns-nowplaying') === '1';
        const data = await fetchUpdate(el.dataset.nowscrobblingShortcode, currentHash, !!isNowPlaying, parsedAttrs);
        
        const result = updateElementFromResponse(el, data);

        // Determine adaptive interval
        const isNowPlayingAfter = el.getAttribute('data-ns-nowplaying') === '1';
        // Base window from settings
        const base = intervalMs;
        // Idle base tries less often; cap by maxIntervalMs
        const idleBase = Math.min(maxIntervalMs, Math.max(base * 2, 60000)); // at least 60s when idle

        // Maintain an idle growth factor to stretch intervals while idle and unchanged
        if (result.changed || isNowPlayingAfter) {
          el._nsState.idleSteps = 0;
        } else {
          el._nsState.idleSteps = Math.min((el._nsState.idleSteps || 0) + 1, 6);
        }

        let nextDelay;
        if (isNowPlayingAfter) {
          // While listening/watching, check roughly every minute (or configured base)
          nextDelay = Math.max(60000, base);
        } else {
          // When idle, increase delay progressively (up to max)
          const growth = Math.pow(2, el._nsState.idleSteps || 0);
          nextDelay = Math.min(maxIntervalMs, idleBase * growth);
        }

        el._nsState.timer = setTimeout(run, nextDelay);
      } catch (e) {
        if (nowscrobbling_ajax.debug) {
          console.warn('NowScrobbling polling error:', e);
        }
        
        el._nsState.fails = Math.min(el._nsState.fails + 1, 6);
        const mult = (nowscrobbling_ajax.polling && nowscrobbling_ajax.polling.backoff_multiplier) || 2;
        const next = Math.min(intervalMs * Math.pow(mult, el._nsState.fails), maxIntervalMs);
        el._nsState.timer = setTimeout(run, next);
      }
    };

    // start initial timer after base interval to avoid double burst
    if (el._nsState.timer) clearTimeout(el._nsState.timer);
    el._nsState.timer = setTimeout(run, intervalMs);
  }

  /**
   * Initialize a single shortcode element
   */
  async function initializeElement(el) {
    const shortcode = el.dataset.nowscrobblingShortcode;
    if (!shortcode) return;

    // Keep SSR/cache content visible; just mark as loading subtly
    el.classList.add('nowscrobbling-loading');

    try {
      const currentHash = el.dataset.nsHash || null;
      const rawAttrs = el.getAttribute('data-ns-attrs');
      const parsedAttrs = rawAttrs ? JSON.parse(rawAttrs) : null;
      // Initial request after SSR uses cache; no forced refresh for faster first paint
      const data = await fetchUpdate(shortcode, currentHash, false, parsedAttrs);
      updateElementFromResponse(el, data);
    } catch (e) {
      if (nowscrobbling_ajax.debug) {
        console.warn('NowScrobbling initial AJAX error:', e);
      }
      // keep SSR content; optionally attach a small retry button inside the element
      el.classList.remove('nowscrobbling-loading');
    }

    // Attach polling
    const interval = nowscrobbling_ajax.polling?.nowplaying_interval || 20000;
    const maxInterval = nowscrobbling_ajax.polling?.max_interval || 300000;
    // Poll for indicators and history widgets to detect changes without reload
    const isIndicator = typeof shortcode === 'string' && shortcode.indexOf('_indicator') !== -1;
    const isHistory = typeof shortcode === 'string' && shortcode.indexOf('_history') !== -1;
    if (isIndicator || isHistory || el.getAttribute('data-ns-nowplaying') === '1') {
      scheduleNowPlayingPoll(el, interval, maxInterval);
    }
  }

  /**
   * Initialize all shortcode wrappers
   */
  const elements = document.querySelectorAll('[data-nowscrobbling-shortcode]');
  // Lazy-initialize elements when they enter the viewport
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const el = entry.target;
          io.unobserve(el);
          initializeElement(el);
        }
      });
    }, { root: null, rootMargin: '0px 0px 200px 0px', threshold: 0 });
    elements.forEach((el) => io.observe(el));
  } else {
    // Fallback for older browsers
    elements.forEach((el) => initializeElement(el));
  }

  /**
   * Handle dynamic content (for themes that load content via AJAX)
   */
  const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      for (const node of mutation.addedNodes) {
        if (node && node.nodeType === 1) { // ELEMENT_NODE
          const list = node.querySelectorAll ? node.querySelectorAll('[data-nowscrobbling-shortcode]') : [];
          // Convert NodeList to array for push
          const arr = Array.prototype.slice.call(list);
          if (node.matches && node.matches('[data-nowscrobbling-shortcode]')) {
            arr.push(node);
          }
          if ('IntersectionObserver' in window) {
            const io = new IntersectionObserver((entries, observer) => {
              entries.forEach((entry) => {
                if (entry.isIntersecting) {
                  const el = entry.target;
                  observer.unobserve(el);
                  initializeElement(el);
                }
              });
            }, { root: null, rootMargin: '0px 0px 200px 0px', threshold: 0 });
            arr.forEach((el) => io.observe(el));
          } else {
            arr.forEach(initializeElement);
          }
        }
      }
    }
  });

  // Observe for dynamically added content
  observer.observe(document.body, {
    childList: true,
    subtree: true
  });

  /**
   * Expose refresh function for manual updates
   */
  window.nowscrobblingRefresh = async function(selector = '[data-nowscrobbling-shortcode]') {
    const elements = document.querySelectorAll(selector);
    const results = [];
    
    for (const el of elements) {
      try {
        const rawAttrs = el.getAttribute('data-ns-attrs');
        const parsedAttrs = rawAttrs ? JSON.parse(rawAttrs) : null;
        const data = await fetchUpdate(el.dataset.nowscrobblingShortcode, null, true, parsedAttrs);
        const result = updateElementFromResponse(el, data);
        results.push({ element: el, success: true, changed: result.changed });
      } catch (e) {
        results.push({ element: el, success: false, error: e.message });
      }
    }
    
    return results;
  };
});