<?php
class pms_update_roomrates_test {
    private $log;
    private $module = "pms_update_roomrates_test";
    private $hotelcode;
    private $ratetype;
    private $roomrate;
    private $ratetypeid;
    private $roomtypeid;
    private $fromdate;
    private $todate;
    private $xmlDoc;
    private $xmlRoot;
	private $recs_updates;
	#Satish - 1.0.28.33 - 06 Oct 2012 - Start
	private $contacts;
	#Satish - 1.0.28.33 - 06 Oct 2012 - End
    //Sushma - 1.0.52.60 - 24 July 2017 - START
	//Purpose : Addons requested by 3rd parties using our API - RoomerPMS
	private $flag_contact;
    //Sushma - 1.0.52.60 - 24 July 2017 - End
	private $datelist = array(); //Manali - 1.0.31.36 - 15 Feb 2013, Purpose : Check for past date roomrate update
	private $todaysdate; //Manali - 1.0.31.36 - 15 Feb 2013, Purpose : Fixed Bug - Check for past date roomrate update
	private $datanotprocessed = array();//Manali - 1.0.31.36 - 15 Feb 2013, Purpose : Check for past date roomrate update
	
	private $userunkid;//Krunal Kaklotar - 21-05-2018 - Set userunkid for derived rateplan update
	public $DeriveRateThreshold = array();//Sanjay Waman - 24 Aug 2018 -  Rate Threshold apply in PMS
	public $DeriveRateThresholdApplied = array();//Sanjay Waman - 24 Aug 2018 -  Rate Threshold apply in PMS
	public $RateThresholdApplied = array();//Sanjay Waman - 24 Aug 2018 -  Rate Threshold apply in PMS
	public $RateThresholdAppliedflag = 0;//Sanjay Waman - 24 Aug 2018 -  Rate Threshold apply in PMS
	public $RateThresholdAppliedmsg = array();//Sanjay Waman - 24 Aug 2018 -  Rate Threshold apply in PMS 
	
    #Object Fields - Getter/Setter - Start
    function __construct() {
        try {
            $this->log = new logger("pms_integration");
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
	#Satish - 1.0.28.33 - 06 Oct 2012 - Start
	//Added new variable $contacts
public function executeRequest($hotelcode, $ratetype, $ratetypeid,$roomtypeid, $fromdate, $todate, $roomrate,$contacts,$ignoreroomtypelist,$ignoreratetypelist) //Manali - 1.0.31.36 - 15 Feb 2013, Purpose : Fixed Bug - Check for roomtype/ratetype exists or not on web
{
	#Satish - 1.0.28.33 - 06 Oct 2012 - End
        $this->log->logIt("Hotel_" . $hotelcode . " - " . get_class($this) . "-" . "executeRequest");
        try {
            $this->hotelcode = $hotelcode;
			$this->xmlDoc = new DOMDocument('1.0','UTF-8');
			$this->xmlDoc->xmlStandalone = true;
			$this->xmlRoot = $this->xmlDoc->createElement("RES_Response");
			$this->xmlDoc->appendChild($this->xmlRoot);
			//Sanjay Waman - 14 Aug 2018 - Rate Threshold apply in PMS -Start
			$this->DeriveRateThreshold = array();
			$this->DeriveRateThresholdApplied = array();
			$this->RateThresholdApplied = array();
			$this->RateThresholdAppliedflag = 0;
			$this->RateThresholdAppliedmsg = array();
			foreach($ratetype as $index => $perroom)
			{
				if(isset($perroom->RoomRate->Base))
				{
					$msg = $this->RateThresholdCheck($hotelcode,$perroom->RoomTypeID.'',$perroom->RoomRate->Base.'');
					if($msg["result"]=="false")
					{
						$this->RateThresholdApplied[$perroom->RoomTypeID.'|'.$perroom->RoomRate->Base.'']=$msg['min_val'].'|'.$msg['max_val'];
						//$this->generateGeneralErrorMsg('500',$perroom->RoomTypeID."- Rate should be between : ".$msg['min_val']." and ".$msg['max_val']."");
						//echo $this->xmlDoc->saveXML();
						//return;
					}
					
				}
			}
			//$this->log->logIt("array for Normal room - ".json_encode($this->RateThresholdApplied,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("array for derive room - ".json_encode($this->DeriveRateThreshold,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//Sanjay Waman - End
			if($this->isUpdateAllowed()){
				$this->ratetype = $ratetype;
           		$this->ratetypeid = $ratetypeid;
            	$this->roomtypeid = $roomtypeid;		
				$this->fromdate = $fromdate;
				$this->todate = $todate;
				$this->roomrate = $roomrate;
				#Satish - 1.0.28.33 - 06 Oct 2012 - Start
				$this->contacts = $contacts;
				#Satish - 1.0.28.33 - 06 Oct 2012 - End
				$this->recs_updates=0;				
				//processing request
				
				//Manali - 1.0.31.36 - 15 Feb 2013 - START
				//Purpose : Fixed Bug - Check for past date roomrate update				
				$this->todaysdate = $this->readConfigParameter("todayDate");
				//Sushma - 1.0.52.60 - 24 July 2017 - START
				//Purpose : Addons requested by 3rd parties using our API - RoomerPMS
				//$this->log->logIt("roomrate=========>".print_r($roomrate,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("ratetype=========>".print_r($ratetype,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("ratetypeid=========>".print_r($ratetypeid,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("roomtypeid=========>".print_r($roomtypeid,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("fromdate=========>".print_r($fromdate,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("todate=========>".print_r($todate,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("contacts=========>".print_r($contacts,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//Sushma - 1.0.52.60 - 24 July 2017 - END
				/*$this->log->logIt(print_r($ignoreroomtypelist,TRUE));
				$this->log->logIt(print_r($ignoreratetypelist,TRUE));*/
				
				
				//Sushma - 1.0.52.60 - 24 July 2017 - START
				//Purpose : Addons requested by 3rd parties using our API - RoomerPMS				
				//Code for identify contact is correct or not - start
				if(count($this->contacts) >0)
				{
					$contacts_database = $this->getContactsForUpdate();
					//$this->log->logIt("contacts in process request=========>".$contacts_database);
					$contactid = explode(",", $contacts_database);
					//$this->log->logIt("Contacts in Database =========>".print_r($contactid, TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					$this->log->logIt("Count of array========>".count($this->contacts));
					$contact_requestarray = [];
					for ($j = 0; $j < count($this->contacts); $j++)
					{
								$contact_requestarray[] = (string)$this->contacts[$j];
					}
					//$this->log->logIt("Contacts in request=========>".print_r($contact_requestarray, TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					
					$result = array_intersect($contact_requestarray, $contactid);
					//$this->log->logIt("result=========>".print_r($result, TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					
					if(count(array_intersect($contact_requestarray, $contactid)) == count($this->contacts))
					{
								$flag_contact = "1";
					}
					else
					{
								$flag_contact = "0";
					}
					$this->log->logIt("flag=========>".$flag_contact);
				}				
				
				
				if(count($this->contacts) >0)
				{
					if($flag_contact != "0")
					{
						//Code for identify contact is correct or not - end
						if ($this->processRequest($ignoreroomtypelist,$ignoreratetypelist)) 
						{
							//$this->log->logIt($this->recs_updates."|".count($this->datelist)."|".count($ignoreroomtypelist)."|".count($ignoreratetypelist));
							if($this->recs_updates==0 && count($this->datelist)==0 && count($ignoreroomtypelist)==0  && count($ignoreratetypelist)==0)
							{
								//Sanjay Waman - 25 Aug 2018 - Rate Threshold Applied on Derived ratplan -START
								if($this->DeriveRateThresholdApplied!=[] && $this->RateThresholdAppliedflag!=1)
								{
									$success = $this->xmlDoc->createElement("Success");
									$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
									$masWithrate='Room Rates Successfully Updated - '.implode(",",$this->DeriveRateThresholdApplied).'';
									$succ_msg->appendChild($this->xmlDoc->createTextNode($masWithrate));
									$success->appendChild($succ_msg);
									$this->xmlRoot->appendChild($success);
									#Satish - 1.0.28.33 - 06 Oct 2012 - Start
									$this->generateGeneralErrorMsg('0','Success');	//Guided By JJ			
									#Satish - 1.0.28.33 - 06 Oct 2012 - End
									$str = $this->xmlDoc->saveXML();
									echo $str;
								}
								elseif($this->DeriveRateThresholdApplied!=[] && $this->RateThresholdAppliedflag==1)
								{
									$masWithrate = implode(",",$this->RateThresholdAppliedmsg).'';
									$masWithrate .=" AND ".implode(",",$this->DeriveRateThresholdApplied);
									$this->generateGeneralErrorMsg('500',$masWithrate);
									echo $this->xmlDoc->saveXML();
									return;
								}
								elseif($this->RateThresholdAppliedflag==1 && count($this->RateThresholdAppliedmsg)>0 && $this->DeriveRateThresholdApplied==[])
								{
									$masWithrate=implode(",",$this->RateThresholdAppliedmsg).'';
									$this->generateGeneralErrorMsg('500',$masWithrate);
									echo $this->xmlDoc->saveXML();
									return;
								}
								//Sanjay Waman - END
								$success = $this->xmlDoc->createElement("Success");
								$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
									$succ_msg->appendChild($this->xmlDoc->createTextNode('No rates are updated on web as PMS - Web rates are same.'));
								$success->appendChild($succ_msg);
								$this->xmlRoot->appendChild($success);								
								$this->generateGeneralErrorMsg('0','Success');
								$str = $this->xmlDoc->saveXML();
								echo $str;
							}
							else if($this->recs_updates>0 && count($this->datelist)==0 && count($ignoreroomtypelist)==0  && count($ignoreratetypelist)==0)
							{
								//Sanjay Waman - 24 Aug 2018 - Rate Threshold Applied on Derived ratplan -START
								//$this->log->logIt("--------=========>".json_encode($this->DeriveRateThresholdApplied,true));
								if($this->DeriveRateThresholdApplied!=[] && $this->RateThresholdAppliedflag!=1)
								{
									$success = $this->xmlDoc->createElement("Success");
									$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
									$masWithrate='Room Rates Successfully Updated - '.implode(",",$this->DeriveRateThresholdApplied).'';
									$succ_msg->appendChild($this->xmlDoc->createTextNode($masWithrate));
									$success->appendChild($succ_msg);
									$this->xmlRoot->appendChild($success);
									#Satish - 1.0.28.33 - 06 Oct 2012 - Start
									$this->generateGeneralErrorMsg('0','Success');	//Guided By JJ			
									#Satish - 1.0.28.33 - 06 Oct 2012 - End
									$str = $this->xmlDoc->saveXML();
									echo $str;
								}
								elseif($this->DeriveRateThresholdApplied!=[] && $this->RateThresholdAppliedflag==1)
								{
									$masWithrate = implode(",",$this->RateThresholdAppliedmsg).'';
									$masWithrate .=" AND ".implode(",",$this->DeriveRateThresholdApplied);
									$this->generateGeneralErrorMsg('500',$masWithrate);
									echo $this->xmlDoc->saveXML();
									return;
								}
								elseif($this->RateThresholdAppliedflag==1 && count($this->RateThresholdAppliedmsg)>0 && $this->DeriveRateThresholdApplied==[])
								{
									$masWithrate=implode(",",$this->RateThresholdAppliedmsg).'';
									$this->generateGeneralErrorMsg('500',$masWithrate);
									echo $this->xmlDoc->saveXML();
									return;
								}
								else
								{
									$success = $this->xmlDoc->createElement("Success");
									$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
										$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Rates Successfully Updated'));
									$success->appendChild($succ_msg);
									$this->xmlRoot->appendChild($success);
									#Satish - 1.0.28.33 - 06 Oct 2012 - Start
									$this->generateGeneralErrorMsg('0','Success');	//Guided By JJ			
									#Satish - 1.0.28.33 - 06 Oct 2012 - End
									$str = $this->xmlDoc->saveXML();
									echo $str;
								}
								//Sanjay Waman - END
								/*$success = $this->xmlDoc->createElement("Success");
								$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
									$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Rates Successfully Updated'));
								$success->appendChild($succ_msg);
								$this->xmlRoot->appendChild($success);
								$this->generateGeneralErrorMsg('0','Success');	
								$str = $this->xmlDoc->saveXML();
								echo $str;*/
							}
							else
							{
								//Sushma Rana - 10th July 2017 - START
								//Purpose - Corrected error messages 
								$allRTTIgnored = $allRMTIgnored = true;
								foreach($ratetypeid AS $ratetypeunkid)
								{
									if(!in_array($ratetypeunkid,$ignoreratetypelist))
									{
										$allRTTIgnored = false;
										break;
									}
								}
								foreach($roomtypeid AS $roomtypeunkid)
								{
									if(!in_array($roomtypeunkid,$ignoreroomtypelist))
									{
										$allRMTIgnored = false;
										break;
									}
								}								
								
								$error_msg='';
								$roomtype_not_exists_msg='';
								$ratetype_not_exists_msg='';
								if(count($ignoreroomtypelist)>0)
									$roomtype_not_exists_msg = 'Room Types Ids : '.implode(",",$ignoreroomtypelist).' not exists on web so not updated.';
									
								if(count($ignoreratetypelist)>0)
									$ratetype_not_exists_msg = 'Rate Types Ids : '.implode(",",$ignoreratetypelist).' not exists on web so not updated.';
								
								if(count($ignoreroomtypelist)>0 || count($ignoreratetypelist)>0)
									$error_msg = ($error_msg!='')?($roomtype_not_exists_msg." ".$ratetype_not_exists_msg." ".$error_msg) : ($roomtype_not_exists_msg." ".$ratetype_not_exists_msg." ");
								
								if(count($this->datelist)>0 && (!$allRTTIgnored || !$allRTTIgnored)) //Falguni Rana - 1.0.50.55 - 11th Aug 2016, Purpose - Added && (!$allRTTIgnored || !$allRTTIgnored)
								{
									//$this->log->logIt(print_r($this->datelist,TRUE));
									$rmtypelist ='';
									
									foreach($this->datelist as $key => $value)
									{
										//$this->log->logIt($key);
										//$this->log->logIt($value);
									
										foreach($value as $datakey =>$datavalue)
										{
											list($roomtypeid,$ratetypeid) = explode("|",$datakey);
											
											/*$this->log->logIt($roomtypeid."|".$ratetypeid);
											$this->log->logIt($ignoreroomtypelist);
											$this->log->logIt($ignoreratetypelist);
											*/
											if(in_array($roomtypeid,$ignoreroomtypelist,TRUE) || in_array($ratetypeid,$ignoreratetypelist,TRUE))
												continue;
											else
											{									
												$rmtypelist = ($rmtypelist!='')?($rmtypelist.", Room Type : ".$roomtypeid." - Rate Type Id :".$ratetypeid): ("Room Type : ".$roomtypeid." - Rate Type Id :".$ratetypeid);
												
												$rmtypelist =  $rmtypelist." from ".$datavalue['fromdate'];
												
												$rmtypelist =  $rmtypelist." to ".$datavalue['todate'];
											}
										}
									}
								
									$error_msg = $error_msg.$rmtypelist." rates not updated due to dates passed.";
								}
								
								$success = $this->xmlDoc->createElement("Success");
								$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
								if($allRTTIgnored && $allRTTIgnored)
									$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Rates not Updated.'));
								else
									$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Rates Partially Updated.'));
							
								$success->appendChild($succ_msg);
								$this->xmlRoot->appendChild($success);
								$this->generateGeneralErrorMsg('1',$error_msg);	
								$str = $this->xmlDoc->saveXML();
								echo $str;					
							}
						} 
						
						else {
							$this->generateGeneralErrorMsg('500', "Error occured during processing.");
							echo $this->xmlDoc->saveXML();
							return;
						}
					}
					else 
					{
						$this->generateGeneralErrorMsg('133', "Saparate source detail mismatch.");
							echo $this->xmlDoc->saveXML();
							return;
					}
					
				}
				//Sushma - 1.0.52.60 - 24 July 2017 - End
				else
				{
					//Code for identify contact is correct or not - end
					if ($this->processRequest($ignoreroomtypelist,$ignoreratetypelist)) 
					{
						//$this->log->logIt($this->recs_updates."|".count($this->datelist)."|".count($ignoreroomtypelist)."|".count($ignoreratetypelist));
						if($this->recs_updates==0 && count($this->datelist)==0 && count($ignoreroomtypelist)==0  && count($ignoreratetypelist)==0)
						{
							//Sanjay Waman - 25 Aug 2018 - Rate Threshold Applied on Derived ratplan -START
							if($this->DeriveRateThresholdApplied!=[] && $this->RateThresholdAppliedflag!=1)
							{
								$success = $this->xmlDoc->createElement("Success");
								$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
								$masWithrate='Room Rates Successfully Updated - '.implode(",",$this->DeriveRateThresholdApplied).'';
								$succ_msg->appendChild($this->xmlDoc->createTextNode($masWithrate));
								$success->appendChild($succ_msg);
								$this->xmlRoot->appendChild($success);
								#Satish - 1.0.28.33 - 06 Oct 2012 - Start
								$this->generateGeneralErrorMsg('0','Success');	//Guided By JJ			
								#Satish - 1.0.28.33 - 06 Oct 2012 - End
								$str = $this->xmlDoc->saveXML();
								echo $str;
							}
							elseif($this->DeriveRateThresholdApplied!=[] && $this->RateThresholdAppliedflag==1)
							{
								$masWithrate = implode(",",$this->RateThresholdAppliedmsg).'';
								$masWithrate .=" AND ".implode(",",$this->DeriveRateThresholdApplied);
								$this->generateGeneralErrorMsg('500',$masWithrate);
								echo $this->xmlDoc->saveXML();
								return;
							}
							elseif($this->RateThresholdAppliedflag==1 && count($this->RateThresholdAppliedmsg)>0 && $this->DeriveRateThresholdApplied==[])
							{
								$masWithrate=implode(",",$this->RateThresholdAppliedmsg).'';
								$this->generateGeneralErrorMsg('500',$masWithrate);
								echo $this->xmlDoc->saveXML();
								return;
							}
							//Sanjay Waman - END
							$success = $this->xmlDoc->createElement("Success");
							$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
								$succ_msg->appendChild($this->xmlDoc->createTextNode('No rates are updated on web as PMS - Web rates are same.'));
							$success->appendChild($succ_msg);
							$this->xmlRoot->appendChild($success);
							#Satish - 1.0.28.33 - 06 Oct 2012 - Start
							$this->generateGeneralErrorMsg('0','Success');	//Guided By JJ			
							#Satish - 1.0.28.33 - 06 Oct 2012 - End
							$str = $this->xmlDoc->saveXML();
							echo $str;
						}
						else if($this->recs_updates>0 && count($this->datelist)==0 && count($ignoreroomtypelist)==0  && count($ignoreratetypelist)==0)
						{
							//Sanjay Waman - 24 Aug 2018 - Rate Threshold Applied on Derived ratplan -START
							//$this->log->logIt("--------=========>".json_encode($this->DeriveRateThresholdApplied,true));
							if($this->DeriveRateThresholdApplied!=[] && $this->RateThresholdAppliedflag!=1)
							{
								$success = $this->xmlDoc->createElement("Success");
								$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
								$masWithrate='Room Rates Successfully Updated - '.implode(",",$this->DeriveRateThresholdApplied).'';
								$succ_msg->appendChild($this->xmlDoc->createTextNode($masWithrate));
								$success->appendChild($succ_msg);
								$this->xmlRoot->appendChild($success);
								#Satish - 1.0.28.33 - 06 Oct 2012 - Start
								$this->generateGeneralErrorMsg('0','Success');	//Guided By JJ			
								#Satish - 1.0.28.33 - 06 Oct 2012 - End
								$str = $this->xmlDoc->saveXML();
								echo $str;
							}
							elseif($this->DeriveRateThresholdApplied!=[] && $this->RateThresholdAppliedflag==1)
							{
								$masWithrate = implode(",",$this->RateThresholdAppliedmsg).'';
								$masWithrate .=" AND ".implode(",",$this->DeriveRateThresholdApplied);
								$this->generateGeneralErrorMsg('500',$masWithrate);
								echo $this->xmlDoc->saveXML();
								return;
							}
							elseif($this->RateThresholdAppliedflag==1 && count($this->RateThresholdAppliedmsg)>0 && $this->DeriveRateThresholdApplied==[])
							{
								$masWithrate=implode(",",$this->RateThresholdAppliedmsg).'';
								$this->generateGeneralErrorMsg('500',$masWithrate);
								echo $this->xmlDoc->saveXML();
								return;
							}
							else
							{
								$success = $this->xmlDoc->createElement("Success");
								$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
									$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Rates Successfully Updated'));
								$success->appendChild($succ_msg);
								$this->xmlRoot->appendChild($success);
								#Satish - 1.0.28.33 - 06 Oct 2012 - Start
								$this->generateGeneralErrorMsg('0','Success');	//Guided By JJ			
								#Satish - 1.0.28.33 - 06 Oct 2012 - End
								$str = $this->xmlDoc->saveXML();
								echo $str;
							}
							//Sanjay Waman - END
							/*$success = $this->xmlDoc->createElement("Success");
							$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
						        $succ_msg->appendChild($this->xmlDoc->createTextNode('Room Rates Successfully Updated'));
							$success->appendChild($succ_msg);
							$this->xmlRoot->appendChild($success);
							#Satish - 1.0.28.33 - 06 Oct 2012 - Start
							$this->generateGeneralErrorMsg('0','Success');	//Guided By JJ			
							#Satish - 1.0.28.33 - 06 Oct 2012 - End
							$str = $this->xmlDoc->saveXML();
							echo $str;*/
						}
						else
						{
							//Falguni Rana - 1.0.50.55 - 11th Aug 2016 - START
							//Purpose - Corrected error messages 
							$allRTTIgnored = $allRMTIgnored = true;
							foreach($ratetypeid AS $ratetypeunkid)
							{
								if(!in_array($ratetypeunkid,$ignoreratetypelist))
								{
									$allRTTIgnored = false;
									break;
								}
							}
							foreach($roomtypeid AS $roomtypeunkid)
							{
								if(!in_array($roomtypeunkid,$ignoreroomtypelist))
								{
									$allRMTIgnored = false;
									break;
								}
							}
							//Falguni Rana - 1.0.50.55 - 11th Aug 2016 - END
							
							$error_msg='';
							$roomtype_not_exists_msg='';
							$ratetype_not_exists_msg='';
							if(count($ignoreroomtypelist)>0)
								$roomtype_not_exists_msg = 'Room Types Ids : '.implode(",",$ignoreroomtypelist).' not exists on web so not updated.';
								
							if(count($ignoreratetypelist)>0)
								$ratetype_not_exists_msg = 'Rate Types Ids : '.implode(",",$ignoreratetypelist).' not exists on web so not updated.';
							
							if(count($ignoreroomtypelist)>0 || count($ignoreratetypelist)>0)
								$error_msg = ($error_msg!='')?($roomtype_not_exists_msg." ".$ratetype_not_exists_msg." ".$error_msg) : ($roomtype_not_exists_msg." ".$ratetype_not_exists_msg." ");
							
							if(count($this->datelist)>0 && (!$allRTTIgnored || !$allRTTIgnored)) //Falguni Rana - 1.0.50.55 - 11th Aug 2016, Purpose - Added && (!$allRTTIgnored || !$allRTTIgnored)
							{
								//$this->log->logIt(print_r($this->datelist,TRUE));
								$rmtypelist ='';
								
								foreach($this->datelist as $key => $value)
								{
									//$this->log->logIt($key);
									//$this->log->logIt($value);
								
									foreach($value as $datakey =>$datavalue)
									{
										list($roomtypeid,$ratetypeid) = explode("|",$datakey);
										
										/*$this->log->logIt($roomtypeid."|".$ratetypeid);
										$this->log->logIt($ignoreroomtypelist);
										$this->log->logIt($ignoreratetypelist);
										*/
										if(in_array($roomtypeid,$ignoreroomtypelist,TRUE) || in_array($ratetypeid,$ignoreratetypelist,TRUE))
											continue;
										else
										{									
											$rmtypelist = ($rmtypelist!='')?($rmtypelist.", Room Type : ".$roomtypeid." - Rate Type Id :".$ratetypeid): ("Room Type : ".$roomtypeid." - Rate Type Id :".$ratetypeid);
											
											$rmtypelist =  $rmtypelist." from ".$datavalue['fromdate'];
											
											$rmtypelist =  $rmtypelist." to ".$datavalue['todate'];
										}
									}
								}
							
								$error_msg = $error_msg.$rmtypelist." rates not updated due to dates passed.";
							}
							
							$success = $this->xmlDoc->createElement("Success");
							$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
							
							//Falguni Rana - 1.0.50.55 - 11th Aug 2016 - START
							//Purpose - Corrected error messages
							if($allRTTIgnored && $allRTTIgnored)
								$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Rates not Updated.'));
							else
								$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Rates Partially Updated.'));
							//Falguni Rana - 1.0.50.55 - 11th Aug 2016 - END
								
							$success->appendChild($succ_msg);
							$this->xmlRoot->appendChild($success);
							
							//Falguni Rana - 1.0.50.55 - 11th Aug 2016, Purpose - Corrected error messages, Commented below line and added very next line
							//$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ
							$this->generateGeneralErrorMsg('1',$error_msg);						
							
							$str = $this->xmlDoc->saveXML();
							echo $str;					
						}
					} 
					//Manali - 1.0.31.36 - 15 Feb 2013 - END
					else {
						$this->generateGeneralErrorMsg('500', "Error occured during processing.");
						echo $this->xmlDoc->saveXML();
						return;
					}
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
    }
    private function processRequest($ignoreroomtypelist,$ignoreratetypelist) 
	{
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "processRequest");
            $flag = true;
						
            $keyVal = $this->prepareKeyVal();
			
			//$this->log->logIt(print_r($keyVal,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
            for ($i = 0; $i < count($keyVal); $i++) {
				$updateflag = 0;//Sanjay Waman - 24 Aug 2018 - Rate Threshold apply in PMS
                foreach ($keyVal[$i] as $updateColumn => $allocations) {
                    foreach ($allocations as $key => $val) {
                        $val = substr($val, 0, strlen($val) - 1);
						$this->log->logIt("Value============>".$val);
						//Manali - 1.0.31.36 - 15 Feb 2013 -START 
						//Purpose : Fixed Bug - Check for roomtype,ratetype exists or not on web					
						if(!in_array($this->roomtypeid[$i]."",array_values($ignoreroomtypelist),TRUE) && !in_array($this->ratetypeid[$i]."",array_values($ignoreratetypelist),TRUE) && $val!='')
						{
							//Sanjay Waman - 24 Aug 2018 - Rate Threshold apply in PMS -Start
							if($this->RateThresholdApplied!=[] && count($this->RateThresholdApplied)>0 && $updateColumn='baserates')
							{
								foreach($this->RateThresholdApplied as $roomtypeidkey => $thresholdvalue)
								{
									$arrVal='';
									$valarr = explode(",",$val);
									$checkval = explode("=",$valarr[0]);
									$thresholdvaluearr = explode("|",$thresholdvalue);
									$roomtypeidkeyArray=explode("|",$roomtypeidkey);
									if($roomtypeidkeyArray[0]==$this->roomtypeid[$i] && $checkval[1]==$roomtypeidkeyArray[1])
									{
										$this->log->logIt("Roomtypeid skipped ============>".$roomtypeidkeyArray[0]);
										$updateflag = 1;
										$arrVal = $roomtypeidkeyArray[0]."- This Room rate is not updated, should be between : ".$thresholdvaluearr[0]." and ".$thresholdvaluearr[1]."";
										if(!in_array($arrVal,$this->RateThresholdAppliedmsg))
										{
											array_push($this->RateThresholdAppliedmsg,$arrVal);
											$this->RateThresholdAppliedflag = 1;
										}
										
									}
								}
							}
							if($updateflag==0)
							{
								if (!$this->updateRoomRates($this->ratetypeid[$i],$this->roomtypeid[$i], explode("-", $key), $val))
								{
									$flag = false;
									break;
								}
							}
							//Sanjay Waman -END
						}
						//Manali - 1.0.31.36 - 15 Feb 2013 -END 	
                    }
                    if (!$flag)
                        break;
                }
            }
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "processRequest - " . $e);
            $this->handleException($e);
        }
        return $flag;
    }
   	private function updateRoomRates($rateteypeid,$roomtypeid,$month_year, $updatewith) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "updateRoomRates - ".$rateteypeid."|".$roomtypeid."|".$month_year[0]."|".$month_year[1]."|".$updatewith."|".$this->readConfigParameter("PMSRatesUpdateOnContact"));
			 //$this->log->logIt($this->contacts);
			 
			//Manali - 1.0.33.38 - 30 Apr 2013 - START
			//Purpose : Set PMS User as loginuserunkid
			$dao = new dao();			
			$strSql1 = "SELECT userunkid from cfuser where username='pms' and hotel_code=".$this->hotelcode;            
            $dao->initCommand($strSql1);
			$userrow=(array)$dao->executeRow();
			
			//$this->log->logIt("User Row : ".$userrow['userunkid']);
			$this->userunkid = $userrow['userunkid'];
			
			$dao = new dao();	
			$dao->initCommand("SELECT @loginuser:=:userunkid,@loginhotel:=:hotel_code;");
			$dao->addParameter(":userunkid",$userrow['userunkid']);
			$dao->addParameter(":hotel_code",$this->hotelcode);
			$result = $dao->executeQuery();
			//$this->log->logIt(print_r($result,TRUE));			
			//Manali - 1.0.33.38 - 30 Apr 2013 - END
            $flag = false;
            $strSql = " UPDATE cfroomrateallocation as cfra";
            $strSql .= " SET " . $updatewith;
            $strSql .= " WHERE cfra.ratetypeunkid=:ratetypeunkid AND cfra.roomtypeunkid=:roomtypeunkid AND cfra.hotel_code=:hotel_code AND cfra.month=:month and cfra.year=:year";
			#Satish - 1.0.28.33 - 06 Oct 2012 - Start
			if(count($this->contacts)>0){
				$strSql.=" and FIND_IN_SET(cfra.contactunkid,:contacts) ";
			}
			else{
					/*
					//Manali - 1.0.33.38 - 15 May 2013 - START
					//Purpose : Enhancement - Derived Rate Update
					$dao = new dao();
					$list_contact = "SELECT contactunkid FROM ".dbtable::Contact." WHERE hotel_code=:hotel_code AND FIND_IN_SET(contacttypeunkid,'".$this->readConfigParameter("PMSRatesUpdateOnContact")."')";
					$dao->initCommand($list_contact);
					$dao->addParameter(":hotel_code", $this->hotelcode);
					$contacts_list = $dao->executeQuery();
					$contacts='';
					$this->log->logIt("contacts list =========>".print_r($contacts_list,TRUE));
					foreach($contacts_list as $contactlist)
						$contacts=($contacts==''?$contactlist['contactunkid']:$contacts.",".$contactlist['contactunkid']);
					*/
					//Sushma - 1.0.52.60 - 24 July 2017 - START
				    //Purpose : Addons requested by 3rd parties using our API - RoomerPMS
					$contacts = $this->getContactsForUpdate();
					//Sushma - 1.0.52.60 - 24 July 2017 - END
					$strSql.=" and cfra.contactunkid in (".$contacts.")"; //Manali - 1.0.32.37 - 05 April 2013,Purpose : Enhancement - Need option to update rates on channel also from PMS. Initially it was updating only on Web. It was requirement of property "Tropical Garden Lounge",Reason - want to manage Rates and inventory through PMS. 
					//Manali - 1.0.33.38 - 15 May 2013 - START
			}
			#Satish - 1.0.28.33 - 06 Oct 2012 - End
			
			// Old Logic - Krunal Kaklotar
            /*$dao = new dao();
            $dao->initCommand($strSql);
            $dao->addParameter(":ratetypeunkid", $rateteypeid);
            $dao->addParameter(":roomtypeunkid", $roomtypeid);			
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":month", $month_year[1]);
            $dao->addParameter(":year", $month_year[0]);*/
			
			#Satish - 1.0.28.33 - 06 Oct 2012 - Start
			if(count($this->contacts)>0){
				
				//$dao->addParameter(":contacts", implode(',',$this->contacts)); // Krunal Kaklotar
				//$contacts = $this->contacts;//Manali - 1.0.33.38 - 15 May 2013,Purpose : Enhancement - Derived Rate Update
				$contacts = implode(',',$this->contacts);//sushma - 1.0.52.60 - 15 May 2013,Purpose : Enhancement - Derived Rate Update
			}
			#Satish - 1.0.28.33 - 06 Oct 2012 - End
			//$rowcount=$dao->executeNonQuery(); // Krunal Kaklotar
			$this->recs_updates=$this->recs_updates+1;	// Krunal Kaklotar - Discussed with Sushma - we every time display "successfully invenotry update message"	

			//Manali - 1.0.33.38 - 15 May 2013 - START
			//Purpose : Enhancement - Derived Rate Update
			$this->processDerivedRate($roomtypeid,$rateteypeid,$updatewith,$month_year[1],$month_year[0],$contacts);
			//Manali - 1.0.33.38 - 15 May 2013 - END

			/*$this->log->logIt($strSql);
			$this->log->logIt('ratetypeunkid : '.$rateteypeid);
			$this->log->logIt('roomtypeunkid : '.$roomtypeid);
			$this->log->logIt('hotel_code : '.$this->hotelcode);
			$this->log->logIt('month : '.$month_year[1]);
			$this->log->logIt('year : '.$month_year[0]);
			$this->log->logIt(implode(',',$this->contacts));
			$this->log->logIt($this->recs_updates);*/

            $flag = true;
			
			
			/*Strore Query for queue Krunal Kaklotar */
			//if($this->hotelcode == "8963"){
				
				global $connection;
				
				//get database name from connection object
				$connection_str = explode("dbname=",$connection->getConnectionString());
				$db_name= "";
				if(count($connection_str) > 0){
					$db_name =  $connection_str[1];	
				}
				
				//Strat - Krunal Kaklotar - 2018-04-23 - prepare upate query to store in rds
				$sqldata = $strSql;
				$sqldata = str_replace(":roomtypeunkid","'".$roomtypeid."'",$sqldata);
				$sqldata = str_replace(":ratetypeunkid","'".$rateteypeid."'",$sqldata);
				$sqldata = str_replace(":hotel_code","'".$this->hotelcode."'",$sqldata);
				$sqldata = str_replace(":contacts","'".$contacts."'",$sqldata);
				$sqldata = str_replace(":month","'".$month_year[1]."'",$sqldata);
				$sqldata = str_replace(":year","'".$month_year[0]."'",$sqldata);
				$atrdsdao = new atrdsdao();
				$atrdsdao->StorePmsAri($sqldata,$db_name,'cfroomrateallocation',$this->hotelcode,$this->userunkid);
				//$this->log->logIt($this->hotelcode . " updateRoomRates - stored at rds " . get_class($this) . "-". $sqldata);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				/* End - Krunal Kaklotar - Strore Query for queue  */
				
			//}
			
			// Start - Krunal Kaklotar - 1.0.53.61 - 2017-11-24 : Separate Auditlog utility
			$objatdao = new atdao();
			$_SESSION['prefix'] = "SaaS_".$this->hotelcode."";
			$_SESSION[$_SESSION['prefix']]['hotel_code']=$this->hotelcode."";
			$_SESSION[$_SESSION['prefix']]['loginuserunkid'] = $userrow['userunkid']."";
			$_SESSION[$_SESSION['prefix']]['loginusername'] = "pms";
			if(count($this->contacts)>0){
				$objatdao->call_deamonauditlog_api("pmsreservationprocess","ROOMRATEALLOCATION","cfroomrateallocation","ratetypeunkid",$rateteypeid."",implode(',',$this->contacts),$month_year[1],$month_year[0],$updatewith,util::VisitorIP(),"roomtypeunkid",$roomtypeid."");
			}
			else
			{
				$objatdao->call_deamonauditlog_api("pmsreservationprocess","ROOMRATEALLOCATION","cfroomrateallocation","ratetypeunkid",$rateteypeid."",$contacts."",$month_year[1],$month_year[0],$updatewith,util::VisitorIP(),"roomtypeunkid",$roomtypeid."");
			}
			// End - Krunal Kaklotar
			
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - updateRoomRates - " . $e);
            $this->handleException($e);
        }
        return $flag;
    }
	//Manali - 1.0.33.38 - 15 May 2013 - START
	//Purpose : Enhancement - Derived Rate Update
	private function processDerivedRate($roomtypeunkid,$ratetypeunkid,$updatewith, $month,$year,$contactunkid)
	{
		$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . " processDerivedRate");
		$this->log->logIt($roomtypeunkid."|".$ratetypeunkid."|".$updatewith."|".$month."|".$year."|".$contactunkid);
		
		$dao = new dao();
		$derivedSQL="SELECT roomrateunkid,roomtypeunkid,ratetypeunkid,derivedrate,derivedratetype,display_name 
 FROM cfroomrate_setting AS rates WHERE hotel_code=:hotel_code AND derivedfrom IN (SELECT roomrateunkid FROM cfroomrateallocation AS rmrates 
 WHERE roomtypeunkid=:roomtypeunkid AND ratetypeunkid=:ratetypeunkid AND hotel_code=:hotel_code AND MONTH=:month AND YEAR=:year 
 AND contactunkid IN (".$contactunkid."))";//Sanjay Waman - 24 Aug 2018 - Add roomtypeunkid & ratetypeunkid field for rate threashold
		
		 $dao->initCommand($derivedSQL);
		 //$this->log->logIt($derivedSQL);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
		
		$dao->addParameter(":ratetypeunkid", $ratetypeunkid);
		$dao->addParameter(":roomtypeunkid", $roomtypeunkid);			
		$dao->addParameter(":hotel_code", $this->hotelcode);
		$dao->addParameter(":month", $month);
		$dao->addParameter(":year", $year);
		$derived_List = $dao->executeQuery();
		
		if(count($derived_List)>0)
		{
			foreach($derived_List as $derivedlist)
			{
				$this->log->logIt($derivedlist['roomrateunkid']."|".$derivedlist['roomtypeunkid']."|".$derivedlist['ratetypeunkid']."|".$derivedlist['derivedrate']."|".$derivedlist['derivedratetype']."|".$derivedlist['display_name']);//Sanjay Waman - 24 Aug 2018 - Add roomtypeunkid & ratetypeunkid field for rate threashold
				$split_data = explode(",",$updatewith);
				
				$derived_updatedata='';
				for($i=0;$i<count($split_data);$i++)
				{
					$calrate=0;
					list($fieldkey,$fieldvalue) = explode("=",$split_data[$i]);
					//$this->log->logIt($fieldkey."|".$fieldvalue);
					if($derivedlist['derivedratetype']=='M')
					{
						$calrate=$fieldvalue*$derivedlist['derivedrate'];
					}
					else if($derivedlist['derivedratetype']=='A')
					{
						$calrate=$fieldvalue+$derivedlist['derivedrate'];
					}
					//Sanjay Waman - 24 Aug 2018 - Rate Threshold apply in PMS -Start
					$this->log->logIt("Lable >>".$fieldkey);
					//$this->log->logIt("Dirive rateplan value check befor >>".json_encode($this->DeriveRateThreshold));
					$this->RateThresholdCheck($this->hotelcode,$derivedlist['roomtypeunkid'],$calrate);//Sanjay Waman - 29 Aug 2018 - Rate Threshold apply in PMS
					$this->log->logIt("Dirive rateplan value check for RateThresholdCheck >>".json_encode($this->DeriveRateThreshold));
					//die;
					if(strpos($fieldkey,'day_base_') !== false)
					{
						$this->log->logIt("Lable2 >>".$fieldkey);
						$this->log->logIt("array for derive room - ".json_encode($this->DeriveRateThreshold,true));
						if(isset($this->DeriveRateThreshold) && $this->DeriveRateThreshold !=[] )
						{
							foreach($this->DeriveRateThreshold as $roomid => $ratethresholdvalue )
							{
								$arrVal='';
								if($derivedlist['roomtypeunkid'] == $roomid)
								{
									$Ratethreshol_array=explode("|",$ratethresholdvalue);
									if($Ratethreshol_array[0]>$calrate) 
									{
										$this->log->logIt("[Roomtype:".$roomid."]Minimum - Rath Threshold applied >>".$Ratethreshol_array[0]);
										$calrate = $Ratethreshol_array[0];
										$arrVal = $derivedlist['roomtypeunkid']."|".$derivedlist['ratetypeunkid']." - This Derived Rateplan is updated with Minimum Rate Threshold (".$calrate.")";
										if(!in_array($arrVal,$this->DeriveRateThresholdApplied))
										{
											array_push($this->DeriveRateThresholdApplied,$arrVal);
										}
										
									}
									elseif($Ratethreshol_array[1]<$calrate)
									{
										$this->log->logIt("[Roomtype:".$roomid."]Maximum - Rath Threshold applied >>".$Ratethreshol_array[1]);
										$calrate = $Ratethreshol_array[1];
										$arrVal = $derivedlist['roomtypeunkid']."|".$derivedlist['ratetypeunkid']." - This Derived Rateplan is updated with Maximum Rate Threshold (".$calrate.")";
										if(!in_array($arrVal,$this->DeriveRateThresholdApplied))
										{
											array_push($this->DeriveRateThresholdApplied,$arrVal);
										}
									}
									else
									{
										$calrate = $calrate;
									}
								}
							}
							
						}
					}
					//Sanjay Waman - END
					$derived_updatedata=(($derived_updatedata=='')?($fieldkey."=".$calrate):($derived_updatedata.",".$fieldkey."=".$calrate));
				}
				
				$this->log->logIt($derived_updatedata);
				if($derived_updatedata!='')
				{
					$dao = new dao();
					$strSql = " UPDATE cfroomrateallocation as cfra";
					$strSql .= " SET " . $derived_updatedata;
					$strSql .= " WHERE cfra.roomrateunkid=:roomrateunkid AND cfra.hotel_code=:hotel_code AND cfra.month=:month and cfra.year=:year";
					$strSql.=" and cfra.contactunkid IN (".$contactunkid.") ";
					
					// Old Login - Krunal Kaklotar
					/*$dao->initCommand($strSql);
					
					$dao->addParameter(":roomrateunkid", $derivedlist['roomrateunkid']);					
					$dao->addParameter(":hotel_code", $this->hotelcode);
					$dao->addParameter(":month", $month);
					$dao->addParameter(":year", $year);
					$dao->executeNonQuery();*/
					
					
					/* Start - Krunal Kaklotar - 2018-04-23 - Strore Query for queue */
					//if($this->hotelcode == "8963"){
						
						global $connection;
						
						//get database name from connection object
						$connection_str = explode("dbname=",$connection->getConnectionString());
						$db_name= "";
						if(count($connection_str) > 0){
							$db_name =  $connection_str[1];	
						}
						
						//Strat - Krunal Kaklotar - 2018-04-23 - prepare upate query to store in rds
						$sqldata = $strSql;
						$sqldata = str_replace(":roomrateunkid","'".$derivedlist['roomrateunkid']."'",$sqldata);
						$sqldata = str_replace(":hotel_code","'".$this->hotelcode."'",$sqldata);
						$sqldata = str_replace(":month","'".$month."'",$sqldata);
						$sqldata = str_replace(":year","'".$year."'",$sqldata);
						$atrdsdao = new atrdsdao();
						$atrdsdao->StorePmsAri($sqldata,$db_name,'cfroomrateallocation',$this->hotelcode,$this->userunkid);
						//$this->log->logIt($this->hotelcode . " processDerivedRate - stored at rds " . get_class($this) . "-". $sqldata);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]	
					//}
					/* End - Krunal Kaklotar - Strore Query for queue  */
					
					// Start - Krunal Kaklotar - 1.0.53.61 - 2017-11-24 : Separate Auditlog utility
					$objatdao = new atdao();
					$_SESSION['prefix'] = "SaaS_".$this->hotelcode."";
					$_SESSION[$_SESSION['prefix']]['hotel_code']=$this->hotelcode."";
					$_SESSION[$_SESSION['prefix']]['loginuserunkid'] = "";
					$_SESSION[$_SESSION['prefix']]['loginusername'] = "pms";
					$objatdao->call_deamonauditlog_api("pmsreservationprocess","ROOMRATEALLOCATION","cfroomrateallocation","roomrateunkid",$derivedlist['roomrateunkid']."",$contactunkid."",$month,$year,$derived_updatedata,util::VisitorIP());
					// End - Krunal Kaklotar					
				}
			}
		}
	}
	//Manali - 1.0.33.38 - 15 May 2013 - START
	//sushma - Function to get contact list - START
	private function getContactsForUpdate()
    {
		try
		{
			$this->log->logIt(get_class($this)."-"."getContactsForUpdate");
			$dao = new dao();
			$list_contact = "SELECT contactunkid FROM ".dbtable::Contact." WHERE hotel_code=:hotel_code AND FIND_IN_SET(contacttypeunkid,'".$this->readConfigParameter("PMSRatesUpdateOnContact")."')";
			$dao->initCommand($list_contact);
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$contacts_list = $dao->executeQuery();
			$contacts='';
			//$this->log->logIt("Contacts list in database =========>".print_r($contacts_list,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			$contact_list_print = json_encode($contacts_list);
			$this->log->logIt("Contacts list in database =========>".$contact_list_print);
			foreach($contacts_list as $contactlist)
				$contacts=($contacts==''?$contactlist['contactunkid']:$contacts.",".$contactlist['contactunkid']);
			
			$this->log->logIt("contacts list in database =========>".$contacts);		
			return $contacts;    
		}
		catch(Exception $e)
		{
			$this->log->logIt(get_class($this)."-"."getContactsForUpdate"."-".$e);
			return -1;
		}	
    }
    private function prepareKeyVal() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "prepareKeyVal");
            $daylist = $this->generateDaysList();  // generate array  list            
            $keyval = array();
			$bflag = false;//Manali - 1.0.33.38 - 15 Apr 2013,Purpose : Customization - Need base rate to be set as optional. Any one rates can be passed either base, extra adult or extra child rates 
            $eaflag = false;
            $ecflag = false;
			
            for ($i = 0; $i < count($daylist); $i++) {
                $month_year = '';
				
				//Manali - 1.0.33.38 - 15 Apr 2013 - START
				//Purpose : Customization - Need base rate to be set as optional. Any one rates can be passed either base, extra adult or extra child rates 
				if (trim($this->roomrate[$i]->Base) != '' && intval($this->roomrate[$i]->Base) >= 0) {
                    $bflag = true;
                } else {
                    $bflag = false;
                }
				//Manali - 1.0.33.38 - 15 Apr 2013 - END
                if (trim($this->roomrate[$i]->ExtraAdult) != '' && intval($this->roomrate[$i]->ExtraAdult) >= 0) {
                    $eaflag = true;
                } else {
                    $eaflag = false;
                }
                if (trim($this->roomrate[$i]->ExtraChild) != '' && intval($this->roomrate[$i]->ExtraChild) >= 0) {
                    $ecflag = true;
                } else {
                    $ecflag = false;
                }
                $keyval[$i] = array();
				
                foreach ($daylist[$i] as $day) {					
					//Manali - 1.03.31-36 - 15 Feb 2013 - START
					//Purpose : Check for past date room rate update
					if($day!='')
					{
						if (date('Y-n', strtotime($day)) != $month_year) {
							$month_year = date('Y-n', strtotime($day));
							$keyval[$i]['baserates'][$month_year] = '';
						}
						
						//Manali - 1.0.33.38 - 15 Apr 2013 - START
						//Purpose : Customization - Need base rate to be set as optional. Any one rates can be passed either base, extra adult or extra child rates 
						if ($bflag) {						
							if (!isset($keyval[$i]['baserates'][$month_year]))
								$keyval[$i]['baserates'][$month_year] = '';
							$keyval[$i]['baserates'][$month_year].="day_base_" . date('j', strtotime($day)) . "=" . $this->roomrate[$i]->Base . ",";
						}
						//Manali - 1.0.33.38 - 15 Apr 2013 - END
						
						if ($eaflag) {
							if (!isset($keyval[$i]['extraadult'][$month_year]))
								$keyval[$i]['extraadult'][$month_year] = '';
							$keyval[$i]['extraadult'][$month_year].="day_extra_adult_" . date('j', strtotime($day)) . "=" . $this->roomrate[$i]->ExtraAdult . ",";
						}
						if ($ecflag) {
							if (!isset($keyval[$i]['extrachild'][$month_year]))
								$keyval[$i]['extrachild'][$month_year] = '';
							$keyval[$i]['extrachild'][$month_year].="day_extra_child_" . date('j', strtotime($day)) . "=" . $this->roomrate[$i]->ExtraChild . ",";
						}
					}
					else
					{
						$month_year = date('Y-n', strtotime($this->todaysdate));
						
						//Manali - 1.0.33.38 - 15 Apr 2013 - START
						//Purpose : Customization - Need base rate to be set as optional. Any one rates can be passed either base, extra adult or extra child rates 
						if ($bflag) {	
							if (!isset($keyval[$i]['baserates'][$month_year]))
								$keyval[$i]['baserates'][$month_year] = '';							
							$keyval[$i]['baserates'][$month_year].="";
						}
						//Manali - 1.0.33.38 - 15 Apr 2013 - END
						if ($eaflag) {
							if (!isset($keyval[$i]['extraadult'][$month_year]))
								$keyval[$i]['extraadult'][$month_year] = '';
							$keyval[$i]['extraadult'][$month_year].="";
						}
						if ($ecflag) {
							if (!isset($keyval[$i]['extrachild'][$month_year]))
								$keyval[$i]['extrachild'][$month_year] = '';
							$keyval[$i]['extrachild'][$month_year].="";
						}
					}					
					//Manali - 1.03.31-36 - 15 Feb 2013 - END
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
    private function generateDaysList() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateDaysList");
            for ($j = 0; $j < count($this->ratetype); $j++) {
                
				//Manali - 1.03.31-36 - 15 Feb 2013 - START
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
					
					$this->log->logIt($this->roomtypeid[$j]."|".$this->ratetypeid[$j]);
						
					array_push($this->datelist,array($this->roomtypeid[$j]."|".$this->ratetypeid[$j] => array("fromdate" => $this->fromdate[$j]."","todate" => $prev_day))); 
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
				
				//Manali - 1.03.31-36 - 15 Feb 2013 - END               
            }
			
            return $alldays;
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateDaysList" . "-" . $e);
            $this->handleException($e);
        }
    }
    private function handleException($e) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "handleException");
            $this->generateGeneralErrorMsg('500', "Error occured during processing");
            echo $this->xmlDoc->saveXML();
            exit;
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "handleException" . "-" . $e);
            exit;
        }
    }
	private function isUpdateAllowed(){
	  try {
	  		$flag=false;
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "isUpdateAllowed");
			if($this->readConfigParameter("PMSRatesUpdate")==1)
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
	//Sanjay Waman - 14 Aug 2018 - Rate Threshold apply in PMS - START
	private function RateThresholdCheck($hotelcode,$roomtypeck,$rate){
	try {
		    $flag='';
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "RateThresholdCheck");
			$dao = new dao();
			$this->log->logIt("HotelCode - ".$hotelcode." RoomType - ".$roomtypeck."");
			$strSql  =" SELECT roomtypeunkid,roomtype,threshold_minrate ,threshold_maxrate FROM ".dbtable::RoomType." WHERE ".
					  " roomtypeunkid=:roomtypeunkid AND hotel_code=:hotel_code ";
			$strSql .= " ORDER BY fsortkey, roomtypeunkid ASC";			
			$dao->initCommand($strSql);			
			$dao->addParameter(":hotel_code", $hotelcode);
			$dao->addParameter(":roomtypeunkid", $roomtypeck);
			$result = $dao->executeRow();
			//$this->log->logIt("Data >>(".$roomtypeck.") ".json_encode($result));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			if(isset($result['threshold_minrate']) && isset($result['threshold_maxrate']))
			{
				if(!array_key_exists($roomtypeck, $this->DeriveRateThreshold))
				{
					$this->DeriveRateThreshold[$roomtypeck]=$result['threshold_minrate']."|".$result['threshold_maxrate'];
				}
				
				if($result['threshold_minrate']<=$rate && $result['threshold_maxrate']>=$rate)
				{
					$flag="true";
				}
				else
				{
					$flag="false";
				}
			}
			else
			{
				$flag="true";
			}
			
			$this->log->logIt("Flag - ".$flag);
			$return_arr = array("result"=>$flag,"min_val"=>$result['threshold_minrate'].'',"max_val"=>$result['threshold_maxrate'].'');
			return $return_arr;
			
		} catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "RateThresholdCheck - " . $e);
            $this->handleException($e);
			return false;
        }
		
	}
	//Sanjay Waman - END
}
?>
