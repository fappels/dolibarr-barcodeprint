# BARCODEPRINT FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Features

Barcode print for 1D GS1 labels and ZPL printing

This module is a base module you can clone/fork to make your own version. No label templating is included, so label content is defined inside the source code.
You can also contact me to make you a verion that suits for your needs.

Two labels are included as an example, Avery-L7160 for A4 sheet printing (Generating A4 pdf sheet) and Zebra-76174 for printing on a ZPL compatible printer.

For sheet generating you set L7160 in module setup, for ZPL printing you set ZPL_76174.

For ZPL printing you can both print to IP of Zebra printer or print to [Zebra's browser print solution](https://www.zebra.com/us/en/support-downloads/software/printer-software/browser-print.html)
To print to IP you set an IP address, empty IP will print to Zebra browser print.

This module use external libs for [ZPL](https://github.com/andersonls/zpl) and [1D GS1](https://github.com/ayeo/gs1_128) generating.

<!--
![Screenshot barcodeprint](img/screenshot_barcodeprint.png?raw=true "BarcodePrint"){imgmd}
-->

Other external modules are available on [Dolistore.com](https://www.dolistore.com).

## Translations

Translations can be completed manually by editing files into directories *langs*.

<!--
This module contains also a sample configuration for Transifex, under the hidden directory [.tx](.tx), so it is possible to manage translation using this service.

For more informations, see the [translator's documentation](https://wiki.dolibarr.org/index.php/Translator_documentation).

There is a [Transifex project](https://transifex.com/projects/p/dolibarr-module-template) for this module.
-->

<!--

## Installation

### From the ZIP file and GUI interface

- If you get the module in a zip file (like when downloading it from the market place [Dolistore](https://www.dolistore.com)), go into
menu ```Home - Setup - Modules - Deploy external module``` and upload the zip file.

Note: If this screen tell you there is no custom directory, check your setup is correct:

- In your Dolibarr installation directory, edit the ```htdocs/conf/conf.php``` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading ```//```) and assign a sensible value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```

### From a GIT repository

- Clone the repository in ```$dolibarr_main_document_root_alt/barcodeprint```

```sh
cd ....../custom
git clone git@github.com:gitlogin/barcodeprint.git barcodeprint
```

### <a name="final_steps"></a>Final steps

From your browser:

  - Log into Dolibarr as a super-administrator
  - Go to "Setup" -> "Modules"
  - You should now be able to find and enable the module

-->

## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and readmes are licensed under GFDL.
