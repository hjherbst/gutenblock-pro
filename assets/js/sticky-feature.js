/**
 * Sticky Feature – Scroll-synced image visibility (CodyHouse adaptation).
 * Usage: codyhouse.co/license
 *
 * @package GutenBlockPro
 */
(function() {
	'use strict';

	function StickyFeature(element) {
		this.element = element;
		this.contentList = this.element.getElementsByClassName('js-sticky-feature__content-list');
		this.assetsList = this.element.getElementsByClassName('js-sticky-feature__media-list');

		if (this.contentList.length < 1 || this.assetsList.length < 1) return;

		this.contentItems = this.contentList[0].getElementsByClassName('js-sticky-feature__content-item');
		this.assetItems = this.assetsList[0].getElementsByClassName('js-sticky-feature__media-item');
		this.titleItems = this.contentList[0].getElementsByClassName('js-sticky-feature__title');
		this.activeSectionClass = 'gbp-sticky-feature-current-item';
		this.bindScroll = false;
		this.scrolling = false;
		initStickyFeature(this);
	}

	function initStickyFeature(el) {
		var observer = new IntersectionObserver(stickyFeatureObserve.bind(el));
		observer.observe(el.contentList[0]);

		for (var i = 0; i < el.titleItems.length; i++) {
			(function(idx) {
				el.titleItems[idx].addEventListener('click', function() {
					scrollToSection(el, idx);
				});
			})(i);
		}
	}

	function stickyFeatureObserve(entries) {
		if (entries[0].isIntersecting) {
			if (!this.bindScroll) {
				getSelectSection(this);
				bindScroll(this);
			}
		} else if (this.bindScroll) {
			unbindScroll(this);
			resetSectionVisibility(this);
		}
	}

	function updateVisibleSection(el) {
		var self = this;
		if (this.scrolling) return;
		this.scrolling = true;
		window.requestAnimationFrame(function() {
			getSelectSection(self);
			self.scrolling = false;
		});
	}

	function getSelectSection(el) {
		resetSectionVisibility(el);
		var index = [];
		for (var i = 0; i < el.contentItems.length; i++) {
			if (el.contentItems[i].getBoundingClientRect().top <= window.innerHeight / 2) {
				index.push(i);
			}
		}
		var itemIndex = (index.length > 0) ? index[index.length - 1] : 0;
		selectSection(el, itemIndex);
	}

	function resetSectionVisibility(el) {
		var selectedItems = el.element.getElementsByClassName(el.activeSectionClass);
		while (selectedItems[0]) {
			selectedItems[0].classList.remove(el.activeSectionClass);
		}
	}

	function selectSection(el, index) {
		el.contentItems[index].classList.add(el.activeSectionClass);
		el.assetItems[index].classList.add(el.activeSectionClass);
	}

	function scrollToSection(el, index) {
		if (el.assetsList[0].offsetWidth < 1) return;
		window.scrollBy({
			top: el.titleItems[index].getBoundingClientRect().top - window.innerHeight / 2 + 10,
			behavior: 'smooth'
		});
	}

	function bindScroll(el) {
		if (!el.bindScroll) {
			el.bindScroll = updateVisibleSection.bind(el);
			window.addEventListener('scroll', el.bindScroll);
		}
	}

	function unbindScroll(el) {
		if (el.bindScroll) {
			window.removeEventListener('scroll', el.bindScroll);
			el.bindScroll = false;
		}
	}

	window.StickyFeature = StickyFeature;

	var stickyFeatures = document.getElementsByClassName('js-sticky-feature');
	if (stickyFeatures.length > 0) {
		for (var i = 0; i < stickyFeatures.length; i++) {
			(function(idx) {
				new StickyFeature(stickyFeatures[idx]);
			})(i);
		}
	}
})();
