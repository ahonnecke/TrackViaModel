<?php

class PstubFtp
{
    var $conn_id = false;
    
    public function init($ftp_server, $ftp_user_name, $ftp_user_pass)
    {
      // set up basic connection
      $this->conn_id = ftp_connect($ftp_server); 

      // login with username and password
      $login_result = ftp_login($this->conn_id, $ftp_user_name, $ftp_user_pass); 

      // check connection
      if ((!$this->conn_id) || (!$login_result)) {
          throw new Exception("Attempt to connect to $ftp_server for user $ftp_user_name failed");
      }
    }

    public function enablePassiveMode() {
        if ( ! ftp_pasv($this->conn_id, true) ) {
            throw new Exception("Passive mode failed");
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
    
    function changeDir($dir_name) {
        if ( ! ftp_chdir($this->conn_id, $dir_name)) {
            throw new Exception("Failed to change directory to $dir_name");
        }
    }

    function getFile($remote_file, $local_file) {
        $handle = fopen($local_file, 'w');
        if ( ! ftp_fget($this->conn_id, $handle, $remote_file, FTP_ASCII, 0) ) {
            throw new Exception("There was a problem while downloading $remote_file to $local_file");
        }
    }
}


