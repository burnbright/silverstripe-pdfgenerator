<?php
/**
 * SSAssetsDestination
 * Handles the saving generated PDF to user-defined output file on server.
 */
class SSAssetsDestination extends DestinationFile2 {
	
	var $_dest_filename;
	
	function __construct($filename, $path = null) {
		parent::__construct($filename,$path);
		$this->_path = ASSETS_PATH."/".$path;
		if( !file_exists( $this->_path )) mkdir( $this->_path , 02775 );
	}
	
}

?>
