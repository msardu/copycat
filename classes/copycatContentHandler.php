<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of copycatContentHandler
 *
 * @author msardu
 */
class copycatContentHandler {

  public $isUpdate;
  private $task;
  private $alterations;
  private $node;
  private $original_node;

  function __construct($nid, $data, $task) {
    $this->task = $task;
    $this->alterations = $task->alterations;
    $this->original_node = $data;
    $this->_retrieveNode($nid);
    try {
      $node_two = $this->_addFields();
    } catch (Exception $e) {
      throw $e;
    }
  }

  private function _retrieveNode($nid) {
    
    $this->isUpdate = false;
    $data = $this->original_node;

    if ($nid > 0) {
      $node = node_load($nid);
      $this->$isUpdate = true;
    }
    else {
      $node = new stdClass();
      $node->type = $this->task->destination;
      $node->uuid = $data['uuid'];
      $node->created = $data['created'];
    }

    $node->title = $data['title'];
    $node->uid = 1; // Copycat uses user admin as author 
    $node->vuuid = $data['vuuid'];
    $node->data = $data['data'];
    $node->changed = $data['changed'];
    $node->language = $data['language'];
    $node->status = $data['status'];
    $node->log = ('Aggiornamento eseguito in automatico dal sistema in data ' . format_date($node->changed, $type = 'custom', $format = 'd/m/Y H:i:s', $timezone = NULL, $langcode = NULL));

    $this->node = $node;
  }

  public function apply_alterations($machine_name, $field_data) {
    if (isset($this->alterations['' . $machine_name]['source_fieldname'])) {
      $source_fieldname = $this->alterations['' . $machine_name]['source_fieldname'];
      $field_data = $this->_alterateMapping($source_fieldname, $field_data);
    }

    if (isset($this->alterations['' . $machine_name]['data_processor'])) {
      $field_data = $this->_alterateValueByFunction($this->alterations['' . $machine_name]['data_processor'], $field_data);
    }
    return $field_data;
  }

  private function _alterateMapping($alteration, $field_data) {
    // Mapping variation - Default is to set the destination field value equal to a source field value with the same machine name
    // Alteration in this block modifies the matching between destination e source field
    if (isset($alteration)) {
      if ($alteration == 'doNotMap') // Esclude field from copy
        return $field_data;
      if ($alteration == 'noSource') { //Set field to empty
        $alterated_data = Array();
      }
      else {
        $alterated_data = $this->original_node['field_' . $alteration]; // Set field value from a source field named in a different way
      }
    }
    else {
      $alterated_data = $field_data; // No modification
    }

    return $alterated_data;
  }

  private function _alterateValueByFunction($alteration, $field_data) {

// This block modifies field value by a callback function 

    if (isset($alteration)) {
      foreach ($alteration as $callback => $params) {
        if (count($params) > 0) {
          // Adds the parameters if any
          $inputData = array_merge($field_data, $params);
        }
        else {
          $inputData = $field_data;
        }
        $alterated_data = call_user_func($callback, $inputData); // Calls the function
      }
    }
    else {
      return $field_data;
    }
    return $alterated_data;
  }

  private function _addImage($machine_name, $field_data) {
    $imgArray = Array();
    if (empty($field_data))
      return false;
    $imgdatas = current($field_data);
    foreach ($imgdatas as $imgdata) {
      $newImage = $this->_image_retrieve($imgdata);
      array_push($imgArray, $newImage);
    }

    $this->node->{'' . $machine_name} = Array(LANGUAGE_NONE => $imgArray);
  }

  public function getNode() {
    return $this->node;
  }

  private function _image_retrieve($imgdata) {

    $imgdir = $this->task->getImagesDir();

    if (empty($imgdata))
      return false;

    $fid = $imgdata['fid'];
    $result = db_query("SELECT * FROM {file_managed} f WHERE uuid = ':uuid'", Array(':uuid' => $imgdata['uuid']));
    if ($result->rowCount() > 0) {
      $newfile = $result->fetchObject();
    }
    else {

      try {
        $file = $this->task->importer->file_retrieve($fid, true);
      } catch (Exception $e) {
        throw $e;
      }

      $filetype = explode('.', $file['filename']);
      if (!file_exists($imgdir)) {
        mkdir($imgdir, '0777', 1);
      };
      $filepath = $imgdir . '/' . $file['filename'];
      $file_data = base64_decode($file['file']);
      try {
        $drupal_file = file_save_data($file_data, $filepath, FILE_EXISTS_RENAME);
      } catch (Exception $e) {
        throw new Exception("Copycat: Image " . $filepath . " was not imported");
      }
    }
    if (!$drupal_file)
      return false;

    try {
      $image = $this->_createImage($imgdata, $drupal_file);
    } catch (Exception $e) {
      throw $e;
    }

    if (empty($image))
      return false;

    return $image;
  }

  private function _addFields() {
    foreach (field_info_instances('node', $this->task->destination) as $kk) {
      $machine_name = $kk['field_name'];

      $field_data = $this->apply_alterations($machine_name, $this->_getOriginalField($machine_name));


      $infos = field_info_field($machine_name);
      $tipo = $infos['type'];
      switch ($tipo) {
        case 'image':
          $this->_addImage($machine_name, $field_data);

          break;

        case 'taxonomy_term_reference':
          $vocab_machine_name = $infos['settings']['allowed_values'][0]['vocabulary'];
          $this->_addTaxonomy($vocab_machine_name, $machine_name, $field_data);
          break;
        default:

          $this->node->{'' . $machine_name} = $field_data;

          break;
      }
    }
  }

  private function _createImage($imgdata, $file) {
    $image = Array();

    $image['fid'] = $file->fid;
    $image['uri'] = $file->uri;
    $image['filesize'] = $file->filesize;

    $image['alt'] = $imgdata['alt'];
    $image['title'] = $imgdata['title'];
    $image['width'] = $imgdata['width'];
    $image['height'] = $imgdata['height'];
    $image['uid'] = 1;
    $image['filename'] = $imgdata['filename'];
    $image['filemime'] = $imgdata['filemime'];
    $image['status'] = $imgdata['status'];
    $image['timestamp'] = $imgdata['timestamp'];
    $image['type'] = $imgdata['type'];
    $image['uuid'] = $imgdata['uuid'];
    $image['image_dimensions'] = Array('width' => $imgdata['width'], 'height' => $imgdata['height']);
    $image['display'] = 1;
    if (!empty($imgdata['field_license'])) {
      $image['field_license'] = $imgdata['field_license'];
    }
    if (!empty($imgdata['rdf_mapping'])) {
      $image['rdf_mapping'] = $imgdata['rdf_mapping'];
    }

    return $image;
  }

  private function _loadTaxonomy($vocab_machine_name, $tid) {

    try {
      $taxonomy = $this->task->importer->taxonomy_retrieve($tid);
    } catch (Exception $e) {
      watchdog('copycat', '@error', array('@error' => $e->getMessage()), WATCHDOG_CRITICAL);
    }

    if (!$taxonomy)
      return false;

    $vocab = taxonomy_vocabulary_machine_name_load($vocab_machine_name);
    $vid = $vocab->vid;

    $result = db_query("SELECT * FROM {taxonomy_term_data} n WHERE uuid = :uuid", Array(':uuid' => $taxonomy['uuid']));

    if ($result->rowCount() > 0) {
      $record = $result->fetchObject();
      $target_tid = $record->tid;
    }
    else {
      
      $term = new stdClass();
      $term->vid = $vid;
      $term->name = $taxonomy['name'];
      $term->uuid = $taxonomy['uuid'];
      //TODO: Implement hyerarchy of taxonomy terms
      //$term->parent: (optional) The parent term(s) for this term. This can be a single term ID or an array of term IDs. A value of 0 means this term does not have any parents. When omitting this variable during an update, the existing hierarchy for the term remains unchanged.
      $term->vocabulary_machine_name = $vocab_machine_name;
      $target_tid = taxonomy_term_save($term);
      
    }

    return $target_tid;
  }

  private function _addTaxonomy($vocab_machine_name, $machine_name, $field_data) {

    if (empty($field_data))
      return false;
    
    
    $obj = entity_metadata_wrapper('node', $this->node);
    $field=&$obj->{''.$machine_name};
    $i=0;
    foreach (current($field_data) as $term) {
          $tid = $this->_loadTaxonomy($vocab_machine_name, $term['tid']);
      
      if ($tid) {
        $i++;
          if(is_array($field)){
          $field[$i]->set($tid);
          }else{
            $field->set($tid);
          }
      }
    }
    return;
  }

  private function _getOriginalField($field_name) {
    if (isset($this->original_node['' . $field_name])) {
      return $this->original_node['' . $field_name];
    }
    return Array();
  }
  
  public function errorRegister(){
    
    
    $id = db_insert('copycat_list')
                    ->fields(array(                        
                        'server_id' => $this->task->importer->id,
                        'server_name' => $this->task->importer->name,
                        'nid' => null,
                        'original_nid' => $this->_getOriginalField('nid'),
                        'original_vid' => $this->_getOriginalField('vid'),
                        'uuid' => $this->_getOriginalField('uuid'),
                        'created' => REQUEST_TIME,
                        'changed' => REQUEST_TIME,
                    ))->execute();
    
  }
  
  
  public function updateRegister($nid,$uuid){
    try{
   if ($this->isUpdate) {
    
    $id = db_update('copycat_list')
            ->fields(array(
                'original_vid' => $this->_getOriginalField('vid'),
                'changed' => REQUEST_TIME,
            ))
            ->condition('uuid', $uuid)
            ->execute();
    // ATTENZIONE: SE SI ELIMINA QUESTA QUERY E IL NOME VIENE AGGIORNATO DOPO LO SCARICAMENTO DELLA LISTA LA PROCEDURA NON TERMINERA' MAI
      $id = db_update('copycat_import')
            ->fields(array(
                'vid' => $this->_getOriginalField('vid'),                
            ))
            ->condition('uuid', $uuid)
            ->execute();
  } else {
    $id = db_insert('copycat_list')
                    ->fields(array(                        
                        'server_id' => $this->task->importer->id,
                        'server_name' => $this->task->importer->name,
                        'nid' => $nid,
                        'original_nid' => $this->_getOriginalField('nid'),
                        'original_vid' => $this->_getOriginalField('vid'),
                        'uuid' => $uuid,
                        'created' => REQUEST_TIME,
                        'changed' => REQUEST_TIME,
                    ))->execute();    
  }
    }  catch (Exception $e){
      
      throw $e;
    
    }
  }  
}
?>
