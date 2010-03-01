<?php

class html2pdf {
	
	var $atts = array();
	
	var $siteRoot;
	var $html2psPath;
	var $tempPath;
	var $outputPath;
	
	var $output = 'client';
	
	function html2pdf() {
	
		$this->atts['pixels'] = 1024;
		$this->atts['scalepoints'] = true;
		$this->atts['renderimages'] = true;
		$this->atts['renderlinks'] = true;
		$this->atts['media'] = 'A4';
		$this->atts['cssmedia'] = 'screen';
		$this->atts['leftmargin'] = 10;
		$this->atts['rightmargin'] = 10;
		$this->atts['topmargin'] = 10;
		$this->atts['bottommargin'] = 10;
		$this->atts['landscape'] = false;
		$this->atts['pageborder'] = false;
		$this->atts['debugbox'] = false;
		$this->atts['encoding'] = NULL;
		$this->atts['method'] = 'fpdf';
		$this->atts['pdfversion'] = 1.3;
		$this->atts['compress'] = true;
		$this->atts['transparency_workaround'] = false;
		$this->atts['imagequality_workaround'] = false;
		
		$this->siteRoot = '../'; // relative path from the page calling this class to the root of your site
		$this->html2psPath = 'html2ps/'; // relative path from the root of your site to the folder that contains html2ps.php
		$this->tempPath = 'temp/'; // relative path from the root of your site to the folder where the html string temporarily saved
		$this->outputPath = 'output/'; // relative path from the root of your site to the folder where the pdf will be saved
		$this->outputFile = 'mypdf'; // pdf file name
	
	}
	
	function createPDF($data) {
	
		// all the code from here to the next comment basically take the
		// siteRoot root relative path, and the absolute script path from
		// the SCRIPT_NAME enviroment variable, and work out what the absolute
		// path to the root of the site should be
		
		$hostName = $_SERVER['SERVER_NAME'];
		
		$scriptPath = getenv('SCRIPT_NAME');
		$scriptPath = dirname($scriptPath);
		$scriptPath = explode('/', $scriptPath);
		$levelsUp = substr_count($this->siteRoot, '../');
		
		$newPath = NULL;
		foreach($scriptPath as $key => $value) {
		if($key < count($scriptPath) - $levelsUp) {
		$newPath .= $value.'/';
		}
		}
		
		// these are the paths used in the script:
		$scr2fnc = $this->siteRoot.$this->html2psPath; // relative: the script calling this class to the html2ps folder
		$scr2tmp = $this->siteRoot.$this->tempPath; // relative: the script calling this class to the temp folder
		$scr2out = $this->siteRoot.$this->outputPath; // relative: the script calling this class to the output folder
		$rem2tmp = 'http://'.$hostName.$newPath.$this->tempPath; // absolute: the remote path to the temp folder *
		$rem2fnc = 'http://'.$hostName.$newPath.$this->html2psPath; // absolute: the remote path to the html2ps folder **
		
		// * this is because i dont know how to work out the relative path
		// from the page calling this class to the html2ps folder, and
		// because html2ps automatically sticks http:// at the begining of
		// the url if it dosent have it already, so relative paths dont work
		
		// ** this is because you cant use a relative path in file_get_contents,
		// if you do, php will read the file server side, and not actually
		// send the http request to it
		
		
		// write the data to a file, because html2ps will only read a remote
		// file, you cant send it a string
		$file = fopen($scr2tmp.'temp.html', 'w');
		fwrite($file, $data);
		fclose($file);
		
		// check the request attributes
		foreach($this->atts as $key => $value) {
		
		if(is_bool($value) && $value == true) {
		$this->atts[$key] = 1;
		}
		if(is_bool($value) && $value == false) {
		unset($this->atts[$key]);
		}
		else if(is_null($value)) {
		$this->atts[$key] = '';
		}
		
		}
		
		// create the request url
		$this->atts['URL'] = $rem2tmp.'temp.html';
		$urlString = http_build_query($this->atts);
		$url = $rem2fnc.'html2ps.php?'.$urlString;
		
		// request the pdf
		switch ($this->output) {
		
		case 'client':
		$url .= '&output=0';
		header("Content-type: application/pdf");
		echo file_get_contents($url);
		break;
		
		case 'server':
		$url .= '&output=1';
		$output = file_get_contents($url);
		$pdf = fopen($scr2out.$this->outputFile.'.pdf', 'w');
		fwrite($pdf, $output);
		fclose($pdf);
		break;
		
		}
		
		// delete the tempoary file
		unlink($scr2tmp.'temp.html');
	
	}

}

/*------------------------------------------------------------*/
/*------------------------------------------------------------*/

$data = file_get_contents('sample.html'); // this would be your string that contains the html

$pdf = new html2pdf;
$pdf->createPDF($data);

?>