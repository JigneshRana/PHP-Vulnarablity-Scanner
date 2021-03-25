<?php
// sushma - 24th july 2017- Support saparate source support for PMS
class pms_saparatesource {
    private $log;
    private $module = "pms_saparatesource";
    private $hotelcode;    
    private $xmlDoc;
    private $xmlRoot;
    private $xmlRoomInfo;
	public $returnflag;//Sanjay Waman - 01 Jul 2019 - Return flag[CEN-1165]
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
    public function executeRequest() {
        $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "executeRequest");
        try {
			$this->xmlDoc = new DOMDocument('1.0','UTF-8');
            $this->xmlRoot = $this->xmlDoc->createElement("RES_Response");
            $this->xmlDoc->appendChild($this->xmlRoot);
          
            //preparing xml
            $this->prepareResponse();
            $str = $this->xmlDoc->saveXML();
			if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
			{
				return $str;
			}
            echo $str;
	    exit;
            return;
        } catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - executeRequest - " . $e);
            $this->handleException($e);
        }
    }
    
    private function prepareResponse() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "prepareResponse");
            $roomtypes = $this->getRoomTypeInfo();
            $ratetypes = $this->getRateTypeInfo();            
			$rateplans = $this->getRatePlanInfo();
			$saparatesourceinfos = $this->getsaparatesourceInfo();
			//$this->log->logIt("saparate source array".print_r($saparatesourceinfos, TRUE));//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
			
            $this->generateRoomInfoXML($roomtypes, $ratetypes,$rateplans,$saparatesourceinfos);
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "prepareResponse - " . $e);
        }
    }
    private function getRoomTypeInfo() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getRoomTypeInfo");
            $result = NULL;
            $sqlStr = " SELECT cfrt.roomtypeunkid,cfrt.roomtype FROM cfroomtype AS cfrt WHERE cfrt.hotel_code=:hotel_code AND cfrt.publishtoweb AND cfrt.isactive";
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
    private function getRateTypeInfo() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getRateTypeInfo");
            $result = NULL;
            $sqlStr = " SELECT cfrt.ratetypeunkid,cfrt.ratetype FROM cfratetype AS cfrt WHERE cfrt.hotel_code=:hotel_code AND cfrt.isactive";
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
	private function getRatePlanInfo() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getRatePlanInfo");
            $result = NULL;
            $sqlStr = "SELECT cfrs.roomrateunkid,cfrs.display_name AS rateplan,cfrs.roomtypeunkid,cfrm.roomtype,cfrs.ratetypeunkid,cfrt.ratetype FROM cfroomrate_setting AS cfrs";
			$sqlStr.=" INNER JOIN cfroomtype AS cfrm ON cfrm.roomtypeunkid=cfrs.roomtypeunkid";
			$sqlStr.=" INNER JOIN cfratetype AS cfrt ON cfrt.ratetypeunkid=cfrs.ratetypeunkid";
			$sqlStr.=" WHERE cfrs.hotel_code=:hotel_code AND cfrs.isactive";
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
	private function getsaparatesourceInfo() {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getRatePlanInfo");
            $result = NULL;
            $sqlStr = "SELECT CASE  WHEN  business_name!='' AND business_name='Channel' 
						AND contacttypeunkid='CHANNEL' THEN 'OTA Common Pool'  WHEN 
						business_name!='' AND contacttypeunkid='CHANNEL' THEN business_name WHEN  name!='' 
						AND contacttypeunkid='CHANNEL' THEN name WHEN  business_name!='' AND contacttypeunkid='FRONT' 
						THEN 'PMS'  WHEN  name!='' AND contacttypeunkid='FRONT' THEN name  WHEN 
						business_name!='' AND contacttypeunkid='WEB' THEN   
						(SELECT CASE WHEN website!='' THEN CONCAT(website,' - ',contacttypeunkid) 
						ELSE CONCAT(business_name,' - ','Web') END  
						FROM cfhotel WHERE hotel_code=:hotel_code)  WHEN  name!='' AND contacttypeunkid='WEB' 
						THEN   (SELECT CASE WHEN website!='' THEN CONCAT(website,' - ',contacttypeunkid) 
						ELSE CONCAT(name,' - ','Web') END  FROM cfhotel WHERE hotel_code=:hotel_code)  
						WHEN  business_name!='' THEN business_name    ELSE CONCAT(NAME,' - ','Web')  
						END AS name  ,contactunkid,ishide,contacttypeunkid,ismaster,roominventory,rate AS ratemode,
						contact.ch_operation,contact.ishide FROM trcontact as contact  
						WHERE 1 AND contact.hotel_code=:hotel_code  AND contacttypeunkid IN('CHANNEL')  
						AND (roominventory!='' OR roominventory!=NULL OR rate!='' OR rate!=NULL) 
						ORDER BY contact.contacttypeunkid,name ASC";
						
				// query for all different sources 		
				/* $sqlStr = "SELECT CASE  WHEN  business_name!='' AND business_name='Channel' 
						AND contacttypeunkid='CHANNEL' THEN 'OTA Common Pool'  WHEN 
						business_name!='' AND contacttypeunkid='CHANNEL' THEN business_name WHEN  name!='' 
						AND contacttypeunkid='CHANNEL' THEN name WHEN  business_name!='' AND contacttypeunkid='FRONT' 
						THEN 'PMS'  WHEN  name!='' AND contacttypeunkid='FRONT' THEN name  WHEN 
						business_name!='' AND contacttypeunkid='WEB' THEN   
						(SELECT CASE WHEN website!='' THEN CONCAT(website,' - ',contacttypeunkid) 
						ELSE CONCAT(business_name,' - ','Web') END  
						FROM cfhotel WHERE hotel_code=:hotel_code)  WHEN  name!='' AND contacttypeunkid='WEB' 
						THEN   (SELECT CASE WHEN website!='' THEN CONCAT(website,' - ',contacttypeunkid) 
						ELSE CONCAT(name,' - ','Web') END  FROM cfhotel WHERE hotel_code=:hotel_code)  
						WHEN  business_name!='' THEN business_name    ELSE CONCAT(NAME,' - ','Web')  
						END AS name  ,contactunkid,ishide,contacttypeunkid,ismaster,roominventory,rate AS ratemode,
						contact.ch_operation,contact.ishide FROM trcontact as contact  
						WHERE 1 AND contact.hotel_code=:hotel_code  AND contacttypeunkid NOT IN('GUEST')  
						AND (roominventory!='' OR roominventory!=NULL OR rate!='' OR rate!=NULL) 
						ORDER BY contact.contacttypeunkid,name ASC";
						*/
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $this->hotelcode);
            //$this->log->logIt(get_class($this) . "-" . "getsaparatesourceInfo" . $sqlStr);//Jay Raval - 16-03-2021 - Purpose:Comment unwanted logs[CEN-1971]
            $result = $dao->executeQuery();
        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "getRatePlanInfo - " . $e);
            $this->handleException($e);
        }
        return $result;
    }
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
    private function generateRoomInfoXML($roomtypes, $ratetypes,$rateplans,$saparatesourceinfos) {
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
			
			$Saparatechannelsources = $this->xmlDoc->createElement("Saparatechannelsources");
			foreach ($saparatesourceinfos as $sp) {
				
                $Saparatechannelsource = $this->xmlDoc->createElement("Saparatechannelsource");
                
				$sname = $this->xmlDoc->createElement("Channel_name");
                $sname->appendChild($this->xmlDoc->createTextNode($sp['name']));
                $Saparatechannelsource->appendChild($sname);
				
			 	$sid = $this->xmlDoc->createElement("ChannelID");
                $sid->appendChild($this->xmlDoc->createTextNode($this->xmlEscape($sp['contactunkid'])));
                $Saparatechannelsource->appendChild($sid);
				
                $Saparatechannelsources->appendChild($Saparatechannelsource);
            }
			$this->xmlRoomInfo->appendChild($Saparatechannelsources);
			
			$this->generateGeneralErrorMsg('0','Success');

        } catch (Exception $e) {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "generateRoomInfoXML" . "-" . $e);
            $this->handleException($e);
        }
    }
    private function handleException($e) {
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "handleException");
            $this->generateGeneralErrorMsg('500', "Error occured during processing");
			if(isset($this->returnflag) && strtolower(trim($this->returnflag))=='xml')//Sanjay Waman - 02 Jul 2019 - Return flag[CEN-1165]
			{
				return $this->xmlDoc->saveXML();
			}
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
