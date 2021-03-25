<?php
class pms_update_roomrates_nonlinear {
    private $log;
    private $module = "pms_update_roomrates_nonlinear";
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
	
	private $contacts;	
	private $flag_contact;    
	private $datelist = array(); 
	private $todaysdate; 
	private $datanotprocessed = array();
	private $contactunkids_forrateupdate = ''; //Falguni Rana - 15th Jun 2018, Purpose - Common Pool
	public $returnflag;//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
	
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
   
public function executeRequest($hotelcode, $ratetype, $ratetypeid,$roomtypeid, $fromdate, $todate, $roomrate,$contacts,$ignoreroomtypelist,$ignoreratetypelist,$ignorerateplanlist=[]) //Manali - 1.0.31.36 - 15 Feb 2013, Purpose : Fixed Bug - Check for roomtype/ratetype exists or not on web
//Dharti Savaliya - 2021-01-21 - Add $ignorerateplanlist CEN-1875
{
        $this->log->logIt("Hotel_" . $hotelcode . " - " . get_class($this) . "-" . "executeRequest");
        try {
            $this->hotelcode = $hotelcode;
			$this->xmlDoc = new DOMDocument('1.0','UTF-8');
			$this->xmlDoc->xmlStandalone = true;
			$this->xmlRoot = $this->xmlDoc->createElement("RES_Response");
			$this->xmlDoc->appendChild($this->xmlRoot);
			
			//Sanjay Waman- 09 Sep 2019 - JsonFormat [CEN-1086] - Start
			if(isset($this->returnflag))
			{
				 $this->log->logIt("XMLJSONFlag>>>>".$this->returnflag);
			}
			//Sanjay Waman -End
			
			if($this->isUpdateAllowed()){
				$this->ratetype = $ratetype;
           		$this->ratetypeid = $ratetypeid;
            	$this->roomtypeid = $roomtypeid;		
				$this->fromdate = $fromdate;
				$this->todate = $todate;
				$this->roomrate = $roomrate;				
				$this->contacts = $contacts;
				
				$this->recs_updates=0;				
							
				$this->todaysdate = $this->readConfigParameter("todayDate");
				
				//$this->log->logIt("roomrate >>".print_r($roomrate,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("ratetype >>".print_r($ratetype,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("ratetypeid >>".print_r($ratetypeid,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("roomtypeid >>".print_r($roomtypeid,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("fromdate >>".print_r($fromdate,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("todate >>".print_r($todate,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				//$this->log->logIt("contacts >>".print_r($contacts,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				
				//Falguni Rana - 15th Jun 2018 - START
				//Purpose - Common Pool
				$objmasterdao = new masterdao();
				$this->contactunkids_forrateupdate = $objmasterdao->getOnlyIndependentSource(((count($this->contacts) >0) ? implode(',',$this->contacts) : $this->getContactsForUpdate()),'rates',$this->hotelcode);
				unset($objmasterdao);
				if($this->contactunkids_forrateupdate == '') {
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
					{
						return $this->generateGeneralErrorJson('134', "All Source(s) are using rate of other source, thefore no update will be allowed.");
					}
					else
					{
						$this->generateGeneralErrorMsg('134', "All Source(s) are using rate of other source, thefore no update will be allowed.");
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
						{
							return $this->xmlDoc->saveXML();
						}
						echo $this->xmlDoc->saveXML();
						return;	
					}
				}
				$this->log->logIt("Final rate update sources => ".$this->contactunkids_forrateupdate);
				//Falguni Rana - 15th Jun 2018 - END
				
				//Purpose : Addons requested by 3rd parties using our API - RoomerPMS				
				//Code for identify contact is correct or not - start
				if(count($this->contacts) >0)
				{
					$contacts_database = $this->getContactsForUpdate();					
					$contactid = explode(",", $contacts_database);
					//$this->log->logIt("Contacts in Database >>".print_r($contactid, TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					$this->log->logIt("Count of array >>".count($this->contacts));
					$contact_requestarray = [];
					for ($j = 0; $j < count($this->contacts); $j++)
					{
								$contact_requestarray[] = (string)$this->contacts[$j];
					}
					//$this->log->logIt("Contacts in request >>".print_r($contact_requestarray, TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]					
					$result = array_intersect($contact_requestarray, $contactid);
					//$this->log->logIt("result >>".print_r($result, TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					if(count(array_intersect($contact_requestarray, $contactid)) == count($this->contacts))
					{
								$flag_contact = "1";
					}
					else
					{
								$flag_contact = "0";
					}
					$this->log->logIt("flag >>".$flag_contact);
				}
				
				if(count($this->contacts) >0)
				{
					if($flag_contact != "0")
					{
						//Code for identify contact is correct or not - end
                         //Dharti Savaliya - 2021-01-21 Purpose : Skip derived ratepaln update CEN-1875
						if ($this->processRequest($ignoreroomtypelist,$ignoreratetypelist,$ignorerateplanlist)) 
						{
							//$this->log->logIt($this->recs_updates."|".count($this->datelist)."|".count($ignoreroomtypelist)."|".count($ignoreratetypelist));
							if($this->recs_updates==0 && count($this->datelist)==0 && count($ignoreroomtypelist)==0  && count($ignoreratetypelist)==0)
							{
								if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
								{
									return $this->generateGeneralErrorJson('0', "Success",array("Success"=>array("SuccessMsg"=>'No rates are updated on web as PMS - Web rates are same.')));
								}
								else
								{
									$success = $this->xmlDoc->createElement("Success");
									$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
										$succ_msg->appendChild($this->xmlDoc->createTextNode('No rates are updated on web as PMS - Web rates are same.'));
									$success->appendChild($succ_msg);
									$this->xmlRoot->appendChild($success);								
									$this->generateGeneralErrorMsg('0','Success');
									$str = $this->xmlDoc->saveXML();
									if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
									{
										return $str;
									}
									echo $str;	
								}
							}
							else if($this->recs_updates>0 && count($this->datelist)==0 && count($ignoreroomtypelist)==0  && count($ignoreratetypelist)==0)
							{
								if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
								{
									return $this->generateGeneralErrorJson('0', "Success",array("Success"=>array("SuccessMsg"=>'Room Rates Successfully Updated.')));
								}
								else
								{
									$success = $this->xmlDoc->createElement("Success");
									$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
										$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Rates Successfully Updated'));
									$success->appendChild($succ_msg);
									$this->xmlRoot->appendChild($success);
									$this->generateGeneralErrorMsg('0','Success');	
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
                                {
                                    $roomtype_not_exists_msg = 'Room Types Ids : '.implode(",",$ignoreroomtypelist).' not exists on web so not updated.';      
                                }
                                
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
										//$this->log->logIt($key);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
										//$this->log->logIt($value);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
									
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
								
								if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
								{
									if($allRTTIgnored && $allRTTIgnored)
									{
										return $this->generateGeneralErrorJson('1', $error_msg,array("Success"=>array("SuccessMsg"=>'Room Rates not Updated.')));
									}
									else
									{
										return $this->generateGeneralErrorJson('1', $error_msg,array("Success"=>array("SuccessMsg"=>'Room Rates Partially Updated.')));
									}
								}
								else
								{
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
									if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
									{
										return $str;
									}
									echo $str;		
								}					
							}
						} 						
						else {
							if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
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
					else 
					{
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
						{
							return $this->generateGeneralErrorJson('133', "Saparate source detail mismatch.");
						}
						else
						{
							$this->generateGeneralErrorMsg('133', "Saparate source detail mismatch.");
							if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
							{
								return $this->xmlDoc->saveXML();
							}
							echo $this->xmlDoc->saveXML();
							return;	
						}
					}
				}
				else
				{
					//Code for identify contact is correct or not - end
                     //Dharti Savaliya - 2021-01-21 Purpose : Skip derived ratepaln update CEN-1875
					if ($this->processRequest($ignoreroomtypelist,$ignoreratetypelist,$ignorerateplanlist)) 
					{
						//$this->log->logIt($this->recs_updates."|".count($this->datelist)."|".count($ignoreroomtypelist)."|".count($ignoreratetypelist));
						if($this->recs_updates==0 && count($this->datelist)==0 && count($ignoreroomtypelist)==0  && count($ignoreratetypelist)==0)
						{
							if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
							{
								return $this->generateGeneralErrorJson('0', 'Success',array("Success"=>array("SuccessMsg"=>'No rates are updated on web as PMS - Web rates are same.')));
							}
							else
							{
								$success = $this->xmlDoc->createElement("Success");
								$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
									$succ_msg->appendChild($this->xmlDoc->createTextNode('No rates are updated on web as PMS - Web rates are same.'));
								$success->appendChild($succ_msg);
								$this->xmlRoot->appendChild($success);							
								$this->generateGeneralErrorMsg('0','Success');	
								$str = $this->xmlDoc->saveXML();
								if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
								{
									return $str;
								}
								echo $str;	
							}
						}
						else if($this->recs_updates>0 && count($this->datelist)==0 && count($ignoreroomtypelist)==0  && count($ignoreratetypelist)==0)
						{
							if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
							{
								return $this->generateGeneralErrorJson('0', 'Success',array("Success"=>array("SuccessMsg"=>'Room Rates Successfully Updated.')));
							}
							else
							{	
								$success = $this->xmlDoc->createElement("Success");
								$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
									$succ_msg->appendChild($this->xmlDoc->createTextNode('Room Rates Successfully Updated'));
								$success->appendChild($succ_msg);
								$this->xmlRoot->appendChild($success);							
								$this->generateGeneralErrorMsg('0','Success');
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
                                {
                                    $roomtype_not_exists_msg = 'Room Types Ids : '.implode(",",$ignoreroomtypelist).' not exists on web so not updated.';
                                }   
                                      								
							if(count($ignoreratetypelist)>0)
								$ratetype_not_exists_msg = 'Rate Types Ids : '.implode(",",$ignoreratetypelist).' not exists on web so not updated.';
							
							if(count($ignoreroomtypelist)>0 || count($ignoreratetypelist)>0)
								$error_msg = ($error_msg!='')?($roomtype_not_exists_msg." ".$ratetype_not_exists_msg." ".$error_msg) : ($roomtype_not_exists_msg." ".$ratetype_not_exists_msg." ");
							
							if(count($this->datelist)>0 && (!$allRTTIgnored || !$allRTTIgnored)) 
							{
								$rmtypelist ='';								
								foreach($this->datelist as $key => $value)
								{
									foreach($value as $datakey =>$datavalue)
									{
										list($roomtypeid,$ratetypeid) = explode("|",$datakey);
										
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
							
							if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
							{
								if($allRTTIgnored && $allRTTIgnored)
								{
									return $this->generateGeneralErrorJson('1', $error_msg,array("Success"=>array("SuccessMsg"=>'Room Rates not Updated.')));
								}
								else
								{
									return $this->generateGeneralErrorJson('1', $error_msg,array("Success"=>array("SuccessMsg"=>'Room Rates Partially Updated.')));	
								}
							}
							else
							{
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
								if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 03 Jun 2019 - Return flag[CEN-1165]
								{
									return $str;
								}
								echo $str;	
							}					
						}
					} 					
					else {
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
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
			}
			else{
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
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
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - executeRequest - " . $e);
            $this->handleException($e);
        }
    }
    //Dharti Savaliya - 2021-01-21 Purpose : Skip derived ratepaln update CEN-1875
    private function processRequest($ignoreroomtypelist,$ignoreratetypelist,$ignorerateplanlist=[]) 
	{
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "processRequest");
            $flag = true;
						
            $keyVal = $this->prepareKeyVal();
			
			//$this->log->logIt("final Array >> ".print_r($keyVal,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			$this->log->logIt("final Array >> ".count($keyVal));
			//Sanjay Waman - 23 Aug 2018 - Query foramt regarding changes - START
            for ($i = 0; $i < count($keyVal); $i++) {
				$final_value=[];
                $val = '';
                foreach ($keyVal[$i] as $updateColumn => $allocations) {
                    foreach ($allocations as $key => $val) {
                        
						if($val!="")//Sanjay Waman - 04 Sep 2018 - check Value with null value
						{
							$this->log->logIt("Value >>".$val);
							$val = substr($val, 0, strlen($val) - 1);
							if(isset($final_value[$key]))
							{
								$final_value[$key] .= ",".$val;
							}
							else
							   $final_value[$key] =  $val;
						}
                    }  
                }
				
                //$this->log->logIt("final Value >>".json_encode($final_value,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
				
				foreach($final_value as $date => $setdata)
				{
                    
                    //Dharti Savaliya - 2021-01-21 Purpose : Skip derived ratepaln update CEN-1875
					if(!in_array($this->roomtypeid[$i]."",array_values($ignoreroomtypelist),TRUE) && !in_array($this->ratetypeid[$i]."",array_values($ignoreratetypelist),TRUE) && $setdata!='' && (!in_array($this->roomtypeid[$i]."_".$this->ratetypeid[$i]."",array_values($ignorerateplanlist),TRUE) ))
					{
						if (!$this->updateRoomRates($this->ratetypeid[$i],$this->roomtypeid[$i], explode("-", $date), $setdata))
						{
							$flag = false;
							break;
						}
					}
				}
				
                /*$val = implode(', ', $final_value);
                $this->log->logIt("final Value >>".$val);                
                if(!in_array($this->roomtypeid[$i]."",array_values($ignoreroomtypelist),TRUE) && !in_array($this->ratetypeid[$i]."",array_values($ignoreratetypelist),TRUE) && $val!='')
                {
                    if (!$this->updateRoomRates($this->ratetypeid[$i],$this->roomtypeid[$i], explode("-", $key), $val))
                    {
                        $flag = false;
                        break;
                    }
                }*/
				//Sanjay Waman  - END
                if (!$flag)
                    break;
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
			
			$dao = new dao();			
			$strSql1 = "SELECT userunkid from cfuser where username='pms' and hotel_code=".$this->hotelcode;            
            $dao->initCommand($strSql1);
			$userrow=(array)$dao->executeRow();
			
			$dao = new dao();	
			$dao->initCommand("SELECT @loginuser:=:userunkid,@loginhotel:=:hotel_code;");
			$dao->addParameter(":userunkid",$userrow['userunkid']);
			$dao->addParameter(":hotel_code",$this->hotelcode);
			$result = $dao->executeQuery();
			
			$this->userunkid = $userrow['userunkid'];
            $flag = false;
            $strSql = " UPDATE cfroomrateallocation as cfra";
            $strSql .= " SET " . $updatewith;
            $strSql .= " WHERE cfra.ratetypeunkid=:ratetypeunkid AND cfra.roomtypeunkid=:roomtypeunkid AND cfra.hotel_code=:hotel_code AND cfra.month=:month and cfra.year=:year";
			//Falguni Rana - 15th Jun 2018 - START
			//Purpose - Common Pool
			$contacts = $this->contactunkids_forrateupdate;
			$strSql.=" and cfra.contactunkid in (".$contacts.")";
			/*
			
			if(count($this->contacts)>0){
				$strSql.=" and FIND_IN_SET(cfra.contactunkid,:contacts) ";
			}
			else{
				
                $contacts = $this->getContactsForUpdate();
                $strSql.=" and cfra.contactunkid in (".$contacts.")"; 
			}
			*/
			//Falguni Rana - 15th Jun 2018 - END
			
            $dao = new dao();
            $dao->initCommand($strSql);
            $dao->addParameter(":ratetypeunkid", $rateteypeid);
            $dao->addParameter(":roomtypeunkid", $roomtypeid);			
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":month", $month_year[1]);
            $dao->addParameter(":year", $month_year[0]);	
			
			//Falguni Rana - 15th Jun 2018 - START
			//Purpose - Common Pool
			/*
			if(count($this->contacts)>0){
				
				$dao->addParameter(":contacts", implode(',',$this->contacts));
				$contacts = implode(',',$this->contacts);//Purpose : Enhancement - Derived Rate Update
			}
			*/
			//Falguni Rana - 15th Jun 2018 - END
			//$rowcount=$dao->executeNonQuery(); // Krunal Kaklotar
			$this->recs_updates=$this->recs_updates+1;	// Krunal Kaklotar - Discussed with Sushma - we every time display "successfully invenotry update message"	

			//Manali - 1.0.33.38 - 15 May 2013 - START
			//Purpose : Enhancement - Derived Rate Update
			$this->processDerivedRate($roomtypeid,$rateteypeid,$updatewith,$month_year[1],$month_year[0],$contacts);
			
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
	
	//Purpose : Enhancement - Derived Rate Update
	private function processDerivedRate($roomtypeunkid,$ratetypeunkid,$updatewith, $month,$year,$contactunkid)
	{
		$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . " processDerivedRate");
		$this->log->logIt($roomtypeunkid."|".$ratetypeunkid."|".$updatewith."|".$month."|".$year."|".$contactunkid);
		
		$dao = new dao();
		$derivedSQL="SELECT roomrateunkid,derivedrate,derivedratetype,display_name 
 FROM cfroomrate_setting AS rates WHERE hotel_code=:hotel_code AND derivedfrom IN (SELECT roomrateunkid FROM cfroomrateallocation AS rmrates 
 WHERE roomtypeunkid=:roomtypeunkid AND ratetypeunkid=:ratetypeunkid AND hotel_code=:hotel_code AND MONTH=:month AND YEAR=:year 
 AND contactunkid IN (".$contactunkid."))";
		
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
				$this->log->logIt($derivedlist['roomrateunkid']."|".$derivedlist['derivedrate']."|".$derivedlist['derivedratetype']."|".$derivedlist['display_name']);
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
					$derived_updatedata=(($derived_updatedata=='')?($fieldkey."=".$calrate):($derived_updatedata.",".$fieldkey."=".$calrate));
				}
				
				$this->log->logIt($derived_updatedata);
				if($derived_updatedata!='')
				{
					//$dao = new dao();
					$strSql = " UPDATE cfroomrateallocation as cfra";
					$strSql .= " SET " . $derived_updatedata;
					$strSql .= " WHERE cfra.roomrateunkid=:roomrateunkid AND cfra.hotel_code=:hotel_code AND cfra.month=:month and cfra.year=:year";
					$strSql.=" and cfra.contactunkid IN (".$contactunkid.") ";
					
					/*$dao->initCommand($strSql);
					
					$dao->addParameter(":roomrateunkid", $derivedlist['roomrateunkid']);					
					$dao->addParameter(":hotel_code", $this->hotelcode);
					$dao->addParameter(":month", $month);
					$dao->addParameter(":year", $year);
					$dao->executeNonQuery();*/
					//Sanjay - 12 Dec 2019 - Store entry in DB
					global $connection;
					$connection_str = explode("dbname=",$connection->getConnectionString());
					$db_name= "";
					if(count($connection_str) > 0){
						$db_name =  $connection_str[1];	
					}
					$sqldata = $strSql;
					$sqldata = str_replace(":roomrateunkid","'".$derivedlist['roomrateunkid']."'",$sqldata);
					$sqldata = str_replace(":hotel_code","'".$this->hotelcode."'",$sqldata);
					$sqldata = str_replace(":month","'".$month."'",$sqldata);
					$sqldata = str_replace(":year","'".$year."'",$sqldata);
					$atrdsdao = new atrdsdao();
					$atrdsdao->StorePmsAri($sqldata,$db_name,'cfroomrateallocation',$this->hotelcode,$this->userunkid);
					$this->log->logIt($this->hotelcode . " Database -  " .$db_name);
					//$this->log->logIt($this->hotelcode . " processDerivedRate - stored at rds " . get_class($this) . "-". $sqldata);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					//Sanjay - End
					global $connection;
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
			//$this->log->logIt("Contacts list in database >>".print_r($contacts_list,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			$contact_list_print = json_encode($contacts_list);
			//$this->log->logIt("Contacts list in database >>".$contact_list_print);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			foreach($contacts_list as $contactlist)
				$contacts=($contacts==''?$contactlist['contactunkid']:$contacts.",".$contactlist['contactunkid']);
			
			$this->log->logIt("contacts list in database >>".$contacts);		
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
            $daylist = $this->generateDaysList();         
            $keyval = array();
			$bflag = false;//Manali - 1.0.33.38 - 15 Apr 2013,Purpose : Customization - Need base rate to be set as optional. Any one rates can be passed either base, extra adult or extra child rates 
            $eaflag = false;
            $ecflag = false;
            $Adult1 = $Adult2 = $Adult3 = $Adult4 = $Adult5 = $Adult6 = $Adult7 = false;
			$Child1 = $Child2 = $Child3 = $Child4 = $Child5 = $Child6 = $Child7 = false;
            
            //$NLadult=array('adult1','adult2','adult3','adult4','adult5','adult6','adult7',);
            //$NLchild=array('child1','child2','child3','child4','child5','child6','child7',);
            $NLadult=array('Adult1','Adult2','Adult3','Adult4','Adult5','Adult6','Adult7',);
            $NLchild=array('Child1','Child2','Child3','Child4','Child5','Child6','Child7',);
            
            for ($i = 0; $i < count($daylist); $i++) {
                $month_year = '';
				
				//Manali - 1.0.33.38 - 15 Apr 2013 - START
				//Purpose : Customization - Need base rate to be set as optional. Any one rates can be passed either base, extra adult or extra child rates 
				if (isset($this->roomrate[$i]->Base) && trim($this->roomrate[$i]->Base) != '' && intval($this->roomrate[$i]->Base) >= 0) {
                    $bflag = true;
                } else {
                    $bflag = false;
                }
				//Manali - 1.0.33.38 - 15 Apr 2013 - END
                if (isset($this->roomrate[$i]->ExtraAdult) && trim($this->roomrate[$i]->ExtraAdult) != '' && intval($this->roomrate[$i]->ExtraAdult) >= 0) {
                    $eaflag = true;
                } else {
                    $eaflag = false;
                }
                if (isset($this->roomrate[$i]->ExtraChild) && trim($this->roomrate[$i]->ExtraChild) != '' && intval($this->roomrate[$i]->ExtraChild) >= 0) {
                    $ecflag = true;
                } else {
                    $ecflag = false;
                }
                foreach($NLadult as $keyVal){                    
                if (isset($this->roomrate[$i]->$keyVal) && trim($this->roomrate[$i]->$keyVal) != '' && intval($this->roomrate[$i]->$keyVal) >= 0)
                    {
                        ${$keyVal} = true;
                        
                    }
                    else
                    {
                        ${$keyVal} = false;       
                    }
                    $this->log->logIt("Adult Flag >>".${$keyVal});
                }
                foreach($NLchild as $keyVal){                    
                if (isset($this->roomrate[$i]->$keyVal) && trim($this->roomrate[$i]->$keyVal) != '' && intval($this->roomrate[$i]->$keyVal) >= 0)
                    {
                        ${$keyVal} = true;                        
                    }
                    else
                    {
                        ${$keyVal} = false;       
                    }
                     $this->log->logIt("Child Flag >>".${$keyVal});
                }
                
                $keyval[$i] = array();
				
                foreach ($daylist[$i] as $day) {					
					//Manali - 1.03.31-36 - 15 Feb 2013 - START
					//Purpose : Check for past date room rate update
					if($day!='')
					{
						if (date('Y-n', strtotime($day)) != $month_year) {
							$month_year = date('Y-n', strtotime($day));
							$keyval[$i]['Baserates'][$month_year] = '';
						}
						
						//Manali - 1.0.33.38 - 15 Apr 2013 - START
						//Purpose : Customization - Need base rate to be set as optional. Any one rates can be passed either base, extra adult or extra child rates 
						if ($bflag) {						
							if (!isset($keyval[$i]['Baserates'][$month_year]))
								$keyval[$i]['Baserates'][$month_year] = '';
							$keyval[$i]['Baserates'][$month_year].="day_base_" . date('j', strtotime($day)) . "=" . $this->roomrate[$i]->Base . ",";
						}
						//Manali - 1.0.33.38 - 15 Apr 2013 - END
						
						if ($eaflag) {
							if (!isset($keyval[$i]['Extraadult'][$month_year]))
								$keyval[$i]['Extraadult'][$month_year] = '';
							$keyval[$i]['Extraadult'][$month_year].="day_extra_adult_" . date('j', strtotime($day)) . "=" . $this->roomrate[$i]->ExtraAdult . ",";
						}
						if ($ecflag) {
							if (!isset($keyval[$i]['Extrachild'][$month_year]))
								$keyval[$i]['Extrachild'][$month_year] = '';
							$keyval[$i]['Extrachild'][$month_year].="day_extra_child_" . date('j', strtotime($day)) . "=" . $this->roomrate[$i]->ExtraChild . ",";
						}
                        $k = 1;
                        foreach($NLadult as $keyValnl){
                            $this->log->logIt("Adult Flag >>".${$keyValnl});
                            if (${$keyValnl}) {
                                $this->log->logIt("Rate >>".$this->roomrate[$i]->$keyValnl);
                                
                                if (!isset($keyval[$i][$keyValnl][$month_year]))
                                    $keyval[$i][$keyValnl][$month_year] = '';
                                $keyvalueadult = strtolower($keyValnl);
                                $keyval[$i][$keyValnl][$month_year].="day_{$keyvalueadult}_" . date('j', strtotime($day)) . "=" . $this->roomrate[$i]->$keyValnl . ",";
                                
                            }
                            $k++;
                        }
                        foreach($NLchild as $keyValnl){
                            $this->log->logIt("Child Flag >>".${$keyValnl});
                            if (${$keyValnl}) {
                                if (!isset($keyval[$i][$keyValnl][$month_year]))
                                    $keyval[$i][$keyValnl][$month_year] = '';
                                $keyvaluechild = strtolower($keyValnl);
                                $keyval[$i][$keyValnl][$month_year].="day_{$keyvaluechild}_" . date('j', strtotime($day)) . "=" . $this->roomrate[$i]->$keyValnl . ",";
                            }  
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
                        foreach($NLadult as $keyValnl){ 
                            if ($keyValnl) {
                                if (!isset($keyval[$i][$keyValnl][$month_year]))
                                    $keyval[$i][$keyValnl][$month_year] = '';
                                $keyval[$i][$keyValnl][$month_year].="";//Sanjay Waman - 04 Sep 2018 - Null value assigned 
                            }  
                        }
                        foreach($NLchild as $keyValnl){ 
                            if ($keyValnl) {
                                if (!isset($keyval[$i][$keyValnl][$month_year]))
                                    $keyval[$i][$keyValnl][$month_year] = '';
                                $keyval[$i][$keyValnl][$month_year].="";//Sanjay Waman - 04 Sep 2018 - Null value assigned 
                            }  
                        }
					}					
					//Manali - 1.03.31-36 - 15 Feb 2013 - END
                }
            }
			//$this->log->logIt("Final key value >>".print_r($keyval,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
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
	
	//Sanjay Waman - 09 Sep 2019 - Json format [CEN-1086] - Start
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
            
			if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
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
				echo $this->xmlDoc->saveXML();
				exit;	
			}
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
}
?>
