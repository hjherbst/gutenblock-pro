/**
 * Tracks block clientIds that are currently generating an image.
 * The ImagePendingKeeper reads this store and paints loader overlays in the canvas.
 */

const pendings = new Set();
const listeners = new Set();

function notify() {
	for (const fn of listeners) {
		try {
			fn();
		} catch (e) {
			// best-effort
		}
	}
}

export function markBlockPending(clientId) {
	if (!clientId) return;
	const before = pendings.size;
	pendings.add(clientId);
	if (pendings.size !== before) notify();
}

export function clearBlockPending(clientId) {
	if (!clientId) return;
	if (pendings.delete(clientId)) notify();
}

export function clearAllBlockPending() {
	if (pendings.size === 0) return;
	pendings.clear();
	notify();
}

export function listPendingBlocks() {
	return [...pendings];
}

export function isBlockPending(clientId) {
	return pendings.has(clientId);
}

export function subscribePendingBlocks(cb) {
	listeners.add(cb);
	return () => listeners.delete(cb);
}
