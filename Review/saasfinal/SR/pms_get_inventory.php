<?php

class pms_get_inventory {

    private $log;
    private $module = "pms_get_inventory";
    private $hotelcode;
    private $roomtype;
    private $roomtypeid;
    private $fromdate;
    private $todate;
    private $xmlDoc;
    private $xmlRoot;
    private $xmlReservation;
    
    private $datelist = array(); //Manali - 1.0.31.36 - 11 Feb 2013, Purpose : Check for past date room inventory update
    private $todaysdate; //Manali - 1.0.31.36 - 12 Feb 2013, Purpose : Fixed Bug - Check for past date room inventory update
    private $datanotprocessed = array();//Manali - 1.0.31.36 - 11 Feb 2013, Purpose : Check for past date room inventory update
    
    #Object Fields - Getter/Setter - Start

    function __construct() {
        try {
            $this->log = new pmslogger("pms_integration");
            $this->xmlDoc = new DOMDocument('1.0', 'UTF-8');
            $this->xmlRoot = $this->xmlDoc->createElement("RES_Response");
            $this->xmlDoc->appendChild($this->xmlRoot);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function __set($name, $value) {
        $name = strtolower($name);
        if (is_string($value) || (trim($value) == '' && !is_null($value))) {
            $value = addslashes($value);
            #For removing L&R space - added by flora
            $value = trim($value);
            $value = strip_tags($value);
            $str = '$this->' . "$name=" . "'" . $value . "'";
        }
	else {
            $str = '$this->' . "$name=" . $value . "";
        }
        eval("$str;");
    }

    public function __get($name) {
        $name = strtolower($name);
        $str = '$this->' . "$name";
        eval("\$str = \"$str\";");
        return $str;
    }

    #Object Fields - Getter/Setter - End

    public function executeRequest($hotelcode,$roomtype, $roomtypeid, $fromdate, $todate, $ignorelist) {
        $this->log->logIt("Hotel_" . $hotelcode . " - " . get_class($this) . "-" . "executeRequest");
        try {
	    $this->hotelcode = $hotelcode;
	    $this->xmlDoc = new DOMDocument('1.0','UTF-8');
	    $this->xmlRoot = $this->xmlDoc->createElement("RES_Response");
	    $this->xmlDoc->appendChild($this->xmlRoot);
	    
	    if($this->isUpdateAllowed())
	    {
		$this->roomtype = $roomtype;
		$this->roomtypeid = $roomtypeid;
		$this->fromdate = $fromdate;
		$this->todate = $todate;
		$this->recs_updates=0;	
		
		//$this->log->logIt(json_encode($ignorelist,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
		
		$this->todaysdate = $this->readConfigParameter("todayDate"); 
		
		//proccessing request
		if($this->processRequest($ignorelist)) 
		{
		    if(count($this->datelist)==0 && count($ignorelist)==0)
		    {
			echo "<RES_Response></RES_Response>";
		    }
		    else{
			$error_msg='';
			if(count($ignorelist)>0)
			    $error_msg = 'Room Types Ids : '.implode(",",$ignorelist).' not exists on web so not updated.';
			
			if(count($this->datelist)>0)
			{
			    //$this->log->logIt(json_encode($this->datelist,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			    $rmtypelist ='';
			    $error_msg = ($error_msg!='')?($error_msg.'Room Types : ') : ('Room Types : ');
			    
			    foreach($this->datelist as $key => $value)
			    {
				foreach($value as $datakey =>$datavalue)
				{
				    if($datavalue['roomid']!='')
				    {
					if(in_array($datavalue['roomid'],$ignorelist))
					    continue;
					else
					{									
					    $rmtypelist = ($rmtypelist!='')?($rmtypelist.", ".$datavalue['roomid']): ("".$datavalue['roomid']);
					    
					    $rmtypelist =  $rmtypelist." from ".$datavalue['fromdate'];
					    
					    $rmtypelist =  $rmtypelist." to ".$datavalue['todate'];
					}
				    }//end if($datavalue['roomid']!='')
				}//end foreach($value as $datakey =>$datavalue)
			    }//end foreach($this->datelist as $key => $value)
			    $error_msg = $error_msg.$rmtypelist." inventory not updated due to dates passed.";
			}//end if(count($this->datelist)>0)
		    }//end else
		}//end if($this->processRequest($ignorelist))
		else
		{
		    $this->generateGeneralErrorMsg('500', "Error occured during processing.");
		    echo $this->xmlDoc->saveXML();
		    return;
		}
	    }
	    else{
		$this->generateGeneralErrorMsg('304', "Update operation is not allowed.");
		echo $this->xmlDoc->saveXML();
		return;
	    }   
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - executeRequest - " . $e);
            $this->handleException($e);
        }
    }//end public function executeRequest
    
    private function processRequest($ignorelist) {
        try {
            $flag = true;			
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "processRequest");
						
            $keyVal = $this->prepareKeyVal();
            for ($i = 0; $i < count($keyVal); $i++) {
                foreach ($keyVal[$i] as $key => $val) {
                    $val = substr($val, 0, strlen($val) - 1);
		    
		    if(count($this->roomtypeid)>0)
		    {
			if(!in_array($this->roomtypeid[$i]."",array_values($ignorelist),TRUE) && $val!='')
			{								
			    //if (!$this->updateInventory($this->roomtypeid[$i], explode("-", $key), $val)) {
			    //	$flag = false;
			    //	break;
			    //}				
			}
		    }
                }
            }		
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "processRequest - " . $e);
            $this->handleException($e);
        }
        return $flag;
    }
    
    private function isUpdateAllowed()
    {
	try {
	    $flag=false;
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "isUpdateAllowed");
	    if($this->readConfigParameter("PMSInventoryUpdate")==1)
		$flag=true;
	}
	catch (Exception $e)
	{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isUpdateAllowed - " . $e);
            $this->handleException($e);
        }
	return $flag;
    }//end private function isUpdateAllowed
    
    private function readConfigParameter($key)
    {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "readConfigParameter");
            $result = '';
            $dao = new dao();
            $strSql = "";
            $strSql .= "SELECT keyvalue FROM cfparameter";
            $strSql .= " WHERE hotel_code=:hotel_code ";
            $strSql .= " AND keyname = :keyname";
            $dao->initCommand($strSql);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":keyname", $key);
            $result = $dao->executeRow();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "readConfigParameter - " . $e);
            $this->handleException($e);
        }
        return $result['keyvalue'];
    }//end private function readConfigParameter
    
    private function handleException($e)
    {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "handleException");
            $this->generateGeneralErrorMsg('500', "Error occured during processing");
            echo $this->xmlDoc->saveXML();
            exit;
        } catch (Exception $e) {
            $this->log->logIt(get_class($this) . "-" . "handleException" . "-" . $e);
            exit;
        }
    }//end private function handleException
    
    private function generateGeneralErrorMsg($code='', $msg='')
    {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateGeneralErrorMsg");
            $genErrors = $this->xmlDoc->createElement("Errors");
            $errorCode = $this->xmlDoc->createElement("ErrorCode");
            $errorCode->appendChild($this->xmlDoc->createTextNode($code));
            $genErrors->appendChild($errorCode);
            $errorMsg = $this->xmlDoc->createElement("ErrorMessage");
            $errorMsg->appendChild($this->xmlDoc->createTextNode($msg));
            $genErrors->appendChild($errorMsg);
            $this->xmlRoot->appendChild($genErrors);
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - generateGeneralErrorMsg - " . $e);
            $this->handleException($e);
        }
    }//end private function generateGeneralErrorMsg
    
    private function prepareKeyVal() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "prepareKeyVal");
            $daylist = $this->generateDaysList();  // generate array  list 
	    
	    //$this->log->logIt("------------------".json_encode($daylist,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
	    //$this->log->logIt("Detailed Array---------".json_encode($this->datelist,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
	    
            $keyval = array();
            $month_year = '';
		
            for ($i = 0; $i < count($daylist); $i++) {
                $keyval[$i] = array();
		
		//$this->log->logIt("------------------".count($daylist[$i]));
		
                foreach ($daylist[$i] as $day) {										
		    
		    //Purpose : Check for past date room inventory update
		    if($day!='')
		    {					
			if (date('Y-n', strtotime($day)) != $month_year) {
			    $month_year = date('Y-n', strtotime($day));
			    $keyval[$i][$month_year] = '';
			}
			if (!isset($keyval[$i][$month_year]))
			    $keyval[$i][$month_year] = '';
			$keyval[$i][$month_year].="day_" . date('j', strtotime($day)) . "=0,";
		    }
		    else
			$keyval[$i][].="";
		    
                }
            }
		
            return $keyval;
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "prepareKeyVal - " . $e);
            $this->handleException($e);
        }
    }//end private function prepareKeyVal
    
    private function generateDaysList() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateDaysList");
            for ($j = 0; $j < count($this->roomtype); $j++)
	    {
		$this->log->logIt($this->fromdate[$j]."|".$this->todate[$j]."|".$this->todaysdate."|".($this->fromdate[$j] < $this->todaysdate));
		
		//Purpose : Check for past date room inventory update
		$counter=0;
		//$this->log->logIt("Called1");
		if($this->fromdate[$j] < $this->todaysdate)
		{
		    //$this->log->logIt("Called2");
		    if($this->todate[$j] < $this->todaysdate)
		    {
			$prev_day = $this->todate[$j];
			$counter=1;
		    }
		    else
		    {							
			$prev_day = date ("Y-m-d", strtotime("-1 day", strtotime($this->todaysdate)));
			$fts = strtotime($this->todaysdate);
		    }
		    array_push($this->datelist,array($j => array("fromdate" => $this->fromdate[$j]."","todate" => $prev_day,"roomid"=>(isset($this->roomtypeid[$j])?($this->roomtypeid[$j].""):"")))); 
		}
		else
		    $fts = strtotime($this->fromdate[$j]);
		
		if($counter==0)
		{	
		    $tts = strtotime($this->todate[$j]);
		    $total_diff = $tts - $fts;
		    $diff_days = ceil($total_diff / 86400) + 1;
		    
		    for ($i = 0; $i < $diff_days; $i++) {
			$slistDate = ($fts + ($i * 86400));
			$alldays[$j][] = date("d-m-Y", $slistDate);
		    }
		}
		else
		    $alldays[$j][] = "";
	    }
            return $alldays;
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateDaysList" . "-" . $e);
            $this->handleException($e);
        }
    }//end private function generateDaysList
}

?>
