<!-- file footer.tpl.php -->
<?php
/* Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 */
// Protection to avoid direct call of template
if (empty($context) || !is_object($context)) {
	print "Error, template page can't be called as URL";
	exit(1);
}
'@phan-var-force Context $context';

global $langs;

if ($context->controller != 'login') :
	?>

<footer class="main-footer">
	<div class="footer-separator" ></div>
	<div class="container">
		<div class="row">
			<div class="col-xs-2">
				<img class="brand__logo-img" style="max-height: 192px; filter: grayscale(100%); opacity: 0.7 " src="http://localhost/thersane/easyA%20project/GAFIC-seul.png" alt="THERSANE">
			</div>
			<div class="col-xs-5">
				<h3 class="widgettitle">Nos coordonnées</h3>

				<div class="row">
					<div class="col-xs-6">
						<b>GAFIC</b><br>
						Z.A. du Layer<br>
						9, Rue Ampère<br>
						21110 GENLIS - France
					</div>

					<div class="col-xs-6">
						Tél. : <span style="font-weight:bold">03 80 74 43 12</span>
						<br>
						Fax : <span style="font-weight:bold">03 80 74 44 96</span>
					</div>
				</div>

			</div>
			<div class="col-xs-offset-2 col-xs-3">
				<ul class="no-bullets">
					<li><strong>About</strong></li>
					<li><a class="secondary" data-discover="true" href="">Nous contacter</a></li>
					<li><a class="secondary" data-discover="true" href="">Notre site</a></li>
					<li><a class="secondary" data-discover="true" href="">Notre réseau</a></li>
					<li><a class="secondary" data-discover="true" href="">Nos services</a></li>
				</ul>
			</div>
		</div>
	</div>
</footer>


	<?php
endif;



// load messages
$html = '';
$htmlSuccess = '';
$htmlWarning = '';
$htmlError = '';
$jsOut = '';
$jsSuccess = '';
$jsWarning = '';
$jsError = '';
//$useJNotify = false;
//if (!empty($conf->use_javascript_ajax) && empty($conf->global->MAIN_DISABLE_JQUERY_JNOTIFY)) {
//$useJNotify = true;
//}
$useJNotify = true;
$context->loadEventMessages();
// alert success
if (!empty($context->eventMessages['mesgs'])) {
	$htmlSuccess = $useJNotify ? '' : '<div class="success" role="alert">';
	$msgNum = 0;
	foreach ($context->eventMessages['mesgs'] as $mesg) {
		if ($msgNum > 0) {
			$htmlSuccess .= '<br>';
		}
		$htmlSuccess .= $langs->trans($mesg);
		$msgNum++;
	}
	$htmlSuccess .= $useJNotify ? '' : '</div>';
	if ($useJNotify) {
		$jsSuccess = '
               jQuery.jnotify("' . dol_escape_js($htmlSuccess) . '",
                        "success",
                        3000
               );';
	}
}
// alert warning
if (!empty($context->eventMessages['warnings'])) {
	$htmlWarning = $useJNotify ? '' : '<div class="warning" role="alert">';
	$msgNum = 0;
	foreach ($context->eventMessages['warnings'] as $mesg) {
		if ($msgNum > 0) {
			$htmlWarning .= '<br>';
		}
		$htmlWarning .= $langs->trans($mesg);
		$msgNum++;
	}
	$htmlWarning .= $useJNotify ? '' : '</div>';
	if ($useJNotify) {
		$jsWarning .= 'jQuery.jnotify("' . dol_escape_js($htmlWarning) . '", "warning", true);';
	}
}
// alert error
if (!empty($context->eventMessages['errors'])) {
	$htmlError = $useJNotify ? '' : '<div class="error" role="alert">';
	$msgNum = 0;
	foreach ($context->eventMessages['errors'] as $mesg) {
		if ($msgNum > 0) {
			$htmlError .= '<br>';
		}
		$htmlError .= $langs->trans($mesg);
		$msgNum++;
	}
	$htmlError .= $useJNotify ? '' : '</div>';
	if ($useJNotify) {
		$jsError .= 'jQuery.jnotify("' . dol_escape_js($htmlError) . '", "error", true );';
	}
}
$html .= $htmlError . $htmlWarning . $htmlSuccess;
if ($html) {
	$jsOut = $jsSuccess . $jsWarning . $jsError;
	if ($jsOut == '') {
		print $html;
	}
}
$context->clearEventMessages();

if ($context->getErrors()) {
	include __DIR__ . '/errors.tpl.php';
}
if ($jsOut) {
	$js = '<script nonce="' . getNonce() . '">';
	$js .= 'jQuery(document).ready(function() {';
	$js .= $jsOut;
	$js .= '});';
	$js .= '</script>';
	print $js;
}

print '<script src="'.$context->getControllerUrl().'/js/theme.js"></script>';
?>

</body>
</html>
