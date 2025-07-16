<!-- file home.tpl.php -->
<?php
/* Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 */
// Protection to avoid direct call of template
if (empty($context) || !is_object($context)) {
	print "Error, template page can't be called as URL";
	exit(1);
}
'@phan-var-force Context $context';

global $conf, $langs;

function loremIpsum($nbMots = 20)
{
	$lorem = "Lorem ipsum dolor sit amet, consectetur adipiscing elit.
              Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
              Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.";

	$mots = explode(" ", $lorem);
	shuffle($mots); // Pour varier un peu

	return implode(" ", array_slice($mots, 0, $nbMots)) . ".";
}

function _status()
{
	$status = [
		[
			'id' => 0,
			'class' => '--yellow' ,
			'label' => 'En cours',
			'color' => '#f472b6'
		],
		[
			'id' => 1,
			'class' => '--red' ,
			'label' => 'En attente',
			'color' => '#f472b6'
		],
		[
			'id' => 2,
			'class' => '--green' ,
			'label' => 'Livré',
			'color' => '#f472b6'
		],
		[
			'id' => 3,
			'class' => '--indigo' ,
			'label' => 'statut autre',
			'color' => '#f472b6'
		],
		[
			'id' => 4,
			'class' => '--purple' ,
			'label' => 'statut autre',
			'color' => '#f472b6'
		],
		[
			'id' => 5,
			'class' => '--pink' ,
			'label' => 'statut autre',
			'color' => '#f472b6'
		]
	];

	return $status;
}


function generateProductRef($length = 8)
{
	$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$ref = '';
	for ($i = 0; $i < $length; $i++) {
		$ref .= $characters[random_int(0, strlen($characters) - 1)];
	}
	return $ref;
}

function randomStatus()
{
	$status = _status();

	$index = rand(0, count($status)-1);
	$status[$index]['label'] = $status[$index]['label'];
	return $status[$index];
}

$statusReliquats =  [
	[
		'id' => 0,
		'class' => '--yellow' ,
		'label' => 'En Cours',
		'color' => '#f472b6'
	],
	[
		'id' => 1,
		'class' => '--red' ,
		'label' => 'En attente',
		'color' => '#f472b6'
	],
	[
		'id' => 2,
		'class' => '--green' ,
		'label' => 'Livré',
		'color' => '#f472b6'
	]
];

function randomTypeCode()
{
	$status = [
		[
			'class' => '--dark' ,
			'label' => 'Plateforme'
		],
		[
			'class' => '' ,
			'label' => 'Partenaire'
		]
	];

	$index = rand(0, count($status)-1);
	return $status[$index];
}

function _genBadge($data, $moreClass = '', $prefixHtml = '')
{
	return '<span class="badge '. $data['class'] .' '.$moreClass.'">'. $prefixHtml.$data['label'] .'</span>';
}


require_once DOL_DOCUMENT_ROOT . '/webportal/class/html.formlistwebportal.class.php';
$formWebPortal = new FormWebPortal($this->db);
$formListWebPortal = new FormListWebPortal($this->db);

$display = [
//	'commandes',
	'products',
//	'reliquats-article',
//	'home'
]

?>

<?php if (in_array('products', $display)) : ?>
	<main class="container ">

		<nav id="webportal-' . $elementEn . '-pagination">
			<ul>
				<li><strong>Atricles</strong> (6546)</li>
<!--				<li>--><?php //print dolGetButtonTitle('Vue par commande', '', 'fa fa-file', '#', '', 1, ['forcenohideoftext' => 1]); ?><!--</li>-->
<!--				<li>--><?php //print dolGetButtonTitle('Vue par article', '', 'fa fa-list-alt', '#', '', 2, ['forcenohideoftext' => 1]); ?><!--</li>-->
			</ul>
<!--			<ul>-->
<!--				<li>--><?php //print dolGetButtonTitle('Imprimer', '', 'fa fa-print', '#', '', 1, ['forcenohideoftext' => 1]); ?><!--</li>-->
<!--			</ul>-->
			<?php print $formListWebPortal::generatePageListNav('#', 262, 0); ?>
		</nav>



		<table class="striped"  responsive="scroll" role="grid">
			<thead>

			<tr role="search-row">


				<th class="text-center" style="opacity: 0.5"><?php print img_picto('Text on title tag for tooltip', 'filter') ?></th>
				<th><input type="text" style="max-width: 96px" ></th>
				<th><input type="text" style="max-width: 96px" ></th>
				<th><input type="text" style="max-width: 96px" ></th>
				<th><input type="text" style="max-width: 96px" ></th>
				<th><input type="text" style="max-width: 100%" ></th>
				<th class="text-center"></th>
				<th class="text-center "></th>
				<th class="text-center"><?php print $formWebPortal->selectArray('dfdf', ['' => '', 0 => 'Promo']); ?></th>
				<th class="text-center"></th>
				<th class="text-center"><?php print $formWebPortal->selectArray('dfdfef', ['' => '', 0 => 'Dispo']); ?></th>
				<th class="text-center"></th>
				<th class="text-center"></th>


				<th data-col="row-checkbox" >
					<button class="btn-filter-icon btn-search-filters-icon" type="submit" name="button_search_x" value="x" ></button>
					<button class="btn-filter-icon btn-remove-search-filters-icon" type="submit" name="button_removefilter_x" value="x" ></button>
				</th>
			</tr>

			<tr>
				<th class="text-center"><input type="checkbox"></th>
				<th class="text-center">Fournisseur</th>
				<th class="text-center">Réf.</th>
				<th class="text-center">Catalog</th>
				<th class="text-center">Page</th>
				<th >Désignation</th>

				<th class="text-center">Prix public</th>
				<th class="text-center col-prix-adhere">Prix Adh.</th>
				<th class="text-center">Promo</th>
				<th class="text-center">Stock</th>
				<th class="text-center">Dispo</th>
				<th class="text-center">Appro.</th>
				<th class="text-center">Délai</th>
				<th class="text-center col-cmd-reste">En Cmd.</th>
			</tr>
			</thead>
			<tbody>
			<?php
			$mdMumber = 26000;
			$date = time();
			for ($i = 1; $i <= 25; $i++) {
				$date = $date - 86000*rand(0, 30);
				$dateDelay = $date + 86000*rand(0, 5);
				$dateLive = $dateDelay + 86000*rand(0, 10);
				$mdMumber = $mdMumber - rand(0, 500);


				$typeCode = randomTypeCode();
				$list = ['ANIMO', 'ROLLER', 'VERNES', 'WINTRER'];
				$frs = $list[rand(0, count($list)-1)];

				$subPrice  = round(rand(200, 1000) / 100, 2);
				$subPriceAdh = round($subPrice * (1 - rand(1, 6)*10 /100), 2);

				$stock = rand(0, 100);

				$remise  = 0;
				if (rand(0, 10) > 7) {
					$remise = rand(0, 20);
				}


				$fourn = [
					'3G', '3M', 'ACO', 'PM8', 'DEG'
				];


				?>
				<tr>
					<td><input type="checkbox" /></td>
					<td class="text-center"><?php print $fourn[rand(0, count($fourn)-1)] ; ?></td>
					<td class="text-center"><a href="#"><?php print generateProductRef(); ?></a></td>
					<td class="text-center"><?php print rand(1, 10000); ?></td>
					<td class="text-center"><?php print rand(1, 300); ?></td>
					<td ><a href="#"><?php print loremIpsum(rand(3, 15)) ?></a></td>
					<td class="text-right"><?php print price($subPrice) ?></td>
					<td class="text-right col-prix-adhere"><?php

					if ($remise>0) {
						print '<strike><small>'.price($subPriceAdh).'</small></strike>&nbsp;&nbsp;&nbsp;';
						print '<strong>'.price(round($subPriceAdh * (1 - $remise/100), 2)).'</strong>';
					} else {
						print '<strong>'.price($subPriceAdh).'</strong>';
					}

					?>
					</td>
					<td class="text-center">
						<?php
						if ($remise>0) {
							print '<strong class="badge --rounded --red">-'.$remise.'%</strong>';
						}
						?>
					</td>
					<td class="text-right"><?php print price($stock); ?></td>
					<td class="text-right"><?php print price($stock- rand(0, $stock)); ?></td>
					<td class="text-right"><?php print price(rand(0, 50)); ?></td>
					<td class="text-center"><?php print rand(1, 7) .'J'; ?></td>
					<td class="text-right col-cmd-reste"><?php
					if (rand(0, 10) > 9) {
						print '<strong >'.price(rand(0, 30)).'</strong>';
					}
					?>
					</td>



				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
	</main>
<?php endif; ?>

<?php if (in_array('reliquats-article', $display)) : ?>
	<main class="container ">

		<nav id="webportal-' . $elementEn . '-pagination">
			<ul>
				<li><strong>Atricles & reliquats</strong> (305)</li>
				<li><?php print dolGetButtonTitle('Vue par commande', '', 'fa fa-file', '#', '', 1, ['forcenohideoftext' => 1]); ?></li>
				<li><?php print dolGetButtonTitle('Vue par article', '', 'fa fa-list-alt', '#', '', 2, ['forcenohideoftext' => 1]); ?></li>
			</ul>
			<ul>
				<li><?php print dolGetButtonTitle('Imprimer', '', 'fa fa-print', '#', '', 1, ['forcenohideoftext' => 1]); ?></li>
			</ul>
			<?php print $formListWebPortal::generatePageListNav('#', 6, 0); ?>
		</nav>



		<table class="striped"  responsive="scroll" role="grid">
			<thead>

			<tr role="search-row">
				<th class="text-center" style="opacity: 0.5"><?php print img_picto('Text on title tag for tooltip', 'filter') ?></th>
				<th><input type="text" style="max-width: 96px" ></th>
				<th>
					<div class="grid width150">
						<?php print $formWebPortal->inputDate('search__dtstart01', ''); ?>
					</div>
				</th>
				<th><input type="text" style="max-width: 96px" ></th>
				<th><input type="text" style="max-width: 96px" ></th>
				<th><input type="text" style="max-width: 96px" ></th>
				<th><input type="text" style="max-width: 100%" ></th>
				<th colspan="7">
					<div class="text-center">
						<span>Statut de livraison : </span>
						<span class="badge --red --border"><input type="checkbox" checked > En attente</span>
						<span class="badge --yellow --border"><input type="checkbox" checked > En cours</span>
						<span class="badge --green --border "><input type="checkbox" checked > Livré</span>
						<!--			--green --border-->
					</div>

				</th>

				<th data-col="row-checkbox" >
					<button class="btn-filter-icon btn-search-filters-icon" type="submit" name="button_search_x" value="x" ></button>
					<button class="btn-filter-icon btn-remove-search-filters-icon" type="submit" name="button_removefilter_x" value="x" ></button>
				</th>
			</tr>

			<tr>
				<th class="text-center">#</th>
				<th class="text-center">No Cde</th>
				<th  class="text-center" table-order="asc" >Date Cde</th>
				<th class="text-center">Frs</th>
				<th class="text-center">Réf.</th>
				<th class="text-center">Catalog</th>
				<th >Désignation</th>
				<th class="text-center">Cdé</th>
				<th class="text-center" >Reliquats</th>
				<th class="text-center" >Mis coté</th>
				<th class="text-center">Réser.</th>
				<th class="text-center">Statut</th>
				<th class="text-center">Info Cde</th>
				<th class="text-center">Prix Net</th>
				<th class="text-center">Montant</th>
			</tr>
			</thead>
			<tbody>
			<?php
			$mdMumber = 26000;
			$date = time();
			for ($i = 1; $i <= 25; $i++) {
				$date = $date - 86000*rand(0, 30);
				$dateDelay = $date + 86000*rand(0, 5);
				$dateLive = $dateDelay + 86000*rand(0, 10);
				$mdMumber = $mdMumber - rand(0, 500);


				$typeCode = randomTypeCode();
				$list = ['ANIMO', 'ROLLER', 'VERNES', 'WINTRER'];
				$frs = $list[rand(0, count($list)-1)];
				$reliquats = rand(1, 150);
				if (rand(0, 3) > 0) {
					$reliquats = 0;
				}
				$qtyCmd = rand($reliquats, 150);
				$qtyCote = rand(0, $reliquats);
				$qtyReserv = rand(0, $reliquats-$qtyCote);
				$subPrice  = round(rand(0, 1000) / 100, 2);



				if ($qtyReserv>0) {
					$status =  $statusReliquats[0];
				} elseif ($reliquats>0) {
					$status =  $statusReliquats[1];
				} else {
					$status =  $statusReliquats[2];
				}

				?>
				<tr>
					<!--			<td><input type="checkbox" /></td>-->

					<td class="text-center"><?php print $i+1 ; ?></td>
					<td class="text-center"><a href="#" style="white-space: nowrap;"><span class="fa fa-file-alt"></span> <?php print $mdMumber ?></a></td>
					<td class="text-center"><?php print dol_print_date($date, "%d/%m/%Y") ?></td>
					<td class="text-center"><?php print $frs; ?></td>
					<td class="text-center"><?php print generateProductRef(); ?></td>
					<td class="text-center"><?php print rand(0, 10000); ?></td>
					<td ><?php print loremIpsum(rand(3, 15)) ?></td>
					<td class="text-right"><?php print price($qtyCmd); ?></td>
					<td class="text-right"><?php if ($reliquats>0) { print '<strong>'.price($reliquats) .'</strong>'; } ?></td>
					<td class="text-right" ><?php if ($reliquats>0) { print price($qtyCote); } ?></td>
					<td class="text-right" ><?php if ($reliquats>0) { print price(rand(0, $reliquats-$qtyCote)); } ?></td>
					<td class="text-center"><?php print _genBadge($status, '--border') ?></td>
					<td class="text-center"></td>
					<td class="text-right"><?php print price($subPrice) ?></td>
					<td class="text-right"><?php print price($subPrice*$qtyCmd) ?></td>



				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
	</main>
<?php endif; ?>
<?php if (in_array('commandes', $display)) : ?>
<main class="container ">

	<nav id="webportal-' . $elementEn . '-pagination">
		<ul>
			<li><strong>Commandes avec reliquats</strong> (52)</li>

			<li><?php print dolGetButtonTitle('Vue par commande', '', 'fa fa-file', '#', '', 2, ['forcenohideoftext' => 1]); ?></li>
			<li><?php print dolGetButtonTitle('Vue par article', '', 'fa fa-list-alt', '#', '', 1, ['forcenohideoftext' => 1]); ?></li>
		</ul>
		<?php print $formListWebPortal::generatePageListNav('#', 2, 0); ?>
	</nav>


	<div class="text-right">
		<span>Statut de livraison : </span>
		<?php
		$TStatus = _status();
		foreach ($TStatus as $k => $status) {
			$checked = 'checked';
			$class = $status['class'].' --border';
			//          if($k>1){
			//              $checked = '';
			//              $class = '--dark';
			//          }

			print '<span class="badge '. $class .' "><input type="checkbox" '.$checked.' > '. $status['label'] .'</span>';
		}
		?>

	</div>

	<table class="striped"  responsive="scroll" role="grid">
		<thead>

			<tr role="search-row">
				<th><input type="text" style="max-width: 96px" ></th>
				<th>
					<div class="grid width150">
						<?php print $formWebPortal->inputDate('search__dtstart01', '', $langs->trans('From')); ?>
					</div>
					<div class="grid width150">
						<?php print $formWebPortal->inputDate('search__dtstart02', '', $langs->trans('To')); ?>
					</div>
				</th>
				<th>

					<div class="grid width150">
						<?php print $formWebPortal->inputDate('search__dtstart03', '', $langs->trans('From')); ?>
					</div>
					<div class="grid width150">
						<?php print $formWebPortal->inputDate('search__dtstart04', '', $langs->trans('To')); ?>
					</div>

				</th>
				<th>

					<div class="grid width150">
						<?php print $formWebPortal->inputDate('search__dtstart05', '', $langs->trans('From')); ?>
					</div>
					<div class="grid width150">
						<?php print $formWebPortal->inputDate('search__dtstart06', '', $langs->trans('To')); ?>
					</div>
				</th>
				<th><?php /*print $formWebPortal->multiselectArray('fzefzef',_status()); */ ?></th>
				<th><input type="text" style="width: 100%;" ></th>
				<th></th>
				<th><?php print $formWebPortal->selectArray('dfdf', ['' => '', 0 => 'Plateforme']); ?></th>

				<th data-col="row-checkbox" >
					<button class="btn-filter-icon btn-search-filters-icon" type="submit" name="button_search_x" value="x" ></button>
					<button class="btn-filter-icon btn-remove-search-filters-icon" type="submit" name="button_removefilter_x" value="x" ></button>
				</th>
			</tr>

			<tr>
				<th class="text-center">No Cde</th>
				<th  class="text-center" table-order="asc" >Date Cde</th>
				<th class="text-center">Délai</th>
				<th class="text-center">Date liv.</th>
				<th class="text-center">Statut</th>
				<th >Référence</th>
				<th class="text-center" >Reliquats</th>
				<th class="text-center">Type code</th>
				<th class="text-center">Frs</th>
			</tr>
		</thead>
		<tbody>
		<?php
		$mdMumber = 26000;
		$date = time();
		for ($i = 1; $i <= 25; $i++) {
			$date = $date - 86000*rand(0, 30);
			$dateDelay = $date + 86000*rand(0, 5);
			$dateLive = $dateDelay + 86000*rand(0, 10);
			$mdMumber = $mdMumber - rand(0, 500);

			$status = randomStatus();

			$typeCode = randomTypeCode();
			$list = ['ANIMO', 'ROLLER', 'VERNES', 'WINTRER'];
			$frs = $list[rand(0, count($list)-1)];
			?>
		<tr>

<!--			<td><input type="checkbox" /></td>-->
			<td class="text-center"><a href="#"><span class="fa fa-file-alt"></span> <?php print $mdMumber ?></a></td>
			<td class="text-center"><?php print dol_print_date($date, "%d/%m/%Y") ?></td>
			<td class="text-center"><?php print dol_print_date($dateDelay, "%d/%m/%Y") ?></td>
			<td class="text-center"><?php print dol_print_date($dateLive, "%d/%m/%Y") ?></td>
			<td class="text-center"><?php print _genBadge($status, '--border') ?></td>
			<td><a href="#"><?php print loremIpsum(rand(3, 15)) ?></a></td>
			<td class="text-center">
			<?php
			if ($status['id'] != 2) {
				$reliquats = rand(1, 100);
				print '<strong>'.$reliquats . '</strong> / ' .rand($reliquats, 100);
			} else {
				print '--';
			}

			?>
			</td>
			<td class="text-center"><?php print _genBadge($typeCode, '--rounded --border-dashed ') ?></td>
			<td class="text-center"><?php print $frs; ?></td>
		</tr>
			<?php
		}
		?>
		</tbody>
	</table>
</main>
<?php endif; ?>


<?php if (in_array('home', $display)) : ?>
<main class="container home-container">
		<div class="home-links-grid grid">

			<article class="home-links-card --propal-list">
				<a class="home-links-card__link" href="">Vos commandes</a> <span class="badge --indigo">150</span><br>
				<a class="home-links-card__link" href="">Vos reliquats</a> <span class="badge --purple">2</span>
			</article>

			<article class="home-links-card --propal-list">
				<div class="home-links-card__icon" ></div>
				<a class="home-links-card__link" href="">Votre panier</a> <span class="badge --indigo">10</span>
			</article>

			<article class="home-links-card --propal-list">
				<a class="home-links-card__link" href="">Votre compte</a>
			</article>


			<?php
			if (isModEnabled('propal') && getDolGlobalInt('WEBPORTAL_PROPAL_LIST_ACCESS')) : ?>
			<article class="home-links-card --propal-list">
				<div class="home-links-card__icon" ></div>
				<?php print '<a class="home-links-card__link" href="' . $context->getControllerUrl('propallist') . '" title="' . $langs->trans('WebPortalPropalListDesc') . '">' . $langs->trans('WebPortalPropalListTitle') . '</a>'; ?>
			</article>
			<?php endif; ?>
			<?php if (isModEnabled('order') && getDolGlobalInt('WEBPORTAL_ORDER_LIST_ACCESS')) : ?>
			<article class="home-links-card --order-list">
				<div class="home-links-card__icon" ></div>
				<?php print '<a class="home-links-card__link" href="' . $context->getControllerUrl('orderlist') . '" title="' . $langs->trans('WebPortalOrderListDesc') . '">' . $langs->trans('WebPortalOrderListTitle') . '</a>'; ?>
			</article>
			<?php endif; ?>
			<?php if (isModEnabled('invoice') && getDolGlobalInt('WEBPORTAL_INVOICE_LIST_ACCESS')) : ?>
			<article class="home-links-card --invoice-list">
				<div class="home-links-card__icon" ></div>
				<?php print '<a class="home-links-card__link" href="' . $context->getControllerUrl('invoicelist') . '" title="' . $langs->trans('WebPortalInvoiceListDesc') . '">' . $langs->trans('WebPortalInvoiceListTitle') . '</a>'; ?>
			</article>
			<?php endif; ?>
		</div>

<?php endif; ?>
</main>
