/**
 * The card beside the Block Templates menu that leads to the walkthrough.
 *
 * Plain JavaScript without jQuery or other dependencies, as it loads on every admin screen until
 * the walkthrough is taken. The data comes from Admin\Tour::menu_pointer() in
 * window.abetMenuPointer.
 */

/**
 * Import styles.
 */
import './menu-pointer.scss';

(() => {
	const data = window.abetMenuPointer;
	const target = document.getElementById(
		'toplevel_page_edit-post_type-block-templates'
	);

	if (!data || !target) {
		return;
	}

	const rtl = document.documentElement.dir === 'rtl';

	const el = (tag, className, text) => {
		const node = document.createElement(tag);

		node.className = className;

		if (text) {
			node.textContent = text;
		}

		return node;
	};

	const box = el('div', 'abet-menu-pointer');
	const body = el('div', 'abet-menu-pointer-body');
	const head = el('div', 'abet-menu-pointer-head');
	const wapuu = el('img', 'abet-menu-pointer-wapuu');
	const title = el('h2', 'abet-menu-pointer-title', data.title);
	const text = el('p', 'abet-menu-pointer-text', data.text);
	const actions = el('div', 'abet-menu-pointer-actions');
	const go = el('a', 'abet-menu-pointer-go', data.linkText);
	const dismiss = el('button', 'abet-menu-pointer-dismiss', data.dismiss);

	title.id = 'abet-menu-pointer-title';
	text.id = 'abet-menu-pointer-text';
	box.setAttribute('role', 'dialog');
	box.setAttribute('aria-labelledby', title.id);
	box.setAttribute('aria-describedby', text.id);
	wapuu.src = data.wapuu;
	wapuu.alt = '';
	wapuu.width = 44;
	wapuu.height = 44;
	go.href = data.link;
	dismiss.type = 'button';

	head.append(wapuu, title);
	actions.append(go, dismiss);
	body.append(head, text, actions);
	box.append(body);

	// The admin menu is pinned when it fits the window and scrolls with the page when it does not.
	// The card does what the menu does, so it moves with the menu item by itself, without a script
	// on every scroll: fixed beside a pinned menu, in page coordinates beside one that scrolls. It
	// opens whole inside the window, under the admin bar, and the arrow slides along its edge to
	// keep pointing at the item.
	const menu = document.getElementById('adminmenuwrap');

	const place = () => {
		const pinned =
			!!menu && window.getComputedStyle(menu).position === 'fixed';
		const offset = pinned ? 0 : window.scrollY;
		const rect = target.getBoundingClientRect();
		const middle = rect.top + rect.height / 2;
		const height = box.offsetHeight;
		const lowest = Math.max(40, window.innerHeight - height - 16);
		const top = Math.min(Math.max(40, middle - 39), lowest);

		box.style.position = pinned ? 'fixed' : 'absolute';
		box.style.top = `${top + offset}px`;
		box.style.setProperty(
			'--abet-arrow',
			`${Math.min(Math.max(middle - top - 7, 12), height - 26)}px`
		);

		if (rtl) {
			box.style.right = `${
				document.documentElement.clientWidth - rect.left + 12
			}px`;
		} else {
			box.style.left = `${
				rect.right + window.scrollX * (pinned ? 0 : 1) + 12
			}px`;
		}
	};

	// "Not now" is core's own dismissal of a pointer, per user and per walkthrough version, so a
	// newer walkthrough brings the card back. A declaration, as onKey below calls it too.
	function close() {
		box.remove();
		window.removeEventListener('resize', place);
		document.removeEventListener('keydown', onKey);

		const form = new FormData();

		form.append('action', 'dismiss-wp-pointer');
		form.append('pointer', data.name);
		window
			.fetch(data.ajaxUrl, {
				method: 'POST',
				body: form,
				credentials: 'same-origin',
			})
			.catch(() => {});
	}

	function onKey(event) {
		if (
			event.key === 'Escape' &&
			box.contains(box.ownerDocument.activeElement)
		) {
			close();
		}
	}

	dismiss.addEventListener('click', close);

	// Other plugins point at the admin menu too: wait until theirs are closed, rather than stack on
	// top of them. The admin menu is hidden on small screens; the card waits for it as well.
	const open = () => {
		const others = [...document.querySelectorAll('.wp-pointer')].some(
			(node) => node.getClientRects().length > 0
		);

		if (others || !target.getClientRects().length) {
			window.setTimeout(open, 1000);

			return;
		}

		document.body.append(box);
		place();

		// WordPress pins and unpins the menu as the window is resized.
		window.addEventListener('resize', place);
		document.addEventListener('keydown', onKey);
	};

	window.setTimeout(open, 500);
})();
