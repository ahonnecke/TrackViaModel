<?php
ini_set("display_errors", 1) ;

class processDailyServiceTask extends sfBaseTask
{
    protected $row = 1;
    protected $headers = array();
    protected $lastLine = array();
    protected $last = array();
    //should go into a config file
    protected $dateFields = array('opendate',
                                  'closeddate',
                                  'promisedate',
                                  'invoicedate',
                                  'deliverydate',
                                  'customerbirthdate',
                                  'inservicedate',
                                  'warrantyexpirationdate',
                                  'rocustomerpaypostdate',
                                  'roinvoicedate');
    protected $rowStop = false;
    protected $rowStart = 0;
    protected $LaborSalesTable = false;
    protected $forceFile = false;
    protected $forceDir = false;
    protected $date = false;
    protected $lastInsertedRoNumber = false;
    protected $skipRoNumber = false;
    
    protected function configure()
    {
        // // add your own arguments here
        // $this->addArguments(array(
        //   new sfCommandArgument('my_arg', sfCommandArgument::REQUIRED, 'My argument'),
        // ));

        $this->addOptions(array(
                                new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name.  MUST BE "frontend".', 'frontend'),
                                new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
                                new sfCommandOption('start', null, sfCommandOption::PARAMETER_OPTIONAL, 'Start processing on this line of the spreadsheet. Default: start at 0', 0),
                                new sfCommandOption('stop', null, sfCommandOption::PARAMETER_OPTIONAL, 'Stop processing on this like of the spreadsheet. Default: process all lines', false),
                                new sfCommandOption('file', null, sfCommandOption::PARAMETER_OPTIONAL, 'Force a specific file to be downloaded from the FTP server: --file="JEA0001_20120208_SV.TXT"', false),
                                new sfCommandOption('dir', null, sfCommandOption::PARAMETER_OPTIONAL, 'Force a specific dir to look in for the file form the FTP server: --dir="Daily Files"', false),
                                new sfCommandOption('date', null, sfCommandOption::PARAMETER_OPTIONAL, 'Force the application to process a file for a specific date <YYYY-MM-DD>', false),
                                // add your own options here
                                ));

        $this->namespace        = 'eagle-honda';
        $this->name             = 'processDailyService';
        $this->briefDescription = 'Import data into trackvia';
        $this->detailedDescription = "
The [processDailyService|INFO] task imports data into trackvia from a spreadsheet on an FTP server
If no options are given, the default behavior is to get over FTP the service file for today,
and then process it completely inserting all lines into the trackvia app.

Example: /Daily Files/SAMPLE_JEA0001_20120301_SV.TXT
(If run on the the first of march, 2012)

Process service file for today:
  php symfony eagle-honda:processDailyService|INFO

Process history file:
  php symfony eagle-honda:processDailyService|INFO --dir=\"History Files\" --file=HIST_JEA0001_20120208_SV.TXT --start=93335
";
    }

    protected function execute($arguments = array(), $options = array())
    {        
        // initialize the database connection
        /*     $databaseManager = new sfDatabaseManager($this->configuration); */
        /*     $connection = $databaseManager->getDatabase($options['connection'])->getConnection(); */
        if($options['stop']) $this->rowStop = $options['stop'];
        if($options['start']) $this->rowStart = $options['start'];
        if($options['file']) $this->forceFile = $options['file'];
        if($options['dir']) $this->forceDir = $options['dir'];
        if($options['date']) $this->date = $options['date'];
        else $this->date = date('Y-m-d');

        if($this->rowStart > 0) {
            $this->log('{main} starting on line '.$this->rowStart);
        }
        $file = $this->getFile('');
        // add your code here

        $this->LaborSalesTable = new LaborSalesTable();
        $this->LaborSalesTable->setLogger($this);
        $this->readFile($file);
    }

    protected function getDir() {
        if($this->forceDir) return $this->forceDir;
        else return 'Daily Files';
    }

    protected function getFilename() {
        if($this->forceFile) return $this->forceFile;
        
        return 'SAMPLE_JEA0001_'.date('Ymd', strtotime($this->date)).'_SV.TXT';
    }
    
    protected function getFile()
    {

        $file_logger = new sfFileLogger($this->dispatcher,
                                        array( 
                                              'file' => '/tmp/service-rollup-'.$this->date.'-'.time().'.log'
                                               ));
        $this->dispatcher->connect('command.log', array($file_logger, 'listenToLogEvent'));

        /*         $this->logSection('Migrating entire table', 'Really, all of it.'); */

        $ftp_server = sfConfig::get('app_ftp_server');
        $ftp_user_name = sfConfig::get('app_ftp_user_name');
        $ftp_user_pass = sfConfig::get('app_ftp_user_pass');
        $ftp_filename = $this->getFilename();

        $ftp = new PstubFtp();
      
        $ftp->init($ftp_server, $ftp_user_name, $ftp_user_pass);
        $this->log("{main} Connected to $ftp_server, for user $ftp_user_name");
      
        $ftp->enablePassiveMode();
      
        //      var_dump($ftp->listDir());

        $ftp->changeDir('Polling Processing');
        $curdir = '/Polling Processing/';
        
        $ftp->changeDir($this->getDir());
        $curdir .= $this->getDir().'/';

        //      var_dump($ftp->listDir());

        $local_filename = '/tmp/service-rollup.csv';
      
        $this->log('{main} Getting file '.$curdir.$ftp_filename);
        $ftp->getFile($ftp_filename, $local_filename);

        return $local_filename;
    }

    protected function readFile($filename) {
        if (($handle = fopen($filename, "r")) == FALSE) {
            throw new Exception('Cannot open $filename');
        }
        
        $this->getHeader($handle);
        //print_r($this->headers);
        $this->getRows($handle);
        fclose($handle);
    }

    protected function getHeader($handle) {
        if($this->headers) return $this->headers;
        while (($cols = fgetcsv($handle, 5000, "\t")) !== FALSE) {
            if($cols) {
                $this->headers = $cols;
                return true;
            }
        }
    }
    
    protected function getRows($handle) {
        while (($cols = fgetcsv($handle, 5000, "\t")) !== FALSE) {
            if($this->row > $this->rowStart) $this->processLine($cols);
            $this->row++;
        }
        
        $this->LaborSalesTable->flush();
    }

    protected function retainValue($colName) {
        if(!$this->lastLine) return false;
        if($this->line[$colName] == '') {
            $this->line[$colName] = $this->lastLine[$colName];
            //$this->log("Retaining $colName from row ".($this->row - 1).' to row '.$this->row);
        }
    }

    protected function createCustomerNumberVin(){
        //should really be in taht other table
        //create a PK for vehicle table
        $pk = $this->line['customernumber'].'-'.$this->line['vehiclevin'];
        if('-' == $pk) $pk = '';
        $this->line[VehicleTable::PK_COLNAME] = $pk;
    }
    
    protected function processLine($line) {
        $this->line = $this->keyLine($line);

        if($this->isRoNumberOkayToProcess() == false) {
            $this->log("Skipping RO# ".$this->line['ronumber']);
            return false;
        }

        if($this->line['customernumber']) $newRoInSheet = true;
        elseif($this->lastInsertedRoNumber === false) $newRoInSheet = true;
        else $newRoInSheet = false;
            
        $this->createCustomerNumberVin();
        foreach($this->line  as $key => $value) {
/*             if(strpos($key, 'date')) */
            if(!empty($value) && in_array($key, $this->dateFields)) {
                $this->line[$key] = date('Y-m-d', strtotime($value));
            }
        }
        $this->retainValue( CustomerTable::PK_COLNAME );
        $this->retainValue( VehicleTable::PK_COLNAME );
        $this->retainValue( RepairOrderSummaryTable::PK_COLNAME );
        $this->retainValue( 'operationcode' );
        $this->retainValue( 'laborlinenumber' );
        $this->retainValue( 'operationdescription' );
        $this->retainValue( 'labortypes2' );
        $this->retainValue( 'labortechnumber' );
        $this->retainValue( 'vehiclevin' );
        $this->retainValue( 'customernumber' );

        $num = count($this->line);
        $this->log("{main} Reading line #{$this->row}");
        try {
            $rot = new RepairOrderSummaryTable();
            $rot->setLogger($this);

            //if RO in the sheet is new, then check the database to see if
            //the RO is already in the DB, and if so, skip the entire RO
            if($newRoInSheet) {
                $this->logSection("Processing RO #", $this->line['ronumber']);
                if($rot->getLine($this->line)) return $this->skipThisRo();
            }

            //If RO is not the last one that we inserted, (new to me)
            //then insert customer and vehicle and ro
            if($this->lastInsertedRoNumber != $this->line['ronumber']) {
                //only die if we are on a new RO
                if($this->rowStop && $newRoInSheet) {
                    if($this->row >= $this->rowStop) $this->terminate();
                }
                //$this->log('{main} Performing vehicle, customer, RO inserts');
                
                //must create the customer, then vehicle, then ro
                $this->getOrMake(new CustomerTable(), $this->line);
                $this->getOrMake(new VehicleTable(), $this->line);
                $this->getOrMake($rot, $this->line);
                $this->lastInsertedRoNumber = $this->line['ronumber'];
            }
            $this->insertNonUniques();
            
            //goes last since it has the "status" line
            //can't beexcluded by there being an exisiting RO
            $this->LaborSalesTable->processLine($this->line);
        } catch(Exception $e) {
            $this->log('{main}  Failed to process line '.$this->row.' because: '.$e);
        }
        if($this->row % 80 == 0) {
            $this->logSection('Peak memory:', memory_get_peak_usage());
        }

        $this->lastLine = $this->line;
    }

    protected function insertNonUniques() {
        //$this->log('{main} Performing Parts, GOG, Sublet, Misc inserts');
        //these don't have unique ids, so if run, they will create dupes
        $this->getOrMake(new PartsSalesTable(), $this->line);
        $this->getOrMake(new GOGSalesTable(), $this->line);
        $this->getOrMake(new SubletSalesTable(), $this->line);
        $this->getOrMake(new MiscSalesTable(), $this->line);
    }

    protected function skipThisRo(){
        $this->log("Marking RO# ".$this->line['ronumber'].' to skip');
        $this->skipRoNumber = $this->line['ronumber'];
    }

    protected function isRoNumberOkayToProcess(){
        if($this->skipRoNumber == $this->line['ronumber']) return false;
        else return true;
    }
    
    protected function keyLine($line) {
        return array_change_key_case(array_combine($this->headers, $line),
                                     CASE_LOWER);
    }
    
    protected function getOrMake($table, $line) {
        return $table->getByLine($line);
    }

    protected function terminate() {
        $this->LaborSalesTable->flush();
        $msg = 'Terminating without processing '.$this->row
             .' (RO# '.$this->line['ronumber'].')';
        $this->log($msg);
        die("\n == Dying == \n");
    }
}