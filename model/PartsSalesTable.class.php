<?php

class PartsSalesTable extends TrackViaTable
{
    CONST PK_COLNAME = false;
    CONST TABLE_NAME = 'partssales';
    
    public function findOneByLine($line) {
        //ronumber and partnumber do not uniquely identify 
        return false;
    }

    public function canLookUpByLine($line) {
        return false;
    }
    
    public function getTableName() {
        return self::TABLE_NAME;
    }
}