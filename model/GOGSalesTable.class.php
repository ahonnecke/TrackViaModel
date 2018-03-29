<?php

class GOGSalesTable extends TrackViaTable
{
    CONST PK_COLNAME = 'ronumber';
    CONST TABLE_NAME = 'gogsales';

    public function findOneByLine($line) {
        return false;
    }

    public function canLookUpByLine($line) {
        return false;
    }
    
    public function getIdName(){
        return self::PK_COLNAME;
    }

    public function getTableName() {
        return self::TABLE_NAME;
    }
}