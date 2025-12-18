/**
 * GutenBlock Pro - Pattern Navigation
 * Creates nested navigation structure in FSE sidebar
 */

(function () {
	'use strict';

	// Wait for editor to be ready
	function initPatternNavigation() {
		// Check if we're in the block editor
		if (!wp || !wp.data || !wp.data.select) {
			setTimeout(initPatternNavigation, 500);
			return;
		}

		// Wait for pattern inserter to be available
		const checkPatternInserter = setInterval(() => {
			const inserter = document.querySelector('.block-editor-inserter__panel-content, .block-editor-inserter__panel-header');
			if (!inserter) {
				return;
			}

			clearInterval(checkPatternInserter);
			setupNestedNavigation();
		}, 500);

		// Stop after 10 seconds
		setTimeout(() => clearInterval(checkPatternInserter), 10000);
	}

	function setupNestedNavigation() {
		// Find pattern category list
		const categoryList = document.querySelector('.block-editor-inserter__panel-content, .block-editor-block-patterns-list');
		if (!categoryList) {
			// Try again after a delay
			setTimeout(setupNestedNavigation, 1000);
			return;
		}

		// Create observer to watch for pattern list changes
		const observer = new MutationObserver(() => {
			groupPatternCategories();
		});

		observer.observe(categoryList, {
			childList: true,
			subtree: true,
		});

		// Initial grouping
		setTimeout(groupPatternCategories, 500);
	}

	function groupPatternCategories() {
		// Find all pattern category buttons/items
		const categoryItems = document.querySelectorAll(
			'.block-editor-inserter__panel-content button[data-category], ' +
			'.block-editor-inserter__panel-content [role="tab"], ' +
			'.block-editor-block-patterns-list [data-category]'
		);

		if (categoryItems.length === 0) {
			return;
		}

		// Group categories
		const sectionsMain = Array.from(categoryItems).find(item => {
			const text = item.textContent || item.innerText || '';
			return text.trim() === 'GutenBlock Sections';
		});

		const sectionsSubs = Array.from(categoryItems).filter(item => {
			const text = item.textContent || item.innerText || '';
			return text.includes('GutenBlock Sections ›');
		});

		const pagesMain = Array.from(categoryItems).find(item => {
			const text = item.textContent || item.innerText || '';
			return text.trim() === 'GutenBlock Pages';
		});

		// Create nested structure for Sections
		if (sectionsMain && sectionsSubs.length > 0) {
			// Add collapse/expand icon to main category
			if (!sectionsMain.querySelector('.gutenblock-pro-collapse-icon')) {
				const icon = document.createElement('span');
				icon.className = 'gutenblock-pro-collapse-icon dashicons dashicons-arrow-down-alt2';
				icon.style.cssText = 'margin-left: 8px; font-size: 16px; vertical-align: middle; transition: transform 0.2s;';
				sectionsMain.appendChild(icon);
			}

			// Wrap subsections in a container
			let subsContainer = document.getElementById('gutenblock-pro-sections-subs');
			if (!subsContainer) {
				subsContainer = document.createElement('div');
				subsContainer.id = 'gutenblock-pro-sections-subs';
				subsContainer.className = 'gutenblock-pro-sections-subs';
				subsContainer.style.cssText = 'margin-left: 20px; margin-top: 4px; display: block;';
				
				// Insert after main category
				sectionsMain.parentNode.insertBefore(subsContainer, sectionsMain.nextSibling);
			}

			// Move subsections into container
			sectionsSubs.forEach(sub => {
				if (sub.parentNode !== subsContainer) {
					subsContainer.appendChild(sub);
					// Style subsection
					sub.style.cssText = (sub.style.cssText || '') + 'padding-left: 12px; font-size: 13px;';
				}
			});

			// Toggle functionality
			const toggleIcon = sectionsMain.querySelector('.gutenblock-pro-collapse-icon');
			if (toggleIcon) {
				sectionsMain.style.cursor = 'pointer';
				sectionsMain.addEventListener('click', function(e) {
					// Don't toggle if clicking on the category itself (to select it)
					if (e.target === toggleIcon || e.target.closest('.gutenblock-pro-collapse-icon')) {
						e.stopPropagation();
						const isExpanded = subsContainer.style.display !== 'none';
						subsContainer.style.display = isExpanded ? 'none' : 'block';
						toggleIcon.style.transform = isExpanded ? 'rotate(-90deg)' : 'rotate(0deg)';
					}
				});
			}
		}
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPatternNavigation);
	} else {
		initPatternNavigation();
	}

	// Also listen for editor initialization
	if (wp && wp.data && wp.data.subscribe) {
		wp.data.subscribe(() => {
			setTimeout(setupNestedNavigation, 1000);
		});
	}
})();

