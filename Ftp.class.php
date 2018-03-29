<?php

class Ftp
{
    var $conn_id = false;
    public function _construct($ftp_server, $ftp_user_name, $ftp_user_pass)
    {
      // set up basic connection
      $this->conn_id = ftp_connect($ftp_server); 

      // login with username and password
      $login_result = ftp_login($this->conn_id, $ftp_user_name, $ftp_user_pass); 

      // check connection
      if ((!$this->conn_id) || (!$login_result)) { 
          $this->log("FTP connection has failed!");
          $this->log("Attempted to connect to $ftp_server for user $ftp_user_name"); 
          return false; 
      } else {
          $this->log("Connected to $ftp_server, for user $ftp_user_name");
      }

      $passive = ftp_pasv($this->conn_id, true);

      if (!$passive) { 
          $this->log("FTP passive has failed!");
          return; 
      } else {
          $this->log("Passive mode initiated");
      }
    }

    protected function log($msg) {
        echo $msg;
    }

    protected function _destruct(){      
        ftp_close($this->conn_id); 
    }

    function listDir() {
        return ftp_rawlist($this->conn_id, '.');
    }
}
