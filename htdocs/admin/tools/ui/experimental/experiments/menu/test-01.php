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
$experimentName = 'ExperimentalUxInputAjaxFeedback';

$experimentAssetsPath = $documentation->baseUrl . '/experimental/experiments/menu/assets/';
$js = [
	$experimentAssetsPath . 'test-01.js',
];

$ext = '';
if (GETPOSTINT('version')) {
	$ext = 'version='.GETPOSTINT('version'); // useful to force no cache on css/js
}
$fontawesome_directory = getDolGlobalString('MAIN_FONTAWESOME_DIRECTORY', '/theme/common/fontawesome-5');
$css = [
	$experimentAssetsPath . 'test-01.css',
	DOL_URL_ROOT.$fontawesome_directory.'/css/all.min.css'.($ext ? '?'.$ext : '')
];


if (!defined('DISABLE_FONT_AWSOME')) {
	print '<!-- Includes CSS for font awesome -->'."\n";
	print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.$fontawesome_directory.'/css/all.min.css'.($ext ? '?'.$ext : '').'">'."\n";
}

// Output html head + body - Param is Title
$documentation->docHeader($langs->trans($experimentName, $group), $js, $css, true);

$randomSubChildMenu = [
	[
		'label' => 'New item',
		'icon' => 'fa fa-plus',
		'type' => 'embed'
	],
	[
		'label' => 'List',
		'type' => 'embed'
	],
	[
		'label' => 'Stats',
		'type' => 'embed'
	],
] ;

$randomSubMenu = [
	[
		'label' => 'Parent item 01',
		'icon' => 'fas fa-file-signature',
		'children' => $randomSubChildMenu
	],
	[
		'label' => 'Parent item 02',
		'icon' => 'fas fa-file-signature',
		'children' => $randomSubChildMenu
	],
	[
		'label' => 'Parent item 03',
		'icon' => 'fas fa-file-signature',
		'children' => $randomSubChildMenu
	],
	[
		'label' => 'Parent item 04',
		'icon' => 'fas fa-file-signature',
		'children' => $randomSubChildMenu
	],
];


$quickTestLeftMenu = [
	[
		'label' => 'Home',
		'icon' => 'fas fa-home',
		'children' => $randomSubMenu
	],
	[
		'label' => 'Tiers',
		'icon' => 'fas fa-building',
		'children' => $randomSubMenu
	],
	[
		'label' => 'Project',
		'icon' => 'fas fa-project-diagram',
		'children' => $randomSubMenu
	],
	[
		'label' => 'Sell',
		'icon' => 'fas fa-suitcase',
		'children' => $randomSubMenu
	],
	[
		'label' => 'Buy',
		'icon' => 'fas fa-suitcase',
		'children' => $randomSubMenu
	]
];
$quickTestLeftMenu = array_merge($quickTestLeftMenu, $quickTestLeftMenu, $quickTestLeftMenu, $quickTestLeftMenu);
// phpcs:disable
/**
 * @param $quickTestLeftMenu
 *
 * @return string
 */
function demoGenerateMenu($quickTestLeftMenu)
{

	$out = '<ul  >';
	foreach ($quickTestLeftMenu as $menuBaseItem) {
		$out.= '<li class="left-menu__item">';

		$out.= '<a class="left-menu__item_label" href="#">';
		$out.= '<span class="left-menu__item_label_icon"><span class="'.$menuBaseItem['icon'].'"></span></span>';
		$out.= '<span class="left-menu__item_label_text"">'.$menuBaseItem['label'].'</span>';
		$out.= '</a>';

		if (!empty($menuBaseItem['children'])) {
			$out.= '<ul class="left-sub-menu-parent" >';

			$out.= '<li class="left-sub-menu__item-title">';
			$out.= '<span class="left-menu__item-title_label_icon"><span class="'.$menuBaseItem['icon'].'"></span></span>';
			$out.= '<span class="left-menu__item-title_label_text"">'.$menuBaseItem['label'].'</span>';
			$out.= '</li>';

			foreach ($menuBaseItem['children'] as $subMenuItem) {
				$out.= '<li class="left-sub-menu__item">';

				$out.= '<a class="left-sub-menu__item_label" href="#">';
				$out.= '<span class="left-sub-menu__item_label_icon"><span class="'.$subMenuItem['icon'].'"></span></span>';
				$out.= '<span class="left-sub-menu__item_label_text">'.$subMenuItem['label'].'</span>';
				$out.= '</a>';

				if (!empty($subMenuItem['children'])) {
					$out.= '<ul class="left-sub-menu-child" >';
					foreach ($subMenuItem['children'] as $subMenuChildItem) {
						$out.= '<li class="left-sub-menu-child__item">';

						$out.= '<a class="left-sub-menu-child__item_label" href="#">';
						if (!empty($subMenuChildItem['icon'])) {
							$out.= '<span class="left-sub-menu-child__item_label_icon"><span class="'.$subMenuChildItem['icon'].'"></span></span>';
						}

						$out.= '<span class="left-sub-menu-child__item_label_text">'.$subMenuChildItem['label'].'</span>';
						$out.= '</a>';
						$out.= '</li>';
					}
					$out.= '</ul>';
				}
				$out.= '</li>';
			}
			$out.= '</ul>';
		}
		$out.= '</li>';
	}
	$out.= '</ul>';

	return $out;
}
// phpcs:enable

?>

	<!-- Top Menu -->
	<header id="top-menu">
		<div class="top-menu-left">
			<button id="burger-btn" aria-label="Toggle menu">
				<svg class="burger-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" stroke="#000" stroke-width=".6" fill="rgba(0,0,0,0)" stroke-linecap="round" style="cursor: pointer">
					<path d="M2,3L5,3L8,3M2,5L8,5M2,7L5,7L8,7">
						<animate dur="0.2s" attributeName="d" values="M2,3L5,3L8,3M2,5L8,5M2,7L5,7L8,7;M3,3L5,5L7,3M5,5L5,5M3,7L5,5L7,7" fill="freeze" begin="start.begin" />
						<animate dur="0.2s" attributeName="d" values="M3,3L5,5L7,3M5,5L5,5M3,7L5,5L7,7;M2,3L5,3L8,3M2,5L8,5M2,7L5,7L8,7" fill="freeze" begin="reverse.begin" />
					</path>
					<rect width="10" height="10" stroke="none">
						<animate dur="2s" id="reverse" attributeName="width" begin="click" />
					</rect>
					<rect width="10" height="10" stroke="none">
						<animate dur="0.001s" id="start" attributeName="width" values="10;0" fill="freeze" begin="click" />
						<animate dur="0.001s" attributeName="width" values="0;10" fill="freeze" begin="reverse.begin" />
					</rect>
				</svg>
			</button>
			<div class="logo">
				<img src="https://placehold.co/64x64/png" alt="Logo de l'entreprise">
			</div>
		</div>

		<div class="top-menu-center">
			<form class="search-form" action="#" method="get">
				<input type="search" placeholder="Rechercher..." aria-label="Recherche">
				<button type="submit">🔍</button>
			</form>
			<nav class="top-nav">
				<ul>
					<li><a href="#">Menu 1</a></li>
					<li><a href="#">Menu 2</a></li>
				</ul>
			</nav>
		</div>

		<div class="top-menu-right">
			<div class="top-menu-item">
				<button class="btn-low-emphasis --btn-icon" aria-label="Notifications"><span class="far fa-bell"></span></button>
			</div>
			<div class="top-menu-item">
				<button class="btn-low-emphasis --btn-icon" aria-label="Notifications"><span class="far fa-star"></span></button>
			</div>
			<div class="top-menu-item user-menu">
				<img src="https://thispersondoesnotexist.com/" alt="Photo utilisateur" class="user-avatar">
			</div>
		</div>
	</header>

	<!-- Left Menu -->
	<aside id="left-menu">
		<nav>
			<?php print demoGenerateMenu($quickTestLeftMenu); ?>
		</nav>
	</aside>

	<!-- Main Content -->
	<main id="content">
		<header class="header" >
			<h1>Présentation Lorem Ipsum</h1>
			<p class="lead">Une page factice pour démonstration : titres, paragraphes, tableau et listes.</p>
		</header>


		<section class="content" role="main" aria-label="Contenu principal">
			<!-- En-tête de page -->
			<header class="page-header">

			</header>

			<!-- Section principale -->
			<article class="article-intro">
				<h2>Introduction</h2>
				<p>
					Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero.
					Sed cursus ante dapibus diam. Sed nisi. Nulla quis sem at nibh elementum imperdiet.
					Duis sagittis ipsum. Praesent mauris. Fusce nec tellus sed augue semper porta.
				</p>
				<p>
					Curabitur sodales ligula in libero. Sed dignissim lacinia nunc. Curabitur tortor. Pellentesque
					nibh. Aenean quam. In scelerisque sem at dolor. Maecenas mattis. Sed convallis tristique sem.
				</p>
			</article>

			<!-- Sous-section -->
			<section class="features">
				<h2>Points clés</h2>
				<ul>
					<li><strong>Performance :</strong> Fusce nec tellus sed augue semper porta.</li>
					<li><strong>Accessibilité :</strong> Curabitur sodales ligula in libero.</li>
					<li><strong>Sécurité :</strong> Maecenas mattis, sed convallis.</li>
				</ul>

				<h3>Résumé court</h3>
				<p>
					Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.
					Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante.
				</p>
			</section>

			<!-- Tableau de démonstration -->
			<section class="data-table" aria-labelledby="table-title">
				<h2 id="table-title">Tableau récapitulatif</h2>
				<table summary="Tableau factice listant des exemples de produits et statuts">
					<caption>Exemples de lignes produit (données fictives)</caption>
					<thead>
					<tr>
						<th scope="col">#</th>
						<th scope="col">Produit</th>
						<th scope="col">Description</th>
						<th scope="col">Quantité</th>
						<th scope="col">Statut</th>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td>1</td>
						<td>Widget Alpha</td>
						<td>Lorem ipsum dolor sit amet, consectetur.</td>
						<td>12</td>
						<td><span class="badge badge-ok">Disponible</span></td>
					</tr>
					<tr>
						<td>2</td>
						<td>Module Beta</td>
						<td>Integer nec odio. Praesent libero.</td>
						<td>0</td>
						<td><span class="badge badge-warn">Rupture</span></td>
					</tr>
					<tr>
						<td>3</td>
						<td>Kit Gamma</td>
						<td>Sed cursus ante dapibus diam.</td>
						<td>5</td>
						<td><span class="badge badge-low">Faible</span></td>
					</tr>
					</tbody>
					<tfoot>
					<tr>
						<td colspan="4" style="text-align:right;">Total articles listés</td>
						<td>3</td>
					</tr>
					</tfoot>
				</table>
			</section>

			<!-- Bloc citation et CTA -->
			<aside class="callout" role="complementary" aria-label="Citation">
				<blockquote>
					« Lorem ipsum dolor sit amet, consectetur adipiscing elit — démonstration de contenu factice. »
					<footer>— Citation fictive</footer>
				</blockquote>
				<p>
					<a href="#contact" class="btn">Contactez-nous</a>
				</p>
			</aside>

			<!-- Formulaire de contact factice -->
			<section class="contact-form" aria-labelledby="contact">
				<h2 id="contact">Nous contacter</h2>
				<form action="#" method="post" novalidate>
					<div class="form-row">
						<label for="name">Nom</label>
						<input id="name" name="name" type="text" placeholder="Votre nom" />
					</div>
					<div class="form-row">
						<label for="email">Email</label>
						<input id="email" name="email" type="email" placeholder="you@example.com" />
					</div>
					<div class="form-row">
						<label for="message">Message</label>
						<textarea id="message" name="message" rows="4" placeholder="Votre message..."></textarea>
					</div>
					<div class="form-actions">
						<button type="submit">Envoyer</button>
						<button type="reset" class="secondary">Réinitialiser</button>
					</div>
				</form>
			</section>

			<!-- Pied de page de la section -->
			<footer class="section-footer">
				<p class="muted">Page de démonstration — contenu fictif • Généré pour tests et maquettes.</p>
			</footer>
		</section>

	</main>

<?php
// Output close body + html
$documentation->docFooter();
