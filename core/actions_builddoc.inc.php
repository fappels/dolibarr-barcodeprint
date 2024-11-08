<?php
/* Copyright (C) 2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * 				 2024 Francis Appels  <francis.appels@yahoo.com>
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
 * or see https://www.gnu.org/
 */

/**
 *	\file			htdocs/core/actions_builddoc.inc.php
 *  \brief			Code for actions on building or deleting documents
 */


// $action must be defined
// $id must be defined
// $object must be defined and must have a method generateDocument().
// $permissiontoadd must be defined
// $upload_dir must be defined (example $conf->projet->dir_output . "/";)
// $hidedetails, $hidedesc, $hideref and $moreparams may have been set or not.

if (!empty($permissioncreate) && empty($permissiontoadd)) {
	$permissiontoadd = $permissioncreate; // For backward compatibility
}

// Build doc
if ($action == 'builddoc') {
	$langs->load("barcodeprint@barcodeprint");
	$result = 0;
	$error = 0;
	$arrayofrecords = array();
	foreach ($productLabels as $productLabel) {
		/** @var ProductLabel $productLabel */
		// barcode value
		if (empty($productLabel->barcode)) {
			setEventMessages($langs->trans("ErrorFieldRequired", $productLabel->ref . ' ' . $langs->transnoentitiesnoconv("BarcodeValue")), null, 'errors');
			$error++;
			break;
		} else {
			$productLabel->is2d = false;
			$productLabel->scale = 1;
			$productLabel->photoFileName = '';
		}
		// barcode type = barcode encoding
		if (empty($productLabel->barcode_type)) {
			setEventMessages($langs->trans("ErrorFieldRequired", $productLabel->ref . ' ' . $langs->transnoentitiesnoconv("BarcodeType")), null, 'errors');
			$error++;
			break;
		}

		$result = $productLabel->fetch_barcode();
		if ($result <= 0) {
			$error++;
			setEventMessages('Failed to get bar code type information ' . $productLabel->error, $productLabel->errors, 'errors');
			break;
		}

		if (preg_match('/ZPL/', $modellabel)) {
			$dataMatrix = (!empty($conf->global->BARCODEPRINT_DATAMATRIX_MODE) ? $conf->global->BARCODEPRINT_DATAMATRIX_MODE : 0);
			$productLabel->buildZplBarcode($dataMatrix);
		} elseif (!empty($productLabel->batch)) {
			$productLabel->buildGS1PNGBarcode();
		} elseif (!empty($conf->global->BARCODEPRINT_DEFAULT_NONLOT_GENERATOR) && $conf->global->BARCODEPRINT_DEFAULT_NONLOT_GENERATOR == 'tcpdf') {
			$productLabel->buildTCPDFBarcode();
		} else {
			$productLabel->buildStandardBarcode();
		}

		if (!empty($productLabel->photoFileName) || $productLabel->template == 'barcodeprinttcpdflabel' || $productLabel->template == 'barcodeprintzebralabel') {
			$arrayofrecords = $productLabel->buildLabelTemplate();
		}
	}

	if (!$error) {
		$mesg = '';
		// Build and output PDF
		if (preg_match('/ZPL/', $modellabel)) {
			$result = ProductLabel::buildZplLabels($modellabel, $arrayofrecords);
		} elseif ($diroutput) {
			$result = ProductLabel::buildPDFLabels($diroutput, $modellabel, $arrayofrecords);
		}

		if (is_string($result) || $result <= 0 || $mesg) {
			if (empty($mesg)) {
				$mesg = 'Error ' . $result;
			}

			setEventMessages($langs->trans('Printer') . ' ' . $mesg, null, 'errors');
		} elseif (!preg_match('/ZPL/', $modellabel)) {
			setEventMessages($langs->trans('BarcodeSheetGenerated'), null, 'mesgs');
		}
	}
}
