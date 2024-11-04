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

		if ($modellabel == 'ZPL_76174') {
			$productLabel->template = 'barcodeprintzebralabel';
			if (!empty($productLabel->batch)) {
				if (!empty($conf->global->BARCODEPRINT_DATAMATRIX_MODE)) {
					// DATAMATRIX GS1
					$productLabel->textforright = $productLabel->barcode . '\n' . $productLabel->batch;
					if ($productLabel->qty > 0) $textforright .= '\n' . $productLabel->qty;
					$productLabel->textforleft = '_1010' . $productLabel->barcode . '10' . $productLabel->batch;
					if ($productLabel->qty > 0) $textforleft .= '_137' . (int) $productLabel->qty;
					$productLabel->encoding = 'DATAMATRIX';
				} else {
					// GS1-128 code 128
					$productLabel->textforright = '';
					$productLabel->textforleft = '>;>8010' . $productLabel->barcode . '>810>6' . $productLabel->batch;
					$productLabel->encoding = 'C-128';
				}
			} else {
				// EAN code
				$productLabel->textforright = '';
				$productLabel->textforleft = substr($productLabel->barcode, 0, 12); // checksum made by zpl
				$productLabel->encoding = 'EAN-13';
			}
		} elseif (!empty($productLabel->batch)) {
			// generate GS1-128 barcode
			$productLot = new Productlot($db);
			$productLot->fetch(0, $productLabel->id, $productLabel->batch);
			if ($productLot->id > 0) {
				$productLabel->photoFileName = $productLabel->createLotBarcodeFile($productLot);
			} else {
				$error++;
				setEventMessages('Failed to get lot information ' . $productLot->error, $productLot->errors, 'errors');
				break;
			}
			$productLabel->encoding = '';
			$productLabel->template = 'barcodeprintstandardlabel';
			$productLabel->textforleft = '';
			$productLabel->textforright = '%PHOTO%';  // Photo will be barcode image
		} elseif (!empty($conf->global->BARCODEPRINT_DEFAULT_NONLOT_GENERATOR) && $conf->global->BARCODEPRINT_DEFAULT_NONLOT_GENERATOR == 'tcpdf') {
			// generate tcpdf barcode

			$generator = 'tcpdfbarcode'; // coder (loaded by fetch_barcode). Engine.
			$productLabel->encoding = strtoupper($productLabel->barcode_type_code); // code (loaded by fetch_barcode). Example 'ean', 'isbn', ...

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
			$productLabel->encoding = $module->getTcpdfEncodingType($encoding); //convert to TCPDF compatible encoding types
			$productLabel->is2d = $module->is2d;
			$productLabel->template = 'barcodeprinttcpdflabel';
			$productLabel->textforleft = '';
			$productLabel->textforright = '%BARCODE%';  // %BARCODE% posible when using TCPDF generator
		} else {
			// generate standard barcode
			$generator = 'phpbarcode'; // coder (loaded by fetch_barcode). Engine.
			$productLabel->encoding = strtoupper($productLabel->barcode_type_code); // code (loaded by fetch_barcode). Example 'ean', 'isbn', ...

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
			$productLabel->photoFileName = $conf->barcode->dir_temp . '/barcode_' . $productLabel->barcode . '_' . $encoding . '.png';
			$result = $module->writeBarCode($productLabel->barcode, $encoding);
			if ($result < 0) {
				$photoFileName = '';
			}
			$productLabel->template = 'barcodeprintstandardlabel';
			$productLabel->textforleft = '';
			$productLabel->textforright = '%PHOTO%';  // Photo will be barcode image
			$productLabel->scale = 0.8;
		}

		if (!empty($photoFileName) || $productLabel->template == 'barcodeprinttcpdflabel' || $productLabel->template == 'barcodeprintzebralabel') {
			$arrayofrecords = $productLabel->buildLabelTemplate();
		}
	}

	if (!$error) {
		$mesg = '';
		// Build and output PDF
		if ($modellabel == 'ZPL_76174') {
			// TODO make universal for all ZPL_ label
			$fontSize = $_Avery_Labels[$modellabel]['font-size'];
			$leftMargin = (float) $_Avery_Labels[$modellabel]['marginLeft'];
			$topMargin = (float) $_Avery_Labels[$modellabel]['marginTop'];
			$width = (float) $_Avery_Labels[$modellabel]['custom_x'] - (2 * $leftMargin);
			$height = (float) $_Avery_Labels[$modellabel]['custom_y'] - (2 * $topMargin);
			$zpl_labels = array();
			$driver = new \Zpl\ZplBuilder('mm');
			$driver->setFontMapper(new \Zpl\Fonts\Generic());
			foreach ($arrayofrecords as $template => $records) {
				if ($template == 'barcodeprintzebralabel') {
					foreach ($records as $index => $record) {
						$driver->reset();
						$driver->setEncoding(28);
						$driver->SetFont('0', $fontSize);
						$driver->SetXY($leftMargin, $topMargin);
						$logodir = $conf->mycompany->dir_output;
						if (!empty($conf->mycompany->multidir_output[$conf->entity])) {
							$logodir = $conf->mycompany->multidir_output[$conf->entity];
						}
						$logo = $logodir . '/logos/thumbs/mybigcompany_small.png';
						if (is_readable($logo)) {
							$driver->drawGraphic($leftMargin, 1, $logo, 135);
						}
						$driver->drawCell($width, 10, $record['textheader'], false, false, 'C');
						if ($record['encoding'] == 'C-128') {
							$driver->drawCode128($leftMargin, $topMargin + 8, $width, 10, $record['textleft'], true, 'N', 'C');
						}
						if ($record['encoding'] == 'DATAMATRIX') {
							$driver->drawDataMatrix($leftMargin + 8, $topMargin + 7, $record['textleft'], 6);
							$driver->SetXY($leftMargin + 16 + 12, $topMargin + 8);
							$cells = explode('\n', $record['textright']);
							if (is_array($cells)) {
								if (count($cells) > 0) {
									$line = 0;
									foreach ($cells as $cell) {
										$driver->drawCell(($width / 2), 10, $cell, false, false, 'L');
										$line += 4;
										$driver->SetXY($leftMargin + 16 + 12, $topMargin + 8 + $line);
									}
								} else {
									$driver->drawCell(($width / 2), 10, $record['textright'], false, false, 'L');
								}
							}
						}
						if ($record['encoding'] == 'EAN-13') {
							$driver->drawEAN13($leftMargin, $topMargin + 8, $width, 10, $record['textleft'], true, 'N', 'C');
						}
						$driver->SetXY($leftMargin, $topMargin + 21);
						$driver->drawCell($width, 10, $record['textfooter'], false, false, 'C');

						if (!empty($conf->global->BARCODEPRINT_ZEBRA_IP)) {
							try {
								\Zpl\Printer::printer($conf->global->BARCODEPRINT_ZEBRA_IP)->send($driver->toZpl());
								$result = 1;
							} catch (\Zpl\CommunicationException $e) {
								$result = $e->getMessage();
							}
						} else {
							// create set of zpl label files to print
							$zpl = $driver->toZpl();
							$zpl_labels[] = $zpl;
							//print_r($zpl);
						}
					}
				} else {
					$result = "Bad configuration.";
				}
			}
		} elseif ($mode == 'label') {
			if (count($arrayofrecords) > 1) {
				$mesg = $langs->trans("OnlyOneGeneratorTypeAllowed");
			}
			foreach ($arrayofrecords as $template => $records) {
				if (!is_array($records) || !count($records)) {
					$mesg = $langs->trans("ErrorRecordNotFound");
				}
				if (empty($modellabel) || $modellabel == '-1') {
					$mesg = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("LabelModel"));
				}

				$outfile = $langs->trans("BarCode") . '_sheets_' . dol_print_date(dol_now(), 'dayhourlog') . '.pdf';

				if (!$mesg) {
					$outputlangs = $langs;

					// This generates and send PDF to output
					// TODO Move
					$result = doc_label_pdf_create($db, $records, $modellabel, $outputlangs, $diroutput, $template, dol_sanitizeFileName($outfile));
				}
			}
		} elseif ($diroutput) {
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
		}

		if (is_string($result) || $result <= 0 || $mesg) {
			if (empty($mesg)) {
				$mesg = 'Error ' . $result;
			}

			setEventMessages($langs->trans('Printer') . ' ' . $mesg, null, 'errors');
		} elseif ($modellabel != 'ZPL_76174') {
			setEventMessages($langs->trans('BarcodeSheetGenerated'), null, 'mesgs');
		}
	}
}
