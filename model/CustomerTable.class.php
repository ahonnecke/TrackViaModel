<?php

class CustomerTable extends TrackViaTable
{
    CONST PK_COLNAME = 'customernumber';
    CONST TABLE_NAME = 'customer';
    
    public function getIdName(){
        return self::PK_COLNAME;
    }

    public function getTableName() {
        return self::TABLE_NAME;
    }
}