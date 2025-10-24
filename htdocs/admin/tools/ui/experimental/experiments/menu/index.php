<?php
/*
 * Copyright (C) 2024 Anthony Damhet <a.damhet@progiseize.fr>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
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

// Load Dolibarr environment
require '../../../../../../main.inc.php';

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

// Load documentation translations
$langs->load('uxdocumentation');

//
$documentation = new Documentation($db);
$group = 'ExperimentalUx';
$experimentName = 'ExperimentalUxMenu';

$js = [];
$css = [];

// Output html head + body - Param is Title
$documentation->docHeader($langs->trans($experimentName, $group), $js, $css);

// Set view for menu and breadcrumb
$documentation->view = [$group, $experimentName];

// Output sidebar
$documentation->showSidebar(); ?>

<div class="doc-wrapper">

	<?php $documentation->showBreadCrumb(); ?>

	<div class="doc-content-wrapper">

		<h1 class="documentation-title"><?php echo $langs->trans($experimentName); ?></h1>

		<?php $documentation->showSummary(); ?>

		<div class="documentation-section" >
			<h2 class="documentation-title" >New menu (Experimental)</h2>

			<p>
				This document presents a series of user experience (UX) experiments conducted on the Dolibarr ERP & CRM menu system.
				The objective of these experiments is to improve navigation efficiency, clarity, and overall usability for end-users.
			</p>

			<h3>Methodology</h3>
			<p>
				Our approach included user testing, heuristic evaluation, and prototype iterations. Users were asked to complete
				common tasks, and their interactions with the menu were observed to identify pain points and opportunities for improvement.
			</p>

			<h3>Key Findings</h3>
			<ul>
				<li>Complex menus caused delays in locating frequently used functions.</li>
				<li>Grouping related actions improved task completion speed.</li>
				<li>Consistent labeling and iconography reduced cognitive load.</li>
				<li>Dynamic menus with contextual highlights increased discoverability.</li>
			</ul>
			<h3>Live Demo</h3>
			<ul>
				<li><a target="_blank" href="<?php print dol_buildpath($documentation->baseUrl.'/experimental/experiments/menu/test-01.php', 1); ?>" >Test 01 <span class="badge badge-pill badge-warning">Work in progress</span> <small>(Suggested by John BOTELLA)</small></a></li>
			</ul>

		</div>


	</div>

</div>
<?php
// Output close body + html
$documentation->docFooter();
?>
