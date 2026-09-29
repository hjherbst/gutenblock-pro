/**
 * Registers the FSE Agent: toolbar toggle + left chat panel.
 */

import { createPortal, useCallback, useEffect, useState } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { dispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { AgentPanel } from './panel';
import { ImagePendingKeeper } from './image-pending-keeper';

function closePublishSidebarsOnly() {
	const stores = ['core/edit-post', 'core/edit-site', 'core/interface'];
	for (const name of stores) {
		try {
			const d = dispatch(name);
			// Keep the block settings sidebar available for coach flows.
			d.closePublishSidebar?.();
		} catch (e) {
			// Store not loaded in this editor.
		}
	}
}

function findPortalTarget() {
	return (
		document.querySelector('.interface-interface-skeleton__body') ||
		document.querySelector('.edit-post-layout') ||
		document.querySelector('.edit-site-editor') ||
		document.body
	);
}

function findToolbarTarget() {
	return (
		document.querySelector('.editor-header__toolbar') ||
		document.querySelector('.edit-post-header-toolbar') ||
		document.querySelector('.edit-site-header-edit-mode__start') ||
		document.querySelector('.edit-post-header__toolbar')
	);
}

function AgentApp() {
	const [open, setOpen] = useState(false);
	const [toolbarEl, setToolbarEl] = useState(null);
	const [bodyEl, setBodyEl] = useState(null);

	useEffect(() => {
		const sync = () => {
			setToolbarEl(findToolbarTarget());
			setBodyEl(findPortalTarget());
		};
		sync();
		const t = window.setInterval(sync, 1500);
		return () => window.clearInterval(t);
	}, []);

	useEffect(() => {
		document.body.classList.toggle('gb-agent-open', open);
		if (open) closePublishSidebarsOnly();
		return () => document.body.classList.remove('gb-agent-open');
	}, [open]);

	const toggle = useCallback(() => {
		setOpen((v) => !v);
	}, []);

	const toolbarButton = toolbarEl
		? createPortal(
			<Button
				className={`gb-agent-toolbar-btn ${open ? 'is-pressed' : ''}`}
				isPressed={open}
				onClick={toggle}
			>
				{__('Agent', 'gutenblock-pro')}
			</Button>,
			toolbarEl
		)
		: null;

	const panel = open && bodyEl
		? createPortal(<AgentPanel onClose={() => setOpen(false)} />, bodyEl)
		: null;

	return (
		<>
			{toolbarButton}
			{open && <ImagePendingKeeper />}
			{panel}
		</>
	);
}

registerPlugin('gutenblock-fse-agent', {
	render: AgentApp,
	icon: 'superhero',
});
