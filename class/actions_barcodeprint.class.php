<?php
/* Copyright (C) 2022 SuperAdmin <francis.appels@z-application.com>
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
 * \file    barcodeprint/class/actions_barcodeprint.class.php
 * \ingroup barcodeprint
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsBarcodePrint
 */
class ActionsBarcodePrint
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;


	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		global $langs;

		$this->db = $db;
		$langs->load('barcodeprint@barcodeprint');
	}


	/**
	 * Execute action
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					<0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action)
	{
		global $db, $langs, $conf, $user;
		$this->resprints = '';
		return 0;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$errors = array();

		if (in_array($parameters['currentcontext'], array('productlotcard'))) {
			if (!empty($object->id)) {
				if ($action == 'generate_barcode') {
					dol_include_once('/barcodeprint/lib/barcodeprint.lib.php');
					// force to sheet model label generate barcode image
					$conf->global->BARCODEPRINT_DEFAULT_MODELLABEL = 'L7160';
					createLotBarcodeFile($this->db, $object);
				}
			}
		}
		if (in_array($parameters['currentcontext'], array('mocard'))) {
			if (!empty($object->id)) {
				if ($action == 'generate_doc_zip') {
					/**
					 * Make zip of lot documents
					 * @var Mo $object Mo object
					*/
					$filearrayConsumed = array();
					$batchConsumed = array();
					$filearrayProduced = array();
					$defaultDocs = array('barcode-gs1-128.png');
					$warnings = array();
					$makezip = false;
					if (is_array($object->lines)) {
						foreach ($object->lines as $line) {
							if ($line->fk_product > 0 && !empty($line->batch)) {
								// get consumed and produced product files
								if (($line->role == 'consumed' || $line->role == 'produced') ) {
									dol_include_once('/product/stock/class/productlot.class.php');
									dol_include_once('/core/lib/files.lib.php');
									// First take old system for document management ( it uses $object->ref)
									$productLot = new ProductLot($this->db);
									$result = $productLot->fetch(0, $line->fk_product, $line->batch);
									$productLot->ref = $productLot->batch;
									if ($result > 0) {
										$dir = $conf->productbatch->multidir_output[$productLot->entity].'/'.get_exdir(0, 0, 0, 1, $productLot, 'product_batch');
										$oldfilearray = dol_dir_list($dir, "files");
										// then take use new system on lot id.
										$result = $productLot->fetch(0, $line->fk_product, $line->batch);
										$dir = $conf->productbatch->multidir_output[$productLot->entity].'/'.get_exdir(0, 0, 0, 1, $productLot, 'product_batch');
										$newfilearray = dol_dir_list($dir, "files");
										if (!empty($oldfilearray) && !empty($newfilearray)) $warnings[] = $langs->trans('LotDocsOnBothRefAndIdMesg', $productLot->batch);
										$filearray = array();
										foreach ($oldfilearray as $file) {
											$filearray[] = $file;
										}
										foreach ($newfilearray as $file) {
											$filearray[] = $file;
										}
										if ($line->role == 'produced') {
											$filearrayProduced[$line->id] = $filearray;
											$batchProduced[$line->id] = $productLot->batch;
										} else {
											$filearrayConsumed[$line->id] = $filearray;
											$batchConsumed[$line->id] = $productLot->batch;
										}
									}
								}
							}
						}

						$dir = $conf->mrp->multidir_output[$object->entity ? $object->entity : $conf->entity]."/".get_exdir(0, 0, 0, 1, $object);

						foreach ($filearrayProduced as $produced_key=>$filearray) {
							if (empty($filearray)) {
								$warnings[] = $langs->trans('NoProducedDocumentForBatch', $batchProduced[$produced_key]);
							} else {
								if (!dol_is_dir($dir.'/documents/produced')) {
									dol_mkdir($dir.'/documents/produced');
								}
								foreach ($filearray as $file) {
									if (count($filearray) == 1 && in_array($file['name'], $defaultDocs)) $warnings[] = $langs->trans('OnlyDefaultProducedDocumentForBatch', $file['name'], $batchProduced[$produced_key]);
									if (!in_array($file['name'], $defaultDocs)) {
										$result = dol_copy($file['fullname'], $dir.'/documents/produced/'.$file['name']);
										if ($result < 0) {
											$langs->load("errors");
											$error++;
											$errors = $langs->trans("ErrorFailToCopyFile", $file['fullname'], $dir.'/documents/produced/'.$file['name']);
											break;
										} else {
											$makezip = true;
										}
									}
								}
							}
						}
						foreach ($filearrayConsumed as $consumed_key=>$filearray) {
							if (empty($filearray)) {
								$warnings[] = $langs->trans('NoConsumedDocumentForBatch', $batchConsumed[$consumed_key]);
							} else {
								if (!dol_is_dir($dir.'/documents/consumed/'.$batchConsumed[$consumed_key])) {
									dol_mkdir($dir.'/documents/consumed/'.$batchConsumed[$consumed_key]);
								}
								foreach ($filearray as $file) {
									if (count($filearray) == 1 && in_array($file['name'], $defaultDocs)) $warnings[] = $langs->trans('OnlyDefaultConsumedDocumentForBatch', $file['name'], $batchConsumed[$consumed_key]);
									if (!in_array($file['name'], $defaultDocs)) {
										$result = dol_copy($file['fullname'], $dir.'/documents/consumed/'.$batchConsumed[$consumed_key].'/'.$file['name']);
										if ($result < 0) {
											$langs->load("errors");
											$error++;
											$errors = $langs->trans("ErrorFailToCopyFile", $file['fullname'], $dir.'/documents/consumed/'.$batchConsumed[$consumed_key].'/'.$file['name']);
											break;
										} else {
											$makezip = true;
										}
									}
								}
							}
						}
						if ($makezip) {
							$result = dol_compress_dir($dir.'/documents', $dir.'/'.$object->ref.'.zip');
							if ($result < 0) {
								$error++;
								$errors = $langs->trans("ZipFileGeneratedInto", $dir.'/documents.zip');
							} else {
								dol_delete_dir_recursive($dir.'/documents');
							}
							setEventMessages($langs->trans('DocumentZipGenerated'), null);
						} else {
							$warnings[] = $langs->trans('NoDocuments');
						}
					} else {
						$warnings[] = $langs->trans('NoLines');
					}
				}
			}
		}
		if (! $error) {
			if (!empty($warnings)) {
				setEventMessages(null, $warnings, 'warnings');
			}
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = $errors;
			return -1;
		}
	}

	/**
	 * Overloading the addMoreActionsButtons function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		if (in_array($parameters['currentcontext'], array('productlotcard'))) {
			print '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=generate_barcode">' . $langs->trans('GenerateBarcode') . '</a></div>';
		}
		if (in_array($parameters['currentcontext'], array('mocard'))) {
			/** @var Mo $object Mo object */
			if (empty($user->socid)) {
				print '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=presend&mode=init#formmailbeforetitle">' . $langs->trans('SendMail') . '</a></div>';
			}
			if ($object->status == Mo::STATUS_PRODUCED) {
				print '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=generate_doc_zip">' . $langs->trans('GenerateDocZip') . '</a></div>';
			}
		}
		return $error;
	}

	/**
	 * Overloading the doMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
			foreach ($parameters['toselect'] as $objectid) {
				// Do action on each object id
			}
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}


	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$disabled = 1;

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
			$this->resprints = '<option value="0"'.($disabled ? ' disabled="disabled"' : '').'>'.$langs->trans("BarcodePrintMassAction").'</option>';
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}



	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$object		   	Object output on PDF
	 * @param   string	$action     	'add', 'update', 'view'
	 * @return  int 		        	<0 if KO,
	 *                          		=0 if OK but we want to process standard actions too,
	 *  	                            >0 if OK and we want to replace standard actions.
	 */
	public function beforePDFCreation($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0; $deltemp = array();
		dol_syslog(get_class($this).'::executeHooks action='.$action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}

	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$pdfhandler     PDF builder handler
	 * @param   string	$action         'add', 'update', 'view'
	 * @return  int 		            <0 if KO,
	 *                                  =0 if OK but we want to process standard actions too,
	 *                                  >0 if OK and we want to replace standard actions.
	 */
	public function afterPDFCreation($parameters, &$pdfhandler, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0; $deltemp = array();
		dol_syslog(get_class($this).'::executeHooks action='.$action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
			// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}



	/**
	 * Overloading the loadDataForCustomReports function : returns data to complete the customreport tool
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function loadDataForCustomReports($parameters, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$langs->load("barcodeprint@barcodeprint");

		$this->results = array();

		$head = array();
		$h = 0;

		if ($parameters['tabfamily'] == 'barcodeprint') {
			$head[$h][0] = dol_buildpath('/module/index.php', 1);
			$head[$h][1] = $langs->trans("Home");
			$head[$h][2] = 'home';
			$h++;

			$this->results['title'] = $langs->trans("BarcodePrint");
			$this->results['picto'] = 'barcodeprint@barcodeprint';
		}

		$head[$h][0] = 'customreports.php?objecttype='.$parameters['objecttype'].(empty($parameters['tabfamily']) ? '' : '&tabfamily='.$parameters['tabfamily']);
		$head[$h][1] = $langs->trans("CustomReports");
		$head[$h][2] = 'customreports';

		$this->results['head'] = $head;

		return 1;
	}



	/**
	 * Overloading the restrictedArea function : check permission on an object
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int 		      			  	<0 if KO,
	 *                          				=0 if OK but we want to process standard actions too,
	 *  	                            		>0 if OK and we want to replace standard actions.
	 */
	public function restrictedArea($parameters, &$action, $hookmanager)
	{
		global $user;

		if ($parameters['features'] == 'myobject') {
			if ($user->rights->barcodeprint->myobject->read) {
				$this->results['result'] = 1;
				return 1;
			} else {
				$this->results['result'] = 0;
				return 1;
			}
		}

		return 0;
	}

	/**
	 * Execute action completeTabsHead
	 *
	 * @param   array           $parameters     Array of parameters
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         'add', 'update', 'view'
	 * @param   Hookmanager     $hookmanager    hookmanager
	 * @return  int                             <0 if KO,
	 *                                          =0 if OK but we want to process standard actions too,
	 *                                          >0 if OK and we want to replace standard actions.
	 */
	public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;

		if (!isset($parameters['object']->element)) {
			return 0;
		}
		if ($parameters['mode'] == 'remove') {
			// utilisé si on veut faire disparaitre des onglets.
			return 0;
		} elseif ($parameters['mode'] == 'add') {
			$langs->load('barcodeprint@barcodeprint');
			// utilisé si on veut ajouter des onglets.
			$counter = count($parameters['head']);
			$element = $parameters['object']->element;
			$id = $parameters['object']->id;
			// verifier le type d'onglet comme member_stats où ça ne doit pas apparaitre
			// if (in_array($element, ['societe', 'member', 'contrat', 'fichinter', 'project', 'propal', 'commande', 'facture', 'order_supplier', 'invoice_supplier'])) {
			if (in_array($element, ['context1', 'context2'])) {
				$datacount = 0;

				$parameters['head'][$counter][0] = dol_buildpath('/barcodeprint/barcodeprint_tab.php', 1) . '?id=' . $id . '&amp;module='.$element;
				$parameters['head'][$counter][1] = $langs->trans('BarcodePrintTab');
				if ($datacount > 0) {
					$parameters['head'][$counter][1] .= '<span class="badge marginleftonlyshort">' . $datacount . '</span>';
				}
				$parameters['head'][$counter][2] = 'barcodeprintemails';
				$counter++;
			}
			if ($counter > 0 && (int) DOL_VERSION < 14) {
				$this->results = $parameters['head'];
				// return 1 to replace standard code
				return 1;
			} else {
				// en V14 et + $parameters['head'] est modifiable par référence
				return 0;
			}
		}
	}

	/* Add here any other hooked methods... */

	/* private functions */

}
