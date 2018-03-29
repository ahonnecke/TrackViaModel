<?php

class RepairOrderSummaryTable extends TrackViaTable
{
    CONST PK_COLNAME = 'ronumber';
    CONST TABLE_NAME = 'repairordersummary';
    
    public function getIdName(){
        return self::PK_COLNAME;
    }

    public function getTableName() {
        return self::TABLE_NAME;
    }
}