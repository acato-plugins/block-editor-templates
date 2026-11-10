/**
 * Renders a thumbnail of each template's blocks in the template list tables.
 *
 * The PHP side (List_Table) prints an empty link per row holding the template's block markup in
 * `data-content`, and loads the editor scripts of every registered block plus the editor settings.
 * Each thumbnail is rendered with the block editor's own BlockPreview once it scrolls into view.
 */

/**
 * Import WordPress dependencies.
 */
import {
	BlockPreview,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { registerCoreBlocks } from '@wordpress/block-library';
import { getBlockType, parse } from '@wordpress/blocks';
import { dispatch } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import './list-preview.scss';

/**
 * The width, in pixels, the template is laid out at before it is scaled down to the column.
 */
const VIEWPORT_WIDTH = 1200;

/**
 * Render one thumbnail.
 *
 * @param {HTMLElement} element The preview link holding the block markup.
 */
const renderPreview = (element) => {
	const blocks = parse(element.dataset.content ?? '');
	if (!blocks.length) {
		return;
	}

	createRoot(element).render(
		<BlockPreview blocks={blocks} viewportWidth={VIEWPORT_WIDTH} />
	);
};

/**
 * How far each key scrolls a focused thumbnail, as a function of its visible height.
 *
 * @type {Object<string, function(HTMLElement): number>}
 */
const SCROLL_KEYS = {
	ArrowDown: () => 40,
	ArrowUp: () => -40,
	PageDown: (element) => element.clientHeight,
	PageUp: (element) => -element.clientHeight,
	Home: (element) => -element.scrollHeight,
	End: (element) => element.scrollHeight,
};

/**
 * Scroll a focused thumbnail with the keyboard.
 *
 * A thumbnail is a link that is also its own scroll container. Browsers do not reliably scroll a
 * focused link with the arrow keys, so do it here, and only when there is something to scroll -
 * otherwise the keys keep scrolling the page.
 *
 * @param {KeyboardEvent} event The keydown event.
 */
const onPreviewKeyDown = (event) => {
	const element = event.currentTarget;
	const distance = SCROLL_KEYS[event.key];
	if (
		!distance ||
		event.altKey ||
		event.ctrlKey ||
		event.metaKey ||
		element.scrollHeight <= element.clientHeight
	) {
		return;
	}

	event.preventDefault();
	element.scrollBy({ top: distance(element) });
};

domReady(() => {
	const previews = document.querySelectorAll(
		'.abet-template-preview[data-content]'
	);
	if (!previews.length) {
		return;
	}

	previews.forEach((preview) =>
		preview.addEventListener('keydown', onPreviewKeyDown)
	);

	// The post editor registers the core blocks itself; this list screen has to do it.
	if (!getBlockType('core/paragraph')) {
		registerCoreBlocks();
	}

	// The theme's editor styles and the assets the preview iframes load.
	const settings = window.abetListPreview?.settings;
	if (settings) {
		dispatch(blockEditorStore).updateSettings(settings);
	}

	if (!('IntersectionObserver' in window)) {
		previews.forEach(renderPreview);
		return;
	}

	// Each thumbnail is an iframe: only build the ones that come near the viewport.
	const observer = new window.IntersectionObserver(
		(entries) =>
			entries.forEach(({ isIntersecting, target }) => {
				if (!isIntersecting) {
					return;
				}
				observer.unobserve(target);
				renderPreview(target);
			}),
		{ rootMargin: '200px' }
	);

	previews.forEach((preview) => observer.observe(preview));
});
