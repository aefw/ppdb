<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$phpVersion = phpversion();
if (version_compare($phpVersion, '7.1', '>=')) {
    if (version_compare($phpVersion, '8.1', '<')) {
        // PHP 7.1 hingga PHP 8.0
		require_once(SYSTEMPATH . 'libraries/external/php74/PHPspreadsheet/vendor/autoload.php');
    } else {
        // PHP 8.1 ke atas
		require_once(SYSTEMPATH . 'libraries/external/php81/PHPspreadsheet/vendor/autoload.php');
    }
} else {
	show_error('Internal Server Error', "Versi PHP tidak didukung. Versi PHP yang digunakan adalah: $phpVersion");
}

class PHPspreadsheet {
	function __construct() {
	}
}

if( !function_exists('PhpSpreadsheet_Spreadsheet') ){
	function PhpSpreadsheet_Spreadsheet() {
		return new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	}
}

if( !function_exists('PhpSpreadsheet_Cell_DataType') ){
	function PhpSpreadsheet_Cell_DataType($type) {
		if( preg_match('/TYPE_STRING/i', $type) ) return \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
		else return \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
	}
}

if( !function_exists('PhpSpreadsheet_Style_Alignment') ){
	function PhpSpreadsheet_Style_Alignment($type) {
		if( preg_match('/HORIZONTAL_CENTER/i', $type) )    return \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER;
		elseif( preg_match('/HORIZONTAL_LEFT/i', $type) )  return \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT;
		elseif( preg_match('/HORIZONTAL_RIGHT/i', $type) ) return \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT;
		elseif( preg_match('/VERTICAL_CENTER/i', $type) )  return \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER;
		elseif( preg_match('/VERTICAL_TOP/i', $type) )     return \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP;
		else return \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT;
	}
}

if( !function_exists('PhpSpreadsheet_Style_NumberFormat') ){
	function PhpSpreadsheet_Style_NumberFormat($type) {
		if( preg_match('/FORMAT_TEXT/i', $type) ) return \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT;
		else return \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT;
	}
}

if( !function_exists('PhpSpreadsheet_Writer_Xlsx') ){
	function PhpSpreadsheet_Writer_Xlsx($objExcel) {
		return new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objExcel);
	}
}

if( !function_exists('PhpSpreadsheet_IOFactory_createReader') ){
	function PhpSpreadsheet_IOFactory_createReader($type) {
		return \PhpOffice\PhpSpreadsheet\IOFactory::createReader($type);
	}
}

if( !function_exists('PhpSpreadsheet_Shared_Date_ToTimestamp') ){
	function PhpSpreadsheet_Shared_Date_ToTimestamp($value) {
		if ( method_exists($value, 'getCalculatedValue') && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($value) ) {
			return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($value);
		}elseif( method_exists($value, 'getCalculatedValue') && $value->getDataType() == 'n' && preg_match('/^[\d]+$/i', $value->getCalculatedValue()) ) {
			$unixDate = ($value->getCalculatedValue() - 25569) * 86400;
			return $unixDate;
		}elseif( method_exists($value, 'getCalculatedValue') && preg_match('/^[\d]{4}-[\d]{2}-[\d]{2}$/i', $value->getCalculatedValue()) ) {
			return strtotime($value->getCalculatedValue());
		}elseif( preg_match('/^[\d]{4}-[\d]{2}-[\d]{2}$/i', $value) ) {
			return strtotime($value);
		}
		return $value;
	}
}

if( !function_exists('PhpSpreadsheet_Worksheet_Drawing') ){
	function PhpSpreadsheet_Worksheet_Drawing() {
		return new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
	}
}

/*
[using composer]
./composer.phar require phpoffice/phpspreadsheet
./composer.phar require phpoffice/phpspreadsheet --no-cache
[download manual]
https://php-download.com/package/phpoffice/phpspreadsheet


[Migration Upload]

$this->load->libraries('PHPspreadsheet');
$config_Excel = get_config('PHPspreadsheet');

if( in_array(strtolower((string) $extension),array('xlsx')) ) {
	$objReader = new \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xlsx");
	$objReader->setReadDataOnly(true);

PHPExcel_Cell_DataType       to \PhpOffice\PhpSpreadsheet\Cell\DataType
PHPExcel_Style_Alignment     to \PhpOffice\PhpSpreadsheet\Style\Alignment
PHPExcel_Style_NumberFormat  to \PhpOffice\PhpSpreadsheet\Style\NumberFormat

[Migration Writer]

$this->load->libraries('PHPspreadsheet');
$config_Excel = get_config('PHPspreadsheet');

$config_PHPExcel to $config_Excel

$FILE_NAME = 'excel.xlsx';
$FILE_NAME = strtolower((string) $FILE_NAME);
$objExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

$_PHPExcel_row   to $_Excel_row
$objPHPExcel     to $objExcel

//*
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$FILE_NAME ");
$objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objExcel);
$objWriter->save('php://output');
//*/





/*
new \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xlsx") to PhpSpreadsheet_IOFactory_createReader("Xlsx")
new \PhpOffice\PhpSpreadsheet\Spreadsheet()                   to PhpSpreadsheet_Spreadsheet()

\PhpOffice\PhpSpreadsheet\Cell\DataType::([\w]+)              to PhpSpreadsheet_Cell_DataType('$1')
\PhpOffice\PhpSpreadsheet\Style\Alignment::([\w]+)            to PhpSpreadsheet_Style_Alignment('$1')
\PhpOffice\PhpSpreadsheet\Style\NumberFormat::([\w]+)         to PhpSpreadsheet_Style_NumberFormat('$1')
PHPExcel_Shared_Date::ExcelToPHP\(([\s]+)?\$value([\s]+)?\)   to PhpSpreadsheet_Shared_Date_ToTimestamp($cell)

new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objExcel)          to PhpSpreadsheet_Writer_Xlsx($objExcel)

//*
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$FILE_NAME ");
$objWriter = PhpSpreadsheet_Writer_Xlsx($objExcel);
$objWriter->save('php://output');
//*/

/*
$objExcel = PhpSpreadsheet_Spreadsheet();
$objExcel->getProperties()
	->setCreator("AEFW")
	->setLastModifiedBy("AE Framework")
	->setTitle("Office XLSX Document")
	->setSubject("Office XLSX Document")
	->setDescription("document for Office XLSX, generated using AEFW PHP classes.")
	->setKeywords("office openxml php")
	->setCategory("Result file data");

/**/
