<?php
/**
 * Sapphire library for handling PDF generation
 * 
 * This solution uses the html2pdf library.
 * Website: http://www.tufat.com/s_html2ps_html2pdf.htm
 */
class BurnbrightPDFGenerator {
	
	protected $data; //HTML content that will be converted to pdf
	protected $footerhtml	= '';//$sitename;
	protected $headerhtml	= '';//$sitename,	
	
	protected $outputFilename = "output";
	protected $inputFilename = null;
	
	protected $base_path = '.private'; //path where pdfs will be stored (dont include a trailing slash)
		
	protected $compress= true;
	protected $pdfversion	= 1.5;
	
	protected $media= 'A4';
	protected $automargins	= false;
	protected $margins = array( 'left'=>10,'right'=>10,'top'=>10,'bottom'=>10 );
	protected $landscape  = null;
	protected $pagewidth = 800;	//1024, 800;
	
	protected $debug = false;

	protected $draw_page_border = 0;
	protected $encoding= 'utf-8';

	protected $html2xhtml	= 1;
	protected $imagequality_workaround = null;
	
	static $method = 'fpdf'; //type of output driver: fpdf, fastps, pdflib, png, pcl //TODO: make this a static
	protected $mode = 'html';
	
	protected $outputtype = 'path'; // browser (render), download (prompt save-as), path (on server), asset (on server in assets dir)
	
	protected $process_mode = 'single';
	protected $proxy = null;
	protected $ps2pdf = null;
	protected $pslevel       = 1;
	protected $renderfields	= 1;
	protected $renderforms	= 0;
	protected $renderimages	= 1;
	protected $renderlinks	= 1;
	protected $scalepoints	= 1;
	protected $smartpagebreak= 1;
	protected $transparency_workaround = null;
	
	protected $toc = null;
	protected $toc_location = 'before';
	
	protected $watermarkhtml = null;
	
	//TODO: refactor these to class variables above
	var $pdfParams	= array(

		'html2xhtml'	=> 1,
		'imagequality_workaround' => null,
		'landscape'     => null,
		'margins'       => array( 'left'=>10,'right'=>10,'top'=>10,'bottom'=>10 ),
		'media'			=> 'A4',
		'mode'			=> 'html',

		'pagewidth'	=> 800,	//1024, 800;
		'pdfversion'	=> 1.5,
		'process_mode'	=> 'single',
		'proxy'			=> null,
		'ps2pdf'        => null,
		'pslevel'       => 1,
		'renderfields'	=> 1,
		'renderforms'	=> 0,
		'renderimages'	=> 1,
		'renderlinks'	=> 1,
		'scalepoints'	=> 1,
		'smartpagebreak'=> 1,
		'transparency_workaround' => null,
		'toc' 			=> null,
		'toc-location'	=> 'before',
		'watermarkhtml'	=> null
	);
	
	function __construct() {
		if ( ini_get("memory_limit") < 33554432 ) ini_set("memory_limit", "256M");
		if ( ini_get("pcre.backtrack_limit") < 1000000) ini_set("pcre.backtrack_limit",1000000);
		@set_time_limit(10000);
		
		//load up html2ps library

		require_once( 'html2ps/config.inc.php' );
		require_once( HTML2PS_DIR . 'pipeline.factory.class.php' );
		parse_config_file( HTML2PS_DIR . "html2ps.xml" );
		
		//load custom classes
		require_once( 'extras/SSTemplateFetcher.php' );
		require_once( 'extras/SSAssetsDestination.php' );

	}
	
	function set_output_method($m){
		self::$method = $m;
	}
	
	/*
	 * Set the content
	 */
	function setData( $data ) {
		$this->data = $this->UniClear( $data );
	}
	
	/**
	 * Save a block of HTML to pdf in file
	 */
	function sendToFile($html,$filename,$assetsfolder = null, $useassetfolder = true, $overwrite = true){
		$this->setData($html);
		
		if($useassetfolder){
			$this->outputtype = 'asset';
		}else{
			$this->outputtype = 'path';			
		}
		
		$this->outputFilename = $filename;
		
		if($assetsfolder) $this->base_path = $assetsfolder;
		
		$pipe = $this->generate(); //generate pdf
		$filename = $pipe->destination->get_filename().".pdf";
		$folder = Folder::findOrMake($this->base_path);
		$fileid = $folder->constructChild($filename);
		//TODO: remove existing database entry, or disallow overwrite (make optional)
		
		return $fileid;
	}
	
	/**
	 * Save block of HTML to file, and then send contents to browser.
	 */
	function sendToBrowser($html,$filename,$filepath = '.private'){
		
		//render to browser
		$file = $this->sendToFile($html,$filename,$filepath);
		$filename = $file->getFilename();
		
		$response = SS_HTTPRequest::send_file(file_get_contents($filename), basename($filename), 'application/pdf');
		$response->output();
	}
		
	function setParam($name,$param){
		$this->pdfParams[$name] = $param;
	}


	/*
	 * Does the actual pdf generation and setup
	 * 
	 * API docs:
	 * http://www.tufat.com/docs/html2ps/api.html
	 * 
	 */	
	function generate() {
		
		//create base pipeline
		$pipeline = PipelineFactory::create_default_pipeline( $this->encoding, '');
		
		/*
		 Fetcher - interface provides a method of fetching the data required to build a document tree.
		 
		 Normally, classes implementing this interface would fetch an HTML/XHTML string from somewhere 
		 (e.g. from remove HTTP server, local file or database). Nevertheless, it MAY fetch ANY data provided
		 that this data will be understood by parser. The pipeline object may contain several fetcher objects;
		 in this case they're used one-by-one until one of them return non-null value.

		It is assumed that if you need to get data from non-standard places (e.g. from template engine or database),
		you should implement Fetcher in your own class.

		Note that the get_data method returns the FetchedData object (or one of its descendants) instead of HTML string! 
		 */
		$pipeline->fetchers[] = new SSTemplateFetcher( $this->data, Director::absoluteBaseURL());
		
		$pipeline->destination = $this->getDestination();		
		
		$baseurl = '';
		$media = Media::predefined( $this->pdfParams['media'] );
		$media->set_landscape( $this->pdfParams['landscape'] );
		$media->set_margins( $this->pdfParams['margins'] );
		$media->set_pixels( $this->pdfParams['pagewidth'] );
		$pipeline->configure( $this->pdfParams );
		
		
		//DataFilter interface describes the filters modifying the raw input data. 
		//The main purpose of these filters is to fix the raw data so that it can be processed by parser without errors. 
		$pipeline->data_filters[] = new DataFilterDoctype();
		$pipeline->data_filters[] = new DataFilterUTF8( $this->encoding);
		$pipeline->data_filters[] = ( $this->pdfParams['html2xhtml'] ? new DataFilterHTML2XHTML() : new DataFilterXHTML2XHTML() );
		
		//Parser interface provides a method of building the DOM tree from the filtered data. 
		$pipeline->parser = new ParserXHTML(); // set the parser
		
		//PreTreeFilter - interface describes a procedure of document tree transformation executed before the layout engine starts. 
		//set up header and footer content
		$pipeline->pre_tree_filters = array();
		$footer_html    = $this->footerhtml;
		$header_html    = $this->headerhtml;
		$filter = new PreTreeFilterHeaderFooter( $header_html, $footer_html );
		$pipeline->pre_tree_filters[] = $filter;
		if ( $this->pdfParams['renderfields'] )
			$pipeline->pre_tree_filters[] = new PreTreeFilterHTML2PSFields();
		
		//LayoutEngine - interface of a class processing of the document tree and calculating positions of page elements.
		//In theory, different implementations of this interface will allow us to use "lightweight" layout engines in case
		//we do not need full HTML/CSS support. 
		$pipeline->layout_engine = ( self::$method === 'ps' ? new LayoutEnginePS() : new LayoutEngineDefault() );
		$pipeline->post_tree_filters = array();
		$image_encoder = ( $this->pdfParams['pslevel'] == 3 ? new PSL3ImageEncoderStream() : new PSL2ImageEncoderStream() );
		
		/*
		 OutputDriver - interface contains device-specific functions - drawing, movement, fonts selection, etc.
		 In general, description of this interface is beyond the scope of this document, as users are not intended to
		 implement this interface themselves. Instead, they would use pre-defined output drivers described below. 
		 */
		switch (self::$method) {
			case 'fastps':	
				$pipeline->output_driver = ( $this->pdfParams['pslevel'] == 3 ? new OutputDriverFastPS( $image_encoder ) : new OutputDriverFastPSLevel2( $image_encoder ) );
				break;
			case 'pdflib':	
				$pipeline->output_driver = new OutputDriverPDFLIB16( $this->pdfParams['pdfversion'] );
				break;
			case 'fpdf':
				$pipeline->output_driver = new OutputDriverFPDF();
				break;
			case 'png':
				$pipeline->output_driver = new OutputDriverPNG();
				break;
			case 'pcl':
				$pipeline->output_driver = new OutputDriverPCL();
				break;
			default:
				die("Unknown output method");
		}
		
		//watermark
		$watermark_text = $this->pdfParams['watermarkhtml'];
		if ( $watermark_text != '' ) $pipeline->add_feature( 'watermark', array( 'text' => $watermark_text ) );
		
		if ( $this->debug ) $pipeline->output_driver->set_debug_boxes( true );
		
		if ( $this->draw_page_border) $pipeline->output_driver->set_show_page_border( true );
		
		if ( $this->pdfParams['ps2pdf'] ) $pipeline->output_filters[] = new OutputFilterPS2PDF( $this->pdfParams['pdfversion'] );
		
		if ( $this->compress && self::$method == 'fastps' ) $pipeline->output_filters[] = new OutputFilterGZip();
		
		if ( $this->pdfParams['toc'] ) $pipeline->add_feature( 'toc', array( 'location'=>isset( $this->pdfParams['toc-location'] ) ? $this->pdfParams['toc-location'] : 'after' ) );
		
		if ( $this->automargins) $pipeline->add_feature( 'automargins', array() );
		
		$status = $pipeline->process_batch( array( $baseurl ), $media ); //create the PDF
		
		if ( $status == null ) {
			die($pipeline->error_message());
		}
		
		return $pipeline;
	}
	
	/*
	 * Destination interface describes the "channel" object which determines where the final output file should be placed.
	 */
	protected function getDestination(){
			
		$filename = $this->outputFilename;
		$path = $this->base_path."/";
		
		switch ($this->outputtype) {
			case 'browser':	
				return new DestinationBrowser( $filename );
				break;
			case 'download':
				return new DestinationDownload( $filename );
				break;
			case 'path':	
				return new DestinationFile2( $filename,  $path);
				break;
			case 'asset':
				return new SSAssetsDestination($filename, $path);
				break;
		}
		die('unknown destination type');		
	}
	
	/*
	 * Removes something...?
	 */
	protected function UniClear( $content ){
		preg_match_all( "/[\x{90}-\x{3000}]/u", $content, $matches );
		foreach( $matches[0] as $match ) $content = str_replace( $match, mb_convert_encoding( $match, "HTML-ENTITIES","UTF-8"), $content );
		return $content;
	}
	
}
?>