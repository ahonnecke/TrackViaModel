<?php

class VehicleTable extends TrackViaTable
{
    CONST PK_COLNAME = 'customernumber_vehiclevin';
    CONST TABLE_NAME = 'vehicle';
    
    public function getIdName(){
        return self::PK_COLNAME;
    }

    public function getTableName() {
        return self::TABLE_NAME;
    }

    public static function createUniqueId($array){
        $pk = $array['customernumber'].'-'.$array['vehiclevin'];
        if('-' == $pk) $pk = '';
        $array[self::PK_COLNAME] = $pk;
        return $array;
    }

    public function findOneByLine($line) {
        return parent::findOneByLine($this->createUniqueId($line));
    }   
}