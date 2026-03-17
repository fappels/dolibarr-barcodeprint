<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2022 Francis Appels <francis.appels@z-application.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    barcodeprint/admin/setup.php
 * \ingroup barcodeprint
 * \brief   BarcodePrint setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/barcodeprint.lib.php';

// Translations
$langs->loadLangs(array("admin", "barcodeprint@barcodeprint"));

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('barcodeprintsetup', 'globalsetup'));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'myobject';

$arrayofparameters = array(
	'BARCODEPRINT_DEFAULT_MODELLABEL'=>array('type'=>'c_format_cards', 'css'=>'minwidth500' ,'enabled'=>1),
	'BARCODEPRINT_ZEBRA_IP'=>array('type'=>'string', 'css'=>'minwidth500' ,'enabled'=>1),
	//'BARCODEPRINT_DEFAULT_NONLOT_GENERATOR'=>array('type'=>'string', 'css'=>'minwidth500' ,'enabled'=>1),
	//'BARCODEPRINT_MYPARAM2'=>array('type'=>'textarea','enabled'=>1),
	//'BARCODEPRINT_MYPARAM3'=>array('type'=>'category:'.Categorie::TYPE_CUSTOMER, 'enabled'=>1),
	//'BARCODEPRINT_MYPARAM4'=>array('type'=>'emailtemplate:thirdparty', 'enabled'=>1),
	'BARCODEPRINT_DATAMATRIX_MODE'=>array('type'=>'yesno', 'enabled'=>1),
	//'BARCODEPRINT_MYPARAM5'=>array('type'=>'thirdparty_type', 'enabled'=>1),
	//'BARCODEPRINT_MYPARAM6'=>array('type'=>'securekey', 'enabled'=>1),
	//'BARCODEPRINT_MYPARAM7'=>array('type'=>'product', 'enabled'=>1),
);

$error = 0;
$setupnotempty = 0;

/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$page_name = "BarcodePrintSetup";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = barcodeprintAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, "barcodeprint@barcodeprint");

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("BarcodePrintSetupPage").'</span><br><br>';


if ($action == 'edit') {
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

	foreach ($arrayofparameters as $constname => $val) {
		if ($val['enabled']==1) {
			$setupnotempty++;
			print '<tr class="oddeven"><td>';
			$tooltiphelp = (($langs->trans($constname . 'Tooltip') != $constname . 'Tooltip') ? $langs->trans($constname . 'Tooltip') : '');
			print '<span id="helplink'.$constname.'" class="spanforparamtooltip">'.$form->textwithpicto($langs->trans($constname), $tooltiphelp, 1, 'info', '', 0, 3, 'tootips'.$constname).'</span>';
			print '</td><td>';

			if ($val['type'] == 'textarea') {
				print '<textarea class="flat" name="'.$constname.'" id="'.$constname.'" cols="50" rows="5" wrap="soft">' . "\n";
				print getDolGlobalString($constname);
				print "</textarea>\n";
			} elseif ($val['type']== 'html') {
				require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
				$doleditor = new DolEditor($constname, getDolGlobalString($constname), '', 160, 'dolibarr_notes', '', false, false, $conf->fckeditor->enabled, ROWS_5, '90%');
				$doleditor->Create();
			} elseif ($val['type'] == 'yesno') {
				print $form->selectyesno($constname, getDolGlobalInt($constname), 1);
			} elseif (preg_match('/emailtemplate:/', $val['type'])) {
				include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
				$formmail = new FormMail($db);

				$tmp = explode(':', $val['type']);
				$nboftemplates = $formmail->fetchAllEMailTemplate($tmp[1], $user, null, 1); // We set lang=null to get in priority record with no lang
				//$arraydefaultmessage = $formmail->getEMailTemplate($db, $tmp[1], $user, null, 0, 1, '');
				$arrayofmessagename = array();
				if (is_array($formmail->lines_model)) {
					foreach ($formmail->lines_model as $modelmail) {
						$moreonlabel = '';
						if (!empty($arrayofmessagename[$modelmail->label])) {
							$moreonlabel = ' <span class="opacitymedium">(' . $langs->trans("SeveralLangugeVariatFound") . ')</span>';
						}
						// The 'label' is the key that is unique if we exclude the language
						$arrayofmessagename[$modelmail->id] = $langs->trans(preg_replace('/\(|\)/', '', $modelmail->label)) . $moreonlabel;
					}
				}
				print $form->selectarray($constname, $arrayofmessagename, getDolGlobalString($constname), 'None', 0, 0, '', 0, 0, 0, '', '', 1);
			} elseif (preg_match('/category:/', $val['type'])) {
				require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
				require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
				$formother = new FormOther($db);

				$tmp = explode(':', $val['type']);
				print img_picto('', 'category', 'class="pictofixedwidth"');
				print $formother->select_categories($tmp[1],  getDolGlobalString($constname), $constname, 0, $langs->trans('CustomersProspectsCategoriesShort'));
			} elseif (preg_match('/thirdparty_type/', $val['type'])) {
				require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
				$formcompany = new FormCompany($db);
				print $formcompany->selectProspectCustomerType(getDolGlobalString($constname), $constname);
			} elseif ($val['type'] == 'securekey') {
				print '<input required="required" type="text" class="flat" id="'.$constname.'" name="'.$constname.'" value="'.(GETPOST($constname, 'alpha') ?GETPOST($constname, 'alpha') : getDolGlobalString($constname)).'" size="40">';
				if (!empty($conf->use_javascript_ajax)) {
					print '&nbsp;'.img_picto($langs->trans('Generate'), 'refresh', 'id="generate_token'.$constname.'" class="linkobject"');
				}
				if (!empty($conf->use_javascript_ajax)) {
					print "\n".'<script type="text/javascript">';
					print '$(document).ready(function () {
					$("#generate_token'.$constname.'").click(function() {
						$.get( "'.DOL_URL_ROOT.'/core/ajax/security.php", {
							action: \'getrandompassword\',
							generic: true
						},
						function(token) {
							$("#'.$constname.'").val(token);
						});
						});
				});';
					print '</script>';
				}
			} elseif ($val['type'] == 'product') {
				if (!empty($conf->product->enabled) || !empty($conf->service->enabled)) {
					$selected = (empty(getDolGlobalString($constname)) ? '' : getDolGlobalString($constname));
					$form->select_produits($selected, $constname, '', 0);
				}
			} elseif ($val['type'] == 'c_format_cards') {
				// Custom handling for c_format_cards type
				include_once DOL_DOCUMENT_ROOT.'/core/lib/format_cards.lib.php';
				$arrayoflabels = array();
				foreach (array_keys($_Avery_Labels) as $codecards) {
					$labeltoshow = $_Avery_Labels[$codecards]['name'];
					$labeltoshow.=' ('.$_Avery_Labels[$codecards]['paper-size'].')';
					$arrayoflabels[$codecards] = $labeltoshow;
				}
				asort($arrayoflabels);
				print $form->selectarray($constname, $arrayoflabels, getDolGlobalString($constname), 'None', 0, 0, '', 0, 0, 0, '', '', 1);
			} else {
				print '<input name="'.$constname.'"  class="flat '.(empty($val['css']) ? 'minwidth200' : $val['css']).'" value="'.getDolGlobalString($constname).'">';
			}
			print '</td></tr>';
		}
	}
	print '</table>';

	print '<br><div class="center">';
	print '<input class="button button-save" type="submit" value="'.$langs->trans("Save").'">';
	print '</div>';

	print '</form>';

	print '<br>';
} else {
	if (!empty($arrayofparameters)) {
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

		foreach ($arrayofparameters as $constname => $val) {
			if ($val['enabled']==1) {
				$setupnotempty++;
				print '<tr class="oddeven"><td>';
				$tooltiphelp = (($langs->trans($constname . 'Tooltip') != $constname . 'Tooltip') ? $langs->trans($constname . 'Tooltip') : '');
				print $form->textwithpicto($langs->trans($constname), $tooltiphelp);
				print '</td><td>';

				if ($val['type'] == 'textarea') {
					print dol_nl2br(getDolGlobalString($constname));
				} elseif ($val['type']== 'html') {
					print  getDolGlobalString($constname);
				} elseif ($val['type'] == 'yesno') {
					print ajax_constantonoff($constname);
				} elseif (preg_match('/emailtemplate:/', $val['type'])) {
					include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
					$formmail = new FormMail($db);

					$tmp = explode(':', $val['type']);

					$template = $formmail->getEMailTemplate($db, $tmp[1], $user, $langs, getDolGlobalString($constname));
					if ($template<0) {
						setEventMessages(null, $formmail->errors, 'errors');
					}
					print $langs->trans($template->label);
				} elseif (preg_match('/category:/', $val['type'])) {
					$c = new Categorie($db);
					$result = $c->fetch(getDolGlobalString($constname));
					if ($result < 0) {
						setEventMessages(null, $c->errors, 'errors');
					} elseif ($result > 0 ) {
						$ways = $c->print_all_ways(' &gt;&gt; ', 'none', 0, 1); // $ways[0] = "ccc2 >> ccc2a >> ccc2a1" with html formated text
						$toprint = array();
						foreach ($ways as $way) {
							$toprint[] = '<li class="select2-search-choice-dolibarr noborderoncategories"' . ($c->color ? ' style="background: #' . $c->color . ';"' : ' style="background: #bbb"') . '>' . $way . '</li>';
						}
						print '<div class="select2-container-multi-dolibarr" style="width: 90%;"><ul class="select2-choices-dolibarr">' . implode(' ', $toprint) . '</ul></div>';
					}
				} elseif (preg_match('/thirdparty_type/', $val['type'])) {
					if (getDolGlobalInt($constname)==2) {
						print $langs->trans("Prospect");
					} elseif (getDolGlobalInt($constname)==3) {
						print $langs->trans("ProspectCustomer");
					} elseif (getDolGlobalInt($constname)==1) {
						print $langs->trans("Customer");
					} elseif (getDolGlobalInt($constname)==0) {
						print $langs->trans("NorProspectNorCustomer");
					}
				} elseif ($val['type'] == 'product') {
					$product = new Product($db);
					$resprod = $product->fetch(getDolGlobalInt($constname));
					if ($resprod > 0) {
						print $product->ref;
					} elseif ($resprod < 0) {
						setEventMessages(null, $product->errors, "errors");
					}
				} else {
					print getDolGlobalString($constname);
				}
				print '</td></tr>';
			}
		}

		print '</table>';
	}

	if ($setupnotempty) {
		print '<div class="tabsAction">';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
		print '</div>';
	} else {
		print '<br>'.$langs->trans("NothingToSetup");
	}
}

if (empty($setupnotempty)) {
	print '<br>'.$langs->trans("NothingToSetup");
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
