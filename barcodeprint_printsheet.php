<?php
/* Copyright (C) 2003       Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2003       Jean-Louis Bergamo    <jlb@j1b.org>
 * Copyright (C) 2006-2017 Laurent Destailleur    <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *    \file        barcodeprint/barcodeprint_printsheet.php
 *    \ingroup    member
 *    \brief        Page to print sheets with barcodes using the document templates into core/modules/printsheets
 */
// from Dolibarr 13 to avoid token error
if (!empty($_POST['mode']) && $_POST['mode'] === 'label') {	// Page is called to build a PDF and output, we must ne renew the token.
	if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');				// Do not roll the Anti CSRF token (used if MAIN_SECURITY_CSRF_WITH_TOKEN is on)
}
// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}

// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}

if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}

// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}

if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}

if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}

if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/format_cards.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/modules/printsheet/modules_labels.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/genericobject.class.php';
require_once DOL_DOCUMENT_ROOT . '/reception/class/reception.class.php';
dol_include_once('/barcodeprint/lib/barcodeprint.lib.php');
dol_include_once('/barcodeprint/class/productlabel.class.php');
dol_include_once('/product/stock/class/productlot.class.php');

// Load translation files required by the page
$langs->loadLangs(array('admin', 'members', 'errors', 'barcodeprint@barcodeprint'));

// Choice of print year or current year.
$now = dol_now();
$year = dol_print_date($now, '%Y');
$month = dol_print_date($now, '%m');
$day = dol_print_date($now, '%d');
$forbarcode = GETPOST('forbarcode');
$fk_barcode_type = GETPOST('fk_barcode_type');
$mode = GETPOST('mode');
$modellabel = (GETPOST('modellabel') ? GETPOST('modellabel') : $conf->global->BARCODEPRINT_DEFAULT_MODELLABEL); // Doc template to use
$numberofsticker = GETPOST('numberofsticker', 'int');
$productid = GETPOST('productid');
$productlotid = GETPOST('productlotid');
$hideView = GETPOST('hide_view');

if (empty($numberofsticker) && $numberofsticker != "0") {
	$numberofsticker = 1; // default
}

$mesg = '';
$productLabels = array();
$zpl_labels = array();

$action = GETPOST('action', 'aZ09');

$producttmp = new ProductLabel($db);
$receptionTmp = new Reception($db);
$productlotTmp = new Productlot($db);
$qty = 0;

if ($productlotid > 0) {
	$productlotTmp->fetch($productlotid);
	$productid = $productlotTmp->fk_product;
	$batch = $productlotTmp->batch;
	if (!empty($productlotTmp->array_options['options_mobilid_countstep'])) $qty = $productlotTmp->array_options['options_mobilid_countstep'];
}

if ($productid > 0) {
	$producttmp->fetch($productid);
	if (empty($forbarcode) || empty($fk_barcode_type)) {
		$forbarcode = $producttmp->barcode;
		$fk_barcode_type = $producttmp->barcode_type;
	}

	$producttmp->barcode = $forbarcode;
	$producttmp->barcode_type = $fk_barcode_type;
	$producttmp->numberofsticker = $numberofsticker;
	if (!empty($batch)) {
		$producttmp->batch = $batch;
		$producttmp->qty = $qty;
	}
	$productLabels[] = $producttmp;
	if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {
		$pdir = get_exdir($producttmp->id, 2, 0, 0, $producttmp, 'product').$producttmp->id."/photos/";
		$dir = $conf->product->dir_output.'/'.$pdir;
	} else {
		$pdir = get_exdir(0, 0, 0, 0, $producttmp, 'product');
	}

	$diroutput = $conf->product->dir_output."/".$pdir;
}

if (GETPOST('receptionid') > 0) {
	$productLabels = array();
	$receptionTmp->fetch(GETPOST('receptionid'));
	$key = 0;
	foreach ($receptionTmp->lines as $line) {
		if ($line->fk_product > 0) {
			$price = 0;
			$price_ttc = 0;
			$productLine = new ProductLabel($db);
			$productLine->fetch($line->fk_product);

			if (!empty($productLine->barcode) && empty($productLine->array_options['options_deactivate_label'])) {
				// barcode type = barcode encoding
				if (empty($productLine->barcode_type)) {
					setEventMessages($langs->trans("ErrorFieldRequired", $productLine->ref . ' ' . $langs->transnoentitiesnoconv("BarcodeType")), null, 'warnings');
					$productLine->barcode_type = $conf->global->PRODUIT_DEFAULT_BARCODE_TYPE;
					$productLine->fetch_barcode();
					if ($productLine->verify() < 0) {
						setEventMessages($langs->trans("ErrorFieldRequired", $productLine->ref . ' ' . $langs->transnoentitiesnoconv($productLine->errors[0])), null, 'errors');
						$error++;
					}
				}
				$numberofsticker = GETPOST('numberofsticker_'.$key, 'int');
				if ((!empty($numberofsticker) && $numberofsticker != $line->qty) || $numberofsticker == "0") {
					$productLine->numberofsticker = $numberofsticker;
				} else {
					$productLine->numberofsticker = $line->qty;
				}
				$productLine->batch = $line->batch;
				$productLabels[$key] = $productLine;
				$key++;
			}
		}
	}
	$rcpref = dol_sanitizeFileName($receptionTmp->ref);
	$diroutput = $conf->reception->dir_output."/".$rcpref;
}

/*
 * Actions
 */

include 'core/actions_builddoc.inc.php';

/*
 * View
 */
if (!$hideView) {
	if (empty($conf->barcode->enabled)) {
		accessforbidden();
	}

	$form = new Form($db);

	llxHeader('', $langs->trans("BarCodePrintsheet"));

	print load_fiche_titre($langs->trans("BarCodePrintsheet"));
	print '<br>';

	print $langs->trans("PageToGenerateBarCodeSheets", $langs->transnoentitiesnoconv("BuildPageToPrint")) . '<br>';
	print '<br>';

	dol_htmloutput_errors($mesg);

	print '<form action="' . $_SERVER["PHP_SELF"] . '" method="POST">';
	print '<input type="hidden" name="action" value="builddoc">';
	print '<input type="hidden" name="token" value="'.currentToken().'">';	// The page will not renew the token but force download of a file, so we must use here currentToken

	print '<div class="tagtable">';

	// Sheet format
	print '	<div class="tagtr">';
	print '	<div class="tagtd" style="overflow: hidden; white-space: nowrap; max-width: 300px;">';
	print $langs->trans("DescADHERENT_ETIQUETTE_TYPE") . ' &nbsp; ';
	print '</div><div class="tagtd maxwidthonsmartphone" style="overflow: hidden; white-space: nowrap;">';
	// List of possible labels (defined into $_Avery_Labels variable set into core/lib/format_cards.lib.php)
	$arrayoflabels = array();
	foreach (array_keys($_Avery_Labels) as $codecards) {
		$labeltoshow = $_Avery_Labels[$codecards]['name'];
		$arrayoflabels[$codecards] = $labeltoshow;
	}
	asort($arrayoflabels);
	print $form->selectarray('modellabel', $arrayoflabels, $modellabel, 1, 0, 0, '', 0, 0, 0, '', '', 1);
	print '</div></div>';

	// Number of stickers to print
	if ($producttmp->id > 0) {
		print '	<div class="tagtr">';
		print '	<div class="tagtd" style="overflow: hidden; white-space: nowrap; max-width: 300px;">';
		if ($modellabel == 'ZPL_76174') {
			print $langs->trans("NumberOfStickersZpl") . ' &nbsp; ';
		} else {
			print $langs->trans("NumberOfStickers") . ' &nbsp; ';
		}
		print '</div><div class="tagtd maxwidthonsmartphone" style="overflow: hidden; white-space: nowrap;">';
		print '<input size="4" type="text" name="numberofsticker" value="' . $numberofsticker . '">';
		print '</div></div>';
	}

	print '</div>';

	print '<br>';

	// Add javascript to make choice dynamic
	print '<script type="text/javascript" language="javascript">
	jQuery(document).ready(function() {
		function init_gendoc_button()
		{
			if ((jQuery("#select_fk_barcode_type").val() > 0 && jQuery("#forbarcode").val()) || jQuery("#receptionid").val() > 0 || jQuery("#productlotid").val() > 0)
			{
				jQuery("#submitformbarcodegen").removeAttr("disabled");
			}
			else
			{
				jQuery("#submitformbarcodegen").prop("disabled", true);
			}
		}
		init_gendoc_button();
		jQuery("#select_fk_barcode_type").change(function() {
			init_gendoc_button();
		});
		jQuery("#forbarcode").keyup(function() {
			init_gendoc_button()
		});
	});
	</script>';

	print '<br>';

	if ($producttmp->id > 0) {
		print $langs->trans("BarCodeDataForProduct", '') . ' ' . $producttmp->getNomUrl(1) . ' ' . (($productlotTmp->id > 0) ? $productlotTmp->getNomUrl(1) : $producttmp->batch) . '<br>';
		print '<div class="tagtable">';
		if ($productlotTmp->id > 0) {
			print '<input type="hidden" name="productlotid" id="productlotid" value="' . $productlotTmp->id . '">';
			print '<input type="hidden" name="productid" value="' . $producttmp->id . '">';
		} else {
			print '<input type="hidden" name="productid" value="' . $producttmp->id . '">';
			// Barcode type
			print '	<div class="tagtr">';
			print '	<div class="tagtd" style="overflow: hidden; white-space: nowrap; max-width: 300px;">';
			print $langs->trans("BarcodeType") . ' &nbsp; ';
			print '</div><div class="tagtd" style="overflow: hidden; white-space: nowrap; max-width: 300px;">';
			require_once DOL_DOCUMENT_ROOT . '/core/class/html.formbarcode.class.php';
			$formbarcode = new FormBarCode($db);
			print $formbarcode->selectBarcodeType($fk_barcode_type, 'fk_barcode_type', 1);

			print '</div></div>';
		}

		// Barcode value
		print '	<div class="tagtr">';
		print '	<div class="tagtd" style="overflow: hidden; white-space: nowrap; max-width: 300px;">';
		print $langs->trans("BarcodeValue") . ' &nbsp; ';
		print '</div><div class="tagtd" style="overflow: hidden; white-space: nowrap; max-width: 300px;">';
		print '<input size="16" type="text" name="forbarcode" id="forbarcode" value="' . $forbarcode . '">';
		print '</div></div>';
	}

	if ($receptionTmp->id > 0) {
		if (!empty($productLabels)) {
			foreach ($productLabels as $key=>$productLabel) {
				// Number of stickers to print
				print '	<div class="tagtr">';
				print '	<div class="tagtd" style="overflow: hidden; white-space: nowrap; max-width: 300px;">';
				print $langs->trans("Product") . ' &nbsp; ';
				print '</div>';
				print '	<div class="tagtd" style="overflow: hidden; white-space: nowrap; max-width: 300px;">';
				print $productLabel->ref . ' &nbsp; ' . $productLabel->batch . ' &nbsp; ';
				print '</div>';
				print '	<div class="tagtd" style="overflow: hidden; white-space: nowrap; max-width: 300px;">';
				if ($modellabel == 'ZPL_76174') {
					print $langs->trans("NumberOfStickersZpl") . ' &nbsp; ';
				} else {
					print $langs->trans("NumberOfStickers") . ' &nbsp; ';
				}
				print '</div><div class="tagtd maxwidthonsmartphone" style="overflow: hidden; white-space: nowrap;">';
				print '<input size="4" type="text" name="numberofsticker_'.$key.'" value="' . (GETPOST('numberofsticker_'.$key) ? GETPOST('numberofsticker_'.$key, 'int') : $productLabel->numberofsticker) . '">';
				print '</div></div>';
			}
		}
		print '	<div class="tagtr">';
		print '	<div class="tagtd" style="overflow: hidden; white-space: nowrap; max-width: 300px;">';
		print $langs->trans("BarCodeDataForReception") . ' &nbsp; ';
		print '</div>';
		print '	<div class="tagtd" style="overflow: hidden; white-space: nowrap; max-width: 300px;">';
		print $receptionTmp->getNomUrl(1) . ' &nbsp; ';
		print '</div>';
		print '<input type="hidden" name="receptionid" id="receptionid" value="' . $receptionTmp->id . '">';
		print '</div>';
		print '<div class="tagtable">';
	}

	print '</div>';

	print '<br><input class="button" type="submit" id="submitformbarcodegen" ' . (GETPOST("selectorforbarcode") ? '' : 'disabled ') . 'value="' . $langs->trans("BuildPageToPrint") . '">';

	print '</form>';
	print '<br>';


	if ($modellabel == 'ZPL_76174' && empty($conf->global->BARCODEPRINT_ZEBRA_IP)) {
		zebraBrowserPrint();
	}

	// End of page
	llxFooter();
}
$db->close();
