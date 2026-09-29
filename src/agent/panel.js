/**
 * FSE Agent chat panel — docked left, canvas stays the live editor.
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { select, subscribe } from '@wordpress/data';
import { Button, ExternalLink, Spinner, TextareaControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { chatAgent, fetchAgentStatus, fetchPatternCatalog, saveAgentContext } from './api';
import { argsFromToolCall, executeToolCall } from './tools';
import { generateImageForBlock, guessFluxAspectRatioFromDimensions } from './block-images';

function getCurrentEditorPostType() {
	try {
		return (
			select('core/editor')?.getCurrentPostType?.() ||
			select('core/edit-post')?.getCurrentPostType?.() ||
			''
		);
	} catch {
		return '';
	}
}

function fieldIds(fields) {
	if (!Array.isArray(fields)) return [];
	return fields
		.map((f) => {
			if (typeof f === 'string') return f;
			if (f && typeof f === 'object') return f.id || f.fieldId || f.slug || '';
			return '';
		})
		.filter(Boolean);
}

function compactCatalog(patterns) {
	if (!Array.isArray(patterns)) return [];
	return patterns.map((p) => ({
		slug: p.slug,
		group: p.group,
		title: p.title,
		description: (p.description || '').slice(0, 180),
		ai_hint: (p.ai_hint || '').slice(0, 220),
		content_fields: fieldIds(p.content_fields),
		classes: Array.isArray(p.classes) ? p.classes.slice(0, 12) : [],
	}));
}

function applyBilling(status, billing) {
	if (!billing || typeof billing.balance !== 'number') return status;
	const next = { ...status, balance: billing.balance };
	if (billing.balanceSource) next.balanceSource = billing.balanceSource;
	if (status?.credits) {
		next.credits = {
			...status.credits,
			balance: billing.balance,
			used: typeof billing.used === 'number' ? billing.used : status.credits.used,
		};
	}
	return next;
}

function ImagePromptIcon() {
	return (
		<svg
			width="18"
			height="18"
			viewBox="0 0 24 24"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
		>
			<path
				d="M4 6.5C4 5.11929 5.11929 4 6.5 4H17.5C18.8807 4 20 5.11929 20 6.5V17.5C20 18.8807 18.8807 20 17.5 20H6.5C5.11929 20 4 18.8807 4 17.5V6.5Z"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinejoin="round"
			/>
			<path
				d="M8 10.5C8 11.3284 7.32843 12 6.5 12C5.67157 12 5 11.3284 5 10.5C5 9.67157 5.67157 9 6.5 9C7.32843 9 8 9.67157 8 10.5Z"
				fill="currentColor"
			/>
			<path
				d="M20 15L16.6 11.6C16.2077 11.2077 15.574 11.2077 15.1817 11.6L11 15.7817"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinejoin="round"
			/>
			<path
				d="M11 15.7817L9.4 14.2C9.00767 13.8077 8.37395 13.8077 7.98162 14.2L4 18.1816"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinejoin="round"
			/>
		</svg>
	);
}

function toolLabel(name, args) {
	if (name === 'build_page') {
		const n = Array.isArray(args.sections) ? args.sections.length : 0;
		return n
			? sprintf(__('Seite mit %d Sections', 'gutenblock-pro'), n)
			: __('Seite', 'gutenblock-pro');
	}
	if (name === 'insert_blocks') {
		return __('Blöcke', 'gutenblock-pro');
	}
	if (name === 'write_article') {
		return args.title || __('Artikel', 'gutenblock-pro');
	}
	if (name === 'insert_image') {
		return __('Bild', 'gutenblock-pro');
	}
	return args.slug || __('Pattern', 'gutenblock-pro');
}

/** Keep a valid text transcript for the next user turn (no orphaned tool_calls). */
function transcriptForNextTurn(msgs) {
	if (!Array.isArray(msgs)) return [];
	return msgs
		.filter(
			(m) =>
				(m.role === 'user' && m.content) ||
				(m.role === 'assistant' && m.content && !m.tool_calls?.length) ||
				(m.role === 'notice' && m.content)
		)
		.map((m) =>
			m.role === 'notice'
				? { role: 'notice', ok: m.ok, content: m.content }
				: { role: m.role, content: m.content }
		);
}

function toApiMessages(conversation) {
	return conversation
		.filter((m) => m.role !== 'notice')
		.map((m) => ({
			role: m.role,
			content: m.content ?? '',
			tool_call_id: m.tool_call_id,
			tool_calls: m.tool_calls,
		}));
}

export function AgentPanel({ onClose }) {
	const [input, setInput] = useState('');
	const [busy, setBusy] = useState(false);
	const [statusLine, setStatusLine] = useState('');
	const [error, setError] = useState('');
	const [messages, setMessages] = useState([]);
	const [status, setStatus] = useState(null);
	const [patterns, setPatterns] = useState([]);
	const [onboarding, setOnboarding] = useState(null);
	const [draftContext, setDraftContext] = useState('');
	const [imageTarget, setImageTarget] = useState(null); // { clientId, aspectRatio }
	const lastImagePromptClientIdRef = useRef(null);
	const dismissedImageModeClientIdRef = useRef(null);
	const listRef = useRef(null);
	const pendingRequestRef = useRef('');
	const [editorPostType, setEditorPostType] = useState('');

	useEffect(() => {
		let cancelled = false;
		setEditorPostType(getCurrentEditorPostType());
		Promise.all([fetchAgentStatus(), fetchPatternCatalog()])
			.then(([s, catalog]) => {
				if (cancelled) return;
				setStatus(s);
				setPatterns(compactCatalog(catalog));
			})
			.catch((e) => {
				if (!cancelled) setError(e?.message || __('Agent konnte nicht geladen werden.', 'gutenblock-pro'));
			});
		return () => {
			cancelled = true;
		};
	}, []);

	// Coming back from the Stripe tab: pull the fresh balance so the purchase shows up instantly.
	useEffect(() => {
		if (busy) return undefined;
		const refresh = () => {
			fetchAgentStatus()
				.then((s) => setStatus(s))
				.catch(() => {});
		};
		window.addEventListener('focus', refresh);
		return () => window.removeEventListener('focus', refresh);
	}, [busy]);

	useEffect(() => {
		let unsub = null;
		try {
			unsub = subscribe(() => {
				try {
					const sel = select('core/block-editor')?.getSelectedBlock?.();
					const nextClientId = sel?.clientId || null;
					const nextName = sel?.name || null;

					if (
						(nextName !== 'core/image' && nextName !== 'core/cover') ||
						!nextClientId
					) {
						setImageTarget(null);
						lastImagePromptClientIdRef.current = null;
						dismissedImageModeClientIdRef.current = null;
						return;
					}

					// User explicitly dismissed the image mode for this exact block.
					// Keep the panel in "normal chat" even if the block remains selected.
					if (dismissedImageModeClientIdRef.current === nextClientId) {
						setImageTarget(null);
						return;
					}

					const isCover = nextName === 'core/cover';
					const dimensions = sel?.attributes?.dimensions;
					const previewUrl =
						sel?.attributes?.url ||
						sel?.attributes?.backgroundImageUrl ||
						sel?.attributes?.backgroundImage ||
						'';
					const aspectRatio = isCover
						? // Gutenberg cover blocks often don't expose dimensions like core/image.
							dimensions ? guessFluxAspectRatioFromDimensions(dimensions) : '16:9'
						: guessFluxAspectRatioFromDimensions(dimensions);
					setImageTarget((prev) => {
						if (
							prev?.clientId === nextClientId &&
							prev?.aspectRatio === aspectRatio &&
							prev?.blockName === nextName
						) {
							return prev;
						}
						return { clientId: nextClientId, aspectRatio, blockName: nextName, previewUrl };
					});

					// Keep the UX clean: thumbnail goes into the panel header, not the chat history.
					if (lastImagePromptClientIdRef.current !== nextClientId) {
						lastImagePromptClientIdRef.current = nextClientId;
						setOnboarding(null);
						setDraftContext('');
					}
				} catch (e) {
					// Ignore editor subscription errors (store not ready yet).
				}
			});
		} catch (e) {
			// Ignore when subscribe/select is not available.
		}

		return () => {
			try {
				unsub?.();
			} catch (e) {
				// ignore
			}
		};
	}, []);

	useEffect(() => {
		if (listRef.current) {
			listRef.current.scrollTop = listRef.current.scrollHeight;
		}
	}, [messages, statusLine, busy, onboarding, imageTarget]);

	const chatExtras = useCallback(
		(extra = {}) => {
			return {
				patterns,
				language: status?.siteLanguage || 'en_US',
				siteContext: status?.siteContext || '',
				editorPostType: editorPostType || '',
				stylePrompt: status?.stylePrompt || '',
				...extra,
			};
		},
		[patterns, status, editorPostType]
	);

	const runInsertLoop = useCallback(
		async (history, uiBase, extrasOverride = {}) => {
			const publish = (next) => {
				if (uiBase) {
					setMessages(uiBase.concat(next.filter((m) => m.role !== 'user')));
					return;
				}
				setMessages(next);
			};

			let conversation = history;
			for (let round = 0; round < 3; round += 1) {
				const response = await chatAgent({
					messages: toApiMessages(conversation),
					...chatExtras(extrasOverride),
				});

				if (response?.billing) {
					setStatus((prev) => applyBilling(prev, response.billing));
				}

				const assistant = response?.message;
				if (!assistant) {
					throw new Error(
						response?.error || __('Leere Agent-Antwort.', 'gutenblock-pro')
					);
				}

				const toolCalls = assistant.tool_calls || [];
				conversation = [
					...conversation,
					{
						role: 'assistant',
						content: assistant.content || '',
						tool_calls: toolCalls.length ? toolCalls : undefined,
					},
				];

				if (!toolCalls.length) {
					const hadToolRound = conversation.some((m) => m.role === 'tool');
					if (!assistant.content && !hadToolRound) {
						throw new Error(
							__('Keine Antwort vom Modell. Bitte den Prompt erneut senden.', 'gutenblock-pro')
						);
					}
					if (uiBase) {
						publish(transcriptForNextTurn(conversation));
					} else {
						setMessages((prev) => {
							const notices = prev.filter((m) => m.role === 'notice');
							return transcriptForNextTurn(conversation).concat(notices);
						});
					}
					break;
				}

				const results = [];
				for (const call of toolCalls) {
					const name = call.function?.name || call.name;
					const args = argsFromToolCall(call);
					setStatusLine(
						name === 'build_page'
							? __('Seite wird aufgebaut…', 'gutenblock-pro')
							: name === 'insert_blocks'
								? __('Blöcke werden eingefügt…', 'gutenblock-pro')
								: name === 'write_article'
									? __('Artikel wird geschrieben…', 'gutenblock-pro')
									: name === 'insert_image'
										? __('Bild wird eingefügt…', 'gutenblock-pro')
										: sprintf(__('Pattern %s wird eingefügt…', 'gutenblock-pro'), args.slug || '')
					);
					let result;
					try {
						result = await executeToolCall(name, args, {
							catalog: patterns,
							chatExtras: chatExtras(extrasOverride),
							onBilling: (billing) => {
								setStatus((prev) => applyBilling(prev, billing));
							},
							onCompletingTexts: () => {
								setStatusLine(__('Fehlende Texte werden ergänzt…', 'gutenblock-pro'));
							},
						});
					} catch (toolErr) {
						result = { ok: false, error: toolErr?.message || String(toolErr) };
					}
					results.push({ name, args, result });
					conversation.push({
						role: 'tool',
						tool_call_id: call.id,
						content: JSON.stringify(result),
					});
				}

				const notices = results.map(({ name, args, result }) => {
					const label = toolLabel(name, args);
					if (result?.ok) {
						return {
							role: 'notice',
							ok: true,
							content: sprintf(__('%s eingefügt', 'gutenblock-pro'), label),
						};
					}
					return {
						role: 'notice',
						ok: false,
						content: sprintf(
							__('%1$s fehlgeschlagen: %2$s', 'gutenblock-pro'),
							label,
							result?.error || __('unbekannter Fehler', 'gutenblock-pro')
						),
					};
				});

				const anyOk = results.some((r) => r.result?.ok);
				const visibleBase = conversation
					.filter((m) => m.role !== 'tool' && !(m.role === 'assistant' && !m.content && m.tool_calls))
					.concat(notices);

				if (!anyOk) {
					const refundRes = await chatAgent({
						messages: toApiMessages(conversation),
						...chatExtras({ ...extrasOverride, refundOnly: true }),
					});
					if (refundRes?.billing) {
						setStatus((prev) => applyBilling(prev, refundRes.billing));
					}
					publish(visibleBase);
					setError(
						notices.find((n) => !n.ok)?.content ||
							__('Einfügen fehlgeschlagen. Credits wurden erstattet.', 'gutenblock-pro')
					);
					break;
				}

				publish(visibleBase);
			}
		},
		[chatExtras]
	);

	const send = useCallback(async () => {
		const text = input.trim();
		if (!text || busy) return;
		if (status && status.canChat === false) {
			setError(status.blockReason || __('Agent derzeit nicht verfügbar.', 'gutenblock-pro'));
			return;
		}

		// Image flow: click an image block → show prompt → user types description → generate.
		if (imageTarget) {
			const targetClientId = imageTarget.clientId;
			const targetBlockName = imageTarget.blockName || 'core/image';
			setInput('');
			setError('');
			setOnboarding(null);
			setDraftContext('');

			const userMsg = { role: 'user', content: text };
			const history = [...transcriptForNextTurn(messages), userMsg];
			setMessages(history);

			setBusy(true);
			setStatusLine(__('Bild wird generiert…', 'gutenblock-pro'));

			try {
				await generateImageForBlock({
					clientId: targetClientId,
					blockName: targetBlockName,
					aspectRatio: imageTarget.aspectRatio,
					prompt: text,
					onBilling: (billing) => {
						setStatus((prev) => applyBilling(prev, billing));
					},
				});

				setMessages((prev) =>
					prev.concat({
						role: 'notice',
						ok: true,
						content: __('Bild generiert und in Mediathek gespeichert.', 'gutenblock-pro'),
					})
				);
			} catch (e) {
				setError(e?.message || __('Bildgenerierung fehlgeschlagen.', 'gutenblock-pro'));
			} finally {
				setBusy(false);
				setStatusLine('');
				setImageTarget(null);
				lastImagePromptClientIdRef.current = targetClientId;
			}

			return;
		}

		setInput('');
		setError('');
		const userMsg = { role: 'user', content: text };
		const history = [...transcriptForNextTurn(messages), userMsg];
		setMessages(history);
		setBusy(true);
		setStatusLine(__('Denke nach…', 'gutenblock-pro'));

		try {
			const needsContext = status && status.hasContext === false;
			if (needsContext && onboarding !== 'ask' && onboarding !== 'confirm') {
				pendingRequestRef.current = text;
				setOnboarding('ask');
				const response = await chatAgent({
					messages: toApiMessages(history),
					...chatExtras({ mode: 'collect_context' }),
				});
				const content = response?.message?.content;
				if (!content) {
					throw new Error(response?.error || __('Leere Agent-Antwort.', 'gutenblock-pro'));
				}
				setMessages([...history, { role: 'assistant', content }]);
				return;
			}

			if (needsContext || onboarding === 'ask' || onboarding === 'confirm') {
				const response = await chatAgent({
					messages: toApiMessages(history),
					...chatExtras({ mode: 'summarize_context' }),
				});
				const content = response?.message?.content;
				if (!content) {
					throw new Error(response?.error || __('Leere Agent-Antwort.', 'gutenblock-pro'));
				}
				setDraftContext(content.trim());
				setOnboarding('confirm');
				setMessages([...history, { role: 'assistant', content }]);
				return;
			}

			await runInsertLoop(history);
		} catch (e) {
			setError(e?.message || __('Anfrage fehlgeschlagen.', 'gutenblock-pro'));
		} finally {
			setBusy(false);
			setStatusLine('');
		}
	}, [input, busy, messages, status, onboarding, chatExtras, runInsertLoop, imageTarget]);

	const confirmContext = useCallback(async () => {
		if (busy || !draftContext) return;
		setBusy(true);
		setError('');
		setStatusLine(__('Denke nach…', 'gutenblock-pro'));
		try {
			const saved = await saveAgentContext(draftContext);
			const hint =
				saved?.savedHint ||
				status?.ui?.savedHint ||
				__('Gespeichert. Du kannst deine Angaben jederzeit unter GutenBlock → Prompts anpassen.', 'gutenblock-pro');
			setStatus((prev) => ({
				...(prev || {}),
				hasContext: true,
				siteContext: saved?.siteContext || draftContext,
			}));
			setOnboarding(null);
			setDraftContext('');

			const notice = { role: 'notice', ok: true, content: hint };
			const uiBase = [...transcriptForNextTurn(messages), notice];
			setMessages(uiBase);

			const pending = pendingRequestRef.current.trim();
			pendingRequestRef.current = '';
			if (pending) {
				await runInsertLoop([{ role: 'user', content: pending }], uiBase, {
					siteContext: saved?.siteContext || draftContext,
				});
			}
		} catch (e) {
			setError(e?.message || __('Anfrage fehlgeschlagen.', 'gutenblock-pro'));
		} finally {
			setBusy(false);
			setStatusLine('');
		}
	}, [busy, draftContext, status, messages, runInsertLoop]);

	const reviseContext = useCallback(() => {
		setOnboarding('ask');
		setDraftContext('');
	}, []);

	const onKeyDown = (event) => {
		if (event.key === 'Enter' && !event.shiftKey) {
			event.preventDefault();
			send();
		}
	};

	const visible = messages.filter(
		(m) =>
			m.role === 'user' ||
			m.role === 'notice' ||
			(m.role === 'assistant' && m.content)
	);
	const credits = status?.credits;
	const balance = typeof status?.balance === 'number' ? status.balance : credits?.balance;
	const used = credits?.used ?? 0;
	const costPerPattern = credits?.costPerPattern ?? 10;
	// One-click Stripe checkout for the recommended pack; falls back to the packs page (e.g. non-admins).
	const buyUrl = status?.buyUrl || '';
	const shopUrl = buyUrl || status?.creditsShopUrl || status?.upgradeUrl || '';
	const settingsUrl = status?.settingsUrl;
	const canChat = status?.canChat !== false;
	const confirmLabel = status?.ui?.confirm || __('Sieht gut aus', 'gutenblock-pro');
	const reviseLabel = status?.ui?.revise || __('Anders beschreiben', 'gutenblock-pro');

	return (
		<div className="gb-agent-panel">
			<div className="gb-agent-panel__header">
				<div>
					<div className="gb-agent-panel__title">{__('FSE Agent', 'gutenblock-pro')}</div>
					<div className="gb-agent-panel__meta">
						{status?.mode === 'byok'
							? __('Eigene API-Keys', 'gutenblock-pro')
							: status?.hasLicenseKey
								? __('Lizenz aktiv', 'gutenblock-pro')
								: __('Testphase', 'gutenblock-pro')}
					</div>
				</div>
				<Button isSmall onClick={onClose} label={__('Schließen', 'gutenblock-pro')}>
					×
				</Button>
			</div>

			<div className="gb-agent-panel__thread" ref={listRef}>
				{visible.length === 0 && (
					<div className="gb-agent-panel__hint">
						<p>
							{__(
								'Beschreibe eine Sektion oder Seite — ich wähle Patterns und schreibe Texte für die gerade geöffnete Seite. Undo im Editor macht Änderungen rückgängig.',
								'gutenblock-pro'
							)}
						</p>
						<p>
							{__(
								'Farben, Rahmen und Abstände änderst du selbst im Styles-Panel des Editors — dafür ist der Agent nicht zuständig. Auch nicht für Theme-Farben, Website-Klone oder Custom-Slider.',
								'gutenblock-pro'
							)}{' '}
							<ExternalLink href="https://gutenblock.com/plugin/agent">
								{__('Was der Agent kann', 'gutenblock-pro')}
							</ExternalLink>
						</p>
					</div>
				)}
				{visible.map((m, i) => (
					<div
						key={i}
						className={
							m.role === 'notice'
								? `gb-agent-notice ${m.ok ? 'gb-agent-notice--ok' : 'gb-agent-notice--err'}`
								: `gb-agent-msg gb-agent-msg--${m.role}`
						}
					>
						{m.ui?.kind === 'imagePrompt' ? (
							<div className="gb-agent-imageprompt">
								<span className="gb-agent-imageprompt__icon">
									<ImagePromptIcon />
								</span>
								<span>{m.content}</span>
							</div>
						) : (
							m.content
						)}
					</div>
				))}
				{onboarding === 'confirm' && !busy && (
					<div className="gb-agent-confirm">
						<Button isPrimary isSmall onClick={confirmContext}>
							{confirmLabel}
						</Button>
						<Button isSmall onClick={reviseContext}>
							{reviseLabel}
						</Button>
					</div>
				)}
				{statusLine && (
					<div className="gb-agent-panel__status">
						<Spinner /> {statusLine}
					</div>
				)}
				{error && <div className="gb-agent-panel__error">{error}</div>}
				{status && !canChat && status.blockReason && !error && (
					<div className="gb-agent-panel__notice">
						<p>{status.blockReason}</p>
						{shopUrl && (
							<Button isPrimary href={shopUrl} target="_blank" rel="noopener noreferrer">
								{__('Credits kaufen', 'gutenblock-pro')}
							</Button>
						)}
					</div>
				)}
			</div>

			<div className="gb-agent-panel__credits">
				{typeof balance === 'number' && status?.mode !== 'byok' && (
					<div className="gb-agent-usagebar">
						<div className="gb-agent-usagebar__bar" aria-hidden="true">
							<div
								className="gb-agent-usagebar__barInner"
								style={{
									width: `${balance > 0 ? Math.max(0, Math.min(100, (used / balance) * 100)) : 0}%`,
								}}
							/>
						</div>
						<div className="gb-agent-usagebar__text">
							{used}/{balance}
						</div>
						{shopUrl && (
							<a
								href={shopUrl}
								className="gb-agent-usagebar__link"
								target="_blank"
								rel="noopener noreferrer"
							>
								{__('Credits kaufen', 'gutenblock-pro')}
							</a>
						)}
					</div>
				)}
			</div>

			<div className="gb-agent-panel__composer">
				{imageTarget?.previewUrl ? (
					<div className="gb-agent-composer__thumb-input">
						<div className="gb-agent-composer__thumb" aria-hidden="true">
							<button
								type="button"
								className="gb-agent-composer__thumb-close"
								onClick={() => {
									dismissedImageModeClientIdRef.current = imageTarget.clientId;
									setImageTarget(null);
									setInput('');
								}}
								aria-label={__('Bildmodus schließen', 'gutenblock-pro')}
							>
								×
							</button>
							<img src={imageTarget.previewUrl} alt="" />
						</div>
						<div className="gb-agent-composer__thumb-col">
							<div className="gb-agent-composer__thumb-hint">
								Replace this image using a prompt below.
							</div>
							<TextareaControl
								value={input}
								onChange={setInput}
								onKeyDown={onKeyDown}
								placeholder={__('Bildbeschreibung: Was soll generiert werden?', 'gutenblock-pro')}
								rows={3}
								disabled={busy || !canChat}
							/>
						</div>
					</div>
				) : (
					<TextareaControl
						value={input}
						onChange={setInput}
						onKeyDown={onKeyDown}
						placeholder={__('z. B. Hero für eine Bäckerei in Berlin…', 'gutenblock-pro')}
						rows={3}
						disabled={busy || !canChat}
					/>
				)}
				<Button isPrimary onClick={send} disabled={busy || !canChat || !input.trim()}>
					{busy ? __('Arbeite…', 'gutenblock-pro') : __('Senden', 'gutenblock-pro')}
				</Button>
			</div>
		</div>
	);
}
