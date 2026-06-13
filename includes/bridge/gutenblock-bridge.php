<?php
/**
 * GutenBlock Bridge (MU-Plugin)
 * Content-Replacement und Style-Preview für GutenBlock SaaS
 * Version: 2.0.7
 * 
 * INSTALLATION: Wird automatisch von GutenBlock Pro nach /wp-content/mu-plugins/ kopiert
 * MU-Plugins werden automatisch geladen, kein Aktivieren nötig.
 * 
 * WICHTIG: Diese Datei hat KEINEN Plugin-Header, damit WordPress sie nicht als separates Plugin erkennt.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// KONFIGURATION
// ============================================================================

// SaaS API URL - kann per wp-config.php überschrieben werden
if (!defined('GUTENBLOCK_SAAS_API_URL')) {
    if (defined('GUTENBLOCK_DEV_MODE') && GUTENBLOCK_DEV_MODE) {
        define('GUTENBLOCK_SAAS_API_URL', 'http://localhost:3000');
    } else {
        define('GUTENBLOCK_SAAS_API_URL', 'https://app.gutenblock.com');
    }
}

// ============================================================================
// CONTENT REPLACEMENT (Live-Preview im SaaS)
// ============================================================================

add_action('wp_enqueue_scripts', 'gutenblock_bridge_content_replacement');

function gutenblock_bridge_content_replacement() {
    // Nur wenn Content-ID vorhanden
    if (!isset($_GET['gutenblock-content-id'])) {
        return;
    }
    
    $content_id = sanitize_text_field($_GET['gutenblock-content-id']);
    
    // API-URL ermitteln
    $api_url = gutenblock_bridge_get_api_url();
    
    // Inline-Script für Content-Replacement
    $script = gutenblock_bridge_get_replacement_script();
    
    wp_register_script('gutenblock-content-replacement', false);
    wp_enqueue_script('gutenblock-content-replacement');
    wp_add_inline_script('gutenblock-content-replacement', $script);
    
    wp_localize_script('gutenblock-content-replacement', 'gutenblockContent', array(
        'apiUrl' => $api_url,
        'contentId' => $content_id
    ));
}

function gutenblock_bridge_get_api_url() {
    // Localhost-Erkennung
    $current_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $is_localhost = (
        strpos($current_host, 'localhost') !== false ||
        strpos($current_host, '127.0.0.1') !== false ||
        strpos($current_host, '.local') !== false
    );
    
    if (defined('GUTENBLOCK_DEV_MODE') && GUTENBLOCK_DEV_MODE) {
        return 'http://localhost:3000/api/v1/content/';
    } elseif ($is_localhost) {
        return 'http://localhost:3000/api/v1/content/';
    }
    
    return GUTENBLOCK_SAAS_API_URL . '/api/v1/content/';
}

function gutenblock_bridge_get_replacement_script() {
    return <<<'JS'
(function() {
    'use strict';
    
    // Funktion zum Ersetzen von Content (wird mehrfach aufgerufen)
    function replaceContent(data) {
            if (!data.content || typeof data.content !== 'object') {
                console.warn('GutenBlock Bridge: Keine Content-Felder gefunden');
                return;
            }
            
            let replacedCount = 0;
            
            for (const [fieldId, text] of Object.entries(data.content)) {
                if (!text) continue;
                
                // Primär: data-content-field Attribut
            // WICHTIG: querySelectorAll findet auch Elemente mit display:none
                let elements = document.querySelectorAll(`[data-content-field="${fieldId}"]`);
                
                // Fallback: CSS-ID — aber nur wenn das gefundene Element ein
                // Text-Leaf ist (Heading, Paragraph, Span etc.). Sonst würde
                // eine Section-ID wie id="services" den Text-Wert über den GANZEN
                // Section-Inhalt schreiben (textContent = "..." wirft alle
                // Kindelemente weg).
                if (elements.length === 0) {
                    const TEXT_LEAF_TAGS = new Set(['H1','H2','H3','H4','H5','H6','P','SPAN','LI','A','BUTTON','STRONG','EM','BLOCKQUOTE','SMALL','CITE']);
                    const candidates = document.querySelectorAll('#' + CSS.escape(fieldId));
                    elements = Array.from(candidates).filter(el => TEXT_LEAF_TAGS.has(el.tagName));
                }
                
                if (elements.length > 0) {
                    elements.forEach(element => {
                    // Ersetze Text in ALLEN gefundenen Elementen (auch in ausgeblendeten Sections)
                        element.textContent = text;
                        replacedCount++;
                    });
                console.log('GutenBlock Bridge: Ersetzt', fieldId, 'in', elements.length, 'Element(en) →', text.substring(0, 50) + '...');
                } else {
                    console.warn('GutenBlock Bridge: Element nicht gefunden:', fieldId);
                }
            }
            
            console.log(`GutenBlock Bridge: ${replacedCount} Felder ersetzt`);
    }
    
    if (typeof gutenblockContent === 'undefined') {
        console.log('GutenBlock Bridge: Keine Content-Daten vorhanden');
        return;
    }
    
    const { apiUrl, contentId } = gutenblockContent;
    const normalizedApiUrl = apiUrl.endsWith('/') ? apiUrl : apiUrl + '/';
    const fullUrl = normalizedApiUrl + contentId;
    
    console.log('GutenBlock Bridge: Lade Content...', { apiUrl: normalizedApiUrl, contentId, fullUrl });
    
    fetch(fullUrl)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error('API-Fehler: ' + response.status + ' - ' + text);
                });
            }
            return response.json();
        })
        .then(data => {
            // Initial Content-Replacement
            replaceContent(data);
            
            // Erneutes Replacement nach Section-Toggle (falls Sections getoggelt werden)
            // Warte auf postMessage für Section-Toggle
            window.addEventListener('message', function(event) {
                if (event.data && event.data.type === 'gutenblock-section-toggled') {
                    // Nach Section-Toggle: Content erneut ersetzen (falls neue Sections sichtbar wurden)
                    setTimeout(() => {
                        console.log('GutenBlock Bridge: Erneutes Content-Replacement nach Section-Toggle');
                        replaceContent(data);
                    }, 100);
                }
            });
        })
        .catch(error => {
            console.error('GutenBlock Bridge: Fehler beim Laden', error);
        });
})();
JS;
}

// ============================================================================
// LINK-DEAKTIVIERUNG (iFrame-Preview)
// ============================================================================

add_action('wp_enqueue_scripts', 'gutenblock_bridge_disable_links');

function gutenblock_bridge_disable_links() {
    if (!isset($_GET['gutenblock-iframe'])) {
        return;
    }
    
    $script = <<<'JS'
(function() {
    'use strict';
    
    // Link-Deaktivierung
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'gutenblock-link-clicked',
                        message: 'Links sind in der Vorschau deaktiviert. Nutze in der Werkzeugleiste "Seiten" für die Navigation.'
                    }, '*');
                }
        });
    });
});
    
    // Section-Toggle Handler
    window.addEventListener('message', function(event) {
        // SCROLL TO SECTION
        if (event.data && event.data.type === 'gutenblock-scroll-to-section') {
            const { sectionId } = event.data;
            
            // Finde Section mit dieser Klasse
            const section = document.querySelector('.' + sectionId);
            if (section) {
                section.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }
        }
        
        // GET SCROLL POSITION - Parent fragt nach aktueller Position
        if (event.data && event.data.type === 'gutenblock-get-scroll') {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'gutenblock-scroll-position',
                    scrollY: window.scrollY
                }, '*');
            }
        }
        
        // SET SCROLL POSITION - Parent will Position wiederherstellen
        if (event.data && event.data.type === 'gutenblock-set-scroll') {
            const { scrollY } = event.data;
            window.scrollTo({
                top: scrollY,
                behavior: 'instant'
            });
        }
        
        if (event.data && event.data.type === 'gutenblock-toggle-sections') {
            const { pageSlug, hiddenSections } = event.data;
            
            // Finde alle Sections
            const allSections = document.querySelectorAll('[class*="gb-section-"]');
            
            // Track aktuell versteckte Sections
            const currentlyHidden = new Set();
            allSections.forEach(function(section) {
                const style = window.getComputedStyle(section);
                if (style.display === 'none') {
                    const classList = Array.from(section.classList);
                    const sectionClass = classList.find(function(cls) { return cls.startsWith('gb-section-'); });
                    if (sectionClass) currentlyHidden.add(sectionClass);
                }
            });
            
            const newHidden = new Set(hiddenSections || []);
            
            // Sections die ausgeblendet werden sollen
            const toHide = [];
            // Sections die eingeblendet werden sollen
            const toShow = [];
            
            allSections.forEach(function(section) {
                const classList = Array.from(section.classList);
                const sectionClass = classList.find(function(cls) { return cls.startsWith('gb-section-'); });
                if (!sectionClass) return;
                
                const wasHidden = currentlyHidden.has(sectionClass);
                const shouldBeHidden = newHidden.has(sectionClass);
                
                if (!wasHidden && shouldBeHidden) {
                    toHide.push({ element: section, id: sectionClass });
                } else if (wasHidden && !shouldBeHidden) {
                    toShow.push({ element: section, id: sectionClass });
                }
            });
            
            // Fade out Sections die versteckt werden sollen
            // WICHTIG: Erst scrollen, dann verstecken (nur wenn Section sichtbar ist)
            if (toHide.length > 0) {
                const firstToHide = toHide[0];
                const sectionElement = document.querySelector('.' + firstToHide.id);
                if (sectionElement) {
                    // Scrolle zur Section, bevor sie versteckt wird
                    sectionElement.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'start' 
                    });
                }
            }
            
            toHide.forEach(function(item) {
                item.element.classList.add('gutenblock-fading-out');
                item.element.classList.add('gutenblock-in-transition');
            });
            
            // Nach Transition: Setze display: none
            setTimeout(function() {
                let styleTag = document.getElementById('gutenblock-hidden-sections');
                if (!styleTag) {
                    styleTag = document.createElement('style');
                    styleTag.id = 'gutenblock-hidden-sections';
                    document.head.appendChild(styleTag);
                }
                
                let cssRules = '';
                if (hiddenSections && hiddenSections.length > 0) {
                    hiddenSections.forEach(function(sectionId) {
                        cssRules += '.' + sectionId + ' { display: none !important; }\n';
                    });
                }
                
                styleTag.textContent = cssRules;
                
                // Entferne fading-out und transition Klassen
                toHide.forEach(function(item) {
                    item.element.classList.remove('gutenblock-fading-out');
                    item.element.classList.remove('gutenblock-in-transition');
                });
            }, 600);
            
            // Fade in Sections die angezeigt werden sollen
            if (toShow.length > 0) {
                // Entferne display: none sofort (aber opacity ist noch 0)
                let styleTag = document.getElementById('gutenblock-hidden-sections');
                if (!styleTag) {
                    styleTag = document.createElement('style');
                    styleTag.id = 'gutenblock-hidden-sections';
                    document.head.appendChild(styleTag);
                }
                
                let cssRules = '';
                if (hiddenSections && hiddenSections.length > 0) {
                    hiddenSections.forEach(function(sectionId) {
                        cssRules += '.' + sectionId + ' { display: none !important; }\n';
                    });
                }
                
                styleTag.textContent = cssRules;
                
                // Setze opacity auf 0 und zeige Section (damit scroll funktioniert)
                toShow.forEach(function(item) {
                    item.element.style.opacity = '0';
                    item.element.style.visibility = 'visible';
                });
                
                // Sende Signal an Parent: Section ist jetzt im Layout (kann gescrollt werden)
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'gutenblock-section-ready-for-scroll',
                        sectionId: toShow[0].id
                    }, '*');
                    
                    // Sende auch Signal für Content-Replacement
                    window.parent.postMessage({
                        type: 'gutenblock-section-toggled',
                        hiddenSections: hiddenSections
                    }, '*');
                    
                    // Scrolle automatisch zur Section, die gerade angezeigt wurde
                    setTimeout(() => {
                        const sectionElement = document.querySelector('.' + toShow[0].id);
                        if (sectionElement) {
                            sectionElement.scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'start' 
                            });
                        }
                    }, 150); // Kurze Verzögerung, damit Section im Layout ist
                }
                
                // Fade in nach kurzer Verzögerung
                setTimeout(function() {
                    toShow.forEach(function(item) {
                        item.element.style.transition = 'opacity 0.4s ease-in';
                        item.element.style.opacity = '1';
                    });
                }, 100);
            }
        }
    });
    
    // Initial: Verstecke Sections mit gb-section-off Klasse
    document.addEventListener('DOMContentLoaded', function() {
        const offSections = document.querySelectorAll('.gb-section-off');
        if (offSections.length > 0) {
            let styleTag = document.getElementById('gutenblock-hidden-sections');
            if (!styleTag) {
                styleTag = document.createElement('style');
                styleTag.id = 'gutenblock-hidden-sections';
                document.head.appendChild(styleTag);
            }
            
            let cssRules = styleTag.textContent || '';
            offSections.forEach(function(section) {
                const classList = Array.from(section.classList);
                const sectionClass = classList.find(function(cls) { return cls.startsWith('gb-section-'); });
                if (sectionClass && cssRules.indexOf('.' + sectionClass) === -1) {
                    cssRules += '.' + sectionClass + ' { display: none !important; }\n';
                }
            });
            styleTag.textContent = cssRules;
        }
    });
})();
JS;
    
    wp_register_script('gutenblock-disable-links', false);
    wp_enqueue_script('gutenblock-disable-links');
    wp_add_inline_script('gutenblock-disable-links', $script);
    
    // CSS für Fade-Animationen
    $css = <<<'CSS'
<style id="gutenblock-section-animations">
.gutenblock-fading-out {
    opacity: 0 !important;
    transition: opacity 0.6s ease-out !important;
}
.gutenblock-in-transition {
    pointer-events: none !important;
}
</style>
CSS;
    echo $css;
}

// ============================================================================
// CUSTOMIZER-BRIDGE (Live-Anpassung von Colors / Fonts / Shapes via postMessage)
// ============================================================================
//
// Aktiv bei ?gutenblock-iframe=1 (= alle SaaS-Embeddings, auch Preview-only).
// Hört auf postMessage 'gbp:apply-customizer' und patcht zur Laufzeit:
//   - CSS-Custom-Properties (--wp--preset--color--{slug})
//   - Body/Heading-Font-Family (Google-Fonts werden per <link> nachgeladen)
//   - Border-Radius für Buttons & Bilder (Shapes)
//
// Wichtig: Es werden KEINE persistenten Theme-Änderungen vorgenommen — die
// Anpassungen leben nur im aktuellen iframe-Document.
//
add_action('wp_enqueue_scripts', 'gutenblock_bridge_customizer_listener');

function gutenblock_bridge_customizer_listener() {
	if ( ! isset( $_GET['gutenblock-iframe'] ) ) {
		return;
	}

	$script = <<<'JS'
(function() {
	'use strict';

	var STYLE_ID = 'gbp-customizer-style';
	var FONTS_ID = 'gbp-customizer-fonts';

	// Bilder/Cards: dezente bis starke Rundung.
	var SHAPE_RADIUS = {
		none:   '0px',
		subtle: '4px',
		medium: '12px',
		strong: '24px'
	};

	// Buttons: bei "strong" sollen sie aussen rund werden (Pill-Look).
	var BUTTON_RADIUS = {
		none:   '0px',
		subtle: '4px',
		medium: '12px',
		strong: '30px'
	};

	function ensureEl(tag, id) {
		var el = document.getElementById(id);
		if (!el) {
			el = document.createElement(tag);
			el.id = id;
			document.head.appendChild(el);
		}
		return el;
	}

	function buildCss(payload) {
		var lines = [];

		// 1) Theme-Farben überschreiben (CSS Custom Properties)
		if (payload.colors && typeof payload.colors === 'object') {
			lines.push(':root, body, .editor-styles-wrapper {');
			Object.keys(payload.colors).forEach(function(slug) {
				var v = payload.colors[slug];
				if (typeof v === 'string' && v.length) {
					lines.push('  --wp--preset--color--' + slug + ': ' + v + ' !important;');
				}
			});
			lines.push('}');
		}

		// 2) Font-Family für Body und Headings (überschreibt theme.json)
		if (payload.fonts && typeof payload.fonts === 'object') {
			if (payload.fonts.body) {
				lines.push('body, p, li, blockquote, .wp-block-paragraph, .wp-block-list, button, input, textarea, select { font-family: ' + payload.fonts.body + ' !important; }');
			}
			if (payload.fonts.heading) {
				var headingDecl = 'font-family: ' + payload.fonts.heading + ' !important;';
				lines.push('h1, h2, h3, h4, h5, h6, .wp-block-heading, .wp-block-post-title, .wp-block-site-title, .wp-block-query-title { ' + headingDecl + ' }');
			}
			// Globale Schriftstärke (font-weight) für alle Headings.
			if (typeof payload.fonts.headingWeight === 'number' && isFinite(payload.fonts.headingWeight)) {
				var w = Math.round(payload.fonts.headingWeight);
				if (w >= 100 && w <= 1000) {
					lines.push('h1, h2, h3, h4, h5, h6, .wp-block-heading, .wp-block-post-title, .wp-block-site-title, .wp-block-query-title { font-weight: ' + w + ' !important; }');
				}
			}
		}

		// 2b) Semantische Schriftgrößen (H1–H3, Absatz) — Overrides aus dem SaaS-Customizer.
		if (payload.semanticFontSizes && typeof payload.semanticFontSizes === 'object') {
			var sem = payload.semanticFontSizes;
			function headingSizeSel(tag) {
				return tag + ', .wp-block-heading.wp-block-heading-' + tag + ', ' + tag + '.wp-block-heading, ' + tag + '.wp-block-post-title, ' + tag + '.wp-block-site-title, ' + tag + '.wp-block-query-title';
			}
			['h1','h2','h3','h4'].forEach(function(tag) {
				var v = sem[tag];
				if (typeof v === 'string' && v.trim()) {
					lines.push(headingSizeSel(tag) + ' { font-size: ' + v.trim() + ' !important; }');
				}
			});
			if (typeof sem.p === 'string' && sem.p.trim()) {
				lines.push('p, .wp-block-paragraph { font-size: ' + sem.p.trim() + ' !important; }');
			}
		}

		// 3) Shapes: border-radius für Buttons + Bilder
		// - Bilder NUR wenn nicht SVG und NICHT innerhalb eines Cover-Blocks
		//   (sonst bekommt der Cover-Container selbst runde Ecken).
		// - Buttons haben eigene Map: bei "strong" → 30px (außen rund).
		if (payload.shape && SHAPE_RADIUS[payload.shape] !== undefined) {
			var r = SHAPE_RADIUS[payload.shape];
			var br = BUTTON_RADIUS[payload.shape] || r;

			// Buttons
			lines.push('.wp-block-button__link, .wp-element-button, button.wp-block-button__link { border-radius: ' + br + ' !important; }');

			// Bilder: nur <img> in wp-block-image / wp-block-post-featured-image,
			//         nicht im Cover-Bild, nicht für SVG-Pfade,
			//         und NICHT wenn die figure.has-custom-border hat
			//         (= manuell gesetzter Radius im Editor/Pattern).
			lines.push('.wp-block-image:not(.has-custom-border) img:not([src$=".svg"]):not([src$=".SVG"]):not([src*=".svg?"]), .wp-block-post-featured-image img:not([src$=".svg"]):not([src$=".SVG"]):not([src*=".svg?"]) { border-radius: ' + r + ' !important; }');

			// Cover-Block selbst und sein Background-Bild NICHT rund machen
			// (sonst bekommt der Container runde Ecken).
			lines.push('.wp-block-cover, .wp-block-cover .wp-block-cover__image-background, .wp-block-cover img.wp-block-cover__image-background { border-radius: 0 !important; }');
		}

		return lines.join('\n');
	}

	function applyCustomizer(payload) {
		if (!payload || typeof payload !== 'object') return;

		// Style-Tag
		var styleEl = ensureEl('style', STYLE_ID);
		styleEl.textContent = buildCss(payload);

		// Google-Fonts-Link (nur ändern, wenn URL anders ist → kein unnötiges Reload)
		var url = payload.fonts && payload.fonts.googleFontsUrl ? String(payload.fonts.googleFontsUrl) : '';
		if (url) {
			var linkEl = document.getElementById(FONTS_ID);
			if (!linkEl) {
				linkEl = document.createElement('link');
				linkEl.id = FONTS_ID;
				linkEl.rel = 'stylesheet';
				document.head.appendChild(linkEl);
			}
			if (linkEl.getAttribute('href') !== url) {
				linkEl.setAttribute('href', url);
			}
		}
	}

	window.addEventListener('message', function(event) {
		if (!event.data || typeof event.data !== 'object') return;
		if (event.data.type !== 'gbp:apply-customizer') return;
		applyCustomizer(event.data.payload || event.data);
	});

	// Parent benachrichtigen, dass Listener bereit ist (SaaS kann initialen
	// Customizer-State direkt schicken, ohne nach 'load' zu warten).
	try {
		(window.parent || window.opener).postMessage({ type: 'gbp:customizer-ready' }, '*');
	} catch (e) { /* same-origin parent ggf. nicht erreichbar */ }

	// ── Content-Höhe an Parent melden (für Zoom/Canvas-Sizing im SaaS) ──────
	// vh-Sektionen (z.B. Cover-Blocks mit min-height:90vh) referenzieren die
	// iframe-Viewport-Höhe. Wenn der Parent die iframe-Höhe an scrollHeight
	// anpasst, wachsen vh-Elemente mit → Endlosschleife.
	// Wir rechnen alle inline-vh-Werte einmalig in absolute px (Basis: 900px
	// Desktop-Viewport, ähnlich 1440x900 Display) um.
	(function fixViewportHeight() {
		var VIEWPORT_PX = 900;
		var ATTR_DONE = 'data-gbp-vh-fixed';
		var RX = /(\d+(?:\.\d+)?)vh\b/g;

		function convert(value) {
			return value.replace(RX, function (_, num) {
				return Math.round(parseFloat(num) / 100 * VIEWPORT_PX) + 'px';
			});
		}

		function fix(root) {
			var nodes = (root || document).querySelectorAll('[style*="vh"]');
			for (var i = 0; i < nodes.length; i++) {
				var el = nodes[i];
				if (el.getAttribute(ATTR_DONE)) continue;
				var s = el.getAttribute('style');
				if (s && s.indexOf('vh') !== -1) {
					el.setAttribute('style', convert(s));
					el.setAttribute(ATTR_DONE, '1');
				}
			}
		}

		if (document.body) fix();
		document.addEventListener('DOMContentLoaded', function () { fix(); });
		window.addEventListener('load', function () { fix(); });

		if (typeof MutationObserver !== 'undefined') {
			try {
				var mo = new MutationObserver(function (muts) {
					for (var i = 0; i < muts.length; i++) {
						var added = muts[i].addedNodes;
						for (var j = 0; j < added.length; j++) {
							if (added[j].nodeType === 1) fix(added[j]);
						}
					}
				});
				mo.observe(document.documentElement, { childList: true, subtree: true });
			} catch (e) {}
		}
	})();

	function reportHeight() {
		try {
			var h = Math.max(
				document.documentElement.scrollHeight,
				document.body ? document.body.scrollHeight : 0,
				document.documentElement.offsetHeight,
				document.body ? document.body.offsetHeight : 0
			);
			(window.parent || window.opener).postMessage({ type: 'gbp:content-height', height: h }, '*');
		} catch (e) {}
	}
	if (document.readyState === 'complete') reportHeight();
	window.addEventListener('load', reportHeight);
	// Auf Resize / DOM-Mutationen reagieren (debounced).
	var rhTimer = null;
	function scheduleReport() {
		if (rhTimer) clearTimeout(rhTimer);
		rhTimer = setTimeout(reportHeight, 200);
	}
	window.addEventListener('resize', scheduleReport);
	if (typeof MutationObserver !== 'undefined') {
		try {
			var mo = new MutationObserver(scheduleReport);
			mo.observe(document.documentElement, { childList: true, subtree: true, attributes: true });
		} catch (e) {}
	}

	// ── Wheel/Pinch an Parent weiterleiten (für Zoom & Pan im SaaS-Canvas) ──
	// Cross-origin iframes verschlucken wheel-Events. Wir sammeln sie hier
	// und schicken sie als gbp:wheel-Event an den Parent.
	function onWheel(e) {
		try {
			(window.parent || window.opener).postMessage({
				type: 'gbp:wheel',
				ctrlKey: !!e.ctrlKey,
				metaKey: !!e.metaKey,
				deltaX: e.deltaX,
				deltaY: e.deltaY,
				deltaMode: e.deltaMode,
				clientX: e.clientX,
				clientY: e.clientY
			}, '*');
		} catch (err) {}
		// Nativen Scroll/Zoom des iframes verhindern.
		if (e.cancelable) e.preventDefault();
	}
	// EINMALIG am window mit capture:true registrieren — fängt 100% der
	// wheel-Events ab (capture-Phase läuft top-down vor allem anderen).
	// Zusätzliche Listener auf document/body würden DASSELBE Event mehrfach
	// an den Parent weiterleiten und Pan/Zoom dort spürbar schneller machen
	// als wenn der Cursor außerhalb des iframes ist.
	window.addEventListener('wheel', onWheel, { passive: false, capture: true });
})();
JS;

	wp_register_script( 'gutenblock-customizer-bridge', false, array(), GUTENBLOCK_PRO_VERSION ?? null, true );
	wp_enqueue_script( 'gutenblock-customizer-bridge' );
	wp_add_inline_script( 'gutenblock-customizer-bridge', $script );
}

// ============================================================================
// EDITOR-BRIDGE (Section-Click + Hover-Outline für SaaS-Editor)
// ============================================================================
//
// Aktiviert sich nur bei ?gbp_edit=1 zusätzlich zu ?gutenblock-iframe=1.
// Erfasst Klicks auf .gb-pattern-{slug}-Sections und sendet postMessage
// an Parent (SaaS-UI), damit dort die Floating-Toolbar erscheinen kann.
//
add_action('wp_enqueue_scripts', 'gutenblock_bridge_editor_mode');

function gutenblock_bridge_editor_mode() {
	if ( ! isset( $_GET['gbp_edit'] ) ) {
		return;
	}

	$script = <<<'JS'
(function() {
	'use strict';

	function getSectionSlug(el) {
		// NUR gb-section-* matchen (Outer-Wrapper). gb-pattern-* ist ein Inner-Container
		// innerhalb der gleichen Section und darf KEINEN eigenen Eintrag erzeugen.
		for (var i = 0; i < el.classList.length; i++) {
			if (el.classList[i].indexOf('gb-section-') === 0) {
				return el.classList[i].substring('gb-section-'.length);
			}
		}
		return null;
	}

	function getSectionRect(el) {
		var r = el.getBoundingClientRect();
		return {
			top:         r.top  + window.scrollY,
			left:        r.left + window.scrollX,
			width:       r.width,
			height:      r.height,
			viewportTop: r.top,
			viewportLeft: r.left,
		};
	}

	function getSectionIndex(el) {
		var all = document.querySelectorAll('[class*="gb-section-"]');
		for (var i = 0; i < all.length; i++) {
			if (all[i] === el) return i;
		}
		return -1;
	}

	function findSectionEl(slug) {
		var all = document.querySelectorAll('[class*="gb-section-"]');
		for (var i = 0; i < all.length; i++) {
			for (var c = 0; c < all[i].classList.length; c++) {
				if (all[i].classList[c] === 'gb-section-' + slug) return all[i];
			}
		}
		return null;
	}

	function detectTone(el) {
		if (!el || !el.classList) return 'neutral';
		if (el.classList.contains('has-contrast-background-color'))  return 'dark';
		if (el.classList.contains('has-tertiary-background-color'))  return 'soft';
		return 'neutral';
	}

	// Tausch der Tone-Klassen am Section-Container (ohne Roundtrip).
	// Original-Klassen werden in dataset.gbpOriginalClass gespeichert.
	function applyTonePreview(el, tone) {
		if (!el) return;
		if (!el.dataset.gbpOriginalClass) {
			el.dataset.gbpOriginalClass = el.className;
		}
		var classes = el.className.split(/\s+/).filter(function(c) {
			if (c === 'has-background' || c === 'has-text-color') return false;
			if (/^has-[a-z0-9-]+-background-color$/.test(c)) return false;
			// `has-*-color` matchen, aber NICHT `has-*-background-color`
			if (/^has-[a-z0-9-]+-color$/.test(c) && !/-background-color$/.test(c)) return false;
			return c !== '';
		});
		if (tone === 'dark') {
			classes.push('has-contrast-background-color', 'has-background', 'has-base-color', 'has-text-color');
		} else if (tone === 'soft') {
			classes.push('has-tertiary-background-color', 'has-background', 'has-contrast-color', 'has-text-color');
		}
		el.className = classes.join(' ');
	}

	function resetTonePreview(el) {
		if (!el) return;
		if (el.dataset.gbpOriginalClass) {
			el.className = el.dataset.gbpOriginalClass;
			delete el.dataset.gbpOriginalClass;
		}
	}

	function send(type, payload) {
		if (window.parent && window.parent !== window) {
			window.parent.postMessage(Object.assign({ type: type }, payload), '*');
		}
	}

	// Outline-Stile: identische, klar erkennbare Lila-Outline für Hover und
	// Aktiv. Kein Overlay/Tönung über der Section, damit Farben unverfälscht
	// bleiben. Beide Klassen können gleichzeitig vorhanden sein (z.B. wenn
	// die aktive Section auch gehovert wird) — Browser zeigt nur eine Outline.
	var styleTag = document.createElement('style');
	styleTag.id = 'gbp-edit-mode-style';
	styleTag.textContent = [
		'[class*="gb-section-"]{cursor:pointer !important;}',
		// box-shadow inset statt outline: kein Grenz-Ambiguitäts-Problem bei
		// pointer-events, keine outline-offset-Quirks, funktioniert identisch visuell.
		'.gbp-edit-hover,.gbp-edit-active{',
		'box-shadow:inset 0 0 0 2px rgba(124,58,237,0.95) !important;',
		'}',
	].join('');
	document.head.appendChild(styleTag);

	var currentEl = null;

	function bindSection(el) {
		var slug = getSectionSlug(el);
		if (!slug || el.dataset.gbpBound) return;
		el.dataset.gbpBound = '1';

		// Visueller Hover-Hinweis (kein Selection-Event mehr — nur optisches
		// Feedback, dass die Section klickbar ist).
		el.addEventListener('mouseenter', function() {
			if (currentEl !== el) el.classList.add('gbp-edit-hover');
		});
		el.addEventListener('mouseleave', function() {
			if (currentEl !== el) el.classList.remove('gbp-edit-hover');
		});
	}

	// Klick-basierte Selection: Klick in eine Section selektiert sie, Klick
	// ins Leere (außerhalb jeder Section) hebt die Auswahl auf.
	document.addEventListener('click', function(e) {
		// Anchor-Klicks im Edit-Mode unterdrücken (sonst navigiert der iframe).
		var anchor = e.target && e.target.closest ? e.target.closest('a') : null;
		if (anchor) {
			e.preventDefault();
			e.stopPropagation();
		}
		// Section finden
		var sectionEl = null;
		var node = e.target;
		while (node && node !== document.body) {
			if (node.classList && Array.from(node.classList).some(function(cn) {
				return cn.indexOf('gb-section-') === 0;
			})) {
				sectionEl = node;
				break;
			}
			node = node.parentNode;
		}
		if (sectionEl) {
			// Re-select gleicher Section nichts ändern
			if (currentEl === sectionEl) return;
			if (currentEl) {
				currentEl.classList.remove('gbp-edit-hover');
				currentEl.classList.remove('gbp-edit-active');
			}
			currentEl = sectionEl;
			sectionEl.classList.add('gbp-edit-active');
			sectionEl.classList.remove('gbp-edit-hover');
			send('gbp:section-selected', {
				slug:  getSectionSlug(sectionEl),
				tone:  detectTone(sectionEl),
				index: getSectionIndex(sectionEl),
				rect:  getSectionRect(sectionEl),
			});
		} else if (currentEl) {
			// Klick ins Leere → deselect
			currentEl.classList.remove('gbp-edit-active');
			currentEl.classList.remove('gbp-edit-hover');
			currentEl = null;
			send('gbp:section-deselected', {});
		}
	}, true);

	// Scroll → Rect aktualisieren (rAF-throttled, kein Debounce)
	var scrollTicking = false;
	window.addEventListener('scroll', function() {
		if (!currentEl || scrollTicking) return;
		scrollTicking = true;
		requestAnimationFrame(function() {
			scrollTicking = false;
			if (currentEl) send('gbp:section-rect-updated', { rect: getSectionRect(currentEl) });
		});
	}, { passive: true });

	// Nachrichten vom Parent
	window.addEventListener('message', function(event) {
		if (!event.data || typeof event.data !== 'object') return;
		var t = event.data.type;

		if (t === 'gbp:get-active-section') {
			if (currentEl) send('gbp:section-rect-updated', { rect: getSectionRect(currentEl) });

		} else if (t === 'gbp:keep-active' || t === 'gbp:release-active') {
			// Legacy-Kompatibilität: vor dem Klick-basierten Modell genutzt.
			// Selection ist nun rein klick-getrieben.

		} else if (t === 'gbp:reload-page') {
			window.location.reload();

		} else if (t === 'gbp:dom-move') {
			// Smooth DOM-Swap ohne Seiten-Reload
			var slug = event.data.slug;
			var dir  = event.data.direction; // 'up' | 'down'
			var all  = Array.from(document.querySelectorAll('[class*="gb-section-"]'));
			var idx  = -1;
			for (var i = 0; i < all.length; i++) {
				for (var c = 0; c < all[i].classList.length; c++) {
					if (all[i].classList[c] === 'gb-section-' + slug) { idx = i; break; }
				}
				if (idx >= 0) break;
			}
			if (idx < 0) return;
			var elA = all[idx];
			var elB = dir === 'up' ? all[idx - 1] : all[idx + 1];
			if (!elB) return;
			// Beide kurz abdimmen, dann tauschen
			elA.style.transition = 'opacity 0.18s';
			elB.style.transition = 'opacity 0.18s';
			elA.style.opacity = '0.4';
			elB.style.opacity = '0.4';
			setTimeout(function() {
				var parent = elA.parentNode;
				if (dir === 'up') {
					parent.insertBefore(elA, elB);
				} else {
					parent.insertBefore(elB, elA);
				}
				elA.style.opacity = '1';
				elB.style.opacity = '1';
				setTimeout(function() {
					elA.style.transition = '';
					elB.style.transition = '';
				}, 200);
				send('gbp:dom-move-done', {});
			}, 200);

		} else if (t === 'gbp:dom-remove') {
			var slug = event.data.slug;
			var all  = Array.from(document.querySelectorAll('[class*="gb-section-"]'));
			var el   = null;
			for (var i = 0; i < all.length; i++) {
				for (var c = 0; c < all[i].classList.length; c++) {
					if (all[i].classList[c] === 'gb-section-' + slug) { el = all[i]; break; }
				}
				if (el) break;
			}
			if (!el) return;
			var h = el.offsetHeight;
			el.style.transition = 'opacity 0.25s, max-height 0.35s 0.1s';
			el.style.overflow   = 'hidden';
			el.style.maxHeight  = h + 'px';
			requestAnimationFrame(function() {
				el.style.opacity   = '0';
				el.style.maxHeight = '0';
				setTimeout(function() { el.remove(); send('gbp:dom-remove-done', {}); }, 400);
			});

		} else if (t === 'gbp:preview-tone') {
			// Live-Preview: Tone-Klassen am Section-Container tauschen,
			// Original wird gemerkt für reset.
			var el = findSectionEl(event.data.slug);
			applyTonePreview(el, event.data.tone || 'neutral');

		} else if (t === 'gbp:reset-tone') {
			var el = findSectionEl(event.data.slug);
			resetTonePreview(el);

		} else if (t === 'gbp:request-sections-list') {
			// Liefert Slug + iframe-relative Geometrie aller Sections.
			var reqId = event.data.id;
			var sections = [];
			document.querySelectorAll('[class*="gb-section-"]').forEach(function(el) {
				var slug = '';
				for (var c = 0; c < el.classList.length; c++) {
					var cn = el.classList[c];
					if (cn.indexOf('gb-section-') === 0) { slug = cn.replace('gb-section-', ''); break; }
				}
				if (!slug) return;
				var rect = el.getBoundingClientRect();
				sections.push({
					slug: slug,
					rect: {
						top: rect.top + window.scrollY,
						left: rect.left + window.scrollX,
						width: rect.width,
						height: rect.height,
						viewportTop: rect.top + window.scrollY,
						viewportLeft: rect.left + window.scrollX,
					},
				});
			});
			send('gbp:sections-list', { id: reqId, sections: sections });

		} else if (t === 'gbp:drag-start') {
			// Smoother Drag-Start: Section visuell "anheben", andere Sections
			// bereit für Slot-Animation. Die gezogene Section bleibt im DOM
			// (für stabile Indexberechnung) und wird per transform/opacity
			// hervorgehoben. Andere Sections bekommen später margin-top, um
			// einen Slot zu öffnen.
			var fromSlug = event.data.slug;
			var src = findSectionEl(fromSlug);
			if (!src) return;
			// Falls schon ein Drag aktiv ist — abbrechen
			if (window.__gbpDrag) return;
			var rect = src.getBoundingClientRect();
			var fromIndex = getSectionIndex(src);
			window.__gbpDrag = {
				el: src,
				origHeight: rect.height,
				pointerStartY: event.data.pointerY,
				fromIndex: fromIndex,
				targetIndex: fromIndex,
				flowShift: 0,
			};
			src.style.transition = 'opacity 0.15s ease, box-shadow 0.18s ease, transform 0.05s linear';
			src.style.opacity = '0.85';
			src.style.boxShadow = '0 16px 40px rgba(124,58,237,0.30), 0 0 0 2px rgba(124,58,237,0.85)';
			src.style.zIndex = '50';
			src.style.position = 'relative';
			src.style.pointerEvents = 'none';
			src.classList.add('gbp-dragging');

		} else if (t === 'gbp:drag-move') {
			var dragSt = window.__gbpDrag;
			if (!dragSt) return;
			var pointerY = event.data.pointerY;
			var dy = pointerY - dragSt.pointerStartY;
			// Ziel-Index basierend auf Pointer-Mitte: vor welcher Section landet er?
			var allSecs = Array.from(document.querySelectorAll('[class*="gb-section-"]'));
			var others  = allSecs.filter(function(e) { return e !== dragSt.el; });
			var target  = others.length;
			for (var i = 0; i < others.length; i++) {
				var r   = others[i].getBoundingClientRect();
				var mid = r.top + window.scrollY + r.height / 2;
				if (pointerY < mid) { target = i; break; }
			}
			if (target !== dragSt.targetIndex) dragSt.targetIndex = target;
			// Synchrone Slot-Animation: gleichzeitig den Original-Slot der
			// gezogenen Section schließen UND den Ziel-Slot am targetIndex
			// öffnen. Beides wird per margin-top (bzw. margin-bottom für die
			// letzte Position) gesteuert und mit 0.18s ease animiert.
			//
			// fromI = Index in `others` der unmittelbar nachfolgenden Section
			//         der gezogenen Section. Diese rückt um origHeight nach
			//         oben → schließt den Original-Slot.
			// target = Index in `others`, vor dem die Section landen würde.
			//          Diese bekommt margin-top: +origHeight → öffnet neuen Slot.
			//
			// Bei target === fromI heben sich +/- auf → 0 (kein Bewegung).
			// Bei target am Ende (= others.length) wird statt margin-top auf
			// das (nicht existente) Element ein margin-bottom auf das letzte
			// Element gesetzt.
			var slot = dragSt.origHeight;
			var fromI = dragSt.fromIndex;
			var lastI = others.length - 1;
			others.forEach(function(s, i) {
				if (!s.style.transition || s.style.transition.indexOf('margin') < 0) {
					s.style.transition = 'margin-top 0.18s ease, margin-bottom 0.18s ease';
				}
				var mt = 0;
				var mb = 0;
				// Original-Slot schließen (es sei denn, gezogene war die letzte
				// Section — dann gibt's keine nachfolgende und wir kürzen unten).
				if (i === fromI && fromI <= lastI) mt -= slot;
				if (fromI > lastI && i === lastI) mb -= slot;
				// Neuen Slot öffnen am Ziel-Index.
				if (i === target && target <= lastI) mt += slot;
				if (target > lastI && i === lastI) mb += slot;
				s.style.marginTop = mt ? mt + 'px' : '';
				s.style.marginBottom = mb ? mb + 'px' : '';
			});

			// DOM-Flow-Kompensation der gezogenen Section:
			// Wenn der neue Slot VOR der gezogenen Section geöffnet wird
			// (target < fromI), schiebt sich die gezogene Section im DOM-Flow
			// um +slot nach unten — zusätzlich zur translateY(dy). Das würde
			// den Cursor relativ zur Section nach oben verschieben (User-Bug:
			// "Handle verliert die vertikale Mitte"). Kompensation: dy -= slot.
			//
			// Damit die Kompensation gleichzeitig mit dem Slot-Open animiert
			// (sonst schnappt die Section sofort um -slot, während die anderen
			// 0.18s brauchen, um aufzugehen), bekommt das transform dieselbe
			// 0.18s ease Transition für die Dauer der Kompensation.
			var flowShift = (target < fromI) ? slot : 0;
			var needAnim = flowShift !== dragSt.flowShift;
			dragSt.flowShift = flowShift;
			if (needAnim) {
				dragSt.el.style.transition = 'transform 0.18s ease, opacity 0.15s ease, box-shadow 0.18s ease';
			} else {
				dragSt.el.style.transition = 'transform 0.05s linear, opacity 0.15s ease, box-shadow 0.18s ease';
			}
			dragSt.el.style.transform = 'translateY(' + (dy - flowShift) + 'px)';

		} else if (t === 'gbp:clear-selection') {
			// SaaS hat die Selection extern entfernt (z.B. Klick in leeren
			// Canvas-Bereich, Tab-Switch, Section-Remove). Bridge muss ihren
			// `currentEl`-Zustand mitführen, sonst behandelt der nächste
			// Klick auf dieselbe Section ein "Re-Select" und sendet kein
			// `gbp:section-selected` mehr.
			if (currentEl) {
				currentEl.classList.remove('gbp-edit-active');
				currentEl.classList.remove('gbp-edit-hover');
				currentEl = null;
			}

		} else if (t === 'gbp:apply-tone') {
			// Smooth Tone-Wechsel: Klassen lokal austauschen + Transitions
			// für background-color / color spendieren, damit der Wechsel
			// fließend statt als harter Reload wirkt. Server-Stand wird
			// parallel via API aktualisiert (ohne Reload).
			var toneSlug = event.data.slug;
			var toneVal  = String(event.data.tone || 'neutral');
			var sectionEl = findSectionEl(toneSlug);
			if (!sectionEl) return;
			// Eindeutige Tone-Profile (analog class-tone-injector.php).
			var TONE_PROFILES = {
				neutral: { bg: null,        text: null     },
				dark:    { bg: 'contrast',  text: 'base'   },
				soft:    { bg: 'tertiary',  text: 'contrast' },
			};
			var profile = TONE_PROFILES[toneVal] || TONE_PROFILES.neutral;
			// Transition setzen — auch auf direkte Children, damit innere
			// Container/Text-Elemente die Farbänderung mitanimieren.
			var transition = 'background-color 0.4s ease, color 0.4s ease, border-color 0.4s ease, fill 0.4s ease';
			sectionEl.style.transition = transition;
			sectionEl.querySelectorAll('*').forEach(function(child) {
				// Nur leichte Transition auf direkten Stylings, damit nichts
				// unerwartet animiert (z.B. Bilder/Links).
				if (!child.style.transition) child.style.transition = transition;
			});
			// Bestehende Tone-Klassen entfernen.
			var classes = Array.prototype.filter.call(sectionEl.classList, function(c) {
				return /^has-[a-z0-9-]+-background-color$/.test(c)
					|| /^has-[a-z0-9-]+-color$/.test(c)
					|| c === 'has-background'
					|| c === 'has-text-color';
			});
			classes.forEach(function(c) { sectionEl.classList.remove(c); });
			// Inline-style-Farben (selten gesetzt, aber falls Theme das tut) entfernen.
			sectionEl.style.removeProperty('background-color');
			sectionEl.style.removeProperty('color');
			// Neue Klassen setzen (außer neutral).
			if (profile.bg) {
				sectionEl.classList.add('has-' + profile.bg + '-background-color');
				sectionEl.classList.add('has-background');
			}
			if (profile.text) {
				sectionEl.classList.add('has-' + profile.text + '-color');
				sectionEl.classList.add('has-text-color');
			}
			// Transitions nach 600ms aufräumen, damit spätere Wechsel ohne
			// Verzögerung sauber starten.
			setTimeout(function() {
				sectionEl.style.transition = '';
				sectionEl.querySelectorAll('*').forEach(function(child) {
					if (child.style.transition === transition) child.style.transition = '';
				});
			}, 600);

		} else if (t === 'gbp:drag-end') {
			var dragEnd = window.__gbpDrag;
			if (!dragEnd) return;
			window.__gbpDrag = null;
			var srcEl = dragEnd.el;
			var slug  = getSectionSlug(srcEl);
			var fromI = dragEnd.fromIndex;
			var toI   = dragEnd.targetIndex;
			var others = Array.from(document.querySelectorAll('[class*="gb-section-"]')).filter(function(e) { return e !== srcEl; });

			// FLIP-Drop-Animation:
			// 1. rectBefore = aktuelle visuelle Drop-Position (Section ist per
			//    translateY(dy - flowShift) am Cursor)
			// 2. Drag-Styles + others-Margins SOFORT (ohne transition) entfernen,
			//    DOM umsortieren → DOM ist nun in Ziel-Konstellation, ALLE
			//    others-Sections sind visuell an ihrer endgültigen Position.
			//    (Die margin-Slot-Logik im drag-move simuliert das DOM-Layout
			//    geometrie-exakt, daher springen die others nicht.)
			// 3. rectAfter = neue DOM-Position der Section (transform = '')
			// 4. Inverse-Transform translateY(rectBefore - rectAfter) → Section
			//    bleibt visuell an der Drop-Position.
			// 5. Force reflow + transition + transform = '' → smooth Animation
			//    von Drop-Position → neue DOM-Position.

			var rectBefore = srcEl.getBoundingClientRect();

			srcEl.style.transition = 'none';
			srcEl.style.transform = '';
			srcEl.style.opacity = '1';
			srcEl.style.boxShadow = '';
			srcEl.style.zIndex = '';
			srcEl.style.position = '';
			srcEl.style.pointerEvents = '';

			others.forEach(function(s) {
				s.style.transition = 'none';
				s.style.marginTop = '';
				s.style.marginBottom = '';
			});

			if (toI !== fromI) {
				var freshOthers = Array.from(document.querySelectorAll('[class*="gb-section-"]')).filter(function(e) { return e !== srcEl; });
				var refEl  = toI >= freshOthers.length ? null : freshOthers[toI];
				var parent = srcEl.parentNode;
				if (refEl) parent.insertBefore(srcEl, refEl); else parent.appendChild(srcEl);
			}

			srcEl.classList.remove('gbp-dragging');

			var rectAfter = srcEl.getBoundingClientRect();
			var flipDy = rectBefore.top - rectAfter.top;

			srcEl.style.transform = 'translateY(' + flipDy + 'px)';
			// Force reflow, damit der Browser die Inverse-Position registriert
			// bevor die Transition gesetzt wird (sonst keine Animation).
			void srcEl.offsetHeight;

			var DROP_DUR = 240;
			srcEl.style.transition = 'transform ' + DROP_DUR + 'ms cubic-bezier(0.22, 0.61, 0.36, 1)';
			srcEl.style.transform = '';

			// Während der Animation kontinuierlich rect-updates an die SaaS
			// schicken, damit Drag-Handle und "+ Section"-Button SYNCHRON mit
			// der Section animieren (rAF, ~60fps).
			var animStart = performance.now();
			(function tickRect() {
				send('gbp:section-rect-updated', { rect: getSectionRect(srcEl) });
				if (performance.now() - animStart < DROP_DUR) {
					requestAnimationFrame(tickRect);
				}
			})();

			// Cleanup nach Animation
			setTimeout(function() {
				srcEl.style.transition = '';
				others.forEach(function(s) { s.style.transition = ''; });
				// Section bleibt aktiv (currentEl unverändert). Active-Outline
				// wieder anwenden, falls sie durch das Drag-Highlight (boxShadow)
				// verdrängt war.
				if (currentEl !== srcEl) {
					if (currentEl) {
						currentEl.classList.remove('gbp-edit-active');
						currentEl.classList.remove('gbp-edit-hover');
					}
					currentEl = srcEl;
				}
				srcEl.classList.add('gbp-edit-active');
				// Final rect-update (Drag-Handle/Overlay an finaler Position)
				send('gbp:section-rect-updated', { rect: getSectionRect(srcEl) });
				// Position-Update zurück an SaaS — der macht den API-Call
				send('gbp:drag-completed', {
					from_slug:  slug,
					from_index: fromI,
					to_index:   toI,
					changed:    toI !== fromI,
				});
			}, DROP_DUR + 20);
		}
	});

	// Alle vorhandenen Sections binden + MutationObserver für dynamische Inhalte
	function bindAll() {
		document.querySelectorAll('[class*="gb-section-"]').forEach(bindSection);
	}

	document.addEventListener('DOMContentLoaded', function() {
		bindAll();
		new MutationObserver(function() { bindAll(); })
			.observe(document.body, { childList: true, subtree: true });
		send('gbp:editor-ready', {});
	});

	// Fallback: falls DOMContentLoaded schon vorbei ist
	if (document.readyState !== 'loading') {
		bindAll();
		send('gbp:editor-ready', {});
	}
})();
JS;

	wp_register_script( 'gutenblock-editor-bridge', false );
	wp_enqueue_script( 'gutenblock-editor-bridge' );
	wp_add_inline_script( 'gutenblock-editor-bridge', $script );
}

// ============================================================================
// CORS & CSP HEADERS
// ============================================================================

add_action('send_headers', 'gutenblock_bridge_send_headers');

function gutenblock_bridge_send_headers() {
    header_remove('X-Frame-Options');
    header("Content-Security-Policy: frame-ancestors 'self' https://gutenblock.com https://app.gutenblock.com https://*.vercel.app http://localhost:3000;");
}

add_action('rest_api_init', 'gutenblock_bridge_cors_headers');

function gutenblock_bridge_cors_headers() {
    add_filter('rest_pre_serve_request', function ($value) {
        $origin = get_http_origin();
        $allowed = array(
            'https://gutenblock.com',
            'https://app.gutenblock.com',
            'http://localhost:3000'
        );
        
        // Auch Vercel Preview URLs erlauben
        if ($origin && (in_array($origin, $allowed, true) || strpos($origin, '.vercel.app') !== false)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Allow-Headers: Authorization, Content-Type");
        }
        return $value;
    });
}

// ============================================================================
// STYLE-VARIANTEN API
// ============================================================================

add_action('rest_api_init', 'gutenblock_bridge_register_api');

function gutenblock_bridge_register_api() {
    register_rest_route('gutenblock/v1', '/style-variants', array(
        'methods' => 'GET',
        'callback' => 'gutenblock_bridge_get_style_variants',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('gutenblock/v1', '/pages', array(
        'methods' => 'GET',
        'callback' => 'gutenblock_bridge_get_pages',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('gutenblock/v1', '/sections', array(
        'methods' => 'GET',
        'callback' => 'gutenblock_bridge_get_sections',
        'permission_callback' => '__return_true',
        'args' => array(
            'page' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));
    
    register_rest_route('gutenblock/v1', '/clear-cache', array(
        'methods' => 'POST',
        'callback' => 'gutenblock_bridge_clear_cache',
        'permission_callback' => '__return_true'
    ));
}

function gutenblock_bridge_get_style_variants() {
    $theme = wp_get_theme();
    $stylesheet_dir = get_stylesheet_directory();
    $styles_dir = $stylesheet_dir . '/styles';
    $theme_json_file = $stylesheet_dir . '/theme.json';
    
    $variants = array();
    $default_variant = null;
    
    // Lade Standard-Theme
    if (class_exists('WP_Theme_JSON_Resolver')) {
        $theme_json = WP_Theme_JSON_Resolver::get_merged_data('custom');
        $theme_data = $theme_json->get_data();
        
        $palette = null;
        if (isset($theme_data['settings']['color']['palette'])) {
            $palette = $theme_data['settings']['color']['palette'];
        }
        
        if ($palette && is_array($palette)) {
            $default_variant = gutenblock_bridge_extract_variant_data(array(
                'settings' => array('color' => array('palette' => $palette)),
                'styles' => isset($theme_data['styles']) ? $theme_data['styles'] : array()
            ), '', 'Standard');
        }
    }
    
    // Lade Style-Variationen
    if (is_dir($styles_dir)) {
        $files = glob($styles_dir . '/*.json');
        
        foreach ($files as $file) {
            $json_content = file_get_contents($file);
            $style_data = json_decode($json_content, true);
            
            if ($style_data && isset($style_data['title'])) {
                $slug = basename($file, '.json');
                $variant_data = gutenblock_bridge_extract_variant_data($style_data, $slug, $style_data['title']);
                if ($variant_data) {
                    $variants[] = $variant_data;
                }
            }
        }
    }
    
    return array(
        'variants' => $variants,
        'theme' => $theme->get('Name'),
        'default' => $default_variant
    );
}

function gutenblock_bridge_extract_variant_data($style_data, $slug, $title) {
    $base_color = '#CCCCCC';
    $contrast_color = '#333333';
    
    $palette = null;
    if (isset($style_data['settings']['color']['palette']['theme'])) {
        $palette = $style_data['settings']['color']['palette']['theme'];
    } elseif (isset($style_data['settings']['color']['palette'])) {
        $palette = $style_data['settings']['color']['palette'];
    }
    
    if (!is_array($palette)) {
        $palette = array();
    }
    
    if (!empty($palette)) {
        foreach ($palette as $color_item) {
            if (isset($color_item['slug']) && $color_item['slug'] === 'base' && isset($color_item['color'])) {
                $base_color = $color_item['color'];
                break;
            }
        }
        if ($base_color === '#CCCCCC' && isset($palette[0]['color'])) {
            $base_color = $palette[0]['color'];
        }
        
        foreach ($palette as $color_item) {
            if (isset($color_item['slug']) && $color_item['slug'] === 'contrast' && isset($color_item['color'])) {
                $contrast_color = $color_item['color'];
                break;
            }
        }
        if ($contrast_color === '#333333' && isset($palette[1]['color'])) {
            $contrast_color = $palette[1]['color'];
        }
    }
    
    return array(
        'id' => $slug,
        'name' => $title,
        'color' => $base_color,
        'baseColor' => $base_color,
        'contrastColor' => $contrast_color,
        'palette' => $palette
    );
}

function gutenblock_bridge_get_pages() {
    $pages = get_pages(array(
        'post_status' => 'publish',
        'sort_column' => 'menu_order,post_title'
    ));
    
    $result = array();
    
    foreach ($pages as $page) {
        $result[] = array(
            'id' => $page->ID,
            'title' => $page->post_title,
            'slug' => $page->post_name,
            'url' => get_permalink($page->ID)
        );
    }
    
    return new WP_REST_Response($result, 200);
}

function gutenblock_bridge_get_sections($request) {
    $page_slug = $request->get_param('page');
    $page = get_page_by_path($page_slug);
    
    if (!$page) {
        return new WP_REST_Response(array('error' => 'Page not found'), 404);
    }
    
    $content = apply_filters('the_content', $page->post_content);
    
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content);
    
    $sections = array();
    $section_groups = array();
    $xpath = new DOMXPath($dom);
    
    $section_nodes = $xpath->query("//section[contains(@class, 'gb-section-')]");
    
    foreach ($section_nodes as $node) {
        $classes = $node->getAttribute('class');
        
        if (preg_match('/gb-section-([a-z0-9-]+)/i', $classes, $matches)) {
            $section_id = 'gb-section-' . $matches[1];
            $section_slug = $matches[1];
            
            $base_slug = $section_slug;
            $variant_number = null;
            if (preg_match('/^(.+)-v(\d+)$/', $section_slug, $variant_matches)) {
                $base_slug = $variant_matches[1];
                $variant_number = intval($variant_matches[2]);
            }
            
            $base_id = 'gb-section-' . $base_slug;
            $section_name = ucfirst(str_replace('-', ' ', $base_slug));
            
            $is_hidden_by_default = preg_match('/\bgb-section-off\b/', $classes);
            
            if (!isset($section_groups[$base_id])) {
                $section_groups[$base_id] = array(
                    'id' => $base_id,
                    'name' => $section_name,
                    'variants' => array()
                );
            }
            
            $section_groups[$base_id]['variants'][] = array(
                'id' => $section_id,
                'name' => $variant_number ? $section_name . ' v' . $variant_number : $section_name,
                'isDefault' => $variant_number === null,
                'isHiddenByDefault' => $is_hidden_by_default
            );
        }
    }
    
    foreach ($section_groups as $group) {
        usort($group['variants'], function($a, $b) {
            if ($a['isDefault']) return -1;
            if ($b['isDefault']) return 1;
            return 0;
        });
        
        $default_variant = null;
        foreach ($group['variants'] as $variant) {
            if (!$variant['isHiddenByDefault']) {
                $default_variant = $variant['id'];
                break;
            }
        }
        if (!$default_variant) {
            $default_variant = $group['variants'][0]['id'];
        }
        
        $sections[] = array(
            'id' => $group['id'],
            'name' => $group['name'],
            'variants' => $group['variants'],
            'hasVariants' => count($group['variants']) > 1,
            'defaultVariant' => $default_variant,
            'isHiddenByDefault' => $group['variants'][0]['isHiddenByDefault']
        );
    }
    
    return new WP_REST_Response($sections, 200);
}

/**
 * POST /wp-json/gutenblock/v1/clear-cache
 * Löscht alle GutenBlock Caches (Pages, Sections, Style Variants)
 */
function gutenblock_bridge_clear_cache() {
    global $wpdb;
    
    // Lösche alle GutenBlock-bezogenen Transients
    $deleted = 0;
    
    // Pattern für unsere Transients
    $patterns = array(
        '_transient_gutenblock_pages_%',
        '_transient_gutenblock_sections_%',
        '_transient_gutenblock_style_variants_%'
    );
    
    foreach ($patterns as $pattern) {
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );
        $deleted += $result;
    }
    
    // Lösche auch Timeout-Transients
    $timeout_patterns = array(
        '_transient_timeout_gutenblock_pages_%',
        '_transient_timeout_gutenblock_sections_%',
        '_transient_timeout_gutenblock_style_variants_%'
    );
    
    foreach ($timeout_patterns as $pattern) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'deleted' => $deleted,
        'message' => 'Cache erfolgreich gelöscht'
    ), 200);
}

// ============================================================================
// CUSTOM STYLES
// ============================================================================

if (!function_exists('gutenblock_bridge_enqueue_custom_styles')) {
    function gutenblock_bridge_enqueue_custom_styles() {
        static $enqueued = false;
        
        if ($enqueued) {
            return;
        }
        
        $css_file = get_stylesheet_directory() . '/css/gutenblock-custom-styles.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'gutenblock-custom-styles',
                get_stylesheet_directory_uri() . '/css/gutenblock-custom-styles.css',
                array(),
                filemtime($css_file)
            );
            $enqueued = true;
        }
    }
    
    add_action('wp_enqueue_scripts', 'gutenblock_bridge_enqueue_custom_styles', 999);
    add_action('enqueue_block_editor_assets', 'gutenblock_bridge_enqueue_custom_styles', 999);
}

// ============================================================================
// CACHE-DEAKTIVIERUNG FÜR PREVIEW
// ============================================================================

if (!empty($_GET['gutenblock-preview']) || !empty($_GET['gutenblock-preview-content'])) {
    add_filter('wp_cache_themes_persistently', '__return_false');
    if (!defined('WP_CACHE')) {
        define('WP_CACHE', false);
    }
}

// ============================================================================
// ACTIVE STYLE FÜR EDITOR
// ============================================================================

add_filter('pre_option_wp_theme_preview', function($value) {
    if (!empty($_GET['gutenblock-preview'])) {
        return sanitize_text_field($_GET['gutenblock-preview']);
    }
    return $value;
});

// ============================================================================
// STYLE-PREVIEW (Inline CSS für Varianten)
// ============================================================================

add_filter('wp_theme_json_data_theme', 'gutenblock_bridge_apply_style_variant', 20);

function gutenblock_bridge_apply_style_variant($theme_json) {
    $style_slug = !empty($_GET['gutenblock-preview']) 
        ? sanitize_text_field($_GET['gutenblock-preview']) 
        : get_option('gutenblock_active_style', '');
    
    if (!$style_slug) {
        return $theme_json;
    }
    
    $style_file = get_stylesheet_directory() . '/styles/' . $style_slug . '.json';
    if (!file_exists($style_file)) {
        $style_file = get_template_directory() . '/styles/' . $style_slug . '.json';
    }
    
    if (!file_exists($style_file)) {
        return $theme_json;
    }
    
    $style_json = file_get_contents($style_file);
    $style_variation = json_decode($style_json, true);
    
    if (!$style_variation || !is_array($style_variation)) {
        return $theme_json;
    }
    
    $theme_data = $theme_json->get_data();
    $modified = false;
    
    if (isset($style_variation['settings'])) {
        if (!isset($theme_data['settings'])) {
            $theme_data['settings'] = array();
        }
        
        if (isset($style_variation['settings']['color']['palette'])) {
            if (!isset($theme_data['settings']['color'])) {
                $theme_data['settings']['color'] = array();
            }
            $theme_data['settings']['color']['palette'] = $style_variation['settings']['color']['palette'];
            $modified = true;
        }
        
        if (isset($style_variation['settings']['typography'])) {
            if (!isset($theme_data['settings']['typography'])) {
                $theme_data['settings']['typography'] = array();
            }
            $theme_data['settings']['typography'] = array_merge(
                $theme_data['settings']['typography'],
                $style_variation['settings']['typography']
            );
            $modified = true;
        }
        
        if (isset($style_variation['settings']['layout'])) {
            if (!isset($theme_data['settings']['layout'])) {
                $theme_data['settings']['layout'] = array();
            }
            $theme_data['settings']['layout'] = array_merge(
                $theme_data['settings']['layout'],
                $style_variation['settings']['layout']
            );
            $modified = true;
        }
    }
    
    if (isset($style_variation['styles'])) {
        if (!isset($theme_data['styles'])) {
            $theme_data['styles'] = array();
        }
        $theme_data['styles'] = array_merge_recursive($theme_data['styles'], $style_variation['styles']);
        $modified = true;
    }
    
    if ($modified) {
        return $theme_json->update_with($theme_data);
    }
    
    return $theme_json;
}

add_action('wp_head', 'gutenblock_bridge_inline_css', 999);

function gutenblock_bridge_inline_css() {
    $style_slug = !empty($_GET['gutenblock-preview']) 
        ? sanitize_text_field($_GET['gutenblock-preview']) 
        : get_option('gutenblock_active_style', '');
    
    if (!$style_slug) {
        return;
    }
    
    $style_file = get_stylesheet_directory() . '/styles/' . $style_slug . '.json';
    if (!file_exists($style_file)) {
        $style_file = get_template_directory() . '/styles/' . $style_slug . '.json';
    }
    
    if (!file_exists($style_file)) {
        return;
    }
    
    $style_json = file_get_contents($style_file);
    $style_data = json_decode($style_json, true);
    
    if (!$style_data) {
        return;
    }
    
    echo '<style id="gutenblock-preview-inline">';
    echo ':root {';
    
    if (isset($style_data['settings']['color']['palette'])) {
        foreach ($style_data['settings']['color']['palette'] as $color) {
            if (isset($color['slug']) && isset($color['color'])) {
                echo '--wp--preset--color--' . esc_attr($color['slug']) . ':' . esc_attr($color['color']) . ';';
            }
        }
    }
    
    if (isset($style_data['settings']['typography']['fontFamilies'])) {
        foreach ($style_data['settings']['typography']['fontFamilies'] as $font) {
            if (isset($font['slug']) && isset($font['fontFamily'])) {
                echo '--wp--preset--font-family--' . esc_attr($font['slug']) . ':' . esc_attr($font['fontFamily']) . ';';
            }
        }
    }
    
    echo '}';
    echo '</style>';
    
    $mode = !empty($_GET['gutenblock-preview']) ? 'Preview' : 'Persistent';
    echo '<!-- GutenBlock ' . $mode . ': ' . esc_attr($style_slug) . ' (Farben + Typografie) -->';
}

// Pattern-Builder wird vom Haupt-Plugin (gutenblock-pro.php) geladen — vermeidet doppelte REST-Registrierung.
