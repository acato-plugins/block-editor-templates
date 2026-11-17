/**
 * The walkthrough of the Post Type Templates list, on WordPress's own admin pointers.
 *
 * The steps come from Admin\Tour. It never starts on its own: it starts from the
 * "Start walkthrough" link on the Plugins screen, which opens this screen with a query argument, or
 * from the "Take the walkthrough" button next to the screen's title. From the Plugins screen,
 * somebody who took an older walkthrough gets only the steps added since; the button always gives
 * all of it.
 */

/**
 * Import WordPress dependencies.
 */
import { speak } from '@wordpress/a11y';
import domReady from '@wordpress/dom-ready';
import { _x, sprintf } from '@wordpress/i18n';

/**
 * Import styles.
 */
import './tour.scss';

const data = window.abetTour;
const $ = window.jQuery;

/**
 * Whether an element takes up room on the screen: a column hidden in Screen Options, or the
 * submenu of a folded admin menu, does not.
 *
 * @param {Element} element The element.
 *
 * @return {boolean} Whether it is visible.
 */
const isVisible = (element) => element.getClientRects().length > 0;

/**
 * The step's target: the first of its selectors, in order, that matches something visible.
 *
 * @param {Object} step The step.
 *
 * @return {Element|null} The target, or null when it is not on this screen.
 */
const targetOf = (step) => {
	for (const selector of step.target.split(',')) {
		const match = [...document.querySelectorAll(selector)].find(isVisible);

		if (match) {
			return match;
		}
	}

	return null;
};

/**
 * The steps that have something to point at here.
 *
 * @param {Object[]} steps The steps.
 *
 * @return {Object[]} The steps present on this screen.
 */
const present = (steps) => steps.filter((step) => targetOf(step));

/**
 * Store that this user completed or skipped the walkthrough. Not saved, it simply shows again.
 *
 * @return {Promise} The request.
 */
const markSeen = () => {
	const body = new URLSearchParams({
		action: data.action,
		_ajax_nonce: data.nonce,
	});

	data.pending = [];

	return window
		.fetch(data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body,
		})
		.catch(() => {});
};

let running = false;

/**
 * Run the walkthrough. Escape or Skip ends it, and focus goes back to where it was.
 *
 * @param {Object[]} steps The steps to show.
 *
 * @return {boolean} Whether it started.
 */
const run = (steps) => {
	if (running || !steps.length) {
		return false;
	}

	running = true;

	const returnFocus = document.body.ownerDocument.activeElement;
	const reduced = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;
	let current = null;
	let ended = false;

	const reposition = () => current?.element.pointer('reposition');

	// A closed pointer stays in the page and on its target, and taking the walkthrough again
	// would open that old one, buttons and all. Each step's pointer is removed instead.
	const dispose = (pointer) => {
		pointer?.element.pointer('close').pointer('destroy');
	};

	// Declarations, as each of the two calls the other.
	function end() {
		if (ended) {
			return;
		}

		ended = true;
		running = false;
		dispose(current);
		document.removeEventListener('keydown', onKey, true);
		window.removeEventListener('resize', reposition);
		returnFocus?.focus?.();
		markSeen();
	}

	function onKey(event) {
		if (event.key === 'Escape') {
			event.stopPropagation();
			end();
		}
	}

	const open = (index) => {
		const step = steps[index];
		const last = index + 1 === steps.length;
		const target = targetOf(step);

		// A pointer that opens below its target wants the target in the upper third of the
		// window, leaving room for itself. The admin menu is pinned or scrolls with the page,
		// so its steps scroll it into view instead.
		if (step.edge === 'left') {
			target.scrollIntoView({
				block: 'nearest',
				behavior: reduced ? 'auto' : 'smooth',
			});
		} else {
			window.scrollTo({
				top: Math.max(
					0,
					target.getBoundingClientRect().top +
						window.scrollY -
						window.innerHeight / 3
				),
				behavior: reduced ? 'auto' : 'smooth',
			});
		}

		const escape = (text) => $('<div/>').text(text).html();
		const eyebrow = sprintf(
			/* translators: 1: number of this step, 2: number of steps. */
			_x(
				'Step %1$d of %2$d',
				'walkthrough progress',
				'block-editor-templates'
			),
			index + 1,
			steps.length
		);
		const progress = Math.round(((index + 1) / steps.length) * 100);

		$(target)
			.pointer({
				pointerClass: 'wp-pointer abet-pointer',
				content:
					'<div class="abet-pointer-head">' +
					`<p class="abet-pointer-eyebrow">${escape(eyebrow)}</p>` +
					`<h3>${escape(step.title)}</h3>` +
					`<span class="abet-pointer-progress" aria-hidden="true"><span style="--abet-progress: ${progress}%"></span></span>` +
					'</div>' +
					`<p>${escape(step.text)}</p>`,
				position: {
					edge: step.edge || 'top',
					align: step.align || 'left',
				},
				pointerWidth: 340,
				buttons: (event, pointer) => {
					current = pointer;

					const wrap = document.createElement('div');
					wrap.className = 'abet-pointer-buttons';

					if (!last) {
						const skip = document.createElement('button');
						skip.type = 'button';
						skip.className = 'button-link';
						skip.textContent = _x(
							'Skip walkthrough',
							'walkthrough button',
							'block-editor-templates'
						);
						skip.addEventListener('click', end);
						wrap.append(skip);
					}

					const next = document.createElement('button');
					next.type = 'button';
					next.className = 'button button-primary abet-pointer-next';
					next.textContent = last
						? _x(
								'Done',
								'walkthrough button',
								'block-editor-templates'
							)
						: _x(
								'Next',
								'walkthrough button',
								'block-editor-templates'
							);
					next.addEventListener('click', () => {
						if (last) {
							end();

							return;
						}

						dispose(pointer);
						open(index + 1);
					});
					wrap.append(next);

					// Core wraps what this returns with jQuery's .wrap().
					return $(wrap);
				},
			})
			.pointer('open');

		// The pointer is appended to <body>, far from its target in the keyboard order, so focus
		// is taken to it and its text read out.
		window.requestAnimationFrame(() => {
			current?.pointer.find('.abet-pointer-next').trigger('focus');
			speak(`${eyebrow}. ${step.title}. ${step.text}`, 'polite');
		});
	};

	document.addEventListener('keydown', onKey, true);
	window.addEventListener('resize', reposition);
	open(0);

	return true;
};

domReady(() => {
	if (!data || !$?.fn?.pointer) {
		return;
	}

	// The way back in, for somebody who skipped it or wants it again: next to the screen's title,
	// where core puts a screen's own actions.
	const heading = document.querySelector('.wrap .wp-heading-inline');

	if (heading) {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'page-title-action abet-tour-replay';
		button.textContent = _x(
			'Take the walkthrough',
			'walkthrough button',
			'block-editor-templates'
		);
		button.addEventListener('click', () => run(present(data.steps)));
		heading.after(' ', button);
	}

	// Asked for from the Plugins screen. The argument comes off the address first, so a reload or
	// a bookmark does not start it again.
	const url = new URL(window.location.href);

	if (url.searchParams.has(data.start)) {
		url.searchParams.delete(data.start);
		window.history.replaceState(window.history.state, '', url);

		// What this user has not seen, or all of it when they saw everything.
		const pending = present(data.pending);
		const steps = pending.length ? pending : present(data.steps);

		// Once the screen has settled, so the first step points at its place.
		window.requestAnimationFrame(() => run(steps));
	}
});
