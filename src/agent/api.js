/**
 * REST helpers for the FSE Agent panel.
 */

import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

const TRIAL_CREDITS = 250;

function formatAgentError(error) {
	const code = error?.code || error?.data?.code || '';
	const status = error?.data?.status || error?.status;

	if (code === 'rest_no_route' || status === 404) {
		return __(
			'Agent-API nicht gefunden. Plugin aktualisieren, Permalinks unter Einstellungen → Permalinks speichern, oder GutenBlock FSE Agent aktivieren.',
			'gutenblock-pro'
		);
	}
	if (code === 'INSUFFICIENT_CREDITS' || code === 'insufficient_credits') {
		const needed = error?.data?.needed;
		const balance = error?.data?.balance;
		if (typeof needed === 'number' && typeof balance === 'number') {
			return sprintf(
				/* translators: 1: needed credits, 2: available credits */
				__('Nicht genug Credits (%1$d benötigt, %2$d verfügbar).', 'gutenblock-pro'),
				needed,
				balance
			);
		}
		return error?.message || __('Nicht genug Credits. Bitte Paket kaufen.', 'gutenblock-pro');
	}
	if (code === 'MISSING_AUTH' || code === 'missing_auth') {
		return sprintf(
			/* translators: %d: trial credit amount */
			__(
				'Keine Lizenz und kein API-Key. Du startest mit %d Test-Credits — Lizenz oder Keys unter Einstellungen → GutenBlock → Lizenz hinterlegen.',
				'gutenblock-pro'
			),
			TRIAL_CREDITS
		);
	}
	if (code === 'TRIAL_NOT_CONFIGURED' || code === 'trial_not_configured') {
		return error?.message || __('Testphase ist für diese Domain noch nicht freigeschaltet.', 'gutenblock-pro');
	}
	if (code === 'saas_error') {
		return error?.message || __('Verbindung zur GutenBlock-API fehlgeschlagen.', 'gutenblock-pro');
	}

	return (
		error?.message ||
		error?.data?.message ||
		(typeof error === 'string' ? error : __('Anfrage fehlgeschlagen.', 'gutenblock-pro'))
	);
}

async function agentFetch(options) {
	try {
		return await apiFetch(options);
	} catch (error) {
		const message = formatAgentError(error);
		const wrapped = new Error(message);
		wrapped.code = error?.code;
		wrapped.data = error?.data;
		throw wrapped;
	}
}

export function fetchAgentStatus() {
	return agentFetch({ path: '/gutenblock-pro/v1/agent/status' });
}

export function fetchPatternCatalog() {
	return agentFetch({ path: '/gutenblock-pro/v1/agent/patterns' });
}

export function fillPattern(payload) {
	return agentFetch({
		path: '/gutenblock-pro/v1/agent/fill-pattern',
		method: 'POST',
		data: payload,
	});
}

export function chatAgent(payload) {
	return agentFetch({
		path: '/gutenblock-pro/v1/agent/chat',
		method: 'POST',
		data: payload,
	});
}

export function saveAgentContext(context) {
	return agentFetch({
		path: '/gutenblock-pro/v1/agent/context',
		method: 'POST',
		data: { context },
	});
}

export function generateAgentImage(payload) {
	return agentFetch({
		path: '/gutenblock-pro/v1/agent/generate-image',
		method: 'POST',
		data: payload,
	});
}

export function uploadAgentImage(payload) {
	return agentFetch({
		path: '/gutenblock-pro/v1/agent/upload-image',
		method: 'POST',
		data: payload,
	});
}
