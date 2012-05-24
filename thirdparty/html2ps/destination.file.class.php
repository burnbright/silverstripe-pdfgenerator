<?php
class DestinationFile extends Destination {
  var $_link_text;

  function DestinationFile($filename, $link_text = null) {
	$this->Destination($filename);
	$this->_link_text = $link_text;
  }
  function process($tmp_filename, $content_type) {
	$dest_filename = OUTPUT_FILE_DIRECTORY.$this->filename_escape($this->get_filename()).".".$content_type->default_extension;
	copy($tmp_filename, $dest_filename);
	$text = $this->_link_text;
	$text = preg_replace('/%link%/', 'file://'.$dest_filename, $text);
	$text = preg_replace('/%name%/', $this->get_filename(), $text);
	print $text;
  }
}
class DestinationFile2 extends Destination {
  var $_path;

  function DestinationFile2($filename, $path = null) {
	$this->Destination($filename);
	$this->_path = $path;
  }
  function process($tmp_filename, $content_type) {
	$_filename = $this->filename_escape($this->get_filename()).".".$content_type->default_extension; 
	$dest_filename = OUTPUT_FILE_DIRECTORY.$_filename;
	copy($tmp_filename, $dest_filename);
	$src_filename = $this->_path . $_filename;
	copy($dest_filename, $src_filename );
	unlink($dest_filename );
	return 1;
  }
}
?>