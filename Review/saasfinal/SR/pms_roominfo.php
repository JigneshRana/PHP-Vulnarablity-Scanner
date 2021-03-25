<?php
class pms_roominfo {
    private $log;
    private $module = "pms_roominfo";
    private $hotelcode;    
    private $xmlDoc;
    private $xmlRoot;
    private $xmlRoomInfo;
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
    #Object Fields - Getter/Setter - End
    public function executeRequest($display=0) {
        $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "executeRequest");
        try {
			$this->xmlDoc = new DOMDocument('1.0','UTF-8');
            $this->xmlRoot = $this->xmlDoc->createElement("RES_Response");
            $this->xmlDoc->appendChild($this->xmlRoot);
          
            //preparing xml
            $this->prepareResponse();
            $str = $this->xmlDoc->saveXML();
			//Sanjay Waman - 12 Jul 2019 - Change for obj calling [CEN-1165] - Start
			if(isset($display) && $display==1)
			{
				return $str;
			}
			//Sanjay Waman - End
            echo $str;
	    exit;
            return;
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - executeRequest - " . $e);
            $this->handleException($e);
        }
    }
	
	//Sanjay - 13 Jun 2019 - For Json Format - Start
	public function executeRequestJson($returnflag=0) {
        $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "executeRequestJson");
        try {          
            $str = $this->prepareResponseJson();
			header('Content-Type: application/json');
			if($returnflag==1)
			{
				return json_encode($str);
			}
			else
			{
				echo json_encode($str);
			}
			exit;
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - executeRequest - " . $e);
            $this->handleException($e);
        }
    }
	private function prepareResponseJson($rateplanrq='') {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "prepareResponseJson");
            $roomtypes = $this->getRoomTypeInfo(1);
            $ratetypes = $this->getRateTypeInfo(1);
			if($rateplanrq=='RatePlanType')
				$rateplans = $this->getRatePlanInfoWithRPType();
			else
				$rateplans = $this->getRatePlanInfo(1);
			
			if(!empty($rateplans)) //Dharti Savaliya 2020-02-10 Purpose : 6 to PHP7.2 changes CEN-1017 
			{
				return array("RoomInfo"=>array("RoomTypes"=>array("RoomType"=>$roomtypes),"RateTypes"=>array("RateType"=>$ratetypes),"RatePlans"=>array("RatePlan"=>$rateplans)));
			}
           
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "prepareResponseJson - " . $e);
        }
    }
	//Sanjay - End
	
	//Sanjay Waman - 19 Dec 2018 - Share Rate Plan Type (Independent,Master,Derived) - Start
	public function executeRequestMoreDetails($returnflag=0) {
        $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "executeRequestMoreDetails");
        try {
			$this->xmlDoc = new DOMDocument('1.0','UTF-8');
            $this->xmlRoot = $this->xmlDoc->createElement("RES_Response");
            $this->xmlDoc->appendChild($this->xmlRoot);
          
            //preparing xml
            $this->prepareResponse("RatePlanType");
            $str = $this->xmlDoc->saveXML();
			if($returnflag==1)
			{
				return $str;
			}
			else
			{
				echo $str;	
			}
            
	    exit;
            return;
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - executeRequestMoreDetails - " . $e);
            $this->handleException($e);
        }
    }
	//Sanjay Waman - End
    
    private function prepareResponse($rateplanrq='') {//Sanjay Waman - 19 Dec 2018 - (Paas arg in function)Changes regarding Rateplan Type for innsoftmigration
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "prepareResponse");
            $roomtypes = $this->getRoomTypeInfo();
            $ratetypes = $this->getRateTypeInfo();
			//Sanjay Waman - 19 Dec 2018 - Changes regarding Rateplan Type for innsoftmigration - START
			if($rateplanrq=='RatePlanType')
				$rateplans = $this->getRatePlanInfoWithRPType();
			else
				$rateplans = $this->getRatePlanInfo();
			//Sanjay Waman - END
			//Sanjay Waman - 20 Dec 2018 - Special Char Pass in xml (Request from innsoft) - START
			if($rateplanrq=='RatePlanType')
				$this->generateRoomInfoXMLSpecialChar($roomtypes, $ratetypes,$rateplans);
			else
				$this->generateRoomInfoXML($roomtypes, $ratetypes,$rateplans);
			//Sanjay Waman - END
           
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "prepareResponse - " . $e);
        }
    }
    private function getRoomTypeInfo($jsonflag=0) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getRoomTypeInfo");
            $result = NULL;
            $sqlStr = " SELECT cfrt.roomtypeunkid,cfrt.roomtype FROM cfroomtype AS cfrt WHERE cfrt.hotel_code=:hotel_code AND cfrt.publishtoweb AND cfrt.isactive";
			$sqlStr.=" AND cfrt.isdefaultunmap=0 "; // Tejaswini - 03 May 2018 - Hide Default Unmapped roomtype
			//Sanjay Waman - 13 Jun 2019 - Json key name changed - Start
			if($jsonflag==1)
			{
				$sqlStr = " SELECT cfrt.roomtypeunkid As ID, cfrt.roomtype As Name FROM cfroomtype AS cfrt WHERE cfrt.hotel_code=:hotel_code AND cfrt.publishtoweb AND cfrt.isactive";
				$sqlStr.=" AND cfrt.isdefaultunmap=0 ";
			}
			//Sanjay Waman - End
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            #$this->log->logIt(get_class($this)."-"."getRoomTypeInfo".$sqlStr);
            $result = $dao->executeQuery();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getRoomTypeInfo - " . $e);
            $this->handleException($e);
        }
        return $result;
    }
    private function getRateTypeInfo($jsonflag=0) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getRateTypeInfo");
            $result = NULL;
            $sqlStr = " SELECT cfrt.ratetypeunkid,cfrt.ratetype FROM cfratetype AS cfrt WHERE cfrt.hotel_code=:hotel_code AND cfrt.isactive";
			$sqlStr.=" AND cfrt.isdefaultunmap=0 "; // Tejaswini - 03 May 2018 - Hide Default Unmapped ratetype
            //Sanjay Waman - 13 Jun 2019 - Json key name changed - Start
			if($jsonflag==1)
			{
				 $sqlStr = " SELECT cfrt.ratetypeunkid As ID, cfrt.ratetype As Name FROM cfratetype AS cfrt WHERE cfrt.hotel_code=:hotel_code AND cfrt.isactive";
				$sqlStr.=" AND cfrt.isdefaultunmap=0 ";
			}
			//Sanjay Waman - End
			$dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            #$this->log->logIt(get_class($this) . "-" . "getRateTypeInfo" . $sqlStr);
            $result = $dao->executeQuery();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getRateTypeInfo - " . $e);
            $this->handleException($e);
        }
        return $result;
    }
	private function getRatePlanInfo($jsonflag=0) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getRatePlanInfo");
            $result = NULL;
            $sqlStr = "SELECT cfrs.roomrateunkid,cfrs.display_name AS rateplan,cfrs.roomtypeunkid,cfrm.roomtype,cfrs.ratetypeunkid,cfrt.ratetype FROM cfroomrate_setting AS cfrs";
			$sqlStr.=" INNER JOIN cfroomtype AS cfrm ON cfrm.roomtypeunkid=cfrs.roomtypeunkid";
			$sqlStr.=" INNER JOIN cfratetype AS cfrt ON cfrt.ratetypeunkid=cfrs.ratetypeunkid";
			$sqlStr.=" WHERE cfrs.hotel_code=:hotel_code AND cfrs.isactive";
			//Sanjay Waman - 13 Jun 2019 - Json key name changed - Start
			if($jsonflag==1)
			{
				$sqlStr = "SELECT cfrs.roomrateunkid As RatePlanID,cfrs.display_name AS Name,cfrs.roomtypeunkid As RoomTypeID,cfrm.roomtype As RoomType,cfrs.ratetypeunkid As RateTypeID,cfrt.ratetype As RateType FROM cfroomrate_setting AS cfrs";
				$sqlStr.=" INNER JOIN cfroomtype AS cfrm ON cfrm.roomtypeunkid=cfrs.roomtypeunkid";
				$sqlStr.=" INNER JOIN cfratetype AS cfrt ON cfrt.ratetypeunkid=cfrs.ratetypeunkid";
				$sqlStr.=" WHERE cfrs.hotel_code=:hotel_code AND cfrs.isactive";
			}
			//Sanjay Waman - End
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            #$this->log->logIt(get_class($this) . "-" . "getRatePlanInfo" . $sqlStr);
            $result = $dao->executeQuery();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getRatePlanInfo - " . $e);
            $this->handleException($e);
        }
        return $result;
    }
	//Sanjay Waman - 19 Dec 2018 - Changes regarding Rateplan Type for innsoftmigration - START
	private function getRatePlanInfoWithRPType() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getRatePlanInfoWithRPType");
            $result = NULL;
            $sqlStr = "SELECT cfrs.roomrateunkid,cfrs.display_name AS rateplan,cfrs.roomtypeunkid,cfrm.roomtype,cfrs.ratetypeunkid,cfrt.ratetype,cfrs.derivedfrom,cfrs.ismaster FROM cfroomrate_setting AS cfrs";
			$sqlStr.=" INNER JOIN cfroomtype AS cfrm ON cfrm.roomtypeunkid=cfrs.roomtypeunkid";
			$sqlStr.=" INNER JOIN cfratetype AS cfrt ON cfrt.ratetypeunkid=cfrs.ratetypeunkid";
			$sqlStr.=" WHERE cfrs.hotel_code=:hotel_code AND cfrs.isactive";
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            #$this->log->logIt(get_class($this) . "-" . "getRatePlanInfo" . $sqlStr);
            $result = $dao->executeQuery();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getRatePlanInfoWithRPType - " . $e);
            $this->handleException($e);
        }
        return $result;
    }
	//Sanjay Waman - END
    private function generateGeneralErrorMsg($code='', $msg) {
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
    private function generateRoomInfoXML($roomtypes, $ratetypes,$rateplans) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateRoomInfoXML");
            $this->xmlRoomInfo = $this->xmlDoc->createElement("RoomInfo");
            $this->xmlRoot->appendChild($this->xmlRoomInfo);
			$RoomTypes = $this->xmlDoc->createElement("RoomTypes");
            foreach ($roomtypes as $room) {
                $roomtype = $this->xmlDoc->createElement("RoomType");
                
				$rid = $this->xmlDoc->createElement("ID");
                $rid->appendChild($this->xmlDoc->createTextNode($room['roomtypeunkid']));
                $roomtype->appendChild($rid);
               
			    $rtname = $this->xmlDoc->createElement("Name");
                $rtname->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($room['roomtype'])));
                $roomtype->appendChild($rtname);
                $RoomTypes->appendChild($roomtype);
            }
			$this->xmlRoomInfo->appendChild($RoomTypes);
			$RateTypes = $this->xmlDoc->createElement("RateTypes");
            foreach ($ratetypes as $rate) {
                $ratetype = $this->xmlDoc->createElement("RateType");
                
				$rid = $this->xmlDoc->createElement("ID");
                $rid->appendChild($this->xmlDoc->createTextNode($rate['ratetypeunkid']));
                $ratetype->appendChild($rid);
               
			    $rtname = $this->xmlDoc->createElement("Name");
                $rtname->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($rate['ratetype'])));
                $ratetype->appendChild($rtname);
                $RateTypes->appendChild($ratetype);
            }
			$this->xmlRoomInfo->appendChild($RateTypes);
			$RatePlans = $this->xmlDoc->createElement("RatePlans");
			//$this->log->logIt("RatePlan >>".json_encode($rateplans,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//die;
			foreach ($rateplans as $rp) {
                $RatePlan = $this->xmlDoc->createElement("RatePlan");
                
				$rid = $this->xmlDoc->createElement("RatePlanID");
                $rid->appendChild($this->xmlDoc->createTextNode($rp['roomrateunkid']));
                $RatePlan->appendChild($rid);
				
			 	$rpname = $this->xmlDoc->createElement("Name");
                $rpname->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($rp['rateplan'])));
                $RatePlan->appendChild($rpname);
				
				$roomtypeid = $this->xmlDoc->createElement("RoomTypeID");
                $roomtypeid->appendChild($this->xmlDoc->createTextNode($rp['roomtypeunkid']));
                $RatePlan->appendChild($roomtypeid);
				
				$roomtype = $this->xmlDoc->createElement("RoomType");
                $roomtype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($rp['roomtype'])));
                $RatePlan->appendChild($roomtype);
				
				$roomtypeid = $this->xmlDoc->createElement("RateTypeID");
                $roomtypeid->appendChild($this->xmlDoc->createTextNode($rp['ratetypeunkid']));
                $RatePlan->appendChild($roomtypeid);
				
				$ratetype = $this->xmlDoc->createElement("RateType");
                $ratetype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($rp['ratetype'])));
                $RatePlan->appendChild($ratetype);
			   
                $RatePlans->appendChild($RatePlan);
            }
			$this->xmlRoomInfo->appendChild($RatePlans);
			$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ

        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateRoomInfoXML" . "-" . $e);
            $this->handleException($e);
        }
    }
	//Sanjay Waman - 20 Dec 2018 - Special Char pass in XML - START
	private function generateRoomInfoXMLSpecialChar($roomtypes, $ratetypes,$rateplans) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "generateRoomInfoXMLSpecialChar");
            $this->xmlRoomInfo = $this->xmlDoc->createElement("RoomInfo");
            $this->xmlRoot->appendChild($this->xmlRoomInfo);
			$RoomTypes = $this->xmlDoc->createElement("RoomTypes");
            foreach ($roomtypes as $room) {
                $roomtype = $this->xmlDoc->createElement("RoomType");
                
				$rid = $this->xmlDoc->createElement("ID");
                $rid->appendChild($this->xmlDoc->createTextNode($room['roomtypeunkid']));
                $roomtype->appendChild($rid);
               
			    $rtname = $this->xmlDoc->createElement("Name");
                $rtname->appendChild($this->xmlDoc->createTextNode($room['roomtype']));
                $roomtype->appendChild($rtname);
                $RoomTypes->appendChild($roomtype);
            }
			$this->xmlRoomInfo->appendChild($RoomTypes);
			$RateTypes = $this->xmlDoc->createElement("RateTypes");
            foreach ($ratetypes as $rate) {
                $ratetype = $this->xmlDoc->createElement("RateType");
                
				$rid = $this->xmlDoc->createElement("ID");
                $rid->appendChild($this->xmlDoc->createTextNode($rate['ratetypeunkid']));
                $ratetype->appendChild($rid);
               
			    $rtname = $this->xmlDoc->createElement("Name");
                $rtname->appendChild($this->xmlDoc->createTextNode($rate['ratetype']));
                $ratetype->appendChild($rtname);
                $RateTypes->appendChild($ratetype);
            }
			$this->xmlRoomInfo->appendChild($RateTypes);
			$RatePlans = $this->xmlDoc->createElement("RatePlans");
			//$this->log->logIt("RatePlan >>".json_encode($rateplans,true));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			//die;
			foreach ($rateplans as $rp) {
                $RatePlan = $this->xmlDoc->createElement("RatePlan");
                
				$rid = $this->xmlDoc->createElement("RatePlanID");
                $rid->appendChild($this->xmlDoc->createTextNode($rp['roomrateunkid']));
                $RatePlan->appendChild($rid);
				
			 	$rpname = $this->xmlDoc->createElement("Name");
                $rpname->appendChild($this->xmlDoc->createTextNode($rp['rateplan']));
                $RatePlan->appendChild($rpname);
				
				$roomtypeid = $this->xmlDoc->createElement("RoomTypeID");
                $roomtypeid->appendChild($this->xmlDoc->createTextNode($rp['roomtypeunkid']));
                $RatePlan->appendChild($roomtypeid);
				
				$roomtype = $this->xmlDoc->createElement("RoomType");
                $roomtype->appendChild($this->xmlDoc->createTextNode($rp['roomtype']));
                $RatePlan->appendChild($roomtype);
				
				$roomtypeid = $this->xmlDoc->createElement("RateTypeID");
                $roomtypeid->appendChild($this->xmlDoc->createTextNode($rp['ratetypeunkid']));
                $RatePlan->appendChild($roomtypeid);
				
				$ratetype = $this->xmlDoc->createElement("RateType");
                $ratetype->appendChild($this->xmlDoc->createTextNode($rp['ratetype']));
                $RatePlan->appendChild($ratetype);
				
				//Sanjay Waman - 19 Dec 2018 - Changes regarding Rateplan Type for innsoftmigration - START
				if(isset($rp['derivedfrom']) || isset($rp['ismaster']))
				{
					$roomrateplantype = $this->xmlDoc->createElement("RatePlanType");
					if($rp['derivedfrom'] == NULL && $rp['ismaster'] == 0 )
						$roomrateplantype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape("INDEPENDENT")));
					elseif($rp['derivedfrom'] == NULL && $rp['ismaster'] == 1 )
						$roomrateplantype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape("MASTER")));
					elseif($rp['derivedfrom'] != NULL)
						$roomrateplantype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape("DERIVED")));
					else
						$roomrateplantype->appendChild($this->xmlDoc->createTextNode($this->xmlEscape("Not Define")));
					
					$RatePlan->appendChild($roomrateplantype);
				}
				//Sanjay Waman - END
			   
                $RatePlans->appendChild($RatePlan);
            }
			$this->xmlRoomInfo->appendChild($RatePlans);
			$this->generateGeneralErrorMsg('0','Success');//Satish - 06 Oct 2012 - Guided By JJ

        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateRoomInfoXMLSpecialChar" . "-" . $e);
            $this->handleException($e);
        }
    }
	//Sanjay Waman - END
    private function handleException($e) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "handleException");
            $this->generateGeneralErrorMsg('500', "Error occured during processing");
            echo $this->xmlDoc->saveXML();
            exit;
        } catch (Exception $e) {
            $this->log->logIt(get_class($this) . "-" . "handleException" . "-" . $e);
            exit;
        }
    }
    private function xmlEscape($string) {
        $string = str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $string);
        return $string;
    }
}
?>
