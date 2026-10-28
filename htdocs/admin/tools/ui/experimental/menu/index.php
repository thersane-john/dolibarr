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
 * \file       htdocs/admin/tools/ui/experimental/menu/index.php
 * \brief      Documentation of the new menu experiment
 */

// Load Dolibarr environment
require '../../../../../main.inc.php';

/**
 * @var DoliDB      $db
 * @var HookManager $hookmanager
 * @var Translate   $langs
 * @var User        $user
 */

// Protection if external user
if ($user->socid > 0) {
	accessforbidden();
}

// Includes
require_once DOL_DOCUMENT_ROOT . '/admin/tools/ui/class/documentation.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/menus/standard/rail.lib.php';

// Load documentation translations
$langs->loadLangs(array('uxdocumentation', 'railmenu'));

//
$documentation = new Documentation($db);
$group = 'ExperimentalUx';

$morejs = [
	'/includes/ace/src/ace.js',
	'/includes/ace/src/ext-statusbar.js',
	'/includes/ace/src/ext-language_tools.js',
];
// Output html head + body - Param is Title
$documentation->docHeader($langs->transnoentitiesnoconv('UxMenuNewMenu', $group), $morejs);

// Set view for menu and breadcrumb
$documentation->view = [$group, 'UxMenuNewMenu'];

// Measure the cost of the full menu tree on this instance
$tree = railMenuBuildTree($db, $user);
$nbEntries = 0;
array_walk_recursive($tree['sections'], function ($value, $key) use (&$nbEntries) {
	if ($key === 'id') {
		$nbEntries++;
	}
});

$previewUrl = DOL_URL_ROOT.'/index.php?mainmenu=home&menu=rail_menu.php';
$featuresLevel = getDolGlobalInt('MAIN_FEATURES_LEVEL');
$currentMenu = getDolGlobalString('MAIN_MENU_STANDARD_FORCED', getDolGlobalString('MAIN_MENU_STANDARD', 'eldy_menu.php'));

// Output sidebar
$documentation->showSidebar(); ?>

<div class="doc-wrapper">

	<?php $documentation->showBreadCrumb(); ?>

	<div class="doc-content-wrapper">

		<h1 class="documentation-title"><?php echo $langs->trans('UxMenuNewMenu'); ?></h1>
		<p class="documentation-text">A proposal for a new menu handler: responsive, with quick access and room for notification badges, built next to eldy without changing it.</p>

		<?php $documentation->showSummary(); ?>

		<div class="documentation-section" id="newmenu-introduction">
			<h2 class="documentation-title">Introduction</h2>
			<p class="documentation-text">
				The goal is a menu that works the same way on a large screen, a tablet and a phone, that gives fast access to what users open every day,
				and that can tell them where something needs their attention.
			</p>
			<p class="documentation-text">
				It is a menu manager, named <b>Rail</b>, next to eldy: <code>core/menus/standard/rail_menu.php</code>. It shows the same menu entries as eldy
				(core and modules, with the permissions of the current user). Eldy is not changed.
			</p>
			<div class="documentation-example">
				<a class="button" href="<?php echo $previewUrl; ?>"><span class="fas fa-eye paddingright"></span>Preview the Rail menu</a>
				<span class="opacitymedium paddingleft">Suggested by John BOTELLA</span>
			</div>
		</div>

		<div class="documentation-section" id="newmenu-enable">
			<h2 class="documentation-title"><?php echo $langs->trans('UxNewMenuEnable'); ?></h2>
			<p class="documentation-text">
				<b>Preview</b>: the button above opens Dolibarr with the parameter <code>menu=rail_menu.php</code>. Only you see the menu, the parameter is kept on the links
				of the pages (not after a form is posted). Nothing is changed in the setup.
			</p>
			<p class="documentation-text"><b>For everyone</b>: the menu manager is listed in <i>Setup &gt; Menus</i> only with the experimental features.</p>
			<table class="noborder centpercent">
				<tr class="liste_titre"><th>Setting</th><th>Value needed</th><th>On this instance</th></tr>
				<tr class="oddeven"><td><code>MAIN_FEATURES_LEVEL</code> (<i>Setup &gt; Other setup</i>)</td><td>2</td><td><?php echo $featuresLevel >= 2 ? img_picto('', 'tick').' ' : img_picto('', 'warning').' '; echo $featuresLevel; ?></td></tr>
				<tr class="oddeven"><td>Menu manager for internal users (<a href="<?php echo DOL_URL_ROOT.'/admin/menus.php'; ?>"><i>Setup &gt; Menus</i></a>)</td><td>Rail</td><td><?php echo $currentMenu == 'rail_menu.php' ? img_picto('', 'tick').' ' : ''; echo dol_escape_htmltag($currentMenu); ?></td></tr>
			</table>
		</div>

		<div class="documentation-section" id="newmenu-audit">
			<h2 class="documentation-title"><?php echo $langs->trans('UxNewMenuAudit'); ?></h2>
			<p class="documentation-text">Findings of a UX audit of the eldy menu handler, on the develop branch.</p>
			<ul>
				<li><b>Responsive steps depend on the number of top menu entries</b>, not on the screen: with 12 entries, labels are cut below 1420px and the left menu disappears below 694px. Each enabled module moves these steps.</li>
				<li><b>Opening another section or a sub level reloads the page</b>: the level 2 entries are shown only when the <code>leftmenu</code> parameter matches.</li>
				<li><b>Quick access is scattered</b>: quick add and the top search are options, off by default; the default search is inside the left menu, so it is hidden with it on small screens. No favorites in the menu.</li>
				<li><b>No room for a badge</b>: a menu entry has no field for it, and the left entries have no key in the html.</li>
				<li><b>Accessibility</b>: the hamburger is a link without accessible name and without <code>aria-expanded</code>; the left menu is made of <code>div</code> and <code>br</code>, without list, <code>nav</code> nor <code>aria-current</code>; the current page is not highlighted in the left menu; left entries are 17px high.</li>
				<li>Entries not allowed are shown greyed by default, the reason is only in a tooltip.</li>
			</ul>
		</div>

		<div class="documentation-section" id="newmenu-decisions">
			<h2 class="documentation-title"><?php echo $langs->trans('UxNewMenuPrinciples'); ?></h2>
			<ul>
				<li><b>A new menu handler</b> (Rail), next to eldy and auguria in <code>core/menus/standard/</code>, selectable in <i>Setup &gt; Menus</i>, behind <code>MAIN_FEATURES_LEVEL</code> 2 while experimental. Eldy is not changed. The right part of the top bar (bookmarks, tools of modules like QuickMemo, hook <code>printTopRightMenu</code>, user menu) stays the one of the core.</li>
				<li><b>Same data</b>: entries declared by modules (<code>$this-&gt;menu</code>, table <code>llx_menu</code>) and core entries of <code>eldy.lib.php</code>. No change for modules.</li>
				<li><b>Layout by screen width</b>, not by number of entries.</li>
				<li><b>Sub menus open on click or keyboard</b>, never on hover only.</li>
				<li><b>A section without sub entries is a direct link</b> (for example a module that only opens an external tool): no empty panel, the page opens at once. When the current page belongs to such a section, the panel column goes away. A link declared with <code>target="_blank"</code> shows a new tab mark.</li>
				<li><b>Quick access reuses what exists</b>: the quick add list (hook <code>menuDropdownQuickaddItems</code>), bookmarks, the search.</li>
				<li><b>Badges are notifications the user subscribed to</b>, not counters of things to do: a badge per draft would saturate the menu. The menu only provides the place and the way to update them; producing notifications is another development.</li>
			</ul>
		</div>

		<div class="documentation-section" id="newmenu-layout">
			<h2 class="documentation-title"><?php echo $langs->trans('UxNewMenuLayouts'); ?></h2>
			<table class="noborder centpercent">
				<tr class="liste_titre"><th>Screen width</th><th>Menu</th><th>Quick access</th></tr>
				<tr class="oddeven"><td>1024px and more</td><td>Two modes, switched with the hamburger or the pin of the panel. <b>Kept open</b>: rail of sections + panel of the current section. <b>Collapsed</b>: rail only, hovering a section opens its panel over the content (a click or the keyboard too).</td><td>Top bar: Go to, Create, notifications</td></tr>
				<tr class="oddeven"><td>768px and more</td><td>The <b>Display</b> button at the bottom of the rail sets the sections bar: <b>icons and labels</b>, <b>icons only</b> (labels in tooltips), or <b>hidden, shown on hover</b> (a thin strip at the left edge; only with a mouse, and the panel then always opens over the content).</td><td></td></tr>
				<tr class="oddeven"><td>768px to 1023px</td><td>Rail of sections, the panel opens over the content on click</td><td>Top bar, icons only</td></tr>
				<tr class="oddeven"><td>Less than 768px</td><td>Drawer: list of sections, then the panel of the chosen section with a back button</td><td>Top bar, icons only: menu, Go to, Create, notifications, user. No bottom bar: on cards it would be mistaken for the action buttons.</td></tr>
			</table>
		</div>

		<div class="documentation-section" id="newmenu-quickaccess">
			<h2 class="documentation-title"><?php echo $langs->trans('UxNewMenuQuickAccess'); ?></h2>
			<ul>
				<li><b>One search field</b> for pages and records: it lists the menu entries the user can open (by label and by section), then "Search ...
					in" each place of the search of the core (same list, hook <code>addSearchEntry</code> included).
					When the text has a digit, like a reference, or matches no page, the search of records comes first.
					When the text starts like a reference of the numbering module in use (for example <code>PO2610-0001</code> with the prefix <code>PO</code> of supplier orders), that place comes first.
					Prefixes are read from the numbering modules (prefix properties, else their example) and kept in session for one hour.
					Other words for the pages come from the translated labels of the search of the core: their words are given to the pages of the same directory (in French, "devis" from "Propositions/devis" finds the pages of the commercial proposals), so there is no dictionary of synonyms to maintain.
					Keyboard: <kbd>/</kbd> or the access key <kbd>s</kbd> to focus, arrows, <kbd>Enter</kbd>.</li>
				<li><b>Favorites</b>: a star next to each entry pins it in the Favorites section of the rail. The star shows on hover or focus of the entry, and stays visible but smaller once the entry is a favorite (touch screens have no hover: stars stay visible but subtle). Favorites are kept in the browser for now; they should become user parameters.</li>
				<li><b>User menu</b>: the user dropdown of the current top menu (<code>top_menu_user()</code>) is reused as is: company information, more information, hook <code>printTopRightMenuLoginDropdownBody</code>, user card, virtual card and logout.</li>
				<li><b>Create</b>: the existing quick add list, with its hook.</li>
			</ul>
		</div>

		<div class="documentation-section" id="newmenu-badges">
			<h2 class="documentation-title"><?php echo $langs->trans('UxNewMenuBadges'); ?></h2>
			<p class="documentation-text">
				A badge is a number stored in the Dolibarr JS context. Each menu entry carries its key, the menu listens to the <code>setContextVar</code> hook
				and refreshes the badges when a value changes. Any code can update a badge without reloading the page.
			</p>
			<table class="noborder centpercent">
				<tr class="liste_titre"><th>Context var</th><th>Badge shown on</th></tr>
				<tr class="oddeven"><td><code>MENU_BADGE__&lt;mainmenu&gt;</code></td><td>The section, for example <code>MENU_BADGE__ticket</code></td></tr>
				<tr class="oddeven"><td><code>MENU_BADGE__&lt;mainmenu&gt;__&lt;leftmenu&gt;</code></td><td>The first entry of the section with this <code>leftmenu</code>; its value is also added to the section</td></tr>
			</table>
			<p class="documentation-text">The total of a section goes up to the notifications button, which lists the sections with something new.</p>
			<p class="documentation-text">
				<b>A badge goes away once seen.</b> Badges announce what is new, not what is to do (a badge for each draft would never go away, so it would be ignored).
				The badge of a section is seen when its panel is opened on purpose (click, keyboard, panel kept open on a page of the section), or by hover when the panel stays open at least one second.
				The badge of an entry is seen when its page is opened. The badge goes away at once, then the date is kept in the user parameter
				<code>MAIN_MENU_RAIL_SEEN_&lt;KEY&gt;</code> (ajax <code>core/ajax/railmenu.php</code>, action <code>markseen</code>), so that it follows the user on every device.
				Producers count what is new since this date (<code>railMenuSeenDate()</code>), the previous login if never seen.
			</p>

			<?php
			$lines = array(
				'<script>',
				'	// A value must be defined with overwrite = true to be updated later',
				'	Dolibarr.setContextVar(\'MENU_BADGE__ticket\', 3, true);',
				'',
				'	// Later, for example after the user read a ticket',
				'	Dolibarr.setContextVar(\'MENU_BADGE__ticket\', 2, true);',
				'</script>',
			);
			$documentation->showCode($lines, 'php');
			?>

			<p class="documentation-text">
				The menu manager has two demo producers, computed on page load: tickets assigned to the current user and not read yet (section <code>ticket</code>),
				and events of today of the current user not done yet (section <code>agenda</code>). The notifications panel also has a demo form to push values.
			</p>
			<p class="documentation-text">
				For the menu handler, server side values would come from a hook that lets modules add context vars, and would be cached, so that counting
				does not slow down each page.
			</p>
		</div>

		<div class="documentation-section" id="newmenu-widgets">
			<h2 class="documentation-title"><?php echo $langs->trans('UxNewMenuWidgets'); ?></h2>
			<p class="documentation-text">
				The bottom of a panel can show a widget about the section, loaded in ajax the first time the panel opens (no query on the other pages).
				The core gives <b>My agenda</b>: the late events of the user (to do, date passed), then his next ones, with a link to his agenda.
				The badge says there is something new, the widget shows what is waiting.
			</p>
			<p class="documentation-text">Modules add a widget with two hooks of the context <code>railmenu</code>:</p>
			<ul>
				<li><code>railMenuPanelWidgetSections</code>: put the main menu codes of the sections that have a widget in <code>resArray</code>.</li>
				<li><code>railMenuPanelWidget</code>: parameter <code>section</code>, put the html in <code>resPrint</code> (return 1 to replace the widget of the core).</li>
			</ul>
		</div>

		<div class="documentation-section" id="newmenu-accessibility">
			<h2 class="documentation-title"><?php echo $langs->trans('UxNewMenuAccessibility'); ?></h2>
			<table class="noborder centpercent">
				<tr class="liste_titre"><th>Key</th><th>Action</th></tr>
				<tr class="oddeven"><td><kbd>/</kbd></td><td>Focus the search field</td></tr>
				<tr class="oddeven"><td><kbd>Escape</kbd> twice in a field of the page</td><td>Leave the field (many pages put the focus in a field), then <kbd>/</kbd> opens the search. The first <kbd>Escape</kbd> stays for the widgets of the page.</td></tr>
				<tr class="oddeven"><td><kbd>Up</kbd> <kbd>Down</kbd> <kbd>Home</kbd> <kbd>End</kbd></td><td>Move between the sections of the rail</td></tr>
				<tr class="oddeven"><td><kbd>Enter</kbd> on a section</td><td>Open its panel and move the focus into it</td></tr>
				<tr class="oddeven"><td><kbd>Escape</kbd></td><td>Close the results, the popover, the panel or the drawer, and give the focus back to what opened it</td></tr>
			</table>
			<ul>
				<li><code>nav</code> landmarks, nested lists, <code>aria-expanded</code> on every button that opens something, <code>aria-current</code> on the current page and section, a skip link.</li>
				<li>Targets of at least 44px on touch screens, visible focus everywhere, no hover only interaction, <code>prefers-reduced-motion</code> respected.</li>
				<li>Colors come from the theme variables, so the menu follows the theme set up and the dark mode.</li>
				<li>Visual choices (tokens at the top of <code>rail_menu.css</code>): font of the theme, scale 1.2; accent = color of the theme menu, rail a bit darker,
					light neutral panel, hover and selection = light tints of the accent (<code>color-mix()</code>), red only for what is late;
					base 4px, rows of 32px; radius 8px and 12px, soft shadows only for what is over the page;
					signature: a light pill around the icon of the active section, relative dates in the widgets.</li>
			</ul>
		</div>

		<div class="documentation-section" id="newmenu-build">
			<h2 class="documentation-title"><?php echo $langs->trans('UxNewMenuBuild'); ?></h2>
			<p class="documentation-text">
				Most core entries are written in PHP in <code>core/menus/standard/eldy.lib.php</code>, not in the database. The menu manager reads them as the
				<code>jmobile</code> mode of eldy does: <code>print_eldy_menu()</code> for the sections, then <code>print_left_eldy_menu()</code> with
				<code>$noout = 1</code> for each section. Entries the user is not allowed to open are not shown.
			</p>
			<div class="documentation-example">
				On this instance: <b><?php echo count($tree['sections']); ?></b> sections, <b><?php echo $nbEntries; ?></b> entries, built in <b><?php echo $tree['buildtime']; ?> ms</b>.
			</div>
			<p class="documentation-text">
				The menu handler could output the current section with the page and load the other sections on first opening, if the full tree costs too much on large instances.
			</p>
		</div>

		<div class="documentation-section" id="newmenu-limits">
			<h2 class="documentation-title"><?php echo $langs->trans('UxNewMenuLimits'); ?></h2>
			<ul>
				<li>The search of records of the left column moves to the top bar (magnifier). The version block and the output of the hook <code>printLeftBlock</code> are not shown yet.</li>
				<li>The whole menu tree is built on each page (time above); it could be loaded on demand later.</li>
				<li>Favorites, the pinned state and the display of the sections bar are kept in the browser only.</li>
				<li>Tested with the eldy theme only.</li>
				<li>Badges of entries need a <code>leftmenu</code> key; entries without it can only have a badge on their section.</li>
			</ul>
		</div>

		<div class="documentation-section" id="newmenu-questions">
			<h2 class="documentation-title"><?php echo $langs->trans('UxNewMenuQuestions'); ?></h2>
			<ul>
				<li>Name of the menu handler and how users switch to it.</li>
				<li>Where favorites are stored: user parameters, or the existing bookmarks.</li>
				<li>Which hook modules use to provide badge values, and how they are cached.</li>
				<li>Should the menu handler replace the dedicated menu page for text browsers (<code>core/get_menudiv.php</code>)?</li>
			</ul>
		</div>

	</div>

</div>
<?php
// Output close body + html
$documentation->docFooter();
