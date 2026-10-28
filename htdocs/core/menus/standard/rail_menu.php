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
 *	\file       htdocs/core/menus/standard/rail_menu.php
 *	\brief      Menu rail manager (experimental): rail of sections, panels, quick find, favorites and badges.
 *				Same menu entries as eldy, see htdocs/admin/tools/ui/experimental/menu/index.php
 */


/**
 *	Class to manage the rail menu
 *
 *	@phan-suppress PhanRedefineClass
 */
class MenuManager
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var int<0,1>	0 for internal users, 1 for external users
	 */
	public $type_user = 0;

	/**
	 * @var string 		To save the default target to use onto links
	 */
	public $atarget = "";

	/**
	 * @var string 		Menu name
	 */
	public $name = "rail";

	/**
	 * @var Menu
	 */
	public $menu;

	/**
	 * @var array<array{rowid:string,fk_menu:string,langs:string,enabled:int<0,2>,type:string,fk_mainmenu:string,fk_leftmenu:string,url:string,titre:string,perms:string,target:string,mainmenu:string,leftmenu:string,position:int,positionfull:int|string,showtopmenuinframe:int,level:int,prefix:string}>
	 */
	public $menu_array;
	/**
	 * @var array<array{rowid:string,fk_menu:string,langs:string,enabled:int<0,2>,type:string,fk_mainmenu:string,fk_leftmenu:string,url:string,titre:string,perms:string,target:string,mainmenu:string,leftmenu:string,position:int,positionfull:int|string,showtopmenuinframe:int,level:int,prefix:string}>
	 */
	public $menu_array_after;

	/**
	 * @var array<array{rowid:string,fk_menu:string,langs:string,enabled:int<0,2>,type:string,fk_mainmenu:string,fk_leftmenu:string,url:string,titre:string,perms:string,target:string,mainmenu:string,leftmenu:string,position:int,positionfull:int|string,showtopmenuinframe:int,level:int,prefix:string}>
	 */
	public $tabMenu;

	/**
	 * @var string		Main menu code of the current page
	 */
	public $mainmenu = '';

	/**
	 * @var ?array{sections:array<int,array<string,mixed>>,buildtime:int}	Menu tree, built once per page
	 */
	private $tree = null;

	/**
	 * @var ?array<string,array{count:int,help:string}>	Demo notifications, computed once per page
	 */
	private $badges = null;


	/**
	 *  Constructor
	 *
	 *  @param	DoliDB		$db     	Database handler
	 *  @param	int<0,1>	$type_user	Type of user
	 */
	public function __construct($db, $type_user)
	{
		$this->type_user = $type_user;
		$this->db = $db;
	}


	/**
	 * Read the current main and left menus, and keep them in session like eldy does.
	 * The entries themselves are read when the menu is shown.
	 *
	 * @param	string	$forcemainmenu		To force mainmenu to load
	 * @param	string	$forceleftmenu		To force leftmenu to load
	 * @return	void
	 */
	public function loadMenu($forcemainmenu = '', $forceleftmenu = '')
	{
		if (GETPOSTISSET("mainmenu")) {
			$mainmenu = GETPOST("mainmenu", 'aZ09');
			$_SESSION["mainmenu"] = $mainmenu;
			$_SESSION["leftmenuopened"] = "";
		} else {
			$mainmenu = isset($_SESSION["mainmenu"]) ? $_SESSION["mainmenu"] : '';
		}
		if (!empty($forcemainmenu)) {
			$mainmenu = $forcemainmenu;
		}

		if (GETPOSTISSET("leftmenu")) {
			$_SESSION["leftmenu"] = GETPOST("leftmenu", 'aZ09');
		}
		if (GETPOSTISSET("idmenu")) {
			$_SESSION["idmenu"] = GETPOSTINT("idmenu");
		}

		$this->mainmenu = (empty($mainmenu) ? 'home' : $mainmenu);
	}


	/**
	 *  Output menu on screen.
	 *
	 *	@param	'top'|'topnb'|'left'|'leftdropdown'|'jmobile'	$mode		'top', 'topnb', 'left', 'leftdropdown', 'jmobile' (used to get full xml ul/li menu)
	 *  @param	?array<string,string>	$moredata	An array with more data to output
	 *  @return int<0,max>				0 or nb of top menu entries if $mode = 'topnb'
	 */
	public function showmenu($mode, $moredata = null)
	{
		global $conf, $langs;

		require_once DOL_DOCUMENT_ROOT.'/core/menus/standard/rail.lib.php';

		$langs->load('railmenu');

		if ($this->type_user == 1) {
			$conf->global->MAIN_SEARCHFORM_SOCIETE_DISABLED = 1;
			$conf->global->MAIN_SEARCHFORM_CONTACT_DISABLED = 1;
		}

		$tree = $this->getTree();
		$assetsUrl = DOL_URL_ROOT.'/core/menus/standard/rail/';

		if ($mode == 'topnb') {
			return count($tree['sections']);
		}

		if ($mode == 'top') {
			print '<link rel="stylesheet" type="text/css" href="'.$assetsUrl.'rail_menu.css'.$this->assetVersion('rail_menu.css').'">'."\n";
			// Sets the display chosen by the user on the body before the menu is drawn (and the class the styles depend on)
			print '<script nonce="'.getNonce().'" src="'.$assetsUrl.'rail_menu_boot.js'.$this->assetVersion('rail_menu_boot.js').'"></script>'."\n";
			print railMenuRenderTopBar($this->getBadges());
			return 0;
		}

		if ($mode == 'left' || $mode == 'leftdropdown') {
			print railMenuRenderNav($tree['sections'], $this->mainmenu, $tree['buildtime']);

			// Badges are values of the JS context, the menu listens to their changes
			print '<script nonce="'.getNonce().'">'."\n";
			foreach ($this->getBadges() as $key => $badge) {
				print 'Dolibarr.setContextVar('.json_encode('MENU_BADGE__'.$key).', '.((int) $badge['count']).', true);'."\n";
			}
			// Preview of this menu without changing the setup (url parameter menu=rail_menu.php): the menu keeps it on the links
			if (GETPOST('menu', 'alpha') == 'rail_menu.php' && $conf->standard_menu != 'rail_menu.php') {
				print 'Dolibarr.setContextVar("MENU_RAIL_PREVIEW", "rail_menu.php");'."\n";
			}
			print '</script>'."\n";
			print '<script nonce="'.getNonce().'" src="'.$assetsUrl.'rail_menu.js'.$this->assetVersion('rail_menu.js').'"></script>'."\n";
			return 0;
		}

		if ($mode == 'jmobile') {
			print '<!-- Generate menu list from menu handler '.$this->name.' -->'."\n";
			print railMenuRenderPlainList($tree['sections']);
		}

		return 0;
	}


	/**
	 * Return the menu tree, built once per page
	 *
	 * @return	array{sections:array<int,array<string,mixed>>,buildtime:int}
	 */
	private function getTree()
	{
		global $user;

		if ($this->tree === null) {
			$this->tree = railMenuBuildTree($this->db, $user);
		}

		return $this->tree;
	}


	/**
	 * Return the demo notifications, computed once per page
	 *
	 * @return	array<string,array{count:int,help:string}>
	 */
	private function getBadges()
	{
		global $user;

		if ($this->badges === null) {
			$this->badges = railMenuGetDemoBadges($this->db, $user);
		}

		return $this->badges;
	}


	/**
	 * Return the version parameter of an asset url, so that browsers reload it when it changes
	 *
	 * @param	string	$file	File name in the assets directory
	 * @return	string			Query string
	 */
	private function assetVersion($file)
	{
		$mtime = @filemtime(DOL_DOCUMENT_ROOT.'/core/menus/standard/rail/'.$file);

		return '?v='.urlencode(DOL_VERSION.($mtime ? '-'.$mtime : ''));
	}
}
