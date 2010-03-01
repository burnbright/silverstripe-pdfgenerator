<?php
/**
 * Sapphire library for handling PDF generation
 */
class PDFGenerator {
	var $data;
	var $fileName;
	var $baseFile;
	var $htmlFile;
	var $pdfFile;
	var $pdfParams	= array(
	'automargins'	=> 0,
	'base_path'	  	=> '/.private',
	'base_url'	  	=> '',
	'compress'		=> 1,
	'cssmedia'		=> 'screen',
	'debugbox'      => null,
	'debugnoclip'   => null,
	'draw_page_border'=> 0,
	'encoding'		=> 'utf-8',
	'footerhtml'	=> '',//$sitename,
	'headerhtml'	=> '',//$sitename,
	'html2xhtml'	=> 1,
	'imagequality_workaround' => null,
	'landscape'     => null,
	'margins'       => array( 'left'=>10,'right'=>10,'top'=>10,'bottom'=>10 ),
	'media'			=> 'A4',
	'method'		=> 'fpdf',
	'mode'			=> 'html',
	'name_html'	  	=> 'in.html',
	'name_pdf'	  	=> 'out.pdf',
	'output'		=> 0, // 0 = open in browser, 1 = prompt save as, 2 = save to path
	'pagewidth'		=> 800,	//1024, 800;
	'pdfversion'	=> 1.5,
	'process_mode'	=> 'single',
	'proxy'			=> null,
	'ps2pdf'        => null,
	'pslevel'       => 1,
	'renderfields'	=> 1,
	'renderforms'	=> 0,
	'renderimages'	=> 1,
	'renderlinks'	=> 1,
	'saveas'		=> 'exported',
	'scalepoints'	=> 1,
	'smartpagebreak'=> 1,
	'transparency_workaround' => null,
	'toc' 			=> null,
	'toc-location'	=> 'before',
	'URL' 		  	=> '/.private/in.html',
	'watermarkhtml'	=> null
	);
	function PDFGenerator() {
		include_once( 'MyNeeds.php' );
	}
	function setData( $data ) {
		$this->data 	= $this->UniClear( $data );
	}
	function setName( $file_name ) {
		$this->fileName = trim( $file_name );
	}
	function setPdfParams() {
		if ( trim( $this->fileName ) == "" ) die( "Please specify FILE NAME to process!" );
		$this->baseFile = preg_replace( '/\\.pdf$/','', $this->fileName );
		$this->htmlFile	= trim( $this->baseFile . '.html' );
		$this->pdfFile 	= trim( basename( $this->baseFile ) );
		$this->pdfParams['base_path'] 	= ASSETS_PATH . "/.private";
		$this->pdfParams['base_url']	= Director::absoluteBaseURL();
		$this->pdfParams['name_html']	= $this->pdfFile . '.html';
		$this->pdfParams['name_pdf']	= $this->pdfFile . '.pdf';
		$this->pdfParams['saveas']		= $this->pdfFile;
		$this->pdfParams['URL'] 		= Director::absoluteBaseURL(). ASSETS_DIR . '/.private/' . $this->pdfFile . '.html';
		if ( trim( $this->pdfParams['base_path'] ) == "" )	die( "Please specify BASE PATH to process!" );
		if ( trim( $this->pdfParams['base_url'] ) == "" ) 	die( "Please specify BASE URL to process!" );
		if ( trim( $this->pdfParams['URL'] ) == "" ) 		die( "Please specify URL to process!" );
		if( !file_exists( $this->pdfParams['base_path'] )) mkdir( $this->pdfParams['base_path'], 02775 );
	}
	function getPDFFromFile() {
		$convert_path	= $this->pdfParams["base_path"]; 
		$name_html 		= $this->pdfParams["name_html"];
		$name_pdf 		= $this->pdfParams["name_pdf"];
		$converted_html	= $convert_path . '/' . $name_html; 
		$converted_pdf	= $convert_path . '/' . $name_pdf; 
		if ( is_file( $converted_html ) ) unlink( $converted_html );
		if ( is_file( $converted_pdf ) ) unlink( $converted_pdf );
		$fh = fopen( $converted_html, "wb") or die("Couldn't open $converted_html for writing" );
		fwrite( $fh, $this->data ) or die("Couldn't write content to $converted_html" );
		fclose( $fh );
		$g_baseurl = trim( $this->pdfParams['URL'] );
		ini_set("user_agent", DEFAULT_USER_AGENT);
		// Add HTTP protocol if none specified
		if ( !preg_match( "/^https?:/", $g_baseurl ) ) $g_baseurl = 'http://' . $g_baseurl;
		$g_css_index = 0;
		// Title of styleshee to use (empty if no preferences are set)
		$g_stylesheet_title = "";
		$proxy = $this->pdfParams['proxy'];
		// validate input data
		// if ( $this->pdfParams['pagewidth'] == 0) die("Please specify non-zero value for the pixel width!");
		// begin processing
		$g_media = Media::predefined( $this->pdfParams['media'] );
		$g_media->set_landscape( $this->pdfParams['landscape'] );
		$g_media->set_margins( $this->pdfParams['margins'] );
		$g_media->set_pixels( $this->pdfParams['pagewidth'] );
		// Initialize the coversion pipeline
		$pipeline = new Pipeline();
		$pipeline->configure( $this->pdfParams );
		if ( extension_loaded('curl') ) {
			require_once( HTML2PS_DIR . 'fetcher.url.curl.class.php' );
			$pipeline->fetchers = array( new FetcherUrlCurl() );
			if ( $proxy != '' ) $pipeline->fetchers[0]->set_proxy( $proxy );
		}
		else {
			require_once( HTML2PS_DIR . 'fetcher.url.class.php' );
			$pipeline->fetchers[] = new FetcherURL();
		}
		$pipeline->data_filters[] = new DataFilterDoctype();
		$pipeline->data_filters[] = new DataFilterUTF8( $this->pdfParams['encoding'] );
		$pipeline->data_filters[] = ( $this->pdfParams['html2xhtml'] ? new DataFilterHTML2XHTML() : new DataFilterXHTML2XHTML() );
		$pipeline->parser = new ParserXHTML();
		$pipeline->pre_tree_filters = array();
		$header_html    = $this->pdfParams['headerhtml'];
		$footer_html    = $this->pdfParams['footerhtml'];
		$filter = new PreTreeFilterHeaderFooter( $header_html, $footer_html );
		$pipeline->pre_tree_filters[] = $filter;
		if ( $this->pdfParams['renderfields'] ) $pipeline->pre_tree_filters[] = new PreTreeFilterHTML2PSFields();
		$pipeline->layout_engine = ( $this->pdfParams['method'] === 'ps' ? new LayoutEnginePS() : new LayoutEngineDefault() );
		$pipeline->post_tree_filters = array();
		$image_encoder = ( $this->pdfParams['pslevel'] == 3 ? new PSL3ImageEncoderStream() : new PSL2ImageEncoderStream() );
		switch ( $this->pdfParams['method'] ) {
			case 'fastps':	$pipeline->output_driver = ( $this->pdfParams['pslevel'] == 3 ? new OutputDriverFastPS( $image_encoder ) : new OutputDriverFastPSLevel2( $image_encoder ) );
				break;
			case 'pdflib':	$pipeline->output_driver = new OutputDriverPDFLIB16( $this->pdfParams['pdfversion'] );
				break;
			case 'fpdf':	$pipeline->output_driver = new OutputDriverFPDF();
				break;
			case 'png':	 	$pipeline->output_driver = new OutputDriverPNG();
				break;
			case 'pcl':		$pipeline->output_driver = new OutputDriverPCL();
				break;
			default:		die("Unknown output method");
		}
		$watermark_text = trim( $this->pdfParams['watermarkhtml'] );
		if ( $watermark_text != '' ) $pipeline->add_feature( 'watermark', array('text' => $watermark_text) );
		if ( $this->pdfParams['debugbox'] )	$pipeline->output_driver->set_debug_boxes(true);
		if ( $this->pdfParams['draw_page_border'] ) $pipeline->output_driver->set_show_page_border(true);
		if ( $this->pdfParams['ps2pdf'] ) $pipeline->output_filters[] = new OutputFilterPS2PDF( $this->pdfParams['pdfversion'] );
		if ( $this->pdfParams['compress'] && $this->pdfParams['method'] == 'fastps' ) $pipeline->output_filters[] = new OutputFilterGZip();
		//$file_name = ( get_var( 'process_mode', $parameters ) == 'batch' ? "batch" : "index" ); //$g_baseurl );
		$file_name	= trim( $this->pdfParams['saveas'] );
		$_path 		= trim( $this->pdfParams['base_path'] );
		switch ( $this->pdfParams['output'] ) {
			case 0: $pipeline->destination = new DestinationBrowser( $file_name );
				break;
			case 1: $pipeline->destination = new DestinationDownload( $file_name );
				break;
			//case 2: $pipeline->destination = new DestinationFile( $file_name, 'File saved as: <a href="%link%">%name%</a>' );
			case 2: $pipeline->destination = new DestinationFile2( $file_name, $_path );
				break;
		}
		if ( $this->pdfParams['toc'] ) $pipeline->add_feature( 'toc', array('location'=> $this->pdfParams['toc-location'] ? $this->pdfParams['toc-location'] : 'after') );
		if ( $this->pdfParams['automargins'] ) $pipeline->add_feature( 'automargins', array() );
		$time = time();
		if ( $this->pdfParams['process_mode'] == 'batch' ) {
			$batch = $this->pdfParams['batch'];
			for ( $i=0; $i < count( $batch ); $i++) {
				if ( trim( $batch[$i] ) != "" ) {
					if ( !preg_match( "/^https?:/", $batch[$i]) ) $batch[$i] = "http://" . $batch[$i];
				}
			}
			$status = $pipeline->process_batch( $batch, $g_media );
		}
		else {
			$status = $pipeline->process( $g_baseurl, $g_media );
		}
		//error_log( sprintf( "Processing of '%s' completed in %u seconds", $g_baseurl, time() - $time ) );
		if ( $status == null ) {
			print( $pipeline->error_message() );			//error_log( "Error in conversion pipeline" );
			die();
		}
		if ( $this->pdfParams['output'] == 2 ) return $status;
	}
	function getPDFFromMemory() {
		$base_path = trim( $this->pdfParams['base_path'] );
		$base_url = trim( $this->pdfParams['base_url'] );
		$name_pdf = trim( $this->pdfParams['name_pdf'] );
		ini_set("user_agent", DEFAULT_USER_AGENT);
		$pipeline = PipelineFactory::create_default_pipeline( $this->pdfParams['encoding'], '');
		$pipeline->fetchers[] = new MyFetcher( $this->data, $base_url );
		$pipeline->destination = new MyDestination( $base_path . $name_pdf );
		$baseurl = '';
		$media = Media::predefined( $this->pdfParams['media'] );
		$media->set_landscape( $this->pdfParams['landscape'] );
		$media->set_margins( $this->pdfParams['margins'] );
		$media->set_pixels( $this->pdfParams['pagewidth'] );
		$pipeline->configure( $this->pdfParams );
		$pipeline->data_filters[] = new DataFilterDoctype();
		$pipeline->data_filters[] = new DataFilterUTF8( $this->pdfParams['encoding'] );
		$pipeline->data_filters[] = ( $this->pdfParams['html2xhtml'] ? new DataFilterHTML2XHTML() : new DataFilterXHTML2XHTML() );
		$pipeline->parser = new ParserXHTML();
		$pipeline->pre_tree_filters = array();
		$footer_html    = $this->pdfParams['footerhtml'];
		$header_html    = $this->pdfParams['headerhtml'];
		$filter = new PreTreeFilterHeaderFooter( $header_html, $footer_html );
		$pipeline->pre_tree_filters[] = $filter;
		if ( $this->pdfParams['renderfields'] ) $pipeline->pre_tree_filters[] = new PreTreeFilterHTML2PSFields();
		$pipeline->layout_engine = ( $this->pdfParams['method'] === 'ps' ? new LayoutEnginePS() : new LayoutEngineDefault() );
		$pipeline->post_tree_filters = array();
		$image_encoder = ( $this->pdfParams['pslevel'] == 3 ? new PSL3ImageEncoderStream() : new PSL2ImageEncoderStream() );
		switch ( $this->pdfParams['method'] ) {
			case 'fastps':	$pipeline->output_driver = ( $this->pdfParams['pslevel'] == 3 ? new OutputDriverFastPS( $image_encoder ) : new OutputDriverFastPSLevel2( $image_encoder ) );
				break;
			case 'pdflib':	$pipeline->output_driver = new OutputDriverPDFLIB16( $this->pdfParams['pdfversion'] );
				break;
			case 'fpdf':	$pipeline->output_driver = new OutputDriverFPDF();
				break;
			case 'png':		$pipeline->output_driver = new OutputDriverPNG();
				break;
			case 'pcl':		$pipeline->output_driver = new OutputDriverPCL();
				break;
			default:		die("Unknown output method");
		}
		$watermark_text = $this->pdfParams['watermarkhtml'];
		if ( $watermark_text != '' ) $pipeline->add_feature( 'watermark', array( 'text' => $watermark_text ) );
		if ( $this->pdfParams['debugbox'] ) $pipeline->output_driver->set_debug_boxes( true );
		if ( $this->pdfParams['draw_page_border'] ) $pipeline->output_driver->set_show_page_border( true );
		if ( $this->pdfParams['ps2pdf'] ) $pipeline->output_filters[] = new OutputFilterPS2PDF( $this->pdfParams['pdfversion'] );
		if ( $this->pdfParams['compress'] && $this->pdfParams['method'] == 'fastps' ) $pipeline->output_filters[] = new OutputFilterGZip();
		//$filename = ( get_var( 'process_mode', $parameters ) == 'batch' ? "batch" : "index" ); //$g_baseurl );
		$filename = trim( $this->pdfParams['saveas'] );
		switch ( $this->pdfParams['output'] ) {
			case 0:	$pipeline->destination = new DestinationBrowser( $filename );
				break;
			case 1:	$pipeline->destination = new DestinationDownload( $filename );
				break;
			case 2:	$pipeline->destination = new DestinationFile( $filename, 'File saved as: <a href="%link%">%name%</a>' );
				break;
		}
		if ( $this->pdfParams['toc'] ) $pipeline->add_feature( 'toc', array( 'location'=>isset( $this->pdfParams['toc-location'] ) ? $this->pdfParams['toc-location'] : 'after' ) );
		if ( $this->pdfParams['automargins'] ) $pipeline->add_feature( 'automargins', array() );
		$time = time();
		//error_log( sprintf( "Processing of '%s' completed in %u seconds", $g_baseurl, time() - $time ) );
		$status = $pipeline->process_batch( array( $baseurl ), $media );
		if ( $status == null ) {
			print( $pipeline->error_message() );		//error_log( "Error in conversion pipeline" );
			die();
		}
		$pipeline->process_batch( array( $baseurl ), $media );
	}
	/**
	* param 1 => show page from memory
	* param 2 => save to file before show
	* 
	* @param init $do
	*/
	function getPDF( $do = 1 ) {
		$this->setPdfParams();
		if ( $do == 1 ) {
			$this->getPDFFromMemory();
		}
		else {
			$this->getPDFFromFile();
		}
		exit();
	}
	function UniClear( $content ){
		preg_match_all( "/[\x{90}-\x{3000}]/u", $content, $matches );
		foreach( $matches[0] as $match ) $content = str_replace( $match, mb_convert_encoding( $match, "HTML-ENTITIES","UTF-8"), $content );
		return $content;
	}
}
?>