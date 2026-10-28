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
 * Rail menu manager (experimental): loaded at the start of the top bar, before the menu is drawn.
 * Sets on the body the display chosen by the user (kept in the browser), so that each page is drawn
 * with it at once instead of showing the default display first. Same rules as railMode() and canPin() in rail_menu.js.
 */
(function () {
	'use strict';

	const body = document.body;
	body.classList.add('nm-body');

	try {
		let rail = JSON.parse(window.localStorage.getItem('dolNewMenuRail') || '"labels"');
		const pinned = JSON.parse(window.localStorage.getItem('dolNewMenuPinned') || 'true');
		if (rail === 'hidden' && !window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
			rail = 'icons';
		}
		body.dataset.rail = rail;
		if (pinned && rail !== 'hidden' && window.matchMedia('(min-width: 1024px)').matches) {
			body.classList.add('nm-pinned');
		}
	} catch (e) {
		// Storage not available: default display
	}
})();
