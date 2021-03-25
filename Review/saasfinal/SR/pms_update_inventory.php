<?php
class pms_update_inventory {
    private $log;
    private $module = "pms_update_inventory";
    private $hotelcode;
    private $roomtype;
    private $roomtypeid;
    private $fromdate;
    private $todate;
    private $avb;
    private $xmlDoc;
    private $xmlRoot;
	private $recs_updates;
	
	private $datelist = array(); //Manali - 1.0.31.36 - 11 Feb 2013, Purpose : Check for past date room inventory update
	private $todaysdate; //Manali - 1.0.31.36 - 12 Feb 2013, Purpose : Fixed Bug - Check for past date room inventory update
	private $datanotprocessed = array();//Manali - 1.0.31.36 - 11 Feb 2013, Purpose : Check for past date room inventory update
	private $contactunkids_forinventoryupdate = ''; //Falguni Rana - 16th Jun 2018, Purpose - Common Pool
	public $returnflag;//Sanjay Waman - 03 Jun 2019 - Return flag [CEN-1165]
		
    #Object Fields - Getter/Setter - Start
    function __construct() { 
        try {
            $this->log = new pmslogger("pms_integration");
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
            eval("$str;");
        } else {
            $str = '$this->' . "$name=" . $value . "";
            eval("$str;");
        }
    }
    public function __get($name) {
        $name = strtolower($name);
        $str = '$this->' . "$name";
        eval("\$str = \"$str\";");
        return $str;
    }
    #Object Fields - Getter/Setter - End
    public function executeRequest($hotelcode,$roomtype, $roomtypeid, $fromdate, $todate, $avb,$ignorelist) //Manali - 1.0.31.36 - 11 Feb 2013, Purpose : Fixed Bug - Check for roomtype exists or not on web
	{
        $this->log->logIt("Hotel_" . $hotelcode . " - " . get_class($this) . "-" . "executeRequest");
        try {            
            $this->hotelcode = $hotelcode;
			$this->xmlDoc = new DOMDocument('1.0','UTF-8');
			$this->xmlRoot = $this->xmlDoc->createElement("RES_Response");
			$this->xmlDoc->appendChild($this->xmlRoot);
			
			//Sanjay Waman- 06 Sep 2019 - JsonFormat [CEN-1086] - Start
			if(isset($this->returnflag))
			{
				 $this->log->logIt("XMLJSONFlag>>>>".$this->returnflag);
			}
			//Sanjay Waman -End
			
			if($this->isUpdateAllowed()){
				$this->roomtype = $roomtype;
				$this->roomtypeid = $roomtypeid;
				$this->fromdate = $fromdate;
				$this->todate = $todate;
				$this->avb = $avb;
				$this->recs_updates=0;	
				
				//$this->log->logIt(json_encode($ignorelist,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				
				//Manali - 1.0.31.36 - 11 Feb 2013 - START
				//Purpose : Fixed Bug - Check for past date room inventory update				
				$this->todaysdate = $this->readConfigParameter("todayDate");
				//Sanjay Waman - Allow pass date inventory update (systoday - 1day ) - for innsoft pms only[CEN-1339] - Start
				if($this->readConfigParameter("ThirdPartyPMS") == "innsoft") 
				{
					$this->todaysdate = date("Y-m-d", strtotime("-1 day", strtotime($this->todaysdate)));
				}
				//Sanjay Waman - End
				
				//Falguni Rana - 16th Jun 2018 - START
				//Purpose - Common Pool
				$objmasterdao = new masterdao();
				$this->contactunkids_forinventoryupdate = $objmasterdao->getOnlyIndependentSource('','inventory',$this->hotelcode);
				unset($objmasterdao);
				if($this->contactunkids_forinventoryupdate == '') {
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 05 Sep 2019 - Return flag for json format [CEN-1086]
					{
						return $this->generateGeneralErrorJson('134', "All Source(s) are using inventory of other source, thefore no update will be allowed.");
					}
					else
					{
						$this->generateGeneralErrorMsg('134', "All Source(s) are using inventory of other source, thefore no update will be allowed.");
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag [CEN-1165]
						{
							return $this->xmlDoc->saveXML();
						}
						echo $this->xmlDoc->saveXML();
						return;
					}
					
				}
				$this->log->logIt("Final inventory update sources => ".$this->contactunkids_forinventoryupdate);
				//Falguni Rana - 16th Jun 2018 - END
				
				//proccessing request
				if($this->processRequest($ignorelist)) 
				{
					if($this->recs_updates==0 && count($this->datelist)==0 && count($ignorelist)==0)
					{
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 05 Sep 2019 - Return flag for json format [CEN-1086]
						{
							return $this->generateGeneralErrorJson('0', "Success",array("Success"=>array("SuccessMsg"=>"Room Inventory not updated as PMS - Web inventory are same.")));
						}
						else
						{
							$success = $this->xmlDoc->createElement("Success");
							$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
							$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Inventory not updated as PMS - Web inventory are same.'));
							$success->appendChild($succ_msg);
							$this->xmlRoot->appendChild($success);
							$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
							$str = $this->xmlDoc->saveXML();
							if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
							{
								return $str;
							}
							echo $str;
						}
					}
					else if($this->recs_updates>0 && count($this->datelist)==0 && count($ignorelist)==0)
					{
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 05 Sep 2019 - Return flag for json format [CEN-1086]
						{
							return $this->generateGeneralErrorJson('0', "Success",array("Success"=>array("SuccessMsg"=>"Room Inventory Successfully Updated")));
						}
						else
						{
							$success = $this->xmlDoc->createElement("Success");
							$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
							$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Inventory Successfully Updated'));
							$success->appendChild($succ_msg);
							$this->xmlRoot->appendChild($success);
							$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
							$str = $this->xmlDoc->saveXML();
							if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
							{
								return $str;
							}
							echo $str;
						}
					}
					else
					{
						//Falguni Rana - 1.0.49.54 - 21st Jul 2016 - START
						//Purpose - Corrected error messages 
						$allRTIgnored = true;
						foreach($roomtypeid AS $roomtypeunkid)
						{
							if(!in_array($roomtypeunkid,$ignorelist))
							{
								$allRTIgnored = false;
								break;
							}
						}
						//Falguni Rana - 1.0.49.54 - 21st Jul 2016 - END
						$error_msg='';
						if(count($ignorelist)>0)
							$error_msg = 'Room Types Ids : '.implode(",",$ignorelist).' not exists on web so not updated.';
						
						if(count($this->datelist)>0 && !$allRTIgnored) //Falguni Rana - 1.0.49.54 - 21st Jul 2016, Purpose - Corrected error messages, Added && !$allRTIgnored
						{
							//$this->log->logIt(json_encode($this->datelist,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
							$rmtypelist ='';
							//$error_msg = ($error_msg!='')?($error_msg.'Room Types : ') : ('Room Types : '); //Falguni Rana - 1.0.49.54 - 21st Jul 2016, Purpose - Corrected error messages, Commented this line
							
							foreach($this->datelist as $key => $value)
							{
								//$this->log->logIt($key);
								//$this->log->logIt($value);
							
								foreach($value as $datakey =>$datavalue)
								{
									if(in_array($datakey,$ignorelist))
										continue;
									else
									{									
										$rmtypelist = ($rmtypelist!='')?($rmtypelist.", ".$datakey): ("".$datakey);
										
										$rmtypelist =  $rmtypelist." from ".$datavalue['fromdate'];
										
										$rmtypelist =  $rmtypelist." to ".$datavalue['todate'];
									}
								}
							}
							//Falguni Rana - 1.0.49.54 - 21st Jul 2016, Purpose - Corrected error messages, Commented below line and added very next line
							//$error_msg = $error_msg.$rmtypelist." inventory not updated due to dates passed.";
							$error_msg = $error_msg.($rmtypelist != '' ? ("Room Types : ".$rmtypelist." inventory not updated due to dates passed.") : "");
						}
						
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 05 Sep 2019 - Return flag for json format [CEN-1086]
						{
							if($allRTIgnored)
								$sucmsg = 'Room Inventory not Updated.';
							else
								$sucmsg = 'Room Inventory Partially Updated.';
								
							return $this->generateGeneralErrorJson('1', $error_msg,array("Success"=>array("SuccessMsg"=>$sucmsg)));
						}
						else
						{
							$success = $this->xmlDoc->createElement("Success");
							$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
	
							//Falguni Rana - 1.0.49.54 - 21st Jul 2016 - START
							//Purpose - Corrected error messages
							if($allRTIgnored)
								$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Inventory not Updated.'));
							else
								$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Inventory Partially Updated.'));
							//Falguni Rana - 1.0.49.54 - 21st Jul 2016 - END
	
							$success->appendChild($succ_msg);
							$this->xmlRoot->appendChild($success);
	
							//Falguni Rana - 1.0.49.54 - 21st Jul 2016, Purpose - Corrected error messages, Commented below line and added very next line
							//$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
							$this->generateGeneralErrorMsg('1',$error_msg);
							$str = $this->xmlDoc->saveXML();
							if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
							{
								return $str;
							}
							echo $str;
						}

						
					}
				} 
				//Manali - 1.0.31.36 - 11 Feb 2013 - END
				else {
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 05 Sep 2019 - Return flag for json format [CEN-1086]
					{
						return $this->generateGeneralErrorJson('500', "Error occured during processing.");
					}
					else
					{
						$this->generateGeneralErrorMsg('500', "Error occured during processing.");
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
						{
							return $this->xmlDoc->saveXML();
						}
						echo $this->xmlDoc->saveXML();
						return;
					}
				}
				
				
			}
			else{
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 06 Sep 2019 - Return flag for json format [CEN-1086]
				{
					return $this->generateGeneralErrorJson('304', "Update operation is not allowed.");
				}
				else
				{
					$this->generateGeneralErrorMsg('304', "Update operation is not allowed.");
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
					{
						return $this->xmlDoc->saveXML();
					}
					echo $this->xmlDoc->saveXML();
					return;
				}
			}
			$this->returnflag='';//Sanjay Waman - 03 jul 2019 - reset variable [CEN-1165]
			
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - executeRequest - " . $e);
            $this->handleException($e);
        }
    }
    private function processRequest($ignorelist) {
        try {
            $flag = true;			
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "processRequest");
						
            $keyVal = $this->prepareKeyVal();
            for ($i = 0; $i < count($keyVal); $i++) {
                foreach ($keyVal[$i] as $key => $val) {
                    $val = substr($val, 0, strlen($val) - 1);
					
					//Manali - 1.0.31.36 - 11 Feb 2013 -START 
					//Purpose : Fixed Bug - Check for roomtype exists or not on web					
					if(!in_array($this->roomtypeid[$i]."",array_values($ignorelist),TRUE) && $val!='')
					{								
						if (!$this->updateInventory($this->roomtypeid[$i], explode("-", $key), $val)) {
							$flag = false;
							break;
						}				
                    }													
					//Manali - 1.0.31.36 - 11 Feb 2013 -END 					
                }
            }			
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "processRequest - " . $e);
            $this->handleException($e);
        }
        return $flag;
    }
    private function updateInventory($roomid, $month_year, $updatewith) {
        try {
            $flag = false;
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "updateInventory"."|".$roomid."|".$month_year[0]."|".$month_year[1]."|".$updatewith);
			
			//Manali - 1.0.33.38 - 29 Apr 2013 - START
			//Purpose : Set PMS User as loginuserunkid
			$dao = new dao();			
			$strSql1 = "SELECT userunkid from cfuser where username='pms' and hotel_code=".$this->hotelcode;            
            $dao->initCommand($strSql1);
			$userrow=(array)$dao->executeRow();
			
			//$this->log->logIt("User Row : ".$userrow['userunkid']);
			
			$dao = new dao();	
			$dao->initCommand("SELECT @loginuser:=:userunkid,@loginhotel:=:hotel_code;");
			$dao->addParameter(":userunkid",$userrow['userunkid']);
			$dao->addParameter(":hotel_code",$this->hotelcode);
			$dao->executeQuery();
			//Manali - 1.0.33.38 - 29 Apr 2013 - END
			
            /*Jignsh - update will be processed by pms queue*/
			/* old code */
			$strSql = " UPDATE cfroomallocation AS cfra";
            $strSql .= " SET " . $updatewith;
            $strSql .= " WHERE cfra.roomtypeunkid=:roomtypeunkid AND cfra.hotel_code=:hotel_code AND cfra.month=:month AND cfra.year=:year";
			//Falguni Rana - 16th Jun 2018, Purpose - Common Pool
			$strSql.=" AND cfra.contactunkid in (".$this->contactunkids_forinventoryupdate.") ";
            /*$dao = new dao();
            $dao->initCommand($strSql);
            $dao->addParameter(":roomtypeunkid", $roomid);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":month", $month_year[1]);
            $dao->addParameter(":year", $month_year[0]);		
			
            $rowcount=$dao->executeNonQuery();
            $this->recs_updates=$this->recs_updates+$rowcount;
		    */
            
            // Sushma Rana - Applied static value as per discussion with jignesh, now functionality "Room Inventory not updated as PMS - Web inventory are same"
            // not work. we every time display "successfully invenotry update message"
			$this->recs_updates=$this->recs_updates+1;
            $flag = true;
			
			/*Strore Query for queue Jignesh */
			//if($this->hotelcode == "8963"){
				
				global $connection;
				
				//get database name from connection object
				$connection_str = explode("dbname=",$connection->getConnectionString());
				$db_name= "";
				if(count($connection_str) > 0){
					$db_name =  $connection_str[1];	
				}
				
				//prepare upate query to store in rds
				$sqldata = $strSql;
				$sqldata = str_replace(":roomtypeunkid","'".$roomid."'",$sqldata);
				$sqldata = str_replace(":hotel_code","'".$this->hotelcode."'",$sqldata);
				$sqldata = str_replace(":month","'".$month_year[1]."'",$sqldata);
				$sqldata = str_replace(":year","'".$month_year[0]."'",$sqldata);
				$atrdsdao = new atrdsdao();
				$atrdsdao->StorePmsAri($sqldata,$db_name,'cfroomallocation',$this->hotelcode,$userrow['userunkid']);
				//$this->log->logIt($this->hotelcode . " updateInventory-stored at rds " . get_class($this) . "-". $sqldata);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				/*Strore Query for queue Jignesh */
				
			//}
			
			// Start - Krunal Kaklotar - 1.0.53.61 - 2017-11-24 : Separate Auditlog Tools
			$objatdao = new atdao();
			$_SESSION['prefix'] = "SaaS_".$this->hotelcode."";
			$_SESSION[$_SESSION['prefix']]['hotel_code']=$this->hotelcode."";;
			$_SESSION[$_SESSION['prefix']]['loginuserunkid'] = $userrow['userunkid']."";
			$_SESSION[$_SESSION['prefix']]['loginusername'] = "pms"; 
			$objatdao->call_deamonauditlog_api("pmsreservationprocess","ROOMALLOCATION","cfroomallocation","roomtypeunkid",$roomid."",'',$month_year[1],$month_year[0],$updatewith,util::VisitorIP());
			// End - Krunal Kaklotar
			
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "updateInventory - " . $e);
            $this->handleException($e);
        }
        return $flag;
    }
    private function prepareKeyVal() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "prepareKeyVal");
            $daylist = $this->generateDaysList();  // generate array  list 
			          
            $keyval = array();
            $month_year = '';
			
            for ($i = 0; $i < count($daylist); $i++) {
                $keyval[$i] = array();
				
				//$this->log->logIt("------------------".count($daylist[$i]));
				
                foreach ($daylist[$i] as $day) {										
					//Manali - 1.03.31-36 - 13 Feb 2013 - START
					//Purpose : Check for past date room inventory update
					if($day!='')
					{					
						if (date('Y-n', strtotime($day)) != $month_year) {
							$month_year = date('Y-n', strtotime($day));
							$keyval[$i][$month_year] = '';
						}
						if (!isset($keyval[$i][$month_year]))
							$keyval[$i][$month_year] = '';
						$keyval[$i][$month_year].="day_" . date('j', strtotime($day)) . "=" . $this->avb[$i] . ",";
					}
					else
						$keyval[$i][].="";
					//Manali - 1.03.31-36 - 13 Feb 2013 - START
                }
            }
			
            return $keyval;
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "prepareKeyVal - " . $e);
            $this->handleException($e);
        }
    }
    private function generateGeneralErrorMsg($code='', $msg='') {
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
    }
	
	//Sanjay Waman - 05 Sep 2019 - Json format [CEN-1086] - Start
	private function generateGeneralErrorJson($code='', $msg='',$responsearr=array(),$dflag=0) {
        try {
			$responsearr['Errors']=array('ErrorCode'=>$code, 'ErrorMessage'=>$msg);
			header('Content-Type: application/json');
			if($dflag===1)
			{
				echo json_encode($responsearr,JSON_PRETTY_PRINT);
				die;
			}
			else
			{
				return json_encode($responsearr,JSON_PRETTY_PRINT);
			}
			
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - generateGeneralErrorJson - " . $e);
            $this->handleException($e);
        }
    }
	//Sanjay Waman - End
	
    private function generateDaysList() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateDaysList");
            for ($j = 0; $j < count($this->roomtype); $j++) {
				
				//Manali - 1.03.31-36 - 12 Feb 2013 - START
				//Purpose : Check for past date room inventory update
				$counter=0;
				if($this->fromdate[$j] < $this->todaysdate)
				{
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
						
					array_push($this->datelist,array($this->roomtypeid[$j]."" => array("fromdate" => $this->fromdate[$j]."","todate" => $prev_day))); 
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
				//Manali - 1.03.31-36 - 12 Feb 2013 - END
		   }
			
			//$this->log->logIt($alldays);
			
            return $alldays;
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateDaysList" . "-" . $e);
            $this->handleException($e);
        }
    }
	private function isUpdateAllowed(){
	  try {
	  		$flag=false;
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "isUpdateAllowed");
			if($this->readConfigParameter("PMSInventoryUpdate")==1)
				$flag=true;
		} catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isUpdateAllowed - " . $e);
            $this->handleException($e);
        }
		return $flag;
		
	}
	private function readConfigParameter($key) {
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
    }
    private function handleException($e) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "handleException");
            
			if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 05 Sep 2019 - Return flag for json format [CEN-1086]
			{
				return $this->generateGeneralErrorJson('500', "Error occured during processing.",array(),1);
			}
			else
			{
				$this->generateGeneralErrorMsg('500', "Error occured during processing");
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
				{
					return $this->xmlDoc->saveXML();
				}
				else
				{
					echo $this->xmlDoc->saveXML();
				}
				exit;	
			}
        } catch (Exception $e) {
            $this->log->logIt(get_class($this) . "-" . "handleException" . "-" . $e);
            exit;
        }
    }
}
?>
