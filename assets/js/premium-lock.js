/**
 * GutenBlock Pro - Premium Pattern Lock
 * Einfach: Verstecke alle Einstellungen für Premium-Patterns
 */

(function() {
	'use strict';
	
	const hasPremium = window.gutenblockProConfig?.hasPremium || false;
	const upgradeUrl = window.gutenblockProConfig?.upgradeUrl || 'https://app.gutenblock.com/licenses';
	
	if (hasPremium) return;
	
	const PREMIUM_PATTERNS = ['gb-section-hero-v2', 'gb-section-cta-v1'];
	
	function isPremiumPattern() {
		if (!window.wp?.data) return false;
		const block = window.wp.data.select('core/block-editor')?.getSelectedBlock();
		if (!block) return false;
		
		const isOuter = block.name === 'core/cover' || block.name === 'core/group';
		if (!isOuter) return false;
		
		const className = block.attributes?.className || '';
		return PREMIUM_PATTERNS.some(pattern => className.includes(pattern));
	}
	
	function lockPremiumPattern() {
		const inspector = document.querySelector('.block-editor-block-inspector');
		if (!inspector) return;
		
		const isPremium = isPremiumPattern();
		
		if (isPremium) {
			console.log('[GutenBlock Pro] 🔒 Premium pattern detected - hiding panels');
			
			// Verstecke ALLE Panels außer unserem Upgrade-Hinweis
			inspector.querySelectorAll('.components-panel__body').forEach(panel => {
				// Behalte Panels mit data-gb-premium oder data-gb-premium-notice
				if (panel.hasAttribute('data-gb-premium') || panel.hasAttribute('data-gb-premium-notice')) {
					panel.style.display = '';
					return;
				}
				// Verstecke alle anderen
				panel.style.display = 'none';
			});
			
			// Füge Upgrade-Hinweis hinzu (wenn noch nicht vorhanden)
			if (!inspector.querySelector('[data-gb-premium]')) {
				const notice = document.createElement('div');
				notice.setAttribute('data-gb-premium', 'true');
				notice.className = 'components-panel__body is-opened';
				notice.innerHTML = `
					<h2 class="components-panel__body-title">Premium Pattern</h2>
					<div style="padding: 16px;">
						<div class="components-notice is-warning">
							<div class="components-notice__content">
								<strong>🔒 Pro Plus erforderlich</strong>
								<p style="margin: 8px 0;">Dieses Pattern kann als Vorschau eingefügt werden, ist aber nur mit GutenBlock Pro Plus bearbeitbar.</p>
								<button class="components-button is-primary" style="margin-top: 12px;" onclick="window.open('${upgradeUrl}', '_blank')">
									Jetzt upgraden
								</button>
							</div>
						</div>
					</div>
				`;
				inspector.insertBefore(notice, inspector.firstChild);
			}
		} else {
			// Nicht Premium: Zeige alle Panels wieder, entferne unseren Hinweis
			inspector.querySelectorAll('.components-panel__body').forEach(panel => {
				if (!panel.hasAttribute('data-gb-premium')) {
					panel.style.display = '';
				}
			});
			
			const notice = inspector.querySelector('[data-gb-premium]');
			if (notice) notice.remove();
		}
	}
	
	// Überwache Block-Auswahl
	if (window.wp?.data) {
		window.wp.data.subscribe(() => {
			setTimeout(lockPremiumPattern, 50);
		});
	}
	
	// Überwache DOM-Änderungen
	const observer = new MutationObserver(lockPremiumPattern);
	const inspector = document.querySelector('.block-editor-block-inspector');
	if (inspector) {
		observer.observe(inspector, { childList: true, subtree: true });
	}
	
	// Initial
	setTimeout(lockPremiumPattern, 500);
	setInterval(lockPremiumPattern, 500); // Fallback alle 500ms
	
})();
