<?php

/**
 * copycatXmlrpcImporter uses XmlRpc to perform import tasks.
 *
 * @author msardu
 */
require 'copycatImporter.php';

class copycatXmlrpcImporter implements copycatImporter {

  private $endpoint;
  public $name;
  public $id;
  private $user;
  private $password;
  private $headers;

  function __construct($settings) {
    $this->endpoint = $settings['server'];
    $this->user = $settings['user'];
    $this->password = $settings['password'];
    $this->name = $settings['importer'];
    $this->id = $settings['id'];
    $this->domain = $settings['domain'];
  }

  public function login() {
    $authenticate = xmlrpc($this->endpoint, Array('user.login' => array($this->user, $this->password),));

    if (is_array($authenticate) && $authenticate['user']['name'] === $this->user && $authenticate['user']['status'] == 1) {
      $this->headers = array('headers' => array());

      // Set up cookies.
      if ($authenticate['sessid'] && !empty($authenticate['session_name'])) {
        $this->headers['headers']['Cookie'] = $authenticate['session_name'] . '=' . $authenticate['sessid'];
      }
      // Retrieve token
      $token = xmlrpc(url($this->endpoint, array('absolute' => TRUE)), array('user.token' => array()), $this->headers);

      if (xmlrpc_error()) {
        throw new Exception('Copycat:Could not retrieve token.Please notice that copycat is only compatible with servers running services 3.5 and more.');
      }

      if ($token) {
        $this->headers['headers']['X-CSRF-Token'] = $token['token'];
      }

      return true;
    }
    else {
      throw new Exception('Copycat: Login failed on server '.$this->domain.'.Please check credentials and server address.');
    }
  }

  public function index($contentType) {
    $nodes = xmlrpc($this->endpoint, Array('node.index' => Array(0, 'nid,uuid,vid', array('type' => $contentType), PHP_INT_MAX)), $this->headers);


    if (xmlrpc_error()) {
      $error = xmlrpc_error();
      throw new Exception('Copycat: Error getting node list from parent server. Error: ' . $error->message);
    }

    return $nodes;
  }

  public function node_retrieve($nid) {
    $node = xmlrpc($this->endpoint, Array('node.retrieve' => Array($nid)), $this->headers);
    if (xmlrpc_error()) {
      $error = xmlrpc_error();
      throw new Exception('Copycat: Could not retrieve node ' . $nid . '. Error: ' . $error->message);
    }
    return $node;
  }

  public function file_retrieve($fid, $imageStyles = true) {
    $file = xmlrpc($this->endpoint, Array('file.retrieve' => Array(Array("fid" => $fid), Array("file_contents" => 'true'), Array("image_styles" => $imageStyles))), $this->headers);
    if (xmlrpc_error()) {
      $error = xmlrpc_error();
      throw new Exception('Copycat: Could not retrieve file ' . $fid . '. Error: ' . $error->message);
    }
    return $file;
  }

  public function taxonomy_retrieve($tid) {
    $taxonomy = xmlrpc($this->endpoint, Array('taxonomy_term.retrieve' => Array($tid)), $this->headers);
    if (xmlrpc_error()) {
      $error = xmlrpc_error();
      throw new Exception('Copycat: Could not retrieve taxonomy term ' . $tid . '. Error: ' . $error->message);
    }
    return $taxonomy;
  }

}

?>
