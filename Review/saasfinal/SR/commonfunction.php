<?php

require_once("pmsinterface_connect.php");
require_once("pms_wrapper.php");//Sanjay Waman - 15 Oct 2019 - Changes regarding curl hit [CEN-1165]
require_once("commonconnection.php"); //Dharti Savaliya 2020-01-09 Purpose: common connection related changes CEN-1017
header("Content-type:text/xml; charset=utf-8");

class commonfunction
{
	public $module = "commonfunction_pms";
	public $log;
	public $objSequenceDao;
	public $objpms_booking1;
	public $hotelcode;	
	private $resno;
	private $key;
	public $versionapiflag;
	
	public function generateneatxml($get)
	{
		$dom = new DOMDocument;
		$dom->preserveWhiteSpace = FALSE;
		$dom->loadXML($get);
		$dom->formatOutput = TRUE;
		$xml = htmlentities($dom->saveXml());
		return $xml;
	}
	
    public function generateErrorMsg($code,$msg,$isexit=0)
	{
		$this->log = new pmslogger($this->module);
		try
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
			$this->log->logIt("Response >>".$xmlDoc->saveXML());
			echo $xmlDoc->saveXML();
			
			if($isexit==1)
				exit;
			else
				return;
		}
		catch(Exception $e)
		{
			$this->log->logIt("Hotel_" . $hotelcode . " - Exception in " . $this->module . " - getcvcdetail - " . $e);
            $this->handleException($e);
		}
	}   
	public function guid()
    {
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);
        $uuid = substr($charid, 0, 8).$hyphen
        .substr($charid, 8, 4).$hyphen
        .substr($charid,12, 4).$hyphen
        .substr($charid,16, 4).$hyphen
        .substr($charid,20,12);
        
        return $uuid;
    }
	public function ccexpdate($exp)
	{
		$this->log = new pmslogger($this->module);
		if (strpos($exp, '-') == false )
		{
			
			$ccdate = DateTime::createFromFormat('mY', $exp);
			if(!empty($ccdate))
			{
				$finaldate = $ccdate->format('Y-m');
			}
			else
			{
				$finaldate = $exp;								
			}
			
		}
		else
		{
			
			$finaldate = $exp;
		}
		return $finaldate;
		
	}

	public function getchannelnameandcode($channelname)
	{
		switch ($channelname)
		{
			case "AD" : return "" ; break;
			case "Agoda" : return "AGO|Agoda" ; break;
			case "Agoda.com" : return "AGO|Agoda" ; break;
			case "Airbnb" : return "" ; break;
			case "All Prefer" : return "" ; break;
			case "AOTGroup" : return "" ; break;
			case "asiaroom" : return "" ; break;
			case "Asiatravel" : return "ATL|Asia Travel" ; break;
			case "Bedbooker" : return "" ; break;
			case "bestdaytravel" : return "BDT|BestDay Travel" ; break;
			case "Booking.com" : return "BDC|Booking.com" ; break;
			case "Bookinglord" : return "" ; break;
			case "Bookvisit.com" : return "" ; break;
			case "Budgetplaces.com" : return "" ; break;
			case "BusyRooms" : return "" ; break;
			case "CentralR" : return "" ; break;
			case "Checkin" : return "" ; break;
			case "Cleartrip" : return "" ; break;
			case "Cleartripxml" : return "" ; break;
			case "ConfirmedRooms" : return "" ; break;
			case "CTrip" : return "CTP|Ctrip" ; break;
			case "Despegar.com" : return "DES|Despegar (old)" ; break;
			case "DespegarXml" : return "DDC|Despegar" ; break;
			case "Easytobook" : return "" ; break;
			case "eglobesolutions" : return "" ; break;
			case "Entertainment" : return "ENT|Entertainment" ; break;
			case "Expedia QuickConnect®" : return "EXP|Expedia" ; break;
			case "Expedia" : return "EXP|Expedia" ; break;
			case "eZee Centrix" : return "" ; break;
			case "Ezibed" : return "" ; break;
			case "Fabhres" : return "" ; break;
			case "FastBooking" : return "FBK|Fastbooking" ; break;
			case "Feratel Deskline" : return "FER|feratel Deskline" ; break;
			case "FlightCenter" : return "" ; break;
			case "Flipkey" : return "" ; break;
			case "Goibibo" : return "" ; break;
			case "Gomio" : return "GMO|Gomio" ; break;
			case "Google Hotel Finder" : return "" ; break;
			case "graysonline" : return "" ; break;
			case "Greatsbooking.com" : return "" ; break;
			case "GTA" : return "GTA|GTA-Travel" ; break;
			case "HolidayLettings" : return "" ; break;
			case "Homeaway" : return "" ; break;
			case "Hostelbookers" : return "" ; break;
			case "HostelsClub.com" : return "HSC|HostelsClub" ; break;
			case "Hostelworld" : return "" ; break;
			case "HostelWorld XML" : return "HWL|Hostel World" ; break;
			case "Hotel Network" : return "" ; break;
			case "Hotelbeds" : return "HBD|Hotelbeds" ; break;
			case "Hotelde" : return "" ; break;
			case "HotelLinkSolution" : return "" ; break;
			case "Hotelsclick" : return "" ; break;
			case "HotelsCombined" : return "HCO|HotelsCombined" ; break;
			case "Hotelsnl" : return "" ; break;
			case "HotelSpecials.nl" : return "" ; break;
			case "HotelTonight" : return "HTN|HotelTonight" ; break;
			case "HotelTravel.com" : return "HTR|HotelTravel" ; break;
			case "Hotusa" : return "HUS|Hotusa" ; break;
			case "Hotwire" : return "HWR|Hotwire" ; break;
			case "HRS" : return "" ; break;
			case "Hutchgo" : return "" ; break;
			case "IBCHotels" : return "IBC|IBC Hotels" ; break;
			case "JasonsAU" : return "" ; break;
			case "Jetstar" : return "" ; break;
			case "Jovago" : return "JVG|Jovago" ; break;
			case "LankaHouse" : return "" ; break;
			case "Lastminute" : return "" ; break;
			case "Late Rooms" : return "LRM|LateRooms/AsiaRooms" ; break;
			case "Lido" : return "LDO|Lido" ; break;
			case "LookBack" : return "" ; break;
			case "makemytrip" : return "" ; break;
			case "MakemytripXml" : return "" ; break;
			case "malaysiabudgethotels" : return "" ; break;
			case "MetGlobal" : return "" ; break;
			case "MrAndMrsSmith" : return "MMS|Mr and Mrs Smith" ; break;
			case "MyERes.com" : return "MRS|Myeres.com" ; break;
			case "Myindianstay" : return "" ; break;
			case "NeedItNow" : return "NIN|NeedItNow" ; break;
			case "Notonenight" : return "NON|Not 1 Night" ; break;
			case "OKgoasia" : return "" ; break;
			case "OpenHotelier" : return "RAT|Openhotelier" ; break;
			case "Orbitz" : return "" ; break;
			case "Ostrovok" : return "OVK|Ostrovok" ; break;
			case "Pelican" : return "" ; break;
			case "PMSXCHANGE" : return "" ; break;
			case "Prestigia" : return "PTA|Prestigia" ; break;
			case "Priceline" : return "PCL|Priceline.com" ; break;
			case "PyoTravel" : return "PYO|PYOTravel" ; break;
			case "Quickbeds" : return "" ; break;
			case "Reconline" : return "RCL|Reconline" ; break;
			case "ReservHotel" : return "" ; break;
			case "Resonline" : return "" ; break;
			case "Roomorama" : return "" ; break;
			case "RoomsTonite" : return "" ; break;
			case "Soulitude" : return "" ; break;
			case "Splendia" : return "SPD|Splendia" ; break;
			case "StayNest" : return "" ; break;
			case "Stayzilla" : return "" ; break;
			case "Stayzillaxml" : return "" ; break;
			case "Sunhotels" : return "" ; break;
			case "Synxis" : return "SYX|SynXis" ; break;
			case "TabletHotels" : return "TBH|TabletHotels" ; break;
			case "TheLuxeNomad" : return "" ; break;
			case "ThinkHotels" : return "" ; break;
			case "ThreeCS" : return "" ; break;
			case "Tiketdotcom" : return "TKT|Tiket.com" ; break;
			case "TouristOnline" : return "TRO|TouristOnline.dk" ; break;
			case "Travco" : return "" ; break;
			case "TravelBoutiqueOnline" : return "" ; break;
			case "Travelguru" : return "" ; break;
			case "TravelguruXML" : return "" ; break;
			case "Travelocity" : return "" ; break;
			case "Traveloka" : return "TRK|Traveloka" ; break;
			case "Travelport" : return "" ; break;
			case "travelrepublic" : return "TRP|Travel Republic" ; break;
			case "TravelTreez" : return "" ; break;
			case "Treovi" : return "" ; break;
			case "UnisterTravel" : return "UNR|Unister" ; break;
			case "venere" : return "" ; break;
			case "VervePortal" : return "" ; break;
			case "Via" : return "" ; break;
			case "Via XML" : return "" ; break;
			case "VVVAmeLand" : return "" ; break;
			case "Webreservation" : return "" ; break;
			case "Welcomebeds" : return "" ; break;
			case "Wotif" : return "" ; break;
			case "Wotifxml" : return "" ; break;
			case "Internet Booking Engine" : return "IBE|Internet Booking Engine" ; break; //Dharti Savaliya - 2019-07-18 - Purpose: php7 migration changes CEN-1017
		}
	}
	
	public function getcvcdetail($hotelcode,$resno)
	{
		try
		{
			$this->log = new pmslogger($this->module);
            $result = NULL;
            $sqlStr = " SELECT FDTI.tranunkid,FDTI.reservationno,";
			$sqlStr.="FDCBI.channelbookingunkid as channelbookingunkid,FDCBI.ruid as ruid,FDCBI.ruid_status as ruid_status ";
			$sqlStr.=" FROM fdtraninfo AS FDTI";
			$sqlStr.=" LEFT JOIN fdchannelbookinginfo AS FDCBI ON FDCBI.tranunkid = FDTI.tranunkid AND ";
			$sqlStr.="FDCBI.hotel_code=:hotel_code";
            $sqlStr.=" WHERE FDTI.hotel_code=:hotel_code AND FDTI.reservationno=:resno";
         
			$dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $hotelcode);
           
            $dao->addParameter(":resno", $resno);
			$result = $dao->executeQuery();
			
			$objSequenceDao = new sequencedao();
			$sequence=array();
			
			if($result[0]['channelbookingunkid']!='' && $result[0]['ruid_status']!='' && $result[0]['ruid']!='')
				$sequence = $objSequenceDao->getsequence($result[0]['ruid_status'],$result[0]['channelbookingunkid'],$result[0]['ruid']);
		
			$cvc=isset($sequence->cvc) ? $sequence->cvc : '';
			return $cvc;
		}
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $hotelcode . " - Exception in " . $this->module . " - getcvcdetail - " . $e);
            $this->handleException($e);
        }
	}
	
	public function handleException($e,$rflag=1) //Sanjay Waman - 09 Apr 2019 - $rflag manage for skiped exception in loop.
	{
		$this->log = new pmslogger($this->module);
        try 
		{
			$this->log = new pmslogger($this->module);
            $this->log->logIt("handleException");
			$this->generateErrorMsg('500', "Error occured during processing",$rflag);            
        } 
		catch (Exception $e) 
		{
            $this->log->logIt(get_class($this) . "-" . "handleException" . "-" . $e);
            exit;
        }
    }
	
	public function executeRequest($request)
    {
        $this->log = new pmslogger($this->module);
        //$this->log->logIt($this->module."-executeRequest");
		try
		{
			$this->log->logIt("request >>".$request);
			//Sanjay Waman - 15 Oct 2019 - reduces curl hits
			//if(isset($this->callobjmethod) && trim($this->callobjmethod)==1)
			//{
				$this->log->logIt("ObjectCallingPartExecute");
				$reservation = new pms_wrapper();				
				if(isset($this->versionapiflag) && $this->versionapiflag == 1)
				{
					$this->log->logIt("Version 2 API Flag >>". $this->versionapiflag);
					$reservation->versionapiflag = 1;
				}
				else
				{
					$reservation->versionapiflag = 0;
				}
				$reservation->objcallflag = 1;
				$reservation->returnmsg = '';
				$response = $reservation->fetchRequest($request);
				if(isset($reservation->returnmsg) && trim($reservation->returnmsg)!='' && trim($response)=='')
				{
					$response = $reservation->returnmsg;
				}
			/*}
			else
			{
			// Sushma Rana - 1st nov 2017 - Changes done for remove URL depency -start			
			global $hostname;
			global $commonurldynamic;			
			
			$this->log->logIt("Comman URL >>".$commonurldynamic);			
			$URL = $commonurldynamic.'pmsinterface/reservation.php';
			$this->log->logIt("Excute request URl >>".$URL);			
			// Sushma Rana - end
			
            $ch=curl_init();
            curl_setopt($ch, CURLOPT_URL, $URL);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
            $httpHeader = array(
                        "Content-Type: text/xml; charset=UTF-8",
                        "Content-Encoding: UTF-8"
                    );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);
            $response=curl_exec($ch);
            $cinfo = curl_getinfo($ch);
            curl_close($ch);
			//$this->log->logIt("httpcode=======>".$cinfo['http_code']);
			}*/
			//Sanjay Waman - End
            $this->log->logIt("response >>".$response);
			return $response;
        }
        catch (Exception $e) 
		{
			//$this->log->logIt("Exception in " . $this->module . " - executeRequest - " . $e);
			$commonfunc->handleException($e);
		}
    } // public function executeRequest($request)
	
	/*public function getUsernamePwd()
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getHotelCodeAuthcode");
			$ObjPMSInterfaceConn = new pmsinterface_connect();
			$i_row = array();
			
			//Check Connection
			global $basePath;
			global $commonurl;
			global $server;
			global $host;
			global $username;
			global $password;			
			global $connection;
			global $cn;
			$this->log->logIt("hotelcode >>".$host. "|" .$username. "|" .$password);
			//$this->log->logIt("hotelcode >>".$hotelcode);
			//$this->log->logIt("getHotelCodeAuthcode >>".$key);
	
			//MySql Connection	- saasconfig database				
			$cn = mysql_connect($host,$username,$password);
			$db = mysql_select_db("saasconfig",$cn);
			if($cn=='' || $db=='')
			$commonfunc->generateErrorMsg('201', "Cannot connect to server",0);
			
			
			return $cn;
		}
		catch (Exception $e)
		{
			 $this->log->logIt("Exception in " . $this->module . " - getUsernamePwd - " . $e);
            $this->handleException($e);			
		}
	}*/
	
	/*public function getHotelCodeAuthcode($hotelcode,$key)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getHotelCodeAuthcode");
			$UsernamePwd = $this->getUsernamePwd();
			$i_result = mysql_query('SELECT * FROM syshotelcodekeymapping 
						inner join syscompany on syscompany.hotel_code=syshotelcodekeymapping.hotel_code
						where integration="RES" and isactive=1 and syshotelcodekeymapping.`key`="'.$key.'" and syshotelcodekeymapping.`hotel_code`="'.$hotelcode.'" ' );
			if(mysql_num_rows($i_result)<=0) die;
			
			$i_row = array(mysql_fetch_assoc($i_result));
			
			return $i_row;
		}
		catch (Exception $e)
		{
			 $this->log->logIt("Hotel_" . $hotelcode . " - Exception in " . $this->module . " - getHotelCodeAuthcode - " . $e);
			 $this->handleException($e);			
		}
	}*/
	
	/*public function readConnection()
	{
		$this->log = new pmslogger($this->module);
		try
		{
			//Sanjay - 11 Nov 2019 - connection related changes - [CEN-1383] - Start
			if(isset($this->hotelcode) && (trim($this->hotelcode)=='15457' || trim($this->hotelcode)=='8963') )
			{
				$this->log->logIt(get_class($this)."-"."readConnection new- ".$this->hotelcode);

				global $server;
				global $host;
				global $username;
				global $password;				
				global $connection;
				global $repl_host;
				global $repl_username;			
				global $repl_password;

				$hotel_code = $this->hotelcode;
				
				$hostname = gethostname();
				$this->log->logIt("Hostname >>". $hostname);
				if($hostname == 'ubuntu')
				{
					$dsn="mysql:host=".$host.";dbname=saasconfig"; 		
					$connection=new TDbConnection($dsn,$username,$password,'utf8');
				}
				else{
					try{			
						$dsn="mysql:host=".$repl_host.";dbname=saasconfig"; 		
						$connection=new TDbConnection($dsn,$repl_username,$repl_password,'utf8');	
					}catch(Exception $e)	
					{
						$this->log->logIt("Exception catch in " . $this->module . " - fetchRequest - " . $e);
						$this->log->logIt("<< Master Connection >>");
						$dsn="mysql:host=".$host.";dbname=saasconfig"; 		
						$connection=new TDbConnection($dsn,$username,$password,'utf8');							
					}	
				}
				$connection->Active=true;			
				if($connection=='')
				{
					return false;
				}

				$dao = new dao();
				$saasconfigquery = "SELECT databasename FROM syscompany WHERE hotel_code=".$hotel_code." ORDER BY companyunkid DESC LIMIT 1";
				$dao->initCommand($saasconfigquery);
				$row = $dao->executeQuery();
				$this->log->logIt($row);
				
				$dbname = isset($row[0])?(isset($row[0]["databasename"])?$row[0]["databasename"].'':''):'';
				if(trim($dbname)=="")
				{
					$this->log->logIt("Wrong Hotel Code");
					return false;
				}				
				try
				{
					$select_db = "USE ".$dbname; // Change database 
					$dao->initCommand($select_db);
					$dao->executeNonQuery();		
					return $connection;
				}catch(Exception $e) 
				{	
					$dsn="mysql:host=".$host.";dbname=".$dbname.""; 		
					$connection=new TDbConnection($dsn,$username,$password,'utf8');
					return $connection;
				}
			}
			else
			{
				//Sanjay - 11 Nov 2019 - connection related changes - [CEN-1383] - END
				$this->log->logIt(get_class($this)."-"."readConnection -".$this->hotelcode);
				global $server;
				global $host;
				global $username;
				global $password;				
				global $connection;
				$hotel_code = $this->hotelcode;
				
				$cn = mysql_connect($host,$username,$password);
				$db = mysql_select_db("saasconfig",$cn);
				$result = mysql_query("SELECT servername,port,mysqlusername,mysqlpassword,databasename,readreplicaname FROM syscompany WHERE hotel_code=".$hotel_code." ORDER BY companyunkid DESC LIMIT 1",$cn);
				$row = mysql_fetch_array($result);
				
				$dbname = $row["databasename"];
				$servername = $row["servername"];
				$port = $row["port"];				
				$mysqlusername=$username; 
				$mysqlpassword=$password;  
				$readreplicaname = $row["readreplicaname"];
				
				
				//Sanjay Waman - 29 Dec 2018 - Wrong Hotel Code - Start
				if(trim($dbname)=="")
				{
					$this->log->logIt("Wrong Hotel Code");
					return false;
				}
				//Sanjay Waman - END 
				try
				{
					$dsn="mysql:host=".$readreplicaname.";port=".$port.";dbname=".$dbname;
					$connection=new TDbConnection($dsn,$mysqlusername,$mysqlpassword,'utf8');	
					$connection->Active=true;
					$this->log->logIt("Replica server >>");
					$dao = new dao();
					$strSql = "select ROUND(Replica_lag_in_msec/1000) as Seconds_Behind_Master,'Yes' as Slave_IO_Running,'Yes' as Slave_SQL_Running from mysql.ro_replica_status where Session_id != 'MASTER_SESSION_ID' limit 1";
					$dao->initCommand($strSql);
					$result = $dao->executeRow();
					$this->log->logIt("Result Data >>".$result);
					if (!($result['Slave_IO_Running'] == 'Yes' && $result['Slave_SQL_Running'] == 'Yes' && $result['Seconds_Behind_Master']<300)) {						
						$dsn="mysql:host=".$servername.";port=".$port.";dbname=".$dbname;
						$connection=new TDbConnection($dsn,$mysqlusername,$mysqlpassword,'utf8');	
						$connection->Active=true;
						$this->log->logIt("master server ");
						return $connection;
					}
				}
				catch (Exception $e) 
				{
					$dsn="mysql:host=".$servername.";port=".$port.";dbname=".$dbname;
					$connection=new TDbConnection($dsn,$mysqlusername,$mysqlpassword,'utf8');	
					$connection->Active=true;
					$this->log->logIt("master server catch block ");
					return $connection;
				}
				//connection
			}
		}
		catch(Exception $e){
			
			$this->log->logIt("Exception in " . $this->module . " - connection - " . $e->getMessage());
            $this->handleException($e->getMessage());		
			
		}
	}*/
	
	public function getroomdetail($pmsroomid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getroomdetail");
			
			//$connection = $this->readConnection();
			$dao = new dao();																	
			$strSql = "select roomtypeunkid from pmsroomtypemapping where hotel_code =:hotel_code AND pmsroomtypeid =:pmsroomtypeid ;";
			$dao->initCommand($strSql);														
			$dao->addParameter(":pmsroomtypeid", $pmsroomid);
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$inventoryid = $dao->executeQuery();
			//$this->log->logIt('sql >> '.$strSql);														
			//$this->log->logIt('Room detail >>'.print_r($inventoryid,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$roomtypeid = isset($inventoryid[0]['roomtypeunkid'])?$inventoryid[0]['roomtypeunkid'].'':"";//Sanjay Waman - 30 jul 2018 - check isset
			$this->log->logIt('Room Detail >> '.$roomtypeid);
			return $roomtypeid;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getroomdetail - " . $e);
            $this->handleException($e);			
		}
	}	
	public function getratedetail($mealplanid, $rateplanid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getratedetail");	
			//$connection = $this->readConnection();
			
			$dao = new dao();
																
			$strSql = "select ratemappingunkid from pmsratetypemapping where hotel_code =:hotel_code AND pmsratemappingunkid =:pmsratetypeid AND pmsmealplan =:pmsmealplanid ;";
			$dao->initCommand($strSql);														
			$dao->addParameter(":pmsmealplanid", $mealplanid);
			$dao->addParameter(":pmsratetypeid", $rateplanid);
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$ratetypeid = $dao->executeQuery();
			//$this->log->logIt('sql >>'.$strSql);														
			//$this->log->logIt('Rate Detail >> '.print_r($ratetypeid,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$ratetypeid = isset($ratetypeid[0]['ratemappingunkid']) ? (string)$ratetypeid[0]['ratemappingunkid'] : ""; //Dharti Savaliya 2018-10-29
			$this->log->logIt('Rate Detail >> '.$ratetypeid);
			
			return $ratetypeid;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getratedetail - " . $e);
            $this->handleException($e);			
		}
	}
	
	public function getrateplandetail($rateplanid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getrateplandetail");	
			//$connection = $this->readConnection();			
			$dao = new dao();																
			$strSql = "select ratemappingunkid from pmsratetypemapping where hotel_code =:hotel_code AND pmsratemappingunkid =:pmsratetypeid;";
			$dao->initCommand($strSql);													
			
			$dao->addParameter(":pmsratetypeid", $rateplanid);
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$ratetypeid = $dao->executeQuery();
			//$this->log->logIt('sql >>'.$strSql);														
			//$this->log->logIt('Rate Detail >> '.print_r($ratetypeid,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$ratetypeid = isset($ratetypeid[0]['ratemappingunkid'])?$ratetypeid[0]['ratemappingunkid'].'':"";//Sanjay Waman - 30 jul 2018 - check isset
			$this->log->logIt('Rate Detail >> '.$ratetypeid);
			
			return $ratetypeid;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getrateplandetail - " . $e);
            $this->handleException($e);			
		}
	}
	public function getpmsroomdetail($roomid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getroomdetail");
			//$connection = $this->readConnection();			
			$dao = new dao();																	
			$strSql = "select pmsroomtypeid from pmsroomtypemapping where hotel_code =:hotel_code AND roomtypeunkid =:roomtypeid ;";
			$dao->initCommand($strSql);														
			$dao->addParameter(":roomtypeid", $roomid);
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$inventoryid = $dao->executeQuery();
			//$this->log->logIt('sql >> '.$strSql);														
			//$this->log->logIt('Room detail >>'.print_r($inventoryid,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$roomtypeid = isset($inventoryid[0]['pmsroomtypeid']) ? (string)$inventoryid[0]['pmsroomtypeid'] : ""; //Dharti Savaliya 2018-10-29 
			$this->log->logIt('Room Detail >> '.$roomtypeid);
			return $roomtypeid;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getpmsroomdetail - " . $e);
            $this->handleException($e);			
		}
	}
	public function getpmsratedetail($rateplanid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getpmsratedetail");	
			//$connection = $this->readConnection();
			
			$dao = new dao();
																
			$strSql = "select pmsratemappingunkid from pmsratetypemapping where hotel_code =:hotel_code AND ratemappingunkid =:ratemappingunkid;";
			$dao->initCommand($strSql);														
			
			$dao->addParameter(":ratemappingunkid", $rateplanid);
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$ratetypeid = $dao->executeQuery();
			//$this->log->logIt('sql >>'.$strSql);														
			//$this->log->logIt('Rate Detail >> '.print_r($ratetypeid,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$ratetypeid = isset($ratetypeid[0]['pmsratemappingunkid']) ? (string)$ratetypeid[0]['pmsratemappingunkid'] : ""; //Dharti Savaliya 2018-10-29 
			$this->log->logIt('Rate Detail >> '.$ratetypeid);
			
			return $ratetypeid;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getpmsratedetail - " . $e);
            $this->handleException($e);			
		}
	}
	
	public function getsystempropertydetail($propertyid,$account_id='')
    {
        $this->log = new pmslogger($this->module);
        $this->log->logIt($this->module."getsystempropertydetail");		
		try
		{
            $this->log->logIt("Property code >>".$propertyid);
			
			$auto_code ='';
			$dao = new dao();
			if(trim($account_id)!="")
			{
				$result = "SELECT hotel_code from syspmshotelinfo WHERE pmshotelcode='".$propertyid."' and custom1='".$account_id."';";
			}
			else
			{
				$result = "SELECT hotel_code from syspmshotelinfo WHERE pmshotelcode='".$propertyid."';";
			}
			$dao->initCommand($result);	
			$row = $dao->executeQuery();
			$hotel_code = (isset($row[0]) && isset($row[0]["hotel_code"])) ? trim($row[0]["hotel_code"]) : '';
			$this->log->logIt("Hotel code >>".$hotel_code);
			if($hotel_code != '')
			{
				$result = "select * from syshotelcodekeymapping where hotel_code=".$hotel_code." AND integration = 'RES';";
				//Sanjay Waman - 14 Aug 2018 - return null value for invalid pmshotelcode -START
				if($result!='')
				{
				  $dao->initCommand($result);	
				  $row = $dao->executeQuery();
				  $auto_code =(isset($row[0]) && isset($row[0]["key"])) ? trim($row[0]["key"]) : '';
				}
			}				
			$this->log->logIt("Authenication code >>".$auto_code);			
			$detail= array($hotel_code, $auto_code); 
						
			return $detail;
        }
        catch (Exception $e) 
		{
			$this->log->logIt("Exception in " . $this->module . " - getsystempropertydetail - " . $e);
			$this->handleException($e);
		}
    }
	
	public function getpmspropertydetail($propertyid)
    {
        $this->log = new pmslogger($this->module);
        $this->log->logIt($this->module."getpmspropertydetail");		
		try
		{
            $this->log->logIt("Property code >>".$propertyid);		
			
			//Connection for get hotel code
			global $server;
			global $host;
			global $username;
			global $password;			
			global $connection;									 
			//AutoScriptConn Object
			$ObjPMSInterfaceConn = new pmsinterface_connect();		
			
			$dao = new dao();
			$result = "SELECT * from saasconfig.syspmshotelinfo WHERE hotel_code='".$propertyid."' limit 1 ;" ;
			$dao->initCommand($result);	
			$row = $dao->executeQuery();
			//$this->log->logIt("PMS Hotel Code >>".json_encode($row[0]),"encrypted");//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]				
			return $row[0];
						
        }
        catch (Exception $e) 
		{
			$this->log->logIt("Exception in " . $this->module . " - getpmspropertydetail - " . $e);
			$this->handleException($e);
		}
    }
	
	//Sanjay Waman - 14 Mar 2019 - Get channel using businesssource - Start
	public function getchannelnamewithbusinessid($Businesssourceid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getchannelnamewithbusinessid >".$Businesssourceid);	
			$channelunkid='0';
			if(isset($Businesssourceid) && trim($Businesssourceid)!='')
			{
				$dao = new dao();		
				$this->log->logIt("Businesssourceid>>".$Businesssourceid);
				$strSql = "SELECT channelunkid FROM saasconfig.syschannelhotelinfo WHERE hotel_code=:hotel_code AND businesssourceaccount=:businesssourceaccount Limit 1;";
				$dao->initCommand($strSql);														
				
				$dao->addParameter(":hotel_code", $this->hotelcode);
				$dao->addParameter(":businesssourceaccount", $Businesssourceid);
				$channelunkid = $dao->executeQuery();
				//$this->log->logIt($channelunkid);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
				$channelunkid = isset($channelunkid[0]['channelunkid']) ? $channelunkid[0]['channelunkid'] : "";
				$this->log->logIt('Businesssourceid - channelunkid>> '.$channelunkid);
				
			}
			if(isset($channelunkid) && $channelunkid != '0')
			{
				$channelname = $this->getchannelname($channelunkid);
			}
			if(isset($channelname) && $channelname != '' )
			{
				return $channelname;
			}
			else
			return false;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getchannelnamewithbusinessid - " . $e);
            $this->handleException($e);
			return false;
		}
	}
	//Sanjay -End
	
	public function getchannelstandardname($tranid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getchannelstandardname");	
			//$connection = $this->readConnection();
			
			$dao = new dao();																
			$strSql = "select channelunkid from fdchannelbookinginfo where hotel_code =:hotel_code AND tranunkid =:tranid;";
			$dao->initCommand($strSql);														
			
			$dao->addParameter(":tranid", $tranid);
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$channelunkid = $dao->executeQuery();
			//$this->log->logIt('sql >>'.$strSql);														
			//$this->log->logIt('Rate Detail >> '.print_r($channelunkid,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$channelunkid = isset($channelunkid[0]['channelunkid']) ? $channelunkid[0]['channelunkid'] : "";
			$this->log->logIt('channelunkid>> '.$channelunkid);
			
			if(isset($channelunkid) && $channelunkid != '0')
			{
				$channelname = $this->getchannelname($channelunkid);
			}
			if(isset($channelname) && $channelname != '' )
			{
				return $channelname;
			}
			else
			return false;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getchannelstandardname - " . $e);
            $this->handleException($e);
		}
	}
	public function getchannelname($channelunkid)
	{
		$this->log = new pmslogger($this->module);
        $this->log->logIt($this->module."getchannelname");		
		try
		{
            $this->log->logIt("Channelunkid >>".$channelunkid);
			//Sanjay Waman - 09 Apr 2019 - changed Db connection (DBGoneWay Error in zen) - START
			//Connection for get hotel code
			/*global $server;
			global $host;
			global $username;
			global $password;			
			global $connection;									 
			
			$cn = mysql_connect($host,$username,$password);
			$db = mysql_select_db("saasconfig",$cn);
			
			$result = mysql_query("SELECT channelname from syschannelinfo WHERE channelunkid='".$channelunkid."';",$cn);
			if($result == false )
			{
				echo mysql_error();
			}
			//$row = mysql_fetch_array($result);
			*/
			$dao = new dao();
			$strSql = "SELECT channelname from saasconfig.syschannelinfo WHERE channelunkid='".$channelunkid."';";			
			$dao->initCommand($strSql);		
			
			$result->resultValue['record'] = $dao->executeRow();
			$row = (array)$result->resultValue['record'];
			//Sanjay Waman - 09 Apr 2019 - END
			//$this->log->logIt("channelname >>".print_r($row,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			
			$channelname = isset($row["channelname"])?$row["channelname"].'':'';
			$this->log->logIt("channelname >>".$channelname);
			
			if($channelname == "TravelguruXML")
			{
				$channelname = "Travelguru";
			}
			if($channelname == "HostelWorld XML")
			{
				$channelname = "HostelWorld";
			}
			if($channelname == "Via XML")
			{
				$channelname = "Via";
			}
			if($channelname == "Hotusaxml")
			{
				$channelname = "Hotusa";
			}
			return $channelname;
        }
        catch (Exception $e) 
		{
			$this->log->logIt("Exception in " . $this->module . " - getchannelname - " . $e);
			$this->handleException($e,0);//Sanjay Waman - 09 Apr 2019 - Return flag manage for skiped exception.
			return "Error";
		}
	}
	public function getrateid($roomtypeunkid,$roomrateunkid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getrateid - ".$roomtypeunkid."|".$roomrateunkid."|".$this->hotelcode);	
			
			$dao = new dao();
			$strSql = "SELECT ratetypeunkid FROM cfroomrate_setting WHERE roomrateunkid=:roomrateunkid AND roomtypeunkid=:roomtypeunkid AND hotel_code=:hotel_code";			
			$dao->initCommand($strSql);		
			$dao->addParameter(":roomtypeunkid",$roomtypeunkid);	
			$dao->addParameter(":roomrateunkid",$roomrateunkid);	
			$dao->addParameter(":hotel_code", $this->hotelcode);	
			
			//$this->log->logIt($strSql);	
			$result->resultValue['record'] = $dao->executeRow();
			$row = (array)$result->resultValue['record'];
			//Dharti Savaliya - START - 2019-08-13 Purpose:- Fiexed Undefine index error 	
			$getratytypeid	= (isset($row['ratetypeunkid']) ? (string)$row['ratetypeunkid'] : '');	
			$this->log->logIt("ratetypeunkid in ".$getratytypeid);
			//Dharti Savaliya - END
			return $getratytypeid;
		}
		catch(Exception $e)
		{
			$this->log->logIt(get_class($this)."-"."getrateid"."-".$e);	
			return "-1";
		}
	}	
	
	public function generateGroup($jsonarray, $operation)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$group_data=array();
			//$this->log->logIt("Array  >>".print_r($jsonarray,true));
			$this->log->logIt("operation  >>".$operation);
			foreach($jsonarray as  $key => $value)
			{
				//$this->log->logIt("key >>".print_r($key,true));
				//$this->log->logIt("value >>".$value);
				list($start_date,$end_date,$roomtypeid,$ratetypeid,$ratePlanId) = explode("|",$key);				
				//$this->log->logIt("Start Date >>".$start_date);
				//$this->log->logIt("End date >>".$end_date);
				//$this->log->logIt("roomtypeid >>".$roomtypeid);
				//$this->log->logIt("ratetypeid >>".$ratetypeid);
				//$this->log->logIt("ratePlanId >>".$ratePlanId);				
				//$this->log->logIt("Operation Value >>".$value);
				
				switch($operation)
					{
						case "UpdateInventory":
							$operation_value=$value."";
							break;
						case "UpdateRoomRates":
							$operation_value = $value."";
							break;
						case "UpdateMinimumNightOfStay":
							$operation_value=$value."";
							break;
						case "UpdateMaxNightOfStay":
							$operation_value=$value."";
							break;
						case "UpdateStopSell":
							$operation_value=$value."";
							break;
						case "UpdateCloseToArrival":
							$operation_value=$value."";
							break;
						case "UpdateCloseToDeparture":
							$operation_value=$value."";							
							break;
					}
				
					if(count($group_data)==0)
					{
						//$group_data[$start_date."|".$end_date."|".$roomtypeid."|"] = $operation_value;
						$group_data[$start_date.'|'.$end_date.'|'.$roomtypeid.'|'.$ratetypeid.'|'.$ratePlanId] = $operation_value;
					}
					else
					{
						$flag=0;								
						foreach(array_keys($group_data) as $dates)
						{
							//$this->log->logIt("Dates >>". print_r($dates, true));							
							list($fromdate,$todate,$grouproomtypeid,$groupratetypeid,$groupratePlanId)=explode("|",$dates);								
							//$this->log->logIt("From Date >>".$fromdate);
							//$this->log->logIt("To date >>".$todate);
							//$this->log->logIt("Start Date >>".$start_date);
							$datediff=$this->getDays($start_date,$todate);							
							//$this->log->logIt("datediff >>". $datediff);
							if(($grouproomtypeid == $roomtypeid) && ($groupratetypeid == $ratetypeid) && ($groupratePlanId == $ratePlanId))
							{
								if($group_data[$fromdate.'|'.$todate.'|'.$roomtypeid.'|'.$ratetypeid.'|'.$ratePlanId] == $operation_value && $datediff==1)
								{
									unset($group_data[$fromdate.'|'.$todate.'|'.$roomtypeid.'|'.$ratetypeid.'|'.$ratePlanId]);
									$group_data[$fromdate.'|'.$end_date.'|'.$roomtypeid.'|'.$ratetypeid.'|'.$ratePlanId] = $operation_value;	
									$flag++;									
								}
							}
						}
						
						if($flag==0)
							$group_data[$start_date.'|'.$end_date.'|'.$roomtypeid.'|'.$ratetypeid.'|'.$ratePlanId] = $operation_value;								
					}						
										
			}
			//$this->log->logIt("Final group Data >>". print_r($group_data, true));
			return $group_data;
		}
		catch(Exception $e)
		{
			$this->log->logIt("generateGroup >>",$e);
			return 0;
		}
	}
	
	public function getDays($enddate,$startdate)
	{
		$interval=date_diff(date_create(date('Y-m-d',strtotime($enddate))),date_create(date('Y-m-d',strtotime($startdate))));
		return $interval->format("%a");
	}
	
	public function insertpmsbooking($hotel_code,$data,$pmsdetail)
	{
		$this->log = new pmslogger($this->module);
		$commoncon = new commonconnection();
		$commoncon->module = $this->module;
		$commoncon->hotelcode = $hotel_code;
		
		$result=new resultobject();
		try
		{
			$this->log->logIt(get_class($this)."-"."insertpmsbooking");
			//$this->log->logIt("Data  >>".print_r($data,True));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$connection = $this->readmasterConnection();
			$connection = $commoncon->masterdbconn(1);
			$dao=new dao();
			
			$data = (array) $data;
				
			$strSql = " INSERT INTO commondb.fdpmsbookinginfo (hotel_code,tranunkid,pmsbookingid,subpmsbookingid,createdate,checkindate,checkoutdate,pmsinfo) ";
			$strSql .= " VALUES(:hotel_code,:tranid,:pms_id,:subpms_id,now(),:checkindate,:checkoutdate,:pmsdetail) ";
			
			$dao->initCommand($strSql);
						
			$dao->addParameter(":hotel_code",$hotel_code);
			$dao->addParameter(":tranid",$data['Tranid']);			
			$dao->addParameter(":pms_id",isset($data['pms_id']) ? (string)$data['pms_id'] : "");		
			$dao->addParameter(":subpms_id",$data['Subresevation_no']);
			$dao->addParameter(":pmsdetail",$pmsdetail);			
			$dao->addParameter(":checkindate",$data['checkindate']);
			$dao->addParameter(":checkoutdate",$data['checkoutdate']);
			
			$dao->executeNonQuery();			
			//$this->log->logIt("SQL >> ".$strSql);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$result->resultCode=resultConstant::Success;
			unset($connection);
			return $result;			
		}
		
		catch(Exception $e)
		{
			$this->log->logIt(get_class($this)."-"."insertpmsbooking"."-".$e);
			$result->resultCode=resultConstant::Error;
			$result->exception=$e;
			$result->viewName="errorpage";
		}		
		return $result;
	}
	
	public function isExistBookingDetail($hotel_code,$tranid)
	{
		$this->log = new pmslogger($this->module);
		$result=new resultobject();
		try
		{
			$this->log->logIt(get_class($this)."-"."isExistBookingDetail");
			$dao=new dao();
			$strSql="SELECT pmsbookingid,subpmsbookingid FROM commondb.fdpmsbookinginfo WHERE tranunkid=:tranid AND hotel_code=:hotel_code";
			$dao->initCommand($strSql);	
			$dao->addParameter(":tranid",$tranid);
			$dao->addParameter(":hotel_code",$hotel_code);
			//$this->log->logIt($strSql.">>".$hotel_code." --> Hotel_code ".$tranid." --> Tranid");//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$result->resultValue['list'] = $dao->executeQuery();
			$result->resultValue['total'] = count($dao->executeQuery());
			return $result;
		}
		catch(Exception $e)
		{
			$this->log->logIt(get_class($this)."-"."isExistBookingDetail"."-".$e);
			$result->resultCode = resultConstant::Error;			
			$result->exception = $e;
			$result->viewName = "errorpage";
			return false;
		}
	}	
	
	public function gettranid($resno,$hotelcode,$sub_res_id='')
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt("Hotel_" . $hotelcode . "gettranid");			
	    
            $sqlStr = "SELECT tranunkid FROM fdtraninfo 
					   WHERE reservationno = :resno AND hotel_code = :hotel_code";
					   
			if($sub_res_id!='')
				$sqlStr.= " AND subreservationno = :sub_res_id";
			
			//$this->log->logIt("Getreservationdetails >>".$sqlStr);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $hotelcode);
			$dao->addParameter(":resno", $resno);
			if($sub_res_id!='')
			$dao->addParameter(":sub_res_id", $sub_res_id);
			
			$result = $dao->executeQuery();
			//$this->log->logIt("Result".print_r($result,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $hotelcode . " - Exception in " . $this->module . " - gettranid - " . $e);
            $this->handleException($e);
        }
        return $result;
	}
	
	public function getstatusdetail($resno,$hotelcode)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt("Hotel_" . $hotelcode . " - " . get_class($this) . "-" . "getstatusdetail");			
	    
            $sqlStr = "SELECT reservationno,statusunkid,subreservationno 
					   FROM fdtraninfo AS FDTI 
					   WHERE hotel_code=:hotel_code AND reservationno=:resno ORDER BY statusunkid ASC;";
			
			//$this->log->logIt("Getreservationdetails >>".$sqlStr);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $hotelcode);
			$dao->addParameter(":resno", $resno);
			
			$result = $dao->executeQuery();
			//$this->log->logIt("Result".print_r($result,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $hotelcode . " - Exception in " . $this->module . " - gettranid - " . $e);
            $this->handleException($e);
        }
        return $result;
	}


	/*public function readmasterConnection()
	{
		$this->log = new pmslogger($this->module);
		try
		{
				$this->log->logIt(get_class($this)."-"."readmasterConnection");
				
				global $server;
				global $host;
				global $username;
				global $password;				
				global $connection;
				$hotel_code = $this->hotelcode;
				
				$cn = mysql_connect($host,$username,$password);
				$db = mysql_select_db("saasconfig",$cn);
				$result = mysql_query("SELECT servername,port,mysqlusername,mysqlpassword,databasename,readreplicaname FROM syscompany WHERE hotel_code=".$hotel_code." ORDER BY companyunkid DESC LIMIT 1",$cn);
				$row = mysql_fetch_array($result);
				
				$dbname = $row["databasename"];
				$servername = $row["servername"];
				$port = $row["port"];			
				
				$this->log->logIt("Masterserver >>".$servername);
				$this->log->logIt("Port >>".$port);
				$this->log->logIt("Dbname >>".$dbname);
				
				$dsn="mysql:host=".$servername.";port=".$port.";dbname=".$dbname;
				$connection=new TDbConnection($dsn,$username,$password,'utf8');	
				$connection->Active=true;
				$connection->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
				$this->log->logIt("Mater server connected >>");
				return $connection;	
		}
		catch(Exception $e){
			
			$this->log->logIt("Exception in " . $this->module . " - readmasterConnection - " . $e);
            $this->handleException($e);		
			
		}
	}*/
	public function fetchcancellationamount($tranid,$hotel_code,$category="CANCELLATIONFEES")//Sanjay Waman - 14 Dec 2018 - Category pass as arg for additional amount
	{
		$this->log = new pmslogger($this->module);
		$result=new resultobject();
		try
		{
			$this->log->logIt(get_class($this)."-"."fetchcancellationamount");
			$dao=new dao();
			$strSql="SELECT reason FROM ".dbtable::FDReason." WHERE tranunkid=:tranid AND hotel_code=:hotel_code AND reasoncategory=:reasoncategory";
			//Sanjay Waman - 14 Dec 2018 - Category pass as arg for additional amount - START
			if($category != 'CANCELLATIONFEES')
			{
				$strSql .=" ORDER BY fdreasonunkid DESC LIMIT 1";	
			}
			//Sanjay Waman - END
			$dao->initCommand($strSql);	
			$dao->addParameter(":tranid",$tranid);
			$dao->addParameter(":hotel_code",$hotel_code);
			$dao->addParameter(":reasoncategory",$category);//Sanjay Waman - 14 Dec 2018 - Category set as dinamic for additional amount
			//$this->log->logIt($strSql.">>".$hotel_code." --> Hotel_code ".$tranid." --> Tranid");//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
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
			$this->log->logIt(get_class($this)."-"."fetchcancellationamount"."-".$e);
			$result->resultCode = resultConstant::Error;			
			$result->exception = $e;
			$result->viewName = "errorpage";
			return false;
		}
	}
	
	public function getExclusiveAmountFromInclusiveAmount($inclusiveAmount,$taxUnkIds,$rentalDate,$roomRateUnkId,$callFor="base")
	{        
		try
        {				            
            $objDao = new dao();
            $result = new resultobject();		            
            $objLog = new pmslogger($this->module);
            
            $objLog->logIt("getExclusiveAmountFromInclusiveAmount");
            $objLog->logIt("Tax Inclusive Amount >> ".$inclusiveAmount);
            $objLog->logIt("Tax IDs >> ".$taxUnkIds);
            $objLog->logIt("Rental Date >> ".$rentalDate);
            
            $taxAmount = 0;
            $taxPercentage = 0;                
            $arrTaxIds = [];
			if($taxUnkIds != "")
			{				
                $arrTaxIds = explode(",",$taxUnkIds);
			}						
			
			if(count($arrTaxIds) > 0 )
			{				                                
                $strSql = "SELECT rackrate,extraadultrate,extrachildrate FROM cfroomrate_setting WHERE hotel_code = :hotel_code AND roomrateunkid = :roomrateunkid LIMIT 1";
                $objDao->initCommand($strSql);
                $objDao->addParameter(":hotel_code",$this->hotelcode);
                $objDao->addParameter(":roomrateunkid",$roomRateUnkId);
                
                $result->resultValue['record'] = [];
                $result->resultValue['record'] = $objDao->executeRow();
                
                $arrRateInfo = [];
                $arrRateInfo = $result->resultValue['record'];
                
                $rackRate = 0;                
                switch($callFor)
                {
                    case "base":
                        $rackRate = $arrRateInfo['rackrate'];
                        break;
                    
                    case "extadult":
                        $rackRate = $arrRateInfo['extraadultrate'];
                        break;
                    
                    case "extchild":
                        $rackRate = $arrRateInfo['extrachildrate'];
                        break;
                }
                
                $arrTaxDetails = [];
                foreach($arrTaxIds as $index=>$taxId)
                {
                    $strSql = "SELECT TaxDetail.*,Tax.*,IFNULL(TaxDetail.postingrule,'') as paxpostingrule ".
                            " FROM ".dbtable::TaxDetail." as TaxDetail,".dbtable::Tax." as Tax WHERE ".
                            " TaxDetail.taxunkid = Tax.taxunkid AND TaxDetail.taxunkid =:taxunkid". 
                            " AND DATE_FORMAT(taxdate,'%Y-%m-%d') <= DATE_FORMAT(:chargedate,'%Y-%m-%d') AND Tax.isactive=1 ".
                            " ORDER BY entrydatetime DESC LIMIT 0,1";
                        
                    $objDao->initCommand($strSql);					
					$objDao->addParameter(":taxunkid",$taxId);
					$objDao->addParameter(":chargedate",$rentalDate);
                    
                    $result->resultCode = resultConstant::Success;
                    
                    $result->resultValue['record'] = null;
                    $result->resultValue['record'] = $objDao->executeRow();
                    
                    if($result->resultValue['record'] != "")
                    {
                        if(isset($result->resultValue['record']['postingtype']) && $result->resultValue['record']['postingtype'] != "")
                        {
                            if(!isset($arrTaxDetails[$result->resultValue['record']['postingtype']]))
                            {
                                $arrTaxDetails[$result->resultValue['record']['postingtype']] = [];
                            }
                            array_push($arrTaxDetails[$result->resultValue['record']['postingtype']],$result->resultValue['record']);    
                        }
                    }                                                            
                }                
                $objLog->logIt("Tax Details >> ".json_encode($arrTaxDetails));
                
                $arrTaxApplyAfter = [];
                foreach($arrTaxDetails as $postingType=>$arrTaxDetail)
                {                                                            
                    if($postingType == "FLATPERCENTAGE")
                    {
                        foreach($arrTaxDetails[$postingType] as $index=>$arrTaxConfig)
                        {                                                        
                            $amount = $arrTaxConfig['amount'];
                            
                            $taxApplyAfter = 0;
                            if($arrTaxConfig["taxapplyafter"] != "")
							{								
                                foreach($arrTaxApplyAfter as $taxKey=>$taxVal)
								{
                                    if(preg_match(",".$taxKey.",",",".$arrTaxConfig["taxapplyafter"].","))
                                    {
                                        $taxApplyAfter += ((((100 + $taxVal) * $amount) / 100) - $amount);
                                        $objLog->logIt($postingType." >> Tax Apply After >> ".$taxApplyAfter);
                                    }
								}                                
							}
                            
                            if($arrTaxConfig['applyonrackrate'] == 1)
                            {
                                $taxAmount += (($rackRate + $taxApplyAfter) * ($amount / 100));
                                $objLog->logIt($postingType." >> Tax Amount >> ".$taxAmount);
                            }
                            else
                            {
                                $taxPercentage += ($amount + $taxApplyAfter);										
                                $arrTaxApplyAfter[$arrTaxConfig['taxunkid']] = $taxPercentage;
                                $objLog->logIt($postingType." >> Tax Percentage >> ".$taxPercentage);
                            }
                        }                        
                    }
                    else if($postingType == "FLATAMOUNT")
                    {
                        foreach($arrTaxDetails[$postingType] as $index=>$arrTaxConfig)
                        {
                            $amount = $arrTaxConfig['amount'];                            
                            if($arrTaxConfig['paxpostingrule'] != "")
                            {
                                $amount = 0;								
                            }                            
							$taxAmount += $amount;
                            $objLog->logIt($postingType." >> Tax Amount >> ".$taxAmount);
                        }
                    }
                    else if($postingType == "SLAB")
                    {                                                
                        $arrSlabTaxDetails = [];
                        $arrSlabTaxDetails = (isset($arrTaxDetails[$postingType])) ? $arrTaxDetails[$postingType] : array();
                        
                        $arrSlabLen = [];                        
                        foreach($arrSlabTaxDetails as $index=>$arrTaxConfig)
                        {
                            $amount = $arrTaxConfig['amount'];                            
                            if($arrTaxConfig['applyonrackrate'] == 1)
                            {
                                $objLog->logIt($postingType." >> Apply On Rack Rate >> Yes");
                                
                                $taxApplyAfter = 0;
                                if($arrTaxConfig["taxapplyafter"] != "")
                                {
                                    foreach($arrTaxApplyAfter as $taxKey=>$taxVal)
                                    {
                                        if(preg_match(",".$taxKey.",",",".$arrTaxApplyAfter["taxapplyafter"].","))
                                        {
                                            $taxApplyAfter += ((((100 + $taxVal) * $amount) / 100) - $amount);
                                            $objLog->logIt($postingType." >> Tax Apply After >> ".$taxApplyAfter);
                                        }
                                    }
                                }
                            
                                $arrSlabs = explode(",",$arrTaxConfig['slab']);                                
                                foreach($arrSlabs as $slab)
                                {
                                    $arrSlabConfig = explode("-",$slab);
                                    
                                    $slabStart = $arrSlabConfig[0];
                                    $slabEnd = $arrSlabConfig[1];
                                    $slabPer = $arrSlabConfig[2];                                    
                                    $objLog->logIt($postingType." >> Slab Config >> ".$slabStart."-".$slabEnd."-".$slabPer);
                                    
                                    if($inclusiveAmount >= $slabStart && $inclusiveAmount <= $slabEnd)
                                    {
                                        $taxAmount += (($rackRate + $taxApplyAfter) * $slabPer / 100);
                                        $objLog->logIt($postingType." >> Tax Amount >> ".$taxAmount);
                                    }
                                }                                
                            }
                            else
                            {
                                $arrSlabConfig = [];
                                $arrSlabConfig = explode(",",$arrTaxConfig['slab']);
                                                                
                                if(count($arrSlabConfig) > 0)
                                {                                
                                    $arrSlabTaxDetails[$index]['slabs'] = $arrSlabConfig;
                                    array_push($arrSlabLen,count($arrSlabConfig));                                
                                }
                            }
                        }
                        
                        // Find maximum slab length
                        $maxSlabLen = 0;
                        $maxSlabLen = (count($arrSlabLen) > 0) ? max($arrSlabLen) : 0;
                        
                        $objLog->logIt($postingType." >> Slab Tax Details >> ".json_encode($arrSlabTaxDetails));
                        $objLog->logIt($postingType." >> Maximum Slab Length >> ".$maxSlabLen);
                        
                        if($maxSlabLen > 0)
                        {
                            for($i=($maxSlabLen-1);$i>=0;$i--)                        
                            {                                                        
                                $totalPercentage = 0;
                                for($j=0;$j<count($arrSlabTaxDetails);$j++)                        
                                {                                
                                    if(isset($arrSlabTaxDetails[$j]['slabs'][$i]))
                                    {
                                        $arrSlabConfig = explode("-",$arrSlabTaxDetails[$j]['slabs'][$i]);
                                        $totalPercentage += (count($arrSlabConfig) > 0) ? $arrSlabConfig[2] : 0;   
                                    }
                                    else
                                    {
                                        $totalPercentage += 0;
                                    }                                 
                                }
                                $objLog->logIt($postingType." >> Total Percentage >> ".$totalPercentage);
                                
                                $amountAfterReverse = 0;
                                $amountAfterReverse =  $inclusiveAmount * 100 / ($totalPercentage + 100);
                                $objLog->logIt($postingType." >> Amount After Reverse >> ".$amountAfterReverse);
                                
                                $finalPercentage = 0;                            
                                for($j=0;$j<count($arrSlabTaxDetails);$j++)                        
                                {                                
                                    if(isset($arrSlabTaxDetails[$j]['slabs'][$i]))
                                    {                                    
                                        $arrSlabConfig = explode("-",$arrSlabTaxDetails[$j]['slabs'][$i]);
                                        
                                        $slabStart = $arrSlabConfig[0];
                                        $slabEnd = $arrSlabConfig[1];
                                        $slabPer = $arrSlabConfig[2]; 
                                        
                                        if($amountAfterReverse >= $slabStart && $amountAfterReverse <= $slabEnd)
                                        {                                        
                                            $amount = $arrSlabTaxDetails[$j]['amount'];
                                            $taxApplyAfter = 0;
                                            if($arrSlabTaxDetails[$j]["taxapplyafter"] != "")
                                            {
                                                foreach($arrTaxApplyAfter as $taxKey=>$taxVal)
                                                {
                                                    if(preg_match(",".$taxKey.",",",".$arrSlabTaxDetails[$j]["taxapplyafter"].","))
                                                    {
                                                        $taxApplyAfter += ((((100 + $taxVal) * $slabPer) / 100) - $slabPer);
                                                    }
                                                }
                                            }                                        
                                    
                                            $finalPercentage += ($slabPer + $taxApplyAfter);                                        
                                            $arrTaxApplyAfter[$arrSlabTaxDetails[$j]['taxunkid']] = $finalPercentage;
                                        }
                                    }                                
                                }
                                
                                // Once we got final tax then do not process further
                                if($finalPercentage > 0)
                                {
                                    $taxPercentage += $finalPercentage;
                                    $objLog->logIt($postingType." >> Final Tax Percentage >> ".$finalPercentage);
                                    break;
                                }
                            }    
                        }                        
                    }                    
                }
			}
			else
            {
				return $inclusiveAmount;
			}

			$objLog->logIt("Tax Percentage >> ".$taxPercentage);
            $objLog->logIt("Tax Amount >> ".$taxAmount);            
                        
            $exclusiveAmount = 0;
			$exclusiveAmount = $inclusiveAmount - $taxAmount;
			$exclusiveAmount =  $exclusiveAmount * 100 / ($taxPercentage + 100);
            
            $objLog->logIt("Tax Exclusive Amount >> ".$exclusiveAmount);
                        
            return $exclusiveAmount;			
		}
		catch(Exception $e)
		{
			$objLog->logIt(" getTaxRackRate-".$e);
			$result->resultCode = resultConstant::Error;
			$result->exception = $e;
			$result->viewName = "errorpage";
		}
		return $result;	
	}

	public function getpromotiondetail($hotelcode,$tranid,$promotionid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt("Hotel_" . $hotelcode . " - " . get_class($this) . "-" . "getpromotiondetail");			
	    
            $sqlStr = "SELECT * FROM cfpromotions 
					   WHERE promotionunkid = :promotionid AND hotel_code = :hotel_code";
				
			//$this->log->logIt("getpromotiondetail >>".$sqlStr);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			
            $dao = new dao();
            $dao->initCommand($sqlStr);
            $dao->addParameter(":hotel_code", $hotelcode);
			$dao->addParameter(":promotionid", $promotionid);			
			$result = $dao->executeRow();
			//$this->log->logIt("Result".print_r($result,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $hotelcode . " - Exception in " . $this->module . " - getpromotiondetail - " . $e);
            $this->handleException($e);
        }
        return $result;
	}
	
	//Dharti 11-06-2018 START
	public function getpmsid($hotel_code,$voucher,$tran_id = '')
	{
		try
		{
            $this->log->logIt("Hotel_" . $hotel_code . " - " . get_class($this) . "-" . "getpmsid");		
			$commonfunc= new commonfunction();			
			$commonfunc->module = $this->module;
			$pms_id = '';
			if(isset($tran_id) && count($tran_id) >= 0)
			{
				$check_booking = $commonfunc->isExistBookingDetail($hotel_code,$tran_id);
				//$this->log->logIt("Check booking >>".print_r($check_booking,TRUE));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
				if($check_booking->resultValue['total'] > 0)
				{
					$pms_id = $check_booking->resultValue['list'][0]['pmsbookingid'];
					$subpms_id = $check_booking->resultValue['list'][0]['subpmsbookingid'];
					//$this->log->logIt("Pms booking detail>>".print_r($pms_id,TRUE));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
					
					if(isset($pms_id) && $pms_id != '')
					{
						if(isset($subpms_id) && $subpms_id != '')
						{
							$pms_id = $pms_id."-".$subpms_id;
						}
						else
						{
							$pms_id = $pms_id;
						}
					}
				}
				
			}
			
            $result = $pms_id;
			
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $hotel_code . " - Exception in  getpmsid - " . $e);            
        }
        return $result;
	}

	public function getcreditcardcode($check_cc_code)
	{
		try
		{
            $this->log->logIt("Getcreditcardcode Called");		
			$cc_code = '';
			if($check_cc_code == "Mastercard")
				$cc_code = "MC";
			elseif($check_cc_code == "Visa")
				$cc_code = "VA";
			elseif($check_cc_code == "Maestro")
				$cc_code = "MA";
			elseif($check_cc_code == "DISCOVERCARD")
				$cc_code = "DS";
			elseif($check_cc_code == "AMERICANEXPRESS")
				$cc_code = "AX";
			elseif($check_cc_code == "DINERSCLUB")
				$cc_code = "DN";
			elseif($check_cc_code == "Diners")
				$cc_code = "DC";
			elseif($check_cc_code == "AMEX")
				$cc_code = "AX";
			elseif($check_cc_code == "CUP debit")
				$cc_code = "CD";
			elseif($check_cc_code == "CUP")
				$cc_code = "CU";
			elseif($check_cc_code == "JCB")
				$cc_code = "JC";
			elseif($check_cc_code == "Mastercard debit")
				$cc_code = "MD";
			elseif($check_cc_code == "Visa debit")
				$cc_code = "VD";
			else
				$cc_code = "";
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $hotel_code . " - Exception in  Getcreditcardcode - " . $e);            
        }
        return $cc_code;
	}
	
	public function getpmsratemealdetail($ratetypeid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getpmsratemealdetail");	
			//$connection = $this->readConnection();
			$dao = new dao();									
			$strSql = "select pmsratemappingunkid, pmsmealplan from pmsratetypemapping where hotel_code =:hotel_code AND ratemappingunkid =:ratemappingunkid";
			$dao->initCommand($strSql);														
			$dao->addParameter(":ratemappingunkid", $ratetypeid);										
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$ratetypeid = $dao->executeQuery();
			//$this->log->logIt('sql >>'.$strSql);														
			//$this->log->logIt('Rate Detail >> '.print_r($ratetypeid,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$pmsratetypeid = isset ($ratetypeid[0]['pmsratemappingunkid']) ? (string)$ratetypeid[0]['pmsratemappingunkid'] : ""; 
			$pmsmealplanid = isset($ratetypeid[0]['pmsmealplan']) ? (string)$ratetypeid[0]['pmsmealplan'] : ""; 
			$pmsratemealdeatil = $pmsratetypeid."|".$pmsmealplanid;
			$this->log->logIt('Rate Detail >> '.$pmsratemealdeatil);			
			return $pmsratemealdeatil;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getpmsratemealdetail - " . $e);
            $this->handleException($e);			
		}
	}
	
	public function getchildage($tranid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getchildage");	
		
			$dao = new dao();									
			$strSql = " SELECT FRI.tranunkid,FRI.childage
						FROM ".dbtable::FDRentalInfo." AS FRI
						INNER JOIN ".dbtable::FDTranInfo." as FTI ON FTI.tranunkid = FRI.tranunkid  
						WHERE FRI.hotel_code=:hotel_code and FRI.tranunkid=:tranunkid ";
			$dao->initCommand($strSql);														
			$dao->addParameter(":tranunkid", $tranid);										
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$childage = $dao->executeRow();															
			//$this->log->logIt('Rate Detail >> '.print_r($childage,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			
			return $childage['childage'];
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getchildage - " . $e);
            $this->handleException($e);			
		}
	}	
	//Dharti savaliya 2018-07-18  -START
	public function getrateplanid($roomtypeunkid,$ratetypeunkid)
    {
        $this->log = new pmslogger($this->module);
        try
        {
            $this->log->logIt(get_class($this)."-"."getrateplanid - ".$roomtypeunkid."|".$ratetypeunkid."|".$this->hotelcode);  
            
            $dao = new dao();
            $strSql = "SELECT roomrateunkid FROM cfroomrate_setting WHERE ratetypeunkid=:ratetypeunkid AND roomtypeunkid=:roomtypeunkid AND hotel_code=:hotel_code";            
            $dao->initCommand($strSql);     
            $dao->addParameter(":roomtypeunkid",$roomtypeunkid);    
            $dao->addParameter(":ratetypeunkid",$ratetypeunkid);    
            $dao->addParameter(":hotel_code", $this->hotelcode);    
            
            //$this->log->logIt($strSql);   
            $result->resultValue['record'] = $dao->executeRow();
            $row = (array)$result->resultValue['record'];           
            $this->log->logIt("roomrateunkid in ".$row['roomrateunkid']);
            return $row['roomrateunkid'];
        }
        catch(Exception $e)
        {
            $this->log->logIt(get_class($this)."-"."getrateplanid"."-".$e); 
            return "-1";
        }
    }
	//Dharti savaliya 2018-07-18  -END
	public function sendBookingNotPostNotificationpms($pmsname,$hotelcode,$error)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt("sendBookingNotPostNotificationpms called");  
			$subject=" ";
			$to=" ";
			$body=" ";
			$headers=" ";	
			
			//$subject = $pmsname;			
			$subject.='Error Occured in Third Party PMS >> '.$pmsname;

			if($hotelcode!='')
				$subject.=" For Hotel ".$hotelcode;		

			//$to  = 'sushma.rana@ezeetechnosys.com, sanjay.waman@ezeetechnosys.com, dharti.savaliya@ezeetechnosys.com';	
			$to  = 'cmalert@ezeetechnosys.com';			
			$body =  '<html><head><meta http-equiv="content-type" content="text/html; charset=windows-1252"></head><body>';
			$body .= '<p style="font-family: Tahoma,Verdana,Helvetica,sans-serif; font-size: 13px;margin-left:10px;">';
			$body .= 'Dear Centrix Team,<br><br>';
			$body .= 'Due to some technical issue booking not transfer at pms end. Please kindly check. <br><br>';
			$body .= $error;											
			$body .= '</p>';
			
			// To send HTML mail, the Content-type header must be set			
			$headers  = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			// Additional headers									
			$headers .= "From: Sushma Rana <sushma.rana@ezeetechnosys.com>\r\n";
			
			$hostname = gethostname();
			if($hostname == 'ubuntu')
			{
				$this->log->logIt("For Local - Mail is cloed >>");
				$flag = 'flase';
			}
			else
			{
				$flag = mail($to, $subject, $body, $headers);
			}
			//$this->log->logIt("To >>".$to);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("Subjest >>".$subject);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("Body >>".$body);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("Header >>".$headers);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("Mail Flag >>".$flag);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
		}
		catch(Exception $e)
		{
			$this->logIt("sendBookingNotPostNotificationpms",$e."");			
		}	
	}
	
	//Added - Subhash - 08 Aug 2018 - Start
	//Purpose: For getting the get Non Reundabel room status for pms
	public function getNonReundabelroomstatusforpms($tranid,$hotel_code) 
	{
		$result=new resultobject();	
		try
		{
			$this->log->logIt(get_class($this)."-"."getNonReundabelroomstatusforpms");
			$dao=new dao();
					   	   
			$strSql = " SELECT sett.is_prepaid_noncancel_nonrefundabel FROM
					  ".dbtable::RoomRateType." AS sett JOIN
					  ".dbtable::FDRentalInfo." AS fdr ON fdr.roomtypeunkid = sett.roomtypeunkid
					  AND fdr.ratetypeunkid = sett.ratetypeunkid AND fdr.hotel_code = sett.hotel_code
					  WHERE fdr.hotel_code = :hotel_code AND sett.hotel_code = :hotel_code
				      AND fdr.tranunkid = :tranunkid GROUP BY fdr.tranunkid LIMIT 1";

			$dao->initCommand($strSql);					
			$dao->addParameter(":hotel_code",$hotel_code);
			$dao->addParameter(":tranunkid",$tranid);
			$result = $dao->executeRow();
			$result = $result['is_prepaid_noncancel_nonrefundabel'];
		}
		catch(Exception $e)
		{
			$this->log->logIt(get_class($this)."-"."getNonReundabelroomstatusforpms"."-".$e);
			$result->resultCode=resultConstant::Error;
			$result->exception=$e;
			$result->viewName="errorpage";
			return false;	
		}
		return $result;
	}
	//Added - Subhash - 08 Aug 2018 - End

	//Sanjay Waman - 22 Aug 2018 - Start
	//Purpose: PMS name return (ARI update Request Limit remove for Mews PMS)
	public function getPMSname($hotel_code) 
	{
		$result=new resultobject();
		$this->log = new pmslogger($this->module);		
		try
		{
			$this->hotelcode = $hotel_code;
			/*if(isset($this->connectiondependency) && $this->connectiondependency == 0 )
			{
				$connection = $this->readConnection();
			}*/	
			$this->log->logIt(get_class($this)."-"."getPMSname");
			$dao = new dao();			   
			$strSql = "";
			$strSql .= "SELECT keyvalue FROM cfparameter";
			$strSql .= " WHERE hotel_code=:hotel_code ";
			$strSql .= " AND keyname = :keyname";
		
			$dao->initCommand($strSql);					
			$dao->addParameter(":hotel_code",$hotel_code);
			$dao->addParameter(":keyname","ThirdPartyPMS");
			$result = $dao->executeRow();
			$result = isset($result['keyvalue'])?$result['keyvalue'].'':'';
			//$this->log->logIt(get_class($this)."- PMS -".$result);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			
		}
		catch(Exception $e)
		{
			$this->log->logIt(get_class($this)."-"."getPMSname"."-".$e);
			$result->resultCode=resultConstant::Error;
			$result->exception=$e;
			$result->viewName="errorpage";
			return false;	
		}
		return $result;
	}
	//Sanjay Waman - End
	
	//Dharti Savaliya - START - 208-08-30 Puropse:- get rateplan related details from pmsratetypemapping table
	public function getpmsrateplandetail($roomrateid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getpmsrateplandetail");
			$this->log->logIt(get_class($this)."-"."Roomrateid".$roomrateid);
			$dao = new dao();
			
			if(isset($roomrateid) && $roomrateid !='')
			{				
				$strSql = "select pmsratemappingunkid,ratemappingunkid,fullsyncperiod,deltasyncperiod,numberofdays from pmsratetypemapping where hotel_code =:hotel_code and ratemappingunkid IN($roomrateid)";
				$dao->initCommand($strSql);
				 $dao->addParameter(":hotel_code", $this->hotelcode);
				 //$this->log->logIt('STRSQL >> '.$strSql);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
				
				$result->resultValue['list'] = $dao->executeQuery();
				//$this->log->logIt('Result rateplan Detail >> '.print_r($result->resultValue['list'],true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
				$i=0;
				$subscrateplan_arr = array();				
				foreach($result->resultValue['list'] as $pmsrateplandetails)
				{
					$subscriptionids =isset($pmsrateplandetails['subscriptionid']) ?  $pmsrateplandetails['subscriptionid']  : '';
					if($pmsrateplandetails['fullsyncperiod'] !='' && $pmsrateplandetails['deltasyncperiod'] !='' && $pmsrateplandetails['numberofdays'] !='')
					{					
						$subscrateplan_arr[$i] = array("pmsratemappingunkid"		=>	$pmsrateplandetails['pmsratemappingunkid'], 
													   "subscratemappingunkid"		=>	$pmsrateplandetails['ratemappingunkid'],
														"fullsyncperiod"		=>	$pmsrateplandetails['fullsyncperiod'],
														"deltasyncperiod"		=>	$pmsrateplandetails['deltasyncperiod'],
														"numberofdays"		=>	$pmsrateplandetails['numberofdays'],
														"subscriptionid"   =>  $subscriptionids,
													  );
					$i++;
					}
					else
					{
						$this->log->logIt("blank");
					}
				}
				//$this->log->logIt('Get Subscreption rateplan Detail >> '.print_r($subscrateplan_arr,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
				return $subscrateplan_arr;
			}
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getpmsratedetail - " . $e);
            $this->handleException($e);			
		}
	}
	//Dharti Savaliya - END - 2018-08-30

	public function getonlyratedetail($ratetypeid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getratedetail");	
			//$connection = $this->readConnection();
			
			$dao = new dao();
																
			$strSql = "select ratemappingunkid from pmsratetypemapping where hotel_code =:hotel_code AND pmsratemappingunkid =:pmsratetypeid ;";
			$dao->initCommand($strSql);														
			
			$dao->addParameter(":pmsratetypeid", $ratetypeid);
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$ratetypeid = $dao->executeQuery();
			//$this->log->logIt('sql >>'.$strSql);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]														
			//$this->log->logIt('Rate Detail >> '.print_r($ratetypeid,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$ratetypeid = $ratetypeid[0]['ratemappingunkid'];
			$this->log->logIt('Rate Detail >> '.$ratetypeid);
			
			return $ratetypeid;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getratedetail - " . $e);
            $this->handleException($e);			
		}
	}

	public function getpmsroomdetailopera($roomid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getroomdetail");	

			//$this->log->logIt($roomid);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$connection = $this->readConnection();
			$dao = new dao();																	
			$strSql = "select roomtypeunkid,pmsroomtypeid from pmsroomtypemapping where hotel_code =:hotel_code AND pmsroomtypeid IN (".$roomid.") ;";
			//$this->log->logIt($strSql);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$dao->initCommand($strSql);														
			
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$inventoryid = $dao->executeQuery();
			//$this->log->logIt('sql >> '.$strSql);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt('Room detail >>'.print_r($inventoryid,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			
			return $inventoryid;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getpmsroomdetail - " . $e);
            $this->handleException($e);			
		}
	}
	public function timestamp($case)
	{
		switch($case)
		{
			case 1:
				$timestamp=date("Y-m-d").'T'.date("H:i:s");
				break;
			case 2:
				$timestamp=date('Y-m-d').'T'.date('H:i:s').'000Z';
				break;
			case 3:
				$timestamp=date('Y-m-d').'T'.date('H:i:s').'Z';
				break;	
			case 4:
				$timestamp=date("YmdHis").rand();
				break;	
			case 5:
				$timestamp=date('Y-m-d');
				break;	
			case 6:
				$timestamp=gmdate('Y-m-d\TH:i:s',time()).'+00:00';
				break;	
		}
       return $timestamp;
	}
	
	//Sanjay Waman - 19 Oct 2018 - Get Ocupancy from room level -Start
	public function occupenacydetail($roomtypeunkid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
				$objRoomtypeDao = new roomtypedao();
				$objRoomtypeDao->roomtypeunkid	= $roomtypeunkid;
				$room_result = $objRoomtypeDao->getRecord();
				$row	= (array)$room_result->resultValue['record'];
				
				$maxadult= $row['max_adult'];
				$baseadult= $row['base_adult'];
				
				$this->log->logIt("Baseadult >>".$baseadult);
				$this->log->logIt("Maxadult >>".$maxadult);
				return $baseadult."-".$maxadult;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - occupenacydetail - " . $e);
			$this->handleException($e);	
			return false;		
		}
		return true;
	}
	//Sanjay Waman - End
	
	//Dharti Savaliya 05-Nov-2018 - START - Purpose :- ,get audit logs details
	public function getauditlogdetail($tranunkid,$op="")
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getauditlogdetail");
			$dao = new dao();
			$this->log->logIt("Dao inside");														 
			$strSql = "SELECT * FROM fdaudittrail WHERE tranunkid=:tranunkid AND hotel_code=:hotel_code ";
			if(trim($op)!="")
			{
				$strSql .= " And operation=:operation";
			}
			$dao->initCommand($strSql);					
			$dao->addParameter(":hotel_code",$this->hotelcode);
			$dao->addParameter(":tranunkid","$tranunkid");
			if(trim($op)!="")
			{
				$dao->addParameter(":operation",$op);
			}
			$result = $dao->executeQuery();
			$final_result = isset($result) ? $result: "";
			//$this->log->logIt(get_class($this)."- Audit Details >>".print_r($final_result,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getauditlogdetail - " . $e);
			$this->handleException($e);	
			return false;		
		}
		return $final_result;
	}
	//Dharti Savaliya - 05-Nov-2018 - END 

	public function getrateplandetailfromroom($roomid)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getroomdetail");	

			//$this->log->logIt($roomid);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$connection = $this->readConnection();
			$dao = new dao();																	
			$strSql = "select roomtypeunkid,ratetypeunkid, roomrateunkid from cfroomrate_setting where hotel_code =:hotel_code AND roomtypeunkid IN (".$roomid.") ;";
			//$this->log->logIt($strSql);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$dao->initCommand($strSql);														
			
			$dao->addParameter(":hotel_code", $this->hotelcode);
			$inventoryid = $dao->executeQuery();
			//$this->log->logIt('sql >> '.$strSql);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt('Room detail >>'.print_r($inventoryid,true));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			
			return $inventoryid;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getpmsroomdetail - " . $e);
            $this->handleException($e);			
		}
	}

	public function getRateBasedRatePlanList($ratetypeunkid='',$roomtypeunkid='',$roomratetypeid='')//Sanjay Waman - 24 Dec 2018 - Pass Roomid for getting all rate plans
	{
		$this->log = new pmslogger($this->module);
		$result=new resultobject();
		try
		{
			$this->log->logIt(get_class($this)."-"."getRoomBasedRatePlanList");
			$dao=new dao();
			$strSql=" SELECT CONCAT(roomrateunkid,'-',roomtypeunkid,'-',ratetypeunkid) As comboroomrateunkid,rackrate,display_name ,roomtypeunkid FROM ".dbtable::RoomRateType." WHERE hotel_code=:hotel_code";
			if($ratetypeunkid!=''){
				$strSql.=" AND ratetypeunkid = :ratetypeunkid";
			}
			//Sanjay Waman - 24 Dec 2018 - Pass Roomid for getting all rate plans - START 
			if(trim($roomtypeunkid)!=''){
				$strSql.=" AND roomtypeunkid = :roomtypeunkid";
			}
			
			if(trim($roomratetypeid)!='')
			{
				$strSql.=" AND roomrateunkid = :roomrateunkid";
			}
			
			//Sanjay
			//$this->log->logIt(" ------> ".$strSql);
			$dao->initCommand($strSql);
			//Sanjay Waman - 24 Dec 2018 - Checked Hotel code condition - START 
			if(isset($_SESSION['prefix']) && isset($_SESSION[$_SESSION['prefix']]['hotel_code']) && $_SESSION[$_SESSION['prefix']]['hotel_code']!='')
			{
				$this->log->logIt(" ------> ".$_SESSION[$_SESSION['prefix']]['hotel_code']);
				$dao->addParameter(":hotel_code",$_SESSION[$_SESSION['prefix']]['hotel_code']);
			}
			elseif(isset($this->hotelcode))
			{
				$dao->addParameter(":hotel_code",$this->hotelcode);
			}
			//Sanjay Waman - END
			if($ratetypeunkid!=''){
				$dao->addParameter(":ratetypeunkid",$ratetypeunkid);
			}
			//Sanjay Waman - 24 Dec 2018 - Pass Roomid for getting all rate plans - START 
			if(trim($roomtypeunkid)!=''){
				$dao->addParameter(":roomtypeunkid",$roomtypeunkid);
			}
			
			if(trim($roomratetypeid)!='')
			{
				$dao->addParameter(":roomrateunkid",$roomratetypeid);
			}
			
			//Sanjay Waman - END
			$result->resultCode = resultConstant::Success;
			$result->resultValue['list']=$dao->executeQuery();
			//$this->log->logIt("llkk");
			//$this->log->logIt($result->resultValue['list']);
		}
		catch(Exception $e)
		{
			$this->log->logIt(get_class($this)."-"."getRoomBasedRatePlanList"."-".$e);
			$result->resultCode=resultConstant::Error;
			$result->exception=$e;
			$result->viewName="errorpage";
		}
		return $result;
	}
	//Tejaswini - 10 May 2018 - Get RoomBase Rateplan List - End

	public function errormail($pmsname,$hotelcode,$error)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt("Errormail called");  
			
			$subject=" ";
			$to=" ";
			$body=" ";
			$headers=" ";	
			
			//$subject = $pmsname;			
			$subject.='Database connection error Occured in Third Party PMS >> '.$pmsname;

			if($hotelcode!='')
				$subject.=" For Hotel ".$hotelcode;		

			//$to  = 'sushma.rana@ezeetechnosys.com, sanjay.waman@ezeetechnosys.com, dharti.savaliya@ezeetechnosys.com';	
			$to  = 'sushma.rana@ezeetechnosys.com';			
			$body =  '<html><head><meta http-equiv="content-type" content="text/html; charset=windows-1252"></head><body>';
			$body .= '<p style="font-family: Tahoma,Verdana,Helvetica,sans-serif; font-size: 13px;margin-left:10px;">';
			$body .= 'Dear Centrix Team,<br><br>';
			$body .= 'Database connection not working for this PMS. Please check. <br><br>';
			$body .= $error;											
			$body .= '</p>';
			
			// To send HTML mail, the Content-type header must be set			
			$headers  = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			// Additional headers									
			$headers .= "From: Sushma Rana <sushma.rana@ezeetechnosys.com>\r\n";
			
			$hostname = gethostname();
			if($hostname == 'ubuntu')
			{
				$this->log->logIt("For Local - Mail is cloed >>");
				$flag = 'flase';
			}
			else
			{
				$flag = mail($to, $subject, $body, $headers);
			}
			//$this->log->logIt("To >>".$to);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("Subjest >>".$subject);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("Body >>".$body);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("Header >>".$headers);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			//$this->log->logIt("Mail Flag >>".$flag);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
		}
catch(Exception $e)
		{
			$this->logIt("errormail",$e."");			
		}	
	}	

	//Purpose: Checked is interface active?
	public function isAuthorizedUser($hotelcode,$authcode) 
	{
		$log = new pmslogger($this->module);
        try 
		{
			$log->logIt("Hotel_" . $hotelcode . " - " . "isAuthorizedUser");
			$flag = '-1';
			$dao = new dao();
			$saasconfigquery = "SELECT hotel_code,`key`,authkey,isactive FROM syshotelcodekeymapping WHERE (`key`='".$authcode."' OR `authkey`='".$authcode."') AND hotel_code='".$hotelcode."' AND integration='RES' LIMIT 1";
			$dao->initCommand($saasconfigquery);
			$row = $dao->executerow();
			
			if(!empty($row))
			{
				$log->logIt($row["isactive"]."|".$row["hotel_code"]."|".$row["key"]."|".$row["authkey"]."|".$hotelcode."|".$authcode);
				
				if($row["hotel_code"]=='')
				{
					$flag=0;
				}
				else
				{
					if($row["isactive"]=='0')
					{
						$flag=2;				
					}							
					else
					{
						if($row["hotel_code"] == $hotelcode && ($row["key"] == $authcode || $row["authkey"] == $authcode)) 
							$flag = 1;
					}				
				}
		    }
			$log->logIt("Flag Value >>".$flag);
        }catch (Exception $e) 
		{
            $log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isAuthorizedUser - " . $e);
            $this->handleException($e);
        }
        return $flag;
    }


	
	// Optimize query - Static mapping - Get room detail 
	public function getmappingforroom($hotelcode)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$roommappingdetail = $roomapping = array();
			$this->log->logIt(get_class($this)."-"."getmappingforroom");
			$dao = new dao();																	
			$strSql = "select roomtypeunkid as ezeeroom, pmsroomtypeid as trdpmsroom from pmsroomtypemapping where hotel_code =:hotel_code;";
			$dao->initCommand($strSql);	
			$dao->addParameter(":hotel_code", $hotelcode);		
			$roommappingdetail = $dao->executeQuery();			
			foreach($roommappingdetail as $key => $val)
			{
				$roomapping[$val['trdpmsroom']]=$val['ezeeroom'];				
			}
			//$this->log->logIt('Room Mapping detail >>'.print_r($roomapping,true));
			return $roomapping;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getroomdetail - " . $e);
            $this->handleException($e);			
		}
	}
	
	// Optimize query - Static mapping - Get rate detail 
	public function getmappingforrateplan($hotelcode)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$rateplanmappingdetail = $rateplanmapping = array();
			$this->log->logIt(get_class($this)."-"."getmappingforrateplan");
			
			$dao = new dao();																	
			$strSql = "select ratemappingunkid as ezeerateplan, pmsratemappingunkid as trdpmsrateplan from pmsratetypemapping where hotel_code =:hotel_code;";
			$dao->initCommand($strSql);	
			$dao->addParameter(":hotel_code", $hotelcode);
			$rateplanmappingdetail = $dao->executeQuery();			
			foreach($rateplanmappingdetail as $key => $val)
			{
				$rateplanmapping[$val['trdpmsrateplan']]=$val['ezeerateplan'];
			}
			//$this->log->logIt('Room Mapping detail >>'.print_r($roomapping,true));
			return $rateplanmapping;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getroomdetail - " . $e);
            $this->handleException($e);			
		}
	}
	
	// Sushma - Changes for CEN-1606, Visbook conenectivity - start	
	public function getpropertylistbyPMS($pmsid="",$pmshotelcode='',$hotel_code = '')
    {
        $this->log = new pmslogger($this->module);
        $this->log->logIt($this->module."getpropertylistbyPMS");		
		try
		{
            $this->log->logIt("PMS List >>".$pmsid);			
			if($pmsid != '' && $pmsid != NULL)
			{
				$auto_code ='';
				$dao = new dao();
				
				$result = "SELECT * from syspmshotelinfo WHERE pmsdetail='".$pmsid."' ";
				if($pmshotelcode != '' && $pmshotelcode != NULL)
				{
					$result .= "and pmshotelcode='".$pmshotelcode."' ";
				}
				if($hotel_code != '' && $hotel_code != NULL)
				{
					$result .= "and hotel_code='".$hotel_code."' ";
				}
				$result .= ";";
			
				//$this->log->logIt($result);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
				
				$dao->initCommand($result);	
				$row = $dao->executeQuery();
				if(!empty($row))
				{
					foreach($row as $pmswisehoteldetail)
					{
						$hotel_code = (isset($pmswisehoteldetail["hotel_code"])) ? trim($pmswisehoteldetail["hotel_code"]) : '';						
						$otherdetail[$hotel_code]['otherdetail'] = $pmswisehoteldetail;
						$this->log->logIt("Hotel code >>".$hotel_code);
						if($hotel_code != '')
						{
							$result = "select * from syshotelcodekeymapping where hotel_code=".$hotel_code." AND integration = 'RES';";
							
							if($result!='')
							{
							  $dao->initCommand($result);	
							  $row = $dao->executeQuery();
							  $auto_code =(isset($row[0]) && isset($row[0]["key"])) ? trim($row[0]["key"]) : '';
							}
						}				
						$this->log->logIt("Authenication code >>".$auto_code);
						$otherdetail[$hotel_code]['authcode'] = $auto_code;
						//$detail[]= array($hotel_code, $auto_code); 
					}			
					return $otherdetail;
				}
			}
			else
			{
				return false;
			}
        }
        catch (Exception $e) 
		{
			$this->log->logIt("Exception in " . $this->module . " - getsystempropertydetail - " . $e);
			$this->handleException($e);
		}
    }
	
	public function getfuturedatadate($hotel_code)
	{
		$this->log = new pmslogger($this->module);
        $this->log->logIt($this->module."getfuturedatadate");
		try
		{
			$dao = new dao();																	
			$strSql = "select max(month) AS h_month, max(year) AS h_year  from cfroomallocation where hotel_code=:hotel_code ;";
			$dao->initCommand($strSql);				
			$dao->addParameter(":hotel_code", $hotel_code);
			$sycdata = $dao->executeQuery();				
			return $sycdata;
		}
		catch (Exception $e) 
		{
			$this->log->logIt("Exception in " . $this->module . " - getfuturedatadate - " . $e);
			$this->handleException($e);
			return false;
		}
	}
	
	public function getpmsroomdetailfromhotelcode($hotel_code)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getpmsroomdetailfromhotelcode"."-".$hotel_code);
			$inventoryid = array();
			
			$dao = new dao();																	
			$strSql = "select pmsroomtypeid, roomtypeunkid from pmsroomtypemapping where hotel_code =:hotel_code;";
			$dao->initCommand($strSql);	
			$dao->addParameter(":hotel_code", $hotel_code);
			$inventoryid = $dao->executeQuery();			
			//$this->log->logIt('Room detail >>'.print_r($inventoryid,true));
			return $inventoryid;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getpmsroomdetail - " . $e);
            $this->handleException($e);			
		}
	}
	
	public function getpmsratedetailfromhotelcode($hotel_code)
	{
		$this->log = new pmslogger($this->module);
		try
		{
			$this->log->logIt(get_class($this)."-"."getpmsroomdetailfromhotelcode"."-".$hotel_code);
			$sysratetypeid = array();
			
			$dao = new dao();																	
			$strSql = "select ratemappingunkid,pmsratemappingunkid, pmsmealplan from pmsratetypemapping where hotel_code =:hotel_code ;";
			$dao->initCommand($strSql);												
			$dao->addParameter(":hotel_code", $hotel_code);
			$sysratetypeid = $dao->executeQuery();		
			//$this->log->logIt('Room detail >>'.print_r($inventoryid,true));
			return $sysratetypeid;
		}
		catch (Exception $e)
		{
			$this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . " - getpmsroomdetail - " . $e);
            $this->handleException($e);			
		}
	}
	
	public function GetCountryCode($countryname)
	{
		$result = '';
		
		try
		{
			if($countryname != '')
			{
                $strSql = 'SELECT alias,isocode,country_ncode FROM cfcountry WHERE country_name=:country_name'; 
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
	
	// Sushma - End.
	
	//Dharti Savaliya - 02 Jan 2021 - Purpose : Derivedrateplan rate update stop CEN-1875- START
	public function isDerivedRateplan($roomtypeid='',$ratetypeid='')
	{
		$this->log = new pmslogger($this->module);
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "isDerivedRateplan");
            $result = NULL;
            $sqlStr = "SELECT cfrs.derivedfrom,cfrs.roomrateunkid FROM cfroomrate_setting AS cfrs";
			$sqlStr.=" INNER JOIN cfroomtype AS cfrm ON cfrm.roomtypeunkid=cfrs.roomtypeunkid";
			$sqlStr.=" INNER JOIN cfratetype AS cfrt ON cfrt.ratetypeunkid=cfrs.ratetypeunkid";
			$sqlStr.=" WHERE cfrs.hotel_code=:hotel_code AND cfrs.isactive AND cfrs.roomtypeunkid =:roomtypeunkid AND cfrs.ratetypeunkid=:ratetypeunkid limit 1";
            $dao = new dao();
            $dao->initCommand($sqlStr);
			 
            $dao->addParameter(":hotel_code", $this->hotelcode);
			$dao->addParameter(":roomtypeunkid", $roomtypeid);
			$dao->addParameter(":ratetypeunkid", $ratetypeid);
			$result = $dao->executeQuery();
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "isDerivedRateplan - " . $e);
            $this->handleException($e);
        }
        return $result;
    }
	//Dharti Savaliya - END
	//Sanjay - 04 Mar 2021 - Purpose : Reduce ids length CEN-1922- START
	public function decryptidsize($string,$sepkey="Z",$replacewith='0')
	{
		$this->log = new pmslogger($this->module);
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "reduceidsize >".$string." - ".$sepkey);
            $arrayids = explode($sepkey,trim($string));
			if(count($arrayids)>1 && intval($arrayids[0])<20){
				$string = str_repeat($replacewith,intval($arrayids[0])).trim($arrayids[1]);
			}
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "reduceidsize - " . $e);
            //$this->handleException($e);
        }
        return $string;
    }
	public function encryptidsize($string,$sepkey="0",$replacewith='Z')
	{
		$this->log = new pmslogger($this->module);
        try
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "reversereduceidsize >".$string." - ".$sepkey);
            $string = ltrim($string,$this->hotelcode);
			$zrocount = strlen($string)-strlen(ltrim($string,$sepkey));
			$string = $zrocount.$replacewith.ltrim($string,$sepkey);
        }
		catch (Exception $e)
		{
            $this->log->logIt("Hotel_" . $this->hotelcode . " - Exception in " . $this->module . "-" . "reversereduceidsize - " . $e);
            //$this->handleException($e);
        }
        return $string;
    }
	//Sanjay - END

}

?>
