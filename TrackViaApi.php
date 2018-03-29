<?php
date_default_timezone_set('America/Denver');
    
class TrackViaApi
{
    const TRACK_VIA_BASE_URL = 'https://secure.trackvia.com/app/api';

    const API_METHOD_GET_RECORD   = 'getrecord';
    const API_METHOD_ADD_RECORD   = 'addrecord';
    const API_METHOD_UPDATE_RECORD = 'updaterecord';
    const API_METHOD_DELETE_RECORD = 'deleterecord';
    const API_METHOD_GET_VIEW     = 'getview';
    const API_METHOD_GET_SEARCH   = 'getsearch';

    protected $accountId = false;
    protected $apiKey = false;
    protected $tableID = false;
    protected $infoAboutLastCall = false;
    protected $debug = true;
    protected $logger = false;

    protected $retries = 0;
    protected $errorArray = array();
    protected $methodFields = array();
    protected $error = false;
    protected $action = false;
    
    private static $instance;
    
    private function __construct($vcharAccountID, $vcharAPIKey) {
        $this->accountID = $vcharAccountID;
        $this->apiKey = $vcharAPIKey;
        //TODO check the keys and stuff
    }

    public function __clone()
    {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }

    public function __wakeup()
    {
        trigger_error('Unserializing is not allowed.', E_USER_ERROR);
    }

    public static function getInstance($vcharAccountID=false, $vcharAPIKey=false)
    {
        if (!isset(self::$instance)) {
            if(!$vcharAPIKey) throw new Exception('no api key');
            if(!$vcharAccountID) throw new Exception('no account id');
            $className = __CLASS__;
            self::$instance = new $className($vcharAccountID, $vcharAPIKey);
        }
        return self::$instance;
    }

    public function setLogger($logger) {
        $this->logger = $logger;
    }

    //called datase id in the url on the site, fun!
    public function setTableId($vcharTableID) {
        $this->tableID = $vcharTableID;
    }
    
    protected function hitApi($action, $methodFields) {
        if(!$this->tableID) throw new Exception('No table id');
        $this->action = $action;
        
        $url = self::TRACK_VIA_BASE_URL;
        $baseFields = array('accountid'=>$this->accountID,
                            'apikey'=>$this->apiKey,
                            'action'=>$action,
                            'tableid'=>$this->tableID
                            );

        $fields = array_merge($baseFields, $methodFields);
        
        $fields_string = "";

        foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string,'&');

        $fields_string = http_build_query($fields);
    
        //open connection
        $ch = curl_init();

        if($this->debug) $this->log("API CALL -- Action: {$this->action} Table Id: {$this->tableID}");

        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_POST,count($fields));
        curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        $this->infoAboutLastCall = curl_getinfo($ch);
        if($this->debug) print_r($fields_string);

        if ($result === false || $this->infoAboutLastCall['http_code'] != 200) {
            $result = "No cURL data returned for $url [". $this->infoAboutLastCall['http_code']. "]";
            if (curl_error($ch)) $result .= "\n". curl_error($ch);
            throw new Exception($result);
        }
        //close connection
        curl_close($ch);

        if($this->debug) print_r("\n".'Result: ');
        if($this->debug) print_r("\n'".$result."'\n");

        //bad data error example:
        //ERROR laborsaleamount field did not contain a valid number: 0/6650/1
        //this could be faster...
        if(strpos($result, 'ERROR') === 0) {
            $this->error = $result;
            if($this->fixData()) return $this->retryApi();
            $this->error = false;
            throw new Exception('API Response: '.$result.' on '.$url."\n");
        }
        else {
            $this->retries = 0;
            $this->error = false;
            $this->errorArray = array();
            return json_decode($result, true);
        }
    }

    protected function retryApi() {
        $this->retries++;
        $this->log('Retrying {$this->action}.  Retry attempt #'.$this->retries);
        if($this->action == self::API_METHOD_ADD_RECORD) {
            return $this->addRecord($this->methodFields);
        }
    }
    
    protected function fixData() {
        if(strpos($this->error, 'did not contain a valid') === false) {
            $this->log('Unable to fix data. :(');
            return false;
        }

        $this->errorArray = explode(' ',$this->error);
        $field = $this->errorArray[1];
        $this->log('Redacting '.$field.' with value: '.$this->methodFields[$field]);
        
        if(isset($this->methodFields[$field])) {
            $this->markAsRedacted($field);
            unset($this->methodFields[$field]);
            return true;
        }

        $this->log('Unable to find field, or it was empty: "'.$this->methodFields[$field].'"');
        return false;
    }

    protected function markAsRedacted($field) {
        if(!isset($this->methodFields['status'])) {
            $this->methodFields['status'] = ' redacted: '.$field;
        } else {
            $this->methodFields['status'] .= ', '.$field;
        }
    }
    
    public function getView($viewId, $format='json') {
        $methodFields = array('veiwid'=>$viewId, 'format' => $format);
        return $this->hitApi(self::API_METHOD_GET_VIEW, $methodFields);
    }
    public function getSearch($term, $format='json') {
        $methodFields = array('terms'=>$term, 'format' => $format);
        return $this->hitApi(self::API_METHOD_GET_SEARCH, $methodFields);
    }
    public function getByColumn($columnName, $value, $format='json') {
        return $this->getSearch($columnName.'='.$value);
    }
    public function getByArray($array, $format='json') {
        foreach($array as $columnName => $value) {
            $termArr[] = $columnName.'='.$value;
        }
        return $this->getSearch(implode($termArr, ' '));
    }
    public function getRecord($recordId, $format='json') {
        $methodFields = array('recordid' => $recordId,
                              'format' => $format);
        return $this->hitApi(self::API_METHOD_GET_RECORD, $methodFields);
    }
    public function addRecord($data) {
        if (!$data) throw new Exception('Empty data');
        if (!is_array($data)) throw new Exception('Data not an array.');
        $this->methodFields = $data;

        $arr = array('data'=>json_encode($data));
        return $this->hitApi(self::API_METHOD_ADD_RECORD, $arr);
    }
    public function updateRecord($recordId, $format='json') {
        if (!$data) throw new Exception('Empty data');
        $methodFields = array('recordid' => $recordId,
                              'format' => $format);
        return $this->hitApi(self::API_METHOD_UPDATE_RECORD, $methodFields);
    }
    public function deleteRecord($recordId, $format='json') {
        $methodFields = array('recordid' => $recordId,
                              'format' => $format);
        return $this->hitApi(self::API_METHOD_DELETE_RECORD, $methodFields);
    }
    protected function log($msg){
        if($this->logger) $this->logger->log($msg);
        else echo $msg."\n";
    }
}
