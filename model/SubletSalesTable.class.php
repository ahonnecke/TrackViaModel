<?php

class SubletSalesTable extends TrackViaTable
{
    CONST PK_COLNAME = 'ronumber';
    CONST TABLE_NAME = 'subletsales';

    public function findOneByLine($line) {
        return false;
    }

    public function canLookUpByLine($line) {
        if(!$line['subletdescription']) return false;
        return true;
    }
    
    public function getIdName(){
        return self::PK_COLNAME;
    }

    public function getTableName() {
        return self::TABLE_NAME;
    }
}