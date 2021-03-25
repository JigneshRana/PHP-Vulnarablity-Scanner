<?php
class pms_update_otheroperations {
    
    private $log;
    private $module = "pms_update_otheroperations";
    private $hotelcode;
    private $rateplan;
    private $rateplanid;
    private $fromdate;
    private $todate;
    private $operation;
    private $operationval;
    private $xmlDoc;
    private $xmlRoot;
    private $recs_updates;	
    private $datelist = array(); 
    private $todaysdate; 
    private $datanotprocessed = array();
	public $returnflag;//Sanjay Waman - 01 Jul 2019 - Return flag[CEN-1165]
	
	//Sushma - 1.0.52.60 - 27 June 2017 - START
	//Purpose : Addons requested by 3rd parties using our API - RoomerPMS
	private $contacts;
	private $flag_contact;
	//Sushma - 1.0.52.60 - 27 June 2017 - END
	private $contactunkids_forupdate = ''; //Falguni Rana - 16th Jun 2018, Purpose - Common Pool
		
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
    public function executeRequest($hotelcode,$rateplan, $rateplanid,$fromdate, $todate, $operation,$operationval,$contacts='',$ignorelist) //Sushma - 1.0.52.60 - 27 June 2017 - START
																																			//Purpose : Addons requested by 3rd parties using our API - RoomerPMS
    {
	$this->log->logIt("Hotel_" . $hotelcode . " - " . get_class($this) . "-" . "executeRequest");
	try
	{            
	    $this->hotelcode = $hotelcode;
	    $this->operation = $operation;
	    $this->xmlDoc = new DOMDocument('1.0','UTF-8');
	    $this->xmlRoot = $this->xmlDoc->createElement("RES_Response");
	    $this->xmlDoc->appendChild($this->xmlRoot);
		//Sanjay Waman- 06 Sep 2019 - JsonFormat [CEN-1086] - Start
		if(isset($this->returnflag))
		{
			 $this->log->logIt("XMLJSONFlag>>>>".$this->returnflag);
		}
		//Sanjay Waman -End
	    if($this->isUpdateAllowed())
	    {
		    $this->rateplan = $rateplan;
		    $this->rateplanid = $rateplanid;
		    $this->fromdate = $fromdate;
		    $this->todate = $todate;		   
		    $this->operationval = $operationval;
		    $this->recs_updates=0;	
		    #Sushma- 1.0.52.60 - 05 Jul 2017 - Start
			$this->contacts = $contacts;
			#Sushma - 1.0.52.60 - 05 Jul 2017 - End
		    //$this->log->logIt(print_r($ignorelist,TRUE));
		    
			//$this->log->logIt("rate plan=========>".print_r($rateplan,TRUE));
			//$this->log->logIt("rateplanid=========>".print_r($rateplanid,TRUE));
			//$this->log->logIt("fromdate=========>".print_r($fromdate,TRUE));
			//$this->log->logIt("todate=========>".print_r($todate,TRUE));
			//$this->log->logIt("operation value=========>".print_r($operationval,TRUE));
			//$this->log->logIt("contacts=========>".print_r($contacts,TRUE));
  		   
		    //Code for identify contact is correct or not - start
			if(!empty($this->contacts) && count($this->contacts) >0)
			{
					$contacts_database = $this->getContactsForUpdate();					
					$contactid = explode(",", $contacts_database);
					//$this->log->logIt("Contacts in Database=========>".json_encode($contactid, TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					$this->log->logIt("Count of array========>".count($this->contacts));
					//$this->log->logIt("Count of array========>".(string)$this->contacts[0]);
					$contact_requestarray = [];
					for ($j = 0; $j < count($this->contacts); $j++)
					{
								$contact_requestarray[] = (string)$this->contacts[$j];
					}
					//$this->log->logIt("Contacts in request=========>".json_encode($contact_requestarray, TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					$result = array_intersect($contact_requestarray, $contactid);
					//$this->log->logIt("result=========>".json_encode($result, TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
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
		   
		    switch($operation)
		    {
			case "coa":
			    $lbloperation = "COA";
			    break;
			case "cod":
			    $lbloperation = "COD";
			    break;
			case "minnights":
			    $lbloperation = "Min Nights";
			    break;
            // Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel - start
            case "maxnights":
			    $lbloperation = "Max Nights";
			    break;
            // Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel - end
			case "stopsell":
			     $lbloperation = "Stop Sell";
			    break;		    	
		    }
		   		    
		    //Falguni Rana - 16th Jun 2018 - START
			//Purpose - Common Pool
			$objmasterdao = new masterdao();
			$this->contactunkids_forupdate = $objmasterdao->getOnlyIndependentSource(((!empty($this->contacts) && count($this->contacts) >0) ? implode(',',$this->contacts) : $this->getContactsForUpdate()),$this->operation,$this->hotelcode);
			unset($objmasterdao);
			if($this->contactunkids_forupdate == '') {
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
				{
					return $this->generateGeneralErrorJson('134', "All Source(s) are using ".$lbloperation." of other source, thefore no update will be allowed.");
				}
				else
				{
					$this->generateGeneralErrorMsg('134', "All Source(s) are using ".$lbloperation." of other source, thefore no update will be allowed.");
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
					{
						return $this->xmlDoc->saveXML();
					}
					echo $this->xmlDoc->saveXML();
					return;	
				}
			}
			$this->log->logIt("Final rate update sources => ".$this->contactunkids_forupdate);
			//Falguni Rana - 16th Jun 2018 - END
			
		    $this->todaysdate = $this->readConfigParameter("todayDate");
			//proccessing request
			//Sushma - 1.0.52.60 - 25 July 2017 - START
		    //Purpose : Addons requested by 3rd parties using our API - RoomerPMS
			if(!empty($this->contacts) && count($this->contacts) >0 && $lbloperation == 'Stop Sell' )
			{
				if($flag_contact != "0")
				{
					//proccessing request
				   if($this->processRequest($ignorelist)) 
				   {
				   if($this->recs_updates==0 && count($this->datelist)==0 && count($ignorelist)==0)
				   {
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
						{
							return $this->generateGeneralErrorJson('0', "Success",array("Success"=>array("SuccessMsg"=>$lbloperation." not updated as PMS - Web ".$lbloperation." are same.")));
						}
						else
						{
							$success = $this->xmlDoc->createElement("Success");
							$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
							$succ_msg->appendChild($this->xmlDoc->createTextNode($lbloperation." not updated as PMS - Web ".$lbloperation." are same."));
							$success->appendChild($succ_msg);
							$this->xmlRoot->appendChild($success);
							$this->generateGeneralErrorMsg('0','Success');
							$str = $this->xmlDoc->saveXML();
							if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
							{
								 return $str;
							}
							echo $str;
						}
				   }
				   else if($this->recs_updates>0 && count($this->datelist)==0 && count($ignorelist)==0)
				   {
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
						{
							return $this->generateGeneralErrorJson('0', "Success",array("Success"=>array("SuccessMsg"=>$lbloperation." Successfully Updated")));
						}
						else
						{
							$success = $this->xmlDoc->createElement("Success");
							$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
							$succ_msg->appendChild($this->xmlDoc->createTextNode($lbloperation." Successfully Updated"));
							$success->appendChild($succ_msg);
							$this->xmlRoot->appendChild($success);
							$this->generateGeneralErrorMsg('0','Success');
							$str = $this->xmlDoc->saveXML();
							if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
							{
								 return $str;
							}
							echo $str;	
						}
				   }
				   else
				   {
					   //Falguni Rana - 1.0.50.55 - 11th Aug 2016 - START
					   //Purpose - Corrected error messages 
					   $allRPIgnored = true;				
					   foreach($rateplanid AS $rateplanunkid)
					   {
						   if(!in_array($rateplanunkid,$ignorelist))
						   {
							   $allRPIgnored = false;
							   break;
						   }
					   }
					   //Falguni Rana - 1.0.50.55 - 11th Aug 2016 - END
					   
					   $error_msg='';
					   if(count($ignorelist)>0)
						   $error_msg = 'Rate Plan Ids : '.implode(",",$ignorelist).' not exists on web so not updated.';
					   
					   if(count($this->datelist)>0 && !$allRPIgnored) //Falguni Rana - 1.0.50.55 - 11th Aug 2016, Purpose - Corrected error messages, Added && !$allRPIgnored
					   {
					   //$this->log->logIt(json_encode($this->datelist,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					   $rplanlist ='';
					   //$error_msg = ($error_msg!='')?($error_msg.'Rate Plans : ') : ('Rate Plans : '); //Falguni Rana - 1.0.50.55 - 11th Aug 2016, Purpose - Corrected error messages, Commented this line
					   
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
							   $rplanlist = ($rplanlist!='')?($rplanlist.", ".$datakey): ("".$datakey);
							   
							   $rplanlist =  $rplanlist." from ".$datavalue['fromdate'];
							   
							   $rplanlist =  $rplanlist." to ".$datavalue['todate'];
						   }
						   }
					   }
					   //Falguni Rana - 1.0.50.55 - 11th Aug 2016, Purpose - Corrected error messages, Commented below line and added very next line
					   //$error_msg = $error_msg.$rplanlist." ".$lbloperation." not updated due to dates passed.";
					   $error_msg = $error_msg.($rplanlist != '' ? ("Rate Plans : ".$rplanlist." ".$lbloperation." not updated due to dates passed.") : "");
					   }
					   
					   if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
						{
							if($allRPIgnored)
							{
								return $this->generateGeneralErrorJson('1', $error_msg,array("Success"=>array("SuccessMsg"=>$lbloperation.' not Updated.')));
							}
							else
							{
								return $this->generateGeneralErrorJson('1', $error_msg,array("Success"=>array("SuccessMsg"=>$lbloperation.' Partially Updated.')));	
							}
						}
						else
						{
							$success = $this->xmlDoc->createElement("Success");
							$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
							
							//Falguni Rana - 1.0.50.55 - 11th Aug 2016 - START
							//Purpose - Corrected error messages
							if($allRPIgnored)
								$succ_msg->appendChild($this->xmlDoc->createTextNode($lbloperation.' not Updated.'));
							else
								$succ_msg->appendChild($this->xmlDoc->createTextNode($lbloperation.' Partially Updated.'));
							//Falguni Rana - 1.0.50.55 - 11th Aug 2016 - END
							
							$success->appendChild($succ_msg);
							$this->xmlRoot->appendChild($success);
							
							//Falguni Rana - 1.0.50.55 - 11th Aug 2016, Purpose - Corrected error messages, Commented below line and added very next line
							//$this->generateGeneralErrorMsg('0','Success');
							$this->generateGeneralErrorMsg('1',$error_msg);
							
							$str = $this->xmlDoc->saveXML();
							if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
							{
								 return $str;
							}
							echo $str;	
						}
				   }
				   } 			
				   else
				   {
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
					{
						return $this->generateGeneralErrorJson('500', 'Error occured during processing.');	
					}
					else
					{
						$this->generateGeneralErrorMsg('500', "Error occured during processing.");
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
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
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
						{
							 return $this->xmlDoc->saveXML();
						}
							echo $this->xmlDoc->saveXML();
							return;
					}
				}
			}
		    //proccessing request
		    else
			{
			//Sushma - 1.0.52.60 - 25 July 2017 - END
				if($this->processRequest($ignorelist)) 
				{
				if($this->recs_updates==0 && count($this->datelist)==0 && count($ignorelist)==0)
				{
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
					{
						return $this->generateGeneralErrorJson('0', 'Success',array("Success"=>array("SuccessMsg"=>$lbloperation." not updated as PMS - Web ".$lbloperation." are same.")));
					}
					else
					{
						$success = $this->xmlDoc->createElement("Success");
						$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
						$succ_msg->appendChild($this->xmlDoc->createTextNode($lbloperation." not updated as PMS - Web ".$lbloperation." are same."));
						$success->appendChild($succ_msg);
						$this->xmlRoot->appendChild($success);
						$this->generateGeneralErrorMsg('0','Success');
						$str = $this->xmlDoc->saveXML();
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
						{
							 return $str;
						}
						echo $str;	
					}
				}
				else if($this->recs_updates>0 && count($this->datelist)==0 && count($ignorelist)==0)
				{
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
					{
						return $this->generateGeneralErrorJson('0', 'Success',array("Success"=>array("SuccessMsg"=>$lbloperation." Successfully Updated")));
					}
					else
					{
						$success = $this->xmlDoc->createElement("Success");
						$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
						$succ_msg->appendChild($this->xmlDoc->createTextNode($lbloperation." Successfully Updated"));
						$success->appendChild($succ_msg);
						$this->xmlRoot->appendChild($success);
						$this->generateGeneralErrorMsg('0','Success');
						$str = $this->xmlDoc->saveXML();
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
						{
							 return $str;
						}
						echo $str;	
					}
				}
				else
				{
					//Falguni Rana - 1.0.50.55 - 11th Aug 2016 - START
					//Purpose - Corrected error messages 
					$allRPIgnored = true;				
					foreach($rateplanid AS $rateplanunkid)
					{
						if(!in_array($rateplanunkid,$ignorelist))
						{
							$allRPIgnored = false;
							break;
						}
					}
					//Falguni Rana - 1.0.50.55 - 11th Aug 2016 - END
					
					$error_msg='';
					if(count($ignorelist)>0)
						$error_msg = 'Rate Plan Ids : '.implode(",",$ignorelist).' not exists on web so not updated.';
					
					if(count($this->datelist)>0 && !$allRPIgnored) //Falguni Rana - 1.0.50.55 - 11th Aug 2016, Purpose - Corrected error messages, Added && !$allRPIgnored
					{
					//$this->log->logIt(json_encode($this->datelist,TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
					$rplanlist ='';
					//$error_msg = ($error_msg!='')?($error_msg.'Rate Plans : ') : ('Rate Plans : '); //Falguni Rana - 1.0.50.55 - 11th Aug 2016, Purpose - Corrected error messages, Commented this line
					
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
							$rplanlist = ($rplanlist!='')?($rplanlist.", ".$datakey): ("".$datakey);
							
							$rplanlist =  $rplanlist." from ".$datavalue['fromdate'];
							
							$rplanlist =  $rplanlist." to ".$datavalue['todate'];
						}
						}
					}
					//Falguni Rana - 1.0.50.55 - 11th Aug 2016, Purpose - Corrected error messages, Commented below line and added very next line
					//$error_msg = $error_msg.$rplanlist." ".$lbloperation." not updated due to dates passed.";
					$error_msg = $error_msg.($rplanlist != '' ? ("Rate Plans : ".$rplanlist." ".$lbloperation." not updated due to dates passed.") : "");
					}
					
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
					{
						if($allRPIgnored)
						{
							return $this->generateGeneralErrorJson('1', $error_msg,array("Success"=>array("SuccessMsg"=>$lbloperation.' not Updated.')));
						}
						else
						{
							return $this->generateGeneralErrorJson('1', $error_msg,array("Success"=>array("SuccessMsg"=>$lbloperation.' Partially Updated.')));
						}
					}
					else
					{
						$success = $this->xmlDoc->createElement("Success");
						$succ_msg = $this->xmlDoc->createElement("SuccessMsg");
						
						//Falguni Rana - 1.0.50.55 - 11th Aug 2016 - START
						//Purpose - Corrected error messages
						if($allRPIgnored)
							$succ_msg->appendChild($this->xmlDoc->createTextNode($lbloperation.' not Updated.'));
						else
							$succ_msg->appendChild($this->xmlDoc->createTextNode($lbloperation.' Partially Updated.'));
						//Falguni Rana - 1.0.50.55 - 11th Aug 2016 - END
						
						$success->appendChild($succ_msg);
						$this->xmlRoot->appendChild($success);
						
						//Falguni Rana - 1.0.50.55 - 11th Aug 2016, Purpose - Corrected error messages, Commented below line and added very next line
						//$this->generateGeneralErrorMsg('0','Success');
						$this->generateGeneralErrorMsg('1',$error_msg);
						
						$str = $this->xmlDoc->saveXML();
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
						{
							 return $str;
						}
						echo $str;	
					}
				}
				} 			
				else
				{
					if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
					{
						return $this->generateGeneralErrorJson('500', "Error occured during processing.");
					}
					else
					{
						$this->generateGeneralErrorMsg('500', "Error occured during processing.");
						if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag [CEN-1165]
						{
							 return $this->xmlDoc->saveXML();
						}
						echo $this->xmlDoc->saveXML();
						return;	
					}
				}
			}
	    }
	    else
	    {
			if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
			{
				return $this->generateGeneralErrorJson('304', "Update operation is not allowed.");
			}
			else
			{
				$this->generateGeneralErrorMsg('304', "Update operation is not allowed.");
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
				{
					 return $this->xmlDoc->saveXML();
				}
				echo $this->xmlDoc->saveXML();
				return;	
			}
	    }
        }
	catch (Exception $e)
	{
            $this->log->logIt("Exception in " . $this->module . " - executeRequest - " . $e);
            $this->handleException($e);
        }
    }
    
    private function processRequest($ignorelist)
    {
        try
	{
            $flag = true;			
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "processRequest");
	    
	    $contactunkid='';//$this->getContactsForUpdate(); //Falguni Rana - 16th Jun 2018, Purpose - Common Pool
	    
            $keyVal = $this->prepareKeyVal();
	    //$this->log->logIt($keyVal);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            for ($i = 0; $i < count($keyVal); $i++)
	    {
                foreach ($keyVal[$i] as $key => $val)
		{
                    $val = substr($val, 0, strlen($val) - 1);
							
		    if(!in_array($this->rateplanid[$i]."",array_values($ignorelist),TRUE) && $val!='')
		    {								
			    if (!$this->operations_update($this->rateplanid[$i], explode("-", $key), $val,$contactunkid))
			    {
				    $flag = false;
				    break;
			    }				
                    }				
                }
            }			
        }
	catch (Exception $e)
	{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "processRequest - " . $e);
            $this->handleException($e);
        }
        return $flag;
    }
    
    private function getContactsForUpdate()
    {
	try
	{
	    $this->log->logIt(get_class($this)."-"."getContactsForUpdate");
	    $dao = new dao();
	    
	    switch($this->operation)
	    {	
		case "coa":
		    $chksetting = "PMSCloseOnArrivalUpdateOnContact";
		    break;
		case "cod":
		    $chksetting = "PMSCloseOnDepartUpdateOnContact";
		    break;
		case "minnights":
		    $chksetting = "PMSMinNightsUpdateOnContact";
		    break;
        // Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel - start
        case "maxnights":
		    $chksetting = "PMSMaxNightsUpdateOnContact";
		    break;
        // Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel - end
		case "stopsell":
		    $chksetting = "PMSStopSellUpdateOnContact";
		    break;	
	    }
	    
	    $list_contact = "SELECT contactunkid FROM ".dbtable::Contact." WHERE hotel_code=:hotel_code AND FIND_IN_SET(contacttypeunkid,'".$this->readConfigParameter($chksetting)."')";
	    //$this->log->logIt($list_contact);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
	    $dao->initCommand($list_contact);
	    $dao->addParameter(":hotel_code", $this->hotelcode);
	    $contacts_list = $dao->executeQuery();
	    $contacts='';
	    foreach($contacts_list as $contactlist)
		    $contacts=($contacts==''?$contactlist['contactunkid']:$contacts.",".$contactlist['contactunkid']);
	    return $contacts;    
	}
	catch(Exception $e)
	{
		$this->log->logIt(get_class($this)."-"."getContactsForUpdate"."-".$e);
		return -1;
	}	
    }
    
    private function operations_update($roomrateunkid,$month_year,$keyValue,$contactunkid)
    {
	    try
	    {			
		$this->log->logIt(get_class($this)."-"."operations_update"."|".$roomrateunkid."|".$month_year[1]."|".$month_year[0]);
		
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
		
		$ipaddress=",remoteaddress='".$this->VisitorIP()."'";
		$atlogtype="";
		switch($this->operation)
		{	
		    case "coa":
			$tablename = "cfcloseonarrival";
			$atlogtype = "COA"; // Added Krunal Kaklotar - 2017-11-24 - auditlog utility
			break;
		    case "cod":
			$tablename = "cfcloseondepart";
			$atlogtype = "COD"; // Added Krunal Kaklotar - 2017-11-24 - auditlog utility
			break;
		    case "minnights":
			$tablename = "cfsetminnights";
			$atlogtype = "MIN"; // Added Krunal Kaklotar - 2017-11-24 - auditlog utility
			break;
           // Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel - start
            case "maxnights":
			$tablename = "cfsetmaxnights";			
			break;
          // Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel - end
		    case "stopsell":
			$tablename = "cfstopsell";
			$atlogtype = "STOP"; // Added Krunal Kaklotar - 2017-11-24 - auditlog utility
			break;	
		}
		
		//Sanjay Waman - 15 May 2019 - Store Stopsell Query in Table - Start
		$strSql = " UPDATE ".$tablename."";
		$strSql .= " SET ".$keyValue.$ipaddress;
		$strSql .= " WHERE roomrateunkid=:roomrateunkid AND hotel_code=:hotel_code AND month=:month AND year=:year";
		//Dharti Savaliya - 12-10-2019 - PMS feeder queue for COA,COD,maxnights,minnights [CEN-844]
		if(trim($this->operation)=="stopsell" || trim($this->operation)=="coa" || trim($this->operation)=="cod" || trim($this->operation)=="minnights" || trim($this->operation)=="maxnights")
		{
			if($this->contactunkids_forupdate!='') $strSql.=" AND contactunkid in (".$this->contactunkids_forupdate.") ";
			//$this->log->logIt($strSql."|".$roomrateunkid."|".$this->contactunkids_forupdate);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]	
			
			$strSql = str_replace(":roomrateunkid","'".$roomrateunkid."'",$strSql);
			$strSql = str_replace(":hotel_code","'".$this->hotelcode."'",$strSql);
			$strSql = str_replace(":month","'".$month_year[1]."'",$strSql);
			$strSql = str_replace(":year","'".$month_year[0]."'",$strSql);
			//$this->log->logIt("operations_update - Stored StopSellQuery  ============>".$strSql);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			$atrdsdao = new atrdsdao();
			global $connection;
			$connection_str = explode("dbname=",$connection->getConnectionString());
			$db_name= "";
			if(count($connection_str) > 0){
				$db_name =  $connection_str[1];	
			}
			$atrdsdao->StorePmsAri($strSql,$db_name,$tablename,$this->hotelcode,$userrow['userunkid']);
			$this->recs_updates=$this->recs_updates+1;
		}//Sanjay Waman - END
        //Dharti Savaliya - 11-10-2019 - END
		else
		{
		$dao=new dao();			
		//Sushma - 1.0.52.60 - 25 July 2017 - START
		//Purpose : Addons requested by 3rd parties using our API - RoomerPMS
		//Falguni Rana - 16th Jun 2018, Purpose - Common Pool
		/*
		if((count($this->contacts)>0)  && ($this->operation == 'stopsell'))
		{
			$strSql.=" and FIND_IN_SET(contactunkid,:contacts) ";
		}
		else
		{
			if($contactunkid!='')	
				$strSql.=" and contactunkid in (".$contactunkid.")"; 
		}
		*/
		//Sushma - 1.0.52.60 - 25 July 2017 - END
		if($this->contactunkids_forupdate!='') $strSql.=" AND contactunkid in (".$this->contactunkids_forupdate.") ";
		//$this->log->logIt($strSql."|".$roomrateunkid."|".$this->contactunkids_forupdate);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
		
		$dao->initCommand($strSql);		
		$dao->addParameter(":roomrateunkid",$roomrateunkid);			
		$dao->addParameter(":hotel_code",$this->hotelcode);
		$dao->addParameter(":month", $month_year[1]);
		$dao->addParameter(":year", $month_year[0]);
		//Sushma - 1.0.52.60 - 25 July 2017 - START
		//Purpose : Addons requested by 3rd parties using our API - RoomerPMS
		$this->log->logIt("Operation  ============>".$this->operation);		
		//Falguni Rana - 16th Jun 2018, Purpose - Common Pool
		/*
		if((count($this->contacts)>0)  && ($this->operation == 'stopsell')){
				$this->log->logIt("Count ============>".count($this->contacts));
		       
				$dao->addParameter(":contacts", implode(',',$this->contacts));	
			}
		*/
		//Sushma - 1.0.52.60 - 25 July 2017 - END
		$rowcount=$dao->executeNonQuery();
		$this->recs_updates=$this->recs_updates+$rowcount;
		}
		
		$flag = true;
		
		#Add AuditLog			
		//auditlog::StoreAuditLog($tablename,'U',$operationunkid);
		
		// Start - Krunal Kaklotar - 1.0.53.61 - 2017-11-22 : Separate Auditlog utility
		if ($atlogtype != "")
		{
			$objatdao = new atdao();
			$_SESSION['prefix'] = "SaaS_".$this->hotelcode."";
			$_SESSION[$_SESSION['prefix']]['hotel_code']=$this->hotelcode."";;
			$_SESSION[$_SESSION['prefix']]['loginuserunkid'] = $userrow['userunkid']."";
			$_SESSION[$_SESSION['prefix']]['loginusername'] = "pms";
			
			//Falguni Rana - 16th Jun 2018, Purpose - Common Pool
			/*
			if((count($this->contacts)>0)  && ($this->operation == 'stopsell'))
			{
				$objatdao->call_deamonauditlog_api("pmsreservationprocess",$atlogtype,$tablename,"roomrateunkid",$roomrateunkid."",implode(',',$this->contacts),$month_year[1],$month_year[0],$keyValue,$this->VisitorIP());
			}
			else
			{
				$objatdao->call_deamonauditlog_api("pmsreservationprocess",$atlogtype,$tablename,"roomrateunkid",$roomrateunkid."",$contactunkid,$month_year[1],$month_year[0],$keyValue,$this->VisitorIP());
			}
			*/
			//$objatdao->call_deamonauditlog_api("pmsreservationprocess",$atlogtype,$tablename,"roomrateunkid",$roomrateunkid."",$this->contactunkids_forupdate,$month_year[1],$month_year[0],$keyValue,$this->VisitorIP());
			//sushma rana - log not inserted properly at auditlog end.
			//if(count($this->contactunkids_forupdate)>0)
            if(!empty($this->contactunkids_forupdate)) //Dharti savaliya 2020-05-27 purpose: php7.2 changes
			{
				$this->log->logIt($this->contactunkids_forupdate);
				$contactdetail = explode(",",$this->contactunkids_forupdate);				
				foreach($contactdetail as $key => $contactunkid)
				{
					//$objatdao->call_deamonauditlog_api("pmsreservationprocess","ROOMRATEALLOCATION","cfroomrateallocation","ratetypeunkid",$rateteypeid."",$contactunkid,$month_year[1],$month_year[0],$updatewith,util::VisitorIP(),"roomtypeunkid",$roomtypeid."");
					$objatdao->call_deamonauditlog_api("pmsreservationprocess",$atlogtype,$tablename,"roomrateunkid",$roomrateunkid."",$contactunkid,$month_year[1],$month_year[0],$keyValue,$this->VisitorIP());
				}
			}

		}
		// End - Krunal Kaklotar
		
	    }
	    catch(Exception $e)
	    {
		    $this->log->logIt(get_class($this)."-"."operations_update"."-".$e);
		    $this->handleException($e);
	    }
	    return $flag;
    }
    
    /*private function updateInventory($roomid, $month_year, $updatewith) {
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
			
            $strSql = " UPDATE cfroomallocation AS cfra";
            $strSql .= " SET " . $updatewith;
            $strSql .= " WHERE cfra.roomtypeunkid=:roomtypeunkid AND cfra.hotel_code=:hotel_code AND cfra.month=:month AND cfra.year=:year";
            $dao = new dao();
            $dao->initCommand($strSql);
            $dao->addParameter(":roomtypeunkid", $roomid);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            $dao->addParameter(":month", $month_year[1]);
            $dao->addParameter(":year", $month_year[0]);
			
            //$this->log->logIt("Sql : ".$strSql);
			
            $rowcount=$dao->executeNonQuery();
            $this->recs_updates=$this->recs_updates+$rowcount;

            $flag = true;
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "updateInventory - " . $e);
            $this->handleException($e);
        }
        return $flag;
    }*/
    
    private function prepareKeyVal()
    {
        try
	{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "prepareKeyVal");
            $daylist = $this->generateDaysList();  // generate array  list 
			          
            $keyval = array();
            $month_year = '';
			
            for ($i = 0; $i < count($daylist); $i++)
	    {
                $keyval[$i] = array();
				
                foreach ($daylist[$i] as $day)
		{	
		    if($day!='')
		    {					
			    if (date('Y-n', strtotime($day)) != $month_year) {
				    $month_year = date('Y-n', strtotime($day));
				    $keyval[$i][$month_year] = '';
			    }
			    if (!isset($keyval[$i][$month_year]))
				    $keyval[$i][$month_year] = '';
			    $keyval[$i][$month_year].="day_" . date('j', strtotime($day)) . "=" . $this->operationval[$i] . ",";
		    }
		    else
			    $keyval[$i][].="";
                }
            }
			
            return $keyval;
        }
	catch (Exception $e)
	{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "prepareKeyVal - " . $e);
            $this->handleException($e);
        }
    }
    
    private function generateGeneralErrorMsg($code='', $msg='')
    {
        try
	{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateGeneralErrorMsg");
            $genErrors = $this->xmlDoc->createElement("Errors");
            $errorCode = $this->xmlDoc->createElement("ErrorCode");
            $errorCode->appendChild($this->xmlDoc->createTextNode($code));
            $genErrors->appendChild($errorCode);
            $errorMsg = $this->xmlDoc->createElement("ErrorMessage");
            $errorMsg->appendChild($this->xmlDoc->createTextNode($msg));
            $genErrors->appendChild($errorMsg);
            $this->xmlRoot->appendChild($genErrors);
        }
	catch (Exception $e)
	{
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
    
    private function generateDaysList()
    {
        try
	{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateDaysList");
            for ($j = 0; $j < count($this->rateplan); $j++)
	    {				
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
				
			array_push($this->datelist,array($this->rateplanid[$j]."" => array("fromdate" => $this->fromdate[$j]."","todate" => $prev_day))); 
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
        }
	catch (Exception $e)
	{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateDaysList" . "-" . $e);
            $this->handleException($e);
        }
    }
        
    private function isUpdateAllowed()
    {
	try
	{
	    $flag=false;
	    
	    switch($this->operation)
	    {	
		case "coa":
		    $chksetting = "PMSCloseOnArrivalUpdate";
		    break;
		case "cod":
		    $chksetting = "PMSCloseOnDepartUpdate";
		    break;
		case "minnights":
		    $chksetting = "PMSMinNightsUpdate";
		    break;
        // Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel - start
        case "maxnights":
		    $chksetting = "PMSMaxNightsUpdate";
		    break;
        // Sushma Rana - 31th Jan 2018 - Set option for max night familyhotel - end
		case "stopsell":
		    $chksetting = "PMSStopSellUpdate";
		    break;	
	    }
	    
        $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "isUpdateAllowed");
	    if($this->readConfigParameter($chksetting)==1)
		$flag=true;
	}
	catch (Exception $e)
	{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isUpdateAllowed - " . $e);
            $this->handleException($e);
        }
	return $flag;
    }
    
    private function readConfigParameter($key)
    {
        try
	{
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
        }
	catch (Exception $e)
	{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "readConfigParameter - " . $e);
            $this->handleException($e);
        }
        return $result['keyvalue'];
    }
        
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
    
    private function handleException($e)
    {
        try
	{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "handleException");
            if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='json')//Sanjay Waman - 09 Sep 2019 - Return flag for json format [CEN-1086]
			{
				return $this->generateGeneralErrorJson('500', "Error occured during processing.",array(),1);
			}
			else
			{
				$this->generateGeneralErrorMsg('500', "Error occured during processing");
				if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
				{
					 return $this->xmlDoc->saveXML();
				}
				echo $this->xmlDoc->saveXML();
				exit;	
			}
        } catch (Exception $e)
	{
            $this->log->logIt(get_class($this) . "-" . "handleException" . "-" . $e);
            exit;
        }
    }
}
?>
