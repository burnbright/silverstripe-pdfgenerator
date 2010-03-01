<?php
/**
 * SSTemplateFetcher
 * Html2ps custom Fetcher class for getting content from a template.
 * 
 */

class SSTemplateFetcher extends Fetcher {
	
	var $base_path;
	var $content;
	
	function __construct( $content, $base_path ) {
		$this->content   = $content;
		$this->base_path = $base_path;
	}
	
	function get_data( $url = '' ) {
		if ( !$url ) {
			return new FetchedDataURL( $this->content, array(), "" );
		}
		else {
			if ( substr( $url, 0, 8 ) == 'file:///' ) {
				$url = substr( $url, 8 );
				if ( PHP_OS == "WINNT" ) $url = substr( $url, 1 );
			}
			return new FetchedDataURL( @file_get_contents( $url ), array(), "" );
		}
	}
	
	function get_base_url() {
		//return '';
		return 'file:///' . $this->base_path . '/dummy.html';
	}
}
?>
