<?php

class pms_bookings_V2 {

    private $log;
    private $module = "pms_bookings_V2";
    private $hotelcode;
    private $xmlDoc;
    private $xmlRoot;
    private $xmlReservation;
	public $objSequenceDao;
    public $objMasterDao2;
	public $returnmsg;//Sanjay Waman - 01 Jun 2019 - Change for Json Format
	public $reservation_arr;//Sanjay Waman - 01 Jun 2019 - Change for Json Format
	public $returnflag;
	public $pmsrequest;

    function __construct() {
        try {
            $this->log = new pmslogger("pms_integration_V2");
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
				$this->prepareResponse();
				$str = $this->xmlDoc->saveXML();
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
					echo $str;
					exit;
					return;
				}				
			}
			else
			{
				$this->generateGeneralErrorMsg('303', "Fetching of bookings is not allowed. Please check the settings on reservation engine.");
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
	

	//Nishant - 19 May 2020 - the method is used for ArrivalList/ DepartureList - OpenAPI(ABS-5175) - Start
	public function executeRequestJson_v2($reqType)
	{
        try
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "executeRequestJson_v2");
			
			$result = $this->getGroupBookings_v2($reqType);
			$this->log->logIt("getGroupBookings_v2 cnt: ".count($result)); 
			$this->reservation_arr = array();
			$bookedbyInfo = array();
			$masterGuestInfo = array();
			$paymentmethod = array();
			$sgle_booking = array();
			if(!empty($result) || $result != null){
				$this->reservation_arr["Reservation"]=array();
                foreach ($result as $res)
				{
					$bookings = $this->getBookings('',$res['tranunkid'], $reqType); //Nishant - 20 May 2020 - OpenAPI(Abs-5175) - Parameter : tranunkid and reqType added for ArrivalList/ DepartureList
					
					if($bookings!=[])
					{
						$bookedbyInfo = $this->getContactInfo($res['tranunkid'], "BookedBy");
						$masterGuestInfo = $this->getContactInfo($res['tranunkid'], "MasterGuest");
						
						$paymentmethod = $this->getPaymentmethod($res['tranunkid']);
						$sgle_booking = $this->generateReservationJSON($res, $bookings, $bookedbyInfo,$masterGuestInfo,$paymentmethod,$reqType);
						
						if(count($sgle_booking)>0)
						{
							array_push($this->reservation_arr["Reservation"],$sgle_booking);
						}
					}
				}
				header('Content-Type: application/json');
				echo json_encode(array("Reservations"=>$this->reservation_arr),JSON_PRETTY_PRINT);
                return;

			}else{
				$this->returnmsg = array('error'=>array('code'=>'503', 'message'=>'No Data Found.'));
				echo json_encode($this->returnmsg,JSON_PRETTY_PRINT);
                return;
			}
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "Total Bookings: " . count($result));
			
        }
		catch (Exception $e)
		{
            $this->log->logIt("Exception in " . $this->module . " - executeRequestJson_v2 - " . $e);
            $this->returnmsg = array('error'=>array('code'=>'303', 'message'=>'Error During Booking Process'));
			echo json_encode($this->returnmsg,JSON_PRETTY_PRINT);
			return;
        }
    }
	//Nishant -  END

	//Sanjay Waman - 01 Jun 2019 - Change for Json Format - Start
	public function executeRequestJson($getbooking=0,$open_apiflag=0,$ResNo='')//Nishit - 7 May 2020 - Purpose: Added $open_apiflag and $ResNo for retrieving single booking - Open API [ABS-5175]
	{
        try
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "executeRequestJson");

			if($this->isTransferAllowed())
			{
				$str = $this->prepareResponseJson($open_apiflag,$ResNo);//Nishit - 7 May 2020 - Purpose: Added $open_apiflag and $ResNo for retrieving single booking - Open API [ABS-5175]
				if($getbooking)
				{
					return $str;
				}
				else
				{
					header('Content-Type: application/json');
					echo $str;
					//Logs insert
					$this->log->logIt("<<<<<<<<<<PULLMETHODE>>>>>>>>>");
					$thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");
					$result = $this->getsyspmsdetail($thirdparty_pms);
					if(isset($result['pmsname']) && isset($result['description']) && trim($result['description']) == "ezeejsonforamtV2" && $result['pushbooking'] == 0)
					{
						$this->log->logIt("<<<<<<<<<<JSONCOMMONPULLMETHODE>>>>>>>>>");
						$pmsbookingauditlogs = new pmsbookingauditlogs_V2();
						if(!isset($this->pmsrequest))
						{
							$this->pmsrequest = "";
						}
						$Returnarray = $pmsbookingauditlogs->jsonreservationlogs($str,$this->pmsrequest,$thirdparty_pms,"PULL",1);
						//$this->log->logIt("Returnarray > ".print_r($Returnarray,true),"encrypted");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
						return $Returnarray;
					}
					exit;
				}				
			}
			else
			{
				$this->returnmsg = array('error'=>array('code'=>'303', 'message'=>'Fetching of bookings is not allowed. Please check the settings on reservation engine.'));
				echo json_encode($this->returnmsg,JSON_PRETTY_PRINT);
                return;
			}
        }
		catch (Exception $e)
		{
            $this->log->logIt("Exception in " . $this->module . " - executeRequestJson - " . $e);
            $this->returnmsg = array('error'=>array('code'=>'303', 'message'=>'Error During Booking Process'));
			echo json_encode($this->returnmsg,JSON_PRETTY_PRINT);
			return;
        }
    }
	//Sanjay Waman -  END
	
    // Change whole notification flow
    public function updateBookingStatus($bookings,$excludeBookings,$manullyFlag=0,$xml='')  //Manali - 1.0.31.36 - 11 Feb 2013, Purpose : Fixed Bug - If bookings sent in group and if any one of them does not exists at our end, it will stop processing other bookings also. So placed this variable for checking
	{
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "updateBookingStatus");
            //$this->log->logIt("Notify Booking >>".print_r($bookings,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("Exclude Booking >>".print_r($excludeBookings,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $errorcode = '';
			
            $adminuserunkid = $this->getAdminUser();
            //$this->log->logIt("Adminuserunkid >>".$adminuserunkid);
            $bookingsUpdated = $groupbookings_count = 0;
			$flag = 0;
			
			$pmsrequest = isset($this->pmsrequest)?$this->pmsrequest.'':"";
			$bookingIDs = array();//Sanjay Waman - 28 Nov 2018 - Notification Booking Log
			
            foreach ($bookings as $booking) {
				
				$resno_new = '';
				$subresno = '';
                $status ='';
                $createdatetime = '';
                $modifydatetime = '';
                $reservation_no = $booking->BookingId;
                $status = $booking->Status;
                $modifydatetime = isset($booking->Modifydatetime)?$booking->Modifydatetime:'';
                $createdatetime = isset($booking->Createdatetime)?$booking->Createdatetime:'';
                
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
				
				array_push($bookingIDs,$resno_new);//Sanjay Waman - 28 Nov 2018 - Booking logs
				$bookingno = $resno_new.(($subresno!='')?'-'.$subresno:'');
				$this->log->logIt("Bookingno >>".$bookingno);
                
                $booking_array = $this->getbookingsource($resno_new);
                //$this->log->logIt("Booking_array >>".print_r($booking_array,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                
                if(isset($booking_array['web_booking']) && count($booking_array['web_booking'])>0 )
                {
                    $this->log->logIt("Web Booking");
                    $final_booking_array = $this->checkwebbooking($bookingno,$status,$modifydatetime,$createdatetime);
                }
                else if(isset($booking_array['channel_booking']) && count($booking_array['channel_booking'])>0)
                {
                    $this->log->logIt("Channel Booking");
                    $final_booking_array = $this->checkchannelbooking($bookingno,$status,$modifydatetime,$createdatetime);
                }
                
                //$this->log->logIt("Final Booking array >>".print_r($final_booking_array,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                
				if(!in_array($bookingno,array_values($excludeBookings),TRUE))
				{
					$this->log->logIt("Update status process");
                    if(count($final_booking_array) == 0)
                    {
                        $count = intval($this->updatePostedStatus(strval($resno_new),($subresno!='')?strval($subresno):'')); //Manali - 1.0.30.35 - 11 Jan 2013, Purpose : to post status of only one transaction based on reservation no and subreservation no checking in case of group reservation
                        $this->log->logIt('Bookings Updated : ' . $count."-".$resno_new);
                        if ($count > 0) {
                            $bookingsUpdated+=1;
                            $groupbookings_count+=$count;
                            $tranunkid = $this->getTranunkid($resno_new,($subresno!='')?strval($subresno):'');                            
                            $this->log->logIt($tranunkid);
                            
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
                    else
                    {
						$this->log->logIt("Booking status or time change at Channel manager. So kindly send latest booking status and time");
                        //$errorcode = "Booking status or time change at Channel manager. So kindly send latest booking status and time";
                    }
                    
				}
				else
				{
					$errorcode .= "BookingID :".$bookingno." Not exist...";
				}
            }
			$this->log->logIt($bookingsUpdated."|".count($bookings));
            if ($bookingsUpdated ==count($bookings)) {
				//Sanjay - 19 May 2020  Change for PMS booking queue - CEN-1628	- start	
				$objpmsbookingqueue = new pmsbookingqueue();	
				$objpmsbookingqueue->hotelcode = $this->hotelcode;
				foreach($bookingIDs as $bookingid)
				{
					$Bookingqueueprocess= $objpmsbookingqueue->updatebookingqueue($bookingid);
					$this->log->logIt('Bookings Updated Queue: ' . $bookingid."-".$Bookingqueueprocess);
				}
				//Sanjay - end
                $success = $this->xmlDoc->createElement("Success");
                $succ_msg = $this->xmlDoc->createElement("SuccessMsg");
                $succ_msg->appendChild($this->xmlDoc->createTextNode(strval($groupbookings_count) . ' booking(s) updated'));
                $success->appendChild($succ_msg);
                $this->xmlRoot->appendChild($success);
				$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
                $str = $this->xmlDoc->saveXML();
                $flag = 1;
               
				if(isset($this->returnflag) && $this->returnflag == 'xml')
				{
					return $str;
				}
				else{
					echo $str;
				}
                //return;
            } 
			else if($bookingsUpdated < count($bookings)){
				//Sanjay - 19 May 2020  Change for PMS booking queue - CEN-1628	- start	
				$objpmsbookingqueue = new pmsbookingqueue();	
				$objpmsbookingqueue->hotelcode = $this->hotelcode;
				foreach($bookingIDs as $bookingid)
				{
					$Bookingqueueprocess= $objpmsbookingqueue->updatebookingqueue($bookingid);
					$this->log->logIt('Bookings Updated Queue: ' . $bookingid."-".$Bookingqueueprocess);
				}
				//Sanjay - end
				//$this->log->logIt("Sushma");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				$success = $this->xmlDoc->createElement("Success");
                $succ_msg = $this->xmlDoc->createElement("SuccessMsg");
				
                $succ_msg->appendChild($this->xmlDoc->createTextNode(strval($groupbookings_count) . ' booking(s) updated'));
                $success->appendChild($succ_msg);
                $this->xmlRoot->appendChild($success);
				
				if($errorcode != '')
				{
					if(isset($errorcode))
					{
						$this->generateGeneralErrorMsg('502',$errorcode);
					}
					else
					{
						$this->generateGeneralErrorMsg('501','Bookings '.implode(",",$excludeBookings).' not exists. So not updated.');
					}
				}
                $str = $this->xmlDoc->saveXML();
                $flag = 0;
               
			   //$this->log->logIt($str);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			   //$this->log->logIt($this->returnflag);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
               if(isset($this->returnflag) && $this->returnflag == 'xml')
				{
					return $str;
				}
				else{
					echo $str;
				}
                //return;
			}            
			else {
                $this->generateGeneralErrorMsg('500', "Error occured during processing.");
                $flag = 0;
              
				if(isset($this->returnflag) && $this->returnflag == 'xml')
				{
					return $this->xmlDoc->saveXML();
				}
				else{
					echo $this->xmlDoc->saveXML();
				}
                //return;
            }
			//Sanjay Waman - 28 Nov 2018 - PMS Booking logs - Notification regarding changes - Start
	
			if(isset($bookingIDs) && count($bookingIDs)>0 )
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
     // Below code not in use
	 public function cancelBooking($bookings,$excludeBookings,$BookingsVoidCancelled) 
	 {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "cancelBooking");
			
			if($this->isCancellationRequestAllowed()){
				 $adminuserunkid = $this->getAdminUser();
				$objTranDao = new trandao();
				$bookingsCancelled = $groupbookingsCancelled = 0; 
				
				foreach ($bookings as $booking) {
					$bookingno = $booking->BookingId.((isset($booking->SubBookingId) && $booking->SubBookingId!='')?'-'.strval($booking->SubBookingId):'');
					if(!in_array($bookingno,array_values($excludeBookings),TRUE) && !in_array($bookingno,array_values($BookingsVoidCancelled),TRUE))
					{	
						$tranunkid = $this->getTranunkid($booking->BookingId,isset($booking->SubBookingId)?strval($booking->SubBookingId):''); 
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
					echo $str;
					return;
				}
				else {
					$this->generateGeneralErrorMsg('500', "Error occured during processing.");
					echo $this->xmlDoc->saveXML();
					return;
				}
			}
			else{
				$this->generateGeneralErrorMsg('304', "Fetching of cancellation booking request is not allowed. Please check the settings on reservation engine.");
                echo $this->xmlDoc->saveXML();
                return;
			}           
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - cancelBooking - " . $e);
            $this->handleException($e);
        }
     }
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
			
			
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - cancelReservation - " . $e);
            $this->handleException($e);
        }
        return $result;
	}

    private function addToAuditTrail($tranunkid, $resno, $adminuserunkid, $pmsbookingid,$subresno='') 
	{
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "addToAuditTrail");
            $operation = "Booking Transferred To PMS"; //Dharti Savaliya 2020-08-10 Purpose : correct spelling CEN-1599
            $description = "Booking transferred to PMS"; //Dharti Savaliya 2020-08-10 Purpose : correct spelling CEN-1599
            $description .= "<br>PMS Booking Id : " . $pmsbookingid;
            $description .= "<br>Reservation No : " . $resno;
			
			if($subresno!='')
				$description .= "-".$subresno;
			
            $this->insertIntoAuditTrail($tranunkid, $operation, $description, '', $adminuserunkid);
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - addToAuditTrail - " . $e);
            $this->handleException($e);
        }
    }

    private function prepareResponse()
	{
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "prepareResponse");
            $transferred = array();
			$UnsetCancel = array();//Sanjay Waman - 12 Aug 2019 - Lookback PMS chnages
            $result = $this->getGroupBookings();
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "Total Bookings: " . count($result));
            
			//Sanjay - 02 May 2020 - Changes for PMS booking Queue [CEN-1628]- Start
			$Pmsqueueflag = 0;
			$arrbookingqueuehotel = $arrbookingqueuepms = $arrbookingqueuedata = array();						
			$log_info_path = "/home/saasfinal/pmsinterface";	
			$file = $log_info_path."/pmsqueue.json";
            $thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");
			//$this->log->logIt("file location >>".$file);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			if(file_exists($file))
			{
				$str  = file_get_contents($file);
				$arrbookingqueuedata = json_decode($str, true);	
				//$this->log->logIt("Pms queue data >>".json_encode($arrbookingqueuedata,true),"encrypted");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				$arrbookingqueuepms = $arrbookingqueuedata['pms'];
				//$this->log->logIt("Pms name array >>".json_encode($arrbookingqueuepms,true),"encrypted");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				if(in_array($thirdparty_pms,$arrbookingqueuepms))
				{
					$arrbookingqueuehotel = $arrbookingqueuedata['hotelcode'];
					//$this->log->logIt("Hotel data >>".json_encode($arrbookingqueuehotel,true),"encrypted");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
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
			//$this->log->logIt("Flag value >>".$Pmsqueueflag);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
			if(isset($Pmsqueueflag) && $Pmsqueueflag == 1){
				$objpmsbookingqueue = new pmsbookingqueue();	
				$objpmsbookingqueue->hotelcode = $this->hotelcode;
				$objpmsbookingqueue->module = 'pms_integration_V2';
				$result= $objpmsbookingqueue->GroupReservationQueue($result,'0');
				unset($objpmsbookingqueue);
			}		
			//Sanjay - End
			
			if($result != NULL)
			{
                 $bookfunObj = new pmsbookingfunctons();
                $bookfunObj->hotelcode = $this->hotelcode;
                foreach ($result as $res)
				{
						$bookings = $this->getBookings($res['reservationno']);
                        //Dharti Savaliya - 08 Jan 2021 - Booking process time + transfer time same [CEN-1897] - Start
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
                                        mail("sanjay.waman@ezeetechnosys.com,dharti.savaliya@ezeetechnosys.com", "GroupBookingSkipCheck", "Booking Skip (".trim($thirdparty_pms)." PMS - ".trim($this->hotelcode).") - Booking ID -".trim($bookings[0]['channelbookingno'])."", "From: saas <noreply@ezeetechnosys.com>\r\n");
                                        continue; //IgnoreCheck
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
                        //Dharti Savaliya -END
						//Sanjay waman - 12 Aug 2019 - Lookback PMS Changes - START
						if($bookings!=[])
						{
							array_push($UnsetCancel,$res['reservationno']);
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
						//Sanjay waman - End
                }
                 unset($bookfunObj);
				// OLD - Shifted ot out side
				//$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
            }
			
			// HRK - 1.0.29.34 - 1 Nov 2012 - START
			// Purpose : When no booking found then also we need error 0 tag
			$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
			// HRK - 1.0.29.34 - 1 Nov 2012 - END
			
			$thirdparty_pms = $this->readConfigParameter("ThirdPartyPMS");
            $result = $this->getCancelledBooking();
			//Sanjay - 03 Jun 2020 - Changes for PMS booking Queue [CEN-1628]- Start
			if(isset($Pmsqueueflag) && $Pmsqueueflag == 1){
				$objpmsbookingqueue = new pmsbookingqueue();	
				$objpmsbookingqueue->hotelcode = $this->hotelcode;
				$objpmsbookingqueue->module = 'pms_integration_V2';
				$result= $objpmsbookingqueue->GroupReservationQueue($result,'c');
				unset($objpmsbookingqueue);
			}
			//Sanjay - End
			//Sanjay Waman  - 12 Aug 2019 - Lookback PMS Changes - Start
			//$this->log->logIt("unsetCancell - ".json_encode($UnsetCancel),"encrypted");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("Cancel - ".json_encode($result),"encrypted");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
			$opentravel = 0;
			if($thirdparty_pms == 'iHotelligence' || $thirdparty_pms == 'CloudInn')
			{
				$opentravel = 1;
			}			
			
			if($opentravel == '0')
			{
				$repeated_cancel=array();
				foreach($result as $key=>$Value)
				{
					$sub_res_no=explode("-",$Value['reservationno']);
					$Value['reservationno']=$sub_res_no[0];
					$result[$key]['reservationno'] = $Value['reservationno'];
					
					if((in_array($Value['reservationno'],$UnsetCancel)) || (in_array($Value['reservationno'],$repeated_cancel)))
					{
						unset($result[$key]);
					}
					array_push($repeated_cancel,$Value['reservationno']);
				}
				//$this->log->logIt("Cancel - ".json_encode($result),"encrypted");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			}
			
			//Sanjay Waman - End
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "Total Cancelled Bookings : " . count($result));
            $cancelledBookings = $this->generateCancelledBookingsXML($result);
            $transferred = array_merge($transferred, $cancelledBookings);
            //$this->log->logIt("Transferred Array >>".print_r($transferred,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - prepareResponse - " . $e);
            $this->handleException($e);
        }
	}
	
	//Sanjay Waman - 01 Jun 2019 - Change for Json Format - Start
	private function prepareResponseJson($open_apiflag,$ResNo)//Nishit - 7 May 2020 - Purpose: Added $open_apiflag and $ResNo for retrieving single booking - Open API [ABS-5175]
	{
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "prepareResponseJson");
            $transferred = array();
            $result = $this->getGroupBookings($ResNo);//Nishit - 7 May 2020 - Purpose: Added $ResNo for retrieving single booking - Open API [ABS-5175]
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "Total Bookings: " . count($result));

			//Sanjay Waman- 10 Sep 2019 - JsonFormat [CEN-1086] - Start
			if(isset($this->returnflag))
			{
				 $this->log->logIt("XMLJSONFlag>>>>".$this->returnflag);
			}
			//Sanjay Waman -End
			$this->reservation_arr = array();
			$paymentmethod='';//Nishit - 7 May 2020 - Purpose: For retrieving single booking - Open API [ABS-5175]
			if($result != NULL)
			{
				$this->reservation_arr["Reservation"]=array();
                foreach ($result as $res)
				{
						$bookings = $this->getBookings($res['reservationno']);
						//$this->log->logIt(print_r($bookings,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                        
						if($bookings!=[])
						{
							$bookedbyInfo = $this->getContactInfo($res['tranunkid'], "BookedBy");
							$masterGuestInfo =  $this->getContactInfo($res['tranunkid'], "MasterGuest");
							//Nishit - 7 May 2020 - Start - Purpose: Added $open_apiflag condition for retrieving single booking - Open API [ABS-5175]
							if($open_apiflag==1)
							{
								$paymentmethod=$this->getPaymentmethod($res['tranunkid']);
								$sgle_booking = $this->generateReservationJSON($res, $bookings, $bookedbyInfo,$masterGuestInfo,$paymentmethod);
							}
							//Nishit - 7 May 2020 - End
							else
								$sgle_booking = $this->generateReservationJSON($res, $bookings, $bookedbyInfo,$masterGuestInfo);
							
							if(count($sgle_booking)>0)
							{
								array_push($this->reservation_arr["Reservation"],$sgle_booking);
							}	
						}
                }
			}

			$result = $this->getCancelledBooking($ResNo); //Nishit - 7 May 2020 - Start - Purpose: Added $ResNo condition for retrieving single booking - Open API [ABS-5175]
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "Total Cancelled Bookings : " . count($result));
			if(count($result)>0)
			{
				$cancelledBooking = $this->generateCancelledBookingsJSON($result);
				if(count($cancelledBooking)>0)
				{
					$this->reservation_arr["CancelReservation"]=array();
					$this->reservation_arr['CancelReservation'] = $cancelledBooking;
				}
			}

			if(empty($this->reservation_arr))
			{
				return json_encode(array("Reservations"=>"No Reservation Found"),true);
			}
			
            return json_encode(array("Reservations"=>$this->reservation_arr),true);
            
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - prepareResponseJson - " . $e);
            $this->returnmsg = array('error'=>array('code'=>'303', 'message'=>'Error During Booking Process'));
			echo json_encode($this->returnmsg,JSON_PRETTY_PRINT);
			return;
        }
    }
	//Sanjay Waman - End

	private function getBookings($resno='', $tranunkid='', $reqType = '')
	{
        try
		{
			$reqTypeArr = array('ArrivalList','DepartureList');
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getBookings - ".$resno);
            $result = NULL; //date(FDTI.arrivaldatetime) as arrivaldate,date(FDTI.departuredatetime) as departuredate
            $sqlStr = " SELECT FDTI.tranunkid,FDTI.trandate AS createdatetime,FDTI.reservationno,FDTI.subreservationno,FDTI.arrivaldatetime as arrivaldate,FDTI.departuredatetime as departuredate, FDTI.commissionplanunkid as commisionid,";   // Added commision fileld  -sushma
			$sqlStr.= " FDTI.statusunkid AS statusid, FDTI.webrateunkid, FDTI.webinventoryunkid, FDR.reasoncategory , CF.reason ,FDR.cfreasonunkid, CF.reasonunkid, "; //Add reasoncategory,reason ,cfreasonunkid field - 2019-05-31
            $sqlStr.="  IFNULL(FDCBI.channelhotelunkid,'') As channelhotelid,IFNULL(FDCBI.channelunkid,'') AS channelid, 
			IFNULL(FDCBI.channelbookingid,FDTI.reservationno) As channelbookingno, "; //Manali - 1.0.48.53 - 08th March 2016, Purpose : Enhancement - Placed CCInfo link in base64 encoded format
			$sqlStr.="FDCBI.channelbookingunkid as channelbookingunkid,FDCBI.ruid as ruid,FDCBI.ruid_status as ruid_status, ";//Manali - 20th Mar 2017,Purpose : Enhancement - Separate CC Servers Functionality - Change logs
			$sqlStr.=" IFNULL((SELECT `businesssourcename` FROM cfbusinesssource WHERE businesssourceunkid=FDTI.businesssourceunkid),"; // Sahdev - 15-05-2019 - CEN-1044 - Purpose :- Business Source in Look back interface
			$sqlStr.=" IFNULL((SELECT business_name FROM trcontact WHERE contactunkid=FDTI.companyunkid),";
            $sqlStr.=" IFNULL((SELECT `business_name` FROM trcontact WHERE contactunkid=FDTI.travelagentunkid),";
            $sqlStr.=" IFNULL((SELECT `name` FROM trcontact WHERE contactunkid=FDTI.roomownerunkid),";
            $sqlStr.=" 'WEB')))) AS source,";
            $sqlStr.=" CFRT.roomtype,CFRT.roomtypeunkid,CFRT.shortcode as roomcode,FDTI.masterfoliounkid,FDTI.travelagentvoucherno,";
			
			//Manali - 1.0.36.41 - 30 Aug 2013 - START
			//Purpose : Customization : Send package,credit card info in bookings for PMS-Web Interface			
			 // Add Dharti Savaliya 2019-05-31 Purpose:-  When we add remark manually at that time in booking xml remark not added at pms side(CEN-1083)	
             $sqlStr.="REPLACE(TRIM(BOTH ',' FROM GROUP_CONCAT(CASE WHEN FDR.reasoncategory='PACKAGE' THEN CONCAT('Package : ',REPLACE(REPLACE(FDR.reason,SUBSTR(FDR.reason,INSTR(FDR.reason,'</b>')),''),'<b>','')) WHEN  FDR.reasoncategory='RESERV' THEN REPLACE(CONCAT('Reservation : ',FDR.reason),'<br>','') WHEN FIND_IN_SET(FDR.reasoncategory,'HOTELIERREMARK') THEN CONCAT(IF(FDR.reasoncategory = 'HOTELIERREMARK', 'Internal Note : ', 'Important Info : ' AND (FDR.reason != '' || CF.reason != '')), IF(FDR.reason != '',REPLACE(REPLACE(FDR.reason,SUBSTR(FDR.reason,INSTR(FDR.reason, '</b>')),''),'<b>',''),REPLACE(REPLACE(CF.reason,SUBSTR(CF.reason,INSTR(CF.reason, '</b>')),''),'<b>','')))  END)),',,',',') AS specreq, ";
             //Dharti Savaliya -END - 2019-05-31
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
			$sqlStr.=' IF((FDCBI.status IS NOT NULL && FDCBI.status="M"),"Modify","New") AS status, ';
			#Chandrakant - 1.0.37.42 - 13 Nov 2013 - END
			$sqlStr.=' FDCBI.status AS status_web, CPT.paymenttype ';
			
            $sqlStr.=" FROM fdtraninfo AS FDTI";
			
			if(!in_array($reqType,$reqTypeArr))
			{
				//Manali - 1.0.49.54 - 26 May 2016 - START
				//Purpose : Customization : Send guarantee - is reservation confirmed or not confirmed
				$sqlStr.=" INNER JOIN cfreservationgaurantee AS CFR ON CFR.gauranteeunkid = FDTI.reservationgauranteeunkid ";
				//Manali - 1.0.49.54 - 26 May 2016 - END
			}else{
				//Nishant - 26 May 2020 - OpenAPI(ABS-5175) - ArrivalList/ DepartureList
				$sqlStr.=" LEFT JOIN cfreservationgaurantee AS CFR ON CFR.gauranteeunkid = FDTI.reservationgauranteeunkid ";
				//Nishant - 26 May 2020 - END
			}

			$sqlStr.=" LEFT JOIN cfpaymenttype AS CPT ON CPT.paymenttypeunkid=FDTI.paymenttypeunkid AND CPT.hotel_code=:hotel_code";
            #$sqlStr.=" INNER JOIN fdrentalstatus AS FDRS ON FDRS.statusunkid = FDTI.statusunkid";
            $sqlStr.=" INNER JOIN cfroomtype AS CFRT ON CFRT.roomtypeunkid = FDTI.roomtypeunkid";
            $sqlStr.=" LEFT JOIN fdreason AS FDR ON FDR.tranunkid = FDTI.tranunkid AND FDR.hotel_code=:hotel_code  ";
			$sqlStr.=" LEFT JOIN cfreason AS CF ON CF.reasonunkid = FDR.cfreasonunkid " ; // Dharti Savaliya 2019-05-31 Purpose:-When we add remark manually at that time in booking xml remark not added at pms side(CEN-1083)	
			
			//Manali - 1.0.36.41 - 30 Aug 2013 - START
			//Purpose : Customization : Send package,credit card info in bookings for PMS-Web Interface			
			//$sqlStr.=" FIND_IN_SET(FDR.reasoncategory,'RESERV,PACKAGE') ";
			$sqlStr.=" LEFT JOIN fdchannelbookinginfo AS FDCBI ON FDCBI.tranunkid = FDTI.tranunkid AND FDCBI.hotel_code=:hotel_code";
			//Manali - 1.0.36.41 - 30 Aug 2013 - END
			
            $sqlStr.=" WHERE FDTI.hotel_code =:hotel_code ";
				
			// Nishant - 20 May 2020 - OpenAPI (ABS-5175) Condition add - start
			if(!in_array($reqType,$reqTypeArr))
			{	
				//Manali - 1.0.49.54 - 28 May 2016 - START
				//Purpose : Transfer unconfirm bookings to PMS based on settings
				if($this->readConfigParameter('PMSTransferUnConfirmBookings')=="0")
					$sqlStr.=" AND FIND_IN_SET(FDTI.statusunkid,'4,13')";
				else
					$sqlStr.=" AND FIND_IN_SET(FDTI.statusunkid,'4,10,13')";
				//Manali - 1.0.49.54 - 28 May 2016 - END

				$sqlStr.=" AND FDTI.reservationno=:resno ";
				$sqlStr.=" GROUP BY FDTI.tranunkid order by FDTI.reservationno, FDTI.subreservationno";//Sanjay Waman - 12 Aug 2019 
			}else{
				$sqlStr.=" AND FDTI.tranunkid=:tranunkid ";
				$sqlStr.=" GROUP BY FDTI.tranunkid order by FDTI.tranunkid";//Nishant - 26 May 2020 - OpenAPI(ABS-5175)
			}
			// Nishant - 20 May 2020 - OpenAPI (ABS-5175) Condition add - End
			
            //$sqlStr.=" GROUP BY FDTI.tranunkid order by FDTI.reservationno, FDTI.subreservationno";//Sanjay Waman - 12 Aug 2019 - remove posted check condition (Lookback) (AND FDTI.isposted=:isposted)
			//$this->log->logIt("get bookings ".$sqlStr);
			$dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
			$dao->addParameter(":isposted", 0);

			if(!in_array($reqType,$reqTypeArr)) //Nishant - 26 May 2020 - OpenAPI(ABS-5175)
				$dao->addParameter(":resno", $resno);
			
			$dao->addParameter(":tranunkid", $tranunkid);

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
	
	
	// purpose - to get reservation other details like arr , dep date , sourcename for pmsxchange
	//Last parameter added which is used in tauras pms to get arr and dep details for cancellation
	public function getreservationdetails($resno='',$hotelcode,$sub_res_id='',$tranunkid='')
	{
		try
		{
			$this->hotelcode=$hotelcode;
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getreservationdetials");
			$this->log->logIt("Reservation no + Hotel code + Subreservation no >>".$resno."-".$hotelcode."-".$sub_res_id);
	    
			$sqlStr = "SELECT * FROM fdtraninfo AS FDTI LEFT JOIN cfbusinesssource AS CFBS ON (FDTI.businesssourceunkid = CFBS.businesssourceunkid AND CFBS.hotel_code = :hotel_code) WHERE FDTI.hotel_code = :hotel_code AND reservationno = :resno "; //Nishant - 2 Jan 2021 - Revert Query for ResNo
			
			//Nishant - 20 May 2020 - OpenAPI (ABS-5175) condition check for Method ArrivalList/ DepartureList -start 
			//if($resno !='') //Nishant - 2 Jan 2021 - Revert Query for ResNo
				//$sqlStr .= " AND reservationno = :resno ";

			if($tranunkid !='')
				$sqlStr .= " AND FDTI.tranunkid = :tranunkid ";
			//Nishant - 20 May 2020 - End
			
			if($sub_res_id !='')
				$sqlStr.= " AND subreservationno = :sub_res_id";
			//$this->log->logIt("Getreservationdetails >>".$sqlStr);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
            $dao = new dao();
            $dao->initCommand($sqlStr);
			$dao->addParameter(":hotel_code", $this->hotelcode);

			//if($resno !='') //Nishant - 2 Jan 2021 - Revert Query for ResNo
			$dao->addParameter(":resno", $resno);

			if($sub_res_id !='')
				$dao->addParameter(":sub_res_id", $sub_res_id);	//Last parameter added which is used in tauras pms to get arr and dep details for cancellation

			if($tranunkid !='')
				$dao->addParameter(":tranunkid", $tranunkid);	//Nishant - 20-May-2020 - OpenAPI(ABS-5175) - used for ArrivalList/ DepartureList 
            //$dao->addParameter(":isposted", 0);
           
			$result = $dao->executeQuery();
			//$this->log->logIt("Result Data >>".print_r($result,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getreservationdetails - " . $e);
            $this->handleException($e);
        }
        return $result;
	}
	
	//Nishant - 19 May 2020 - the method is used for ArrivalList/ DepartureList  - Open API [ABS-5175]
	private function getGroupBookings_v2($reqType)
	{
        try
		{
			$rr = json_decode($this->pmsrequest);
			$from_date =  $rr->RES_Request->Date->from_date;
			$to_date =  $rr->RES_Request->Date->to_date;
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getGroupBookings_v2");	    
			
	        //$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "Request:".$reqType);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
	        //$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "From:".$from_date);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "To:".$to_date);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
            $result = NULL;
            $sqlStr = " SELECT FDTI.tranunkid,FDTI.reservationno,";
            $sqlStr.=" CASE WHEN (FDTI.webrateunkid IS NOT NULL AND FDTI.webinventoryunkid IS NOT NULL) THEN 'WEB' ELSE 'CHANNEL' END as bookingtype,";
			$sqlStr.=" IFNULL((SELECT `businesssourcename` FROM cfbusinesssource WHERE businesssourceunkid=FDTI.businesssourceunkid),"; 
			$sqlStr.=" IFNULL((SELECT business_name FROM trcontact WHERE contactunkid=FDTI.companyunkid),";
            $sqlStr.=" IFNULL((SELECT `business_name` FROM trcontact WHERE contactunkid=FDTI.travelagentunkid),";
            $sqlStr.=" IFNULL((SELECT `name` FROM trcontact WHERE contactunkid=FDTI.roomownerunkid),";
            $sqlStr.=" 'WEB')))) AS source";
            $sqlStr.=" FROM fdtraninfo AS FDTI";
			$sqlStr.=" WHERE FDTI.hotel_code =:hotel_code ";
						
			//Nishant - 20 May 2020 - OpenAPI (ABS-5175) condition check for Method ArrivalList/ DepartureList -start
			$sqlStr.=" AND IFNULL(FDTI.transactionflag,0)<>0 ";
			$sqlStr.=" AND FDTI.statusunkid NOT IN (5,6,7,10)";

			if($reqType == 'ArrivalList')
			{
				$sqlStr.=" AND CAST(FDTI.arrivaldatetime As DATE)>=:from_date AND CAST(FDTI.arrivaldatetime As DATE)<= :to_date" ;
			}else if($reqType == 'DepartureList')
			{
				$sqlStr.=" AND CAST(FDTI.departuredatetime As DATE)>=:from_date AND CAST(FDTI.departuredatetime As DATE)<=:to_date" ;
			}			
			//Nishant - 20 May 2020 - OpenAPI (ABS-5175) end

			$sqlStr.= " GROUP BY FDTI.tranunkid ORDER BY FDTI.tranunkid";
			$sqlStr.=" LIMIT 0,25"; //Manali - 1.0.34.39 - 24 May 2013, Purpose : Fixed Bug - It was making server down when all bookings are fetched in one time, so placed limit here. 
			
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":from_date", $from_date);
            $dao->addParameter(":to_date", $to_date);
			$result = $dao->executeQuery();
			
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getGroupBookings_v2 - " . $e);
            $this->handleException($e);
        }
        return $result;
	}
	
    private function getGroupBookings($resno='')//Nishit - 7 May 2020 - Purpose: Added $ResNo for retrieving single booking - Open API [ABS-5175]
	{
        try
		{
            //$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getGroupBookings");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]	    
			$workingdate=date ("Y-m-d", strtotime("-1 day", strtotime($this->readConfigParameter('todayDate'))));
	        $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getGroupBookings". "working Date".$workingdate);
            $result = NULL;
            $sqlStr = " SELECT FDTI.tranunkid,FDTI.reservationno,";
            $sqlStr .= "FDTI.groupunkid,";//Dharti Savaliya - 08 Jan 2021 - Added FDTI.groupunkid [CEN-1897]
            #$sqlStr.=" ,FDTI.subreservationno,";//date(FDTI.arrivaldatetime) as arrivaldate,date(FDTI.departuredatetime) as departuredate,";
            $sqlStr.=" CASE WHEN (FDTI.webrateunkid IS NOT NULL AND FDTI.webinventoryunkid IS NOT NULL) THEN 'WEB' ELSE 'CHANNEL' END as bookingtype,";
			
			$sqlStr.=" IFNULL((SELECT `businesssourcename` FROM cfbusinesssource WHERE businesssourceunkid=FDTI.businesssourceunkid),"; // Sahdev - 15-05-2019 - CEN-1044 - Purpose :- Business Source in Look back interface
			$sqlStr.=" IFNULL((SELECT business_name FROM trcontact WHERE contactunkid=FDTI.companyunkid),";
            $sqlStr.=" IFNULL((SELECT `business_name` FROM trcontact WHERE contactunkid=FDTI.travelagentunkid),";
            $sqlStr.=" IFNULL((SELECT `name` FROM trcontact WHERE contactunkid=FDTI.roomownerunkid),";
            $sqlStr.=" 'WEB')))) AS source";

            #$sqlStr.=" CFRT.roomtype,CFRT.roomtypeunkid,CFRT.shortcode as roomcode,FDTI.masterfoliounkid,FDR.reason as specreq";
            $sqlStr.=" FROM fdtraninfo AS FDTI";
            #$sqlStr.=" INNER JOIN fdrentalstatus AS FDRS ON FDRS.statusunkid = FDTI.statusunkid";
			//$sqlStr.=" INNER JOIN fdchannelbookinginfo as FCBI on FDTI.tranunkid=FCBI.tranunkid";
            #$sqlStr.=" INNER JOIN cfroomtype AS CFRT ON CFRT.roomtypeunkid = FDTI.roomtypeunkid";
            #$sqlStr.=" LEFT JOIN fdreason AS FDR ON FDR.tranunkid = FDTI.tranunkid AND FDR.reasoncategory='RESERV'";
			$sqlStr.=" WHERE FDTI.hotel_code =:hotel_code ";
			//Nishit - 7 May 2020 - Start - Purpose: Added $ResNo for retrieving single booking - Open API [ABS-5175]
			if($resno!='')
				$sqlStr.=" AND FDTI.reservationno = '".$resno."' ";
			//Nishit - 7 May 2020 - End
			//Manali - 1.0.49.54 - 28 May 2016 - START
			//Purpose : Transfer unconfirm bookings to PMS based on settings
			if($this->readConfigParameter('PMSTransferUnConfirmBookings')=="0")
				 $sqlStr.=" AND FIND_IN_SET(FDTI.statusunkid,'4,13,6')";//Sanjay Waman - 12 Aug 2019 - add 6 param. for cancellatiob booking (Lookback)
			else
				 $sqlStr.=" AND FIND_IN_SET(FDTI.statusunkid,'4,10,13,6')";//Sanjay Waman - 12 Aug 2019 - add 6 param. for cancellatiob booking (Lookback)
			//Manali - 1.0.49.54 - 28 May 2016 - END
			
			// HRK - 1.0.30.35 - 17 Dec 2012 - START
			// Purpose : Prevent transaction from posting whose payment is not acknowledged
			if($this->readConfigParameter('PMSTransferPaymentAcknowledgedBookingsOnly')=="1")
				$sqlStr.=" AND IFNULL(FDTI.transactionflag,0)<>0 ";
			// HRK - 1.0.30.35 - 17 Dec 2012 - END
			
            $sqlStr.=" AND FDTI.isposted=:isposted AND CAST(FDTI.arrivaldatetime As DATE)>='".$workingdate."' GROUP BY FDTI.reservationno ORDER BY FDTI.reservationno";			//Manali - 1.0.46.51 - 29th May 2015, Purpose : Applied checking for past date reservations not to send in PMS
			$sqlStr.=" LIMIT 0,25"; //Manali - 1.0.34.39 - 24 May 2013, Purpose : Fixed Bug - It was making server down when all bookings are fetched in one time, so placed limit here. 
			
			//$this->log->logIt("Group bookings".$sqlStr);
			
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
	
    private function getCancelledBooking($resno='') {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getCancelledBooking");
	    
			$workingdate=date ("Y-m-d", strtotime("-1 day", strtotime($this->readConfigParameter('todayDate'))));
	    
            $result = NULL;
            $sqlStr = " SELECT FDTI.tranunkid,CASE WHEN FDTI.subreservationno IS NOT NULL AND FDTI.subreservationno!='' THEN CONCAT(FDTI.reservationno,'-',FDTI.subreservationno) ELSE FDTI.reservationno END as reservationno,FDR.reason AS fremark,CFR.reason AS cremark,IFNULL(FDTI.travelagentvoucherno,'') AS voucherno, FDTI.canceldatetime AS canceldatetime, "; 
            $sqlStr.=" FDTI.webrateunkid, FDTI.webinventoryunkid FROM fdtraninfo AS FDTI";
            $sqlStr.=" LEFT JOIN fdreason AS FDR ON FDR.tranunkid = FDTI.tranunkid AND FDR.reasoncategory='CANRESERV' AND FDR.hotel_code=:hotel_code";
            $sqlStr.=" LEFT JOIN cfreason AS CFR ON CFR.reasonunkid=FDR.cfreasonunkid AND CFR.reasoncategory='CANRESERV' AND CFR.hotel_code=:hotel_code";
            $sqlStr.=" WHERE FDTI.hotel_code =:hotel_code ";
            $sqlStr.=" AND FDTI.statusunkid=6 AND IFNULL(FDTI.transactionflag,0)<>0 "; 
			$sqlStr.=" AND FDTI.isposted=:isposted  AND CAST(FDTI.arrivaldatetime As DATE)>='".$workingdate."'"; 
			if($resno!='')
				$sqlStr.=" AND reservationno=:reservationno "; 
			$sqlStr.=" LIMIT 0,25"; 
						
            $dao = new dao();
            $dao->initCommand($sqlStr);
			$dao->addParameter(":hotel_code", $this->hotelcode);
			if($resno!='')
				$dao->addParameter(":reservationno", $resno);
            $dao->addParameter(":isposted", 0);
            
            $result = $dao->executeQuery();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getCancelledBooking - " . $e);
            $this->handleException($e);
        }
        return $result;
    }

    private function updatePostedStatus($resno,$subreservationno)   
	{
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "updatePostedStatus"."-".$resno."-".$subreservationno);
            $bookingsUpdated = -1;
			
			$strSql = " UPDATE fdtraninfo";
            $strSql .= " SET isposted=:isposted";
            $strSql .= " WHERE hotel_code =:hotel_code AND reservationno=:resno";
			
			if($subreservationno!='')
				$strSql .= " AND subreservationno=:subreservationno";			
			 	
            $dao = new dao();
            $dao->initCommand($strSql);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":isposted", 1);
		    $dao->addParameter(":resno", $resno);
			
			if($subreservationno!='')
				 $dao->addParameter(":subreservationno", $subreservationno);
			
			//$this->log->logIt($strSql);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $bookingsUpdated = $dao->executeNonQuery();
           	//$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "updatePostedStatus"."-".$bookingsUpdated);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			 
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
             			
            $result = $dao->executeRow();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getContactInfo - " . $e);
            $this->handleException($e);
        }
        return $result;
	}
	//Nishit - 7 May 2020 - Start - Purpose: Open API [ABS-5175]
	public function getPaymentmethod($tranunkid) {
        try {
            $paymentmethod='';
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getPaymentmethod");
			$strSql ='';
			$dao = new dao();
						
			$strSql = "SELECT IFNULL(settlementtype,'') AS settlementtype,IFNULL(settlementmasterunkid,'') AS settlementmasterunkid from fasfoliomaster INNER JOIN fdtraninfo ON fdtraninfo.masterfoliounkid=fasfoliomaster.foliounkid Where fasfoliomaster.hotel_code=:hotel_code AND lnktranunkid=:tranunkid";	

            $dao->initCommand($strSql);
            $dao->addParameter(":tranunkid", $tranunkid);
			$dao->addParameter(":hotel_code", $this->hotelcode);    			
			$result = $dao->executeRow();
			
			if(is_array($result) && count($result)>0 && $result['settlementmasterunkid']!= '' && $result['settlementtype']!='')
			{
				if($result['settlementtype']=='CREDIT')
				{
					$strSql1 = "SELECT `name` from fasmaster Where hotel_code=:hotel_code AND masterunkid=:masterunkid ";	

					$dao->initCommand($strSql1);
					$dao->addParameter(":masterunkid", $result['settlementmasterunkid']);
					$dao->addParameter(":hotel_code", $this->hotelcode);    			
					$result1 = $dao->executeRow();
					$paymentmethod=$result1['name'];
				}
				else
				{
					$strSql1 = "select paymenttype,fasmastertype from cfpaymenttype Where hotel_code=:hotel_code AND paymenttypeunkid=:paymenttypeunkid ";	

					$dao->initCommand($strSql1);
					$dao->addParameter(":paymenttypeunkid", $result['settlementmasterunkid']);
					$dao->addParameter(":hotel_code", $this->hotelcode);    			
					$result1 = $dao->executeRow();
					$paymentmethod=$result1['fasmastertype']=='Cash'?'Cash':'Credit';
				}
			}
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getPaymentmethod - " . $e);
            $this->handleException($e);
        }
        return $paymentmethod;
    }
	//Nishit - 7 May 2020 - End	
	private function getSharerInfo($tranunkid) {
        try {
            $result = NULL;
            
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
	
	//Sanjay Waman - 10 Sep 2019 - Json format [CEN-1086] - Start
	private function generateGeneralErrorJson($code='', $msg='',$responsearr=array(),$dflag=0) {
        try {
			$responsearr['Errors']=array('ErrorCode'=>$code, 'ErrorMessage'=>$msg);
			header('Content-Type: application/json');
			if($dflag===1)
			{
				echo json_encode($responsearr,JSON_PRETTY_PRINT);
				exit;
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
            $dao = new dao();
            //Dharti Savaliya - START - 2020-12-21 Purpose :when we create multiple extra charges with same type and same day, we transfer only a single extra charge entry instead of all extra charges CEN-1792
            $strSql = "SELECT IFNULL(FORMAT(FD.baseamount/cfe.rate,0),0) AS qty,  FASM.name as chargeName,DATE(FD.trandate) as chargeDate, FD.detailunkid,".
		      " FD.baseamount AS netcharge,
              cfe.shortcode as Shortcode, FD.description as description ".
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
	        //Dharti Savaliya - END
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
	    
	    
	    $strSql = " SELECT cfroomtype.roomtypeunkid,cfroomtype.roomtype AS roomtype, cfroom.name AS room_name, cfratetype.ratetypeunkid, ".
		      " cfratetype.ratetype,cfroomrate_setting.roomrateunkid,cfroomrate_setting.display_name, ".                  
		      " fdrentalinfo.rentaldate AS rentaldate,fdrentalinfo.adult AS adult,fdrentalinfo.child AS child, ". 
		      " fasfoliodetail.detailunkid AS foliodetailunkid,fasfoliodetail.baseamount+SUM(IFNULL(b.baseamount,0)) AS netRate, ".
			  " fasfoliodetail.baseamount AS rentalRate ".
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
		      " GROUP BY fasfoliodetail.trandate,fasfoliodetail.detailunkid";//Sanjay Waman - 11 Oct 2018 - add Befor tax amount as rentalRate (request by innsoft) | Nishant - 4 Dec 2020 - OpenAPI - Purpose: added Room name (cfroom.name AS room_name)
	    
            $dao->initCommand($strSql);
            $dao->addParameter(":tranunkid", $tranunkid);
			$dao->addParameter(":hotel_code", $this->hotelcode);
            
            $result = $dao->executeQuery();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getDayWiseBookingInformation" . "-" . $e);
            $this->handleException($e);
        }
        return $result;
    }
		
    private function generateReservationXML($res, $bookings, $bookedbyInfo,$masterGuestInfo) 
	{
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateReservationXML");
            $reservation = $this->xmlDoc->createElement("Reservation");
            $bookedByInfo = $this->xmlDoc->createElement("BookByInfo");

            $locid = $this->xmlDoc->createElement("LocationId");
            $locid->appendChild($this->xmlDoc->createTextNode($this->hotelcode)); 
            $bookedByInfo->appendChild($locid);

            $unkid = $this->xmlDoc->createElement("UniqueID");  
                   
            //$this->log->logIt("Booking detail >>".print_r($bookings,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            
            $status_final_web = '';    
            if(isset($bookings[0]['webrateunkid']) && isset($bookings[0]['webinventoryunkid']) && $bookings[0]['webinventoryunkid'] != '' && $bookings[0]['webrateunkid'] != '')
            {
                $this->log->logIt("Web Booking for cancellation");
                $status_final_web = $this->checkbookingstatusforweb($res['reservationno'],$bookings[0]['webrateunkid'],$bookings[0]['webinventoryunkid']);
            }
            $this->log->logIt('status_final_web >>'.$status_final_web);
            
            $unkid->appendChild($this->xmlDoc->createTextNode($res['reservationno']));
            $bookedByInfo->appendChild($unkid);

            $bookedBy = $this->xmlDoc->createElement("BookedBy");
            $bookedBy->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($bookedbyInfo['name'])));
            $bookedByInfo->appendChild($bookedBy);

            $salutation = $this->xmlDoc->createElement("Salutation");
            $salutation->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['salutation']));
            $bookedByInfo->appendChild($salutation);

			$master_guest = explode(" ",$masterGuestInfo['name']);
			
			$first_name ='';
			$last_name = '';

			$first_name = $master_guest[0];
			unset($master_guest[0]);
			$last_name = implode(' ',$master_guest);

            $fname = $this->xmlDoc->createElement("FirstName");
            $fname->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($first_name)));
            $bookedByInfo->appendChild($fname);

            $lname = $this->xmlDoc->createElement("LastName");
            $lname->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($last_name)));
            $bookedByInfo->appendChild($lname);
			
			$gender = $this->xmlDoc->createElement("Gender");
            $gender->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['gender'])));
            $bookedByInfo->appendChild($gender);
			
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
            $country->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['country'])));
            $bookedByInfo->appendChild($country);
			
            $zipcode = $this->xmlDoc->createElement("Zipcode");
            $zipcode->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['zipcode']));
            $bookedByInfo->appendChild($zipcode);

            $phone = $this->xmlDoc->createElement("Phone");
            $phone->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['phone']));
            $bookedByInfo->appendChild($phone);
			
			$mobile = $this->xmlDoc->createElement("Mobile");
            $mobile->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['mobile']));
            $bookedByInfo->appendChild($mobile);
			
			$fax = $this->xmlDoc->createElement("Fax");
            $fax->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['fax']));
            $bookedByInfo->appendChild($fax);			
			
            $email = $this->xmlDoc->createElement("Email");
            $email->appendChild($this->xmlDoc->createTextNode($masterGuestInfo['email']));
            $bookedByInfo->appendChild($email);			
			
			$source = $this->xmlDoc->createElement("Source");
            $source->appendChild($this->xmlDoc->createTextNode($res['source']));
            $bookedByInfo->appendChild($source);
	    
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
			
            $bookings = $this->getBookings($res['reservationno']);
			$remainingdata = $this->getreservationdetails($res['reservationno'], $this->hotelcode);//Sanjay - 08 Sep 2018 - For Innsoft PMS
			$objSequenceDao = new sequencedao(); 
            foreach ($bookings as $booking)
			{
				//$this->log->logIt('Bookings >>'.print_r($booking,true));
                
                $bookingTranInfo = $this->xmlDoc->createElement("BookingTran");
                $subres = $this->xmlDoc->createElement("SubBookingId");
                $subres->appendChild($this->xmlDoc->createTextNode($booking['subreservationno']!=''?($res['reservationno']."-".$booking['subreservationno']):$res['reservationno'])); 
                $bookingTranInfo->appendChild($subres);
				$TranUnkid = $this->xmlDoc->createElement("TransactionId");
                $TranUnkid->appendChild($this->xmlDoc->createTextNode($booking['tranunkid']));
                $bookingTranInfo->appendChild($TranUnkid);
				
				//Sanjay - 08 Sep 2018 - For Innsoft PMS -START
				if(isset($remainingdata) && count($remainingdata)>0)
				{
					foreach($remainingdata as $key => $value)
					{
						//$this->log->logIt("Remaindata >>".print_r($value,true));
						$tranid = $value['tranunkid'];
						if($tranid == $booking['tranunkid'])
						{
							$promotionidk = $value['promotionunkid'];
							$webrateunkidk = $value['webrateunkid'];
							$webinventoryunkidk = $value['webinventoryunkid'];
							break;
						}
					}
				}
				if($webrateunkidk != NULL && $webinventoryunkidk != NULL && $webrateunkidk != '' && $webinventoryunkidk != '')
				{
					$promotiondetail = $this->getpromotiondetail($this->hotelcode,$booking['tranunkid'],$promotionidk);
					if(isset($promotiondetail) && count($promotiondetail) > 0 && $promotiondetail != '')
					{
						//$this->log->logIt('Promotiondetail >>'.print_r($promotiondetail,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
						
						$Promotiondetail = $this->xmlDoc->createElement("Promotiondetail");

						$ptype = $this->xmlDoc->createElement("Type");
						$ptype->appendChild($this->xmlDoc->createTextNode($promotiondetail['type']));
						$Promotiondetail->appendChild($ptype);
						
						$pname = $this->xmlDoc->createElement("Promotionname");
						$pname->appendChild($this->xmlDoc->createTextNode($promotiondetail['promotionname']));
						$Promotiondetail->appendChild($pname);
						
						$pamount = $this->xmlDoc->createElement("Amount");
						$pamount->appendChild($this->xmlDoc->createTextNode($promotiondetail['amount']));
						$Promotiondetail->appendChild($pamount);
						$bookingTranInfo->appendChild($Promotiondetail);
                
					}
				}
				//Sanjay - 08 Sep 2018 - END
                
                $Createdatetime = $this->xmlDoc->createElement("Createdatetime");
                $Createdatetime->appendChild($this->xmlDoc->createTextNode($booking['createdatetime']));
                $bookingTranInfo->appendChild($Createdatetime);
                
                
                $Modifydatetime = $this->xmlDoc->createElement("Modifydatetime");
                $modifytime = '';
               
                $statusid = $booking['statusid'];
                $this->log->logIt('Status id >>'.$statusid);
                if(($statusid != 6 && $statusid != 5) && $booking['status'] == "Modify")
                {
                    $modifytime = $this->getmodifydatetime($booking['tranunkid']);
                    $this->log->logIt('modifytime >>'.$modifytime);
                    $Modifydatetime->appendChild($this->xmlDoc->createTextNode($modifytime));
                }
                else
                {
                    $Modifydatetime->appendChild($this->xmlDoc->createTextNode($booking['createdatetime']));
                }
                $bookingTranInfo->appendChild($Modifydatetime);
                $TranStatus = $this->xmlDoc->createElement("Status");
                if(isset($status_final_web) && $status_final_web != null)
                {
                    $TranStatus->appendChild($this->xmlDoc->createTextNode($status_final_web));
                }
                else{    
                    $TranStatus->appendChild($this->xmlDoc->createTextNode($booking['status']));
                }
                $bookingTranInfo->appendChild($TranStatus);
                
				$TranGuarantee = $this->xmlDoc->createElement("IsConfirmed");
                $TranGuarantee->appendChild($this->xmlDoc->createTextNode($booking['isconfirmed']));
                $bookingTranInfo->appendChild($TranGuarantee);

								
                $voucherno = $this->xmlDoc->createElement("VoucherNo");
                $voucherno->appendChild($this->xmlDoc->createTextNode($booking['travelagentvoucherno']));
                $bookingTranInfo->appendChild($voucherno);

                $masterGuestInfo = $this->getContactInfo($booking['tranunkid'], "MasterGuest");
                
                $totalNetBookingAmount = $this->getNetRate($booking['tranunkid']);
                
                $totalDiscount = $this->getFasMasterTypeTotal('Discount', $booking['tranunkid']);
                
                $totalExtraCharges = $this->getFasMasterTypeTotal('Extra Charges', $booking['tranunkid']);
                
                $extraChargeInfo = $this->getExtraChargeInfo($booking['tranunkid']);				
                $dayWiseBookingInfo = $this->getDayWiseBookingInformation($booking['tranunkid']); 				              
				$totalPayment=$this->getTotalPayment($booking['tranunkid']);
                
                $amountBeforetax = $this->getFasMasterTypeTotal('Room Charges', $booking['tranunkid']);
                
                $totalTax = $this->getFasMasterTypeChildTotal('Room Charges', 'Tax', $booking['tranunkid']);
                
                $TaxDetail = $this->getTaxdetail('Room Charges', 'Tax', $booking['tranunkid']);
                
                $CurrencyCode = $this->getPropertyCurrencyCode();

                $totalCommision = '0';
                $this->log->logIt("TotalNetBookingAmount with Tax >>".$totalNetBookingAmount);
                $this->log->logIt("TotalDiscount >>".$totalDiscount);
                $this->log->logIt("TotalExtraCharges >>".$totalExtraCharges);
                $this->log->logIt("TotalPayment >>".$totalPayment);
                $this->log->logIt("Amountbeforetax >>".$amountBeforetax);
                $this->log->logIt("Totaltax >>".$totalTax);
                $this->log->logIt("Currencycode >>".$CurrencyCode);
                //$this->log->logIt("Tax Detail >>".print_r($TaxDetail, TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                
                $this->log->logIt("commision id>>".$booking['commisionid']);
                if(isset($booking['commisionid']) && $booking['commisionid'] == 300 )
                {
                    $totalCommision = $this->getcommision($booking['tranunkid']);
                }
                
                $this->log->logIt("Total commision >>".$totalCommision);
              
                $packagecode = $this->xmlDoc->createElement("PackageCode");
                $packagecode->appendChild($this->xmlDoc->createTextNode($dayWiseBookingInfo[0]['ratetypeunkid']));
                $bookingTranInfo->appendChild($packagecode);

                $pakcagename = $this->xmlDoc->createElement("PackageName");
                $pakcagename->appendChild($this->xmlDoc->createTextNode($dayWiseBookingInfo[0]['ratetype']));
                $bookingTranInfo->appendChild($pakcagename);

                $rateplancode = $this->xmlDoc->createElement("RateplanCode");
                $rateplancode->appendChild($this->xmlDoc->createTextNode($dayWiseBookingInfo[0]['roomrateunkid']));
                $bookingTranInfo->appendChild($rateplancode);

                $rateplanname = $this->xmlDoc->createElement("RateplanName");
                $rateplanname->appendChild($this->xmlDoc->createTextNode($dayWiseBookingInfo[0]['display_name']));
                $bookingTranInfo->appendChild($rateplanname);


                $roomtypecode = $this->xmlDoc->createElement("RoomTypeCode");
                $roomtypecode->appendChild($this->xmlDoc->createTextNode($booking['roomtypeunkid']));
                $bookingTranInfo->appendChild($roomtypecode);

                $roomtype = $this->xmlDoc->createElement("RoomTypeName");
                $roomtype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($booking['roomtype'])));
                $bookingTranInfo->appendChild($roomtype);

                $arrivaldate = $this->xmlDoc->createElement("Start");
                $arrivaldate->appendChild($this->xmlDoc->createTextNode(date("Y-m-d", strtotime($booking['arrivaldate']))));//Sanjay waman - 11 Oct 2018 - only display date (requested by innsoft)
                $bookingTranInfo->appendChild($arrivaldate);

                $departuredate = $this->xmlDoc->createElement("End");
                $departuredate->appendChild($this->xmlDoc->createTextNode(date("Y-m-d", strtotime($booking['departuredate']))));//Sanjay waman - 11 Oct 2018 - only display date (requested by innsoft)
                $bookingTranInfo->appendChild($departuredate);
				
				//Sanjay Waman - 11 Oct 2018 - Add arrival and departure time - Start
				$arrivaldate = $this->xmlDoc->createElement("ArrivalTime");
                $arrivaldate->appendChild($this->xmlDoc->createTextNode(date("H:i:s", strtotime($booking['arrivaldate']))));
                $bookingTranInfo->appendChild($arrivaldate);

                $departuredate = $this->xmlDoc->createElement("DepartureTime");
                $departuredate->appendChild($this->xmlDoc->createTextNode(date("H:i:s", strtotime($booking['departuredate']))));
                $bookingTranInfo->appendChild($departuredate);
				//Sanjay Waman - End
                
                $currencycode = $this->xmlDoc->createElement("CurrencyCode");
                $currencycode->appendChild($this->xmlDoc->createTextNode($CurrencyCode));
                $bookingTranInfo->appendChild($currencycode);
                
                $totalrate = $this->xmlDoc->createElement("TotalAmountAfterTax");
                $totalrate->appendChild($this->xmlDoc->createTextNode(number_format(floatval($totalNetBookingAmount), 2, '.', '')));
                $bookingTranInfo->appendChild($totalrate);
                
                $amountbeforetax = $this->xmlDoc->createElement("TotalAmountBeforeTax");
                $amountbeforetax->appendChild($this->xmlDoc->createTextNode(number_format(floatval($amountBeforetax), 2, '.', '')));
                $bookingTranInfo->appendChild($amountbeforetax);
                
                $totaltax = $this->xmlDoc->createElement("TotalTax");
                $totaltax->appendChild($this->xmlDoc->createTextNode(number_format(floatval($totalTax), 2, '.', '')));
                $bookingTranInfo->appendChild($totaltax);
                
                $totaldiscount = $this->xmlDoc->createElement("TotalDiscount");
                $totaldiscount->appendChild($this->xmlDoc->createTextNode(number_format(floatval($totalDiscount), 2, '.', '')));
                $bookingTranInfo->appendChild($totaldiscount);

                $totalextracharges = $this->xmlDoc->createElement("TotalExtraCharge");
                $totalextracharges->appendChild($this->xmlDoc->createTextNode(number_format(floatval($totalExtraCharges), 2, '.', '')));
                $bookingTranInfo->appendChild($totalextracharges);

                $totalpayment = $this->xmlDoc->createElement("TotalPayment");
                $totalpayment->appendChild($this->xmlDoc->createTextNode(number_format(floatval($totalPayment), 2, '.', '')));
                $bookingTranInfo->appendChild($totalpayment);
                
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
				$first_name = $master_guest[0];
				unset($master_guest[0]);
				$last_name = implode(' ',$master_guest);

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
				$nationality->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['nationality'])));
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
                $country->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($masterGuestInfo['country'])));
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
                $source->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($booking['source'])));
                $bookingTranInfo->appendChild($source);

                $comment = $this->xmlDoc->createElement("Comment");				
                $comment->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($booking['specreq'])));
                $bookingTranInfo->appendChild($comment);
		
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
                
                if($booking['paymenttype'] == 'Zeamster Booking Engine' && isset($booking['webrateunkid']) && isset($booking['webinventoryunkid']) && $booking['webinventoryunkid'] != '' && $booking['webrateunkid'] != '')
                {
                    $zeamster_detail = $this->getdetailforzeamster($booking['tranunkid']);
                    //$this->log->logIt("Zeamster_detail >>".print_r($zeamster_detail,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                    $tokenid = isset($zeamster_detail[0]['token_id'])?$zeamster_detail[0]['token_id'].'':"";
                    $zcardtype = isset($zeamster_detail[0]['cardtype'])?$zeamster_detail[0]['cardtype'].'':"";
                    $expdate = isset($zeamster_detail[0]['expdate'])?$zeamster_detail[0]['expdate'].'':"";
                    $alias = isset($zeamster_detail[0]['account_vault_id'])?$zeamster_detail[0]['account_vault_id'].'':"";
                    
                    $cardcode = $this->xmlDoc->createElement("CardCode");
                    $cardcode->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($tokenid)));
                    $bookingTranInfo->appendChild($cardcode);
                            
                    $cardnumber = $this->xmlDoc->createElement("CCNo");
                    $cardnumber->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($alias)));
                    $bookingTranInfo->appendChild($cardnumber);
                            
                    $cardtype = $this->xmlDoc->createElement("CCType");
                    $cardtype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($zcardtype)));
                    $bookingTranInfo->appendChild($cardtype);
                            
                    $ccexpirydate = $this->xmlDoc->createElement("CCExpiryDate");
                    $ccexpirydate->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($expdate)));
                    $bookingTranInfo->appendChild($ccexpirydate);
                    
                }
                else
                {	
                    $cc_no='';
                    $cc_type='';
                    $cc_expirydate_original='';
                    $cc_holdersname='';
                    $token=$enctoken='';
                    
                    $sequence=array();
                    if($booking['channelbookingunkid']!='' && $booking['ruid_status']!='' && $booking['ruid']!='')
                        $sequence = $objSequenceDao->getsequence($booking['ruid_status'],$booking['channelbookingunkid'],$booking['ruid']);
                    
                    if(!empty($sequence)) //Dharti Savaliya 2020-02-10 Purpose : 6 to PHP7.2 changes CEN-1017 
                    {
                        //Expiry Date
                        $cc_expirydate_original = isset($sequence->expirydate) ? $sequence->expirydate : '';
                        
                        //CCType
                        $cc_type = isset($sequence->type) ? $sequence->type : '';
                        
                        //Name On Card
                        $cc_holdersname = isset($sequence->name_on_card) ? $sequence->name_on_card : '';
                        
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
                    
                    $cc_expirydate = '';
                    if(isset($cc_expirydate_original) && $cc_expirydate_original != '')
                    {
                        //$cc_expirydate_original = '0120';					
                       // $this->log->logIt("CC expiry date original which is get from token server----> ".$cc_expirydate_original);
                        $objMasterDao2 = new masterdao_2();
                        $cc_expirydate = $objMasterDao2->CCExpiryDateFormat($cc_expirydate_original);
                        
                    }
                   
                    $ccinfo = $this->xmlDoc->createElement("CCLink");		
                    $ccinfo->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($enctoken!=''?base64_encode($enctoken):'')));
                    $bookingTranInfo->appendChild($ccinfo);
                                    
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
                }
                
                foreach($TaxDetail as $Taxdeatil)
                {
                     
                    $taxdeatil = $this->xmlDoc->createElement("TaxDeatil");

                    $taxCode = $this->xmlDoc->createElement("TaxCode");
                    $taxCode->appendChild($this->xmlDoc->createTextNode($Taxdeatil['shortcode']));
                    $taxdeatil->appendChild($taxCode);
                    
                    $taxname = $this->xmlDoc->createElement("TaxName");
                    $taxname->appendChild($this->xmlDoc->createTextNode($Taxdeatil['tax']));
                    $taxdeatil->appendChild($taxname);
                    
                    $taxamount = $this->xmlDoc->createElement("TaxAmount");
                    $taxamount->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($Taxdeatil['TaxAmount'])));
                    $taxdeatil->appendChild($taxamount);
                    
                }
                foreach ($extraChargeInfo as $extracharge)
				{
                    $extraCharge = $this->xmlDoc->createElement("ExtraCharge");

                    $chargedate = $this->xmlDoc->createElement("ChargeDate");
                    $chargedate->appendChild($this->xmlDoc->createTextNode($extracharge['chargeDate']));
                    $extraCharge->appendChild($chargedate);
                    
                    $chargecode = $this->xmlDoc->createElement("ChargeCode");
                    $chargecode->appendChild($this->xmlDoc->createTextNode($extracharge['Shortcode']));
                    $extraCharge->appendChild($chargecode);
                    
                    $chargename = $this->xmlDoc->createElement("ChargeName");
                    $chargename->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($extracharge['chargeName'])));
                    $extraCharge->appendChild($chargename);
                    
                    $chargedesc = $this->xmlDoc->createElement("ChargeDesc");
                    $chargedesc->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($extracharge['description'])));
                    $extraCharge->appendChild($chargedesc);

                    $remark = $this->xmlDoc->createElement("Remark");					
                    $remark->appendChild($this->xmlDoc->createTextNode(''));
                    $extraCharge->appendChild($remark);

                    $qty = $this->xmlDoc->createElement("Quantity");
                    $qty->appendChild($this->xmlDoc->createTextNode($extracharge['qty']));
                    $extraCharge->appendChild($qty);
					
				    $ExtrachargeTaxDetails = $this->ExtrachargeTaxDetails($this->hotelcode,$booking['tranunkid'],$extracharge['chargeName'],$extracharge['chargeDate']);
					$chargedate = $this->xmlDoc->createElement("AmountBeforeTax");
                    $chargedate->appendChild($this->xmlDoc->createTextNode(number_format(floatval($ExtrachargeTaxDetails), 2, '.', '')));
                    $extraCharge->appendChild($chargedate);
					
					$chargedate = $this->xmlDoc->createElement("AmountAfterTax");
                    $chargedate->appendChild($this->xmlDoc->createTextNode(number_format(floatval($extracharge['netcharge']), 2, '.', '')));
                    $extraCharge->appendChild($chargedate);
					
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
                    $roomtype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($rental['roomtype'])));
                    $rentalInfo->appendChild($roomtype);

                    $adult = $this->xmlDoc->createElement("Adult");
                    $adult->appendChild($this->xmlDoc->createTextNode($rental['adult']));
                    $rentalInfo->appendChild($adult);

                    $child = $this->xmlDoc->createElement("Child");
                    $child->appendChild($this->xmlDoc->createTextNode($rental['child']));
                    $rentalInfo->appendChild($child);
					
					//Sanjay Waman - 11 Oct 2018 - added befor tax amount (request by innsoft) -Start
					$rent = $this->xmlDoc->createElement("RentPreTax");
                    $rent->appendChild($this->xmlDoc->createTextNode(number_format(floatval($rental['rentalRate']), 2, '.', '')));
                    $rentalInfo->appendChild($rent);
					//Sanjay Waman - End

                    $rent = $this->xmlDoc->createElement("Rent");
                    $rent->appendChild($this->xmlDoc->createTextNode(number_format(floatval($rental['netRate']), 2, '.', '')));
                    $rentalInfo->appendChild($rent);

                    $discount = $this->xmlDoc->createElement("Discount");
                    $discount->appendChild($this->xmlDoc->createTextNode(number_format(floatval($this->getDiscountAmount($rental['foliodetailunkid'])), 2, '.', '')));
                    $rentalInfo->appendChild($discount);

                    $bookingTranInfo->appendChild($rentalInfo);
                }
				
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
					$nationality->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['nationality'])));
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
					$country->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sharer['country'])));
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
				
                $bookedByInfo->appendChild($bookingTranInfo);
            }           
            $reservation->appendChild($bookedByInfo);
            
            //$this->log->logIt("validXML >>".$this->xmlDoc->saveXML($reservation));
            $validXML = simplexml_load_string($this->xmlDoc->saveXML($reservation));
			
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
 
	//Sanjay Waman - 28 May 2019 - Changes for json format - Start
	private function generateReservationJSON($res, $bookings, $bookedbyInfo,$masterGuestInfo,$paymentmethod='', $reqType='') //Nishit - 7 May 2020 - Purpose: Added $paymentmethod for Open API [ABS-5175] | // Nishant - 21 May 2020 - add reqType ArrivalList/ DepartureList - Open API [ABS-5175]
	{
        try
		{
			
            $mongo_detail = array();
			$bookedByInfoarr = array();
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateReservationJSON");
		    $bookedByInfoarr['BookingTran'] = array(); //Sanjay : 12 March 2020 : PHP 7 Change
			$bookedByInfoarr['LocationId'] = $this->hotelcode.'';
			$bookedByInfoarr['UniqueID'] = trim($res['reservationno']);
			$bookedByInfoarr['BookedBy'] = trim($bookedbyInfo['name']);
			$bookedByInfoarr['Salutation'] = trim($masterGuestInfo['salutation']);
                  
            //$this->log->logIt("Booking detail >>".print_r($bookings,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]

			$master_guest = explode(" ",$masterGuestInfo['name']);
			
			$first_name ='';
			$last_name = '';

			$first_name = $master_guest[0];
			unset($master_guest[0]);
			$last_name = implode(' ',$master_guest);
			
			$bookedByInfoarr['FirstName'] = trim($first_name);
			$bookedByInfoarr['LastName'] = trim($last_name);
			$bookedByInfoarr['Gender'] = trim($masterGuestInfo['gender']);
			$bookedByInfoarr['Address'] = trim($masterGuestInfo['address']);
			$bookedByInfoarr['City'] = trim($masterGuestInfo['city']);
			$bookedByInfoarr['State'] = trim($masterGuestInfo['state']);
			$bookedByInfoarr['Country'] = trim($masterGuestInfo['country']);
			$bookedByInfoarr['Zipcode'] = trim($masterGuestInfo['zipcode']);
			$bookedByInfoarr['Phone'] = trim($masterGuestInfo['phone']);
			$bookedByInfoarr['Mobile'] = trim($masterGuestInfo['mobile']);
			$bookedByInfoarr['Fax'] = trim($masterGuestInfo['fax']);
			$bookedByInfoarr['Email'] = trim($masterGuestInfo['email']);
			$bookedByInfoarr['Source'] = trim($res['source']);
			//Nishit - 7 May 2020 - Start - Purpose: Added $paymentmethod for Open API [ABS-5175]
			if($paymentmethod!='')
				$bookedByInfoarr['PaymentMethod'] = trim($paymentmethod);
			//Nishit - 7 May 2020 - End
			if(isset($res['bookingtype']))
			{
				if($res['bookingtype']=='CHANNEL')
					$bookedByInfoarr['IsChannelBooking'] = "1";
				else
					$bookedByInfoarr['IsChannelBooking'] = "0";
			}
			else
				$bookedByInfoarr['IsChannelBooking'] = "";
			
				
			//Nishant - 26 May 2020 - OpenAPI(Abs-5175) - for ArrivalList/ DepartureList - Start
			if($reqType == 'ArrivalList' || $reqType == 'DepartureList'){
				$bookings = $this->getBookings('',$res['tranunkid'], $reqType); //Nishant - 20 May 2020 - OpenAPI(Abs-5175) - Parameter : tranunkid and reqType added for ArrivalList/ DepartureList
				$remainingdata = $this->getreservationdetails('', $this->hotelcode,'',$res['tranunkid']); //Nishant - 20 May 2020 - OpenAPI(Abs-5175) - Parameter : tranunkid added for ArrivalList/ DepartureList
			}else{
				$bookings = $this->getBookings($res['reservationno'],$res['tranunkid'], $reqType); //Nishant - 20 May 2020 - OpenAPI(Abs-5175) - Parameter : tranunkid and reqType added for ArrivalList/ DepartureList
				$remainingdata = $this->getreservationdetails($res['reservationno'], $this->hotelcode); 
			}
			//Nishant - 26 May 2020 - OpenAPI(Abs-5175) - for ArrivalList/ DepartureList - End

			$objSequenceDao = new sequencedao();
			$roomcount=0;
			$webrateunkidk = '';
			$webinventoryunkidk = '';
			$status_final_web = '';

			//Nishant - 5 Dec 2020 - Purpose: get current status of Booking for OpenAPI - Start
			$statusNameArr = array();
			if($reqType == 'ArrivalList' || $reqType == 'DepartureList'){
				$dao = new dao();       
				$strSql ='select statusunkid,displaystatus as status from fdrentalstatus where hotel_code= :hotel_code';
				$dao->initCommand($strSql);
				$dao->addParameter(":hotel_code", $this->hotelcode);
				$statusNameArr = $dao->executeKeyValueQuery();
				//$this->log->logIt("---------------");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt($statusNameArr);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			}
			//Nishant - 5 Dec 2020 - End

            foreach ($bookings as $booking)
			{
				//$this->log->logIt('Bookings >>'.print_r($booking,true));
                $bookingTranarr = array();
				$bookingTranarr['SubBookingId'] = trim(($booking['subreservationno']!='')?($res['reservationno']."-".$booking['subreservationno']):$res['reservationno']);
              
				$bookingTranarr['TransactionId'] = trim($booking['tranunkid']);
				
				if(isset($remainingdata) && count($remainingdata)>0)
				{
					foreach($remainingdata as $key => $value)
					{
						$tranid = $value['tranunkid'];
						if($tranid == $booking['tranunkid'])
						{
							$promotionidk = $value['promotionunkid'];
							$webrateunkidk = $value['webrateunkid'];
							$webinventoryunkidk = $value['webinventoryunkid'];
							break;
						}
					}
				}
				if($webrateunkidk != NULL && $webinventoryunkidk != NULL && $webrateunkidk != '' && $webinventoryunkidk != '')
				{
					$promotiondetail = $this->getpromotiondetail($this->hotelcode,$booking['tranunkid'],$promotionidk);
					if(isset($promotiondetail) && count($promotiondetail) > 0 && $promotiondetail != '')
					{
						//$this->log->logIt('Promotiondetail >>'.print_r($promotiondetail,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
						$bookingTranarr['Promotiondetail']['type'] = trim($promotiondetail['type']);
						$bookingTranarr['Promotiondetail']['Promotionname'] = trim($promotiondetail['promotionname']);
						$bookingTranarr['Promotiondetail']['Amount'] = trim($promotiondetail['amount']);       
					}
				}
                
				$bookingTranarr['Createdatetime'] = trim($booking['createdatetime']); 
                $modifytime = '';
                $statusid = $booking['statusid'];
                $this->log->logIt('Status id >>'.$statusid);
                if(($statusid != 6 && $statusid != 5) && $booking['status'] == "Modify")
                {
                    $modifytime = $this->getmodifydatetime($booking['tranunkid']);
                    $this->log->logIt('modifytime >>'.$modifytime);
                    $bookingTranarr['Modifydatetime'] = trim($modifytime); 
                }
                else
                {
					$bookingTranarr['Modifydatetime'] = trim($booking['createdatetime']); 
                }
				
				$bookingTranarr['Status'] = '';
				if(isset($status_final_web) && $status_final_web != null)
                {
					$bookingTranarr['Status'] = trim($status_final_web);
                }
                else{
					$bookingTranarr['Status'] = trim($booking['status']);
                }
				
				//Nishant - 5 Dec 2020 - Purpose: get current status of Booking for OpenAPI - Start
				if(($reqType == 'ArrivalList' || $reqType == 'DepartureList') && !empty($statusNameArr)){
					$bookingTranarr['currentstatus'] = $statusNameArr[$booking['statusid']]; 
				}
				//Nishant - 5 Dec 2020 - End

                $bookingTranarr['IsConfirmed'] = trim($booking['isconfirmed']);
				$bookingTranarr['VoucherNo'] = trim($booking['travelagentvoucherno']);
				$masterGuestInfo = $this->getContactInfo($booking['tranunkid'], "MasterGuest");
                
                $totalNetBookingAmount = $this->getNetRate($booking['tranunkid']);
                
                $totalDiscount = $this->getFasMasterTypeTotal('Discount', $booking['tranunkid']);
                
                $totalExtraCharges = $this->getFasMasterTypeTotal('Extra Charges', $booking['tranunkid']);
                
                $extraChargeInfo = $this->getExtraChargeInfo($booking['tranunkid']);				
				$dayWiseBookingInfo = $this->getDayWiseBookingInformation($booking['tranunkid']); 	
				$totalPayment=$this->getTotalPayment($booking['tranunkid']);
                
                $amountBeforetax = $this->getFasMasterTypeTotal('Room Charges', $booking['tranunkid']);
                
                $totalTax = $this->getFasMasterTypeChildTotal('Room Charges', 'Tax', $booking['tranunkid']);
                
                $TaxDetail = $this->getTaxdetail('Room Charges', 'Tax', $booking['tranunkid']);
                
                $CurrencyCode = $this->getPropertyCurrencyCode();

                $totalCommision = '0';
                $this->log->logIt("TotalNetBookingAmount with Tax >>".$totalNetBookingAmount);
                $this->log->logIt("TotalDiscount >>".$totalDiscount);
                $this->log->logIt("TotalExtraCharges >>".$totalExtraCharges);
                $this->log->logIt("TotalPayment >>".$totalPayment);
                $this->log->logIt("Amountbeforetax >>".$amountBeforetax);
                $this->log->logIt("Totaltax >>".$totalTax);
                $this->log->logIt("Currencycode >>".$CurrencyCode);
                //$this->log->logIt("Tax Detail >>".print_r($TaxDetail, TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                
                $this->log->logIt("commision id>>".$booking['commisionid']);
                if(isset($booking['commisionid']) && $booking['commisionid'] == 300 )
                {
                    $totalCommision = $this->getcommision($booking['tranunkid']);
                }
                
                $this->log->logIt("Total commision >>".$totalCommision);
              
				$bookingTranarr['PackageCode'] = trim($dayWiseBookingInfo[0]['ratetypeunkid']);
				$bookingTranarr['PackageName'] = trim($dayWiseBookingInfo[0]['ratetype']);
				$bookingTranarr['RateplanCode'] = trim($dayWiseBookingInfo[0]['roomrateunkid']);
				$bookingTranarr['RateplanName'] = trim($dayWiseBookingInfo[0]['display_name']);
				
				$bookingTranarr['RoomTypeCode'] = trim($booking['roomtypeunkid']);
				$bookingTranarr['RoomTypeName'] = trim($booking['roomtype']);
				
				
				$bookingTranarr['Start'] = trim(date("Y-m-d", strtotime($booking['arrivaldate'])));
				$bookingTranarr['End'] = trim(date("Y-m-d", strtotime($booking['departuredate'])));
				
				$bookingTranarr['ArrivalTime'] = trim(date("H:i:s", strtotime($booking['arrivaldate'])));
				$bookingTranarr['DepartureTime'] = trim(date("H:i:s", strtotime($booking['departuredate'])));
				
				$bookingTranarr['CurrencyCode'] = trim($CurrencyCode);
				$bookingTranarr['TotalAmountAfterTax'] = trim(number_format(floatval($totalNetBookingAmount), 2, '.', ''));
				$bookingTranarr['TotalAmountBeforeTax'] = trim(number_format(floatval($amountBeforetax), 2, '.', ''));
				$bookingTranarr['TotalTax'] = trim(number_format(floatval($totalTax), 2, '.', ''));
				
				$bookingTranarr['TotalDiscount'] = trim(number_format(floatval($totalDiscount), 2, '.', ''));
				$bookingTranarr['TotalExtraCharge'] = trim(number_format(floatval($totalExtraCharges), 2, '.', ''));
				$bookingTranarr['TotalPayment'] = trim(number_format(floatval($totalPayment), 2, '.', ''));
				$bookingTranarr['TACommision'] = trim(number_format(floatval($totalCommision), 2, '.', ''));
				$bookingTranarr['Salutation'] = trim($masterGuestInfo['salutation']);
				

				$master_guest = explode(" ",$masterGuestInfo['name']);
				$first_name ='';
				$last_name = '';
				$first_name = $master_guest[0];
				unset($master_guest[0]);
				$last_name = implode(' ',$master_guest);

				$bookingTranarr['FirstName'] = trim($first_name);
                $bookingTranarr['LastName'] = trim($last_name);
				
				if($masterGuestInfo['salutation']!='')
				{
					if($masterGuestInfo['salutation']=='MRS' || $masterGuestInfo['salutation']=='MS' || $masterGuestInfo['salutation']=='MAM')
						$gender="Female";
					else
						$gender="Male";
				}
				else
					$gender="Other";
				
				$bookingTranarr['Gender'] = trim($masterGuestInfo['gender']!=''?$masterGuestInfo['gender']:$gender);
				
				
				$bookingTranarr['DateOfBirth'] = trim($masterGuestInfo['birthdate']);
				$bookingTranarr['SpouseDateOfBirth'] = trim($masterGuestInfo['spousebirthdate']);
				$bookingTranarr['WeddingAnniversary'] = trim($masterGuestInfo['anniversary']);
				$bookingTranarr['Address'] = trim($masterGuestInfo['address']);
				$bookingTranarr['City'] = trim($masterGuestInfo['city']);
				$bookingTranarr['State'] = trim($masterGuestInfo['state']);
				$bookingTranarr['Country'] = trim($masterGuestInfo['country']);
				$bookingTranarr['Nationality'] = trim($masterGuestInfo['nationality']);
				$bookingTranarr['Zipcode'] = trim($masterGuestInfo['zipcode']);
				$bookingTranarr['Phone'] = trim($masterGuestInfo['phone']);
				$bookingTranarr['Mobile'] = trim($masterGuestInfo['mobile']);
				$bookingTranarr['Fax'] = trim($masterGuestInfo['fax']);
				$bookingTranarr['Email'] = trim($masterGuestInfo['email']);
				$bookingTranarr['IdentiyType'] = trim($masterGuestInfo['identitytype']);
				$bookingTranarr['IdentityNo'] = trim($masterGuestInfo['identityno']);
				$bookingTranarr['ExpiryDate'] = trim($masterGuestInfo['expirydate']);
				$bookingTranarr['TransportationMode'] = trim($masterGuestInfo['transportationmode']);
				$bookingTranarr['Vehicle'] = trim($masterGuestInfo['pickupvehicle']);
				
				
				$transportdate=$transporttime='';
				if($masterGuestInfo['pickupdatetime']!='')
					list($transportdate,$transporttime)=explode(" ",$masterGuestInfo['pickupdatetime']);
							
				$bookingTranarr['PickupDate'] = trim($transportdate);
				$bookingTranarr['PickupTime'] = trim($transporttime);
				
				
				$bookingTranarr['Source'] = trim($booking['source']);
				$bookingTranarr['Comment'] = trim($booking['specreq']);
				
				$bookingTranarr['AffiliateCode'] = $bookingTranarr['AffiliateName'] = "";
				
				if($booking['affiliateunkid']!='')
				{
					$affiliateInfo = array();	
					$affiliateInfo =  $this->getAffiliateInfo($booking['affiliateunkid']);
					
					if(isset($affiliateInfo[0]))
					{
						if(isset($affiliateInfo[0]['name']))
							$bookingTranarr['AffiliateName'] = trim($affiliateInfo[0]['name']);
						
					
						if(isset($affiliateInfo[0]['affiliate_code']))
							$bookingTranarr['AffiliateCode'] = trim($affiliateInfo[0]['affiliate_code']);
						
					}
				}
				
                
                if($booking['paymenttype'] == 'Zeamster Booking Engine' && isset($booking['webrateunkid']) && isset($booking['webinventoryunkid']) && $booking['webinventoryunkid'] != '' && $booking['webrateunkid'] != '')
                {
                    $zeamster_detail = $this->getdetailforzeamster($booking['tranunkid']);
                    //$this->log->logIt("Zeamster_detail >>".print_r($zeamster_detail,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                    $tokenid = isset($zeamster_detail[0]['token_id'])?$zeamster_detail[0]['token_id'].'':"";
                    $zcardtype = isset($zeamster_detail[0]['cardtype'])?$zeamster_detail[0]['cardtype'].'':"";
                    $expdate = isset($zeamster_detail[0]['expdate'])?$zeamster_detail[0]['expdate'].'':"";
                    $alias = isset($zeamster_detail[0]['account_vault_id'])?$zeamster_detail[0]['account_vault_id'].'':"";
					
					$bookingTranarr['CardCode'] = trim($tokenid);
					$bookingTranarr['CCNo'] = trim($alias);
					$bookingTranarr['CCType'] = trim($zcardtype);
					$bookingTranarr['CCExpiryDate'] = trim($expdate);
                }
                else
                {	
                    $cc_no='';
                    $cc_type='';
                    $cc_expirydate_original='';
                    $cc_holdersname='';
                    $token=$enctoken='';
                    
                    $sequence=array();
                    if($booking['channelbookingunkid']!='' && $booking['ruid_status']!='' && $booking['ruid']!='')
                        $sequence = $objSequenceDao->getsequence($booking['ruid_status'],$booking['channelbookingunkid'],$booking['ruid']);
                    
                    if(count($sequence)>0) // Dharti Savaliya 10-02-2020 - Purpose : PHP5.6 to 7.2 CEN-1017
                    {
                        //Expiry Date
                        $cc_expirydate_original = isset($sequence->expirydate) ? $sequence->expirydate : '';
                        
                        //CCType
                        $cc_type = isset($sequence->type) ? $sequence->type : '';
                        
                        //Name On Card
                        $cc_holdersname = isset($sequence->name_on_card) ? $sequence->name_on_card : '';
                        
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
                    
                    $cc_expirydate = '';
                    if(isset($cc_expirydate_original) && $cc_expirydate_original != '')
                    {
                        //$cc_expirydate_original = '0120';					
                       // $this->log->logIt("CC expiry date original which is get from token server----> ".$cc_expirydate_original);
                        $objMasterDao2 = new masterdao_2();
                        $cc_expirydate = $objMasterDao2->CCExpiryDateFormat($cc_expirydate_original);
                        
                    }
                   
				    $bookingTranarr['CCLink'] = trim(($enctoken!='')?base64_encode($enctoken):'');
					$bookingTranarr['CCNo'] = trim($cc_no);
					$bookingTranarr['CCType'] = trim($cc_type);
					$bookingTranarr['CCExpiryDate'] = trim($cc_expirydate);
					$bookingTranarr['CardHoldersName'] = trim($cc_holdersname);			
                }
                
				if(count($TaxDetail)>0)
				{
					$tc=0;
					foreach($TaxDetail as $Taxdeatil)
					{
						 
						$bookingTranarr['TaxDeatil'][$tc]['TaxCode'] = trim($Taxdeatil['shortcode']);
						$bookingTranarr['TaxDeatil'][$tc]['TaxName'] = trim($Taxdeatil['tax']);
						$bookingTranarr['TaxDeatil'][$tc]['TaxAmount'] = trim($Taxdeatil['TaxAmount']);
						$tc++;
					}
				}
				
				if(count($extraChargeInfo)>0)
				{
					$tc=0;
					foreach($extraChargeInfo as $extracharge)
					{
						 
						$bookingTranarr['ExtraCharge'][$tc]['ChargeDate'] = trim($extracharge['chargeDate']);
						$bookingTranarr['ExtraCharge'][$tc]['ChargeCode'] = trim($extracharge['Shortcode']);
						$bookingTranarr['ExtraCharge'][$tc]['ChargeName'] = trim($extracharge['chargeName']);
						$bookingTranarr['ExtraCharge'][$tc]['ChargeDesc'] = trim($extracharge['chargeName']);
						$bookingTranarr['ExtraCharge'][$tc]['Remark'] = trim($extracharge['chargeName']);
						$bookingTranarr['ExtraCharge'][$tc]['Quantity'] = trim($extracharge['qty']);
						
						 $ExtrachargeTaxDetails = $this->ExtrachargeTaxDetails($this->hotelcode,$booking['tranunkid'],$extracharge['chargeName'],$extracharge['chargeDate']);
						 
						$bookingTranarr['ExtraCharge'][$tc]['AmountBeforeTax'] = trim(number_format(floatval($ExtrachargeTaxDetails), 2, '.', ''));
						$bookingTranarr['ExtraCharge'][$tc]['AmountAfterTax'] = trim(number_format(floatval($extracharge['netcharge']), 2, '.', ''));
						$tc++;
					}
				}
				
				if(count($dayWiseBookingInfo)>0)
				{
					$tc=0;
					foreach ($dayWiseBookingInfo as $rental)
					{
						 
						$bookingTranarr['RentalInfo'][$tc]['EffectiveDate'] = trim($rental['rentaldate']);
						$bookingTranarr['RentalInfo'][$tc]['PackageCode'] = trim($rental['ratetypeunkid']);
						$bookingTranarr['RentalInfo'][$tc]['PackageName'] = trim($rental['ratetype']);
						$bookingTranarr['RentalInfo'][$tc]['RoomTypeCode'] = trim($rental['roomtypeunkid']);
						$bookingTranarr['RentalInfo'][$tc]['RoomTypeName'] = trim($rental['roomtype']);
						//Nishant - 7 Dec 2020 - OpenAPI - Purpose: Room Number in both arrival and departure List APIs - Start
						if($reqType == 'ArrivalList' || $reqType == 'DepartureList'){
							$bookingTranarr['RentalInfo'][$tc]['RoomName'] = trim($rental['room_name']);
						}
						//Nishant - 7 Dec 2020 - End
						
						$bookingTranarr['RentalInfo'][$tc]['Adult'] = trim($rental['adult']);
						$bookingTranarr['RentalInfo'][$tc]['Child'] = trim($rental['child']);
						$bookingTranarr['RentalInfo'][$tc]['RentPreTax'] = trim(number_format(floatval($rental['rentalRate']), 2, '.', ''));
						$bookingTranarr['RentalInfo'][$tc]['Rent'] = trim(number_format(floatval($rental['netRate']), 2, '.', ''));
						$bookingTranarr['RentalInfo'][$tc]['Discount'] = trim(number_format(floatval($this->getDiscountAmount($rental['foliodetailunkid'])), 2, '.', ''));
						$tc++;
					}
				}
                
				
				$sharerInfo = array();	
				$sharerInfo =  $this->getSharerInfo($booking['tranunkid']);
				
				if(count($sharerInfo)>0)
				{
					$tc=0;
					foreach ($sharerInfo as $sharer)
					{
						$bookingTranarr['Sharer'][$tc]['Salutation'] = trim($sharer['salutation']);
						$master_guest = explode(" ",$sharer['name']);
						$first_name ='';
						$last_name = '';
						$first_name = $master_guest[0];
						unset($master_guest[0]);
						$last_name = implode(' ',$master_guest);
		
						$bookingTranarr['Sharer'][$tc]['FirstName'] = trim($first_name);
						$bookingTranarr['Sharer'][$tc]['LastName'] = trim($last_name);
						
						if($sharer['salutation']!='')
						{
							if($sharer['salutation']=='MRS' || $sharer['salutation']=='MS' || $sharer['salutation']=='MAM')
								$gender="Female";
							else
								$gender="Male";
						}
						else
							$gender="Other";
							
						$bookingTranarr['Sharer'][$tc]['Gender'] = trim(($sharer['gender']!='')?$sharer['gender']:$gender);
						
						
						$bookingTranarr['Sharer'][$tc]['DateOfBirth'] = trim($sharer['birthdate']);
						$bookingTranarr['Sharer'][$tc]['SpouseDateOfBirth'] = trim($sharer['spousebirthdate']);
						$bookingTranarr['Sharer'][$tc]['WeddingAnniversary'] = trim($sharer['anniversary']);
						$bookingTranarr['Sharer'][$tc]['Nationality'] = trim($sharer['nationality']);
						$bookingTranarr['Sharer'][$tc]['Address'] = trim($sharer['address']);
						$bookingTranarr['Sharer'][$tc]['City'] = trim($sharer['city']);
						$bookingTranarr['Sharer'][$tc]['State'] = trim($sharer['state']);
						$bookingTranarr['Sharer'][$tc]['Country'] = trim($sharer['country']);
						$bookingTranarr['Sharer'][$tc]['Zipcode'] = trim($sharer['zipcode']);
						$bookingTranarr['Sharer'][$tc]['Phone'] = trim($sharer['phone']);
						$bookingTranarr['Sharer'][$tc]['Mobile'] = trim($sharer['mobile']);
						$bookingTranarr['Sharer'][$tc]['Fax'] = trim($sharer['fax']);
						$bookingTranarr['Sharer'][$tc]['Email'] = trim($sharer['email']);
						$bookingTranarr['Sharer'][$tc]['IdentiyType'] = trim($sharer['identitytype']);
						$bookingTranarr['Sharer'][$tc]['IdentityNo'] = trim($sharer['identityno']);
						$bookingTranarr['Sharer'][$tc]['ExpiryDate'] = trim($sharer['expirydate']);
						
					}
					
					
				}
				$bookedByInfoarr['BookingTran'][$roomcount] = $bookingTranarr;
				$roomcount++;
            }
			
			return $bookedByInfoarr;
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateReservationXML" . "-" . $e);
            $this->handleException($e);
        }
    }
	//Sanjay Waman - End
	
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
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateCancelledBookingsXML");
            $transfered_bookings = array();
            $temp = '';
            //$this->log->logIt("Hotel_" . $this->hotelcode .print_r($cancelled_bookings,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $cancel_bookingdetail = array();
           
            foreach ($cancelled_bookings as $cbooking)
			{
                if (trim($cbooking['fremark']) != '')
                    $can_remark = $cbooking['fremark'];
                else if (trim($cbooking['cremark']) != '')
                    $can_remark = $cbooking['cremark'];
                else
                    $can_remark='';
                    	
                    $cancel_bookingid = $cbooking['reservationno'];
                    $cancel_bookingdetail[$cancel_bookingid]['tranunkid'] = $cbooking['tranunkid'] ;
                    $cancel_bookingdetail[$cancel_bookingid]['locationId'] = $this->hotelcode;
                    $cancel_bookingdetail[$cancel_bookingid]['reservationno'] = $cancel_bookingid;
                    $cancel_bookingdetail[$cancel_bookingid]['canceldatetime'] = $cbooking['canceldatetime'];
                    $cancel_bookingdetail[$cancel_bookingid]['remark'] = $can_remark;
                    $cancel_bookingdetail[$cancel_bookingid]['voucherno'] = $cbooking['voucherno'];
            }  
            //$this->log->logIt("Cancel bookingdetail >>" .print_r($cancel_bookingdetail,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            if(count($cancel_bookingdetail)>0)
            {
                foreach($cancel_bookingdetail as $key => $value)
                {
                    //$this->log->logIt("Cancel bookingdetail >>" .print_r($value,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                    
                    $cancelreservation = $this->xmlDoc->createElement("CancelReservation");
                    
                    $locid = $this->xmlDoc->createElement("LocationId");
                    $locid->appendChild($this->xmlDoc->createTextNode($this->hotelcode)); 
                    $cancelreservation->appendChild($locid);
            
                    $uniqueid = $this->xmlDoc->createElement("UniqueID");
                    $uniqueid->appendChild($this->xmlDoc->createTextNode($value['reservationno']));
                    $cancelreservation->appendChild($uniqueid);
                    
                    $canceldatetime = $this->xmlDoc->createElement("Canceldatetime");
                    $canceldatetime->appendChild($this->xmlDoc->createTextNode($value['canceldatetime']));
                    $cancelreservation->appendChild($canceldatetime);               
                    
                    $remark = $this->xmlDoc->createElement("Remark");                        				
                    $remark->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($value['remark'])));
                    $cancelreservation->appendChild($remark);
                    
                    $voucherno = $this->xmlDoc->createElement("VoucherNo");
                    $voucherno->appendChild($this->xmlDoc->createTextNode($value['voucherno']));
                    $cancelreservation->appendChild($voucherno);
                    $transfered_bookings[] = $value['tranunkid'];
                    
                   // $this->log->logIt("validXML >>".$this->xmlDoc->saveXML($cancelreservation));
                    $validCancelledXML = simplexml_load_string($this->xmlDoc->saveXML($cancelreservation));
                    //$this->log->logIt("Cancel bookingdetail >>" .print_r($validCancelledXML,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                    
					//Sanjay - 12 Sep 2018 - OTACancellationFees fees for cancal status - Start
					$cancelamountdeatil = $this->fetchcancellationamount($value['tranunkid'],$this->hotelcode);
					//$this->log->logIt('Cancel Booking amount from OTA sigle booking >>'.print_r($cancelamountdeatil,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
															
					if(isset($cancelamountdeatil) && $cancelamountdeatil != false )
					{
						if(isset($cancelamountdeatil[0]['reason']) && $cancelamountdeatil[0]['reason'] != '')
						{
							$otacancelfees = $this->xmlDoc->createElement("OTACancellationFees");
							$otacancelfees->appendChild($this->xmlDoc->createTextNode(number_format(floatval($cancelamountdeatil[0]['reason']), 2, '.', '')));
							$cancelreservation->appendChild($otacancelfees);
						}
					}										
					//Sanjay - End
                    
                    if ($validCancelledXML === false)
                    {
                        $this->generateInvalidReservationXML($value['tranunkid']);
                        return false;
                    }
                    else
                    {
                        $this->xmlReservation->appendChild($cancelreservation);
                    }
                    
                                                                               
                }
            }
            //unset($cancel_bookingdetail);           
            return $transfered_bookings;
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateCancelledBookingsXML" . "-" . $e);
            $this->handleException($e);
        }
    }

    
	//Sanjay Waman - 28 May 2019 - changes regarding - Start
	private function generateCancelledBookingsJSON($cancelled_bookings)
	{
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateCancelledBookingsJSON");
            $cancelreservationarr = array();
            $temp = '';
            //$this->log->logIt("Hotel_" . $this->hotelcode .print_r($cancelled_bookings,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $cancel_bookingdetail = array();
           
            foreach ($cancelled_bookings as $cbooking)
			{
                if (trim($cbooking['fremark']) != '')
                    $can_remark = $cbooking['fremark'];
                else if (trim($cbooking['cremark']) != '')
                    $can_remark = $cbooking['cremark'];
                else
                    $can_remark='';
                    	
                    $cancel_bookingid = $cbooking['reservationno'];
                    $cancel_bookingdetail[$cancel_bookingid]['tranunkid'] = $cbooking['tranunkid'] ;
                    $cancel_bookingdetail[$cancel_bookingid]['locationId'] = $this->hotelcode;
                    $cancel_bookingdetail[$cancel_bookingid]['reservationno'] = $cancel_bookingid;
                    $cancel_bookingdetail[$cancel_bookingid]['canceldatetime'] = $cbooking['canceldatetime'];
                    $cancel_bookingdetail[$cancel_bookingid]['remark'] = $can_remark;
                    $cancel_bookingdetail[$cancel_bookingid]['voucherno'] = $cbooking['voucherno'];
            }  
            //$this->log->logIt("Cancel bookingdetail >>" .print_r($cancel_bookingdetail,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            if(count($cancel_bookingdetail)>0)
            {
				$ccount = 0;
                foreach($cancel_bookingdetail as $key => $value)
                {
                    //$this->log->logIt("Cancel bookingdetail >>" .print_r($value,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                    $cancelreservationarr[$ccount]['LocationId'] = trim($this->hotelcode);
                    $cancelreservationarr[$ccount]['UniqueID'] = trim($value['reservationno']);
					$cancelreservationarr[$ccount]['Status'] = "Cancel";
					$cancelreservationarr[$ccount]['Canceldatetime'] = trim($value['canceldatetime']);
					$cancelreservationarr[$ccount]['Remark'] = trim($value['remark']);
					$cancelreservationarr[$ccount]['VoucherNo'] = trim($value['voucherno']);
					//Sanjay - 12 Sep 2018 - OTACancellationFees fees for cancal status 
					$cancelamountdeatil = $this->fetchcancellationamount($value['tranunkid'],$this->hotelcode);
					//$this->log->logIt('Cancel Booking amount from OTA sigle booking >>'.print_r($cancelamountdeatil,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					if(isset($cancelamountdeatil) && $cancelamountdeatil != false )
					{
						if(isset($cancelamountdeatil[0]['reason']) && $cancelamountdeatil[0]['reason'] != '')
						{
							$cancelreservationarr[$ccount]['OTACancellationFees'] = trim(number_format(floatval($cancelamountdeatil[0]['reason']), 2, '.', ''));
						}
					}
					$ccount++;
                }
            }
            //unset($cancel_bookingdetail);           
            return $cancelreservationarr;
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateCancelledBookingsXML" . "-" . $e);
            $this->handleException($e);
        }
    }
	//Sanjay Waman - End
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

    private function getAdminUser() {
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
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "insertIntoAuditTrail" . "-" . $e);
            $this->handleException($e);
        }
    }

	
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
				return $this->generateGeneralErrorJson('500', "Error occured during processing.",array(),1);
			}
			else
			{
				#$this->log->logIt(get_class($this) . "-" . "handleException");
				$this->generateGeneralErrorMsg('500', "Error occured during processing");
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
	
	public function checkBookingsExists($getcount=0)
	{
		try
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . $this->module . "-" . "checkBookingsExists");
			$dao = new dao();
			$workingdate=date ("Y-m-d", strtotime("-1 day", strtotime($this->readConfigParameter('todayDate'))));
			
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
			$dao->initCommand($strSql);
			//$this->log->logIt($strSql);
			
			$dao->addParameter(":hotel_code", $this->hotelcode);
				    
			$result = $dao->executeRow();
			$count = $result['count'];
	    			
			$success = $this->xmlDoc->createElement("Success");
			
			if($count==0)
			{
				$count_msg = $this->xmlDoc->createElement("Count");
				$count_msg->appendChild($this->xmlDoc->createTextNode(0));				
				$success->appendChild($count_msg);
				
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
			if($getcount)
			{
				return $count;
			}
			else
			{
				echo $str;
				return;			
			}
		}
		catch (Exception $e) 
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkBookingsExists" . "-" . $e);
            $this->handleException($e);
		}
	}
		
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
	//Get commision detail
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
	// Get modifydate time
	Public function getmodifydatetime($tranunkid){
		try{
            
		 	$this->log->logIt(get_class($this) . "-" . "getmodifydatetime");
            //$this->log->logIt('hotel_code >> '.$this->hotelcode);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt('tranunkid >> '.$tranunkid);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            
		 	$dao = new dao();            
            $strSql = "SELECT auditdatetime FROM fdaudittrail
                        WHERE tranunkid=:tranunkid AND operation='Modify Channel Reservation' AND hotel_code=:hotel_code
                        ORDER BY auditdatetime DESC LIMIT 1;";                                
            $dao->initCommand($strSql);			
            $dao->addParameter(":tranunkid", $tranunkid);
			$dao->addParameter(":hotel_code", $this->hotelcode);
            $modifytime = $dao->executeRow();
            //$this->log->logIt($modifytime);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $modifytime = $modifytime['auditdatetime'];            
            //$this->log->logIt($strSql);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            //$this->log->logIt("Modify time >>".$modifytime);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
		}
		catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getmodifydatetime" . "-" . $e);
            $this->handleException($e);
            $modifytime = false;
		}
		return $modifytime;
	}
    
	
    // Develop to get detail whether booking is from web or channel 
    Public function getbookingsource($bookingid){
		try{
                $this->log->logIt(get_class($this) . "-" . "getbookingsource");
				
                $booking_array['web_booking'] = array();
                $booking_array['channel_booking'] = array();
				
				$dao = new dao();
                $strSql = "SELECT FDTI.webrateunkid, FDTI.webinventoryunkid ".
                          "FROM fdtraninfo AS FDTI ".
                          "WHERE FDTI.hotel_code=:hotel_code AND FDTI.reservationno=:reservationno";												
                
                $dao->initCommand($strSql);														
                $dao->addParameter(":reservationno", $bookingid);
                $dao->addParameter(":hotel_code", $this->hotelcode);            
                $result_status_res = $dao->executeQuery();
				
                //$this->log->logIt('Response query >>'.print_r($result_status_res,true));
                
                foreach($result_status_res as $key => $value)
                {
                    //$this->log->logIt('Response query >>'.print_r($value,true));
                    $webrateunkid = $value['webrateunkid'];
                    //$this->log->logIt('webrateunkid >>'.$webrateunkid);
                    $webinventoryunkid = $value['webinventoryunkid'];
                    //$this->log->logIt('webinventoryunkid >>'.$webinventoryunkid);
                    
                    //identify web booking
                    if($webrateunkid != NULL AND $webinventoryunkid != NULL)
                    {
                        $booking_array['web_booking'] =  $bookingid;                    
                    }
                    else
                    {
                        $booking_array['channel_booking'] = $bookingid;														
                    }                    
                }                
                //$this->log->logIt('Response query >>'.print_r($booking_array,true));
                return $booking_array;
            }
		catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getbookingsource" . "-" . $e);
            $this->handleException($e);
            return false;
		}
		
	}
    // Check Channel booking for notification
    Public function checkchannelbooking($bookingno,$status,$modifydatetime,$createdatetime,$flag = '1'){
		try{
                $this->log->logIt(get_class($this) . "-" . "checkchannelbooking".$bookingno."-".$status."-".$modifydatetime."-".$createdatetime);
                $subbookingno = '';
                $arrstatus = array();
				
                if(strpos($bookingno, '-') !== FALSE)
                {
                    $bookingnos = explode("-",$bookingno);
                    //$this->log->logIt('bookingid >> '.$bookingnos[0]);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                    //$this->log->logIt('bookingid >> '.$bookingnos[1]);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                    $bookingno = $bookingnos[0];
                    $subbookingno = $bookingnos[1];
                }
				if(isset($subbookingno) && $subbookingno!='')
				{
					$status_array = $this->getbookingstatus($bookingno,$subbookingno);
				}
				else
				{
					$status_array = $this->getbookingstatus($bookingno);
				}				
                //$this->log->logIt('status_array >> '.print_r($status_array,true));
				if(!isset($status_array[0]))
				{
					return null;
				}
                $status_database = $status_array[0]['status'].'';
                if($status_database == "N")
                {
                        $status_final = "New";
                }
                else if($status_database == "M")
                {
                        $status_final = "Modify";
                }
                else if($status_database == "C")
                {
                        $status_final = "Cancel";
                }
                    
                $this->log->logIt('Database Status  >>'.$status_final);
                $this->log->logIt('Notification Status >>'.$status);
                
                if($status_final != $status) 
                {
                  $arrstatus[$bookingno] = $status;                        
                }
                elseif(($status_final == 'Modify') && ($status == 'Modify'))
                {
                    foreach($status_array as $key => $value)
                    {																		
                        $transunkid = $value['tranunkid'];
                        $this->log->logIt('Transunkid >>'.$transunkid);
                        $statusunkid = $value['statusunkid'];
                        $this->log->logIt('Statusunkid >>'.$statusunkid);
                        if($statusunkid != 5 && $statusunkid != 6)
                        {
                            $resultmodifytime = $this->getmodifydatetime($transunkid);
                            //$this->log->logIt("Modify Date-time ".$resultmodifytime);                            
                            break;
                        }                       
                    }
					$this->log->logIt('System Date time  >>'.$resultmodifytime);
					$this->log->logIt('Request Date time  >>'.$modifydatetime);
                    if(strtotime($resultmodifytime) != strtotime($modifydatetime))
                    {
                       //$arrstatus[$bookingno] = $status;                            
                    }
                }
                else
                {
                    $this->log->logIt("Status and Time Match >>".$bookingno);
                }                
                //$this->log->logIt('Response query >>'.print_r($arrstatus,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                return $arrstatus;
            }
		catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkchannelbooking" . "-" . $e);
            $this->handleException($e);
            return false;
		}
	}    
    Public function checkwebbooking($bookingno,$status,$modifydatetime,$createdatetime){
		try{
                $this->log->logIt(get_class($this) . "-" . "checkwebbooking");
				
                $subbookingno = '';
                $arrstatus = $partial_array = array();
                $flag = 0;
                
                //$this->log->logIt('hotel_code >> '.$this->hotelcode);
                //$this->log->logIt('bookingid >> '.$bookingno);
                
                if(strpos($bookingno, '-') !== FALSE)
                {
                    $bookingnos = explode("-",$bookingno);
                    $this->log->logIt('bookingid >> '.$bookingnos[0]);
                    $this->log->logIt('Sub bookingid >> '.$bookingnos[1]);
                    $bookingno = $bookingnos[0];
                    $subbookingno = $bookingnos[1];
                }
				if(isset($subbookingno) && $subbookingno!='')
				{
					$status_array = $this->getbookingstatus($bookingno,$subbookingno);
				}
				else
				{
					$status_array = $this->getbookingstatus($bookingno);
				}
                if(!isset($status_array[0]))
				{
					return null;
				}
                foreach($status_array as $key => $value)
                {
                    //$this->log->logIt($value);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                    $transunkid = $value['tranunkid'];                    
                    $statusunkid = $value['statusunkid'];
                    array_push($partial_array,$statusunkid);
                }
                
                //$this->log->logIt('Partial_array >> '.print_r($partial_array,true));
                $flag = array_unique($partial_array);
                //$this->log->logIt('Flag >>'.print_r($flag,true));
               
			    $status_final='';
                if(in_array("6", $flag) && in_array("4", $flag))
                {
                    if(in_array("10", $flag) || in_array("13", $flag))
                    $status_final = "Modify";
                    else
                    $status_final = "Modify";
                }
                else if($flag[0] == '6')
                {
                    $status_final = "Cancel";	
                }
                else
                {
                    $status_final = "New";	
                }
                //$this->log->logIt('Database Status  >>'.$status_final);
                //$this->log->logIt('Notification Status >>'.$status);
                
                if($status_final != $status) 
                {
                   $arrstatus[$bookingno] = $status;                        
                }
                else if($status == 'Modify' && $status_final == 'Modify')
                {
                    $result_date = $this->getreservationdetails($bookingno,$this->hotelcode);
                    //$this->log->logIt('Canceldatetime array >>'.json_encode($result_date,true));
                    $mostRecent= 0;      
                    foreach($result_date as $key => $value)
                    { 
                       $curDate = strtotime($value['canceldatetime']);
                       $this->log->logIt('Modify date >>'.$value['canceldatetime']);
                        if ($curDate > $mostRecent) {
                         $mostRecent = $curDate;
                      }                      
                    }
                    $this->log->logIt('Modify date for booking engine mostrecent >>'.$mostRecent);
                    $this->log->logIt('Modify date for notification>>'.$modifydatetime);
                    if($mostRecent !=  strtotime($modifydatetime))
                    {
                        //$arrstatus[$bookingno] = $status;
                    }
                }
                else
                {
                    $this->log->logIt("Status and Time Match >>".$bookingno);
                }                
                //$this->log->logIt('Response query >>'.print_r($arrstatus,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                return $arrstatus;
            }
		catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "checkwebbooking" . "-" . $e);
            $this->handleException($e);
            return false;
		}
		
	}
    Public function getbookingstatus($bookingno,$subbookingid='')
    {
        try
        {
            $this->log->logIt(get_class($this) . "-" . "getbookingstatus");
            //$this->log->logIt('hotel_code >> '.$this->hotelcode);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $this->log->logIt('bookingid >> '.$bookingno."-".$subbookingid);
            
            $dao = new dao();                
            $strSql ='SELECT FDCBI.status, FDTI.statusunkid, FDTI.tranunkid
                        FROM fdtraninfo AS FDTI
                        LEFT JOIN fdchannelbookinginfo AS FDCBI ON FDCBI.tranunkid = FDTI.tranunkid AND FDCBI.hotel_code=:hotel_code
                        WHERE FDTI.hotel_code =:hotel_code AND FDTI.reservationno =:reservationno';
			if(isset($subbookingid) && $subbookingid!='')
			{
				$strSql .= ' AND FDTI.subreservationno =:subreservationno ';
			}
			
			$strSql .=' ORDER BY FDTI.statusunkid ASC'; 
            //$this->log->logIt('Response >>'.$strSql);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $dao->initCommand($strSql);													
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":reservationno", $bookingno);
			if(isset($subbookingid) && $subbookingid!='')
			{
				 $dao->addParameter(":subreservationno", $subbookingid);
			}
            
            $result_status = $dao->executeQuery();
            //$this->log->logIt('Response >>'.print_r($result_status,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            
            return $result_status;
        }
        catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getbookingstatus" . "-" . $e);
            $this->handleException($e);
            return false;
        }
	}
	
    Public function getdetailforzeamster($tranid)
    {
        try
        {
            $this->log->logIt(get_class($this) . "-" . "getdetailforzeamster");
            //$this->log->logIt('hotel_code >> '.$this->hotelcode);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $this->log->logIt('tranid >> '.$tranid);
			$dao = new dao();    
           														
            $strSql = "SELECT docno AS token_id, subdocno AS cardtype, expdate AS expdate, alias as account_vault_id FROM fasfoliodetail AS FD
                        INNER JOIN fasmaster AS FAS ON FAS.masterunkid=FD.masterunkid
                        INNER JOIN fasfoliomaster AS FM ON FM.foliounkid=FD.foliounkid AND FM.foliotypeunkid=1 AND FM.hotel_code=:hotel_code 
                        WHERE FD.hotel_code=:hotel_code
                        AND FM.lnktranunkid=:tranunkid AND FAS.mastertypeunkid IN (6, 9) ";														 

            $dao->initCommand($strSql);														
            $dao->addParameter(":tranunkid", $tranid);
            $dao->addParameter(":hotel_code", $this->hotelcode);															
            $resultpaymentgatewayzeamster = $dao->executeQuery();
            //$this->log->logIt('sql '.$strSql);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            //$this->log->logIt('Query_response=====>'.print_r($resultpaymentgatewayzeamster,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
														
            return $resultpaymentgatewayzeamster;
        }
        catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getdetailforzeamster" . "-" . $e);
            $this->handleException($e);
            return false;
        }
    }
    
    
    Public function checkbookingstatusforweb($bookingno,$webrateunkid, $webinventoryunkid)
    {
        try
        {
            $this->log->logIt(get_class($this) . "-" . "checkbookingstatusforweb");
           
            $status_final_web = '';            
            $partial_array = array();
            $status_array = $this->getbookingstatus($bookingno);
            foreach($status_array as $key => $value)
            {
                $statusunkid = $value['statusunkid'];
                $this->log->logIt('statusunkid >>'.$statusunkid);
                array_push($partial_array,$statusunkid);
            }
            
            //$this->log->logIt('Partial_array >> '.print_r($partial_array,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $flag = array_unique($partial_array);
            //$this->log->logIt('Flag >>'.print_r($flag,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            
            if(in_array("6", $flag) && in_array("4", $flag))
            {
                if(in_array("10", $flag) || in_array("13", $flag))
                $status_final_web = "Modify";
                else
                $status_final_web = "Modify";
            }
            return $status_final_web;
        }
        catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getmodifydatetime" . "-" . $e);
            $this->handleException($e);
            return false;
        }
    }
    
    //sushma - Function for tax detail
    public function getTaxdetail($parenttype, $childtype, $tranunkid)
	{
        try
		{
            $result = NULL;
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getTaxdetail");
            $dao = new dao();
            if($parenttype=="Room Charges")
            $parenttypeid=5;
            $childtypeid=3;//Tax
	    
            $strSql = "SELECT baseamount AS TaxAmount,tax,shortcode FROM  fasfoliodetail ". 
                          "	INNER JOIN  fasmaster ON fasmaster.masterunkid = fasfoliodetail.masterunkid AND fasmaster.mastertypeunkid=:childtype AND fasmaster.hotel_code=:hotel_code".
                          " INNER JOIN  cftax ON cftax.lnkmasterunkid = fasfoliodetail.masterunkid AND cftax.hotel_code=:hotel_code".
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
                $result = $dao->executeQuery();
               
                //$this->log->logIt("Tax Detail >>".print_r($result,True));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
                
            } catch (Exception $e) {
                $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getTaxdetail" . "-" . $e);
                $this->handleException($e);
            }
            return $result;
    }
	//Sanjay Waman - 08 Sep 2018 - functions For innsoft pms - Start
	public function getpromotiondetail($hotelcode,$tranid,$promotionid)
	{
		try
		{
			$this->log->logIt("Hotel_" . $hotelcode . " - " . get_class($this) . "-" . "getpromotiondetail");			
	    
            $sqlStr = "SELECT * FROM cfpromotions 
					   WHERE promotionunkid = :promotionid AND hotel_code = :hotel_code";
				
			//$this->log->logIt("getpromotiondetail >>".$sqlStr);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $hotelcode);
			$dao->addParameter(":promotionid", $promotionid);			
			$result = $dao->executeRow();
			//$this->log->logIt("Result".print_r($result,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $hotelcode . " - Exception in " . $this->module . " - getpromotiondetail - " . $e);
            $this->handleException($e);
        }
        return $result;
	}
	public function ExtrachargeTaxDetails($hotelcode,$tranid,$Extrachargename="",$Extrachargedate="")
	{
		try
		{
			$dao = new dao();
			//$this->log->logIt("Dao inside");
			$strSql =  "SELECT FD.baseamount AS netcharge 
						FROM fdtraninfo AS FDTI 
						INNER JOIN fasfoliomaster AS FM ON FM.foliounkid =FDTI.masterfoliounkid AND FM.hotel_code=:hotel_code  AND FM.foliotypeunkid=1 
						LEFT JOIN fasfoliodetail AS FD ON FD.foliounkid=FM.foliounkid AND FD.hotel_code=:hotel_code 
						LEFT JOIN fasfoliodetail AS IFD ON (FD.detailunkid=IFD.parentid AND IFD.isvoid_cancel_noshow_unconfirmed=0 AND IFD.hotel_code=:hotel_code) 
						LEFT JOIN fasmaster AS FASM ON FASM.masterunkid=FD.masterunkid AND FASM.hotel_code=:hotel_code
						LEFT JOIN cfextracharges AS cfe ON cfe.lnkmasterunkid=FASM.masterunkid ANd cfe.hotel_code=:hotel_code
						WHERE FDTI.tranunkid=:tranunkid AND FASM.mastertypeunkid=4 
						AND FD.isvoid_cancel_noshow_unconfirmed=0 AND DATE(FD.trandate)=:chargedate AND FASM.name=:chargename 
						GROUP BY FASM.name,FD.trandate ASC"; 
			
			$dao->initCommand($strSql);														
			$dao->addParameter(":tranunkid", $tranid);
			$dao->addParameter(":hotel_code", $hotelcode);
			$dao->addParameter(":chargename", $Extrachargename);
			$dao->addParameter(":chargedate", $Extrachargedate);
			
			$result_baseamount = $dao->executeQuery();
			
			$extracharge_netchage = $result_baseamount[0]['netcharge'];
			$extracharge_netchage_final = (number_format(floatval($extracharge_netchage), 2, '.', ''));
			return $extracharge_netchage_final;
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $hotelcode . " - Exception in " . $this->module . " - getpromotiondetail - " . $e);
            $this->handleException($e);
        }
        return false;
	}
	public function fetchcancellationamount($tranid,$hotel_code)
	{
		try
		{
			$this->log->logIt(get_class($this)."-"."fetchcancellationamount");
			$dao=new dao();
			$strSql="SELECT reason FROM ".dbtable::FDReason." WHERE tranunkid=:tranid AND hotel_code=:hotel_code AND reasoncategory=:reasoncategory";
			$dao->initCommand($strSql);	
			$dao->addParameter(":tranid",$tranid);
			$dao->addParameter(":hotel_code",$hotel_code);
			$dao->addParameter(":reasoncategory","CANCELLATIONFEES");
			//$this->log->logIt($strSql.">>".$hotel_code." --> Hotel_code ".$tranid." --> Tranid");//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			if(count($dao->executeQuery())>0)
			{
				$result = $dao->executeQuery();
				return $result;
			}
			else
			{
				return false;
			}
		}
		catch(Exception $e)
		{
			$this->log->logIt("Hotel_" . $hotelcode . " - Exception in " . $this->module . " - fetchcancellationamount - " . $e);
            $this->handleException($e);
		}
	}
    //Sanjay Waman - END
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
}

?>
