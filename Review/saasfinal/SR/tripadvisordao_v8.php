<?php

$basePath = dirname(__FILE__);
require($basePath.'/../common/simple_html_dom.php');
            
class tripadvisordao_v8
{
    private $hostname;
    private $hotelcode;
    private $servername;
    private $interfacehost;
    private $databasename;
    private $log, $apikey;
    private $error=array();
    private $module = "TripAdvisorDao";
    
    function __construct()
    {
        try {
            $this->log = new logger("tripadvisor_integration_v8");
            $hostname  = gethostname();
            $this->log->logIt("Server detail >> " . $hostname);
            if ($hostname == 'ubuntu') {
                $this->interfacehost = "http://" . $_SERVER['HTTP_HOST'];
            } else {
                $this->interfacehost = "https://live.ipms247.com";
            }
            $this->log->logIt("Server detail >> " . $this->interfacehost);
        }
        catch (Exception $e) {
            throw $e;
        }
    }
    
    public function __set($name, $value)
    {
        $name = strtolower($name);
        if (is_string($value) || (trim($value) == '' && !is_null($value))) {
            $value = addslashes($value);
            $value = trim($value);
            $value = strip_tags($value);
            $str   = '$this->' . "$name=" . "'" . $value . "'";
        } else {
            $str = '$this->' . "$name=" . $value . "";
        }
        eval("$str;");
    }
    
    public function __get($name)
    {
        $name = strtolower($name);
        $str  = '$this->' . "$name";
        eval("\$str = \"$str\";");
        return $str;
    }
    
    public function hotelAvailableData($detail)
    {
        try{
            $this->log->logIt($this->module.' - hotelAvailableData - Hotel code - '.$this->hotelcode);
            $this->log->logIt($this->module.' - server name - '.$this->servername);
            
            $_REQUEST = array();
            $checkin   = $detail['start_date'];
            $checkout  = $detail['end_date'];
            $currency  = $detail['currency'];
            $hotelcode = $this->hotelcode;
            $tempAdultChildArr = array();
            $finalAdultChildArr = array();
            
            foreach($detail['party'] as $key => $Objvalue){
                $Objvalue = (array)$Objvalue;
                    $tempAdultChildArr[$Objvalue["adults"] . "oldkey" . $key] = $Objvalue;
                    ksort($tempAdultChildArr);
                    $finalAdultChildArr = array_values($tempAdultChildArr);
            }
            
            $_SESSION['prefix']                          = 'hotel';
            $_SESSION[$_SESSION['prefix']]['hotel_code'] = $hotelcode;
            
            $reqadult                                    = $reqadult_tot = $reqchild = $reqchild_tot = 0;
            $apikey                                      = $detail['api_key'];
            $days = util::DateDiff($checkin, $checkout);
            
            $num_rooms = 1;
            if (isset($detail['num_rooms'])){
                $num_rooms = (int) trim($detail['num_rooms']);
            }
            
            if (isset($detail['children'])){
                $reqchild_tot = (int) sizeof($detail['children']);
            }
            
            $reqadult_tot = array_sum($detail['num_adults']);
            
            $_REQUEST['HotelCode'] = $hotelcode;
            $_REQUEST['APIKey'] = $apikey;
            $_REQUEST['check_in_date'] = $checkin;
            $_REQUEST['check_out_date'] = $checkout;
            $_REQUEST['num_rooms'] = 1;
            $_REQUEST['property_configuration_info'] = 1;
            $_REQUEST['showsummary'] = 1;
            
            $arrAllExtraCharges = $this->getExtracharge($hotelcode);
            $this->log->logIt($this->module.' - Get Extra charge - '.json_encode($arrAllExtraCharges,true));
            
            $hotelData = $this->getHotelDetail($hotelcode,$apikey);
            
            //$this->log->logIt($this->module.' - Hotel details - '.json_encode($hotelData,true));
            $base_currency = (string) $hotelData[0]['CurrencyCode'];
        
            if (strtoupper($base_currency) != strtoupper($currency)){
                $conversion = $this->new_currency(strtoupper($base_currency), strtoupper($currency), 1);
            }
            else{
                $conversion = 1;
            }
            
            $this->log->logIt($this->module.' - conversion - '.$conversion);
            
            $final_price        = $total_tax = 0;
            $roomarray          =    array();
            $notAvail           =    array();
            $check_room_arr     =    array();
            $temp_roomarray     =    array();
            $comArry            =    array();
            $Roomdetails_array  =    array();
            $Ratesdetails_array =    array();
            $TmpInvArray        =    array();
            
            for ($nor = 0; $nor < $num_rooms; $nor++)
            {
                $totExtraCharge = 0;
                if (isset($finalAdultChildArr[$nor]['children'])) {
                    $reqchild = (int) sizeof($finalAdultChildArr[$nor]['children']);
                } else {
                    $reqchild = 0;
                }
                
                $reqadult = (int) $finalAdultChildArr[$nor]['adults'];
                $_REQUEST['number_adults'] = $reqadult;
                $_REQUEST['number_children'] = $reqchild;
                
                $op = $this->getRoomList($hotelcode,$apikey);
                    
                if (count($op) <= 0) {
                    $notAvail[] = 1;
                }
                
                if (!in_array(1, $notAvail) && (is_array($op) || is_object($op))) {
                        $cnt         = 0;
                        $RateCnt     = 0;
                        $RoomRateCnt = 0;
                        foreach ($op as $key => $rooms) {
                            $avg_min = isset($rooms['Avg_min_nights']) ? $rooms['Avg_min_nights'] : 1;
                            
                            $rateTypeId    = $rooms['ratetypeunkid'];
                            $ratePlanUnkId = $rooms['roomrateunkid'];
                            $roomTypeUnId  = $rooms['roomtypeunkid'];
                            
                            if ($rooms['deals'] != '') {
                                $Roomtype_Name = $rooms['Roomtype_Name'] . "|" . $rooms['Room_Name'];
                            } else {
                                $Roomtype_Name = $rooms['Roomtype_Name'];
                            }
                            
                            $arrExtraCharge = array();
                            $arrExtraCharge = $this->calculateTotalExtraCharge($hotelcode, $apikey, $checkin, $checkout, $reqadult_tot, $reqchild_tot, $arrAllExtraCharges, $ratePlanUnkId);
                       
                            $totExtraChargePayment = 0;
                            //Devang : 11 March 2020 : Changes for handle count() error
                            if(isset($arrExtraCharge['total_exchanrges']) && !empty($arrExtraCharge['total_exchanrges']) && $arrExtraCharge['total_exchanrges'] > 0){
                                $totExtraChargePayment = $arrExtraCharge['total_exchanrges'];    
                            }
                            
                            if (isset($avg_min) && !empty($avg_min)) {
                                if ((int) $days < (int) $avg_min) {
                                    $this->log->logIt("continue >>" . $rooms['Room_Name']);
                                    continue;
                                } else {
                                    $this->log->logIt("no continue >>" . $rooms['Room_Name']);
                                }
                            }
                            
                            $inventory = $rooms['available_rooms'];
                            $flag      = 0;
                            foreach ($inventory as $k => $value) {
                                if ($value <= 0) {
                                    $flag = 1;
                                    break;
                                }
                            }
                            if ($flag == 0) {
                                for ($i = 0; $i < $days; $i++) {
                                    $currentdate  = date('Y-m-d', strtotime(' + ' . $i . ' days', strtotime($checkin)));
                                    $n            = $i + 1;
                                    $checkout_new = date('Y-m-d', strtotime(' + ' . $n . ' days', strtotime($checkin)));
                                   
                                    if (sizeof($rooms['available_rooms']) == 0)
                                        continue;
                                  
                                    $inventory = (isset($rooms['available_rooms'][$currentdate])) ? (int) $rooms['available_rooms'][$currentdate] : 0;
                                    
                                    $baseadult = $rooms['base_adult_occupancy'];
                                    $basechild = $rooms['base_child_occupancy'];
                                    $maxadult  = $rooms['max_adult_occupancy'];
                                    $maxchild  = $rooms['max_child_occupancy'];
                                    
                                    if ($reqadult > $maxadult) {
                                        continue;
                                    }
                                    
                                    if ($reqchild > $maxchild) {
                                        continue;
                                    }
                                   
                                    $total_tax   = $rooms['Total_Tax'];
                                    $final_price = $rooms['Final_Total_Price'];
                                    $TotalPrice  = $rooms['Total_Price'];
                                    
                                    $total_adjustment = $rooms['Total_Adjusment'];
                                    
                                    if ($total_tax == 0 && $total_adjustment < 0)
                                        $total_tax = 0;
                                    else
                                        $total_tax = $total_tax + $total_adjustment;
                                        
                                    $roomprice = 0;
                                    
                                    if(isset($rooms['room_rates_info']['exclusive_tax'][$currentdate])){
                                        $roomprice = (string) $rooms['room_rates_info']['exclusive_tax'][$currentdate];
                                    }
                                    
                                    if ($roomprice == 0 && trim($conversion) == '') {
                                        continue;
                                    }
                                    
                                    $this->log->logIt($this->module.' Room price -- '.$roomprice);
                                    
                                    $romm_extra_price = (string) $rooms['Total_Price'];
                                    $localfolder      = (string) $rooms['localfolder'];
                                    
                                    $CalDateFormat        = (string) $rooms['CalDateFormat'];
                                    $digits_after_decimal = (string) $rooms['digits_after_decimal'];
                                    $hotelCode            = array();
                                    $hotelCode            = $rooms['hotelcode'];
                                    $hotelCode            = substr($hotelCode, 0, 50);
                                    $hotelCode            = htmlentities($hotelCode, ENT_COMPAT, "UTF-8");
                                    $response_type        = "response_type";
                                   
                                    $roomname = $rooms['Room_Name'];
                                    $roomname = substr($roomname, 0, 50);
                                    $roomname = htmlentities($roomname, ENT_COMPAT, "UTF-8");
                                    
                                    if ($conversion > 0) {
                                        $roomprice        = $roomprice * $conversion;
                                        $romm_extra_price = $romm_extra_price * $conversion;
                                        $total_tax        = $total_tax * $conversion;
                                        $TotalPrice       = $TotalPrice * $conversion;
                                        
                                        $final_price           = round($final_price * $conversion);
                                        $total_adjustment      = $total_adjustment * $conversion;
                                        $totExtraChargePayment = $totExtraChargePayment * $conversion;
                                    }
                                    
                                    $roomprice        = round($roomprice, $digits_after_decimal);
                                    $romm_extra_price = round($romm_extra_price, $digits_after_decimal);
                                    $total_tax        = round($total_tax, $digits_after_decimal);
                                    $final_price      = round($final_price, $digits_after_decimal);
                                    $TotalPrice       = round($TotalPrice, $digits_after_decimal);
                                    
                                    $total_adjustment      = round($total_adjustment, $digits_after_decimal);
                                    $totExtraChargePayment = round($totExtraChargePayment, $digits_after_decimal);
                                    
                                    $this->log->logIt("Total Extra charge >>" . $totExtraChargePayment);
                                    
                                    $RoomAmenities      = (string) $rooms['RoomAmenities'];
                                    $hidefrommetasearch = (string) $rooms['hidefrommetasearch'];
                                    
                                    preg_match("/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/", $checkin, $datePart); //Pinal - 24 December 2019 - Purpose : PHP5.6 to 7.2 [deprecatedFunctions]
                                    $cal_date = date(staticarray::$webcalformat_key[$CalDateFormat], mktime(0, 0, 0, $datePart[2], $datePart[3], $datePart[1]));
                                    
                                    if ($hidefrommetasearch == '' && $inventory >= $num_rooms) {
                                    
                                        if (count($TmpInvArray) >= 0 ) {
                                            $tmp[$Roomtype_Name.'_'.$ratePlanUnkId] = $nor;
                                            $TmpInvArray = $tmp;
                                        }
                                        
                                        if (count($Roomdetails_array) >= 0 && !in_array($Roomtype_Name, $Roomdetails_array)) {
                                            array_push($Roomdetails_array, $Roomtype_Name);
                                        }
                                        if (count($Ratesdetails_array) >= 0 && !in_array($ratePlanUnkId, $Ratesdetails_array)) {
                                            array_push($Ratesdetails_array, $ratePlanUnkId);
                                        }
                                        
                                        $Index                                 = '';
                                        $RoomIndex                             = '';
                                        $temp_roomarray[$hotelcode][$cnt]      = $roomname; //to check room for multi room case        
                                        $roomarray[$hotelcode][$response_type] = "available";
                                        //Room details
                                        if (isset($roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["room_types"]) && in_array($Roomtype_Name, array_column($roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["room_types"], 'persistent_room_type_code'))) {
                                            $countArr = count($roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["room_types"]);
                                            
                                            $RoomIndex = array_search($roomTypeUnId, array_column($roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["room_types"], 'persistent_room_type_code')) + 1;
                                        } else {
                                            $cnt = $cnt + 1;
                                            $roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["room_types"][$cnt]['persistent_room_type_code'] = $Roomtype_Name;
                                        }
                                        //Rate plan
                                        if (isset($roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["rate_plans"]) && in_array($ratePlanUnkId, array_column($roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["rate_plans"], 'persistent_rate_plan_code'))) {
                                            $countArr = count($roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["rate_plans"]);
                                            
                                            $Index = array_search($ratePlanUnkId, array_column($roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["rate_plans"], 'persistent_rate_plan_code')) + 1;
                                            
                                        } else {
                                            $RateCnt = $RateCnt + 1;
                                            $roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["rate_plans"][$RateCnt]['persistent_rate_plan_code'] = $ratePlanUnkId;
                                        }
                                        //RoomRate
                                        $key = array_search($Roomtype_Name, $Roomdetails_array);
                                        $key = $key +1;
                                        $key1 = array_search($ratePlanUnkId, $Ratesdetails_array);
                                        $key1 = $key1 +1;
                                        $roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["room_rates"][$key.'_'.$key1]["room_type_key"] = '';
                                        $roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["room_rates"][$key.'_'.$key1]["rate_plan_key"] = '';
                                        if($hotelcode == 12841){ //Akshay Parihar - 20-05-2019 - display cname url in response
                                            $roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["room_rates"][$key.'_'.$key1]["url"] = "https://webres.evolveback.com/booking/searchroom.php?HotelId=" . $localfolder . "&checkin=" . $cal_date . "&nonights=" . $days . "&calendarDateFormat=" . $CalDateFormat . "&adults=" . $reqadult_tot . "&child=" . $reqchild_tot . "&rooms=" . $num_rooms;
                                        }
                                        else
                                        {
                                            $roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["room_rates"][$key.'_'.$key1]["url"] = $this->interfacehost . "/booking/searchroom.php?HotelId=" . $localfolder . "&checkin=" . $cal_date . "&nonights=" . $days . "&calendarDateFormat=" . $CalDateFormat . "&adults=" . $reqadult_tot . "&child=" . $reqchild_tot . "&rooms=" . $num_rooms;    
                                        }
                                        
                                        
                                        $taxarr = ($total_tax != 0) ? array(
                                                "price" => array(
                                                    "requested_currency_price" => array(
                                                        "amount" => $total_tax,
                                                        "currency" => strtoupper($currency)
                                                    )
                                                ),
                                                "type" => 'tax',
                                                "sub_type" => 'tax_other',
                                                "paid_at_checkout" => 'false'
                                            ) : false;
                                        $feearr = ($totExtraChargePayment != 0) ? array(
                                                "price" => array(
                                                    "requested_currency_price" => array(
                                                        "amount" => $totExtraChargePayment,
                                                        "currency" => strtoupper($currency)
                                                    )
                                                ),
                                                "type" => 'fee',
                                                "sub_type" => 'fee_other',
                                                "paid_at_checkout" => 'false'
                                            ) : false;
                                        $roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["room_rates"][$key.'_'.$key1]["line_items"][$nor] = array(
                                                array(
                                                    "price" => array(
                                                        "requested_currency_price" => array(
                                                            "amount" => $TotalPrice,
                                                            "currency" => strtoupper($currency)
                                                        )
                                                    ),
                                                    "type" => 'rate',
                                                    "paid_at_checkout" => 'false'
                                                )
                                            );
                                        if ($taxarr != false && $taxarr["price"]["requested_currency_price"]["amount"] != 0) {
                                                array_push($roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["room_rates"][$key.'_'.$key1]["line_items"][$nor], $taxarr);
                                            }
                                        if ($feearr != false && $feearr["price"]["requested_currency_price"]["amount"] != 0) {
                                                array_push($roomarray[$hotelcode][$roomarray[$hotelcode][$response_type]]["room_rates"][$key.'_'.$key1]["line_items"][$nor], $feearr);
                                            }
                                    }
                                }
                            }
                        }
                    }
                $check_room_arr[$nor] = $temp_roomarray; 
                $temp_roomarray       = array(); 
            }
            
            $arrFinalResult = [];
            $i = 1;
            
            if(isset($roomarray[$hotelcode]['available']["room_rates"]))
            {
              foreach($roomarray[$hotelcode]['available']["room_rates"] as $combinationKey => $arrRoomRate)
                {   
                    $name = '';
                    $rate = '';
                    $arrCombinationKeys = [];
                    $arrCombinationKeys = explode("_",$combinationKey);
                    
                    $arrSubResult = [];
                    $arrSubResult['room_type_key'] = $arrCombinationKeys[0];
                    $arrSubResult['rate_plan_key'] = $arrCombinationKeys[1];
                    if(array_key_exists($arrSubResult['room_type_key']-1,$Roomdetails_array))
                    {
                        $name = $Roomdetails_array[$arrSubResult['room_type_key']-1];
                    }
                    
                    if(array_key_exists($arrSubResult['rate_plan_key']-1,$Ratesdetails_array))
                    {
                        $rate = $Ratesdetails_array[$arrSubResult['rate_plan_key']-1];
                    }
                    
                    if(array_key_exists($name.'_'.$rate,$TmpInvArray) && $TmpInvArray[$name.'_'.$rate] + 1 == $num_rooms)
                    {
                        $arrSubResult['url'] = $arrRoomRate['url'];
                        
                        $arrFinalAmounts = [];
                        $arrFinalAmounts['total_rate'] = 0;
                        $arrFinalAmounts['total_tax'] = 0;
                        $arrFinalAmounts['total_fees'] = 0;                    
                        foreach($arrRoomRate['line_items'] as $index => $arrPrice)
                        {
                            foreach($arrPrice as $index1 => $arrPrice1){
                                
                                if(isset($arrPrice1['type']) && $arrPrice1['type'] == "rate")
                                {
                                     $arrFinalAmounts['total_rate'] +=   $arrPrice1['price']['requested_currency_price']['amount'];
                                     $arrFinalAmounts['currency'] = $arrPrice1['price']['requested_currency_price']['currency'];
                                 }
                                 if(isset($arrPrice1['type']) && $arrPrice1['type'] == "tax")
                                {
                                     $arrFinalAmounts['total_tax'] +=   $arrPrice1['price']['requested_currency_price']['amount']; 
                                }
                                if(isset($arrPrice1['type']) && $arrPrice1['type'] == "fee")
                                {
                                     $arrFinalAmounts['total_fees'] =   $arrPrice1['price']['requested_currency_price']['amount']; 
                                } 
                            }
                        }
                        
                        $tax_array = ($arrFinalAmounts['total_tax'] != 0) ? array("price"=>array(
                                "requested_currency_price" => array("amount" => $arrFinalAmounts['total_tax'],"currency" => $arrFinalAmounts['currency'])),
                                "type" => "tax",
                                "sub_type" => 'tax_other',
                                "paid_at_checkout" => "false",
                                                 ) : false ;
                        $fee_array = ($arrFinalAmounts['total_fees'] != 0) ? array("price"=>array(
                                "requested_currency_price" => array("amount" => $arrFinalAmounts['total_fees'],"currency" => $arrFinalAmounts['currency'])),
                                "type" => "fee",
                                "sub_type" => 'fee_other',
                                "paid_at_checkout" => "false",
                                                 ) : false ;
                        
                        $arrSubResult['line_items'] =array(
                            array("price"=>array(
                                "requested_currency_price" => array("amount" => $arrFinalAmounts['total_rate'],"currency" => $arrFinalAmounts['currency'])),
                                "type" => "rate",
                                "paid_at_checkout" => "false",
                                                ));
                        
                        if ($tax_array != false && $arrFinalAmounts['total_tax'] != 0) {
                                array_push($arrSubResult['line_items'], $tax_array);
                            }
                            
                        if ($fee_array != false && $arrFinalAmounts['total_fees'] != 0) {
                                array_push($arrSubResult['line_items'], $fee_array);
                            }
                           
                        $arrFinalResult[$i] = $arrSubResult;
                        $i++;
                    }
                    else{
                        foreach($roomarray[$hotelcode]['available']["room_types"] as $combinationKey => $arrRoomRate){
                            if($arrRoomRate['persistent_room_type_code'] == $name)
                            {
                              unset($roomarray[$hotelcode]['available']["room_types"][$combinationKey]);
                            }
                        }
                        foreach($roomarray[$hotelcode]['available']["rate_plans"] as $combinationKey => $arrRatePlan){
                            if($arrRatePlan['persistent_rate_plan_code'] == $rate)
                            {
                              unset($roomarray[$hotelcode]['available']["rate_plans"][$combinationKey]);
                            }
                        }
                    }
                }  
            }
            else{
                    $roomarray = array(
                    $hotelcode => array(
                        "response_type" => "unavailable"
                    )
                );
            }
            
            $roomarray[$hotelcode]['available']["room_rates"] = $arrFinalResult;
            $temp1_roomarray = array();
            if (count($check_room_arr) > 1) {
                $temp1_roomarray = call_user_func_array('array_intersect_key', $check_room_arr);
            } else {
                foreach ($check_room_arr as $val) {
                    $temp1_roomarray = $val;
                }
            }
            foreach ($roomarray as $hotelCode => $roomvalues) {
                $tem_keys = array_keys($temp1_roomarray);
                if (in_array($hotelCode, $tem_keys)) {
                    $this->log->logIt($this->module." - Room Not Removed - " . $hotelCode);
                } else {
                    unset($roomarray[$hotelCode]);
                    $this->log->logIt($this->module." - Room Removed For Multiroom - " . $hotelCode);
                }
            }
            if (count($roomarray) <= 0) {
                $roomarray = array(
                    $hotelcode => array(
                        "response_type" => "unavailable"
                    )
                );
            }
            if (!in_array(1, $notAvail))
                return $roomarray;
            else
                return;
            
            exit(0);
        }
        catch(Exception $e){
            $this->log->logIt($this->module." - Exception in " . $this->module . " - hotelAvailableData - " . $e);
            $this->handleException($e);
        }
	}
	
	function getExpanded_availability($detail)
    {
		try{
			$this->log->logIt($this->module.' - getExpanded_availability ');
			$this->log->logIt($this->module.' request data - '.json_encode($detail));

			$hotelcode                                   = $this->hotelcode;
            $_SESSION['prefix']                          = 'hotel';
			$_SESSION[$_SESSION['prefix']]['hotel_code'] = $hotelcode;
			
			$checkin  = $detail['start_date'];
            $checkout = $detail['end_date'];
            $apikey   = $detail['api_key'];
			
			$tempAdultChildArr 	= array();
			$finalAdultChildArr = array();

			foreach($detail['party_details'] as $key => $Objvalue){
                $Objvalue = (array)$Objvalue;
                    $tempAdultChildArr[$Objvalue["adults"] . "oldkey" . $key] = $Objvalue;
                    ksort($tempAdultChildArr); 
                    $finalAdultChildArr = array_values($tempAdultChildArr);
			}

			$days = util::DateDiff($checkin, $checkout);
            
            $reqadult_tot = $reqchild_tot = $reqchild = 0;
			
			$num_rooms = 1;
            if (isset($detail['num_rooms'])){
				$num_rooms = (int) trim($detail['num_rooms']);
			}
			$_REQUEST['HotelCode'] = $hotelcode;
            $_REQUEST['APIKey'] = $apikey;
            $_REQUEST['check_in_date'] = $checkin;
            $_REQUEST['check_out_date'] = $checkout;
            $_REQUEST['num_rooms'] = 1;
            $_REQUEST['property_configuration_info'] = 1;
            $_REQUEST['showsummary'] = 1;

			$currency = $detail['currency'];
            
			$hotelData = $this->getHotelDetail($hotelcode,$apikey);
			
			//$this->log->logIt($this->module.' - Hotel data - '.json_encode($hotelData));
            
            $cancelstr = $hotelData[0]['Cancellation_Policy'];
            $cancelstr = trim(strip_tags($cancelstr));
			$cancelstr = preg_replace("/\r\n|\r|\n|\t/", '', $cancelstr);
			
			$cancelstr = htmlentities($cancelstr, ENT_COMPAT, "UTF-8");
            if (strlen($cancelstr) >= 1000){
				$cancelstr = substr($cancelstr, 0, 900) . ' ...';
			}
				
			$policy    = $hotelData[0]['CheckIn_Policy'];
            $policy    = strip_tags($policy);
			$policy    = preg_replace("/\r\n|\r|\n|\t/", '', $policy);
			
			$hotel_img = '';
            if (isset($hotelData[0]['HotelImages'][0])){
				$hotel_img = $hotelData[0]['HotelImages'][0];
			}
			
			$hot_country = strtoupper($hotelData[0]['Country_ISOCode']);
            if ($hot_country != '' && $hot_country != 'INDIA'){
				$hot_country = $hot_country;
			}
            else{
				$hot_country = 'IND';
			}
				
			if (isset($hotelData[0]['State']) && trim($hotelData[0]['State']) != ''){
				$basic_hotel["state"] = strtoupper($hotelData[0]['State']);
			}

            if (isset($hotelData[0]['Zipcode']) && trim($hotelData[0]['Zipcode']) != ''){
				$basic_hotel["postal_code"] = $hotelData[0]['Zipcode'];
			}

			$hotelId = $hotelcode;

			if (isset($detail['children'])) {
                $ch_cnt = 0;
                foreach ($detail['children'] as $key => $ch_vals) {
                    if (intval($ch_vals))
                        ++$ch_cnt;
                }
                $reqchild_tot = $ch_cnt;
			}
			
			$reqadult_tot = array_sum($detail['num_adults']);
			$base_currency = (string) $hotelData[0]['CurrencyCode'];

			if (strtoupper($base_currency) != strtoupper($currency)){
				$conversion = $this->new_currency(strtoupper($base_currency), strtoupper($currency), 1);
			}
            else{
				$conversion = 1;
			}

			$arrAllExtraCharges = array();
            $arrAllExtraCharges = $this->getExtracharge($hotelcode);
			$this->log->logIt($this->module." - arrAllExtraCharges " . json_encode($arrAllExtraCharges, true));
			
			$roomarray = $notAvail = $check_room_arr = $temp_roomarray = $comArry = $Roomdetails_array = $Ratesdetails_array = $TmpInvArray = array();

			for ($nor = 0; $nor < $num_rooms; $nor++) // number of Rooms
			{
				if (isset($finalAdultChildArr[$nor]['children'])){
					$reqchild = (int) sizeof($finalAdultChildArr[$nor]['children']);
				}
				else{
					$reqchild = 0;
				}
				$reqadult = (int) $finalAdultChildArr[$nor]['adults'];

				$_REQUEST['number_adults'] = $reqadult;
				$_REQUEST['number_children'] = $reqchild;

				$url = $this->interfacehost . "/booking/reservation_api/listing.php?request_type=RoomList&HotelCode=$hotelcode&APIKey=$apikey&check_in_date=$checkin&check_out_date=$checkout&number_adults=$reqadult&number_children=$reqchild&num_rooms=1&property_configuration_info=1&showsummary=1";
				
				$op = $this->getRoomList($hotelcode,$apikey);

				//$this->log->logIt($this->module.' - room data - '.json_encode($op));

				if (count($op) <= 0) {
                    $notAvail[] = 1;
				}
				$roomtax     = $adjustment = 0;
				$roomdetail  = array();
				
				if (!in_array(1, $notAvail) && (is_array($op) || is_object($op))) {
					$cnt           = 0;
					$RateCnt       = 0;
					$RoomRateCnt   = 0;
					$response_type = 'response_type';

					foreach ($op as $key => $rooms) {
						$avg_min        = isset($rooms['Avg_min_nights']) ? $rooms['Avg_min_nights'] : 1;
						$extraadultrate = 0;
						$extraadult_tax = 0;
						$extrachildrate = 0;
						$extrachild_tax = 0;

						$roomRateUnkId = $rooms['roomrateunkid'];
						$ratePlanUnkId = $rooms['ratetypeunkid'];
						$roomTypeUnId  = $rooms['roomtypeunkid'];
						$RatePlan_Name = $rooms['Room_Name'];

						if ($rooms['deals'] != '') {
							$Roomtype_Name = $rooms['Roomtype_Name'] . "|" . $rooms['Room_Name'];
						} else {
							$Roomtype_Name = $rooms['Roomtype_Name'];
						}
						
						$totExtraChargePayment_arr = array();
						$totExtraChargePayment_arr = $this->calculateTotalExtraCharge($hotelId, $apikey, $checkin, $checkout, $reqadult_tot, $reqchild_tot, $arrAllExtraCharges, $roomRateUnkId);
						
						$totExtraChargePayment = 0;
                        //Devang : 11 March 2020 : Changes for handle count() error
						if(isset($arrExtraCharge['total_exchanrges']) && !empty($arrExtraCharge['total_exchanrges']) && count($arrExtraCharge['total_exchanrges']) > 0){
							$totExtraChargePayment = $arrExtraCharge['total_exchanrges'];    
						}

						if (isset($avg_min) && !empty($avg_min)) {
							if ((int) $days < (int) $avg_min) {
								$this->log->logIt($this->module." - continue - " . $rooms['Room_Name']);
								continue;
							} else {
								$this->log->logIt($this->module." - no continue - " . $rooms['Room_Name']);
							}
						}

						$inventory = $rooms['available_rooms'];
						$flag      = 0;
						foreach ($inventory as $k => $value) {
							if ($value <= 0) {
								$flag = 1;
								break;
							}
						}

						if ($flag == 0) {
							for ($i = 0; $i < $days; $i++) {
								$currentdate  = date('Y-m-d', strtotime(' + ' . $i . ' days', strtotime($checkin)));
								$n            = $i + 1;
								$checkout_new = date('Y-m-d', strtotime(' + ' . $n . ' days', strtotime($checkin)));

								if (sizeof($rooms['available_rooms']) == 0){
									continue;
								}
								$inventory = (isset($rooms['available_rooms'][$currentdate])) ? (int) $rooms['available_rooms'][$currentdate] : 0;
								$this->log->logIt($this->module." - Conversion Ratio - " . $conversion);
								
								$baseadult = $rooms['base_adult_occupancy'];
								$basechild = $rooms['base_child_occupancy'];
								$maxadult  = $rooms['max_adult_occupancy'];
								$maxchild  = $rooms['max_child_occupancy'];

								if ($reqadult > $maxadult){
									continue;
								}
                                if ($reqchild > $maxchild){
									continue;
								}

								if (isset($rooms['room_rates_info']['tax'][$currentdate])){
									$roomtax = (string) $rooms['room_rates_info']['tax'][$currentdate];
								}

								if (isset($rooms['room_rates_info']['adjustment'][$currentdate])){
									$adjustment = (string) $rooms['room_rates_info']['adjustment'][$currentdate];
								}

								if (isset($rooms['extra_adult_rates_info']['exclusive_tax'][$currentdate]) && ($reqadult > $baseadult)){
									$extraadultrate = (string) $rooms['extra_adult_rates_info']['exclusive_tax'][$currentdate];
								}
                                    
								if (isset($rooms['extra_adult_rates_info']['tax'][$currentdate]) && ($reqadult > $baseadult)){
									$extraadult_tax = (string) $rooms['extra_adult_rates_info']['tax'][$currentdate];
								}
															
								if (isset($rooms['extra_child_rates_info']['exclusive_tax'][$currentdate]) && ($reqchild > $basechild)){
									$extrachildrate = (string) $rooms['extra_child_rates_info']['exclusive_tax'][$currentdate];
								}
								
								if (isset($rooms['extra_child_rates_info']['tax'][$currentdate]) && ($reqchild > $basechild)){
									$extrachild_tax = (string) $rooms['extra_child_rates_info']['tax'][$currentdate];
								}

								$total_tax        = $rooms['Total_Tax'];
								$final_price      = $rooms['Final_Total_Price'];
								$TotalPrice       = $rooms['Total_Price'];
								$total_adjustment = $rooms['Total_Adjusment'];

								if ($total_tax == 0 && $total_adjustment < 0){
									$total_tax = 0;
								}
								else{
									$total_tax = $total_tax + $total_adjustment;
								}
								
								$roomprice = (string) $rooms['room_rates_info']['exclusive_tax'][$currentdate];
								
								if ($roomprice == 0 && trim($conversion) == ''){
									continue;
								}
									
								$romm_extra_price = (string) $rooms['Total_Price'];
								$localfolder      = (string) $rooms['localfolder'];
								$roomtypeunkid    = $rooms['roomtypeunkid'];

								$hotelCode      = $rooms['hotelcode'];
								$hotelCode      = substr($hotelCode, 0, 50);
								$hotelCode      = htmlentities($hotelCode, ENT_COMPAT, "UTF-8");
								$CalDateFormat  = (string) $rooms['CalDateFormat'];
								$masterroomname = $rooms['Roomtype_Name'];
								$masterroomname = substr($masterroomname, 0, 50);
								$masterroomname = htmlentities($masterroomname, ENT_COMPAT, "UTF-8");

								$roomdesc = $rooms['Room_Description'];
								$roomdesc = substr($roomdesc, 0, 500);
								$roomdesc = htmlentities($roomdesc, ENT_COMPAT, "UTF-8");

								if ($conversion > 0) {
									$roomprice        = $roomprice * $conversion;
									$romm_extra_price = $romm_extra_price * $conversion;
									$total_tax        = $total_tax * $conversion;
									$final_price      = round($final_price * $conversion);
									
									$TotalPrice = $TotalPrice * $conversion;
									
									$total_adjustment      = $total_adjustment * $conversion;
									$totExtraChargePayment = $totExtraChargePayment * $conversion;
									$extraadultrate        = $extraadultrate * $conversion;
									$extraadult_tax        = $extraadult_tax * $conversion;
									$extrachildrate        = $extrachildrate * $conversion;
									$extrachild_tax        = $extrachild_tax * $conversion;
								}
								$digits_after_decimal = (string) $rooms['digits_after_decimal'];
                                    
								$roomprice             = round($roomprice, $digits_after_decimal);
								$romm_extra_price      = round($romm_extra_price, $digits_after_decimal);
								$total_tax             = round($total_tax, $digits_after_decimal);
								$final_price           = round($final_price, $digits_after_decimal);
								$total_adjustment      = round($total_adjustment, $digits_after_decimal);
								$totExtraChargePayment = round($totExtraChargePayment, $digits_after_decimal);
								$TotalPrice            = round($TotalPrice, $digits_after_decimal);
								
								$extraadultrate = round($extraadultrate, $digits_after_decimal);
								$extraadult_tax = round($extraadult_tax, $digits_after_decimal);
								$extrachildrate = round($extrachildrate, $digits_after_decimal);
								$extrachild_tax = round($extrachild_tax, $digits_after_decimal);

								$RoomAmenities      = str_replace('"', "", (string) $rooms['RoomAmenities']);
								$room_img           = (string) $rooms['room_main_image'];
								$hidefrommetasearch = (string) $rooms['hidefrommetasearch'];
								$roomrateunkid      = $rooms['roomrateunkid'];
								$roomtypeunkid      = $rooms['roomtypeunkid'];
								$ratetypeunkid      = $rooms['ratetypeunkid'];
								$refundable         = (int) $rooms['prepaid_noncancel_nonrefundable'];
								$cancellation_day   = (isset($rooms['cancellation_deadline'])) ? $rooms['cancellation_deadline'] : "";

								$refundstr             = "";
								$cancellation_deadline = "";
								
								if ($refundable == 0) {
									if ($cancellation_day != "") {
										$cancellation_deadline = date('Y-m-d\TH:i:s', strtotime('-' . $cancellation_day . ' day', strtotime($checkin)));
									} else {
										$cancellation_deadline = date('Y-m-d\TH:i:s', strtotime($checkin));
									}

									$refundstr    = 'full'; 
									$TempCurrDate = date("Y-m-d");

									if (strtotime($cancellation_deadline) <= strtotime($TempCurrDate)) {
										$refundstr = 'none'; 
									}
								} else {
									$refundstr = 'none'; 
								}

								preg_match("/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/", $checkin, $datePart); //Pinal - 24 December 2019 - Purpose : PHP5.6 to 7.2 [deprecatedFunctions]
								$cal_date = date(staticarray::$webcalformat_key[$CalDateFormat], mktime(0, 0, 0, $datePart[2], $datePart[3], $datePart[1]));
								
								if ($hidefrommetasearch == '' && $inventory >= $num_rooms) {
									if (count($TmpInvArray) >= 0 ) {
										$tmp[$Roomtype_Name.'_'.$roomRateUnkId] = $nor;
										$TmpInvArray = $tmp;
									}
									if (count($Roomdetails_array) >= 0 && !in_array($Roomtype_Name, $Roomdetails_array)) {
										array_push($Roomdetails_array, $Roomtype_Name);
									}
									if (count($Ratesdetails_array) >= 0 && !in_array($roomRateUnkId, $Ratesdetails_array)) {
										array_push($Ratesdetails_array, $roomRateUnkId);
									}
									$width     = $height = 150;
									$currency  = strtoupper($currency);
									$Index     = '';
									$RoomIndex = '';

									$temp_roomarray[$hotelCode]            = $hotelCode; 
									$roomarray[$hotelCode][$response_type] = "available";
									
									if (isset($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"]) && in_array($Roomtype_Name, array_column($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"], 'persistent_room_type_code'))) {
										$countArr = count($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"]);
										
										$RoomIndex = array_search($roomTypeUnId, array_column($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"], 'persistent_room_type_code')) + 1;
									} else {
										$cnt++;
										$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['persistent_room_type_code'] = $Roomtype_Name;
										$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['name']                      = $rooms['Room_Name'];
										if (isset($room_img) && $room_img != '') {
											$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['photos'] = array(
												array(
													'url' => $room_img,
													'width' => (int) $width,
													'height' => (int) $height,
													'caption' => ''
												)
											);
										} else {
											$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['photos'] = array();
										}
										if (isset($RoomAmenities) && $RoomAmenities != '') {
											$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['room_amenities'] = array(
												"standard" => array(),
												"custom" => explode(",", $RoomAmenities)
											);
										}
										$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['bed_configurations']       = array(
											array(
												"standard" => array(
													array(
														'code' => 1,
														'count' => 1
													)
												),
												"custom" => array(
													array(
														'name' => 'unknown',
														'count' => 1
													)
												)
											)
										);
										$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['extra_bed_configurations'] = array(
											array(
												"standard" => array(
													array(
														'code' => 1,
														'count' => 1
													)
												),
												"custom" => array(
													array(
														'name' => 'unknown',
														'count' => 1
													)
												)
											)
										);
										$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['max_occupancy']            = array(
											"number_of_adults" => $maxadult,
											"number_of_children" => $maxchild
										);
										$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['room_smoking_policy']      = 'unknown';
									}
									$Index = '';

									if (isset($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"]) && in_array($roomRateUnkId, array_column($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"], 'persistent_rate_plan_code'))) {
										$countArr = count($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"]);
										
										$Index = array_search($roomRateUnkId, array_column($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"], 'persistent_rate_plan_code')) + 1;
									} else {
										$RateCnt = $RateCnt + 1;
										$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['persistent_rate_plan_code'] = $roomRateUnkId;
										$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['name']                      = $RatePlan_Name;
										$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['photos']                    = array();
										if (isset($RoomAmenities) && $RoomAmenities != '') {
											$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['rate_amenities'] = array(
												"standard" => array(),
												"custom" => explode(",", $RoomAmenities)
											);
										}
										$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['cancellation_policy']['cancellation_summary'] = array(
											"refundable" => $refundstr,
											'unstructured_cancellation_text' => $cancelstr
										);
									   
										if ($refundstr == "full") {
											$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['cancellation_policy']['cancellation_summary'] = array(
												"refundable" => $refundstr,
												"cancellation_deadline" => $cancellation_deadline . 'Z',
												'unstructured_cancellation_text' => $cancelstr
											);
										}
										$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['meal_plan'] = array(
											"standard" => array(),
											"custom" => array()
										);
									}
									$key = array_search($Roomtype_Name, $Roomdetails_array);
									$key = $key +1;
									$key1 = array_search($roomRateUnkId, $Ratesdetails_array);
									$key1 = $key1 +1;

									$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["room_type_key"] = '';
									$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["rate_plan_key"] = '';
									$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["rooms_remaining"] = $inventory;
									$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["url"] = $url;

									$taxarr = ($total_tax != 0) ? array(
										"price" => array(
											"requested_currency_price" => array(
												"amount" => $total_tax,
												"currency" => strtoupper($currency)
											)
										),
										"type" => 'tax',
										"sub_type" => 'tax_other',
										"paid_at_checkout" => 'false'
									) : false;

									$feearr = ($totExtraChargePayment != 0) ? array(
											"price" => array(
												"requested_currency_price" => array(
													"amount" => $totExtraChargePayment,
													"currency" => strtoupper($currency)
												)
											),
											"type" => 'fee',
											"sub_type" => 'fee_other',
											"paid_at_checkout" => 'false'
										) : false;
									$roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["line_items"][$nor] = array(
										array(
											"price" => array(
												"requested_currency_price" => array(
													"amount" => $TotalPrice,
													"currency" => strtoupper($currency)
												)
											),
											"type" => 'rate',
											"paid_at_checkout" => 'false'
										)
									);

									if ($taxarr != false && $taxarr["price"]["requested_currency_price"]["amount"] != 0) {
										array_push($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["line_items"][$nor], $taxarr);
									}
									if ($feearr != false && $feearr["price"]["requested_currency_price"]["amount"] != 0) {
										array_push($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["line_items"][$nor], $feearr);
									}
								}
							}
						}
					}
				}
				$check_room_arr[$nor] = $temp_roomarray; 
				$temp_roomarray       = array(); 
			}
			$arrFinalResult = [];
			$i = 1;
			
			if(isset($roomarray[$hotelCode]['available']["room_rates"]))
			{
				foreach($roomarray[$hotelCode]['available']["room_rates"] as $combinationKey => $arrRoomRate)
				{
					$arrCombinationKeys = [];
					$arrCombinationKeys = explode("_",$combinationKey);
					
					$arrSubResult = [];
					$arrSubResult['room_type_key'] = $arrCombinationKeys[0];
					$arrSubResult['rate_plan_key'] = $arrCombinationKeys[1];
					if(array_key_exists($arrSubResult['room_type_key']-1,$Roomdetails_array))
					{
						$name = $Roomdetails_array[$arrSubResult['room_type_key']-1];
					}

					if(array_key_exists($arrSubResult['rate_plan_key']-1,$Ratesdetails_array))
					{
						$rate = $Ratesdetails_array[$arrSubResult['rate_plan_key']-1];
					}

					if(array_key_exists($name.'_'.$rate,$TmpInvArray) && $TmpInvArray[$name.'_'.$rate] + 1 == $num_rooms)
					{
						$arrSubResult['rooms_remaining'] = $arrRoomRate['rooms_remaining'];
						$arrSubResult['url'] = $arrRoomRate['url'];
						
						$arrFinalAmounts = [];
						$arrFinalAmounts['total_rate'] = 0;
						$arrFinalAmounts['total_tax'] = 0;
						$arrFinalAmounts['total_fees'] = 0;

						foreach($arrRoomRate['line_items'] as $index => $arrPrice)
						{
							foreach($arrPrice as $index1 => $arrPrice1){
								if(isset($arrPrice1['type']) && $arrPrice1['type'] == "rate")
								{
										$arrFinalAmounts['total_rate'] +=   $arrPrice1['price']['requested_currency_price']['amount'];
										$arrFinalAmounts['currency'] = $arrPrice1['price']['requested_currency_price']['currency'];
								}
								if(isset($arrPrice1['type']) && $arrPrice1['type'] == "tax")
								{
										$arrFinalAmounts['total_tax'] +=   $arrPrice1['price']['requested_currency_price']['amount']; 
								}
								if(isset($arrPrice1['type']) && $arrPrice1['type'] == "fee")
								{
										$arrFinalAmounts['total_fees'] =   $arrPrice1['price']['requested_currency_price']['amount']; 
								}
							}
						}
						$tax_array = ($arrFinalAmounts['total_tax'] != 0) ? array("price"=>array(
							"requested_currency_price" => array("amount" => $arrFinalAmounts['total_tax'],"currency" => $arrFinalAmounts['currency'])),
							"type" => "tax",
							"sub_type" => 'tax_other',
							"paid_at_checkout" => "false",
											 ) : false ;
						$fee_array = ($arrFinalAmounts['total_fees'] != 0) ? array("price"=>array(
								"requested_currency_price" => array("amount" => $arrFinalAmounts['total_fees'],"currency" => $arrFinalAmounts['currency'])),
								"type" => "fee",
								"sub_type" => 'fee_other',
								"paid_at_checkout" => "false",
												) : false ;
						
						$arrSubResult['line_items'] = array(
							array("price"=>array(
								"requested_currency_price" => array("amount" => $arrFinalAmounts['total_rate'],"currency" => $arrFinalAmounts['currency'])),
								"type" => "rate",
								"paid_at_checkout" => "false",
												));

						if ($tax_array != false && $arrFinalAmounts['total_tax'] != 0) {
								array_push($arrSubResult['line_items'], $tax_array);
						}
							
						if ($fee_array != false && $arrFinalAmounts['total_fees'] != 0) {
								array_push($arrSubResult['line_items'], $fee_array);
						}
						$arrFinalResult[$i] = $arrSubResult;
						$i++;
					}
					else
					{
						foreach($roomarray[$hotelCode]['available']["room_types"] as $combinationKey => $arrRoomRate){
							if($arrRoomRate['persistent_room_type_code'] == $name)
							{
							  unset($roomarray[$hotelCode]['available']["room_types"][$combinationKey]);
							}
						}
						foreach($roomarray[$hotelCode]['available']["rate_plans"] as $combinationKey => $arrRatePlan){
							if($arrRatePlan['persistent_rate_plan_code'] == $rate)
							{
							  unset($roomarray[$hotelCode]['available']["rate_plans"][$combinationKey]);
							}
						}
					}
				}
			}
			else{
				$roomarray = array(
                    $hotelcode => array(
                        "response_type" => "unavailable"
                        )
                    );
			}
			$roomarray[$hotelCode]['available']["room_rates"] = $arrFinalResult;
                
			$temp1_roomarray = array();
			if (count($check_room_arr) > 1)
				$temp1_roomarray = call_user_func_array('array_intersect_key', $check_room_arr);
			else {
				foreach ($check_room_arr as $val) {
					$temp1_roomarray = $val;
				}
			}
			foreach ($roomarray as $hotelCode => $roomvalues) {
				if (in_array($hotelCode, $temp1_roomarray)) {
					$this->log->logIt("Room not remove >> " . $hotelCode);
				} else {
					unset($roomarray[$hotelCode]);
					$this->log->logIt("Room remove for multi room >> " . $hotelCode);
				}
			}
			if (count($roomarray) <= 0) {
                $roomarray = array(
                    $hotelcode => array(
                        "response_type" => "unavailable"
                    )
                );
			}
			if (in_array(1, $notAvail)){
				unset($roomarray['hotel_room_types']);
			}
            return $roomarray;
		}
		catch(Exception $e ){
			$this->log->logIt($this->module.' - Exception - getExpanded_availability - '.$e);
		}
	}
	function getBooking_availability($detail)
    {
		try{
			$this->log->logIt($this->module.' -  getBooking_availability ');
			$hotelcode                                   = $this->hotelcode;
            $_SESSION['prefix']                          = 'hotel';
			$_SESSION[$_SESSION['prefix']]['hotel_code'] = $hotelcode;
			
			$checkin  			= $detail['start_date'];
            $checkout 			= $detail['end_date'];
            $apikey   			= $detail['api_key'];
            $tempAdultChildArr 	= array();
			$finalAdultChildArr = array();

			$_REQUEST['HotelCode'] = $hotelcode;
            $_REQUEST['APIKey'] = $apikey;
            $_REQUEST['check_in_date'] = $checkin;
            $_REQUEST['check_out_date'] = $checkout;
            $_REQUEST['num_rooms'] = 1;
            $_REQUEST['property_configuration_info'] = 1;
            $_REQUEST['showsummary'] = 1;
			
			foreach($detail['party_details'] as $key => $Objvalue){
                $Objvalue = (array)$Objvalue;
                    $tempAdultChildArr[$Objvalue["adults"] . "oldkey" . $key] = $Objvalue;
                    ksort($tempAdultChildArr); 
                    $finalAdultChildArr = array_values($tempAdultChildArr);
			}
			
			$days = util::DateDiff($checkin, $checkout);
			$reqadult_tot = $reqchild_tot = $reqchild = 0;
			
			$num_rooms = 1;
            if (isset($detail['num_rooms'])){
				$num_rooms = (int) trim($detail['num_rooms']);
			}
			$currency = $detail['currency'];

			$hotelData  = $this->getHotelDetail($hotelcode,$apikey);
            $Country    = $hotelData[0]['Country'];
            $Url        = $hotelData[0]['BookingEngineURL'];
            $Email      = $hotelData[0]['Email'];
			$websit_url = $hotelData[0]['Website'];
			
			$phoneCode  = $this->FindphoneCode($Country);

			$Terms_And_Conditions = substr($hotelData[0]['Hotel_Policy'], 0, 500);
            $Payment_Policy       = strip_tags($hotelData[0]['Booking_Conditions']);
			
			$Phone_number         = $hotelData[0]['Phone'];
            $Final_number         = str_replace('+' . $phoneCode, '', $Phone_number);

			if (strlen($Phone_number) > 14) {
                $position_phone = strpos($Phone_number, "/");
                $Final_number   = substr($Phone_number, 0, $position_phone);
            }
            if ($Final_number == '') {
                $position_phone = strpos($Phone_number, ",");
                $Final_number   = substr($Phone_number, 0, $position_phone);
                
                if ($Final_number == '') {
                    $Final_number = str_replace('+' . $phoneCode, '', $Phone_number);
                    $Final_number = str_replace(" ", '', $Final_number);
                }
			}
			
			$cancelstr = $hotelData[0]['Cancellation_Policy']	;
            $cancelstr = trim(strip_tags($cancelstr));
			$cancelstr = preg_replace("/\r\n|\r|\n|\t/", '', $cancelstr);
			
			$cancelstr = htmlentities($cancelstr, ENT_COMPAT, "UTF-8");
            if (strlen($cancelstr) >= 1000){
				$cancelstr = substr($cancelstr, 0, 900) . ' ...';
			}

			$policy = $hotelData[0]['CheckIn_Policy'];
            $policy = strip_tags($policy);
            $policy = preg_replace("/\r\n|\r|\n|\t/", '', $policy);
			
			$hotel_img = '';
            if (isset($hotelData[0]['HotelImages'][0])){
				$hotel_img = $hotelData[0]['HotelImages'][0];
			}
			
			$hot_country = strtoupper($hotelData[0]['Country_ISOCode']);
            if ($hot_country != '' && $hot_country != 'INDIA'){
				$hot_country = $hot_country;
			}
            else{
				$hot_country = 'IND';
			}

			if (isset($hotelData[0]['State']) && trim($hotelData[0]['State']) != ''){
				$basic_hotel["state"] = strtoupper($hotelData[0]['State']);
			}

            if (isset($hotelData[0]['Zipcode']) && trim($hotelData[0]['Zipcode']) != ''){
				$basic_hotel["postal_code"] = $hotelData[0]['Zipcode'];
			}

			$hotelId = $hotelcode;

			if (isset($detail['children'])) {
                $ch_cnt = 0;
                foreach ($detail['children'] as $key => $ch_vals) {
                    if (intval($ch_vals))
                        ++$ch_cnt;
                }
                $reqchild_tot = $ch_cnt;
			}

			$reqadult_tot = array_sum($detail['num_adults']);
			$base_currency = (string) $hotelData[0]['CurrencyCode'];

			if (strtoupper($base_currency) != strtoupper($currency)){
				$conversion = $this->new_currency(strtoupper($base_currency), strtoupper($currency), 1);
			}
            else{
				$conversion = 1;
			}

			$arrAllExtraCharges = array();
            $arrAllExtraCharges = $this->getExtracharge($hotelcode);
			$this->log->logIt($this->module." - arrAllExtraCharges " . json_encode($arrAllExtraCharges, true));

			$roomarray = $notAvail = $check_room_arr = $temp_roomarray = $comArry = $Roomdetails_array = $Ratesdetails_array = $TmpInvArray = array();

			for ($nor = 0; $nor < $num_rooms; $nor++) // number of Rooms
			{
				if (isset($finalAdultChildArr[$nor]['children'])){
					$reqchild = (int) sizeof($finalAdultChildArr[$nor]['children']);
				}
				else{
					$reqchild = 0;
				}
				$reqadult = (int) $finalAdultChildArr[$nor]['adults'];

				$_REQUEST['number_adults'] = $reqadult;
				$_REQUEST['number_children'] = $reqchild;

				$url = $this->interfacehost . "/booking/reservation_api/listing.php?request_type=RoomList&HotelCode=$hotelcode&APIKey=$apikey&check_in_date=$checkin&check_out_date=$checkout&number_adults=$reqadult&number_children=$reqchild&num_rooms=1&property_configuration_info=1&showsummary=1";

				$op = $this->getRoomList($hotelcode,$apikey);

				//$this->log->logIt($this->module.' - room data - '.json_encode($op));

				if (count($op) <= 0) {
                    $notAvail[] = 1;
				}
				$roomtax     = $adjustment = 0;
				$roomdetail  = array();

				if (!in_array(1, $notAvail) && (is_array($op) || is_object($op))) {
					$cnt               		= 0;
                        $RateCnt           	= 0;
                        $total_tax         	= 0;
                        $RoomRateCnt       	= 0;
                        $response_type     	= 'response_type';
                        $comArry           	= array();
						$package_count 		= 1;
						
						foreach ($op as $key => $rooms) {

							$Child_policy        = substr($hotelData[0]['Children_ExtraGuest_Details'], 0, 500);
							$Parking_policy_temp = substr($hotelData[0]['Parking_Policy'], 0, 500);
							
							if (isset($Parking_policy_temp) && $Parking_policy_temp != '' && strlen($Parking_policy_temp) > 10) {
                                
                            } else {
                                $Parking_policy_temp = null;
							}
							
							//if(isset($rooms['Hotel_amenities']) && count($rooms['Hotel_amenities']) > 0){
                            if(isset($rooms['Hotel_amenities']) && !empty($rooms['Hotel_amenities'])){//Kaushik Chauhan 29th July 2020 Purpose: Php7.2 Compitibility
								$hotel_amenities = json_encode($rooms['Hotel_amenities']);
							}
							else{
								$hotel_amenities = '';
							}
                            $hotel_amenities = trim($hotel_amenities, '"');
                            $hotel_amenities = (str_replace(array('\"','[',']'), '', $hotel_amenities));
							$hotel_amenities = implode(',',array_unique(explode(',', $hotel_amenities)));
							
							$basic_hotel = array(
												"name" => $hotelData[0]['Hotel_Name'],
												"address1" => $hotelData[0]['Address'],
												"country" => $hot_country,
												"city" => $hotelData[0]['City'],
												"state" => '',
												"postal_code" => '',
												"phone" => $hotelData[0]['Phone'],
												"checkin_checkout_policy" => substr($policy, 0, 1000), //maintain to 1000 character
												"checkin_time" => $rooms['check_in_time'],
												"checkout_time" => $rooms['check_out_time'],
												"hotel_smoking_policy" => array(
													"standard" => array(),
													"custom" => array()
												),
												"pet_policy" => array(
													"standard" => array(),
													"custom" => array()
												),
												"child_policy" => strip_tags(isset($Child_policy) && $Child_policy != '' && strlen($Child_policy) > 10 ? $Child_policy : null),
												"parking_shuttle" => array(
													"standard" => array(),
													"custom" => array(
														$Parking_policy_temp
													)
												),
												"hotel_amenities" => array(
													"standard" => array(),
													"custom" => explode(",",$hotel_amenities)
												),
												"photos" => array(
													array(
														'url' => $hotel_img,
														'width' => 150,
														'height' => 150,
														'caption' => ''
													)
												)
											);

							if ($hotel_img == null || $hotel_img == '') {
								unset($basic_hotel['photos']);
							}
							if ($Parking_policy_temp == null) {
								unset($basic_hotel['parking_shuttle']);
							}
							if (isset($hotelData[0]['State']) && trim($hotelData[0]['State']) != ''){
								$basic_hotel["state"] = strtoupper($hotelData[0]['State']);
							}
								
							if (isset($hotelData[0]['Zipcode']) && trim($hotelData[0]['Zipcode']) != ''){
								$basic_hotel["postal_code"] = $hotelData[0]['Zipcode'];
							}
								
							$avg_min        = isset($rooms['Avg_min_nights']) ? $rooms['Avg_min_nights'] : 1;
							$extraadultrate = $extraadult_tax = $extrachildrate = $extrachild_tax = 0;
							
							$RoomRateUnkId        = $rooms['roomrateunkid'];
                            $ratePlanUnkId        = $rooms['ratetypeunkid'];
                            $roomTypeUnId         = $rooms['roomtypeunkid'];
                            $RatePlan_Name        = $rooms['Room_Name'];
							$SpecialConditions    = strip_tags($rooms['specialconditions']);
							
							$tmp_RoomName = '';

							if ($rooms['deals'] != '') {
                                $Roomtype_Name = $rooms['Roomtype_Name'] . "|" . $rooms['Room_Name'];
                                $tmp_RoomName = $rooms['Roomtype_Name'] . "|" .$package_count;
                                $package_count++;
                                $RatePlan_Description = $rooms['Specials_Desc'];
                                if($RatePlan_Description == ''){
                                    $RatePlan_Description = $rooms['Room_Description'];    
                                }
                                $roomRateUnkId        	= $rooms['roomrateunkid']. "|" .$package_count; 
                            } else {
                                $Roomtype_Name 			= $rooms['Roomtype_Name'];
                                $tmp_RoomName 			= $rooms['Roomtype_Name'];
                                $RatePlan_Description 	= $rooms['Room_Description'];
                                $roomRateUnkId        	= $rooms['roomrateunkid'];
							}
							
							$RatePlan_Description = substr($RatePlan_Description, 0, 500);
							$RatePlan_Description = strip_tags($RatePlan_Description);
							
							$totExtraChargePayment_arr  = array();
							$totExtraChargePayment_arr  = $this->calculateTotalExtraCharge($hotelId, $apikey, $checkin, $checkout, $reqadult_tot, $reqchild_tot, $arrAllExtraCharges, $RoomRateUnkId);
							
							$totExtraChargePayment = 0;
                            $temp_totExtraChargePayment = '';
                            //Devang : 11 March 2020 : Changes for handle count() error
                            if(isset($totExtraChargePayment_arr['total_exchanrges']) && !empty($totExtraChargePayment_arr['total_exchanrges']) && count($totExtraChargePayment_arr['total_exchanrges']) > 0){
                                $temp_totExtraChargePayment = $totExtraChargePayment_arr['total_exchanrges'];  
							}
                    		
							if (isset($avg_min) && !empty($avg_min)) {
                                if ((int) $days < (int) $avg_min) {
                                    $this->log->logIt("continue >> " . $rooms['Room_Name']);
                                    continue;
                                } else {
                                    $this->log->logIt("no continue >> " . $rooms['Room_Name']);
                                }
							}
							$inventory = $rooms['available_rooms'];
                            $flag      = 0;
                            foreach ($inventory as $k => $value) {
                                if ($value <= 0) {
                                    $flag = 1;
                                    break;
                                }
							}
							
							if ($flag == 0) {
                                for ($i = 0; $i < $days; $i++) {
                                    $currentdate  = date('Y-m-d', strtotime(' + ' . $i . ' days', strtotime($checkin)));
                                    $n            = $i + 1;
                                    $checkout_new = date('Y-m-d', strtotime(' + ' . $n . ' days', strtotime($checkin)));
                                    
                                    if (sizeof($rooms['available_rooms']) == 0){
										continue;
									}
                                        
                                    
                                    $inventory1 = (isset($rooms['available_rooms'][$currentdate])) ? (int) $rooms['available_rooms'][$currentdate] : 0;
                                    
                                    $this->log->logIt($this->module." - Conversion Ratio - " . $conversion);
                                    
                                    $baseadult = $rooms['base_adult_occupancy'];
                                    $basechild = $rooms['base_child_occupancy'];
                                    $maxadult  = $rooms['max_adult_occupancy'];
                                    $maxchild  = $rooms['max_child_occupancy'];
                                    
                                    if ($reqadult > $maxadult)
                                        continue;
                                    
                                    if ($reqchild > $maxchild)
                                        continue;
                                    
                                    if (isset($rooms['room_rates_info']['tax'][$currentdate])){
										$roomtax = (string) $rooms['room_rates_info']['tax'][$currentdate];
									}
									
                                    if (isset($rooms['room_rates_info']['adjustment'][$currentdate])){
										$adjustment = (string) $rooms['room_rates_info']['adjustment'][$currentdate];
									}

                                    if (isset($rooms['extra_adult_rates_info']['exclusive_tax'][$currentdate]) && ($reqadult > $baseadult)){
										$extraadultrate = (string) $rooms['extra_adult_rates_info']['exclusive_tax'][$currentdate];
									}
                                                                        
                                    if (isset($rooms['extra_adult_rates_info']['tax'][$currentdate]) && ($reqadult > $baseadult)){
										$extraadult_tax = (string) $rooms['extra_adult_rates_info']['tax'][$currentdate];
									}
                                    
                                    if (isset($rooms['extra_child_rates_info']['exclusive_tax'][$currentdate]) && ($reqchild > $basechild)){
										$extrachildrate = (string) $rooms['extra_child_rates_info']['exclusive_tax'][$currentdate];
									}
                                    
                                    if (isset($rooms['extra_child_rates_info']['tax'][$currentdate]) && ($reqchild > $basechild)){
										$extrachild_tax = (string) $rooms['extra_child_rates_info']['tax'][$currentdate];
									}
                                    
                                    $total_tax        = $rooms['Total_Tax'];
                                    $total_tax1       = $rooms['Total_Tax'];
                                    $final_price      = $rooms['Final_Total_Price'];
                                    $total_adjustment = $rooms['Total_Adjusment'];
                                    $TotalPrice       = $rooms['Total_Price'];
                                    $TotalPrice1      = $rooms['Total_Price'];
                                    $currency_code    = $rooms['currency_code'];
                                  
                                    if ($total_tax == 0 && $total_adjustment < 0)
                                        $total_tax = 0;
                                    else
                                        $total_tax = $total_tax + $total_adjustment;
                                    
                                    $roomprice = (string) $rooms['room_rates_info']['exclusive_tax'][$currentdate];
                                    
                                    if ($roomprice == 0 && trim($conversion) == '')
                                        continue;
                                    
                                    $romm_extra_price = (string) $rooms['Total_Price'];
                                    $localfolder      = (string) $rooms['localfolder'];
                                    $roomtypeunkid    = $rooms['roomtypeunkid'];
                                    
                                    $hotelCode = $rooms['hotelcode'];
                                    $hotelCode = substr($hotelCode, 0, 50);
                                    $hotelCode = htmlentities($hotelCode, ENT_COMPAT, "UTF-8");
                                    
                                    $masterroomname = $rooms['Roomtype_Name'];
                                    $masterroomname = substr($masterroomname, 0, 50);
                                    $masterroomname = htmlentities($masterroomname, ENT_COMPAT, "UTF-8");
                                    
                                    $roomdesc = $rooms['Room_Description'];
                                    $roomdesc = substr($roomdesc, 0, 500);
                                    $roomdesc = htmlentities($roomdesc, ENT_COMPAT, "UTF-8");
                                   
                                    $totExtraChargePayment = $temp_totExtraChargePayment;
                                    
                                    if ($conversion > 0) {
                                        $roomprice             = $roomprice * $conversion;
                                        $romm_extra_price      = $romm_extra_price * $conversion;
                                        $total_tax             = $total_tax * $conversion;
                                        $final_price           = round($final_price * $conversion);
                                        $TotalPrice            = $TotalPrice * $conversion;
                                        $total_adjustment      = (float)$total_adjustment * $conversion;//Kaushik Chauhan 29th July 2020 Purpose: Apply Type Convrsion To solve the warning "A non-numeric value encountered in".
                                        $totExtraChargePayment = $totExtraChargePayment * $conversion;
                                        
                                        $extraadultrate = $extraadultrate * $conversion;
                                        $extraadult_tax = $extraadult_tax * $conversion;
                                        $extrachildrate = $extrachildrate * $conversion;
                                        $extrachild_tax = $extrachild_tax * $conversion;
                                    }
                                    
                                    $digits_after_decimal  = (string) $rooms['digits_after_decimal'];
                                    $roomprice             = round($roomprice, $digits_after_decimal);
                                    $romm_extra_price      = round($romm_extra_price, $digits_after_decimal);
                                    $total_tax             = round($total_tax, $digits_after_decimal);
                                    $final_price           = round($final_price, $digits_after_decimal);
                                    $TotalPrice            = round($TotalPrice, $digits_after_decimal);
                                    $total_adjustment      = round($total_adjustment, $digits_after_decimal);
                                    $totExtraChargePayment = round($totExtraChargePayment, $digits_after_decimal);
                                    
                                    $extraadultrate = round($extraadultrate, $digits_after_decimal);
                                    $extraadult_tax = round($extraadult_tax, $digits_after_decimal);
                                    $extrachildrate = round($extrachildrate, $digits_after_decimal);
                                    $extrachild_tax = round($extrachild_tax, $digits_after_decimal);
                                    
                                    $RoomAmenities         = str_replace('"', "", (string) $rooms['RoomAmenities']);
                                    $room_img              = (string) $rooms['room_main_image'];
                                    $hidefrommetasearch    = (string) $rooms['hidefrommetasearch'];
                                    $roomrateunkid         = $rooms['roomrateunkid'];
                                    $roomtypeunkid         = $rooms['roomtypeunkid'];
                                    $ratetypeunkid         = $rooms['ratetypeunkid'];
                                    $refundable            = (int) $rooms['prepaid_noncancel_nonrefundable'];
                                    $cancellation_day      = (isset($rooms['cancellation_deadline'])) ? $rooms['cancellation_deadline'] : "";
                                    $refundstr             = "";
                                    $cancellation_deadline = "";
                                    
                                    if ($refundable == 0) {
                                                             
                                        if ($cancellation_day != "") {
                                            $cancellation_deadline = date('Y-m-d\TH:i:s', strtotime('-' . $cancellation_day . ' day', strtotime($checkin)));
                                        } else {
                                            $cancellation_deadline = date('Y-m-d\TH:i:s', strtotime($checkin));
                                        }
                                        $refundstr    = 'full'; 
                                        $TempCurrDate = date("Y-m-d");
                                        if (strtotime($cancellation_deadline) <= strtotime($TempCurrDate)) {
                                            $refundstr = 'none'; 
                                        }
                                        
                                    } else {
                                        $refundstr = 'none';
                                    }
                                    
                                    if ($hidefrommetasearch == '' && $inventory1 >= $num_rooms) {
                                       
                                       if (count($TmpInvArray) >= 0 ) {
                                            $tmp[$Roomtype_Name.'_'.$roomRateUnkId] = $nor;
                                            $TmpInvArray = $tmp;
                                        }
                                        
                                       if (count($Roomdetails_array) >= 0 && !in_array($Roomtype_Name, $Roomdetails_array)) {
                                            array_push($Roomdetails_array, $Roomtype_Name);
                                        }
                                        if (count($Ratesdetails_array) >= 0 && !in_array($roomRateUnkId, $Ratesdetails_array)) {
                                            array_push($Ratesdetails_array, $roomRateUnkId);
                                        } 
                                        $width     = $height = 150;
                                        $currency  = strtoupper($currency);
                                        $Index     = '';
                                        $RoomIndex = '';
                                        
                                        if (count($Roomdetails_array) >= 0 && !in_array($Roomtype_Name, $Roomdetails_array)) {
                                            array_push($Roomdetails_array, $Roomtype_Name);
                                        }
                                        $temp_roomarray[$hotelCode]            = $hotelCode; 
										$roomarray[$hotelCode][$response_type] = "available";
										
                                        if (isset($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"]) && in_array($Roomtype_Name, array_column($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"], 'persistent_room_type_code'))) {
                                            $countArr = count($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"]);
                                            
                                            $RoomIndex = array_search($roomTypeUnId, array_column($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"], 'persistent_room_type_code')) + 1;
                                            
                                        } else {
                                            $cnt = $cnt + 1;
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['persistent_room_type_code'] = $Roomtype_Name;
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['name']                      = $tmp_RoomName;
                                            $this->log->logIt("Room name >> " . $rooms['Roomtype_Name']);
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['description'] = $RatePlan_Description;
                                            if (isset($room_img) && $room_img != '') {
                                                $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['photos'] = array(
                                                    array(
                                                        'url' => $room_img,
                                                        'width' => (int) $width,
                                                        'height' => (int) $height,
                                                        'caption' => ''
                                                    )
                                                );
                                            } else {
                                                $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['photos'] = array();
                                            }
                                            if (isset($RoomAmenities) && $RoomAmenities != '') {
                                                $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['room_amenities'] = array(
                                                    "standard" => array(),
                                                    "custom" => explode(",", $RoomAmenities)
                                                );
                                            }
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['bed_configurations']       = array(
                                                array(
                                                    "standard" => array(
                                                        array(
                                                            'code' => 1,
                                                            'count' => 1
                                                        )
                                                    ),
                                                    "custom" => array(
                                                        array(
                                                            'name' => 'unknown',
                                                            'count' => 1
                                                        )
                                                    )
                                                )
                                            );
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['extra_bed_configurations'] = array(
                                                array(
                                                    "standard" => array(
                                                        array(
                                                            'code' => 1,
                                                            'count' => 1
                                                        )
                                                    ),
                                                    "custom" => array(
                                                        array(
                                                            'name' => 'unknown',
                                                            'count' => 1
                                                        )
                                                    )
                                                )
                                            );
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['max_occupancy']            = array(
                                                "number_of_adults" => $maxadult,
                                                "number_of_children" => $maxchild
                                            );
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_types"][$cnt]['room_smoking_policy']      = 'unknown';
                                        }
                                        
                                        $Index = '';
                                        if (isset($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"]) && in_array($roomRateUnkId, array_column($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"], 'persistent_rate_plan_code'))) {
                                            $countArr = count($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"]);
                                            
                                            $Index = array_search($roomRateUnkId, array_column($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"], 'persistent_rate_plan_code')) + 1;
                                        } else {
                                            $RateCnt = $RateCnt + 1;
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['persistent_rate_plan_code'] = $roomRateUnkId;
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['name']                      = $RatePlan_Name;
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['description']               = $SpecialConditions;
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['photos']                    = array();
                                            if (isset($RoomAmenities) && $RoomAmenities != '') {
                                                $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['rate_amenities'] = array(
                                                    "standard" => array(),
                                                    "custom" => explode(",", $RoomAmenities)
                                                );
                                            }
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['cancellation_policy']['cancellation_summary'] = array(
                                                "refundable" => $refundstr,
                                                "unstructured_cancellation_text" => $cancelstr
                                            );
                                            
                                            if ($refundstr == "full") {
                                                $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["rate_plans"][$RateCnt]['cancellation_policy']['cancellation_summary'] = array(
                                                    "refundable" => $refundstr,
                                                    "cancellation_deadline" => $cancellation_deadline . 'Z',
                                                    'unstructured_cancellation_text' => $cancelstr
                                                );
                                            }
                                        }
                                        
                                        $key = array_search($Roomtype_Name, $Roomdetails_array);
                                        $key = $key +1;
                                        $key1 = array_search($roomRateUnkId, $Ratesdetails_array);
                                        $key1 = $key1 +1;
                                        
                                        $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["room_type_key"] = '';
                                        $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["rate_plan_key"] = '';
                                        $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["partner_data"] = array(
                                                'myhotel_code' => $hotelCode,
                                                'myroom_code' => $roomTypeUnId,
                                                'rate_hotel_info' => $ratePlanUnkId
                                            );
                                        if (isset($Payment_Policy) && $Payment_Policy != '') {
                                        $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["payment_policy"] = strip_tags($Payment_Policy);
                                        }
                                        $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["rooms_remaining"] = $inventory1;
                                        $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["url"] = $url;
                                        
                                         $taxarr  = ($total_tax != 0) ? array(
                                                "price" => array(
                                                    "requested_currency_price" => array(
                                                        "amount" => $total_tax,
                                                        "currency" => strtoupper($currency)   
                                                    ),
                                                    "currency_of_charge_price" => array(
                                                        "amount" => $total_tax1,
                                                        "currency" => strtoupper($currency_code)
                                                    )
                                                ),
                                                "type" => 'tax',
                                                "sub_type" => 'tax_other',
                                                "paid_at_checkout" => 'false'
                                            ) : false;
                                            
                                            $feearr = ($totExtraChargePayment != 0) ? array(
                                                "price" => array(
                                                    "requested_currency_price" => array(
                                                        "amount" => $totExtraChargePayment,
                                                        "currency" => strtoupper($currency)
                                                    ),
                                                    "currency_of_charge_price" => array(
                                                        "amount" => $temp_totExtraChargePayment,
                                                        "currency" => strtoupper($currency_code)
                                                    )
                                                ),
                                                "type" => 'fee',
                                                "sub_type" => 'fee_other',
                                                "paid_at_checkout" => 'false'
                                            ) : false;
                                            
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["line_items"][$nor] = array(
                                                array(
                                                    "price" => array(
                                                        "requested_currency_price" => array(
                                                            "amount" => $TotalPrice,
                                                            "currency" => strtoupper($currency)
                                                        ),
                                                        "currency_of_charge_price" => array(
                                                            "amount" => $TotalPrice1,
                                                            "currency" => strtoupper($currency_code)
                                                        )
                                                    ),
                                                    "type" => 'rate',
                                                    "paid_at_checkout" => 'false'
                                                )
                                            );
                                            if ($taxarr != false && $taxarr['price']['requested_currency_price']['amount'] != 0) {
                                                
                                                array_push($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["line_items"][$nor], $taxarr);
                                                
                                            }
                                            if ($feearr != false && $feearr['price']['requested_currency_price']['amount'] != 0) {
                                                array_push($roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["room_rates"][$key.'_'.$key1]["line_items"][$nor], $feearr);
                                            }
                                        
                                        $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]["hotel_details"]                                    = $basic_hotel;
                                        $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]['partner_booking_details']['accepted_credit_cards'] = array(
                                            "Visa",
                                            "MasterCard"
                                        );
                                        $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]['partner_booking_details']['terms_and_conditions']  = strip_tags($Terms_And_Conditions);
                                        
                                        if (isset($Payment_Policy) && $Payment_Policy != '') {
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]['partner_booking_details']['payment_policy'] = strip_tags($Payment_Policy);
                                        }
                                        
                                        if (strlen($Final_number) > 15) {
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]['partner_booking_details']['customer_support']['phone_numbers'] = array(
                                                "standard" => array(),
                                                "custom" => array(
                                                    array(
                                                        "description" => 'Reservation Desk',
                                                        "number" => str_replace(' ', '', $Final_number)
                                                    )
                                                )
                                            );
                                        } else {
                                            $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]['partner_booking_details']['customer_support']['phone_numbers'] = array(
                                                "standard" => array(
                                                    array(
                                                        "country_code" => $phoneCode,
                                                        "number" => str_replace(' ', '', $Final_number),
                                                        "description" => 'Reservation Desk'
                                                    )
                                                )
                                            );
                                        }
                                        
                                        $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]['partner_booking_details']['customer_support']['emails'] = array(
                                            array(
                                                "email" => $Email,
                                                "description" => "customer support Mail Id"
                                            )
                                        );
                                        $roomarray[$hotelCode][$roomarray[$hotelCode][$response_type]]['partner_booking_details']['customer_support']['urls']   = array(
                                            array(
                                                "url" => $websit_url,
                                                "description" => "Visit on website"
                                            )
                                        );
                                    } 
                                } 
                            }
						}
					}
					$check_room_arr[$nor] = $temp_roomarray; 
                    $temp_roomarray       = array(); 
				}

				$arrFinalResult = [];
				$i = 1;
				
				if(isset($roomarray[$hotelCode]['available']["room_rates"]))
                {
                    foreach($roomarray[$hotelCode]['available']["room_rates"] as $combinationKey => $arrRoomRate)
                    {                    
                        $arrCombinationKeys = [];
                        $arrCombinationKeys = explode("_",$combinationKey);
                        
                        $arrSubResult = [];
                        $arrSubResult['room_type_key'] = $arrCombinationKeys[0];
                        $arrSubResult['rate_plan_key'] = $arrCombinationKeys[1];
                        
                        if(array_key_exists($arrSubResult['room_type_key']-1,$Roomdetails_array))
                        {
                            $name = $Roomdetails_array[$arrSubResult['room_type_key']-1];
                        }
                        
                        if(array_key_exists($arrSubResult['rate_plan_key']-1,$Ratesdetails_array))
                        {
                            $rate = $Ratesdetails_array[$arrSubResult['rate_plan_key']-1];
                        }
                        
                        if(array_key_exists($name.'_'.$rate,$TmpInvArray) && $TmpInvArray[$name.'_'.$rate] + 1 == $num_rooms)
                        {
                            $arrSubResult['partner_data'] = $arrRoomRate['partner_data'];
                            if(isset($arrRoomRate['payment_policy']) && $arrRoomRate['payment_policy'] != ''){
                                $arrSubResult['payment_policy'] = $arrRoomRate['payment_policy'];   
                            }
                            $arrSubResult['rooms_remaining'] = $arrRoomRate['rooms_remaining'];
                            $arrSubResult['url'] = $arrRoomRate['url'];
                            
                            $arrFinalAmounts = [];
                            $arrFinalAmounts['total_rate'] = 0;
                            $arrFinalAmounts['total_tax'] = 0;
                            $arrFinalAmounts['base_total_rate'] = 0;
                            $arrFinalAmounts['base_total_tax'] = 0;
                            $arrFinalAmounts['total_fees'] = 0;
                            $arrFinalAmounts['base_total_fees'] = 0; 
                            foreach($arrRoomRate['line_items'] as $index => $arrPrice)
                            {
                                foreach($arrPrice as $index1 => $arrPrice1){
                                    
                                    if(isset($arrPrice1['type']) && $arrPrice1['type'] == "rate")
                                    {
                                         $arrFinalAmounts['total_rate'] +=   $arrPrice1['price']['requested_currency_price']['amount'];
                                         $arrFinalAmounts['currency'] = $arrPrice1['price']['requested_currency_price']['currency'];
                                         $arrFinalAmounts['base_total_rate'] +=   $arrPrice1['price']['currency_of_charge_price']['amount'];
                                         $arrFinalAmounts['base_currency'] = $arrPrice1['price']['currency_of_charge_price']['currency'];
                                     }
                                     if(isset($arrPrice1['type']) && $arrPrice1['type'] == "tax")
                                     {
                                         $arrFinalAmounts['total_tax'] +=   $arrPrice1['price']['requested_currency_price']['amount'];
                                         $arrFinalAmounts['base_total_tax'] +=   $arrPrice1['price']['currency_of_charge_price']['amount']; 
                                     }
                                    if(isset($arrPrice1['type']) && $arrPrice1['type'] == "fee")
                                    {
                                         $arrFinalAmounts['total_fees'] =   $arrPrice1['price']['requested_currency_price']['amount'];
                                         $arrFinalAmounts['base_total_fees'] =   $arrPrice1['price']['currency_of_charge_price']['amount']; 
                                    } 
                                }
                            }
                            
                            $tax_array = ($arrFinalAmounts['total_tax'] != 0) ? array("price"=>array(
                                    "requested_currency_price" => array("amount" => $arrFinalAmounts['total_tax'],"currency" => $arrFinalAmounts['currency']),
                                    "currency_of_charge_price" => array("amount" => $arrFinalAmounts['base_total_tax'],"currency" => $arrFinalAmounts['base_currency'])
                                    ),
                                    "type" => "tax",
                                    "sub_type" => 'tax_other',
                                    "paid_at_checkout" => "false",
                                                     ) : false ;
                            $fee_array = ($arrFinalAmounts['total_fees'] != 0) ? array("price"=>array(
                                    "requested_currency_price" => array("amount" => $arrFinalAmounts['total_fees'],"currency" => $arrFinalAmounts['currency']),
                                    "currency_of_charge_price" => array("amount" => $arrFinalAmounts['base_total_fees'],"currency" => $arrFinalAmounts['base_currency'])
                                    ),
                                    "type" => "fee",
                                    "sub_type" => 'fee_other',
                                    "paid_at_checkout" => "false",
                                                     ) : false ;
                            
                            $arrSubResult['line_items'] = array(
                                array("price"=>array(
                                    "requested_currency_price" => array("amount" => $arrFinalAmounts['total_rate'],"currency" => $arrFinalAmounts['currency']),
                                    "currency_of_charge_price" => array("amount" => $arrFinalAmounts['base_total_rate'],"currency" => $arrFinalAmounts['base_currency'])
                                    ),
                                    "type" => "rate",
                                    "paid_at_checkout" => "false",
                                                     ));
                            if ($tax_array != false && $arrFinalAmounts['total_tax'] != 0) {
                                    array_push($arrSubResult['line_items'], $tax_array);
                                }
                                
                            if ($fee_array != false && $arrFinalAmounts['total_fees'] != 0) {
                                    array_push($arrSubResult['line_items'], $fee_array);
                                }
                                
                            $arrFinalResult[$i] = $arrSubResult;
                            $i++;
                        }
                        else{
                            foreach($roomarray[$hotelCode]['available']["room_types"] as $combinationKey => $arrRoomRate){
                                if($arrRoomRate['persistent_room_type_code'] == $name)
                                {
                                  unset($roomarray[$hotelCode]['available']["room_types"][$combinationKey]);
                                }
                            }
                            foreach($roomarray[$hotelCode]['available']["rate_plans"] as $combinationKey => $arrRatePlan){
                                if($arrRatePlan['persistent_rate_plan_code'] == $rate)
                                {
                                  unset($roomarray[$hotelCode]['available']["rate_plans"][$combinationKey]);
                                }
                            }
                        }
                    }
                }
                else{
                    $roomarray = array(
                    $hotelcode => array(
                        "response_type" => "unavailable"
                        )
                    );
				}
				
				$roomarray[$hotelCode]['available']["room_rates"] = $arrFinalResult;
                $temp1_roomarray = array();
                if (count($check_room_arr) > 1)
                    $temp1_roomarray = call_user_func_array('array_intersect_key', $check_room_arr);
                else {
                    foreach ($check_room_arr as $val) {
                        $temp1_roomarray = $val;
                    }
                }
                
                foreach ($roomarray as $hotelCode => $roomvalues) {
                    if (in_array($hotelCode, $temp1_roomarray)) {
                        $this->log->logIt("Room not remove >> " . $hotelCode);
                    } else {
                        unset($roomarray[$hotelCode]);
                        $this->log->logIt("Room remove for multi room >> " . $hotelCode);
                    }
				}
				
				if (count($roomarray) <= 0) {
					$roomarray = array(
						$hotelcode => array(
							"response_type" => "unavailable"
						)
					);
				}

				if (in_array(1, $notAvail))
				unset($roomarray['hotel_room_types']);
				
           	 	return $roomarray;
		}
		catch(Exception $e ){
			$this->log->logIt($this->module.' - Exception - getBooking_availability - '.$e);
		}

	}
    
    //DB connection
    public function connectDB($server, $dbname) {
        try
		{
            $this->log->logIt(get_class($this) . "-" . "connectDB".$server."==".$dbname);
                 
            $projectPath = dirname(__FILE__);
            require $projectPath."/../../common_connection.php";
        
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
                        $serverurl = "http://" . $_SERVER['HTTP_HOST'] . "/";
                        $host = $commonDbConnection['dbConnection']['liverds']['mysqlhost'];
                        $username =$commonDbConnection['dbConnection']['liverds']['mysqluser'];
                        $password = $commonDbConnection['dbConnection']['liverds']['mysqlpwd'];
                        break;
                    default:
                        $flag = false;
                }

                global $connection;
                if(!empty($connection)){
                    $db_info = $connection->getAttribute(PDO::ATTR_SERVER_INFO);
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
                    }
                }
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
        
    public function connectMasterDB($server, $dbname) {
        try {
                $this->log->logIt(get_class($this) . "-" . "connectMasterDB".$server."==".$dbname);

                $projectPath = dirname(__FILE__);
                require $projectPath."/../../common_connection.php";
                
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
                            $serverurl = "http://" . $_SERVER['HTTP_HOST'] . "/";
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
    
    function getpartnerdata($hotel_code){
		
		$hostname = gethostname();
		if($hostname == 'ubuntu') //local server
		{
			$path = "http://192.168.20.14/protected/pages/service/tripadvisor_data.json";
			//$path = "/home/saasfinal/protected/pages/service/tripadvisor_data.json";
		}
		else//live server
		{
			$path = "/home/logdrive/trip_threshold/tripadvisor_data.json";
		}
		
		$resultData = array();
		
		//if (file_exists($path)) {
			
			$jsonData = file_get_contents($path);
			
			$jsonData = json_decode($jsonData,true);
			
			if (array_key_exists($hotel_code,$jsonData))
			{
				$resultData = $jsonData[$hotel_code];
			}		
		//}
		
		return $resultData;
	}
    
    public function getExtracharge ($hotelcode)
    {
        try{
            
            $this->log->logIt($this->module.' - getExtracharge');       
            $ObjProcessDao=new processdao();
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$hotelcode);
            
            if(isset($hotelcode) && $hotelcode!='')
			{				
				$language = (isset($_REQUEST['language']))?$_REQUEST['language']:'en';
				
				$WebSelectedLanguage=$ObjProcessDao->getreadParameter('WebSelectedLanguage',$hotelcode);
				$lang_ava=array();
				if($WebSelectedLanguage!='')
					$lang_ava=explode(",",$WebSelectedLanguage);
				
				if(isset(staticarray::$languagearray[$language]) && staticarray::$languagearray[$language]!='' && in_array($language,$lang_ava))
					$language =$language;
				else
					$language='en';
							
				$list_extras=$ObjProcessDao->getExtraCharges($hotelcode,$language,'','','');
			}
			else
			{
				$error_response=$this->ShowErrors('InvalidHotelCode');				
				echo $error_response;
				exit(0);
			}
            
            return $list_extras;
        }
        catch(Exception $e){
            $this->log->logIt(" - Exception in " . $this->module . "- " . "getExtracharge - " . $e);
        }
    }
    
    public function getHotelDetail($hotelcode,$APIkey){
        try{
            $this->log->logIt($this->module.' - getHotelDetail');
            
            $ObjProcessDao=new processdao();
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$hotelcode);
            
            $list_of_ava_properties=array();
			$api_cnt=0;
            
            if($APIkey!='')
			{
                $this->log->logIt($this->module.' - database name - '.$resultauth[0]['databasename']);
                if(isset($resultauth[0]['databasename']) && $resultauth[0]['databasename']!='')
				{
                    $crslocation = '';
                    $lang_code = (isset($_REQUEST['language']))?$_REQUEST['language']:'en';
                    $alldbs=array();$offset = '';$limit = '';
                    
                    array_push($alldbs,$resultauth[0]['databasename']);
                    
                    $list_of_ava_properties=array();$hcount = 0;
                    
                    foreach($alldbs as $databases)
					{
                        $ObjProcessDao->databasename =  $databases;
                        $this->selectdatabase($ObjProcessDao->databasename);
                        
                        $result_hoteldetails=$ObjProcessDao->getHotelDetails('',$_REQUEST['HotelCode'],'',$lang_code);
                        
                        foreach($result_hoteldetails as $hoteldetails)
						{
                            $this->log->logIt($this->module.' - Get Hotel List - '.$hoteldetails['Hotel_Name']);
                           $imagedata=$ObjProcessDao->getImageList($hoteldetails['Hotel_Code'],imageobjecttype::Hotel);
                           
                           $hotelimages_arr=array();
							foreach($imagedata as $imgdata)
							{
								if($imgdata['image_path']!='')
								{
									$ObjImageDao=new imagedao();
									$img=$ObjImageDao->getImageFromBucket($imgdata['image_path']);
									array_push($hotelimages_arr,$img);
								}
							}
                            $hoteldetails['BookingEngineFolderName']=$hoteldetails['BookingEngineURL'];
                            $hoteldetails['HotelImages']=$hotelimages_arr;
                            
                            $rsCurrenyData=$ObjProcessDao->getBaseCurrency($hoteldetails['Hotel_Code']);
							$hoteldetails['CurrencyCode']=$rsCurrenyData["currency_code"];
                            
                            $BookingEngineConfig=$ObjProcessDao->getreadParameter('BookingEngineConfig',$hotelcode);
                            
                            $_Params=explode("<123>",$BookingEngineConfig);						
                            foreach($_Params as $keys)
                            {
                                $_PluginParam=explode("<:>",$keys);
                                if($_PluginParam[0]=='LayoutTheme')
                                    $hoteldetails['LayoutTheme']=$_PluginParam[1];
                            }
                            array_push($list_of_ava_properties,$hoteldetails);
                        }
                    }
                }
            }
            else
			{
				$api_cnt++;
			}
            
            return $list_of_ava_properties;
        }
        catch(Exception $e){
            $this->log->logIt(" - Exception in " . $this->module . "- " . "getHotelDetail - " . $e);
        }
    }
    
    public function getRoomList($hotelcode,$APIkey){
        try{
            $this->log->logIt($this->module.' - getRoomList');
            global $eZConfig;
            
            $ObjProcessDao=new processdao();			
			$resultauth=$ObjProcessDao->isAuthorizedUser('','',$hotelcode);
            
            foreach($resultauth as $authuser)
			{
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;		
				$ObjProcessDao->newurl = $authuser['newurl'];	
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename); 
			}
            $showlog = false;
            
            $this->selectdatabase($resultauth[0]['databasename']);
            
            if(isset($hotelcode) && $hotelcode!='')
			{
                $res_rate =  $ObjProcessDao->getRatemode($hotelcode);
				$rate_mode=$res_rate['rate'];
                
                $Iscrs_HotelList = (isset($_REQUEST['getHotelRatesAvailability']) && ($_REQUEST['getHotelRatesAvailability']=='1'))?'1':'0';
                $Iscrs_RoomList = (isset($_REQUEST['getDetailedRoomListing']) && ($_REQUEST['getDetailedRoomListing']=='1'))?'1':'0';
                
                $isTaxInc = '0';
				if($Iscrs_HotelList == '1'){
					$isTaxInc = (isset($_REQUEST['IsTaxInc']))?$_REQUEST['IsTaxInc']:'0';
				}
                
                $number_adult = (isset($_REQUEST['number_adults']))?$_REQUEST['number_adults']:1;	
				$number_child = (isset($_REQUEST['number_children']))?$_REQUEST['number_children']:0;				
				$num_rooms = (isset($_REQUEST['num_rooms']))?$_REQUEST['num_rooms']:1;
				$promotioncode = (isset($_REQUEST['promotion_code']))?$_REQUEST['promotion_code']:'';
				$getminrateonly = (isset($_REQUEST['getMinRate']) && $Iscrs_HotelList==1)?$_REQUEST['getMinRate']:0;
                
                if($number_adult<=0 || $number_child<0 || $num_rooms<=0)
				{
					 $error_response=$this->ShowErrors('NegativValues'); 						
					 echo $error_response;
					 exit(0);
				}
                
                $showcoacodstopsell = (isset($_REQUEST['showcoacodstopsell']))?$_REQUEST['showcoacodstopsell']:0;
                $no_nights = (isset($_REQUEST['num_nights']))?$_REQUEST['num_nights']:'';
				$property_configuration_info = (isset($_REQUEST['property_configuration_info']))?$_REQUEST['property_configuration_info']:'0';
                
                $WebSelectedLanguage=$ObjProcessDao->getreadParameter('WebSelectedLanguage',$hotelcode);
				$property_configuration_info = (isset($_REQUEST['property_configuration_info']))?$_REQUEST['property_configuration_info']:'0';
                
                $eZ_chkin = (isset($_REQUEST['check_in_date']))?$_REQUEST['check_in_date']:'';
				$calformat = 'yy-mm-dd';
				$eZ_chkout = (isset($_REQUEST['check_out_date']))?$_REQUEST['check_out_date']:'';
				$language = (isset($_REQUEST['language']))?$_REQUEST['language']:'en';
				$lang_code = (isset($_REQUEST['language']))?$_REQUEST['language']:'en';
                
                $lang_ava=array();
				if($WebSelectedLanguage!='')
					$lang_ava=explode(",",$WebSelectedLanguage);
				
				if(isset(staticarray::$languagearray[$language]) && staticarray::$languagearray[$language]!='' && in_array($language,$lang_ava))
					$language ="-".$language."-".staticarray::$languagearray[$language];
				else
					$language='-en-English';
                    
                if($showlog==true) $this->log->logIt("language : " .$language);
				
				$metasearch = (isset($_REQUEST['metasearch']))?$_REQUEST['metasearch']:'';
                
                if($no_nights > 30 && !(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1')) 
				{
					if($metasearch != 'GHF')
					{
						$error_response=$this->ShowErrors('NightsLimitExceeded'); 						
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
						
						$checkout_date = date('Y-m-d', mktime(0, 0, 0, $checkin_month, $checkin_day + ($no_nights) , $checkin_year));
						$number_night = util::DateDiff($eZ_chkin,$checkout_date);
					}				
					
					if($number_night>30 && !(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']=='1'))
					{
                        $error_response=$this->ShowErrors('NightsLimitExceeded'); 						
                        echo $error_response;
                        exit(0);
					}
					
					if($number_night>30 && $metasearch!='GHF')
					{
						if(isset($_REQUEST['allow_without_api']) && $_REQUEST['allow_without_api']!='1')
						{
							$error_response=$this->ShowErrors('NightsLimitExceeded'); 						
							echo $error_response;
							exit(0);
						}
					}
					
					if($Iscrs_HotelList == 1 || $Iscrs_RoomList == 1)
						$cal_date_format='yy-mm-dd';
					else
						$cal_date_format=$ObjProcessDao->getreadParameter('WebAvailabilityCalDateFormat',$hotelcode);
						
					$WebDefaultCurrency=$ObjProcessDao->getreadParameter('WebDefaultCurrency',$hotelcode);
					$rsCurrenyData=$ObjProcessDao->getBaseCurrency($hotelcode);
					
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
					
					$RoomRevenueTax=$ObjProcessDao->getreadParameter('RoomRevenueTax',$hotelcode);
					
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
					$taxdata=$ObjProcessDao->getTaxDefinitionForSpecificDates($calStartDt,$calEndDt,$RoomRevenueTax,$hotelcode,$exchange_rate1,$exchange_rate2);
                    
					$taxnamearray=array();
					if($taxdata!='')
					{
						foreach($taxdata as $key=>$value)
						{
							for($i=0;$i<count($value);$i++)
							{
								$taxname['Taxname_'.$i]=$value[$i]['TaxName'];
							}
							$taxnamearray['TaxName'][$key]=$taxname;
						}
					}
					else
					{
						$taxnamearray['TaxName']=[];
					}
					
					if($showlog==true)
					{
						$this->log->logIt("cal start : " .$calStartDt);
						$this->log->logIt("cal end : " .$calEndDt);
					}					
					
					if(isset($_REQUEST['showsummary']))
						$showsummary = $_REQUEST['showsummary'];
					else
						$showsummary=0;
						
					if($Iscrs_HotelList == 1 || $Iscrs_RoomList == 1)
					{
						$showsummary = 1;
					}
					
					if(isset($_REQUEST['roomtypeunkid']))
						$rmtype_unkid = $_REQUEST['roomtypeunkid'];
					else
						$rmtype_unkid='';
					
					if(isset($_REQUEST['roomrateunkid']))
						$roomrateId_unkid = $_REQUEST['roomrateunkid'];
					else
						$roomrateId_unkid='';
					
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
						
						$hcode=$hotelcode;
						
						$shownight='false';
						
							$hotel_checkin_date=$ObjProcessDao->getDateAllowfrmBooking($res_hotel['TodaysDate'],$res_hotel['WebSiteDefaultDaysReservation'],$res_hotel['CutOffTime']);	
							$ArrvalDt=$hotel_checkin_date;
							$this->log->logIt("hotel_checkin_date : " .$ch_dt . " < " .$hotel_checkin_date);
							if($ch_dt < $hotel_checkin_date)
							{
								$error_response=$this->ShowErrors('DateNotvalid');							
								echo $error_response;
								exit(0);
							}
							
							$BookingEngineConfig=$ObjProcessDao->getreadParameter('BookingEngineConfig',$hotelcode);	
							$_Params=explode("<123>",$BookingEngineConfig);
							$showchild='false';
							foreach($_Params as $keys)
							{	
								$_PluginParam=explode("<:>",$keys);	
								if($_PluginParam[0]=='LayoutTheme')
									$LayoutTheme=$_PluginParam[1];
								else if($_PluginParam[0]=='ShowDepart') 
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
							
						
						$notdefinedstay=(isset($_REQUEST['notdefinedstay']) && $_REQUEST['notdefinedstay']=='true')?'API':'';
						
						$SplGoogleHotelFinder=(isset($_REQUEST['SplGoogleHotelFinder']) && $_REQUEST['SplGoogleHotelFinder']==1)?1:0;
						
						$agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
						$this->log->logIt('agent:'.$agent);
						
						$agent_array = array('self/tripconnect_v7');   
						
						$serverurl=$eZConfig['urls']['cnfServerUrl'];
						
							if($agent != '' && in_array($agent,$agent_array)){
								$roomurl = $serverurl."rmdetails/interface";
							}else{
								$roomurl = $serverurl."rmdetails";
							}
							
							$InvForAvailCalender = (isset($_REQUEST['InvForAvailCalender'])) ? $_REQUEST['InvForAvailCalender'] : '';
							
							$this->log->logIt("=-=-=-=-=>>> RoomURL : ".$roomurl . " || Google Finder : " . $SplGoogleHotelFinder . " || Meta Search : " . $metasearch . " || Hotel Code : " . $hotelcode);
							$url = $serverurl."searchdetail.php?HotelId=".$hotelId."&checkin=".$checkin_date."&nonights=".$number_night."&calendarDateFormat=".$cal_date_format."&adults=".$number_adult."&child=".$number_child."&rooms=".$num_rooms;
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
								  'InvForAvailCalender' => $InvForAvailCalender,
								  'metasearch' => $metasearch
								 );
							
							$this->log->logIt("HotelId : ".$hotelId." : " .date('H:i:s'));
							if($showlog==true) $this->log->logIt($data);
							
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
							$roomarray = array();
							
							if($Iscrs_HotelList == 1 || $Iscrs_RoomList == 1)
								$WebShowRatesAvgNightORWholeStay=1;
							else
								$WebShowRatesAvgNightORWholeStay=$ObjProcessDao->getreadParameter('WebShowRatesAvgNightORWholeStay',$hotelcode);
								
							if($Iscrs_HotelList == 0)
							{
								$chk_in_time=$ObjProcessDao->getreadParameter('CheckInTime',$hotelcode);
								$chk_out_time=$ObjProcessDao->getreadParameter('CheckOutTime',$hotelcode);
							}
							
							$type='HOTEL';
							$list_amenities=$ObjProcessDao->getAmenities($hotelcode,$type,$lang_code);
							$hotelamenities=array();
							if($list_amenities!='' && count($list_amenities)>0 && isset($list_amenities))
							{
							foreach($list_amenities as $am)
							{
								$hotelamenities[]=$am['amenity'];					
							}
							}
							
							if($Iscrs_HotelList == 1 || $Iscrs_RoomList == 1)
							{
								$bb_viewoptions=0;
								$selectedviewoptions=0;
								$bb_ShowOnlyAvailableRatePlanOrPackage=1;
								$conf_ShowMinNightsMatchedRatePlan=0;
								$FindSlabForGSTIndia=$ObjProcessDao->getreadParameter('FindSlabBeforeDiscount',$hotelcode); 
							}
							else
							{
								$BookingEngineConfig=$ObjProcessDao->getreadParameter('BookingEngineConfig',$hotelcode);
								$_Params=explode("<123>",$BookingEngineConfig);
								foreach($_Params as $keys)
								{
									$_PluginParam=explode("<:>",$keys);
									if($_PluginParam[0]=='bb_viewoptions')
										$bb_viewoptions=$_PluginParam[1];
									if($_PluginParam[0]=='selectedviewoptions')
										$selectedviewoptions=$_PluginParam[1];
									if($_PluginParam[0]=='bb_ShowOnlyAvailableRatePlanOrPackage')
										$bb_ShowOnlyAvailableRatePlanOrPackage=$_PluginParam[1];
									if($_PluginParam[0]=='bb_adults')
										$conf_max_adults=$_PluginParam[1];
									if($_PluginParam[0]=='bb_child')
										$conf_max_child=$_PluginParam[1];
									if($_PluginParam[0]=='ShowMinNightsMatchedRatePlan')
									{
										$conf_ShowMinNightsMatchedRatePlan=$_PluginParam[1];
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
										exit(0);
									}
									if($number_child>$conf_max_child){
										$error_response=$this->ShowErrors('MaxChildLimitReach');									
										echo $error_response;
										exit(0);
									}
								}
						   
								if(isset($_REQUEST['show_only_available_rooms']) && $_REQUEST['show_only_available_rooms']==1)
									$bb_ShowOnlyAvailableRatePlanOrPackage=1;
									
								if(isset($_REQUEST['show_matched_minimum_nights_rateplans']) && $_REQUEST['show_matched_minimum_nights_rateplans']==1)
								{
									$conf_ShowMinNightsMatchedRatePlan=1;
								}
								else if(isset($_REQUEST['show_matched_minimum_nights_rateplans']) && $_REQUEST['show_matched_minimum_nights_rateplans']=='2') 
								{
										$conf_ShowMinNightsMatchedRatePlan=3;
								}
								
								if($InvForAvailCalender == true)
									$bb_ShowOnlyAvailableRatePlanOrPackage=0;
									
								$FindSlabForGSTIndia=$ObjProcessDao->getreadParameter('FindSlabBeforeDiscount',$hotelcode); 
							}
							
							$packagedeal=array();
							$hiderateplan=array();
							$packagedealids=array();
							$ispromoappliedvalid=false; 
							$packages_as_ratetype=array(); 
							if(isset($res[0]) && sizeof($res[0]) > 0)
							{
								foreach($res[0] as $rmdetail)
								{
									$min_nights = $max_nights = 1;
									$minimum_nights_arr=array();
									$maximum_nights_arr=array();
									
									$stopsell = $inv = $dtselection = 1;
									$discount = $rate = $cod = $coa = 0;
									$selenddt=$range='';
									$inventory=array();
									
									$_loopCnt=0;
									$_startDay=1;
									$PayForStart=1;
									$StayForStart=1;
									$BeforeDiscountRate=array();
									$BeforeDiscountRate_Package=array(); 
									$BeforeDiscountRate_Package_bd=array();
									$BaseRate=array();
									$rateGST=array(); 
									$ExtraAdultRate=array();
									$ExtraChildRate = array();
									
									$WithoutTax_BaseRate=array();
									$WithoutTax_GSTRate=array(); 
									$WithoutTax_ExtraAdultRate=array();
									$WithoutTax_ExtraChildRate = array();
									
									$Adjustment=array();
									$Adjustment_Extra_Adult=array();
									$Adjustment_Extra_Child=array();
	
									$Taxes=array();
									$Taxes_Extra_Adult=array();
									$Taxes_Extra_Child=array();
									$StrikeRate=array();
	
									$find_total=0;
									
									$non_linear_rates=array();
									$non_linear_rates_before=array();
									$non_linear_rates_before_gst=array();
									$non_linear_extraadult=array();
									$non_linear_extrachild=array();
									
									$find_tax=0;
									$find_adjustment=0;
									$regularrate_promo=array();
									for($i=1;$i<=$number_night;$i++)
									{
										$dt3='dt_'.$i;
										$date = 'day_'.$i;
										$sdate = 'stopsell_'.$i;			
										$baserate = 'o_day_base_'.$i;			
										$coa_val = 'coa_'.$i;
										$cod_val = 'cod_'.$i;
										$min_nights_val='min_night_'.$i;
										$max_nights_val='max_night_'.$i;
										$beforediscount='before_discount_tax_day_base_'.$i;
										
										if($i==1)
											$selenddt='dt_'.$i;
										
										if($rmdetail->$date == 0 && $bb_ShowOnlyAvailableRatePlanOrPackage==true)
										{
											$inv = 0;
										}
										else
										{
											if(isset($rmdetail->$dt3))
												$inventory[$rmdetail->$dt3]=$rmdetail->$date;											
										}
										
										if(isset($rmdetail->$sdate) && $rmdetail->$sdate == 1)
										{
											$stopsell = 0;
											
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
										
										if(isset($rmdetail->$coa_val) && $rmdetail->$coa_val == 1 && $i==1) 
										{
											  $coa = 1;
											  if($showcoacodstopsell==1)
											  {
												 $coa = 0;
												 $inventory[$rmdetail->$dt3]=0;
											  }
										}
										
										if($Iscrs_HotelList == 1 || $Iscrs_RoomList == 1)
										{
											if(isset($rmdetail->$cod_val) && $rmdetail->$cod_val == 1)
											{
												  $cod = 1;
												  if($showcoacodstopsell==1)
												  {
													 $cod = 0;
													 $inventory[$rmdetail->$dt3]=0;
												  }
											}
										}
										
										if($Iscrs_HotelList == 1)
											$min_n = 1;
										else
											$min_n=(isset($rmdetail->$min_nights_val)?$rmdetail->$min_nights_val:1); 
											
										$max_n=(isset($rmdetail->$max_nights_val)?$rmdetail->$max_nights_val:'');
	
										if(($conf_ShowMinNightsMatchedRatePlan==1 && ($min_n > $number_night)) || ($conf_ShowMinNightsMatchedRatePlan==3 && ($min_n!=$number_night)))
										{									   
											$this->log->logIt("HotelId : ".$hotelId." : ".$rmdetail->display_name." :  " .$min_n." > ".$number_night." : ".date('H:i:s'));
											$min_nights = 0;
										}
										else
										{
											if(isset($rmdetail->$dt3) && isset($rmdetail->$min_nights_val)) 
												$minimum_nights_arr[$rmdetail->$dt3]=$rmdetail->$min_nights_val;	
										}
										
										if($max_n!='' && $max_n < $number_night && $notdefinedstay!='API' && $metasearch != 'GHF') 
										{
											$this->log->logIt("HotelId : ".$hotelId." : ".$rmdetail->display_name." :  " .$max_n." > ".$number_night." : ".date('H:i:s'));
											$max_nights = 0;
										}
										else
										{
											if(isset($rmdetail->$dt3) && isset($rmdetail->$max_nights_val)) 
												$maximum_nights_arr[$rmdetail->$dt3]=$rmdetail->$max_nights_val;	
										}
										
										if($SplGoogleHotelFinder==1)
										{
											$bb_ShowOnlyAvailableRatePlanOrPackage=0;
											$conf_ShowMinNightsMatchedRatePlan=0;
										}
										
										if(isset($rmdetail->$baserate))
										   $rate =	$rate + $rmdetail->$baserate;
										   
										if(isset($rmdetail->$beforediscount))
										   $discount = $discount + $rmdetail->$beforediscount;
										
										$range.=$i.",";
										
										$nightrule_var='nightrule_'.$_startDay;
										
										$nightrule=''; 
										if(isset($rmdetail->$nightrule_var)) 
										   $nightrule=$rmdetail->$nightrule_var;
										   
										if(isset($rmdetail->deals))
											$deal=$rmdetail->deals;
										else
											$deal='';
										
										
										if($selectedviewoptions==1 && $deal!='' && $bb_viewoptions==1 && $LayoutTheme == 1 )
											$inv=0;
										if($selectedviewoptions==2 && $deal=='' && $bb_viewoptions==1 && $LayoutTheme == 1 )
											$inv=0;
										
										
										$beforediscount_var='before_discount_day_base_'.$_startDay;
										$pack_beforediscount_var='PackRackRate_'.$_startDay; 
										
										if($Iscrs_RoomList == 1)
											$pack_beforediscount_var_bd='PackRackRate_before_discount_'.$_startDay;
										
										$RackRate=0; 
										$rateGST=0;
										$packdealtype=''; 
										if(isset($rmdetail->$beforediscount_var)){ 
										   $RackRate=$rmdetail->$beforediscount_var;
										   $rateGST=$rmdetail->$beforediscount_var; 
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
											if($deal!='')
											{
												$spdeal=explode(":",$deal);
												$nightcount=explode("|",$spdeal[1]);
												
												$RackRate=0; 
												$rateGST=0; 
												if(isset($rmdetail->$beforediscount_var)){ 
													$Rackrate=$rmdetail->$beforediscount_var;
													$rateGST=$rmdetail->$beforediscount_var;
												}
												
												if($RoomRevenueTax!='' && isset($rmdetail->$dt2) && isset($taxdata[$rmdetail->$dt2]))
												{
													
													if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
													{
														$find_tax=$ObjProcessDao->calculateTax($rateGST, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode);
													}
													else
													{
														$find_tax=$ObjProcessDao->calculateTax($RackRate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode);
													}
																									
													 $taxrate = $RackRate + $find_tax;
												}
												else
													 $taxrate = $RackRate;
												 $find_adjustment=$ObjProcessDao->getRoundVal($taxrate,false,$hotelcode,$digits_after_decimal);										 
												 $taxrate=$taxrate+$find_adjustment;
												if(isset($rmdetail->$dt2))
													$StrikeRate[$rmdetail->$dt2]=$taxrate;
												  
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
													$RackRate=0; 
													if(isset($rmdetail->$beforediscount_var)) 
														$RackRate=$rmdetail->$beforediscount_var;
														
													$noofnights=$nightcount[1];
													$ratepernight=$nightcount[2];						
													$extranightrateon=$nightcount[3];
													$packdealtype = $nightcount[4];
													
													if($packdealtype=='FIXED')
														$ratepernight2=$ratepernight;
													else if($packdealtype=='PER')
														$ratepernight2=$RackRate-(($RackRate*$ratepernight)/100);							
													else if($packdealtype=='AMT')
														$ratepernight2=$RackRate-$ratepernight;													
													else
														$ratepernight2=$ratepernight;
														
													
													if($_loopCnt<$noofnights)
													{												
													if(isset($rmdetail->$isdisplayregularrate) && $rmdetail->$isdisplayregularrate=='Y')
														$discount_amt = $RackRate;
													else
														$discount_amt = $ratepernight2;
																									
													}
													else if($extranightrateon=='Y')
													{
														if(isset($rmdetail->$isdisplayregularrate) && $rmdetail->$isdisplayregularrate=='Y')
															$discount_amt = $RackRate;
														else
															$discount_amt = $ratepernight2;														
													}
													else if($extranightrateon=='N')
														$discount_amt=$RackRate;
													$dealcnt++;
												}
												else if($nightcount[0]=='PERCENTDISCOUNT')
												{
													$RackRate=0; 
													if(isset($rmdetail->$beforediscount_var))
														$RackRate=$rmdetail->$beforediscount_var;
														
													$discount=$rmdetail->packagediscount;
													
													if(isset($rmdetail->$isdisplayregularrate) && $rmdetail->$isdisplayregularrate=='Y')
														$discount_amt = $RackRate;
													else
														$discount_amt = $RackRate - (($RackRate * $discount) / 100);
													$dealcnt++;
												}	
												else if($nightcount[0]=='AMOUNTDISCOUNT')
												{
													$RackRate=0; 
													if(isset($rmdetail->$beforediscount_var))
														$RackRate=$rmdetail->$beforediscount_var;
													$discount=$rmdetail->packagediscount;
													
													if(isset($rmdetail->$isdisplayregularrate) && $rmdetail->$isdisplayregularrate=='Y')
														$discount_amt = $RackRate;
													else
														$discount_amt = $RackRate - $discount;												
													$dealcnt++;
												}
												
											}
											
											if($nightrule=='' && $dealcnt > 0)
											{
												if(isset($rmdetail->$dt2))
												{
													$WithoutTax_BaseRate[$rmdetail->$dt2]=$discount_amt;
													
													$WithoutTax_GSTRate[$rmdetail->$dt2]=$rateGST;
												}
												if($RoomRevenueTax!='' && isset($rmdetail->$dt2) && isset($taxdata[$rmdetail->$dt2]))
												{
													if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
													{
														$find_tax=$ObjProcessDao->calculateTax($rateGST, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode);
													}
													else
													{
														$find_tax=$ObjProcessDao->calculateTax($discount_amt, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode);
													}
													if(isset($rmdetail->$dt2))
														$Taxes[$rmdetail->$dt2]=$find_tax;
													
													$taxrate = $discount_amt + $find_tax;
												}
												else
													$taxrate = $discount_amt;
												   
												   $find_adjustment=$ObjProcessDao->getRoundVal($taxrate,false,$hotelcode,$digits_after_decimal);
												   if(isset($rmdetail->$dt2))
													$Adjustment[$rmdetail->$dt2]=$find_adjustment;
												   $taxrate=$taxrate+$find_adjustment;
												   
												   if(isset($rmdetail->$dt2))
													$BaseRate[$rmdetail->$dt2]=$taxrate;
											}
												
											if(isset($nightrule) && $nightrule!='')
											{
												if($dealcnt==0)
												{
													$RackRate=0; 
													if(isset($rmdetail->$beforediscount_var))
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
													if($lblfree=='')
														$RackRate = calculatePromotion("PER", $promotiondeal, $RackRate, $amount, $nnights, $_loopCnt, $arrDateRange2,$displayregurate);
													else
														$RackRate = $RackRate;	
													
													if(isset($rmdetail->$dt2))
													{
														$WithoutTax_BaseRate[$rmdetail->$dt2]=$RackRate;
														
														$WithoutTax_GSTRate[$rmdetail->$dt2]=$rateGST;
														
													}
													if($RoomRevenueTax!='' && isset($rmdetail->$dt2) && isset($taxdata[$rmdetail->$dt2]))
													{
														
														if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
														{
															$find_tax=$ObjProcessDao->calculateTax($rateGST, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode);
														}
														else
														{
															$find_tax=$ObjProcessDao->calculateTax($RackRate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode);
														}
														if(isset($rmdetail->$dt2))
															$Taxes[$rmdetail->$dt2]=$find_tax;
														$taxrate = $RackRate + $find_tax;
													}
													else
														$taxrate = $RackRate;
														
													$find_adjustment=$ObjProcessDao->getRoundVal($taxrate,false,$hotelcode,$digits_after_decimal);//adjustment
													// array_push($Adjustment,$find_adjustment);
													if(isset($rmdetail->$dt2))
														$Adjustment[$rmdetail->$dt2]=$find_adjustment;
													
													$taxrate=$taxrate+$find_adjustment;
													 //array_push($BaseRate,$taxrate);
													if(isset($rmdetail->$dt2))
													$BaseRate[$rmdetail->$dt2]=$taxrate;
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
															$find_tax=$ObjProcessDao->calculateTax($rateGST, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode);
														}
														else
														{
															$find_tax=$ObjProcessDao->calculateTax($RackRate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode);
														}
														//array_push($Taxes,$find_tax);
														if(isset($rmdetail->$dt2))
															$Taxes[$rmdetail->$dt2]=$find_tax;
														$taxrate = $RackRate + $find_tax;
													}
													else
														$taxrate = $RackRate;
													$find_adjustment=$ObjProcessDao->getRoundVal($taxrate,false,$hotelcode,$digits_after_decimal);//adjustment
													// array_push($Adjustment,$find_adjustment);
													if(isset($rmdetail->$dt2))
														$Adjustment[$rmdetail->$dt2]=$find_adjustment;
													$taxrate=$taxrate+$find_adjustment;
														//array_push($BaseRate,$taxrate);
													if(isset($rmdetail->$dt2))
														$BaseRate[$rmdetail->$dt2]=$taxrate;
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
													
													if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
													{
														$find_tax=$ObjProcessDao->calculateTax($rateGST, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode);
													}
													else
													{
													//Chandrakant - 1.0.53.61 - 08 December 2017 - END
														$find_tax=$ObjProcessDao->calculateTax($RackRate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode);
													}
													
													//array_push($Taxes,$find_tax);
													if(isset($rmdetail->$dt2))
													   $Taxes[$rmdetail->$dt2]=$find_tax;
													$taxrate = $RackRate + $find_tax;
												}
												else
													$taxrate = $RackRate;
												$find_adjustment=$ObjProcessDao->getRoundVal($taxrate,false,$hotelcode,$digits_after_decimal);//adjustment
												//array_push($Adjustment,$find_adjustment);
												 if(isset($rmdetail->$dt2))
														$Adjustment[$rmdetail->$dt2]=$find_adjustment;
												$taxrate=$taxrate+$find_adjustment;
												//array_push($BaseRate,$taxrate);//changed from o_day_base
												if(isset($rmdetail->$dt2))
													$BaseRate[$rmdetail->$dt2]=$taxrate;
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
											
											$o_day_extra_adult='o_day_extra_adult_'.$_startDay;
											$o_day_extra_child='o_day_extra_child_'.$_startDay;
											
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
											
											//array_push($WithoutTax_ExtraAdultRate,$adult_rate);
											if(isset($rmdetail->$dt2))
												$WithoutTax_ExtraAdultRate[$rmdetail->$dt2]=$adult_rate;
											
											//array_push($WithoutTax_ExtraChildRate,$child_rate);
											if(isset($rmdetail->$dt2))
												$WithoutTax_ExtraChildRate[$rmdetail->$dt2]=$child_rate;
											
											if($RoomRevenueTax!='' && isset($rmdetail->$dt2) && isset($taxdata[$rmdetail->$dt2]))
											{
											  # Sheetal Panchal - 1.0.52.60 - 5th Aug 2017, Purpose - For Non linear rate mode setting , Pass rate mode
												//$find_tax=$ObjProcessDao->calculateTax($adult_rate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','extraadult','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,'',$rate_mode);
												$find_tax=$ObjProcessDao->calculateTax($adult_rate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'extraadult','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,'',$rate_mode); //Pinal - 1.0.52.60 - 8 August 2017 - Purpose - Issue in tax for per adult , per child and per pax.
												
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
											  
												//$find_tax=$ObjProcessDao->calculateTax($child_rate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base','','extrachild',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,'',$number_child,$rate_mode);
												//$this->log->logIt($rmdetail->display_name." | ".$rmdetail->roomtypeunkid." - ".$rmdetail->ratetypeunkid);
												
												$find_tax=$ObjProcessDao->calculateTax($child_rate, $RoomRevenueTax, $rmdetail->$dt2,$taxdata[$rmdetail->$dt2],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'extrachild','','',$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,'',$number_child,$rate_mode); //Pinal - 1.0.52.60 - 8 August 2017 - Purpose - Issue in tax for per adult , per child and per pax. , //Pinal - 1.0.52.60 - 9 August 2017 - Purpose - Issue in apply on rack rack
												//array_push($Taxes_Extra_Child,$find_tax);
												
												if(isset($rmdetail->$dt2))
													$Taxes_Extra_Child[$rmdetail->$dt2]=$find_tax;
												$extra_child_rate = $child_rate + $find_tax;
											}
											else
												$extra_child_rate = $child_rate;
											
											$find_adjustment=$ObjProcessDao->getRoundVal($extra_adult_rate,false,$hotelcode,$digits_after_decimal);//adjustment
											//array_push($Adjustment_Extra_Adult,$find_adjustment);//adjustment
											if(isset($rmdetail->$dt2))
													$Adjustment_Extra_Adult[$rmdetail->$dt2]=$find_adjustment;
											$extra_adult_rate=$extra_adult_rate+$find_adjustment;
											
											$find_adjustment=$ObjProcessDao->getRoundVal($extra_child_rate,false,$hotelcode,$digits_after_decimal);//adjustment
											//array_push($Adjustment_Extra_Child,$find_adjustment);//adjustment
											if(isset($rmdetail->$dt2))
													$Adjustment_Extra_Child[$rmdetail->$dt2]=$find_adjustment;
											$extra_child_rate=$extra_child_rate+$find_adjustment;
											
											//array_push($ExtraAdultRate,$extra_adult_rate);
											if(isset($rmdetail->$dt2))
													$ExtraAdultRate[$rmdetail->$dt2]=$extra_adult_rate;
											//array_push($ExtraChildRate,$extra_child_rate);
											if(isset($rmdetail->$dt2))
													$ExtraChildRate[$rmdetail->$dt2]=$extra_child_rate;
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
															'totalprice_inclusive_all'=>$find_total_withinc_all,
				   'avg_per_night_before_discount'=>(isset($StrikeRate) && !empty($StrikeRate))?(array_sum($StrikeRate) / count($StrikeRate)):'',
															'avg_per_night_after_discount'=>$sum_tot,
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
											$roomarray[$iCnt]['taxdetails']=$taxdata;
											$roomarray[$iCnt]['room_rates_info']['RackRates']=$room_rack_value;
											
											if($rate_mode=='NONLINEAR')
											{
												$roomarray[$iCnt]['room_rates_info']['Non_Linear_Rates']=$non_linear_rates;
												$roomarray[$iCnt]['room_rates_info']['Non_Linear_Rates_Beforediscount']=$non_linear_rates_before;
												$roomarray[$iCnt]['room_rates_info']['Non_Linear_Rates_BeforediscountGST']=$non_linear_rates_before_gst;
											}
										}
										if(($Iscrs_RoomList == 1 || $getminrateonly == 1) && isset($rmdetail->MinAvgPerNight))
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
													 
													 $extrachild=($number_child - $roomarray[$iCnt]['base_child_occupancy']); 
												 }
											  }
	
												$pernighttax_tmp=0; //Pinal
												if($RoomRevenueTax!='' && isset($arrDateRange2[$i_price]) && isset($taxdata[$arrDateRange2[$i_price]]))
												{
			
													  if($rate_mode=='NONLINEAR')
													  {
														 if($number_adult > $roomarray[$iCnt]['base_adult_occupancy'])
															   $ExtraAdult_rackratetax=$number_adult - $roomarray[$iCnt]['base_adult_occupancy'];
														 if($number_child > $roomarray[$iCnt]['base_child_occupancy'])
															   $ExtraChild_rackratetax=$number_child - $roomarray[$iCnt]['base_child_occupancy'];
															   
														if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
														{
															$pernighttax_tmp=$ObjProcessDao->calculateTax($night_priceGST, $RoomRevenueTax, $arrDateRange2[$i_price],$taxdata[$arrDateRange2[$i_price]],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base',$ExtraAdult_rackratetax,$ExtraChild_rackratetax,$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode);
														}
														else
														{
															$pernighttax_tmp=$ObjProcessDao->calculateTax($night_price, $RoomRevenueTax, $arrDateRange2[$i_price],$taxdata[$arrDateRange2[$i_price]],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base',$ExtraAdult_rackratetax,$ExtraChild_rackratetax,$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode); //Pinal - fixed issue of tax apply on rack rate
														}
			
													  }
													  else
													  {
														 if($FindSlabForGSTIndia==1 && $LayoutTheme==2)
														 {
															$pernighttax_tmp=$ObjProcessDao->calculateTax($night_priceGST, $RoomRevenueTax, $arrDateRange2[$i_price],$taxdata[$arrDateRange2[$i_price]],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base',$extraadult,$extrachild,$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode);
														 }
														 else
														 {
															$pernighttax_tmp=$ObjProcessDao->calculateTax($night_price, $RoomRevenueTax, $arrDateRange2[$i_price],$taxdata[$arrDateRange2[$i_price]],0,$rmdetail->roomtypeunkid,$rmdetail->ratetypeunkid,$room_rack_value,'base',$extraadult,$extrachild,$exchange_rate1,$exchange_rate2,$digits_after_decimal,$hotelcode,$number_adult,$number_child,$rate_mode); //Pinal - fixed issue of tax apply on rack rate
														 }
			
													  }
														$pernighttax=$pernighttax+$pernighttax_tmp;
														$pernighttaxpax=$pernighttax_tmp; //Chandrakant - 2.0.16 - 22 April 2019 //Purpose : CEN-1032
												}
												
												$findround=$pernighttax_tmp+$night_price; 
												
												$total_roundval=$total_roundval+$ObjProcessDao->getRoundVal($findround,false,$hotelcode,$digits_after_decimal);//adjustment
												
												$total_roundvalpax=$ObjProcessDao->getRoundVal($findround,false,$hotelcode,$digits_after_decimal);//adjustment
												
												$roomarray[$iCnt]['Total_Price_ExtraPax'][$arrDateRange2[$i_price]]=$total_pricepax+$pernighttaxpax+$total_roundvalpax;
											}
											
											$roomarray[$iCnt]['Total_Price']=$total_price;
											$roomarray[$iCnt]['Total_ExclusiveTax_Price']=$total_exclusivetax_price;
											$roomarray[$iCnt]['Total_Tax']=$pernighttax;
											$roomarray[$iCnt]['Total_Adjusment']=$total_roundval;
											$roomarray[$iCnt]['Final_Total_Price']=$total_price+$pernighttax+$total_roundval;
											$roomarray[$iCnt]['Avg_Strike_Rate_Exclusive_Tax']=array_sum($BeforeDiscountRate)/count($BeforeDiscountRate); //Pinal - 9 February 2019 - Purpose : Multiple property listing get strike data.
										}
										//Summary calculation - end - flora
										
										$roomarray[$iCnt]['min_nights']=$minimum_nights_arr;
										$roomarray[$iCnt]['Hotel_amenities']=json_encode($hotelamenities);
										
										if($Iscrs_HotelList == 0)//Chinmay Gandhi - 10th Sept 2018 - Hotel Listing [ RES-1452 ]
										{
											if(!empty($minimum_nights_arr)){
												$roomarray[$iCnt]['Avg_min_nights']=max($minimum_nights_arr); //Pinal - changed from min to max.
											}
											
											$roomarray[$iCnt]['max_nights']=$maximum_nights_arr;
											
											if(!empty($maximum_nights_arr)){
												$roomarray[$iCnt]['Avg_max_nights']=min($maximum_nights_arr);
											}
										
											$roomarray[$iCnt]['check_in_time']=$chk_in_time;
											$roomarray[$iCnt]['check_out_time']=$chk_out_time;
											$roomarray[$iCnt]['TaxName']=$taxnamearray['TaxName'];
											
											$roomarray[$iCnt]['ShowPriceFormat'] = ($WebShowRatesAvgNightORWholeStay==2)?'Price for Whole stay':'Average Per Night Rate';
											$roomarray[$iCnt]['DefaultDisplyCurrencyCode'] = $WebDefaultCurrency;
																	 
											$roomarray[$iCnt]['deals'] = (isset($rmdetail->deals) && $rmdetail->deals!='')?$rmdetail->deals:'';
											
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

										if(isset($rmdetail->specialunkid) && isset($rmdetail->specialunkid))
										{
											 $packagedeal[$iCnt]=$rmdetail->roomrateunkid;
											 $packagedealids[$iCnt]=$rmdetail->specialunkid; //Pinal - 10 May 2018
										}
										
										if(isset($rmdetail->hiderateplan) && $rmdetail->hiderateplan=='1') //Pinal - 4 May 2018 - Purpose : Removed condition of $notdefinedstay according to condition added it was never going to be true and this was creating issue in api in meta search.
										{
											 $hiderateplan[$iCnt]=$rmdetail->roomrateunkid;
										}
										
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
											
											$amelist='';
											$ameidlist=array();
											//if(count($roomAmenities) > 0){
                                            if (!empty($roomAmenities)){//Kaushik Chauhan 29th July 2020 Purpose : 6 to PHP7.2 changes
												foreach($roomAmenities as $ame)
												{
													$amelist.=stripslashes($ame['amenity']).",";
													$ameidlist[$ame['amenityunkid']] = stripslashes($ame['amenity']);
												}
												$roomarray[$iCnt]['RoomAmenities'] = trim($amelist,",");
											}
											else
											{
												$roomarray[$iCnt]['RoomAmenities'] = '';
											}
											
											if($Iscrs_RoomList == 1)
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
													$img=$ObjImageDao->getImageFromBucket($data['image_path']);												
													if($firstphoto==0)		
													{							
														$roomarray[$iCnt]['room_main_image'] = $img;
														$fillImgs[$imgCnt]['room_main_image']=$img;
														$firstphoto++;
													}
													
													$fillImgs[$imgCnt]['image']=$img;											
													$imgCnt++;										
												}
											}									
											if(!empty($fillImgs))
											{
												$roomarray[$iCnt]['RoomImages'] = $fillImgs;
											}
											else
												$roomarray[$iCnt]['room_main_image'] = '';
										}
										
											$iCnt++;										
										$iIndex++;
									}										
								}
							}
                            
							if($metasearch=='GHF')
							{
								$findhidebe=implode(array_values($hiderateplan),',');
								$result_hidebe=$ObjProcessDao->gethidefrombookingengine($findhidebe,$hcode);
								
								foreach($hiderateplan as $index=>$rateplanid)
								{
									   if(in_array($rateplanid,$packagedeal) || (isset($result_hidebe[$rateplanid]) && $result_hidebe[$rateplanid]==1))
									   {
										  unset($roomarray[$index]);
									   }
								}
								
								$gethideassociated=implode(array_values($packagedealids),',');
								$result_hideassoc=$ObjProcessDao->gethideassociatedrareplan($gethideassociated,$hcode);
								
								foreach($packagedealids as $index=>$packid)
								{
										$roomarray[$index]['hiderateplan']=$result_hideassoc[$packid];
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
                            
							$roomarray=array_values($roomarray);
							
							unset($packages_as_ratetype,$packages_as_ratetype_map);
							
							$isbookedtoday=false;
						}						
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
					
					if($isInv)
					{
						$lst_roomprice=floatval($roomdata['room_rates_info']['MinAvgPerNight']);
					}
					
					if($isInv == false)
						$roomInv = 0;
						
					if((int)$roomdata['min_nights'][$eZ_chkin] > (int)$number_night)
						$roomInv = 0;
					
					if(!isset($RoomListingData['min_rate']))
						$RoomListingData['min_rate'] = 0;
						
					if(!isset($RoomListingData['available_room'])){
                        $RoomListingData['available_room'] = 0;
                    }
						
					if($roomInv > 0)
					{
						if($RoomListingData['min_rate'] > $lst_roomprice || $RoomListingData['min_rate'] == 0)
						{
							if($isTaxInc == '1')//Chinmay Gandhi - Set Round off setting
								$RoomListingData['min_rate'] = $lst_roomprice+$ObjProcessDao->getRoundVal($lst_roomprice,false,$hotelcode,$digits_after_decimal);
							else
								$RoomListingData['min_rate'] = $lst_roomprice;
						}
						
						if($roomInv == 0)
							$RoomListingData['available_room'] = 0;
						else
							$RoomListingData['available_room'] = 1;
					}
				}
				return $RoomListingData;
			}
			else
			{
				return $roomarray;
			}
        }
        catch(Exception $e){
            $this->log->logIt($this->module.' - Exception - getRoomList - '.$e);
        }
    }
    
    public function new_currency($from_Currency, $to_Currency, $amount)
    {
        try {
                $this->log->logIt($this->module.' - Currency conversion');
                $amount        = urlencode($amount);
                $from_Currency = urlencode($from_Currency);
                $to_Currency   = urlencode($to_Currency);
                
                $html = new simple_html_dom();
                //Devang : 06-02-2020 : add GEL currency for exchange rate - CEN-1569
                //Mayur : 24-03-2020 : Added Condition for LAK Currency
                if($from_Currency == 'BAM' || $to_Currency == 'BAM' || $from_Currency == 'GEL' || $to_Currency == 'GEL'|| $from_Currency == 'LAK' || $to_Currency == 'LAK'){
                    $URL = "https://www.exchange-rates.org/converter/$from_Currency/$to_Currency/$amount";
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $URL);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'self/tripconnect_v7');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
                    $inter_content = curl_exec($ch);
                    $arrCurlInfo = curl_getinfo($ch);
                    $this->log->logIt('curl infor new_currency -- '.json_encode($arrCurlInfo));
                    curl_close($ch);
                    
                    
                    $html->load($inter_content);
                    
                    if ($html->find('span[id=ctl00_M_lblToAmount]')) {
                        foreach ($html->find('span[id=ctl00_M_lblToAmount]') as $v) {
                            $data = $v->text();
                            break;
                        }
                        
                        $newprice = preg_replace("/[a-zA-Z]/", "", $data);
                        $newprice = trim($newprice);
                        
                        //Devang : 26 March 2020 : Curency conversion sorted out : START
                        if ($newprice > 0)
                        {
                            $newprice = str_replace(",", "", $newprice);
                            $newprice = (float)$newprice;
                        }
                        //Devang : 26 March 2020 : Curency conversion sorted out : END
                        
                        if ($newprice > 0)
                            return $newprice;
                        else {
                            $this->log->logIt($this->module.' - Currency conversion from yahoo ');
                            $yahooprice = $this->new_currency1($from_Currency, $to_Currency, $amount);
                            return $yahooprice;
                        }
                    } else {
                        $this->log->logIt($this->module . "-new rates not found error Divert to yahoo Currency Function...");
                        $yahooprice = $this->new_currency1($from_Currency, $to_Currency, $amount);
                        return $yahooprice;
                    }
                }
                else
                {
                    $URL = "http://www.x-rates.com/calculator/?from=$from_Currency&to=$to_Currency&amount=$amount";
                    //$this->log->logIt($this->module.' - URL - '.$URL);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $URL);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'self/tripconnect_v7');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
                    $inter_content = curl_exec($ch);
                    
                    $arrCurlInfo = curl_getinfo($ch);
                    //$this->log->logIt('curl infor x-rates -- '.json_encode($arrCurlInfo));//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
                    curl_close($ch);
                    
                    $html->load($inter_content);
                    
                    if ($html->find('span[class=ccOutputRslt]')) {
                        foreach ($html->find('span[class=ccOutputRslt]') as $v) {
                            $data = $v->text();
                            break;
                        }
                        
                        $newprice = preg_replace("/[a-zA-Z]/", "", $data);
                        $newprice = trim($newprice);
                      
                        if ($newprice > 0)
                            return $newprice;
                        else {
                            $yahooprice = $this->new_currency1($from_Currency, $to_Currency, $amount);
                            return $yahooprice;
                        }
                    } else {
                        $yahooprice = $this->new_currency1($from_Currency, $to_Currency, $amount);
                        return $yahooprice;
                    }
                }
        }
        catch (Exception $e) {
            $this->log->logIt($this->module . "- Exception Currency - ");
            
            $URL = "http://www.x-rates.com/calculator/?from=$from_Currency&to=$to_Currency&amount=$amount";
            $this->log->logIt($this->module.' - URL - '.$URL);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $URL);
            curl_setopt($ch, CURLOPT_USERAGENT, 'self/tripconnect_v7');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
           
            $inter_content = curl_exec($ch);
            curl_close($ch);
            
            $html->load($inter_content);
            
            if ($html->find('span[class=ccOutputRslt]')) {
                foreach ($html->find('span[class=ccOutputRslt]') as $v) {
                    $data = $v->text();
                    break;
                }
                
                $newprice = preg_replace("/[a-zA-Z]/", "", $data);
                $newprice = trim($newprice);
              
                if ($newprice > 0){
                    return $newprice;
                }
            }
        }
    }
    
    public function new_currency1($from_Currency, $to_Currency, $amount)
    {
        try {
            
            $html = new simple_html_dom();
            
            $URL = "http://www.x-rates.com/calculator/?from=$from_Currency&to=$to_Currency&amount=$amount";
            $this->log->logIt($this->module.' - URL - '.$URL);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $URL);
            curl_setopt($ch, CURLOPT_USERAGENT, 'self/tripconnect_v7');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            $inter_content = curl_exec($ch);
            
            $arrCurlInfo = curl_getinfo($ch);
            $this->log->logIt('curl infor x-rates -- '.json_encode($arrCurlInfo));
            curl_close($ch);
            
            $html->load($inter_content);
            
            if ($html->find('span[class=ccOutputRslt]')) {
                foreach ($html->find('span[class=ccOutputRslt]') as $v) {
                    $data = $v->text();
                    break;
                }
                $newprice = 1;
                $newprice = preg_replace("/[a-zA-Z]/", "", $data);
                $newprice = trim($newprice);
              
                if ($newprice > 0){
                    return $newprice;
                }
            }
        }
        catch (Exception $e) {
            $this->log->logIt($this->module . "- yahoo catch Error Divert to google Currency Function...");
            $price = $this->currency($from_Currency, $to_Currency, $amount);
            return $price;
        }
    }
    
    public function yahoocurrency($from_Currency, $to_Currency, $amount)
    {
        try {
            
            $URL = "https://www.exchange-rates.org/converter/$from_Currency/$to_Currency/$amount";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $URL);
            curl_setopt($ch, CURLOPT_USERAGENT, 'self/tripconnect_v7');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            
            $inter_content = curl_exec($ch);
            $arrCurlInfo = curl_getinfo($ch);
            $this->log->logIt('curl infor yahoocurrency -- '.json_encode($arrCurlInfo));
            curl_close($ch);
            
            $html->load($inter_content);
            
            if ($html->find('span[id=ctl00_M_lblToAmount]')) {
                foreach ($html->find('span[id=ctl00_M_lblToAmount]') as $v) {
                    $data = $v->text();
                    break;
                }
                
                $newprice = preg_replace("/[a-zA-Z]/", "", $data);
                $newprice = trim($newprice);
                
                if ($newprice > 0){
                    return $newprice;
                }
            }
        }
        catch (Exception $e) {
            $this->log->logIt($this->module . "- yahoo catch Error Divert to google Currency Function...");
            $price = $this->currency($from_Currency, $to_Currency, $amount);
            return $price;
        }
    }
    
    
    public function currency($from_Currency, $to_Currency, $amount)
    {
        try {
                $this->log->logIt("In Currency module....");
                $amount        = urlencode($amount);
                $from_Currency = urlencode($from_Currency);
                $to_Currency   = urlencode($to_Currency);
                
                $get = file_get_contents("https://www.google.com/finance/converter?a=$amount&from=$from_Currency&to=$to_Currency");
             
                $this->log->logIt("https://www.google.com/finance/converter?a=$amount&from=$from_Currency&to=$to_Currency");
                $get = explode("<span class=bld>", $get);
                
                if (isset($get[1]) && trim($get[1]) != '') {
                    $get              = explode("</span>", $get[1]);
                    $converted_amount = preg_replace("/[^0-9\.]/", null, $get[0]);
                    return $converted_amount;
                } else {
                    $price = $this->currency_old($from_Currency, $to_Currency, $amount);
                    return $price;
                }
        }
        catch (Exception $e) {
            $this->log->logIt($this->module . "- Error Divert to Currency Old Function...");
            $price = $this->currency_old($from_Currency, $to_Currency, $amount);
            return $price;
        }
    }
    
    
    public function currency_old($from_Currency, $to_Currency, $amount)
    {
        try {
            $this->log->logIt("In old currency function ...");
            $amount        = urlencode($amount);
            $from_Currency = urlencode($from_Currency);
            $to_Currency   = urlencode($to_Currency);
            
            $fields_string = "template=9a&Amount=1&From=" . $from_Currency . "&To=" . $to_Currency . "&submit=Perform+Currency+Conversion";
            $this->log->logIt("String ==> " . $fields_string);
            $url     = "http://www.xe.com/ucc/convert.cgi";
            $ch      = curl_init();
            $timeout = 0;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, 'self/tripconnect_v7');
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $rawdata = curl_exec($ch);
            curl_close($ch);
            
            $rawdata = preg_replace('/(\s\s+|\t|\n)/', ' ', $rawdata);
            
            if (preg_match("#<TABLE CELLPADDING=5 CELLSPACING=0 BORDER=0>(.*?)<\/TABLE>#", $rawdata, $match2)) {
                if (preg_match("#<TD ALIGN=LEFT><FONT FACE=\"Arial,Helvetica\"> <FONT SIZE=\+1><B>(.*?)<\/B><\/FONT>#", $match2[1], $match3)) {
                    $rec = explode(" ", $match3[1]);
                    $this->log->logIt("Conversion Rate in getCurrency ====> " . $rec[0]);
                    $price = str_replace(',', '', $rec[0]);
                    $this->log->logIt("Conversion Rate in getCurrency ====> " . $price);
                    return $price;
                }
            }
        }
        catch (Exception $e) {
            $this->log->logIt("In old currency function ... Error found..." . $e);
        }
    }
    
    public function calculateTotalExtraCharge($hotelCode, $apiKey, $checkIn, $checkOut, $reqadultTot, $reqchildTot, $arrExtraCharge = array(), $ratePlanUnkId = 0)
    {
        $arrTotalExtraCharge = array();
        if (count($arrExtraCharge) > 0 && $arrExtraCharge != '') {
            $ObjProcessDao=new processdao();
            $resultauth=$ObjProcessDao->isAuthorizedUser('','',$hotelCode);
            
            foreach($resultauth as $authuser)
			{
				$ObjProcessDao->databasename =  $authuser['databasename'];
				$ObjProcessDao->hotel_code = $authuser['hotel_code'];
				$ObjProcessDao->hostname = $this->hostname;
				$ObjProcessDao->newurl = $authuser['newurl'];
				$ObjProcessDao->localfolder = $authuser['localfolder'];
				
				$this->selectdatabase($ObjProcessDao->databasename); 
			}
            
            $arrTotalExtraCharge = array();
            $totExtraCharge      = 0;
            $excnt               = 0;
            $addcnt              = 0;
            
            $this->log->logIt($this->module.' - charges - '.json_encode($arrExtraCharge));
            foreach ($arrExtraCharge as $extra_charge) {
                $ischargealways  = (isset($extra_charge['ischargealways'])) ? (string) $extra_charge['ischargealways'] : 0;
                $chargeRule      = (isset($extra_charge['ChargeRule'])) ? (string) $extra_charge['ChargeRule'] : "";
                $postingRule     = (isset($extra_charge['PostingRule'])) ? (string) $extra_charge['PostingRule'] : "";
                $extraChargeId   = (isset($extra_charge['ExtraChargeId'])) ? (int) $extra_charge['ExtraChargeId'] : 0;
                $applyonRatePlan = (isset($extra_charge['applyon_rateplan'])) ? (string) $extra_charge['applyon_rateplan'] : "";
                
                if ($ischargealways == 1) {
                    if ($applyonRatePlan != "ALL") {
                        
                        $arrApplyRatePlan = array();
                        $arrApplyRatePlan = explode(",", $applyonRatePlan);
                        if (!in_array($ratePlanUnkId, $arrApplyRatePlan)) {
                            continue;
                        }
                    }
                    
                    ++$addcnt;
                    switch ($chargeRule) {
                        case "PERADULT":
                            $titem = $reqadultTot;
                            break;
                        
                        case "PERCHILD":
                            $titem = $reqchildTot; 
                            break;
                        
                        case "PERPERSON":
                            $titem = $reqadultTot + $reqchildTot;
                            break;
                        
                        case "PERINSTANCE":
                            $titem = 1;
                            break;
                        
                        case "PERBOOKING":
                            $titem = 1;
                            break;
                        
                        case "PERQUANTITY":
                            $arrTotalExtraCharge['quantity'][$excnt] = ($excnt + 1);
                            $titem                                   = 1;
                            break;
                    }
                    
                    
                    if(isset($hotelCode) && $hotelCode!='')
                    {				
                        if(isset($extraChargeId) && $extraChargeId!='' && isset($checkIn) && $checkIn!='' && isset($checkOut) && $checkOut!='' && isset($titem) && $titem!='' )
                        {
                            $ExtraChargeId=$extraChargeId;
                            $check_in_date=$checkIn;
                            $check_out_date=$checkOut;
                            $Total_ExtraItem=$titem;
                            
                            $ExtraChargeId_arr=explode(",",$extraChargeId);
                            $Total_ExtraItem_arr=explode(",",$titem);
                        
                            
                            if(count($ExtraChargeId_arr)!=count($Total_ExtraItem_arr) || array_search('',$ExtraChargeId_arr) || array_search('',$Total_ExtraItem_arr))
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
                            
                            $extrachargedetails=$ObjProcessDao->getExtraCharges($hotelCode,'en',$ExtraChargeId);
                          
                            $individual_charge=array();
                            $totalcharge=0;
                            if(count($extrachargedetails)>0)
                            {						
                                for($i=0;$i<count($extrachargedetails);$i++)
                                {
                                    $result=$ObjProcessDao->getTotalExtraCharge($check_in_date,$check_out_date,$extrachargedetails[$i]['PostingRule'],$Total_ExtraItem_arr[$i],$extrachargedetails[$i]['Rate'],$extrachargedetails[$i]['Tax'],$hotelCode,$extrachargedetails[$i]['ValidFrom'],$extrachargedetails[$i]['ValidTo']);
                                    $individual_charge[$extrachargedetails[$i]['ExtraChargeId']]=$result;							
                                    $totalcharge+=(float)$result;
                                }
                                
                                $extra_cal_Info =  array(
                                        "IndividualCharge"=>$individual_charge,
                                        "TotalCharge"=>$totalcharge
                                         );
                            }
                            else
                            {
                                return array();
                            }
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

                    $totExtraCharge = $totExtraCharge + (int) $extra_cal_Info['TotalCharge'];
                    
                    
                    $arrTotalExtraCharge['ExtraCharge']['Extra_' . $addcnt]['ExtraChargeId'] = $extra_charge['ExtraChargeId'];
                    
                    if ($extra_charge['ChargeRule'] == 'PERADULT' || $extra_charge['ChargeRule'] == 'PERPERSON') {
                        $arrTotalExtraCharge['ExtraCharge']['Extra_' . $addcnt]['ChargeAdult'] = $reqadultTot;
                    }
                    
                    if ($extra_charge['ChargeRule'] == 'PERCHILD' || $extra_charge['ChargeRule'] == 'PERPERSON') {
                        $arrTotalExtraCharge['ExtraCharge']['Extra_' . $addcnt]['ChargeChild'] = $reqchildTot;
                    }
                    
                    if ($extra_charge['ChargeRule'] == 'PERQUANTITY') {
                        $arrTotalExtraCharge['ExtraCharge']['Extra_' . $addcnt]['ChargeQuantity'] = $excnt + 1;
                    }
                    ++$excnt;
                }
            }
            $arrTotalExtraCharge['total_exchanrges'] = $totExtraCharge;
            $this->log->logIt($this->module." - Total calculation extra charge - " . json_encode($arrTotalExtraCharge, true));
        }
        return $arrTotalExtraCharge;
	}
	
	public function FindphoneCode($country)
    {
        $this->log->logIt("find country phone code...");
        $CountryCode           = '';
        $MobileCodeCountryVise = Array(
            "Afghanistan" => "93",
            "Albania" => "355",
            "Algeria" => "213",
            "American Samoa" => "1 684",
            "Andorra" => "376",
            "Angola" => "244",
            "Anguilla" => "1 264",
            "Antigua and Barbuda" => "1 268",
            "Argentina" => "54",
            "Armenia" => "374",
            "Aruba" => "297",
            "Australia" => "61",
            "Austria" => "43",
            "Azerbaijan" => "994",
            "Bahamas" => "1 242",
            "Bahrain" => "973",
            "Bangladesh" => "880",
            "Barbados" => "1 246",
            "Belarus" => "375",
            "Belgium" => "32",
            "Belize" => "501",
            "Benin" => "229",
            "Bermuda" => "1 441",
            "Bhutan" => "975",
            "Bolivia" => "591",
            "Bosnia and Herzegovina" => "387",
            "Botswana" => "267",
            "Brazil" => "55",
            "British Indian Ocean Territory" => "246",
            "Brunei" => "673",
            "Bulgaria" => "359",
            "Burkina Faso" => "226",
            "Burundi" => "257",
            "Cambodia" => "855",
            "Cameroon" => "237",
            "Canada" => "1",
            "Cape Verde" => "238",
            "Cayman Islands" => "1 345",
            "Central African Republic" => "236",
            "Chad" => "235",
            "Chile" => "56",
            "China" => "86",
            "Christmas Island" => "61 8 9164",
            "Cocos Islands" => "61 8 9162",
            "Colombia" => "57",
            "Cook Islands" => "682",
            "Costa Rica" => "506",
            "Côte d'Ivoire" => "225",
            "Croatia (Hrvatska)" => "385",
            "Croatia" => "385", 
            "croatia" => "385", 
            "Cuba" => "53",
            "Cyprus" => "357",
            "Czech Republic" => "420",
            "Denmark" => "45",
            "Djibouti" => "253",
            "Dominica" => "1 767",
            "Dominican Republic" => "1 809 / 829 / 849",
            "East Timor" => "670",
            "Ecuador" => "593",
            "Egypt" => "20",
            "El Salvador" => "503",
            "Equatorial Guinea" => "240",
            "Eritrea" => "291",
            "Estonia" => "372",
            "Ethiopia" => "251",
            "Falkland Islands" => "500",
            "Faroe Islands" => "298",
            "Fiji" => "679",
            "Finland" => "358",
            "France" => "33",
            "French Guiana" => "594",
            "French Polynesia" => "689",
            "Gabon" => "241",
            "Gambia" => "220",
            "Georgia" => "995",
            "Germany" => "49",
            "Ghana" => "233",
            "Gibraltar" => "350",
            "Greece" => "30",
            "Greenland" => "299",
            "Grenada" => "1 473",
            "Guadeloupe" => "590",
            "Guam" => "1 671",
            "Guatemala" => "502",
            "Guinea" => "224",
            "Guinea-Bissau" => "245",
            "Guyana" => "592",
            "Haiti" => "509",
            "Honduras" => "504",
            "Hungary" => "36",
            "Iceland" => "354",
            "India" => "91",
            "Indonesia" => "62",
            "Iran" => "98",
            "Iraq" => "964",
            "Ireland" => "353",
            "Israel" => "972",
            "Italy" => "39",
            "Jamaica" => "1 876",
            "Japan" => "81",
            "Jordan" => "962",
            "Kazakhstan" => "7 6xx/7xx",
            "Kenya" => "254",
            "Kiribati" => "686",
            "Kuwait" => "965",
            "Kyrgyzstan" => "996",
            "Laos" => "856",
            "Latvia" => "371",
            "Lebanon" => "961",
            "Lesotho" => "266",
            "Liberia" => "231",
            "Libya" => "218",
            "Liechtenstein" => "423",
            "Lithuania" => "370",
            "Luxembourg" => "352",
            "Macedonia" => "389",
            "Madagascar" => "261",
            "Malawi" => "265",
            "Malaysia" => "60",
            "Maldives" => "960",
            "Mali" => "223",
            "Malta" => "356",
            "Marshall Islands" => "692",
            "Martinique" => "596",
            "Mauritania" => "222",
            "Mauritius" => "230",
            "Mayotte" => "262 269 / 639",
            "Mexico" => "52",
            "Moldova" => "373",
            "Monaco" => "377",
            "Mongolia" => "976",
            "Montserrat" => "1 664",
            "Morocco" => "212",
            "Mozambique" => "258",
            "Myanmar" => "95",
            "Namibia" => "264",
            "Nauru" => "674",
            "Nepal" => "977",
            "Netherlands" => "31",
            "New Caledonia" => "687",
            "New Zealand" => "64",
            "Nicaragua" => "505",
            "Niger" => "227",
            "Nigeria" => "234",
            "Norfolk Island" => "672 3",
            "Northern Mariana Islands" => "1 670",
            "Norway" => "47",
            "Oman" => "968",
            "Pakistan" => "92",
            "Palau" => "680",
            "Panama" => "507",
            "Papua New Guinea" => "675",
            "Paraguay" => "595",
            "Peru" => "51",
            "Philippines" => "63",
            "Poland" => "48",
            "Portugal" => "351",
            "Puerto Rico" => "1 787 / 939",
            "Qatar" => "974",
            "Réunion" => "262",
            "Romania" => "40",
            "Russia" => "7",
            "Rwanda" => "250",
            "Saint Helena" => "290",
            "Saint Kitts and Nevis" => "1 869",
            "Saint Lucia" => "1 758",
            "Saint Vincent and the Grenadines" => "1 784",
            "Saint-Pierre and Miquelon" => "508",
            "Samoa" => "685",
            "San Marino" => "378",
            "Sao Tome and Principe" => "239",
            "Saudi Arabia" => "966",
            "Senegal" => "221",
            "Seychelles" => "248",
            "Sierra Leone" => "232",
            "Singapore" => "65",
            "Slovakia" => "421",
            "Slovenia" => "386",
            "Solomon Islands" => "677",
            "Somalia" => "252",
            "South Africa" => "27",
            "Spain" => "34",
            "Sri Lanka" => "94",
            "Sudan" => "249",
            "Suriname" => "597",
            "Swaziland" => "268",
            "Sweden" => "46",
            "Switzerland" => "41",
            "Syria" => "963",
            "Tajikistan" => "992",
            "Tanzania" => "255",
            "Thailand" => "66",
            "Togo" => "228",
            "Tokelau" => "690",
            "Tonga" => "676",
            "Trinidad and Tobago" => "1 868",
            "Tunisia" => "216",
            "Turkey" => "90",
            "Turkmenistan" => "993",
            "Turks and Caicos Islands" => "1 649",
            "Tuvalu" => "688",
            "Uganda" => "256",
            "Ukraine" => "380",
            "United Arab Emirates" => "971",
            "United Kingdom" => "44",
            "United States of America" => "1",
            "Uruguay" => "598",
            "Uzbekistan" => "998",
            "Vanuatu" => "678",
            "Vatican City" => "39 06 698",
            "Venezuela" => "58",
            "Vietnam" => "84",
            "Western Sahara" => "212 5288 / 5289",
            "Yemen" => "967",
            "Zambia" => "260",
            "Zimbabwe" => "263",
            "Montenegro" => "382",
            "Serbia" => "381",
            "South Sudan" => "211",
            "Bonaire" => "599 7",
            "Curacao" => "599 9",
            "Saba" => "599 4",
            "Sint Eustatius" => "599 3",
            "Sint Maarten" => "599 5"
        );
        
        foreach ($MobileCodeCountryVise as $key => $code) {
            if ($country == $key) {
                $CountryCode = $code;
            }
        }
        return $CountryCode;
    }
}

?>
