<?php
/* Copyright (C) 2024 Francis Appels <francis.appels@z-application.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
use Ayeo\Barcode;

dol_include_once('/barcodeprint/lib/vendor/autoload.php');
dol_include_once('/product/stock/class/productlot.class.php');
dol_include_once('/product/class/product.class.php');
dol_include_once('/core/lib/files.lib.php');

/**
 * \file    barcodeprint/class/productlabel.class.php
 * \ingroup barcodeprint
 * \brief   File for product labels class
 */

/**
 * class for product labels
 *
 */
class ProductLabel extends Product
{
	/**
	 * @var int $numberofsticker number of labels to print
	 */
	public $numberofsticker;

	/**
	 * create product lot barcode png file
	 *
	 * @param Productlot	$productLot 	lot object
	 * @param string		$type			barcode type, default gs1-128
	 * @param string		$eanPackageCode	EAN14 package code '0' is no specific pacakge
	 * @param boolean		$generatethumbs	add thumbs for barcode file.
	 *
	 * @return string	full destination filename
	 */
	public function createLotBarcodeFile(Productlot $productLot, $type = 'gs1-128', $eanPackageCode = '0', $generatethumbs = true)
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
				dol_print_error($this->db, $e->getMessage());
				return '';
			}
			$builder->setFilename($destfull);
			try {
				$builder->setImageFormat('png');
			} catch (Exception $e) {
				dol_print_error($this->db, $e->getMessage());
				return '';
			}
			$builder->setWidth(600);
			$builder->setHeight(140);
			//$builder->setFontPath('FreeSans.ttf');
			try {
				$builder->setFontSize(15);
			} catch (Exception $e) {
				dol_print_error($this->db, $e->getMessage());
				return '';
			}
			$builder->setBackgroundColor(255, 255, 255);
			$builder->setPaintColor(0, 0, 0);

			// get product GTIN (EAN14)
			$this->fetch_barcode();
			if ($this->barcode_type_code == 'UPC') {
				// dolibarr UPC is UPCA
				$this->barcode_type_code = 'UPCA';
			}
			$productBarcode = '';
			if (in_array($this->barcode_type_code, array('EAN8', 'EAN13', 'UPCA')) && !empty($this->barcode)) {
				include_once TCPDF_PATH.'tcpdf_barcodes_1d.php';
				$barcodeObj = new TCPDFBarcode($this->barcode, $this->barcode_type_code);
				$barcode = $barcodeObj->getBarcodeArray();
				if ($this->barcode_type_code == 'EAN8') {
					$productBarcode =  '(01)' . $eanPackageCode . '00000' . $barcode['code'];
				} elseif ($this->barcode_type_code == 'UPCA') {
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
}