<?php

class LaborSalesTable extends TrackViaTable
{
    CONST PK_COLNAME = 'ronumber_laborlinenumber_labortechnumber';
    CONST TABLE_NAME = 'laborsales';

    protected $lines = array();
    protected $laborHourSum = 0;
    protected $roNumber = false;
    public $logger = false;
    
    public function getIdName(){
        return self::PK_COLNAME;
    }

    public function getTableName() {
        return self::TABLE_NAME;
    }

    public static function createCustomerNumberVin($array){
        $pk = $array['ronumber'].'-'.$array['laborlinenumber'].'-'.$array['labortechnumber'];
        $array[self::PK_COLNAME] = $pk;
        return $array;
    }

    public function findOneByLine($line) {
        return parent::findOneByLine($line);
    }   
    
    public function canLookUpByLine($line) {
        if(!$this->lastLine) {
            $this->lastLine = $line;
            return false; //first line
        } return true;
    }
    
    protected function doCreate($array) {
        //if the line has no tech, then we ignore it
        if(!$array['labortechnumber']) return false;

        if (!$array) throw new Exception('Empty line');
        $log = ' **** creating entry in '.$this->getTableName();
        $log.= ' - '.$array[self::PK_COLNAME];
        $this->log($log);
        return $this->getTva()->addRecord($array);
    }
    
    protected function printValues($array) {
        return ' ro:'.$array['ronumber'].', '
            . ' tech:'.$array['labortechnumber'].', '
            .' hours:'.str_pad($array['laborhours'], 4).', '
            . ' opcode:'.str_pad($array['operationcode'], 20);
    }
    
    public function createByLine($line) {
        $record = $this->findOneByLine($line);
        $unique = 'ronumber:'.$line['ronumber'].' techno:' .$line['labortechnumber'];
        if($record) {
            //something has already been inserted for the ro number and tech number
            $this->log('Record exists for '.$line[self::PK_COLNAME]);
            return false;
        }
        //        $this->log('Record does not exist for '.$unique);

        return $this->doCreate($line);
    }

    protected function isNewRONumber() {
        if(!$this->lines) return true;
        return ($this->roNumber != $this->line['ronumber']);
    }

    public function flush(){
        try {
            $this->log('Flushing labor table');
            $this->insertLinesFromArray();
        } catch(Exception $e) {
            $this->log('Failed to flush labor table because: '.$e);
        }
    }
    
    protected function getLineKey($line) {
        return $line['labortechnumber'].'-'.$line['laborlinenumber'];
    }
    
    public function processLine($line) {
        $this->prepLine($line);
        if($this->isNewRONumber()) {
            $this->insertLinesFromArray();
            $this->lines = array();
            $this->roNumber = $this->line['ronumber'];
            $this->log('NEW RO NUmber');
        }
        if($this->line != false) {
            $key = $this->getLineKey($line);
            $this->log('pushing labor line '.$key);
            $this->lines[$key][] = $this->line;
        }
        $this->line = false;
    }

    protected function insertLinesFromArray() {
        try {
            if(!$this->lines) return false; //first line
            foreach($this->lines as $laborlinekey => $laborlines) {
                $this->insertLaborLine($laborlines, $laborlinekey);
            }
        } catch(Exception $e) {
            $this->log('Failed to insert labor line because: '.$e);
        }            
    }

    protected function insertLaborLine($laborlines, $laborlinekey) {
        $laborHourSum = 0;
        $insertArray = $laborlines[0];
        
        foreach($laborlines as $line) {
            $laborHourSum += $line['laborhours'];
        }
            
        $insertArray['laborhours'] = $laborHourSum;
        $insertArray['labortechhours'] = $laborHourSum;

        // $this->log('Rolling '.count($laborlines).' lines');
        
        $this->createByLine($insertArray);        
    }

    protected function prepLine($line){
        if (!$line) throw new Exception('Empty line');
        $this->line = $this->createCustomerNumberVin($line);
    }
}
