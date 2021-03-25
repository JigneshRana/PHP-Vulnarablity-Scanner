<?php
//Reservation object
class pms_wrapper
{
	public $module = "pms_wrapper";
	public $log;
	private $reqType;
	private $hotelcode;
    private $authcode;
	private $ratetype;
    private $roomrate;
    private $ratetypeid;
    private $roomtype;
    private $roomtypeid;
    private $avb;
    private $fromdate;
    private $todate;
	private $notifiedBookings;
	private $tdate;
	private $tfromdate;
	private $ttodate;
	private $contacts;	
	private $excludeBookings=array();	
	private $BookingsVoidCancelled=array(); 	
	private $ignoreRoomType=array();
	private $ignoreRateType=array();
	private $ignoreRatePlan=array();
	private $rateplan;
	private $rateplanid;
	private $coa;
	private $cod;
	private $minnights;
	private $stopsell;
	public $logmsg;
	public $pmsrequest;
	public $objcallflag;//Sanjay Waman - 01 Jul 2019 - changes for obj calling [CEN-1165]
	public $returnmsg;//Sanjay Waman - 29 Jul 2019 - changes for obj calling [CEN-1165]
	public $commoncon;
	public $versionapiflag;
	private $ignoreDerivedRatePlan=array(); //Dharti Savaliya 2021-01-09 Purpose : Derivedrateplan rate update stop CEN-1875
	
	public function fetchRequest($requ="")//Sanjay Waman - 01 Jul 2019 - Changes for obj calling[CEN-1165]
	{
		$this->log = new pmslogger($this->module);
		try
		{
			//Sanjay Waman - 19 Jun 2019 - Changes for object calling [CEN-1165]- Start
			if(isset($requ) && trim($requ)!='')
			{
				$this->objcallflag = 1;
				$this->returnmsg="";
			}
			else
			{
				//Fetch Request
				$requ = file_get_contents("php://input");//SecurityReviewed
			}
			$this->log->logIt(trim($requ));//Added trim() - Sanjay
			
			//Sanjay Waman - 26 Dec 2018 - START - Block IP For stop Request
			/*if(isset($_SERVER['HTTP_X_SUCURI_CLIENTIP']) && $_SERVER['HTTP_X_SUCURI_CLIENTIP']=="113.161.81.74")
			{
				$this->log->logIt("IP >> 113.161.81.74 Found");
				return true;
			}*/
			//Sanjay Waman - END

			try 
			{
				libxml_use_internal_errors(true);
				$request_xml = simplexml_load_string($requ);	
				if (!$request_xml) {
					$this->log->logIt(">> Parsing Error");
					$rsp = $this->generateErrorMsg('501', "Invalid xml tags found in the request.");
					
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					
					exit;
				}
				else
				{
					$this->log->logIt(">> No parsing error");
				}
			} 
			catch (Exception $e) 
			{
				$this->log->logIt("Exception in " . $this->module . " - onLoad - " . $e);
				$rsp = $this->generateErrorMsg('501', "Invalid xml tags found in the request.");
				
				if(isset($this->objcallflag) && trim($this->objcallflag)==1)
					return $rsp;
				else
					echo $rsp;
				
				exit;			
			}
			
			if($request_xml==='')
			{
				$rsp = $this->generateErrorMsg('100', "Missing required parameters.");
				
				if(isset($this->objcallflag) && trim($this->objcallflag)==1)
					return $rsp;
				else
					echo $rsp;
				
				exit;
			}
			else
			{
				require_once("pmsinterface_connect.php");//SecurityReviewed
				$this->reqType = '';
				if(isset($request_xml->Request_Type) && $request_xml->Request_Type!='')
					$this->reqType = $request_xml->Request_Type;
				
				if ($this->reqType != '')
				{
					$res = $this->setAndValidateRequest($this->reqType, $request_xml);
					$this->log->logIt("VALIDATION RETURN >>>".$res);
					if((isset($this->objcallflag) && trim($this->objcallflag)==1) && ( (isset($res) && $res!==0) || (trim($this->returnmsg)!='') ))
					{
						if(trim($res)=='')
						{
							if(isset($this->returnmsg) && trim($this->returnmsg)!='')
							    return $this->returnmsg;
							else
								return $this->generateErrorMsg('500', "Error occured during processing"); 
						}
						else
						{
							return $res;
						}
					}
					
				}
				else
				{
				 	$rsp = $this->generateErrorMsg('502', "Request Type is missing",0);
					
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					
					exit;
				}
			}
			 
			$this->pmsrequest='';
			if($this->reqType.'' == 'BookingRecdNotification' || $this->reqType.'' == 'BookingRecdNotificationCM' || $this->reqType.'' == 'Bookings')
			{
				$this->pmsrequest = (String)$requ;
			}
			//Sanjay- 13 Dec 2019 - Db migration [CEN-1017] - Start
			try
			{		
				$this->log->logIt("Varify Data start ......");
				if($this->hotelcode == '' || $this->authcode == '')
				{
					$rsp = $this->generateErrorMsg('301', "Unauthorized Request. Please check hotel code and authentication code");	
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;	
					exit;
				}
				else
				{
					require_once("commonconnection.php");
					$this->commoncon = new commonconnection();
					$this->commoncon->module = $this->module;
					$this->commoncon->hotelcode = $this->hotelcode;
					$connection=$this->commoncon->replicaconnection_V2();									
					$chkautorization = $this->isAuthorizedUser();	
					if ($chkautorization===0)
					{
						$rsp = $this->generateErrorMsg('301', "Unauthorized Request. Please check hotel code and authentication code");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						exit;
					}
					if ($chkautorization=='2')
					{
						$rsp = $this->generateErrorMsg('303', "Auth Code is inactive.");	
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						exit;
					}
					$this->commoncon->removeconnection($connection);
				}
				$this->log->logIt("Varify Data end ......");
			}
			catch (Exception $e) 
			{
				$this->log->logIt("Error in Varification");
				$rsp = $this->generateErrorMsg('301', "Please check hotel code and authentication code.");		
				if(isset($this->objcallflag) && trim($this->objcallflag)==1)
					return $rsp;
				else
					echo $rsp;
				exit;
			}
			//Sanjay- 13 Dec 2019 - Db migration [CEN-1017] - End			
			if((isset($this->objcallflag) && trim($this->objcallflag)==1) && $chkautorization!=1)
			{
				$this->log->logIt("ErrorFoundWithhotelCode");
				return $chkautorization;
				exit;
			}
			
			if(isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) && $_SERVER["HTTP_X_FORWARDED_PROTO"] != '')
			{
				$this->log->logIt("HOTEL:".$this->hotelcode.":HTTP-PORT:". $_SERVER["HTTP_X_FORWARDED_PROTO"]);
			}
			else{
				if(isset($_SERVER["HTTP_HOST"]))
				{
					$this->log->logIt("HOTEL:".$this->hotelcode.":HTTP-HOST:". $_SERVER["HTTP_HOST"]);	
				}
			}
			
			//Fetching db of client
			
			//Sanjay- 14 Dec 2019 - Db migration [CEN-1017] - Start
			$this->log->logIt("Request Type >>".$this->reqType);		
			if( trim($this->reqType)=='BookingRecdNotification' || trim($this->reqType)=='BookingRecdNotificationCM' || trim($this->reqType)=='CancelBookingNotificationFromPMS' )
			{
				try
				{
					$connection= $this->commoncon->masterdbconn(1);
				}
				catch (Exception $e) 
				{
					$this->log->logIt("New Connection doing...");
					$connection= $this->commoncon->masterdbconn(1);
				}
			}
			else
			{
				try {
					// Duplicate Hit Check - START
					if($this->reqType=="Bookings" || $this->reqType=="ReqSameBookings")
					{
						$reqcheck="./reqlog/hotel_".$this->hotelcode.".txt";
						if(file_exists($reqcheck)) {
							
							$rtime=strtotime(date("Y-m-d H:i:s", filemtime($reqcheck)));
							$ctime=strtotime(date("Y-m-d H:i:s"));
							$rlag=$ctime-$rtime;
							
							if($rlag<60) {
								$this->log->logIt($this->hotelcode." >> this is request came after ".$rlag." seconds -- INVALID");
								$rtnstr = '<?xml version="1.0" encoding="UTF-8"?>
									   <RES_Response>
										   <Reservations>
										   </Reservations>
										   <Errors>
											   <ErrorCode>0</ErrorCode>
											   <ErrorMessage>Success</ErrorMessage>
										   </Errors>
									   </RES_Response>';
							   if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							   {
									return $rtnstr;
							   }
							   else
							   {
									echo $rtnstr;
							   }
							   exit;
							} else {
								$this->log->logIt($this->hotelcode." >> this is request came after ".$rlag." seconds -- VALID");
								file_put_contents($reqcheck,date("Y-m-d H:i:s"));
							}
						} else {
							file_put_contents($reqcheck,date("Y-m-d H:i:s"));
						}
					}
					// Duplicate Hit Check - END
					
					$connection= $this->commoncon->replicadbconnenction();
				}
				catch (Exception $e) 
				{
					$this->log->logIt("New Connection doing...");
					$connection= $this->commoncon->masterdbconn(1);
				}
			}
			//Sanjay- End 	   
			//Check if integration allowed
			if ($this->isIntegrationAllowed() !== true)
			{
			    $rsp = $this->generateErrorMsg('302', "Unauthorized Request. Integration is not allowed");
					
				if(isset($this->objcallflag) && trim($this->objcallflag)==1)
					return $rsp;
				else
					echo $rsp;
			}
			
			 //Check required data is available or not in hotel
			 $this->isRequiredDataAvailable();
			 if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			 {
				if(isset($this->returnmsg) && trim($this->returnmsg)!='')
				{
					return $this->returnmsg;
				}
			 }
			 
			 //Process Request	
			 //Sanjay Waman - 01 Jul 2019 - response return function call base on condition [CEN-1165] - Start
			 if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			 {
				$response = $this->processRequestreturn($this->reqType);
				return $response;
			 }
			 else
			 {
				 $this->processRequest($this->reqType);
			 }
			 //Sanjay Waman - 01 Jul 2019 - End
		}
		catch (Exception $e) 
		{
            $this->log->logIt("Exception in " . $this->module . " - fetchRequest - " . $e);
            if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				return $this->handleException($e);
			}
			echo $this->handleException($e);
        }
	}
	
	private function isAuthorizedUser()
	{
        try 
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . "isAuthorizedUser");
			$flag = '-1';
			$dao = new dao();
			$strSql = "SELECT hotel_code,`key`,authkey,isactive FROM syshotelcodekeymapping WHERE (`key`='".$this->authcode."' OR `authkey`='".$this->authcode."') AND hotel_code='".$this->hotelcode."' AND integration='RES' LIMIT 1";
			$dao->initCommand($strSql);
			$row = $dao->executeRow();					
				
			$this->log->logIt($row["isactive"]."|".$row["hotel_code"]."|".$row["key"]."|".$row["authkey"]."|".$this->hotelcode."|".$this->authcode);
			
			if($row["hotel_code"]=='')
				$flag=0;
			else
			{
				if($row["isactive"]=='0' && $this->reqType!='RoomInfo')
					$flag=2;
			
				else if ($row["isactive"]=='0' && $this->reqType!='Separatesourcemapping')
				{
				    $flag=2;					
				}
				
				else
				{
					if($row["hotel_code"] == $this->hotelcode && ($row["key"] == $this->authcode || $row["authkey"] == $this->authcode)) 
						$flag = 1;
				}				
			}
			$this->log->logIt($flag);
        } 
		catch (Exception $e) 
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isAuthorizedUser - " . $e);
			$rsp = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$flag=0;
				return $rsp;
			}
			else
				echo $rsp;
			
			exit;
        }
        return $flag;
    }
		
    private function isIntegrationAllowed() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "isIntegrationAllowed");
            $flag = false;
            $dao = new dao();
            $strSql = "";
            $strSql .= "SELECT keyvalue FROM cfparameter";
            $strSql .= " WHERE hotel_code=:hotel_code ";
            $strSql .= " AND keyname = :keyname";
            $dao->initCommand($strSql);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":keyname", "FDIntegration");
            $result = $dao->executeRow();
            if ($result['keyvalue'] == 1) {
                $flag = true;
            }
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isIntegrationAllowed - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
				exit;
			}
        }
        return $flag;
    }
	
	private function setAndValidateRequest($reqtype, $requestparams) 
	{
        try 
		{
            $this->log->logIt(get_class($this) . "-" . "setAndValidateRequest");
            $this->hotelcode = $requestparams->Authentication->HotelCode;
            $this->authcode = $requestparams->Authentication->AuthCode;
			$this->logmsg = isset($requestparams->Reason)?$requestparams->Reason.'':''; 
            if ($this->hotelcode == '')
			{
				$rsp = $this->generateErrorMsg('101', "Hotel Code is missing");
				if(isset($this->objcallflag) && trim($this->objcallflag)==1)
					return $rsp;
				else
					echo $rsp;
                
				exit;
			}
            if ($this->authcode == '')
			{
				$rsp = $this->generateErrorMsg('102', "Authentication Code is missing");
				if(isset($this->objcallflag) && trim($this->objcallflag)==1)
					return $rsp;
				else
					echo $rsp;
				
				exit;
			}
			
            switch ($reqtype) {		
                case 'UpdateAvailability':
                    $this->roomtype = $requestparams->xpath('//RoomType');
                    $this->roomtypeid = $requestparams->xpath('//RoomType/RoomTypeID');
                    $this->fromdate = $requestparams->xpath('//RoomType/FromDate');
                    $this->todate = $requestparams->xpath('//RoomType/ToDate');
                    $this->avb = $requestparams->xpath('//RoomType/Availability');
                    if (count($this->roomtypeid) > 0) {
                        foreach ($this->roomtypeid as $rt) {
                            if ($rt == '')
							{
							    $rsp = $this->generateErrorMsg('103', "Room type is missing");
								if(isset($this->objcallflag) && trim($this->objcallflag)==1)
									return $rsp;
								else
									echo $rsp;
								
								exit;
							}
                        }
                    }
                    else {
						$rsp = $this->generateErrorMsg('103', "Room type is missing");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
                    }
                   if (count($this->avb) > 0) {
                        foreach ($this->avb as $key=>$av) {
                            if ($av == '')
							{
								$rsp = $this->generateErrorMsg('110', "Inventory value is missing");
								if(isset($this->objcallflag) && trim($this->objcallflag)==1)
									return $rsp;
								else
									echo $rsp;
								
								exit;
							}

							if ((strcmp(strval(intval($av)), $av)))
							{
								$rsp = $this->generateErrorMsg('111', "Invalid inventory value");
								if(isset($this->objcallflag) && trim($this->objcallflag)==1)
									return $rsp;
								else
									echo $rsp;
								
								exit;
							}
							
							if(intval($av) < 0)
								$this->avb[$key]=0;
                        }
                    } else {
						
						$rsp = $this->generateErrorMsg('110', "Inventory value is missing");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
                    }
                    break;
                case 'UpdateRoomRates':
                    $this->ratetype = $requestparams->xpath('//RateType');
                    $this->ratetypeid = $requestparams->xpath('//RateType/RateTypeID');
                    $this->roomtypeid = $requestparams->xpath('//RateType/RoomTypeID');					
                    $this->fromdate = $requestparams->xpath('//RateType/FromDate');
                    $this->todate = $requestparams->xpath('//RateType/ToDate');
                    $this->roomrate = $requestparams->xpath('//RateType/RoomRate');
                    $this->contacts = $requestparams->xpath('//Sources/ContactId');	
																					
                    if (count($this->ratetypeid) > 0) {
                        foreach ($this->ratetypeid as $rt) {
                            if ($rt == '')
							{
								$rsp = $this->generateErrorMsg('104', "Rate type is missing");
								if(isset($this->objcallflag) && trim($this->objcallflag)==1)
									return $rsp;
								else
									echo $rsp;
								
								exit;
                                
							}
                        }
                    }
                    else {
						$rsp = $this->generateErrorMsg('104', "Rate type is missing");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
                    }
					if (count($this->roomtypeid) > 0) {
                        foreach ($this->roomtypeid as $rt) {
                            if ($rt == '')
							{
								$rsp = $this->generateErrorMsg('103', "Room type is missing");
								if(isset($this->objcallflag) && trim($this->objcallflag)==1)
									return $rsp;
								else
									echo $rsp;
								
								exit;
							}
                        }
                    }
                    else {
						$rsp = $this->generateErrorMsg('103', "Room type is missing");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
                    }
					
                    if (count($this->roomrate) > 0)
					{						
						$flag=0;
						foreach ($this->roomrate as $rr) {							                          
						if(isset($rr->Base)){
							if (is_numeric(strval($rr->Base)) == false)
							{
								$rsp = $this->generateErrorMsg('113', "Invalid base rate");
								if(isset($this->objcallflag) && trim($this->objcallflag)==1)
									return $rsp;
								else
									echo $rsp;
								
								exit;
							}
						}
						else
							$flag++;
						
						if(isset($rr->ExtraAdult)){
							if (is_numeric(strval($rr->ExtraAdult)) == false)
							{
								$rsp = $this->generateErrorMsg('114', "Invalid extra adult rate");
								if(isset($this->objcallflag) && trim($this->objcallflag)==1)
									return $rsp;
								else
									echo $rsp;
								
								exit;
							}
						}
						else
							$flag++;
							
						if(isset($rr->ExtraChild)){
							if (is_numeric(strval($rr->ExtraChild)) == false)
							{
								$rsp = $this->generateErrorMsg('115', "Invalid extra child rate",1);
								if(isset($this->objcallflag) && trim($this->objcallflag)==1)
									return $rsp;
								else
									echo $rsp;
								
								exit;
							}
						}
						else
							$flag++;
								}
				
					} 
					else {
						$rsp = $this->generateErrorMsg('121', "No Rates to update");	
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;	
                    }					
                    break;
				case 'UpdateRoomRatesNL':
				   $this->ratetype = $requestparams->xpath('//RateType');
				   $this->ratetypeid = $requestparams->xpath('//RateType/RateTypeID');
				   $this->roomtypeid = $requestparams->xpath('//RateType/RoomTypeID');					
				   $this->fromdate = $requestparams->xpath('//RateType/FromDate');
				   $this->todate = $requestparams->xpath('//RateType/ToDate');
				   $this->roomrate = $requestparams->xpath('//RateType/RoomRate');
				   $this->contacts = $requestparams->xpath('//Sources/ContactId');	
																				   
				   if (count($this->ratetypeid) > 0) {
					   foreach ($this->ratetypeid as $rt) {
						   if ($rt == '')
						   {
							    $rsp = $this->generateErrorMsg('104', "Rate type is missing");	
								if(isset($this->objcallflag) && trim($this->objcallflag)==1)
									return $rsp;
								else
									echo $rsp;
								
								exit;
							   
						   }
					   }
				   }
				   else {
						$rsp = $this->generateErrorMsg('104', "Rate type is missing");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
				   }
				   if (count($this->roomtypeid) > 0) {
					   foreach ($this->roomtypeid as $rt) {
						   if ($rt == '')
						   {
								$rsp = $this->generateErrorMsg('103', "Room type is missing");
								if(isset($this->objcallflag) && trim($this->objcallflag)==1)
									return $rsp;
								else
									echo $rsp;
								
								exit;
							   
						   }
					   }
				   }
				   else {
						$rsp = $this->generateErrorMsg('103', "Room type is missing");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
				   }
				   
				   if (count($this->roomrate) > 0)
					{						
					$flag=0;
					foreach ($this->roomrate as $rr) {							                          
							
							
							if(isset($rr->Adultrate1) || isset($rr->Adultrate2) || isset($rr->Adultrate3) || isset($rr->Adultrate4) || isset($rr->Adultrate5) || isset($rr->Adultrate6) || isset($rr->Adultrate7) ){
								if (is_numeric(strval($rr->Adultrate1)) == false || is_numeric(strval($rr->Adultrate2)) == false || is_numeric(strval($rr->Adultrate3)) == false || is_numeric(strval($rr->Adultrate4)) == false || is_numeric(strval($rr->Adultrate5)) == false || is_numeric(strval($rr->Adultrate6)) == false || is_numeric(strval($rr->Adultrate7)) == false)
								{
									$rsp = $this->generateErrorMsg('135', "Invalid rate for any between adult1 to adult7");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
								}
							}
							else
							$flag++;
							
							
					}
					if($flag==5)
					{
						$rsp =  $this->generateErrorMsg('121', "No Rates to update");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
					}
					
					} else {
						$rsp =  $this->generateErrorMsg('121', "No Rates to update");	
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;		
					}					
				   break;
			case "BookingRecdNotification":
				 $this->notifiedBookings = $requestparams->xpath('//Bookings/Booking');
				  if (count($this->notifiedBookings) > 0) {
					 foreach ($this->notifiedBookings as $booking) {
						 if ($booking->BookingId == '')
						 {
							$rsp=$this->generateErrorMsg('117', "Booking id(s) missing in booking received notification request");	
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							
							exit;
						 }
						 if ($booking->PMS_BookingId == '')
						 {
							$rsp=$this->generateErrorMsg('118', "PMS Booking id(s) missing in booking received notification request");		
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							
							exit;
						 }
					 }					  	
				  }
				  else
				  {
					$rsp = $this->generateErrorMsg('100', "Missing required parameters");		
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					
					exit;
				  }
				break;
			
			case "BookingRecdNotificationCM":
				 $this->notifiedBookings = $requestparams->xpath('//Bookings/Booking');
				  if (count($this->notifiedBookings) > 0) {
					 foreach ($this->notifiedBookings as $booking) {
						 if ($booking->BookingId == '')
						 {
							$rsp =$this->generateErrorMsg('117', "Booking id(s) missing in booking received notification request");
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							
							exit;								 							 
						 }
						 if ($booking->PMS_BookingId == '')
						 {
							$rsp =$this->generateErrorMsg('118',"PMS Booking id(s) missing in booking received notification request");
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							
							exit;	
						 }
					 }					  	
				  }
				  else
				  {
					$rsp = $this->generateErrorMsg('100', "Missing required parameters");
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					
					exit;
				  }
				break;
			
			
			case "CancelBookingNotificationFromPMS":
				 $this->notifiedBookings = $requestparams->xpath('//Bookings/Booking');
				  if (count($this->notifiedBookings) > 0) {
					 foreach ($this->notifiedBookings as $booking) {
						 if ($booking->BookingId == '')
						 {
							$rsp = $this->generateErrorMsg('119', "Booking id(s) missing in cancel booking notification request");
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							
							exit;	
						 }
						 if ($booking->PMS_BookingId == '')
						 {
							$rsp = $this->generateErrorMsg('120', "PMS Booking id(s) missing in cancel booking notification request");
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							
							exit;
						 }
					 }					  	
				  }
				   else
				   {
						$rsp = $this->generateErrorMsg('100', "Missing required parameters");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
				   }
				break;
			case 'UpdateCOA':	    
						$this->rateplan = $requestparams->xpath('//RatePlan');
						$this->rateplanid = $requestparams->xpath('//RatePlan/RatePlanID');
						$this->fromdate = $requestparams->xpath('//RatePlan/FromDate');
						$this->todate = $requestparams->xpath('//RatePlan/ToDate');
						$this->coa = $requestparams->xpath('//RatePlan/COA');
			   
				if (count($this->rateplanid) > 0) {
							foreach ($this->rateplanid as $rt) {
								if ($rt == '')
								{
									$rsp = $this->generateErrorMsg('122', "Rate Plan ID is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
								}
							}
						}
						else
						{
							$rsp = $this->generateErrorMsg('122', "Rate Plan ID is missing");
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							
							exit;
						}
						
				if (count($this->coa) > 0) {
							foreach ($this->coa as $key=>$av) {
								if ($av == '')
								{
									$rsp = $this->generateErrorMsg('123', "COA value is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
								}
	
					if ((strcmp(strval(intval($av)), $av)))
					{
						$rsp =  $this->generateErrorMsg('124', "Invalid COA value");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
					}
						
					if(intval($av) < 0)
						$this->coa[$key]=0;
							}
						}
				else
				{
					$rsp = $this->generateErrorMsg('123', "COA value is missing");
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					
					exit;
				}
					   break;
			case 'UpdateCOD':	    
						$this->rateplan = $requestparams->xpath('//RatePlan');
						$this->rateplanid = $requestparams->xpath('//RatePlan/RatePlanID');
						$this->fromdate = $requestparams->xpath('//RatePlan/FromDate');
						$this->todate = $requestparams->xpath('//RatePlan/ToDate');
						$this->cod = $requestparams->xpath('//RatePlan/COD');
			   
				if (count($this->rateplanid) > 0) {
							foreach ($this->rateplanid as $rt) {
								if ($rt == '')
								{
									$rsp = $this->generateErrorMsg('122', "Rate Plan ID is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
								}
							}
						}
						else
						{
							$rsp = $this->generateErrorMsg('122', "Rate Plan ID is missing");
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							
							exit;
						}
						
				if (count($this->cod) > 0) {
							foreach ($this->cod as $key=>$av) {
								if ($av == '')
								{
									$rsp = $this->generateErrorMsg('125', "COD value is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
								}
	
					if ((strcmp(strval(intval($av)), $av)))
					{
						$rsp = $this->generateErrorMsg('126', "Invalid COD value");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
					}
						
					if(intval($av) < 0)
						$this->cod[$key]=0;
							}
						}
				else
				{
					$rsp = $this->generateErrorMsg('125', "COD value is missing");
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					
					exit;
				}
					   break;
			case 'UpdateMinNights':	    
						$this->rateplan = $requestparams->xpath('//RatePlan');
						$this->rateplanid = $requestparams->xpath('//RatePlan/RatePlanID');
						$this->fromdate = $requestparams->xpath('//RatePlan/FromDate');
						$this->todate = $requestparams->xpath('//RatePlan/ToDate');
						$this->minnights = $requestparams->xpath('//RatePlan/MinNight');
			   
				if (count($this->rateplanid) > 0) {
							foreach ($this->rateplanid as $rt) {
								if ($rt == '')
								{
									$rsp = $this->generateErrorMsg('122', "Rate Plan ID is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
								}
							}
						}
						else
						{
							$rsp = $this->generateErrorMsg('122', "Rate Plan ID is missing");
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							
							exit;
						}
						
				if (count($this->minnights) > 0) {
							foreach ($this->minnights as $key=>$av) {
								if ($av == '')
								{
									$rsp = $this->generateErrorMsg('127', "MinNight value is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
								}
								
								if($av == 0)
								{
									$rsp = $this->generateErrorMsg('128', "Invalid MinNight value");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
								}
								
		
					if ((strcmp(strval(intval($av)), $av)))
					{
						$rsp = $this->generateErrorMsg('128', "Invalid MinNight value");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
					}
						
					if(intval($av) < 0)
						$this->minnights[$key]=0;
							}
						}
				else
				{
					$rsp = $this->generateErrorMsg('127', "MinNight value is missing");
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					
					exit;
				}
					   break;
			
			case 'UpdateMaxNights':
						$this->rateplan = $requestparams->xpath('//RatePlan');
						$this->rateplanid = $requestparams->xpath('//RatePlan/RatePlanID');
						$this->fromdate = $requestparams->xpath('//RatePlan/FromDate');
						$this->todate = $requestparams->xpath('//RatePlan/ToDate');
						$this->maxnights = $requestparams->xpath('//RatePlan/MaxNight');
			   
				if (count($this->rateplanid) > 0) {
							foreach ($this->rateplanid as $rt) {
								if ($rt == '')
								{
									$rsp = $this->generateErrorMsg('122', "Rate Plan ID is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
									
								}
							}
						}
						else
						{
							$rsp = $this->generateErrorMsg('122', "Rate Plan ID is missing");
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							
							exit;
						}
						
				if (count($this->maxnights) > 0) {
							foreach ($this->maxnights as $key=>$av) {
								if ($av == '')
								{
									$rsp =  $this->generateErrorMsg('127', "Maxnight value is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
								}							
								if($av == 0)
								{
									$rsp = $this->generateErrorMsg('128', "Invalid MaxNight value");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
								}							
		
					if ((strcmp(strval(intval($av)), $av)))
					{
						$rsp = $this->generateErrorMsg('128', "Invalid MaxNight value");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
					}
						
					if(intval($av) < 0)
						$this->maxnights[$key]=0;
							}
						}
				else
				{
					$rsp = $this->generateErrorMsg('127', "MaxNight value is missing");
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					
					exit;	 
				}
					   break;
			
			case 'UpdateStopSell':	    
						$this->rateplan = $requestparams->xpath('//RatePlan');
						$this->rateplanid = $requestparams->xpath('//RatePlan/RatePlanID');
						$this->fromdate = $requestparams->xpath('//RatePlan/FromDate');
						$this->todate = $requestparams->xpath('//RatePlan/ToDate');
						$this->stopsell = $requestparams->xpath('//RatePlan/StopSell');
						$this->contacts = $requestparams->xpath('//Sources/ContactId'); 
				if (count($this->rateplanid) > 0) {
							foreach ($this->rateplanid as $rt) {
								if ($rt == '')
								{
									$rsp = $this->generateErrorMsg('122', "Rate Plan ID is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
								}
							}
						}
						else
						{
							$rsp = $this->generateErrorMsg('122', "Rate Plan ID is missing");
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							
							exit;
						}
						
				if (count($this->stopsell) > 0) {
							foreach ($this->stopsell as $key=>$av) {
								if ($av == '')
								{
									$rsp = $this->generateErrorMsg('129', "StopSell value is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									
									exit;
								   
								}
	
					if ((strcmp(strval(intval($av)), $av)))
					{
						$rsp = $this->generateErrorMsg('130', "Invalid StopSell value");
						if(isset($this->objcallflag) && trim($this->objcallflag)==1)
							return $rsp;
						else
							echo $rsp;
						
						exit;
					}
						
					if(intval($av) < 0)
						$this->stopsell[$key]=0;
							}
						}
				else
				{
					$rsp = $this->generateErrorMsg('129', "StopSell value is missing");
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					exit;
				}
					   break;
					
			
			case 'noshowAPI':
						$this->noshowdetail = $requestparams->xpath('//noshowdetail');
						if (count($this->noshowdetail) > 0)
						{
						   foreach ($this->noshowdetail as $booking)
						   {
							  //$this->log->logIt("No-Show booking Data >>>".json_encode($booking,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
							  if ($booking->bookingID == '')
							  {
									$rsp = $this->generateErrorMsg('140', "Booking ID is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									exit;
							  }
							  if ($booking->VoucherNo == '')
							  {
									$rsp = $this->generateErrorMsg('141', "Voucher No is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									exit;
							  }
							  if ($booking->subbookingID == '')
							  {
									$rsp = $this->generateErrorMsg('142', "Subbooking ID is missing");
									if(isset($this->objcallflag) && trim($this->objcallflag)==1)
										return $rsp;
									else
										echo $rsp;
									exit;
							  }
						   }					  	
						}
						else
						{
							$rsp = $this->generateErrorMsg('100', "Missing required parameters");
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							exit;
						}
						break;				
			
			}
	    	
            if ($reqtype == "UpdateAvailability" || $reqtype == "UpdateRoomRates" ||  $reqtype == "UpdateRoomRatesNL" || $reqtype == "UpdateCOA" ||  $reqtype == "UpdateCOD" ||  $reqtype == "UpdateMinNights" ||  $reqtype == "UpdateStopSell" || $reqtype == "UpdateMaxNights") {
				
				
				
                if (count($this->fromdate) > 0 && count($this->todate) > 0)
                {
					 $_fdate_array = array();
					 $_tdate_array = array();
					 $_days_count = array();    					 
					 
					 $cnt = $this->getRequestNodeCounts();

					 for ($i = 0; $i < $cnt; $i++) {
						
						
						$f_date = date_create((string)$this->fromdate[$i]);
						$t_date = date_create((string)$this->todate[$i]);

						$diff = date_diff($f_date,$t_date); 
						
						
						$_fdate_array[]=(string)$this->fromdate[$i];
						$_tdate_array[]=(string)$this->todate[$i];
						$_days_count[]=($diff->format("%a"))+1; 
					 }
					 
					
					 $req_array = array(
									"_fdate_array" => $_fdate_array,
									"_tdate_array" => $_tdate_array,
									"_days_count" => $_days_count,
									"_reqtype" => $reqtype,
								  );
					 
					 
					 $ObjPMSInterfaceConn = new pmsinterface_connect();
					 
					 if(isset($this->objcallflag) && trim($this->objcallflag)==1)
					 {
						$validdatemsg = $ObjPMSInterfaceConn->threshold_check($this->hotelcode,"reservation",$req_array,1);
						
						if($validdatemsg!==true)
						{
							return $validdatemsg;
						}
					 }
					 else
					 {
						$ObjPMSInterfaceConn->threshold_check($this->hotelcode,"reservation",$req_array);
					 }
					
					
					
				}
                
				
                if (count($this->fromdate) > 0) {
                    foreach ($this->fromdate as $dt) {
                        if ($dt == '')
						{
							$rsp = $this->generateErrorMsg('105', "From Date is missing");	
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							exit;
						}
                    }
                }
                else {
					$rsp = $this->generateErrorMsg('105', "From Date is missing");		
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					exit;
                }
				
                if (!$this->checkDate($this->fromdate)) {
					$rsp = $this->generateErrorMsg('106', $this->tdate.' - From Date is not a valid date');			
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					exit;
                }
                
                
                
                if (count($this->todate) > 0) {
                    foreach ($this->todate as $dt) {
                        if ($dt == '')
						{
							$rsp = $this->generateErrorMsg('107','To Date is missing');		
							if(isset($this->objcallflag) && trim($this->objcallflag)==1)
								return $rsp;
							else
								echo $rsp;
							exit;
						}
                    }
                }
                else {
					$rsp = $this->generateErrorMsg('107','To Date is missing');	
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					exit;
                }
                if (!$this->checkDate($this->todate)) {
					$rsp = $this->generateErrorMsg('108',$this->tdate.' - To Date is not a valid date');	
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					exit;
                }
                if (!($this->diffBetweenDates())) {
					$rsp = $this->generateErrorMsg('109','From Date:'.$this->tfromdate.' To Date : '.$this->ttodate.' - Please check From and To date. To Date should be greater than fromdate');	
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
						return $rsp;
					else
						echo $rsp;
					exit;	
                }
            }
            return 0;
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - setAndValidateRequest - " . $e);
			$rsp = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
				return $rsp;
			else
				echo $rsp;
			exit;
        }
    }
		
	private function isRequiredDataAvailable() 
	{
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "isRequiredDataAvailable");
			switch($this->reqType)
			{
				case "BookingRecdNotification":
				case "BookingRecdNotificationCM":
					$this->checkExistanceOfBookings();						
				break;
				case "CancelBookingNotificationFromPMS":
					$this->checkExistanceOfBookings();	
					$this->checkBookingsAlreadyCancelledOrVoid();						
				break;
				case "UpdateAvailability":
					$this->checkExistanceOfRoomType();						
				break;
				case "UpdateRoomRates":
					$this->checkExistanceOfRoomType();						
					$this->checkExistanceOfRateType();
					// Dharti Savaliya 2021-01-06Purpose : Derivedrateplan rate update stop CEN-1875
						$this->checkDerivedRatepaln();
				break;
				case "UpdateRoomRatesNL":
					$this->checkExistanceOfRoomType();						
					$this->checkExistanceOfRateType();
					// Dharti Savaliya 2021-01-06Purpose : Derivedrateplan rate update stop CEN-1875
					$this->checkDerivedRatepaln();	
				break;
				
				case 'UpdateCOA':
				case 'UpdateCOD':
				case 'UpdateMinNights':
				case 'UpdateMaxNights':
				case 'UpdateStopSell':
					$this->checkExistanceOfRatePlan();
				break;
				
			}          
        } 
		catch (Exception $e) 
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isRequiredDataAvailable - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return;
			}
			else
			{
				echo $returnerr;
				exit;
			}
			
        }
    }
	
	private function checkExistanceOfBookings()
	{
		try 
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "checkExistanceOfBookings");
			 foreach ($this->notifiedBookings as $booking) 
			 {
				$checked = $this->isBookingExists($booking->BookingId,isset($booking->SubBookingId)?strval($booking->SubBookingId):'');
				if($checked==false) 
				{
					array_push($this->excludeBookings,$booking->BookingId.((isset($booking->SubBookingId) && $booking->SubBookingId!='')?'-'.strval($booking->SubBookingId):''));
				}				
			}
		}
		catch (Exception $e) 
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkExistanceOfBookings - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
				exit;
			}
		}	
	}
	
	private function checkBookingsAlreadyCancelledOrVoid()
	{
		 try 
		 {
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "checkBookingsAlreadyCancelledOrVoid");
			 foreach ($this->notifiedBookings as $booking) 
			 {				
				if($this->isBookingAlreadyCancelled($booking->BookingId,isset($booking->SubBookingId)?strval($booking->SubBookingId):''))
				{
					$bookingno = $booking->BookingId.((isset($booking->SubBookingId) && $booking->SubBookingId!='')?'-'.strval($booking->SubBookingId):'');
					if(!in_array($bookingno,array_values($this->excludeBookings),TRUE))
					{
						array_push($this->BookingsVoidCancelled,$booking->BookingId.((isset($booking->SubBookingId) && $booking->SubBookingId!='')?'-'.strval($booking->SubBookingId):''));	
					}
				}
			}
		}
		catch (Exception $e) 
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkBookingsAlreadyCancelledOrVoid - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
				exit;
			}
		}	
	}
	
	private function checkExistanceOfRoomType()
	{
		try 
		{
			 $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "checkExistanceOfRoomType");
			 foreach ($this->roomtypeid as $rt) 
			 {
				if(!$this->isRoomTypeExists($rt))
				{	
					if(!in_array($rt."",$this->ignoreRoomType,TRUE))		
						array_push($this->ignoreRoomType,$rt."");	
				}
			 }
		}
		catch (Exception $e) 
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkExistanceOfRoomType - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
				exit;
			}
        }	
	}
	
	private function checkExistanceOfRateType()
	{
		 try 
		 {
			 $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "checkExistanceOfRateType");
			 foreach ($this->ratetypeid as $rt) 
			 {
			 	if(!$this->isRateTypeExists($rt))
				{
					if(!in_array($rt."",$this->ignoreRateType,TRUE))		
						array_push($this->ignoreRateType,$rt."");	
				}
			 }
		}
		catch (Exception $e) {
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkExistanceOfRateType - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
				exit;
			}
		}
	}
	
	private function checkExistanceOfRatePlan()
	{
		try 
		 {
			 $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "checkExistanceOfRatePlan");
			 foreach ($this->rateplanid as $rt) 
			 {
			 	if(!$this->isRatePlanExists($rt))
				{
					if(!in_array($rt."",$this->ignoreRatePlan,TRUE))		
						array_push($this->ignoreRatePlan,$rt."");	
				}
			 }
		}
		catch (Exception $e) {
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkExistanceOfRatePlan - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
				exit;
			}
		}
	}
	
	private function isRatePlanExists($rateplanid) 
	{
		try 
		{
		    $this->log->logIt("Hotel_" . $this->hotelcode  . "-" . "isRatePlanExists");
		    $flag = false;
		    $dao = new dao();
		    $strSql = "";
		    $strSql .= "select count(roomrateunkid) as cnt from cfroomrate_setting where hotel_code=:hotel_code and roomrateunkid=:roomrateid";
		    $dao->initCommand($strSql);
		    $dao->addParameter(":hotel_code", $this->hotelcode);
		    $dao->addParameter(":roomrateid", $rateplanid);
		    $result = $dao->executeRow();
		    if ($result['cnt'] >0)
			$flag = true;
		} 
		catch (Exception $e) 
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isRatePlanExists - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
				exit;
			}
		}
	    return $flag;
	}
	
	private function isBookingExists($resno,$subresno='') 
	{
		try 
			{
		    $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "isBookingExists");
		    $flag = false;
		    $dao = new dao();
		    $strSql = "";
		    
		    $pos = strpos($resno, "-");	   
		    if($pos)
			list($resno,$subresno)=explode("-",$resno);
		    
		    $strSql .= "SELECT COUNT(reservationno) AS cnt FROM fdtraninfo WHERE hotel_code=:hotel_code AND reservationno=:reservationno";
				
				if($subresno!='') 
					$strSql .= " and subreservationno=:subreservationno";
				
		    $dao->initCommand($strSql);
		    $dao->addParameter(":hotel_code", $this->hotelcode);
		    $dao->addParameter(":reservationno", $resno);
				
				if($subresno!='') 
					$dao->addParameter(":subreservationno", $subresno);
				
		    $result = $dao->executeRow();
		    if ($result['cnt'] >0) 
			$flag = true;
		} 
		catch (Exception $e) 
		{
		    $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isBookingExists - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
				exit;
			}
		}
		return $flag;
	    }
	
	private function isBookingAlreadyCancelled($resno,$subresno='') 
	{
		try 
			{
		    $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "isBookingAlreadyCancelled");
		    $flag = false;
		    $dao = new dao();
		    $strSql = "";
		    
		    $pos = strpos($resno, "-");	   
		    if($pos)
			list($resno,$subresno)=explode("-",$resno);
		    
		    $strSql .= "SELECT COUNT(reservationno) AS cnt FROM fdtraninfo WHERE hotel_code=:hotel_code AND reservationno=:reservationno and FIND_IN_SET(statusunkid,'5,6')";
				
				if($subresno!='')
					$strSql .= " and subreservationno=:subreservationno";
				
		    $dao->initCommand($strSql);
		    $dao->addParameter(":hotel_code", $this->hotelcode);
		    $dao->addParameter(":reservationno", $resno);
				
				if($subresno!='')
					$dao->addParameter(":subreservationno", $subresno);
					
		    $result = $dao->executeRow();
		    if ($result['cnt'] >0) 
			$flag = true;
		} 
		catch (Exception $e) 
		{
		    $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isBookingAlreadyCancelled - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
				exit;
			}
		}
		return $flag;
	}
	
	private function isRoomTypeExists($roomtypeid) 
	{
		try 
			{
		    $this->log->logIt("Hotel_" . $this->hotelcode. "-" . "isRoomTypeExists");
		    $flag = false;
		    $dao = new dao();
		    $strSql = "";
		    $strSql .= "SELECT COUNT(roomtypeunkid) AS cnt FROM cfroomtype WHERE hotel_code=:hotel_code AND isactive AND roomtypeunkid=:roomtypeunkid";
		    $dao->initCommand($strSql);
		    $dao->addParameter(":hotel_code", $this->hotelcode);
		    $dao->addParameter(":roomtypeunkid", $roomtypeid);
		    $result = $dao->executeRow();
		    if ($result['cnt'] >0)
			$flag = true;
		} 
		catch (Exception $e) 
		{
		    $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isRoomTypeExists - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
				exit;
			}
		}
		return $flag;
	}
	
	private function isRateTypeExists($ratetypeid) 
	{
		try 
		{
		    $this->log->logIt("Hotel_" . $this->hotelcode  . "-" . "isRateTypeExists");
		    $flag = false;
		    $dao = new dao();
		    $strSql = "";
		    $strSql .= "SELECT COUNT(ratetypeunkid) AS cnt FROM cfratetype WHERE hotel_code=:hotel_code AND isactive AND ratetypeunkid=:ratetypeid";
		    $dao->initCommand($strSql);
		    $dao->addParameter(":hotel_code", $this->hotelcode);
		    $dao->addParameter(":ratetypeid", $ratetypeid);
		    $result = $dao->executeRow();
		    if ($result['cnt'] >0)
			$flag = true;
		} 
		catch (Exception $e) 
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isRateTypeExists - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
				exit;
			}
		}
	    return $flag;
	}
	
	//Sanjay Waman - 01 Jul 2019 - function for return response [CEN-1165] - Start
	private function processRequestreturn($reqtype) //processRequestObjBase
	{
        try 
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "processRequestreturn");
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "Request Type : " . $reqtype);
            switch ($this->reqType) 
			{
                case 'Bookings':
                    $pmsbookings = new pms_bookings();
                    $pmsbookings->hotelcode = $this->hotelcode;
					$pmsbookings->pmsrequest = $this->pmsrequest;
					$response = $pmsbookings->executeRequest(1);
					break;
				case 'ReqSameBookings':
					$pmsbookings = new pms_bookings_ReqSame();
					$pmsbookings->hotelcode = $this->hotelcode;
					$pmsbookings->returnflag = "xml";
					$response = $pmsbookings->executeRequest(1);
					break;
                case 'RoomInfo':
                    $pmsroominfo = new pms_roominfo();
                    $pmsroominfo->hotelcode = $this->hotelcode;
                    $response = $pmsroominfo->executeRequest(1);
                    break;
                case 'UpdateAvailability':
                    $pmsinv = new pms_update_inventory();
					$pmsinv->returnflag = "xml";
                    $response = $pmsinv->executeRequest($this->hotelcode, $this->roomtype, $this->roomtypeid, $this->fromdate, $this->todate, $this->avb,$this->ignoreRoomType); 
                    break;
                case 'UpdateRoomRates':
					$pmsrates = new pms_update_roomrates();
					$pmsrates->returnflag = "xml";
					//Dharti Savaliya 2021-01-09 Purpose : Derivedrateplan rate update stop CEN-1875
					$response = $pmsrates->executeRequest($this->hotelcode, $this->ratetype,$this->ratetypeid,$this->roomtypeid, $this->fromdate, $this->todate, $this->roomrate,$this->contacts,$this->ignoreRoomType,$this->ignoreRateType,$this->ignoreDerivedRatePlan); 
					//Dharti Savaliya - END
                    break; //IgnoreCheck
				case 'UpdateRoomRatesNL':
                    $pmsrates = new pms_update_roomrates_nonlinear();
					$pmsrates->returnflag = "xml";
					//Dharti Savaliya 2021-01-09 Purpose : Derivedrateplan rate update stop CEN-1875
					$response = $pmsrates->executeRequest($this->hotelcode, $this->ratetype,$this->ratetypeid,$this->roomtypeid, $this->fromdate, $this->todate, $this->roomrate,$this->contacts,$this->ignoreRoomType,$this->ignoreRateType,$this->ignoreDerivedRatePlan); 
					//Dharti Savaliya - END
                    break; //IgnoreCheck
				case 'BookingRecdNotification':
					if(isset($this->versionapiflag) && $this->versionapiflag == 1)
					{
						$pmsbookings = new pms_bookings_V2();
						$pmsbookings->returnflag = "xml";
						$pmsbookings->hotelcode = $this->hotelcode;
						//$this->log->logIt("Notify Booking >>".print_r($this->notifiedBookings,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
						//$this->log->logIt("Exclude Booking >>".print_r($this->excludeBookings,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]						
						$response = $pmsbookings->updateBookingStatus($this->notifiedBookings,$this->excludeBookings,0);
						$this->log->logIt("Booking END");
					}
					else
					{
						$pmsbookings = new pms_bookings();
						$pmsbookings->returnflag = "xml";
						$pmsbookings->hotelcode = $this->hotelcode;					
						$response = $pmsbookings->updateBookingStatus($this->notifiedBookings,$this->excludeBookings,0,$this->pmsrequest);
					}                    
                    break;
				case 'BookingRecdNotificationCM':
                    $pmsbookings = new pms_bookings();
					$pmsbookings->pmsDescription = isset($this->logmsg)?$this->logmsg.'':""; 
					$this->log->logIt("Hotel_" . $this->hotelcode . "-" . "BookingRecdNotificationCM - Description :::::>".$pmsbookings->pmsDescription.'');
					$pmsbookings->hotelcode = $this->hotelcode;
					$pmsbookings->returnflag = "xml";
                    $response = $pmsbookings->updateBookingStatus($this->notifiedBookings,$this->excludeBookings,1,$this->pmsrequest);
                    break;
				case 'CancelBookingNotificationFromPMS':
					$pmsbookings = new pms_bookings();
					$pmsbookings->hotelcode = $this->hotelcode;
					$pmsbookings->returnflag = "xml";
                    $response = $pmsbookings->cancelBooking($this->notifiedBookings,$this->excludeBookings,$this->BookingsVoidCancelled);
                    break;
				case 'noshowAPI':
					$noshowapi = new noshowapi();
					$noshowapi->hotelcode = $this->hotelcode;
					$bookingInfo=$this->noshowdetail;
					$noshowapi->returnflag = "xml";
					//$this->log->logIt("No-Show bookingInfo >>>" .json_encode($bookingInfo,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
                    $response = $noshowapi->noShowAPIRequest($bookingInfo[0]->bookingID,$bookingInfo[0]->VoucherNo,$bookingInfo[0]->subbookingID);
					break;
				case 'IsBookingsExists':
					$pmsbookings = new pms_bookings();
					$pmsbookings->hotelcode = $this->hotelcode;
					$pmsbookings->returnflag = "xml";
                    $response = $pmsbookings->checkBookingsExists();
                    break;
				case 'UpdateCOA':
					$pmscoa = new pms_update_otheroperations();
					$pmscoa->returnflag = "xml";
					$response = $pmscoa->executeRequest($this->hotelcode, $this->rateplan, $this->rateplanid, $this->fromdate, $this->todate, 'coa',$this->coa,'',$this->ignoreRatePlan); 
					break;
				case 'UpdateCOD':
					$pmscod = new pms_update_otheroperations();
					$pmscod->returnflag = "xml";
					$response = $pmscod->executeRequest($this->hotelcode, $this->rateplan, $this->rateplanid, $this->fromdate, $this->todate, 'cod',$this->cod,'',$this->ignoreRatePlan); 
					break;
				case 'UpdateMinNights':
					$pmsminnights = new pms_update_otheroperations();
					$pmsminnights->returnflag = "xml";
					$response = $pmsminnights->executeRequest($this->hotelcode, $this->rateplan, $this->rateplanid, $this->fromdate, $this->todate, 'minnights',$this->minnights,'',$this->ignoreRatePlan); 
					break;
				case 'UpdateMaxNights':
					$pmsminnights = new pms_update_otheroperations();
					$pmsminnights->returnflag = "xml";
					$response = $pmsminnights->executeRequest($this->hotelcode, $this->rateplan, $this->rateplanid, $this->fromdate, $this->todate, 'maxnights',$this->maxnights,'',$this->ignoreRatePlan); 
					break;
				case 'UpdateStopSell':
					$pmsstopsell = new pms_update_otheroperations();
					$pmsstopsell->returnflag = "xml";
					$response = $pmsstopsell->executeRequest($this->hotelcode, $this->rateplan, $this->rateplanid, $this->fromdate, $this->todate, 'stopsell',$this->stopsell,$this->contacts,$this->ignoreRatePlan); 
					break;
				case 'Separatesourcemapping':
					$pmssaparatesource = new pms_saparatesource();
					$pmssaparatesource->hotelcode = $this->hotelcode;
					$pmssaparatesource->returnflag = "xml";
					$response = $pmssaparatesource->executeRequest();
					break;	
                default:
                    if(isset($this->objcallflag) && trim($this->objcallflag)==1)
					{
						return $this->generateErrorMsg('502', "Request Type is missing",1);
					}
                    $this->generateErrorMsg('502', "Request Type is missing",1);
					break;
            }
		
			if(isset($response) && trim($response)!="")
			{
				return $response;
			}
        } 
		catch (Exception $e) 
		{
            $this->log->logIt("Exception in " . $this->module . " - processRequestreturn - " . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				return $returnerr;
			}
			else
			{
				echo $returnerr;
			}
			exit;
        }
    }
	//Sanjay Waman - End
	
	private function processRequest($reqtype) 
	{
        try 
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "processRequest");
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "Request Type : " . $reqtype);
            switch ($this->reqType) 
			{
                case 'Bookings':
                    $pmsbookings = new pms_bookings();
                    $pmsbookings->hotelcode = $this->hotelcode;
					$pmsbookings->pmsrequest = $this->pmsrequest;
					$pmsbookings->executeRequest();
					break;
				case 'ReqSameBookings':
					$pmsbookings = new pms_bookings_ReqSame();
					$pmsbookings->hotelcode = $this->hotelcode;
					$pmsbookings->executeRequest();
					break;
                case 'RoomInfo':
                    $pmsroominfo = new pms_roominfo();
                    $pmsroominfo->hotelcode = $this->hotelcode;
                    $pmsroominfo->executeRequest();
                    break;
                case 'UpdateAvailability':
                    $pmsinv = new pms_update_inventory();
                    $pmsinv->executeRequest($this->hotelcode, $this->roomtype, $this->roomtypeid, $this->fromdate, $this->todate, $this->avb,$this->ignoreRoomType); 
                    break;
                case 'UpdateRoomRates':
					$pmsrates = new pms_update_roomrates();
					//Dharti Savaliya 2021-01-09 Purpose : Derivedrateplan rate update stop CEN-1875
					 $response = $pmsrates->executeRequest($this->hotelcode, $this->ratetype,$this->ratetypeid,$this->roomtypeid, $this->fromdate, $this->todate, $this->roomrate,$this->contacts,$this->ignoreRoomType,$this->ignoreRateType,$this->ignoreDerivedRatePlan); 
					//Dharti Savaliya - END
					break; //IgnoreCheck
				case 'UpdateRoomRatesNL':
                    $pmsrates = new pms_update_roomrates_nonlinear();
					//Dharti Savaliya 2021-01-09 Purpose : Derivedrateplan rate update stop CEN-1875
					$response = $pmsrates->executeRequest($this->hotelcode, $this->ratetype,$this->ratetypeid,$this->roomtypeid, $this->fromdate, $this->todate, $this->roomrate,$this->contacts,$this->ignoreRoomType,$this->ignoreRateType,$this->ignoreDerivedRatePlan); 
					//Dharti Savaliya - END
					break; //IgnoreCheck
				case 'BookingRecdNotification':
                    $pmsbookings = new pms_bookings();
					$pmsbookings->hotelcode = $this->hotelcode;					
                    $pmsbookings->updateBookingStatus($this->notifiedBookings,$this->excludeBookings,0,$this->pmsrequest);
                    break;
				case 'BookingRecdNotificationCM':
                    $pmsbookings = new pms_bookings();
					$pmsbookings->pmsDescription = isset($this->logmsg)?$this->logmsg.'':"";
					$this->log->logIt("Hotel_" . $this->hotelcode . "-" . "BookingRecdNotificationCM - Description :::::>".$pmsbookings->pmsDescription.'');
					$pmsbookings->hotelcode = $this->hotelcode;					
                    $pmsbookings->updateBookingStatus($this->notifiedBookings,$this->excludeBookings,1,$this->pmsrequest);
                    break;
				case 'CancelBookingNotificationFromPMS':
					$pmsbookings = new pms_bookings();
					$pmsbookings->hotelcode = $this->hotelcode;
                    $pmsbookings->cancelBooking($this->notifiedBookings,$this->excludeBookings,$this->BookingsVoidCancelled);
                    break;
				case 'noshowAPI':
					$noshowapi = new noshowapi();
					$noshowapi->hotelcode = $this->hotelcode;
					$bookingInfo=$this->noshowdetail;
					//$this->log->logIt("No-Show bookingInfo >>>" .json_encode($bookingInfo,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
                    $noshowapi->noShowAPIRequest($bookingInfo[0]->bookingID,$bookingInfo[0]->VoucherNo,$bookingInfo[0]->subbookingID);
					break;
				case 'IsBookingsExists':
					$pmsbookings = new pms_bookings();
					$pmsbookings->hotelcode = $this->hotelcode;
                    $pmsbookings->checkBookingsExists();
                    break;
				case 'UpdateCOA':
					$pmscoa = new pms_update_otheroperations();
					$pmscoa->executeRequest($this->hotelcode, $this->rateplan, $this->rateplanid, $this->fromdate, $this->todate, 'coa',$this->coa,'',$this->ignoreRatePlan); 
					break;
				case 'UpdateCOD':
					$pmscod = new pms_update_otheroperations();
					$pmscod->executeRequest($this->hotelcode, $this->rateplan, $this->rateplanid, $this->fromdate, $this->todate, 'cod',$this->cod,'',$this->ignoreRatePlan); 
					break;
				case 'UpdateMinNights':
					$pmsminnights = new pms_update_otheroperations();
					$pmsminnights->executeRequest($this->hotelcode, $this->rateplan, $this->rateplanid, $this->fromdate, $this->todate, 'minnights',$this->minnights,'',$this->ignoreRatePlan); 
					break;
				case 'UpdateMaxNights':
					$pmsminnights = new pms_update_otheroperations();
					$pmsminnights->executeRequest($this->hotelcode, $this->rateplan, $this->rateplanid, $this->fromdate, $this->todate, 'maxnights',$this->maxnights,'',$this->ignoreRatePlan); 
					break;
				case 'UpdateStopSell':
					$pmsstopsell = new pms_update_otheroperations();
					$pmsstopsell->executeRequest($this->hotelcode, $this->rateplan, $this->rateplanid, $this->fromdate, $this->todate, 'stopsell',$this->stopsell,$this->contacts,$this->ignoreRatePlan); 
					break;
				case 'Separatesourcemapping':
					$pmssaparatesource = new pms_saparatesource();
					$pmssaparatesource->hotelcode = $this->hotelcode;
					$pmssaparatesource->executeRequest();
					break;	
                default:
					if(isset($this->objcallflag) && trim($this->objcallflag)==1)
					{
						return $this->generateErrorMsg('502', "Request Type is missing",1);
					}
                    $this->generateErrorMsg('502', "Request Type is missing",1);
					break;	
            }
        } 
		catch (Exception $e) 
		{
            $this->log->logIt("Exception in " . $this->module . " - processRequest - " . $e);
            $this->handleException($e);
        }
    }
	
	 private function checkDate($dts) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "checkDate");
            foreach ($dts as $dt) {
				
                if (!(date('Y-m-d', strtotime($dt)) == $dt || date('Y-m-j', strtotime($dt)) == $dt || date('Y-n-d', strtotime($dt)) == $dt || date('Y-n-j', strtotime($dt)) == $dt)) 				{
					$this->tdate=$dt;
                    return false;
                }
            }
            return true;
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkDate" . "-" . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
			}
			exit;
        }
    }
    
    private function validateDateLimit() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "validateDateLimit");
            
            $xml_dt_checking_flag = 0;
            $sys_dt_checking_flag = 0;
            
            $_fdate_array = array();
            $_tdate_array = array();
            
            $min_from_date = $min_to_date = $max_from_date = $max_to_date = "";            
            $from_date = $to_date = "";            
            $dStart_fdate = $dEnd_fdate = $fdDiff = "";
            
            $cnt = $this->getRequestNodeCounts(); 
            
            if($cnt == 0){
				return false;
			}    
            for ($i = 0; $i < $cnt; $i++) {
                $_fdate_array[]=(string)$this->fromdate[$i];
                $_tdate_array[]=(string)$this->todate[$i];
            }
                        
			global $log_info_path;
			
			$str = $this->hotelcode."#".json_encode($_fdate_array)."#".json_encode($_tdate_array).PHP_EOL;
			$debug_log = $log_info_path."/".date('Y_m_d')."_pms_debug_logs.txt";
			$debug_handle = fopen($debug_log, 'a');//SecurityReviewed
			fwrite($debug_handle,$str);//SecurityReviewed
			fclose($debug_handle);
			
			$min_from_date = min($_fdate_array);
			$min_to_date   = min($_tdate_array);
			$max_from_date = max($_fdate_array);
			$max_to_date   = max($_tdate_array);
			
			$days_limit = 730; 
			$from_date = new DateTime($min_from_date);
			$to_date   = new DateTime($max_to_date);
			$dt_diff   = $to_date->diff($from_date)->format('%a');  
			
			$this->log->logIt("Hotel_" . $this->hotelcode . "-" . " >> ".$min_from_date." | ".$min_to_date." | ".$max_from_date." | ".$max_to_date);
			$this->log->logIt("Hotel_" . $this->hotelcode . "-" . "dt_diff >> ".$dt_diff);
			
			$dStart_fdate = new DateTime($min_from_date);
			$dEnd_fdate  = new DateTime(date('Y-m-d'));
			$fdDiff = $dEnd_fdate->diff($dStart_fdate)->format('%a');
			$this->log->logIt("Hotel_" . $this->hotelcode . "-" . " fdDiff >> ".$fdDiff);
			
			
			
			if($dt_diff > $days_limit){
				$this->log->logIt("Hotel_" . $this->hotelcode . "-" . " request is having days more than 2 years.");
				$xml_dt_checking_flag = 1;
			}
			
			if( ($min_from_date < date('Y-m-d')) || $fdDiff > $days_limit){
				$this->log->logIt("Hotel_" . $this->hotelcode . "-" . " request is either past date or more than 2 years duration from current date.");
				$sys_dt_checking_flag = 1;
			}
			
			if($xml_dt_checking_flag == 1  || $sys_dt_checking_flag == 1){
				$this->log->logIt("Hotel_" . $this->hotelcode . "-" . " return will be false");
				return false;
			}
			            
            return true;
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "validateDateLimit" . "-" . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
			}
			exit;
        }
    }
	
    private function diffBetweenDates() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "diffBetweenDates");
            
            $cnt = $this->getRequestNodeCounts(); 
            if($cnt == 0){
				return false;
			}    
			
			
            for ($i = 0; $i < $cnt; $i++) {
                if (!((strtotime($this->todate[$i])) - (strtotime($this->fromdate[$i])) >= 0)) {
					$this->tfromdate=$this->fromdate[$i];
					$this->ttodate=$this->todate[$i];					
                    return false;
                }
            }
            return true;
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "diffBetweenDates" . "-" . $e);
			$returnerr = $this->handleException($e);
			if(isset($this->objcallflag) && trim($this->objcallflag)==1)
			{
				$this->returnmsg = $returnerr;
				return false;
			}
			else
			{
				echo $returnerr;
			}
			exit;
        }
    }
	
	public function generateErrorMsg($code,$msg)
	{
		$xmlDoc = new DOMDocument('1.0');
        $xmlDoc->xmlStandalone = true;
        $xmlRoot = $xmlDoc->createElement("RES_Response");
        $xmlDoc->appendChild($xmlRoot);
	  	$genErrors = $xmlDoc->createElement("Errors");
		$errorCode = $xmlDoc->createElement("ErrorCode");
		$errorCode->appendChild($xmlDoc->createTextNode($code));
		$genErrors->appendChild($errorCode);
		$errorMsg = $xmlDoc->createElement("ErrorMessage");
		$errorMsg->appendChild($xmlDoc->createTextNode($msg));
		$genErrors->appendChild($errorMsg);
		$xmlRoot->appendChild($genErrors);	
		return $xmlDoc->saveXML();
	}
	
    private function handleException($e) 
	{
        try 
		{
            $this->log->logIt(get_class($this) . "-" . "handleException");
			return $this->generateErrorMsg('500', "Error occured during processing");            
        } 
		catch (Exception $e) 
		{
            $this->log->logIt(get_class($this) . "-" . "handleException" . "-" . $e);
            exit;
        }
    }
    
    public function getRequestNodeCounts(){
		
		  try {
				$this->log->logIt("Hotel_" . $this->hotelcode . "-" . "getRequestNodeCounts");
				
				$cnt = 0;	
				
                if ($this->reqType == "UpdateAvailability"){
					$cnt = count($this->roomtype);
				}else if ($this->reqType == "UpdateRoomRates"){
					$cnt=count($this->ratetype);
				}
				else if ($this->reqType == "UpdateRoomRatesNL"){
					$cnt=count($this->ratetype);				 
				}
				else if ($this->reqType == "UpdateCOA" || $this->reqType == "UpdateCOD" || $this->reqType == "UpdateMinNights" || $this->reqType == "UpdateStopSell" || $this->reqType == "UpdateMaxNights"){
					$cnt=count($this->rateplan);    
				}
				
				return $cnt;
			} catch (Exception $e) {
				$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getRequestNodeCounts" . "-" . $e);
				$returnerr = $this->handleException($e);
				if(isset($this->objcallflag) && trim($this->objcallflag)==1)
				{
					$this->returnmsg = $returnerr;
					return 0;
				}
				else
				{
					echo $returnerr;
				}
				exit;
			}
	}
	
	//Dharti Savaliya - 06 Jan 2021 - Purpose : Derivedrateplan rate update stop CEN-1875- START
	private function checkDerivedRatepaln()
	{
		 try 
		 {
			 require_once("commonfunction.php");
			 $this->commonfunc = new commonfunction();
			 $this->commonfunc->module = $this->module;
			 $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "checkDerivedRatepaln");
			 $rattypearry = array();
			 $derivedrateplanflag = 1;
			 foreach($this->ratetype as $rt => $arrData)
			 {
				$roomtypid = $this->ratetype[$rt]->RoomTypeID;
				$ratetypeid = $this->ratetype[$rt]->RateTypeID;
				$combineid= $roomtypid. '_'.$ratetypeid;
				array_push($rattypearry,$combineid);
			 }
			 foreach($rattypearry as $dt => $roomrateids)
			 {
				list($roomtypids,$ratetypeids)=explode('_',$roomrateids);
				$this->commonfunc->hotelcode = $this->hotelcode;
				$checkderivedornot= $this->commonfunc->isDerivedRateplan($roomtypids,$ratetypeids);
				if(isset($checkderivedornot) && $checkderivedornot!='')
				{
					$derivedfrom = (isset($checkderivedornot[0]) && isset($checkderivedornot[0]['derivedfrom'])) ? $checkderivedornot[0]['derivedfrom'] : '';
					if(isset($derivedfrom) && $derivedfrom !='')
					{
						if(!empty($rattypearry) && count($rattypearry) ==1)
						{
							$derivedrateplanflag =1;
						}
						else
						{
							array_push($this->ignoreDerivedRatePlan,$roomrateids."");
						}
					}
					else
					{
						$derivedrateplanflag = 0;
					}
				}
			 }
			 if(isset($derivedrateplanflag) && $derivedrateplanflag==1)
			 {
				$rsp =$this->generateErrorMsg('501', "Derived  rate plan values cannot be changed directly, please update master rate plan to make change in derived rate plan.");
							
				if(isset($this->objcallflag) && trim($this->objcallflag)==1)
				{
					$this->returnmsg = $rsp;
					return false;
				}
				else
				{
					echo $rsp;
				}
				
				exit;
			}
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkDerivedRatepaln - " . $e);
			$returnerr = $this->handleException($e);
				if(isset($this->objcallflag) && trim($this->objcallflag)==1)
				{
					$this->returnmsg = $returnerr;
					return 0;
				}
				else
				{
					echo $returnerr;
				}
				exit;
		}
	}
	//Dharti - END
	
}

?>
