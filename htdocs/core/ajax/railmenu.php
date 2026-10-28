<?php
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
 *       \file       htdocs/core/ajax/railmenu.php
 *       \brief      Ajax calls of the rail menu manager (experimental, see core/menus/standard/rail_menu.php).
 *                   GET action=widget&section=xxx returns the html of the widget at the bottom of the panel of a section.
 *                   POST action=markseen&key=xxx keeps that the user looked at the badge of a menu key now.
 *                   Answer is a JsonResponse.
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1'); // Disables token renewal, the page is called several times in a row
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREDIRECTBYMAINTOLOGIN')) {
	define('NOREDIRECTBYMAINTOLOGIN', '1');
}

// Load Dolibarr environment
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/jsonResponse.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/menus/standard/rail.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$action = GETPOST('action', 'aZ09');

$langs->loadLangs(array('errors', 'railmenu'));

top_httphead('application/json');

$jsonResponse = new JsonResponse();

if (empty($user->id) || !empty($user->socid)) {
	$jsonResponse->setError($langs->transnoentitiesnoconv('ErrorForbidden'), JsonResponse::HTTP_FORBIDDEN);
	$jsonResponse->output();
}

/*
 * Actions
 */

if ($action == 'widget' && $_SERVER['REQUEST_METHOD'] === 'GET') {	// Test on permission already done (internal user above, rights of each widget in railMenuRenderPanelWidget())
	$section = GETPOST('section', 'aZ09');
	$jsonResponse->data = array('html' => railMenuRenderPanelWidget($db, $user, $section));
	$jsonResponse->setSuccess();
} elseif ($action == 'markseen' && $_SERVER['REQUEST_METHOD'] === 'POST') {	// Test on permission not required (only a param of the user himself)
	// POST only, so the CSRF token is always checked. An invalid token empties $_POST, so the key is empty.
	$key = GETPOST('key', 'aZ09');
	if (!preg_match('/^[a-z0-9_]+$/i', $key)) {
		$jsonResponse->setError($langs->transnoentitiesnoconv('ErrorBadParameters'), JsonResponse::HTTP_BAD_REQUEST);
	} elseif (railMenuMarkSeen($db, $user, $key) < 0) {
		$jsonResponse->setError($db->lasterror(), JsonResponse::HTTP_INTERNAL_ERROR);
	} else {
		$jsonResponse->setSuccess();
	}
} else {
	$jsonResponse->setError($langs->transnoentitiesnoconv('ErrorBadParameters'), JsonResponse::HTTP_BAD_REQUEST);
}


/*
 * View
 */

$jsonResponse->output();
