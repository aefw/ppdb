<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$phpVersion = phpversion();
if (version_compare($phpVersion, '7.1', '>=')) {
    if (version_compare($phpVersion, '8.1', '<')) {
        // PHP 7.1 hingga PHP 8.0
		require_once(SYSTEMPATH . 'libraries/external/php74/PHPWord/vendor/autoload.php');
    } else {
        // PHP 8.1 ke atas
		require_once(SYSTEMPATH . 'libraries/external/php81/PHPWord/vendor/autoload.php');
    }
} else {
	show_error('Internal Server Error', "Versi PHP tidak didukung. Versi PHP yang digunakan adalah: $phpVersion");
}

class PHPWord {
	function __construct() {
	}
}

if( !function_exists('PhpWord_PhpWord') ){
	function PhpWord_PhpWord($source = NULL) {
		if( $source == NULL )
			return new \PhpOffice\PhpWord\PhpWord();
		else
			return \PhpOffice\PhpWord\IOFactory::load($source);
	}
}

if( !function_exists('PhpWord_TemplateProcessor') ){
	function PhpWord_TemplateProcessor($filename) {
		return new \PhpOffice\PhpWord\TemplateProcessor($filename);
	}
}
