<?php

class pms_bookings {

    private $log;
    private $module = "pms_bookings";
    private $hotelcode;
    private $xmlDoc;
    private $xmlRoot;
    private $xmlReservation;
	public $objSequenceDao;//Manali - 20th Mar 2017,Purpose : Enhancement - Separate CC Servers Functionality - Change logs
    #Object Fields - Getter/Setter - Start
    public $objMasterDao2; // Sushma- 24th August2017
	public $pmsDescription;//Sanjay Waman - 22 Nov 2018 - Set Description at PMS leve
	public $pmsrequest;//Sanjay Waman - 29 Nov 2018 - pull booking log

	public $auditlogs; // Dharti 2019-06-24 Purpose:- If new booking not transfer at pms end and booking came into the modify status at that time booking status as a new

	public $mongodb;//Sanjay Waman - 28 Jun 2019 - For Mongo DB Connection (Family Hotel PMS)
	public $returnflag;//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]


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
        } else {
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
	
	// HRK - 1.0.46.51 - 14 Aug 2015 - START
	// Purpose : Push booking logic, Added one optional parameter
    public function executeRequest($getbooking=0)
	{
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "executeRequest");
            $this->xmlReservation = $this->xmlDoc->createElement("Reservations");
            $this->xmlRoot->appendChild($this->xmlReservation);
			if($this->isTransferAllowed())
			{
				//preparing xml
				//Sushma Rana - 3rd July 2019 - Change for PMS booking queue - CEN-1151	- start	
				$Pmsqueueflag = 0;
				$arrbookingqueuehotel = $arrbookingqueuepms = $arrbookingqueuedata = array();
				$thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");						
				$log_info_path = "/home/saasfinal/pmsinterface";	
				$file = $log_info_path."/pmsqueue.json";
				//$this->log->logIt("file location >>".$file);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				if(file_exists($file))
				{
					$str  = file_get_contents($file);
					$arrbookingqueuedata = json_decode($str, true);	
					//$this->log->logIt("Pms queue data >>".print_r($arrbookingqueuedata,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					$arrbookingqueuepms = $arrbookingqueuedata['pms'];
					//$this->log->logIt("Pms name array >>".print_r($arrbookingqueuepms,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					if(in_array($thirdparty_pms,$arrbookingqueuepms))
					{
						$arrbookingqueuehotel = $arrbookingqueuedata['hotelcode'];
						//$this->log->logIt("Hotel data >>".json_encode($arrbookingqueuehotel,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
						//$this->log->logIt("session hotelcode >>".$this->hotelcode);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
						if(in_array($this->hotelcode,$arrbookingqueuehotel))
						{
							$Pmsqueueflag = 1;
						}
						else if(count($arrbookingqueuehotel) == 0)
						{
							$this->log->logIt("Hotelcount not found");	
							$Pmsqueueflag = 1;
						}							
					}
				}
				$this->log->logIt("Flag value >>".$Pmsqueueflag);	
				
				if(isset($Pmsqueueflag) && $Pmsqueueflag == 1)
				{
					
					$this->prepareResponseQueue();
				}
				else
				{
					$this->prepareResponse();
				}
				
				$str = $this->xmlDoc->saveXML();				
				// HRK - 1.0.46.51 - 14 Aug 2015 - START
				// Purpose : Push booking logic
				if($getbooking)
				{
					return $str;
				}
				else
				{
					//Sanjay Waman - 29 Nov 2018 - Booking Logs for push - Start
					$bookingauditlogs = new pmsbookingauditlogs();
					$bookingauditlogs->module = "pms_integration";
					$thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");
					$result = $this->getsyspmsdetail($thirdparty_pms);
					if(isset($result['pmsname']) && isset($result['description']) && $result['description'] == "ezeexmlformat" && $result['pushbooking'] == 0)
					{
						$bookingauditlogs->reservationlogs($str,$this->pmsrequest,$thirdparty_pms,"PULL");
						$this->log->logIt("Booking Inserted in pmsbookinglogs");
					}
					//Sanjay Waman - End
					/*if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
					{
						return $str;
					}*/
					echo $str;
					exit;
					return;
				}
				// HRK - 1.0.46.51 - 14 Aug 2015 - END
			}
			else
			{
				$this->generateGeneralErrorMsg('303', "Fetching of bookings is not allowed. Please check the settings on reservation engine.");
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 12 jul 2019 - Return flag[CEN-1165]
				{
					return $this->xmlDoc->saveXML();
				}
                echo $this->xmlDoc->saveXML();
                return;
			}
        }
		catch (Exception $e)
		{
            $this->log->logIt("Exception in " . $this->module . " - executeRequest - " . $e);
            $this->handleException($e);
        }
    }

    public function updateBookingStatus($bookings,$excludeBookings,$manullyFlag=0,$pmsrequest='')  //Manali - 1.0.31.36 - 11 Feb 2013, Purpose : Fixed Bug - If bookings sent in group and if any one of them does not exists at our end, it will stop processing other bookings also. So placed this variable for checking // Sanjay Waman - 23 Oct 2018 - Manully flag added
	{
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "updateBookingStatus");
            if(isset($this->returnflag))//Sanjay Waman - 10 Sep 2019 - Return flag for json format [CEN-1086]
			{
				$this->log->logIt("ReturnFormat>>>".$this->returnflag);
			}
			$adminuserunkid = $this->getAdminUser();
            $bookingsUpdated = $groupbookings_count = 0;
			$bookingIDs=array();//Sanjay Waman - 30 Oct 2018 - Booking logs
			
            foreach ($bookings as $booking) {
				
				$resno_new = '';
				$subresno = '';
			 	if(!isset($booking->SubBookingId) || $booking->SubBookingId=='')
				{
					$resno_arr = explode('-',$booking->BookingId);
				
					if(count($resno_arr)>2)
					{
						for($r=0;$r<(count($resno_arr)-1);$r++)
						{
							$resno_new .= $resno_arr[$r]."-";
						}
						$resno_new = rtrim($resno_new,'-');
						$subresno = $resno_arr[(count($resno_arr)-1)];
					}
					else{
						$resno_new = $resno_arr[0];
						$subresno = (count($resno_arr)==2)?($resno_arr[1]):'';
					}
				}
				else{
					$resno_new = $booking->BookingId;
					$subresno = strval($booking->SubBookingId);
				}
				array_push($bookingIDs,$resno_new);//Sanjay Waman - 30 Oct 2018 - Booking logs
				$bookingno = $resno_new.(($subresno!='')?'-'.$subresno:'');
				
				if(!in_array($bookingno,array_values($excludeBookings),TRUE))
				{
					$count = intval($this->updatePostedStatus(strval($resno_new),($subresno!='')?strval($subresno):'')); //Manali - 1.0.30.35 - 11 Jan 2013, Purpose : to post status of only one transaction based on reservation no and subreservation no checking in case of group reservation
					$this->log->logIt('Bookings Updated : ' . $count);
					
					
					if ($count > 0) {
						$bookingsUpdated+=1;
						$groupbookings_count+=$count;
						$tranunkid = $this->getTranunkid($resno_new,($subresno!='')?strval($subresno):''); //Manali - 1.0.30.35 - 08 Feb 2013, Fixed bug - on setting notification on one transaction,it was inserting audit trails in only first transaction 
						
						$this->log->logIt($tranunkid);
						//Sanjay Waman - 23 Oct 2018 - changes regarding manully posted flag - Start
						if($manullyFlag!=0)
						{
							$this->addToAuditTrail($tranunkid, $resno_new, $adminuserunkid, $booking->PMS_BookingId,($subresno!='')?strval($subresno):'',$manullyFlag);
						}
						else
						{ 
							$this->addToAuditTrail($tranunkid, $resno_new, $adminuserunkid, $booking->PMS_BookingId,($subresno!='')?strval($subresno):''); //Manali - 1.0.30.35 - 08 Feb 2013, Purpose : to post status of only one transaction based on reservation no and subreservation no checking in case of group reservation
						}
					}
				}					
            }
			
			$this->log->logIt($bookingsUpdated."|".count($bookings));

            if ($bookingsUpdated ==count($bookings)) {
				
				//Sushma Rana - 3rd July 2019 - Change for PMS booking queue - CEN-1151	- start	
				$objpmsbookingqueue = new pmsbookingqueue();	
				$objpmsbookingqueue->hotelcode = $this->hotelcode;
				foreach($bookingIDs as $bookingid)
				{
					$Bookingqueueprocess= $objpmsbookingqueue->updatebookingqueue($bookingid);
					$this->log->logIt('Bookings Updated Queue: ' . $bookingid."-".$Bookingqueueprocess);
				}
				//Sushma Rana - 3rd July 2019 - Change for PMS booking queue - CEN-1151	- end
								
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 10 Sep 2019 - Return flag for json format [CEN-1086]
				{
					$str = $this->generateGeneralErrorJson('0', "Success",array("Success"=>array("SuccessMsg"=>strval($groupbookings_count) . ' booking(s) updated')));
				
					$thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");
					$result = $this->getsyspmsdetail($thirdparty_pms);
					if(isset($result['pmsname']) && isset($result['description']) && trim($result['description']) == "ezeejsonforamtV2" )
					{
						foreach($bookingIDs as $bookingid)
						{
							$this->log->logIt(" >> Commo PMS found << ");
							$logBookingArr=array();
							$logBookingArr[$this->hotelcode]["notification_request"]=(String)$pmsrequest;
							$logBookingArr[$this->hotelcode]["BookingID"]=(String)$bookingid;
							$logBookingArr[$this->hotelcode]["notification_flag"]=1;
							$logBookingArr[$this->hotelcode]["notification_res"]=(String)$str;
							$bookingauditlogs = new pmsbookingauditlogs();
							$bookingauditlogs->module = "pms_integration";
							$bookingauditlogs->BookingNotificationlog($logBookingArr,1);
							unset($bookingauditlogs);
						}
					}
					return $str;
				}
				else
				{
					$success = $this->xmlDoc->createElement("Success");
					$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
					$succ_msg->appendChild($this->xmlDoc->createTextNode(strval($groupbookings_count) . ' booking(s) updated'));
					$success->appendChild($succ_msg);
					$this->xmlRoot->appendChild($success);
					$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
					$str = $this->xmlDoc->saveXML();
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 12 jul 2019 - Return flag[CEN-1165]
					{
						return $str;
					}
					echo $str;	
				}
                //return;
            } 
			else if($bookingsUpdated < count($bookings)){				
				
				//Sushma Rana - 3rd July 2019 - Change for PMS booking queue - CEN-1151	- start	
				$objpmsbookingqueue = new pmsbookingqueue();	
				$objpmsbookingqueue->hotelcode = $this->hotelcode;
				foreach($bookingIDs as $bookingid)
				{
					$Bookingqueueprocess= $objpmsbookingqueue->updatebookingqueue($bookingid);
					$this->log->logIt('Bookings Updated Queue: ' . $bookingid."-".$Bookingqueueprocess);
				}
				//Sushma Rana - 3rd July 2019 - Change for PMS booking queue - CEN-1151	- end
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 10 Sep 2019 - Return flag for json format [CEN-1086]
				{
					$str = $this->generateGeneralErrorJson('501','Bookings '.implode(",",$excludeBookings).' not exists. So not updated.',array("Success"=>array("SuccessMsg"=>strval($groupbookings_count) . ' booking(s) updated')));
				
					$thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");
					$result = $this->getsyspmsdetail($thirdparty_pms);
					if(isset($result['pmsname']) && isset($result['description']) && trim($result['description']) == "ezeejsonforamtV2" )
					{
						foreach($bookingIDs as $bookingid)
						{
							$this->log->logIt(" >> Commo PMS found << ");
							$logBookingArr=array();
							$logBookingArr[$this->hotelcode]["notification_request"]=(String)$pmsrequest;
							$logBookingArr[$this->hotelcode]["BookingID"]=(String)$bookingid;
							$logBookingArr[$this->hotelcode]["notification_flag"]=1;
							$logBookingArr[$this->hotelcode]["notification_res"]=(String)$str;
							$bookingauditlogs = new pmsbookingauditlogs();
							$bookingauditlogs->module = "pms_integration";
							$bookingauditlogs->BookingNotificationlog($logBookingArr,1);
							unset($bookingauditlogs);
						}
					}
					return $str;
				}
				else
				{
					$success = $this->xmlDoc->createElement("Success");
					$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
					
					$succ_msg->appendChild($this->xmlDoc->createTextNode(strval($groupbookings_count) . ' booking(s) updated'));
					$success->appendChild($succ_msg);
					$this->xmlRoot->appendChild($success);
					$this->generateGeneralErrorMsg('501','Bookings '.implode(",",$excludeBookings).' not exists. So not updated.');
					$str = $this->xmlDoc->saveXML();
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 12 jul 2019 - Return flag[CEN-1165]
					{
						return $str;
					}
					echo $str;	
				}
                //return;
			}
			else {
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 10 Sep 2019 - Return flag for json format [CEN-1086]
				{
					$str = $this->generateGeneralErrorJson('500',"Error occured during processing.");
					$thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");
					$result = $this->getsyspmsdetail($thirdparty_pms);
					if(isset($result['pmsname']) && isset($result['description']) && trim($result['description']) == "ezeejsonforamtV2" )
					{
						foreach($bookingIDs as $bookingid)
						{
							$this->log->logIt(" >> Commo PMS found << ");
							$logBookingArr=array();
							$logBookingArr[$this->hotelcode]["notification_request"]=(String)$pmsrequest;
							$logBookingArr[$this->hotelcode]["BookingID"]=(String)$bookingid;
							$logBookingArr[$this->hotelcode]["notification_flag"]=1;
							$logBookingArr[$this->hotelcode]["notification_res"]=(String)$str;
							$bookingauditlogs = new pmsbookingauditlogs();
							$bookingauditlogs->module = "pms_integration";
							$bookingauditlogs->BookingNotificationlog($logBookingArr,1);
							unset($bookingauditlogs);
						}
					}
					return $str;
				}
				else
				{
					$this->generateGeneralErrorMsg('500', "Error occured during processing.");
					$str = $this->xmlDoc->saveXML();
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 12 jul 2019 - Return flag[CEN-1165]
					{
						return $str;
					}
					echo $str; //Sanjay Waman - 27 Oct 2018 - Get msg in varialble for bookingauditlogs
					//return;	
				}
            }
			//Sanjay Waman - 27 Oct 2018 - PMS Booking logs - Notification regarding changes - Start
			$thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");
			$result = $this->getsyspmsdetail($thirdparty_pms);
			if(isset($result['pmsname']) && isset($result['description']) && (trim($result['description']) == "ezeexmlformat" || trim($result['description']) == "ezeejsonforamtV2") )
			{
				foreach($bookingIDs as $bookingid)
				{
					$this->log->logIt(" >> Commo PMS found << ");
					$logBookingArr=array();
					$logBookingArr[$this->hotelcode]["notification_request"]=(String)$pmsrequest;
					$logBookingArr[$this->hotelcode]["BookingID"]=(String)$bookingid;
					$logBookingArr[$this->hotelcode]["notification_flag"]=1;
					$logBookingArr[$this->hotelcode]["notification_res"]=(String)$str;
					$bookingauditlogs = new pmsbookingauditlogs();
					$bookingauditlogs->module = "pms_integration";
					$bookingauditlogs->BookingNotificationlog($logBookingArr,1);
					unset($bookingauditlogs);
				}
			}
			return;
			//Sanjay Waman - End
			
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - updateBookingStatus - " . $e);
            $this->handleException($e);
        }
    }

	//Manali - 1.0.31.36 - 08 Feb 2013 - START
	//Purpose : Enhancement - Cancel Booking Notification From PMS
	 private function isCancellationRequestAllowed(){
	  try {
	  		$flag=false;
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "isTransferAllowed");
			if($this->readConfigParameter("RetrieveCancelledBookingsFromPMS")==1)
				$flag=true;
		} catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isCancellationRequestAllowed - " . $e);
            $this->handleException($e);
        }
		return $flag;
		
	 }
	
	 public function cancelBooking($bookings,$excludeBookings,$BookingsVoidCancelled) 
	 {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "cancelBooking");
			
			if($this->isCancellationRequestAllowed()){
				 $adminuserunkid = $this->getAdminUser();
				$objTranDao = new trandao();
				$bookingsCancelled = $groupbookingsCancelled = 0; //Manali - 1.0.38.43 - 01 Jan 2014, Purpose : Fixed Bug - In case of group bookings, audittrails should be entered for whole group if subreservation no is not passed, but email and sms should be sent only to group leader. 
				
				/*$this->log->logIt(print_r($bookings,TRUE));
				$this->log->logIt(print_r($excludeBookings,TRUE));
				$this->log->logIt(print_r($BookingsVoidCancelled,TRUE));*/
				
				foreach ($bookings as $booking) {
					$bookingno = $booking->BookingId.((isset($booking->SubBookingId) && $booking->SubBookingId!='')?'-'.strval($booking->SubBookingId):'');
					if(!in_array($bookingno,array_values($excludeBookings),TRUE) && !in_array($bookingno,array_values($BookingsVoidCancelled),TRUE))
					{	
						$tranunkid = $this->getTranunkid($booking->BookingId,isset($booking->SubBookingId)?strval($booking->SubBookingId):'');
						
						//Manali - 1.0.38.43 - 01 Jan 2014 - START
						//Purpose : Fixed Bug - In case of group bookings, audittrails should be entered for whole group if subreservation no is not passed. 
						$this->log->logIt($tranunkid);
						$tranid_list = explode("|",$tranunkid);			
						foreach($tranid_list as $tranidlist)
						{
							list($tranid,$isgroupowner,$groupunkid) = explode("-",$tranidlist);
							$count = intval($this->cancelReservation($tranid,$adminuserunkid,strval($booking->BookingId),isset($booking->SubBookingId)?strval($booking->SubBookingId):'',isset($booking->CancelReason)?strval($booking->CancelReason):'')); 
							$this->log->logIt('Bookings Cancelled : ' . $count."|".$isgroupowner."|".$groupunkid);
							if ($count > 0) 
							{
								$bookingsCancelled+=1; 		
								$groupbookingsCancelled +=$count;	
								
								audittrail::add($tranid,audittrail::CancelPMSReservation,array('pmsbookingid' => $booking->PMS_BookingId)); 
								$objTranDao->sendConfiguredEmails(parametername::EmailForCancelReservation,$tranid);														
								smsqueue::add($tranid,smsqueue::SmsOnCancelReservationConfirmation,"en"); 
							}
						}
						//Manali - 1.0.38.43 - 01 Jan 2014 - END
					}
				}
	
				if ($bookingsCancelled == count($bookings)) 
				{
					$success = $this->xmlDoc->createElement("Success");
					$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
					$succ_msg->appendChild($this->xmlDoc->createTextNode(strval($groupbookingsCancelled) . ' booking(s) cancelled'));
					$success->appendChild($succ_msg);
					$this->xmlRoot->appendChild($success);
					$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
					$str = $this->xmlDoc->saveXML();
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 12 jul 2019 - Return flag[CEN-1165]
					{
						return $str;
					}
					echo $str;
					return;
				} 
				else if($bookingsCancelled < count($bookings))
				{
					$success = $this->xmlDoc->createElement("Success");
					$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
					
					$succ_msg->appendChild($this->xmlDoc->createTextNode(strval($groupbookingsCancelled) . ' booking(s) updated'));
					$success->appendChild($succ_msg);
					$this->xmlRoot->appendChild($success);
								
					$error_msg='';
					if(count($excludeBookings)>0)
						$error_msg = 'Bookings '.implode(",",$excludeBookings).' not exists. So not updated.';
					
					if(count($BookingsVoidCancelled)>0)
					{
						$error_msg = ($error_msg!='')?($error_msg.'Bookings '.implode(",",$BookingsVoidCancelled).' already void or cancelled on web.') : ('Bookings '.implode(",",$BookingsVoidCancelled).' already void or cancelled on web.');
					}
					
					$this->generateGeneralErrorMsg('501',$error_msg);
					$str = $this->xmlDoc->saveXML();
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 12 jul 2019 - Return flag[CEN-1165]
					{
						return $str;
					}
					echo $str;
					return;
				}
				else {
					$this->generateGeneralErrorMsg('500', "Error occured during processing.");
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 12 jul 2019 - Return flag[CEN-1165]
					{
						return $this->xmlDoc->saveXML();
					}
					echo $this->xmlDoc->saveXML();
					return;
				}
			}
			else{
				$this->generateGeneralErrorMsg('304', "Fetching of cancellation booking request is not allowed. Please check the settings on reservation engine.");
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 12 jul 2019 - Return flag[CEN-1165]
				{
					return $this->xmlDoc->saveXML();
				}
                echo $this->xmlDoc->saveXML();
                return;
			}           
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - cancelBooking - " . $e);
            $this->handleException($e);
        }
    }
	
	//Rahul - start 19 sep 2016
	//Purpose - to get roomrateid to update stopsell & minnight for innsoft pms(pmsxchange)
	public function getroomrateid($roomtypeunkid,$ratetypeunkid,$hotelcode)
	{
		try
		{
			$this->log->logIt(get_class($this)."-"."getRoomRateId - ".$roomtypeunkid."|".$ratetypeunkid."|".$hotelcode);	
			
			$dao = new dao();
			$strSql = "SELECT roomrateunkid FROM cfroomrate_setting WHERE ratetypeunkid=:ratetypeunkid AND roomtypeunkid=:roomtypeunkid AND hotel_code=:hotel_code";			
			$dao->initCommand($strSql);			
			$dao->addParameter(":roomtypeunkid",$roomtypeunkid);	
			$dao->addParameter(":ratetypeunkid",$ratetypeunkid);	
			$dao->addParameter(":hotel_code", $hotelcode);	
			
			//$this->log->logIt($strSql);	
			$result->resultValue['record'] = $dao->executeRow();
			$row = (array)$result->resultValue['record'];
			//$this->log->logIt("roomrateunkid in ".$row['roomrateunkid']);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			return $row['roomrateunkid'];
		}
		catch(Exception $e)
		{
			$this->log->logIt(get_class($this)."-"."getRoomRateId"."-".$e);	
			return "-1";
		}
	}
	//Rahul - end 19 sep 2016
	
	public function cancelReservation($tranunkid,$adminuserunkid,$resno,$subres_no='',$cancelreason='')
	{
		 try {	
		 	$_SESSION['prefix']='SaaS_'.$this->hotelcode;
			$_SESSION[$_SESSION['prefix']]['hotel_code']=$this->hotelcode;
			$_SESSION[$_SESSION['prefix']]['loginuserunkid']=$adminuserunkid;
			$_SESSION[$_SESSION['prefix']]['gfmt_DateTimeFormat']="Y-m-d H:i:s";
			$_SESSION[$_SESSION['prefix']]['gfmt_DateFormat']="Y-m-d";
			$_SESSION[$_SESSION['prefix']]['language']=util::getLanguage('USR');
			
			$hoteldao = new hotelinfodao();
			$result = $hoteldao->getRecord();
			$_SESSION[$_SESSION['prefix']]['account']=$result->resultValue['record']['accountfor']; #Manali - 1.0.39.44 - 30 Jan 2014, Purpose : Added this session as it is required in void_cancel_noshow_reservation method of trandao
			
		 	$objMasterDao = new masterdao();
			$objTranDao = new trandao();
		 	$result=$objMasterDao->getFDRentalStatusID('CANCEL');
			$row=(array)$result->resultValue['record'];
			$objTranDao->statusunkid=$row['statusunkid'];			
			$objTranDao->tranunkid=$tranunkid;		
			$objTranDao->reservationno=$resno;
			$objTranDao->userunkid=$_SESSION[$_SESSION['prefix']]['loginuserunkid'];
			$objTranDao->is_void_cancelled_noshow_unconfirmed=1;
			$objTranDao->reasondatetime=util::getLocalDateTime();		
			$objTranDao->reasonunkid=NULL;
			$objTranDao->canceluserunkid=$_SESSION[$_SESSION['prefix']]['loginuserunkid'];
			$objTranDao->canceldatetime=util::getLocalDateTime();	
			
			$objTranDao->cancellationno=$objMasterDao->getAutoManualNo(parametername::CRNumberType,parametername::CRNumberPrefix,parametername::CRNumberNext);
			
			$result = $objTranDao->void_cancel_noshow_reservation(0,'CANCEL',$cancelreason);
		 	
			if($result->resultCode==resultConstant::Success)
				return 1;
			else
				return 0;
			/*$dao=new dao();	
			
			$statusunkid=$this->getFDRentalStatusID('CANCEL');
						
			$strSql = " INSERT INTO fdreason(fdreasonunkid, tranunkid,cfreasonunkid, userunkid,reasondatetime,hotel_code,trantype,reasoncategory,reason) ";			
			$strSql .= " VALUES (:fdreasonunkid, :tranunkid, :cfreasonunkid, :userunkid, :reasondatetime, :hotel_code, :trantype,:reasoncategory,:reason) ";
			$dao->initCommand($strSql);
			$dao->addParameter(":fdreasonunkid","fun_getnextid(fdreason,'".$this->hotelcode."')");
			$dao->addParameter(":tranunkid",$tranunkid);			
			$dao->addParameter(":cfreasonunkid",NULL);				
			$dao->addParameter(":userunkid",$adminuserunkid);
			$dao->addParameter(":reasondatetime",util::getLocalDateTime());
			$dao->addParameter(":trantype",$trantype);
			$dao->addParameter(":reasoncategory",'CANRESERV');
			$dao->addParameter(":reason",$cancelreason!=''?$cancelreason:NULL);
			$result = $dao->executeQuery();
			
			$strSql = " UPDATE fdtraninfo AS FDTI, fdrentalinfo AS FDRI"; 
			$strSql .= " SET FDRI.is_void_cancelled_noshow_unconfirmed=:is_void_cancelled_noshow_unconfirmed,FDTI.statusunkid=:statusunkid,FDRI.statusunkid=:statusunkid";
			$strSql .= ", FDTI.cancellationno=:cancellationno,FDTI.canceluserunkid=:canceluserunkid,FDTI.canceldatetime=:canceldatetime";
			$strSql .= " WHERE FDTI.tranunkid=:tranunkid AND FDTI.hotel_code=:hotel_code AND FDTI.tranunkid = FDRI.tranunkid AND !FIND_IN_SET(FDRI.statusunkid,'8,12') AND FDRI.is_void_cancelled_noshow_unconfirmed = 0"; 
			
			$dao->initCommand($strSql);	
					
			$dao->addParameter(":is_void_cancelled_noshow_unconfirmed",1);				
			$dao->addParameter(":statusunkid",$statusunkid);						
			$dao->addParameter(":tranunkid",$tranunkid);
			$dao->addParameter(":hotel_code",$this->hotelcode);
			$dao->addParameter(":cancellationno",$this->cancellationno);
			$dao->addParameter(":canceluserunkid",$this->canceluserunkid);
			$dao->addParameter(":canceldatetime",$this->canceldatetime);
			$dao->executeNonQuery();*/
			
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - cancelReservation - " . $e);
            $this->handleException($e);
        }
        return $result;
	}
	//Manali - 1.0.31.36 - 08 Feb 2013 - END

    private function addToAuditTrail($tranunkid, $resno, $adminuserunkid, $pmsbookingid,$subresno='',$manualflag=0) //Sanjay Waman - 23 Oct 2018 - Set message - manual posted for auditlog ($manualflag=0)
	{
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "addToAuditTrail");
			//Sanjay Waman - 23 Oct 2018 - Set message - manual posted for auditlog - Start
			if($manualflag==1)
			{
				$operation = "Booking Manually Updated by Channel Manager ";
				//Sanjay Waman - 22 Nov 2018 - Set Description at PMS level - Start
				if(isset($this->pmsDescription) && trim($this->pmsDescription) != '')
				{
					$description = (String)$this->pmsDescription;
                    $description .= "<br>PMS Booking Id : " . $resno; //Add Dharti 2018-11-26
					$description .= "<br>Reservation No : " . $resno; //Add Dharti 2018-11-26
				}
				else
				{
					$description = "Transferred Booking Multiple Times to PMS and after Booking Manually Updated by CM";
					$description .= "<br>PMS Booking Id : " . $pmsbookingid;
					$description .= "<br>Reservation No : " . $resno;
					
					if($subresno!='')
						$description .= "-".$subresno;
				}
				//Sanjay Waman - End
			}
			else
			{
				$operation = "Booking Transferred To PMS"; //Dharti Savaliya 2020-08-10 Purpose : correct spelling CEN-1599
				$description = "Booking transferred to PMS"; //Dharti Savaliya 2020-08-10 Purpose : correct spelling CEN-1599
				$description .= "<br>PMS Booking Id : " . $pmsbookingid;
				$description .= "<br>Reservation No : " . $resno;
				
				if($subresno!='')
					$description .= "-".$subresno;
			}
			//Sanjay Waman - End
			
			
            $this->insertIntoAuditTrail($tranunkid, $operation, $description, '', $adminuserunkid);
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - addToAuditTrail - " . $e);
            $this->handleException($e);
        }
    }
    
    private function prepareResponseQueue()
	{
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "prepareResponse");
			$transferred = $transferred_cancel = $updatequeue = $updatequeuecancel = $reservation_no = $cancelreservation_no = $mismatchqueue = $mismatchqueuecancel =  array();
			$groupresultqueue = $cancelgroupresultqueue = $woqueuereservation_no = $woqueuereservation_nocancel = array();
			$bookingqueue = $bookingqueuecancel = array();
			$activebookingcount = $cancelbookingcount = '';// Sushma Rana - changes for booking count mail - 14th feb 2019	
			
			$objpmsbookingqueue = new pmsbookingqueue();	
			$objpmsbookingqueue->hotelcode = $this->hotelcode;
			
			$bookingqueue= $objpmsbookingqueue->getBookingQueue();			
			//$this->log->logIt("Group booking queue detail >>".print_r($bookingqueue,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
			$groupresultqueue = $this->getGroupBookings();
			//$this->log->logIt("Group booking normal detail >>".print_r($groupresultqueue,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
			$logtemp = new pmslogger("pmsqueuemoniter");	
			if(count($bookingqueue)>0)
			{
				foreach ($bookingqueue as $bookingqueueres)
				{
					//Sanjay - 12 Mar 2021 - issue with manually post and unposted [CEN-1916] - Start
					if(strpos("tmp".trim($bookingqueueres['bookingid']), '-') > 2){
						$bookingqueueres['bookingid'] = explode("-",trim($bookingqueueres['bookingid']))[0];
					}
					//Sanjay - 12 Mar 2021 - End
					$reservation_no[] = $bookingqueueres['bookingid'];
				}
				//Sanjay - 12 Mar 2021 - issue with manually post and unposted [CEN-1916] - Start
				if(count($reservation_no)>0)
					$reservation_no = array_unique($reservation_no);
				//Sanjay - 12 Mar 2021 - End
				// Logic to check witout queue array
				foreach ($groupresultqueue as $groupresultqueueres)
				{
					$woqueuereservation_no[] = $groupresultqueueres['reservationno'];
					
				}				
				
				$thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");		
				$logtemp->logIt("Queue - Hotel Code  >>".$this->hotelcode);
				$logtemp->logIt("PMS Name >>".$thirdparty_pms);
				$logtemp->logIt("Without queue res number >>".json_encode($woqueuereservation_no,true));
				$logtemp->logIt("With queue Res number >>".json_encode($reservation_no,true));				
				$mismatchqueue = array_merge(array_diff($woqueuereservation_no,$reservation_no),array_diff($reservation_no,$woqueuereservation_no));				
				$logtemp->logIt("Mismatch queue >>".json_encode($mismatchqueue,true));
				
				
				
				$result = $this->getGroupBookingsqueue($reservation_no);				
				//$this->log->logIt("Group booking array >>".json_encode($result,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				$activebookingcount = count($result);// Sushma Rana - changes for booking count mail - 14th feb 2019
				//$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "Total Bookings: " . count($result));   //Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				 
				if($result != NULL)
				{
					foreach ($result as $res)
					{
						$bookings = $this->getBookings($res['reservationno']);
						//$this->log->logIt(json_encode($bookings,true));
						$bookedbyInfo = $this->getContactInfo($res['tranunkid'], "BookedBy");
						//$this->log->logIt("bookedbyInfo".print_r($bookedbyInfo,true));						
						//Added Masterguest information to the booking to show the master guest for whole booking
						$masterGuestInfo =  $this->getContactInfo($res['tranunkid'], "MasterGuest");
						//$this->log->logIt("masterGuestInfo".print_r($masterGuestInfo,true));														
						$flag = $this->generateReservationXML($res, $bookings, $bookedbyInfo,$masterGuestInfo);						
						if ($flag == true)
						{
							$transferred[] = $res['reservationno'];
						}
					}
					// OLD - Shifted ot out side
					//$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
				}
			}			
			// HRK - 1.0.29.34 - 1 Nov 2012 - START
			// Purpose : When no booking found then also we need error 0 tag
			$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
			// HRK - 1.0.29.34 - 1 Nov 2012 - END
			//$this->log->logIt("Array for booking succesfully transferred >>".json_encode($transferred,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			foreach($transferred as $resno)
			{
				$this->log->logIt("issend queue res no >>".$resno);
				//$objpmsbookingqueue = new pmsbookingqueue();	
				//$objpmsbookingqueue->hotelcode = $this->hotelcode;
				//$Bookingqueueprocess= $objpmsbookingqueue->sendbookingqueueflag($resno);
				//$this->log->logIt('Bookings issend flag: ' . $Bookingqueueprocess);
			}
			
			// Queue for cancel booking
			$resultcancel = array();
			$bookingqueuecancel= $objpmsbookingqueue->getBookingQueue('c');
			//$this->log->logIt("With queue group booking for cancel >>".json_encode($bookingqueuecancel,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			if(count($bookingqueuecancel)>0)
			{
				$cancelgroupresultqueue = $this->getCancelledBooking();
				//$this->log->logIt("Without queue group booking fro cancel >>".json_encode($cancelgroupresultqueue,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				
				foreach ($bookingqueuecancel as $bookingqueuerescancel)
				{
					//Sanjay - 12 Mar 2021 - issue with manually post and unposted [CEN-1916] - Start
					if(strpos("tmp".trim($bookingqueuerescancel['bookingid']), '-') > 2){
						$bookingqueuerescancel['bookingid'] = explode("-",trim($bookingqueuerescancel['bookingid']))[0];
					}
					//Sanjay - 12 Mar 2021 - End
					//$this->log->logIt("Queue base booking detail >>".print_r($bookingqueuerescancel,true));
					$cancelreservation_no[] = $bookingqueuerescancel['bookingid'];
				}
				//Sanjay - 16 Mar 2021 - issue with manually post and unposted [CEN-1916] - Start
				if(count($cancelreservation_no)>0)
					$cancelreservation_no = array_unique($cancelreservation_no);
				//Sanjay - 16 Mar 2021 - End
				// Logic to check witout queue array
				foreach ($cancelgroupresultqueue as $res)
				{
					$res_no = $res['reservationno'];
					if(isset($res['reservationno']) && strpos($res['reservationno'], '-') != false)
					{
						list($res_no, $subres_id) = explode('-', $res['reservationno']);
					}
					$woqueuereservation_nocancel[] = $res_no;
				}
				
				$logtemp->logIt("Without queue res number cancel >>".json_encode($woqueuereservation_nocancel,true));
				$logtemp->logIt("With queue Res number cancel >>".json_encode($cancelreservation_no,true));				
				$mismatchqueuecancel = array_merge(array_diff($woqueuereservation_nocancel,$cancelreservation_no),array_diff($cancelreservation_no,$woqueuereservation_nocancel));			
				$logtemp->logIt("Mismatch queue for cancel >>".json_encode($mismatchqueuecancel,true));
			    // Logic to check witout queue array
				
				$result = $this->getCancelledBookingqueue($cancelreservation_no);
				$cancelbookingcount = count($cancelgroupresultqueue); // Sushma Rana - changes for booking count mail - 14th feb 2019
				//$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "Total Cancelled Bookings : " . $cancelbookingcount);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				$cancelledBookings = $this->generateCancelledBookingsXML($result);
				//$this->log->logIt("cancelledBookings >>".print_r($cancelledBookings,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$transferred = array_merge($transferred, $cancelledBookings);
				if (count($cancelledBookings) > 0)
				{
					foreach($cancelledBookings as $resno)
					{
						if(isset($resno) && strpos($resno, '-') != false)
						{
							list($res_no, $subres_id) = explode('-', $resno);
						}
						$this->log->logIt("issend queue cancel res no >>".$res_no);	
						//$objpmsbookingqueue = new pmsbookingqueue();	
						//$objpmsbookingqueue->hotelcode = $this->hotelcode;
						//$Bookingqueueprocess= $objpmsbookingqueue->sendbookingqueueflag($res_no,"c");
						//$this->log->logIt('Bookings issend flag: ' . $Bookingqueueprocess);
					}
				}
			}
			
			// CEN- 1151, Sushma - Past date booking flag change under queue - start			
			$pastbooking = $pastbookingcheck = array();
			$pastbookingcheck = array_merge($bookingqueue,$bookingqueuecancel); 
			if(!empty($pastbookingcheck))
			{
				foreach ($pastbookingcheck as $bookingqueueres)
				{
					//Sanjay - 16 Mar 2021 - issue with manually post and unposted [CEN-1916] - Start
					$res_no = $bookingqueueres['bookingid'];
					if(strpos("tmp".trim($res_no), '-') > 2){
						$res_no = explode("-",trim($res_no))[0];
					}
					//Sanjay - 16 Mar 2021 - End
					$reservation_no[] = $res_no;
				}
			}
			$pastbooking = $this->getpastdatequeue($reservation_no);
			//$this->log->logIt("Past booking detail >>".print_r($pastbooking,true),"encrypted");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			if($this->hotelcode == 13563)
			{			
				if(!empty($pastbooking))
				{
					$objpmsbookingqueue = new pmsbookingqueue();	
					$objpmsbookingqueue->hotelcode = $this->hotelcode;
					foreach($pastbooking as $pastbookingid)
					{
						
						$Bookingqueueprocess= $objpmsbookingqueue->updatebookingqueue($pastbookingid);
						//$this->log->logIt('Bookings Updated Queue: ' .$pastbookingid."-".$Bookingqueueprocess);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					}
					
				}
			}
			//CEN- 1151, Sushma - Past date booking flag change under queue - end
			
			
			// Sushma Rana - changes for booking count mail - Start - 14th feb 2019
			//$this->log->logIt("activebookingcount >>".$activebookingcount."-"."cancelbookingcount >>".$cancelbookingcount);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			$thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");
			
			if($activebookingcount >= 10)
			{
				$this->log->logIt("Common File XML- Penddingbooingcountmail called");
				$this->Penddingbooingcountmail($thirdparty_pms,$activebookingcount,$this->hotelcode,"n");
			}			
			// Sushma Rana - changes for booking count mail - Start - 14th feb 2019
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - prepareResponse - " . $e);
            $this->handleException($e);
        }
    }


    private function prepareResponse()
	{
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "prepareResponse");
			$transferred = array();
			
			$activebookingcount = $cancelbookingcount = '';// Sushma Rana - changes for booking count mail - 14th feb 2019			
			$thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");
			$result = $this->getGroupBookings();
			//$this->log->logIt("Group booking array >>".print_r($result,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			$activebookingcount = count($result);// Sushma Rana - changes for booking count mail - 14th feb 2019
            //$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "Total Bookings: " . count($result));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			if($result != NULL)
			{
                $bookfunObj = new pmsbookingfunctons();
				$bookfunObj->hotelcode = $this->hotelcode;
				foreach ($result as $res)
                {
					$bookings = $this->getBookings($res['reservationno']);
						//$this->log->logIt(json_encode($bookings,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					//Sanjay Waman - 28 Jun 2019 - Changes for Family Hotel PMS [CEN-1470] - Start
					if(isset($res['groupunkid']) && trim($res['groupunkid'])!='')
					{
						try
                        {
							if(isset($bookings[0]) && (((isset($bookings[0]['channelhotelid']) && trim($bookings[0]['channelhotelid'])!="") && (isset($bookings[0]['channelid']) && trim($bookings[0]['channelid'])!='')) || (isset($bookings[0]['bookingtype']) && trim($bookings[0]['bookingtype'])== "WEB" )))
							{
								$this->log->logIt("*****GroupVerifyChecked*****");
								$groupverifyflag = $bookfunObj->isgroupbookingprocessed(trim($res['groupunkid']));
								
								$this->log->logIt("GroupVerifyflag>>".$groupverifyflag);
								if($groupverifyflag==0) 
								{
									$this->log->logIt("BookingSkip - Booking is InProcessOrFail(****".trim($bookings[0]['channelbookingno'])."***)");
									mail("sanjay.waman@ezeetechnosys.com", "GroupBookingSkipCheck", "Booking Skip (".trim($thirdparty_pms)." PMS - ".trim($this->hotelcode).") - Booking ID -".trim($bookings[0]['channelbookingno'])."", "From: saas <noreply@ezeetechnosys.com>\r\n");
									continue;
								}
							}
							else
							{
								$this->log->logIt("*****WithoutGroupVerifyChecked*****");
							}
						}
						catch (Exception $e)
						{
							$this->log->logIt("*****ExceptionGroupVerifyChecked*****");
							$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception " . $this->module . " - ExceptionGroupVerifyChecked - " . $e);
							mail("sanjay.waman@ezeetechnosys.com", "ExceptionGroupVerifyChecked", trim("Hotel_" . $this->hotelcode . " - Exception " . $this->module . " - GroupVerifyChecked - " . $e), "From: saas <noreply@ezeetechnosys.com>\r\n");
						}
					}
					//Sanjay Waman - End
					
					$bookedbyInfo = $this->getContactInfo($res['tranunkid'], "BookedBy");
					//$this->log->logIt("bookedbyInfo".print_r($bookedbyInfo,true));
					/* Satish - 11 Sep 2012 - Start*/
					//Added Masterguest information to the booking to show the master guest for whole booking
					$masterGuestInfo =  $this->getContactInfo($res['tranunkid'], "MasterGuest");
					//$this->log->logIt("masterGuestInfo".print_r($masterGuestInfo,true));
					/* Satish - 11 Sep 2012 - End*/		
					
					$flag = $this->generateReservationXML($res, $bookings, $bookedbyInfo,$masterGuestInfo);
					
					if ($flag == true)
					{
						$transferred[] = $res['tranunkid'];
					}
				}
                unset($bookfunObj);
				// OLD - Shifted ot out side
				//$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
            }
			
			// HRK - 1.0.29.34 - 1 Nov 2012 - START
			// Purpose : When no booking found then also we need error 0 tag
			$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
			// HRK - 1.0.29.34 - 1 Nov 2012 - END
			
			$result = $this->getCancelledBooking();
			$cancelbookingcount = count($result); // Sushma Rana - changes for booking count mail - 14th feb 2019
			//$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "Total Cancelled Bookings : " . count($result));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $cancelledBookings = $this->generateCancelledBookingsXML($result);
			$transferred = array_merge($transferred, $cancelledBookings);
			
			// Sushma Rana - changes for booking count mail - Start - 14th feb 2019
			//$this->log->logIt("activebookingcount >>".$activebookingcount."-"."cancelbookingcount >>".$cancelbookingcount);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			$thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");
			
			if($activebookingcount >= 10)
			{
				$this->log->logIt("Common File XML- Penddingbooingcountmail called");
				$this->Penddingbooingcountmail($thirdparty_pms,$activebookingcount,$this->hotelcode,"n");
			}
			/*
			if($cancelbookingcount == 25)
			{
				$this->log->logIt("Common File XML-Penddingbooingcountmail called");
				$this->Penddingbooingcountmail($thirdparty_pms,$cancelbookingcount,$this->hotelcode,"c");
			}
			*/
			// Sushma Rana - changes for booking count mail - Start - 14th feb 2019
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - prepareResponse - " . $e);
            $this->handleException($e);
        }
    }

    private function getBookings($resno)
	{
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getBookings - ".$resno);
            $result = NULL;
            $sqlStr = " SELECT FDTI.tranunkid,FDTI.trandate,FDTI.reservationno,FDTI.subreservationno,date(FDTI.arrivaldatetime) as arrivaldate,date(FDTI.departuredatetime) as departuredate, FDTI.commissionplanunkid as commisionid, FDR.reasoncategory ,CF.reason ,FDR.cfreasonunkid, CF.reasonunkid, "; //Add reasoncategory,reason ,cfreasonunkid field - 2019-06-06   // Added commision fileld  -sushma
			$sqlStr.="  IFNULL(FDCBI.channelhotelunkid,'') As channelhotelid,IFNULL(FDCBI.channelunkid,'') AS channelid, 
			IFNULL(FDCBI.channelbookingid,FDTI.reservationno) As channelbookingno, "; //Manali - 1.0.48.53 - 08th March 2016, Purpose : Enhancement - Placed CCInfo link in base64 encoded format
			$sqlStr.="FDCBI.channelbookingunkid as channelbookingunkid,FDCBI.ruid as ruid,FDCBI.ruid_status as ruid_status, ";//Manali - 20th Mar 2017,Purpose : Enhancement - Separate CC Servers Functionality - Change logs
			$sqlStr.=" IFNULL((SELECT business_name FROM trcontact WHERE contactunkid=FDTI.companyunkid),";
            $sqlStr.=" IFNULL((SELECT `business_name` FROM trcontact WHERE contactunkid=FDTI.travelagentunkid),";
            $sqlStr.=" IFNULL((SELECT `name` FROM trcontact WHERE contactunkid=FDTI.roomownerunkid),";
            $sqlStr.=" IFNULL((SELECT `businesssourcename` FROM cfbusinesssource WHERE businesssourceunkid=FDTI.businesssourceunkid),";
            $sqlStr.=" 'WEB')))) AS source,";
			//Mayur Kannaujia - 1.0.53.61 - 09-03-2018 - Purpose: If Booking comes from MMT then source/CityLedger should be MakemytripXMl not goibibo - START
			$sqlStr.=" IFNULL((SELECT `businesssourcename` FROM cfbusinesssource WHERE businesssourceunkid=FDTI.businesssourceunkid),'') as channel_source,";
			//Mayur Kannaujia - 1.0.53.61 - 09-03-2018 - Purpose: If Booking comes from MMT then source/CityLedger should be MakemytripXMl not goibibo - END
            $sqlStr.=" CFRT.roomtype,CFRT.roomtypeunkid,CFRT.shortcode as roomcode,FDTI.masterfoliounkid,FDTI.travelagentvoucherno,";
			
			//Manali - 1.0.36.41 - 30 Aug 2013 - START
			//Purpose : Customization : Send package,credit card info in bookings for PMS-Web Interface			
			// Add Dharti Savaliya 2019-05-31 Purpose:-  When we add remark manually at that time in booking xml remark not added at pms side(CEN-1083)	
			$sqlStr.="REPLACE(TRIM(BOTH ',' FROM GROUP_CONCAT(CASE WHEN FDR.reasoncategory='PACKAGE' THEN CONCAT('Package : ',REPLACE(REPLACE(FDR.reason,SUBSTR(FDR.reason,INSTR(FDR.reason,'</b>')),''),'<b>','')) WHEN  FDR.reasoncategory='RESERV' THEN REPLACE(CONCAT('Reservation : ',FDR.reason),'<br>','') WHEN FIND_IN_SET(FDR.reasoncategory,'HOTELIERREMARK') THEN CONCAT(IF(FDR.reasoncategory = 'HOTELIERREMARK', 'Internal Note : ', 'Important Info : ' AND (FDR.reason != '' || CF.reason != '')), IF(FDR.reason != '',REPLACE(REPLACE(FDR.reason,SUBSTR(FDR.reason,INSTR(FDR.reason, '</b>')),''),'<b>',''),REPLACE(REPLACE(CF.reason,SUBSTR(CF.reason,INSTR(CF.reason, '</b>')),''),'<b>','')))  END)),',,',',') AS specreq, ";
			//Dharti Savaliya -END - 2019-06-06
			$sqlStr.="IFNULL(FDCBI.pin,'') AS cc_info1,IFNULL(FDTI.ccinfopin,'') AS cc_info2, ";
			//Manali - 1.0.36.41 - 30 Aug 2013 - END
	    
			//Charul - 16 July 2014 - START
			//Purpose : Enhancement - To Get Affilate Information
			$sqlStr.=" IFNULL(FDTI.affiliateunkid,'') as affiliateunkid,";
			$sqlStr.=" CASE WHEN (FDTI.webrateunkid IS NOT NULL AND FDTI.webinventoryunkid IS NOT NULL) THEN 'WEB' ELSE 'CHANNEL' END as bookingtype,";
			//Charul - 16 July 2014 - END
	    
			//Manali - 1.0.49.54 - 26 May 2016 - START
			//Purpose : Customization : Send guarantee - is reservation confirmed or not confirmed
			$sqlStr.=' CFR.confirmed AS isconfirmed, ';
			//Manali - 1.0.49.54 - 26 May 2016 - END
			
			#Chandrakant - 1.0.37.42 - 13 Nov 2013 - START
			#Purpose : Enhancement : add status from fdchannelbookinginfo
			$sqlStr.=' IF((FDCBI.status IS NOT NULL && FDCBI.status="M"),"Modify","New") AS status ';
			#Chandrakant - 1.0.37.42 - 13 Nov 2013 - END
			
            $sqlStr.=" FROM fdtraninfo AS FDTI";
			
			//Manali - 1.0.49.54 - 26 May 2016 - START
			//Purpose : Customization : Send guarantee - is reservation confirmed or not confirmed
			$sqlStr.=" INNER JOIN cfreservationgaurantee AS CFR ON CFR.gauranteeunkid = FDTI.reservationgauranteeunkid ";
			//Manali - 1.0.49.54 - 26 May 2016 - END
			
            #$sqlStr.=" INNER JOIN fdrentalstatus AS FDRS ON FDRS.statusunkid = FDTI.statusunkid";
            $sqlStr.=" INNER JOIN cfroomtype AS CFRT ON CFRT.roomtypeunkid = FDTI.roomtypeunkid";
            $sqlStr.=" LEFT JOIN fdreason AS FDR ON FDR.tranunkid = FDTI.tranunkid AND FDR.hotel_code=:hotel_code ";
			$sqlStr.=" LEFT JOIN cfreason AS CF ON CF.reasonunkid = FDR.cfreasonunkid " ; // Dharti Savaliya 2019-05-31 Purpose:-When we add remark manually at that time in booking xml remark not added at pms side(CEN-1083)
			
			//Manali - 1.0.36.41 - 30 Aug 2013 - START
			//Purpose : Customization : Send package,credit card info in bookings for PMS-Web Interface			
			//$sqlStr.=" FIND_IN_SET(FDR.reasoncategory,'RESERV,PACKAGE') ";
			$sqlStr.=" LEFT JOIN fdchannelbookinginfo AS FDCBI ON FDCBI.tranunkid = FDTI.tranunkid AND FDCBI.hotel_code=:hotel_code";
			//Manali - 1.0.36.41 - 30 Aug 2013 - END
			
            $sqlStr.=" WHERE FDTI.hotel_code =:hotel_code ";
            			
			//Manali - 1.0.49.54 - 28 May 2016 - START
			//Purpose : Transfer unconfirm bookings to PMS based on settings
			if($this->readConfigParameter('PMSTransferUnConfirmBookings')=="0")
				 $sqlStr.=" AND FIND_IN_SET(FDTI.statusunkid,'4,13')";
			else
				 $sqlStr.=" AND FIND_IN_SET(FDTI.statusunkid,'4,10,13')";
			//Manali - 1.0.49.54 - 28 May 2016 - END
			
            $sqlStr.=" AND FDTI.reservationno=:resno AND FDTI.isposted=:isposted GROUP BY FDTI.tranunkid  order by FDTI.reservationno, FDTI.subreservationno";
            //$this->log->logIt("get bookings ".$sqlStr);
			$dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":isposted", 0);
            $dao->addParameter(":resno", $resno);

			//$this->log->logIt(get_class($this) . "-" . "getBookings - " . $sqlStr);
			//$this->log->logIt(get_class($this) . "-" . "hotel_code - " .  $this->hotelcode);
			//$this->log->logIt(get_class($this) . "-" . "isposted - " .  0); 
			//$this->log->logIt(get_class($this) . "-" . "resno - " .  $resno);
			 
            $result = $dao->executeQuery();
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getBookings - " . $e);
            $this->handleException($e);
        }
        return $result;
    }
	
	//Rahul - start 29 july 2016
	// purpose - to get reservation other details like arr , dep date , sourcename for pmsxchange
	//Harry - 1.0.49.54 - 26 Aug 2016 - Last parameter added which is used in tauras pms to get arr and dep details for cancellation
	public function getreservationdetails($resno,$hotelcode,$sub_res_id='')
	{
		try
		{
			$this->hotelcode=$hotelcode;
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getreservationdetials");
			$this->log->logIt("Hotel_harry-->".$resno."---".$hotelcode."----".$sub_res_id."<====");
	    
            $sqlStr = "SELECT * FROM fdtraninfo AS FDTI LEFT JOIN cfbusinesssource AS CFBS ON (FDTI.businesssourceunkid = CFBS.businesssourceunkid
						AND CFBS.hotel_code = :hotel_code) WHERE reservationno = :resno AND FDTI.hotel_code = :hotel_code";
			if($sub_res_id!='')
				$sqlStr.= " AND subreservationno = :sub_res_id";
			//$this->log->logIt("Getreservationdetails-->".$sqlStr);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
			$dao->addParameter(":resno", $resno);
			$dao->addParameter(":sub_res_id", $sub_res_id);	//Harry - 1.0.49.54 - 26 Aug 2016 - Last parameter added which is used in tauras pms to get arr and dep details for cancellation
            //$dao->addParameter(":isposted", 0);
            
			/*$this->log->logIt(get_class($this) . "-" . "getGroupBookings - " . $sqlStr);
            $this->log->logIt(get_class($this) . "-" . "hotel_code - " .  $this->hotelcode);
            $this->log->logIt(get_class($this) . "-" . "isposted - " .  0);*/
            
			$result = $dao->executeQuery();
			
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getreservationdetails - " . $e);
            $this->handleException($e);
        }
        return $result;
	}
	//Rahul - end 29 july 2016
	
    private function getGroupBookings()
	{
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getGroupBookings");	    
			$workingdate=date ("Y-m-d", strtotime("-1 day", strtotime($this->readConfigParameter('todayDate'))));
	        $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getGroupBookings". "working Date".$workingdate);
            $result = NULL;
            $sqlStr = " SELECT FDTI.tranunkid,FDTI.reservationno,";
			$sqlStr .= "FDTI.groupunkid,";//Sanjay - 31 Dec 2019 - Added FDTI.groupunkid [CEN-1470]
            #$sqlStr.=" ,FDTI.subreservationno,";//date(FDTI.arrivaldatetime) as arrivaldate,date(FDTI.departuredatetime) as departuredate,";
             $sqlStr.=" CASE WHEN (FDTI.webrateunkid IS NOT NULL AND FDTI.webinventoryunkid IS NOT NULL) THEN 'WEB'";
           $sqlStr.=" ELSE 'CHANNEL' END as bookingtype,";
			$sqlStr.=" IFNULL((SELECT business_name FROM trcontact WHERE contactunkid=FDTI.companyunkid),";
            $sqlStr.=" IFNULL((SELECT `business_name` FROM trcontact WHERE contactunkid=FDTI.travelagentunkid),";
            $sqlStr.=" IFNULL((SELECT `name` FROM trcontact WHERE contactunkid=FDTI.roomownerunkid),";
            $sqlStr.=" IFNULL((SELECT `businesssourcename` FROM cfbusinesssource WHERE businesssourceunkid=FDTI.businesssourceunkid),";
            $sqlStr.=" 'WEB')))) AS source";

            #$sqlStr.=" CFRT.roomtype,CFRT.roomtypeunkid,CFRT.shortcode as roomcode,FDTI.masterfoliounkid,FDR.reason as specreq";
            $sqlStr.=" FROM fdtraninfo AS FDTI";
            #$sqlStr.=" INNER JOIN fdrentalstatus AS FDRS ON FDRS.statusunkid = FDTI.statusunkid";
			//$sqlStr.=" INNER JOIN fdchannelbookinginfo as FCBI on FDTI.tranunkid=FCBI.tranunkid";
            #$sqlStr.=" INNER JOIN cfroomtype AS CFRT ON CFRT.roomtypeunkid = FDTI.roomtypeunkid";
            #$sqlStr.=" LEFT JOIN fdreason AS FDR ON FDR.tranunkid = FDTI.tranunkid AND FDR.reasoncategory='RESERV'";
            $sqlStr.=" WHERE FDTI.hotel_code =:hotel_code ";
	    //CEN-1733, Changes for gureetee detail for HOLD booking - Start
           //$hotelArray = array("1082","6300");
            if($this->readConfigParameter("ThirdPartyPMS") == 'familyhotel')
			{
				$pmsbookingfunction = new pmsbookingfunctons();
				$pmsbookingfunction->hotelcode = $this->hotelcode;
				$holdbookingid = $pmsbookingfunction->getholdgaurantee();
                $this->log->logIt("Holdbookingid  ==>>".$holdbookingid); 
				$sqlStr.=" AND ((FDTI.webrateunkid IS NOT NULL AND FDTI.webinventoryunkid IS NOT NULL AND FDTI.reservationgauranteeunkid IN('".$holdbookingid."')) OR (FDTI.webinventoryunkid IS NULL OR FDTI.webrateunkid IS NULL))";          
			}
            //CEN-1733- End
			//Manali - 1.0.49.54 - 28 May 2016 - START
			//Purpose : Transfer unconfirm bookings to PMS based on settings
			if($this->readConfigParameter('PMSTransferUnConfirmBookings')=="0")
				 $sqlStr.=" AND FIND_IN_SET(FDTI.statusunkid,'4,13')";
			else
				 $sqlStr.=" AND FIND_IN_SET(FDTI.statusunkid,'4,10,13')";
			//Manali - 1.0.49.54 - 28 May 2016 - END
			
			// HRK - 1.0.30.35 - 17 Dec 2012 - START
			// Purpose : Prevent transaction from posting whose payment is not acknowledged
			if($this->readConfigParameter('PMSTransferPaymentAcknowledgedBookingsOnly')=="1")
				$sqlStr.=" AND IFNULL(FDTI.transactionflag,0)<>0 ";
			// HRK - 1.0.30.35 - 17 Dec 2012 - END
			
            $sqlStr.=" AND FDTI.isposted=:isposted AND CAST(FDTI.arrivaldatetime As DATE)>='".$workingdate."' GROUP BY FDTI.reservationno ORDER BY FDTI.reservationno";			//Manali - 1.0.46.51 - 29th May 2015, Purpose : Applied checking for past date reservations not to send in PMS
			$sqlStr.=" LIMIT 0,25"; //Manali - 1.0.34.39 - 24 May 2013, Purpose : Fixed Bug - It was making server down when all bookings are fetched in one time, so placed limit here. 
			
			//$this->log->logIt("Group bookings".$sqlStr);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":isposted", 0);
            
			/*$this->log->logIt(get_class($this) . "-" . "getGroupBookings - " . $sqlStr);
            $this->log->logIt(get_class($this) . "-" . "hotel_code - " .  $this->hotelcode);
            $this->log->logIt(get_class($this) . "-" . "isposted - " .  0);*/
            
			$result = $dao->executeQuery();
			
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getGroupBookings - " . $e);
            $this->handleException($e);
        }
        return $result;
    }    
   
	private function getGroupBookingsqueue($reservationarray)
	{
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getGroupBookings");	    
			$workingdate=date ("Y-m-d", strtotime("-1 day", strtotime($this->readConfigParameter('todayDate'))));
	        $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getGroupBookings". "working Date".$workingdate);
	        //$this->log->logIt("Reservation array >>".print_r($reservationarray,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
	        
	        $reservationarray = '"'.implode('","',$reservationarray).'"';	        
	        //$this->log->logIt("Reservation array >>".$reservationarray);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
	        
            $result = NULL;
            $sqlStr = " SELECT FDTI.tranunkid,FDTI.reservationno,";
             $sqlStr.=" CASE WHEN (FDTI.webrateunkid IS NOT NULL AND FDTI.webinventoryunkid IS NOT NULL) THEN 'WEB'";
            //CEN-1733, Changes for gureetee detail for HOLD booking - Start
            //$hotelArray = array("4996","6300");
            if($this->readConfigParameter("ThirdPartyPMS") == 'familyhotel')
			{
				$pmsbookingfunction = new pmsbookingfunctons();
				$pmsbookingfunction->hotelcode = $this->hotelcode;
				$holdbookingid = $pmsbookingfunction->getholdgaurantee();
                $sqlStr.=" AND ((FDTI.webrateunkid IS NOT NULL AND FDTI.webinventoryunkid IS NOT NULL AND FDTI.reservationgauranteeunkid IN('".$holdbookingid."')) OR (FDTI.webinventoryunkid IS NULL OR FDTI.webrateunkid IS NULL))";         
			}
            //CEN-1733- End
           $sqlStr.=" ELSE 'CHANNEL' END as bookingtype,";
            $sqlStr.=" IFNULL((SELECT business_name FROM trcontact WHERE contactunkid=FDTI.companyunkid),";
            $sqlStr.=" IFNULL((SELECT `business_name` FROM trcontact WHERE contactunkid=FDTI.travelagentunkid),";
            $sqlStr.=" IFNULL((SELECT `name` FROM trcontact WHERE contactunkid=FDTI.roomownerunkid),";
            $sqlStr.=" IFNULL((SELECT `businesssourcename` FROM cfbusinesssource WHERE businesssourceunkid=FDTI.businesssourceunkid),";
            $sqlStr.=" 'WEB')))) AS source";
            $sqlStr.=" FROM fdtraninfo AS FDTI";
            #$sqlStr.=" INNER JOIN fdrentalstatus AS FDRS ON FDRS.statusunkid = FDTI.statusunkid";
			//$sqlStr.=" INNER JOIN fdchannelbookinginfo as FCBI on FDTI.tranunkid=FCBI.tranunkid";
            #$sqlStr.=" INNER JOIN cfroomtype AS CFRT ON CFRT.roomtypeunkid = FDTI.roomtypeunkid";
            #$sqlStr.=" LEFT JOIN fdreason AS FDR ON FDR.tranunkid = FDTI.tranunkid AND FDR.reasoncategory='RESERV'";
            $sqlStr.=" WHERE FDTI.hotel_code =:hotel_code ";
			
			//Manali - 1.0.49.54 - 28 May 2016 - START
			//Purpose : Transfer unconfirm bookings to PMS based on settings
			if($this->readConfigParameter('PMSTransferUnConfirmBookings')=="0")
				 $sqlStr.=" AND FIND_IN_SET(FDTI.statusunkid,'4,13')";
			else
				 $sqlStr.=" AND FIND_IN_SET(FDTI.statusunkid,'4,10,13')";
			//Manali - 1.0.49.54 - 28 May 2016 - END
			
			// HRK - 1.0.30.35 - 17 Dec 2012 - START
			// Purpose : Prevent transaction from posting whose payment is not acknowledged
			if($this->readConfigParameter('PMSTransferPaymentAcknowledgedBookingsOnly')=="1")
				$sqlStr.=" AND IFNULL(FDTI.transactionflag,0)<>0 ";
			// HRK - 1.0.30.35 - 17 Dec 2012 - END
			
            $sqlStr.=" AND FDTI.isposted=:isposted AND CAST(FDTI.arrivaldatetime As DATE)>='".$workingdate."' AND FDTI.reservationno IN ($reservationarray) GROUP BY FDTI.reservationno ORDER BY FDTI.reservationno";			//Manali - 1.0.46.51 - 29th May 2015, Purpose : Applied checking for past date reservations not to send in PMS
			$sqlStr.=" LIMIT 0,25"; //Manali - 1.0.34.39 - 24 May 2013, Purpose : Fixed Bug - It was making server down when all bookings are fetched in one time, so placed limit here. 
			
			//$this->log->logIt("Group bookings queue".$sqlStr);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":isposted", 0);
            
			/*$this->log->logIt(get_class($this) . "-" . "getGroupBookings - " . $sqlStr);
            $this->log->logIt(get_class($this) . "-" . "hotel_code - " .  $this->hotelcode);
            $this->log->logIt(get_class($this) . "-" . "isposted - " .  0);*/
            
			$result = $dao->executeQuery();
			
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getGroupBookings - " . $e);
            $this->handleException($e);
        }
        return $result;
    }    
    
	private function getpastdatequeue($reservationarray)
	{
        try
		{
			
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getpastdatequeue");	    
			$workingdate=date ("Y-m-d", strtotime("-2 day", strtotime($this->readConfigParameter('todayDate'))));
	        $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getGroupBookings". "working Date".$workingdate);
	       
			$reservationarray = array_unique($reservationarray);			
	        $reservationarray = '"'.implode('","',$reservationarray).'"';
			//$this->log->logIt("Reservation array >>".$reservationarray);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
			$pastbooking = array();
	        
            $result = NULL;
            $sqlStr = " SELECT FDTI.reservationno as pastbooking ";  
            $sqlStr.=" FROM fdtraninfo AS FDTI ";           
            $sqlStr.=" WHERE FDTI.hotel_code =:hotel_code ";
			$sqlStr.=" AND CAST(FDTI.arrivaldatetime As DATE) < '".$workingdate."' AND CAST(FDTI.departuredatetime As DATE) < '".$workingdate."' ";			
			$sqlStr.=" AND FDTI.reservationno IN ($reservationarray) GROUP BY FDTI.reservationno ORDER BY FDTI.reservationno ";
			
			//$this->log->logIt("PastDate Booking Queue >>".$sqlStr);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			$dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);            
			$result = $dao->executeQuery();
			
			foreach($result as $booking)
			{
				$pastbooking[] = $booking['pastbooking'];				
			}			
			//$this->log->logIt($pastbooking);
			$result = $pastbooking;
			
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getGroupBookings - " . $e);
            $this->handleException($e);
        }
        return $result;
    } 
	
    private function getCancelledBookingqueue($reservationarray) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getCancelledBooking");
	    
			$workingdate=date ("Y-m-d", strtotime("-1 day", strtotime($this->readConfigParameter('todayDate'))));
	    
			$reservationarray = '"'.implode('","',$reservationarray).'"';	        
	        //$this->log->logIt("Reservation array >>".$reservationarray);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
	        
            $result = NULL;
            $sqlStr = " SELECT FDTI.tranunkid,CASE WHEN FDTI.subreservationno IS NOT NULL AND FDTI.subreservationno!='' THEN CONCAT(FDTI.reservationno,'-',FDTI.subreservationno) ELSE FDTI.reservationno END as reservationno,FDR.reason AS fremark,CFR.reason AS cremark,IFNULL(FDTI.travelagentvoucherno,'') AS voucherno FROM fdtraninfo AS FDTI"; //Manali - 1.0.35.40 - 30 Jul 2013, Purpose : Passed subreservation no with reservation no in case of group bookings.
            #$sqlStr.=" INNER JOIN fdrentalstatus AS FDRS ON FDRS.statusunkid = FDTI.statusunkid";
            $sqlStr.=" LEFT JOIN fdreason AS FDR ON FDR.tranunkid = FDTI.tranunkid AND FDR.reasoncategory='CANRESERV' AND FDR.hotel_code=:hotel_code";
            $sqlStr.=" LEFT JOIN cfreason AS CFR ON CFR.reasonunkid=FDR.cfreasonunkid AND CFR.reasoncategory='CANRESERV' AND CFR.hotel_code=:hotel_code";
            $sqlStr.=" WHERE FDTI.hotel_code =:hotel_code ";
            $sqlStr.=" AND FDTI.statusunkid=6 AND IFNULL(FDTI.transactionflag,0)<>0 "; # AND (FIND_IN_SET(FDR.reasoncategory,'CANRESERV') OR FIND_IN_SET(CFR.reasoncategory,'CANRESERV'))";
            $sqlStr.=" AND FDTI.isposted=:isposted  AND FDTI.reservationno IN ($reservationarray) AND CAST(FDTI.arrivaldatetime As DATE)>='".$workingdate."'"; //Manali - 1.0.46.51 - 29th May 2015, Purpose : Applied checking for past date reservations not to send in PMS

			$sqlStr.=" LIMIT 0,25"; //Manali - 1.0.34.39 - 24 May 2013, Purpose : Fixed Bug - It was making server down when all bookings are fetched in one time, so placed limit here. 
			
			//$this->log->logIt("cancel query".$sqlStr);
			
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":isposted", 0);
            #$this->log->logIt(get_class($this) . "-" . "getBookings - " . $sqlStr);
            #$this->log->logIt(get_class($this) . "-" . "hotel_code - " .  $this->hotelcode);
            #$this->log->logIt(get_class($this) . "-" . "isposted - " .  0);
            $result = $dao->executeQuery();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getCancelledBooking - " . $e);
            $this->handleException($e);
        }
        return $result;
    }
   
    private function getCancelledBooking() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getCancelledBooking");
	    
			$workingdate=date ("Y-m-d", strtotime("-1 day", strtotime($this->readConfigParameter('todayDate'))));
	    
            $result = NULL;
            $sqlStr = " SELECT FDTI.tranunkid,CASE WHEN FDTI.subreservationno IS NOT NULL AND FDTI.subreservationno!='' THEN CONCAT(FDTI.reservationno,'-',FDTI.subreservationno) ELSE FDTI.reservationno END as reservationno,FDR.reason AS fremark,CFR.reason AS cremark,IFNULL(FDTI.travelagentvoucherno,'') AS voucherno FROM fdtraninfo AS FDTI"; //Manali - 1.0.35.40 - 30 Jul 2013, Purpose : Passed subreservation no with reservation no in case of group bookings.
            #$sqlStr.=" INNER JOIN fdrentalstatus AS FDRS ON FDRS.statusunkid = FDTI.statusunkid";
            $sqlStr.=" LEFT JOIN fdreason AS FDR ON FDR.tranunkid = FDTI.tranunkid AND FDR.reasoncategory='CANRESERV' AND FDR.hotel_code=:hotel_code";
            $sqlStr.=" LEFT JOIN cfreason AS CFR ON CFR.reasonunkid=FDR.cfreasonunkid AND CFR.reasoncategory='CANRESERV' AND CFR.hotel_code=:hotel_code";
            $sqlStr.=" WHERE FDTI.hotel_code =:hotel_code ";
            $sqlStr.=" AND FDTI.statusunkid=6 AND IFNULL(FDTI.transactionflag,0)<>0 "; # AND (FIND_IN_SET(FDR.reasoncategory,'CANRESERV') OR FIND_IN_SET(CFR.reasoncategory,'CANRESERV'))";
            $sqlStr.=" AND FDTI.isposted=:isposted  AND CAST(FDTI.arrivaldatetime As DATE)>='".$workingdate."'"; //Manali - 1.0.46.51 - 29th May 2015, Purpose : Applied checking for past date reservations not to send in PMS

			$sqlStr.=" LIMIT 0,25"; //Manali - 1.0.34.39 - 24 May 2013, Purpose : Fixed Bug - It was making server down when all bookings are fetched in one time, so placed limit here. 
			
			//$this->log->logIt("cancel query".$sqlStr);
			
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":isposted", 0);
            #$this->log->logIt(get_class($this) . "-" . "getBookings - " . $sqlStr);
            #$this->log->logIt(get_class($this) . "-" . "hotel_code - " .  $this->hotelcode);
            #$this->log->logIt(get_class($this) . "-" . "isposted - " .  0);
            $result = $dao->executeQuery();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getCancelledBooking - " . $e);
            $this->handleException($e);
        }
        return $result;
    }

    private function updatePostedStatus($resno,$subreservationno)   //Manali - 1.0.30.35 - 11 Jan 2013, Purpose : Passed $subreservationno to post status of only one transaction based on reservation no and subreservation no checking in case of group reservation
	{
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "updatePostedStatus");
            $bookingsUpdated = -1;
			
			/*$resno_arr = explode('-',$resno);
			
			$resno_new = '';
			$subresno = '';
			if(count($resno_arr)>2)
			{
				for($r=0;$r<(count($resno_arr)-1);$r++)
				{
					$resno_new .= $resno_arr[$r]."-";
				}
				$resno_new = rtrim($resno_new,'-');
				$subresno = $resno_arr[(count($resno_arr)-1)];
			}
			else{
				$resno_new = $resno_arr[0];
				$subresno = (count($resno_arr)==2)?($resno_arr[1]):'';
			}*/

            $strSql = " UPDATE fdtraninfo";
            $strSql .= " SET isposted=:isposted";
            $strSql .= " WHERE hotel_code =:hotel_code AND reservationno=:resno";
			
			//Manali - 1.0.30.35 - 11 Jan 2013 - START
			//Purpose : Passed $subreservationno to post status of only one transaction based on reservation no and subreservation no checking in case of group reservation
			if($subreservationno!='')
				$strSql .= " AND subreservationno=:subreservationno";
			//else if($subreservationno=='' && $subresno!='')
			//	$strSql .= " AND subreservationno=:subreservationno";
			//Manali - 1.0.30.35 - 11 Jan 2013 - END
			 	
            $dao = new dao();
            $dao->initCommand($strSql);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":isposted", 1);
			
			//if($subreservationno=='' && $subresno!='')
//				$dao->addParameter(":resno", $resno_new);
//			else
            	$dao->addParameter(":resno", $resno);
			
			//Manali - 1.0.30.35 - 11 Jan 2013 - START
			//Purpose : Passed $subreservationno to post status of only one transaction based on reservation no and subreservation no checking in case of group reservation
			if($subreservationno!='')
				 $dao->addParameter(":subreservationno", $subreservationno);
			//else if($subreservationno=='' && $subresno!='')
//				$dao->addParameter(":subreservationno", $subresno);
			//Manali - 1.0.30.35 - 11 Jan 2013 - END
			
            $bookingsUpdated = $dao->executeNonQuery();
           	 //$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "updatePostedStatus"."-".$bookingsUpdated);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			 /*
              $this->log->logIt(get_class($this) . "-" . "updatePostedStatus - " . $strSql);
              $this->log->logIt(get_class($this) . "-" . "resno");
              $this->log->logIt($resno.($subreservationno!=''?"-".$subreservationno:''));
              $this->log->logIt(get_class($this) . "-" . "hotel_code"."-".$this->hotelcode); */
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - updatePostedStatus - " . $e);
            $this->handleException($e);
        }
        return $bookingsUpdated;
    }
    //sushma - private to public to call same method in otaxmls.php file.
    public function getContactInfo($tranunkid, $contactType) {
        try {
            $result = NULL;
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getContactInfo");
			$strSql ='';
			
			//Manali - 1.0.33.38 - 10 May 2013 - START
			//Purpose : Enhancement - Passing Transport/Identity/Sharer Information,Personal Information - Gender,Nationality, Date Of Birth,Spouse Birthdate,Wedding Anniversary			
			if($contactType=="MasterGuest"){
				 $strSql = "SELECT IFNULL(TRC.salutation,'') as salutation,IFNULL(TRC.business_name,'') As business_name,TRC.name,IFNULL(TRC.address,'') As address,IFNULL(TRC.city,'') as city,IFNULL(TRC.state,'') As state,IFNULL(TRC.zipcode,'') As zipcode,IFNULL(TRC.country,'') AS country,IFNULL(TRC.phone,'') AS phone,IFNULL(TRC.mobile,'') AS mobile,IFNULL(TRC.fax,'') AS fax,IFNULL(TRC.email,'') AS email,TRC.contacttypeunkid,";	
				$strSql.="IFNULL(TRC.gender,'') AS gender,IFNULL(DATE(TRC.birthdate),'') AS birthdate,CASE WHEN (TRC.identityunkid IS NOT NULL AND TRC.identityunkid <>0) THEN cfidentitytype.identitytype ELSE '' END AS identitytype,
							IFNULL(TRC.identity_no,'') AS identityno,IFNULL(DATE(TRC.exp_date),'') AS expirydate,IFNULL(TRC.nationality,'') AS nationality,DATE(TRC.anniversary) AS anniversary,DATE(TRC.spousebirthdate) AS spousebirthdate, 
							CASE WHEN (FDGT.pickupmodeunkid IS NOT NULL AND FDGT.pickupmodeunkid <>0) THEN CFTM.transportationmode ELSE '' END AS transportationmode,
							IFNULL(FDGT.pickupvehicle,'') AS pickupvehicle,IFNULL(pickupdatetime,'') AS pickupdatetime ";	
					
           		 $strSql.=" FROM trcontact AS TRC";  
				 $strSql.=" INNER JOIN fdguesttran AS FDGT ON FDGT.guestunkid=TRC.contactunkid";
                 $strSql.=" INNER JOIN fdtraninfo AS FDTI ON FDTI.masterguesttranunkid=FDGT.guesttranunkid";    
				 $strSql.=" LEFT JOIN cfidentitytype ON cfidentitytype.identitytypeunkid = TRC.identityunkid AND cfidentitytype.hotel_code=:hotel_code"; 
				 $strSql.=" LEFT JOIN cftransportation_mode AS CFTM ON CFTM.modeunkid = FDGT.pickupmodeunkid AND CFTM.hotel_code=:hotel_code "; 
            }
			else if($contactType=="BookedBy"){
				 $strSql.="SELECT";
				 $strSql.=" IFNULL((SELECT business_name FROM trcontact WHERE contactunkid=FDTI.companyunkid),";
				 $strSql.=" IFNULL((SELECT `business_name` FROM trcontact WHERE contactunkid=FDTI.travelagentunkid),";
				 $strSql.=" IFNULL((SELECT `name` FROM trcontact WHERE contactunkid=FDTI.roomownerunkid),";
				 $strSql.=" IFNULL((SELECT `businesssourcename` FROM cfbusinesssource WHERE businesssourceunkid=FDTI.businesssourceunkid),";
				 $strSql.=" (SELECT `name` FROM trcontact AS TRC INNER JOIN fdguesttran AS FDGT ON FDGT.guestunkid=TRC.contactunkid";
				 $strSql.=" WHERE FDTI.masterguesttranunkid=FDGT.guesttranunkid ) )))) AS name FROM fdtraninfo AS FDTI";
			}
            $strSql.=" WHERE FDTI.tranunkid=:tranunkid AND FDTI.hotel_code=:hotel_code";
			
            $dao = new dao();
            $dao->initCommand($strSql);
            $dao->addParameter(":tranunkid", $tranunkid);
			 $dao->addParameter(":hotel_code", $this->hotelcode);
             
			
			//$this->log->logIt(get_class($this) . "-" . "getContactInfo - " . $strSql);
			/*
            $this->log->logIt(get_class($this) . "-" . "tranunkid - " . $tranunkid);
            $this->log->logIt(get_class($this) . "-" . "contactType - " . $contactType);
			  */
			//Manali - 1.0.33.38 - 10 May 2013 - END			
            $result = $dao->executeRow();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getContactInfo - " . $e);
            $this->handleException($e);
        }
        return $result;
    }

	//Manali - 1.0.33.38 - 10 May 2013 - START
	//Purpose : Enhancement - Added Sharer Information	
	private function getSharerInfo($tranunkid) {
        try {
            $result = NULL;
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getContactInfo");
			$strSql ='';
			
            $strSql = "SELECT IFNULL(TRC.salutation,'') as salutation,IFNULL(TRC.business_name,'') As business_name,TRC.name,IFNULL(TRC.address,'') As address,IFNULL(TRC.city,'') as city,IFNULL(TRC.state,'') As state,IFNULL(TRC.zipcode,'') As zipcode,IFNULL(TRC.country,'') AS country,IFNULL(TRC.phone,'') AS phone,IFNULL(TRC.mobile,'') AS mobile,IFNULL(TRC.fax,'') AS fax,IFNULL(TRC.email,'') AS email,TRC.contacttypeunkid,";	
			$strSql.="IFNULL(TRC.gender,'') AS gender,IFNULL(DATE(TRC.birthdate),'') AS birthdate,CASE WHEN (TRC.identityunkid IS NOT NULL AND TRC.identityunkid <>0) THEN cfidentitytype.identitytype ELSE '' END AS identitytype,
	IFNULL(TRC.identity_no,'') AS identityno,IFNULL(DATE(TRC.exp_date),'') AS expirydate,IFNULL(TRC.nationality,'') AS nationality,DATE(TRC.anniversary) AS anniversary,DATE(TRC.spousebirthdate) AS spousebirthdate, 
CASE WHEN (FDGT.pickupmodeunkid IS NOT NULL AND FDGT.pickupmodeunkid <>0) THEN CFTM.transportationmode ELSE '' END AS transportationmode,
IFNULL(FDGT.pickupvehicle,'') AS pickupvehicle,IFNULL(pickupdatetime,'') AS pickupdatetime ";				
			 $strSql.=" FROM trcontact AS TRC";  
			 $strSql.=" INNER JOIN fdguesttran AS FDGT ON FDGT.guestunkid=TRC.contactunkid";
			 $strSql.=" INNER JOIN fdtraninfo AS FDTI ON (FDTI.masterguesttranunkid!=FDGT.guesttranunkid AND FDTI.tranunkid=FDGT.tranunkid)";    
			 $strSql.=" LEFT JOIN cfidentitytype ON cfidentitytype.identitytypeunkid = TRC.identityunkid AND cfidentitytype.hotel_code=:hotel_code"; 
			 $strSql.=" LEFT JOIN cftransportation_mode AS CFTM ON CFTM.modeunkid = FDGT.pickupmodeunkid AND CFTM.hotel_code=:hotel_code ";
			 $strSql.=" WHERE FDTI.tranunkid=:tranunkid AND FDTI.hotel_code=:hotel_code";
			
            $dao = new dao();
            $dao->initCommand($strSql);
            $dao->addParameter(":tranunkid", $tranunkid);
			 $dao->addParameter(":hotel_code", $this->hotelcode);           
            $result = $dao->executeQuery();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getContactInfo - " . $e);
            $this->handleException($e);
        }
        return $result;
    }
	//Manali - 1.0.33.38 - 10 May 2013 - END

    private function getNetRate($tranunkid) {
        try {
            $netRate = NULL;
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getNetRate");
			#Room Charges
            $resultRoomCharges = $this->getFasMasterTypeTotal('Room Charges', $tranunkid);
            #Total tax
            $resultTotalTax = $this->getFasMasterTypeChildTotal('Room Charges', 'Tax', $tranunkid);
            #Adjustments
            $resultAdjustments = $this->getFasMasterTypeChildTotal('Room Charges', 'Adjustments', $tranunkid);
            #Discount
            $resultDiscont = $this->getFasMasterTypeTotal('Discount', $tranunkid);

			/* Romal - 1.0.46.51 - 28 Aug 2015 - Purpose : Add $resultAdjustments in Net Rate*/
            $netRate = $resultRoomCharges + $resultTotalTax + $resultAdjustments + $resultDiscont;
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getNetRate - " . $e);
            $this->handleException($e);
        }
        return $netRate;
    }

    private function generateGeneralErrorMsg($code='', $msg) {
        try {
            $this->log->logIt(get_class($this) . "-" . "generateGeneralErrorMsg");
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

	//sushma - private to public to call same method in otaxmls.php file.
    public function getFasMasterTypeTotal($type, $tranunkid) {
        try {
            $result = NULL;
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getFasMasterTypeTotal");
            $dao = new dao();
	    
	    //Manali - 1.0.48.53 - 24 Feb 2016 - START
	    //Purpose : Optimized query as query taking too much time after VPC migration
	    if($type=="Discount")
			$mastertypeunkid=11;
	    else if($type=="Extra Charges")
			$mastertypeunkid=4;
	    else
			$mastertypeunkid=5;
            /*$strSql = "SELECT IFNULL(SUM(baseamount),0) AS Total FROM fdtraninfo ";
            $strSql.="		INNER JOIN fasfoliomaster ";
            $strSql.="			ON fasfoliomaster.lnktranunkid = fdtraninfo.tranunkid ";
            $strSql.="		INNER JOIN fasfoliotype ";
            $strSql.="			ON (fasfoliotype.foliotypeunkid = fasfoliomaster.foliotypeunkid AND fasfoliotype.type = 'FrontOffice') ";
            $strSql.="		INNER JOIN  fasfoliodetail ";
            $strSql.="			ON fasfoliomaster.foliounkid = fasfoliodetail.foliounkid ";
            $strSql.="		INNER JOIN fasmaster ";
            $strSql.="			ON fasmaster.masterunkid = fasfoliodetail.masterunkid ";
            $strSql.="		INNER JOIN fasmastertype ";
            $strSql.="			ON (fasmastertype.mastertypeunkid = fasmaster.mastertypeunkid AND fasmastertype.type in (:type)) ";
            $strSql.="		WHERE fdtraninfo.tranunkid = :tranunkid AND fdtraninfo.hotel_code = :hotel_code ";
            $strSql.="		AND isvoid_cancel_noshow_unconfirmed = 0 AND fasfoliodetail.hotel_code = :hotel_code AND fasfoliomaster.hotel_code=:hotel_code";
            
             $dao->initCommand($strSql);
            $dao->addParameter(":tranunkid", $tranunkid);
            $dao->addParameter(":hotel_code", $this->hotelcode);
	    //$dao->addParameter(":type", $type);
            */
	    
	    $strSql="SELECT IFNULL(SUM(baseamount),0) AS Total FROM fdtraninfo ".
		    " INNER JOIN fasfoliomaster ".
		    " ON fasfoliomaster.lnktranunkid = fdtraninfo.tranunkid AND fasfoliomaster.hotel_code=:hotel_code AND fasfoliomaster.foliotypeunkid=1 AND fasfoliomaster.isvoid=0 ".
		    " INNER JOIN  fasfoliodetail ". 
		    " ON fasfoliomaster.foliounkid = fasfoliodetail.foliounkid AND fasfoliodetail.hotel_code = :hotel_code AND fasfoliodetail.isvoid_cancel_noshow_unconfirmed = 0 ". 
		    " INNER JOIN fasmaster ". 
		    " ON fasmaster.masterunkid = fasfoliodetail.masterunkid AND fasmaster.hotel_code=:hotel_code AND fasmaster.mastertypeunkid=:type ". 
		    " WHERE fdtraninfo.tranunkid = :tranunkid AND fdtraninfo.hotel_code = :hotel_code "; 
	    
            $dao->initCommand($strSql);
            $dao->addParameter(":tranunkid", $tranunkid);
            $dao->addParameter(":hotel_code", $this->hotelcode);	    
            $dao->addParameter(":type", $mastertypeunkid);
	     //Manali - 1.0.48.53 - 24 Feb 2016 - END
	    
            $result = $dao->executeRow();
            $result = $result['Total'];
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getFasMasterTypeTotal" . "-" . $e);
            $this->handleException($e);
        }
        return $result;
    }
   //sushma - private to public to call same method in otaxmls.php file.
    public function getFasMasterTypeChildTotal($parenttype, $childtype, $tranunkid)
	{
        try
		{
            $result = NULL;
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getFasMasterTypeChildTotal");
            $dao = new dao();
	    
			//Manali - 1.0.48.53 - 24 Feb 2016 - START
			//Purpose : Optimized query as query taking too much time after VPC migration
            /*$strSql = "SELECT IFNULL(SUM(baseamount),0) AS Total FROM  fasfoliodetail ";
            $strSql.="		INNER JOIN  fasmaster ";
            $strSql.="			ON fasmaster.masterunkid = fasfoliodetail.masterunkid";
            $strSql.="		INNER JOIN  fasmastertype  ";
            $strSql.="			ON (fasmastertype.mastertypeunkid = fasmaster.mastertypeunkid AND fasmastertype.type = :childtype) ";
            $strSql.="INNER JOIN ( ";
            $strSql.="SELECT fasfoliodetail.detailunkid FROM  fdtraninfo ";
            $strSql.="		INNER JOIN  fasfoliomaster ";
            $strSql.="			ON fasfoliomaster.lnktranunkid = fdtraninfo.tranunkid ";
            $strSql.="		INNER JOIN   fasfoliotype ";
            $strSql.="			ON (fasfoliotype.foliotypeunkid = fasfoliomaster.foliotypeunkid AND fasfoliotype.type = 'FrontOffice') ";
            $strSql.="		INNER JOIN   fasfoliodetail ";
            $strSql.="			ON fasfoliomaster.foliounkid = fasfoliodetail.foliounkid ";
            $strSql.="		INNER JOIN  fasmaster ";
            $strSql.="			ON fasmaster.masterunkid = fasfoliodetail.masterunkid ";
            $strSql.="		INNER JOIN  fasmastertype ";
            $strSql.="			ON (fasmastertype.mastertypeunkid = fasmaster.mastertypeunkid AND fasmastertype.type = :parenttype) ";
            $strSql.="		WHERE fdtraninfo.tranunkid = :tranunkid AND fdtraninfo.hotel_code = :hotel_code ";
            $strSql.="	AND isvoid_cancel_noshow_unconfirmed = 0 )  AS parenttable ON parenttable.detailunkid=fasfoliodetail.parentid AND isvoid_cancel_noshow_unconfirmed = 0 ";
            $dao->initCommand($strSql);
            $dao->addParameter(":tranunkid", $tranunkid);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":parenttype", $parenttype);
            $dao->addParameter(":childtype", $childtype);*/
	   
	    $parenttypeid=5; 
	    if($parenttype=="Room Charges")
		$parenttypeid=5;
	    
	    $childtypeid=3;//Tax
	    if($childtype=="Discount")
		$childtypeid=11;
	    else if($childtype=="Adjustments")
		$childtypeid=12;
	    else
		$childtypeid=3;
	    
	    $strSql = "SELECT IFNULL(SUM(baseamount),0) AS Total FROM  fasfoliodetail ". 
            	      "	INNER JOIN  fasmaster ON fasmaster.masterunkid = fasfoliodetail.masterunkid AND fasmaster.mastertypeunkid=:childtype AND fasmaster.hotel_code=:hotel_code".
		      " INNER JOIN ( ".
                      " SELECT fasfoliodetail.detailunkid FROM  fdtraninfo ".
            	      "	INNER JOIN fasfoliomaster ON fasfoliomaster.lnktranunkid = fdtraninfo.tranunkid AND fasfoliomaster.foliotypeunkid=1 AND fasfoliomaster.isvoid=0 ".
            	      "	INNER JOIN fasfoliodetail ON fasfoliomaster.foliounkid = fasfoliodetail.foliounkid AND fasfoliodetail.hotel_code= :hotel_code AND isvoid_cancel_noshow_unconfirmed = 0 ".
            	      "	INNER JOIN  fasmaster ON fasmaster.masterunkid = fasfoliodetail.masterunkid AND fasmaster.hotel_code=:hotel_code AND fasmaster.mastertypeunkid=:parenttype ".
            	      " WHERE fdtraninfo.tranunkid = :tranunkid AND fdtraninfo.hotel_code = :hotel_code ".
		      ")  AS parenttable ".
		      "ON parenttable.detailunkid=fasfoliodetail.parentid AND fasfoliodetail.hotel_code=:hotel_code AND isvoid_cancel_noshow_unconfirmed = 0";
	    $dao->initCommand($strSql);
            $dao->addParameter(":tranunkid", $tranunkid);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":parenttype", $parenttypeid);
            $dao->addParameter(":childtype", $childtypeid);
	    //Manali - 1.0.48.53 - 24 Feb 2016 - END
            $result = $dao->executeRow();
            $result = $result['Total'];
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getFasMasterTypeChildTotal" . "-" . $e);
            $this->handleException($e);
        }
        return $result;
    }

    private function getExtraChargeInfo($tranunkid='')
    {
        try
        {
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getExtraChargeInfo");
            $dao = new dao();
            //Manali - 1.0.48.53 - 24 Feb 2016 - START
            //Purpose : Optimized query as query taking too much
            //Dharti Savaliya - START - 2020-12-21 Purpose :when we create multiple extra charges with same type and same day, we transfer only a single extra charge entry instead of all extra charges CEN-1792
           // $hotelArray = array("4996","6379");
           // if(in_array($this->hotelcode,$hotelArray))
           // {
                $strSql = "SELECT IFNULL(FORMAT(FD.baseamount/cfe.rate,0),0) AS qty,  FASM.name as chargeName,DATE(FD.trandate) as chargeDate,FD.detailunkid,".
                 " FD.baseamount AS netcharge ".
                 " FROM fdtraninfo AS FDTI ".
                 " INNER JOIN fasfoliomaster AS FM ON FM.foliounkid =FDTI.masterfoliounkid AND FM.hotel_code=:hotel_code AND FM.foliotypeunkid=1 ".
                 " LEFT JOIN fasfoliodetail AS FD ON FD.foliounkid=FM.foliounkid AND FD.hotel_code=:hotel_code ".
                 "	LEFT JOIN fasfoliodetail AS IFD ON (FD.detailunkid=IFD.parentid AND IFD.isvoid_cancel_noshow_unconfirmed=0 AND IFD.hotel_code=:hotel_code) ".
                 "	LEFT JOIN fasmaster AS FASM ON FASM.masterunkid=FD.masterunkid AND FASM.hotel_code=:hotel_code ".
                 "	LEFT JOIN cfextracharges AS cfe ON cfe.lnkmasterunkid=FASM.masterunkid ANd cfe.hotel_code=:hotel_code ".
                 "	WHERE FDTI.tranunkid=:tranunkid AND FASM.mastertypeunkid=4 ".
                 " AND FD.isvoid_cancel_noshow_unconfirmed=0 ";
               //Manali - 1.0.48.53 - 24 Feb 2016 - END	   
                $dao->initCommand($strSql);
                $dao->addParameter(":tranunkid", $tranunkid);
                $dao->addParameter(":hotel_code", $this->hotelcode);
                $result = $dao->executeQuery();
                //$this->log->logIt($result);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                     
                if(!empty($result))
                {
                   $myarray =array();
                   foreach($result as $key=>$value){
                       if(isset($value['detailunkid']) && trim($value['detailunkid'])!='')
                           array_push($myarray,$value['detailunkid']);
                   }
                   //$this->log->logIt($myarray);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                   if(!empty($myarray))
                   {	 
                       $strSql1="SELECT SUM(IFNULL(FD.baseamount,0)) as tax,FD.parentid".
                            " FROM fasfoliodetail AS FD ".
                            " WHERE FD.parentid IN (".implode(',',$myarray).") AND FD.hotel_code=:hotel_code GROUP BY FD.parentid ";
                
                       $dao->initCommand($strSql1);
                       $dao->addParameter(":hotel_code", $this->hotelcode);
                       $result1 = $dao->executeQuery();
                       //$this->log->logIt($result1);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                       foreach($result as $key=>$first)
                       {
                           foreach($result1 as $second)
                           {
                               if($first['detailunkid']==$second['parentid'])
                               {
                                   $result[$key]['netcharge']=floatval($first['netcharge'])+floatval($second['tax']);
                               }
                           }
                       }
                       //$this->log->logIt($result);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                   }
                }
           /* }
            else
            {
                $strSql = "SELECT IFNULL(FORMAT(FD.baseamount/cfe.rate,0),0) AS qty,  FASM.name as chargeName,DATE(FD.trandate) as chargeDate,".
                 " FD.baseamount+SUM(IFNULL(IFD.baseamount,0)) AS netcharge ".
                 " FROM fdtraninfo AS FDTI ".
                 " INNER JOIN fasfoliomaster AS FM ON FM.foliounkid =FDTI.masterfoliounkid AND FM.hotel_code=:hotel_code AND FM.foliotypeunkid=1 ".
                 " LEFT JOIN fasfoliodetail AS FD ON FD.foliounkid=FM.foliounkid AND FD.hotel_code=:hotel_code ".
                 "	LEFT JOIN fasfoliodetail AS IFD ON (FD.detailunkid=IFD.parentid AND IFD.isvoid_cancel_noshow_unconfirmed=0 AND IFD.hotel_code=:hotel_code) ".
                 "	LEFT JOIN fasmaster AS FASM ON FASM.masterunkid=FD.masterunkid AND FASM.hotel_code=:hotel_code ".
                 "	LEFT JOIN cfextracharges AS cfe ON cfe.lnkmasterunkid=FASM.masterunkid ANd cfe.hotel_code=:hotel_code ".
                 "	WHERE FDTI.tranunkid=:tranunkid AND FASM.mastertypeunkid=4 ".
                 " AND FD.isvoid_cancel_noshow_unconfirmed=0 ".
                 " GROUP BY FASM.name,FD.trandate ASC ";
                //Manali - 1.0.48.53 - 24 Feb 2016 - END	   
                $dao->initCommand($strSql);
                $dao->addParameter(":tranunkid", $tranunkid);
                $dao->addParameter(":hotel_code", $this->hotelcode);
                $result = $dao->executeQuery();
                #$this->log->logIt('sql '.$strSql);
                
            }*/
            //Dharti Savaliya -END
        }
        catch (Exception $e)
        {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getExtraChargeInfo" . "-" . $e);
            $this->handleException($e);
        }
        return $result;
    }
    
    //sushma - private to public to call same method in otaxmls.php file.
    public function getDayWiseBookingInformation($tranunkid) {
        $result = NULL;
        try {
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getDayWiseBookingInformation");
            $dao = new dao();
            /*$strSql = " SELECT ";
            $strSql .= " 	cfroomtype.roomtypeunkid,";
            $strSql .= " 	cfroomtype.roomtype AS roomtype, ";
            $strSql .= " 	cfratetype.ratetypeunkid,";
            $strSql .= " 	cfratetype.ratetype,";

            $strSql .= " 	cfroomrate_setting.roomrateunkid,";                  //fields added by Nitin
            $strSql .= " 	cfroomrate_setting.display_name,";                   // fields added by Nitin 

            $strSql .= " 	fdrentalinfo.rentaldate AS rentaldate, ";
            $strSql .= " 	fdrentalinfo.adult AS adult, ";
            $strSql .= " 	fdrentalinfo.child AS child, ";
            $strSql .= " 	fasfoliodetail.detailunkid AS foliodetailunkid, ";
            $strSql .= " 	fasfoliodetail.baseamount+SUM(IFNULL(b.baseamount,0)) AS netRate ";
            $strSql .= " FROM fasfoliodetail ";
            $strSql .= " LEFT JOIN fasfoliodetail AS b ON (b.hotel_code=:hotel_code AND fasfoliodetail.detailunkid=b.parentid AND b.isvoid_cancel_noshow_unconfirmed=0)  ";
            $strSql .= " INNER JOIN  fdrentalinfo ON fdrentalinfo.detailunkid=fasfoliodetail.detailunkid ";
            $strSql .= " INNER JOIN fdtraninfo ON (fdtraninfo.tranunkid=fdrentalinfo.tranunkid AND fdrentalinfo.statusunkid<>8)";
            $strSql .= " INNER JOIN fdguesttran ON fdguesttran.guesttranunkid=fdtraninfo.masterguesttranunkid ";
            $strSql .= " INNER JOIN trcontact ON fdguesttran.guestunkid=trcontact.contactunkid ";
            $strSql .= " INNER JOIN fasfoliomaster ON ( fasfoliomaster.foliounkid=fdtraninfo.masterfoliounkid AND fasfoliomaster.foliotypeunkid=1 ) ";
            $strSql .= " INNER JOIN fasmaster ON fasfoliodetail.masterunkid=fasmaster.masterunkid ";
            $strSql .= " LEFT JOIN cfroom ON fdrentalinfo.roomunkid=cfroom.roomunkid ";
            $strSql .= " INNER JOIN cfroomtype ON fdrentalinfo.roomtypeunkid=cfroomtype.roomtypeunkid ";
            $strSql .= " INNER JOIN cfratetype ON fdrentalinfo.ratetypeunkid=cfratetype.ratetypeunkid ";
           	
			// HRK - Converted Inner To Left Join - 08 Mar 2013
			$strSql .= " LEFT JOIN cfroomrate_setting ON cfroomrate_setting.ratetypeunkid=cfratetype.ratetypeunkid and cfroomrate_setting.roomtypeunkid=cfroomtype.roomtypeunkid ";  //Enable by Nitin  as JJ need rateplan code and name in PMS.
            
			$strSql .= " WHERE IFNULL(fdrentalinfo.detailunkid,0)<>0 ";
            $strSql .= " AND fdrentalinfo.tranunkid=:tranunkid AND fdrentalinfo.is_void_cancelled_noshow_unconfirmed=0 ";
            $strSql .= " GROUP BY fasfoliodetail.trandate,fasfoliodetail.detailunkid ";*/
            
          //Dharti Savaliya 2018-10-16 - START - Add ratetye id and name for unmapped room
        /*  if($pmsname == 'Lemon')
          {
            $mappratetypedetails ="select cfroomrate_setting.ratetypeunkid, FDR.ratetypeunkid,cfroomrate_setting.roomtypeunkid,FDR.roomtypeunkid from fdrentalinfo AS FDR INNER JOIN cfroomrate_setting ON cfroomrate_setting.ratetypeunkid=FDR.ratetypeunkid and cfroomrate_setting.roomtypeunkid=FDR.roomtypeunkid where FDR.hotel_code=:hotel_code and FDR.tranunkid=:tranunkid";
            $dao->initCommand($mappratetypedetails);
            // $this->log->logIt("strSqls :".print_r($mappratetypedetails,true));
            $dao->addParameter(":tranunkid", $tranunkid);
			$dao->addParameter(":hotel_code", $this->hotelcode);
             $mapperatetype = $dao->executeQuery();
          }
            if($pmsname == 'Lemon' &&  empty($mapperatetype))
            { 
                $strSql = "SELECT cfroomtype.roomtypeunkid,cfroomtype.roomtype AS roomtype, cfratetype.ratetypeunkid, ".
                              " cfratetype.ratetype,chunmaplogicdetail.ratetypeunkid,chunmaplogicdetail.roomtypeunkid, ".                  
                              " fdrentalinfo.rentaldate AS rentaldate,fdrentalinfo.adult AS adult,fdrentalinfo.child AS child, ". 
                              " fasfoliodetail.detailunkid AS foliodetailunkid,fasfoliodetail.baseamount+SUM(IFNULL(b.baseamount,0)) AS netRate ". 
                              " FROM fasfoliodetail ".
                          " LEFT JOIN fasfoliodetail AS b ON (b.hotel_code=:hotel_code AND fasfoliodetail.detailunkid=b.parentid ".
                          " AND b.isvoid_cancel_noshow_unconfirmed=0) ".  
                          " INNER JOIN  fdrentalinfo ON fdrentalinfo.detailunkid=fasfoliodetail.detailunkid AND fdrentalinfo.hotel_code=:hotel_code ". 
                          " INNER JOIN fdtraninfo ON (fdtraninfo.tranunkid=fdrentalinfo.tranunkid AND fdrentalinfo.statusunkid<>8) ".
                          " INNER JOIN fdguesttran ON fdguesttran.guesttranunkid=fdtraninfo.masterguesttranunkid ".
                          " INNER JOIN trcontact ON fdguesttran.guestunkid=trcontact.contactunkid ".
                          " INNER JOIN fasfoliomaster ON (fasfoliomaster.foliounkid=fdtraninfo.masterfoliounkid ".
                          " AND fasfoliomaster.foliotypeunkid=1 AND fasfoliomaster.hotel_code=:hotel_code) ".
                          " INNER JOIN fasmaster ON fasfoliodetail.masterunkid=fasmaster.masterunkid ".
                          " LEFT JOIN cfroom ON fdrentalinfo.roomunkid=cfroom.roomunkid AND cfroom.hotel_code=:hotel_code ".
                          " INNER JOIN cfroomtype ON fdrentalinfo.roomtypeunkid=cfroomtype.roomtypeunkid ".
                          " INNER JOIN cfratetype ON fdrentalinfo.ratetypeunkid=cfratetype.ratetypeunkid ".
                          "LEFT JOIN chunmaplogicdetail AS chunmaplogicdetail ON ".
                          "chunmaplogicdetail.ratetypeunkid=cfratetype.ratetypeunkid and ". 
                          "chunmaplogicdetail.roomtypeunkid=cfroomtype.roomtypeunkid AND ".
                          "chunmaplogicdetail.hotel_code=:hotel_code WHERE IFNULL(fdrentalinfo.detailunkid,0)<>0 AND ". "fasfoliodetail.hotel_code=:hotel_code AND fdrentalinfo.tranunkid=:tranunkid AND ".
                          "fdrentalinfo.is_void_cancelled_noshow_unconfirmed=0 ".
                          " GROUP BY fasfoliodetail.trandate,fasfoliodetail.detailunkid";
            }
            else
            {*/
                $strSql = " SELECT cfroomtype.roomtypeunkid,cfroomtype.roomtype AS roomtype, cfratetype.ratetypeunkid, ".
                              " cfratetype.ratetype,cfroomrate_setting.roomrateunkid,cfroomrate_setting.display_name, ".                  
                              " fdrentalinfo.rentaldate AS rentaldate,fdrentalinfo.adult AS adult,fdrentalinfo.child AS child, ". 
                              " fasfoliodetail.detailunkid AS foliodetailunkid,fasfoliodetail.baseamount+SUM(IFNULL(b.baseamount,0)) AS netRate ". 
                              " FROM fasfoliodetail ". 
                              " LEFT JOIN fasfoliodetail AS b ON (b.hotel_code=:hotel_code AND fasfoliodetail.detailunkid=b.parentid ". 
                              " AND b.isvoid_cancel_noshow_unconfirmed=0) ".  
                              " INNER JOIN  fdrentalinfo ON fdrentalinfo.detailunkid=fasfoliodetail.detailunkid AND fdrentalinfo.hotel_code=:hotel_code ". 
                              " INNER JOIN fdtraninfo ON (fdtraninfo.tranunkid=fdrentalinfo.tranunkid AND fdrentalinfo.statusunkid<>8) ".
                              " INNER JOIN fdguesttran ON fdguesttran.guesttranunkid=fdtraninfo.masterguesttranunkid ".
                              " INNER JOIN trcontact ON fdguesttran.guestunkid=trcontact.contactunkid ".
                              " INNER JOIN fasfoliomaster ON (fasfoliomaster.foliounkid=fdtraninfo.masterfoliounkid ".
                              " AND fasfoliomaster.foliotypeunkid=1 AND fasfoliomaster.hotel_code=:hotel_code) ".
                              " INNER JOIN fasmaster ON fasfoliodetail.masterunkid=fasmaster.masterunkid ".
                              " LEFT JOIN cfroom ON fdrentalinfo.roomunkid=cfroom.roomunkid AND cfroom.hotel_code=:hotel_code ".
                              " INNER JOIN cfroomtype ON fdrentalinfo.roomtypeunkid=cfroomtype.roomtypeunkid ".
                              " INNER JOIN cfratetype ON fdrentalinfo.ratetypeunkid=cfratetype.ratetypeunkid ".
                              " LEFT JOIN cfroomrate_setting ON cfroomrate_setting.ratetypeunkid=cfratetype.ratetypeunkid ".
                              " and cfroomrate_setting.roomtypeunkid=cfroomtype.roomtypeunkid AND cfroomrate_setting.hotel_code=:hotel_code ".  
                              " WHERE IFNULL(fdrentalinfo.detailunkid,0)<>0 AND fasfoliodetail.hotel_code=:hotel_code ".
                              " AND fdrentalinfo.tranunkid=:tranunkid AND fdrentalinfo.is_void_cancelled_noshow_unconfirmed=0 ".
                              " GROUP BY fasfoliodetail.trandate,fasfoliodetail.detailunkid";
            //}   	    
             
            $dao->initCommand($strSql);
            $dao->addParameter(":tranunkid", $tranunkid);
			$dao->addParameter(":hotel_code", $this->hotelcode);
            //Dharti Savaliya 2018-10-16 - END - Add ratetye id and name for unmapped room
            #$this->log->logIt("tranunkid :".$tranunkid);
            $results = $dao->executeQuery();
           
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getDayWiseBookingInformation" . "-" . $e);
            $this->handleException($e);
        }
        return $results;
    }
	//Satish - 11 Sep 2012 - Start
	//Added param -> $masterGuestInfo
	//Satish - 11 Sep 2012 - End	
    private function generateReservationXML($res, $bookings, $bookedbyInfo,$masterGuestInfo) 
	{
        try
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateReservationXML");

			$pmsname=$this->readConfigParameter("ThirdPartyPMS");
			//$this->log->logIt("PMS Name >>".$pmsname); //Rahul//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]

            $reservation = $this->xmlDoc->createElement("Reservation");
            $bookedByInfo = $this->xmlDoc->createElement("BookByInfo");

            $locid = $this->xmlDoc->createElement("LocationId");
            $locid->appendChild($this->xmlDoc->createTextNode($this->hotelcode)); //Manali - 1.0.46.51 - 27 Aug 2015, Purpose : As LocationId tag was kept but no value was passing in it, so we have used this Location Id now, passing hotel code in Location Id which is used in our push model
            $bookedByInfo->appendChild($locid);

            $unkid = $this->xmlDoc->createElement("UniqueID");
            /*$resno=$res['reservationno'];
              if($res['subreservationno']!='' and $res['subreservationno']!=NULL)
              $resno.="-".$res['subreservationno']; */
            $unkid->appendChild($this->xmlDoc->createTextNode($res['reservationno']));
            $bookedByInfo->appendChild($unkid);
			//Mayur Kannaujia - 1.0.53.61 - 09-03-2018 - Purpose: If Booking comes from MMT then source/CityLedger should be MakemytripXMl not goibibo - START
			$channel_Source = '';
			$channelID = isset($bookings[0]['channelid'])?$bookings[0]['channelid']:'';
			$channelSource = isset($bookings[0]['channel_source'])?$bookings[0]['channel_source']:'';
			if(isset($channelSource) &&  $channelSource != '')
			{
				$channel_Source = $this->findSetChannelCityLedgerIfExists($channelSource);
			}

            $bookedBy = $this->xmlDoc->createElement("BookedBy");
		
			if(isset($channelID) && $channelID == 64 && $channel_Source != '')
			{
				//$this->log->logIt("Channel ID Channel Source---------------------------->".$channel_Source);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				$bookedBy->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($channel_Source)));
			}
			else
			{
				$bookedBy->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($bookedbyInfo['name'])));
			}
			//Mayur Kannaujia - 1.0.53.61 - 09-03-2018 - Purpose: If Booking comes from MMT then source/CityLedger should be MakemytripXMl not goibibo - END
            $bookedByInfo->appendChild($bookedBy);
			
			/*Satish - 11 Sep 2012 - Start*/
			//Removed detailed booker/booked by information and replaced it with master guest info.
			/*Satish - 11 Sep 2012 - End*/
			
			//Satish - 11 Sep 2012 - Start
			//Master Guest Info		

            $salutation = $this->xmlDoc->createElement("Salutation");
            $salutation->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['salutation']));
            $bookedByInfo->appendChild($salutation);

			$master_guest = explode(" ",$masterGuestInfo['name']);
			
			$first_name ='';
			$last_name = '';
			//Sanjay Waman - 12 Jul 2019 - Muktiple space in guest name [CEN-1167] - Start
			if(count($master_guest)>2 && (isset($master_guest[2]) && trim($master_guest[2])!=''))
			{
				//$this->log->logIt("MultipleSpaceInName");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				$first_name = $master_guest[0]." ".$master_guest[1]."";
				unset($master_guest[0]);
				unset($master_guest[1]);
				$last_name = implode(' ',$master_guest);
			}
			else
			{
			//Sanjay Waman - End
			$first_name = $master_guest[0];
			unset($master_guest[0]);
			$last_name = implode(' ',$master_guest);
			}
            
            $fname = $this->xmlDoc->createElement("FirstName");
            $fname->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($first_name)));
            $bookedByInfo->appendChild($fname);

            $lname = $this->xmlDoc->createElement("LastName");
            $lname->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($last_name)));
            $bookedByInfo->appendChild($lname);
			
			//Manali - 1.0.33.38 - 10 May 2013 - START
			//Purpose : Enhancement - Passing Personal Information - Gender
			$gender = $this->xmlDoc->createElement("Gender");
            $gender->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['gender'])));
            $bookedByInfo->appendChild($gender);
			//Manali - 1.0.33.38 - 10 May 2013 - END
			
			$address = $this->xmlDoc->createElement("Address");
            $address->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['address'])));
            $bookedByInfo->appendChild($address);

            $city = $this->xmlDoc->createElement("City");
            $city->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['city'])));
            $bookedByInfo->appendChild($city);

            $state = $this->xmlDoc->createElement("State");
            $state->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['state'])));
            $bookedByInfo->appendChild($state);

			$country = $this->xmlDoc->createElement("Country");
			if(isset($pmsname) && $pmsname == 'Lemon' && $masterGuestInfo['country'] !== '')
			{				 
				$countryISOcode = $this->GetCountryISOCode($masterGuestInfo['country']);					
				//$this->log->logIt("nationalityISOcode >>".print_r($countryISOcode,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				$this->log->logIt("nationalityISOcode >>".$countryISOcode['isocode']);									
				$country->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($countryISOcode['isocode'])));
			}
			else
			{
				$country->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['country'])));
			}

            
            $bookedByInfo->appendChild($country);
			
            $zipcode = $this->xmlDoc->createElement("Zipcode");
            $zipcode->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['zipcode']));
            $bookedByInfo->appendChild($zipcode);

            $phone = $this->xmlDoc->createElement("Phone");
            $phone->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['phone']));
            $bookedByInfo->appendChild($phone);
			
			//Flora - 1.0.30.35 - 10th Dec 2012 - START
			//Purpose : Added Mobile & Fax fields	
			$mobile = $this->xmlDoc->createElement("Mobile");
            $mobile->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['mobile']));
            $bookedByInfo->appendChild($mobile);
			
			$fax = $this->xmlDoc->createElement("Fax");
            $fax->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['fax']));
            $bookedByInfo->appendChild($fax);			
			//Flora - 1.0.30.35 - 10th Dec 2012 - END
			
            $email = $this->xmlDoc->createElement("Email");
            $email->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['email']));
            $bookedByInfo->appendChild($email);			
			//Satish - 11 Sep 2012 - End
			
			$source = $this->xmlDoc->createElement("Source");
			//Mayur Kannaujia - 1.0.53.61 - 09-03-2018 - Purpose: If Booking comes from MMT then source/CityLedger should be MakemytripXMl not goibibo - START
			if(isset($channelID) && $channelID == 64 && $channel_Source != '')
			{
				//$channel_Source = $this->findSetChannelCityLedgerIfExists($channel_Source);
				$source->appendChild($this->xmlDoc->createTextNode($channel_Source));
			}
			else
			{
				$source->appendChild($this->xmlDoc->createTextNode($res['source']));
			}
			//Mayur Kannaujia - 1.0.53.61 - 09-03-2018 - Purpose: If Booking comes from MMT then source/CityLedger should be MakemytripXMl not goibibo - END
            $bookedByInfo->appendChild($source);
	    
			//	Charul	-	24th July,2014	-	START
			$bookingtype = $this->xmlDoc->createElement("IsChannelBooking");
			if(isset($res['bookingtype']))
			{
				if($res['bookingtype']=='CHANNEL')
					$bookingtype->appendChild($this->xmlDoc->createTextNode('1'));
				else
					$bookingtype->appendChild($this->xmlDoc->createTextNode('0'));
			}
			else
				$bookingtype->appendChild($this->xmlDoc->createTextNode(''));
				$bookedByInfo->appendChild($bookingtype);
				//	Charul	-	24th July,2014	-	END
			
            $bookings = $this->getBookings($res['reservationno']);
			//$bookings = $this->getcancelBookingsdetails($res['reservationno'],$this->hotelcode); //Rahul
			$auditlogs = new pmsbookingfunctons(); //Dharti Savaliya - 2019-06-24
			$objSequenceDao = new sequencedao(); //Manali - 1.0.51-56 - 17 Feb 2017,Purpose : Enhancement - Separate CC Servers Functionality - Change logs
            foreach ($bookings as $booking)
			{
				//$this->log->logIt('Some bookings=====>'.print_r($booking,true)); 
                $bookingTranInfo = $this->xmlDoc->createElement("BookingTran");
                $subres = $this->xmlDoc->createElement("SubBookingId");
                $subres->appendChild($this->xmlDoc->createTextNode($booking['subreservationno']!=''?($res['reservationno']."-".$booking['subreservationno']):$res['reservationno'])); //Manali - 1.0.30.35 - 11 Jan 2012, Purpose : Passing reservation no with subreservation no as per requirement
                $bookingTranInfo->appendChild($subres);
							
				#Chandrakant - 1.0.37.42 - 13 Nov 2013 - START
				#Purpose : Enhancement : add status from fdchannelbookinginfo
				$TranUnkid = $this->xmlDoc->createElement("TransactionId");
                $TranUnkid->appendChild($this->xmlDoc->createTextNode($booking['tranunkid']));
                $bookingTranInfo->appendChild($TranUnkid);
				
				//Dharti Savaliya - 2019-06-24 Purpose:- If new booking not transfer at pms end and booking came into the modify status at that time booking status as a new
				$Bookigstatus = '';
				$TranStatus = $this->xmlDoc->createElement("Status");
				$auditlogs->hotelcode = $this->hotelcode ;
				$auditdetail = $auditlogs->getauditlogdetails($booking['tranunkid']);
                //Dharti Savaliya 2020-08-10 Purpose : correct spelling CEN-1599
				if(!in_array('Booking Transferred To PMS', array_column($auditdetail, 'operation')) && $booking['status'] == 'Modify')
				{
					$Bookigstatus = 'New';
					$TranStatus->appendChild($this->xmlDoc->createTextNode($Bookigstatus));                	
				}
				else
				{					
					$TranStatus->appendChild($this->xmlDoc->createTextNode($booking['status']));					
				}
				$bookingTranInfo->appendChild($TranStatus);
				#Chandrakant - 1.0.37.42 - 13 Nov 2013 - END
				//Dharti Savaliya - 2019-06-24 -END
				
				//Manali - 1.0.49.54 - 26 May 2016 - START
				//Purpose : Customization : Send guarantee - is reservation confirmed or not confirmed
				$TranGuarantee = $this->xmlDoc->createElement("IsConfirmed");
                $TranGuarantee->appendChild($this->xmlDoc->createTextNode($booking['isconfirmed']));
                $bookingTranInfo->appendChild($TranGuarantee);
				//Manali - 1.0.49.54 - 26 May 2016 - END
								
                $voucherno = $this->xmlDoc->createElement("VoucherNo")		;
                $voucherno->appendChild($this->xmlDoc->createTextNode($booking['travelagentvoucherno']));
                $bookingTranInfo->appendChild($voucherno);

                $masterGuestInfo = $this->getContactInfo($booking['tranunkid'], "MasterGuest");
                $totalNetBookingAmount = $this->getNetRate($booking['tranunkid']);
                $totalDiscount = $this->getFasMasterTypeTotal('Discount', $booking['tranunkid']);
                $totalExtraCharges = $this->getFasMasterTypeTotal('Extra Charges', $booking['tranunkid']);
                $extraChargeInfo = $this->getExtraChargeInfo($booking['tranunkid']);
              
                $dayWiseBookingInfo = $this->getDayWiseBookingInformation($booking['tranunkid']); 				              
				$totalPayment=$this->getTotalPayment($booking['tranunkid']);
                
                //Sushma Rana - Added below code for commision - 6th nov 2017
                $totalCommision = '0';
                $this->log->logIt("Total commision ====>".$booking['commisionid']);
                if(isset($booking['commisionid']) && $booking['commisionid'] == 300 )
                {
                    $totalCommision = $this->getcommision($booking['tranunkid']);                    
                }
                //$this->log->logIt("Type Total payment ====>".gettype($totalPayment));
                //$this->log->logIt("Type Total commision ====>".gettype($totalCommision));
                $this->log->logIt("Total commision ====>".$totalCommision);
               
				/*$this->log->logIt($booking['tranunkid']);*/
				//$daywisebooking_print = json_encode($dayWiseBookingInfo);
				//$this->log->logIt("daywise booking".print_r($dayWiseBookingInfo,TRUE)); //Rahul
				//$this->log->logIt("daywise booking".$daywisebooking_print); //Rahul
				
				
                $packagecode = $this->xmlDoc->createElement("PackageCode");
                $packagecode->appendChild($this->xmlDoc->createTextNode($dayWiseBookingInfo[0]['ratetypeunkid']));
                $bookingTranInfo->appendChild($packagecode);

                $pakcagename = $this->xmlDoc->createElement("PackageName");
                $pakcagename->appendChild($this->xmlDoc->createTextNode($dayWiseBookingInfo[0]['ratetype']));
                $bookingTranInfo->appendChild($pakcagename);
                //Dharti Savaliya 2018-10-16 - START - Add ratetye id and name for unmapped room 
                $rateplancode = $this->xmlDoc->createElement("RateplanCode");
                //if(isset($dayWiseBookingInfo[0]['roomrateunkid']))
                //{
                    $rateplancode->appendChild($this->xmlDoc->createTextNode($dayWiseBookingInfo[0]['roomrateunkid']));
              //  }
               // else
               // {
                   // $rateplancode->appendChild($this->xmlDoc->createTextNode($dayWiseBookingInfo[0]['ratetypeunkid']));
               // }
                $bookingTranInfo->appendChild($rateplancode);

                $rateplanname = $this->xmlDoc->createElement("RateplanName");
               // if(isset($dayWiseBookingInfo[0]['display_name']))
               // {
                    $rateplanname->appendChild($this->xmlDoc->createTextNode($dayWiseBookingInfo[0]['display_name']));
               // }
                //else
               // {
                   // $rateplanname->appendChild($this->xmlDoc->createTextNode($dayWiseBookingInfo[0]['ratetype']));
              //  }
                $bookingTranInfo->appendChild($rateplanname);
                //Dharti Savaliya 2018-10-16 - END - Add ratetye id and name for unmapped room 

                $roomtypecode = $this->xmlDoc->createElement("RoomTypeCode");
                $roomtypecode->appendChild($this->xmlDoc->createTextNode($booking['roomtypeunkid']));
                $bookingTranInfo->appendChild($roomtypecode);
				//Dharti Savaliya 2019-01-02 -START - purpose:- family hotel roomtype issue(portuguese language)
				$roomtype = $this->xmlDoc->createElement("RoomTypeName");
				if(isset($pmsname) && $pmsname == 'familyhotel')
				{
					$roomtypes ='';					
					$roomtypes = $this->normalize($booking['roomtype']);					
                	$roomtype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($roomtypes)));	
				}
				else
				{	
					$roomtype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($booking['roomtype'])));
				}
				//Dharti 2019-01-02 - END
				$bookingTranInfo->appendChild($roomtype);
				
                $arrivaldate = $this->xmlDoc->createElement("Start");
                $arrivaldate->appendChild($this->xmlDoc->createTextNode($booking['arrivaldate']));
                $bookingTranInfo->appendChild($arrivaldate);

                $departuredate = $this->xmlDoc->createElement("End");
                $departuredate->appendChild($this->xmlDoc->createTextNode($booking['departuredate']));
                $bookingTranInfo->appendChild($departuredate);

                $totalrate = $this->xmlDoc->createElement("TotalRate");
                $totalrate->appendChild($this->xmlDoc->createTextNode(number_format(floatval($totalNetBookingAmount), 2, '.', '')));
                $bookingTranInfo->appendChild($totalrate);

                $totaldiscount = $this->xmlDoc->createElement("TotalDiscount");
                $totaldiscount->appendChild($this->xmlDoc->createTextNode(number_format(floatval($totalDiscount), 2, '.', '')));
                $bookingTranInfo->appendChild($totaldiscount);

                $totalextracharges = $this->xmlDoc->createElement("TotalExtraCharge");
                $totalextracharges->appendChild($this->xmlDoc->createTextNode(number_format(floatval($totalExtraCharges), 2, '.', '')));
                $bookingTranInfo->appendChild($totalextracharges);

                $totalpayment = $this->xmlDoc->createElement("TotalPayment");
                $totalpayment->appendChild($this->xmlDoc->createTextNode(number_format(floatval($totalPayment), 2, '.', '')));
                $bookingTranInfo->appendChild($totalpayment);
				
				//Sanjay Waman - 23 Aug 2019 - Buzzotel request for PayAthotel flag [CEN-1234] - Start
				if(isset($pmsname) && $pmsname == 'EzeeTest')
				{
					$totalpaymentflag = $this->xmlDoc->createElement("PayAtHotel");
					$paymentflag = "true";
					if($totalPayment>0)
					{
						$paymentflag = "false";
					}
					$totalpaymentflag->appendChild($this->xmlDoc->createTextNode($paymentflag));
					$bookingTranInfo->appendChild($totalpaymentflag);
				}
				//Sanjay Waman - End
                
                 //Sushma Rana - Added below code for commision - 6th nov 2017
                $totalcommision = $this->xmlDoc->createElement("TACommision");
                $totalcommision->appendChild($this->xmlDoc->createTextNode(number_format(floatval($totalCommision), 2, '.', '')));
                $bookingTranInfo->appendChild($totalcommision);

                $salutation = $this->xmlDoc->createElement("Salutation");
                $salutation->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['salutation']));
                $bookingTranInfo->appendChild($salutation);

				$master_guest = explode(" ",$masterGuestInfo['name']);
				$first_name ='';
				$last_name = '';
				//Sanjay Waman - 12 Jul 2019 - Muktiple space in guest name [CEN-1167] - Start
				if(count($master_guest)>2 && (isset($master_guest[2]) && trim($master_guest[2])!=''))
				{
					$this->log->logIt("2MultipleSpaceInName");
					$first_name = $master_guest[0]." ".$master_guest[1]."";
					unset($master_guest[0]);
					unset($master_guest[1]);
					$last_name = implode(' ',$master_guest);
				}
				else
				{
				$first_name = $master_guest[0];
				unset($master_guest[0]);
				$last_name = implode(' ',$master_guest);
				}
                $fname = $this->xmlDoc->createElement("FirstName");
                $fname->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($first_name)));
                $bookingTranInfo->appendChild($fname);

                $lname = $this->xmlDoc->createElement("LastName");
                $lname->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($last_name)));
                $bookingTranInfo->appendChild($lname);
				
				//Manali - 1.0.33.38 - 10 May 2013 - START
				//Purpose : Enhancement - Personal Information - Gender,Nationality, Date Of Birth,Spouse Birthdate,Wedding Anniversary
				$gendertag = $this->xmlDoc->createElement("Gender");
				if($masterGuestInfo['salutation']!='')
				{
					if($masterGuestInfo['salutation']=='MRS' || $masterGuestInfo['salutation']=='MS' || $masterGuestInfo['salutation']=='MAM')
						$gender="Female";
					else
						$gender="Male";
				}
				else
					$gender="Other";
				$gendertag->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['gender']!=''?$masterGuestInfo['gender']:$gender)));
				$bookingTranInfo->appendChild($gendertag);
				
				$dob = $this->xmlDoc->createElement("DateOfBirth");
				$dob->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['birthdate'])));
				$bookingTranInfo->appendChild($dob);
				
				$sdob = $this->xmlDoc->createElement("SpouseDateOfBirth");
				$sdob->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['spousebirthdate'])));
				$bookingTranInfo->appendChild($sdob);
				
				$anniversary = $this->xmlDoc->createElement("WeddingAnniversary");
				$anniversary->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['anniversary'])));
				$bookingTranInfo->appendChild($anniversary);
				
				$nationality = $this->xmlDoc->createElement("Nationality");
				if(isset($pmsname) && $pmsname == 'Lemon' && $masterGuestInfo['nationality'] !== '')
				{
					$this->log->logIt("nationalityISOcode >>".$masterGuestInfo['nationality']); 
					$nationalityISOcode = $this->GetCountryISOCode($masterGuestInfo['nationality']);					
					$this->log->logIt("nationalityISOcode >>".print_r($nationalityISOcode,true)); 	
					$this->log->logIt("nationalityISOcode >>".$nationalityISOcode['isocode']);									
					$nationality->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($nationalityISOcode['isocode'])));
				}
				else
				{
					$nationality->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['nationality'])));
				}
				$bookingTranInfo->appendChild($nationality);
				//Manali - 1.0.33.38 - 10 May 2013 - END
				
                $address = $this->xmlDoc->createElement("Address");
                $address->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['address'])));
                $bookingTranInfo->appendChild($address);

                $city = $this->xmlDoc->createElement("City");
                $city->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['city'])));
                $bookingTranInfo->appendChild($city);

                $state = $this->xmlDoc->createElement("State");
                $state->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['state'])));
                $bookingTranInfo->appendChild($state);

				$country = $this->xmlDoc->createElement("Country");				
			
				if(isset($pmsname) && $pmsname == 'Lemon' && $masterGuestInfo['country'] !== '')
				{
					$this->log->logIt("countryISOcode >>".$masterGuestInfo['country']); 
					$countryISOcode = $this->GetCountryISOCode($masterGuestInfo['country']);
					$this->log->logIt("countryISOcode >>".json_encode($countryISOcode,true)); //Rahul					
					$country->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($countryISOcode['isocode'])));					
				}
				else
				{
					$country->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['country'])));
				}
                $bookingTranInfo->appendChild($country);

                $zipcode = $this->xmlDoc->createElement("Zipcode");
                $zipcode->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['zipcode']));
                $bookingTranInfo->appendChild($zipcode);

                $phone = $this->xmlDoc->createElement("Phone");
                $phone->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['phone']));
                $bookingTranInfo->appendChild($phone);
				
				//Flora - 1.0.30.35 - 10th Dec 2012 - START
				//Purpose : Added Mobile & Fax fields	
				$mobile = $this->xmlDoc->createElement("Mobile");
				$mobile->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['mobile']));
				$bookingTranInfo->appendChild($mobile);
				
				$fax = $this->xmlDoc->createElement("Fax");
				$fax->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['fax']));
				$bookingTranInfo->appendChild($fax);			
				//Flora - 1.0.30.35 - 10th Dec 2012 - END
				
                $email = $this->xmlDoc->createElement("Email");
                $email->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['email']));
                $bookingTranInfo->appendChild($email);
				
				//Manali - 1.0.33.38 - 10 May 2013 - START
				//Purpose : Enhancement - Passing Transport/Identity Information
				$identitytype = $this->xmlDoc->createElement("IdentiyType");
                $identitytype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['identitytype'])));
                $bookingTranInfo->appendChild($identitytype);
				
				$identityno = $this->xmlDoc->createElement("IdentityNo");
                $identityno->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['identityno'])));
                $bookingTranInfo->appendChild($identityno);
					
				$expdate = $this->xmlDoc->createElement("ExpiryDate");
                $expdate->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['expirydate'])));
                $bookingTranInfo->appendChild($expdate);
				
				$tmode = $this->xmlDoc->createElement("TransportationMode");
                $tmode->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['transportationmode'])));
                $bookingTranInfo->appendChild($tmode);
				
				$vehicle = $this->xmlDoc->createElement("Vehicle");
                $vehicle->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['pickupvehicle'])));
                $bookingTranInfo->appendChild($vehicle);
				
				$transportdate=$transporttime='';
				if($masterGuestInfo['pickupdatetime']!='')
					list($transportdate,$transporttime)=explode(" ",$masterGuestInfo['pickupdatetime']);
									
				$pickupdate = $this->xmlDoc->createElement("PickupDate");				
                $pickupdate->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($transportdate)));
                $bookingTranInfo->appendChild($pickupdate);
				
				$pickuptime = $this->xmlDoc->createElement("PickupTime");				
                $pickuptime->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($transporttime)));
                $bookingTranInfo->appendChild($pickuptime);
				//Manali - 1.0.33.38 - 10 May 2013 - END
				
                $source = $this->xmlDoc->createElement("Source");
				//Mayur Kannaujia - 1.0.53.61 - 09-03-2018 - Purpose: If Booking comes from MMT then source/CityLedger should be MakemytripXMl not goibibo - START
				if(isset($channelID) && $channelID == 64 && $channel_Source != '')
				{
					$source->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($channel_Source)));
				}
				else
				{
					$source->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($booking['source'])));
				}
				//Mayur Kannaujia - 1.0.53.61 - 09-03-2018 - Purpose: If Booking comes from MMT then source/CityLedger should be MakemytripXMl not goibibo - END
                $bookingTranInfo->appendChild($source);
				//Add Dharti Savaliya - START - 2018-12-15 - Purpose:- remove special charecter for buzzohel and family hotel PMS
				$comment = $this->xmlDoc->createElement("Comment");	 
				if(isset($pmsname) && ($pmsname == 'EzeeTest' || $pmsname == 'familyhotel') )
				{
					$this->log->logIt("Comment >>");
					$comments=strip_tags(htmlspecialchars_decode($booking['specreq']));							
                	$comment->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($comments)));
					
				}
				else
				{			
               	 	$comment->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($booking['specreq'])));
				}
				$bookingTranInfo->appendChild($comment);
                //Add Dharti - END - 2018-12-15
		
				//Charul - 15 July 2014 - START
				//Purpose : Enhancement - To fetch Affilate Information
				if($booking['affiliateunkid']!='')
				{
					$affiliateInfo = array();	
					$affiliateInfo =  $this->getAffiliateInfo($booking['affiliateunkid']);
					//$this->log->logIt(print_r($affiliateInfo,true));
					
					$affil_name = $this->xmlDoc->createElement("AffiliateName");
					$affil_code = $this->xmlDoc->createElement("AffiliateCode");
					if(isset($affiliateInfo[0]))
					{
						if(isset($affiliateInfo[0]['name']))
							$affil_name->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($affiliateInfo[0]['name'])));
						else
							$affil_name->appendChild($this->xmlDoc->createTextNode($this->xmlEscape('')));
					
						if(isset($affiliateInfo[0]['affiliate_code']))
							$affil_code->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($affiliateInfo[0]['affiliate_code'])));
						else
							$affil_code->appendChild($this->xmlDoc->createTextNode($this->xmlEscape('')));
					}
					else
					{
						$affil_name->appendChild($this->xmlDoc->createTextNode($this->xmlEscape('')));
						$affil_code->appendChild($this->xmlDoc->createTextNode($this->xmlEscape('')));
					}
					$bookingTranInfo->appendChild($affil_name);
					$bookingTranInfo->appendChild($affil_code);
				}
				else
				{
					$affil_name = $this->xmlDoc->createElement("AffiliateName");
					$affil_name->appendChild($this->xmlDoc->createTextNode($this->xmlEscape('')));
					$bookingTranInfo->appendChild($affil_name);
					
					$affil_code = $this->xmlDoc->createElement("AffiliateCode");
					$affil_code->appendChild($this->xmlDoc->createTextNode($this->xmlEscape('')));
					$bookingTranInfo->appendChild($affil_code);
				}
				//Charul - 15 July 2014 - END
			
				//Manali - 1.0.36.41 - 30 Aug 2013 - START
				//Purpose : Customization : Send package,credit card info in bookings for PMS-Web Interface	
				$cc_no='';
				$cc_type='';
				$cc_expirydate_original='';
				$cc_holdersname='';
				$token=$enctoken='';
				$cvc = '';
				
				//Manali - 20th Mar 2017 - START
				//Purpose : Enhancement - Separate CC Servers Functionality - Change logs
				$sequence=array();
				if($booking['channelbookingunkid']!='' && $booking['ruid_status']!='' && $booking['ruid']!='')
					$sequence = $objSequenceDao->getsequence($booking['ruid_status'],$booking['channelbookingunkid'],$booking['ruid']);
				
				if(count($sequence)>0) // Dharti Savaliya 10-02-2020 January 2020 - Purpose : PHP5.6 to 7.2 CEN-1017
				{
					//Expiry Date
					$cc_expirydate_original = isset($sequence->expirydate) ? $sequence->expirydate : '';
					
					//CCType
					$cc_type = isset($sequence->type) ? $sequence->type : '';
					
					//Name On Card
					$cc_holdersname = isset($sequence->name_on_card) ? $sequence->name_on_card : '';

					//Name On Card
					$cvc = isset($sequence->cvc) ? $sequence->cvc : '';
					
					//Credit Card No
					$cc_no=isset($sequence->no) ? $sequence->no : '';
					$partial_creditcard_no = substr($cc_no,-8);
					
					$obj = new encdec();
					$channelhotelid = ($booking['channelhotelid']!='' && $booking['channelhotelid']!='0')?$booking['channelhotelid']:$booking['tranunkid'];
					$channelbookingid = ($booking['channelbookingno']!='' && $booking['channelbookingno']!='0')?$booking['channelbookingno']:$booking['reservationno'];
					$channelunkid = ($booking['channelid']!='' && $booking['channelid']!='0')?$booking['channelid']:0;
					
					$token=$channelhotelid.'|'.$channelbookingid.'|'.$partial_creditcard_no.'|'.$channelunkid;					
					$enctoken = 'https://'.util::getServerURL().'/index.php/page/reservation.viewreservation?token='.$obj->encode($token);
				}
				
				//$this->log->logIt("Expiry date----> ".$cc_expirydate_original);
				//$this->log->logIt("CC type----> ".$cc_type);
				//$this->log->logIt("cc_holdersname----> ".$cc_holdersname);
				//$this->log->logIt("CC number----> ".$cc_no);
				//$this->log->logIt("Innsoft cc encrpt log debug 1");
				//$this->log->logIt($res['reservationno']."|".$booking['cc_info1']."|".$booking['cc_info2']);
				//if($booking['cc_info1']!='' && $booking['cc_info2']!='')
				//{
					//$obj = new encdec();
					
					//$this->log->logIt("Tran Table CCPin : ".$booking['cc_info2']);
					//$trancreditcard_pin = $obj->decode($booking['cc_info2']);
					//$this->log->logIt($trancreditcard_pin);
					
					//list($partial_creditcard_no,$cc_holdersname1) = explode("|",$trancreditcard_pin);
					
					//Commented code as per new cc logic changed - Manali - 20th Mar 2017
					//$this->log->logIt("Channel Booking Table CCPin : ".$booking['cc_info1']);
					/*$creditcardxml = $obj->decode($booking['cc_info1']);
					$creditcardxml =  util::string_replace("&","&amp;",$creditcardxml);*/
					//$this->log->logIt($creditcardxml);
					
					//Manali - 1.0.48.53 - 8th March 2016 - START
					//Purpose : Enhancement - Placed CCInfo link in base64 encoded format
					//$channelhotelid = ($booking['channelhotelid']!='' && $booking['channelhotelid']!='0')?$booking['channelhotelid']:$booking['tranunkid'];
					//$channelbookingid = ($booking['channelbookingno']!='' && $booking['channelbookingno']!='0')?$booking['channelbookingno']:$booking['reservationno'];
					//$channelunkid = ($booking['channelid']!='' && $booking['channelid']!='0')?$booking['channelid']:0;
					
					//$token=$channelhotelid.'|'.$channelbookingid.'|'.$partial_creditcard_no.'|'.$channelunkid;					
					//$enctoken = 'https://'.util::getServerURL().'/index.php/page/reservation.viewreservation?token='.$obj->encode($token);
					//Manali - 1.0.48.53 - 8th March 2016 - END
					
					//OLD CODE : Commented below code as per new cc logic changed - Manali - 20th Mar 2017					
					//$this->log->logIt("START");
					/*$creditcard_response = simplexml_load_string($creditcardxml);	
					//$this->log->logIt($creditcard_response->a->b);										
					foreach ($creditcard_response as $cc_key => $cc_value) 
					{
						//$this->log->logIt($cc_key);
						//$this->log->logIt($cc_value . '');
						switch ($cc_key) 
						{
							case "b":
								//CVV Code;						
								break;
							case "c":
								$cc_expirydate = $cc_value.""; 															
								break;
							case "d":
								$cc_value =  util::string_replace("&amp;","&",$cc_value . '');							
								$cc_holdersname = $cc_value . '';						
								break;
							case "e":						
								$cc_no = $cc_value . '' . $partial_creditcard_no;
								break;
							case "f":
								$cc_type = $cc_value . '';							
								break;
							case "g":						
								break;
						}
					}	*/
					//$this->log->logIt("END");	
				//}
				//Manali - 20th Mar 2017 - END
				
				//Sushma - 16th Aug 2017 - start
				// Change below code Because it create problem when 31st of month and expiry date
				// = 6/2020 so here "DateTime::createFromFormat" function take today date and today
				// is 31st so it took 31/06/2020 and it is not possible 31 days in 6th month so it
				//  will take 1st july 2020 it will change expiry date.
				//$this->log->logIt("Expiry date Before----> ".$cc_expirydate_original);
				$cc_expirydate = '';
				if(isset($cc_expirydate_original) && $cc_expirydate_original != '')
				{
					//$cc_expirydate_original = '0120';					
					//$this->log->logIt("CC expiry date original which is get from token server----> ".$cc_expirydate_original);
					$objMasterDao2 = new masterdao_2();
					$cc_expirydate = $objMasterDao2->CCExpiryDateFormat($cc_expirydate_original);
					
					//$this->log->logIt("Final date----> ".$cc_expirydate);
					// Sushma- 21st august 2016 - Method transfer to msaterdao_2.php
					//$cc_expirydate_ori = $cc_expirydate;					
					//$cc_expirydate = $this->ccexpirydate($cc_expirydate_original);					
					/*
					$this->log->logIt("Final date1----> ".$cc_expirydate);
					if($cc_expirydate!='')
					{
						
						$ccdate = DateTime::createFromFormat('Y-m-d', $cc_expirydate);						
						//$ccdate->setTimezone(new DateTimeZone('UTC'));
						$this->log->logIt("Defined CC formats match Y-m ----> ".print_r($ccdate,true));
						if(!empty($ccdate))
						{
							$cc_expirydate = $ccdate->format('Y-m');
							$this->log->logIt("Final defined CC formats match Y-m ---> ".$cc_expirydate);
						}
						else
						{
							$ccdate = DateTime::createFromFormat('d/m/Y', $cc_expirydate);
							
							$this->log->logIt("Defined CC formats match m/Y----> ".print_r($ccdate,true));
							if(!empty($ccdate))
							{
								$cc_expirydate = $ccdate->format('Y-m');
								$this->log->logIt("Defined CC formats match m/Y----> ".$cc_expirydate);
								//$this->log->logIt("today date----> ".date("Y-m-d H:i:s"));
							}
							else
							{
								$ccdate = DateTime::createFromFormat('d-m-Y', $cc_expirydate);							   
								if(!empty($ccdate))
								{
									$cc_expirydate = $ccdate->format('Y-m');
									$this->log->logIt("Defined CC formats match m-Y----> ".$cc_expirydate);   
								}
								else
								{
									$ccdate = DateTime::createFromFormat('Y/m/d', $cc_expirydate);									
									if(!empty($ccdate))
									{
										$cc_expirydate = $ccdate->format('Y-m');
										$this->log->logIt("Defined CC formats match Y/m----> ".$cc_expirydate);
									}
									else
									{
										
										$this->log->logIt("Defined CC formats match my----> ".$cc_expirydate);
										$ccdate = DateTime::createFromFormat('dmy', $cc_expirydate);
										
									    $this->log->logIt("Defined CC formats match my----> ".print_r($ccdate,true));
										if(!empty($ccdate))
										{
											
											$cc_expirydate = $ccdate->format('Y-m');
											$this->log->logIt("Defined CC formats match my----> ".$cc_expirydate);
										}
										else
										{
											$this->log->logIt("Defined CC formats match mY----> ".$cc_expirydate);
											$ccdate = DateTime::createFromFormat('dmY', $cc_expirydate);											
											$this->log->logIt("Defined CC formats match mY----> ".print_r($ccdate,true));
											if(!empty($ccdate))
											{
												
												$cc_expirydate = $ccdate->format('Y-m');
												$this->log->logIt("Defined CC formats match mY----> ".$cc_expirydate);
											}
											else
											{
												$this->log->logIt("Defined CC formats match else part ----> ".$cc_expirydate);									
											}								
										}
									}
								}
							}
						}
					}
					*/
				
				}
				
				//Sushma - 16th Aug 2017 - end
				
				//$this->log->logIt("CC expiry date after----> ".$cc_expirydate);
			    
				//Manali - 1.0.48.53 - 7th March 2016 - START
				//Purpose : Enhancement - Placed CCInfo link in base64 encoded format
				$ccinfo = $this->xmlDoc->createElement("CCLink");		
				$ccinfo->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($enctoken!=''?base64_encode($enctoken):'')));
				$bookingTranInfo->appendChild($ccinfo);
				//Manali - 1.0.48.53 - 7th March 2016 - END
								
				$ccno = $this->xmlDoc->createElement("CCNo");
				$ccno->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($cc_no)));
				$bookingTranInfo->appendChild($ccno);
						
				$cctype = $this->xmlDoc->createElement("CCType");
				$cctype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($cc_type)));
				$bookingTranInfo->appendChild($cctype);
						
				$ccexpirydate = $this->xmlDoc->createElement("CCExpiryDate");
				$ccexpirydate->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($cc_expirydate)));
				$bookingTranInfo->appendChild($ccexpirydate);
						
				$ccholdersname = $this->xmlDoc->createElement("CardHoldersName");
				$ccholdersname->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($cc_holdersname)));
				$bookingTranInfo->appendChild($ccholdersname);						
				
				//Sushma Rana - CEN-1418, Send CVC detail to ROOMCLOUD - Start
				if(isset($pmsname) && $pmsname == 'roomcloud' && $cvc != '')
				{
					$cvc_detail= $this->xmlDoc->createElement("cvc");											
					$cvc_detail->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($cvc)));
					$bookingTranInfo->appendChild($cvc_detail);
					//Manali - 1.0.36.41 - 30 Aug 2013 - END
				}
				//CEN-1418, Send CVC detail to ROOMCLOUD - end
				
                foreach ($extraChargeInfo as $extracharge)
				{
                    $extraCharge = $this->xmlDoc->createElement("ExtraCharge");

                    $chargedate = $this->xmlDoc->createElement("ChargeDate");
                    $chargedate->appendChild($this->xmlDoc->createTextNode($extracharge['chargeDate']));
                    $extraCharge->appendChild($chargedate);

                    $chargename = $this->xmlDoc->createElement("ChargeName");
                    $chargename->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($extracharge['chargeName'])));
                    $extraCharge->appendChild($chargename);

                    $remark = $this->xmlDoc->createElement("Remark");					
                    $remark->appendChild($this->xmlDoc->createTextNode(''));
                    $extraCharge->appendChild($remark);

                    $qty = $this->xmlDoc->createElement("Quantity");
                    $qty->appendChild($this->xmlDoc->createTextNode($extracharge['qty']));
                    $extraCharge->appendChild($qty);

                    $charge = $this->xmlDoc->createElement("Amount");
                    $charge->appendChild($this->xmlDoc->createTextNode(number_format(floatval($extracharge['netcharge']), 2, '.', '')));
                    $extraCharge->appendChild($charge);

                    $bookingTranInfo->appendChild($extraCharge);
                }
                foreach ($dayWiseBookingInfo as $rental)
				{

                    $rentalInfo = $this->xmlDoc->createElement("RentalInfo");

                    $effectdate = $this->xmlDoc->createElement("EffectiveDate");
                    $effectdate->appendChild($this->xmlDoc->createTextNode($rental['rentaldate']));
                    $rentalInfo->appendChild($effectdate);

                    $packageCode = $this->xmlDoc->createElement("PackageCode");
                    $packageCode->appendChild($this->xmlDoc->createTextNode($rental['ratetypeunkid']));
                    $rentalInfo->appendChild($packageCode);

                    $packageName = $this->xmlDoc->createElement("PackageName");
                    $packageName->appendChild($this->xmlDoc->createTextNode($rental['ratetype']));
                    $rentalInfo->appendChild($packageName);

                    $roomtypeid = $this->xmlDoc->createElement("RoomTypeCode");
                    $roomtypeid->appendChild($this->xmlDoc->createTextNode($rental['roomtypeunkid']));
                    $rentalInfo->appendChild($roomtypeid);

                    $roomtype = $this->xmlDoc->createElement("RoomTypeName");
                    $roomtype->appendChild($this->xmlDoc->createTextNode(($rental['roomtype'])));
                    $rentalInfo->appendChild($roomtype);

                    $adult = $this->xmlDoc->createElement("Adult");
                    $adult->appendChild($this->xmlDoc->createTextNode($rental['adult']));
                    $rentalInfo->appendChild($adult);

                    $child = $this->xmlDoc->createElement("Child");
                    $child->appendChild($this->xmlDoc->createTextNode($rental['child']));
                    $rentalInfo->appendChild($child);

                    $rent = $this->xmlDoc->createElement("Rent");
                    $rent->appendChild($this->xmlDoc->createTextNode(number_format(floatval($rental['netRate']), 2, '.', '')));
                    $rentalInfo->appendChild($rent);

                    $discount = $this->xmlDoc->createElement("Discount");
                    $discount->appendChild($this->xmlDoc->createTextNode(number_format(floatval($this->getDiscountAmount($rental['foliodetailunkid'])), 2, '.', '')));
                    $rentalInfo->appendChild($discount);

                    $bookingTranInfo->appendChild($rentalInfo);
                }
				//Manali - 1.0.33.38 - 11 May 2013 - START
				//Purpose : Enhancement - Added Sharer Information
				$sharerInfo = array();	
				$sharerInfo =  $this->getSharerInfo($booking['tranunkid']);
				
				foreach ($sharerInfo as $sharer)
				{
                    $sharerElement = $this->xmlDoc->createElement("Sharer");

					$salutation = $this->xmlDoc->createElement("Salutation");
					$salutation->appendChild($this->xmlDoc->createTextNode($sharer['salutation']));
					$sharerElement->appendChild($salutation);
	
					$master_guest = explode(" ",$sharer['name']);
					$first_name ='';
					$last_name = '';
					$first_name = $master_guest[0];
					unset($master_guest[0]);
					$last_name = implode(' ',$master_guest);                    
                    
					$fname = $this->xmlDoc->createElement("FirstName");
					$fname->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($first_name)));
					$sharerElement->appendChild($fname);
	
					$lname = $this->xmlDoc->createElement("LastName");
					$lname->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($last_name)));
					$sharerElement->appendChild($lname);
					
					$gendertag = $this->xmlDoc->createElement("Gender");
					if($sharer['salutation']!='')
					{
						if($sharer['salutation']=='MRS' || $sharer['salutation']=='MS' || $sharer['salutation']=='MAM')
							$gender="Female";
						else
							$gender="Male";
					}
					else
						$gender="Other";
					$gendertag->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['gender']!=''?$sharer['gender']:$gender)));
					$sharerElement->appendChild($gendertag);
					
					$dob = $this->xmlDoc->createElement("DateOfBirth");
					$dob->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['birthdate'])));
					$sharerElement->appendChild($dob);
					
					$sdob = $this->xmlDoc->createElement("SpouseDateOfBirth");
					$sdob->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['spousebirthdate'])));
					$sharerElement->appendChild($sdob);
					
					$anniversary = $this->xmlDoc->createElement("WeddingAnniversary");
					$anniversary->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['anniversary'])));
					$sharerElement->appendChild($anniversary);
					
					$nationality = $this->xmlDoc->createElement("Nationality");

					if(isset($pmsname) && $pmsname == 'Lemon' && $sharer['nationality'] != '')
					{
						$nationalityISOcode = $this->GetCountryISOCode($sharer['nationality']);
						$this->log->logIt("nationalityISOcode >>".json_encode($nationalityISOcode,true)); 					
						$nationality->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($nationalityISOcode['isocode'])));
					}
					else{
						$nationality->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['nationality'])));
					}
					
					$sharerElement->appendChild($nationality);
					
					$address = $this->xmlDoc->createElement("Address");
					$address->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['address'])));
					$sharerElement->appendChild($address);
	
					$city = $this->xmlDoc->createElement("City");
					$city->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['city'])));
					$sharerElement->appendChild($city);
	
					$state = $this->xmlDoc->createElement("State");
					$state->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['state'])));
					$sharerElement->appendChild($state);
	
					$country = $this->xmlDoc->createElement("Country");
					if(isset($pmsname) && $pmsname == 'Lemon' && $sharer['country'] != '')
					{
						$countryISOcode = $this->GetCountryISOCode($sharer['country']);
						$this->log->logIt("countryISOcode >>".json_encode($countryISOcode,true)); 					
						$country->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($countryISOcode['isocode'])));
					}
					else{
						$country->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['country'])));
					}
					
					$sharerElement->appendChild($country);
	
					$zipcode = $this->xmlDoc->createElement("Zipcode");
					$zipcode->appendChild($this->xmlDoc->createTextNode($sharer['zipcode']));
					$sharerElement->appendChild($zipcode);
	
					$phone = $this->xmlDoc->createElement("Phone");
					$phone->appendChild($this->xmlDoc->createTextNode($sharer['phone']));
					$sharerElement->appendChild($phone);
					
					$mobile = $this->xmlDoc->createElement("Mobile");
					$mobile->appendChild($this->xmlDoc->createTextNode($sharer['mobile']));
					$sharerElement->appendChild($mobile);
					
					$fax = $this->xmlDoc->createElement("Fax");
					$fax->appendChild($this->xmlDoc->createTextNode($sharer['fax']));
					$sharerElement->appendChild($fax);			
									
					$email = $this->xmlDoc->createElement("Email");
					$email->appendChild($this->xmlDoc->createTextNode($sharer['email']));
					$sharerElement->appendChild($email);
					
					$identitytype = $this->xmlDoc->createElement("IdentiyType");
					$identitytype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['identitytype'])));
					$sharerElement->appendChild($identitytype);
					
					$identityno = $this->xmlDoc->createElement("IdentityNo");
					$identityno->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['identityno'])));
					$sharerElement->appendChild($identityno);
					
					$expdate = $this->xmlDoc->createElement("ExpiryDate");
					$expdate->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['expirydate'])));
					$sharerElement->appendChild($expdate);

                    $bookingTranInfo->appendChild($sharerElement);
                }
				//Manali - 1.0.33.38 - 11 May 2013 - END
                $bookedByInfo->appendChild($bookingTranInfo);
            }           
            $reservation->appendChild($bookedByInfo);
            
            // $reservationxml1=$reservation;
            // $this->log->logIt("Reservation========>".$reservation);
            //Dharti 28-10-2017 purpose:- Simple load string getting error.
            //$reservationxml = preg_replace('/& /', 'and ', $reservationxml1);
            $validXML = simplexml_load_string($this->xmlDoc->saveXML($reservation));
			//$this->log->logIt("validXML child append".$validXML);

            if ($validXML === false)
			{
                $this->generateInvalidReservationXML($res['tranunkid']);
                return false;
            }
			else
			{
                $this->xmlReservation->appendChild($reservation);
                return true;
            }
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateReservationXML" . "-" . $e);
            $this->handleException($e);
        }
    }

    private function getDiscountAmount($detailunkid) {
        $result = 0;
        try {
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getDiscountAmount");
            $dao = new dao();
            $strSql = " SELECT SUM(fasfoliodetail.baseamount) as totaldiscount FROM cfdiscount ";
            $strSql .= " 	INNER JOIN fasfoliodetail ON fasfoliodetail.masterunkid=cfdiscount.lnkmasterunkid ";
            $strSql .= " 	INNER JOIN fasmaster ON fasmaster.masterunkid=fasfoliodetail.masterunkid ";
            $strSql .= " 	INNER JOIN fasmastertype ON fasmaster.mastertypeunkid=fasmastertype.mastertypeunkid ";
            $strSql .= " 	WHERE fasmastertype.type='Discount' AND fasmaster.hotel_code=:hotel_code ";
            $strSql .= " 	AND parentid=:detailunkid AND fasfoliodetail.isvoid_cancel_noshow_unconfirmed=0";
            $dao->initCommand($strSql);
            $dao->addParameter(":detailunkid", $detailunkid);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $result = $dao->executeRow();
            $result = $result['totaldiscount'];
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getDiscountAmount" . "-" . $e);
            $this->handleException($e);
        }
        return $result;
    }

    private function generateCancelledBookingsXML($cancelled_bookings)
	{
        try
		{
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateCancelledBookingsXML");
            $transfered_bookings = array();
            foreach ($cancelled_bookings as $cbooking)
			{
                $cancelreservation = $this->xmlDoc->createElement("CancelReservation");
		
				//Manali - 1.0.46.51 - 27 Aug 2015 - START
				//Purpose : Kept Location Id tag, passing hotel code in Location Id which is used in our push model
				$locid = $this->xmlDoc->createElement("LocationId");
				$locid->appendChild($this->xmlDoc->createTextNode($this->hotelcode)); 
				$cancelreservation->appendChild($locid);
				//Manali - 1.0.46.51 - 27 Aug 2015 - END
		
                $uniqueid = $this->xmlDoc->createElement("UniqueID");
                $uniqueid->appendChild($this->xmlDoc->createTextNode($cbooking['reservationno']));
                $cancelreservation->appendChild($uniqueid);
                $remark = $this->xmlDoc->createElement("Remark");
                if (trim($cbooking['fremark']) != '')
                    $can_remark = $cbooking['fremark'];
                else if (trim($cbooking['cremark']) != '')
                    $can_remark = $cbooking['cremark'];
                else
                    $can_remark='';				
                $remark->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($can_remark)));
                $cancelreservation->appendChild($remark);
				
				$voucherno = $this->xmlDoc->createElement("VoucherNo");
                $voucherno->appendChild($this->xmlDoc->createTextNode($cbooking['voucherno']));
                $cancelreservation->appendChild($voucherno);
				
                $this->xmlReservation->appendChild($cancelreservation);
                $transfered_bookings[] = $cbooking['reservationno'];
            }
            return $transfered_bookings;
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateCancelledBookingsXML" . "-" . $e);
            $this->handleException($e);
        }
    }

    private function generateInvalidReservationXML($tranunkid) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateInvalidReservationXML");
            $invalidXML = $this->xmlDoc->createElement("InvalidReservation");
            $uniqueid = $this->xmlDoc->createElement("UniqueID");
            $uniqueid->appendChild($this->xmlDoc->createTextNode($tranunkid));
            $invalidXML->appendChild($uniqueid);
            $remark = $this->xmlDoc->createElement("Remark");
            $remark->appendChild($this->xmlDoc->createTextNode('Invalid XML'));
            $invalidXML->appendChild($remark);
            $this->xmlReservation->appendChild($invalidXML);
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateInvalidReservationXML" . "-" . $e);
            $this->handleException($e);
        }
    }

    public function getAdminUser() {
        try {
            $adminuserunkid = '';
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getAdminUser");
            $strsql = " SELECT userunkid FROM cfuser WHERE hotel_code=:hotel_code AND username='pms' LIMIT 1; "; //Manali - 1.0.33.38 - 30 Apr 2013,Purpose : get PMS User
            $dao = new dao();
            $dao->initCommand($strsql);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $adminuserunkid = $dao->executeScalar();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getAdminUser" . "-" . $e);
            $this->handleException($e);
        }
        return $adminuserunkid;
    }

    private function getTranunkid($resno,$subresno='') 
	{
        try {
            $tranunkid = '';
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getTranunkid"."|".$resno."|".$subresno."|".$this->hotelcode);
            $strsql = " SELECT CONCAT(tranunkid,'-',IFNULL(isgroupowner,0),'-',IFNULL(groupunkid,0)) As tranunkid FROM fdtraninfo WHERE hotel_code=:hotel_code AND reservationno=:resno";
			
			if($subresno!='')
				$strsql.=" AND subreservationno=:subresno";
			
			$strsql.=" order by tranunkid ASC; ";
			
			if($subresno!='')
				$strsql.=" LIMIT 1";
            $dao = new dao();
            $dao->initCommand($strsql);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":resno", $resno);
			
			if($subresno!='')
				$dao->addParameter(":subresno", $subresno);
			
            $tranid_list = $dao->executeQuery();
			
			$i=0;
			foreach($tranid_list as $tranidlist)
			{
				if($i==0)
					$tranunkid = $tranidlist['tranunkid'];
				else
					$tranunkid = $tranunkid."|".$tranidlist['tranunkid'];
				$i++;
			}
			
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getTranunkid" . "-" . $e);
            $this->handleException($e);
        }
       return $tranunkid;
    }

    private function insertIntoAuditTrail($tranunkid, $operation, $description, $webflag='', $extuserunkid='') {
        $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "insertIntoAuditTrail");
        try {
            $dao = new dao();
			
			//Manali - 1.0.38.43 - 01 Jan 2014 - START
			//Purpose : Fixed Bug - In case of group bookings, audittrails should be entered for whole group if subreservation no is not passed. 
			$tranid_list = explode("|",$tranunkid);
			
			foreach($tranid_list as $tranidlist)
			{	
				list($tranid,$isgroupowner,$groupunkid) = explode("-",$tranidlist);	
				$strSql = " INSERT INTO fdaudittrail (hotel_code,year_code,tranunkid,userunkid,operation,description,auditdatetime,visitorip) ";
				$strSql .= " VALUES (:hotel_code,:year_code,:tranunkid,:userunkid,:operation,:description,:auditdatetime,:visitorip)";
	
				$dao->initCommand($strSql);
				$dao->addParameter(":year_code", date('Y'));
				$dao->addParameter(":hotel_code", $this->hotelcode);
				$dao->addParameter(":userunkid", $extuserunkid);
				$dao->addParameter(":tranunkid", $tranid);
				$dao->addParameter(":operation", $operation);
				$dao->addParameter(":description", $description);
				$dao->addParameter(":auditdatetime", $this->getLocalDateTime());
                //$dao->addParameter(":auditdatetime", util::getLocalDateTime());
				$dao->addParameter(":visitorip",$this->VisitorIP());	
				$dao->executeNonQuery();
                //$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "insertIntoAuditTraiTime"."-".$this->getLocalDateTime());//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
           		//$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "insertIntoAuditTrail"."-".$strSql);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			}
			//Manali - 1.0.38.43 - 01 Jan 2014 - END
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "insertIntoAuditTrail" . "-" . $e);
            $this->handleException($e);
        }
    }

	//Pinal - 1.0.53.61 - 20 November 2017 - START
	//Purpose : Day light saving mode issue.
    /*private function getLocalDateTime() {
        try {
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getLocalDateTime");
            $timediff = explode(":", $this->readConfigParameter("DisTimeZone"));
            $localdatetime = gmdate("Y-m-d H:i:s", mktime(date("H") + ($timediff[0]), date("i") + ($timediff[1]), date("s"), date("n"), date("j"), date("Y")));
            return $localdatetime;
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getLocalDateTime - " . $e);
            $this->handleException($e);
        }
    }*/
	private function getLocalDateTime()
	{
		$localdatetime=$this->getLocalDateTimeDST('',"Y-m-d H:i:s");
		return $localdatetime;
	}
	public function getLocalDateTimeDST($hotel_code='',$datetimeformat='')
	{
		try
		{
			$timezone=$this->readConfigParameter("DisTimeZoneKey");
			
			$istimezonevalid=in_array($timezone, timezone_identifiers_list());
				
			$isdaylightsaving = 0;
			$default_timezone=date_default_timezone_get();
			
			if($istimezonevalid)
			{
				date_default_timezone_set($timezone);
				$isdaylightsaving = date("I");
			}
			
			if($isdaylightsaving)
			{
				$localdatetime = date($datetimeformat);
			}
			else
			{
				$timezoneoffset=$this->readConfigParameter("DisTimeZone");
				$timediff=explode(":",$timezoneoffset);
				$localdatetime = gmdate($datetimeformat,mktime(date("H")+($timediff[0]),date("i")+($timediff[1]),date("s"),date("n"),date("j"),date("Y")));
			}
			
			if($istimezonevalid)
				date_default_timezone_set($default_timezone);
			return $localdatetime;
		}
		catch(Exception $e)
		{
			$log->logIt("getLocalDateTimeDST Error : $e");
			return $localdatetime = gmdate("Y-m-d H:i:s",mktime(date("H"),date("i"),date("s"),date("n"),date("j"),date("Y")));
		}
	}
	//Pinal - 1.0.53.61 - 20 November 2017 - END
    
    public function readConfigParameter($key) {
        try {
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "readConfigParameter");
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
	public function isTransferAllowed(){
	  try {
	  		$flag=false;
            #$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "isTransferAllowed");
			if($this->readConfigParameter("PMSTransferBookings")==1)
				$flag=true;
		} catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isTransferAllowed - " . $e);
            $this->handleException($e);
        }
		return $flag;
		
	}

   private function xmlEscape($string) {
        //$string = str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $string);
        return $string;
    }

    private function handleException($e) {
        try {
			
			if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 10 Sep 2019 - Return flag for json format [CEN-1086]
			{
				return $this->generateGeneralErrorJson('500', 'Error occured during processing.',array(),1);
			}
			else
			{
				#$this->log->logIt(get_class($this) . "-" . "handleException");
				$this->generateGeneralErrorMsg('500', "Error occured during processing");
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 12 jul 2019 - Return flag[CEN-1165]
				{
					return $this->xmlDoc->saveXML();
				}
				echo $this->xmlDoc->saveXML();
				exit;
			}
        } catch (Exception $e) {
            $this->log->logIt(get_class($this) . "-" . "handleException" . "-" . $e);
            exit;
        }
    }
	#Satish - 1.0.27.32 - 01 Aug 2012 - Start
	private function getTotalPayment($tranunkid){
		try{
		 	#$this->log->logIt(get_class($this) . "-" . "getTotalPayment");
		 	$dao = new dao();
            $strSql =" SELECT SUM(FD.baseamount)*(-1) AS total_payment FROM fasfoliodetail AS FD";
			$strSql.=" INNER JOIN fasmaster AS FAS ON FAS.masterunkid=FD.masterunkid";
			$strSql.=" INNER JOIN fasfoliomaster AS FM ON FM.foliounkid=FD.foliounkid AND FM.foliotypeunkid=1 AND FM.hotel_code=:hotel_code ";
			$strSql.=" WHERE FD.isvoid_cancel_noshow_unconfirmed=0 AND  FD.hotel_code=:hotel_code AND FM.lnktranunkid=:tranunkid AND FAS.mastertypeunkid IN (2,6,9)";
			$dao->initCommand($strSql);
			//$this->log->logIt($strSql);
			/*$this->log->logIt('Hotel Code : '.$this->hotelcode);
			$this->log->logIt('Hotel Code : '.$tranunkid);			*/
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":tranunkid", $tranunkid);
          //  $dao->addParameter(":mastertypeunkid",'2,6,9');			
            $result = $dao->executeRow();
            $result = $result['total_payment'];
		}
		catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getTotalPayment" . "-" . $e);
            $this->handleException($e);
		}
		return $result;
	}
	#Satish - 1.0.27.32 - 01 Aug 2012 - End
	
	// HRK - 1.0.46.51 - 14 Aug 2015 - START
	// Purpose : Push booking logic, Added one optional parameter
	//Manali - 1.0.37.42 - 16 Oct 2013 - START
	//Purpose : Enhancement - Get new/cancelled bookings count if exists on web - Developed on request of Jitubhai
	public function checkBookingsExists($getcount=0)
	{
		try
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . $this->module . "-" . "checkBookingsExists");
			$dao = new dao();
			
			//Manali - 1.0.46.51 - 03 July 2015 - START
			//Purpose : Applied checking for past date reservations not to send in PMS
			$workingdate=date ("Y-m-d", strtotime("-1 day", strtotime($this->readConfigParameter('todayDate'))));
			
			//CEN-1733, Changes for gureetee detail for HOLD booking - Start
             //$hotelArray = array("4996","6300");
            if($this->readConfigParameter("ThirdPartyPMS") == 'familyhotel')
			{
				$pmsbookingfunction = new pmsbookingfunctons();
				$pmsbookingfunction->hotelcode = $this->hotelcode;
				$holdbookingid = $pmsbookingfunction->getholdgaurantee();
		
				$strSql =" SELECT IFNULL(SUM(tbl.cnt),0) As count".
					 " FROM ".
					 "	( ".
					 "		SELECT COUNT(tranunkid) AS cnt FROM fdtraninfo ".
					 "		WHERE hotel_code=:hotel_code AND ((FDTI.webrateunkid IS NOT NULL AND fdtraninfo.webinventoryunkid IS NOT NULL AND fdtraninfo.reservationgauranteeunkid IN('".$holdbookingid."')) OR (fdtraninfo.webinventoryunkid IS NULL OR fdtraninfo.webrateunkid IS NULL)) AND isposted=0 ".
					 "		AND FIND_IN_SET(statusunkid,'4,10,13') AND CAST(fdtraninfo.arrivaldatetime As DATE)>='".$workingdate."' GROUP BY reservationno ".
					 "		UNION ALL ".
					 "		SELECT COUNT(tranunkid) AS cnt FROM fdtraninfo ".
					 "		WHERE hotel_code=:hotel_code AND IFNULL(transactionflag,0)<>0 AND isposted=0 AND FIND_IN_SET(statusunkid,'6') ".
					 "	 AND CAST(fdtraninfo.arrivaldatetime As DATE)>='".$workingdate."' ) AS tbl";
				        
			}
			//CEN-1733 - End
			else
			{
				$strSql =" SELECT IFNULL(SUM(tbl.cnt),0) As count".
					 " FROM ".
					 "	( ".
					 "		SELECT COUNT(tranunkid) AS cnt FROM fdtraninfo ".
					 "		WHERE hotel_code=:hotel_code AND IFNULL(transactionflag,0)<>0 AND isposted=0 ".
					 "		AND FIND_IN_SET(statusunkid,'4,10,13') AND CAST(fdtraninfo.arrivaldatetime As DATE)>='".$workingdate."' GROUP BY reservationno ".
					 "		UNION ALL ".
					 "		SELECT COUNT(tranunkid) AS cnt FROM fdtraninfo ".
					 "		WHERE hotel_code=:hotel_code AND IFNULL(transactionflag,0)<>0 AND isposted=0 AND FIND_IN_SET(statusunkid,'6') ".
					 "	 AND CAST(fdtraninfo.arrivaldatetime As DATE)>='".$workingdate."' ) AS tbl";
			}
			$dao->initCommand($strSql);
			//$this->log->logIt($strSql);
			//Manali - 1.0.46.51 - 03 July 2015 - END
			$dao->addParameter(":hotel_code", $this->hotelcode);
				    
			$result = $dao->executeRow();
			$count = $result['count'];
	    			
			$success = $this->xmlDoc->createElement("Success");
			
			if($count==0)
			{
			        //Manali - 1.0.46.51 - 03 July 2015 - START
				//Purpose : Provided 0 count in case of no bookings to fetch as per requested by JJ
				$count_msg = $this->xmlDoc->createElement("Count");
				$count_msg->appendChild($this->xmlDoc->createTextNode(0));				
				$success->appendChild($count_msg);
				//Manali - 1.0.46.51 - 03 July 2015 - END
				
				$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
				$succ_msg->appendChild($this->xmlDoc->createTextNode('No booking(s) to fetch.'));
			}
			else
			{
				$succ_msg = $this->xmlDoc->createElement("Count");
				$succ_msg->appendChild($this->xmlDoc->createTextNode($count));				
			}
			$success->appendChild($succ_msg);
			$this->xmlRoot->appendChild($success);			
			$str = $this->xmlDoc->saveXML();
			// HRK - 1.0.46.51 - 14 Aug 2015 - START
			// Purpose : Push booking logic
			if($getcount)
			{
				return $count;
			}
			else
			{
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 12 jul 2019 - Return flag[CEN-1165]
				{
					return $str;
				}
				echo $str;
				return;			
			}
			// HRK - 1.0.46.51 - 14 Aug 2015 - END
		}
		catch (Exception $e) 
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkBookingsExists" . "-" . $e);
            $this->handleException($e);
		}
	}
	//Manali - 1.0.37.42 - 16 Oct 2013 - END

	//Charul - 15 July 2014 - START
	//Purpose : Enhancement - To Get Affilate Information	
	private function getAffiliateInfo($affiliateunkid)
	{
	    try {
		$result = NULL;
		#$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getAffiliateInfo");
		$strSql ='';
		
		$strSql = 'Select affiliate_code,name from cfaffiliate where hotel_code=:hotel_code and affiliateunkid=:affiliateunkid';
		
		$dao = new dao();
		$dao->initCommand($strSql);
		
		$dao->addParameter(":hotel_code", $this->hotelcode);
		$dao->addParameter(":affiliateunkid", $affiliateunkid);
		
		$result = $dao->executeQuery();
	    } catch (Exception $e) {
		$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getAffiliateInfo - " . $e);
		$this->handleException($e);
	    }
	    return $result;
    }
    //Charul - 15 July 2014 - END
        
    private function VisitorIP()
    { 
	    if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
		    $TheIp=$_SERVER['HTTP_X_FORWARDED_FOR'];
	    else 
		    $TheIp=$_SERVER['REMOTE_ADDR'];

	    $iparr = explode(',',$TheIp);
	    
	    if(count($iparr)>0)
	    {
		    $TheIp=trim($iparr[0]);
	    }
	    
	    return trim($TheIp);
    }
	
	//Anil Ahir - 1.0.51.56 - 24 Nov 2016 - START
	//Purpose : To get 3-char Country ISO code
	public function GetCountryISOCode($countryname)
	{
		$result = '';
		
		try
		{
			if($countryname != '')
			{
                //Add Dharti 22-11-2017 START purpose: Mismatch of guest's country and system defined country in booking
				$strSql = 'SELECT alias,isocode FROM cfcountry WHERE country_name=:country_name'; 
				$dao = new dao();
				$dao->initCommand($strSql);
				$dao->addParameter(":country_name", $countryname);
				
				$result = $dao->executeRow();
			}
	    }
		catch(Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - GetCountryISOCode - " . $e);
			$this->handleException($e);
	    }
		
	    return $result;
	}
    //Add Dharti 22-11-2017 END purpose: countryname miss match issue
	//Anil Ahir - 1.0.51.56 - 24 Nov 2016 - END
	
	//Anil Ahir - 1.0.51.56 - 24 Nov 2016 - START
	//Purpose : To get property currency code
	public function getPropertyCurrencyCode()
	{
		$result = '';
		
		try
		{
			$strSql = 'SELECT currency_code FROM cfexchangerate WHERE hotel_code=:hotel_code AND isbasecurrency=1 AND isactive=1';
			$dao = new dao();
			$dao->initCommand($strSql);
			$dao->addParameter(":hotel_code", $this->hotelcode);
			
			$result = $dao->executeRow();
			$result = $result['currency_code'];
		}
		catch(Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getPropertyCurrencyCode - " . $e);
			$this->handleException($e);
		}
		
		return $result;
	}
	//Anil Ahir - 1.0.51.56 - 24 Nov 2016 - END
	
    //Sushma - 1.0.53.61 - 02 Nov 2017 - Start
	Public function getcommision($tranunkid){
		try{
            
		 	$this->log->logIt(get_class($this) . "-" . "getcommision");
		 	$dao = new dao();
            
            $strSql="SELECT commision FROM fdtraninfo WHERE hotel_code=:hotel_code and tranunkid=:tranunkid";
            $dao->initCommand($strSql);
			//$this->log->logIt($strSql);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt('Hotel Code : '.$this->hotelcode);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt('Tranunid : '.$tranunkid);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":tranunkid", $tranunkid);          		
            $result = $dao->executeRow();
            $result = $result['commision'];
		}
		catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getcommision" . "-" . $e);
            $this->handleException($e);
		}
		return $result;
	}
	//Sushma - 1.0.53.61 - 02 Nov 2017 - End
	//Mayur Kannaujia - 1.0.53.61 - 09-03-2018 - Purpose: If Booking comes from MMT then source/CityLedger should be MakemytripXMl not goibibo - START
	
	public function findSetChannelCityLedgerIfExists($channel_source)
	{
		try
		{
			$this->log->logIt(get_class($this)."-"."findSetChannelCityLedgerIfExists");
			$dao = new dao();
			$strSql="SELECT business_name FROM ".dbtable::Contact." WHERE hotel_code=:hotel_code AND LOWER(business_name) LIKE LOWER(:channelsourcename) AND contacttypeunkid=:contacttypeunkid LIMIT 1";
			$dao->initCommand($strSql);
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$dao->addParameter(":channelsourcename",'%'.$channel_source.'%');
			$dao->addParameter(":contacttypeunkid",'TRAVELAGENT');
			$data=$dao->executeQuery();
			$cityledgeraccount = '';
			if(count($data)>0)
			{
				$cityledgeraccount=isset($data[0]['business_name'])?$data[0]['business_name']:'';
				$this->log->logIt("cityledgeraccount inside function===>".$cityledgeraccount);
				return $cityledgeraccount;
			}
			else
			{
				$this->log->logIt("cityledgeraccount else ===> No CityLedger Account Found..");
			}
		}
		catch(Exception $e)
		{
			$this->log->logIt(get_class($this)."-"."findSetChannelCityLedgerIfExists"."-".$e);
			return false;
		}	
	}
	
	//Mayur Kannaujia - 1.0.53.61 - 09-03-2018 - Purpose: If Booking comes from MMT then source/CityLedger should be MakemytripXMl not goibibo - END
    
    
    
	//Sushma - 14th Aug 2017 - start
	//Sushma - 21st august 2017 - Comment below code method transfer to masterdao_2
	/*
	public function ccexpirydate($cc_expirydate_ori)
	{
		$this->log->logIt("expiry date ====>".$cc_expirydate_ori);
		$ccarray = array();
		$finaldate = '';
		try
		{
			if (strpos($cc_expirydate_ori, '-') != false )
			{
			    list($str1, $str2)=  explode("-",$cc_expirydate_ori);
				
				if(strlen($str1) == 4)
				{
					list($year, $month)=  explode("-",$cc_expirydate_ori);				
					$this->log->logIt("Year---> ".$year);
					$this->log->logIt("Month---> ".$month);
					$lastdate = $this->lastOfMonth($month,$year);
					$this->log->logIt("Lastdate---> ".$lastdate);
					$finaldate = $year . '-' .$month . '-' . $lastdate;
					$this->log->logIt("Final date======>".$finaldate);
					//$final_date = array($lastdate, $month, $year);
					//$this->log->logIt("Lastdate---> ".print_r($final_date,true));
					//$final_date1 = implode("-", array($lastdate, $month, $year));
					//$this->log->logIt("Final date======>".$final_date1);				
					//$finalexpdate = substr($exp, 0, 2);
					//$finalexpyear = substr($exp, 2, 5);			
					//$finaldate = $finalexpyear."-". $finalexpdate;
				}
				else
				{
					list($month, $year)=  explode("-",$cc_expirydate_ori);				
					$this->log->logIt("Year---> ".$year);
					$this->log->logIt("Month---> ".$month);
					$lastdate = $this->lastOfMonth($month,$year);
					$this->log->logIt("Lastdate---> ".$lastdate);
					$finaldate = $lastdate . '-' .$month . '-' . $year;
					$this->log->logIt("Final date======>".$finaldate);					
				}
			}
			else if(strpos($cc_expirydate_ori, '/') != false )
			{
				list($string1, $string2)=  explode("/",$cc_expirydate_ori);
				if(strlen($string1) == 2)
				{
					list($month, $year)=  explode("/",$cc_expirydate_ori);
					$this->log->logIt("Year---> ".$year);
					$this->log->logIt("Month---> ".$month);
					$lastdate = $this->lastOfMonth($month,$year);
					$this->log->logIt("Lastdate---> ".$lastdate);
					$finaldate = $lastdate . '/' .$month . '/' . $year;
					$this->log->logIt("Final date======>".$finaldate);
				}
				else
				{
					list($year, $month)=  explode("/",$cc_expirydate_ori);
					$this->log->logIt("Year---> ".$year);
					$this->log->logIt("Month---> ".$month);
					$lastdate = $this->lastOfMonth($month,$year);
					$this->log->logIt("Lastdate---> ".$lastdate);
					$finaldate = $year . '/' .$month . '/' . $lastdate;
					$this->log->logIt("Final date======>".$finaldate);
				}
				
			}
			else if(strpos($cc_expirydate_ori, '/') == false && strpos($cc_expirydate_ori, '-') == false)
			{
				$this->log->logIt("CCdate else part---> ".$cc_expirydate_ori);
				if(strlen($cc_expirydate_ori) > 4)
				{
					$month = substr($cc_expirydate_ori, 0, 2);
					$year = substr($cc_expirydate_ori, 2, 5);
					$this->log->logIt("Year---> ".$year);
					$this->log->logIt("Month---> ".$month);
					$lastdate = $this->lastOfMonth($month,$year);
					$this->log->logIt("Lastdate---> ".$lastdate);
					$finaldate = $lastdate.$month.$year;
					$this->log->logIt("Final date======>".$finaldate);
				}
				else
				{
					$month = substr($cc_expirydate_ori, 0, 2);
					$year = substr($cc_expirydate_ori, 2, 4);	
					$this->log->logIt("Year---> ".$year);
					$this->log->logIt("Month---> ".$month);
					$lastdate = $this->lastOfMonth($month,$year);
					$this->log->logIt("Lastdate---> ".$lastdate);
					$finaldate = $lastdate.$month.$year;
					$this->log->logIt("Final date======>".$finaldate);
				}
			}
			else
			{
				$this->log->logIt("CCdate else part---> ".$cc_expirydate_ori);
				$finaldate = $cc_expirydate_ori;
			}
			
		}
		catch(Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getPropertyCurrencyCode - " . $e);
			$this->handleException($e);
		}
		return $finaldate;		
	}
	function lastOfMonth($month,$year) 
	{
		return date("d", strtotime('-1 second',strtotime('+1 month',strtotime($month.'/01/'.$year.' 00:00:00'))));
	}
	*/
    //Akshay Start : 23 feb 2018
	//purpose : Made this function for send unposetd notification to hotelier 
	Public function NotPostedNotificationMail($BookingArray,$HotelCode,$reason,$internalmail='') //Dharti 2018-09-17 Add  internalmail
    {
		//$this->log->logIt("hotel code >>".$HotelCode." -- Booking ids >>".json_encode($BookingArray,true) ." -- Reason >>".$reason);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
        
		try 
		{
            //Dharti Savaliya 2018-09-18 - START Purpose :- send mail to booking not transfer
			$this->log->logIt("Hotel_" . $this->hotelcode . "-" . "sendNotificationEmailToHotelier");
			$subject="Booking Reference Number {request_number} - TRANSFER TO PMS FAILED, {hotelname}";
			$body='  <p style="font-family:Verdana, Arial, Helvetica, sans-serif;font-size:13px;color:#000;">Dear {name},</p>'.	         '	<p style="font-size: 13px;line-height:26px;color:#000;"> <b> Hotel Name </b> : {hotelname}<br/>'.		  
		  ' <b> Reason </b> : {reason} <br> <b> Booking reference no </b> : {request_number} </br>'.
          ' <br> <b> Pms reference no </b> :  </br>'.
		  '  <br> <b> Note </b> : Please take needful action on above booking. </br>'.
		  '  </p>';
			//Dharti Savaliya 2018-09-18 - END Purpose :- send mail to booking not transfer  
			//Get Hotel Details
			$dao = new dao();
			$strSql="SELECT client.name as ownername,cfhotel.*,country.country_name as country".
				" FROM saasconfig.sysclient as client ".
				" INNER JOIN saasconfig.syshotel as hotel ON hotel.clientunkid=client.clientunkid ".
				" INNER JOIN cfhotel as cfhotel ON cfhotel.hotel_code=hotel.hotel_code ".
				" LEFT JOIN cfcountry as country ON country.countryunkid=cfhotel.country ".					
				" WHERE client.isactive=1 AND hotel.isactive=1 AND hotel.hotel_code=".$this->hotelcode;		
			$dao->initCommand($strSql);	    
			$hotelinfo = $dao->executeRow();
			//$this->log->logIt("Hotelinfo >>".print_r($hotelinfo,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
           
			/*if($hotelinfo['logo']!='')
			{
			$propertylogo="https://saas.s3.amazonaws.com/uploads/".$hotelinfo['logo'];
			$body=util::replace_email_var("{imgsrc}","<img src=".$propertylogo." border='0' alt='Logo' />",$body);	
			}
			else*/ // Sanjay Waman - 17 Dec 2018 - Rempve Logo from Mail
				$body=util::replace_email_var("{imgsrc}"," ",$body);
			$TempBookignId = str_replace(array( '{', '}','"'), '', json_encode($BookingArray));
           
           
            $subject=util::replace_email_var("{hotelname}",$hotelinfo['name'],$subject);
            $subject=util::replace_email_var("{request_number}",$TempBookignId,$subject);
			//$subject=util::replace_email_var("{request_number}",$resno.($subresno!=''?"-".$subresno:''),$subject);
			$this->log->logIt("Subject".$subject);
			
			$body=util::replace_email_var("{request_number}",$TempBookignId,$body);
			$body=util::replace_email_var("{hotelname}",$hotelinfo['name'],$body);
			$body=util::replace_email_var("{owneremail}",$hotelinfo['email'],$body);
			$body=util::replace_email_var("{name}",$hotelinfo['ownername'],$body);	    
			$body=util::replace_email_var("{reason}",$reason,$body);
			
			$body = wordwrap($body, 50);
			$dao = new dao();
			$strSql="SELECT custom2,pmsdetail FROM saasconfig.syspmshotelinfo where hotel_code=".$this->hotelcode;		
			$dao->initCommand($strSql);	    
			$cc = $dao->executeRow();
            $ccemail = explode(",",$cc['custom2']); //Dharti 2018-10-16 - Multiple cc add 
            // $ccemail =array($cc['custom2']);
            $pmsname = $cc['pmsdetail'];
            //Dharti Savaliya 2018-09-18 - START Purpose :- send mail to booking not transfer 
            if(isset($internalmail) && $internalmail != '')
            {
                $toemail= $internalmail;
                
                //Hetal - 17th Dce 2018 - set "noreply@ezeetechnosys.com" replace of "centrix365@gmail.com" - Start
                $fromemail = 'noreply@ezeetechnosys.com';   
                //Hetal - 17th Dce 2018 - End
                              
                $fromname_arr = explode("@",$fromemail);                
                $fromname=$fromname_arr[0];                 
            }
            else{
                 
                $toemail=$hotelinfo['email'];
                
                //Hetal - 17th Dce 2018 - set "noreply@ezeetechnosys.com" replace of "centrix365@gmail.com" - Start
                $fromemail='noreply@ezeetechnosys.com';
                //Hetal - 17th Dce 2018 - End
                
                $fromname_arr = explode("@",$fromemail);
                $fromname=$fromname_arr[0];
            }
			//Dharti Savaliya 2018-09-18 - END Purpose :- send mail to booking not transfer 
			$ret = $this->sendSMTPMail($toemail,$subject,$body,$fromemail,$fromname,$ccemail);
             //$this->log->logIt("FromMail >> ".$fromemail ." -- ToMail >>".$toemail ." -- CCMail >>".print_r($ccemail,true) . " -- FromName >>".$fromname);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
             $msg = '';
			if($ret == 'false')
			{
                if(strtoupper(substr(PHP_OS,0,3)=='WIN')) 
                    $eol="\r\n";
                elseif (strtoupper(substr(PHP_OS,0,3)=='MAC')) 
                    $eol="\r";
                else
                    $eol="\n";
                    
                 // $this->log->logIt("Headers >> ".$headers);
                //Common Headers  
                $headers = $msg = '';
                $headers .= 'From: '.$fromemail.$eol;							
                //$headers .= 'Return-Path: absolute <ezeeabs365@gmail.com>'.$eol; #Return Path is actually receiver can reply on this mail, this is not required so commented code - Manali 
               if($ccemail !='')
               {
                    $headers .= 'cc:'.$ccemail[0].$eol;
               }
                //Boundry for marking the split & Multitype Headers
                $mime_boundary=md5(time());
                $headers .= 'MIME-Version: 1.0'.$eol;
                $headers .= "Content-Type: multipart/related; boundary=\"".$mime_boundary."\"".$eol;	    
                $msg .= "Content-Type: multipart/alternative".$eol;
            
                //HTML Version
                $msg .= "--".$mime_boundary.$eol;
                $msg .= "Content-Type: text/html; charset=utf-8".$eol;
                $msg .= "Content-Transfer-Encoding: 8bit\n\n"; // Change 8bit to 7 bit and also add \n\n because of html content issue.	    
                $msg .= $body;
                //$this->log->logIt("mail contain >> ".$msg);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                global $server;
                if($server!="local")
                    mail($toemail, $subject, $msg, $headers);
			}
		}
		catch(Exception $e)
		{
			$this->log->logIt($e);
			return array("status" => "-1","message" => "Exception in " . $this->module . "-" . "sendNotificationEmailToHotelier - ".$e);	
		}
		return $msg;
	}
	
	private function sendSMTPMail($to,$subject,$body,$fromemail,$fromname,$cc='',$bcc='')
    {
        try
        {
            $this->log->logIt("Hotel_" . $this->hotelcode . "-" . "sendSMTPMail");			
            $dao=new dao();
            $WebRunOnSES = $this->readParameter('WebRunOnSES');                           
            //if($WebRunOnSES==1)
            //{
                /* Nitesh - 31st Aug 2020 - START
                Purpose : commented old code [ABS-5419] */
				//$smtp_host = "tls://email-smtp.us-east-1.amazonaws.com";
				//$smtp_username = "AKIAJIRFGPH2INAQIKFA";
				//$smtp_password = "AlMjugXV4W/lfSxI9BdxBadFS8D/osZ5PhIClT5kJVBV";
				//$smtp_port = 465;
                //Nitesh - END
				$smtp_from_address = $fromemail;
				$smtp_from_name = $fromname;
					
				# $this->log->logIt("SMTP Details: ".$smtp_host."|".$smtp_username."|".$smtp_password."|".$smtp_port."|".$smtp_from_address);					
				//$this->log->logIt("SMTP Content From Email id >> ".$fromemail);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("SMTP Content From Email name >> ".$fromname);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("SMTP Content TO Email id >> ".$to);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("SMTP Content CC Email id >> ".print_r($cc,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				
				//if($cc!='')
				//    $this->log->logIt("CC Emails: ".implode(",",$cc));              
				//if($bcc!='')
				//    $this->log->logIt("Bcc Emails: ".implode(",",$bcc));
				
				$mail = new PHPMailerV2();	
				$mail->IsSMTP(true);            // use SMTP			
				//$mail->SMTPDebug  = 2;        // enables SMTP debug information (for testing)
				// 1 = errors and messages
				// 2 = messages only
			
				$mail->SMTPAuth   = true;      // enable SMTP authentication
				/* Nitesh - 29th Aug 2020 - START
                Purpose : changes the SES functionality service version [ABS-5419] */
                require("/home/sesservicekey.php");
                $mail->Host       = $seshost; // Amazon SES server, note "tls://" protocol
				$mail->Port       = $sesport;      // set the SMTP port		
				$mail->Username   = $sesaccesskey;  // SES SMTP  username
				$mail->Password   = $sessecretkey;  // SES SMTP password
				//Nitesh - END				
				$from    = $smtp_from_address;            
				$mail->SetFrom($from, $smtp_from_name);				
				$to_address = explode(",",$to);
					
				for($i = 0; $i < count($to_address); $i++)
					$mail->AddAddress($to_address[$i], '');
					
				if(count($cc)>0 && $cc!='')
				{
					//for($i = 0; $i < count($cc); $i++)
					//$mail->AddCC($cc[$i], '');					
					$mail->AddCC($cc, '');
				}
					
				if(count($bcc)>0 && $bcc!='')
				{
					//for($i = 0; $i < count($bcc); $i++)
					//$mail->AddCC($cc[$i], '');
					$mail->AddCC($bcc, '');
				}
					
				$mail->Subject  = $subject;					
				$mail->MsgHTML($body);							
				$rec = $mail->Send();
				
				if(!$rec)
				{
					$this->log->logIt("Hotel_" . $this->hotelcode."-"."Mailer Error: >>" . $mail->ErrorInfo);                              
					return 'false';	
				}
				else
					{                              
					$this->log->logIt("Hotel_" . $this->hotelcode."-"."Message Sent >>  ");
					return 'true';	
				}	
            //}
            //else
            // return 'false';		
        }
        catch(phpmailerException $e)
        {			
            $this->log->logIt("Exception in " . $this->module . " - fetchRequest - " . $e->getMessage());
            return array("status" => "-1","message" => "Exception in " . $this->module . "-" . "isBookingExists - ".$e);  	
        }
    }
    //Akshay continue : 23 feb 2018. 
	 public function readParameter($key)
    {
        try
		{
            $dao = new dao();
            $strSql = "";
            $strSql .= "SELECT keyvalue FROM cfparameter";
            $strSql .= " WHERE hotel_code=:hotel_code ";
            $strSql .= " AND keyname = :keyname";
            $dao->initCommand($strSql);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":keyname", $key);
            $result= $dao->executeRow();
			
	    return $result['keyvalue'];
        }
	catch (Exception $e)
	{
	    $this->log->logIt($e);
            return array("status" => "-1","message" => "Exception in " . $this->module . "-" . "readParameter - ".$e);
        }        
    }
	//Akshay End : 23 feb 2018.
	//Sanjay Waman - 27 Oct 2018 - syspmsdetail detail get. 
	public function getsyspmsdetail($pmsname)
    {
        try
		{
            $dao = new dao();
            $strSql = "";
            $strSql .= "SELECT * FROM saasconfig.syspmsdetail";
            $strSql .= " WHERE pmsname = :pmsname";
            $dao->initCommand($strSql);
            $dao->addParameter(":pmsname", $pmsname);
            $result = $dao->executeRow();
			
	    return $result;
        }
		catch (Exception $e)
		{
			$this->log->logIt($e);
				return array("status" => "-1","message" => "Exception in " . $this->module . "-" . "readParameter - ".$e);
		}        
    }
	//Sanjay Waman - End
    
    //Dharti Savaliya - START - 2018-10-30 Purpose:- get modify dateand time
    Public function getmodifydatetime($tranunkid)
    {
        try
        {
            $this->log->logIt(get_class($this) . "-" . "getmodifydatetime");
            $this->log->logIt('hotel_code >> '.$this->hotelcode);
            $this->log->logIt('tranunkid >> '.$tranunkid);
            $dao = new dao(); 
            $strSql = "SELECT auditdatetime FROM fdaudittrail
                       WHERE tranunkid=:tranunkid AND operation='Modify Channel Reservation' AND hotel_code=:hotel_code
                       ORDER BY auditdatetime DESC LIMIT 1;"; 
            $dao->initCommand($strSql);         
            $dao->addParameter(":tranunkid", $tranunkid);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $modifytime = $dao->executeRow();
            //$this->log->logIt($modifytime);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $modifytime = isset($modifytime['auditdatetime']) ? $modifytime['auditdatetime'] : ""; 
            //$this->log->logIt($strSql);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            //$this->log->logIt("Modify time >>".$modifytime);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
        }
        catch (Exception $e)
        {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getmodifydatetime" . "-" . $e);
            $this->handleException($e);
            $modifytime = false;
        }
        return $modifytime;
	}	
	
	//Sushma Rana  : 01 Nov 2018
	//purpose : Function fro notification mail
	Public function bookingnottransfermail($BookingArray,$HotelCode,$reason,$internalmail='',$pmsname='') 
    {
		try 
		{
			 //Dharti Savaliya 2018-09-18 - START Purpose :- send mail to booking not transfer
			$this->log->logIt("Hotel_" . $this->hotelcode . "-" . "sendNotificationEmailToHotelier");
			//$this->log->logIt("Reason >>".$reason);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("Unposted Booking array >>".print_r($BookingArray,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
			$subject="PMS Booking Not Transfer Notification(Booking {request_number} transfer to {pms_name} pms failed, {hotelname})";
			$body='  <p style="font-family:Tahoma,Verdana,Helvetica,sans-serif;font-size:13px;color:#000;">Dear {name},</p>'.	         '  <p style="font-size: 13px;line-height:26px;color:#000;"> <b> Hotel Name </b> : {hotelname}<br/>'.
			'  <p style="font-size: 13px;line-height:26px;color:#000;"> <b> Hotel Code </b> : {hotelcode}<br/>'.		  
			'  <b> Reason </b> : {reason} <br> <b> Booking Reference No(Reservation Number) </b> : {request_number} <br/>'.
			'  <b> Pms Reference No </b> : {pmsresno} <br/>'.
			'  <b> Guest Name </b> : {guestname} <br/>'.
			'  <b> Arrival Date </b> : {checkindate}  <br/>'.
			'  <b> Departure Date </b> : {checkoutdate} <br/>'.
			'  <b> Room </b> : {roomdetail} <br/>'.
			'  <b> Booking Source </b> : {source} <br/>'.
			'  <b> Voucher Number </b> : {vouchernumber}  <br/>'.
			'  <b> Booking Status</b> : {bookingstatus}  <br/>'.
			'  <b> Note </b> : Please take needful action on above booking. </br><br>'.
			'  <br>Best Regards,<br>'.
			'  Channel Manager Team'.			
			'  </p>';
		

			$dao = new dao();
			$strSql="SELECT client.name as ownername,cfhotel.*,country.country_name as country".
				" FROM saasconfig.sysclient as client ".
				" INNER JOIN saasconfig.syshotel as hotel ON hotel.clientunkid=client.clientunkid ".
				" INNER JOIN cfhotel as cfhotel ON cfhotel.hotel_code=hotel.hotel_code ".
				" LEFT JOIN cfcountry as country ON country.countryunkid=cfhotel.country ".					
				" WHERE client.isactive=1 AND hotel.isactive=1 AND hotel.hotel_code=".$this->hotelcode."";		
			
			//$this->log->logIt("Hotelinfo >>".$strSql);
			$dao->initCommand($strSql);	    
			$hotelinfo = $dao->executeRow();
			//$this->log->logIt("Hotelinfo >>".print_r($hotelinfo,true));
           
			/*if($hotelinfo['logo']!='')
			{
				$propertylogo="https://saas.s3.amazonaws.com/uploads/".$hotelinfo['logo'];
				$body=util::replace_email_var("{imgsrc}","<img src=".$propertylogo." border='0' alt='Logo' />",$body);	
			}
			else*/ // Sanjay Waman - 17 Dec 2018 - remove Logo from mail 
				$body=util::replace_email_var("{imgsrc}"," ",$body);

			         
		   
			$dao = new dao();
			$strSql="SELECT custom2,pmsdetail FROM saasconfig.syspmshotelinfo where hotel_code=".$this->hotelcode;		
			$dao->initCommand($strSql);	    
			$cc = $dao->executeRow();

			//$ccemail = '';
			//if($cc['custom2'] != '')
			//{
			//	$ccemail = $cc['custom2'].''; 
			//}
			//else
			//{
				$ccemail = 'centrix365@gmail.com';
			//}

			if($pmsname == '')
			{
				$pmsname = $cc['pmsdetail'];
			}
            
            $subject=util::replace_email_var("{hotelname}",$hotelinfo['name'],$subject);
			$subject=util::replace_email_var("{request_number}",$BookingArray['reservationno'],$subject);	
			$subject=util::replace_email_var("{pms_name}",$pmsname,$subject);		

			$body=util::replace_email_var("{request_number}",$BookingArray['reservationno'],$body);
			$body=util::replace_email_var("{hotelname}",$hotelinfo['name'],$body);			
			$body=util::replace_email_var("{hotelcode}",$hotelinfo['hotel_code'],$body);
			$body=util::replace_email_var("{owneremail}",$hotelinfo['email'],$body);
			$body=util::replace_email_var("{name}",$hotelinfo['ownername'],$body);	    
			$body=util::replace_email_var("{reason}",$reason,$body);	

			$body=util::replace_email_var("{pmsresno}",$BookingArray['pmsresno'],$body);
			$body=util::replace_email_var("{guestname}",$BookingArray['guestname'],$body);
			$body=util::replace_email_var("{checkindate}",$BookingArray['checkindate'],$body);
			$body=util::replace_email_var("{checkoutdate}",$BookingArray['checkoutdate'],$body);
			$body=util::replace_email_var("{roomdetail}",$BookingArray['roomname'],$body);
			$body=util::replace_email_var("{source}",$BookingArray['source'],$body);
			$body=util::replace_email_var("{vouchernumber}",$BookingArray['voucherNo'],$body);
			$body=util::replace_email_var("{bookingstatus}",$BookingArray['status'],$body);
			
			$body = wordwrap($body, 50);

			
            if(isset($internalmail) && $internalmail != '')
            {
                //Add Dharti Savaliya - 2019-09-24 - START - Purpose: Add multiple to in mail
                if(isset($cc['custom2']) && $cc['custom2'] != '')
                {
                   $toemail= $internalmail . ',' .$cc['custom2'].''; 
                }
                else
                {
                    $toemail= $internalmail;
                }
                //Hetal - 17th Dce 2018 - set "noreply@ezeetechnosys.com" replace of "centrix365@gmail.com" - Start
                $fromemail = 'noreply@ezeetechnosys.com';
                //Hetal - 17th Dce 2018 - End
                                 
                $fromname_arr = explode("@",$fromemail);                
                $fromname=$fromname_arr[0];                 
            }
            else
            {
                //Dharti Savaliya - START -2020-08-22 Purpose : Add email id for lemon PMS
                $lemonemailid ='';
                if(isset($pmsname) && $pmsname == 'Lemon' && $hotelinfo['hotel_code'] == 12535)
                {
                    $lemonemailid = 'service@hihotel.com.tw';
                    $toemail=$hotelinfo['email'] . ',' . $lemonemailid.'';
                }
                else
                {
                    if(isset($cc['custom2']) && $cc['custom2'] != '')
                    {                 
                        $toemail=$hotelinfo['email'] . ',' . $cc['custom2'].'';
                    }
                    else
                    {
                        $toemail=$hotelinfo['email'];
                    }
                }
                //Dharti Savaliya - END - 2020-08-22
                //Dharti Savaliya - END - 2019-09-24
                //Hetal - 17th Dce 2018 - set "noreply@ezeetechnosys.com" replace of "centrix365@gmail.com" - Start
                $fromemail='noreply@ezeetechnosys.com';
                //Hetal - 17th Dce 2018 - End
                
                $fromname_arr = explode("@",$fromemail);
                $fromname=$fromname_arr[0];
			}
			//$this->log->logIt("Body >>".$body);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("FromMail >> ".$fromemail ." -- ToMail >>".$toemail ." -- CCMail >>".print_r($ccemail,true) . " -- FromName >>".$fromname);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
			global $server;

			if($server!="local")
			{
				$ret = $this->sendSMTPMail($toemail,$subject,$body,$fromemail,$fromname,$ccemail);		
			}
			else{
				$ret = $this->sendSMTPMail($toemail,$subject,$body,$fromemail,$fromname,$ccemail);	
				//$ret = 'false';
			}
			
			
			$this->log->logIt($ret);
            $msg = '';
			if($ret == 'false')
			{
                if(strtoupper(substr(PHP_OS,0,3)=='WIN')) 
                    $eol="\r\n";
                elseif (strtoupper(substr(PHP_OS,0,3)=='MAC')) 
                    $eol="\r";
                else
                    $eol="\n";
                    
                 //$this->log->logIt("Headers >> ".$headers);
                //Common Headers  
                $headers = $msg = '';
                $headers .= 'From: '.$fromemail.$eol;							
              
               if($ccemail !='')
               {
                    $headers .= 'cc:'.$ccemail[0].$eol;
               }
                //Boundry for marking the split & Multitype Headers
                $mime_boundary=md5(time());
                $headers .= 'MIME-Version: 1.0'.$eol;
                $headers .= "Content-Type: multipart/related; boundary=\"".$mime_boundary."\"".$eol;	    
                $msg .= "Content-Type: multipart/alternative".$eol;
            
                //HTML Version
                $msg .= "--".$mime_boundary.$eol;
                $msg .= "Content-Type: text/html; charset=utf-8".$eol;
                $msg .= "Content-Transfer-Encoding: 8bit\n\n"; // Change 8bit to 7 bit and also add \n\n because of html content issue.	    
                $msg .= $body;
                //$this->log->logIt("mail contain >> ".$msg);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                global $server;
                if($server!="local")
                    mail($toemail, $subject, $msg, $headers);
			}
		}
		catch(Exception $e)
		{
			$this->log->logIt($e);
			return array("status" => "-1","message" => "Exception in " . $this->module . "-" . "sendNotificationEmailToHotelier - ".$e);	
		}
		return $msg;
	}
	
	public function getbookingdatetimedetail($resno,$hotelcode,$sub_res_id='')
	{
		try
		{
			$this->hotelcode=$hotelcode;
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getbookingdatetimedetail");		
	    
            $sqlStr = "SELECT trandate, canceldatetime FROM fdtraninfo AS FDTI WHERE reservationno = :resno AND FDTI.hotel_code = :hotel_code";
			if($sub_res_id!='')
				$sqlStr.= " AND subreservationno = :sub_res_id";
			//$this->log->logIt("Getreservationdetails >>".$sqlStr);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
			$dao->addParameter(":resno", $resno);
			if($sub_res_id!='')
			$dao->addParameter(":sub_res_id", $sub_res_id);	
			$result = $dao->executeQuery();
			
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getreservationdetails - " . $e);
            $this->handleException($e);
        }
        return $result;
	}
    
    //Dharti Savaliya  : 29 Nov 2018
	//purpose : Function for InvalidHotelcode notification mail
	Public function Invalidhotelcodemail($HotelCode,$reason,$internalmail='',$pmsname='') 
    {
		try 
		{
			 //Dharti Savaliya 2018-09-18 - START Purpose :- send mail to booking not transfer
			$this->log->logIt("Hotel_" . $this->hotelcode . "-" . "SendinvalidhotelcodemailToHotelier");
			//$this->log->logIt("Reason >>".$reason);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
			$subject="Invalid Hotelcode Notification for {pms_name}, {hotelname})";
			$body=	'  <p style="font-family:Tahoma,Verdana,Helvetica,sans-serif;font-size:13px;color:#000;">Dear {name},</p>'.	           '  <p style="font-size: 13px;line-height:26px;color:#000;"> <b> Hotel Name </b> : {hotelname}<br/>'.
			'  <p style="font-size: 13px;line-height:26px;color:#000;"> <b> Hotel Code </b> : {hotelcode}<br/>'.		  
			'  <b> Reason </b> : {reason} <br/>'.
			'  <br>Best Regards,<br>'.
			'  Channel Manager Team'.			
			'  </p>';
		

			$dao = new dao();
			$strSql="SELECT client.name as ownername,cfhotel.*,country.country_name as country".
				" FROM saasconfig.sysclient as client ".
				" INNER JOIN saasconfig.syshotel as hotel ON hotel.clientunkid=client.clientunkid ".
				" INNER JOIN cfhotel as cfhotel ON cfhotel.hotel_code=hotel.hotel_code ".
				" LEFT JOIN cfcountry as country ON country.countryunkid=cfhotel.country ".					
				" WHERE client.isactive=1 AND hotel.isactive=1 AND hotel.hotel_code=".$this->hotelcode."";		
			
			//$this->log->logIt("Hotelinfo >>".$strSql);
			$dao->initCommand($strSql);	    
			$hotelinfo = $dao->executeRow();
			//$this->log->logIt("Hotelinfo >>".print_r($hotelinfo,true));
           
			/*if($hotelinfo['logo']!='')
			{
				$propertylogo="https://saas.s3.amazonaws.com/uploads/".$hotelinfo['logo'];
				$body=util::replace_email_var("{imgsrc}","<img src=".$propertylogo." border='0' alt='Logo' />",$body);	
			}
			else*/ //Sanjay Waman - 17 Dec 2018 - remove Logo from mail 
				$body=util::replace_email_var("{imgsrc}"," ",$body);

			         
		   
			$dao = new dao();
			$strSql="SELECT custom2,pmsdetail FROM saasconfig.syspmshotelinfo where hotel_code=".$this->hotelcode;		
			$dao->initCommand($strSql);	    
			$cc = $dao->executeRow();

			$ccemail = '';
			if($cc['custom2'] != '')
			{
				$ccemail = explode(",",$cc['custom2']); 
			}
			else
			{
				$ccemail = 'centrix365@gmail.com';
			}

			if($pmsname == '')
			{
				$pmsname = $cc['pmsdetail'];
			}
            
            $subject=util::replace_email_var("{hotelname}",$hotelinfo['name'],$subject);	
			$subject=util::replace_email_var("{pms_name}",$pmsname,$subject);		

			$body=util::replace_email_var("{hotelname}",$hotelinfo['name'],$body);			
			$body=util::replace_email_var("{hotelcode}",$hotelinfo['hotel_code'],$body);
			$body=util::replace_email_var("{owneremail}",$hotelinfo['email'],$body);
			$body=util::replace_email_var("{name}",$hotelinfo['ownername'],$body);	    
			$body=util::replace_email_var("{reason}",$reason,$body);	
			$body = wordwrap($body, 50);

            if(isset($internalmail) && $internalmail != '')
            {
                $toemail= $internalmail;
                
                //Hetal - 17th Dce 2018 - set "noreply@ezeetechnosys.com" replace of "centrix365@gmail.com" - Start
                $fromemail = 'noreply@ezeetechnosys.com';
                //Hetal - 17th Dce 2018 - End
                
                $fromname_arr = explode("@",$fromemail);                
                $fromname=$fromname_arr[0];                 
            }
            else{
                 
                $toemail=$hotelinfo['email'];
                
                //Hetal - 17th Dce 2018 - set "noreply@ezeetechnosys.com" replace of "centrix365@gmail.com" - Start
                $fromemail='noreply@ezeetechnosys.com';
                //Hetal - 17th Dce 2018 - End
                
                $fromname_arr = explode("@",$fromemail);
                $fromname=$fromname_arr[0];
			}
			//$this->log->logIt("Body >>".$body);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("FromMail >> ".$fromemail ." -- ToMail >>".$toemail ." -- CCMail >>".print_r($ccemail,true) . " -- FromName >>".$fromname);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
			global $server;

			if($server!="local")
			{
				$ret = $this->sendSMTPMail($toemail,$subject,$body,$fromemail,$fromname,$ccemail);		
			}
			else{
				$ret = $this->sendSMTPMail($toemail,$subject,$body,$fromemail,$fromname,$ccemail);	
				//$ret = 'false';
			}
			
			
			$this->log->logIt($ret);
            $msg = '';
			if($ret == 'false')
			{
                if(strtoupper(substr(PHP_OS,0,3)=='WIN')) 
                    $eol="\r\n";
                elseif (strtoupper(substr(PHP_OS,0,3)=='MAC')) 
                    $eol="\r";
                else
                    $eol="\n";
                    
                 //$this->log->logIt("Headers >> ".$headers);
                //Common Headers  
                $headers = $msg = '';
                $headers .= 'From: '.$fromemail.$eol;							
              
               if($ccemail !='')
               {
                    $headers .= 'cc:'.$ccemail[0].$eol;
               }
                //Boundry for marking the split & Multitype Headers
                $mime_boundary=md5(time());
                $headers .= 'MIME-Version: 1.0'.$eol;
                $headers .= "Content-Type: multipart/related; boundary=\"".$mime_boundary."\"".$eol;	    
                $msg .= "Content-Type: multipart/alternative".$eol;
            
                //HTML Version
                $msg .= "--".$mime_boundary.$eol;
                $msg .= "Content-Type: text/html; charset=utf-8".$eol;
                $msg .= "Content-Transfer-Encoding: 8bit\n\n"; // Change 8bit to 7 bit and also add \n\n because of html content issue.	    
                $msg .= $body;
                //$this->log->logIt("mail contain >> ".$msg);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                global $server;
                if($server!="local")
                    mail($toemail, $subject, $msg, $headers);
			}
		}
		catch(Exception $e)
		{
			$this->log->logIt($e);
			return array("status" => "-1","message" => "Exception in " . $this->module . "-" . "SendinvalidhotelcodemailToHotelier - ".$e);	
		}
		return $msg;
	}
	//Dharti Savaliya - 2018-11-29
	
	//Dharti Savaliya 2019-01-02 -START - Purpose:- family hotel roomtype issue(portuguese language)
	function normalize ($roomtypes) {
		$table = array(
			'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'C'=>'C', 'c'=>'c', 'C'=>'C', 'c'=>'c',
			'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
			'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
			'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'à'=>'a',
			 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
			'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
			'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
			'ÿ'=>'y', 'R'=>'R', 'r'=>'r',
		);
		return strtr($roomtypes, $table);
	}
	//Dharti Savaliya -END
	
	//Sanjay Waman - 13 Feb 2019 - common level function for pending Bookig count mail
	Public function Penddingbooingcountmail($pms,$op,$hotelcode,$type="")
	{
		try 
		{
			$this->log->logIt("<< Penddingbooingcountmail >>");
			$hostname = gethostname();		
			$headers = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			$headers .= 'From: CM_tech <cmtech@ezeetechnosys.com>'."\r\n";
			
			$subject = $pms." Booking Queue increase to >>".$op;
			$error = $subject."<br/> Hotel code >>".$hotelcode;
			if($type=='n')
			{
				$error .= "<br/> Booking Type >> New";
			}
			elseif($type=='c')
			{
				$error .= "<br/> Booking Type >> Cancel";
			}
			elseif($type=='v')
			{
				$error .= "<br/> Booking Type >> Void";
			}		
	
			//$this->log->logIt(get_class($this)." - Mail Msg >>".$error);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt(get_class($this)." - Mail Subject >>".$subject. ' - hostname >> '.$hostname);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
			if( $hostname != "ubuntu" )
			{
				$res = mail('cmalert@ezeetechnosys.com', $subject, $error,$headers);
				$this->log->logIt(get_class($this)." - Mail Response >>".$res);
			}
			return true;
		}
		catch(Exception $e)
		{
			$this->log->logIt("Penddingbooingcountmail - ".$e);
			return false;	
		}
	}
	//Sanjay Waman - End

}

?>
