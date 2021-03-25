<?php
require_once("pmsinterface_connect.php");
require_once("commonconnection.php");//Sanjay - 11 Dec 2019 - Db migration [CEN-1017]
require_once("commonfunction.php");//Sanjay - 11 Dec 2019 - Db migration [CEN-1017]
$obj = new reservation();
$obj->fetchRequest();

class reservation
{
	public $module = "pms_integration";
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
	//Purpose : Addons requested by 3rd parties using our API - RoomerPMS - Upon discussion with Harshdeep
	private $ignoreRatePlan=array();
	private $rateplan;
	private $rateplanid;
	private $coa;
	private $cod;
	private $minnights;
	private $stopsell;
	public $logmsg;//Sanjay Waman - 22 Nov 2018 - Set Description at PMS level 
	public $pmsrequest;//Sanjay Waman - 30 Oct 2018 - PMS booking log
	public $commonfunc;
	private $ignoreDerivedRatePlan=array(); //Dharti Savaliya 2021-01-09 Purpose : Derivedrateplan rate update stop CEN-1875
	public function fetchRequest()
	{
		$this->log = new pmslogger($this->module);
		$this->commonfunc=new commonfunction();//Sanjay- 11 Dec 2019 - Db migration [CEN-1017]									
		$this->commonfunc->module = $this->module;
		try
		{
			//to check the connection from browser
			/*if(isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT']!=''){
				echo "Connection Successful..!!";
				exit(0);
			}*/
			
			//Fetch Request
			$requ = file_get_contents("php://input");
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
				//Sanjay Waman - 30 Apr 2019 - Parsing error added - Start
				if (!$request_xml) {
					$this->log->logIt(">> Parsing Error");
					$this->commonfunc->generateErrorMsg('501', "Invalid xml tags found in the request.",1);
					exit;
				}
				else
				{
					$this->log->logIt(">> No parsing error");
				}
				//Sanjay Waman - End
			} 
			catch (Exception $e) 
			{
				$this->log->logIt("Exception in " . $this->module . " - onLoad - " . $e);
				$this->commonfunc->generateErrorMsg('501', "Invalid xml tags found in the request.",1);				
			}
			
			//Request XML blank
			if($request_xml==='')
				$this->commonfunc->generateErrorMsg('100', "Missing required parameters.",1);
			else
			{
				$this->reqType = '';
				
				//Set Request Type
				if(isset($request_xml->Request_Type) && $request_xml->Request_Type!='')
					$this->reqType = $request_xml->Request_Type;
				
				//Validate other paramters	
				if ($this->reqType != '') 
					$res = $this->setAndValidateRequest($this->reqType, $request_xml);
				else 
				 	$this->commonfunc->generateErrorMsg('502', "Request Type is missing",0);
			 }
			 
			 
			 global $pmsrequest;//Sanjay Waman - 30 Oct 2018 - PMS Booking logs
			// global $derivedrateplan; //Dharti Savaliya 2021-01-09 Purpose : Derivedrateplan rate update stop CEN-1875
			//Sanjay Waman - 30 Oct 2018 - PMS Booking logs - Start
			$this->pmsrequest='';
			if($this->reqType.'' == 'BookingRecdNotification' || $this->reqType.'' == 'BookingRecdNotificationCM' || $this->reqType.'' == 'Bookings')//|| $this->reqType.'' == 'ReqSameBookings'
			{
				$this->pmsrequest = (String)$requ;
			}
			//Sanjay Waman - End
			//Sanjay- 11 Dec 2019 - Db migration [CEN-1017] - Start
			try
			{		
				$this->log->logIt("Varify Data start ......");
				if($this->hotelcode == '' || $this->authcode == '')
				{
					$this->commonfunc->generateErrorMsg('301', "Unauthorized Request. Please check hotel code and authentication code",1);		
				}
				else
				{
					$this->commoncon = new commonconnection();
					$this->commoncon->module = $this->module;
					$this->commoncon->hotelcode = $this->hotelcode;
					$connection=$this->commoncon->replicaconnection_V2();									
					$accessflag = $this->commonfunc->isAuthorizedUser($this->hotelcode,$this->authcode);
					$this->log->logIt("Accessflag >>".$accessflag);
					
					if($accessflag == 0)
					{
						$this->commonfunc->generateErrorMsg('301', "Unauthorized Request. Please check hotel code and authentication code",1);	
					}
					else if($accessflag == 2)
					{
						$this->commonfunc->generateErrorMsg('303', "Auth Code is inactive.",1);
					}
					$this->commoncon->removeconnection($connection);
				}
				$this->log->logIt("Varify Data end ......");
			}
			catch (Exception $e) 
			{
				$this->log->logIt("Error in Varification");
				$this->commonfunc->generateErrorMsg('301', "Please check hotel code and authentication code",1);		
			}
			//Sanjay- 11 Dec 2019 - Db migration [CEN-1017] - End 
			//Sushma Rana - PUT log As jigensh request.
			//$this->log->logIt($_SERVER);
			if(isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) && $_SERVER["HTTP_X_FORWARDED_PROTO"] != '')
			{
				$this->log->logIt("HOTEL:".$this->hotelcode.":HTTP-PORT:". $_SERVER["HTTP_X_FORWARDED_PROTO"]);
			}
			else{
				$this->log->logIt("HOTEL:".$this->hotelcode.":HTTP-HOST:". $_SERVER["HTTP_HOST"]);
			}
			
			//Sanjay- 11 Dec 2019 - Db migration [CEN-1017] - Start
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
								echo '<?xml version="1.0" encoding="UTF-8"?>
										<RES_Response>
											<Reservations></Reservations>
											<Errors>
												<ErrorCode>0</ErrorCode>
												<ErrorMessage>Success</ErrorMessage>
											</Errors>
										</RES_Response>';
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
			 if (!$this->isIntegrationAllowed()) 
			 	$this->commonfunc->generateErrorMsg('302', "Unauthorized Request. Integration is not allowed",0);
			
			 //Check required data is available or not in hotel
			 $this->isRequiredDataAvailable();	
			 
			 //Process Request	
			 $this->processRequest($this->reqType);
			 $this->commoncon->removeconnection($connection);//Sanjay- 11 Dec 2019 - Db migration [CEN-1017]
		}
		catch (Exception $e) 
		{
            $this->log->logIt("Exception in " . $this->module . " - fetchRequest - " . $e);
            $this->handleException($e);
        }
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
            $this->handleException($e);
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
			$this->logmsg = isset($requestparams->Reason)?$requestparams->Reason.'':'';//Sanjay Waman - 22 Nov 2018 - Set Description at PMS leve 
            if ($this->hotelcode == '') 
                $this->commonfunc->generateErrorMsg('101', "Hotel Code is missing",1);
            if ($this->authcode == '') 
				$this->commonfunc->generateErrorMsg('102', "Authentication Code is missing",1);
			
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
                               $this->commonfunc->generateErrorMsg('103', "Room type is missing",1);
                        }
                    }
                    else {
                        $this->commonfunc->generateErrorMsg('103', "Room type is missing",1);
                    }
                   if (count($this->avb) > 0) {
                        foreach ($this->avb as $key=>$av) {
                            if ($av == '')
                               $this->commonfunc->generateErrorMsg('110', "Inventory value is missing",1);

				if ((strcmp(strval(intval($av)), $av)))							
					$this->commonfunc->generateErrorMsg('111', "Invalid inventory value",1);
					
				if(intval($av) < 0)
					$this->avb[$key]=0;
                        }
                    } else {
                         $this->commonfunc->generateErrorMsg('110', "Inventory value is missing",1);
                    }
                    break;
                case 'UpdateRoomRates':
                    $this->ratetype = $requestparams->xpath('//RateType');
                    $this->ratetypeid = $requestparams->xpath('//RateType/RateTypeID');
                    $this->roomtypeid = $requestparams->xpath('//RateType/RoomTypeID');					
                    $this->fromdate = $requestparams->xpath('//RateType/FromDate');
                    $this->todate = $requestparams->xpath('//RateType/ToDate');
                    $this->roomrate = $requestparams->xpath('//RateType/RoomRate');
                    $this->contacts = $requestparams->xpath('//Sources/ContactId');	//Sushma - 1.0.52.60 - 25 July 2017 - START
																					//Purpose : Addons requested by 3rd parties using our API - RoomerPMS
                    if (count($this->ratetypeid) > 0) {
                        foreach ($this->ratetypeid as $rt) {
                            if ($rt == '')
                                $this->commonfunc->generateErrorMsg('104', "Rate type is missing",1);
                        }
                    }
                    else {
                        $this->commonfunc->generateErrorMsg('104', "Rate type is missing",1);
                    }
					if (count($this->roomtypeid) > 0) {
                        foreach ($this->roomtypeid as $rt) {
                            if ($rt == '')
                                $this->commonfunc->generateErrorMsg('103', "Room type is missing",1);
                        }
                    }
                    else {
                         $this->commonfunc->generateErrorMsg('103', "Room type is missing",1);
                    }
					
                    if (count($this->roomrate) > 0)
					{						
						$flag=0;
								foreach ($this->roomrate as $rr) {							                          
						if(isset($rr->Base)){
							if (is_numeric(strval($rr->Base)) == false) 
								$this->commonfunc->generateErrorMsg('113', "Invalid base rate",1);
						}
						else
							$flag++;
						
						if(isset($rr->ExtraAdult)){
							if (is_numeric(strval($rr->ExtraAdult)) == false)
								$this->commonfunc->generateErrorMsg('114', "Invalid extra adult rate",1);
						}
						else
							$flag++;
							
						if(isset($rr->ExtraChild)){
							if (is_numeric(strval($rr->ExtraChild)) == false)
								$this->commonfunc->generateErrorMsg('115', "Invalid extra child rate",1);
						}
						else
							$flag++;
								}

								//Dharti Savaliya 2019-02-04 
								//Purpose:-When we try to multiple 3 request includibg extradult  at that time issue create So i need to comment below code
							// 	if($flag==3)
							// $this->commonfunc->generateErrorMsg('121', "No Rates to update",1);				
					} 
					else {
                       	$this->commonfunc->generateErrorMsg('121', "No Rates to update",1);		
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
							   $this->commonfunc->generateErrorMsg('104', "Rate type is missing",1);
					   }
				   }
				   else {
					   $this->commonfunc->generateErrorMsg('104', "Rate type is missing",1);
				   }
				   if (count($this->roomtypeid) > 0) {
					   foreach ($this->roomtypeid as $rt) {
						   if ($rt == '')
							   $this->commonfunc->generateErrorMsg('103', "Room type is missing",1);
					   }
				   }
				   else {
						$this->commonfunc->generateErrorMsg('103', "Room type is missing",1);
				   }
				   
				   if (count($this->roomrate) > 0)
					{						
					$flag=0;
					foreach ($this->roomrate as $rr) {							                          
							/*if(isset($rr->Base)){
								if (is_numeric(strval($rr->Base)) == false) 
									$this->commonfunc->generateErrorMsg('113', "Invalid base rate",1);
							}
							else
								$flag++;
							
							if(isset($rr->ExtraAdult)){
								if (is_numeric(strval($rr->ExtraAdult)) == false)
									$this->commonfunc->generateErrorMsg('114', "Invalid extra adult rate",1);
							}
							else
								$flag++;
								
							if(isset($rr->ExtraChild)){
								if (is_numeric(strval($rr->ExtraChild)) == false)
									$this->commonfunc->generateErrorMsg('115', "Invalid extra child rate",1);
							}
							else
							$flag++;*/
							
							if(isset($rr->Adultrate1) || isset($rr->Adultrate2) || isset($rr->Adultrate3) || isset($rr->Adultrate4) || isset($rr->Adultrate5) || isset($rr->Adultrate6) || isset($rr->Adultrate7) ){
								if (is_numeric(strval($rr->Adultrate1)) == false || is_numeric(strval($rr->Adultrate2)) == false || is_numeric(strval($rr->Adultrate3)) == false || is_numeric(strval($rr->Adultrate4)) == false || is_numeric(strval($rr->Adultrate5)) == false || is_numeric(strval($rr->Adultrate6)) == false || is_numeric(strval($rr->Adultrate7)) == false)
									$this->commonfunc->generateErrorMsg('135', "Invalid rate for any between adult1 to adult7",1);
							}
							else
							$flag++;
							
							/*if(isset($rr->Childrate1) || isset($rr->Childrate2) || isset($rr->Childrate3) || isset($rr->Childrate4) || isset($rr->Childrate5) || isset($rr->Childrate6) || isset($rr->Childrate7) ){
								if (is_numeric(strval($rr->Childrate1)) == false || is_numeric(strval($rr->Childrate2)) == false || is_numeric(strval($rr->Childrate3)) == false || is_numeric(strval($rr->Childrate4)) == false || is_numeric(strval($rr->Childrate5)) == false || is_numeric(strval($rr->Childrate6)) == false || is_numeric(strval($rr->Childrate7)) == false)
									$this->commonfunc->generateErrorMsg('135', "Invalid rate for any between child1 to child7",1);
							}
							else
							$flag++;*/
					}
					if($flag==5)
						   $this->commonfunc->generateErrorMsg('121', "No Rates to update",1);				
					} else {
					   $this->commonfunc->generateErrorMsg('121', "No Rates to update",1);		
					}					
				   break;
			case "BookingRecdNotification":
				 $this->notifiedBookings = $requestparams->xpath('//Bookings/Booking');
				  if (count($this->notifiedBookings) > 0) {
					 foreach ($this->notifiedBookings as $booking) {
						 if ($booking->BookingId == '')	
							  $this->commonfunc->generateErrorMsg('117', "Booking id(s) missing in booking received notification request",1);								 							 
						 if ($booking->PMS_BookingId == '')	  
							  $this->commonfunc->generateErrorMsg('118', "PMS Booking id(s) missing in booking received notification request",1);	
					 }					  	
				  }
				  else
					 $this->commonfunc->generateErrorMsg('100', "Missing required parameters",1);
				break;
			//Sanjay Waman - 23 Oct 2018 - Changes regarding manully posted - Start
			case "BookingRecdNotificationCM":
				 $this->notifiedBookings = $requestparams->xpath('//Bookings/Booking');
				  if (count($this->notifiedBookings) > 0) {
					 foreach ($this->notifiedBookings as $booking) {
						 if ($booking->BookingId == '')	
							  $this->commonfunc->generateErrorMsg('117', "Booking id(s) missing in booking received notification request",1);								 							 
						 if ($booking->PMS_BookingId == '')	  
							  $this->commonfunc->generateErrorMsg('118', "PMS Booking id(s) missing in booking received notification request",1);	
					 }					  	
				  }
				  else
					 $this->commonfunc->generateErrorMsg('100', "Missing required parameters",1);
				break;
			//Sanjay Waman - End
			
			case "CancelBookingNotificationFromPMS":
				 $this->notifiedBookings = $requestparams->xpath('//Bookings/Booking');
				  if (count($this->notifiedBookings) > 0) {
					 foreach ($this->notifiedBookings as $booking) {
						 if ($booking->BookingId == '')							 
							$this->commonfunc->generateErrorMsg('119', "Booking id(s) missing in cancel booking notification request",1);	
						 if ($booking->PMS_BookingId == '')							 
							  $this->commonfunc->generateErrorMsg('120', "PMS Booking id(s) missing in cancel booking notification request",1);	
					 }					  	
				  }
				   else
					 $this->commonfunc->generateErrorMsg('100', "Missing required parameters",1);
				break;
			case 'UpdateCOA'://Manali - 1.0.47.52 - 05 Nov 2015,Purpose : Addons requested by 3rd parties using our API - RoomerPMS - Upon discussion with Harshdeep	    
						$this->rateplan = $requestparams->xpath('//RatePlan');
						$this->rateplanid = $requestparams->xpath('//RatePlan/RatePlanID');
						$this->fromdate = $requestparams->xpath('//RatePlan/FromDate');
						$this->todate = $requestparams->xpath('//RatePlan/ToDate');
						$this->coa = $requestparams->xpath('//RatePlan/COA');
			   
				if (count($this->rateplanid) > 0) {
							foreach ($this->rateplanid as $rt) {
								if ($rt == '')
									$this->commonfunc->generateErrorMsg('122', "Rate Plan ID is missing",1);
							}
						}
						else 
							$this->commonfunc->generateErrorMsg('122', "Rate Plan ID is missing",1);
						
				if (count($this->coa) > 0) {
							foreach ($this->coa as $key=>$av) {
								if ($av == '')
								   $this->commonfunc->generateErrorMsg('123', "COA value is missing",1);
	
					if ((strcmp(strval(intval($av)), $av)))							
						$this->commonfunc->generateErrorMsg('124', "Invalid COA value",1);
						
					if(intval($av) < 0)
						$this->coa[$key]=0;
							}
						}
				else 
							 $this->commonfunc->generateErrorMsg('123', "COA value is missing",1);
					   break;
			case 'UpdateCOD'://Manali - 1.0.47.52 - 10 Nov 2015,Purpose : Addons requested by 3rd parties using our API - RoomerPMS - Upon discussion with Harshdeep	    
						$this->rateplan = $requestparams->xpath('//RatePlan');
						$this->rateplanid = $requestparams->xpath('//RatePlan/RatePlanID');
						$this->fromdate = $requestparams->xpath('//RatePlan/FromDate');
						$this->todate = $requestparams->xpath('//RatePlan/ToDate');
						$this->cod = $requestparams->xpath('//RatePlan/COD');
			   
				if (count($this->rateplanid) > 0) {
							foreach ($this->rateplanid as $rt) {
								if ($rt == '')
									$this->commonfunc->generateErrorMsg('122', "Rate Plan ID is missing",1);
							}
						}
						else 
							$this->commonfunc->generateErrorMsg('122', "Rate Plan ID is missing",1);
						
				if (count($this->cod) > 0) {
							foreach ($this->cod as $key=>$av) {
								if ($av == '')
								   $this->commonfunc->generateErrorMsg('125', "COD value is missing",1);
	
					if ((strcmp(strval(intval($av)), $av)))							
						$this->commonfunc->generateErrorMsg('126', "Invalid COD value",1);
						
					if(intval($av) < 0)
						$this->cod[$key]=0;
							}
						}
				else 
							 $this->commonfunc->generateErrorMsg('125', "COD value is missing",1);
					   break;
			case 'UpdateMinNights'://Manali - 1.0.47.52 - 27 Nov 2015,Purpose : Addons requested by 3rd parties using our API - RoomerPMS - Upon discussion with Harshdeep	    
						$this->rateplan = $requestparams->xpath('//RatePlan');
						$this->rateplanid = $requestparams->xpath('//RatePlan/RatePlanID');
						$this->fromdate = $requestparams->xpath('//RatePlan/FromDate');
						$this->todate = $requestparams->xpath('//RatePlan/ToDate');
						$this->minnights = $requestparams->xpath('//RatePlan/MinNight');
			   
				if (count($this->rateplanid) > 0) {
							foreach ($this->rateplanid as $rt) {
								if ($rt == '')
									$this->commonfunc->generateErrorMsg('122', "Rate Plan ID is missing",1);
							}
						}
						else 
							$this->commonfunc->generateErrorMsg('122', "Rate Plan ID is missing",1);
						
				if (count($this->minnights) > 0) {
							foreach ($this->minnights as $key=>$av) {
								if ($av == '')
								{
								   $this->commonfunc->generateErrorMsg('127', "MinNight value is missing",1);
								}
								//Sushma 24-07-2017 -START
								//purpose- When minof night value 0 then not update
								if($av == 0)
								{
									$this->commonfunc->generateErrorMsg('128', "Invalid MinNight value",1);
								}
								//Sushma 24-07-2017 -END
		
					if ((strcmp(strval(intval($av)), $av)))							
						$this->commonfunc->generateErrorMsg('128', "Invalid MinNight value",1);
						
					if(intval($av) < 0)
						$this->minnights[$key]=0;
							}
						}
				else 
							 $this->commonfunc->generateErrorMsg('127', "MinNight value is missing",1);
					   break;
			// Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel - start
			case 'UpdateMaxNights':
						$this->rateplan = $requestparams->xpath('//RatePlan');
						$this->rateplanid = $requestparams->xpath('//RatePlan/RatePlanID');
						$this->fromdate = $requestparams->xpath('//RatePlan/FromDate');
						$this->todate = $requestparams->xpath('//RatePlan/ToDate');
						$this->maxnights = $requestparams->xpath('//RatePlan/MaxNight');
			   
				if (count($this->rateplanid) > 0) {
							foreach ($this->rateplanid as $rt) {
								if ($rt == '')
									$this->commonfunc->generateErrorMsg('122', "Rate Plan ID is missing",1);
							}
						}
						else 
							$this->commonfunc->generateErrorMsg('122', "Rate Plan ID is missing",1);
						
				if (count($this->maxnights) > 0) {
							foreach ($this->maxnights as $key=>$av) {
								if ($av == '')
								{
								   $this->commonfunc->generateErrorMsg('127', "Maxnight value is missing",1);
								}							
								if($av == 0)
								{
									$this->commonfunc->generateErrorMsg('128', "Invalid MaxNight value",1);
								}							
		
					if ((strcmp(strval(intval($av)), $av)))							
						$this->commonfunc->generateErrorMsg('128', "Invalid MaxNight value",1);
						
					if(intval($av) < 0)
						$this->maxnights[$key]=0;
							}
						}
				else 
							 $this->commonfunc->generateErrorMsg('127', "MaxNight value is missing",1);
					   break;
			// Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel - end
			case 'UpdateStopSell'://Manali - 1.0.47.52 - 27 Nov 2015,Purpose : Addons requested by 3rd parties using our API - RoomerPMS - Upon discussion with Harshdeep	    
						$this->rateplan = $requestparams->xpath('//RatePlan');
						$this->rateplanid = $requestparams->xpath('//RatePlan/RatePlanID');
						$this->fromdate = $requestparams->xpath('//RatePlan/FromDate');
						$this->todate = $requestparams->xpath('//RatePlan/ToDate');
						$this->stopsell = $requestparams->xpath('//RatePlan/StopSell');
						$this->contacts = $requestparams->xpath('//Sources/ContactId'); //Sushma - 1.0.52.60 - 25 July 2017 - START
																						//Purpose : Addons requested by 3rd parties using our API - RoomerPMS
				if (count($this->rateplanid) > 0) {
							foreach ($this->rateplanid as $rt) {
								if ($rt == '')
									$this->commonfunc->generateErrorMsg('122', "Rate Plan ID is missing",1);
							}
						}
						else 
							$this->commonfunc->generateErrorMsg('122', "Rate Plan ID is missing",1);
						
				if (count($this->stopsell) > 0) {
							foreach ($this->stopsell as $key=>$av) {
								if ($av == '')
								   $this->commonfunc->generateErrorMsg('129', "StopSell value is missing",1);
	
					if ((strcmp(strval(intval($av)), $av)))							
						$this->commonfunc->generateErrorMsg('130', "Invalid StopSell value",1);
						
					if(intval($av) < 0)
						$this->stopsell[$key]=0;
							}
						}
				else 
							 $this->commonfunc->generateErrorMsg('129', "StopSell value is missing",1);
					   break;
					
			//Added - Subhash - 08 Aug 2018	- Start
			//Purpose : For no show booking
			case 'noshowAPI':
						$this->noshowdetail = $requestparams->xpath('//noshowdetail');
						if (count($this->noshowdetail) > 0)
						{
						   foreach ($this->noshowdetail as $booking)
						   {
							  $this->log->logIt("No-Show booking Data >>>".json_encode($booking,true));
							  if ($booking->bookingID == '')
							  {
								   $this->commonfunc->generateErrorMsg('140', "Booking ID is missing",1);
							  }
							  if ($booking->VoucherNo == '')
							  {
								   $this->commonfunc->generateErrorMsg('141', "Voucher No is missing",1);
							  }
							  if ($booking->subbookingID == '')
							  {
								   $this->commonfunc->generateErrorMsg('142', "Subbooking ID is missing",1);
							  }
						   }					  	
						}
						else
						   $this->commonfunc->generateErrorMsg('100', "Missing required parameters",1);
						break;				
			//Added - Subhash - 08 Aug 2018	- End	
			}
	    	// Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel 
            if ($reqtype == "UpdateAvailability" || $reqtype == "UpdateRoomRates" ||  $reqtype == "UpdateRoomRatesNL" || $reqtype == "UpdateCOA" ||  $reqtype == "UpdateCOD" ||  $reqtype == "UpdateMinNights" ||  $reqtype == "UpdateStopSell" || $reqtype == "UpdateMaxNights") {
				
				
				// Vihang Joshi - 1.0.52.63 - 10 Jan 2018 - PMS Threshold Checking - START
                if (count($this->fromdate) > 0 && count($this->todate) > 0)
                {
					 $_fdate_array = array();
					 $_tdate_array = array();
					 $_days_count = array();   // Vihang Joshi - 1.0.52.63 - 08 Feb 2018 - PMS Threshold Checking 					 
					 
					 $cnt = $this->getRequestNodeCounts();

					 for ($i = 0; $i < $cnt; $i++) {
						
						// Vihang Joshi - 1.0.52.63 - 08 Feb 2018 - PMS Threshold Checking - START 
						$f_date = date_create((string)$this->fromdate[$i]);
						$t_date = date_create((string)$this->todate[$i]);

						$diff = date_diff($f_date,$t_date); 
						// Vihang Joshi - 1.0.52.63 - 08 Feb 2018 - PMS Threshold Checking - END
						
						$_fdate_array[]=(string)$this->fromdate[$i];
						$_tdate_array[]=(string)$this->todate[$i];
						$_days_count[]=($diff->format("%a"))+1; // Vihang Joshi - 1.0.52.63 - 08 Feb 2018 - PMS Threshold Checking - Sushma rana // Add +1, Because it disply wrong count if single sinel request
					 }
					 
					 // Vihang Joshi - 1.0.53.61 - 13 Feb 2018 - Set params in array - START
					 $req_array = array(
									"_fdate_array" => $_fdate_array,
									"_tdate_array" => $_tdate_array,
									"_days_count" => $_days_count,
									"_reqtype" => $reqtype,
								  );
					 // Vihang Joshi - 1.0.53.61 - 13 Feb 2018 - Set params in array - END
					 
					 $ObjPMSInterfaceConn = new pmsinterface_connect();
					 //Sanjay Waman - 22 Aug 2018 - threshold_check not check for Mews PMS -START
				
						$ObjPMSInterfaceConn->threshold_check($this->hotelcode,"reservation",$req_array); // Vihang Joshi - 1.0.52.63 - 08 Feb 2018 - PMS Threshold Checking - Added $_days_count parameter
					//Sanjay Waman - 22 Aug 2018 - END
				}
                // Vihang Joshi - 1.0.52.63 - 10 Jan 2018 - PMS Threshold Checking - END
				
                if (count($this->fromdate) > 0) {
                    foreach ($this->fromdate as $dt) {
                        if ($dt == '')
                            $this->commonfunc->generateErrorMsg('105', "From Date is missing",1);	
                    }
                }
                else {
                    $this->commonfunc->generateErrorMsg('105', "From Date is missing",1);	
                }
                if (!$this->checkDate($this->fromdate)) {
                     $this->commonfunc->generateErrorMsg('106', $this->tdate.' - From Date is not a valid date',1);	
                }
                
                
                
                if (count($this->todate) > 0) {
                    foreach ($this->todate as $dt) {
                        if ($dt == '')
							$this->commonfunc->generateErrorMsg('107','To Date is missing',1);	
                    }
                }
                else {
                   $this->commonfunc->generateErrorMsg('107','To Date is missing',1);	
                }
                if (!$this->checkDate($this->todate)) {
                    $this->commonfunc->generateErrorMsg('108',$this->tdate.' - To Date is not a valid date',1);	
                }
                if (!($this->diffBetweenDates())) {
                     $this->commonfunc->generateErrorMsg('109','From Date:'.$this->tfromdate.' To Date : '.$this->ttodate.' - Please check From and To date. To Date should be greater than fromdate',1);	
                }
            }
            return 0;
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - setAndValidateRequest - " . $e);
            $this->handleException($e);
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
				case "BookingRecdNotificationCM"://Sanjay Waman - 23 Oct 2018 - chnages for manully posted
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
					// Dharti Savaliya 2021-01-05 Purpose : Derivedrateplan rate update stop CEN-1875
					$this->checkDerivedRatepaln();															
				break;
				case "UpdateRoomRatesNL":
					$this->checkExistanceOfRoomType();						
					$this->checkExistanceOfRateType();
					// Dharti Savaliya 2021-01-05 Purpose : Derivedrateplan rate update stop CEN-1875
					$this->checkDerivedRatepaln();	
				break;
				//Manali - 1.0.47.52 - 05 Nov 2015 - START
				//Purpose : Addons requested by 3rd parties using our API - RoomerPMS - Upon discussion with Harshdeep
				case 'UpdateCOA':
				case 'UpdateCOD':
				case 'UpdateMinNights':
				case 'UpdateMaxNights': //// Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel 
				case 'UpdateStopSell':
					$this->checkExistanceOfRatePlan();
				break;
				//Manali - 1.0.47.52 - 05 Nov 2015 - END
			}          
        } 
		catch (Exception $e) 
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isRequiredDataAvailable - " . $e);
            $this->handleException($e);
        }
    }
	
	private function checkExistanceOfBookings()
	{
		 try 
		 {
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "checkExistanceOfBookings");
			 foreach ($this->notifiedBookings as $booking) 
			 {
				if(!$this->isBookingExists($booking->BookingId,isset($booking->SubBookingId)?strval($booking->SubBookingId):'')) 
				{
					array_push($this->excludeBookings,$booking->BookingId.((isset($booking->SubBookingId) && $booking->SubBookingId!='')?'-'.strval($booking->SubBookingId):''));
				}				
			}
		}
		catch (Exception $e) 
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkExistanceOfBookings - " . $e);
			$this->handleException($e);
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
			$this->handleException($e);
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
            $this->handleException($e);
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
			$this->handleException($e);
		}
	}
	
	//Manali - 1.0.46.51 - 05 Nov 2015 - START
	//Purpose : Addons requested by 3rd parties using our API - RoomerPMS - Upon discussion with Harshdeep
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
			$this->handleException($e);
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
			$this->handleException($e);
		}
	    return $flag;
	}
	//Manali - 1.0.46.51 - 05 Nov 2015 - END
	
	private function isBookingExists($resno,$subresno='') 
	{
		try 
			{
		    $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "isBookingExists");
		    $flag = false;
		    $dao = new dao();
		    $strSql = "";
		    
		    //Manali - 1.0.45.50 -  28 May 2015 - START
		    //Fixed Bug: Group Booking notification was not upating
		    $pos = strpos($resno, "-");	   
		    if($pos)
			list($resno,$subresno)=explode("-",$resno);
		    //Manali - 1.0.45.50 -  28 May 2015 - END
		    
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
		    $this->handleException($e);
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
		    
		   //Manali - 1.0.45.50 -  28 May 2015 - START
		    //Fixed Bug: Group Booking notification was not upating
		    $pos = strpos($resno, "-");	   
		    if($pos)
			list($resno,$subresno)=explode("-",$resno);
		    //Manali - 1.0.45.50 -  28 May 2015 - END
		    
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
		    $this->handleException($e);
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
		    $this->handleException($e);
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
			$this->handleException($e);
		}
	    return $flag;
	}
	
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
					$pmsbookings->pmsrequest = $this->pmsrequest;//Sanjay Waman - 29 Nov 2018 - Pms booking log
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
					$pmsrates->executeRequest($this->hotelcode, $this->ratetype,$this->ratetypeid,$this->roomtypeid, $this->fromdate, $this->todate, $this->roomrate,$this->contacts,$this->ignoreRoomType,$this->ignoreRateType,$this->ignoreDerivedRatePlan); //Dharti Savaliya 2021-01-09 Purpose : Derivedrateplan rate update stop CEN-1875- START
					break; //IgnoreCheck
			case 'UpdateRoomRatesNL':
                    $pmsrates = new pms_update_roomrates_nonlinear();
					//Dharti Savaliya 2021-01-09 Purpose : Derivedrateplan rate update stop CEN-1875- START
					$pmsrates->executeRequest($this->hotelcode, $this->ratetype,$this->ratetypeid,$this->roomtypeid, $this->fromdate, $this->todate, $this->roomrate,$this->contacts,$this->ignoreRoomType,$this->ignoreRateType,$this->ignoreDerivedRatePlan); 
					//Dharti Savaliya -END
                    break; //IgnoreCheck
				case 'BookingRecdNotification':
                    $pmsbookings = new pms_bookings();
					$pmsbookings->hotelcode = $this->hotelcode;					
                    $pmsbookings->updateBookingStatus($this->notifiedBookings,$this->excludeBookings,0,$this->pmsrequest);//Sanjay Waman - 30 Oct 2018 - paas notification xml for logs
                    break;
				case 'BookingRecdNotificationCM':
                    $pmsbookings = new pms_bookings();
					$pmsbookings->pmsDescription = isset($this->logmsg)?$this->logmsg.'':"";//Sanjay Waman - 22 Nov 2018 - Set Description at PMS level 
					$this->log->logIt("Hotel_" . $this->hotelcode . "-" . "BookingRecdNotificationCM - Description :::::>".$pmsbookings->pmsDescription.'');//Sanjay Waman - 22 Nov 2018 - Set Description at PMS level
					$pmsbookings->hotelcode = $this->hotelcode;					
                    $pmsbookings->updateBookingStatus($this->notifiedBookings,$this->excludeBookings,1,$this->pmsrequest);//Sanjay Waman - 30 Oct 2018 - paas notification xml for logs
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
				//Manali - 1.0.37.42 - 16 Oct 2013 - START
				//Purpose : Enhancement - Get new/cancelled bookings count if exists on web - Developed on request of Jitubhai
				case 'IsBookingsExists':
					$pmsbookings = new pms_bookings();
					$pmsbookings->hotelcode = $this->hotelcode;
                    $pmsbookings->checkBookingsExists();
                    break;
		//Manali - 1.0.37.42 - 16 Oct 2013 - END
		
		//Manali - 1.0.47.52 - 05 Nov 2015 - START
		//Purpose : Addons requested by 3rd parties using our API - RoomerPMS - Upon discussion with Harshdeep
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
		// Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel - start
		case 'UpdateMaxNights':
                    $pmsminnights = new pms_update_otheroperations();
                    $pmsminnights->executeRequest($this->hotelcode, $this->rateplan, $this->rateplanid, $this->fromdate, $this->todate, 'maxnights',$this->maxnights,'',$this->ignoreRatePlan); 
                    break;
		// Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel - end
		case 'UpdateStopSell':
                    $pmsstopsell = new pms_update_otheroperations();
                    $pmsstopsell->executeRequest($this->hotelcode, $this->rateplan, $this->rateplanid, $this->fromdate, $this->todate, 'stopsell',$this->stopsell,$this->contacts,$this->ignoreRatePlan); 
                    break;
		//Manali - 1.0.47.52 - 05 Nov 2015 - END
		//Sushma - 1.0.52.60 - 27 June 2017 - START
		//Purpose : Addons requested by 3rd parties using our API - RoomerPMS
		case 'Separatesourcemapping':
                    $pmssaparatesource = new pms_saparatesource();
                    $pmssaparatesource->hotelcode = $this->hotelcode;
                    $pmssaparatesource->executeRequest();
                    break;	
					
                default:
                    $this->commonfunc->generateErrorMsg('502', "Request Type is missing",1);
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
            $this->handleException($e);
        }
    }
    
    /* Vihang Joshi - 1.0.53.61 - 4 Jan 2018
     * Purpose: Don't allow system to process the request if from date is found after 2 years in single request.
     * 		Modified By: Vihang Joshi - 1.0.53.61 - 9 Jan 2018
     *      Modified Content: Added code which gets the min and max date from request and performs certain action based on it.
     */
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
            
            $cnt = $this->getRequestNodeCounts(); // Vihang Joshi - 1.0.53.61 - 9 Jan 2018 - Purpose: Created function for the same reason mentioned above function.
            
            // Vihang Joshi - 1.0.53.61 - 9 Jan 2018 - START 
            if($cnt == 0){
				return false;
			}    
			// Vihang Joshi - 1.0.53.61 - 9 Jan 2018 - END
			
            
            for ($i = 0; $i < $cnt; $i++) {
                $_fdate_array[]=(string)$this->fromdate[$i];
                $_tdate_array[]=(string)$this->todate[$i];
            }
                        
			global $log_info_path;
			
			$str = $this->hotelcode."#".json_encode($_fdate_array)."#".json_encode($_tdate_array).PHP_EOL;
			$debug_log = $log_info_path."/".date('Y_m_d')."_pms_debug_logs.txt";
			$debug_handle = fopen($debug_log, 'a');
			fwrite($debug_handle,$str);
			fclose($debug_handle);
			
			$min_from_date = min($_fdate_array);
			$min_to_date   = min($_tdate_array);
			$max_from_date = max($_fdate_array);
			$max_to_date   = max($_tdate_array);
			
			$days_limit = 730; // 2 years
			
			// Count days difference from the dates receiving in XML request - START
			$from_date = new DateTime($min_from_date);
			$to_date   = new DateTime($max_to_date);
			$dt_diff   = $to_date->diff($from_date)->format('%a');  // Get the difference in days 
			
			$this->log->logIt("Hotel_" . $this->hotelcode . "-" . " >> ".$min_from_date." | ".$min_to_date." | ".$max_from_date." | ".$max_to_date);
			$this->log->logIt("Hotel_" . $this->hotelcode . "-" . "dt_diff >> ".$dt_diff);
			// Count days difference from the dates receiving in XML request - END
			
			// Count days difference from current date with dates receiving in XML request - START
			$dStart_fdate = new DateTime($min_from_date);
			$dEnd_fdate  = new DateTime(date('Y-m-d'));
			$fdDiff = $dEnd_fdate->diff($dStart_fdate)->format('%a');
			$this->log->logIt("Hotel_" . $this->hotelcode . "-" . " fdDiff >> ".$fdDiff);
			
			// Count days difference from current date with dates receiving in XML request - END
			
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
            $this->handleException($e);
        }
    }
	/* End */
	
	/* Modified By: Vihang Joshi - 1.0.53.61 - 9 Jan 2018
     * Modified Content: Modified condition that will check diffBetweenDates for these oprations - UpdateCOA, UpdateCOD, UpdateMinNights, UpdateStopSell.
     * Reason To Modify: Previously there was no functionality to check the correct dates.
     */
    private function diffBetweenDates() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "diffBetweenDates");
            
            $cnt = $this->getRequestNodeCounts(); // Vihang Joshi - 1.0.53.61 - 9 Jan 2018 - Purpose: Created function for the same reason mentioned above function.
             
            // Vihang Joshi - 1.0.53.61 - 9 Jan 2018 - START 
            if($cnt == 0){
				return false;
			}    
			// Vihang Joshi - 1.0.53.61 - 9 Jan 2018 - END
			
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
            $this->handleException($e);
        }
    }
	
	/*public function generateErrorMsg($code,$msg,$isexit=0)
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
		echo $xmlDoc->saveXML();
		
		if($isexit==1)
			exit;
		else
			return;
	}*/
	
    private function handleException($e) 
	{
        try 
		{
            $this->log->logIt(get_class($this) . "-" . "handleException");
			$this->commonfunc->generateErrorMsg('500', "Error occured during processing",1);            
        } 
		catch (Exception $e) 
		{
            $this->log->logIt(get_class($this) . "-" . "handleException" . "-" . $e);
            exit;
        }
    }
    
    // Vihang Joshi - 1.0.53.61 - 9 Jan 2018 - START
    // Purpose: Get total no. of nodes in request.
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
				$this->handleException($e);
			}
	}
	// Vihang Joshi - 1.0.53.61 - 9 Jan 2018 - END
	
	//Dharti Savaliya - 05 Jan 2021 - Purpose : Derivedrateplan rate update stop CEN-1875- START
	private function checkDerivedRatepaln()
	{
		 try 
		 {
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
				$this->commonfunc->generateErrorMsg('501', "Derived  rate plan values cannot be changed directly, please update master rate plan to make change in derived rate plan.",1);
				exit;
			 }
		}
		catch (Exception $e) {
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkDerivedRatepaln - " . $e);
			$this->handleException($e);
		}
	}
	//Dharti - END
}

?>
