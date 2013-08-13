<?php
/**
 * Importer inferface declaration
 *
 * @author msardu
 */
interface copycatImporter {
  
  public function login();
  
  public function index($params);
  
  public function node_retrieve($nid);
  
  public function taxonomy_retrieve($tid);
  
  public function file_retrieve($fid); 
   
  
}

?>
