/**
 * NowScrobbling AJAX Loader
 * Handles dynamic content updates with hash comparison and polling
 */
document.addEventListener('DOMContentLoaded', () => {
  const API_URL = nowscrobbling_ajax && nowscrobbling_ajax.ajax_url;
  if (!API_URL) return;

  const $ = (sel, root = document) => root.querySelector(sel);

  // Simple helper to derive service from shortcode slug
  function getServiceFromShortcode(slug) {
    if (!slug) return 'generic';
    if (slug.indexOf('lastfm') !== -1) return 'lastfm';
    if (slug.indexOf('trakt') !== -1) return 'trakt';
    return 'generic';
  }

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
  /**
   * Fetch update from server with error handling and retry logic
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

    // Retry logic mit exponential backoff
    let attempts = 0;
    const maxAttempts = 3;
    const baseDelay = 1000; // 1s

    while (attempts < maxAttempts) {
      try {
        const res = await fetch(API_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: params,
          // Abbruch bei langen Requests vermeiden
          signal: AbortSignal.timeout ? AbortSignal.timeout(20000) : undefined // 20s Timeout, falls unterstützt
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
      } catch (err) {
        attempts++;
        
        // Bei letztem Versuch direkt werfen
        if (attempts >= maxAttempts) {
          if (nowscrobbling_ajax.debug) {
            console.warn(`NowScrobbling: Alle Versuche fehlgeschlagen (${maxAttempts}):`, err);
          }
          throw err;
        }
        
        // Sonst warten und erneut versuchen (exponential backoff)
        const delay = baseDelay * Math.pow(2, attempts - 1);
        if (nowscrobbling_ajax.debug) {
          console.warn(`NowScrobbling: Versuch ${attempts} fehlgeschlagen, versuche erneut in ${delay}ms:`, err);
        }
        
        await new Promise(resolve => setTimeout(resolve, delay));
      }
    }

    // Sollte nie erreicht werden, aber zur Sicherheit
    throw new Error('NowScrobbling: Unerwarteter Fehler in fetchUpdate');
  }

  /**
   * Schedule polling for now playing content
   */
  function scheduleNowPlayingPoll(el, intervalMs = 20000, maxIntervalMs = 300000) {
    // Keep per-element state
    if (!el._nsState) el._nsState = { fails: 0, timer: null };

    const shortcode = el.dataset.nowscrobblingShortcode || '';
    const service = getServiceFromShortcode(shortcode);
    // Service-spezifisch: Last.fm 30s, Trakt 60s
    const cfgBase = intervalMs;
    const baseInterval = service === 'lastfm' ? Math.max(cfgBase, 30000) : Math.max(cfgBase, 60000);
    const baseMax = Math.max(maxIntervalMs, 300000);
    // Stop conditions: auto-stop after a few minutes, sooner if inactive/hidden
    const stopAfterMs = service === 'trakt' ? 10 * 60 * 1000 : 5 * 60 * 1000; // trakt ~10min, lastfm ~5min
    const hiddenStopGraceMs = 180 * 1000; // stop if hidden for >3min
    const nowTs = Date.now();
    if (!el._nsState.startedAt) el._nsState.startedAt = nowTs;
    if (!el._nsState.lastActivityAt) el._nsState.lastActivityAt = nowTs;

    // Track basic user activity to detect idleness
    const markActive = () => { el._nsState.lastActivityAt = Date.now(); };
    if (!el._nsState.boundActivity) {
      ['pointerdown','keydown','scroll','mousemove','touchstart'].forEach(evt => window.addEventListener(evt, markActive, { passive: true }));
      document.addEventListener('visibilitychange', markActive, { passive: true });
      el._nsState.boundActivity = true;
    }

    const run = async () => {
      // Stop if element was removed
      if (!document.body.contains(el)) {
        if (el._nsState.timer) clearTimeout(el._nsState.timer);
        return;
      }

      // Stop if exceeded lifetime or page hidden for a while
      const now = Date.now();
      if (now - (el._nsState.startedAt || now) > stopAfterMs) {
        if (el._nsState.timer) clearTimeout(el._nsState.timer);
        return;
      }
      if (document.visibilityState === 'hidden' && now - (el._nsState.lastActivityAt || now) > hiddenStopGraceMs) {
        if (el._nsState.timer) clearTimeout(el._nsState.timer);
        return;
      }

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
        // Base window from service-specific settings
        const base = baseInterval;
        // Idle base tries less often; cap by baseMax
        const idleBase = Math.min(baseMax, Math.max(base * 2, service === 'trakt' ? 60000 : 30000));

        // Maintain an idle growth factor to stretch intervals while idle and unchanged
        if (result.changed || isNowPlayingAfter) {
          el._nsState.idleSteps = 0;
        } else {
          el._nsState.idleSteps = Math.min((el._nsState.idleSteps || 0) + 1, 6);
        }

        let nextDelay;
        if (isNowPlayingAfter) {
          // While listening/watching, check using service base (>=60s lastfm, >=10min trakt)
          nextDelay = base;
        } else {
          // When idle, increase delay progressively (up to max)
          const growth = Math.pow(2, el._nsState.idleSteps || 0);
          nextDelay = Math.min(baseMax, idleBase * growth);
        }

        // Aktualisiere zugehörige Indicator/History-Elemente desselben Dienstes, wenn weiterhin Now-Playing aktiv ist
        if (isNowPlayingAfter) {
          try {
            const siblings = [];
            if (service === 'lastfm') {
              siblings.push('nowscr_lastfm_indicator', 'nowscr_lastfm_history');
            } else if (service === 'trakt') {
              siblings.push('nowscr_trakt_indicator', 'nowscr_trakt_history');
            }
            const rawAttrs = el.getAttribute('data-ns-attrs');
            const parsedAttrs = rawAttrs ? JSON.parse(rawAttrs) : null;
            siblings.forEach(async (sc) => {
              document.querySelectorAll(`[data-nowscrobbling-shortcode="${sc}"]`).forEach(async (other) => {
                if (other === el) return;
                const ch = other.dataset.nsHash || null;
                try {
                  const payload = await fetchUpdate(sc, ch, false, parsedAttrs);
                  updateElementFromResponse(other, payload);
                } catch (_) {}
              });
            });
          } catch (_) {}
        }

        // If now playing ended, stop polling entirely (resume only on manual refresh/page reload)
        if (!isNowPlayingAfter) {
          if (el._nsState.timer) clearTimeout(el._nsState.timer);
          return;
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
    el._nsState.timer = setTimeout(run, baseInterval);
  }

  /**
   * Initialize a single shortcode element
   */
  async function initializeElement(el) {
    const shortcode = el.dataset.nowscrobblingShortcode;
    if (!shortcode) return;

    // Tracking für Initialisierungsstatus
    if (el._nsInitialized) return;
    el._nsInitialized = true;

    // Keep SSR/cache content visible; just mark as loading subtly
    el.classList.add('nowscrobbling-loading');

    try {
      const currentHash = el.dataset.nsHash || null;
      const rawAttrs = el.getAttribute('data-ns-attrs');
      const parsedAttrs = rawAttrs ? JSON.parse(rawAttrs) : null;
      
      // Initial request after SSR: force refresh für bestimmte Situationen
      const isLastfm = shortcode && shortcode.indexOf('lastfm') !== -1;
      const placeholder = (el.textContent || '').trim();
      const looksEmpty = placeholder === '' || /Unbekannt|Keine kürzlichen Tracks gefunden|\s-\s/.test(placeholder);
      
      // Force-Refresh wenn:
      // 1. Last.fm und kein Hash oder leerer Content
      // 2. Trakt und "Keine Aktivitäten"-Platzhalter
      // 3. Expliziter force-refresh-Attribut
      const shouldForce = 
        (isLastfm && (!currentHash || looksEmpty)) || 
        (!isLastfm && placeholder.includes('Keine') && placeholder.includes('gefunden')) ||
        el.hasAttribute('data-ns-force-refresh');
      
      const data = await fetchUpdate(shortcode, currentHash, shouldForce, parsedAttrs);
      const result = updateElementFromResponse(el, data);
      
      // Falls bei diesem Update Content-Änderung festgestellt, aber keine Now-Playing-Markierung,
      // ggf. verwandte Shortcodes aktualisieren (z.B. wenn now-playing gerade geendet hat)
      if (result.changed && el.getAttribute('data-ns-nowplaying') !== '1') {
        const service = getServiceFromShortcode(shortcode);
        if (service === 'lastfm' || service === 'trakt') {
          // Verwandte Elemente asynchron aktualisieren (ohne auf Ergebnis zu warten)
          refreshRelatedElements(service, el).catch(() => {});
        }
      }
    } catch (e) {
      if (nowscrobbling_ajax.debug) {
        console.warn('NowScrobbling initial AJAX error:', e);
      }
      // keep SSR content; optionally attach a small retry button inside the element
      el.classList.remove('nowscrobbling-loading');
      
      // Füge retry-Button hinzu, falls Content eindeutig fehlt
      if ((el.textContent || '').trim() === '' || 
          (el.textContent || '').includes('Keine') && (el.textContent || '').includes('gefunden')) {
        const retryBtn = document.createElement('button');
        retryBtn.className = 'ns-retry-btn';
        retryBtn.textContent = 'Aktualisieren';
        retryBtn.style.cssText = 'font-size:0.8em;padding:2px 8px;margin-top:5px;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;cursor:pointer;';
        retryBtn.onclick = () => {
          el.classList.add('nowscrobbling-loading');
          if (retryBtn.parentNode === el) el.removeChild(retryBtn);
          initializeElement(el).catch(() => {
            el.classList.remove('nowscrobbling-loading');
          });
        };
        el.appendChild(retryBtn);
      }
    }

    // Attach polling only when Now-Playing is active
    const interval = nowscrobbling_ajax.polling?.nowplaying_interval || 20000;
    const maxInterval = nowscrobbling_ajax.polling?.max_interval || 300000;
    if (el.getAttribute('data-ns-nowplaying') === '1') {
      scheduleNowPlayingPoll(el, interval, maxInterval);
    }
  }

  /**
   * Aktualisiert verwandte Elemente desselben Dienstes
   */
  async function refreshRelatedElements(service, sourceEl) {
    try {
      const siblings = [];
      if (service === 'lastfm') {
        siblings.push('nowscr_lastfm_indicator', 'nowscr_lastfm_history');
      } else if (service === 'trakt') {
        siblings.push('nowscr_trakt_indicator', 'nowscr_trakt_history');
      }
      
      const rawAttrs = sourceEl.getAttribute('data-ns-attrs');
      const parsedAttrs = rawAttrs ? JSON.parse(rawAttrs) : null;
      
      const promises = [];
      siblings.forEach(sc => {
        document.querySelectorAll(`[data-nowscrobbling-shortcode="${sc}"]`).forEach(other => {
          if (other === sourceEl) return;
          if (other._nsUpdating) return; // Vermeide gleichzeitige Updates
          
          other._nsUpdating = true;
          const promise = (async () => {
            try {
              const ch = other.dataset.nsHash || null;
              const payload = await fetchUpdate(sc, ch, false, parsedAttrs);
              updateElementFromResponse(other, payload);
            } catch (e) {
              if (nowscrobbling_ajax.debug) {
                console.warn('Related refresh failed:', e);
              }
            } finally {
              other._nsUpdating = false;
            }
          })();
          
          promises.push(promise);
        });
      });
      
      await Promise.all(promises);
    } catch (e) {
      if (nowscrobbling_ajax.debug) {
        console.warn('Related refresh error:', e);
      }
    }
  }

  /**
   * Initialize all shortcode wrappers
   */
  const elements = document.querySelectorAll('[data-nowscrobbling-shortcode]');
  
  // Lazy-initialize elements when they enter the viewport
  if ('IntersectionObserver' in window) {
    // Prio 1: Erst sichtbare Elemente initialisieren
    const visibleElements = [];
    const nonVisibleElements = [];
    
    // Separate sichtbare von unsichtbaren Elementen
    elements.forEach(el => {
      const rect = el.getBoundingClientRect();
      const isVisible = rect.top < window.innerHeight && rect.bottom > 0;
      if (isVisible) {
        visibleElements.push(el);
      } else {
        nonVisibleElements.push(el);
      }
    });
    
    // Sichtbare sofort laden
    visibleElements.forEach(el => {
      initializeElement(el).catch(() => {});
    });
    
    // Unsichtbare lazy laden
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const el = entry.target;
          io.unobserve(el);
          initializeElement(el).catch(() => {});
        }
      });
    }, { root: null, rootMargin: '0px 0px 200px 0px', threshold: 0 });
    
    nonVisibleElements.forEach(el => io.observe(el));
  } else {
    // Fallback für ältere Browser: alle Elemente sequentiell initialisieren
    elements.forEach(el => {
      initializeElement(el).catch(() => {});
    });
  }

  // When tab becomes visible again, refresh all elements and (re)attach polling as needed
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      if (typeof window.nowscrobblingRefresh === 'function') {
        window.nowscrobblingRefresh().catch(() => {});
      }
      const baseInterval = nowscrobbling_ajax.polling?.nowplaying_interval || 20000;
      const maxInterval = nowscrobbling_ajax.polling?.max_interval || 300000;
      document.querySelectorAll('[data-nowscrobbling-shortcode]').forEach((el) => {
        const isNow = el.getAttribute('data-ns-nowplaying') === '1';
        if (isNow) {
          if (!el._nsState || !el._nsState.timer) {
            scheduleNowPlayingPoll(el, baseInterval, maxInterval);
          }
        }
      });
    }
  });

  /**
   * Handle dynamic content (for themes that load content via AJAX)
   */
  const processMutations = (mutations) => {
    // Batch-Verarbeitung aller neuen Elemente über alle Mutationen
    const newElements = new Set();
    
    // Phase 1: Sammle alle hinzugefügten Elemente
    for (const mutation of mutations) {
      // Nur addedNodes verarbeiten
      for (const node of mutation.addedNodes) {
        // Nur Element-Nodes verarbeiten (nodeType 1)
        if (!node || node.nodeType !== 1) continue;
        
        // Element selbst prüfen
        if (node.matches && node.matches('[data-nowscrobbling-shortcode]')) {
          newElements.add(node);
        }
        
        // Kind-Elemente prüfen
        if (node.querySelectorAll) {
          const matches = node.querySelectorAll('[data-nowscrobbling-shortcode]');
          matches.forEach(el => newElements.add(el));
        }
      }
    }
    
    // Phase 2: Verarbeite nur neue, einzigartige Elemente
    if (newElements.size > 0) {
      // Teile in sichtbare und unsichtbare Elemente auf
      const visibleElements = [];
      const nonVisibleElements = [];
      
      newElements.forEach(el => {
        const rect = el.getBoundingClientRect();
        const isVisible = rect.top < window.innerHeight && rect.bottom > 0;
        if (isVisible) {
          visibleElements.push(el);
        } else {
          nonVisibleElements.push(el);
        }
      });
      
      // Sichtbare Elemente direkt initialisieren
      visibleElements.forEach(el => {
        if (!el._nsInitialized) {
          initializeElement(el).catch(() => {});
        }
      });
      
      // Für unsichtbare Elemente IntersectionObserver nutzen
      if (nonVisibleElements.length > 0 && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries, observer) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              const el = entry.target;
              observer.unobserve(el);
              if (!el._nsInitialized) {
                initializeElement(el).catch(() => {});
              }
            }
          });
        }, { root: null, rootMargin: '0px 0px 200px 0px', threshold: 0 });
        
        nonVisibleElements.forEach(el => {
          if (!el._nsInitialized) {
            io.observe(el);
          }
        });
      } else {
        // Fallback: Alle direkt initialisieren
        nonVisibleElements.forEach(el => {
          if (!el._nsInitialized) {
            initializeElement(el).catch(() => {});
          }
        });
      }
    }
  };

  // Optimierte Beobachtung mit Throttling zur Performance-Verbesserung
  let pendingUpdate = false;
  
  const throttledObserver = new MutationObserver(mutations => {
    // Wir nutzen das normale MutationObserver-Pattern, aber throtteln
    // die tatsächliche Verarbeitung mit requestAnimationFrame
    if (!pendingUpdate) {
      pendingUpdate = true;
      requestAnimationFrame(() => {
        throttledObserver.disconnect();
        processMutations(mutations);
        pendingUpdate = false;
      });
    }
  });

  // Observe for dynamically added content
  throttledObserver.observe(document.body, {
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