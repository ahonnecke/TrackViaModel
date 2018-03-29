<?php

class MiscSalesTable extends TrackViaTable
{
    CONST PK_COLNAME = 'ronumber';
    CONST TABLE_NAME = 'miscsales';

    public function findOneByLine($line) {
        return false;
    }

    public function canLookUpByLine($line) {
        if(!$line['misccode']) return false;
        return true;
    }
    
    public function getIdName(){
        return self::PK_COLNAME;
    }

    public function getTableName() {
        return self::TABLE_NAME;
    }
}