<?php

abstract class TrackViaTable
{
    protected $trackViaAPI = false;
    protected $logger = false;
    protected $result = SELF::RESULT_GET;

    const RESULT_MAKE = 'MAKE';
    const RESULT_GET = 'GET';
    
    protected function getTva(){
        if($this->trackViaAPI == FALSE) {
            $apiAccount = sfConfig::get('app_trackvia_api_account');
            $apiKey = sfConfig::get('app_trackvia_api_key');

            $this->trackViaAPI = TrackViaApi::getInstance($apiAccount,
                                                          $apiKey);
        }
        $app = 'app_trackvia_table_'.$this->getTableName().'_tablenumber';
        $table_id = sfConfig::get($app);
        if(!$table_id) throw new Exception('No table ID in config for '.$app);
        $this->trackViaAPI->setTableId($table_id);
        
        return $this->trackViaAPI;
    }

    public function setLogger($logger) {
        $this->logger = $logger;
        $this->getTva()->setLogger($this->logger);
    }
    
    public function findOneByLine($line) {
        return $this->getTva()->getByColumn($this->getIdName(),
                                            $line[$this->getIdName()]);
    }

    public function canLookUpByLine($line) {
        if(!$line[$this->getIdName()]) {
            $this->log('No id, '.$this->getTableName().' must have just been created ');
            return false;
        }
        return true;
    }
    
    public function getIdName(){
        return false;
    }    

    public function getIdentifier($line) {
        if(!$this->getIdName()) return $line['ronumber'];
        return $line[$this->getIdName()];
    }

    protected function getNonEmptyKey() {
        return sfConfig::get('app_trackvia_table_'.$this->getTableName().'_nonempty');
    }    

    protected function lineHasValuesForCurrentTable($line) {
        if(! $this->getNonEmptyKey()) return true;
        if($line[$this->getNonEmptyKey()]) return true;
        else return false;
    }
    
    public function getByLine($line) { 
        $this->result = self::RESULT_GET;
        if(!$this->lineHasValuesForCurrentTable($line)) return;
        
        $record = $this->findOneByLine($line);
        if($record) {
            $this->result = self::RESULT_GET;
            //$this->log("found {$this->getTableName()} ".$this->getIdentifier($line)."\n");
        } else {
            //$this->log("unable to find {$this->getTableName()} ".$this->getIdentifier($line).' creating one'."\n");
            if(! $this->createByLine($line)) {
                //only report creation if it actually happened.
                $this->result = self::RESULT_MAKE;
                $this->log('Failed to create new '.$this->getTableName());
            }
        }
    }

    public function getLine($line) { 
        $this->result = self::RESULT_GET;
        if(!$this->canLookUpByLine($line)) return;
        
        $record = $this->findOneByLine($line);
        if($record) return true;
        else return false;
    }
    
    public function createByLine($line) {
        if (!$line) throw new Exception('Empty line');
        $log = ' **** creating entry in '.$this->getTableName();
        $log.= ' ('.$this->getIdentifier($line).')';
        $this->log($log);
        return $this->getTva()->addRecord($line);
    }

    public function getResult(){
    }
    
    protected function log($msg){
        $msg = '  {'.$this->getTableName().'} '.$msg;
        if($this->logger) $this->logger->log($msg);
        else echo $msg."\n";
    }
}
