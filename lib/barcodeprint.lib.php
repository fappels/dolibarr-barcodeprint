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
