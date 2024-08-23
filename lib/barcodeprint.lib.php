<?php
/* Copyright (C) 2022 Francis Appels <francis.appels@z-application.com>
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
 * \file    barcodeprint/lib/barcodeprint.lib.php
 * \ingroup barcodeprint
 * \brief   Library files with common functions for BarcodePrint
 */
use Ayeo\Barcode;

dol_include_once('/barcodeprint/lib/vendor/autoload.php');
dol_include_once('/product/stock/class/productlot.class.php');
dol_include_once('/product/class/product.class.php');
dol_include_once('/core/lib/files.lib.php');

/**
 * Prepare admin pages header
 *
 * @return array
 */
function barcodeprintAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("barcodeprint@barcodeprint");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/barcodeprint/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	/*
	$head[$h][0] = dol_buildpath("/barcodeprint/admin/myobject_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$head[$h][2] = 'myobject_extrafields';
	$h++;
	*/

	$head[$h][0] = dol_buildpath("/barcodeprint/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@barcodeprint:/barcodeprint/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@barcodeprint:/barcodeprint/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'barcodeprint@barcodeprint');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'barcodeprint@barcodeprint', 'remove');

	return $head;
}

/**
 * create product lot barcode png file
 *
 * @param Object		$db				Dolibarr db object
 * @param Productlot	$productLot 	lot object
 * @param string		$type			barcode type, default gs1-128
 * @param string		$eanPackageCode	EAN14 package code '0' is no specific pacakge
 * @param boolean		$generatethumbs	add thumbs for barcode file.
 *
 * @return string	full destination filename
 */
function createLotBarcodeFile($db, Productlot $productLot, $type = 'gs1-128', $eanPackageCode = '0', $generatethumbs = true)
{
	global $conf;

	$modulepart = 'product_batch';
	$upload_dir = $conf->productbatch->multidir_output[$productLot->entity].'/'.get_exdir(0, 0, 0, 1, $productLot, $modulepart);
	// check if there are already doc on old location
	$productLot->ref = $productLot->batch;
	$check_dir = $conf->productbatch->multidir_output[$productLot->entity].'/'.get_exdir(0, 0, 0, 1, $productLot, $modulepart);
	$oldfilearray = dol_dir_list($check_dir, "files");
	if (!empty($oldfilearray)) {
		$upload_dir = $check_dir;
	}
	$destfile = 'barcode-' . $type . '.png';
	$destfull = $upload_dir . '/' . $destfile;
	$result = dol_mkdir($upload_dir);
	if ($result >= 0) {
		$builder = new Barcode\Builder();
		try {
			$builder->setBarcodeType($type);
		} catch (Exception $e) {
			dol_print_error($db, $e->getMessage());
			return '';
		}
		$builder->setFilename($destfull);
		try {
			$builder->setImageFormat('png');
		} catch (Exception $e) {
			dol_print_error($db, $e->getMessage());
			return '';
		}
		$builder->setWidth(600);
		$builder->setHeight(140);
		//$builder->setFontPath('FreeSans.ttf');
		try {
			$builder->setFontSize(15);
		} catch (Exception $e) {
			dol_print_error($db, $e->getMessage());
			return '';
		}
		$builder->setBackgroundColor(255, 255, 255);
		$builder->setPaintColor(0, 0, 0);

		// get product GTIN (EAN14)
		$product = new Product($db);
		$product->fetch($productLot->fk_product);
		$product->fetch_barcode();
		if ($product->barcode_type_code == 'UPC') {
			// dolibarr UPC is UPCA
			$product->barcode_type_code = 'UPCA';
		}
		$productBarcode = '';
		if (in_array($product->barcode_type_code, array('EAN8', 'EAN13', 'UPCA')) && !empty($product->barcode)) {
			include_once TCPDF_PATH.'tcpdf_barcodes_1d.php';
			$barcodeObj = new TCPDFBarcode($product->barcode, $product->barcode_type_code);
			$barcode = $barcodeObj->getBarcodeArray();
			if ($product->barcode_type_code == 'EAN8') {
				$productBarcode =  '(01)' . $eanPackageCode . '00000' . $barcode['code'];
			} elseif ($product->barcode_type_code == 'UPCA') {
				$productBarcode =  '(01)' . $eanPackageCode . '0' . $barcode['code'];
			} else {
				$productBarcode =  '(01)' . $eanPackageCode . $barcode['code'];
			}
		}

		$lotBarcode = '(10)'.$productLot->batch;

		try {
			$builder->saveImage($productBarcode.$lotBarcode);
		} catch (Exception $e) {
			dol_print_error($db, $e->getMessage());
			return '';
		}

		// Generate thumbs.
		if ($generatethumbs) {
			$productLot->addThumbs($destfull);
		}
		addFileIntoDatabaseIndex($upload_dir, basename($destfile), '', 'generated', 0, $productLot);
	}
	return $destfull;
}

/**
 * print zebra label using Zebra browserprint
 *
 * @param String	$printer	zebra printer
 * @return void
 */
function zebraBrowserPrint($printer = '')
{
	global $langs, $zpl_labels;

	$labels = '"'.implode("','", $zpl_labels).'"';
	$printOk = '"'.$langs->trans("BarcodePrinted").'"';

print <<<HTML
<script type="text/javascript" src="lib/zebra/js/BrowserPrint-3.1.250.min.js"></script>
<script type="text/javascript">

jQuery(document).ready(function() {
	var selected_device;
	var devices = [];
	var labels = [$labels];
	var alerted = false;

	function print() {
		BrowserPrint.getLocalDevices( function(device_list) {
			// print on first found device
			selected_device = device_list.printer[0];
			for (let index = 0; index < labels.length; index++) {
				if (labels[index]) {
					selected_device.send(labels[index], function() {
						console.log(index + ' print ok');
						/* jnotify(message, preset of message type, keepmessage) */
						$.jnotify($printOk, "3000", false, {
							remove: function() {}
						});
					}, function(error) {
						console.log(index + ' print nok');
						if (!alerted) {
							/* jnotify(message, preset of message type, keepmessage) */
							$.jnotify(error, "3000", false, {
								remove: function() {}
							});
						}
						alerted = true;
					});
				}
			}
		}, function(error) {
			alert(error);
		})
	}

	print();
});
</script>
HTML;
}
