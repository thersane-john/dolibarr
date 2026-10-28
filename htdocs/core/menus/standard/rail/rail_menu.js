/* Copyright (C) 2026		John BOTELLA
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Rail menu manager (experimental), see htdocs/core/menus/standard/rail_menu.php
 * Layout by viewport width, panels opened on click, hover or keyboard, quick find, favorites,
 * and badges read from the Dolibarr JS context (MENU_BADGE__<mainmenu> and MENU_BADGE__<mainmenu>__<leftmenu>).
 */
(async function () {
	'use strict';

	// Texts of the menu: the domain is loaded once, then trans() is synchronous. Tools are defined at Ready.
	await Dolibarr.ready();
	try {
		await Dolibarr.tools.langs.load('railmenu');
	} catch (e) {
		Dolibarr.log('rail menu: translations not loaded');
	}
	// Texts are always inserted as text, so html entities of the translations are decoded
	function t(key, ...args) {
		return Dolibarr.tools.langs.transNoEntities(key, ...args);
	}

	const BADGE_PREFIX = 'MENU_BADGE__';
	const STORAGE_PINNED = 'dolNewMenuPinned';
	const STORAGE_FAVORITES = 'dolNewMenuFavorites';
	const STORAGE_RAIL = 'dolNewMenuRail';
	// Params ignored to find the menu entry of the page shown
	const IGNORED_PARAMS = new Set(['mainmenu', 'leftmenu', 'idmenu', 'token', 'menu', 'restore_lastsearch_values']);
	const FIND_MAX_RESULTS = 8;
	const ESCAPE_DOUBLE_DELAY = 600;

	const body = document.body;
	const nav = document.getElementById('nm-nav');
	const burger = document.getElementById('nm-burger');
	const backdrop = document.querySelector('.nm-backdrop');
	const findInput = document.getElementById('nm-find-input');
	const findBox = document.getElementById('nm-find-results');
	const findList = findBox.querySelector('ul');
	const findEmpty = findBox.querySelector('.nm-find-empty');
	// Places where records can be searched, from the search of the core
	let searchTargets = [];
	try {
		searchTargets = JSON.parse(document.querySelector('.nm-find').dataset.searchTargets || '[]');
	} catch (e) {
		searchTargets = [];
	}
	// Search target of the page in use: same script (a list of these records), else same directory (a card)
	const contextTarget = (function () {
		const path = window.location.pathname;
		const dir = path.replace(/[^/]*$/, '');
		const paths = searchTargets.map(function (target) {
			return new URL(target.url, window.location.href).pathname;
		});
		const same = paths.indexOf(path);
		return same !== -1 ? same : paths.findIndex(function (p) {
			return p.replace(/[^/]*$/, '') === dir;
		});
	})();
	// On the list of these records, its own filter form is sent: filters, sort and columns are kept
	const listForm = contextTarget !== -1 && new URL(searchTargets[contextTarget].url, window.location.href).pathname === window.location.pathname
		? document.getElementById('searchFormList') : null;
	const notifPop = document.getElementById('nm-notif-pop');

	const mqDesktop = window.matchMedia('(min-width: 1024px)');
	const mqMobile = window.matchMedia('(max-width: 767.98px)');
	const mqHover = window.matchMedia('(hover: hover) and (pointer: fine)');
	const HOVER_OPEN_DELAY = 120;
	const HOVER_SWITCH_DELAY = 180;
	const HOVER_CLOSE_DELAY = 300;
	const HOVER_SEEN_DELAY = 1000;

	const state = {
		pinned: readStorage(STORAGE_PINNED, true),
		openSection: null,
		openedBy: null,
		currentSection: nav.dataset.currentSection || null,
		drawerOpen: false,
		favorites: readStorage(STORAGE_FAVORITES, []),
		rail: readStorage(STORAGE_RAIL, 'labels'),
		badges: new Map(),
		lastTrigger: null,
	};

	/*
	 * Storage, per viewer convenience only (favorites and display options should become user params)
	 */

	function readStorage(key, fallback) {
		try {
			const value = window.localStorage.getItem(key);
			return value === null ? fallback : JSON.parse(value);
		} catch (e) {
			return fallback;
		}
	}

	function writeStorage(key, value) {
		try {
			window.localStorage.setItem(key, JSON.stringify(value));
		} catch (e) {
			// Private mode or blocked storage: the menu still works, without memory
		}
	}

	/*
	 * Layout and panels
	 */

	function layout() {
		if (mqMobile.matches) return 'mobile';
		return mqDesktop.matches ? 'desktop' : 'tablet';
	}

	/**
	 * Display of the sections bar: 'labels', 'icons' or 'hidden' (shown on hover, needs a pointer that can hover)
	 */
	function railMode() {
		return state.rail === 'hidden' && !mqHover.matches ? 'icons' : state.rail;
	}

	// The panel can be kept open on large screens, unless the sections bar itself is hidden
	function canPin() {
		return layout() === 'desktop' && railMode() !== 'hidden';
	}

	function isOverlay() {
		return !canPin() || !state.pinned;
	}

	function applyRail() {
		const mode = railMode();
		body.dataset.rail = mode;
		nav.querySelectorAll('.nm-rail-item').forEach(function (item) {
			// Without visible label, the label goes in a tooltip
			if (mode === 'labels') {
				item.removeAttribute('title');
			} else {
				item.title = item.querySelector('.nm-rail-label').textContent;
			}
		});
		nav.querySelectorAll('input[name="nm-rail"]').forEach(function (input) {
			input.checked = input.value === mode;
		});
		nav.querySelector('.nm-option-hover').hidden = !mqHover.matches;
	}

	nav.querySelectorAll('input[name="nm-rail"]').forEach(function (input) {
		input.addEventListener('change', function () {
			state.rail = input.value;
			writeStorage(STORAGE_RAIL, state.rail);
			applyRail();
			if (isOverlay()) closePanel(false);
			applyLayout();
		});
	});
	nav.querySelector('input[name="nm-pinned"]').addEventListener('change', function (e) {
		setPinned(e.target.checked);
	});

	function railItem(section) {
		return nav.querySelector('.nm-rail-item[data-section="' + CSS.escape(section) + '"]');
	}

	function applyLayout() {
		const current = layout();
		body.dataset.layout = current;
		body.classList.toggle('nm-pinned', canPin() && state.pinned);

		if (current !== 'mobile' && state.drawerOpen) {
			closeDrawer(false);
		}
		if (canPin() && state.pinned && !state.openSection) {
			openPanel(state.currentSection, false);
		}
		updateBurger();
	}

	function updateBurger() {
		let expanded;
		if (layout() === 'mobile') {
			expanded = state.drawerOpen;
		} else if (canPin()) {
			expanded = state.pinned;
		} else {
			expanded = state.openSection !== null;
		}
		burger.setAttribute('aria-expanded', expanded ? 'true' : 'false');

		// On large screens the button switches between the two modes, its icon and label say which one comes next
		let icon = 'fa-bars';
		let label = t('RailMenuMainMenu');
		if (canPin()) {
			icon = state.pinned ? 'fa-outdent' : 'fa-indent';
			label = state.pinned ? t('RailMenuCollapse') : t('RailMenuKeepOpen');
		}
		burger.querySelector('.fas').className = 'fas ' + icon;
		burger.setAttribute('aria-label', label);
		burger.title = label;

		nav.querySelectorAll('.nm-pin').forEach(function (pin) {
			const pinLabel = state.pinned ? t('RailMenuCollapseShort') : t('RailMenuKeepOpen');
			pin.setAttribute('aria-pressed', state.pinned ? 'true' : 'false');
			pin.setAttribute('aria-label', pinLabel);
			pin.title = pinLabel;
		});

		const pinOption = nav.querySelector('input[name="nm-pinned"]');
		pinOption.checked = state.pinned;
		pinOption.disabled = !canPin();
		pinOption.closest('.nm-option').classList.toggle('nm-option-disabled', !canPin());
	}

	function setPinned(value) {
		state.pinned = value;
		writeStorage(STORAGE_PINNED, state.pinned);
		if (state.pinned) {
			openPanel(state.openSection || state.currentSection, false);
		} else {
			closePanel(false);
		}
		applyLayout();
	}

	function openPanel(section, moveFocus, by) {
		if (!section) return;
		state.openedBy = by || 'click';
		const panel = document.getElementById('nm-panel-' + section);
		if (!panel) return;
		const wasClosed = !state.openSection;

		nav.querySelectorAll('.nm-panel').forEach(function (p) {
			p.classList.remove('nm-panel-boot');
			p.hidden = p !== panel;
		});
		nav.querySelectorAll('button.nm-rail-item').forEach(function (item) {
			item.setAttribute('aria-expanded', item.dataset.section === section ? 'true' : 'false');
		});
		state.openSection = section;
		body.classList.add('nm-panel-open');
		// No backdrop for a panel opened by hover, it closes when the pointer leaves the menu
		backdrop.hidden = !(state.drawerOpen || (state.openedBy !== 'hover' && isOverlay() && layout() !== 'mobile'));
		updateBurger();

		if (moveFocus) {
			const first = panel.querySelector('.nm-panel-body a, .nm-panel-body button, .nm-panel-title a');
			if (first) first.focus();
		}

		// A panel over the page comes out from the sections bar (not when another section is shown in the open panel)
		if (wasClosed && isOverlay() && layout() !== 'mobile') {
			const panels = nav.querySelector('.nm-panels');
			panels.classList.remove('nm-panels-enter');
			void panels.offsetWidth;
			panels.classList.add('nm-panels-enter');
		}

		loadWidget(panel);
		seenOnOpen(section, state.openedBy);
	}

	/*
	 * Widget at the bottom of a panel, loaded the first time the panel opens
	 */

	const AJAX_URL = Dolibarr.getContextVar('DOL_URL_ROOT', '') + '/core/ajax/railmenu.php';

	function loadWidget(panel) {
		const slot = panel.querySelector('.nm-panel-widget[data-widget-section]');
		if (!slot || slot.dataset.loaded) return;
		slot.dataset.loaded = 'loading';
		Dolibarr.tools.fetch(AJAX_URL, {
			data: { action: 'widget', section: slot.dataset.widgetSection },
			token: true,
			unwrap: true,
			showErrors: false,
		}).then(function (data) {
			slot.dataset.loaded = 'done';
			if (data && data.html) {
				// Html built by the server (core and hook railMenuPanelWidget)
				slot.innerHTML = data.html;
				slot.hidden = false;
				Dolibarr.initNewContent(slot);
			}
		}).catch(function () {
			// No widget, the panel is still usable: try again at the next opening
			delete slot.dataset.loaded;
		});
	}

	/*
	 * Badges seen: a badge goes away once the user looked at what it announces
	 */

	let seenTimer = null;

	// A panel opened on purpose (click, keyboard, kept open) is seen at once, a panel opened by hover only if it stays open
	function seenOnOpen(section, by) {
		clearTimeout(seenTimer);
		if (by !== 'hover') {
			markSeen(section);
			return;
		}
		seenTimer = setTimeout(function () {
			if (state.openSection === section) markSeen(section);
		}, HOVER_SEEN_DELAY);
	}

	function markSeen(key) {
		const previous = state.badges.get(key) || 0;
		if (!previous) return;
		// Optimistic: the badge goes away now, it comes back if the server could not keep it
		Dolibarr.setContextVar(BADGE_PREFIX + key, 0, true);
		Dolibarr.tools.fetch.post(AJAX_URL, { action: 'markseen', key: key }, { showErrors: false }).catch(function () {
			Dolibarr.setContextVar(BADGE_PREFIX + key, previous, true);
		});
	}

	function closePanel(returnFocus) {
		const section = state.openSection;
		nav.querySelectorAll('.nm-panel').forEach(function (p) {
			p.classList.remove('nm-panel-boot');
			p.hidden = true;
		});
		nav.querySelectorAll('button.nm-rail-item').forEach(function (item) {
			item.setAttribute('aria-expanded', 'false');
		});
		state.openSection = null;
		state.openedBy = null;
		body.classList.remove('nm-panel-open');
		if (!state.drawerOpen) backdrop.hidden = true;
		updateBurger();

		if (returnFocus && section) {
			const item = railItem(section);
			if (item) item.focus();
		}
	}

	function setInert(value) {
		// While the drawer is open, the rest of the page is not reachable
		document.querySelectorAll('.nm-topbar, .nm-content').forEach(function (el) {
			el.inert = value;
		});
	}

	function openDrawer() {
		state.drawerOpen = true;
		body.classList.add('nm-drawer-open');
		backdrop.hidden = false;
		setInert(true);
		updateBurger();
		const target = railItem(state.currentSection) || nav.querySelector('.nm-rail-item');
		if (target) target.focus();
	}

	function closeDrawer(returnFocus) {
		state.drawerOpen = false;
		body.classList.remove('nm-drawer-open');
		closePanel(false);
		backdrop.hidden = true;
		setInert(false);
		updateBurger();
		if (returnFocus) burger.focus();
	}

	burger.addEventListener('click', function () {
		const current = layout();
		if (current === 'mobile') {
			if (state.drawerOpen) {
				closeDrawer(true);
			} else {
				openDrawer();
			}
		} else if (canPin()) {
			setPinned(!state.pinned);
		} else if (state.openSection) {
			closePanel(true);
		} else {
			openPanel(state.currentSection, true);
		}
	});

	nav.addEventListener('click', function (e) {
		const item = e.target.closest('button.nm-rail-item');
		if (item) {
			const section = item.dataset.section;
			clearHoverTimers();
			if (state.openSection === section && state.openedBy === 'hover') {
				// A click on a section opened by hover keeps it open
				openPanel(section, false, 'click');
			} else if (state.openSection === section && isOverlay()) {
				closePanel(false);
			} else {
				openPanel(section, e.detail === 0);
			}
			return;
		}

		if (e.target.closest('.nm-pin')) {
			setPinned(!state.pinned);
			return;
		}

		const toggle = e.target.closest('.nm-toggle');
		if (toggle) {
			setExpanded(toggle, toggle.getAttribute('aria-expanded') !== 'true');
			return;
		}

		if (e.target.closest('.nm-back')) {
			closePanel(true);
			return;
		}
		if (e.target.closest('.nm-panel-close')) {
			if (layout() === 'mobile') {
				closeDrawer(true);
			} else {
				closePanel(true);
			}
			return;
		}

		const fav = e.target.closest('.nm-fav');
		if (fav) {
			toggleFavorite(fav.dataset.entryId);
		}
	});

	// Arrow keys move between the sections of the rail
	nav.querySelector('.nm-rail').addEventListener('keydown', function (e) {
		if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(e.key)) return;
		const items = Array.from(nav.querySelectorAll('.nm-rail-item'));
		const index = items.indexOf(document.activeElement);
		if (index === -1) return;
		e.preventDefault();
		let next = index;
		if (e.key === 'ArrowDown') next = (index + 1) % items.length;
		if (e.key === 'ArrowUp') next = (index - 1 + items.length) % items.length;
		if (e.key === 'Home') next = 0;
		if (e.key === 'End') next = items.length - 1;
		items[next].focus();
	});

	/*
	 * Collapsed mode: hovering a section opens its panel. Click and keyboard still work the same way.
	 */

	let hoverOpenTimer = null;
	let hoverCloseTimer = null;

	function hoverEnabled() {
		return mqHover.matches && isOverlay() && layout() !== 'mobile';
	}

	function clearHoverTimers() {
		clearTimeout(hoverOpenTimer);
		clearTimeout(hoverCloseTimer);
	}

	nav.querySelectorAll('.nm-rail-item').forEach(function (item) {
		item.addEventListener('mouseenter', function () {
			if (!hoverEnabled() || state.openedBy === 'click') return;
			const section = item.dataset.section;
			clearTimeout(hoverOpenTimer);
			if (item.tagName === 'A') {
				// Direct link: nothing to open, the panel opened by hover goes away
				hoverOpenTimer = setTimeout(function () {
					if (state.openedBy === 'hover') closePanel(false);
				}, HOVER_SWITCH_DELAY);
				return;
			}
			// A short delay so that crossing the rail does not open every section on the way
			hoverOpenTimer = setTimeout(function () {
				if (state.openSection !== section) openPanel(section, false, 'hover');
			}, state.openSection ? HOVER_SWITCH_DELAY : HOVER_OPEN_DELAY);
		});
		item.addEventListener('mouseleave', function () {
			clearTimeout(hoverOpenTimer);
		});
	});

	nav.addEventListener('mouseenter', function () {
		clearTimeout(hoverCloseTimer);
	});
	nav.addEventListener('mouseleave', function () {
		clearTimeout(hoverOpenTimer);
		const displayPop = document.getElementById('nm-display-pop');
		// When the sections bar is hidden, its display options go away with it
		const closeDisplay = railMode() === 'hidden' && !displayPop.hidden;
		if (state.openedBy !== 'hover' && !closeDisplay) return;
		hoverCloseTimer = setTimeout(function () {
			if (closeDisplay) hidePopovers(false);
			// Keep the panel if the keyboard focus is inside (a focus left by a mouse click does not count)
			if (state.openedBy === 'hover' && !nav.querySelector(':focus-visible')) closePanel(false);
		}, HOVER_CLOSE_DELAY);
	});

	backdrop.addEventListener('click', function () {
		if (state.drawerOpen) {
			closeDrawer(false);
		} else {
			closePanel(false);
		}
	});

	function setExpanded(toggle, expanded) {
		const sub = document.getElementById(toggle.getAttribute('aria-controls'));
		toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		if (sub) sub.hidden = !expanded;
	}

	/*
	 * Links
	 */

	// Preview without changing the setup (url parameter menu=rail_menu.php): the parameter is kept on the links of the page
	const previewMenu = Dolibarr.getContextVar('MENU_RAIL_PREVIEW', '');
	if (previewMenu) {
		document.addEventListener('click', function (e) {
			const a = e.target.closest ? e.target.closest('a[href]') : null;
			if (!a) return;
			const raw = a.getAttribute('href') || '';
			if (raw === '' || raw.charAt(0) === '#' || /^javascript:/i.test(raw)) return;
			try {
				const url = new URL(a.href);
				if (url.origin === window.location.origin && /\.php$/.test(url.pathname)) {
					url.searchParams.set('menu', previewMenu);
					a.href = url.toString();
				}
			} catch (err) {
				// Not an url, nothing to do
			}
		}, true);
	}

	function navigate(href) {
		const url = new URL(href, window.location.href);
		if (url.origin !== window.location.origin) {
			window.open(url.toString(), '_blank', 'noopener');
			return;
		}
		if (previewMenu) url.searchParams.set('menu', previewMenu);
		window.location.href = url.toString();
	}

	function closeUserMenu(returnFocus) {
		const dropdown = document.getElementById('topmenu-login-dropdown');
		if (!dropdown || !dropdown.classList.contains('open')) return false;
		dropdown.classList.remove('open');
		if (returnFocus) {
			const toggle = dropdown.querySelector('.dropdown-toggle');
			if (toggle) toggle.focus();
		}
		return true;
	}

	/**
	 * Section of a page that is not an entry of the menu (a card for example): an entry on the same page,
	 * else an entry in the same directory (societe/card.php belongs to the section of societe/list.php)
	 */
	function sectionFromPath(pathname) {
		const dir = pathname.replace(/[^/]*$/, '');
		let sameDir = null;
		const links = nav.querySelectorAll('.nm-panel:not([data-section="favorites"]) a.nm-link');
		for (const a of links) {
			const path = new URL(a.href, window.location.href).pathname;
			const section = a.closest('.nm-panel').dataset.section;
			if (path === pathname) return section;
			if (!sameDir && path.replace(/[^/]*$/, '') === dir) sameDir = section;
		}
		return sameDir;
	}

	/**
	 * Find the menu entry of the page shown: same path, all its params found in the page url, most params matched
	 */
	function syncCurrent(loc) {
		const pageParams = new URLSearchParams(loc.search);
		let best = null;
		let bestScore = -1;
		// Without exact match (a list opened with other filters), the first entry of the same page
		let samePage = null;

		nav.querySelectorAll('.nm-panel:not([data-section="favorites"]) a.nm-link').forEach(function (a) {
			const url = new URL(a.href, window.location.href);
			if (url.pathname !== loc.pathname) return;
			if (!samePage) samePage = a;
			let score = 0;
			let ok = true;
			url.searchParams.forEach(function (value, key) {
				if (IGNORED_PARAMS.has(key)) return;
				if (pageParams.get(key) === value) {
					score++;
				} else {
					ok = false;
				}
			});
			if (ok && score > bestScore) {
				best = a;
				bestScore = score;
			}
		});

		if (!best) best = samePage;

		nav.querySelectorAll('[aria-current]').forEach(function (el) {
			el.removeAttribute('aria-current');
		});

		let section = pageParams.get('mainmenu') || sectionFromPath(loc.pathname) || state.currentSection;
		if (best) {
			best.setAttribute('aria-current', 'page');
			// The badge of an entry goes away when its page is opened
			if (best.dataset.menuKey) markSeen(best.dataset.menuKey);
			section = best.closest('.nm-panel').dataset.section;
			// Open the branches that contain the current entry
			let sub = best.closest('.nm-sub[hidden]');
			while (sub) {
				const toggle = nav.querySelector('.nm-toggle[aria-controls="' + sub.id + '"]');
				if (toggle) setExpanded(toggle, true);
				sub = sub.parentElement.closest('.nm-sub[hidden]');
			}
		}

		if (section && railItem(section)) {
			state.currentSection = section;
			railItem(section).setAttribute('aria-current', 'true');
			if (!isOverlay() && state.openSection !== 'favorites') {
				if (document.getElementById('nm-panel-' + section)) {
					openPanel(section, false);
				} else {
					// Section without panel: the column goes away, the page takes the width
					closePanel(false);
				}
			}
		}
	}

	/*
	 * Popovers (create, notifications)
	 */

	function hidePopovers(returnFocus) {
		let had = false;
		document.querySelectorAll('.nm-popover').forEach(function (pop) {
			if (!pop.hidden) had = true;
			pop.hidden = true;
		});
		document.querySelectorAll('.nm-pop-trigger').forEach(function (btn) {
			btn.setAttribute('aria-expanded', 'false');
		});
		if (had && returnFocus && state.lastTrigger) state.lastTrigger.focus();
		return had;
	}

	document.querySelectorAll('.nm-pop-trigger').forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			const pop = document.getElementById(btn.getAttribute('aria-controls'));
			const wasOpen = !pop.hidden;
			hidePopovers(false);
			if (wasOpen) return;
			if (state.drawerOpen) closeDrawer(false);
			if (state.openedBy === 'hover') closePanel(false);
			pop.hidden = false;
			btn.setAttribute('aria-expanded', 'true');
			state.lastTrigger = btn;
			if (e.detail === 0) {
				const first = pop.querySelector('a, select, button');
				if (first) first.focus();
			}
		});
	});

	document.addEventListener('pointerdown', function (e) {
		if (!e.target.closest('.nm-pop, .nm-popover, .nm-rail-tool')) hidePopovers(false);
		if (!e.target.closest('.nm-find, .nm-find-toggle')) closeFind(false);
		if (isOverlay() && layout() !== 'mobile' && state.openSection && !e.target.closest('.nm-nav, #nm-burger')) {
			closePanel(false);
		}
	});

	/*
	 * Quick find on all the menu entries
	 */

	let findIndex = null;
	let findActive = -1;

	function normalize(text) {
		return text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
	}

	function dirOf(href) {
		try {
			return new URL(href, window.location.href).pathname.replace(/[^/]*$/, '');
		} catch (e) {
			return '';
		}
	}

	/**
	 * Other words for the pages, without a dictionary to maintain: the labels of the search of the core are translated
	 * and point to a list ("Propositions/devis" for comm/propal/list.php), their words are given to the pages of the same directory
	 */
	function keywordsByDir() {
		const map = new Map();
		searchTargets.forEach(function (target) {
			const dir = dirOf(target.url);
			if (!dir || dir === '/') return;
			map.set(dir, (map.get(dir) || '') + ' ' + target.label);
		});
		return map;
	}

	function buildFindIndex() {
		findIndex = [];
		const keywords = keywordsByDir();
		nav.querySelectorAll('.nm-panel:not([data-section="favorites"]) a.nm-link').forEach(function (a) {
			const panel = a.closest('.nm-panel');
			const path = [panel.getAttribute('aria-label')];
			let node = a.closest('.nm-node');
			while (node) {
				node = node.parentElement.closest('.nm-node');
				if (node) path.splice(1, 0, node.querySelector('.nm-link-label').textContent);
			}
			const label = a.querySelector('.nm-link-label').textContent;
			findIndex.push({
				label: label,
				context: path.join(' > '),
				href: a.href,
				haystack: normalize(label + ' ' + path.join(' ') + ' ' + (keywords.get(dirOf(a.href)) || '')),
				labelNorm: normalize(label),
			});
		});
		// Sections without sub entries are links of the rail
		nav.querySelectorAll('a.nm-rail-link').forEach(function (a) {
			const label = a.querySelector('.nm-rail-label').textContent;
			findIndex.push({
				label: label,
				context: t('RailMenuSection'),
				href: a.href,
				haystack: normalize(label),
				labelNorm: normalize(label),
			});
		});
	}

	function closeFind(clear) {
		findBox.hidden = true;
		findInput.setAttribute('aria-expanded', 'false');
		findInput.removeAttribute('aria-activedescendant');
		findActive = -1;
		if (clear) findInput.value = '';
		body.classList.remove('nm-find-open');
	}

	function renderFind() {
		const query = normalize(findInput.value.trim());
		findList.innerHTML = '';
		findActive = -1;
		findInput.removeAttribute('aria-activedescendant');
		if (query === '') {
			findBox.hidden = true;
			findInput.setAttribute('aria-expanded', 'false');
			return;
		}
		if (!findIndex) buildFindIndex();

		const raw = findInput.value.trim();
		const tokens = query.split(/\s+/);
		const pages = findIndex.filter(function (item) {
			return tokens.every(function (token) {
				return item.haystack.includes(token);
			});
		}).sort(function (a, b) {
			return rank(a, query) - rank(b, query);
		}).slice(0, FIND_MAX_RESULTS);

		// Letters at the start of a reference (PO2610-0001 gives PO): the place whose numbering uses them comes first
		const refMatch = raw.match(/^([a-z]+)[^a-z]/i);
		const refLetters = refMatch ? refMatch[1].toUpperCase() : '';
		const searches = searchTargets.map(function (target, i) {
			const inList = i === contextTarget && listForm !== null;
			return {
				// The text typed is shown once in the title of the group, each line names the place only
				label: inList ? t('RailMenuSearchThisList', target.label) : target.label,
				fullLabel: inList ? t('RailMenuSearchInList', raw, target.label) : t('RailMenuSearchIn', raw, target.label),
				inList: inList,
				here: i === contextTarget,
				icon: target.icon,
				href: target.url + (target.url.indexOf('?') === -1 ? '?' : '&') + 'search_all=' + encodeURIComponent(raw),
				match: (refLetters !== '' && (target.prefixes || []).includes(refLetters))
					|| tokens.every(function (token) {
						return normalize(target.label).includes(token);
					}),
			};
		}).sort(function (a, b) {
			// Reference letters or name typed first, then the records of the page in use
			const score = function (item) {
				return (item.match ? 2 : 0) + (item.here ? 1 : 0);
			};
			return score(b) - score(a);
		});

		// A reference (with a digit) or a text that is not a page: the search of records comes first
		const recordsFirst = pages.length === 0 || /\d/.test(raw);
		let index = 0;
		const addGroup = function (title, items, render, headClass) {
			if (!items.length) return;
			const head = document.createElement('li');
			head.className = 'nm-find-group' + (headClass ? ' ' + headClass : '');
			head.setAttribute('role', 'presentation');
			head.textContent = title;
			head.title = title;
			findList.appendChild(head);
			items.forEach(function (item) {
				const li = document.createElement('li');
				li.id = 'nm-find-opt-' + (index++);
				li.setAttribute('role', 'option');
				li.setAttribute('aria-selected', 'false');
				li.dataset.href = item.href;
				if (item.inList) li.dataset.inList = '1';
				render(li, item);
				findList.appendChild(li);
			});
		};
		const renderPage = function (li, item) {
			const label = document.createElement('span');
			label.className = 'nm-find-label';
			label.textContent = item.label;
			const context = document.createElement('span');
			context.className = 'nm-find-context';
			context.textContent = item.context;
			li.append(label, context);
		};
		const renderSearch = function (li, item) {
			li.classList.add('nm-find-search');
			if (item.match) li.classList.add('nm-find-match');
			const label = document.createElement('span');
			label.className = 'nm-find-label';
			// Icon html comes from the server (img_picto)
			label.innerHTML = item.icon;
			label.append(item.label);
			li.appendChild(label);
			li.setAttribute('aria-label', item.fullLabel);
		};

		if (recordsFirst) {
			addGroup(t('RailMenuSearchRecordsIn', raw), searches, renderSearch, 'nm-find-group-search');
			addGroup(t('RailMenuPages'), pages, renderPage);
		} else {
			addGroup(t('RailMenuPages'), pages, renderPage);
			addGroup(t('RailMenuSearchRecordsIn', raw), searches, renderSearch, 'nm-find-group-search');
		}

		findEmpty.hidden = index > 0;
		findEmpty.textContent = t('RailMenuNoMatch', raw);
		findBox.hidden = false;
		findInput.setAttribute('aria-expanded', 'true');
		if (index) setFindActive(0);
	}

	function rank(item, query) {
		if (item.labelNorm.startsWith(query)) return 0;
		if (item.labelNorm.includes(query)) return 1;
		return 2;
	}

	function setFindActive(index) {
		const options = findList.querySelectorAll('[role="option"]');
		if (!options.length) return;
		findActive = (index + options.length) % options.length;
		options.forEach(function (opt, i) {
			opt.setAttribute('aria-selected', i === findActive ? 'true' : 'false');
		});
		findInput.setAttribute('aria-activedescendant', options[findActive].id);
		options[findActive].scrollIntoView({ block: 'nearest' });
	}

	findInput.addEventListener('input', renderFind);
	findInput.addEventListener('focus', function () {
		if (findInput.value.trim() !== '') {
			// The search of the page is kept in the field: selected, so that typing replaces it
			findInput.select();
			renderFind();
		}
	});
	findInput.addEventListener('keydown', function (e) {
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			setFindActive(findActive + 1);
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			setFindActive(findActive - 1);
		} else if (e.key === 'Enter') {
			const options = findList.querySelectorAll('[role="option"]');
			if (findActive >= 0 && options[findActive]) {
				e.preventDefault();
				chooseFind(options[findActive]);
			}
		}
	});
	findList.addEventListener('click', function (e) {
		const opt = e.target.closest('[role="option"]');
		if (opt) chooseFind(opt);
	});

	function chooseFind(opt) {
		if (opt.dataset.inList && listForm) {
			searchInList(findInput.value.trim());
		} else {
			navigate(opt.dataset.href);
		}
	}

	// Sends the filter form of the list with the text in search_all, back to the first page
	function searchInList(text) {
		const setField = function (name, value) {
			let field = listForm.querySelector('input[name="' + name + '"]');
			if (!field) {
				field = document.createElement('input');
				field.type = 'hidden';
				field.name = name;
				listForm.appendChild(field);
			}
			field.value = value;
		};
		setField('search_all', text);
		if (listForm.querySelector('[name="page"]')) setField('page', '0');
		if (previewMenu) setField('menu', previewMenu);
		// The form may have a field named submit that hides the method
		HTMLFormElement.prototype.submit.call(listForm);
	}

	function openFind() {
		body.classList.add('nm-find-open');
		findInput.focus();
	}

	/*
	 * Favorites
	 */

	function toggleFavorite(id) {
		const index = state.favorites.indexOf(id);
		if (index === -1) {
			state.favorites.push(id);
		} else {
			state.favorites.splice(index, 1);
		}
		writeStorage(STORAGE_FAVORITES, state.favorites);
		renderFavorites();
	}

	function renderFavorites() {
		// The tooltip says what a click does, the accessible name stays the entry (toggle button with aria-pressed)
		nav.querySelectorAll('.nm-fav').forEach(function (btn) {
			const isFavorite = state.favorites.includes(btn.dataset.entryId);
			btn.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
			btn.title = t(isFavorite ? 'RailMenuFavoriteRemoveTitle' : 'RailMenuFavoriteAddTitle');
		});

		const list = nav.querySelector('.nm-fav-list');
		list.innerHTML = '';
		let count = 0;
		state.favorites.forEach(function (id) {
			const source = nav.querySelector('.nm-panel:not([data-section="favorites"]) a.nm-link[data-entry-id="' + CSS.escape(id) + '"]');
			if (!source) return;
			count++;
			const li = document.createElement('li');
			li.className = 'nm-node';
			const row = document.createElement('div');
			row.className = 'nm-row';
			const a = document.createElement('a');
			a.className = 'nm-link';
			a.href = source.href;
			const label = document.createElement('span');
			label.className = 'nm-link-label';
			label.textContent = source.querySelector('.nm-link-label').textContent;
			const context = document.createElement('span');
			context.className = 'nm-link-context';
			context.textContent = source.closest('.nm-panel').getAttribute('aria-label');
			a.append(label, context);
			const remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'nm-fav';
			remove.dataset.entryId = id;
			remove.setAttribute('aria-pressed', 'true');
			remove.title = t('RailMenuFavoriteRemoveTitle');
			remove.setAttribute('aria-label', t('RailMenuRemoveFavorite', label.textContent));
			remove.innerHTML = '<span class="fas fa-star" aria-hidden="true"></span>';
			row.append(a, remove);
			li.appendChild(row);
			list.appendChild(li);
		});
		nav.querySelector('.nm-fav-empty').hidden = count > 0;
	}

	/*
	 * Badges, read from the JS context
	 */

	function formatCount(n) {
		return n > 99 ? '99+' : String(n);
	}

	function setBadge(el, n) {
		if (!el) return;
		el.hidden = !(n > 0);
		el.textContent = n > 0 ? formatCount(n) : '';
		if (n > 0) {
			el.setAttribute('aria-label', t(n > 1 ? 'RailMenuNbNotifications' : 'RailMenuNbNotification', n));
		} else {
			el.removeAttribute('aria-label');
		}
	}

	function renderBadges() {
		const totals = new Map();
		let total = 0;

		// Entries: the badge is shown on the first entry of the key
		nav.querySelectorAll('a.nm-link[data-menu-key] > .nm-badge').forEach(function (el) {
			setBadge(el, 0);
		});
		state.badges.forEach(function (value, key) {
			const section = key.split('__')[0];
			totals.set(section, (totals.get(section) || 0) + value);
			if (key.includes('__')) {
				const link = nav.querySelector('a.nm-link[data-menu-key="' + CSS.escape(key) + '"]');
				if (link) setBadge(link.querySelector('.nm-badge'), value);
			}
		});

		nav.querySelectorAll('.nm-rail-item[data-menu-key]').forEach(function (item) {
			const n = totals.get(item.dataset.menuKey) || 0;
			total += n;
			setBadge(item.querySelector('.nm-badge'), n);
		});
		document.querySelectorAll('.nm-badge-total').forEach(function (el) {
			setBadge(el, total);
		});

		renderNotifications(totals);
	}

	function renderNotifications(totals) {
		let help = {};
		try {
			help = JSON.parse(notifPop.dataset.badgeHelp || '{}');
		} catch (e) {
			help = {};
		}
		const list = notifPop.querySelector('.nm-notif-list');
		list.innerHTML = '';
		totals.forEach(function (n, section) {
			const item = railItem(section);
			if (!(n > 0) || !item) return;
			const sectionLink = nav.querySelector('#nm-panel-' + CSS.escape(section) + ' .nm-section-link') || (item.tagName === 'A' ? item : null);
			const li = document.createElement('li');
			const a = document.createElement(sectionLink ? 'a' : 'span');
			a.className = 'nm-notif-item';
			if (sectionLink) a.href = sectionLink.href;
			const label = document.createElement('span');
			label.className = 'nm-notif-label';
			label.textContent = item.querySelector('.nm-rail-label').textContent;
			const count = document.createElement('span');
			count.className = 'nm-badge';
			setBadge(count, n);
			a.append(label, count);
			if (help[section]) {
				const text = document.createElement('span');
				text.className = 'nm-notif-help';
				text.textContent = help[section];
				a.appendChild(text);
			}
			li.appendChild(a);
			list.appendChild(li);
		});
		notifPop.querySelector('.nm-notif-empty').hidden = list.children.length > 0;
	}

	function readBadgesFromContext() {
		const keys = new Set();
		nav.querySelectorAll('[data-menu-key]').forEach(function (el) {
			keys.add(el.dataset.menuKey);
		});
		keys.forEach(function (key) {
			const value = Dolibarr.getContextVar(BADGE_PREFIX + key, null);
			if (value !== null) state.badges.set(key, Number(value) || 0);
		});
	}

	// Any code can update a badge: Dolibarr.setContextVar('MENU_BADGE__ticket', 3, true)
	Dolibarr.on('setContextVar', function (data) {
		if (!data || typeof data.key !== 'string' || data.key.indexOf(BADGE_PREFIX) !== 0) return;
		state.badges.set(data.key.slice(BADGE_PREFIX.length), Number(data.value) || 0);
		renderBadges();
	});

	function initDemo() {
		const select = document.getElementById('nm-demo-key');
		nav.querySelectorAll('.nm-rail-item[data-menu-key]').forEach(function (item) {
			const option = document.createElement('option');
			option.value = item.dataset.menuKey;
			option.textContent = item.querySelector('.nm-rail-label').textContent;
			select.appendChild(option);
		});
		if (state.badges.size) select.value = state.badges.keys().next().value;

		notifPop.querySelector('.nm-demo').addEventListener('click', function (e) {
			const btn = e.target.closest('[data-demo]');
			if (!btn) return;
			const key = select.value;
			const value = btn.dataset.demo === 'add' ? (state.badges.get(key) || 0) + 1 : 0;
			Dolibarr.setContextVar(BADGE_PREFIX + key, value, true);
		});
	}

	/*
	 * Global keys and small screens
	 */

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			if (!findBox.hidden || body.classList.contains('nm-find-open')) {
				closeFind(false);
				findInput.blur();
			} else if (hidePopovers(true) || closeUserMenu(true)) {
				// Closed
			} else if (state.drawerOpen && state.openSection) {
				closePanel(true);
			} else if (state.drawerOpen) {
				closeDrawer(true);
			} else if (state.openSection && isOverlay()) {
				closePanel(true);
			}
			return;
		}
		if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && !isTyping(document.activeElement)) {
			e.preventDefault();
			openFind();
		}
	});

	function isTyping(el) {
		return !!el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
	}

	// Pages often give the focus to a field: Escape twice leaves it, then "/" opens the search.
	// The first Escape stays for the widgets of the page (select lists, autocompletes, dialogs).
	// Capture phase: a widget that stops the Escape key does not hide it from here.
	let lastEscape = 0;
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		const el = document.activeElement;
		if (!isTyping(el) || el.closest('.nm-topbar, .nm-nav')) {
			lastEscape = 0;
			return;
		}
		const now = Date.now();
		if (now - lastEscape < ESCAPE_DOUBLE_DELAY) {
			lastEscape = 0;
			el.blur();
			hintFind();
		} else {
			lastEscape = now;
		}
	}, true);

	// Shows for a moment that "/" now opens the search
	let hintTimer = null;
	function hintFind() {
		const find = document.querySelector('.nm-find');
		find.classList.add('nm-find-hint');
		clearTimeout(hintTimer);
		hintTimer = setTimeout(function () {
			find.classList.remove('nm-find-hint');
		}, 1500);
	}

	// Small screens: the search field is shown over the top bar
	document.querySelector('.nm-find-toggle').addEventListener('click', function () {
		hidePopovers(false);
		if (state.drawerOpen) closeDrawer(false);
		openFind();
	});

	/*
	 * Init
	 */

	mqDesktop.addEventListener('change', applyLayout);
	mqMobile.addEventListener('change', applyLayout);
	mqHover.addEventListener('change', function () {
		applyRail();
		applyLayout();
	});

	applyRail();
	readBadgesFromContext();
	renderFavorites();
	renderBadges();
	initDemo();
	if (state.currentSection && railItem(state.currentSection)) {
		railItem(state.currentSection).setAttribute('aria-current', 'true');
	}
	applyLayout();
	syncCurrent(window.location);
})();
