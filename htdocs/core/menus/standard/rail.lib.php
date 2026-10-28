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
 * \file       htdocs/core/menus/standard/rail.lib.php
 * \brief      Functions of the rail menu manager (experimental): menu tree built from eldy, html output
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/menu.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/menubase.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/menus/standard/eldy.lib.php';

/**
 * Build the full menu tree (all top entries with their left menu), from the eldy menu handler.
 * Same approach as the 'jmobile' mode of eldy: the core entries are hard coded into eldy.lib.php,
 * so they can only be read through print_eldy_menu() and print_left_eldy_menu() with $noout = 1.
 *
 * @param	DoliDB	$db		Database handler
 * @param	User	$user	Current user
 * @return	array{sections:array<int,array<string,mixed>>,buildtime:int}	Sections and time spent to build them (ms)
 */
function railMenuBuildTree($db, $user)
{
	$start = microtime(true);

	$typeUser = empty($user->socid) ? 0 : 1;
	$substitarray = array(
		'__LOGIN__' => $user->login,
		'__USER_ID__' => $user->id,
		'__USERID__' => $user->id,
		'__USER_SUPERVISOR_ID__' => $user->fk_user,
	);

	// dolBuildUrl() adds the mainmenu and leftmenu of the current request to the urls it builds: right for eldy,
	// that builds the current section only, wrong for the full tree. They are hidden while the tree is built.
	$savedParams = array();
	foreach (array('mainmenu', 'leftmenu') as $param) {
		if (isset($_GET[$param])) {
			$savedParams['_GET'][$param] = $_GET[$param];
			unset($_GET[$param]);
		}
		if (isset($_POST[$param])) {
			$savedParams['_POST'][$param] = $_POST[$param];
			unset($_POST[$param]);
		}
	}

	$tabMenu = array();
	$menuArbo = new Menubase($db, 'eldy');
	$menuArbo->menuLoad('all', 'all', $typeUser, 'eldy', $tabMenu);

	$topMenu = new Menu();
	print_eldy_menu($db, '', $typeUser, $tabMenu, $topMenu, 1, 'jmobile');

	$sections = array();
	foreach ($topMenu->liste as $val) {
		// Entries not allowed are not shown at all (they are greyed in eldy)
		if ($val['enabled'] != 1 || empty($val['mainmenu'])) {
			continue;
		}

		$subMenu = new Menu();
		print_left_eldy_menu($db, array(), array(), $tabMenu, $subMenu, 1, $val['mainmenu'], 'all', null, $typeUser);

		$flat = railMenuFlattenEntries($subMenu->liste, $val['mainmenu'], $substitarray);
		$index = 0;

		$section = array(
			'key' => $val['mainmenu'],
			'label' => str_replace(' | ', ' & ', railMenuCleanLabel($val['titre'])),
			'icon' => railMenuIcon(empty($val['prefix']) ? '' : $val['prefix']),
			'url' => railMenuBuildUrl($val['url'], $substitarray),
			'target' => empty($val['target']) ? '' : $val['target'],
			'entries' => railMenuTreeFromFlat($flat, $index, 0),
		);

		// A section with neither sub entries nor url leads nowhere
		if (!empty($section['entries']) || $section['url'] !== '') {
			$sections[] = $section;
		}
	}

	foreach ($savedParams['_GET'] ?? array() as $param => $value) {
		$_GET[$param] = $value;
	}
	foreach ($savedParams['_POST'] ?? array() as $param => $value) {
		$_POST[$param] = $value;
	}

	return array('sections' => $sections, 'buildtime' => (int) round((microtime(true) - $start) * 1000));
}

/**
 * Keep only the allowed entries of a left menu, with their url built and their label cleaned
 *
 * @param	array<int,array<string,mixed>>	$liste			Entries of a Menu object
 * @param	string							$mainmenu		Main menu code of the section
 * @param	array<string,string|int>		$substitarray	Substitutions for the urls
 * @return	array<int,array<string,mixed>>					Flat list of entries
 */
function railMenuFlattenEntries($liste, $mainmenu, $substitarray)
{
	$flat = array();
	$skipBelowLevel = -1;

	foreach ($liste as $val) {
		$level = (int) $val['level'];

		// Children of an entry not allowed are not allowed either
		if ($skipBelowLevel >= 0 && $level > $skipBelowLevel) {
			continue;
		}
		$skipBelowLevel = -1;
		if (empty($val['enabled'])) {
			$skipBelowLevel = $level;
			continue;
		}

		$label = railMenuCleanLabel($val['titre']);
		if ($label === '') {
			continue;
		}
		$leftmenu = empty($val['leftmenu']) ? '' : $val['leftmenu'];

		$flat[] = array(
			'id' => substr(md5($mainmenu.'|'.$val['url'].'|'.$label), 0, 12),
			'level' => $level,
			'label' => $label,
			'url' => railMenuAddMenuParams(railMenuBuildUrl($val['url'], $substitarray), $mainmenu),
			'target' => empty($val['target']) ? '' : $val['target'],
			'menukey' => ($leftmenu !== '' ? $mainmenu.'__'.$leftmenu : ''),
			// Groups of eldy have an icon (prefix), kept as is
			'icon' => empty($val['prefix']) ? '' : railMenuIcon($val['prefix'], false),
			'children' => array(),
		);
	}

	return $flat;
}

/**
 * Nest a flat list of entries according to their level
 *
 * @param	array<int,array<string,mixed>>	$flat	Flat list of entries
 * @param	int								$i		Index of the entry to read, moved forward
 * @param	int								$level	Level of the entries to return
 * @return	array<int,array<string,mixed>>			Entries of this level with their children
 */
function railMenuTreeFromFlat($flat, &$i, $level)
{
	$nodes = array();
	$nb = count($flat);

	while ($i < $nb && $flat[$i]['level'] >= $level) {
		if ($flat[$i]['level'] > $level && !empty($nodes)) {
			$last = count($nodes) - 1;
			$nodes[$last]['children'] = array_merge($nodes[$last]['children'], railMenuTreeFromFlat($flat, $i, $flat[$i]['level']));
			continue;
		}
		$nodes[] = $flat[$i];
		$i++;
	}

	return $nodes;
}

/**
 * Return a menu label without html
 *
 * @param	string	$label	Label of the menu entry, may contain html
 * @return	string			Plain text label
 */
function railMenuCleanLabel($label)
{
	return trim(dol_html_entity_decode(dol_string_nohtmltag((string) $label), ENT_QUOTES | ENT_HTML5));
}

/**
 * Return the absolute url of a menu entry
 *
 * @param	string						$url			Url of the menu entry (relative to DOL_URL_ROOT or external)
 * @param	array<string,string|int>	$substitarray	Substitutions
 * @return	string									Url, empty if the entry has no link
 */
function railMenuBuildUrl($url, $substitarray)
{
	$url = make_substitutions((string) $url, $substitarray);
	if ($url === '' || $url === '#') {
		return '';
	}
	if (preg_match('/^https?:\/\//i', $url)) {
		return $url;
	}

	return dol_buildpath($url, 1);
}

/**
 * Add the main menu of its section to the url of an entry, as eldy does when it outputs the left menu,
 * so that the section of the page opened is known
 *
 * @param	string	$url		Url of the entry
 * @param	string	$mainmenu	Main menu code of the section
 * @return	string				Url
 */
function railMenuAddMenuParams($url, $mainmenu)
{
	if ($url === '' || preg_match('/^https?:\/\//i', $url) || preg_match('/[?&]mainmenu=/', $url)) {
		return $url;
	}
	$param = 'mainmenu='.urlencode($mainmenu).(preg_match('/[?&]leftmenu=/', $url) ? '' : '&leftmenu=');

	return $url.(strpos($url, '?') === false ? '?' : '&').$param;
}

/**
 * Return the html of the icon of a menu entry, from its 'prefix'
 *
 * @param	string	$prefix			Prefix of the menu entry ('fa-xxx' or html)
 * @param	bool	$withdefault	Return a default icon when the prefix gives none (sections of the rail)
 * @return	string					Html of the icon
 */
function railMenuIcon($prefix, $withdefault = true)
{
	$reg = array();
	if (preg_match('/^(fa[rsb]? )?fa-/', $prefix, $reg)) {
		return '<span class="'.(empty($reg[1]) ? 'fa ' : '').dol_escape_htmltag($prefix).' fa-fw" aria-hidden="true"></span>';
	}
	if (strpos($prefix, '<span') === 0) {
		return preg_replace('/^<span /', '<span aria-hidden="true" ', $prefix);
	}
	// Modules may give an image (img_picto() of a picto file of the module)
	if (strpos($prefix, '<img') === 0) {
		return preg_replace('/^<img /', '<img aria-hidden="true" ', $prefix);
	}

	return $withdefault ? '<span class="fas fa-th-large fa-fw" aria-hidden="true"></span>' : '';
}

/**
 * Demo producers of notifications: what is new for the user since he last looked at the section.
 * Later, modules will provide them through the JS context.
 *
 * @param	DoliDB	$db		Database handler
 * @param	User	$user	Current user
 * @return	array<string,array{count:int,help:string}>	Counters by menu key
 */
function railMenuGetDemoBadges($db, $user)
{
	global $langs;

	$badges = array();

	if (isModEnabled('ticket') && $user->hasRight('ticket', 'read')) {
		require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticket.class.php';
		$sql = "SELECT COUNT(t.rowid) as nb FROM ".MAIN_DB_PREFIX."ticket as t";
		$sql .= " WHERE t.entity IN (".getEntity('ticket').")";
		$sql .= " AND t.fk_user_assign = ".((int) $user->id);
		$sql .= " AND t.fk_statut IN (".Ticket::STATUS_NOT_READ.", ".Ticket::STATUS_ASSIGNED.")";
		$sql .= " AND t.datec > '".$db->idate(railMenuSeenDate($user, 'ticket'))."'";
		$badges['ticket'] = array('count' => railMenuCount($db, $sql), 'help' => $langs->trans('RailMenuDemoTicket'));
	}

	if (isModEnabled('agenda') && $user->hasRight('agenda', 'myactions', 'read')) {
		// Events of the user created by someone else, not done
		$sql = "SELECT COUNT(a.id) as nb FROM ".MAIN_DB_PREFIX."actioncomm as a";
		$sql .= " WHERE a.entity IN (".getEntity('agenda').")";
		$sql .= " AND ".railMenuSqlMyEvents((int) $user->id);
		$sql .= " AND (a.fk_user_author IS NULL OR a.fk_user_author <> ".((int) $user->id).")";
		$sql .= " AND a.percent <> 100";
		$sql .= " AND a.datec > '".$db->idate(railMenuSeenDate($user, 'agenda'))."'";
		$badges['agenda'] = array('count' => railMenuCount($db, $sql), 'help' => $langs->trans('RailMenuDemoAgenda'));
	}

	return $badges;
}

/**
 * Name of the user parameter that keeps when the user last looked at a badge
 *
 * @param	string	$key	Menu key of the badge (mainmenu or mainmenu__leftmenu)
 * @return	string			Parameter name
 */
function railMenuSeenParam($key)
{
	return 'MAIN_MENU_RAIL_SEEN_'.strtoupper($key);
}

/**
 * Date when the user last looked at a badge, kept as user parameter so that it follows him on every device.
 * Never looked: his previous login.
 *
 * @param	User	$user	User
 * @param	string	$key	Menu key of the badge
 * @return	int				Timestamp
 */
function railMenuSeenDate($user, $key)
{
	$seen = getDolUserInt(railMenuSeenParam($key), 0, $user);
	if ($seen > 0) {
		return $seen;
	}

	return empty($user->datepreviouslogin) ? dol_now() : (int) $user->datepreviouslogin;
}

/**
 * Keep that the user looked at a badge now
 *
 * @param	DoliDB	$db		Database handler
 * @param	User	$user	User
 * @param	string	$key	Menu key of the badge
 * @return	int				Result of dol_set_user_param(): <0 if KO, >0 if OK
 */
function railMenuMarkSeen($db, $user, $key)
{
	global $conf;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

	return dol_set_user_param($db, $conf, $user, array(railMenuSeenParam($key) => dol_now()));
}

/**
 * Sql condition on the events of a user: owner of the event or among its attendees
 *
 * @param	int		$userid		Id of the user
 * @return	string				Sql condition on the alias "a" of llx_actioncomm
 */
function railMenuSqlMyEvents($userid)
{
	$sql = "(a.fk_user_action = ".((int) $userid);
	$sql .= " OR EXISTS (SELECT r.rowid FROM ".MAIN_DB_PREFIX."actioncomm_resources as r";
	$sql .= " WHERE r.fk_actioncomm = a.id AND r.element_type = 'user' AND r.fk_element = ".((int) $userid)."))";

	return $sql;
}

/**
 * Sections that have a widget at the bottom of their panel. Modules add theirs with the hook railMenuPanelWidgetSections
 * (resArray of section keys), then output it with the hook railMenuPanelWidget.
 *
 * @param	User	$user	Current user
 * @return	string[]		Main menu codes
 */
function railMenuPanelWidgetSections($user)
{
	global $hookmanager;

	$sections = array();
	if (isModEnabled('agenda') && $user->hasRight('agenda', 'myactions', 'read')) {
		$sections[] = 'agenda';
	}

	if (is_object($hookmanager)) {
		$hookmanager->initHooks(array('railmenu'));
		$parameters = array('sections' => $sections);
		$reshook = $hookmanager->executeHooks('railMenuPanelWidgetSections', $parameters);
		if ($reshook >= 0 && !empty($hookmanager->resArray)) {
			$sections = array_merge($sections, array_values($hookmanager->resArray));
		}
	}

	return array_values(array_unique($sections));
}

/**
 * Return the html of the widget at the bottom of the panel of a section (loaded in ajax when the panel opens)
 *
 * @param	DoliDB	$db			Database handler
 * @param	User	$user		Current user
 * @param	string	$section	Main menu code
 * @return	string				Html, empty if the section has no widget
 */
function railMenuRenderPanelWidget($db, $user, $section)
{
	global $hookmanager;

	$out = '';
	if ($section == 'agenda') {
		$out .= railMenuWidgetAgenda($db, $user);
	}

	if (is_object($hookmanager)) {
		$hookmanager->initHooks(array('railmenu'));
		$parameters = array('section' => $section);
		$reshook = $hookmanager->executeHooks('railMenuPanelWidget', $parameters);
		if ($reshook > 0) {
			$out = $hookmanager->resPrint;
		} elseif ($reshook == 0) {
			$out .= $hookmanager->resPrint;
		}
	}

	return $out;
}

/**
 * Widget "My agenda": events of the user that are late, then the next ones
 *
 * @param	DoliDB	$db		Database handler
 * @param	User	$user	Current user
 * @return	string			Html
 */
function railMenuWidgetAgenda($db, $user)
{
	global $langs;

	if (!isModEnabled('agenda') || !$user->hasRight('agenda', 'myactions', 'read')) {
		return '';
	}
	$langs->loadLangs(array('agenda', 'railmenu'));

	$now = dol_now();
	$sqlbase = "SELECT a.id, a.label, a.datep, a.fulldayevent, s.nom as thirdparty";
	$sqlbase .= " FROM ".MAIN_DB_PREFIX."actioncomm as a";
	$sqlbase .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = a.fk_soc";
	$sqlbase .= " WHERE a.entity IN (".getEntity('agenda').")";
	$sqlbase .= " AND ".railMenuSqlMyEvents((int) $user->id);

	// Late: to do (percent 0 to 99, -1 is "not applicable"), date passed
	$sql = $sqlbase." AND a.percent >= 0 AND a.percent < 100 AND a.datep < '".$db->idate($now)."'";
	$sql .= $db->order('a.datep', 'DESC').$db->plimit(3);
	$lateEvents = railMenuFetchRows($db, $sql);

	$sql = $sqlbase." AND a.percent <> 100 AND a.datep >= '".$db->idate($now)."'";
	$sql .= $db->order('a.datep', 'ASC').$db->plimit(5);
	$nextEvents = railMenuFetchRows($db, $sql);

	$out = '<div class="nm-widget">';
	$out .= '<h3 class="nm-widget-title">'.$langs->trans('RailMenuMyAgenda').'</h3>';
	if (!empty($lateEvents)) {
		$out .= '<p class="nm-widget-group nm-widget-late">'.$langs->trans('RailMenuLate').'</p>';
		$out .= railMenuWidgetAgendaList($lateEvents, true);
	}
	$out .= '<p class="nm-widget-group">'.$langs->trans('RailMenuUpcoming').'</p>';
	if (!empty($nextEvents)) {
		$out .= railMenuWidgetAgendaList($nextEvents, false);
	} else {
		$out .= '<p class="nm-empty">'.$langs->trans('RailMenuNothingPlanned');
		if ($user->hasRight('agenda', 'myactions', 'create')) {
			$out .= ' <a href="'.DOL_URL_ROOT.'/comm/action/card.php?action=create&mainmenu=agenda">'.$langs->trans('RailMenuCreateEvent').'</a>';
		}
		$out .= '</p>';
	}
	$out .= '<a class="nm-widget-more" href="'.DOL_URL_ROOT.'/comm/action/list.php?mainmenu=agenda&mode=show_list&search_filtert='.((int) $user->id).'">'.$langs->trans('RailMenuSeeMyAgenda').' <span class="fas fa-arrow-right" aria-hidden="true"></span></a>';
	$out .= '</div>';

	return $out;
}

/**
 * Return the list of events of the agenda widget
 *
 * @param	array<int,object>	$rows	Events (id, label, datep, fulldayevent, thirdparty)
 * @param	bool				$late	Events are late
 * @return	string						Html
 */
function railMenuWidgetAgendaList($rows, $late)
{
	global $db;

	$out = '<ul class="nm-widget-list">';
	foreach ($rows as $row) {
		$date = $db->jdate($row->datep);
		$out .= '<li><a class="nm-widget-item'.($late ? ' nm-widget-item-late' : '').'" href="'.DOL_URL_ROOT.'/comm/action/card.php?id='.((int) $row->id).'&mainmenu=agenda">';
		$out .= '<span class="nm-widget-when"><span class="nm-widget-day">'.railMenuRelativeDay($date).'</span>';
		if (empty($row->fulldayevent)) {
			$out .= '<span class="nm-widget-hour">'.dol_print_date($date, '%H:%M', 'tzuserrel').'</span>';
		}
		$out .= '</span>';
		$out .= '<span class="nm-widget-what"><span class="nm-widget-label">'.dol_escape_htmltag((string) $row->label).'</span>';
		if (!empty($row->thirdparty)) {
			$out .= '<span class="nm-widget-sub">'.dol_escape_htmltag((string) $row->thirdparty).'</span>';
		}
		$out .= '</span></a></li>';
	}
	$out .= '</ul>';

	return $out;
}

/**
 * Return a short day label: today, tomorrow, yesterday, else the day of the week and the date
 *
 * @param	int		$date	Timestamp
 * @return	string			Label
 */
function railMenuRelativeDay($date)
{
	global $langs;

	$day = dol_print_date($date, '%Y-%m-%d', 'tzuserrel');
	$now = dol_now();
	if ($day == dol_print_date($now, '%Y-%m-%d', 'tzuserrel')) {
		return $langs->trans('Today');
	}
	if ($day == dol_print_date($now + 86400, '%Y-%m-%d', 'tzuserrel')) {
		return $langs->trans('Tomorrow');
	}
	if ($day == dol_print_date($now - 86400, '%Y-%m-%d', 'tzuserrel')) {
		return $langs->trans('Yesterday');
	}

	return dol_print_date($date, '%a %d/%m', 'tzuserrel');
}

/**
 * Run a query and return its rows
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$sql	Query
 * @return	array<int,object>	Rows, empty on error
 */
function railMenuFetchRows($db, $sql)
{
	$rows = array();
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_WARNING);
		return $rows;
	}
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
	}
	$db->free($resql);

	return $rows;
}

/**
 * Run a count query
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$sql	Query returning a 'nb' column
 * @return	int				Result, 0 on error
 */
function railMenuCount($db, $sql)
{
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_WARNING);
		return 0;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);

	return $obj ? (int) $obj->nb : 0;
}

/**
 * Return the mark of a link opened in a new tab
 *
 * @param	string	$target		Target of the link
 * @return	string				Html, empty if the link opens in the page
 */
function railMenuNewTabMark($target)
{
	global $langs;

	if ($target !== '_blank') {
		return '';
	}

	return ' <span class="fas fa-external-link-alt nm-newtab" aria-hidden="true"></span><span class="nm-visually-hidden">'.$langs->trans('RailMenuNewTab').'</span>';
}

/**
 * Return the entries of a section as nested lists
 *
 * @param	array<int,array<string,mixed>>	$entries		Entries with their children
 * @param	int								$depth			Depth of the list
 * @param	string							$sectionLabel	Label of the section: a group with the same label is not repeated under the title of the panel
 * @return	string											Html
 */
function railMenuRenderEntries($entries, $depth = 0, $sectionLabel = '')
{
	global $langs;

	$out = '<ul class="nm-tree nm-tree-depth'.$depth.'">';
	foreach ($entries as $entry) {
		$hasChildren = !empty($entry['children']);
		$subId = 'nm-sub-'.$entry['id'];
		$label = dol_escape_htmltag($entry['label']);

		// "Banques | Caisses" under the title "Banques & Caisses": the group shows its entries only
		if ($depth == 0 && $hasChildren && $sectionLabel !== '' && railMenuSameLabel($entry['label'], $sectionLabel)) {
			$out .= '<li class="nm-node nm-group nm-group-untitled">';
			$out .= '<div id="'.$subId.'" class="nm-sub">'.railMenuRenderEntries($entry['children'], 1).'</div>';
			$out .= '</li>';
			continue;
		}

		$out .= '<li class="nm-node'.($depth == 0 ? ' nm-group' : '').'">';
		$out .= '<div class="nm-row">';
		if ($hasChildren && $depth > 0) {
			$out .= '<button type="button" class="nm-toggle" aria-expanded="false" aria-controls="'.$subId.'" aria-label="'.dol_escape_htmltag($langs->trans('RailMenuShowEntriesOf', $entry['label'])).'">';
			$out .= '<span class="fas fa-chevron-right" aria-hidden="true"></span></button>';
		}
		if ($entry['url'] !== '') {
			$out .= '<a class="nm-link" href="'.dol_escape_htmltag($entry['url']).'" data-entry-id="'.$entry['id'].'"';
			$out .= ($entry['menukey'] !== '' ? ' data-menu-key="'.dol_escape_htmltag($entry['menukey']).'"' : '');
			$out .= ($entry['target'] !== '' ? ' target="'.dol_escape_htmltag($entry['target']).'"' : '').'>';
			$out .= ($entry['icon'] !== '' ? '<span class="nm-link-icon">'.$entry['icon'].'</span>' : '');
			$out .= '<span class="nm-link-label">'.$label.railMenuNewTabMark($entry['target']).'</span><span class="nm-badge" hidden></span></a>';
			$out .= '<button type="button" class="nm-fav" aria-pressed="false" data-entry-id="'.$entry['id'].'" aria-label="'.dol_escape_htmltag($langs->trans('RailMenuFavorite', $entry['label'])).'">';
			$out .= '<span class="fas fa-star" aria-hidden="true"></span></button>';
		} else {
			$out .= '<span class="nm-link nm-link-nourl">'.($entry['icon'] !== '' ? '<span class="nm-link-icon">'.$entry['icon'].'</span>' : '').'<span class="nm-link-label">'.$label.'</span></span>';
		}
		$out .= '</div>';
		if ($hasChildren) {
			$out .= '<div id="'.$subId.'" class="nm-sub"'.($depth > 0 ? ' hidden' : '').'>'.railMenuRenderEntries($entry['children'], $depth + 1).'</div>';
		}
		$out .= '</li>';
	}
	$out .= '</ul>';

	return $out;
}

/**
 * Compare two labels of menu entries, without case nor separators ("Banques | Caisses" and "Banques & Caisses" are the same)
 *
 * @param	string	$label1		Label
 * @param	string	$label2		Label
 * @return	bool				True if same
 */
function railMenuSameLabel($label1, $label2)
{
	return railMenuLabelKey($label1) === railMenuLabelKey($label2);
}

/**
 * Return a label without case nor separators, to compare labels
 *
 * @param	string	$label	Label
 * @return	string			Key
 */
function railMenuLabelKey($label)
{
	return (string) preg_replace('/[\s|&\/,-]+/u', '', dol_strtolower($label));
}

/**
 * Return the html of the panel header buttons (back on small screens, pin, close)
 *
 * @return	string	Html
 */
function railMenuPanelButtons()
{
	global $langs;

	$pin = '<button type="button" class="nm-iconbtn nm-pin" aria-pressed="false" aria-label="'.dol_escape_htmltag($langs->trans('RailMenuKeepOpen')).'" title="'.dol_escape_htmltag($langs->trans('RailMenuKeepOpen')).'"><span class="fas fa-thumbtack" aria-hidden="true"></span></button>';
	$close = '<button type="button" class="nm-iconbtn nm-panel-close" aria-label="'.dol_escape_htmltag($langs->trans('RailMenuClosePanel')).'"><span class="fas fa-times" aria-hidden="true"></span></button>';

	return $pin.$close;
}

/**
 * Return the places where records can be searched, same list as the search of the core (hook addSearchEntry included)
 *
 * @return	array<int,array{label:string,url:string,icon:string}>	Search targets, sorted
 */
function railMenuSearchTargets()
{
	global $langs, $conf, $db, $user, $hookmanager;	// used by htdocs/core/ajax/selectsearchbox.php

	$usedbyinclude = 1;		// Used by selectsearchbox.php
	$arrayresult = array();
	include DOL_DOCUMENT_ROOT.'/core/ajax/selectsearchbox.php'; // This sets $arrayresult
	'@phan-var-force array<string,array{label?:string,url?:string,img?:string}> $arrayresult';

	$refPrefixes = railMenuRefPrefixes();

	$targets = array();
	foreach ($arrayresult as $key => $item) {
		if (empty($item['url'])) {
			continue;
		}
		$targets[] = array(
			'label' => railMenuCleanLabel(empty($item['label']) ? '' : $item['label']),
			'url' => $item['url'],
			'icon' => empty($item['img']) ? '' : img_picto('', $item['img'], 'class="pictofixedwidth"'),
			'prefixes' => empty($refPrefixes[$key]) ? array() : $refPrefixes[$key],
		);
	}

	return $targets;
}

/**
 * Return the letters that start the references of each kind of object, read from the numbering module in use
 * (prefix properties of the simple modules, else the example of the module). Kept in session for one hour.
 *
 * @return	array<string,string[]>	Prefixes (upper case) by key of search target
 */
function railMenuRefPrefixes()
{
	global $conf, $db;

	// Key of the search target => constant of the numbering module and directory of the numbering modules
	$numberings = array(
		'searchintopropal' => array('PROPALE_ADDON', 'propale'),
		'searchintoorder' => array('COMMANDE_ADDON', 'commande'),
		'searchintoshipment' => array('EXPEDITION_ADDON_NUMBER', 'expedition'),
		'searchintoinvoice' => array('FACTURE_ADDON', 'facture'),
		'searchintosupplierpropal' => array('SUPPLIER_PROPOSAL_ADDON', 'supplier_proposal'),
		'searchintosupplierorder' => array('COMMANDE_SUPPLIER_ADDON_NUMBER', 'supplier_order'),
		'searchintosupplierinvoice' => array('INVOICE_SUPPLIER_ADDON_NUMBER', 'supplier_invoice'),
		'searchintoreception' => array('RECEPTION_ADDON_NUMBER', 'reception'),
		'searchintocontract' => array('CONTRACT_ADDON', 'contract'),
		'searchintointervention' => array('FICHEINTER_ADDON', 'fichinter'),
		'searchintoprojects' => array('PROJECT_ADDON', 'project'),
		'searchintotickets' => array('TICKET_ADDON', 'ticket'),
		'searchintomo' => array('MRP_MO_ADDON', 'mrp'),
		'searchintoexpensereport' => array('EXPENSEREPORT_ADDON', 'expensereport'),
		'searchintoleaves' => array('HOLIDAY_ADDON', 'holiday'),
	);

	$modules = array();
	foreach ($numberings as $key => $numbering) {
		$modules[$key] = getDolGlobalString($numbering[0]);
	}
	$signature = md5($conf->entity.'|'.json_encode($modules));
	$cache = empty($_SESSION['railmenu_refprefixes']) ? array() : $_SESSION['railmenu_refprefixes'];
	if (!empty($cache['signature']) && $cache['signature'] == $signature && $cache['time'] > dol_now() - 3600) {
		return $cache['prefixes'];
	}

	$prefixes = array();
	foreach ($numberings as $key => $numbering) {
		$module = preg_replace('/\.php$/', '', $modules[$key]);
		if (empty($module) || !preg_match('/^[a-z0-9_]+$/i', $module)) {
			continue;
		}
		$found = array();
		foreach (array_merge(array('/'), (array) $conf->modules_parts['models']) as $reldir) {
			$file = dol_buildpath($reldir.'core/modules/'.$numbering[1].'/'.$module.'.php', 0);
			if (!file_exists($file)) {
				continue;
			}
			try {
				// Numbering modules may print or warn while computing their example
				ob_start();
				require_once $file;
				if (class_exists($module)) {
					$obj = new $module($db);
					foreach (get_object_vars($obj) as $property => $value) {
						if (strpos($property, 'prefix') === 0 && is_string($value) && preg_match('/^[a-z]+$/i', $value)) {
							$found[] = strtoupper($value);
						}
					}
					if (empty($found) && method_exists($obj, 'getExample')) {
						$reg = array();
						// @phan-suppress-next-line PhanPluginUnknownObjectMethodCall
						if (preg_match('/^([a-z]+)[^a-z]/i', (string) $obj->getExample(), $reg)) {
							$found[] = strtoupper($reg[1]);
						}
					}
				}
				ob_end_clean();
			} catch (Throwable $e) {
				ob_end_clean();
				dol_syslog(__FUNCTION__.' '.$module.': '.$e->getMessage(), LOG_WARNING);
			}
			break;
		}
		if (!empty($found)) {
			$prefixes[$key] = array_values(array_unique($found));
		}
	}

	$_SESSION['railmenu_refprefixes'] = array('signature' => $signature, 'time' => dol_now(), 'prefixes' => $prefixes);

	return $prefixes;
}

/**
 * Return the html of the left part of the top bar: menu button, company, search field (menu entries and records), create and notifications
 *
 * @param	array<string,array{count:int,help:string}>	$badges		Demo notifications, for their help text
 * @return	string												Html
 */
function railMenuRenderTopBar($badges)
{
	global $langs;

	$badgeHelp = array();
	foreach ($badges as $key => $badge) {
		$badgeHelp[$key] = $badge['help'];
	}

	$out = '<a class="nm-skip" href="#id-right">'.$langs->trans('RailMenuSkipToContent').'</a>';
	$out .= '<div class="nm-topbar">';

	// Same width as the rail, so the button is centered above the sections
	$out .= '<div class="nm-topbar-start"><button type="button" id="nm-burger" class="nm-iconbtn" aria-controls="nm-nav" aria-expanded="true" aria-label="'.dol_escape_htmltag($langs->trans('RailMenuMainMenu')).'">';
	$out .= '<span class="fas fa-bars" aria-hidden="true"></span></button></div>';
	$out .= '<a class="nm-brand" href="'.DOL_URL_ROOT.'/index.php?mainmenu=home"><span class="nm-brand-name">'.dol_escape_htmltag(getDolGlobalString('MAIN_INFO_SOCIETE_NOM', 'Dolibarr')).'</span></a>';
	$out .= '<a class="nm-tag" href="'.dol_buildpath('/admin/tools/ui/experimental/menu/index.php', 1).'">'.$langs->trans('RailMenuExperimental').'</a>';

	// One field for both: pages of the menu, then search of records. It keeps the search of the page (search_all), so that
	// the user sees the text searched and can refine it or search the same text elsewhere.
	$out .= '<div class="nm-find" role="search" data-search-targets="'.dol_escape_htmltag((string) json_encode(railMenuSearchTargets())).'">';
	$out .= '<label class="nm-visually-hidden" for="nm-find-input">'.$langs->trans('RailMenuGoToEntry').'</label>';
	$out .= '<span class="fas fa-search nm-find-icon" aria-hidden="true"></span>';
	$out .= '<input id="nm-find-input" type="search" role="combobox" aria-expanded="false" aria-controls="nm-find-results" aria-autocomplete="list" autocomplete="off" spellcheck="false" enterkeyhint="search" accesskey="s" value="'.dol_escape_htmltag(GETPOST('search_all', 'alphanohtml')).'" placeholder="'.dol_escape_htmltag($langs->trans('RailMenuGoTo')).'">';
	$out .= '<kbd class="nm-kbd" aria-hidden="true">/</kbd>';
	$out .= '<div id="nm-find-results" class="nm-find-results" hidden><ul role="listbox" aria-label="'.dol_escape_htmltag($langs->trans('RailMenuEntries')).'"></ul><p class="nm-find-empty" hidden></p></div>';
	$out .= '</div>';

	$out .= '<div class="nm-actions">';
	$out .= '<button type="button" class="nm-iconbtn nm-find-toggle" aria-controls="nm-find-input" aria-label="'.dol_escape_htmltag($langs->trans('RailMenuGoToEntry')).'"><span class="fas fa-search" aria-hidden="true"></span></button>';

	// Quick add list, unless the option already puts it in the top bar
	if (!getDolGlobalString('MAIN_USE_TOP_MENU_QUICKADD_DROPDOWN')) {
		$out .= '<div class="nm-pop">';
		$out .= '<button type="button" class="nm-btn-primary nm-pop-trigger" aria-expanded="false" aria-controls="nm-create-pop"><span class="fas fa-plus" aria-hidden="true"></span><span class="nm-btn-label">'.$langs->trans('RailMenuCreate').'</span></button>';
		$out .= '<div id="nm-create-pop" class="nm-popover" hidden><h2 class="nm-popover-title">'.$langs->trans('RailMenuCreate').'</h2>'.printDropdownQuickadd(1).'</div>';
		$out .= '</div>';
	}

	$out .= '<div class="nm-pop">';
	$out .= '<button type="button" class="nm-iconbtn nm-pop-trigger" aria-expanded="false" aria-controls="nm-notif-pop" aria-label="'.dol_escape_htmltag($langs->trans('RailMenuNotifications')).'"><span class="far fa-bell" aria-hidden="true"></span><span class="nm-badge nm-badge-total" hidden></span></button>';
	$out .= '<div id="nm-notif-pop" class="nm-popover" hidden data-badge-help="'.dol_escape_htmltag((string) json_encode($badgeHelp)).'">';
	$out .= '<h2 class="nm-popover-title">'.$langs->trans('RailMenuNotifications').'</h2>';
	$out .= '<ul class="nm-notif-list"></ul>';
	$out .= '<p class="nm-empty nm-notif-empty">'.$langs->trans('RailMenuNoNotification').'</p>';
	$out .= '<form class="nm-demo"><p class="nm-demo-title">'.$langs->trans('RailMenuDemoPush').'</p>';
	$out .= '<label class="nm-visually-hidden" for="nm-demo-key">'.$langs->trans('RailMenuSection').'</label><select id="nm-demo-key"></select>';
	$out .= '<button type="button" class="nm-btn" data-demo="add">+1</button><button type="button" class="nm-btn" data-demo="clear">'.$langs->trans('RailMenuDemoClear').'</button></form>';
	$out .= '</div>';
	$out .= '</div>';

	$out .= '</div>';
	$out .= '</div>';

	return $out;
}

/**
 * Return the html of the navigation: rail of sections, display options and panels
 *
 * @param	array<int,array<string,mixed>>	$sections			Sections of the menu tree
 * @param	string							$currentSection		Main menu code of the current page
 * @param	int								$buildtime			Time spent to build the tree (ms), for information
 * @return	string												Html
 */
function railMenuRenderNav($sections, $currentSection, $buildtime)
{
	global $langs, $user;

	$out = '<nav id="nm-nav" class="nm-nav" aria-label="'.dol_escape_htmltag($langs->trans('RailMenuMainMenu')).'" data-current-section="'.dol_escape_htmltag($currentSection).'" data-buildtime="'.((int) $buildtime).'">';

	// Rail of sections
	$out .= '<ul class="nm-rail">';
	$out .= '<li><button type="button" class="nm-rail-item" data-section="favorites" aria-controls="nm-panel-favorites" aria-expanded="false">';
	$out .= '<span class="nm-rail-icon"><span class="fas fa-star fa-fw" aria-hidden="true"></span></span><span class="nm-rail-label">'.$langs->trans('RailMenuFavorites').'</span></button></li>';
	foreach ($sections as $section) {
		$key = dol_escape_htmltag($section['key']);
		$content = '<span class="nm-rail-icon">'.$section['icon'].'<span class="nm-badge" hidden></span></span>';
		$content .= '<span class="nm-rail-label">'.dol_escape_htmltag($section['label']).railMenuNewTabMark($section['target']).'</span>';
		if (empty($section['entries'])) {
			// A section without sub entries is a direct link, there is no panel to open
			$out .= '<li><a class="nm-rail-item nm-rail-link" href="'.dol_escape_htmltag($section['url']).'" data-section="'.$key.'" data-menu-key="'.$key.'"';
			$out .= ($section['target'] !== '' ? ' target="'.dol_escape_htmltag($section['target']).'"' : '').'>'.$content.'</a></li>';
		} else {
			$out .= '<li><button type="button" class="nm-rail-item" data-section="'.$key.'" data-menu-key="'.$key.'" aria-controls="nm-panel-'.$key.'" aria-expanded="false">'.$content.'</button></li>';
		}
	}
	$out .= '<li class="nm-rail-bottom"><button type="button" class="nm-rail-tool nm-pop-trigger" aria-controls="nm-display-pop" aria-expanded="false" aria-label="'.dol_escape_htmltag($langs->trans('RailMenuDisplayOptions')).'" title="'.dol_escape_htmltag($langs->trans('RailMenuDisplayOptions')).'">';
	$out .= '<span class="nm-rail-icon"><span class="fas fa-sliders-h fa-fw" aria-hidden="true"></span></span><span class="nm-rail-label">'.$langs->trans('RailMenuDisplay').'</span></button></li>';
	$out .= '</ul>';

	// Display options (kept in the browser for now, user params later)
	$out .= '<div id="nm-display-pop" class="nm-popover nm-display-pop" hidden>';
	$out .= '<fieldset class="nm-display-group"><legend class="nm-popover-title">'.$langs->trans('RailMenuSectionsBar').'</legend>';
	$out .= '<label class="nm-option"><input type="radio" name="nm-rail" value="labels"> '.$langs->trans('RailMenuIconsAndLabels').'</label>';
	$out .= '<label class="nm-option"><input type="radio" name="nm-rail" value="icons"> '.$langs->trans('RailMenuIconsOnly').'</label>';
	$out .= '<label class="nm-option nm-option-hover"><input type="radio" name="nm-rail" value="hidden"> '.$langs->trans('RailMenuHiddenOnHover').'</label>';
	$out .= '</fieldset>';
	$out .= '<label class="nm-option nm-option-pin"><input type="checkbox" name="nm-pinned"> '.$langs->trans('RailMenuKeepPanelOpen').'</label>';
	$out .= '</div>';

	// Panels
	$back = '<button type="button" class="nm-iconbtn nm-back" aria-label="'.dol_escape_htmltag($langs->trans('RailMenuBackToSections')).'"><span class="fas fa-arrow-left" aria-hidden="true"></span></button>';
	$out .= '<div class="nm-panels">';
	$out .= '<section id="nm-panel-favorites" class="nm-panel" data-section="favorites" aria-label="'.dol_escape_htmltag($langs->trans('RailMenuFavorites')).'" hidden>';
	$out .= '<header class="nm-panel-head">'.$back.'<h2 class="nm-panel-title">'.$langs->trans('RailMenuFavorites').'</h2>'.railMenuPanelButtons().'</header>';
	$out .= '<div class="nm-panel-body"><ul class="nm-tree nm-fav-list"></ul><p class="nm-empty nm-fav-empty">'.$langs->trans('RailMenuNoFavorite').'</p></div>';
	$out .= '</section>';
	$widgetSections = railMenuPanelWidgetSections($user);
	foreach ($sections as $section) {
		if (empty($section['entries'])) {
			continue;
		}
		$key = dol_escape_htmltag($section['key']);
		// The panel of the current section is drawn open: shown at once when the menu is kept open (see rail_menu.css)
		$isCurrent = ($section['key'] == $currentSection);
		$out .= '<section id="nm-panel-'.$key.'" class="nm-panel'.($isCurrent ? ' nm-panel-boot' : '').'" data-section="'.$key.'" aria-label="'.dol_escape_htmltag($section['label']).'"'.($isCurrent ? '' : ' hidden').'>';
		$out .= '<header class="nm-panel-head">'.$back.'<h2 class="nm-panel-title">';
		if ($section['url'] !== '') {
			$out .= '<a class="nm-link nm-section-link" href="'.dol_escape_htmltag($section['url']).'"><span class="nm-link-label">'.dol_escape_htmltag($section['label']).'</span></a>';
		} else {
			$out .= dol_escape_htmltag($section['label']);
		}
		$out .= '</h2>'.railMenuPanelButtons().'</header>';
		$out .= '<div class="nm-panel-body">'.railMenuRenderEntries($section['entries'], 0, $section['label']);
		// Widget of the section, loaded when the panel opens
		if (in_array($section['key'], $widgetSections)) {
			$out .= '<div class="nm-panel-widget" data-widget-section="'.$key.'" aria-live="polite" hidden></div>';
		}
		$out .= '</div>';
		$out .= '</section>';
	}
	$out .= '</div>';
	$out .= '</nav>';

	$out .= '<div class="nm-backdrop" hidden></div>';

	return $out;
}

/**
 * Return the menu tree as nested lists, for the menu page of text browsers and phones (core/get_menudiv.php)
 *
 * @param	array<int,array<string,mixed>>	$sections	Sections of the menu tree
 * @return	string									Html
 */
function railMenuRenderPlainList($sections)
{
	$out = '<ul class="ulmenu ullevel0" data-inset="true">'."\n";
	foreach ($sections as $section) {
		$out .= '<li class="lilevel0">';
		if (empty($section['entries'])) {
			$out .= '<a class="alilevel0" href="'.dol_escape_htmltag($section['url']).'">'.dol_escape_htmltag($section['label']).'</a>';
		} else {
			$out .= '<a class="alilevel0" href="#">'.dol_escape_htmltag($section['label']).'</a>';
			$out .= railMenuRenderPlainEntries($section['entries'], 1);
		}
		$out .= '</li>'."\n";
	}
	$out .= '</ul>'."\n";

	return $out;
}

/**
 * Return entries as nested lists, without decoration
 *
 * @param	array<int,array<string,mixed>>	$entries	Entries with their children
 * @param	int								$level		Level of the list
 * @return	string									Html
 */
function railMenuRenderPlainEntries($entries, $level)
{
	$out = '<ul class="ullevel'.$level.'">';
	foreach ($entries as $entry) {
		$out .= '<li class="lilevel'.$level.'">';
		if ($entry['url'] !== '') {
			$out .= '<a href="'.dol_escape_htmltag($entry['url']).'">'.dol_escape_htmltag($entry['label']).'</a>';
		} else {
			$out .= '<span>'.dol_escape_htmltag($entry['label']).'</span>';
		}
		if (!empty($entry['children'])) {
			$out .= railMenuRenderPlainEntries($entry['children'], $level + 1);
		}
		$out .= '</li>';
	}
	$out .= '</ul>';

	return $out;
}
