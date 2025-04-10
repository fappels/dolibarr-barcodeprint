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
	public $numberofsticker = 1;

	public $template;

	public $scale;

	public $textforleft;

	public $textforright;

	public $encoding;

	public $is2d;

	public $photoFileName;

	public $year;

	public $month;

	public $day;

	public $batch;

	public $qty;

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

	/**
	 * Build zpl string of barcode (for the moment only Code128, EAN13 and GS1 supported).
	 * If product with lot number (batch property set with lot number)
	 * If batch and qty property set also qty 37 AI code will be set with lot qty (only for datamatrix type code)
	 *
	 * @param int	$dataMatrixmode	if 1 a datamatrix code will be made else a GS1-128
	 */
	public function buildZplBarcode($dataMatrixmode)
	{
		$this->template = 'barcodeprintzebralabel';
		if (!empty($this->batch) && $this->barcode_type_code == 'EAN13') {
			$barcodeWithChecksum = $this->fetchBarcodeWithChecksum($this);
			if (!empty($dataMatrixmode)) {
				// DATAMATRIX GS1
				$this->textforright = $barcodeWithChecksum . '\n' . $this->batch;
				if ($this->qty > 0) $this->textforright .= '\n' . $this->qty;
				$this->textforleft = '_1010' . $barcodeWithChecksum . '10' . $this->batch;
				if ($this->qty > 0) $this->textforleft .= '_137' . (int) $this->qty;
				$this->encoding = 'DATAMATRIX';
			} else {
				// GS1-128 code 128
				$this->textforright = '';
				$this->textforleft = '>;>8010' . $barcodeWithChecksum . '>810>6' . $this->batch;
				$this->encoding = 'C-128';
			}
		} elseif ($this->barcode_type_code == 'EAN13') {
			// EAN code
			$this->textforright = '';
			$this->textforleft = substr($this->barcode, 0, 12); // checksum made by zpl
			$this->encoding = 'EAN-13';
		} elseif ($this->barcode_type_code == 'C128') {
			// code 128
			$this->textforright = '';
			$this->textforleft = $this->barcode;
			$this->encoding = 'C-128';
		} else {
			// DATAMATRIX or other with type code compatible with encoding
			$this->textforright = '';
			$this->textforleft = $this->barcode;
			$this->encoding = $this->barcode_type_code;
		}
	}

	/**
	 * Make GS1-128 barcode png image and store file patch in photoFileName property
	 * template subtitution '%PHOTO%' to be used
	 */
	public function buildGS1PNGBarcode()
	{
		// generate GS1-128 barcode
		$productLot = new Productlot($this->db);
		$productLot->fetch(0, $this->id, $this->batch);
		if ($productLot->id > 0) {
			$this->photoFileName = $this->createLotBarcodeFile($productLot);
		}
		$this->encoding = '';
		$this->template = 'barcodeprintstandardlabel';
		$this->textforleft = '';
		$this->textforright = '%PHOTO%';  // Photo will be barcode image
	}

	/**
	 * Make barcode using tcpdf barcode generator (will be generated when making pdf sheet)
	 */
	public function buildTCPDFBarcode()
	{
		global $conf;

		// generate tcpdf barcode

		$generator = 'tcpdfbarcode'; // coder (loaded by fetch_barcode). Engine.
		$this->encoding = strtoupper($this->barcode_type_code); // code (loaded by fetch_barcode). Example 'ean', 'isbn', ...

		// Generate barcode
		$dirbarcode = array_merge(array("/core/modules/barcode/doc/"), $conf->modules_parts['barcode']);

		foreach ($dirbarcode as $reldir) {
			$dir = dol_buildpath($reldir, 0);
			$newdir = dol_osencode($dir);

			// Check if directory exists (we do not use dol_is_dir to avoid loading files.lib.php)
			if (!is_dir($newdir)) {
				continue;
			}

			$result = @include_once $newdir . $generator . '.modules.php';
			if ($result) {
				break;
			}
		}

		// Load barcode class for generating barcode image
		$classname = "mod" . ucfirst($generator);
		$module = new $classname($this->db);
		$this->encoding = $module->getTcpdfEncodingType($this->encoding); //convert to TCPDF compatible encoding types
		$this->is2d = $module->is2d;
		$this->template = 'barcodeprinttcpdflabel';
		$this->textforleft = '';
		$this->textforright = '%BARCODE%';  // %BARCODE% posible when using TCPDF generator
	}

	/**
	 * Make barcode using standard barcode generator, wiil first make png to include in pdf sheet
	 */
	public function buildStandardBarcode()
	{
		global $conf;

		// generate standard barcode
		$generator = 'phpbarcode'; // coder (loaded by fetch_barcode). Engine.
		$this->encoding = strtoupper($this->barcode_type_code); // code (loaded by fetch_barcode). Example 'ean', 'isbn', ...

		// Generate barcode
		$dirbarcode = array_merge(array("/core/modules/barcode/doc/"), $conf->modules_parts['barcode']);

		foreach ($dirbarcode as $reldir) {
			$dir = dol_buildpath($reldir, 0);
			$newdir = dol_osencode($dir);

			// Check if directory exists (we do not use dol_is_dir to avoid loading files.lib.php)
			if (!is_dir($newdir)) {
				continue;
			}

			$result = @include_once $newdir . $generator . '.modules.php';
			if ($result) {
				break;
			}
		}

		// Load barcode class for generating barcode image
		$classname = "mod" . ucfirst($generator);
		$module = new $classname($db);
		$this->photoFileName = $conf->barcode->dir_temp . '/barcode_' . $this->barcode . '_' . $encoding . '.png';
		$result = $module->writeBarCode($this->barcode, $this->encoding);
		if ($result < 0) {
			$this->photoFileName = '';
		}
		$this->template = 'barcodeprintstandardlabel';
		$this->textforleft = '';
		$this->textforright = '%PHOTO%';  // Photo will be barcode image
		$this->scale = 0.8;
	}

	/**
	 * Do label substitution
	 *
	 * @param	array $arrayofrecords already subtituted labels to append to.
	 * @return	array substituted labels
	 */
	public function buildLabelTemplate($arrayofrecords = array())
	{
		global $conf, $mysoc, $user, $langs;

		// List of values to scan for a replacement
		$substitutionarray = array(
			'%LOGIN%' => $user->login,
			'%COMPANY%' => $mysoc->name,
			'%ADDRESS%' => $mysoc->address,
			'%ZIP%' => $mysoc->zip,
			'%TOWN%' => $mysoc->town,
			'%COUNTRY%' => $mysoc->country,
			'%COUNTRY_CODE%' => $mysoc->country_code,
			'%EMAIL%' => $mysoc->email,
			'%YEAR%' => $this->year,
			'%MONTH%' => $this->month,
			'%DAY%' => $this->day,
			'%DOL_MAIN_URL_ROOT%' => DOL_MAIN_URL_ROOT,
			'%SERVER%' => "http://" . $_SERVER["SERVER_NAME"] . "/",
			'%PRODUCTREF%' => $this->ref,
			'%PRODUCTLABEL%' => $this->label,
			'%PRODUCTPRICE%' => $this->price,
			'%PRODUCTPRICETTC%' => $this->price_ttc,
			'%BR%' => chr(10),
		);
		complete_substitutions_array($substitutionarray, $langs);

		$textleft = make_substitutions($this->textforleft, $substitutionarray);
		$textheader = make_substitutions((empty($conf->global->BARCODE_LABEL_HEADER_TEXT) ? '' : $conf->global->BARCODE_LABEL_HEADER_TEXT), $substitutionarray);
		$textfooter = make_substitutions((empty($conf->global->BARCODE_LABEL_FOOTER_TEXT) ? '' : $conf->global->BARCODE_LABEL_FOOTER_TEXT), $substitutionarray);
		$textright = make_substitutions($this->textforright, $substitutionarray);
		$forceimgscalewidth = (empty($conf->global->BARCODE_FORCEIMGSCALEWIDTH) ? $this->scale : $conf->global->BARCODE_FORCEIMGSCALEWIDTH);
		$forceimgscaleheight = (empty($conf->global->BARCODE_FORCEIMGSCALEHEIGHT) ? $this->scale : $conf->global->BARCODE_FORCEIMGSCALEHEIGHT);

		for ($i = 0; $i < $this->numberofsticker; $i++) {
			$arrayofrecords[$this->template][] = array(
				'textleft' => $textleft,
				'textheader' => $textheader,
				'textfooter' => $textfooter,
				'textright' => $textright,
				'code' => $this->barcode,
				'encoding' => $this->encoding,
				'is2d' => $this->is2d,
				'photo' => $this->photoFileName,
				'imgscalewidth' => $forceimgscalewidth,
				'imgscaleheight' => $forceimgscaleheight
			);
		}

		return $arrayofrecords;
	}

	/**
	 * build array of zpl string or send to network printer if BARCODEPRINT_ZEBRA_IP defined
	 *
	 * @param string	$modellabel		label model to use
	 * @param array		$arrayofrecords	array of substituted label
	 *
	 * @return array|string	array of zpl string or string with print result.
	 */
	public static function buildZplLabels($modellabel = 'ZPL_76174', $arrayofrecords = array())
	{
		global $conf, $_Avery_Labels, $zpl_labels, $mysoc;

		// TODO make more universal for all ZPL_ label
		if (!empty($_Avery_Labels[$modellabel])) {
			$fontSize = $_Avery_Labels[$modellabel]['font-size'];
			$leftMargin = (float) $_Avery_Labels[$modellabel]['marginLeft'];
			$topMargin = (float) $_Avery_Labels[$modellabel]['marginTop'];
			$width = (float) $_Avery_Labels[$modellabel]['custom_x'] - (2 * $leftMargin);
			$height = (float) $_Avery_Labels[$modellabel]['custom_y'] - (2 * $topMargin);
			$zpl_labels = array();
			$result = 'No label printed';
			$logodir = $conf->mycompany->dir_output;
			if (!empty($conf->mycompany->multidir_output[$conf->entity])) {
				$logodir = $conf->mycompany->multidir_output[$conf->entity];
			}
			$logo = $logodir . '/logos/thumbs/'.$mysoc->logo_small;
			$driver = new \Zpl\ZplBuilder('mm');
			$driver->setFontMapper(new \Zpl\Fonts\Generic());
			foreach ($arrayofrecords as $template => $records) {
				if ($template == 'barcodeprintzebralabel') {
					foreach ($records as $index => $record) {
						$driver->reset();
						$driver->setEncoding(28);
						$driver->SetFont('0', $fontSize);
						$driver->SetXY($leftMargin, $topMargin);
						if ($modellabel == 'ZPL_76173') {
							if (is_readable($logo)) {
								$driver->drawGraphic($leftMargin + 41, 1, $logo, 60);
								$driver->drawCell($width, 10, $record['textheader'], false, false, 'L');
							} else {
								$driver->drawCell($width, 10, $record['textheader'], false, false, 'C');
							}

							if ($record['encoding'] == 'C-128') {
								$driver->drawCode128($leftMargin, $topMargin + 8, $width, 10, $record['textleft'], true, 'N', 'C');
							} elseif ($record['encoding'] == 'DATAMATRIX') {
								$driver->drawDataMatrix($leftMargin + 3, $topMargin + 7, $record['textleft'], 6);
								$driver->SetXY($leftMargin + 22, $topMargin + 8);
								$cells = explode('\n', $record['textright']);
								if (is_array($cells)) {
									if (count($cells) > 0) {
										$line = 0;
										foreach ($cells as $cell) {
											$driver->drawCell(($width / 2), 10, $cell, false, false, 'L');
											$line += 4;
											$driver->SetXY($leftMargin + 22, $topMargin + 8 + $line);
										}
									} else {
										$driver->drawCell(($width / 2), 10, $record['textright'], false, false, 'L');
									}
								}
							} elseif ($record['encoding'] == 'EAN-13') {
								$driver->drawEAN13($leftMargin, $topMargin + 8, $width, 10, $record['textleft'], true, 'N', 'C');
							}
							$driver->SetXY($leftMargin, $topMargin + 21);
							$driver->drawCell($width, 10, $record['textfooter'], false, false, 'C');
						} elseif ($modellabel == 'ZPL_76174') {
							if (is_readable($logo)) {
								$driver->drawGraphic($leftMargin, 1, $logo, 135);
							}
							$driver->drawCell($width, 10, $record['textheader'], false, false, 'C');
							if ($record['encoding'] == 'C-128') {
								$driver->drawCode128($leftMargin, $topMargin + 8, $width, 10, $record['textleft'], true, 'N', 'C');
							} elseif ($record['encoding'] == 'DATAMATRIX') {
								$driver->drawDataMatrix($leftMargin + 8, $topMargin + 7, $record['textleft'], 6);
								$driver->SetXY($leftMargin + 28, $topMargin + 8);
								$cells = explode('\n', $record['textright']);
								if (is_array($cells)) {
									if (count($cells) > 0) {
										$line = 0;
										foreach ($cells as $cell) {
											$driver->drawCell(($width / 2), 10, $cell, false, false, 'L');
											$line += 4;
											$driver->SetXY($leftMargin + 28, $topMargin + 8 + $line);
										}
									} else {
										$driver->drawCell(($width / 2), 10, $record['textright'], false, false, 'L');
									}
								}
							} elseif ($record['encoding'] == 'EAN-13') {
								$driver->drawEAN13($leftMargin, $topMargin + 8, $width, 10, $record['textleft'], true, 'N', 'C');
							}
							$driver->SetXY($leftMargin, $topMargin + 21);
							$driver->drawCell($width, 10, $record['textfooter'], false, false, 'C');
						} else {
							$result = 'Label not supported';
							break;
						}

						if (!empty($conf->global->BARCODEPRINT_ZEBRA_IP)) {
							try {
								\Zpl\Printer::printer($conf->global->BARCODEPRINT_ZEBRA_IP)->send($driver->toZpl());
								$result = 'Label printed';
							} catch (\Zpl\CommunicationException $e) {
								$result = $e->getMessage();
								break;
							}
						} else {
							// create set of zpl label files to print
							$zpl = $driver->toZpl();
							$zpl_labels[] = $zpl;
							$result = $zpl_labels;
							//print_r($zpl);
						}
					}
				} else {
					$result = "Bad configuration.";
					break;
				}
			}
		} else {
			$result = "Bad label model";
		}
		return $result;
	}

	/**
	 * Make barcode pdf sheet
	 *
	 * @param string	$diroutput		pdf output dir
	 * @param string	$modellabel		label format model
	 * @param array		$arrayofrecords	array of substituted label
	 *
	 * @return string	pdf create result
	 */
	public static function buildPDFLabels($diroutput, $modellabel, $arrayofrecords = array())
	{
		global $langs;

		$result = 0;

		foreach ($arrayofrecords as $template => $records) {
			$file = "pdf_" . $template . ".class.php";
			$outfile = $langs->trans("BarCode") . (!empty($batch) ? '_' . $batch : '') . '_sheets_' . dol_print_date(dol_now(), 'dayhourlog') . '.pdf';
			// If selected modele is a filename template (then $modele="modelname:filename")
			$tmp = explode(':', $template, 2);
			if (!empty($tmp[1])) {
				$template = $tmp[0];
				$srctemplatepath = $tmp[1];
			} else {
				$srctemplatepath = $modellabel;
			}

			$file = dol_buildpath("/barcodeprint/core/doc/" . $file, 0);
			if (file_exists($file)) {
				$classname = 'pdf_' . $template;
				require_once $file;

				$obj = new $classname($db);

				$result = $obj->write_file($records, (empty($outputlangs) ? $langs : $outputlangs), $srctemplatepath, $diroutput, dol_sanitizeFileName($outfile));
			} else {
				$result = "Label template " . $template . " not found.";
			}
		}
		return $result;
	}

	/**
	 * print zebra label using Zebra browserprint
	 *
	 * @param array	$zpl_labels	array of zpl labels
	 * @param String	$printer	zebra printer
	 * @return void
	 */
	public function zebraBrowserPrint($zpl_labels, $printer = '')
	{
		global $langs;

		$labels = '"'.implode("','", $zpl_labels).'"';
		$printOk = '"'.$langs->trans("BarcodePrinted").'"';
		$libPath = dol_buildpath('/barcodeprint/lib/zebra/js/BrowserPrint-3.1.250.min.js', 1);

print <<<HTML
<script type="text/javascript" src="$libPath"></script>
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

	/**
	 * public method to fetch barcode with checksum from dolibarr generated barcodes, which are stored without checksum
	 *
	 * @param ProductFournisseur $object product object containing barcode values
	 *
	 * @return string barcode with checksum
	 */
	public function fetchBarcodeWithChecksum($object)
	{
		$barcodeType = '';
		$barcode = '';

		$barcodeTypeData = $this->readBarcodeType();
		foreach ($barcodeTypeData as $barcodeType) {
			$barcodeTypes[$barcodeType->id] = $barcodeType->code;
		}

		if (!empty($object->supplier_barcode)) {
			$barcode = $object->supplier_barcode;
			$barcodeType = $barcodeTypes[$object->supplier_fk_barcode_type];
		} else {
			$barcode = $object->barcode;
			$barcodeType = $barcodeTypes[$object->barcode_type];
		}

		if ($barcodeType == 'UPC') {
			// dolibarr UPC is UPCA
			$barcodeType = 'UPCA';
		}

		// if barcode is full ean13 and first char in '0', we strip 0 and return stripped value,
		// because barcode readers interprete ean13 with leading 0 as a UPC code.
		if (substr($barcode, 0, 1) === '0' && $barcodeType == 'EAN13' && strlen($barcode) == 13) {
			$barcodeType = '';
			$barcode = substr($barcode, 1);
		}

		if (in_array($barcodeType, array('EAN8', 'EAN13', 'UPCA')) && !empty($barcode)) {
			include_once TCPDF_PATH.'tcpdf_barcodes_1d.php';
			$barcodeObj = new TCPDFBarcode($barcode, $barcodeType);
			$barcode = $barcodeObj->getBarcodeArray();
			return $barcode['code'];
		} else {
			return $barcode;
		}
	}

	/**
	 *    Load available barcodetypes
	 *
	 *    @return     stdClass result data
	 */
	public function readBarcodeType()
	{
		global $conf;

		$results = array();
		$row = new stdClass;
		if (! empty($conf->barcode->enabled)) {
			$sql = "SELECT rowid, code, libelle as label, coder";
			$sql.= " FROM ".MAIN_DB_PREFIX."c_barcode_type";
			dol_syslog(get_class($this).'::readBarcodeType', LOG_DEBUG);
			$resql=$this->db->query($sql);

			if ($resql) {
				$num=$this->db->num_rows($resql);
				$row->id    = 0;
				$row->code  = 'NONE';
				$row->label = '';
				$row->coder = '0';
				$row->product_default = false;
				$row->company_default = false;
				array_push($results, clone $row);
				for ($i = 0;$i < $num; $i++) {
					$obj = $this->db->fetch_object($resql);
					$row->id    = $obj->rowid;
					$row->code  = $obj->code;
					$row->label = $obj->label;
					$row->coder = $obj->coder;
					$row->product_default = false;
					$row->company_default = false;
					if ($row->id == $conf->global->PRODUIT_DEFAULT_BARCODE_TYPE) {
						$row->product_default = true;
					} elseif ($row->id == $conf->global->GENBARCODE_BARCODETYPE_THIRDPARTY) {
						$row->company_default = true;
					}
					array_push($results, clone $row);
				}
			}
		}
		return $results;
	}
}
