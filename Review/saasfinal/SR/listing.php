<?php

if(!isset($_REQUEST['Iscrs']))
{
	if(isset($_REQUEST['crsaccount']) || isset($_REQUEST['chainaccount']) || isset($_REQUEST['GroupCode']))
		include_once("../common/multiproperty_apiconfig.php");
	else
		include_once("../common/apiconfig.php");
	include_once("../common/gridfunction.php");
	include_once("../database/parameter.php");
	include_once("../common/encdec.php");
	$objlist=new listing();
}

//define('SECURE_SITE', true); //Pinal
defined("SECURE_SITE") or  define('SECURE_SITE', true); //Pinal - 23 January 2020 - Purpose : RES-2388

class listing
{
    //Nency Dalal 1.0.52.57/0.2  -2017-oct-31 START
    //PURPOSE: Transfer livetest , livelocal variables dynemically in API & other few place in code
	//private $servername = 'local'; #livelocal for live #local for local #livetest'
	private $servername;
	//Nency Dalal 1.0.52.57/0.2  -2017-oct-31 END
	
	private $hostname;
	private $log;
	private $module='listing';
	private $hotelcode;
	private $reqType;
	private $detail_array=array();
	private $error=array();
	private $iscallfromchatbot=false;
	
	public function __construct()
	{
		$this->log=new logger('reservation_api_listing');
		$this->log->LogIt("Page Load");               
		
		if(!isset($_REQUEST['Iscrs']))
			$this->_checkparams($_REQUEST);                                    
	}
        
	public function _checkparams($param)
	{
		$this->log->logIt($this->module." - "."_checkparams");
		
		if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
			$_REQUEST=$param;
		
		//Pinal - 4 September 2018 - START
		//Purpose : BE Chat Bot
		if(count($_REQUEST) == 0){
			
			$resobj = @file_get_contents("php://input"); //SecurityReviewed
			$request = json_decode($resobj, true);
			if(!empty($request))
			{
				$_REQUEST=$request;
				$this->iscallfromchatbot=true;
			}
			
			if(count($_REQUEST) == 0){
				$error_response=$this->ShowErrors('EmptyParameter');			
				echo $error_response;
				exit(0);
			}
		}
		//Pinal - 4 September 2018 - END
		
		if($_REQUEST['request_type']!='PaymentList' && (!(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1'))) //Chandrakant - 1.0.47.52 - 19 Oct 2015
		{
			if(!isset($_REQUEST['APIKey']) || $_REQUEST['APIKey']==''){
			   $error_response=$this->ShowErrors('APIKeyMissing');			
			   echo $error_response;
			   exit(0);
			}
		}
		
		//Falguni Rana - 7th Sep 2018, Purpose - BE Chat Bot, added GuestReviews
		if(isset($_REQUEST['request_type']) && $_REQUEST['request_type']!='' && in_array($_REQUEST['request_type'],array('HotelList','RoomList','HotelAmenity','RoomAmenity','ConfiguredDetails','ExtraCharges','InsertBooking','ProcessBooking','RoomTypeList','PaymentList','CalculateExtraCharge','BookingList','VerifyUser','InsertTravelAgent','ReadBooking','CancelBooking','GuestReviews','InsertBooking_hotelchain','ProcessBooking_hotelchain','ConfiguredPGList','ConfiguredPGList_hotelchain')))//kishan Tailor Added CancelBooking purpose:Cancel Booking API, //Chandrakant - 07 March 2020, Purpose - RES-2451 - ConfiguredPGList is added, //Chandrakant - 23 March 2020, Purpose - MHBEAPIEnhPG - ConfiguredPGList_hotelchain is added
		{
			$this->log->logIt($this->module." - "."Load reservation_api_listing");			
			
			//Nishant - 4 Jun 2020 - Start - Purpose: Open API [ABS-5175] - Validation
			if($_REQUEST['request_type']!='PaymentList' && (!(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1'))){ 
		
				//Nishant - 9 Jul 2020 - PaymentList API issue due to this, So. comment - Start
			//if(!(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1')){
				// if(!isset($_REQUEST['APIKey']) || $_REQUEST['APIKey']==''){
				// 	$error_response=$this->ShowErrors('APIKeyMissing');			
				// 	echo $error_response;
				// 	exit(0);
				// }
				//Nishant - 9 Jul 2020 - PaymentList API issue due to this - End
				
				include_once("../../protected/database/openapi.php"); //Nishant - 4 Jun 2020 - OpenAPI Validation function.
				$objOpenApi = new openapi();
				$objOpenApi->requestValidation(isset($_REQUEST['HotelCode'])?$_REQUEST['HotelCode']:'',$_REQUEST['APIKey'],isset($_REQUEST['GroupCode'])?$_REQUEST['GroupCode']:'');
				//Mehul Patel - 17 May 2020 -Start- Purpose: Open API [ABS-5175]
				if(in_array($_REQUEST['request_type'],array('ExtraCharges','RoomTypeList'))){
					if(isset($_REQUEST['publishtoweb']))
					{
						if(strlen($_REQUEST['publishtoweb'])==1 && (strcmp($_REQUEST['publishtoweb'],'0')==0 || strcmp($_REQUEST['publishtoweb'],'1')==0)){
							$this->log->logIt($this->module." - "."publishtoweb filter is valid");
						}else{
							$error_response=$this->ShowErrors('Invalidpublishtoweb'); 					
							echo $error_response;
							exit(0);
						}
					}
				}
				//Mehul Patel - 17 May 2020 -End- Purpose: Open API [ABS-5175]
			}
			//Nishant - 4 Jun 2020 - End - Purpose: Open API [ABS-5175] - Validation

			$basePathStag = dirname(__FILE__);
			global $allserver_IPs,$staginghostarray;
			require $basePathStag."/../../all_servers_ip.php"; // Shweta - to avoid staging server dependency - 2nd feb 2018
			
			//Nency Dalal 1.0.52.57/0.2  -2017-oct-31 START
			//PURPOSE: Transfer livetest , livelocal variables dynemically in API & other few place in code
			$hostname=gethostname();
			
			if($hostname == 'ubuntu') //local server
			{
			   $this->hostname = $_SERVER["HTTP_HOST"];
			   $this->servername = 'local';
			}
			elseif(in_array($hostname, $allserver_IPs['IPlist']['staging'])) //demo server
			{
			   $this->hostname = $staginghostarray[$hostname]; // Shweta - to avoid staging server dependency - 2nd feb 2018
			   $this->servername = 'livetest';
			}
			else
			{
			   $this->hostname = 'live.ipms247.com';
			   $this->servername = 'livelocal';
			}
			
			$server = $this->servername;		
			
			/*if($server == 'local')
				$this->hostname = $_SERVER["HTTP_HOST"];
			else if($server == 'livetest')
			{
				$this->hostname = '107.21.244.5';				
			}
			else
				$this->hostname = 'live.ipms247.com';
			*/
			
			//Nency Dalal 1.0.52.57/0.2  -2017-oct-31 END
			
			$dbname = 'saasconfig';
			
			//connect to replication server
			if($_REQUEST['request_type']!='InsertBooking' && $_REQUEST['request_type']!='ProcessBooking' && $_REQUEST['request_type']!='InsertBooking_hotelchain' && $_REQUEST['request_type']!='ProcessBooking_hotelchain')
			{
				if(!$this->connectDB($server, $dbname))
				{
					$error_response=$this->ShowErrors('DBConnectError');                              
					echo $error_response;    
				}
				else
				{
					$this->log->logIt($this->module." - "."database connected");
					
					if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
					{
						$result = $this->ValidateRequestParameter($_REQUEST['request_type'],$_REQUEST);
						return $result;
						exit(0);
					}
					else
						$this->ValidateRequestParameter($_REQUEST['request_type'],$_REQUEST);
				}
			}
			else
			{
			   //connect to master server
			   if(!$this->connectMasterDB($server, $dbname)) {
				   $error_response=$this->ShowErrors('DBConnectError');                              
								   echo $error_response;    
						   }
			   else
			   {
					$this->log->logIt($this->module." - "."database connected");
					
					if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
					{
						$result = $this->ValidateRequestParameter($_REQUEST['request_type'],$_REQUEST);
						return $result;
						exit(0);
					}
					else
						$this->ValidateRequestParameter($_REQUEST['request_type'],$_REQUEST);
			   }
			}
		}
		else
		{
			$error_response=$this->ShowErrors('BadRequest');  			
                        echo $error_response; 			
		}		 
	}
         
	public function ValidateRequestParameter($reqtype, $requestparams)
	{
		try
		{
			$this->log->logIt(get_class($this) . "-" . "ValidateRequestParameter");           
			switch ($reqtype)
			{
				case 'HotelList'://get hotel list
						$hotel_list=array();
						//Pinal - 1.0.46.51 - 24 August 2015 - START
						//Purpose - Get hotel data by hotel code
						if((!isset($_REQUEST['GroupCode']) && !isset($_REQUEST['HotelCode']))) 
						{
							$error_response=$this->ShowErrors('GroupHotelCodeMissing');
							
							if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
								return $error_response;
							else
								echo $error_response;
								
							exit(0);
						}
						
						if(!(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1') && (isset($_REQUEST['GroupCode']) && $_REQUEST['GroupCode']!='' && isset($_REQUEST['chainaccount']) && $_REQUEST['chainaccount']!='')) //Pinal - 6 February 2019 - Purpose : Multiple property listing API.
						{
							if(!((isset($_REQUEST['Location']) && $_REQUEST['Location']!='') || (isset($_REQUEST['HotelCode']) && $_REQUEST['HotelCode']!='')))
							{
								$error_response=$this->ShowErrors('SearchCriteriaMissing');
								
								if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
									return $error_response;
								else
									echo $error_response;
								exit(0);
							}
						}
						
						if(isset($_REQUEST['GroupCode']) && $_REQUEST['GroupCode']!='')
						{
							$hotel_list=$this->getHotelList($_REQUEST['GroupCode'],'');
						}
						else if(isset($_REQUEST['HotelCode']) && $_REQUEST['HotelCode']!='')
						{
							$hotel_list=$this->getHotelList('',$_REQUEST['HotelCode']);
						}
						else
						{
							$error_response=$this->ShowErrors('GroupHotelCodeMissing');
							
							if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
								return $error_response;
							else
								echo $error_response;
								
							exit(0);
						}
						//Pinal - 1.0.46.51 - 24 August 2015 - END
						if(empty($hotel_list))
						{
							$error_response[]=(array(
										  'Error Details'=>array(
										  "Error_Code" => -1,
										  "Error_Message" => "No Data found.")
									  )
							  );
							
							if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
								return json_encode($error_response);
							else
								echo json_encode($error_response);
								
							exit(0);
						}
						else
						{
							if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
								return json_encode($hotel_list,JSON_UNESCAPED_UNICODE);//php 5> version
							else
								echo json_encode($hotel_list,JSON_UNESCAPED_UNICODE);//php 5> version
						}
					break;
            case 'RoomList'://get room list
						if(!isset($_REQUEST['HotelCode']) && $_REQUEST['HotelCode']!='')//kishan Tailor purpose:To solve issue when hotecode is empty RES-1452
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty');
							
							if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
								return $error_response;
							else
								echo $error_response;
							exit(0);
						}
						
						$this->log->logIt(get_class($this) . "-" . "getRoomList : start ".$_REQUEST['HotelCode'] . " : " .date('H:i:s'));
						
						if(isset($_REQUEST['HotelCode']) && $_REQUEST['HotelCode']!='')//kishan Tailor purpose:To solve issue when hotecode is empty RES-1452                               
							$room_list=$this->getRoomList($_REQUEST['HotelCode']);
							
						if(empty($room_list))
						{
							$error_response[]=(array('Error Details'=>array(
												 "Error_Code" => -1,
												 "Error_Message" => "No Data found.")
										)
									 );
							
							if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
							{
								return json_encode($error_response);
								exit(0);
							}
							else
							{
								echo json_encode($error_response);
								exit(0);
							}
						}
						else
						{
							if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
								return json_encode($room_list,JSON_UNESCAPED_UNICODE);
							else
								echo json_encode($room_list,JSON_UNESCAPED_UNICODE);
						}
					break;
				case 'RoomTypeList'://get rate plan list
						if(!isset($_REQUEST['HotelCode']))
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty'); 					
							echo $error_response;
							exit(0);
						}
						
						$ispublishtoweb=(isset($_REQUEST['publishtoweb']) && $_REQUEST['publishtoweb']==1)?1:0; //Nishit - 12 May 2020 - Purpose: Open API [ABS-5175] // 1 for open and 0 for WEB only
						if(isset($_REQUEST['HotelCode']))                               
							$roomtype_list=$this->getRoomTypeList($_REQUEST['HotelCode'],$ispublishtoweb); //Nishit - 12 May 2020 - Purpose:Added $ispublishtoweb parameter for Open API [ABS-5175]
						if(empty($roomtype_list))
						{
							$error_response[]=(array('Error Details'=>array(
									"Error_Code" => -1,
									"Error_Message" => "No Data found.")
								)
							);
							echo json_encode($error_response);
							exit(0);
						}
						else
							// echo json_encode($room_list,JSON_PRETTY_PRINT);
							echo json_encode($roomtype_list,JSON_UNESCAPED_UNICODE);//php 5> version
							//echo json_encode($room_list);	
               break;		
            case 'HotelAmenity':					
						if(!isset($_REQUEST['HotelCode']))
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty'); 					
							echo $error_response;
							exit(0);
						}
						if(isset($_REQUEST['HotelCode']))                               
							$amenity_list=$this->getHotelAmenityList($_REQUEST['HotelCode']);
							 
						if(empty($amenity_list))
						{
							$error_response[]=(array('Error Details'=>array(
												 "Error_Code" => -1,
												 "Error_Message" => "No Data found.")
										)
								);
							echo json_encode($error_response);
							exit(0);
						}
						else
							echo json_encode($amenity_list,JSON_UNESCAPED_UNICODE);//php 5> version
							//echo ($this->utf8_urldecode(json_encode($amenity_list)));
							//echo json_encode($amenity_list);
					break;				                         
				case 'RoomAmenity':					
						//if(isset($_REQUEST['HotelCode']))
						//    //get hotel list
						//if(isset($_REQUEST['HotelCode']) && isset($_REQUEST['Roomtype_id']))
						//    //get hotel list                                
					break;
				case 'PaymentList':
						$countryname = isset($_REQUEST['country'])?urldecode($_REQUEST['country']):'';
						$payment_list=$this->getPaymentList($countryname);                  
					break;
				case 'ConfiguredDetails': //Pinal - 1.0.46.51 - 13 August 2015 - Purpose - Get combo data like salutation , country
						if(!isset($_REQUEST['HotelCode']))
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty'); 					
							echo $error_response;
							exit(0);
						}
						
						if(isset($_REQUEST['HotelCode']))                               
							$combos_list=$this->getCombosList($_REQUEST['HotelCode']);
						
						if(empty($combos_list))
						{
							$error_response[]=(array('Error Details'=>array(
												 "Error_Code" => -1,
												 "Error_Message" => "No Data found.")
										)
									 );
							echo json_encode($error_response);
							exit(0);
						}
						else
							echo json_encode($combos_list,JSON_UNESCAPED_UNICODE);//php 5> version
									break;
				case 'ExtraCharges': //Pinal - 1.0.46.51 - 13 August 2015 - Purpose - Get Extra Charges List
						if(!isset($_REQUEST['HotelCode']))
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty');
							
							if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
								return $error_response;
							else
								echo $error_response;
								
							exit(0);
						}
						$ispublishtoweb=(isset($_REQUEST['publishtoweb']) && $_REQUEST['publishtoweb']==1)?1:0;//Nishit - 12 May 2020 - Purpose:Open API [ABS-5175] // 1 for open and 0 for WEB only

						if(isset($_REQUEST['HotelCode']))                               
							$extras_list=$this->getExtraChargeList($_REQUEST['HotelCode'],$ispublishtoweb);//Nishit - 12 May 2020 - Purpose:Added $ispublishtoweb parameter for Open API [ABS-5175]
						
						if(empty($extras_list))
						{
							$error_response[]=(array('Error Details'=>array(
												 "Error_Code" => -1,
												 "Error_Message" => "No Data found.")
										)
									 );
							
							if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
								return json_encode($error_response);
							else
								echo json_encode($error_response);
								
							exit(0);
						}
						else
						{
							if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
								return json_encode($extras_list,JSON_UNESCAPED_UNICODE);
							else
								echo json_encode($extras_list,JSON_UNESCAPED_UNICODE);
						}
					break;
				case 'InsertBooking': //Pinal - 1.0.46.51 - 14 August 2015 - Purpose - Insert Transaction in system
						if(!isset($_REQUEST['HotelCode']))
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty'); 					
							echo $error_response;
							exit(0);
						}
						
						if(isset($_REQUEST['HotelCode']))                               
							$transaction=$this->insertTransaction($_REQUEST['HotelCode']);
						
						if(empty($transaction))
						{
							$error_response[]=(array('Error Details'=>array(
												 "Error_Code" => -1,
												 "Error_Message" => "Booking Not Inserted.")
										)
									 );
							echo json_encode($error_response);
							exit(0);
						}
						else
						{
							echo json_encode($transaction,JSON_UNESCAPED_UNICODE);//php 5> version
						}
							//echo ($this->utf8_urldecode(json_encode($amenity_list)));
							//echo json_encode($amenity_list);
									break;
				case 'ProcessBooking': //Pinal - 1.0.46.51 - 17 August 2015 - Purpose - Process Inserted Transaction
						if(!isset($_REQUEST['HotelCode']))
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty'); 					
							echo $error_response;
							exit(0);
						}
						
						if(isset($_REQUEST['HotelCode']))                               
							$process=$this->ProcessTransaction($_REQUEST['HotelCode']);
						
						if(empty($process))
						{
							$error_response[]=(array('Error Details'=>array(
												 "Error_Code" => -1,
												 "Error_Message" => "Booking Process Failure.")
										)
									 );
							echo json_encode($error_response);
							exit(0);
						}
						else
							echo json_encode($process,JSON_UNESCAPED_UNICODE);//php 5> version
							//echo ($this->utf8_urldecode(json_encode($amenity_list)));
							//echo json_encode($amenity_list);
							break;
				case 'CalculateExtraCharge': //Pinal - 1.0.47.52 - 20 October 2015 - Purpose - Calculate Extra Charge
						if(!isset($_REQUEST['HotelCode']))
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty'); 					
							echo $error_response;
							exit(0);
						}
						
						if(isset($_REQUEST['HotelCode']))                               
							$total_charge=$this->getTotalExtraCharge($_REQUEST['HotelCode']);
						
						if(empty($total_charge))
						{
							$error_response[]=(array('Error Details'=>array(
												 "Error_Code" => -1,
												 "Error_Message" => "No Data found.")
										)
									 );
							echo json_encode($error_response);
							exit(0);
						}
						else
							echo json_encode($total_charge,JSON_UNESCAPED_UNICODE);//php 5> version
							//echo ($this->utf8_urldecode(json_encode($amenity_list)));
							//echo json_encode($amenity_list);
									break;
				case 'BookingList': //Pinal - 1.0.50.55 - 16 August 2016 - Purpose - Booking List API
						if(!isset($_REQUEST['HotelCode']) || (isset($_REQUEST['HotelCode']) && $_REQUEST['HotelCode']==''))
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty');
							echo $error_response;
							exit(0);
						}
						
						if(isset($_REQUEST['HotelCode']))                               
							$total_charge=$this->getBookingList();
							
						if(empty($total_charge))
						{
							$error_response[]=(array('Error Details'=>array(
												 "Error_Code" => -1,
												 "Error_Message" => "No Data found.")
										)
									 );
							echo json_encode($error_response);
							exit(0);
						}
						else
							echo json_encode($total_charge,JSON_UNESCAPED_UNICODE);//php 5> version
					break;
				case 'VerifyUser':
						if(!isset($_REQUEST['groupcode']) || (isset($_REQUEST['groupcode']) && $_REQUEST['groupcode']==''))
						{
							$error_response=$this->ShowErrors('GCODEEMPTY');
							echo $error_response;
							exit(0);
						}
						else if(!isset($_REQUEST['username']) || (isset($_REQUEST['username']) && $_REQUEST['username']==''))
						{
							$error_response=$this->ShowErrors('UsernameEmpty');
							echo $error_response;
							exit(0);
						}
						else if(!isset($_REQUEST['password']) || (isset($_REQUEST['password']) && $_REQUEST['password']==''))
						{
							$error_response=$this->ShowErrors('PasswordEmpty');
							echo $error_response;
							exit(0);
						}
						
						if(isset($_REQUEST['groupcode']))                               
							$talist=$this->VerifyUser($_REQUEST['username'],$_REQUEST['password'],$_REQUEST['groupcode']);
							
						if(empty($talist))
						{
							$error_response[]=(array('Error Details'=>array(
										"Error_Code" => -1,
										"Error_Message" => "Booking Process Failure.")
									)
							);
							echo json_encode($error_response);
							exit(0);
						}
						else
							echo json_encode($talist,JSON_UNESCAPED_UNICODE);//php 5> version
					break;
				case 'InsertTravelAgent':
						if(!isset($_REQUEST['HotelCode']) || (isset($_REQUEST['HotelCode']) && $_REQUEST['HotelCode']==''))
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty');
							echo $error_response;
							exit(0);
						}
						
						if(isset($_REQUEST['HotelCode']))
							$total_charge=$this->InsertTravelAgent($_REQUEST);
							
						if(empty($total_charge))
						{
							$error_response[]=(array('Error Details'=>array(
										"Error_Code" => -1,
										"Error_Message" => "Booking Not Inserted.")
									)
							);
							echo json_encode($error_response);
							exit(0);
						}
						else
							echo json_encode($total_charge,JSON_UNESCAPED_UNICODE);//php 5> version
					break;
				case 'ReadBooking':
						 $Viewdata=[];
						 if(!isset($_REQUEST['HotelCode']) || (isset($_REQUEST['HotelCode']) && $_REQUEST['HotelCode']==''))
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty');
							echo $error_response;
							exit(0);
						}
						if(isset($_REQUEST['HotelCode']))
						    $Viewdata=$this->ViewTransaction($_REQUEST);
						echo $Viewdata;	
						break;
				case 'CancelBooking'://Kishan Tailor -1.0.53.61/0.02 -20 Jan purpose:Cancel Reservation API START
						 if(!isset($_REQUEST['HotelCode']) || (isset($_REQUEST['HotelCode']) && $_REQUEST['HotelCode']==''))
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty');
							echo $error_response;
							exit(0);
						}
						if(isset($_REQUEST['HotelCode']))
						    $canceldata=$this->cancelbooking($_REQUEST);

						echo json_encode($canceldata);
						break;	//Kishan Tailor -1.0.53.61/0.02 -20 Jan purpose:Cancel Reservation API END
				//Falguni Rana - 7th Sep 2018, Purpose - BE Chat Bot
				case 'GuestReviews':
						$GuestReviews=array();
						if(!isset($_REQUEST['HotelCode']) || (isset($_REQUEST['HotelCode']) && $_REQUEST['HotelCode']=='')) 
						{
							$error_response=$this->ShowErrors('HotelCodeEmpty'); 				
							echo $error_response;
							exit(0);
						}
						if(isset($_REQUEST['HotelCode']))
							$GuestReviews=$this->getGuestReview($_REQUEST['HotelCode']);
						if(empty($GuestReviews))
						{
							$error_response[]=(array(
										  'Error Details'=>array(
										  "Error_Code" => -1,
										  "Error_Message" => "No Data found.")
									  )
							  );
							echo json_encode($error_response);
							exit(0);
						}
						else
							echo json_encode($GuestReviews,JSON_UNESCAPED_UNICODE);//php 5> version
						break;
				case 'InsertBooking_hotelchain':
					if(!isset($_REQUEST['HotelCode']) && !isset($_REQUEST['GroupCode']))
					{
						$error_response=$this->ShowErrors('HCODGCOD'); 					
						return $error_response;
						exit(0);
					}
					
					if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs'] == '1')
						$Isreturn = '1';
					else
						$Isreturn = '0';
					
					if(isset($_REQUEST['HotelCode']))                               
						$transaction=$this->InsertTransaction_hotelchain($_REQUEST['HotelCode'],'');
					else if(isset($_REQUEST['GroupCode']))
						$transaction=$this->InsertTransaction_hotelchain('',$_REQUEST['GroupCode']);
					
					if(empty($transaction))
					{
						$error_response[]=(array('Error Details'=>array(
											 "Error_Code" => -1,
											 "Error_Message" => "Booking Not Inserted.")
									)
								 );
						
						if($Isreturn == '1')
							return json_encode($error_response);
						else
							echo json_encode($error_response);
						exit(0);
					}
					else
					{
						if($Isreturn == '1')
							return json_encode($transaction,JSON_UNESCAPED_UNICODE);
						else
							echo json_encode($transaction,JSON_UNESCAPED_UNICODE);
						exit(0);
					}
					break;
				case 'ProcessBooking_hotelchain':
					if(!isset($_REQUEST['HotelCode']) && !isset($_REQUEST['GroupCode']))
					{
						$error_response=$this->ShowErrors('HCODGCOD'); 					
						return $error_response;
						exit(0);
					}
					if(!isset($_REQUEST['TransactionData']) && !isset($_REQUEST['crsaccount']))
					{
						$error_response=$this->ShowErrors('ParametersMissing');
						return $error_response;
						exit(0);
					}
					
					if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs'] == '1')
						$Isreturn = '1';
					else
						$Isreturn = '0';
					
					$WebSelectedLanguage = ((isset($_REQUEST['WebSelectedLanguage']) && $_REQUEST['WebSelectedLanguage']!='')?$_REQUEST['WebSelectedLanguage']:'en');
					
					$_REQUEST['crsaccount']=(isset($_REQUEST['GroupCode']) && $_REQUEST['GroupCode']!='')?'1':((isset($_REQUEST['HotelCode']) && $_REQUEST['HotelCode']!='')?'0':''); //Pinal
					
					if(isset($_REQUEST['HotelCode']))                               
						$processdata=$this->ProcessBooking_hotelchain($_REQUEST['TransactionData'],$_REQUEST['HotelCode'],'',$WebSelectedLanguage,$_REQUEST['crsaccount']);
					else if(isset($_REQUEST['GroupCode']))
						$processdata=$this->ProcessBooking_hotelchain($_REQUEST['TransactionData'],'',$_REQUEST['GroupCode'],$WebSelectedLanguage,$_REQUEST['crsaccount']);
						
					if(empty($processdata))
					{
						$error_response[]=(array('Error Details'=>array(
											 "Error_Code" => -1,
											 "Error_Message" => "Booking Not Inserted.")
									)
								 );
						
						if($Isreturn == '1')
							return json_encode($error_response);
						else
							echo json_encode($error_response);
						exit(0);
					}
					else
					{
						if($Isreturn == '1')
							return json_encode($processdata,JSON_UNESCAPED_UNICODE);
						else
							echo json_encode($processdata,JSON_UNESCAPED_UNICODE);
						exit(0);
					}
					break;

				//Chandrakant - 07 March 2020 - START
				//Purpose : RES-2451
				case 'ConfiguredPGList':
					if(!isset($_REQUEST['HotelCode']) || (isset($_REQUEST['HotelCode']) && $_REQUEST['HotelCode']==''))//Mehul Patel, 17 June 2020 Purpose:OpenAPI ABS-5175 
					{
						$error_response=$this->ShowErrors('HotelCodeEmpty'); 					
						echo $error_response;
						exit(0);
					}
					$payment_list=$this->GetBEConfiguredPG($_REQUEST);                  
				break;
				//Chandrakant - 07 March 2020 - END
				//Chandrakant - 23 March 2020 - START
				//Purpose : MHBEAPIEnhPG
				case 'ConfiguredPGList_hotelchain':
					if(!isset($_REQUEST['GroupCode']))
					{
						$error_response=$this->ShowErrors('GroupHotelCodeMissing'); 					
						echo $error_response;
						exit(0);
					}
					$payment_list=$this->GetMHBEConfiguredPG($_REQUEST);                  
				break;
				//Chandrakant - 23 March 2020 - END
				
				default:                       
					break;                    
         }
      }
      catch (Exception $e)
		{
			$this->log->logIt("Exception in " . $this->module . " - setAndValidateRequest - " . $e);
			$this->handleException($e);
		}
	}
		
   public function getPaymentList($country='')
   {
	  try
	  {
		 $this->log->logIt(get_class($this) . "-" . "getPaymentList");
		 $ObjProcessDao=new processdao();
		 
		 if($country!='')
			$result = $ObjProcessDao->getPaymentGatewayList($country);
		 else
			$result = $ObjProcessDao->getPaymentGatewayList();
		 
		 if(empty($result))
		 {
			 $error_response[]=(array('Error Details'=>array(
								"Error_Code" => -1,
								"Error_Message" => "No Data found.")
						 )
				 );
			 echo json_encode($error_response);
			 exit(0);
		 }
		 else
		 {
			echo json_encode($result,JSON_UNESCAPED_UNICODE);
			exit(0);
		 }
	  }
	  catch (Exception $e)
	  {
		 $this->log->logIt("Exception in " . $this->module . " - getPaymentList - " . $e);
		 $this->handleException($e);
	  }
   }
	
	public function getHotelAmenityList($Hotel_Code)
	{
		try{			
			$this->log->logIt(get_class($this) . "-" . "getHotelAmenityList ".$Hotel_Code);
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			$api_key='';
			$isopenAPI=$api_cnt=0;
			$openapi_arr=array();
			$ObjProcessDao=new processdao();
			if(!(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1'))
			{					
				$result=$ObjProcessDao->isMetaSearch($Hotel_Code,$_REQUEST['APIKey']);
				if(is_array($result) && count($result)>0)
				{
					$api_key=$result['key'];
					$isopenAPI=$result['integration']=='OpenAPI'?1:0;
					if($result['openapi_mappingid']!='')
						$openapi_arr = explode(',', $result['openapi_mappingid']);

					if($isopenAPI==1)
					{
						if(!in_array('32', $openapi_arr))
						{
							$error_response=$this->ShowErrors('UNAUTHREQ'); 
							echo $error_response;
							exit(0);
						}
					}
				}
				else{
					$objOpenApi = new openapi();
					//$objOpenApi->log=$this->log;
					$this->log->logIt("getHotelAmenityList: Calling function isAuthorized_DemoUser");
					$demo_res=$objOpenApi->isAuthorized_DemoUser($Hotel_Code,$_REQUEST['APIKey'],'32');
					//$this->log->logIt($demo_res,"Result>>>");
					if($demo_res==$Hotel_Code)
					{
						$api_key=$_REQUEST['APIKey'];
						$isopenAPI=1;
					}
					else
						exit;
				}

				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
				if($isopenAPI == 1 && ($Hotel_Code != 0 || $Hotel_Code != '')){
					$req_array = array(
						"_reqtype" => 'HotelAmenity'
					);
					$this->log->logIt("listing-HotelAmenity : Calling threshold_check");
					$objOpenApi = new openapi();	
					$objOpenApi->threshold_check($Hotel_Code,"listing",$req_array);
				}
				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

				$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
				$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
				if($api_key=='')
				{
					$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
					echo $error_response;
					exit(0);
				}
			}	
			//Nishit - 12 May 2020 - End
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$Hotel_Code,'',$isopenAPI);	//Nishit - 12 May 2020 - Purpose: Added $isopenAPI parameter for Open API [ABS-5175]	
			// $api_cnt=0;
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}
			//Nishit - 12 May 2020 - End
			foreach($resultauth as $authuser)
			{				
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;		
				$ObjProcessDao->newurl = $authuser['newurl'];	
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				
				if($this->iscallfromchatbot && isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1') //Pinal - 4 September 2018 - Purpose : BE Chat Bot
				{
					  $Hotel_Code=$authuser['hotel_code'];
				}
				//Nishit - 12 May 2020 - Start - Purpose: Commented below code for Open API [ABS-5175]
				// else
				// {
				// 	$api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				// 	if($api_key!='')				
				// 		$Hotel_Code=$authuser['hotel_code'];				
				// 	else
				// 		$api_cnt++;
				// }
			}
			
			// if($api_cnt>0)
			// {
			// 	$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
			// 	echo $error_response;
			// 	exit(0);	
			// }
			//Nishit - 12 May 2020 - End		
			if(isset($Hotel_Code) && $Hotel_Code!='')
			{				
				$language = (isset($_REQUEST['language']))?$_REQUEST['language']:'en';
				
				$WebSelectedLanguage=$ObjProcessDao->getreadParameter('WebSelectedLanguage',$Hotel_Code);
				$lang_ava=array();
				if($WebSelectedLanguage!='')
					$lang_ava=explode(",",$WebSelectedLanguage);
				
				if(isset(staticarray::$languagearray[$language]) && staticarray::$languagearray[$language]!='' && in_array($language,$lang_ava))
					$language =$language;
				else
					$language='en';
				$this->log->logIt("language : " .$language);
				//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
				// $type='HOTEL,BOTH';
				if($isopenAPI==1)
					$type='HOTEL,BOTH,ROOM';
				else
					$type='HOTEL,BOTH';
				//Nishit - 12 May 2020 - End
				$list_amenities=$ObjProcessDao->getAmenities($Hotel_Code,$type,$language);
				
				if($this->iscallfromchatbot) //Pinal - 4 September 2018 - Purpose : BE Chat Bot
				{
					$amenities='';
					foreach($list_amenities as $am)
					{
						$amenities.=$am['amenity'].",";
					}
					$amenities=trim($amenities,",");
				}
				else
				{
					foreach($list_amenities as $am)
					{
						$amenities[]=array('amenity'=>($am['amenity'])
								   );					
					}
				}
				//print_r($amenities);
			}
			else
			{
				$error_response=$this->ShowErrors('InvalidHotelCode');				
				echo $error_response;
				exit(0);
			}		
			return $amenities;
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('HotelAmenityListingError'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "getHotelAmenityList - " . $e);
			$this->handleException($e);  		
		}    		
	}
	//Pinal - 13 August 2015 - Purpose - New detail listing - START
	public function getHotelList($GroupCode='',$HotelCode='')
	{
		try
		{
			$this->log->logIt(get_class($this) . "-" . "getHotelList ".$GroupCode);
			global $eZConfig;
			
			$ObjProcessDao=new processdao();
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			// if($GroupCode!='')
			// 	$resultauth=$ObjProcessDao->isAuthorizedUser($GroupCode);
			// else
			// 	$resultauth=$ObjProcessDao->isAuthorizedUser('','',$HotelCode);
			
			//$this->log->logIt(get_class($this) . "-" . " resultauth ".$resultauth);
			
			$list_of_ava_properties=$openapi_arr=array();
			$api_cnt=0;
			$isopenAPI=0;
			$api_key='';
			
			if(isset($_REQUEST['allow_without_api']) && intval($_REQUEST['allow_without_api'])==1)
			{
				$api_key=$_REQUEST['APIKey'];
			}
			else
			{
				if($GroupCode!='')
					$result=$ObjProcessDao->isMetaSearch('',$_REQUEST['APIKey']);
				else
					$result=$ObjProcessDao->isMetaSearch($HotelCode,$_REQUEST['APIKey']);

				if(is_array($result) && count($result)>0)
				{
					$api_key=$result['key'];
					$isopenAPI=$result['integration']=='OpenAPI'?1:0;
					if($result['openapi_mappingid']!='')
						$openapi_arr = explode(',', $result['openapi_mappingid']);
					if($api_key=='')
					{
						$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
						echo $error_response;
						exit(0);
					}
					if($isopenAPI==1)
					{
						if(!in_array('31', $openapi_arr))
						{
							$error_response=$this->ShowErrors('UNAUTHREQ');
							echo $error_response;
							exit(0);
						}
					}
				}
				else{
				// elseif ($result['integration'] == 'OpenAPI'){ //Nishant - 16 Jun 2020 - Demo user check point added
					$objOpenApi = new openapi();
					//$objOpenApi->log=$this->log;
					$this->log->logIt("getHotelList: Calling function isAuthorized_DemoUser");
					$demo_res=$objOpenApi->isAuthorized_DemoUser($HotelCode,$_REQUEST['APIKey'],'31');
					//$this->log->logIt($demo_res,"Result>>>");
					if($demo_res==$HotelCode)
					{
						$api_key=$_REQUEST['APIKey'];
						$isopenAPI=1;
					}
					else
						exit;
				}

				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
				if($isopenAPI == 1 && ($HotelCode != 0 || $HotelCode != '')){
					$req_array = array(
						"_reqtype" => 'HotelList'
					);
					$this->log->logIt("listing-HotelList : Calling threshold_check");
					$objOpenApi = new openapi();	
					$objOpenApi->threshold_check($HotelCode,"listing",$req_array);
				}
				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End
			}
			$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
			$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
			// if($api_key=='')
			// {
			// 	$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
			// 	echo $error_response;
			// 	exit(0);
			// }
			// if($isopenAPI==1)
			// {
			// 	if(!in_array('31', $openapi_arr))
			// 	{
			// 		$error_response=$this->ShowErrors('UNAUTHREQ');
			// 		echo $error_response;
			// 		exit(0);
			// 	}
			// }

			//Chandrakant - 16 December 2020 - START
			//Purpose : RES-2691
			if($this->iscallfromchatbot)
				$ObjProcessDao->iscallfromchatbot=1;
			//Chandrakant - 16 December 2020 - END
			
			if($GroupCode!='')
				$resultauth=$ObjProcessDao->isAuthorizedUser($GroupCode,'','','',$isopenAPI);//Nishit - 12 May 2020 - Purpose: Added $isopenAPI parameter for Open API [ABS-5175]
			else
				$resultauth=$ObjProcessDao->isAuthorizedUser('','',$HotelCode,'',$isopenAPI);//Nishit - 12 May 2020 - Purpose: Added $isopenAPI parameter for Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}
			//Nishit - 12 May 2020 - End

			$serverurl=$eZConfig['urls']['cnfServerUrl'];#Chinmay Gandhi - 1.0.53.61 - HTTPS Migration - HTTPSAutoMigration
			
			if($api_key!='')
			{
			    $this->log->logIt(get_class($this) . "-" . " datbase name : ".$resultauth[0]['databasename']);
				
				$eZ_chkout = (isset($_REQUEST['check_out_date']))?$_REQUEST['check_out_date']:'';
				$eZ_chkin = (isset($_REQUEST['check_in_date']))?$_REQUEST['check_in_date']:'';
				$no_nights = (isset($_REQUEST['num_nights']))?$_REQUEST['num_nights']:'';
							
				$getminrateinv=isset($_REQUEST['getHotelRatesAvailability'])?$_REQUEST['getHotelRatesAvailability']:0; //Pinal - 6 February 2019
				if(isset($resultauth[0]['databasename']) && $resultauth[0]['databasename']!='')
				{				  
					//Pinal - 9 January 2019 - START
					//Purpose : Multiple property listing database and url changes.
					
					//$ObjProcessDao->databasename =  $resultauth[0]['databasename'];
					//
					//$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
					
					$crslocation = (isset($_REQUEST['Location'])?$_REQUEST['Location']:'');//Chinmay Gandhi - 20th Sept 2018 - Hotel Listing [ RES-1452 ]
					
					$lang_code = (isset($_REQUEST['language']))?$_REQUEST['language']:'en'; //Pinal - 27 December 2018 - Purpose : Multiple property listing
					
					$alldbs=array();$offset = '';$limit = '';
					if((isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1') || (isset($_REQUEST['chainaccount']) && $_REQUEST['chainaccount'] == '1'))
					{
						foreach($resultauth as $res)
						{
							array_push($alldbs,$res['databasename']);
						}
						$alldbs=array_unique($alldbs);
					}
					else
					{
						array_push($alldbs,$resultauth[0]['databasename']);
					}
					//Pinal - 9 January 2019 - END
					
					if((isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1' && $_REQUEST['scrollflag'] == '0'))
					{
						foreach($alldbs as $databases)
						{
							$_SESSION[$_SESSION['group_prefix']][$databases]['offset'] = 0;
							$_SESSION[$_SESSION['group_prefix']][$databases]['limit'] = 4;
						}
					}
					
					$list_of_ava_properties=array();$hcount = 0;
					foreach($alldbs as $databases)
					{
						if((isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1' && $_REQUEST['scrollflag'] == '1' && $hcount < 4 && $hcount > 0))
						{
							$_SESSION[$_SESSION['group_prefix']][$databases]['offset'] = 0;
							$_SESSION[$_SESSION['group_prefix']][$databases]['limit'] = 4 - $hcount;
						}
						
						$ObjProcessDao->databasename =  $databases;
						$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018

						if($GroupCode!='')
						{
							$offset_sess='';
							$limit_sess='';
							if((isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1' && isset($_REQUEST['scrollflag']) && isset($_SESSION[$_SESSION['group_prefix']][$databases]['offset'])))
							{
								$offset_sess=(string)$_SESSION[$_SESSION['group_prefix']][$databases]['offset'];
								$limit_sess=$_SESSION[$_SESSION['group_prefix']][$databases]['limit'];
							}
							$result_hoteldetails=$ObjProcessDao->getHotelDetails($_REQUEST['GroupCode'],isset($_REQUEST['HotelCode'])?$_REQUEST['HotelCode']:'',$crslocation,$lang_code,$offset_sess,$limit_sess,$isopenAPI);//Chinmay Gandhi - 20th Sept 2018 - Hotel Listing [ RES-1452 ]//Nishit - 12 May 2020 - Purpose: Added $isopenAPI parameter for Open API [ABS-5175]
						}
						else
						{
							$result_hoteldetails=$ObjProcessDao->getHotelDetails('',$_REQUEST['HotelCode'],$crslocation,$lang_code,'','',$isopenAPI);//Chinmay Gandhi - 20th Sept 2018 - Hotel Listing [ RES-1452 ]//Nishit - 12 May 2020 - Purpose: Added $isopenAPI parameter for Open API [ABS-5175]
						}
						
						if($this->iscallfromchatbot)
						{
							$ObjProcessDao->iscallfromchatbot=1;
							defined("SECURE_SITE") or  define('SECURE_SITE', true); //Pinal - 23 January 2020 - Purpose : RES-2388
						}
						
						
						$single_db_flag = 0;
						foreach($result_hoteldetails as $hoteldetails)
						{
							$this->log->logIt(get_class($this) . "-" . "getHotelList ============".$hoteldetails['Hotel_Name']);
							$imagedata=$ObjProcessDao->getImageList($hoteldetails['Hotel_Code'],imageobjecttype::Hotel);
							
							$hotelimages_arr=array();
							foreach($imagedata as $imgdata)
							{
								if($imgdata['image_path']!='')
								{
									$ObjImageDao=new imagedao();
									$img=$ObjImageDao->getImageFromBucket($imgdata['image_path'],'API'); //Jemin Added "API" for use  https API in image url
									array_push($hotelimages_arr,$img);
								}
							}
							
							$hoteldetails['BookingEngineFolderName']=$hoteldetails['BookingEngineURL']; //Pinal

							//Chandrakant - 22 July 2020 - START
							//Purpose : RES-2549
							$lookuprow=$ObjProcessDao->getLookup('PROPERTYTYPE',$hoteldetails['Property_Type'],$hoteldetails['Hotel_Code'],$lang_code);
							if(isset($lookuprow['lookup']) && $lookuprow['lookup']!='') //Chandrakant - 12 February 2021, Purpose - To fix notice error
								$hoteldetails['Property_Type']=$lookuprow['lookup'];
							//Chandrakant - 22 July 2020 - END
							
							if(!((isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1') || (isset($_REQUEST['chainaccount']) && $_REQUEST['chainaccount'] == '1')))
								$hoteldetails['BookingEngineURL']=$serverurl."book-rooms-".$hoteldetails['BookingEngineURL'];#Chinmay Gandhi - 1.0.53.61 - HTTPS Migration - HTTPSAutoMigration
							
							$hoteldetails['HotelImages']=$hotelimages_arr;
							
							//Currency
							if($GroupCode=='' && !$this->iscallfromchatbot)
							{
								   $rsCurrenyData=$ObjProcessDao->getBaseCurrency($hoteldetails['Hotel_Code']);
								   $hoteldetails['CurrencyCode']=$rsCurrenyData["currency_code"];
							}
							
							//Pinal - 1.0.50.55 - 19 August 2016 - START
							//Purpose - Booking Engine config params
							if(!$this->iscallfromchatbot)
							{
								$BookingEngineConfig=$ObjProcessDao->getreadParameter('BookingEngineConfig',$HotelCode);						
								$_Params=explode("<123>",$BookingEngineConfig);						
								foreach($_Params as $keys)
								{
									$_PluginParam=explode("<:>",$keys);
									if($_PluginParam[0]=='LayoutTheme')
										$hoteldetails['LayoutTheme']=$_PluginParam[1];
									//if($_PluginParam[0]=='LayoutView')
									//	$hoteldetails['LayoutView']=$_PluginParam[1];
								}
							}
							//Pinal - 1.0.50.55 - 19 August 2016 - END
							
							//Pinal - 6 February 2019 - START
							//Purpose : Multiple property listing API.
							if($getminrateinv==1 && ($eZ_chkin!='' && ($eZ_chkout!='' || $no_nights!='')))
							{
								$_REQUEST['check_in_date'] = $eZ_chkin;
								$_REQUEST['check_out_date'] = $eZ_chkout;
								$_REQUEST['num_nights'] = $no_nights;
								$_REQUEST['selectedlanguage'] = $lang_code;
								$_REQUEST['GroupCode'] = $GroupCode;
								$_REQUEST['HotelCode'] = $hoteldetails['Hotel_Code'];
								$_REQUEST['num_rooms'] = '1';
								$_REQUEST['number_adults'] = '1';
								$_REQUEST['number_children'] = '0';
								$_REQUEST['getMinRate'] ='1';
								$_REQUEST['allow_without_api'] ='1';
								
								$result2=$this->getRoomList($hoteldetails['Hotel_Code']);
								
								if(!isset($result2[0]['Error Details']['Error_Code']))
								{
									if(sizeof($result2)>0)
									{
										foreach($result2 as $key_2=>$value_2)
										{
											$hoteldetails[$key_2] = $value_2;
										}
									}
								}
							}
							//Pinal - 6 February 2019 - END
							
							array_push($list_of_ava_properties,$hoteldetails);
							$hcount++;$single_db_flag++;
						}
						
						//if((isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1') || (isset($_REQUEST['chainaccount']) && $_REQUEST['chainaccount'] == '1'))
						//{
						//	//$chk_lst_property = $hcount - $single_db_flag;
						//	//if(($hcount == 4 && $chk_lst_property == 0) || ($hcount != 4 && $chk_lst_property == 0))
						//	//{
						//		$_SESSION[$_SESSION['group_prefix']][$databases]['offset'] = (int)$_SESSION[$_SESSION['group_prefix']][$databases]['offset'] + 4;
						//		$_SESSION[$_SESSION['group_prefix']][$databases]['limit'] = 4;
						//	//}
						//	//else
						//	//{
						//		//$_SESSION[$_SESSION['group_prefix']][$databases]['offset'] = $hcount - $single_db_flag;
						//		//$_SESSION[$_SESSION['group_prefix']][$databases]['limit'] = 4;
						//	//}
						//}
						
						//if((isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1' && $hcount == 4) || (isset($_REQUEST['chainaccount']) && $_REQUEST['chainaccount'] == '1' && $hcount == 4))
						//	break;
					}
				}
			}
			else
			{
				$api_cnt++;
			}
			
			//Commented by Pinal
			//Old one- START
			/*foreach($resultauth as $authuser)
			{				
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;		
				$ObjProcessDao->newurl = $authuser['newurl'];	
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->log->logIt(get_class($this) . "-" . "getHotelList ".$authuser['hotel_code']. " : " .$_REQUEST['APIKey']." : ".$_REQUEST['GroupCode']);
				
				//$api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				$api_key=$ObjProcessDao->isMetaSearch('',$_REQUEST['APIKey']);
				if($api_key!='')
				{
					$list_of_ava_properties[]=array('Hotel_Code'=>$authuser['hotel_code'],
									'Hotel_Name'=>$authuser['hotel_name'],									
								);					
				}
				else
				{
					$api_cnt++;
				}
			}*/
			//Commented by Pinal
			//Old one- END

			if($api_cnt>0)
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				echo $error_response;
				exit(0);	
			}
			else
			{				
				if(!(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1') && (isset($_REQUEST['chainaccount']) && $_REQUEST['chainaccount'] == '1'))
				{
					$Objmultiproperty_masterdao=new multiproperty_masterdao();
					
					$Objmultiproperty_masterdao->switchtochaindb($resultauth[0]['chaindatabasename']);
					
					if(!isset($_SESSION['group_prefix']))
					{
						$_SESSION['group_prefix']='eZBooking_'.$GroupCode;
						$_SESSION[$_SESSION['group_prefix']]=array();
						$_SESSION[$_SESSION['group_prefix']]['groupcode']=$GroupCode;
					}
					$_SESSION[$_SESSION['group_prefix']]['LanguageCode']=$lang_code;
					
					$group_dta=$Objmultiproperty_masterdao->_getGroupDetail($GroupCode);
					
					if(count($group_dta)>0)
					{
						unset($group_dta['crshotelunkid']);
						unset($group_dta['groupcode']);
						unset($group_dta['bookingcontenttmpl']);
						unset($group_dta['grouplogolink']);
						unset($group_dta['propertyvideolink']);
						
						$list_of_ava_properties['GroupDetail']=$group_dta;						
					}
				}
				
				return $list_of_ava_properties;			
			}
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('HotelListingError'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "getHotelList - " . $e);
			$this->handleException($e);  		
		}         
	}
	//Pinal - 13 August 2015 - Purpose - New detail listing - END
	
	public function getRoomTypeList($HotelCode,$ispublishtoweb=0)//Nishit - 12 May 2020 - Purpose:Added $ispublishtoweb parameter for Open API [ABS-5175]
	{
		try{
			$this->log->logIt(get_class($this) . "-" . "getRoomTypeList ".$HotelCode);
			
			$ObjProcessDao=new processdao();
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			$api_key='';
			$isopenAPI=0;
			$openapi_arr=array();
			$result=$ObjProcessDao->isMetaSearch($HotelCode,$_REQUEST['APIKey']);
			if(is_array($result) && count($result)>0)
			{
				$api_key=$result['key'];
				$isopenAPI=$result['integration']=='OpenAPI'?1:0;
				if($result['openapi_mappingid']!='')
					$openapi_arr = explode(',', $result['openapi_mappingid']);

				if($isopenAPI==1)
				{
					if(!in_array('33', $openapi_arr))
					{
						$error_response=$this->ShowErrors('UNAUTHREQ'); 
						echo $error_response;
						exit(0);
					}
				}	
			}
			else{
				$objOpenApi = new openapi();
				//$objOpenApi->log=$this->log;
				$this->log->logIt("getRoomTypeList: Calling function isAuthorized_DemoUser");
				$demo_res=$objOpenApi->isAuthorized_DemoUser($HotelCode,$_REQUEST['APIKey'],'33');
				//$this->log->logIt($demo_res,"Result>>>");
				if($demo_res==$HotelCode)
				{
					$api_key=$_REQUEST['APIKey'];
					$isopenAPI=1;
				}
				else
					exit;
			}

			//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
			if($isopenAPI == 1 && ($HotelCode != 0 || $HotelCode != '')){
				$req_array = array(
					"_reqtype" => 'RoomTypeList'
				);
				$this->log->logIt("listing-RoomTypeList : Calling threshold_check");
				$objOpenApi = new openapi();	
				$objOpenApi->threshold_check($HotelCode,"listing",$req_array);
			}
			//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

			$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
			$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
			if($api_key=='')
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				echo $error_response;
				exit(0);
			}
			else	
				$Hotel_Code=$HotelCode;
			
			//Nishit - 12 May 2020 -End
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$HotelCode,'',$isopenAPI);//Nishit - 12 May 2020 - Purpose: Added $isopenAPI parameter for Open API [ABS-5175]
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}
			//Nishit - 12 May 2020 - End
			$list_of_ava_properties=array();
			$api_cnt=0;
			foreach($resultauth as $authuser)
			{				
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;		
				$ObjProcessDao->newurl = $authuser['newurl'];	
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				//Nishit - 12 May 2020 - Start - Purpose: Commented below code for Open API [ABS-5175]
				// $api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				
				// if($api_key!='')				
				// 	$Hotel_Code=$authuser['hotel_code'];				
				// else
				// 	$api_cnt++;				
			}
			
			// if($api_cnt>0)
			// {
			// 	$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
			// 	echo $error_response;
			// 	exit(0);	
			// }
			//Nishit - 12 May 2020 - End
			$list=$ObjProcessDao->getRoomTypeList($Hotel_Code,'',$isopenAPI,$ispublishtoweb);//Nishit - 12 May 2020 - Purpose:Added $ispublishtoweb parameter for Open API [ABS-5175]
			return $list;
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('getRoomTypeListError'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "getRoomTypeList - " . $e);
			$this->handleException($e);  		
		}         
	}	
	
	
	public function getRoomList($HotelCode)
	{
		try{
			$this->log->logIt(get_class($this) . "-" . "getRoomList ".$HotelCode);
			
			$showlog=false;
			global $eZConfig;
			
			//date format fix at time of request 			
			$ObjProcessDao=new processdao();		
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$HotelCode);
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}
			//Nishit - 12 May 2020 - End
			$list_of_ava_properties=array();
			$api_cnt=0;
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			$api_key='';
			$isopenAPI=0;
			$openapi_arr = array();
			//Nishit - 12 May 2020 - End
			foreach($resultauth as $authuser)
			{
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;		
				$ObjProcessDao->newurl = $authuser['newurl'];	
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				
				if(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1')
				{
					  $Hotel_Code=$authuser['hotel_code'];
				}
				else
				{
				//Nishit - 12 May 2020 - Start - Purpose: Commented below code for Open API [ABS-5175]
				//    $api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				//    if($api_key!='')				
				// 	   $Hotel_Code=$authuser['hotel_code'];				
				//    else
				// 	   $api_cnt++;

					$result=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
					if(is_array($result) && count($result)>0)
					{
						$api_key=$result['key'];
						$isopenAPI=$result['integration']=='OpenAPI'?1:0;
						if($result['openapi_mappingid']!='')
							$openapi_arr = explode(',', $result['openapi_mappingid']);
						$Hotel_Code=$authuser['hotel_code'];

						if($isopenAPI==1)
						{
							if(!in_array('30', $openapi_arr))
							{
								$error_response=$this->ShowErrors('UNAUTHREQ'); 
								echo $error_response;
								exit(0);
							}
						}
					}
					else{
						$objOpenApi = new openapi();
						$Hotel_Code=$authuser['hotel_code'];
						//$objOpenApi->log=$this->log;
						$this->log->logIt("getRoomList: Calling function isAuthorized_DemoUser");
						$demo_res=$objOpenApi->isAuthorized_DemoUser($Hotel_Code,$_REQUEST['APIKey'],'30');
						//$this->log->logIt($demo_res,"Result>>>");
						if($demo_res==$Hotel_Code)
						{
							$api_key=$_REQUEST['APIKey'];
							$isopenAPI=1;
						}
						else
							exit;
					}

					//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
					if($isopenAPI == 1 && ($HotelCode != 0 || $HotelCode != '')){
						$req_array = array(
							"_reqtype" => 'RoomList'
						);
						$this->log->logIt("listing-RoomList : Calling threshold_check");
						$objOpenApi = new openapi();	
						$objOpenApi->threshold_check($HotelCode,"listing",$req_array);
					}
					//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

					$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
					$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
					if($api_key=='')
					{
						$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
						echo $error_response;
						exit(0);
					}
					//Mehul Patel - 30 Jun 2020 - Start - Purpose: Open API [ABS-5175]
					/*if($isopenAPI==1)
					{
						if(!in_array('30', $openapi_arr))
						{
							$error_response=$this->ShowErrors('UNAUTHREQ'); 
							echo $error_response;
							exit(0);
						}
					} */
					//Mehul Patel - 30 Jun 2020 - End - Purpose: Open API [ABS-5175]	
				}
			}
			
			// if($api_cnt>0)
			// {
			// 	$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
			// 	echo $error_response;
			// 	exit(0);	
			// }
		
			//Nishit - 12 May 2020 - End
			$WebClosestAvailability = 0;//Chinmay Gandhi - Closest Availability Tool [ RES-2079 ]
			list($bookingrate_decimalplace,$display_decimalplace) = explode('#',parameter::getDigitsAfterDecimal($HotelCode)); //Falguni Rana - Round Off issue
			if(isset($Hotel_Code) && $Hotel_Code!='')
			{
				# Sheetal Panchal - 1.0.52.60 - 3rd Aug 2017, Purpose - For Non linear rate mode setting - START
				$res_rate =  $ObjProcessDao->getRatemode($Hotel_Code);
				$rate_mode=$res_rate['rate'];
				# Sheetal Panchal - 1.0.52.60 - 3rd Aug 2017, Purpose - For Non linear rate mode setting - END
				
				$Iscrs_HotelList = (isset($_REQUEST['getHotelRatesAvailability']) && ($_REQUEST['getHotelRatesAvailability']=='1'))?'1':'0';//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
				
				$Iscrs_RoomList = (isset($_REQUEST['getDetailedRoomListing']) && ($_REQUEST['getDetailedRoomListing']=='1'))?'1':'0';//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
				
				//Chinmay Gandhi - Set Round off setting - Start
				$isTaxInc = '0';
				if($Iscrs_HotelList == '1')
					$isTaxInc = (isset($_REQUEST['IsTaxInc']))?$_REQUEST['IsTaxInc']:'0';
				//Chinmay Gandhi - Set Round off setting - Start
				
				$number_adult = (isset($_REQUEST['number_adults']))?$_REQUEST['number_adults']:1;	
				$number_child = (isset($_REQUEST['number_children']))?$_REQUEST['number_children']:0;				
				$num_rooms = (isset($_REQUEST['num_rooms']))?$_REQUEST['num_rooms']:1;
				$promotioncode = (isset($_REQUEST['promotion_code']))?$_REQUEST['promotion_code']:'';
				$getminrateonly = (isset($_REQUEST['getMinRate']) && $Iscrs_HotelList==1)?$_REQUEST['getMinRate']:0; //Pinal - 6 February 2019
				$getRate_closest_avali = (isset($_REQUEST['getRate_closest_avali']))?$_REQUEST['getRate_closest_avali']:0;//Chinmay Gandhi - Closest Availability Tool [ RES-2079 ]
				
				if($number_adult<=0 || $number_child<0 || $num_rooms<=0)
				{
					 $error_response=$this->ShowErrors('NegativValues'); 						
					 echo $error_response;
					 exit(0);
				}
				
				//stop sell data for case of availability calender- flora
				$showcoacodstopsell = (isset($_REQUEST['showcoacodstopsell']))?$_REQUEST['showcoacodstopsell']:0;
				
				$no_nights = (isset($_REQUEST['num_nights']))?$_REQUEST['num_nights']:'';
				$property_configuration_info = (isset($_REQUEST['property_configuration_info']))?$_REQUEST['property_configuration_info']:'0';
				$WebClosestAvailability=$ObjProcessDao->getreadParameter('ShowClosestAvailability',$HotelCode);//Chinmay Gandhi - Closest Availability Tool [ RES-2079 ]
				
				//Chinmay Gandhi - 10th Sept 2018 - Start
				//Purpose : Hotel Listing [ RES-1452 ]
				if($Iscrs_HotelList == 1 || $Iscrs_RoomList == 1)
				{
					//$WebSelectedLanguage=(isset($_REQUEST['selectedlanguage']))?$_REQUEST['selectedlanguage']:'en';
					$WebSelectedLanguage='en';
					if(isset($_REQUEST['selectedlanguage']))
						$WebSelectedLanguage=$_REQUEST['selectedlanguage'];
					else if(isset($_REQUEST['language']))
						$WebSelectedLanguage=$_REQUEST['language'];
						
					$property_configuration_info = 1;
				}
				else
				{
					$WebSelectedLanguage=$ObjProcessDao->getreadParameter('WebSelectedLanguage',$HotelCode);
					$property_configuration_info = (isset($_REQUEST['property_configuration_info']))?$_REQUEST['property_configuration_info']:'0';
				}
				
				$eZ_chkin = (isset($_REQUEST['check_in_date']))?$_REQUEST['check_in_date']:'';
				$calformat = 'yy-mm-dd';//(isset($_REQUEST['calformat']))?$_REQUEST['calformat']:'';
				$eZ_chkout = (isset($_REQUEST['check_out_date']))?$_REQUEST['check_out_date']:'';
				$language = (isset($_REQUEST['language']))?$_REQUEST['language']:'en';
				$lang_code = (isset($_REQUEST['language']))?$_REQUEST['language']:'en'; //Pinal - 27 December 2018 - Purpose : Multiple property listing
				
				$lang_ava=array();
				if($WebSelectedLanguage!='')
					$lang_ava=explode(",",$WebSelectedLanguage);
				
				if(isset(staticarray::$languagearray[$language]) && staticarray::$languagearray[$language]!='' && in_array($language,$lang_ava))
					$language ="-".$language."-".staticarray::$languagearray[$language];
				else
					$language='-en-English';
				
				if($showlog==true) $this->log->logIt("language : " .$language);
				
				$metasearch = (isset($_REQUEST['metasearch']))?$_REQUEST['metasearch']:'';
				
				//kishan Tailor 25th sep 2020 purpose:Night Limit & package percentage display RES-2610 START
				if(isset($_REQUEST["nightslimit"]) && $_REQUEST["nightslimit"]==1)
					$number_night_limit=100;
				else
					$number_night_limit=30;
				//kishan Tailor 25th sep 2020 END
				
				if($no_nights > $number_night_limit && !(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1')) //Pinal - 13 June 2017 - Purpose : Do not check for night restriction when called from our system //kishan Tailor 25th sep 2020 purpose:Added $number_night_limit for Night Limit RES-2610
				{
					if($metasearch != 'GHF')//Chinmay - 24th July 2018 - Urgent Fix
					{
						//kishan Tailor 25th sep 2020 purpose:Added $number_night_limit vard for Night Limit RES-2610 START
						if($number_night_limit==30)
							$error_response=$this->ShowErrors('NightsLimitExceeded');
						else
							$error_response=$this->ShowErrors('NightsLimitExceeded100');
						//kishan Tailor 25th sep 2020  END 						
						echo $error_response;
						exit(0);
					}
				}
				
				if($eZ_chkin!='' && $calformat!='' && $calformat=='yy-mm-dd')
				{
					if(isset($eZ_chkout) && $eZ_chkout!='' && isset($no_nights) && $no_nights!='')
					{
						$error_response=$this->ShowErrors('InvalidSearchCriteria'); 						
						echo $error_response;
						exit(0);
					}
					
					list($checkin_year, $checkin_month, $checkin_day) = explode("-", $eZ_chkin);
					
					if(isset($eZ_chkout) && $eZ_chkout!='')
					{
						$number_night = util::DateDiff($eZ_chkin,$eZ_chkout);
						$checkout_date =$eZ_chkout;
					}
					else
					{
						//$checkout_date = date('Y-m-d', mktime(0, 0, 0, $checkin_month, $checkin_day + ($no_nights-1) , $checkin_year));
						$checkout_date = date('Y-m-d', mktime(0, 0, 0, $checkin_month, $checkin_day + ($no_nights) , $checkin_year));
						$number_night = util::DateDiff($eZ_chkin,$checkout_date);
					}				
					
					//if($number_night>60)
					//{
					//	$error_response=$this->ShowErrors('NightsGreater'); 						
					//	echo $error_response;
					//	exit(0);						
					//}
						
					if($number_night>$number_night_limit && !(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1'))//Pinal - 30 June 2016 - kept same restriction of 30 days for check out date same as night //Pinal - 13 June 2017 - Purpose : Do not check for night restriction when called from our system//kishan Tailor 21st sep 2020 purpose:Added $number_night_limit vard for Night Limit RES-2610
					{
						if($metasearch != 'GHF')//Chinmay - 24th July 2018 - Urgent Fix
						{
							//kishan Tailor 21st sep 2020 purpose:Added $number_night_limit vard for Night Limit RES-2610 START
							if($number_night_limit==30)
								$error_response=$this->ShowErrors('NightsLimitExceeded');
							else
								$error_response=$this->ShowErrors('NightsLimitExceeded100');
							//kishan Tailor 21st sep 2020  END
							echo $error_response;
							exit(0);
						}
					}
					
					//Chinmay - 24th July 2018 - Urgent Fix - Start
					if($number_night>$number_night_limit && $metasearch!='GHF')
					{
						if(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']!='1')
						{
							//kishan Tailor 21st sep 2020 purpose:Added $number_night_limit vard for Night Limit RES-2610 START
							if($number_night_limit==30)
								$error_response=$this->ShowErrors('NightsLimitExceeded');
							else
								$error_response=$this->ShowErrors('NightsLimitExceeded100');
							//kishan Tailor  21st Sep 2020 END
							echo $error_response;
							exit(0);
						}
					}
					//Chinmay - 24th July 2018 - Urgent Fix - End
					
					//Chinmay Gandhi - 10th Sept 2018 - Start
					//Purpose : Hotel Listing [ RES-1452 ]
					if($Iscrs_HotelList == 1 || $Iscrs_RoomList == 1)
						$cal_date_format='yy-mm-dd';
					else
						$cal_date_format=$ObjProcessDao->getreadParameter('WebAvailabilityCalDateFormat',$HotelCode); //dd-mm-yy
						
					$WebDefaultCurrency=$ObjProcessDao->getreadParameter('WebDefaultCurrency',$HotelCode);

					$rsCurrenyData=$ObjProcessDao->getBaseCurrency($HotelCode);
					
					if($showlog==true) $this->log->logIt("cal date format : " .$cal_date_format);
					
					$checkin_date=util::Config_Format_Date($eZ_chkin,staticarray::$webcalformat_key[$cal_date_format]);
					$checkout_date=util::Config_Format_Date($checkout_date,staticarray::$webcalformat_key[$cal_date_format]);
				
					if($showlog==true)
					{
						$this->log->logIt($number_adult . " | " .$number_child. " | " .$num_rooms.  " | " .$promotioncode. " | " .$eZ_chkin. " | " .$calformat. " | " .$eZ_chkout. " | " .$no_nights);
						$this->log->logIt("check in : " .$checkin_date);
						$this->log->logIt("check out : " .$checkout_date);
					}
					
					$ch_dt=date('Y-m-d', util::getDateFormatWise($checkin_date, $cal_date_format));
					$chout_dt=date('Y-m-d', util::getDateFormatWise($checkout_date, $cal_date_format));
					
					if($showlog==true)
					{
						$this->log->logIt("check in : " .$ch_dt);
						$this->log->logIt("check out : " .$chout_dt);
					}
					
					if($ch_dt >= $chout_dt)
					{
						$error_response=$this->ShowErrors('CheckDate');							
						echo $error_response;
						exit(0);
					}
					
					$arrDateRange2=util::generateDateRange($ch_dt,$chout_dt);
					
					$RoomRevenueTax=$ObjProcessDao->getreadParameter('RoomRevenueTax',$HotelCode);
					
					if(isset($_REQUEST['showtax']))
						$showtax = $_REQUEST['showtax'];
					else
						$showtax=$RoomRevenueTax;
						
					if($showtax==0)
						$RoomRevenueTax='';
					
					//Tax Defination - start
					
					if($rsCurrenyData['currency_name']!='')
					{
						$exchange_rate1=$rsCurrenyData['exchange_rate1'];
						$exchange_rate2=$rsCurrenyData['exchange_rate2'];
						$digits_after_decimal=$rsCurrenyData['digits_after_decimal'];
					}
					
					$calStartDt=$ch_dt;
					list($checkin_year,$checkin_month,$checkin_day)=explode("-",$ch_dt);
					$calEndDt=date('Y-m-d',mktime(0,0,0,$checkin_month,$checkin_day+($number_night-1),$checkin_year));
					$taxdata=$ObjProcessDao->getTaxDefinitionForSpecificDates($calStartDt,$calEndDt,$RoomRevenueTax,$HotelCode,$exchange_rate1,$exchange_rate2);
				    //kishan Tailor 1.0.53.61/0.02 purpose:For Get TaxName START
					$taxnamearray=array();//kishan Tailor 1.0.53.61/0.02 5 Jan 2017 slove Undefined varible
					$this->log->logIt($taxdata);
					if($taxdata!='')
					{
						foreach($taxdata as $key=>$value)
						{
							for($i=0;$i<count($value);$i++)
							{
								//echo "<pre>";
								//print_r($value);
								$taxname['Taxname_'.$i]=$value[$i]['TaxName'];
								//kishan Tailor purpose:Need Tax details in Room List API START
								$taxname['taxdate_'.$i]=$value[$i]['taxdate'];
								$taxname['exemptafter_'.$i]=$value[$i]['exemptafter'];
								$taxname['postingtype_'.$i]=$value[$i]['postingtype'];
								$taxname['postingrule_'.$i]=$value[$i]['postingrule'];
								$taxname['amount_'.$i]=$value[$i]['amount'];
								$taxname['slab_'.$i]=$value[$i]['slab'];
								$taxname['discounttype_'.$i]=$value[$i]['discounttype'];
								$taxname['entrydatetime_'.$i]=$value[$i]['entrydatetime'];
								$taxname['taxapplyafter_'.$i]=$value[$i]['taxapplyafter'];
								$taxname['applyonrackrate_'.$i]=$value[$i]['applyonrackrate'];
								$taxname['applytaxdate_'.$i]=$value[$i]['applytaxdate'];
								$taxname['exchange_rate1_'.$i]=$value[$i]['exchange_rate1'];
								$taxname['exchange_rate2_'.$i]=$value[$i]['exchange_rate2'];
								//kishan Tailor purpose:Need Tax details in Room List API END
							}
							$taxnamearray['TaxName'][$key]=$taxname;
						}
					}
					else
					{
						$taxnamearray['TaxName']=[];
					}
					//kishan Tailor 1.0.53.61/0.02 purpose:For Get TaxName END
					if($showlog==true)
					{
						$this->log->logIt("cal start : " .$calStartDt);
						$this->log->logIt("cal end : " .$calEndDt);
					}					
					//Tax Defination - End
					
					//Show Summary block
					if(isset($_REQUEST['showsummary']))
						$showsummary = $_REQUEST['showsummary'];
					else
						$showsummary=0;
						
					if($Iscrs_HotelList == 1 || $Iscrs_RoomList == 1)
					{
						$showsummary = 1;
					}
					
					//Selected Roomtype list
					if(isset($_REQUEST['roomtypeunkid']))
						$rmtype_unkid = $_REQUEST['roomtypeunkid'];
					else
						$rmtype_unkid='';
					
					//Selected Rateplan list
					if(isset($_REQUEST['roomrateunkid']))
						$roomrateId_unkid = $_REQUEST['roomrateunkid'];
					else
						$roomrateId_unkid='';
					
					//Selected special list
					if(isset($_REQUEST['specialunkid']))
						$Spl_unkid = $_REQUEST['specialunkid'];
					else
						$Spl_unkid='';
						
					
					$roomarray = array();
					$res_hotel =  $ObjProcessDao->getHotelDetail();
					if($res_hotel['hotel_code']!='')
					{
						if($res_hotel['newurl'] == 0)	
							$hotelId = $res_hotel['hotel_code'];
						else
							$hotelId =$res_hotel['localfolder'];
						
						$hcode=$Hotel_Code;
						
						$shownight='false'; //Pinal
						if($Iscrs_HotelList == 1 || $Iscrs_RoomList == 1)//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
						{
							$LayThm='';
							$LayoutTheme=2;
							$hotel_checkin_date=$ObjProcessDao->getDateAllowfrmBooking($res_hotel['TodaysDate'],0,$res_hotel['TodaysDate']);
							
							//kishan Tailor 22 Jab 2021 purpose:allow to list for past 1 day START
							if(isset($_REQUEST['isbookongoogle']) && $_REQUEST['isbookongoogle']==1)
							{
								$hotel_checkin_date=date('Y-m-d', strtotime('-1 day', strtotime($hotel_checkin_date)));
								$ArrvalDt=$hotel_checkin_date;
							}
							else
								$ArrvalDt=$hotel_checkin_date;
							//kishan Tailor 22 Jab 2021 purpose:allow to list for past 1 day END
							
							$showchild='false';
						
							if($ch_dt < $hotel_checkin_date)
							{
								$error_response=$this->ShowErrors('DateNotvalid');							
								echo $error_response;
								exit(0);
							}
						}
						else
						{
							if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
								$hotel_checkin_date=$ObjProcessDao->getDateAllowfrmBooking($res_hotel['TodaysDate'],0,$res_hotel['TodaysDate']);
							else
								$hotel_checkin_date=$ObjProcessDao->getDateAllowfrmBooking($res_hotel['TodaysDate'],$res_hotel['WebSiteDefaultDaysReservation'],$res_hotel['CutOffTime']);
							
							//kishan Tailor 22 Jab 2021 purpose:allow to list for past 1 day START	
							if(isset($_REQUEST['isbookongoogle']) && $_REQUEST['isbookongoogle']==1)
							{
								$hotel_checkin_date=date('Y-m-d', strtotime('-1 day', strtotime($hotel_checkin_date)));
								$ArrvalDt=$hotel_checkin_date;
							}
							else
								$ArrvalDt=$hotel_checkin_date;
							//kishan Tailor 22 Jab 2021 purpose:allow to list for past 1 day END
							
							$this->log->logIt("hotel_checkin_date : " .$ch_dt . " < " .$hotel_checkin_date);
							if($ch_dt < $hotel_checkin_date)
							{
								$error_response=$this->ShowErrors('DateNotvalid');							
								echo $error_response;
								exit(0);
								//return $roomarray;
							}
							
							//Added flora - for responsive UI - start
							$BookingEngineConfig=$ObjProcessDao->getreadParameter('BookingEngineConfig',$HotelCode);	
							$_Params=explode("<123>",$BookingEngineConfig);
							$showchild='false';
							foreach($_Params as $keys)
							{	
								$_PluginParam=explode("<:>",$keys);	
								if($_PluginParam[0]=='LayoutTheme')
									$LayoutTheme=$_PluginParam[1];
								else if($_PluginParam[0]=='ShowDepart') //Pinal - 1.0.49.54 - 27 April 2016 - Purpose - Departure date or night visiblity setting
								{
									if($_PluginParam[1]=='false')
										$shownight='true';
									else
										$shownight='false';
								}
							}
							$LayThm='';
							if($LayoutTheme==2)
							{
								$LayThm=2;
							}
							//Added flora - for responsive UI - end
						}
						
						$notdefinedstay=(isset($_REQUEST['notdefinedstay']) && $_REQUEST['notdefinedstay']=='true')?'API':'';
						
						$SplGoogleHotelFinder=(isset($_REQUEST['SplGoogleHotelFinder']) && $_REQUEST['SplGoogleHotelFinder']==1)?1:0;
						
						//Vihang - 9 March 2018 : 1.0.54.62 - To divert request to interface server - START
						$agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
						$this->log->logIt('agent:'.$agent);
						
						$agent_array = array('self/tripconnect_v7');   // If multiple agents needs to check add the agent name here.
						
						$serverurl=$eZConfig['urls']['cnfServerUrl'];#Chinmay Gandhi - 1.0.53.61 - HTTPS Migration - HTTPSAutoMigration
						$nearestflag = (isset($_REQUEST['nearestflag'])) ? $_REQUEST['nearestflag'] : '';//Chinmay Gandhi - Nearest Availability Create Issue When Min-Night Configure [ RES-2183 ]
						$roomTypeunkId = (isset($_REQUEST['roomTypeunkId']))?$_REQUEST['roomTypeunkId']:''; //Jemin -14-Nov-2019 - RES-2147 - Availability Calender
						if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
						{
							require_once(eZFile_Path."roomdetails.php");
							$data = array("checkin"=>$checkin_date,
								  "HotelId"=>$hotelId,
								  "nonights"=>$number_night,
								  "calendarDateFormat"=>$cal_date_format,
								  "adults"=>$number_adult,
								  "child" =>$number_child,
								  "rooms" =>$num_rooms,
								  "gridcolumn" => $number_night,
								  "promotion"=>$promotioncode,
								  "modifysearch" => "false",
								  "ArrvalDt" => $ArrvalDt,
								  "selectedLang"=>$language,
								  'LayoutTheme'=>$LayoutTheme,
								  'LayThm'=>$LayThm,
								  'request_TA'=>(isset($_REQUEST['travelagentunkid'])?$_REQUEST['travelagentunkid']:''),
								  'calledfrom'=>$notdefinedstay,
								  'SplGoogleHotelFinder' => $SplGoogleHotelFinder,
								  'InvForAvailCalender' => '',
								  'nearestflag' => $nearestflag,//Chinmay Gandhi - Nearest Availability Create Issue When Min-Night Configure [ RES-2183 ]
								  'Iscrs' => '1',
								 );
							$res = run($data);
							$room_detail = $res;
						}
						else
						{
							$this->log->logIt("Availability Calendar For RoomType =====>".$roomTypeunkId);
							if($agent != '' && in_array($agent,$agent_array)){
								$roomurl = $serverurl."rmdetails/interface";#Chinmay Gandhi - 1.0.53.61 - HTTPS Migration - HTTPSAutoMigration
							}else{
								$roomurl = $serverurl."rmdetails";#Chinmay Gandhi - 1.0.53.61 - HTTPS Migration - HTTPSAutoMigration
							}
							//Vihang - 9 March 2018 : 1.0.54.62 - To divert request to interface server - END
							
							$InvForAvailCalender = (isset($_REQUEST['InvForAvailCalender'])) ? $_REQUEST['InvForAvailCalender'] : '';//Chinmay Gandhi - 29th June 2018 - [ AvaCalenderrates ]
							
							#resgrid request
							//$roomurl = "http://".$this->hostname."/booking/rmdetails";
							$this->log->logIt("=-=-=-=-=>>> RoomURL : ".$roomurl . " || Google Finder : " . $SplGoogleHotelFinder . " || Meta Search : " . $metasearch . " || Hotel Code : " . $Hotel_Code);
							$url = $serverurl."searchdetail.php?HotelId=".$hotelId."&checkin=".$checkin_date."&nonights=".$number_night."&calendarDateFormat=".$cal_date_format."&adults=".$number_adult."&child=".$number_child."&rooms=".$num_rooms;#Chinmay Gandhi - 1.0.53.61 - HTTPS Migration - HTTPSAutoMigration
							$data = array("checkin"=>$checkin_date,
								  "HotelId"=>$hotelId,
								  "nonights"=>$number_night,
								  "calendarDateFormat"=>$cal_date_format,
								  "adults"=>$number_adult,
								  "child" =>$number_child,
								  "rooms" =>$num_rooms,
								  "gridcolumn" => $number_night,
								  "promotion"=>$promotioncode,
								  "modifysearch" => "false",
								  "ArrvalDt" => $ArrvalDt,
								  "selectedLang"=>$language,
								  'LayoutTheme'=>$LayoutTheme,//flora for case of responsive UI
								  'LayThm'=>$LayThm,
								  'request_TA'=>(isset($_REQUEST['travelagentunkid'])?$_REQUEST['travelagentunkid']:''),
								  //"source"=>'test',
								  'calledfrom'=>$notdefinedstay, //Pinal - not to display live room availability discard when called from api since for some cases like availabiliy calender we don't have stay
								  'SplGoogleHotelFinder' => $SplGoogleHotelFinder, //Chandrakant - 2.0.6 - 15 June 2018 - GHFIssue
								  'InvForAvailCalender' => $InvForAvailCalender,//Chinmay Gandhi - 29th June 2018 - [ AvaCalenderrates ]
								  'metasearch' => $metasearch, //Akshay Parihar - 27th March 2019 identify metasearch name on roomdetail file and avoid max night issue for GHA
								  'nearestflag' => $nearestflag,//Chinmay Gandhi - Nearest Availability Create Issue When Min-Night Configure [ RES-2183 ]
								  'roomTypeunkId'=>$roomTypeunkId //Jemin -14-Nov-2019 - RES-2147 - Availability Calender
								 );
							
							$this->log->logIt("HotelId : ".$hotelId." : " .date('H:i:s'));
							
							$ch=curl_init();
							curl_setopt($ch, CURLOPT_URL, $roomurl);
							curl_setopt($ch, CURLOPT_POST, 1);
							curl_setopt($ch, CURLOPT_POSTFIELDS,  $data);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
							 
							$res="";
							$res=curl_exec($ch);
							$curlinfo = curl_getinfo($ch);
							curl_close($ch);
							
							$res = preg_replace('/(\s\s+|\t|\n)/', ' ',$res);
							$exp=explode("var resgrid=",$res);
							
							if(isset($exp[1]) && $exp[1]!='')
								$room_detail = $exp[1];
							else
								$room_detail = '';
						}
						
						if($this->iscallfromchatbot) //Pinal - 23 January 2020 - Purpose : RES-2388
						{
							defined("SECURE_SITE") or  define('SECURE_SITE', true);
						}
						
						if(isset($room_detail) && $room_detail!='')
						{
							$exp=explode("; resgrid",$room_detail,2);
							$res_rec=$exp[0];
							$res = json_decode($res_rec);
							
							$rate_array=array();	
							$availability=0;
							$iIndex=0;
							$rowid=-1;
							$iCnt=0;
							//$this->log->logIt("res_rec :".$res_rec);
							$roomarray = array();
							
							if($Iscrs_HotelList == 1 || $Iscrs_RoomList == 1)//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
								$WebShowRatesAvgNightORWholeStay=1;
							else
								$WebShowRatesAvgNightORWholeStay=$ObjProcessDao->getreadParameter('WebShowRatesAvgNightORWholeStay',$HotelCode);
								
							//kishan Tailor - 1.0.53.61/0.02 - purpose:hotel checkin & check out time SATRT
							if($Iscrs_HotelList == 0)//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
							{
								$chk_in_time=$ObjProcessDao->getreadParameter('CheckInTime',$HotelCode);
								$chk_out_time=$ObjProcessDao->getreadParameter('CheckOutTime',$HotelCode);
							}
							
							$type='HOTEL';
							$list_amenities=$ObjProcessDao->getAmenities($HotelCode,$type,$lang_code);
							$hotelamenities=array();//kishan Tailor 1.0.53.61/0.02 5 Jan 2017 slove Undefined varible
							if($list_amenities!='' && count($list_amenities)>0 && isset($list_amenities))//kishan 5 Jan 2017
							{
								foreach($list_amenities as $am)
								{
									$hotelamenities[]=$am['amenity'];					
								}
							}
							
							//Chinmay Gandhi - 10th Sept 2018 - Start
							//Purpose : Hotel Listing [ RES-1452 ]
							if($Iscrs_HotelList == 1 || $Iscrs_RoomList == 1)
							{
								$bb_viewoptions=0;
								$selectedviewoptions=0;
								$bb_ShowOnlyAvailableRatePlanOrPackage=1;
								$conf_ShowMinNightsMatchedRatePlan=0;
								$FindSlabForGSTIndia=$ObjProcessDao->getreadParameter('FindSlabBeforeDiscount',$Hotel_Code); 
							}
							else
							{
								#Get Property Configuration Settings
								$BookingEngineConfig=$ObjProcessDao->getreadParameter('BookingEngineConfig',$HotelCode);
								$_Params=explode("<123>",$BookingEngineConfig);
								foreach($_Params as $keys)
								{
									$_PluginParam=explode("<:>",$keys);
									if($_PluginParam[0]=='bb_viewoptions')
										$bb_viewoptions=$_PluginParam[1];
									if($_PluginParam[0]=='selectedviewoptions')
										$selectedviewoptions=$_PluginParam[1];
									if($_PluginParam[0]=='bb_ShowOnlyAvailableRatePlanOrPackage')
										$bb_ShowOnlyAvailableRatePlanOrPackage=$_PluginParam[1];// false means show all , true means only available
									if($_PluginParam[0]=='bb_adults')
										$conf_max_adults=$_PluginParam[1];
									if($_PluginParam[0]=='bb_child')
										$conf_max_child=$_PluginParam[1];
									if($_PluginParam[0]=='ShowMinNightsMatchedRatePlan')
									{
										$conf_ShowMinNightsMatchedRatePlan=$_PluginParam[1];
										
										//$this->log->logIt("MAIN conf_ShowMinNightsMatchedRatePlan :   " .$conf_ShowMinNightsMatchedRatePlan);
									//echo "<br>".$_PluginParam[0]." : " .$_PluginParam[1];
									if($conf_ShowMinNightsMatchedRatePlan==true && $conf_ShowMinNightsMatchedRatePlan=='true')
										$conf_ShowMinNightsMatchedRatePlan=1;
									else if($conf_ShowMinNightsMatchedRatePlan==false && $conf_ShowMinNightsMatchedRatePlan=='false')
										$conf_ShowMinNightsMatchedRatePlan=0;
									}
								}
							
								if($property_configuration_info==0)
								{
									$bb_viewoptions=0;
									$selectedviewoptions=0;
									$bb_ShowOnlyAvailableRatePlanOrPackage=0;
									$conf_ShowMinNightsMatchedRatePlan=0;
								}
								else
								{
									if($number_adult>$conf_max_adults){
										$error_response=$this->ShowErrors('MaxAdultLimitReach');									
										echo $error_response;
										//return $roomarray;								    
										exit(0);
									}
									if($number_child>$conf_max_child){
										$error_response=$this->ShowErrors('MaxChildLimitReach');									
										echo $error_response;
										//return $roomarray;
										exit(0);
									}
								}
						   
								if(isset($_REQUEST['show_only_available_rooms']) && $_REQUEST['show_only_available_rooms']==1)
									$bb_ShowOnlyAvailableRatePlanOrPackage=1;
									
								if(isset($_REQUEST['show_matched_minimum_nights_rateplans']) && $_REQUEST['show_matched_minimum_nights_rateplans']==1)
								{
									$conf_ShowMinNightsMatchedRatePlan=1;
								}
								else if(isset($_REQUEST['show_matched_minimum_nights_rateplans']) && $_REQUEST['show_matched_minimum_nights_rateplans']=='2') //Pinal - match exactly with nights
								{
										$conf_ShowMinNightsMatchedRatePlan=3;
								}
								
								if($InvForAvailCalender == true)
									$bb_ShowOnlyAvailableRatePlanOrPackage=0;
									
								$FindSlabForGSTIndia=$ObjProcessDao->getreadParameter('FindSlabBeforeDiscount',$Hotel_Code); //Chandrakant - 1.0.53.61 - 08 Decemeber 2017
							}
							//Chinmay Gandhi - 10th Sept 2018 - End
							
							$packagedeal=array();
							$hiderateplan=array();
							$packagedealids=array(); //Pinal - 10 May 2018
							$ispromoappliedvalid=false; //Pinal
							$packages_as_ratetype=array(); //Pinal - 29 March 2019
							if(isset($res[0]) && sizeof($res[0]) > 0)
							{
								$ApplyPackageOnExtras = parameter::readParameter(parametername::ApplyPackageOnExtras,$Hotel_Code); //Priyanka Maurya - 02 March 2020 - Purpose : RES-1825
								foreach($res[0] as $rmdetail)
								{
									$min_nights = $max_nights = 1;
									$minimum_nights_arr=array();
									$maximum_nights_arr=array();//Chinmay Gandhi - Add maximum night for Rate plan
									//kishan Tailor 30th Oct 2020 purpose:need of Stopesell,COA and COD Data START
									$stopesell_array=array();
									$coa_array=array();
									$cod_array=array();
									//kishan Tailor 30th Oct 2020 END
									
									$stopsell = $inv = $dtselection = 1;//Chinmay Gandhi - Add maximum night for Rate plan
									$discount = $rate = $cod = $coa = 0;
									$selenddt=$range='';
									$inventory=array();
									
									//package promotion variable- start
									$_loopCnt=0;
									$_startDay=1;
									$PayForStart=1;
									$StayForStart=1;
									$BeforeDiscountRate=array();
									$BeforeDiscountRate_Package=array(); //Pinal
									$BeforeDiscountRate_Package_bd=array();//Chinmay Gandhi - 6th April 2019 - Hotel Listing
									$BaseRate=array();
									$rateGST=array(); //Chandrakant - 1.0.53.61 - 08 December 2017
									$ExtraAdultRate=array();
									$ExtraChildRate = array();
									
									$WithoutTax_BaseRate=array();
									$WithoutTax_GSTRate=array(); //Chandrakant - 1.0.53.61 - 21 December 2017
									$WithoutTax_ExtraAdultRate=array();
									$WithoutTax_ExtraChildRate = array();
									$WithoutTax_ExtraAdultRate_GST=array(); //Pinal - 15 May 2019 - Purpose : RES-1825
									$WithoutTax_ExtraChildRate_GST=array(); //Pinal - 15 May 2019 - Purpose : RES-1825
									
									$Adjustment=array();
									$Adjustment_Extra_Adult=array();
									$Adjustment_Extra_Child=array();
	
									$Taxes=array();
									$Taxes_Extra_Adult=array();
									$Taxes_Extra_Child=array();
									$StrikeRate=array();
	
									$find_total=0;
									//package promotion variable- end
									
									$non_linear_rates=array();
									$non_linear_rates_before=array();
									$non_linear_rates_before_gst=array();
									$non_linear_extraadult=array();
									$non_linear_extrachild=array();
									$non_linear_rates_exclusive=array();//kishan Tailor puporse:Need NoN-liner rates in RoomLit API   NoNLinerRateRoomListAPI
									
									$find_tax=0;
									$find_adjustment=0;
									$regularrate_promo=array();
									$pack_avg_discount=0;//kishan Tailor 22nd sep 2020 purpose:Night Limit & package percentage display RES-2610
									for($i=1;$i<=$number_night;$i++)
									{
										$dt3='dt_'.$i;
										$date = 'day_'.$i;
										$sdate = 'stopsell_'.$i;			
										$baserate = 'o_day_base_'.$i;			
										$coa_val = 'coa_'.$i;
										$cod_val = 'cod_'.$i;
										$min_nights_val='min_night_'.$i;
										$max_nights_val='max_night_'.$i;//Chinmay Gandhi - Add maximum night for Rate plan
										$beforediscount='before_discount_tax_day_base_'.$i;
										$max_night_parent='max_night_parent_'.$i; //Chandrakant - 28 January 2021, Purpose - RES-2723 - To get Rateplan max night for package
										
										if($i==1)
											$selenddt='dt_'.$i;
										
										//Show Only fully Available Rate Plan Or Package - bb_ShowOnlyAvailableRatePlanOrPackage
										if($rmdetail->$date == 0 && $bb_ShowOnlyAvailableRatePlanOrPackage==true)
										{
											$inv = 0;
										}
										else
										{
											if(isset($rmdetail->$dt3))
												$inventory[$rmdetail->$dt3]=$rmdetail->$date;											
											//array_push($inventory,$rmdetail->$date);										
										}
										
										if(isset($rmdetail->$sdate) && $rmdetail->$sdate == 1)//Pinal - added isset condition.
										{
											$stopsell = 0;
											
											//flora
										   if($showcoacodstopsell==1)
										   {
											  $stopsell = 1;
											  $inventory[$rmdetail->$dt3]=0;
										   }
										}
										
										if($SplGoogleHotelFinder==1)
										{
											$stopsell = 1;
										}
										
										if(isset($rmdetail->$coa_val) && $rmdetail->$coa_val == 1 && $i==1) //Pinal - added isset condition
										{
											  $coa = 1;
											  //flora
											  if($showcoacodstopsell==1)
											  {
												 $coa = 0;
												 $inventory[$rmdetail->$dt3]=0;
											  }
										}
										
										//Chinmay Gandhi - Hotel Listing - Start
										if($Iscrs_HotelList == 1 || $Iscrs_RoomList == 1)
										{
											if(isset($rmdetail->$cod_val) && $rmdetail->$cod_val == 1)
											{
												  $cod = 1;
												  //flora
												  if($showcoacodstopsell==1)
												  {
													 $cod = 0;
													 $inventory[$rmdetail->$dt3]=0;
												  }
											}
										}
										//Chinmay Gandhi - Hotel Listing - End
										
										if($Iscrs_HotelList == 1)
											$min_n = 1;
										else
											$min_n=(isset($rmdetail->$min_nights_val)?$rmdetail->$min_nights_val:1); //Pinal - added isset condition
											
										$max_n=(isset($rmdetail->$max_nights_val)?$rmdetail->$max_nights_val:'');//Chinmay Gandhi - Add maximum night for Rate plan
	
										if(($conf_ShowMinNightsMatchedRatePlan==1 && ($min_n > $number_night)) || ($conf_ShowMinNightsMatchedRatePlan==3 && ($min_n!=$number_night)))
										{									   
											$this->log->logIt("HotelId : ".$hotelId." : ".$rmdetail->display_name." :  " .$min_n." > ".$number_night." : ".date('H:i:s'));
											$min_nights = 0;
										}
										else
										{
											//array_push($minimum_nights_arr,$rmdetail->$min_nights_val);
											if(isset($rmdetail->$dt3) && isset($rmdetail->$min_nights_val)) //Pinal - added isset condition
												$minimum_nights_arr[$rmdetail->$dt3]=$rmdetail->$min_nights_val;	
										}
										
										//Chinmay Gandhi - 1.0.52.60 - Add maximum nights for rate plan - Start
										if($max_n!='' && $max_n < $number_night && $notdefinedstay!='API' && $metasearch != 'GHF') //Akshay parihar - 29 march 2019 - skip this condition when metasearch is GHF
										{
											$this->log->logIt("HotelId : ".$hotelId." : ".$rmdetail->display_name." :  " .$max_n." > ".$number_night." : ".date('H:i:s'));
											$max_nights = 0;
										}
										else
										{
											if(isset($rmdetail->$dt3) && isset($rmdetail->$max_nights_val)) //Pinal - added isset condition
												$maximum_nights_arr[$rmdetail->$dt3]=$rmdetail->$max_nights_val;
											//Chandrakant - 28 January 2021 - START
											//Purpose : RES-2723 - Check if rateplan max night available or not and assign in package level max night for BOG case
											elseif(isset($rmdetail->$dt3) && isset($rmdetail->$max_night_parent))
											{
												if(isset($_REQUEST['isbookongoogle']) && $_REQUEST['isbookongoogle']==1)
													$maximum_nights_arr[$rmdetail->$dt3]=$rmdetail->$max_night_parent;	
											}
											//Chandrakant - 28 January 2021 - END	
										}
										//Chinmay Gandhi - 1.0.52.60 - Add maximum nights for rate plan - END
										
										//kishan Tailor 30th Oct 2020 purpose:need of Stopesell,COA and COD Data START
										if(isset($rmdetail->$dt3) && isset($rmdetail->$sdate)) 
												$stopesell_array[$rmdetail->$dt3]=$rmdetail->$sdate;
										
										if(isset($rmdetail->$dt3) && isset($rmdetail->$coa_val)) 
												$coa_array[$rmdetail->$dt3]=$rmdetail->$coa_val;
										
										if(isset($rmdetail->$dt3) && isset($rmdetail->$cod_val)) 
												$cod_array[$rmdetail->$dt3]=$rmdetail->$cod_val;
										
										//kishan Tailor 30th Oct 2020 END
										
										if($SplGoogleHotelFinder==1)
										{
											$bb_ShowOnlyAvailableRatePlanOrPackage=0;
											$conf_ShowMinNightsMatchedRatePlan=0;
										}
										
										//Pinal - added isset condition - START
										if(isset($rmdetail->$baserate))
										   $rate =	$rate + $rmdetail->$baserate;
										   
										if(isset($rmdetail->$beforediscount))
										   $discount = $discount + $rmdetail->$beforediscount;
										//Pinal - added isset condition - END
										
										$range.=$i.",";
										
										//calculate package & promotion discount-start
										$nightrule_var='nightrule_'.$_startDay;
										
										$nightrule=''; //Pinal - adding isset condition
										if(isset($rmdetail->$nightrule_var)) //Pinal - adding isset condition
										   $nightrule=$rmdetail->$nightrule_var;
										
										//Chandrakant - 30 June 2020 - Purpose : Fix Bug Package Deal is Applying All day same when i apply it different - RES-2531 - START
										//if(isset($rmdetail->deals))
										//	$deal=$rmdetail->deals;
										$deals_var='deals_'.$_startDay;
										if(isset($rmdetail->$deals_var))
											$deal=$rmdetail->$deals_var;
										//Chandrakant - 30 June 2020 - END
										else
											$deal='';
										
										//Display Options for Show Special Offers & Regular Rates / Show Regular Rates only / Show Special Offers only) :  - start
										if($selectedviewoptions==1 && $deal!='' && $bb_viewoptions==1 && $LayoutTheme == 1 )//Show only Rate plans // Priyanka Maurya - 05 April 2019 - Purpose : When this option is Set Showing Issue in Responsive UI.[ Added $LayoutTheme Condition ] - RES-1849
											$inv=0;
										if($selectedviewoptions==2 && $deal=='' && $bb_viewoptions==1 && $LayoutTheme == 1 )//Show only Packages // Priyanka Maurya - 05 April 2019 - Purpose : When this option is Set Showing Issue in Responsive UI.[ Added $LayoutTheme Condition ]- RES-1849
											$inv=0;
										//Display Options for Show Special Offers & Regular Rates / Show Regular Rates only / Show Special Offers only) :  - end
										
										$beforediscount_var='before_discount_day_base_'.$_startDay;
										$pack_beforediscount_var='PackRackRate_'.$_startDay; //Pinal
										
										if($Iscrs_RoomList == 1)
											$pack_beforediscount_var_bd='PackRackRate_before_discount_'.$_startDay;
										
										$RackRate=0; //Pinal - added isset condition
										$rateGST=0; //Chandrakant - 1.0.53.61 - 08 December 2017
										$packdealtype=''; //Chandrakant - 1.0.53.61 - 21 December 2017
										if(isset($rmdetail->$beforediscount_var)){ //Pinal - added isset condition
										   $RackRate=$rmdetail->$beforediscount_var;
										   $rateGST=$rmdetail->$beforediscount_var; //Chandrakant - 1.0.53.61 - 08 December 2017
										}
	
										$dealcnt=$dealothercnt=0;	
										$displayregurate='';
										$lblfree='';
										
										$dt2='dt_'.$i;
										$room_rack_value=array();
										$room_rack_value['RackRate'] = $rmdetail->RackRate;
										$room_rack_value['EARate'] = $rmdetail->EARate;
										$room_rack_value['ECRate'] = $rmdetail->ECRate;
																			
										if($_loopCnt<$number_night)			
										{
											$isdisplayregularrate='isdisplayregularrate_' . $_startDay;
											if(isset($rmdetail->$isdisplayregularrate))
												$displayregurate=$rmdetail->$isdisplayregularrate;
											$regularrate_promo["night_$_loopCnt"]=$displayregurate;
											
											//Priyanka Maurya - 11 Feb 2020 - Purpose : Apply Package Discount on Extra Adult & Child Rate - RES-1825 - START
											$o_day_extra_adult='o_day_extra_adult_'.$_startDay;
											$o_day_extra_child='o_day_extra_child_'.$_startDay;
											//Priyanka Maurya - END
											
											//Pinal - added isset condition - START
											$adult_rate=0;
											if(isset($rmdetail->$o_day_extra_adult))
											{
											  $adult_rate=$rmdetail->$o_day_extra_adult;
											  $non_linear_extraadult[$i]=$rmdetail->$o_day_extra_adult;
											}
											  
											$child_rate=0;
											if(isset($rmdetail->$o_day_extra_child))
											{
											  $child_rate=$rmdetail->$o_day_extra_child;
											  $non_linear_extrachild[$i]=$rmdetail->$o_day_extra_child;
											}
											//Pinal - added isset condition - END
												
											if($deal!='')
											{
												$spdeal=explode(":",$deal);
												$nightcount=explode("|",$spdeal[1]);
												
												$RackRate=0; //Pinal - added isset condition
												$rateGST=0; //Chandrakant - 1.0.53.61 - 08 December 2017
												if(isset($rmdetail->$beforediscount_var)){ //Pinal - added isset condition
													$Rackrate=$rmdetail->$beforediscount_var;
													$rateGST=$rmdetail->$beforediscount_var; //Chandrakant - 1.0.53.61 - 08 December 2017
												}
												
												//Strike Rates Price- start											
												if($RoomRevenueTax!='' && isset($rmdetail->$dt2) && isset($taxdata[$rmdetail->$dt2]))
												{
													# Sheetal Panchal - 1.0.52.60 - 5th Aug 2017, Purpose - For Non linear rate mode setting , Pass rate mode
													//Chandrakant - 1.0.53.61 - 08 December 2017 - START
													//Purpose : Added if else section for GST India related changes. 
													//if($FindSlabForGSTIndia==1 && $packdealtype!='FIXED' && $LayoutTheme==2)
													if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
													{
														$find_tax=$ObjProcessDao->calculateTax($rateGST, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode);
													}
													else
													{
													//Chandrakant - 1.0.53.61 - 08 December 2017 - END
														$find_tax=$ObjProcessDao->calculateTax($RackRate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode);
													}
																									
													 $taxrate = $RackRate + $find_tax;
												}
												else
													 $taxrate = $RackRate;
												 $find_adjustment=$ObjProcessDao->getRoundVal($taxrate,false,$HotelCode,$digits_after_decimal);//adjustment											 
												 $taxrate=$taxrate+$find_adjustment;
												//array_push($StrikeRate,$taxrate);
												if(isset($rmdetail->$dt2))
													$StrikeRate[$rmdetail->$dt2]=$taxrate;
												//Strike Rates Price- end
												  
												if($nightcount[0]=='PAYSTAYDEAL')
												{
													$guestpayfor=$nightcount[1];
													$gueststayfor=$nightcount[2];
													if($PayForStart<=$guestpayfor)
													{
														$discount_amt=$Rackrate;
														$PayForStart++;
													}
													else if($StayForStart<=($gueststayfor-$guestpayfor))
													{												
														if(isset($rmdetail->$isdisplayregularrate) && $rmdetail->$isdisplayregularrate=='Y')
															$discount_amt = $RackRate;
														else
														{
															$discount_amt = "0.00";
															$lblfree = 'Free';
														}												
														$StayForStart++;
													}						
													if($PayForStart>=$guestpayfor && $StayForStart>=($gueststayfor-$guestpayfor+1))
													{
														$PayForStart=1;
														$StayForStart=1;							
													}						
													$dealcnt++;	
												}
												else if($nightcount[0]=='PACKAGEDEAL')
												{
													$RackRate=0; //Pinal - added isset condition
													if(isset($rmdetail->$beforediscount_var)) //Pinal - added isset condition
														$RackRate=$rmdetail->$beforediscount_var;
														
													$noofnights=$nightcount[1];
													$ratepernight=$nightcount[2];						
													$extranightrateon=$nightcount[3];
													$packdealtype = $nightcount[4];
													//kishan Tailor 23rd sep 2020 purpose:Night Limit & package percentage display RES-2610 START
													if($packdealtype=='PER' && isset($_REQUEST['showavgpercentdiscount']) && $_REQUEST['showavgpercentdiscount']==1)
														$pack_avg_discount+=$ratepernight;
													//kishan Tailor 23rd sep 2020 END
													$discounted_ar=$adult_rate;
													$discounted_cr=$child_rate;
													//added code - start
													if($packdealtype=='FIXED')
														$ratepernight2=$ratepernight;
													else if($packdealtype=='PER')
													{
														$ratepernight2=$RackRate-(($RackRate*$ratepernight)/100);
														
														if($ApplyPackageOnExtras=='1') //Priyanka Maurya - 02 March 2020 - Purpose : RES-1825
														{
															$discounted_ar=$discounted_ar-(($discounted_ar*$ratepernight)/100);
															$discounted_cr=$discounted_cr-(($discounted_cr*$ratepernight)/100);
														}
													}
													else if($packdealtype=='AMT')
														$ratepernight2=$RackRate-$ratepernight;													
													else
														$ratepernight2=$ratepernight;
													//added code - end
														
													
													if($_loopCnt<$noofnights)
													{												
													if(isset($rmdetail->$isdisplayregularrate) && $rmdetail->$isdisplayregularrate=='Y')
														$discount_amt = $RackRate;
													else
													{
														$discount_amt = $ratepernight2;
														if($ApplyPackageOnExtras=='1') //Priyanka Maurya - 02 March 2020 - Purpose : RES-1825
														{
															$adult_rate=$discounted_ar;
															$child_rate=$discounted_cr;
														}
													}												
													}
													else if($extranightrateon=='Y')
													{
														if(isset($rmdetail->$isdisplayregularrate) && $rmdetail->$isdisplayregularrate=='Y')
															$discount_amt = $RackRate;
														else
														{
															$discount_amt = $ratepernight2;
															
															if($ApplyPackageOnExtras=='1') //Priyanka Maurya - 02 March 2020 - Purpose : RES-1825
															{
																$adult_rate=$discounted_ar;
																$child_rate=$discounted_cr;
															}
														}
													}
													else if($extranightrateon=='N')
														$discount_amt=$RackRate;
													$dealcnt++;
												}
												else if($nightcount[0]=='PERCENTDISCOUNT')
												{
													$RackRate=0; //Pinal - added isset condition
													if(isset($rmdetail->$beforediscount_var)) //Pinal - added isset condition
														$RackRate=$rmdetail->$beforediscount_var;
														
													//Chandrakant - 30 June 2020 - Purpose : Fix Bug Apply discount All day same when i apply it different - RES-2531 - START
													//$discount=$rmdetail->packagediscount;
													$discount=0;
													$packagediscount_var = 'packagediscount_'.$_startDay;
													if(isset($rmdetail->$packagediscount_var))
														$discount=$rmdetail->$packagediscount_var;
													//Chandrakant - 30 June 2020 - END
													if(isset($_REQUEST['showavgpercentdiscount']) && $_REQUEST['showavgpercentdiscount']==1)
													$pack_avg_discount+=$discount;//kishan Tailor 22 sep 2020 purpose:Night Limit & package percentage display RES-2610
													
													if(isset($rmdetail->$isdisplayregularrate) && $rmdetail->$isdisplayregularrate=='Y')
														$discount_amt = $RackRate;
													else
													{
														$discount_amt = $RackRate - (($RackRate * $discount) / 100);

														if($ApplyPackageOnExtras=='1') //Priyanka Maurya - 02 March 2020 - Purpose : RES-1825
														{
															$adult_rate=$adult_rate-(($adult_rate*$discount)/100);
															$child_rate=$child_rate-(($child_rate*$discount)/100);
														}
													}
													$dealcnt++;
												}	
												else if($nightcount[0]=='AMOUNTDISCOUNT')
												{
													$RackRate=0; //Pinal - added isset condition
													if(isset($rmdetail->$beforediscount_var)) //Pinal - added isset condition
														$RackRate=$rmdetail->$beforediscount_var;
													//	echo $RackRate."<br>";
													
													//Chandrakant - 30 June 2020 - Purpose : Fix Bug Apply discount All day same when i apply it different - RES-2531 - START
													//$discount=$rmdetail->packagediscount;
													$discount=0;
													$packagediscount_var = 'packagediscount_'.$_startDay;
													if(isset($rmdetail->$packagediscount_var))
														$discount=$rmdetail->$packagediscount_var;
													//Chandrakant - 30 June 2020 - END
													
													if(isset($rmdetail->$isdisplayregularrate) && $rmdetail->$isdisplayregularrate=='Y')
														$discount_amt = $RackRate;
													else
														$discount_amt = $RackRate - $discount;												
													$dealcnt++;
													//echo $discount_amt;
												}
												
											}//deals
											
											if($nightrule=='' && $dealcnt > 0)
											{
												if(isset($rmdetail->$dt2))
												{
													$WithoutTax_BaseRate[$rmdetail->$dt2]=$discount_amt;
													
													//Chandrakant - 1.0.53.61 - 21 December 2017 - START
													$WithoutTax_GSTRate[$rmdetail->$dt2]=$rateGST;
													//Chandrakant - 1.0.53.61 - 21 December 2017 - END
												}
												//array_push($WithoutTax_BaseRate,$discount_amt);	
												if($RoomRevenueTax!='' && isset($rmdetail->$dt2) && isset($taxdata[$rmdetail->$dt2]))
												{
												   # Sheetal Panchal - 1.0.52.60 - 5th Aug 2017, Purpose - For Non linear rate mode setting , Pass rate mode
	
													//Chandrakant - 1.0.53.61 - 08 December 2017 - START
													//Purpose : Added if else section for GST India related changes. 
													//if($FindSlabForGSTIndia==1 && $packdealtype!='FIXED' && $LayoutTheme==2)
													if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
													{
														$find_tax=$ObjProcessDao->calculateTax($rateGST, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode);
													}
													else
													{
													//Chandrakant - 1.0.53.61 - 08 December 2017 - END
														$find_tax=$ObjProcessDao->calculateTax($discount_amt, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode);
													}
													//array_push($Taxes,$find_tax);
													if(isset($rmdetail->$dt2))
														$Taxes[$rmdetail->$dt2]=$find_tax;
													
													$taxrate = $discount_amt + $find_tax;
												}
												else
													$taxrate = $discount_amt;
												   
												   $find_adjustment=$ObjProcessDao->getRoundVal($taxrate,false,$HotelCode,$digits_after_decimal);//adjustment
												   //array_push($Adjustment,$find_adjustment);
												   if(isset($rmdetail->$dt2))
													$Adjustment[$rmdetail->$dt2]=round($find_adjustment,$display_decimalplace);
												   $taxrate=$taxrate+$find_adjustment;
												   
												 //  array_push($BaseRate,$taxrate);
												   if(isset($rmdetail->$dt2))
													$BaseRate[$rmdetail->$dt2]=round($taxrate,$display_decimalplace);
											}
												
											if(isset($nightrule) && $nightrule!='')
											{
												if($dealcnt==0)
												{
													$RackRate=0; //Pinal - added isset condition
													if(isset($rmdetail->$beforediscount_var)) //Pinal - added isset condition
														$RackRate=$rmdetail->$beforediscount_var;
												}
												else
													$RackRate=$discount_amt;
													
												$promotiondeal=explode(":",$nightrule);
												if(isset($promotiondeal[3]) && $promotiondeal[3]!='')
													$amount=$promotiondeal[3];
												if(isset($promotiondeal[2]) && $promotiondeal[2]!='')	   
													$nnights=$promotiondeal[2];					
												   
												if(isset($promotiondeal[4]) && $promotiondeal[4]=='PER')
												{
													//echo $promotiondeal." " . $RackRate." ". $amount." ". $nnights." ". $_loopCnt."". $arrDateRange2." ".$displayregurate;
													if($lblfree=='')//silver sand
														$RackRate = calculatePromotion("PER", $promotiondeal, $RackRate, $amount, $nnights, $_loopCnt, $arrDateRange2,$displayregurate);
													else
														$RackRate = $RackRate;	
													//array_push($WithoutTax_BaseRate,$RackRate);
													
													if(isset($rmdetail->$dt2))
													{
														$WithoutTax_BaseRate[$rmdetail->$dt2]=$RackRate;
														
														//Chandrakant - 1.0.53.61 - 21 December 2017 - START
														//if($packdealtype!='FIXED')
														//	$WithoutTax_GSTRate[$rmdetail->$dt2]=$rateGST;
														//else
														$WithoutTax_GSTRate[$rmdetail->$dt2]=$rateGST;
														
														//Chandrakant - 1.0.53.61 - 21 December 2017 - END
													}
													if($RoomRevenueTax!='' && isset($rmdetail->$dt2) && isset($taxdata[$rmdetail->$dt2]))
													{
														# Sheetal Panchal - 1.0.52.60 - 5th Aug 2017, Purpose - For Non linear rate mode setting , Pass rate mode
														//Chandrakant - 1.0.53.61 - 08 December 2017 - START
														//Purpose : Added if else section for GST India related changes. 
														//if($FindSlabForGSTIndia==1 && $packdealtype!='FIXED' && $LayoutTheme==2)
														if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
														{
															$find_tax=$ObjProcessDao->calculateTax($rateGST, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode);
														}
														else
														{
														//Chandrakant - 1.0.53.61 - 08 December 2017 - END
															$find_tax=$ObjProcessDao->calculateTax($RackRate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode);
														}
														//array_push($Taxes,$find_tax);
														if(isset($rmdetail->$dt2))
															$Taxes[$rmdetail->$dt2]=$find_tax;
														$taxrate = $RackRate + $find_tax;
													}
													else
														$taxrate = $RackRate;
														
													$find_adjustment=$ObjProcessDao->getRoundVal($taxrate,false,$HotelCode,$digits_after_decimal);//adjustment
													// array_push($Adjustment,$find_adjustment);
													if(isset($rmdetail->$dt2))
														$Adjustment[$rmdetail->$dt2]=round($find_adjustment,$display_decimalplace);
													
													$taxrate=$taxrate+$find_adjustment;
													 //array_push($BaseRate,$taxrate);
													if(isset($rmdetail->$dt2))
													$BaseRate[$rmdetail->$dt2]=round($taxrate,$display_decimalplace);
												}
												else if(isset($promotiondeal[4]) && $promotiondeal[4]=='AMT')
												{
													if($lblfree=='')//silver sand
														$RackRate = calculatePromotion("AMT", $promotiondeal, $RackRate, $amount, $nnights, $_loopCnt, $arrDateRange2,$displayregurate);
													else
														$RackRate = $RackRate;
													//array_push($WithoutTax_BaseRate,$RackRate);
													if(isset($rmdetail->$dt2))
													{
														$WithoutTax_BaseRate[$rmdetail->$dt2]=$RackRate;
														
														//Chandrakant - 1.0.53.61 - 21 December 2017 - START
														//if($packdealtype!='FIXED')
														//	$WithoutTax_GSTRate[$rmdetail->$dt2]=$rateGST;
														//else
														$WithoutTax_GSTRate[$rmdetail->$dt2]=$rateGST;
														//Chandrakant - 1.0.53.61 - 21 December 2017 - END
													}
													if($RoomRevenueTax!='' && isset($rmdetail->$dt2) && isset($taxdata[$rmdetail->$dt2]))
													{
														# Sheetal Panchal - 1.0.52.60 - 5th Aug 2017, Purpose - For Non linear rate mode setting , Pass rate mode
														//Chandrakant - 1.0.53.61 - 08 December 2017 - START
														//Purpose : Added if else section for GST India related changes. 
														//if($FindSlabForGSTIndia==1 && $packdealtype!='FIXED' && $LayoutTheme==2)
														if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
														{
															$find_tax=$ObjProcessDao->calculateTax($rateGST, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode);
														}
														else
														{
															$find_tax=$ObjProcessDao->calculateTax($RackRate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode);
														}
														//array_push($Taxes,$find_tax);
														if(isset($rmdetail->$dt2))
															$Taxes[$rmdetail->$dt2]=$find_tax;
														$taxrate = $RackRate + $find_tax;
													}
													else
														$taxrate = $RackRate;
													$find_adjustment=$ObjProcessDao->getRoundVal($taxrate,false,$HotelCode,$digits_after_decimal);//adjustment
													// array_push($Adjustment,$find_adjustment);
													if(isset($rmdetail->$dt2))
														$Adjustment[$rmdetail->$dt2]=round($find_adjustment,$display_decimalplace);
													$taxrate=$taxrate+$find_adjustment;
														//array_push($BaseRate,$taxrate);
													if(isset($rmdetail->$dt2))
														$BaseRate[$rmdetail->$dt2]=round($taxrate,$display_decimalplace);
												}
											}
											
											if($deal=='' && $nightrule=='')
											{
												$RackRate=0; //Pinal - added isset condition
												if(isset($rmdetail->$beforediscount_var)) //Pinal - added isset condition
													$RackRate=$rmdetail->$beforediscount_var;
													
												//array_push($WithoutTax_BaseRate,$RackRate);
												if(isset($rmdetail->$dt2))
												{
													$WithoutTax_BaseRate[$rmdetail->$dt2]=$RackRate;
														
													//Chandrakant - 1.0.53.61 - 21 December 2017 - START
													//if($packdealtype!='FIXED')
													//	$WithoutTax_GSTRate[$rmdetail->$dt2]=$rateGST;
													//else
													$WithoutTax_GSTRate[$rmdetail->$dt2]=$rateGST;
													//Chandrakant - 1.0.53.61 - 21 December 2017 - END
												}
												if($RoomRevenueTax!='' && isset($rmdetail->$dt2) && isset($taxdata[$rmdetail->$dt2]))
												{
													# Sheetal Panchal - 1.0.52.60 - 5th Aug 2017, Purpose - For Non linear rate mode setting , Pass rate mode
													//Chandrakant - 1.0.53.61 - 08 December 2017 - START
													//Purpose : Added if else section for GST India related changes. 
													//if($FindSlabForGSTIndia==1 && $packdealtype!='FIXED' && $LayoutTheme==2)
													if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
													{
														$find_tax=$ObjProcessDao->calculateTax($rateGST, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode);
													}
													else
													{
													//Chandrakant - 1.0.53.61 - 08 December 2017 - END
														$find_tax=$ObjProcessDao->calculateTax($RackRate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode);
													}
													
													//array_push($Taxes,$find_tax);
													if(isset($rmdetail->$dt2))
													   $Taxes[$rmdetail->$dt2]=$find_tax;
													$taxrate = $RackRate + $find_tax;
												}
												else
													$taxrate = $RackRate;
												$find_adjustment=$ObjProcessDao->getRoundVal($taxrate,false,$HotelCode,$digits_after_decimal);//adjustment
												//array_push($Adjustment,$find_adjustment);
												 if(isset($rmdetail->$dt2))
														$Adjustment[$rmdetail->$dt2]=round($find_adjustment,$display_decimalplace);
												$taxrate=$taxrate+$find_adjustment;
												//array_push($BaseRate,$taxrate);//changed from o_day_base
												if(isset($rmdetail->$dt2))
													$BaseRate[$rmdetail->$dt2]=round($taxrate,$display_decimalplace);
											}
											
											//Pinal - added isset condition - START
											#Store Base Rate / Extra Adult / Child Rate
											if(isset($rmdetail->$beforediscount_var)) 
												array_push($BeforeDiscountRate,$rmdetail->$beforediscount_var);
											else
												array_push($BeforeDiscountRate,0);
											//Pinal - added isset condition - END
											
											//Pinal - 13 February 2019 - START
											//Purpose : Multiple property listing
											if(isset($rmdetail->$pack_beforediscount_var)) 
												array_push($BeforeDiscountRate_Package,$rmdetail->$pack_beforediscount_var);
											else
												array_push($BeforeDiscountRate_Package,0);
											//Pinal - 13 February 2019 - END
											
											//Chinmay Gandhi - 6th April 2019 - Hotel Listing [ Apply Promotion ] - Start
											if($Iscrs_RoomList == 1)
											{
												if(isset($rmdetail->$pack_beforediscount_var_bd)) 
													array_push($BeforeDiscountRate_Package_bd,$rmdetail->$pack_beforediscount_var_bd);
												else
													array_push($BeforeDiscountRate_Package_bd,0);
											}
											//Chinmay Gandhi - 6th April 2019 - Hotel Listing [ Apply Promotion ] - End
											
											if($Iscrs_RoomList == 1 && $rate_mode=='NONLINEAR') //Pinal - 19 February 2019 - Purpose : Multiple property listing
											{
												for($i_non=1;$i_non<=7;$i_non++)
												{
													$non_adult_var='o_day_adult'.$i_non."_".$_startDay;
													if(isset($rmdetail->$non_adult_var))
														$non_linear_rates[$i]['adult'.$i_non]=$rmdetail->$non_adult_var;
													
													$non_adult_var='o_day_child'.$i_non."_".$_startDay;
													if(isset($rmdetail->$non_adult_var))
														$non_linear_rates[$i]['child'.$i_non]=$rmdetail->$non_adult_var;
												}
												$non_linear_rates_before[$i]=$non_linear_rates[$i];
												$non_linear_rates_before_gst[$i]=$non_linear_rates[$i];
												if($nightrule!='' || $deal!='')
												{
													//$this->log->logIt("<> ".$rmdetail->display_name." <>");
													//$this->log->logIt($non_linear_rates[$i]);
													
													if($deal!='')
													{
														for($i_non=1;$i_non<=7;$i_non++)
														{
															if(isset($non_linear_rates[$i]['adult'.$i_non]))
															{
																//$this->log->logIt($non_linear_rates[$i]['adult'.$i_non]." BEFORE $i_non");
																$non_linear_rates[$i]['adult'.$i_non]=calculatePackage($deal,$non_linear_rates[$i]['adult'.$i_non],$displayregurate,$_loopCnt);
																//$this->log->logIt($non_linear_rates[$i]['adult'.$i_non]." AFTER $i_non");
															}
															
															$spfm=explode(':',$deal);
															$frml=explode('|',$spfm[1]);
															
															if(isset($non_linear_rates[$i]['child'.$i_non]) && $frml[4]!="AMT" && $frml[0]!='AMOUNTDISCOUNT')
															{
																$non_linear_rates[$i]['child'.$i_non]=calculatePackage($deal,$non_linear_rates[$i]['child'.$i_non],$displayregurate,$_loopCnt);
															}
														}
													}
													
													$non_linear_rates_before[$i]=$non_linear_rates[$i];
													
													if($nightrule!='')
													{
														$promorule=explode(':',$nightrule);
														$drate=$promorule[3];
														
														for($i_non=1;$i_non<=7;$i_non++)
														{
															if($displayregurate=='N'/* && $promorule[4]!='AMT'*/)
															{
																if(isset($non_linear_rates[$i]['adult'.$i_non]))
																{
																	//$this->log->logIt($non_linear_rates[$i]['adult'.$i_non]." $_loopCnt  PROMOBEFORE $i_non");
																	$promo_rates = calculatePromotion($promorule[4], $promorule, $non_linear_rates[$i]['adult'.$i_non], $promorule[3],$number_night,$_loopCnt, $arrDateRange2,$displayregurate);
																	$non_linear_rates[$i]['adult'.$i_non]=$promo_rates;
																	//$this->log->logIt($non_linear_rates[$i]['adult'.$i_non]." $_loopCnt PROMOAFTER $i_non");
																}
																
																if(isset($non_linear_rates[$i]['child'.$i_non]) && $promorule[4]!='AMT')
																{
																	$promo_rates = calculatePromotion($promorule[4], $promorule, $non_linear_rates[$i]['child'.$i_non], $promorule[3],$number_night,$_loopCnt, $arrDateRange2,$displayregurate);
																	$non_linear_rates[$i]['child'.$i_non]=$promo_rates;
																}
															}
														}
													}
												}
											}
											//kishan Tailor puporse:Need NoN-liner rates in RoomLit API   NoNLinerRateRoomListAPI START
											if(!isset($_REQUEST['getDetailedRoomListing']) && isset($_REQUEST['ShowNoNLinerRate']) && $_REQUEST['ShowNoNLinerRate']==1 && $rate_mode=='NONLINEAR')
											{
											 for($i_non=1;$i_non<=7;$i_non++)
												{
													$non_adult_var='o_day_adult'.$i_non."_".$_startDay;
													if(isset($rmdetail->$non_adult_var))
														$non_linear_rates_exclusive[$rmdetail->$dt2]['adult'.$i_non]=$rmdetail->$non_adult_var;
													
													$non_child_var='o_day_child'.$i_non."_".$_startDay;
													if(isset($rmdetail->$non_child_var))
														$non_linear_rates_exclusive[$rmdetail->$dt2]['child'.$i_non]=$rmdetail->$non_child_var;
												}
											}
											//kishan Tailor puporse:Need NoN-liner rates in RoomLit API END
											
											//$o_day_extra_adult='o_day_extra_adult_'.$_startDay;
											//$o_day_extra_child='o_day_extra_child_'.$_startDay;
											//
											////Pinal - added isset condition - START
											//$adult_rate=0;
											//if(isset($rmdetail->$o_day_extra_adult))
											//{
											//  $adult_rate=$rmdetail->$o_day_extra_adult;
											//  $non_linear_extraadult[$i]=$rmdetail->$o_day_extra_adult;
											//}
											//  
											//$child_rate=0;
											//if(isset($rmdetail->$o_day_extra_child))
											//{
											//  $child_rate=$rmdetail->$o_day_extra_child;
											//  $non_linear_extrachild[$i]=$rmdetail->$o_day_extra_child;
											//}
											////Pinal - added isset condition - END
											
											//array_push($WithoutTax_ExtraAdultRate,$adult_rate);
											if(isset($rmdetail->$dt2))
											{
												if($ApplyPackageOnExtras=='1') //Priyanka Maurya - 02 March 2020 - Purpose : RES-1825
												{
													$WithoutTax_ExtraAdultRate[$rmdetail->$dt2]=$adult_rate;
												
													if(isset($rmdetail->$o_day_extra_adult))
														$WithoutTax_ExtraAdultRate_GST[$rmdetail->$dt2]=$rmdetail->$o_day_extra_adult;
													else
														$WithoutTax_ExtraAdultRate_GST[$rmdetail->$dt2]=$adult_rate;
												}
												else
												{
													if(isset($rmdetail->$o_day_extra_adult))
														$WithoutTax_ExtraAdultRate[$rmdetail->$dt2]=$rmdetail->$o_day_extra_adult;
													else
														$WithoutTax_ExtraAdultRate[$rmdetail->$dt2]=0;
												}
											}
											
											//array_push($WithoutTax_ExtraChildRate,$child_rate);
											if(isset($rmdetail->$dt2))
											{
												if($ApplyPackageOnExtras=='1') //Priyanka Maurya - 02 March 2020 - Purpose : RES-1825
												{
													$WithoutTax_ExtraChildRate[$rmdetail->$dt2]=$child_rate;
												
													if(isset($rmdetail->$o_day_extra_child))
														$WithoutTax_ExtraChildRate_GST[$rmdetail->$dt2]=$rmdetail->$o_day_extra_child;
													else
														$WithoutTax_ExtraChildRate_GST[$rmdetail->$dt2]=$child_rate;
												}
												else
												{
													if(isset($rmdetail->$o_day_extra_child))
														$WithoutTax_ExtraChildRate[$rmdetail->$dt2]=$rmdetail->$o_day_extra_child;
													else
														$WithoutTax_ExtraChildRate[$rmdetail->$dt2]=0;
												}
											}
											
											if($RoomRevenueTax!='' && isset($rmdetail->$dt2) && isset($taxdata[$rmdetail->$dt2]))
											{
											  # Sheetal Panchal - 1.0.52.60 - 5th Aug 2017, Purpose - For Non linear rate mode setting , Pass rate mode
												//$find_tax=$ObjProcessDao->calculateTax($adult_rate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','extraadult','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,'',$rate_mode);
												$find_tax=$ObjProcessDao->calculateTax($adult_rate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'extraadult','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,'',$rate_mode); //Pinal - 1.0.52.60 - 8 August 2017 - Purpose - Issue in tax for per adult , per child and per pax.
												
												//array_push($Taxes_Extra_Adult,$find_tax);
												if(isset($rmdetail->$dt2))
													$Taxes_Extra_Adult[$rmdetail->$dt2]=$find_tax;
												$extra_adult_rate = $adult_rate + $find_tax;
											}
											else
												$extra_adult_rate = $adult_rate;
												
											//if($rmdetail->display_name=='KIng_copy_1')	
											//$this->log->LogIt("extra_adult_rate ......".$rmdetail->display_name. " : " .$find_tax.$rmdetail->roomtypeunkid. " : " .$rmdetail->ratetypeunkid);
											
											if($RoomRevenueTax!='' && isset($rmdetail->$dt2) && isset($taxdata[$rmdetail->$dt2]))
											{
											  # Sheetal Panchal - 1.0.52.60 - 5th Aug 2017, Purpose - For Non linear rate mode setting , Pass rate mode
											  
												//$find_tax=$ObjProcessDao->calculateTax($child_rate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','extrachild',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,'',$number_child,$rate_mode);
												//$this->log->logIt($rmdetail->display_name." | ".$rmdetail->roomtypeunkid." - ".$rmdetail->ratetypeunkid);
												
												$find_tax=$ObjProcessDao->calculateTax($child_rate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'extrachild','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,'',$number_child,$rate_mode); //Pinal - 1.0.52.60 - 8 August 2017 - Purpose - Issue in tax for per adult , per child and per pax. , //Pinal - 1.0.52.60 - 9 August 2017 - Purpose - Issue in apply on rack rack
												//array_push($Taxes_Extra_Child,$find_tax);
												
												if(isset($rmdetail->$dt2))
													$Taxes_Extra_Child[$rmdetail->$dt2]=$find_tax;
												$extra_child_rate = $child_rate + $find_tax;
											}
											else
												$extra_child_rate = $child_rate;
											
											$find_adjustment=$ObjProcessDao->getRoundVal($extra_adult_rate,false,$HotelCode,$digits_after_decimal);//adjustment
											//array_push($Adjustment_Extra_Adult,$find_adjustment);//adjustment
											if(isset($rmdetail->$dt2))
													$Adjustment_Extra_Adult[$rmdetail->$dt2]=round($find_adjustment,$display_decimalplace);
											$extra_adult_rate=$extra_adult_rate+$find_adjustment;
											
											$find_adjustment=$ObjProcessDao->getRoundVal($extra_child_rate,false,$HotelCode,$digits_after_decimal);//adjustment
											//array_push($Adjustment_Extra_Child,$find_adjustment);//adjustment
											if(isset($rmdetail->$dt2))
													$Adjustment_Extra_Child[$rmdetail->$dt2]=round($find_adjustment,$display_decimalplace);
											$extra_child_rate=$extra_child_rate+$find_adjustment;
											
											//array_push($ExtraAdultRate,$extra_adult_rate);
											if(isset($rmdetail->$dt2))
													$ExtraAdultRate[$rmdetail->$dt2]=round($extra_adult_rate,$display_decimalplace);
											//array_push($ExtraChildRate,$extra_child_rate);
											if(isset($rmdetail->$dt2))
													$ExtraChildRate[$rmdetail->$dt2]=round($extra_child_rate,$display_decimalplace);
											 $_startDay++;
											 $_loopCnt++;
											 
										}//loop count
										//calculate package & promotion discount-end
									}
									
									$find_total_room_only=0;
									foreach($WithoutTax_BaseRate as $cnt_tot)
										$find_total_room_only+=$cnt_tot;
										
									$find_total_withinc_all=0;
									foreach($BaseRate as $cnt_tot)
										$find_total_withinc_all+=$cnt_tot;
									
									if(!isset($promotioncode) && $promotioncode!='')
										$inv = 0;
									
									//echo $ch_dt;
									//echo " < " .$hotel_checkin_date."<br>";
										
									if($ch_dt < $hotel_checkin_date)
										$dtselection=0;
										
									$rowid++;
									//echo "<br>".$inv." - ".$stopsell." - ".$min_nights." - ".$coa." - ".$cod." - ".$dtselection;
									
									if($inv == 0) continue;				
									if($stopsell == 0) continue;
									
									if($min_nights == 0) continue;
									if($max_nights == 0) continue;
									
									if($coa == 1) continue;							
									if($cod == 1) continue;
									if($dtselection == 0) continue;
									
									if($rmtype_unkid!='' && $rmdetail->roomtypeunkid!=$rmtype_unkid){
										$this->log->logIt("rmtype_unkid  ".$rmtype_unkid);										
										continue;//skip this 
									}
									
									//flora
									if($roomrateId_unkid!='' && $rmdetail->roomrateunkid!=$roomrateId_unkid){
										$this->log->logIt("roomrateunkid  ".$roomrateId_unkid);										
										continue;//skip this 
									}
									
									//flora
									if(isset($Spl_unkid) && $Spl_unkid!='' && isset($rmdetail->specialunkid) && $rmdetail->specialunkid!=$Spl_unkid){ //Pinal - to avoid undefined property error
										$this->log->logIt("specialunkid  ".$Spl_unkid);										
										continue;//skip this 
									}
									
									$currency = $rmdetail->currency;
									$roomname = $rmdetail->display_name;//$rmdetail->roomtype;
									$room_id = $rmdetail->roomrateunkid;//$rmdetail->roomtypeunkid;
									$hotel_id = $rmdetail->hotel_code;//$rmdetail->roomtypeunkid;
									$roomtypeshortkey=$ObjProcessDao->getRoomTypeList($hotel_id,$rmdetail->roomtypeunkid);//kishan Tailor  1.0.53.61/0.02 Purpose For Rooom Type Short code 25 Dec 2017
									if(isset($roomtypeshortkey[0]['shortcode']))
										$roomtypeshort=$roomtypeshortkey[0]['shortcode'];
									else
										$roomtypeshort='';
									
									if($inv == 1 && $stopsell == 1 && $min_nights == 1 && $max_nights == 1 && $coa == 0 && $cod == 0 && $dtselection == 1 )//Chinmay Gandhi - Add maximum night for Rate plan
										$availability++;
									
									if($availability > 0 )
									{
										if($Iscrs_RoomList == 1)
										{
											$RateTypeName=$ObjProcessDao->_getRateType($hotel_id,$rmdetail->ratetypeunkid,$lang_code); //Pinal - 27 December 2018 - Purpose : Multiple property listing
											
											$roomarray[$iCnt]['RateType_Name'] = $RateTypeName;
										}
									
										if($Iscrs_HotelList == 0)//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
										{
											$roomarray[$iCnt]['Room_Name'] = $roomname;
											$webdescription=$rmdetail->webdescription;
											$webdescription=util::convert_line_breaks(stripslashes($webdescription),'<br>');									
											$roomarray[$iCnt]['Room_Description'] = $webdescription;
											$roomarray[$iCnt]['Roomtype_Name'] = $rmdetail->roomtype; //Pinal - 8th August 2016
											$roomarray[$iCnt]['Roomtype_Short_code']=$roomtypeshort;//kishan Tailor 1.0.53.61/0.02 Purpose For Rooom Type Short code 25 Dec 2017
											
											$spldescription=(isset($rmdetail->deals))?$rmdetail->spldescription:'';
											$spldescription=util::convert_line_breaks(stripslashes($spldescription),'<br>');
											$roomarray[$iCnt]['Package_Description'] = $spldescription;
											$roomarray[$iCnt]['Specials_Desc'] = $spldescription;
											
											//Akshay Parihar - Start - 13 April 2019
											//Purpose: Add special condition in the API
											$specialconditions=(isset($rmdetail->specialconditions))?$rmdetail->specialconditions:'';	
											$specialhighlightinclusion=(isset($rmdetail->specialhighlightinclusion))?$rmdetail->specialhighlightinclusion:'';
											   
											$roomarray[$iCnt]['specialconditions'] = $specialconditions;
											$roomarray[$iCnt]['specialhighlightinclusion'] = $specialhighlightinclusion;
											//AKshay End
											
											$roomarray[$iCnt]['hotelcode'] = $hcode;	
											//$roomarray[$iCnt]['foldername'] = $hotelId;
											$roomarray[$iCnt]['roomtypeunkid'] = $rmdetail->roomtypeunkid;
											$roomarray[$iCnt]['ratetypeunkid'] = $rmdetail->ratetypeunkid;
											$roomarray[$iCnt]['roomrateunkid'] = $room_id;
										}
										
										$roomarray[$iCnt]['base_adult_occupancy'] = $rmdetail->base_adult;
										$roomarray[$iCnt]['base_child_occupancy'] = $rmdetail->base_child;
										$roomarray[$iCnt]['max_adult_occupancy'] = $rmdetail->max_adult;
										$roomarray[$iCnt]['max_child_occupancy'] = $rmdetail->max_child;

										//Chinmay Gandhi - Max Occupancy module for Multi Hotel Booking Engine[ RES-2010 ] - Start
										if($Iscrs_RoomList == 1)
										{
											if(isset($rmdetail->max_occupancy) && $rmdetail->max_occupancy != '' && $rmdetail->max_occupancy != null)
												$roomarray[$iCnt]['max_occupancy'] = $rmdetail->max_occupancy;
											else
												$roomarray[$iCnt]['max_occupancy'] = '';
											
											if(isset($rmdetail->ShowMaxPax))
												$roomarray[$iCnt]['ShowMaxPax'] = $rmdetail->ShowMaxPax;
											else
												$roomarray[$iCnt]['ShowMaxPax'] = 'N';
										}//kishan Tailor 5 Dec 2019 purpose:Max Occupancy For Lumiere RES-2325 START
										else
										{
											if(isset($rmdetail->max_occupancy) && $rmdetail->max_occupancy != '' && $rmdetail->max_occupancy != null)
												$roomarray[$iCnt]['max_occupancy'] = $rmdetail->max_occupancy;
											else
												$roomarray[$iCnt]['max_occupancy'] = '';
										}//kishan Tailor 5 Dec 2019 purpose:Max Occupancy END
										//Chinmay Gandhi - Max Occupancy module for Multi Hotel Booking Engine[ RES-2010 ] - End

										$roomarray[$iCnt]['inclusion'] = $rmdetail->inclusion;
										$roomarray[$iCnt]['available_rooms']=$inventory;
										
										
										if($Iscrs_RoomList == 1 && isset($rmdetail->promotioncode) && $rmdetail->promotioncode!='' && $rmdetail->promotioncode!=null && isset($rmdetail->nightrule))
										{
											$isdisplayregularrate='isdisplayregularrate_' . $_startDay;
											
											//$roomarray[$iCnt]['Promotion_Code_Rule'] = array(
											//								'nightrule'=>$rmdetail->nightrule,
											//								'raterule'=>array("night_$i_price"=>$rmdetail->$isdisplayregularrate),
											//								);
											$roomarray[$iCnt]['Promotion_Code_Rule']['nightrule']=$rmdetail->nightrule;
											$roomarray[$iCnt]['Promotion_Code_Rule']['raterule']=$regularrate_promo;
											
											//Promotion_Code_Rule
											//$non_linear_rates
										}
											
										if($Iscrs_HotelList == 0)//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
										{
											if(!empty($inventory))
											   $roomarray[$iCnt]['min_ava_rooms'] = min($inventory);
										}
										
										$sum_tot=0;//flora
										$sum_tot_exctax = 0;//Chinmay - Hotel Listing [ RES-1452 ]
										if(count($BaseRate)>0)
										{
										   $sum_tot=array_sum($BaseRate) / count($BaseRate);
										   $sum_tot_exctax = $find_total_room_only / count($BaseRate);//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
										}
										
										//Chandrakant - 1.0.53.61 - 13 January 2018 - START
										//Purpose : RES-1469 - Replace with discount rate if setting is display and also not using responsive UI
										if($FindSlabForGSTIndia!=1 || $LayoutTheme!=2)
											$WithoutTax_GSTRate = $WithoutTax_BaseRate;
											
										//Chandrakant - 1.0.53.61 - 13 January 2018 - END
										$roomarray[$iCnt]['room_rates_info']=array(
															'before_discount_inclusive_tax_adjustment'=>$StrikeRate,
															'exclusive_tax' => $WithoutTax_BaseRate,
															'exclusivetax_baserate' => $WithoutTax_GSTRate, //Chandrakant - 1.0.53.61 - 21 December 2017
															'tax' => $Taxes,
															'adjustment' => $Adjustment,
															'inclusive_tax_adjustment' => $BaseRate,
															'rack_rate' => $rmdetail->RackRate,
															'totalprice_room_only'=>$find_total_room_only,
															'totalprice_inclusive_all'=>round($find_total_withinc_all,$display_decimalplace),
				   'avg_per_night_before_discount'=>(isset($StrikeRate) && !empty($StrikeRate))?(array_sum($StrikeRate) / count($StrikeRate)):'',
															'avg_per_night_after_discount'=>round($sum_tot,$display_decimalplace),
															'avg_per_night_without_tax'=>$sum_tot_exctax,//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
															);
										
										//Pinal - 13 February 2019 - START
										//Purpose : Multiple property listing
										if(isset($rmdetail->specialunkid) && isset($rmdetail->specialunkid))
										{
											$roomarray[$iCnt]['room_rates_info']['day_wise_baserackrate']=($BeforeDiscountRate_Package);
											
											//Chinmay Gandhi - 6th April 2019 - Hotel Listing [ Apply Promotion ] - Start
											if($Iscrs_RoomList == 1)
												$roomarray[$iCnt]['room_rates_info']['day_wise_baserackrate_bd']=$BeforeDiscountRate_Package_bd;
											//Chinmay Gandhi - 6th April 2019 - Hotel Listing [ Apply Promotion ] - End
										}
										else
										{
											$roomarray[$iCnt]['room_rates_info']['day_wise_baserackrate']=($BeforeDiscountRate);
											
											//Chinmay Gandhi - 6th April 2019 - Hotel Listing [ Apply Promotion ] - Start
											if($Iscrs_RoomList == 1)
												$roomarray[$iCnt]['room_rates_info']['day_wise_baserackrate_bd']=$BeforeDiscountRate;
											//Chinmay Gandhi - 6th April 2019 - Hotel Listing [ Apply Promotion ] - End
										}
										
										$roomarray[$iCnt]['room_rates_info']['day_wise_beforediscount']=$BeforeDiscountRate;
										
										if($Iscrs_RoomList == 1) //Pinal - 19 February 2019 - Purpose : Multiple property listing
										{
											$roomarray[$iCnt]['max_occupancy'] = $rmdetail->max_occupancy;
											$roomarray[$iCnt]['taxdetails']=$taxdata;
											$roomarray[$iCnt]['room_rates_info']['RackRates']=$room_rack_value;
											
											if($rate_mode=='NONLINEAR')
											{
												$roomarray[$iCnt]['room_rates_info']['Non_Linear_Rates']=$non_linear_rates;
												$roomarray[$iCnt]['room_rates_info']['Non_Linear_Rates_Beforediscount']=$non_linear_rates_before;
												$roomarray[$iCnt]['room_rates_info']['Non_Linear_Rates_BeforediscountGST']=$non_linear_rates_before_gst;
											}
											// Jemin - 11-Oct-2019 - RES-2230  - START
											$roomarray[$iCnt]['disppromodescription'] = isset($rmdetail->disppromodescription) ? $rmdetail->disppromodescription : '' ;
											// Jemin END
										}//kishan Tailor 5 Dec 2019 purpose:Max Occupancy For Lumiere RES-2325 START
										else
										{
											if(isset($rmdetail->max_occupancy) && $rmdetail->max_occupancy != '' && $rmdetail->max_occupancy != null)
												$roomarray[$iCnt]['max_occupancy'] = $rmdetail->max_occupancy;
											else
												$roomarray[$iCnt]['max_occupancy'] = "";
										}//kishan Tailor 5 Dec 2019 purpose:Max Occupancy END
										//kishan Tailor 28 December 2020 puporse:Need NoN-liner rates in RoomLit API   NoNLinerRateRoomListAPI START
										if(!isset($_REQUEST['getDetailedRoomListing']) && isset($_REQUEST['ShowNoNLinerRate']) &&  $_REQUEST['ShowNoNLinerRate']==1 && $rate_mode=='NONLINEAR')
											$roomarray[$iCnt]['room_rates_info']['Non_Linear_Rates_Exclusive_Tax']=$non_linear_rates_exclusive;
										//kishan Tailor 28 December 2020 END
										
										if(($Iscrs_RoomList == 1 || $getminrateonly == 1 || $getRate_closest_avali == 1) && isset($rmdetail->MinAvgPerNight))//Chinmay Gandhi - Closest Availability Tool [ RES-2079 ]
												$roomarray[$iCnt]['room_rates_info']['MinAvgPerNight']=$rmdetail->MinAvgPerNight;
												
										if(($Iscrs_RoomList == 1 || $getminrateonly == 1) && isset($rmdetail->MinAvgPerNightDiscount))
												$roomarray[$iCnt]['room_rates_info']['MinAvgPerNightDiscount']=$rmdetail->MinAvgPerNightDiscount;
										
										if($Iscrs_RoomList == 1 && isset($rmdetail->specialname) && $rmdetail->specialname!='' && isset($rmdetail->specialunkid) && $rmdetail->specialunkid!='') //Pinal - 29 March 2019 - Purpose : Displaying packge as rate type Multiple property listing
										{
											$packages_as_ratetype[htmlspecialchars_decode($rmdetail->specialname)][]=$rmdetail->specialunkid;
											$roomarray[$iCnt]['RatePlanName']=$rmdetail->rtplanname;
										}
										//Pinal - 13 February 2019 - END
										
											$roomarray[$iCnt]['extra_adult_rates_info']=array(
															'exclusive_tax' => $WithoutTax_ExtraAdultRate,
															'tax' => $Taxes_Extra_Adult,													    
															'adjustment' => $Adjustment_Extra_Adult,
															'inclusive_tax_adjustment' => $ExtraAdultRate,
															'rack_rate' => $rmdetail->EARate,
															);
										
										if($Iscrs_RoomList == 1 && $rate_mode=='NONLINEAR') //Pinal - 19 February 2019 - Purpose : Multiple property listing
											$roomarray[$iCnt]['extra_adult_rates_info']['Extra_Adult_Rates']=$non_linear_extraadult;
										
											$roomarray[$iCnt]['extra_child_rates_info']=array(
															'exclusive_tax' => $WithoutTax_ExtraChildRate,
															'tax' => $Taxes_Extra_Child,
															'adjustment' => $Adjustment_Extra_Child,
															'inclusive_tax_adjustment' => $ExtraChildRate,													    
															'rack_rate' => $rmdetail->ECRate,
															);
										
										if($ApplyPackageOnExtras=='1') //Priyanka Maurya - 02 March 2019 - Purpose : RES-1825
										{
											$roomarray[$iCnt]['extra_adult_rates_info']['exclusive_tax_beforediscount']= $WithoutTax_ExtraAdultRate_GST;
											$roomarray[$iCnt]['extra_child_rates_info']['exclusive_tax_beforediscount']= $WithoutTax_ExtraChildRate_GST;
										}
										//Pinal - 15 May 2019 - END
										if($Iscrs_RoomList == 1 && $rate_mode=='NONLINEAR') //Pinal - 19 February 2019 - Purpose : Multiple property listing
											$roomarray[$iCnt]['extra_child_rates_info']['Extra_Child_Rates']=$non_linear_extrachild;
											
										//echo "<pre>";
										//print_r($roomarray); exit;
										//$this->log->logIt("WithoutTax Price array : ".json_encode($WithoutTax_BaseRate,true));
									 
										//Summary calculation - start - flora									
										$total_price=0;
										$pernighttax=0;
										$total_roundval=0;
										$ExtraChild_rackratetax=0;
										$ExtraAdult_rackratetax=0;
										$total_exclusivetax_price=0; //Chandrakant - 1.0.53.61 - 27 December 2017
										//$this->log->logIt("arrDateRange : ".json_encode($arrDateRange2,true));
										
										if($num_rooms == 1 && $showsummary==1)
										{
											for($i_price=0;$i_price<count($arrDateRange2)-1;$i_price++)
											{
												$night_price=0;
												$night_priceGST=0; //Chandrakant - 1.0.53.61 - 21 December 2017
												$findround=0;
												$extrachild=$extraadult='';
												
												//Chandrakant - 2.0.16 - 22 April 2019 - START
												//Purpose : CEN-1032 - Need to show total rate with PAX price day wise.
												$total_pricepax=0;
												$pernighttaxpax=0;
												$total_roundvalpax=0;
												//Chandrakant - 2.0.16 - 22 April 2019 - END
												
												//$this->log->logIt("adult/child/rooms :  ".$number_adult. " / ". $number_child. " / " .$num_rooms);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
												if(isset($roomarray[$iCnt]['room_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]])) //Pinal - to avoid undefined index error
												{
												 # Sheetal Panchal - 1.0.52.60 - 3rd Aug 2017, Purpose - For Non linear rate mode setting - START
													if($rate_mode=='NONLINEAR')
													{
													   if(7<$number_adult)
													   {
															//Chandrakant - 2.0.16 - 22 April 2019 - START
															//Purpose : CEN-1032 - Need to show total rate with PAX price day wise.
															$total_pricepax = $roomarray[$iCnt]['room_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]] + ($number_adult - 7) * $roomarray[$iCnt]['extra_adult_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]];
															
															$total_price +=$total_pricepax;
															//Chandrakant - 2.0.16 - 22 April 2019 - END
															
														   $night_price+=$roomarray[$iCnt]['room_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]] + ($number_adult - 7) * $roomarray[$iCnt]['extra_adult_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]];
														   $extraadult=($number_adult - 7); //Pinal
														   
														   $total_exclusivetax_price+=$night_priceGST+=$roomarray[$iCnt]['room_rates_info']['exclusivetax_baserate'][$arrDateRange2[$i_price]] + ($number_adult - 7) * $roomarray[$iCnt]['extra_adult_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]]; //Chandrakant - 1.0.53.61 - 21 December 2017
													   }
													   else
													   {
															//Chandrakant - 2.0.16 - 22 April 2019 - START
															//Purpose : CEN-1032 - Need to show total rate with PAX price day wise.
															$total_pricepax = $roomarray[$iCnt]['room_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]];
														
															$total_price += $total_pricepax;
															//Chandrakant - 2.0.16 - 22 April 2019 - END
															
														   $night_price+=$roomarray[$iCnt]['room_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]];
														   
														   $total_exclusivetax_price+=$night_priceGST+=$roomarray[$iCnt]['room_rates_info']['exclusivetax_baserate'][$arrDateRange2[$i_price]]; //Chandrakant - 1.0.53.61 - 21 December 2017
													   }
														
														$this->log->logIt("Total Price : ".$rate_mode."=".$total_price);
													}
													else
													{ //linear case
													   if($roomarray[$iCnt]['base_adult_occupancy']<$number_adult)
													   {
															//Chandrakant - 2.0.16 - 22 April 2019 - START
															//Purpose : CEN-1032 - Need to show total rate with PAX price day wise.
															$total_pricepax = $roomarray[$iCnt]['room_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]] + ($number_adult - $roomarray[$iCnt]['base_adult_occupancy']) * $roomarray[$iCnt]['extra_adult_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]];
															
														    $total_price+= $total_pricepax;
															//Chandrakant - 2.0.16 - 22 April 2019 - END
															
														   $night_price+=$roomarray[$iCnt]['room_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]] + ($number_adult - $roomarray[$iCnt]['base_adult_occupancy']) * $roomarray[$iCnt]['extra_adult_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]];
														   $extraadult=($number_adult - $roomarray[$iCnt]['base_adult_occupancy']); //Pinal
														   
														   $total_exclusivetax_price+=$night_priceGST+=$roomarray[$iCnt]['room_rates_info']['exclusivetax_baserate'][$arrDateRange2[$i_price]] + ($number_adult - $roomarray[$iCnt]['base_adult_occupancy']) * $roomarray[$iCnt]['extra_adult_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]]; //Chandrakant - 1.0.53.61 - 21 December 2017
													   }
													   else
													   {
															//Chandrakant - 2.0.16 - 22 April 2019 - START
															//Purpose : CEN-1032 - Need to show total rate with PAX price day wise.
															$total_pricepax = $roomarray[$iCnt]['room_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]];
														
															$total_price += $total_pricepax;
														   //Chandrakant - 2.0.16 - 22 April 2019 - END
														   
														   $night_price+=$roomarray[$iCnt]['room_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]];
														   
														   $total_exclusivetax_price+=$night_priceGST+=$roomarray[$iCnt]['room_rates_info']['exclusivetax_baserate'][$arrDateRange2[$i_price]]; //Chandrakant - 1.0.53.61 - 21 December 2017
													   }
													}
													# Sheetal Panchal - 1.0.52.60 - 3rd Aug 2017, Purpose - For Non linear rate mode setting - END
												}
											  # Sheetal Panchal - 1.0.52.60 - 3rd Aug 2017, Purpose - For Non linear rate mode setting - START
											  if($rate_mode=='NONLINEAR')
											  {
												if(7<$number_child)
												{
													if(isset($roomarray[$iCnt]['room_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]])) //Pinal - to avoid undefined index error
													{
														//Chandrakant - 2.0.16 - 22 April 2019 - START
														//Purpose : CEN-1032 - Need to show total rate with PAX price day wise.
														$total_pricepax =($number_child - 7) * $roomarray[$iCnt]['extra_child_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]];
														
														$total_price +=$total_pricepax;
														//Chandrakant - 2.0.16 - 22 April 2019 - END
														
														$night_price +=($number_child - 7) * $roomarray[$iCnt]['extra_child_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]];
														
														$total_exclusivetax_price+=$night_priceGST +=($number_child - 7) * $roomarray[$iCnt]['extra_child_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]]; //Chandrakant - 1.0.53.61 - 21 December 2017
													}
													
													$extrachild=($number_child - $roomarray[$iCnt]['base_child_occupancy']); //Pinal
													//$this->log->logIt(" night_price== : ". $night_price );
												}
											  }
											  else
											  { //linear case
												 if($roomarray[$iCnt]['base_child_occupancy']<$number_child)
												 {
													if(isset($roomarray[$iCnt]['room_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]])) //Pinal - to avoid undefined index error
													{
														//Chandrakant - 2.0.16 - 22 April 2019 - START
														//Purpose : CEN-1032 - Need to show total rate with PAX price day wise.
														$total_pricepax = ($number_child - $roomarray[$iCnt]['base_child_occupancy']) * $roomarray[$iCnt]['extra_child_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]];
														
														$total_price += $total_pricepax;
														//Chandrakant - 2.0.16 - 22 April 2019 - END
														
														$night_price +=($number_child - $roomarray[$iCnt]['base_child_occupancy']) * $roomarray[$iCnt]['extra_child_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]];
														
														$total_exclusivetax_price+=$night_priceGST +=($number_child - $roomarray[$iCnt]['base_child_occupancy']) * $roomarray[$iCnt]['extra_child_rates_info']['exclusive_tax'][$arrDateRange2[$i_price]]; //Chandrakant - 1.0.53.61 - 21 December 2017
													}
													 
													 $extrachild=($number_child - $roomarray[$iCnt]['base_child_occupancy']); //Pinal
													 //$this->log->logIt(" night_price== : ". $night_price );
												 }
											  }
												# Sheetal Panchal - 1.0.52.60 - 3rd Aug 2017, Purpose - For Non linear rate mode setting - END
	
												$pernighttax_tmp=0; //Pinal
												if($RoomRevenueTax!='' && isset($arrDateRange2[$i_price]) && isset($taxdata[$arrDateRange2[$i_price]]))
												{
												   # Sheetal Panchal - 1.0.52.60 - 11th Aug 2017, Purpose - For Non linear rate mode setting - START
			
													  if($rate_mode=='NONLINEAR')
													  {
														 if($number_adult > $roomarray[$iCnt]['base_adult_occupancy'])
															   $ExtraAdult_rackratetax=$number_adult - $roomarray[$iCnt]['base_adult_occupancy'];
														 if($number_child > $roomarray[$iCnt]['base_child_occupancy'])
															   $ExtraChild_rackratetax=$number_child - $roomarray[$iCnt]['base_child_occupancy'];
															   
														//Chandrakant - 1.0.53.61 - 21 December 2017 - START
														//Purpose : Added if else section for GST India related changes. 
														//if($FindSlabForGSTIndia==1 && $packdealtype!='FIXED' && $LayoutTheme==2)
														if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
														{
															$pernighttax_tmp=$ObjProcessDao->calculateTax($night_priceGST, $RoomRevenueTax, $arrDateRange2[$i_price],$taxdata[$arrDateRange2[$i_price]],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base',$ExtraAdult_rackratetax,$ExtraChild_rackratetax,$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode);
														}
														else
														{
														//Chandrakant - 1.0.53.61 - 21 December 2017 - END
															$pernighttax_tmp=$ObjProcessDao->calculateTax($night_price, $RoomRevenueTax, $arrDateRange2[$i_price],$taxdata[$arrDateRange2[$i_price]],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base',$ExtraAdult_rackratetax,$ExtraChild_rackratetax,$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode); //Pinal - fixed issue of tax apply on rack rate
														}
			
													  }
													  else
													  {
														 //Chandrakant - 1.0.53.61 - 21 December 2017 - START
														 //Purpose : Added if else section for GST India related changes.
														 //if($FindSlabForGSTIndia==1 && $packdealtype!='FIXED' && $LayoutTheme==2)
														 if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
														 {
															$pernighttax_tmp=$ObjProcessDao->calculateTax($night_priceGST, $RoomRevenueTax, $arrDateRange2[$i_price],$taxdata[$arrDateRange2[$i_price]],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base',$extraadult,$extrachild,$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode);
														 }
														 else
														 {
														 //Chandrakant - 1.0.53.61 - 21 December 2017 - END
															$pernighttax_tmp=$ObjProcessDao->calculateTax($night_price, $RoomRevenueTax, $arrDateRange2[$i_price],$taxdata[$arrDateRange2[$i_price]],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base',$extraadult,$extrachild,$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child,$rate_mode); //Pinal - fixed issue of tax apply on rack rate
														 }
			
													  }
														 # Sheetal Panchal - 1.0.52.60 - 11th Aug 2017, Purpose - For Non linear rate mode setting - END
														//Pinal - 1.0.49.54 - 2 July 2016 - START
														//Purpose - Issue : Wrong adjustment
														# Sheetal Panchal - 1.0.52.60 - 5th Aug 2017, Purpose - For Non linear rate mode setting , Pass rate mode
														$pernighttax=$pernighttax+$pernighttax_tmp;
														$pernighttaxpax=$pernighttax_tmp; //Chandrakant - 2.0.16 - 22 April 2019 //Purpose : CEN-1032
														//$pernighttax=$pernighttax+$ObjProcessDao->calculateTax($night_price, $RoomRevenueTax, $arrDateRange2[$i_price],$taxdata[$arrDateRange2[$i_price]],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$HotelCode,$number_adult,$number_child);//Old code
														//Pinal - 1.0.49.54 - 2 July 2016 - END
												}
												
												$findround=$pernighttax_tmp+$night_price; //Pinal - 1.0.49.54 - 2 July 2016 - Purpose - Issue : Wrong adjustment
												//$findround=$pernighttax+$night_price; //Old code
												
												$total_roundval=$total_roundval+$ObjProcessDao->getRoundVal($findround,false,$HotelCode,$digits_after_decimal);//adjustment
												
												//Chandrakant - 2.0.16 - 22 April 2019 - START
												//Purpose : CEN-1032 - Need to show total rate with PAX price day wise.
												$total_roundvalpax=$ObjProcessDao->getRoundVal($findround,false,$HotelCode,$digits_after_decimal);//adjustment
												
												$roomarray[$iCnt]['Total_Price_ExtraPax'][$arrDateRange2[$i_price]]=round($total_pricepax+$pernighttaxpax+$total_roundvalpax,$display_decimalplace);
												//Chandrakant - 2.0.16 - 22 April 2019 - END
											}
											
											$roomarray[$iCnt]['Total_Price']=$total_price;
											$roomarray[$iCnt]['Total_ExclusiveTax_Price']=$total_exclusivetax_price;
											$roomarray[$iCnt]['Total_Tax']=$pernighttax;
											$roomarray[$iCnt]['Total_Adjusment']=round($total_roundval,$display_decimalplace);
											$roomarray[$iCnt]['Final_Total_Price']=round($total_price+$pernighttax+$total_roundval,$display_decimalplace);
											$roomarray[$iCnt]['Avg_Strike_Rate_Exclusive_Tax']=array_sum($BeforeDiscountRate)/count($BeforeDiscountRate); //Pinal - 9 February 2019 - Purpose : Multiple property listing get strike data.
										}
										//Summary calculation - end - flora
										
										$roomarray[$iCnt]['min_nights']=$minimum_nights_arr;
										//kishan Tailor 30th Oct 2020 purpose:need of Stopesell,COA and COD Data START
										$roomarray[$iCnt]['stopsells']=$stopesell_array;
										$roomarray[$iCnt]['close_on_arrival']=$coa_array;
										$roomarray[$iCnt]['close_on_dept']=$cod_array;
										//kishan Tailor 30th Oct 2020 END
										$roomarray[$iCnt]['Hotel_amenities']=json_encode($hotelamenities);
										
										if($Iscrs_HotelList == 0)//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
										{
											if(!empty($minimum_nights_arr))
												$roomarray[$iCnt]['Avg_min_nights']=max($minimum_nights_arr); //Pinal - changed from min to max.
											
											//Chinmay Gandhi - 1.0.52.60 - Add maximum nights - Start
											$roomarray[$iCnt]['max_nights']=$maximum_nights_arr;
											
											if(!empty($maximum_nights_arr))
												$roomarray[$iCnt]['Avg_max_nights']=min($maximum_nights_arr);
											//Chinmay Gandhi - 1.0.52.60 - Add maximum nights -End
										
											//kishan Tailor - 1.0.53.61/0.02 - purpose:hotel checkin & check out time SATRT
											$roomarray[$iCnt]['check_in_time']=$chk_in_time;
											$roomarray[$iCnt]['check_out_time']=$chk_out_time;
											$roomarray[$iCnt]['TaxName']=$taxnamearray['TaxName'];
											//kishan Tailor - 1.0.53.61/0.02 - purpose:hotel checkin & check out time END
											
											$roomarray[$iCnt]['ShowPriceFormat'] = ($WebShowRatesAvgNightORWholeStay==2)?'Price for Whole stay':'Average Per Night Rate';
											$roomarray[$iCnt]['DefaultDisplyCurrencyCode'] = $WebDefaultCurrency;
																	 
											$roomarray[$iCnt]['deals'] = (isset($rmdetail->deals) && $rmdetail->deals!='')?$rmdetail->deals:'';
											//kishan Tailor 22 sep 2020 purpose:Night Limit & package percentage display RES-2610 START
											if(isset($rmdetail->deals) && $rmdetail->deals!='' && isset($_REQUEST['showavgpercentdiscount']) && $_REQUEST['showavgpercentdiscount']==1)
											{
												if(isset($nightcount[0]) && $nightcount[0]=='PERCENTDISCOUNT')
													$roomarray[$iCnt]['discount_caption']=round($pack_avg_discount/$number_night,$digits_after_decimal)."% Discount";
												elseif(isset($nightcount[0]) && $nightcount[0]=='PACKAGEDEAL')
												{
													if(isset($nightcount[4]) && $nightcount[4]=='PER')
														$roomarray[$iCnt]['discount_caption']=round($pack_avg_discount/$number_night,$digits_after_decimal)."% Discount";
												}
												else
												{
													$roomarray[$iCnt]['discount_caption']="";
												}
											}
											//kishan Tailor 22 sep 2020 END
											
											$roomarray[$iCnt]['IsPromotion'] = $rmdetail->promotion;
											$roomarray[$iCnt]['Promotion_Code'] = $rmdetail->promotioncode;
											$roomarray[$iCnt]['Promotion_Description'] = $rmdetail->promotiondesc;
											$roomarray[$iCnt]['Promotion_Name'] = $rmdetail->promotionname;
											$roomarray[$iCnt]['Promotion_Id'] = $rmdetail->promotionunkid;
											
											$roomarray[$iCnt]['Package_Name'] = isset($rmdetail->specialname)?$rmdetail->specialname:'';
											$roomarray[$iCnt]['Package_Id'] = isset($rmdetail->specialunkid)?$rmdetail->specialunkid:'';
											$roomarray[$iCnt]['Package_Description'] = isset($rmdetail->splshortdesc)?$rmdetail->splshortdesc:'';
										}
										
										$roomarray[$iCnt]['currency_code'] = $rmdetail->curr_code;
										$roomarray[$iCnt]['currency_sign'] = $currency;
										
										//Pinal - Overlap and exactly match with nights case
										/*START*/
										if(isset($rmdetail->specialunkid) && isset($rmdetail->specialunkid))
										{
											 $packagedeal[$iCnt]=$rmdetail->roomrateunkid;
											 $packagedealids[$iCnt]=$rmdetail->specialunkid; //Pinal - 10 May 2018
										}
										
										//if(isset($rmdetail->hiderateplan) && $rmdetail->hiderateplan=='1')//it was not showing hide associated rate plan on add reservation screen
										//if(isset($rmdetail->hiderateplan) && $rmdetail->hiderateplan=='1' && ($notdefinedstay=='' && $notdefinedstay=='false'))//flora - solved issue for hiding rate plans for case of availability calender in add reservation case
										if(isset($rmdetail->hiderateplan) && $rmdetail->hiderateplan=='1') //Pinal - 4 May 2018 - Purpose : Removed condition of $notdefinedstay according to condition added it was never going to be true and this was creating issue in api in meta search.
										{
											 $hiderateplan[$iCnt]=$rmdetail->roomrateunkid;
										}
										/*END*/
										
										if($Iscrs_HotelList == 0)//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
										{
											$roomarray[$iCnt]['localfolder'] = $hotelId;
											$roomarray[$iCnt]['CalDateFormat'] = $cal_date_format;
											$roomarray[$iCnt]['ShowTaxInclusiveExclusiveSettings'] = $rmdetail->ShowTaxInclusiveExclusiveSettings;
											$roomarray[$iCnt]['hidefrommetasearch'] = $rmdetail->hidefrommetasearch;
											$roomarray[$iCnt]['prepaid_noncancel_nonrefundable'] = $rmdetail->is_prepaid_noncancel_nonrefundabel;
											
											$roomarray[$iCnt]['cancellation_deadline'] = $rmdetail->cancellation_deadline;
											$roomarray[$iCnt]['digits_after_decimal'] = $rmdetail->decimal; //Pinal
											$roomarray[$iCnt]['visiblity_nights'] = $shownight; //Pinal
											$roomarray[$iCnt]['BookingEngineURL'] = $serverurl."book-rooms-".$hotelId; #Chinmay Gandhi - 1.0.53.61 - HTTPS Migration - HTTPSAutoMigration//Pinal
										
											$roomAmenities=$ObjProcessDao->getRoomAmenities($hotel_id,$rmdetail->amenities,$lang_code); //Pinal - 27 December 2018 - Purpose : Multiple property listing
											
											$amelist='';$ameidlist=array();//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
											foreach($roomAmenities as $ame)
											{
												$amelist.=stripslashes($ame['amenity']).",";
												$ameidlist[$ame['amenityunkid']] = stripslashes($ame['amenity']);//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
											}
											$roomarray[$iCnt]['RoomAmenities'] = trim($amelist,",");
											
											if($Iscrs_RoomList == 1)//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
												$roomarray[$iCnt]['RoomAmenitiesId'] = json_encode($ameidlist);
										
											$imagedata = $imagedata1 = $imagedata2 = array();									
											$imagedata=$ObjProcessDao->getImageList($rmdetail->roomtypeunkid,imageobjecttype::RoomType);
											$imagedata1=$ObjProcessDao->getImageList($rmdetail->roomrateunkid,imageobjecttype::RatePlan);
											$imagedata2 = array_merge($imagedata,$imagedata1);
											
											$ObjImageDao=new imagedao();
											$firstphoto=0;
											$fillImgs=array();
											$imgCnt=0;
											$num=rand();
											
											foreach($imagedata2 as $data)
											{
												if(isset($data['image_path']) && $data['image_path']!=''){
													$img=$ObjImageDao->getImageFromBucket($data['image_path'],'API');//Jemin Added "API" for use  https API in image url  												
													if($firstphoto==0)		
													{							
														$roomarray[$iCnt]['room_main_image'] = $img;
														$fillImgs[$imgCnt]['room_main_image']=$img;
														$firstphoto++;
													}
													
													//$fillImgs[$imgCnt]['id']=$data['imageunkid'];
													//$fillImgs[$imgCnt]['idx']=$imgCnt;
													$fillImgs[$imgCnt]['image']=$img;											
													///$fillImgs[$imgCnt]['num']=$num;	
													//$fillImgs[$imgCnt]['image_path']=$data['image_path'];										
																	
													//if($imgCnt==0)
													//{
													//	$fillImgs[$imgCnt]['setclass']='activeimg thumb_highlight';
													//}
													//else
													//	$fillImgs[$imgCnt]['setclass']='activeimg';								
													$imgCnt++;										
												}
											}									
											if(!empty($fillImgs))
											{
												$roomarray[$iCnt]['RoomImages'] = $fillImgs;
												//$roomarray[$iCnt]['roomimage_json'] =json_encode(json_decode(json_encode($fillImgs),true));								
											}
											else
												$roomarray[$iCnt]['room_main_image'] = '';
										}
											#$this->log->logIt($room_images);
										if($this->iscallfromchatbot) //Pinal - 7 September 2018 - Purpose : BE Chat Bot
										{
											$roomarray[$iCnt]['disppromodescription'] = (isset($rmdetail->disppromodescription) && $rmdetail->disppromodescription!=='')?$rmdetail->disppromodescription:'';
											$roomarray[$iCnt]['number_of_booking'] = (isset($rmdetail->number_of_booking) && $rmdetail->number_of_booking!=='')?$rmdetail->number_of_booking:'';
											$roomarray[$iCnt]['specialhighlightinclusion'] = (isset($rmdetail->specialhighlightinclusion) && $rmdetail->specialhighlightinclusion!=='')?$rmdetail->specialhighlightinclusion:'';
											
											if($promotioncode!='' && $rmdetail->promotion==1)
											{
												$ispromoappliedvalid=true;
											}
										}
											$iCnt++;										
										$iIndex++;
									}										
								}
							}
							//Pinal - Overlap and exactly match with nights case
							/*START*/
							//Pinal - 5 May 2018 - START
							//Purpose : Issue of hidefrombookingengine not being considered due to this there is mismatch in listing on API and BE.
							if($metasearch=='GHF' || (isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1'))//Chinmay Gandhi - 30th Oct 2019 - Hide this rate plan from Booking Engine in MHBE Case [ RES-2281 ]
							{
								$findhidebe=implode(array_values($hiderateplan),',');
								$result_hidebe=$ObjProcessDao->gethidefrombookingengine($findhidebe,$hcode);
								
								foreach($hiderateplan as $index=>$rateplanid)
								{
									if(in_array($rateplanid,$packagedeal) || (isset($result_hidebe[$rateplanid]) && $result_hidebe[$rateplanid]==1))
									{
										//Chandrakant - 29 January 2021 - START
										//Purpose : RES-2723 - display rate plan even if its hide for booking engine
										if(isset($_REQUEST['isbookongoogle']) && $_REQUEST['isbookongoogle']==1)
											$roomarray[$index]['hideitfrombookingengine']=1;
										else
										//Chandrakant - 29 January 2021 - END
											unset($roomarray[$index]);
									}
								}
								
								if($metasearch=='GHF')//Chinmay Gandhi - 30th Oct 2019 - Hide this rate plan from Booking Engine in MHBE Case [ RES-2281 ]
								{
									//Pinal - 10 May 2018 - START
									//Purpose : Issue of hiderateplan when stay is not defined for GHF.
									$gethideassociated=implode(array_values($packagedealids),',');
									$result_hideassoc=$ObjProcessDao->gethideassociatedrareplan($gethideassociated,$hcode);
									
									foreach($packagedealids as $index=>$packid)
									{
										$roomarray[$index]['hiderateplan']=$result_hideassoc[$packid];
									}
									//Pinal - 10 May 2018 - END
								}
							}
							else
							{
								if($notdefinedstay!='API')
								{
									foreach($hiderateplan as $index=>$rateplanid)
									{
										if(in_array($rateplanid,$packagedeal))
										{
											unset($roomarray[$index]);
										}
									}
								}
							}
							//Pinal - 5 May 2018 - END
							$roomarray=array_values($roomarray);
							
							//Pinal - 29 March 2019 - START
							//Purpose : Displaying packge as rate type Multiple property listing
							if($Iscrs_RoomList == 1)
							{
								$packages_as_ratetype_map=array();
								
								$cnt_pkg=0;
								foreach($packages_as_ratetype as $idx=>$pkgs)
								{
									if(count($pkgs)>1)
									{
										foreach($pkgs as $pkgid)
										{
											$packages_as_ratetype_map[$pkgid]=$cnt_pkg;
										}
										$cnt_pkg++;
									}
								}
								
								$roomarray[0]['Packages_as_Ratetype'] = $packages_as_ratetype_map;
							}
							unset($packages_as_ratetype,$packages_as_ratetype_map);
							//Pinal - 29 March 2019 - END
							
							//if($showlog==true){ echo "<pre>";print_r($roomarray);echo "</pre>";}
							
							//Pinal - 5 September 2018 - START
							//Purpose : BE Chat Bot
							$isbookedtoday=false;
							if($this->iscallfromchatbot)
							{
								if($promotioncode!='' && !$ispromoappliedvalid)
								{
									$error_response[]=array('Error Details'=>array(
										"Error_Code" => "PromocodeInvalid",
										"Error_Message" => "Applied promotion code is not valid!")
										);
									
									return ($error_response);
								}
								else
								{
									if(isset($_REQUEST['findclosestavail']) && $_REQUEST['findclosestavail']=='true')
									{
										$range=util::generateDateRange($ArrvalDt,date('Y-m-d', strtotime("+14 day", strtotime($ArrvalDt))));
										$calendarDateFormat=$ObjProcessDao->getreadParameter('WebAvailabilityCalDateFormat',$Hotel_Code);
										$num_nights_stay=$_REQUEST['staynights'];
										$clo_data=array();
										$ArrivalDate=$_REQUEST['todaydate'];
										
										if($ArrivalDate!='')
										{
											list($checkin_year,$checkin_month,$checkin_day)=explode("-",$ArrivalDate);								
											$checkout_selection_date=date('Y-m-d',mktime(0,0,0,$checkin_month,$checkin_day+($num_nights_stay),$checkin_year));
										}
										foreach($roomarray as $r)
										{
											$cnt=0;
											foreach($range as $date)
											{
												$checkdate_from=$date;
												$comparedate = date('Y-m-d', strtotime("+".$num_nights_stay." day", strtotime($date)));
												$range2=util::generateDateRange($checkdate_from,$comparedate);
												
												$available=0;
												for($idatecmp1=0;$idatecmp1<$num_nights_stay;$idatecmp1++)
												{											
													if(isset($range2[$idatecmp1]) && isset($r["available_rooms"][$range2[$idatecmp1]]) && $r["available_rooms"][$range2[$idatecmp1]]>0)
													{
														$available++;
													}
													
													if($available==($num_nights_stay) && $cnt<3)
													{
														$from_date=date('M j', (strtotime($checkdate_from)));
														$to_date=date('M j', (strtotime($comparedate)));
														
														$link_start_date=util::Config_Format_Date($checkdate_from,staticarray::$webcalformat_key[$calendarDateFormat]);
														$link_end_date=util::Config_Format_Date($comparedate,staticarray::$webcalformat_key[$calendarDateFormat]);
														
														if($ArrivalDate!='' && isset($checkout_selection_date) && $checkout_selection_date!='')
														{
															if($ArrivalDate!=$checkdate_from && $checkout_selection_date!=$comparedate)
															{
																$clo_data[$cnt]['DatesDisplay']=$from_date." - ".$to_date;
																$clo_data[$cnt]['Date']=$link_start_date."^".$link_end_date;
																
																$cnt++;
															}
														}
													}
													
													if($cnt==3)
													{
														break 2;
													}
												}
											}
										}
										
										return $clo_data;
									}
									else
									{
										$chatbotarray=array();
										$totalroomsavailable=0;
										$disppromodescription=array();
										$dispshighlightincl=array();
										$tmp_specialinclusion=array();
										$tmp_specialinclusion_all=array();
										$dftext='';
										
										$WebAvailabilityDisplayMaxPax=$ObjProcessDao->getreadParameter('WebAvailabilityDisplayMaxPax',$Hotel_Code);
										
										$ShowTaxInclusiveExclusiveSettings=$ObjProcessDao->getreadParameter('ShowTaxInclusiveExclusiveSettings',$Hotel_Code);
										foreach($roomarray as $rarray)
										{
											if($rarray['roomtypeunkid']=='')
											{
												continue;
											}
											
											$isavailable=true;
											$findrooms=array();
											foreach($rarray['available_rooms'] as $avail)
											{
												if($avail<1)
												{
													$isavailable=false;
													break;
												}
												array_push($findrooms,$avail);
											}
											if($isavailable)
											{
												$chatbotarray[$rarray['roomtypeunkid']]['Roomtype_Name']=$rarray['Roomtype_Name'];
												$chatbotarray[$rarray['roomtypeunkid']]['room_main_image']=$rarray['room_main_image'];
												
												if($WebAvailabilityDisplayMaxPax=='Y')
												{
													$chatbotarray[$rarray['roomtypeunkid']]['max_adult_occupancy']=$rarray['max_adult_occupancy'];
													if($showchild=='true')
														$chatbotarray[$rarray['roomtypeunkid']]['max_child_occupancy']=$rarray['max_child_occupancy'];
												}
												
												if($ShowTaxInclusiveExclusiveSettings=='1')
												{
													if(isset($chatbotarray[$rarray['roomtypeunkid']]['Final_Total_Price']))
													{
														if($rarray['Final_Total_Price']<$chatbotarray[$rarray['roomtypeunkid']]['Final_Total_Price'])
															$chatbotarray[$rarray['roomtypeunkid']]['Final_Total_Price']=$rarray['Final_Total_Price'];
													}
													else
													{
														$chatbotarray[$rarray['roomtypeunkid']]['Final_Total_Price']=$rarray['Final_Total_Price'];
														$totalroomsavailable+=min($findrooms);
													}
												}
												else
												{
													if(isset($chatbotarray[$rarray['roomtypeunkid']]['Final_Total_Price']))
													{
														if($rarray['Total_ExclusiveTax_Price']<$chatbotarray[$rarray['roomtypeunkid']]['Final_Total_Price'])
															$chatbotarray[$rarray['roomtypeunkid']]['Final_Total_Price']=$rarray['Total_ExclusiveTax_Price'];
													}
													else
													{
														$chatbotarray[$rarray['roomtypeunkid']]['Final_Total_Price']=$rarray['Total_ExclusiveTax_Price'];
														$totalroomsavailable+=min($findrooms);
													}
												}
												
												$chatbotarray[$rarray['roomtypeunkid']]['inventory']=min($findrooms);
												$chatbotarray[$rarray['roomtypeunkid']]['currency_sign']=$rarray['currency_sign'];
												if(!isset($chatbotarray[$rarray['roomtypeunkid']]['number_of_booking']))
													$chatbotarray[$rarray['roomtypeunkid']]['number_of_booking']=0;
													
												$chatbotarray[$rarray['roomtypeunkid']]['number_of_booking']+=$rarray['number_of_booking'];
												
												if($chatbotarray[$rarray['roomtypeunkid']]['number_of_booking']>0)
												{
													$isbookedtoday=true;
												}
												
												if($rarray['disppromodescription']!='')
												{
													$disp=explode("<:123:>",$rarray['disppromodescription']);
													foreach($disp as $d)
													{
														$disppromodescription[]=$d;
													}
												}
												
												if($rarray['specialhighlightinclusion']!='')
												{
													$disp=explode(" , ",$rarray['specialhighlightinclusion']);
													foreach($disp as $d)
													{
														if(!in_array($d,$dispshighlightincl))
															$dispshighlightincl[]=$d;
															
														$tmp_specialinclusion[$rarray['roomtypeunkid']][]=$d;
													}
													if(!isset($chatbotarray[$rarray['roomtypeunkid']]['specialhighlightinclusion']))
														$chatbotarray[$rarray['roomtypeunkid']]['specialhighlightinclusion']=array(); //Parth - RISE-409 - 01 Aug 2020 - For not getting room & rates data defining as an array.
													//$chatbotarray[$rarray['roomtypeunkid']]['specialhighlightinclusion'].=$rarray['specialhighlightinclusion']." , ";
													$chatbotarray[$rarray['roomtypeunkid']]['specialhighlightinclusion'][]=$rarray['specialhighlightinclusion'];
													$chatbotarray[$rarray['roomtypeunkid']]['Final_Total_Price_All'][]=$rarray['Final_Total_Price'];
												}
												
												$chatbotarray[$rarray['roomtypeunkid']]['IsPromotion']=($rarray['IsPromotion'])?true:false;
												
											}
											
										}
										
										function sortByOrder($a, $b) {
											return $a['Final_Total_Price'] - $b['Final_Total_Price'];
										}
										
										$sortlowtohigh=$chatbotarray;
										usort($sortlowtohigh, 'sortByOrder');
										
										function sortByOrder2($a, $b) {
											return $b['Final_Total_Price'] - $a['Final_Total_Price'];
										}
										
										$sorthightolow=$chatbotarray;
										usort($sorthightolow, 'sortByOrder2');
										
										$sorttodaypopular=array();
										if($isbookedtoday)
										{
											function sortByOrder3($a, $b) {
												return $b['number_of_booking'] - $a['number_of_booking'];
											}
											
											$sorttodaypopular=$sortlowtohigh;
											usort($sorttodaypopular, 'sortByOrder3');
										}
										
										$defaulttextdata='';
										$highlight_filter=array();
										$finallisting_inc=array();
										if(isset($_REQUEST['specialhighlightfilter']) && $_REQUEST['specialhighlightfilter']=='true')
										{	
											foreach($tmp_specialinclusion as $index=>$spinc)
											{
												$tmp_specialinclusion[$index]=array_unique($spinc);
											}
											
											if(!empty($tmp_specialinclusion))
												$dftext=call_user_func_array("array_intersect",$tmp_specialinclusion);
												
											$defaulttextdata='';
											if($dftext!='')
											{
												foreach($dftext as $dtext)
												{
													$pos=array_search($dtext,$dispshighlightincl);
													unset($dispshighlightincl[$pos]);
												}
											
												$defaulttextdata=implode(" , ",$dftext);
											}
											
											foreach($dispshighlightincl as $disp_inclu)
											{
												if(preg_match('/breakfast/',strtolower($disp_inclu)) || preg_match('/lunch/',strtolower($disp_inclu)) || preg_match('/dinner/',strtolower($disp_inclu)))
												{
													$finallisting_inc[]=$disp_inclu;
												}
											}
											
											$finallisting_inc=array_unique($finallisting_inc);
											$cnt=count($finallisting_inc);
											if($cnt<5)
											{
												foreach($dispshighlightincl as $disp_inclu)
												{
													if(!(preg_match('/breakfast/',strtolower($disp_inclu)) || preg_match('/lunch/',strtolower($disp_inclu)) || preg_match('/dinner/',strtolower($disp_inclu))))
													{
														$finallisting_inc[]=$disp_inclu;
													}
													if(count($finallisting_inc)==5)
													{
														break;
													}
												}
											}
											
											foreach($chatbotarray as $rmtype=>$charray)
											{
												if(isset($charray['specialhighlightinclusion']) && $charray['specialhighlightinclusion']!='' && count($charray['specialhighlightinclusion'])>0)
												{
													foreach($charray['specialhighlightinclusion'] as $incindex=>$linc)
													{
														$disp=explode(" , ",trim($linc," , "));
														if(count($disp)>0)
														{
															foreach($finallisting_inc as $index=>$inc)
															{
																if(in_array($inc,$disp))
																{
																	$highlight_filter[$index][$rmtype]=$charray;
																	
																	if(isset($highlight_filter[$index][$rmtype]['new_Final_Total_Price']))
																	{
																		if($charray['Final_Total_Price_All']<$highlight_filter[$index][$rmtype]['new_Final_Total_Price'])
																			$highlight_filter[$index][$rmtype]['new_Final_Total_Price']=$charray['Final_Total_Price_All'][$incindex];
																	}
																	else
																	{
																		$highlight_filter[$index][$rmtype]['new_Final_Total_Price']=$charray['Final_Total_Price_All'][$incindex];
																	}
																}
															}
														}
													}
												}
											}
											
											unset($disp,$dispshighlightincl,$tmp_specialinclusion,$tmp_specialinclusion_all);
										}
										
										unset($isbookedtoday);
										
										return array("roomlisting"=>$chatbotarray,"totalroomsavailable"=>$totalroomsavailable,"disppromodescription"=>array_unique($disppromodescription),"roomlisting_L2H"=>$sortlowtohigh,"roomlisting_H2L"=>$sorthightolow,"roomlisting_todaypopular"=>$sorttodaypopular,"defaulttextdata"=>$defaulttextdata,"filters"=>$finallisting_inc,"highlight_filter"=>$highlight_filter,"ShowTaxInclusiveExclusiveSettings"=>$ShowTaxInclusiveExclusiveSettings);
									}
								}
							}
							//Pinal - 5 September 2018 - END
						}//if						
					}
				}
				else
				{
					$error_response=$this->ShowErrors('EmptyParameter');					
					echo $error_response;
					exit(0);
				}				
			}
			else
			{
				$error_response=$this->ShowErrors('InvalidHotelCode');				
				echo $error_response;
				exit(0);
			}
			
			//Pinal - 6 February 2019 - START
			//Purpose : Multiple property listing API.
			if($getminrateonly == 1)
			{
				$RoomListingData=array();
				$RoomListingData['Hotel_amenities']=$roomarray[0]['Hotel_amenities'];
				$RoomListingData['currency_code']=$roomarray[0]['currency_code'];
				$RoomListingData['currency_sign']=$roomarray[0]['currency_sign'];
				
				foreach($roomarray as $roomdata)
				{
					$roomInv=$roomprice=$exadult=$exchild=$price_withmanagetax=$tax_rate=$tax_adult=$tax_child=$tax=0;
					$roomInv = 0;$isInv = true;
					
					for($InvFlag=0;$InvFlag<count($arrDateRange2)-1;$InvFlag++)
					{
						if($roomdata['available_rooms'][$arrDateRange2[$InvFlag]] != 0)
							$roomInv += $roomdata['available_rooms'][$arrDateRange2[$InvFlag]];
						else
							$isInv = false;
						
						if($roomdata['available_rooms'][$arrDateRange2[$InvFlag]] < $num_rooms)
							$isInv = false;
					}
					//$this->log->logIt($isInv." >==== is inv available");
					//$this->log->logIt($arrDateRange2);
					if($isInv)
					{
						//for($InvFlag=0;$InvFlag<count($arrDateRange2)-1;$InvFlag++)
						//{
						//	if(isset($roomdata['room_rates_info']['exclusivetax_baserate'][$arrDateRange2[$InvFlag]]) && $roomdata['room_rates_info']['exclusivetax_baserate'][$arrDateRange2[$InvFlag]] != 0 && sizeof($roomdata['room_rates_info']['exclusivetax_baserate']) > 0)
						//		$roomprice += ($roomdata['room_rates_info']['exclusivetax_baserate'][$arrDateRange2[$InvFlag]] / sizeof($roomdata['room_rates_info']['exclusivetax_baserate']));
						//	
						//	if(isset($roomdata['extra_adult_rates_info']['exclusive_tax'][$arrDateRange2[$InvFlag]]) && $roomdata['extra_adult_rates_info']['exclusive_tax'][$arrDateRange2[$InvFlag]] != 0 && sizeof($roomdata['extra_adult_rates_info']['exclusive_tax']) > 0)
						//		$exadult += ($roomdata['extra_adult_rates_info']['exclusive_tax'][$arrDateRange2[$InvFlag]] / sizeof($roomdata['extra_adult_rates_info']['exclusive_tax']));
						//	
						//	if(isset($roomdata['extra_child_rates_info']['exclusive_tax'][$arrDateRange2[$InvFlag]]) && $roomdata['extra_child_rates_info']['exclusive_tax'][$arrDateRange2[$InvFlag]] != 0 && sizeof($roomdata['extra_child_rates_info']['exclusive_tax']) > 0)
						//		$exchild += ($roomdata['extra_child_rates_info']['exclusive_tax'][$arrDateRange2[$InvFlag]] / sizeof($roomdata['extra_child_rates_info']['exclusive_tax']));
						//	
						//	if(isset($roomdata['room_rates_info']['tax'][$arrDateRange2[$InvFlag]]) && $roomdata['room_rates_info']['tax'][$arrDateRange2[$InvFlag]] != 0 && sizeof($roomdata['room_rates_info']['tax']) > 0)
						//		$tax_rate += ($roomdata['room_rates_info']['tax'][$arrDateRange2[$InvFlag]] / sizeof($roomdata['room_rates_info']['tax']));
						//	
						//	if(isset($roomdata['extra_adult_rates_info']['tax'][$arrDateRange2[$InvFlag]]) && $roomdata['extra_adult_rates_info']['tax'][$arrDateRange2[$InvFlag]] != 0 && sizeof($roomdata['extra_adult_rates_info']['tax']) > 0)
						//		$tax_adult += ($roomdata['extra_adult_rates_info']['tax'][$arrDateRange2[$InvFlag]] / sizeof($roomdata['extra_adult_rates_info']['tax']));
						//	
						//	if(isset($roomdata['extra_child_rates_info']['tax'][$arrDateRange2[$InvFlag]]) && $roomdata['extra_child_rates_info']['tax'][$arrDateRange2[$InvFlag]] != 0 && sizeof($roomdata['extra_child_rates_info']['tax']) > 0)
						//		$tax_child += ($roomdata['extra_child_rates_info']['tax'][$arrDateRange2[$InvFlag]] / sizeof($roomdata['extra_child_rates_info']['tax']));
						//}
						//
						//$tax = $roomdata['Total_Tax'];
						//
						//$lst_roomprice = 0;
						//$lst_roomprice_withtax = 0;
						//if($number_adult > $roomdata['base_adult_occupancy'] && $number_child > $roomdata['base_child_occupancy'])
						//	$lst_roomprice += ((float)$roomprice + ($exadult * ($number_adult - $roomdata['base_adult_occupancy'])) + ($exchild * ($number_child - $roomdata['base_child_occupancy'])));
						//else if($number_adult > $roomdata['base_adult_occupancy'] && $number_child <= $roomdata['base_child_occupancy'])
						//	$lst_roomprice += ((float)$roomprice + ($exadult * ($number_adult - $roomdata['base_adult_occupancy'])));
						//else if($number_adult <= $roomdata['base_adult_occupancy'] && $number_child > $roomdata['base_child_occupancy'])
						//	$lst_roomprice += ((float)$roomprice + ($exchild * ($number_child - $roomdata['base_child_occupancy'])));
						//else
						//	$lst_roomprice += (float)$roomprice;
						//	
						//$lst_roomprice_withtax=$lst_roomprice+$tax;
						
						//$lst_roomprice_withtax=getroundval(($roomdata['room_rates_info']['MinAvgPerNight']),$RoundOffType);
						$lst_roomprice=floatval($roomdata['room_rates_info']['MinAvgPerNight']);
					}
					
					if($isInv == false)
						$roomInv = 0;
					
					//Chinmay Gandhi - 21th Aug 2019 - Start
					//Purpose : Show room availability when minimum night is grather then daterange [ MHBE_SOLD_OUT_ISSUE ]
					//if((int)$roomdata['min_nights'][$eZ_chkin] > (int)$number_night)
					//	$roomInv = 0;
					//Chinmay Gandhi - 21th Aug 2019 - End
					
					if(!isset($RoomListingData['min_rate']))
						$RoomListingData['min_rate'] = 0;
					
					//if(!isset($RoomListingData['min_rate_tax']))
					//	$RoomListingData['min_rate_tax'] = 0;
						
					if(!isset($RoomListingData['available_room']))
						$RoomListingData['available_room'] = 0;
						
					if($roomInv > 0)
					{
						//if(floatval($RoomListingData['min_rate_tax']) > floatval($lst_roomprice_withtax) || floatval($RoomListingData['min_rate_tax'] == 0))
						//{
						//	$RoomListingData['min_rate_tax'] =  $lst_roomprice_withtax+$ObjProcessDao->getRoundVal(round($lst_roomprice_withtax,$digits_after_decimal),false,$Hotel_Code,$digits_after_decimal);
						//}
						
						if($RoomListingData['min_rate'] > $lst_roomprice || $RoomListingData['min_rate'] == 0)
						{
							if($isTaxInc == '1')//Chinmay Gandhi - Set Round off setting
								$RoomListingData['min_rate'] = $lst_roomprice+$ObjProcessDao->getRoundVal($lst_roomprice,false,$Hotel_Code,$digits_after_decimal);
							else
								$RoomListingData['min_rate'] = $lst_roomprice;
						}
						
						if($roomInv == 0)
							$RoomListingData['available_room'] = 0;
						else
							$RoomListingData['available_room'] = 1;
					}
				}
				
				//Chinmay Gandhi - Closest Availability Tool [ RES-2079 ] - Start
				$multiHotelRoomResult['RoomResult'] = $RoomListingData;
				$multiHotelRoomResult['closest_availability_flag'] = $WebClosestAvailability;
				return $multiHotelRoomResult;
				//Chinmay Gandhi - Closest Availability Tool [ RES-2079 ] - End
			}
			else
			{
				//Chinmay Gandhi - Closest Availability Tool [ RES-2079 ] - Start
				if($Iscrs_RoomList == 1)
				{
					$multiHotelRoomResult['RoomResult'] = $roomarray;
					$multiHotelRoomResult['closest_availability_flag'] = $WebClosestAvailability;
					return $multiHotelRoomResult;
				}//Chinmay Gandhi - Closest Availability Tool [ RES-2079 ] - End
				else
				{
					return $roomarray;
				}
			}
			//Pinal - 6 February 2019 - END
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('RoomListingError');			
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "getRoomList - " . $e);
			$this->handleException($e);
		}
	}
	
        private function connectDB($server, $dbname) {
            try
			{
				if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs'] == '1')
					return 1;
				
                $this->log->logIt(get_class($this) . "-" . "connectDB".$server."==".$dbname);
					 
				//Shweta - 19th June 2017 : 1.0.52.60 - START
				//Purpose : to make mysql credentails ecrypt instead of writing as palin text
					$projectPath = dirname(__FILE__);
					require $projectPath."/../../common_connection.php";
				// END
			
                $flag = true;
                if ($server != '' && $dbname != '') {
                    switch ($server) {
                        case 'local':
                            $serverurl = "http://" . $_SERVER['HTTP_HOST'] . "/booking/";
                            $host = $commonDbConnection['dbConnection']['local']['mysqlhost'];
                            $username = $commonDbConnection['dbConnection']['local']['mysqluser'];
                            $password = $commonDbConnection['dbConnection']['local']['mysqlpwd'];
							
							$repl_host = $commonDbConnection['dbConnection']['local']['repl_host'];
							$repl_username =  $commonDbConnection['dbConnection']['local']['repl_username'];
							$repl_password = $commonDbConnection['dbConnection']['local']['repl_password'];
                            break;
                        case 'livetest':
                            $serverurl = (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) ? (strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"]) == "https" ? "https://" : "http://") : "http://") . $_SERVER['HTTP_HOST'] . "/";
                            $host = $commonDbConnection['dbConnection']['demoserver']['mysqlhost'];
                            $username = $commonDbConnection['dbConnection']['demoserver']['mysqluser'];
                            $password =  $commonDbConnection['dbConnection']['demoserver']['mysqlpwd'];
							
							$repl_host = $commonDbConnection['dbConnection']['demoserver']['repl_host'];
							$repl_username = $commonDbConnection['dbConnection']['demoserver']['repl_username'];
							$repl_password = $commonDbConnection['dbConnection']['demoserver']['repl_password'];
                            break;
                        case 'livelocal':
                            $serverurl = (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) ? (strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"]) == "https" ? "https://" : "http://") : "http://") . $_SERVER['HTTP_HOST'] . "/";
                            $host = $commonDbConnection['dbConnection']['liveserver']['mysqlhost'];
                            $username =  $commonDbConnection['dbConnection']['liveserver']['mysqluser'];
                            $password = $commonDbConnection['dbConnection']['liveserver']['mysqlpwd'];
							
							$repl_host = $commonDbConnection['dbConnection']['liveserver']['repl_host'];
							$repl_username = $commonDbConnection['dbConnection']['liveserver']['repl_username'];
							$repl_password =  $commonDbConnection['dbConnection']['liveserver']['repl_password'];
                            break;
                        case 'liverds':
                            $serverurl = "https://" . $_SERVER['HTTP_HOST'] . "/"; //Chirag 19th july 2019 http-https migration[PCIDSSChanges]
                            $host = $commonDbConnection['dbConnection']['liverds']['mysqlhost'];
                            $username =$commonDbConnection['dbConnection']['liverds']['mysqluser'];
                            $password = $commonDbConnection['dbConnection']['liverds']['mysqlpwd'];
                            break;
                        default:
                            $flag = false;
                    }
					
                    //old
                    //global $connection;
                    //$connection=new PDO("mysql:host=".$host.";dbname=". $dbname,$username,$password);	
                    //$connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
                    //$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    //$connection->setAttribute(PDO::ATTR_TIMEOUT,30);
                    //$connection->exec("SET NAMES utf8");
					
					//Connect to replication server while livelocal
					global $connection;
					//Akshay Parihar - Start - 09-04-2019
					//purpose: reduce unwanted DB connection
					if(!empty($connection)){
						/* Nitesh - 10th Jun 2019 - Start
						Purpose : check db connection if losted then reconnect it and commented old code because on some server there is a versioning issue so check it by old way [PCIDSSChanges] */
						try
						{
							$dao = new dao();
							$checkdb = "SELECT 1";
							$dao->initCommand($checkdb);
							$dao->executeQuery();
						}
						catch(Exception $e)
						{
							$connection=new PDO("mysql:host=".$repl_host.";dbname=". $dbname,$repl_username,$repl_password);	
							$connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
							$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$connection->setAttribute(PDO::ATTR_TIMEOUT,30);
							$connection->exec("SET NAMES utf8");
						}
						/*$db_info = $connection->getAttribute(PDO::ATTR_SERVER_INFO);
						if( trim($db_info) == "MySQL server has gone away" ){
							$connection=new PDO("mysql:host=".$repl_host.";dbname=". $dbname,$repl_username,$repl_password);	
							$connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
							$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$connection->setAttribute(PDO::ATTR_TIMEOUT,30);
							$connection->exec("SET NAMES utf8");	
						}
						else
						{
							$this->log->logIt('DB already connected...');
							$strSql = "use ".$dbname;
							$command = $connection->prepare($strSql);   
							$command->execute();
							return true;	
						}*/
						//Nitesh - End
					}//Akshay Parihar : End
					else
					{
						$connection=new PDO("mysql:host=".$repl_host.";dbname=". $dbname,$repl_username,$repl_password);	
						$connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
						$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
						$connection->setAttribute(PDO::ATTR_TIMEOUT,30);
						$connection->exec("SET NAMES utf8");	
					}
					if($server=='livelocal')
					{
						   $dao = new dao();
						   $strSql = "select ROUND(Replica_lag_in_msec/1000) as Seconds_Behind_Master,'Yes' as Slave_IO_Running,'Yes' as Slave_SQL_Running from mysql.ro_replica_status where Session_id != 'MASTER_SESSION_ID' limit 1";
						   $dao->initCommand($strSql);
						   $result = $dao->executeRow();
						   if (!($result['Slave_IO_Running'] == 'Yes' &&
								 $result['Slave_SQL_Running'] == 'Yes' && $result['Seconds_Behind_Master']<30))
						   {
								 $connection=new PDO("mysql:host=".$host.";dbname=". $dbname,$username,$password);	
								 $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
								 $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
								 $connection->setAttribute(PDO::ATTR_TIMEOUT,30);
								 $connection->exec("SET NAMES utf8");
								 $this->log->logIt("Replication error fail.... Change connection from Replica to Master server ...");
						   }
					}
                                    
                } else {
                    $flag = false;
                }
            } catch (Exception $e) {
				try{
				  
				   //Shweta - 19th June 2017 : 1.0.52.60 - START
					//Purpose : to make mysql credentails ecrypt instead of writing as palin text
					$projectPath = dirname(__FILE__);
					require $projectPath."/../../common_connection.php";
					// END
					
				   if ($server != '' && $dbname != '') {
                    switch ($server) {
                        case 'local':
                            $serverurl = "http://" . $_SERVER['HTTP_HOST'] . "/booking/";
                            $host =  $commonDbConnection['dbConnection']['local']['mysqlhost'];
                            $username = $commonDbConnection['dbConnection']['local']['mysqluser'];
                            $password = $commonDbConnection['dbConnection']['local']['mysqlpwd'];
							
							$repl_host =  $commonDbConnection['dbConnection']['local']['repl_host'];
							$repl_username = $commonDbConnection['dbConnection']['local']['repl_username'];
							$repl_password = $commonDbConnection['dbConnection']['local']['repl_password'];
							
                            break;
                        case 'livetest':
                            $serverurl = (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) ? (strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"]) == "https" ? "https://" : "http://") : "http://") . $_SERVER['HTTP_HOST'] . "/";
                            $host = $commonDbConnection['dbConnection']['demoserver']['mysqlhost'];
                            $username = $commonDbConnection['dbConnection']['demoserver']['mysqluser'];
                            $password = $commonDbConnection['dbConnection']['demoserver']['mysqlpwd'];
							
							$repl_host = $commonDbConnection['dbConnection']['demoserver']['repl_host'];
							$repl_username = $commonDbConnection['dbConnection']['demoserver']['repl_username'];
							$repl_password = $commonDbConnection['dbConnection']['demoserver']['repl_password'];
                            break;
                        case 'livelocal':
                            $serverurl = (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) ? (strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"]) == "https" ? "https://" : "http://") : "http://") . $_SERVER['HTTP_HOST'] . "/";
                            $host = $commonDbConnection['dbConnection']['liveserver']['mysqlhost'];
                            $username = $commonDbConnection['dbConnection']['liveserver']['mysqluser'];
                            $password =$commonDbConnection['dbConnection']['liveserver']['mysqlpwd'];
							
							$repl_host =  $commonDbConnection['dbConnection']['liveserver']['repl_host'];
							$repl_username =$commonDbConnection['dbConnection']['liveserver']['repl_username'];
							$repl_password = $commonDbConnection['dbConnection']['liveserver']['repl_password'];
                            break;
                        default:
                            $flag = false;
                    }
				   }
					global $connection;
					$connection=new PDO("mysql:host=".$host.";dbname=". $dbname,$username,$password);	
					$connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
					$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					$connection->setAttribute(PDO::ATTR_TIMEOUT,30);
					$connection->exec("SET NAMES utf8");
				}
				catch(Exception $e)
				{
				  $error_response=$this->ShowErrors('DBConnectError');               
				  echo $error_response; 
				  $this->log->logIt(" - Exception in " . $this->module . "-" . "connectDB - " . $e);
				  $this->handleException($e);
				}
            }
            return $flag;
        }
        
		private function connectMasterDB($server, $dbname) {
            try {
					$this->log->logIt(get_class($this) . "-" . "connectMasterDB".$server."==".$dbname);
					 
					if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs'] == '1')
						return 1;
					 
					  //Shweta - 19th June 2017 : 1.0.52.60 - START
					//Purpose : to make mysql credentails ecrypt instead of writing as palin text
					$projectPath = dirname(__FILE__);
					require $projectPath."/../../common_connection.php";
					// END
					
                $flag = true;
                if ($server != '' && $dbname != '') {
                    switch ($server) {
                        case 'local':
                            $serverurl = "http://" . $_SERVER['HTTP_HOST'] . "/booking/";
                            $host = $commonDbConnection['dbConnection']['local']['mysqlhost'];
                            $username = $commonDbConnection['dbConnection']['local']['mysqluser'];
                            $password = $commonDbConnection['dbConnection']['local']['mysqlpwd'];
                            break;
                        case 'livetest':
                            $serverurl = (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) ? (strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"]) == "https" ? "https://" : "http://") : "http://") . $_SERVER['HTTP_HOST'] . "/";
                            $host =$commonDbConnection['dbConnection']['demoserver']['mysqlhost'];
                            $username = $commonDbConnection['dbConnection']['demoserver']['mysqluser'];
                            $password = $commonDbConnection['dbConnection']['demoserver']['mysqlpwd'];
                            break;
                        case 'livelocal':
                            $serverurl = (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) ? (strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"]) == "https" ? "https://" : "http://") : "http://") . $_SERVER['HTTP_HOST'] . "/";
                            $host =$commonDbConnection['dbConnection']['liveserver']['mysqlhost'];
                            $username = $commonDbConnection['dbConnection']['liveserver']['mysqluser'];
                            $password = $commonDbConnection['dbConnection']['liveserver']['mysqlpwd'];
                            break;
                        case 'liverds':
                            $serverurl = "https://" . $_SERVER['HTTP_HOST'] . "/"; //Chirag 19th july 2019 http-https migration[PCIDSSChanges]
                            $host = $commonDbConnection['dbConnection']['liverds']['mysqlhost'];
                            $username =$commonDbConnection['dbConnection']['liverds']['mysqluser'];
                            $password =  $commonDbConnection['dbConnection']['liverds']['mysqlpwd'];
                            break;
                        default:
                            $flag = false;
                    }
                    
                    global $connection;
                    $connection=new PDO("mysql:host=".$host.";dbname=". $dbname,$username,$password);	
                    $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
                    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $connection->setAttribute(PDO::ATTR_TIMEOUT,30);
                    $connection->exec("SET NAMES utf8");
                                    
                } else {
                    $flag = false;
                }
            } catch (Exception $e) {
			    $error_response=$this->ShowErrors('DBConnectError');               
                echo $error_response; 
                $this->log->logIt(" - Exception in " . $this->module . "-" . "connectMasterDB - " . $e);
                $this->handleException($e);               
            }
            return $flag;
        }
		
   private function generateGeneralErrorMsg($code='',$hotel_code='')
	{
		try
		{
			$this->log->logIt(get_class($this) . "-" . "generateGeneralErrorMsg");
			$message = "";
			
			switch ($code) {
				case 'UnknownError':
					$message = 'Unknown Error';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case '2':
					$message = 'Cannot Parse Request';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;			
				case '3':
					$message = 'Hotel code '.$hotel_code.' is no longer used.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case '4':
					$message = 'Timeout requested. Stops requests for the specified time.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case '5':
					$message = 'Recoverable Error. Equivalent to http 503.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'BadRequest':
					$message = 'Bad request type.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'DBConnectError':
					$message = 'database not connected.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
                case 'ParametersMissing':
					$message = 'Missing parameters.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
                case 'EmptyParameter':
					$message = 'Parameters are empty.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'HotelListingError':
					$message = 'Hotel List error';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'RoomListingError':
					$message = 'Room List error';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'getRoomTypeListError':
					$message = 'Room Type List error';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;				
				case 'HotelAmenityListingError':
					$message = 'Hotel amenity listing error.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'DateNotvalid':
					$message = 'Requested date is past.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;				
				case 'HotelCodeEmpty':
					$message = 'Hotel code is empty.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'MaxAdultLimitReach':
					$message = 'Requested adults are greater then actual property configuration.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'MaxChildLimitReach':
					$message = 'Requested child are greater then actual property configuration.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'GroupHotelCodeMissing':
					$message = 'Please pass Group Code or Hotel Code.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'InvalidHotelCode':
					$message = 'Invalid Hotel code.Please check your property code.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'InvalidSearchCriteria':
					$message = 'Invalid search criteria found.Check out date & No of nights can not be pass together.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;				
				case 'APIACCESSDENIED':
					$message = "Your property doesn't have access of API integration or Key is incorrect. Please contact support for this.";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'APIKeyMissing':
					$message = "APIKey Is Missing.";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'NightsLimitExceeded':
					$message = "You can not request for more then 30 nights.";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'NightsLimitExceeded100':
					$message = "You can not request for more then 100 nights.";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'InvalidData':
					$message = "Please check data passed.";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'ReservationNotExist':
					$message = "Reservation No. does not exist. Please check.";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'ReservationAlreadyProcessed':
					$message = "Reservation is already processed.";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'RoomsNotAvailable':
					$message = "Booking insertion failed due to rooms not available.";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'NightsGreater':
					$message = "Nights can not be greater then 60 days.";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'CheckDate':
					$message = "Check out date should be greater than Check in date";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'NegativValues':
					$message = "Negative values or zero not allowed";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'getBookingListError':
					$message = 'Booking List error';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'getExtraChargeListError':
					$message = 'Extra Charge List error';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'BookingListLimitExceed':
					$message = "You can not request data of more than 365 days.";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'ProcessTransaction':
					$message = "There was some error while processing.";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				//Chinmay Gandhi - 1.0.53.61 - TravelAgent API - Start
				case 'RECORDEXIST':
					$message = "Record already exists.";
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'GCODEEMPTY':
					$message = 'Please pass Group Code.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'GCODEWRONG':
					$message = 'Group Code is Wrong.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'UsernameEmpty':
					$message = 'Please Check Username.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'PasswordEmpty':
					$message = 'Please Check Password.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'MANDATORYPARAM':
					$message = '[ salutation,name,businessname,email,country ] This Parameters are mandatory.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'INVUSEPASS':
					$message = 'Invalid Username and Password.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				//Chinmay Gandhi - 1.0.53.61 - TravelAgent API - End
				case 'HCODGCOD':
					$message = 'Hotel Code OR Group Code is missing';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'CRSBOOKERROR':
					$message = 'Generate Error while insert booking from multipriperty booking engine';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'SearchCriteriaMissing':
					$message = 'Please pass searching criteria , Location or Hotel Code.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
				case 'NORESACC':
					$message = 'This request is valid for Reservation Account only. You may not have opted for Reservation Account OR Hotel Code and Authentication are invalid OR This property is deactivated.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'NORESACC1':
					$message = 'This request is valid for Reservation Account only. You may not have opted for Reservation Account OR Groups Code and Authentication are invalid OR This property is deactivated.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				case 'UNAUTHREQ':
					$message = 'Unauthorized request. This request is not valid for this hotel code.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				//Nishit - 12 May 2020 - End 
				//Mehul Patel - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
				case 'Invalidpublishtoweb':
					$message = 'Invalid publishtoweb. publishtoweb filter should be 0 or 1.';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
				//Mehul Patel - 12 May 2020 - End 
				default:
					$message =  'Unknown Error';
					$this->log->logIt(get_class($this) . "-" . "Error : ".$code."-".$message);
					break;
			}
			
			$array = array(  "error_code"	=> $code,
							  "message" => $message
					   );
						
			array_push($this->error,$array);	
			return 	$array;	
			
		} catch (Exception $e) {
			$this->log->logIt("Exception in " . $this->module . " - generateGeneralErrorMsg - " . $e);
		}
  	}
	
	 private function handleException($e,$hotel_code='') {
         try {
	    $this->ErrorReport($e);
            exit;
        } catch (Exception $e) {
            $this->log->logIt(get_class($this) . "-" . "handleException" . "-" . $e);
            exit;
        }
    }	
    
	public function ShowErrors($error_code)
	{
		try{
			$this->log->logIt(get_class($this) . "-" . "ShowErrors ".$error_code);
			$error_result=$this->generateGeneralErrorMsg($error_code);
			$error_response[]=(array('Error Details'=>array(
										  "Error_Code" => $error_result['error_code'],
										  "Error_Message" => $error_result['message'])
										 )
								);
			return json_encode($error_response);			
		}
		 catch (Exception $e) {
			$this->log->logIt("Exception in " . $this->module . " - ShowErrors - " . $e);
		}
	}
	
	
	public function utf8_urldecode($str) {
		/*$log=new logger();
		$log->logIt("before " .$str);
		$log->logIt("urldecode str " .urldecode($str));		*/	
		$str = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;",urldecode($str));
		//$log->logIt("after " .$str);    	
		return html_entity_decode($str,null,'UTF-8');
 	} 
	
	public function ErrorReport($message)
	{
		try
		{
			$to      = 'ezeeres365@gmail.com';
			$subject = 'Reservation Metasearch API';
			$message = $message;
			$headers = 'From: ezeeres365@gmail.com' . "\r\n" .
			    'Reply-To: ezeeres365@gmail.com' . "\r\n" .
			    'X-Mailer: PHP/' . phpversion();			
			mail($to, $subject, $message, $headers);						
		}	
		catch(Exception $e)
		{
			$this->log->logIt("Exception in " .$e);
		}		
	}//function
	
	//Pinal - 1.0.46.51 - 13 August 2015 - START
	//Purpose - Get combo data like salutation , country
	public function getCombosList($Hotel_Code)
	{
		try{			
			$this->log->logIt(get_class($this) . "-" . "getCombosList ".$Hotel_Code);
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			$api_key='';
			$isopenAPI=$api_cnt=0;
			$openapi_arr=array();
			$ObjProcessDao=new processdao();
			$result=$ObjProcessDao->isMetaSearch($Hotel_Code,$_REQUEST['APIKey']);
			if(is_array($result) && count($result)>0)
			{
				$api_key=$result['key'];
				$isopenAPI=$result['integration']=='OpenAPI'?1:0;
				if($result['openapi_mappingid']!='')
					$openapi_arr = explode(',', $result['openapi_mappingid']);
				if($isopenAPI==1)
				{
					if(!in_array('36', $openapi_arr))
					{
						$error_response=$this->ShowErrors('UNAUTHREQ'); 
						echo $error_response;
						exit(0);
					}
				}
			}
			else{
				$objOpenApi = new openapi();
				//$objOpenApi->log=$this->log;
				$this->log->logIt("getCombosList: Calling function isAuthorized_DemoUser");
				$demo_res=$objOpenApi->isAuthorized_DemoUser($Hotel_Code,$_REQUEST['APIKey'],'36');
				//$this->log->logIt($demo_res,"Result>>>");
				if($demo_res==$Hotel_Code)
				{
					$api_key=$_REQUEST['APIKey'];
					$isopenAPI=1;
				}
				else
					exit;
			}

			//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
			if($isopenAPI == 1 && ($Hotel_Code != 0 || $Hotel_Code != '')){
				$req_array = array(
					"_reqtype" => 'ConfiguredDetails'
				);
				$this->log->logIt("listing-ConfiguredDetails : Calling threshold_check");
				$objOpenApi = new openapi();	
				$objOpenApi->threshold_check($Hotel_Code,"listing",$req_array);
			}
			//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

			$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
			$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
			if($api_key=='')
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				echo $error_response;
				exit(0);
			}
			//Nishit - 12 May 2020 - End
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$Hotel_Code,'',$isopenAPI);//Nishit - 12 May 2020 - Purpose: Added $isopenAPI parameter for Open API [ABS-5175]
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}	
			//Nishit - 12 May 2020 - End		
			$api_cnt=0;
			
			foreach($resultauth as $authuser)
			{				
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;		
				$ObjProcessDao->newurl = $authuser['newurl'];	
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				//Nishit - 12 May 2020 - Start - Purpose: Commented below code for Open API [ABS-5175]
				// $api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				// if($api_key!='')				
				// 	$Hotel_Code=$authuser['hotel_code'];				
				// else
				// 	$api_cnt++;				
			}
			
			// if($api_cnt>0)
			// {
			// 	$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
			// 	echo $error_response;
			// 	exit(0);	
			// }
			//Nishit - 12 May 2020 - End
			$combo_list=array();			
			if(isset($Hotel_Code) && $Hotel_Code!='')
			{				
				$language = (isset($_REQUEST['language']))?$_REQUEST['language']:'en';
				
				$WebSelectedLanguage=$ObjProcessDao->getreadParameter('WebSelectedLanguage',$Hotel_Code);
				$lang_ava=array();
				if($WebSelectedLanguage!='')
					$lang_ava=explode(",",$WebSelectedLanguage);
				
				if(isset(staticarray::$languagearray[$language]) && staticarray::$languagearray[$language]!='' && in_array($language,$lang_ava))
					$language =$language;
				else
					$language='en';
					
				$list_salutation=$ObjProcessDao->getLookupListByLookupType($Hotel_Code,'SALUTATION',$language);
				
				foreach($list_salutation as $sal)
				{
					$combo_list['Salutation'][$sal['lookupunkid']]=$sal['lookup'];	
				}
				
				$country_list=$ObjProcessDao->getCountryList();
				
				foreach($country_list as $country)
				{
					$combo_list['CountryList'][$country['countryunkid']]=$country['country_name'];	
				}
			}
			else
			{
				$error_response=$this->ShowErrors('InvalidHotelCode');				
				echo $error_response;
				exit(0);
			}		
			return $combo_list;
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('getCombosListError'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "getCombosList - " . $e);
			$this->handleException($e);  		
		}
	}
	//Pinal - 1.0.46.51 - 13 August 2015 - END
	
	//Pinal - 1.0.46.51 - 13 August 2015 - START
	//Purpose - Get Extra Charges List
	public function getExtraChargeList($HotelCode,$ispublishtoweb)//Nishit - 12 May 2020 - Purpose:Added $ispublishtoweb parameter for Open API [ABS-5175]
	{
		try
		{
			$this->log->logIt($this->module . "-" . "getExtraChargeList");
			$ObjProcessDao=new processdao();
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			$api_key='';
			$isopenAPI=0;
			$openapi_arr=array();
			if(!(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1'))
			{
				$result=$ObjProcessDao->isMetaSearch($HotelCode,$_REQUEST['APIKey']);
				if(is_array($result) && count($result)>0)
				{
					$api_key=$result['key'];
					$isopenAPI=$result['integration']=='OpenAPI'?1:0;
					if($result['openapi_mappingid']!='')
						$openapi_arr = explode(',', $result['openapi_mappingid']);
					if($isopenAPI==1)
					{
						if(!in_array('37', $openapi_arr))
						{
							$error_response=$this->ShowErrors('UNAUTHREQ'); 
							echo $error_response;
							exit(0);
						}
					}
				}
				else{
					$objOpenApi = new openapi();
					//$objOpenApi->log=$this->log;
					$this->log->logIt("getExtraChargeList: Calling function isAuthorized_DemoUser");
					$demo_res=$objOpenApi->isAuthorized_DemoUser($HotelCode,$_REQUEST['APIKey'],'37');
					//$this->log->logIt($demo_res,"Result>>>");
					if($demo_res==$HotelCode)
					{
						$api_key=$_REQUEST['APIKey'];
						$isopenAPI=1;
					}
					else
						exit;
				}

				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
				if($isopenAPI == 1 && ($HotelCode != 0 || $HotelCode != '')){
					$req_array = array(
						"_reqtype" => 'ExtraCharges'
					);
					$this->log->logIt("listing-ExtraCharges : Calling threshold_check");
					$objOpenApi = new openapi();	
					$objOpenApi->threshold_check($HotelCode,"listing",$req_array);
				}
				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

				$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
				$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
				if($api_key=='')
				{
					$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
					echo $error_response;
					exit(0);
				}
				else	
					$Hotel_Code=$HotelCode;
			}
			//Nishit - 12 May 2020 - End
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$HotelCode,'',$isopenAPI);//Nishit - 12 May 2020 - Purpose: Added $isopenAPI parameter for Open API [ABS-5175]
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}	
			//Nishit - 12 May 2020 - End
			$api_cnt=0;
			
			foreach($resultauth as $authuser)
			{
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;
				$ObjProcessDao->newurl = $authuser['newurl'];
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				
				if(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1') //Pinal - 11 September 2018 - Purpose : BE Chat Bot
				{
					  $Hotel_Code=$authuser['hotel_code'];
				}
				//Nishit - 12 May 2020 - Start - Purpose: Commented below code for Open API [ABS-5175]
				// else
				// {
				// 	$api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				// 	if($api_key!='')
				// 		$Hotel_Code=$authuser['hotel_code'];
				// 	else
				// 		$api_cnt++;

				// }
			}
			
			// if($api_cnt>0)
			// {
			// 	$error_response=$this->ShowErrors('APIACCESSDENIED');
			// 	echo $error_response;
			// 	exit(0);
			// }
			//Nishit - 12 May 2020 - End
			if(isset($Hotel_Code) && $Hotel_Code!='')
			{				
				$language = (isset($_REQUEST['language']))?$_REQUEST['language']:'en';
				
				$WebSelectedLanguage=$ObjProcessDao->getreadParameter('WebSelectedLanguage',$Hotel_Code);
				$lang_ava=array();
				if($WebSelectedLanguage!='')
					$lang_ava=explode(",",$WebSelectedLanguage);
				
				if(isset(staticarray::$languagearray[$language]) && staticarray::$languagearray[$language]!='' && in_array($language,$lang_ava))
					$language =$language;
				else
					$language='en';
				
				$types='';
				if(isset($_REQUEST['getpickupdropoff']) && $_REQUEST['getpickupdropoff']!='')
				{
					$types="'PICKUPSERVICE','PICKUP','DROPOFFSERVICE','PICKUPDROPOFFSERVICE'";
				}
							
				if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
					$list_extras=$ObjProcessDao->getExtraCharges($Hotel_Code,$language,'',1,$types,$isopenAPI,$ispublishtoweb);//Nishit - 12 May 2020 - Purpose:Added $isopenAPI and $ispublishtoweb parameter for Open API [ABS-5175]
				else
					$list_extras=$ObjProcessDao->getExtraCharges($Hotel_Code,$language,'','',$types,$isopenAPI,$ispublishtoweb);//Nishit - 12 May 2020 - Purpose:Added $isopenAPI and $ispublishtoweb parameter for Open API [ABS-5175]
				
				if($this->iscallfromchatbot) //Pinal - 11 September 2018 - Purpose : BE Chat Bot
				{
					$imagedata=$ObjProcessDao->getImageList('',imageobjecttype::ExtraCharges,$Hotel_Code);
					defined("SECURE_SITE") or  define('SECURE_SITE', true); //Pinal - 23 January 2020 - Purpose : RES-2388
					$ObjImageDao=new imagedao();
					foreach($list_extras as $index=>$extrs)
					{
						if($imagedata[$extrs['ExtraChargeId']]!='')
							$list_extras[$index]['Extra_Image']=$ObjImageDao->getImageFromBucket($imagedata[$extrs['ExtraChargeId']],'API');//Jemin Added "API" for use  https API in image url
					}
				}
			}
			else
			{
				$error_response=$this->ShowErrors('InvalidHotelCode');				
				echo $error_response;
				exit(0);
			}		
			return $list_extras;
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('getExtraChargeListError'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "getExtraChargeList - " . $e);
			$this->handleException($e);  		
		}
	}
	//Pinal - 1.0.46.51 - 13 August 2015 - END
	
	//Pinal - 1.0.46.51 - 14 August 2015 - START
	//Purpose - Insert Transaction
	public function insertTransaction($HotelCode)
	{
		try
		{
			$this->log->logIt($this->module . "-" . "insertTransaction");
			$ObjProcessDao=new processdao();
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$HotelCode);
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}	
			$api_key='';
			$isopenAPI=0;
			$openapi_arr = array();
			//Nishit - 12 May 2020 - End
			$api_cnt=0;
			foreach($resultauth as $authuser)
			{
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;
				$ObjProcessDao->newurl = $authuser['newurl'];
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				
				// $api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				// if($api_key!='')
				// 	$Hotel_Code=$authuser['hotel_code'];
				// else
				// 	$api_cnt++;

				$result=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				if(is_array($result) && count($result)>0)
				{
					$api_key=$result['key'];
					$isopenAPI=$result['integration']=='OpenAPI'?1:0;
					if($result['openapi_mappingid']!='')
						$openapi_arr = explode(',', $result['openapi_mappingid']);
					$Hotel_Code=$authuser['hotel_code'];
					if($isopenAPI==1)
					{
						if(!in_array('34', $openapi_arr))
						{
							$error_response=$this->ShowErrors('UNAUTHREQ'); 
							echo $error_response;
							exit(0);
						}
					}
				}
				else{
					$objOpenApi = new openapi();
					//$objOpenApi->log=$this->log;
					$this->log->logIt("insertTransaction: Calling function isAuthorized_DemoUser");
					$demo_res=$objOpenApi->isAuthorized_DemoUser($authuser['hotel_code'],$_REQUEST['APIKey'],'34');
					//$this->log->logIt($demo_res,"Result>>>");
					if($demo_res==$authuser['hotel_code'])
					{
						$api_key=$_REQUEST['APIKey'];
						$isopenAPI=1;
						$Hotel_Code=$authuser['hotel_code'];
					}
					else
						exit;
				}

				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
				if($isopenAPI == 1 && ($authuser['hotel_code'] != 0 || $authuser['hotel_code'] != '')){
					$req_array = array(
						"_reqtype" => 'InsertBooking'
					);
					$this->log->logIt("listing-InsertBooking : Calling threshold_check");
					$objOpenApi = new openapi();	
					$objOpenApi->threshold_check($authuser['hotel_code'],"listing",$req_array);
				}
				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

				$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
				$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
			}
			// if($api_cnt>0)
			// {
			// 	$error_response=$this->ShowErrors('APIACCESSDENIED');
			// 	echo $error_response;
			// 	exit(0);
			// }
			//Nishit - 12 May 2020 - End
			if($api_key=='')
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				echo $error_response;
				exit(0);
			}
			if(isset($Hotel_Code) && $Hotel_Code!='')
			{				
				if(!$this->connectMasterDB($this->servername, $authuser['databasename']))
				{
					$error_response=$this->ShowErrors('DBConnectError');                              
					echo $error_response;    
				}
				else
				{
					$this->log->logIt($this->module." - "."database connected");
				}
					
				$transactiondata=json_decode($_REQUEST['BookingData'],true);
				
				$language = (isset($transactiondata['Languagekey']))?$transactiondata['Languagekey']:'en';
				
				$WebSelectedLanguage=parameter::readParameter(parametername::WebSelectedLanguage,$Hotel_Code);
				
				$lang_ava=array();
				if($WebSelectedLanguage!='')
					$lang_ava=explode(",",$WebSelectedLanguage);
				
				if(isset(staticarray::$languagearray[$language]) && staticarray::$languagearray[$language]!='' && in_array($language,$lang_ava))
				{
					$transactiondata['Languagekey']=$language;
				}
				else
				{
					$transactiondata['Languagekey']='en';
				}
				
				//print_r($transactiondata);
				
				if(isset($transactiondata['Room_Details']) && count($transactiondata['Room_Details'])>0
				   && isset($transactiondata['Email_Address']) && $transactiondata['Email_Address']!=''
				   && isset($transactiondata['check_in_date']) && $transactiondata['check_in_date']!=''
				   && isset($transactiondata['check_out_date']) && $transactiondata['check_out_date']!='')
				{
					//$this->log->logIt($transactiondata['Room_Details']); //Chandrakant - 1.0.54.62 - 16 May 2018 - Comment it because of GDPR changes
					$this->log->logIt("check_in_date : ".$transactiondata['check_in_date']." - check_out_date : ".$transactiondata['check_out_date']);
					$noofnight = util::DateDiff($transactiondata['check_in_date'],$transactiondata['check_out_date']);
					
					if($ObjProcessDao->newurl == 0)	
						$localfolder_id =$Hotel_Code;
					else
						$localfolder_id =$ObjProcessDao->localfolder;
					
					$group_roomtype_cnt=array();
					$inverntory_rateplanwise=array();
					$ObjBookingDao=new bookingdao();
					$i=0;
					
					foreach($transactiondata['Room_Details'] as $roomdetails)
					{
						if(isset($roomdetails['Rateplan_Id']) && $roomdetails['Rateplan_Id']!=''
						   && isset($roomdetails['Ratetype_Id']) && $roomdetails['Ratetype_Id']!=''
						   && isset($roomdetails['Roomtype_Id']) && $roomdetails['Roomtype_Id']!=''
						   && isset($roomdetails['baserate']) && $roomdetails['baserate']!=''
						   && isset($roomdetails['extradultrate']) && $roomdetails['extradultrate']!=''
						   && isset($roomdetails['extrachildrate']) && $roomdetails['extrachildrate']!=''
						   && isset($roomdetails['number_adults']) && $roomdetails['number_adults']!=''
						   && isset($roomdetails['number_children']) && $roomdetails['number_children']!='')
						{
							$this->log->logIt("Rateplan_Id =>".$roomdetails['Rateplan_Id']);
							$this->log->logIt("Ratetype_Id =>".$roomdetails['Ratetype_Id']);
							$this->log->logIt("Roomtype_Id =>".$roomdetails['Roomtype_Id']);
							$this->log->logIt("baserate =>".$roomdetails['baserate']);
							$this->log->logIt("extradultrate =>".$roomdetails['extradultrate']);
							$this->log->logIt("extrachildrate =>".$roomdetails['extrachildrate']);
							$this->log->logIt("number_adults =>".$roomdetails['number_adults']);
							$this->log->logIt("number_children =>".$roomdetails['number_children']);
							
							$CalDateFormat=$ObjProcessDao->getreadParameter('WebAvailabilityCalDateFormat',$Hotel_Code);
							$checkin = util::Config_Format_Date($transactiondata['check_in_date'],staticarray::$webcalformat_key[$CalDateFormat]);
							$BookingEngineConfig=$ObjProcessDao->getreadParameter('BookingEngineConfig',$Hotel_Code);			
							$_Params=explode("<123>",$BookingEngineConfig);
							foreach($_Params as $keys)
							{								
								$_PluginParam=explode("<:>",$keys);								
								if($_PluginParam[0]=='LayoutTheme')
									$LayoutTheme=$_PluginParam[1];
							}
							
							//Pinal - 1.0.49.54 - 3 May 2016 - START
							//Purpose - Issue in check availability from api when layout is responsive
							$TravelId = isset($transactiondata['Source_Id'])?$transactiondata['Source_Id']:''; //Chandrakant - 1.0.53.61 - 23 November 2017 Purpose : To check travelagent inventory if source id 
							$inventory = $ObjBookingDao->check_room_availability($noofnight,$checkin,$localfolder_id,$CalDateFormat,$roomdetails['number_adults'],$roomdetails['number_children'],1,$roomdetails['Rateplan_Id'],1,$TravelId,$LayoutTheme);
							//note (flora) : once passed requested travelagent id  , it will check for TA inventory
							
							//Pinal - 1.0.49.54 - 3 May 2016 - END
							$this->log->logIt($noofnight." | ".$checkin." | ".$localfolder_id." | ".$CalDateFormat." | ".$roomdetails['number_adults']." | ".$roomdetails['number_children']." | 1 | ".$roomdetails['Rateplan_Id']." | 1 |  | ".$LayoutTheme);
							$this->log->logIt("INVENTORY");
							$this->log->logIt($inventory);
							$inverntory_rateplanwise[$i][$roomdetails['Roomtype_Id']]=$inventory;
							$i++;
							
							$group_roomtype_cnt[$roomdetails['Roomtype_Id']][]=$roomdetails['Roomtype_Id'];
							
							$count_baserate=explode(",",$roomdetails['baserate']);
							$count_extraadultrate=explode(",",$roomdetails['extradultrate']);
							$count_extrachildrate=explode(",",$roomdetails['extrachildrate']);
							
							if(count($count_baserate)!=$noofnight || count($count_extraadultrate)!=$noofnight || count($count_extrachildrate)!=$noofnight)
							{
								$error_response=$this->ShowErrors('InvalidData');
								echo $error_response;
								exit(0);
							}
							
							//print_r($roomdetails['Promotion_Details']['Promotion_Id']);
							
						}
						else
						{
							$error_response=$this->ShowErrors('ParametersMissing');				
							echo $error_response;
							exit(0);
						}
					}
					
					//print_r($inverntory_rateplanwise);
					//print_r($group_roomtype_cnt);
					
					$i=0;
					foreach($transactiondata['Room_Details'] as $roomdetails)
					{
						
						if(empty($inverntory_rateplanwise[$i][$roomdetails['Roomtype_Id']]))
						{
							$error_response=$this->ShowErrors('RoomsNotAvailable');				
							echo $error_response;
							exit(0);
						}
						else
						{
							foreach($inverntory_rateplanwise[$i][$roomdetails['Roomtype_Id']] as $inv)
							{	
								for($j=0;$j<count($inv);$j++)
								{
									//echo " - ".$inv[$j];
									if($inv[$j]<count($group_roomtype_cnt[$roomdetails['Roomtype_Id']]))
									{
										$error_response=$this->ShowErrors('RoomsNotAvailable');				
										echo $error_response;
										exit(0);
									}
								}
							}
						}
						$i++;
					}
					  
					$result=$ObjProcessDao->insertTransaction($Hotel_Code,$transactiondata);
					
					//Store Credit Card Details - START
					if(isset($result['ReservationNo']) && $result['ReservationNo']!='')
					{
					   if(isset($transactiondata['CardDetails']) &&
					   isset($transactiondata['CardDetails']['cc_cardnumber']) && $transactiondata['CardDetails']['cc_cardnumber']!='' &&
					   isset($transactiondata['CardDetails']['cc_cardtype']) && $transactiondata['CardDetails']['cc_cardtype']!='' &&
					   isset($transactiondata['CardDetails']['cc_expiremonth']) && $transactiondata['CardDetails']['cc_expiremonth']!='' &&
					   isset($transactiondata['CardDetails']['cc_expireyear']) && $transactiondata['CardDetails']['cc_expireyear']!='' &&
					   isset($transactiondata['CardDetails']['cvvcode']) && $transactiondata['CardDetails']['cvvcode']!='' &&
					   isset($transactiondata['CardDetails']['cardholdername']) && $transactiondata['CardDetails']['cardholdername']!='')
					   {
						  $resno=$result['ReservationNo'];
						  $groupunkid=$result['groupunkid'];
						  $tranunkid=$result['tranunkid'];
						  
						  $ObjProcessDao->processManualCard($transactiondata['CardDetails'],$resno,$groupunkid,$tranunkid);
					   }
					}
					//Store Credit Card Details - END
					
					unset($result['groupunkid']);
					unset($result['tranunkid']);
					return $result;
					
				}
				else
				{
					$error_response=$this->ShowErrors('ParametersMissing');				
					echo $error_response;
					exit(0);
				}
				
			}
			else
			{
				$error_response=$this->ShowErrors('InvalidHotelCode');				
				echo $error_response;
				exit(0);
			}
			
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('getExtraChargeListError'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "getExtraChargeList - " . $e);
			$this->handleException($e);  		
		}
	}
	//Pinal - 1.0.46.51 - 14 August 2015 - END
	
	//Pinal - 1.0.46.51 - 17 August 2015 - START
	//Purpose - Process Transaction
	public function ProcessTransaction($HotelCode)
	{
		try
		{
			$this->log->logIt($this->module . "-" . "ProcessTransaction");
			global $eZConfig;
			
			$ObjProcessDao=new processdao();
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$HotelCode);
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}	
			$api_key='';
			$isopenAPI=0;
			$openapi_arr = array();
			//Nishit - 12 May 2020 - End
			$api_cnt=0;
			$serverurl=$eZConfig['urls']['cnfServerUrl'];#Chinmay Gandhi - 1.0.53.61 - HTTPS Migration - HTTPSAutoMigration
			
			foreach($resultauth as $authuser)
			{				
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;
				$ObjProcessDao->newurl = $authuser['newurl'];
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
				// $api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				// if($api_key!='')
				// 	$Hotel_Code=$authuser['hotel_code'];
				// else
				// 	$api_cnt++;
				$result=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				if(is_array($result) && count($result)>0)
				{
					$api_key=$result['key'];
					$isopenAPI=$result['integration']=='OpenAPI'?1:0;
					if($result['openapi_mappingid']!='')
						$openapi_arr = explode(',', $result['openapi_mappingid']);
					$Hotel_Code=$authuser['hotel_code'];
					if($isopenAPI==1)
					{
						if(!in_array('35', $openapi_arr))
						{
							$error_response=$this->ShowErrors('UNAUTHREQ'); 
							echo $error_response;
							exit(0);
						}
					}
				}
				else{
					$objOpenApi = new openapi();
					//$objOpenApi->log=$this->log;
					$this->log->logIt("ProcessTransaction: Calling function isAuthorized_DemoUser");
					$demo_res=$objOpenApi->isAuthorized_DemoUser($authuser['hotel_code'],$_REQUEST['APIKey'],'35');
					//$this->log->logIt($demo_res,"Result>>>");
					if($demo_res==$authuser['hotel_code'])
					{
						$api_key=$_REQUEST['APIKey'];
						$isopenAPI=1;
						$Hotel_Code=$authuser['hotel_code'];
					}
					else
						exit;
				}

				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
				if($isopenAPI == 1 && ($authuser['hotel_code'] != 0 || $authuser['hotel_code'] != '')){
					$req_array = array(
						"_reqtype" => 'ProcessBooking'
					);
					$this->log->logIt("listing-ProcessBooking : Calling threshold_check");
					$objOpenApi = new openapi();	
					$objOpenApi->threshold_check($authuser['hotel_code'],"listing",$req_array);
				}
				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End
				
				$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
				$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
			}
			// if($api_cnt>0)
			// {
			// 	$error_response=$this->ShowErrors('APIACCESSDENIED');
			// 	echo $error_response;
			// 	exit(0);
			// }
			//Nishit - 12 May 2020 - End
			if($api_key=='')
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				echo $error_response;
				exit(0);
			}	
			
			if(isset($Hotel_Code) && $Hotel_Code!='')
			{
				if(isset($_REQUEST['Process_Data']) && $_REQUEST['Process_Data']!='')
				{
					$URL = $serverurl.'paymentprocess.php';#Chinmay Gandhi - 1.0.53.61 - HTTPS Migration - HTTPSAutoMigration
					
					$process_detail=json_decode(json_encode(json_decode($_REQUEST['Process_Data'])),true);
					
					if($ObjProcessDao->newurl == 0)	
						$localfolder_id =$Hotel_Code;
					else
						$localfolder_id =$ObjProcessDao->localfolder;
					
					if(isset($process_detail['ReservationNo']) && $process_detail['ReservationNo']!=''
					   && isset($process_detail['Inventory_Mode']) && in_array($process_detail['Inventory_Mode'],array('ALLOCATED','REGULAR'))
					   && isset($process_detail['Action']) && in_array($process_detail['Action'],array('PendingBooking','FailBooking','ConfirmBooking'))
					   )
					{
						
						if(!$this->connectMasterDB($this->servername, $authuser['databasename']))
						{
							$error_response=$this->ShowErrors('DBConnectError');                              
							echo $error_response;
						}
						else
						{
							$this->log->logIt($this->module." - "."database connected");
						}
						
						$ObjMasterDao=new masterdao();
						$tranflagstatus=$ObjMasterDao->getResTransactionFlag($process_detail['ReservationNo'],$Hotel_Code);
						//$this->log->logIt("tranflagstatus................................................................");
						//$this->log->logIt($tranflagstatus);
						if(!empty($tranflagstatus))
						{
							foreach($tranflagstatus as $tran)
							{
								if($tran['transactionflag']==1)
								{
									$error_response=$this->ShowErrors('ReservationAlreadyProcessed');				
									echo $error_response;
									exit(0);
								}
							}
						}
						else
						{
							$error_response=$this->ShowErrors('ReservationNotExist');				
							echo $error_response;
							exit(0);
						}
						
						$result=$ObjProcessDao->curlprocess($URL,$localfolder_id,$process_detail['Action'],$process_detail['ReservationNo'],$process_detail['Inventory_Mode'],(isset($process_detail['Error_Text']))?$process_detail['Error_Text']:'',(isset($process_detail['Booking_Payment_Mode']))?$process_detail['Booking_Payment_Mode']:'',$Hotel_Code);
					}
					else
					{
						//echo "para missing 1";
						$error_response=$this->ShowErrors('ParametersMissing');				
						echo $error_response;
						exit(0);
					}
				}
				else
				{
					//echo "para missing 2";
					$error_response=$this->ShowErrors('ParametersMissing');				
					echo $error_response;
					exit(0);
				}
			}
			else
			{
				$error_response=$this->ShowErrors('InvalidHotelCode');				
				echo $error_response;
				exit(0);
			}
			if($result!="Error")
			{
				return array(
						"result"=>"success",
						"message"=>"Booking Processed Succesfully"
					    );
			}
			else
			{
				$error_response=$this->ShowErrors('1');				
				echo $error_response;
				exit(0);
			}
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('ProcessTransaction'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "ProcessTransaction - " . $e);
			$this->handleException($e);  		
		}
	}
	//Pinal - 1.0.46.51 - 17 August 2015 - END
	
	//Pinal - 1.0.47.52 - 20 October 2015 - START
	//Purpose - Calculate Extra Charge
	public function getTotalExtraCharge($HotelCode)
	{
		try
		{
			$this->log->logIt($this->module . "-" . "getTotalExtraCharge");
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			$api_key='';
			$isopenAPI=$api_cnt=0;
			$openapi_arr=array();
			$ObjProcessDao=new processdao();
			
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$HotelCode);
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}
			//Nishit - 12 May 2020 - End
			// $api_cnt=0;
			foreach($resultauth as $authuser)
			{				
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;
				$ObjProcessDao->newurl = $authuser['newurl'];
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
				// $api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				$result=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				if(is_array($result) && count($result)>0)
				{
					$api_key=$result['key'];
					$isopenAPI=$result['integration']=='OpenAPI'?1:0;
					if($result['openapi_mappingid'])
						$openapi_arr = explode(',', $result['openapi_mappingid']);
					if($isopenAPI==1)
					{
						if(!in_array('38', $openapi_arr))
						{
							$error_response=$this->ShowErrors('UNAUTHREQ'); 
							echo $error_response;
							exit(0);
						}
					}
				}
				else{
					$objOpenApi = new openapi();
					//$objOpenApi->log=$this->log;
					$this->log->logIt("getTotalExtraCharge: Calling function isAuthorized_DemoUser");
					$demo_res=$objOpenApi->isAuthorized_DemoUser($authuser['hotel_code'],$_REQUEST['APIKey'],'38');
					//$this->log->logIt($demo_res,"Result>>>");
					if($demo_res==$authuser['hotel_code'])
					{
						$api_key=$_REQUEST['APIKey'];
						$isopenAPI=1;
					}
					else
						exit;
				}

				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
				if($isopenAPI == 1 && ($authuser['hotel_code'] != 0 || $authuser['hotel_code'] != '')){
					$req_array = array(
						"_reqtype" => 'CalculateExtraCharge'
					);
					$this->log->logIt("listing-CalculateExtraCharge : Calling threshold_check");
					$objOpenApi = new openapi();	
					$objOpenApi->threshold_check($authuser['hotel_code'],"listing",$req_array);
				}
				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

				$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
				$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
				//Nishit - 12 May 2020 - End
				if($api_key!='')
					$Hotel_Code=$authuser['hotel_code'];
				else
					$api_cnt++;
			}
			//Nishit - 12 May 2020 - Start - Purpose: Commented below code for Open API [ABS-5175]
			// if($api_cnt>0)
			// {
			// 	$error_response=$this->ShowErrors('APIACCESSDENIED');
			// 	echo $error_response;
			// 	exit(0);
			// }
			
			if($api_key=='')
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				echo $error_response;
				exit(0);
			}
			//Nishit - 12 May 2020 - End
			if(isset($Hotel_Code) && $Hotel_Code!='')
			{				
				if(isset($_REQUEST['ExtraChargeId']) && $_REQUEST['ExtraChargeId']!=''
				   && isset($_REQUEST['check_in_date']) && $_REQUEST['check_in_date']!=''
				   && isset($_REQUEST['check_out_date']) && $_REQUEST['check_out_date']!=''
				   && isset($_REQUEST['Total_ExtraItem']) && $_REQUEST['Total_ExtraItem']!=''
				   //Priyanka Maurya - 11 Jan 2020 - Purpose : Extra Charge Enhancement - RES-2061 - START
				   && isset($_REQUEST['BaserateExclusiveTax']) && $_REQUEST['BaserateExclusiveTax'] !=''
				   && isset($_REQUEST['BaserateInclusiveTax']) && $_REQUEST['BaserateInclusiveTax'] !=''
				   && isset($_REQUEST['BaserateAdjustment']) && $_REQUEST['BaserateAdjustment'] !=''
				   //Priyanka Maurya - END
				   )
				{
					
					$ExtraChargeId=$_REQUEST['ExtraChargeId'];
					$check_in_date=$_REQUEST['check_in_date'];
					$check_out_date=$_REQUEST['check_out_date'];
					$Total_ExtraItem=$_REQUEST['Total_ExtraItem'];
					
					$ExtraChargeId_arr=explode(",",$_REQUEST['ExtraChargeId']);
					$Total_ExtraItem_arr=explode(",",$_REQUEST['Total_ExtraItem']);
					
					//Priyanka Maurya - 19 Dec 2019 - Purpose : Extra Charge Enhancement - RES-2061 - START
					$BaserateExclusiveTax_arr = explode(",",$_REQUEST['BaserateExclusiveTax']);
					$BaserateInclusiveTax_arr = explode(",",$_REQUEST['BaserateInclusiveTax']);
					$BaserateAdjustment_arr   = explode(",",$_REQUEST['BaserateAdjustment']);

					$daterange = util::generateDateRange($_REQUEST['check_in_date'],$_REQUEST['check_out_date']);
					array_pop($daterange);
					$nights = count($daterange);
					//Priyanka Maurya - END
					
					//print_r($ExtraChargeId_arr);
					//print_r($Total_ExtraItem_arr);
					
					if(count($ExtraChargeId_arr)!=count($Total_ExtraItem_arr) || array_search('',$ExtraChargeId_arr) || array_search('',$Total_ExtraItem_arr)|| count($BaserateExclusiveTax_arr)!= $nights || array_search('',$BaserateExclusiveTax_arr) || count($BaserateInclusiveTax_arr)!= $nights || array_search('',$BaserateInclusiveTax_arr) || count($BaserateAdjustment_arr)!= $nights || array_search('',$BaserateAdjustment_arr) ) //Priyanka Maurya - Purpose : Extra Charge Enhancement - RES-2061 - START
					{
						$error_response=$this->ShowErrors('ParametersMissing');
						echo $error_response;
						exit(0);
					}
					
					if($check_in_date >= $check_out_date)
					{
						$error_response=$this->ShowErrors('CheckDate');							
						echo $error_response;
						exit(0);
					}
					
					if(!$this->connectDB($this->servername, $authuser['databasename']))
					{
						$error_response=$this->ShowErrors('DBConnectError');                              
						echo $error_response;
					}
					else
					{
						$this->log->logIt($this->module." - "."database connected");
					}
					
					$extrachargedetails=$ObjProcessDao->getExtraCharges($Hotel_Code,'en',$ExtraChargeId,'','',$isopenAPI);//Nishit - 12 May 2020 - Purpose: Added $isopenAPI parameter for Open API [ABS-5175]
					//print_r($_REQUEST);
					//print_r($extrachargedetails);
					
					$individual_charge=array();
					$totalcharge=0;
					if(count($extrachargedetails)>0)
					{
						//Priyanka Maurya - 19 Dec 2019 - Purpose : Extra Charge Enhancement - RES-2061
						$date_wise_BaserateExclusiveTax = array();
						$date_wise_BaserateInclusive    = array();
						$date_wise_BaserateAdj          = array();
						
						foreach( $daterange as $keys=>$date )
						{
							$date_wise_BaserateExclusiveTax[$date] = $BaserateExclusiveTax_arr[$keys];
							$date_wise_BaserateInclusive[$date]    = $BaserateInclusiveTax_arr[$keys];
							$date_wise_BaserateAdj[$date]		   = $BaserateAdjustment_arr[$keys];
						}
						//Priyanka Maurya - END
						
						for($i=0;$i<count($extrachargedetails);$i++)
						{
							$result=$ObjProcessDao->getTotalExtraCharge($check_in_date,$check_out_date,$extrachargedetails[$i]['PostingRule'],$Total_ExtraItem_arr[$i],$extrachargedetails[$i]['Rate'],$extrachargedetails[$i]['Tax'],$Hotel_Code,$extrachargedetails[$i]['ValidFrom'],$extrachargedetails[$i]['ValidTo'],$extrachargedetails[$i]['PostingType'],$extrachargedetails[$i]['OfferRate'],$date_wise_BaserateExclusiveTax,$date_wise_BaserateInclusive,$date_wise_BaserateAdj); //Priyanka Maurya - 19 Dec 2019 - Purpose : Extra Charge Enhancement [Added Posting Type,Offer Rate, Date wise Room Rate, Date Wise Room Tax, Date Wise Adjustment] - RES-2061
							$individual_charge[$extrachargedetails[$i]['ExtraChargeId']]=$result;							
							$totalcharge+=(float)$result;
						}
						
						return array(
								"IndividualCharge"=>$individual_charge,
								"TotalCharge"=>$totalcharge
							     );
					}
					else
					{
						return array();
					}
					
					//return array(
					//	"TotalExtraCharge"=>$result
					//    );
				}
				else
				{
					$error_response=$this->ShowErrors('ParametersMissing');
					echo $error_response;
					exit(0);
				}
			}
			else
			{
				$error_response=$this->ShowErrors('InvalidHotelCode');				
				echo $error_response;
				exit(0);
			}
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('getExtraChargeListError'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "getTotalExtraCharge - " . $e);
			$this->handleException($e);  		
		}
	}
	//Pinal - 1.0.47.52 - 20 October 2015 - END
	
	//Pinal - 1.0.50.55 - 16 August 2016 - START
	//Purpose - Booking List API
	public function getBookingList()
	{
		try
		{
			$this->log->logIt($this->module . "-" . "getBookingList");
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			$api_key='';
			$isopenAPI=0;
			$openapi_arr=array();
			$ObjProcessDao=new processdao();
			$result=$ObjProcessDao->isMetaSearch($_REQUEST['HotelCode'],$_REQUEST['APIKey']);
			if(is_array($result) && count($result)>0)
			{
				$api_key=$result['key'];
				$isopenAPI=$result['integration']=='OpenAPI'?1:0;
				if($result['openapi_mappingid']!='')
					$openapi_arr = explode(',', $result['openapi_mappingid']);

				if($isopenAPI==1)
				{
					if(!in_array('39', $openapi_arr))
					{
						$error_response=$this->ShowErrors('UNAUTHREQ'); 
						echo $error_response;
						exit(0);
					}
				}
			}
			else{
				$objOpenApi = new openapi();
				//$objOpenApi->log=$this->log;
				$this->log->logIt("getBookingList: Calling function isAuthorized_DemoUser");
				$demo_res=$objOpenApi->isAuthorized_DemoUser($_REQUEST['HotelCode'],$_REQUEST['APIKey'],'39');
				//$this->log->logIt($demo_res,"Result>>>");
				if($demo_res==$_REQUEST['HotelCode'])
				{
					$api_key=$_REQUEST['APIKey'];
					$isopenAPI=1;
				}
				else
					exit;
			}

			//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
			if($isopenAPI == 1 && ($_REQUEST['HotelCode'] != 0 || $_REQUEST['HotelCode'] != '')){
				$req_array = array(
					"_reqtype" => 'BookingList'
				);
				$this->log->logIt("listing-BookingList : Calling threshold_check");
				$objOpenApi = new openapi();	
				$objOpenApi->threshold_check($_REQUEST['HotelCode'],"listing",$req_array);
			}
			//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

			$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
			$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
			if($api_key=='')
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				echo $error_response;
				exit(0);
			}
			else
				$Hotel_Code=$_REQUEST['HotelCode'];
				$this->log->logIt(get_class($this) . "-" . " hotelcode : ".$Hotel_Code);
			//Nishit - 12 May 2020 - End
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$_REQUEST['HotelCode'],'',$isopenAPI);//Nishit - 12 May 2020 - Purpose: Added $isopenAPI parameter for Open API [ABS-5175]
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}
			//Nishit - 12 May 2020 - End
			$api_cnt=0;
			$accountfor = ''; //Priyanka Maurya - 16 Dec 2019 - Purpose : Reservation Booking List API Optimization - RES-2332
			foreach($resultauth as $authuser)
			{
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;
				$ObjProcessDao->newurl = $authuser['newurl'];
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				$accountfor = $authuser['accountfor']; //Priyanka Maurya - 16 Dec 2019 - Purpose : Reservation Booking List API Optimization - RES-2332
				
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				//Nishit - 12 May 2020 - Start - Purpose: Commented below code for Open API [ABS-5175]
				// $api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				// if($api_key!='')
				// 	$Hotel_Code=$authuser['hotel_code'];
				// else
				// 	$api_cnt++;
			}
			
			// if($api_cnt>0)
			// {
			// 	$error_response=$this->ShowErrors('APIACCESSDENIED');
			// 	echo $error_response;
			// 	exit(0);
			// }
			//Nishit - 12 May 2020 - End
			if(isset($Hotel_Code) && $Hotel_Code!='')
			{
				
				if(!$this->connectDB($this->servername, $authuser['databasename']))
				{
					$error_response=$this->ShowErrors('DBConnectError');                              
					echo $error_response;    
				}
				else
				{
					$this->log->logIt($this->module." - "."database connected");
				}
				
				$createddate_from='';
				$createddate_to='';
				
				$arrivaldate_from='';
				$arrivaldate_to='';
				$EmailId='';//kishan Tailor 5 Dec 2019 purpose:based upon User email system will retrieve all bookings of that user list RES-2324
				
				if(isset($_REQUEST['created_from']) && $_REQUEST['created_from']!='')
					$createddate_from=$_REQUEST['created_from'];
					
				if(isset($_REQUEST['created_to']) && $_REQUEST['created_to']!='')
					$createddate_to=$_REQUEST['created_to'];
					
				if(isset($_REQUEST['arrival_from']) && $_REQUEST['arrival_from']!='')
					$arrivaldate_from=$_REQUEST['arrival_from'];
					
				if(isset($_REQUEST['arrival_to']) && $_REQUEST['arrival_to']!='')
					$arrivaldate_to=$_REQUEST['arrival_to'];
					
				//kishan Tailor 5 Dec 2019 purpose:based upon User email system will retrieve all bookings of that user list RES-2324 START
				if(isset($_REQUEST['EmailId']) && $_REQUEST['EmailId']!='')
					$EmailId=$_REQUEST['EmailId'];
				//Kishan Tailor RES-2324 END
												
				$chkpms='false';
				if($accountfor=='ABS')
					$chkpms='true';
				//Shafin - EnhanceBookingListAPI
				$filter_details=array();
				if($createddate_from!='' && $createddate_to!='')
				{
					$createddays = util::DateDiff($createddate_from,$createddate_to);
					if($createddays>365)
					{
						$error_response=$this->ShowErrors('BookingListLimitExceed');
						echo $error_response;
						exit(0);
					}	
					$bookinglist=$ObjProcessDao->getWebBookingList('','','','','','','','','','','','',$createddate_from,$createddate_to,'','','false','true','','true','true',$Hotel_Code,'','',$chkpms,$EmailId);//kishan Tailor purpose:Add Param $EmailId RES-2324
					$filter_details=array('created_from'=>$createddate_from,"created_to"=>$createddate_to);
					if($accountfor=='ABS')
						$roomList=$ObjProcessDao->getRoomListBlock($createddate_from,$createddate_to,$Hotel_Code);//Shafin Saiyed - 24th Sep 2018 - Purpose: Booking API List Enhancement - EnhanceBookingListAPI
				}
				else if($arrivaldate_from!='' && $arrivaldate_to!='')
				{
					$arrivaldays = util::DateDiff($arrivaldate_from,$arrivaldate_to);
					
					if($arrivaldays>365)
					{
						$error_response=$this->ShowErrors('BookingListLimitExceed');
						echo $error_response;
						exit(0);
					}
					$bookinglist=$ObjProcessDao->getWebBookingList('','','','','','','','','','',$arrivaldate_from,$arrivaldate_to,'','','','','true','false','','true','true',$Hotel_Code,'','',$chkpms,$EmailId);//kishan Tailor purpose:Add Param $EmailId RES-2324
					$filter_details=array('arrival_from'=>$arrivaldate_from,"arrival_to"=>$arrivaldate_to);
					if($accountfor=='ABS')	
						$roomList=$ObjProcessDao->getRoomListBlock($arrivaldate_from,$arrivaldate_to,$Hotel_Code);//Shafin Saiyed - 24th Sep 2018 - Purpose: Booking API List Enhancement - EnhanceBookingListAPI
				}
				else
				{
					$createddate_to=date('Y-m-d');
					$createddate_from=date('Y-m-d',strtotime($createddate_to." -15 days"));
					
					$bookinglist=$ObjProcessDao->getWebBookingList('','','','','','','','','','','','',$createddate_from,$createddate_to,'','','false','true','','true','true',$Hotel_Code,'','',$chkpms,$EmailId);//kishan Tailor purpose:Add Param $EmailId RES-2324
					$filter_details=array('created_from'=>$createddate_from,"created_to"=>$createddate_to);
					if($accountfor=='ABS')
						$roomList=$ObjProcessDao->getRoomListBlock($createddate_from,$createddate_to,$Hotel_Code);//Shafin Saiyed - 24th Sep 2018 - Purpose: Booking API List Enhancement - EnhanceBookingListAPI
				}
								
				//Shafin Saiyed - 24th Sep 2018 - Start
				//Purpose: add roomlist - EnhanceBookingListAPI
				if($accountfor=='ABS')
				{
					//if(!empty($return_list) || !empty($roomList))//Priyanka Maurya - 17 Dec 2019 - Purpose : Reservation Booking List API Optimization - RES-2332
					if(!empty($bookinglist) || !empty($roomList))
						return array('SearchCriteria'=>$filter_details,
								 'RoomList'=>$roomList,
						     'BookingList'=>$bookinglist);
					else
						return array();
				}
				else
				{
					//if(!empty($return_list)) //Priyanka Maurya - 17 Dec 2019 - Purpose : Reservation Booking List API Optimization - RES-2332
					if(!empty($bookinglist))
						return array('SearchCriteria'=>$filter_details,
						     'BookingList'=>$bookinglist);
					else
						return array();
				}
				//Shafin Saiyed - 24th Sep 2018 - End
			}
			else
			{
				$error_response=$this->ShowErrors('InvalidHotelCode');				
				echo $error_response;
				exit(0);
			}
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('getBookingListError'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "getBookingList - " . $e);
			$this->handleException($e);  		
		}
	}
	//Pinal - 1.0.50.55 - 16 August 2016 - END
	
	//Chinmay Gandhi - 1.0.53.61 - 28th nov 2017 - Start
	//Purpose : TravelAgent Varification
	public function VerifyUser($username,$password,$groupcode)
	{
		try
		{
			$this->log->logIt($this->module . "-" . "VerifyUser");

			$ObjProcessDao=new processdao();
			$resultauth=$ObjProcessDao->isAuthorizedUser($groupcode,'','');
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC1'); 						
				echo $error_response;
				exit(0);	
			}
			// if(count($resultauth)==0)
			// {
			// 	$error_response=$this->ShowErrors('GCODEWRONG'); 						
			// 	echo $error_response;
			// 	exit(0);
			// }

			$Hotel_Code=$i=$defoult_code='';
			$api_cnt=0;
			$api_key='';
			$isopenAPI=0;
			$openapi_arr=array();
			//Nishit - 12 May 2020 - End
			foreach($resultauth as $authuser)
			{
				if($i != '')
					$i=',';
					
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;		
				$ObjProcessDao->newurl = $authuser['newurl'];	
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
				$result=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
				$Hotel_Code = $Hotel_Code . $i . $authuser['hotel_code'];
				
				if(is_array($result) && count($result)>0)
				{
					$api_key=$result['key'];
					$isopenAPI=$result['integration']=='OpenAPI'?1:0;
					if($result['openapi_mappingid']!='')
						$openapi_arr = explode(',', $result['openapi_mappingid']);
					//$Hotel_Code=$authuser['hotel_code'];
					if($isopenAPI==1)
					{
						if(!in_array('42', $openapi_arr))
						{
							$error_response=$this->ShowErrors('UNAUTHREQ');
							echo $error_response;
							exit(0);
						}
					}
				}
				else{
				// elseif ($result['integration'] == 'OpenAPI'){ //Nishant - 16 Jun 2020 - Demo user check point added
					$objOpenApi = new openapi();
					//$objOpenApi->log=$this->log;
					$this->log->logIt("VerifyUser: Calling function isAuthorized_DemoUser");
					$demo_res=$objOpenApi->isAuthorized_DemoUser($authuser['hotel_code'],$_REQUEST['APIKey'],'42');
					//$this->log->logIt($demo_res,"Result>>>");
					if($demo_res==$authuser['hotel_code'])
					{
						$api_key=$_REQUEST['APIKey'];
						$isopenAPI=1;
					}
					else
						exit;
				}

				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
				if($isopenAPI == 1 && ($authuser['hotel_code'] != 0 || $authuser['hotel_code'] != '')){
					$req_array = array(
						"_reqtype" => 'VerifyUser'
					);
					$this->log->logIt("listing-VerifyUser : Calling threshold_check");
					$objOpenApi = new openapi();	
					$objOpenApi->threshold_check($authuser['hotel_code'],"listing",$req_array);
				}
				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

				$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
				$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
				// if($api_key=='')
				// {
				// 	$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				// 	echo $error_response;
				// 	exit(0);
				// }
				
				//Nishit - 12 May 2020 - End
				// $Hotel_Code = $Hotel_Code . $i . $authuser['hotel_code'];

				if($api_key!='')
				{
					$defoult_code = $authuser['hotel_code'];
					$api_cnt++;
				}
				$i++;
			}

			if($api_cnt==0)
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				echo $error_response;
				exit(0);	
			}
			
			if (!empty($Hotel_Code))
			{
				$ObjProcessDao->loginname=$_REQUEST['username'];
				$ObjProcessDao->pass=base64_decode($_REQUEST['password']);
				$ObjProcessDao->hotelcode=$Hotel_Code;
				$hotel=$ObjProcessDao->checkUserExists($defoult_code);
			}

			if($hotel == 'false')
			{
				$error_response=$this->ShowErrors('INVUSEPASS'); 						
				echo $error_response;
				exit(0);
			}
			else
			{
				return array('contact_detail'=>$hotel);
			}
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('VerifyUser'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "VerifyUser - " . $e);
			$this->handleException($e);  		
		}
	}
	
	public function InsertTravelAgent($param)
	{
		try
		{
			$this->log->logIt($this->module . "-" . "InsertTravelAgent");
			
			$ObjProcessDao=new processdao();
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$param['HotelCode']);
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}

			// if(count($resultauth)==0)
			// {
			// 	$error_response=$this->ShowErrors('InvalidHotelCode'); 						
			// 	echo $error_response;
			// 	exit(0);
			// }
			
			$Hotel_Code=$groupcode='';
			$api_cnt=$isopenAPI=0;
			$api_key='';
			$openapi_arr=array();
			//Nishit - 12 May 2020 - End
			foreach($resultauth as $authuser)
			{
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;
				$ObjProcessDao->newurl = $authuser['newurl'];
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
				$result=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$param['APIKey']);
				if(is_array($result) && count($result)>0)
				{
					$api_key=$result['key'];
					$isopenAPI=$result['integration']=='OpenAPI'?1:0;
					if($result['openapi_mappingid']!='')
						$openapi_arr = explode(',', $result['openapi_mappingid']);
					$Hotel_Code=$authuser['hotel_code'];
					if($isopenAPI==1)
					{
						if(!in_array('43', $openapi_arr))
						{
							$error_response=$this->ShowErrors('UNAUTHREQ');
							echo $error_response;
							exit(0);
						}
					}
				}
				else{
					$objOpenApi = new openapi();
					//$objOpenApi->log=$this->log;
					$this->log->logIt("InsertTravelAgent: Calling function isAuthorized_DemoUser");
					$demo_res=$objOpenApi->isAuthorized_DemoUser($authuser['hotel_code'],$_REQUEST['APIKey'],'43');
					//$this->log->logIt($demo_res,"Result>>>");
					if($demo_res==$authuser['hotel_code'])
					{
						$api_key=$_REQUEST['APIKey'];
						$isopenAPI=1;
					}
					else
						exit;
				}

				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
				if($isopenAPI == 1 && ($authuser['hotel_code'] != 0 || $authuser['hotel_code'] != '')){
					$req_array = array(
						"_reqtype" => 'InsertTravelAgent'
					);
					$this->log->logIt("listing-InsertTravelAgent : Calling threshold_check");
					$objOpenApi = new openapi();	
					$objOpenApi->threshold_check($authuser['hotel_code'],"listing",$req_array);
				}
				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

				$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
				$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
				if($api_key=='')
				{
					$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
					echo $error_response;
					exit(0);
				}
				//Nishit - 12 May 2020 - End
				if($api_key!='')
				{
					$Hotel_Code=$authuser['hotel_code'];
					$groupcode=$authuser['groupcode'];
					$api_cnt++;
				}
			}
			
			if($api_cnt==0)
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				echo $error_response;
				exit(0);	
			}
			else
			{
				if(!$this->connectMasterDB($this->servername, $authuser['databasename']))
				{
					$error_response=$this->ShowErrors('DBConnectError');                              
					echo $error_response;    
				}
				else
				{
					$this->log->logIt($this->module." - "."database connected");
				}
				
				//Mandatory Field - Start 
				$ObjProcessDao->hotelcode=$Hotel_Code;
				
				if(isset($param['businessname']) && $param['businessname'] != '')
				{
					$ObjProcessDao->business_name=$param['businessname'];
				}
				else
				{
					$error_response=$this->ShowErrors('MANDATORYPARAM');
					echo $error_response;
					exit(0);
				}

				if(isset($param['name']) && $param['name'] != '')
				{
					$ObjProcessDao->name=$param['name'];
				}
				else
				{
					$error_response=$this->ShowErrors('MANDATORYPARAM'); 						
					echo $error_response;
					exit(0);
				}

				if(isset($param['email']) && $param['email'] != '')
				{
					$ObjProcessDao->email=$param['email'];
				}
				else
				{
					$error_response=$this->ShowErrors('MANDATORYPARAM'); 						
					echo $error_response;
					exit(0);
				}

				if(isset($param['country']) && $param['country']!='')
				{
					$ObjProcessDao->country=$param['country'];
				}
				else
				{
					$error_response=$this->ShowErrors('MANDATORYPARAM'); 						
					echo $error_response;
					exit(0);
				}

				if(isset($param['salutation']) && $param['salutation']!='')
				{
					$salutation=$param['salutation'];
				}
				else
				{
					$error_response=$this->ShowErrors('MANDATORYPARAM'); 						
					echo $error_response;
					exit(0);
				}
				//Mandatory Field - End 
					
				//Optional Field - Start
				if(isset($param['bccmailid']) && $param['bccmailid'] != '')
					$ObjProcessDao->companymail=$param['bccmailid'];
				else
					$ObjProcessDao->companymail=NULL;
				
				if(isset($param['businesssource']) && $param['businesssource']!='')
					$ObjProcessDao->businesssource=$param['businesssource'];
				else
					$ObjProcessDao->businesssource='false';
				
				if(isset($param['percentdiscount']) && $param['percentdiscount']!='')
					$ObjProcessDao->percentdiscount=$param['percentdiscount'];
				else
					$ObjProcessDao->percentdiscount=0;
					
				if(isset($param['address']) && $param['address']!='')
					$ObjProcessDao->address=$param['address'];
				else
					$ObjProcessDao->address=NULL;
					
				if(isset($param['city']) && $param['city']!='')
					$ObjProcessDao->city=$param['city'];
				else
					$ObjProcessDao->city=NULL;
					
				if(isset($param['state']) && $param['state']!='')
					$ObjProcessDao->state=$param['state'];
				else
					$ObjProcessDao->state=NULL;
				
				if(isset($param['zipcode']) && $param['zipcode']!='')
					$ObjProcessDao->zipcode=$param['zipcode'];
				else
					$ObjProcessDao->zipcode=NULL;
				
				if(isset($param['phone']) && $param['phone']!='')
					$ObjProcessDao->phone=$param['phone'];
				else
					$ObjProcessDao->phone=NULL;

				if(isset($param['mobile']) && $param['mobile']!='')
					$ObjProcessDao->mobile=$param['mobile'];
				else
					$ObjProcessDao->mobile=NULL;
				
				if(isset($param['fax']) && $param['fax']!='')
					$ObjProcessDao->fax=$param['fax'];
				else
					$ObjProcessDao->fax=NULL;
					
				if(in_array($param['salutation'],array('MRS','MS','MAM')))
					$ObjProcessDao->gender='Female';
				else
					$ObjProcessDao->gender='Male';
					
				if(isset($param['isusercreated']) && $param['isusercreated']!='')
					$ObjProcessDao->isusercreated=$param['isusercreated'] == 'true' ?'1':'0';
				else
					$ObjProcessDao->isusercreated=0;
				
				if(isset($param['allowtoviewccblock']) && $param['allowtoviewccblock']!='')
					$ObjProcessDao->allowtoviewccblock=$param['allowtoviewccblock'] == 'true' ?'1':'0';
				else
					$ObjProcessDao->allowtoviewccblock=0;
				
				if(isset($param['sendemailtoguest']) && $param['sendemailtoguest']!='')
					$ObjProcessDao->sendemailtoguest=$param['sendemailtoguest'] == 'true' ?'1':'0';
				else
					$ObjProcessDao->sendemailtoguest=0;
					
				if(isset($param['ismailsend']) && $param['ismailsend']!='')
					$ObjProcessDao->ismailsend=$param['ismailsend'] == 'true' ?'1':'0';
				else
					$ObjProcessDao->ismailsend=0;
				//Optional Field - Start

				$ObjProcessDao->contactunkid=NULL;
				$ObjProcessDao->birthdate=NULL;
				$ObjProcessDao->ischild=0;
				$ObjProcessDao->identity_no=NULL;
				$ObjProcessDao->identity_state=NULL;
				$ObjProcessDao->identity_country=NULL;
				$ObjProcessDao->exp_date=NULL;
				$ObjProcessDao->contacttypeunkid='TRAVELAGENT';
				$ObjProcessDao->nationality=NULL;
				$ObjProcessDao->anniversary=NULL;
				$ObjProcessDao->paymenttypeunkid=NULL;
				$ObjProcessDao->directbillingunkid=NULL;
				$ObjProcessDao->taxid=NULL;
				$ObjProcessDao->creditlimit=NULL;
				$ObjProcessDao->creditterm=NULL;
				$ObjProcessDao->roomlist=NULL;
				$ObjProcessDao->vipstatusunkid=NULL;
				$ObjProcessDao->spousebirthdate=NULL;
				$ObjProcessDao->webtype=1;
				$ObjProcessDao->isReservation=1;
				$ObjProcessDao->rate='REGULAR';
				$ObjProcessDao->roominventory='REGULAR';
				$ObjProcessDao->identityunkid=NULL;
				$ObjProcessDao->commission_planunkid=NULL;
				$ObjProcessDao->commission_plan=NULL;
				$ObjProcessDao->contactsortkey=NULL;

				$result = $ObjProcessDao->getBaseCurrency($Hotel_Code);
					$ObjProcessDao->lnkmasterunkid=$result['exchangerateunkid'];

				$lookuprow = $ObjProcessDao->getLookup('SALUTATION',$salutation,$Hotel_Code,'');
					$ObjProcessDao->salutation=$lookuprow['lookup'];
					
				$ObjProcessDao->groupcode=$groupcode;

				$result=$ObjProcessDao->InsertTravelAgent();

				return $result;
				//if($result->resultValue['message']=='RECORDEXIST')
				//{
				//	$error_response=$this->ShowErrors('RECORDEXIST'); 						
				//	echo $error_response;
				//	exit(0);
				//}
				//else if($result->resultValue['message']=='RECORDSAVE')
				//{
				//	return array('MSG'=>'Record saved successfully.');
				//}
				//else if($result->resultValue['message']=='HELFRECORDEXIST')
				//{
				//	$ExistId=$i='';
				//	$cnt=0;
				//	foreach($result->resultValue['id'] as $exid)
				//	{
				//		if($cnt != 0)
				//			$i=',';
				//		$ExistId = $ExistId . $i . $exid;
				//		$cnt++;
				//	}
				//	return array('MSG'=>'Record saved successfully but this record already exists in this [ ' . $ExistId . ' ] hotel code.');
				//}
			}
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('InsertTravelAgent'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "InsertTravelAgent - " . $e);
			$this->handleException($e);  		
		}
	}
	//Chinmay Gandhi - 1.0.53.61 - End
	//kishan Tailor 1.0.53.61/0.02 12 Jan 2017 purpose:For Creating Read Booking API START
	public function ViewTransaction($request)
	{
		try
		{
			$ObjProcessDao=new processdao();
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$request['HotelCode']);
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}
			$api_cnt=$isopenAPI=0;
			$api_key='';
			$openapi_arr=array();
			//Nishit - 12 May 2020 - End
			//$api_cnt=0;			
			foreach($resultauth as $authuser)
			{
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;
				$ObjProcessDao->newurl = $authuser['newurl'];
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				//Nishit - 12 May 2020 - Start - Purpose: Commented below code for Open API [ABS-5175]
				//$api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$request['APIKey']);
				$result=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$request['APIKey']);
				if(is_array($result) && count($result)>0)
				{
					$api_key=$result['key'];
					$isopenAPI=$result['integration']=='OpenAPI'?1:0;
					if($result['openapi_mappingid']!='')
						$openapi_arr = explode(',', $result['openapi_mappingid']);
					if($isopenAPI==1)
					{
						if(!in_array('40', $openapi_arr))
						{
							$error_response=$this->ShowErrors('UNAUTHREQ');
							echo $error_response;
							exit(0);
						}
					}
				}
				else{
					$objOpenApi = new openapi();
					//$objOpenApi->log=$this->log;
					$this->log->logIt("ViewTransaction: Calling function isAuthorized_DemoUser");
					$demo_res=$objOpenApi->isAuthorized_DemoUser($authuser['hotel_code'],$_REQUEST['APIKey'],'40');
					//$this->log->logIt($demo_res,"Result>>>");
					if($demo_res==$authuser['hotel_code'])
					{
						$api_key=$_REQUEST['APIKey'];
						$isopenAPI=1;
					}
					else
						exit;
				}

				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
				if($isopenAPI == 1 && ($authuser['hotel_code'] != 0 || $authuser['hotel_code'] != '')){
					$req_array = array(
						"_reqtype" => 'ReadBooking'
					);
					$this->log->logIt("listing-ReadBooking : Calling threshold_check");
					$objOpenApi = new openapi();	
					$objOpenApi->threshold_check($authuser['hotel_code'],"listing",$req_array);
				}
				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

				$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
				$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
				if($api_key=='')
				{
					$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
					echo $error_response;
					exit(0);
				}

				if($api_key!='')
					$Hotel_Code=$authuser['hotel_code'];
				else
					$api_cnt++;
			}
			
			// if($api_cnt>0)
			// {
			// 	$error_response=$this->ShowErrors('APIACCESSDENIED');
			// 	echo $error_response;
			// 	exit(0);
			// }
			//Nishit - 12 May 2020 - End
			if(isset($Hotel_Code) && $Hotel_Code!='')
			{
				if(!$this->connectDB($this->servername, $authuser['databasename']))
				{
					$error_response=$this->ShowErrors('DBConnectError');                              
					echo $error_response;    
				}
				else
				{
					$this->log->logIt($this->module." - "."database connected");
				}
				
				if(isset($request['ResNo']) && $request['ResNo']!='')
				{	$trandata=$ObjProcessDao->getTransaction($request['ResNo'],$Hotel_Code);

					if(count($trandata)>0)
						return json_encode($trandata);
					else
					{
						$error_response=$this->ShowErrors('InvalidData');                              
						echo $error_response;
					}
				}
				else
				{
					$error_response=$this->ShowErrors('ReservationNotExist');                              
					echo $error_response;
				}
			}
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('ViewTransaction'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "ViewTransaction - " . $e);
			$this->handleException($e);
		}
	}
	//kishan Tailor 1.0.53.61/0.02 12 Jan 2017 purpose:For Creating Read Booking API END
	//kishan Tailor 1.0.53.61/0.02 17 Jan 2017 purpose:For Cancel Reservation API START
	public function cancelbooking($request)
	{
		try
		{
			$ObjProcessDao=new processdao();
			$ObjMasterDao=new masterdao();
			$ObjBookingDao=new bookingdao();
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$request['HotelCode'],1);
			$accountfor='';
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			$api_cnt=0;
			$api_key='';
			$isopenAPI=0;
			$openapi_arr=array();
			//Nishit - 12 May 2020 - End
			foreach($resultauth as $authuser)
			{
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;
				$ObjProcessDao->newurl = $authuser['newurl'];
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				$accountfor=$authuser['accountfor'];
				
				$this->selectdatabase($ObjProcessDao->databasename); //Pinal - 28 March 2018
				//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
				$result=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$request['APIKey']);
				if(is_array($result) && count($result)>0)
				{
					$api_key=$result['key'];
					$isopenAPI=$result['integration']=='OpenAPI'?1:0;
					if($result['openapi_mappingid']!='')
						$openapi_arr = explode(',', $result['openapi_mappingid']);
					if($isopenAPI==1)
					{
						if(!in_array('41', $openapi_arr))
						{
							$error_response=$this->ShowErrors('UNAUTHREQ');
							echo $error_response;
							exit(0);
						}
					}
				}
				else{
					$objOpenApi = new openapi();
					//$objOpenApi->log=$this->log;
					$this->log->logIt("cancelbooking: Calling function isAuthorized_DemoUser");
					$demo_res=$objOpenApi->isAuthorized_DemoUser($authuser['hotel_code'],$request['APIKey'],'41');
					//$this->log->logIt($demo_res,"Result>>>");
					if($demo_res==$authuser['hotel_code'])
					{
						$api_key=$_REQUEST['APIKey'];
						$isopenAPI=1;
					}
					else
						exit;
				}

				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
				if($isopenAPI == 1 && ($authuser['hotel_code'] != 0 || $authuser['hotel_code'] != '')){
					$req_array = array(
						"_reqtype" => 'CancelBooking'
					);
					$this->log->logIt("listing-CancelBooking : Calling threshold_check");
					$objOpenApi = new openapi();	
					$objOpenApi->threshold_check($authuser['hotel_code'],"listing",$req_array);
				}
				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

				$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
				$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
				if($api_key=='')
				{
					$error_response=$this->ShowErrors('APIACCESSDENIED');
					echo $error_response;
					exit(0);
				}
				
				//Nishit - 12 May 2020 - End
				if($api_key!='')
					$Hotel_Code=$authuser['hotel_code'];
				else
					$api_cnt++;
			}
			if($api_cnt>0)
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED');
				echo $error_response;
				exit(0);
			}
			if(isset($Hotel_Code) && $Hotel_Code!='')
			{
				if(!$this->connectMasterDB($this->servername, $authuser['databasename']))
				{
					$error_response=$this->ShowErrors('DBConnectError');                              
					echo $error_response;    
				}
				else
				{
					$this->log->logIt($this->module." - "."database connected");
				}
				if(isset($_REQUEST['ResNo']) && $_REQUEST['ResNo']!='')
				{
				$cancelno=$ObjMasterDao->getAutoManualNo('CRNumberType','CRNumberPrefix','CRNumberNext','',$Hotel_Code);
				$resno=$_REQUEST['ResNo'];
				$SubresNo=isset($_REQUEST['SubNo'])?$_REQUEST['SubNo']:'';
				$tranunkid=$ObjBookingDao->getTranunkid($resno,$SubresNo,$Hotel_Code);
				if(count($tranunkid)>0)
				{
						$tranid=$tranunkid[0]['tranunkid'];
						$ownership=$tranunkid[0]['isgroupowner'];
						$groupid=$tranunkid[0]['groupunkid'];
						$ispostedtorms=$tranunkid[0]['ispostedtorms'];
						$isposted=$tranunkid[0]['isposted'];
						
						// Shailesh - 10-07-2020 - START
						// Purpose - Send Trivago Conversion API Call For Trivago Bookings (CEN-1687)
						$ObjBookingDao->sendTrivagoConversionAPI("booking_cancellation",$resno,$tranid);						
						// Shailesh - 10-07-2020 - END
					
						if($ownership==1)
						{
							$ObjMasterDao->groupunkid = $groupid;
							$cnt=$ObjMasterDao->getGroupGuestCount($Hotel_Code);
							if($cnt>0)
							{
								//Find and Set ownership to next guest of same group
								$ObjMasterDao->updateOwnership($ObjMasterDao->findNextGuestForOwnership($Hotel_Code),1,$Hotel_Code);
								
								//Remove master transaction from ownership
								$ObjMasterDao->updateOwnership($tranid,NULL,$Hotel_Code);
							}								
						}
						$ObjBookingDao->cancellationno=$cancelno;
						$row=$ObjMasterDao->getFDRentalStatusID('CANCEL',$Hotel_Code);
						$ObjBookingDao->tranunkid=$tranid;
						$ObjBookingDao->statusunkid=$row['statusunkid'];
						$ObjBookingDao->resno=$resno;		
						$ObjBookingDao->is_void_cancelled_noshow_unconfirmed=1;	
						$ObjBookingDao->canceldatetime=util::getLocalDateTime($Hotel_Code);
						$res_user=$ObjMasterDao->getUserUnkid('admin',$Hotel_Code,1);
						$ObjBookingDao->canceluserunkid=$res_user['userunkid'];
						$rowTran=$ObjMasterDao->getArrivalData('',$resno,'',$tranid,'','',$Hotel_Code);
						$invarr=array();
						$roomownerunkid='';
						$companyunkid='';
						$travelagentunkid='';
						$sourceid='';
						foreach($rowTran as $rowTranData)
						{
							if($roomownerunkid!='')
							$sourceid=$rowTranData['roomownerunkid'];	
							if($companyunkid!='')
							$sourceid=$rowTranData['companyunkid']; 
							if($travelagentunkid!='')
							$sourceid=$rowTranData['travelagentunkid'];
							
							$webinventoryunkid=$rowTranData['webinventoryunkid'];
							list($chkdate,$chktime)=explode(" ",$rowTranData['arrivaldatetime']);
							list($chkOutdate,$chkOuttime)=explode(" ",$rowTranData['departuredatetime']);
							$reservationgauranteeunkid=$rowTranData['reservationgauranteeunkid'];
						}
						$rowRoomsData=$ObjMasterDao->getRoomsInfo($rowTranData['tranunkid'],$Hotel_Code);
						array_push($invarr,$rowRoomsData);
						$rsWebMode=$ObjMasterDao->getWebMode($sourceid,$Hotel_Code);
						if($rsWebMode['contactunkid']!='')
						{
							$resstaus=$ObjBookingDao->getGuranteeStatus($reservationgauranteeunkid,$Hotel_Code);
							$this->log->logIt("resstaus => ".$resstaus);
							if($rsWebMode['roominventory']=='ALLOCATED' && $resstaus==1)//flora , $resstaus ==1 , if bookign confirm 
							{
								
								#Update Inventory
								updateInventory($invarr,$webinventoryunkid,$chkdate,$chkOutdate,1,'',$Hotel_Code);
							}	
						}
						
						$result=$ObjBookingDao->cancelReservation($isposted,$ispostedtorms,$accountfor,$Hotel_Code);
						
						//Sushma Rana - 3rd July 2019 - Change for PMS booking queue - CEN-1151	- start					
						$pmsbkqueuedetail = $arrbookingqueuehotel = $arrbookingqueuePMS = $pmsbkqueuedetailreswise =  array();						
						try
						{	
							if(parameter::readParameter(parametername::FDIntegration,$Hotel_Code) == '1')
							{
								$Pmsqueueflag = 0;
								$arrbookingqueuehotel = $arrbookingqueuepms = $arrbookingqueuedata = array();
								$log_info_path = "/home/saasfinal/pmsinterface";	
								$file = $log_info_path."/pmsqueue.json";
								if (file_exists($file))
								{
									$str  = file_get_contents($file); //SecurityReviewed
									$arrbookingqueuedata = json_decode($str, true);
									//$this->log->logIt("Pms queue data >>".print_r($arrbookingqueuedata,true),"encrypted");//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
									$arrbookingqueuepms = $arrbookingqueuedata['pms'];
									//$this->log->logIt("Pms name array >>".print_r($arrbookingqueuepms,true),"encrypted");//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
									if(in_array(parameter::readParameter(parametername::ThirdPartyPMS,$Hotel_Code),$arrbookingqueuepms))
									{
										$arrbookingqueuehotel = $arrbookingqueuedata['hotelcode'];
										//$this->log->logIt("Hotel data >>".json_encode($arrbookingqueuehotel,true),"encrypted");//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
										if(in_array($Hotel_Code,$arrbookingqueuehotel))
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
									$pmsdetail = parameter::readParameter(parametername::ThirdPartyPMS,$Hotel_Code);
									$uniquekey = $Hotel_Code."|".$resno."|".$pmsdetail."|".$rowTranData['tranunkid'];
									$this->log->logIt("Unique key >>"."-".$uniquekey);		
									
									$pmsbkqueuedetail[$uniquekey]['reservationno'] = $resno;
									$pmsbkqueuedetail[$uniquekey]['channelbookingid'] = "";
									$pmsbkqueuedetail[$uniquekey]['bookingstatus'] = "cancel";
									$pmsbkqueuedetail[$uniquekey]['bookingsource'] = "web";
									$pmsbkqueuedetail[$uniquekey]['bookingroomcount'] = "";
									$pmsbkqueuedetail[$uniquekey]['pmsdetail'] = $pmsdetail;
									$pmsbkqueuedetail[$uniquekey]['hotel_code'] = $Hotel_Code;
									$pmsbkqueuedetail[$uniquekey]['trandatetime'] = date("Y-m-d H:i:s");
									$pmsbkqueuedetail[$uniquekey]['modifydatetime'] = "";
									$pmsbkqueuedetail[$uniquekey]['canceldatetime'] = "";
									$pmsbkqueuedetail[$uniquekey]['tranid'] = $rowTranData['tranunkid'];						
									$pmsbkqueuedetailreswise[$resno] = $pmsbkqueuedetail;	
								}
								$this->log->logIt("PMS booking queue >>"."-".print_r($pmsbkqueuedetailreswise,true));	
								if(count($pmsbkqueuedetailreswise)>0)
								{
									require_once(eZABSDATABASE."/pmsbookingqueue.php");
									$objpmsbookingqueue = new pmsbookingqueue();
									$pmsbookingqueue= $objpmsbookingqueue->insertbookingqueue($pmsbkqueuedetailreswise);		
									$this->log->logIt("Insert status in pmsbookingqueue >>".$pmsbookingqueue);				
								}	
							}						
						}					
						catch(Exception $e)
						{
							$this->log->logIt(get_class($this)."-"."Insert PMSQUEUE issue"."-".$e);					
						}
						//Sushma Rana - 3rd July 2019 - Change for PMS booking queue - CEN-1151	- end						
					
						return array('status'=>'Successful');
					}
					else
					{
						$error_response=$this->ShowErrors('ReservationNotExist');                              
						echo $error_response;
						exit(0);
					}
				}
				else
				{
					$error_response=$this->ShowErrors('ReservationNotExist');                              
					echo $error_response;
					exit(0);
				}
			}
			
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('CancelReservation'); 				
			echo $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "ViewTransaction - " . $e);
			$this->handleException($e);
		}
	}
	//kishan Tailor 1.0.53.61/0.02 17 Jan 2017 purpose:For Cancel Reservation API END
	
	//Falguni Rana - 7th Sep 2018, Purpose - BE Chat Bot
	public function getGuestReview($HotelCode='') 
	{
		$GuestReview = array();
		try
		{
			$this->log->logIt(get_class($this) . "-" . "getGuestReview ".$HotelCode);
			
			$ObjProcessDao=new processdao();
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$HotelCode);
			$api_key=$_REQUEST['APIKey'];
			$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
			if($api_key=='')
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				echo $error_response;
				exit(0);
			}
			else
			{
				$this->log->logIt(get_class($this) . "-" . " datbase name : ".$resultauth[0]['databasename']);
				if(isset($resultauth[0]['databasename']) && $resultauth[0]['databasename']!='')
				{				  
					$this->selectdatabase($resultauth[0]['databasename']);
					$ObjMasterDao=new masterdao();
					$GuestReview=$ObjMasterDao->getTotalReviewScore('',$_REQUEST['HotelCode']);
					unset($ObjMasterDao);
				}
			}
		}
		catch(Exception $e)
		{
			$this->log->logIt(" - Exception in " . $this->module . "-" . "getGuestReview - " . $e);
		}
		return $GuestReview;
	}
	
	//Pinal - 28 March 2018 - START
	//Purpose : Booking Engine Optimization , To remove database name from query so that it can be cached.
	private function selectdatabase($dbname)
	{
		try
		{	
			global $connection;
			$sql="use ".$dbname;
			$command=$connection->prepare($sql);
			$command->execute();
		}
		catch(Exception $e)
		{
			$this->log->logIt(" - selectdatabase Error" . $e);
		}
	}
	//Pinal - 28 March 2018 - END
	
	//Chinmay Gandhi - Hotel Listing [ RES-1452 ] - Start
	public function InsertTransaction_hotelchain($HotelCode,$groupcode)
	{
		try
		{
			$this->log->logIt($this->module . "-" . "InsertTransaction_hotelchain : " . $HotelCode);
			
			// Ravi V - RES-1931 - 26 July 2019 - CNAME support for Razorpay PG - START
			global $eZConfig;
			$CNAME=$eZConfig['urls']['cnfDomainHostName'];
			// Ravi V - RES-1931 - 26 July 2019 - CNAME support for Razorpay PG - END
			
			$this->log->logIt("CNAME  >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>".$CNAME);
			$_REQUEST['crsaccount']=(isset($_REQUEST['GroupCode']) && $_REQUEST['GroupCode']!='')?'1':((isset($_REQUEST['HotelCode']) && $_REQUEST['HotelCode']!='')?'0':''); //Pinal
			
			if($_REQUEST['crsaccount']=='')
			{
				$error_response=$this->ShowErrors('ParametersMissing');
				echo $error_response;
				exit(0);
			}
				
			$ObjCRSMasterDao=new multiproperty_masterdao();
			$ObjProcessDao=new processdao();
			
			$booking_data = json_decode($_REQUEST['BookingData'], true);
			$reservation_no = array();$inv_notavailable=array();
			$Booking_Payment_Mode = '';$inventory_mode='';$inv_flag=0;
			$groupId = '';
			$cnt = 0;
			
			$Inventory_roomwise=array();
			$inverntory_rateplanwise=array();
			$group_roomtype_cnt=array();
			$request_parameter = $_REQUEST;
			foreach($booking_data as $key=>$book_element)
			{
				$HotelCode = explode('<::>', base64_decode($key))[0];
				$resultauth=$ObjProcessDao->isAuthorizedUser('','',$HotelCode);
				$api_cnt=0;
				$inverntory_rateplanwise[$key] = array();
				foreach($resultauth as $authuser)
				{
					$ObjProcessDao->databasename =  $authuser['databasename'];
					$ObjProcessDao->hotel_code = $authuser['hotel_code'];
					$ObjProcessDao->hostname = $this->hostname;
					$ObjProcessDao->newurl = $authuser['newurl'];
					$ObjProcessDao->localfolder = $authuser['localfolder'];
					$ObjProcessDao->chaindatabasename = $authuser['chaindatabasename'];
					$groupId = $authuser['chainfoldername'];
					
					$this->selectdatabase($ObjProcessDao->databasename);
					
					if(isset($request_parameter['allow_without_api']) && $request_parameter['allow_without_api']=='1')
					{
						$getKey=$ObjCRSMasterDao->_getHotelData($HotelCode);
						
						$apikey='';
						foreach($getKey as $hotelKey)
							$apikey=$hotelKey['key'];
							
						$api_key=$apikey;
						$Hotel_Code=$authuser['hotel_code'];
					}
					else
					{
						if($groupcode!='')
							$api_key=$ObjProcessDao->isMetaSearch('',$request_parameter['APIKey']);
						else
							$api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$request_parameter['APIKey']);
							
						if($api_key!='')
							$Hotel_Code=$authuser['hotel_code'];
						else
							$api_cnt++;
					}
				}
				
				if($api_cnt>0)
				{
					$error_response=$this->ShowErrors('APIACCESSDENIED');
					echo $error_response;
					exit(0);
				}
				
				$transactiondata=$book_element;
				
				if(isset($transactiondata['Room_Details']) && count($transactiondata['Room_Details'])>0 && isset($transactiondata['Email_Address']) && $transactiondata['Email_Address']!='' && isset($transactiondata['check_in_date']) && $transactiondata['check_in_date']!='' && isset($transactiondata['check_out_date']) && $transactiondata['check_out_date']!='')
				{
					$noofnight = util::DateDiff($transactiondata['check_in_date'],$transactiondata['check_out_date']);
					$daterange=util::generateDateRange($transactiondata['check_in_date'],$transactiondata['check_out_date']);
							
					if($ObjProcessDao->newurl == 0)
						$localfolder_id =$Hotel_Code;
					else
						$localfolder_id =$ObjProcessDao->localfolder;
						
					
					$ObjBookingDao=new bookingdao();
					$i=0;
					
					foreach($transactiondata['Room_Details'] as $roomdetails)
					{
						if(isset($roomdetails['Rateplan_Id']) && $roomdetails['Rateplan_Id']!='' && isset($roomdetails['Ratetype_Id']) && $roomdetails['Ratetype_Id']!='' && isset($roomdetails['Roomtype_Id']) && $roomdetails['Roomtype_Id']!='' && isset($roomdetails['baserate']) && $roomdetails['baserate']!='' && isset($roomdetails['extradultrate']) && $roomdetails['extradultrate']!='' && isset($roomdetails['extrachildrate']) && $roomdetails['extrachildrate']!='' && isset($roomdetails['number_adults']) && $roomdetails['number_adults']!='' && isset($roomdetails['number_children']) && $roomdetails['number_children']!='')
						{
							$CalDateFormat = 'yy-mm-dd';
							$checkin = $transactiondata['check_in_date'];
							$LayoutTheme = 2;
							
							$TravelId = isset($transactiondata['Source_Id'])?$transactiondata['Source_Id']:'';
							$inventory = $ObjBookingDao->check_room_availability($noofnight,$checkin,$localfolder_id,$CalDateFormat,$roomdetails['number_adults'],$roomdetails['number_children'],1,$roomdetails['Rateplan_Id'],1,$TravelId,$LayoutTheme,1);
							
							foreach($daterange as $dtindex=>$dt)
							{
								if(isset($inventory[$roomdetails['Roomtype_Id']][$dtindex]))
								{
									if(!isset($Inventory_roomwise[$roomdetails['Roomtype_Id']][$dt]))
										$Inventory_roomwise[$roomdetails['Roomtype_Id']][$dt] = 0;
									
									$Inventory_roomwise[$roomdetails['Roomtype_Id']][$dt]=(int)$inventory[$roomdetails['Roomtype_Id']][$dtindex];
								}
							}
							
							$group_roomtype_cnt[$key][$roomdetails['Roomtype_Id']][]=$roomdetails['Roomtype_Id'];
							$inverntory_rateplanwise[$key][$i][$roomdetails['Roomtype_Id']]=$inventory;
							$i++;
								
							$count_baserate=explode(",",$roomdetails['baserate']);
							$count_extraadultrate=explode(",",$roomdetails['extradultrate']);
							$count_extrachildrate=explode(",",$roomdetails['extrachildrate']);
							
							if(count($count_baserate)!=$noofnight || count($count_extraadultrate)!=$noofnight || count($count_extrachildrate)!=$noofnight)
							{
								$inv_notavailable['error_detail']['error_code'] = 1;
								$inv_notavailable['error_detail']['error_message'] = "Parameters are missing.";
								
								if(isset($request_parameter['Iscrs']) && $request_parameter['Iscrs']=='1')
									return $error_response;
								else
									echo $error_response;
								exit(0);
							}
						}
						else
						{
							$inv_notavailable['error_detail']['error_code'] = 1;
							$inv_notavailable['error_detail']['error_message'] = "Parameters are missing.";
							
							if(isset($request_parameter['Iscrs']) && $request_parameter['Iscrs']=='1')
								return $error_response;
							else
								echo $error_response;
							exit(0);
						}
					}
				}
			}
			$_REQUEST = $request_parameter;
				
			foreach($booking_data as $key=>$book_element)
			{
				$HotelCode = explode('<::>', base64_decode($key))[0];
				
				$resultauth=$ObjProcessDao->isAuthorizedUser('','',$HotelCode);
				$api_cnt=0;
				foreach($resultauth as $authuser)
				{
					$ObjProcessDao->databasename =  $authuser['databasename'];
					$ObjProcessDao->hotel_code = $authuser['hotel_code'];
					$ObjProcessDao->hostname = $this->hostname;
					$ObjProcessDao->newurl = $authuser['newurl'];
					$ObjProcessDao->localfolder = $authuser['localfolder'];
					$ObjProcessDao->chaindatabasename = $authuser['chaindatabasename'];
					$groupId = $authuser['chainfoldername'];
					
					$this->selectdatabase($ObjProcessDao->databasename);
					
					if(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1')
					{
						$getKey=$ObjCRSMasterDao->_getHotelData($HotelCode);
						
						$apikey='';
						foreach($getKey as $hotelKey)
							$apikey=$hotelKey['key'];
							
						$api_key=$apikey;
						$Hotel_Code=$authuser['hotel_code'];
					}
					else
					{
						if($groupcode!='')
							$api_key=$ObjProcessDao->isMetaSearch('',$_REQUEST['APIKey']);
						else
							$api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$_REQUEST['APIKey']);
							
						if($api_key!='')
							$Hotel_Code=$authuser['hotel_code'];
						else
							$api_cnt++;
					}
				}
				
				if($api_cnt>0)
				{
					$error_response=$this->ShowErrors('APIACCESSDENIED');
					echo $error_response;
					exit(0);
				}
				
				if(isset($Hotel_Code) && $Hotel_Code!='')
				{
					if($cnt == 0)
					{
						if($_REQUEST['crsaccount'] == 1)
						{
							$this->selectdatabase($ObjProcessDao->chaindatabasename);
							$itineraryno = $ObjCRSMasterDao->_setItineraryNo($groupcode,$_REQUEST['crsaccount'],'');
							$GroupId = $ObjCRSMasterDao->getGroupFolderName($Hotel_Code);
							$WebSelectedLanguage = parameter::MultiProperty_ReadParameter(parametername::WebSelectedLanguage,$groupcode);
						}
						else
						{
							$itineraryno = $ObjCRSMasterDao->_setItineraryNo($Hotel_Code,$_REQUEST['crsaccount'],$ObjProcessDao->databasename);
							$WebSelectedLanguage = parameter::readParameter(parametername::WebSelectedLanguage,$Hotel_Code);
						}
					}
					
					if(!$this->connectMasterDB($this->servername, $authuser['databasename']))
					{
						$error_response=$this->ShowErrors('DBConnectError');                              
						echo $error_response;    
					}
					else
					{
						$this->log->logIt($this->module." - "."database connected");
					}
					
					$transactiondata=$book_element;
					$language = (isset($transactiondata['Languagekey']))?$transactiondata['Languagekey']:'en';
					if(isset($_REQUEST['language']) && $_REQUEST['language']!='')
						$language=$_REQUEST['language'];
						
					$Booking_Payment_Mode = $transactiondata['Booking_Payment_Mode'];
					
					$lang_ava=array();
					if($WebSelectedLanguage!='')
						$lang_ava=explode(",",$WebSelectedLanguage);
					
					if(isset(staticarray::$languagearray[$language]) && staticarray::$languagearray[$language]!='' && in_array($language,$lang_ava))
						$transactiondata['Languagekey']=$language;
					else
						$transactiondata['Languagekey']='en';
					
					if(isset($transactiondata['Room_Details']) && count($transactiondata['Room_Details'])>0 && isset($transactiondata['Email_Address']) && $transactiondata['Email_Address']!='' && isset($transactiondata['check_in_date']) && $transactiondata['check_in_date']!='' && isset($transactiondata['check_out_date']) && $transactiondata['check_out_date']!='')
					{
						$daterange=util::generateDateRange($transactiondata['check_in_date'],$transactiondata['check_out_date']);
						$noofnight = util::DateDiff($transactiondata['check_in_date'],$transactiondata['check_out_date']);
						$i=0;
						foreach($transactiondata['Room_Details'] as $roomdetails)
						{
							if(empty($inverntory_rateplanwise[$key][$i][$roomdetails['Roomtype_Id']]))
							{
								$inv_notavailable['error_detail']['error_code'] = 2;
								$inv_notavailable['error_detail']['error_message'] = "Inventory not available.";
								
								$room_data = array('id'=>$roomdetails['Roomtype_Id'],'checkIn'=>$transactiondata['check_in_date'],'checkOut'=>$transactiondata['check_out_date']);
								
								if(!isset($inv_notavailable['error_detail']['error_data']))
									$inv_notavailable['error_detail']['error_data'] = array();
												
								array_push($inv_notavailable['error_detail']['error_data'],$room_data);
								$inv_flag++;
							}
							else
							{
								foreach($inverntory_rateplanwise[$key][$i][$roomdetails['Roomtype_Id']] as $invindex=>$inv)
								{
									for($j=0;$j<count($inv);$j++)
									{
										if($Inventory_roomwise[$roomdetails['Roomtype_Id']][$daterange[$j]] == 0)
										{
											$inv_notavailable['error_detail']['error_code'] = 2;
											$inv_notavailable['error_detail']['error_message'] = "Inventory not available.";
								
											$room_data = array('id'=>$roomdetails['Roomtype_Id'],'checkIn'=>$transactiondata['check_in_date'],'checkOut'=>$transactiondata['check_out_date']);
											
											if(!isset($inv_notavailable['error_detail']['error_data']))
												$inv_notavailable['error_detail']['error_data'] = array();
															
											array_push($inv_notavailable['error_detail']['error_data'],$room_data);
											$inv_flag++;
										}
										else
										{
											if($inv[$j]<count($group_roomtype_cnt[$key][$roomdetails['Roomtype_Id']]))
											{
												$inv_notavailable['error_detail']['error_code'] = 2;
												$inv_notavailable['error_detail']['error_message'] = "Inventory not available.";
												$room_data = array('id'=>$roomdetails['Roomtype_Id'],'checkIn'=>$transactiondata['check_in_date'],'checkOut'=>$transactiondata['check_out_date']);
												if(!isset($inv_notavailable['error_detail']['error_data']))
													$inv_notavailable['error_detail']['error_data'] = array();
													
												array_push($inv_notavailable['error_detail']['error_data'],$room_data);
											}
											$Inventory_roomwise[$roomdetails['Roomtype_Id']][$daterange[$j]]=$Inventory_roomwise[$roomdetails['Roomtype_Id']][$daterange[$j]] - 1;
										}
									}
								}
							}
							$i++;
						}
					}
					else
					{
								
						$inv_notavailable['error_detail']['error_code'] = 1;
						$inv_notavailable['error_detail']['error_message'] = "Parameters are missing.";
						
						if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
							return $error_response;
						else
							echo $error_response;
						exit(0);
					}
				}
				else
				{
					$inv_notavailable['error_detail']['error_code'] = 1;
					$inv_notavailable['error_detail']['error_message'] = "Parameters are missing.";
					
					if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
						return $error_response;
					else
						echo $error_response;
					exit(0);
				}
			}
			
			if($inv_flag == 0)
			{
				$totpayamount = 0;
				$currency_code = $Firstname = $Lastname = $Email_Address = $MobileNo = $amount_detail = '';$card_detail = $totpayamountarray=array();
				$insertstatsdata=array(); //Pinal
				$Objmongodbdao=new mongodbdao();
				//Tejaswini - 20190628 - get all hotel code of group booking - Start
				$hotelarr = array();
				foreach($booking_data as $key=>$book_element)
				{
					$hotel_code = explode('<::>', base64_decode($key))[0];					
					array_push($hotelarr,$hotel_code);
				}
				//Tejaswini - 20190628 - get all hotel code of group booking - End
				$countryarray = array();//Chinmay Gandhi - Give country name to PG [ paypal_getcountry ]
				foreach($booking_data as $key=>$book_element)
				{
					$HotelCode = explode('<::>', base64_decode($key))[0];
					$getKey=$ObjCRSMasterDao->_getHotelData($HotelCode);
						
					$apikey='';
					foreach($getKey as $hotelKey)
						$apikey=$hotelKey['key'];
					
					$resultauth=$ObjProcessDao->isAuthorizedUser('','',$HotelCode);
					$api_cnt=0;
					foreach($resultauth as $authuser)
					{
						$ObjProcessDao->databasename =  $authuser['databasename'];
						$ObjProcessDao->hotel_code = $authuser['hotel_code'];
						$ObjProcessDao->hostname = $this->hostname;
						$ObjProcessDao->newurl = $authuser['newurl'];
						$ObjProcessDao->localfolder = $authuser['localfolder'];
						
						$this->selectdatabase($ObjProcessDao->databasename);
						
						if(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1')
						{
							  $Hotel_Code=$authuser['hotel_code'];
						}
						else
						{
							$api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$apikey);
							if($api_key!='')
								$Hotel_Code=$authuser['hotel_code'];
							else
								$api_cnt++;
						}
					}
					
					if($api_cnt>0)
					{
						$error_response=$this->ShowErrors('APIACCESSDENIED');
						if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
							return $error_response;
						else
							echo $error_response;
						exit(0);
					}
					
					if(isset($Hotel_Code) && $Hotel_Code!='')
					{
						//Chinmay Gandhi - Give country name to PG [ paypal_getcountry ] - Start
						$countryname = $ObjCRSMasterDao->getCountryHotelWise($Hotel_Code)['country_name'];
						if(!in_array($countryname, $countryarray))
							array_push($countryarray,$countryname);
						//Chinmay Gandhi - Give country name to PG [ paypal_getcountry ] - End
						
						if(!$this->connectMasterDB($this->servername, $authuser['databasename']))
						{
							$error_response=$this->ShowErrors('DBConnectError');
							if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
								return $error_response;
							else
								echo $error_response;
							exit(0);
						}
						else
						{
							$this->log->logIt($this->module." - "."database connected");
						}
						
						$chainlayout = 'YES';
						$transactiondata=$book_element;
						$language = (isset($transactiondata['Languagekey']))?$transactiondata['Languagekey']:'en';
						$Booking_Payment_Mode = $transactiondata['Booking_Payment_Mode'];
						
						//Chandrakant - 23 March 2020 - START
						//Purpose :	 - MHBEAPIEnhPG - paas paymenttypeunkid from API
						$WebPaymentGatwayCRS = (isset($transactiondata['paymenttypeunkid']))?$transactiondata['paymenttypeunkid']:'';
						$this->log->logIt('PGID : '.$WebPaymentGatwayCRS);
						if($WebPaymentGatwayCRS=='')
						//Chandrakant - 23 March 2020 - END
						$WebPaymentGatwayCRS = (isset($transactiondata['paymentgatewaydetails']['payment_method']) && $transactiondata['paymentgatewaydetails']['payment_method']!='')?$transactiondata['paymentgatewaydetails']['payment_method']:'';
						$default_currency = parameter::readParameter(parametername::WebDefaultCurrency,$Hotel_Code);
						
						if($_REQUEST['crsaccount'] == 1)
						{
							$this->selectdatabase($ObjProcessDao->chaindatabasename);
							$WebSelectedLanguage = parameter::MultiProperty_ReadParameter(parametername::WebSelectedLanguage,$groupcode);
							$creditcard_email = parameter::MultiProperty_ReadParameter(parametername::WebCCEmailNotificationToCRS,$groupcode);
							$WebRunOnSESCRS = parameter::MultiProperty_ReadParameter(parametername::WebRunOnSESCRS,$groupcode);
							
							if($WebPaymentGatwayCRS != '')
							{
								$get_PGName = $ObjCRSMasterDao->_getPgname($WebPaymentGatwayCRS,$groupcode);
								$paymentType = $get_PGName['paymenttype'];
								$ServiceType = $get_PGName['ServiceType'];// Neha singh - 20 MAR 2021 - Purpose : Added ServiceType feilds for solving the paymentype saving issue while booking comes from CRS property [CI-1097]
								
								$this->selectdatabase($ObjProcessDao->databasename);
								$getSettlementId = $ObjCRSMasterDao->_getSettlementId($paymentType,$Hotel_Code,$ServiceType);// Neha singh - 20 MAR 2021 - Purpose : Added ServiceType feilds for solving the paymentype saving issue while booking comes from CRS property [CI-1097]
								$settlementtypeunkid = $getSettlementId['paymenttypeunkid'];
							}
							else
							{
								$settlementtypeunkid = '';
							}
						}
						else
						{
							$this->selectdatabase($ObjProcessDao->databasename);
							$WebSelectedLanguage = parameter::readParameter(parametername::WebSelectedLanguage,$Hotel_Code);
							$WebRunOnSESCRS = parameter::readParameter(parametername::WebRunOnSES,$Hotel_Code);
							$settlementtypeunkid = $WebPaymentGatwayCRS;
						}
						
						if((isset($transactiondata['currency_code']) && $transactiondata['currency_code'] == '') || (!isset($transactiondata['currency_code'])))
							$transactiondata['currency_code'] = $default_currency;

						$totpayamount = (isset($transactiondata['totpayamount']))?$transactiondata['totpayamount']:'';
						$currency_code = (isset($transactiondata['currency_code']))?$transactiondata['currency_code']:$default_currency;
						$Firstname = (isset($transactiondata['Firstname']))?$transactiondata['Firstname']:'';
						$Lastname = (isset($transactiondata['Lastname']))?$transactiondata['Lastname']:'';
						$Email_Address = (isset($transactiondata['Email_Address']))?$transactiondata['Email_Address']:'';
						$MobileNo = (isset($transactiondata['MobileNo']))?$transactiondata['MobileNo']:'';
						
						if(isset($transactiondata['CardDetails']))
							$card_detail = $transactiondata['CardDetails'];
						
						$lang_ava=array();
						if($WebSelectedLanguage!='')
							$lang_ava=explode(",",$WebSelectedLanguage);
						
						if(isset(staticarray::$languagearray[$language]) && staticarray::$languagearray[$language]!='' && in_array($language,$lang_ava))
							$transactiondata['Languagekey']=$language;
						else
							$transactiondata['Languagekey']='en';
							
						if($_REQUEST['crsaccount'] == 1)
							$transactiondata['callfrommetasearch'] = 'Hotel Chain Booking';//kishan Tailor 14 March 2019 purpose:Caption Change needed
						else
							$transactiondata['callfrommetasearch'] = 'Prime Layout Call';
						
						$this->selectdatabase($ObjProcessDao->databasename);
						$result=$ObjProcessDao->insertTransaction($Hotel_Code,$transactiondata,$chainlayout,$settlementtypeunkid,$itineraryno);
						
						$amount_detail .= $Hotel_Code.":".$result['totalpaybleamount']."|";
						
						//$result['statsdata']['HotelId']=$HotelCode;
						$insertstatsdata[]=$result['statsdata'];
						
						
						if($Booking_Payment_Mode == '3')
							$totpayamountarray[] = $result['totalpaybleamount'];
							
						if($_REQUEST['crsaccount'] == 1)
							$this->selectdatabase($ObjProcessDao->chaindatabasename);
						
						$this->log->logIt($result['ReservationNo'] . " =-= " . $itineraryno);
						
						$ObjCRSMasterDao->_storeItineraryno($result['ReservationNo'],$result['SubReservationNo'],$Hotel_Code,$groupcode,$itineraryno,$result['lang_key'],$_REQUEST['crsaccount'],$result['tranunkids']);
						
						$this->selectdatabase($ObjProcessDao->databasename);
						$inventory_mode .= $result['ReservationNo'].'::'.$result['Inventory_Mode'] . "<:>";
						
						if(isset($result['ReservationNo']) && $result['ReservationNo']!='' && $Booking_Payment_Mode != '3')
						{
						   if(isset($transactiondata['CardDetails']) &&
						   isset($transactiondata['CardDetails']['cc_cardnumber']) && $transactiondata['CardDetails']['cc_cardnumber']!='' &&
						   isset($transactiondata['CardDetails']['cc_cardtype']) && $transactiondata['CardDetails']['cc_cardtype']!='' &&
						   isset($transactiondata['CardDetails']['cc_expiremonth']) && $transactiondata['CardDetails']['cc_expiremonth']!='' &&
						   isset($transactiondata['CardDetails']['cc_expireyear']) && $transactiondata['CardDetails']['cc_expireyear']!='' &&
						   isset($transactiondata['CardDetails']['cvvcode']) && $transactiondata['CardDetails']['cvvcode']!='' &&
						   isset($transactiondata['CardDetails']['cardholdername']) && $transactiondata['CardDetails']['cardholdername']!='')
						   {
							  $resno=$result['ReservationNo'];
							  $groupunkid=$result['groupunkid'];
							  $tranunkid=$result['tranunkid'];
							  
							  $ObjProcessDao->processManualCard($transactiondata['CardDetails'],$resno,$groupunkid,$tranunkid,$_REQUEST['crsaccount'],$creditcard_email,$WebRunOnSESCRS,$hotelarr);//Tejaswini - 20190628 - array of hotel codes
						   }
						}
						if($Booking_Payment_Mode==3)
							$PGdata=isset($transactiondata['paymentgatewaydetails'])?$transactiondata['paymentgatewaydetails']:'';
						
						unset($result['groupunkid']);
						unset($result['tranunkid']);
					}
				}
				
				$amount_detail = substr($amount_detail,0,-1);
				
				if($Booking_Payment_Mode == '1')
					$action = 'PendingBooking';
				else if($Booking_Payment_Mode == '2')
					$action = 'ConfirmBooking';
				else if($Booking_Payment_Mode == '3')
					$action = 'PendingBooking';
				else
				{
					//Chandrakant - 31 December 2019 - START
					//Purpose : RES-2337 - Allow to confirm booking even there is no billing option is activated
					//$action = 'FailBooking';
					$action = 'ConfirmBooking';
					//Chandrakant - 31 December 2019 - END
				}
				
				$reservation_no['Itinerary_No'] = $itineraryno;
				$reservation_no['Inventory_Mode'] = substr($inventory_mode,0,-3);
				$reservation_no['Action'] = $action;
				$reservation_no['Booking_Payment_Mode'] = $Booking_Payment_Mode;
				
				if($_REQUEST['crsaccount'] == 1)
					$this->selectdatabase($ObjProcessDao->chaindatabasename);
				
				if(!empty($insertstatsdata))
				{
					foreach($insertstatsdata as $statdata)
					{
						foreach($statdata as $statdata1)
						{	
							$statdata1['Itinerary_No'] = $itineraryno;
							if($Booking_Payment_Mode != '3')
								$statdata1["booking_status"] = $action;
								
							if($_REQUEST['crsaccount'] == 1)
								$statdata1['groupcode']=$groupcode;
							else
								$statdata1['HotelId']=$HotelCode;
								
							$statdata1['tranunkid']=trim($statdata1['tranunkid'],',');
							$statdata1['reservationno']=trim($statdata1['reservationno'],',');
							
							$Objmongodbdao->MultiGenerateArrayBookNow($statdata1,$Booking_Payment_Mode,$statdata1['groupunkid']);
						}
					}
				}
				
				if($Booking_Payment_Mode != '3')
				{
					$result = $this->ProcessBooking_hotelchain(json_encode($reservation_no),$HotelCode,$groupcode,$WebSelectedLanguage,$_REQUEST['crsaccount']);
					
					return $result;
					exit(0);
				}
				else
				{
					if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
					{
						$ObjEmailDao=new emaildao();
						$pgredirection = array();
						$pgredirection['pgredirect']['action'] = 'PaymentGateway';
						$pgredirection['pgredirect']['data'] = $reservation_no;
						if($_REQUEST['crsaccount'] == 1)
						{
							$this->selectdatabase($ObjProcessDao->chaindatabasename);
							$BeforePaymentHotelier = parameter::MultiProperty_ReadParameter(parametername::EmailBeforePaymentHotelierCRS,$groupcode);
							$BeforePaymentBooker = parameter::MultiProperty_ReadParameter(parametername::EmailBeforePaymentBookerCRS,$groupcode);
							$_SESSION['PaymentGatewayDetail']='PaymentGatewayDetail_'.$GroupId['chainfoldername'];
							$pgredirection['pgredirect']['pro_id'] = $groupId;
						}
						else
						{
							$_SESSION['PaymentGatewayDetail']='PaymentGatewayDetail_'.$localfolder_id;
							$pgredirection['pgredirect']['pro_id'] = $localfolder_id;
							$BeforePaymentHotelier = (parameter::readParameter(parametername::EmailBeforePaymentHotelier,$Hotel_Code)=='0')?'1':'0';
							$BeforePaymentBooker = (parameter::readParameter(parametername::EmailBeforePaymentBooker,$Hotel_Code)=='0')?'1':'0';
						}
						
						if($BeforePaymentHotelier == '1' || $BeforePaymentBooker == '1')
							$ObjEmailDao->_sendvoucher($itineraryno,$HotelCode,$ObjProcessDao->localfolder,$groupcode,$_REQUEST['crsaccount'],$language,$totpayamount,$BeforePaymentHotelier,$BeforePaymentBooker);
							
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['Itinerary_No'] = $itineraryno;
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['Inventory_Mode'] = substr($inventory_mode,0,-3);
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['Totalamoutpay'] = array_sum($totpayamountarray);
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['currency_code'] = $currency_code;
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['Firstname'] = $Firstname;
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['Lastname'] = $Lastname;
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['guestname'] = $Firstname.' '.$Lastname;// Ravi V - 22 July 2019 - RES-2106 - For full name.
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['Email_Address'] = $Email_Address;
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['MobileNo'] = $MobileNo;
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['card_detail']=$PGdata;
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['payment_method']=isset($PGdata['payment_method'])?$PGdata['payment_method']:'';
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['amount_with_hotel']=$amount_detail;
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['CNAME']=$CNAME;// Ravi V - RES-1931 - 26 July 2019 - CNAME support for Razorpay PG.
						$_SESSION[$_SESSION['PaymentGatewayDetail']]['PaymantGatwayDetail']['Country']=$countryarray;//Chinmay Gandhi - Give country name to PG [ paypal_getcountry ]
						return $pgredirection;
						exit();
					}
					else
					{
						return $reservation_no;
						exit();
					}
				}
			}
			else
			{
				if(isset($_REQUEST['Iscrs']) && $_REQUEST['Iscrs']=='1')
					return $inv_notavailable;
				else
					return $inv_notavailable;
				exit(0);
			}
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('CRSBOOKERROR'); 				
			return $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "InsertTransaction_hotelchain - " . $e);
			$this->handleException($e);  		
		}
	}
	
	public function ProcessBooking_hotelchain($reservation_no,$hotelcode='',$groupcode='',$WebSelectedLanguage='',$crsaccount)
	{
		try
		{
			$this->log->logIt($this->module . "-" . "ProcessBooking_hotelchain");
			
			global $eZConfig;
			global $method_result;
			$serverurl=$eZConfig['urls']['cnfServerUrl'];
			$ObjCRSMasterDao=new multiproperty_masterdao();
			$ObjProcessDao=new processdao();
			$ObjEmailDao=new emaildao();
			$Booking_Payment_Mode = '';
			$status_data = array();

			$requestdata = $_REQUEST;
			$reservation_no = json_decode($reservation_no, true);
			$action = '';
			
			if(isset($reservation_no['Itinerary_No']) && $reservation_no['Itinerary_No'] != '')
			{
				$status_data['Itinerary_No'] = $reservation_no['Itinerary_No'];
				
				if($crsaccount == 1)
					$ObjCRSMasterDao->switchtochaindb('',$groupcode);
				else
				{
					$resultauth=$ObjProcessDao->isAuthorizedUser('','',$hotelcode);
					foreach($resultauth as $authuser)
						$dbname = $authuser['databasename'];
						
					$ObjCRSMasterDao->switchtochaindb($dbname);
				}
				
				$reserv_no=$ObjCRSMasterDao->_getResNo($reservation_no['Itinerary_No'],$hotelcode,$groupcode,$crsaccount);
				$dupres = array();
				foreach($reserv_no as $key=>$val)
				{
					$dupres[$key] = array();
					$resultauth=$ObjProcessDao->isAuthorizedUser('','',$key);
					$api_cnt=0;
					foreach($resultauth as $authuser)
					{
						$ObjProcessDao->databasename =  $authuser['databasename'];
						$ObjProcessDao->hotel_code = $authuser['hotel_code'];
						$ObjProcessDao->hostname = $this->hostname;
						$ObjProcessDao->newurl = $authuser['newurl'];
						$ObjProcessDao->localfolder = $authuser['localfolder'];
						$ObjProcessDao->chaindatabasename = $authuser['chaindatabasename'];
						
						$this->selectdatabase($ObjProcessDao->databasename);
						
						if(isset($requestdata['allow_without_api']) && $requestdata['allow_without_api']=='1')
						{
							$getKey=$ObjCRSMasterDao->_getHotelData($key);
							
							$apikey='';
							foreach($getKey as $hotelKey)
								$apikey=$hotelKey['key'];
								
							$api_key=$apikey;
							
							$Hotel_Code=$authuser['hotel_code'];
						}
						else
						{
							if($groupcode!='')
								$api_key=$ObjProcessDao->isMetaSearch('',$requestdata['APIKey']);
							else
								$api_key=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$requestdata['APIKey']);
								
							if($api_key!='')
								$Hotel_Code=$authuser['hotel_code'];
							else
								$api_cnt++;
						}
					}
					
					if($api_cnt>0)
					{
						$error_response=$this->ShowErrors('APIACCESSDENIED');
						echo $error_response;
						exit(0);
					}
					
					if(isset($reservation_no['Action']) && $reservation_no['Action']!='' && isset($Hotel_Code) && $Hotel_Code!='')
					{
						foreach($val as $res_no)
						{
							if(!in_array($res_no,$dupres[$key]))
							{
								foreach(explode('<:>', $reservation_no['Inventory_Mode']) as $inv)
								{
									$mode = explode('::',$inv);
									if($mode[0] == $res_no)
										$Inventory_Mode = $mode[1];
								}
								
								$method_result[$key.'<:>'.$res_no] = array();
								$URL = $serverurl.'multiproperty_paymentprocess.php';
								
								if($ObjProcessDao->newurl == 0)
									$localfolder_id = $Hotel_Code;
								else
									$localfolder_id = $ObjProcessDao->localfolder;
								
								if(isset($res_no) && $res_no!='' && isset($Inventory_Mode) && in_array($Inventory_Mode,array('ALLOCATED','REGULAR')) && isset($reservation_no['Action']) && in_array($reservation_no['Action'],array('PendingBooking','FailBooking','ConfirmBooking')))
								{
									if(!$this->connectMasterDB($this->servername, $authuser['databasename']))
									{
										$error_response=$this->ShowErrors('DBConnectError');                              
										echo $error_response;
									}
									else
									{
										$this->log->logIt($this->module." - "."database connected");
									}
									
									$ObjMasterDao=new masterdao();
									
									$reserv_Action = '';
									if($reservation_no['Booking_Payment_Mode'] == '1')
									{
										$Guarantee = parameter::readParameter(parametername::WebBookingEnquiryReservationGuarantee,$Hotel_Code);
										
										if($Guarantee == '')
											$Guarantee = parameter::readParameter(parametername::WebReservationGuarantee,$Hotel_Code);
											
										$reserv_Action = $ObjMasterDao->getReservationGurrentee($Guarantee,$Hotel_Code);
									}
									else if($reservation_no['Booking_Payment_Mode'] == '2')
									{
										$Guarantee = parameter::readParameter(parametername::WebReservationGuarantee,$Hotel_Code);
										$reserv_Action = $ObjMasterDao->getReservationGurrentee($Guarantee,$Hotel_Code);
									}
									
									if($reserv_Action == 'CONFRESERV')
										$reservation_no['Action'] = 'ConfirmBooking';
									else if($reserv_Action == 'UNCONFRESERV')
										$reservation_no['Action'] = 'PendingBooking';
									
									$tranflagstatus=$ObjMasterDao->getResTransactionFlag($res_no,$Hotel_Code);
										
									if(!empty($tranflagstatus))
									{
										foreach($tranflagstatus as $tran)
										{
											if($tran['transactionflag']==1)
											{
												$error_response=$this->ShowErrors('ReservationAlreadyProcessed');
												echo $error_response;
												exit(0);
											}
										}
									}
									else
									{
										$error_response=$this->ShowErrors('ReservationNotExist');				
										echo $error_response;
										exit(0);
									}

									//Jemin - 14-Feb-2021 - CI-1140 - TO Insert PG response in Foliodetail - START
									$transactionData = array();
									if(isset($reservation_no['tp']) && $reservation_no['tp']='CommonPG')
									{
										$transactionData['tp'] = $reservation_no['tp'];
										$transactionData['Ezeetran'] =$reservation_no['Ezeetran'];
										$transactionData['pgTran'] =$reservation_no['pgTran'];
										$transactionData['pgname'] =$reservation_no['pgname'];
										$transactionData['maskedacctnums']=$reservation_no['maskedacctnums'];
										$transactionData['transactionid'] =$reservation_no['transactionid'];
										$transactionData['cardtypes'] =$reservation_no['cardtypes'];
										$transactionData['approvalcode'] =$reservation_no['approvalcode'];
									
									}
									//Jemin - 14-Feb-2021 - CI-1140 - TO Insert PG response in Foliodetail - END

									$result=$ObjProcessDao->curlprocess($URL,$localfolder_id,$reservation_no['Action'],$res_no,$Inventory_Mode,(isset($reservation_no['ErrorTxt']))?$reservation_no['ErrorTxt']:'',$reservation_no['Booking_Payment_Mode'],$Hotel_Code,$groupcode,$method_result,$crsaccount,$reservation_no['Itinerary_No'],(isset($reservation_no['pg_response']))?$reservation_no['pg_response']:array(),$transactionData); //Jemin - 14-Feb-2021 - CI-1140 - TO Insert PG response in Foliodetail added $transactionData
									
									$Booking_Payment_Mode = $reservation_no['Booking_Payment_Mode'];
									$method_result = json_decode($result, true);
									$status_data['transactiontype'] = $method_result[$key.'<:>'.$res_no]['transactiontype'];
									$status_data['Action'] = $method_result[$key.'<:>'.$res_no]['Action'];
									$status_data['ErrorTxt'] = $method_result[$key.'<:>'.$res_no]['ErrorTxt'];
									
									$action = $method_result[$key.'<:>'.$res_no]['Action'];
								}
								else
								{
									$error_response=$this->ShowErrors('ParametersMissing');				
									echo $error_response;
									exit(0);
								}
							}
							array_push($dupres[$key],$res_no);
						}
					}
					else
					{
						$error_response=$this->ShowErrors('ParametersMissing');				
						echo $error_response;
						exit(0);
					}
				}
			}
			else
			{
				$error_response=$this->ShowErrors('ParametersMissing');				
				echo $error_response;
				exit(0);
			}
			
			if($action == 'Fail')
				$ObjEmailDao->failEmail($reservation_no['Itinerary_No'],$groupcode,$method_result,$Booking_Payment_Mode,$WebSelectedLanguage,$crsaccount);
			else
				$ObjEmailDao->sendEmail($reservation_no['Itinerary_No'],$groupcode,$method_result,$Booking_Payment_Mode,$WebSelectedLanguage,$crsaccount);
			
			return $status_data;
			exit(0);
		}
		catch(Exception $e)
		{
			$error_response=$this->ShowErrors('CRSBOOKERROR'); 				
			return $error_response; 
			$this->log->logIt(" - Exception in " . $this->module . "-" . "ProcessBooking_hotelchain - " . $e);
			$this->handleException($e);  		
		}
	}
	//Chinmay Gandhi - Hotel Listing [ RES-1452 ] - End

	//Chandrakant - 07 March 2020 - START
	//Purpose : RES-2451
	public function GetBEConfiguredPG($request)
	{
		try
		{
			$this->log->logIt(get_class($this) . " - " . "GetBEConfiguredPG");
			
			$ObjProcessDao=new processdao();
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$request['HotelCode']);
			//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
			if(is_array($resultauth) && count($resultauth)==0)
			{
				$error_response=$this->ShowErrors('NORESACC'); 						
				echo $error_response;
				exit(0);	
			}
			$api_cnt=$isopenAPI=0;
			$api_key='';
			$openapi_arr=array();
			//Nishit - 12 May 2020 - End
			foreach($resultauth as $authuser)
			{				
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;		
				$ObjProcessDao->newurl = $authuser['newurl'];	
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename);
				
				$result=$ObjProcessDao->isMetaSearch($authuser['hotel_code'],$request['APIKey']);
				//Nishit - 12 May 2020 - Start - Purpose: Open API [ABS-5175]
				if(is_array($result) && count($result)>0)
				{
					$api_key=$result['key'];
					$isopenAPI=$result['integration']=='OpenAPI'?1:0;
					if($result['openapi_mappingid']!='')
						$openapi_arr = explode(',', $result['openapi_mappingid']);
					$Hotel_Code=$authuser['hotel_code'];
					if($isopenAPI==1)
					{
						if(!in_array('44', $openapi_arr))
						{
							$error_response=$this->ShowErrors('UNAUTHREQ');
							echo $error_response;
							exit(0);
						}
					}
				}
				else{
					$objOpenApi = new openapi();
					$Hotel_Code=$authuser['hotel_code'];
					//$objOpenApi->log=$this->log;
					$this->log->logIt("GetBEConfiguredPG: Calling function isAuthorized_DemoUser");
					$demo_res=$objOpenApi->isAuthorized_DemoUser($authuser['hotel_code'],$request['APIKey'],'44');
					//$this->log->logIt($demo_res,"Result>>>");
					if($demo_res==$authuser['hotel_code'])
					{
						$api_key=$request['APIKey'];
						$isopenAPI=1;
					}
					else
						exit;
				}

				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - Start
				if($isopenAPI == 1 && ($authuser['hotel_code'] != 0 || $authuser['hotel_code'] != '')){
					$req_array = array(
						"_reqtype" => 'ConfiguredPGList'
					);
					$this->log->logIt("listing-ConfiguredPGList : Calling threshold_check");
					$objOpenApi = new openapi();	
					$objOpenApi->threshold_check($authuser['hotel_code'],"listing",$req_array);
				}
				//Nishant - 4 Jul 2020 - Purpose: Open API [ABS-5175] log mechanism - End

				$this->log->logIt(get_class($this) . "-" . " Open API : ".$isopenAPI);
				$this->log->logIt(get_class($this) . "-" . " api_key : ".$api_key);
				
				// if($api_key!='')				
				// 	$Hotel_Code=$authuser['hotel_code'];				
				// else
				// 	$api_cnt++;				
			}
			
			// if($api_cnt>0)
			// {
			// 	$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
			// 	echo $error_response;
			// 	exit(0);	
			// }
			
			if($api_key=='')
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				echo $error_response;
				exit(0);
			}
			
			//Nishit - 12 May 2020 - End
			$result = $ObjProcessDao->GetConfiguredPaymentGatewayBE($Hotel_Code);
			
			if(empty($result))
			{
				$error_response[]=(array('Error Details'=>array(
								"Error_Code" => -1,
								"Error_Message" => "No Data found.")
							)
					);
				echo json_encode($error_response);
				exit(0);
			}
			else
			{
				echo json_encode($result,JSON_UNESCAPED_UNICODE);
				exit(0);
			}
		}
		catch (Exception $e)
		{
			$this->log->logIt("Exception in " . $this->module . " - GetBEConfiguredPG - " . $e);
			$this->handleException($e);
		}
	}
	//Chandrakant - 07 March 2020 - END

	//Chandrakant - 23 March 2020 - START
	//Purpose : MHBEAPIEnhPG
	public function GetMHBEConfiguredPG($request)
	{
		try
		{
			$this->log->logIt($request);
			$this->log->logIt(get_class($this) . " - " . "GetMHBEConfiguredPG");
			$GroupCode=$request['GroupCode'];

			$ObjProcessDao=new processdao();
			$resultauth=$ObjProcessDao->isAuthorizedUser($GroupCode);
			
			$api_cnt=0;
			foreach($resultauth as $authuser)
			{				
				$this->selectdatabase($authuser['chaindatabasename']);
				
				$api_key=$ObjProcessDao->isMetaSearch('',$request['APIKey']);
				
				if($api_key!='')				
					$GroupCode=$authuser['groupcode'];				
				else
					$api_cnt++;		
					
			break;
			}
			
			if($api_cnt>0)
			{
				$error_response=$this->ShowErrors('APIACCESSDENIED'); 						
				echo $error_response;
				exit(0);	
			}
			
			$result = $ObjProcessDao->GetConfiguredPaymentGatewayMHBE($GroupCode);
			
			if(empty($result))
			{
				$error_response[]=(array('Error Details'=>array(
								"Error_Code" => -1,
								"Error_Message" => "No Data found.")
							)
					);
				echo json_encode($error_response);
				exit(0);
			}
			else
			{
				echo json_encode($result,JSON_UNESCAPED_UNICODE);
				exit(0);
			}
		}
		catch (Exception $e)
		{
			$this->log->logIt("Exception in " . $this->module . " - GetMHBEConfiguredPG - " . $e);
			$this->handleException($e);
		}
	}
	//Chandrakant - 23 March 2020 - END
}

?>