<?php

/**
 * Description of copycatTask
 *
 * @author msardu
 */
class copycatTask {

  public $importer;
  private $refresh_list;
  public $force_update;
  public $purgeOrphanNodes;
  public $startingTime;
  public $alterations;
  public $source;
  public $destination;
  private $target_image_dir;
  private $settings;

  function __construct($importer, $settings) {
    $this->importer = $importer;
    $this->refresh_list = $settings['refresh_list_minutes'];
    $this->alterations = $settings['field_map'];
    $this->source = $settings['source_structure'];
    $this->destination = $settings['target_structure'];
    $this->target_image_dir = $settings['target_image_dir'];
    $this->settings=$settings;
  }

  public function getSettings($item){
    return $this->settings[$item];
    
  }
  
  function isListInvalid() {
    $created = $this->_ageOfTheList();
    
    $delta = (time() - $created) - $this->refresh_list * 60;
    
    if ($delta > 0)
      return true;
    return false;
  }



function createList($nodes){
  $numNodes=count($nodes);
  if($numNodes==0){throw new Exception('Copycat:This list has no nodes in it!');}
  
      foreach ($nodes as $node) {
        $values[] = Array(  
          'nid' => $node['nid'],          
          'vid' => $node['vid'],
          'uuid' => $node['uuid'],
          'created' => REQUEST_TIME,          
        );
        }
          
      $query = db_insert('copycat_import')->fields(array('nid', 'vid', 'uuid','created'));
      
      foreach ($values as $record) {
          $query->values($record);
       }
       
      try{
       $query->execute();
       }catch(Exception $e){
         throw new Exception('Copycat:No list was created. Error:'.$e->getMessage());         
       }
       
       return $numNodes;
       
      } 
     
  
public function purge(){
  $query=db_select('copycat_list','list');
        $query->leftJoin('copycat_import','import', 'list.uuid = import.uuid');
        $query->fields('list',Array('nid'))
        ->isNull('import.uuid')
        ->condition('list.server_id', $this->importer->id) ;  
        
        $result=$query->execute();
        $num_deleted=$result->rowCount();
        
        if($num_deleted>0){
          
          while($record = $result->fetchAssoc()) {
            $nid=$record['nid'];
             _secure_purge($nid);            
            }
          
          return $num_deleted;
          
        }
  
} 

public function status(){
  if(!($this->_getListSize()>0)) return false;
  $importedNodes=$this->_countImportedNodes();
  $num_of_results=$this->_getListSize();
  $percent= floor((($importedNodes /$num_of_results)*100));
  return Array('imported'=>  $importedNodes, 'total'=> $num_of_results, 'errors' => $this->_countErrors(), 'percent'=>$percent);
  
}

private function _countErrors(){
  $result = db_select('copycat_list', 'table_alias')
            ->fields('table_alias')
            ->condition('server_name', $this->importer->name)
            ->condition('nid', null)
            ->execute();
  return($result->rowCount());
  
}


private function _countImportedNodes(){
  
  if($this->force_update){
   $query = db_select('copycat_import', 'import');            
  
            $query->leftJoin('copycat_list', 'list', 'list.uuid = import.uuid');
            
            $query->fields('import');
            
            $query->where('import.created <= list.changed AND list.uuid');
            $sql=(string) $query;
            $result=$query->execute();
            
            
            
            }else{
              
            $result = db_select('copycat_list', 'table_alias')
            ->fields('table_alias')
            ->condition('server_id',$this->importer->id)    
            ->execute();
            
            }
            
    
            return($result->rowCount());
  
   
}

private function _getListSize(){
  $result = db_select('copycat_import', 'table_alias')
            ->fields('table_alias')            
            ->execute();
  
  return($result->rowCount());
  
}

private function _secure_purge($nid){
  
  $types=node_type_get_names();
  foreach ($types as $type=>$value){
    $data=field_info_instances("node",$type);
    foreach ($data as $field){
      $widtype=$field['widget']['type'];
      $name=$field['field_name'];
      if(strpos($widtype,'node_reference')=== false)continue;
      try{
      $result = db_select('field_data_'.$name, 'reference')
            ->fields('reference')
            ->condition($name.'_nid', $nid)
            ->execute();}catch(Exception $e){             
            }
            
  $is_referenced = $result->rowCount();
  if($is_referenced>0) {
    return false;
    }
    }
    
  }
  
}

private function _ageOfTheList(){
  $query = db_select('copycat_import', 'import');
    $query->join('copycat_list', 'list', 'list.uuid = import.uuid');
    $query->fields('import', array('created'))
        ->condition('list.server_name', $this->importer->name)
        ->range(0, 1);
    $result = $query->execute();
    
    if($result->rowCount()==0) return true;
    
    $created = 0;
    while ($record = $result->fetchAssoc()) {
      $created = $record['created'];
    }
    
    return $created;
  
}

public function getImagesDir(){
  return $this->target_image_dir;  
}


}
?>
