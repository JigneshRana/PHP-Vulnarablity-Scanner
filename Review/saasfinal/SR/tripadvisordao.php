<?php

class tripadvisordao {	

    private $log;
    private $module = "tripadvisordao";
    private $hotelcode;
    private $tripid;
    private $databasename;
    private $hostname;
	private $chdao;	
	private $ObjTranProcessDao;
	private $ObjBookingDao;
	private	$ObjMasterDao;
	private $ObjTranDao;
	private $ObjRoomTypeDao;
	public  $connection;
	public  $roomtypeunkid;
    function __construct() {
        try {
            $this->log = new logger("tripadvisor_integration");
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

    public function GetRecordset($detail)  
	{
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "GetRoomdetail");
			#$this->log->logIt(print_r($detail,true));
			$start_date = $detail['start_date'];
			$end_date = $detail['end_date'];

			$this->chdao = new channelupdatedao();
			$this->chdao->log =  new logger("tripadvisor_integration");

			$number_night = util::DateDiff($start_date,$end_date);
			
			$number_adult = $detail['num_adults'];
			$num_children = $detail['num_children'];
			$num_rooms = trim($detail['num_rooms']);
			$currency = $detail['currency'];
			
			$result =  $this->getHotelDetail();
			$res = $result->resultValue['record'];
			
			#print_r($res);
			
			#http://192.168.20.65/booking/book-rooms-1001  local
			#live live.ipms247.com
			#"http://".$_SERVER["HTTP_HOST"]."/saas/test/testplugin.html";
			#http://192.168.20.65/booking/rmdetails

			preg_match ("/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/", $start_date, $datePart); //Pinal - 24 December 2019 - Purpose : PHP5.6 to 7.2 [deprecatedFunctions]
			$out_date=date(staticarray::$webcalformat_key[$res['caldateformat']],mktime(0,0,0,$datePart[2],$datePart[3],$datePart[1]));		

			if($res['newurl'] == 0)	
			{
				#$url = "http://192.168.20.65/booking/book-rooms-".$this->hotelcode;
				$hotelId = $this->hotelcode;
			}	
			else
			{
				#$url = "http://192.168.20.65/booking/book-rooms-".$res['localfolder'];
				$hotelId = $res['localfolder'];
			}	

			$roomurl = "http://".$this->hostname."/booking/rmdetails";
						
		    $url = "http://".$this->hostname."/booking/searchroom.php?HotelId=".$hotelId."&checkin=".$out_date."&nonights=".$number_night."&calendarDateFormat=".$res['caldateformat']."&adults=".$number_adult."&child=0&rooms=".$num_rooms;

			$this->log->logIt("Request URL :- ".$roomurl);

			$data = array("checkin"=>$out_date,
						  "HotelId"=>$hotelId,
						  "nonights"=>$number_night,
						  "calendarDateFormat"=>$res['caldateformat'],
						  "adults"=>$number_adult,
						  "child" =>$num_children,
						  "rooms" =>$num_rooms,
						  "gridcolumn" => $number_night,
						  "modifysearch" => "false"
						 );

			$this->log->logIt("Request Data :- ".print_r($data,true));
			
			$ch=curl_init();
			curl_setopt($ch, CURLOPT_URL, $roomurl);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS,  $data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			 
			$res="";
			$res=curl_exec($ch);
			$curlinfo = curl_getinfo($ch);
			
			#print $res;

			$roomarray = array();

			$res = preg_replace('/(\s\s+|\t|\n)/', ' ',$res);
					
			if(preg_match('/var resgrid=(.*?); resgrid/',$res,$matches))
			{	
				$res_rec = $matches[1];

				$parameterArr = $this->chdao->getcfParameter($this->databasename,$this->hotelcode);
				$parameterInfo=array();
				foreach($parameterArr as $paramArr)
				{
					$parameterInfo[$paramArr['keyname']]=$paramArr['keyvalue'];
				}
				
				
				if($parameterInfo['RoomRevenueTax'] != '')
				{
					$appliedTax = $parameterInfo['RoomRevenueTax'];
					
					//Take Tax Details - START
					$taxid=explode(',',$appliedTax);
					$tax_string = implode("','",$taxid);

					$taxinfo_arr=array();

					if(count($taxid) > 0)
					{
						$taxresult = $this->chdao->getTaxDetail($this->databasename,$this->hotelcode,$tax_string);
						
						$apprackrate_arr = array();
						
						if(count($taxresult) > 0 )
						{
							foreach($taxresult as $data)
							{
								$taxinfo_arr[$data['taxunkid']]['taxunkid']=$data['taxunkid'];
								$taxinfo_arr[$data['taxunkid']]['taxdate']=$data['taxdate'];
								$taxinfo_arr[$data['taxunkid']]['applyonrackrate']=$data['applyonrackrate'];
								$taxinfo_arr[$data['taxunkid']]['amount']=$data['amount'];
								$taxinfo_arr[$data['taxunkid']]['slab']=$data['slab'];
								$taxinfo_arr[$data['taxunkid']]['tax']=$data['tax'];
								$taxinfo_arr[$data['taxunkid']]['taxapplyafter']=$data['taxapplyafter'];
								$taxinfo_arr[$data['taxunkid']]['postingtype']=$data['postingtype'];
								$taxinfo_arr[$data['taxunkid']]['postingrule']=$data['postingrule'];
								
								array_push($apprackrate_arr,$data['applyonrackrate']);
							}//end foreach($taxrow as $data)
						}//end if(count($result->resultValue['list']) > 0 )
					}
				}	

				$exchangerateInfo = $this->chdao->getcurrencylist($this->databasename,$this->hotelcode);
				$base_currecny = 'USD';
				$base_exchangerate1 = 1;
				$base_exchangerate2 = 1;
									
				if(count($exchangerateInfo) > 0)
				{
					foreach($exchangerateInfo as $exrate)
					{
						$exchangerate1=($exrate['exchange_rate1']!='' || $exrate['exchange_rate1']!='0')?$exrate['exchange_rate1']:'1.0000';
						$exchangerate2=($exrate['exchange_rate2']!='' || $exrate['exchange_rate2']!='0')?$exrate['exchange_rate2']:'1.0000';
						
						if($exrate['isbasecurrency'] == 1)
						{
							$base_currecny = $exrate['currency_code'];								
							$base_exchangerate1 = $exchangerate1; 
							$base_exchangerate2 = $exchangerate2;
						}
					}
				}

				$res = json_decode($res_rec);

				foreach($res[0] as $rmdetail)	
				{
					$inv = 1;
					$stopsell = 1;
					$rate 	= 0;
					$exadultrate = 0;
					$discount = 0;
					
					$rate_string = array();
					$rate_string['date'] = '';		
					$rate_string['rate'] = '';
					$rate_date = array(); 
					$rate_adult_date = array(); 
					
					$taxincexcflag = 0;
					if(isset($rmdetail->ShowTaxInclusiveExclusiveSettings))
					{
						$taxincexcflag = ($rmdetail->ShowTaxInclusiveExclusiveSettings!='')?($rmdetail->ShowTaxInclusiveExclusiveSettings.""):'0';
					}
					$roomtypeunkid = $rmdetail->roomtypeunkid;
					$ratetypeunkid = $rmdetail->ratetypeunkid;
					$roomrateunkid = $rmdetail->roomrateunkid;
					$baserackrate  = $rmdetail->RackRate;
					$EArackrate  = $rmdetail->EARate;
					$ECrackrate  = $rmdetail->ECRate;
					
					$base_adult = $rmdetail->base_adult;
					$max_adult = $rmdetail->max_adult;
						
					for($i=1;$i<=$number_night;$i++)
					{
						$date = 'day_'.$i;
						$sdate = 'stopsell_'.$i;			
						$baserate = 'o_day_base_'.$i;	
						$extraadult = 'o_day_extra_adult_'.$i;		
						$extrachild = 'o_day_extra_child_'.$i;
						$dateid = 'dt_'.$i;
						
						if($rmdetail->$date == 0)
							$inv = 0;
			
						if($rmdetail->$sdate == 1)	
							$stopsell = 0;
							
						$rate =	$rate + $rmdetail->$baserate;
						$exadultrate = $exadultrate + $rmdetail->$extraadult."";
						
						$rate_string['date'] .= $rmdetail->$dateid.',';
						$rate_string['rate'] .= $rmdetail->$baserate."|".$rmdetail->$extraadult."|".$rmdetail->$extrachild.'<--->';
						
						$rate_date[$rmdetail->$dateid] = $rmdetail->$baserate;
						$rate_adult_date[$rmdetail->$dateid] = $rmdetail->$extraadult;
					}
					
					//$this->log->logIt("Values1 :- ".$rate."|".$exadultrate);
							
					$appDate = rtrim($rate_string['date'],',');
					$appRate = rtrim($rate_string['rate'],'<--->');

					$rackrate_arr[$roomrateunkid]['base']=$baserackrate;
					$rackrate_arr[$roomrateunkid]['extadult']=$EArackrate;
					$rackrate_arr[$roomrateunkid]['extchild']=$ECrackrate;

					if($parameterInfo['RoomRevenueTax'] != '')
					{
						if($taxincexcflag == 1){
							 $rateaftertax_arr = $this->chdao->getReverseTaxWithRackRateFromRatePlan_new($appliedTax,$base_exchangerate1."|".$base_exchangerate2,$taxinfo_arr,$appDate,$appRate,$rackrate_arr,$roomrateunkid);
						}
						else{
							$rateaftertax_arr = $this->getTaxWithRackRateFromRatePlan_new('N','S',$appliedTax,$base_exchangerate1."|".$base_exchangerate2,$taxinfo_arr,$appDate,$appRate,$rackrate_arr,$number_adult,$base_adult,$roomrateunkid);
						}
						#$this->log->logIt('Rate after taxes');
						#$this->log->logIt(print_r($rateaftertax_arr,true));
					}
					
					$total_tax = 0;
					$total_adult_tax = 0;
					
					$this->log->logIt(print_r($rate_date,true));
					$this->log->logIt(print_r($rateaftertax_arr,true));
					
					foreach($rate_date as $key=>$value)
					{
						if(isset($rateaftertax_arr[$key]))
						{
							$rateafter_tax = explode('|',$rateaftertax_arr[$key]);
							
							//$this->log->logIt("tax Value :- ".$rate_date[$key]."|".$rate_adult_date[$key]."|".$rateafter_tax[0]."|".$rateafter_tax[1]);
							
							if($taxincexcflag == 1){
								$total_tax += ($rate_date[$key]-$rateafter_tax[0]);
								$total_adult_tax += ($rate_adult_date[$key]-$rateafter_tax[1]);
							}
							else{
								$total_tax += ($rateafter_tax[0]-$rate_date[$key]);
								if(is_array($rateafter_tax) && isset($rateafter_tax[1]))
									$total_adult_tax += ($rateafter_tax[1]-$rate_adult_date[$key]);
								else
									$total_adult_tax += 0;
							}
						}
					}
					
					
					
					if($inv == 0)
						continue;
			
					if($stopsell == 0)
						continue;
					
					if($number_adult>$max_adult)
						continue;
					
					#$roomname = $rmdetail->display_name; 
					
					$room_id = '';
					$roomname = $rmdetail->display_name;
					$room_id = $rmdetail->roomtypeunkid;
					$amnty = '';
					
					/*if($room_id)
					{
						$resultnew = $this->getroom_amnities($room_id);
						$resamt = $resultnew->resultValue['record'];
						
						#print_r($resamt);
						$amnty = ($resamt['amenity'] != '')?$resamt['amenity']:'';
						#print_r($amnty);
					}*/	
					
					if(strtoupper($rmdetail->curr_code) != strtoupper($currency))
						$conversion = $this->currency(strtoupper($rmdetail->curr_code),strtoupper($currency),1);
					else	
						$conversion = 1;
						
					$roomarray[$roomname] =  array();
					$roomarray[$roomname]['url'] = $url;
					
					/*$rate = round($rate);
					$exadultrate = round($exadultrate);
					$total_tax = round($total_tax);
					$total_adult_tax = round($total_adult_tax);
					if($conversion > 0)
						$conversion = round($conversion);*/
					
					//$this->log->logIt("Values2 :- ".$rate."|".$exadultrate."|".$total_tax."|".$total_adult_tax."|".$conversion);
						
					//Net Rate Calculation	-	START
					if($taxincexcflag == 1 && $rate>$total_tax){
						//$this->log->logIt("Called");
						$roomarray[$roomname]['price'] = $rate - $total_tax;
					}
					else{
						$roomarray[$roomname]['price'] = $rate;
					}
					
					if($taxincexcflag == 1 && $exadultrate>$total_adult_tax){
						$exadultrate = $exadultrate - $total_adult_tax;
					}
					
					if($number_adult>$base_adult)
						$roomarray[$roomname]['price'] = $roomarray[$roomname]['price'] + (($number_adult-$base_adult)*$exadultrate);
																
					if($conversion > 0)
						$roomarray[$roomname]['price'] = ($roomarray[$roomname]['price']*$conversion);
						
					$roomarray[$roomname]['price'] = round($roomarray[$roomname]['price']);
					//Net Rate Calculation	-	END
					
					$roomarray[$roomname]['fees'] = 0;
					$roomarray[$roomname]['fees_at_checkout'] = 0;
					
					//Tax Rate Calculation	-	START
					if($number_adult>$base_adult)
						$roomarray[$roomname]['taxes'] = $total_tax + (($number_adult-$base_adult)*$total_adult_tax);
					else
						$roomarray[$roomname]['taxes'] = $total_tax;

					if($conversion > 0)
						$roomarray[$roomname]['taxes'] = ($roomarray[$roomname]['taxes']*$conversion);
						
					$roomarray[$roomname]['taxes'] = round($roomarray[$roomname]['taxes']);
					//Tax Rate Calculation	-	END
					
					$roomarray[$roomname]['taxes_at_checkout'] = 0;
					
					//Sell Rate Calculation	-	START
					if($taxincexcflag == 1)
						$rate = $rate;
					else
						$rate = $rate+$total_tax;
					
					if($taxincexcflag == 1 && $number_adult>$base_adult)
						$rate = $rate + (($number_adult-$base_adult)*$exadultrate);
					else if($taxincexcflag != 1 && $number_adult>$base_adult)
						$rate = $rate + (($number_adult-$base_adult)*$exadultrate) + (($number_adult-$base_adult)*$total_adult_tax);
						
					$roomarray[$roomname]['final_price'] = $rate;
					if($conversion > 0)
						$roomarray[$roomname]['final_price'] = ($rate*$conversion);
					
					$roomarray[$roomname]['final_price'] = round($roomarray[$roomname]['final_price']);
					//Sell Rate Calculation	-	END
					
					//$this->log->logIt("Rate->".round($rate*$conversion));
					//$this->log->logIt("Total Tax->".$roomarray[$roomname]['taxes']."|".$roomarray[$roomname]['price']."|".$roomarray[$roomname]['final_price']."|".$taxincexcflag);
					
					$no_room = 1;
					
					$roomarray[$roomname]['discounts'] = array();
					$roomarray[$roomname]['currency'] = strtoupper($currency);
					$roomarray[$roomname]['num_rooms'] = (int)$no_room;
					$roomarray[$roomname]['room_code'] = '';
					
					if($amnty)
						$roomarray[$roomname]['room_amenities'] = explode(',',$amnty);
					else	
						$roomarray[$roomname]['room_amenities'] = array();
					
					//~ $roomarray[$roomname]['room_amenities'] = $result['amenity'];
				}	
			}
			#exec($res);			
			#print_r($roomarray);
			#$this->log->logIt("Room array :- ".$roomarray);
			
			return $roomarray;			
			exit(0);
			
		} catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - GetRoomdetail - " . $e);
            $this->handleException($e);
        }
	}		


/************************** New Code start here GetRecordset_new *************************************/


    public function GetRecordset_new($detail)  
	{
        try {
            $this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "GetRoomdetail");
			#$this->log->logIt(print_r($detail,true));
			$start_date = $detail['start_date'];
			$end_date = $detail['end_date'];

			$this->chdao = new channelupdatedao();
			$this->chdao->log =  new logger("tripadvisor_integration");

			//~ print_r($detail['party_details']);
			//~ exit;

			$number_night = util::DateDiff($start_date,$end_date);

			$number_adult = (int)$detail['num_adults'];			
			
			$number_child = 0;
			if(isset($detail['num_children']))
				$number_child = (int)$detail['num_children'];

			$num_rooms = 1;			
			if(isset($detail['num_rooms']))
				$num_rooms = (int)trim($detail['num_rooms']);

			$currency = $detail['currency'];

			$result =  $this->getHotelDetail();
			$res = $result->resultValue['record'];

		//~ $hotel_img  = 'http://saas.s3.amazonaws.com/uploads/1041_20141022101134_0844549001413952894_288_image006.jpg';

		$hotel_img  = 'http://saas.s3.amazonaws.com/uploads/';
	
		if($res['image'])
			$hotel_img = $hotel_img.''.$res['image'];

		if($res['country'] != '') $hot_country = $res['country']; else $hot_country = 'IND';
		if(isset($res['checkinpolicy'])) $policy = $res['checkinpolicy']; else $policy = 'no policy defined';
			$basic_hotel = array(
				"name"=>$res['name'],
				"address1"=>$res['address1'],
				"city"=>$res['city'],
				"state"=>$res['state'],
				"postal_code"=>$res['zipcode'],
				"country"=>$hot_country,
				"latitude"=>(int)$res['latitude'],
				"longitude"=>(int)$res['longitude'],
				"phone"=>$res['phone'],
				"url"=>$res['website'],
				"amenities"=>explode(',',$res['amenity']),
				"photos"=>array(array('url'=> $hotel_img,
					'caption'=>'',
					'width'=>150,
					'height'=>150
				)),
			    "checkinout_policy"=> $policy
				//~ "extra_fields"=>array(array('id'=>'',
					//~ 'description'=>'',
					//~ 'required'=>false,
					//~ 'type'=>''
				//~ ))
				//~ "accepted_credit_cards"	=>''

			);

			//~ echo $this->databasename,'<br><br><br>';	
			//~ print_r($res);
			
			#http://192.168.20.65/booking/book-rooms-1001  local
			#live live.ipms247.com
			#"http://".$_SERVER["HTTP_HOST"]."/saas/test/testplugin.html";
			#http://192.168.20.65/booking/rmdetails

			preg_match ("/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/", $start_date, $datePart); //Pinal - 24 December 2019 - Purpose : PHP5.6 to 7.2 [deprecatedFunctions]
			$out_date=date(staticarray::$webcalformat_key[$res['caldateformat']],mktime(0,0,0,$datePart[2],$datePart[3],$datePart[1]));

			if($res['newurl'] == 0)	
			{
				#$url = "http://192.168.20.65/booking/book-rooms-".$this->hotelcode;
				$hotelId = $this->hotelcode;
			}	
			else
			{
				#$url = "http://192.168.20.65/booking/book-rooms-".$res['localfolder'];
				$hotelId = $res['localfolder'];
			}	

			$roomurl = "http://".$this->hostname."/booking/rmdetails";
			//~ $roomurl = "http://107.21.244.5/booking/rmdetails";
			
		    $url = "http://".$this->hostname."/booking/searchroom.php?HotelId=".$hotelId."&checkin=".$out_date."&nonights=".$number_night."&calendarDateFormat=".$res['caldateformat']."&adults=".$number_adult."&child=".$number_child."&rooms=".$num_rooms."&ShowMinNightsMatchedRatePlan=false";
//~ echo $url;
//~ checkin=2014-25-10&gridcolumn=13&adults=1&child=0&nonights=2&ShowSelectedNights=true&DefaultSelectedNights=2&calendarDateFormat=yy-dd-mm&rooms=1&promotion=&ArrvalDt=2014-10-25&HotelId=7&isLogin=lf&selectedLang=&modifysearch=false&mulroomtypebooking=0&layoutView=0&ShowMinNightsMatchedRatePlan=false&LayoutTheme=1

			$available = (array)$this->check_room_availability($roomurl,$out_date,$hotelId,$number_night,$res['caldateformat'],$detail['party_details']);
			if(!in_array(-1,$available))
			{
				$this->log->logIt("Request URL :- ".$roomurl);

				$data = array("checkin"=>$out_date,
							  "HotelId"=>$hotelId,
							  "nonights"=>$number_night,
							  "calendarDateFormat"=>$res['caldateformat'],
							  "adults"=>$number_adult,
							  "child" =>$number_child,
							  "rooms" =>$num_rooms,
							  "gridcolumn" => $number_night,
							  "modifysearch" => "false"
							 );

				$this->log->logIt("Request Data :- ".print_r($data,true));

				$ch=curl_init();
				curl_setopt($ch, CURLOPT_URL, $roomurl);
				curl_setopt($ch, CURLOPT_POST, TRUE);
				curl_setopt($ch, CURLOPT_POSTFIELDS,  $data);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				 
				$res='';
				$res=curl_exec($ch);
				$curlinfo = curl_getinfo($ch);
				
				//~ echo $res;
				
				//~ print $res;

				$roomarray = array();
				$res = preg_replace('/(\s\s+|\t|\n)/', ' ',$res);
				
				//~ echo '<pre>',$res,'</pre>';exit;
						
				if(preg_match('/var resgrid=(.*?); resgrid/',$res,$matches))
				{	
					$res_rec = $matches[1];
					
					//~ print_r($res_rec);	echo '<br><br><br><br>';
					$parameterArr = $this->chdao->getcfParameter($this->databasename,$this->hotelcode);
					$parameterInfo=array();
					foreach($parameterArr as $paramArr)
					{
						$parameterInfo[$paramArr['keyname']]=$paramArr['keyvalue'];
					}
					
					//~ print_r($parameterInfo);
					
					if($parameterInfo['RoomRevenueTax'] != '')
					{
						$appliedTax = $parameterInfo['RoomRevenueTax'];
						
						//Take Tax Details - START
						$taxid=explode(',',$appliedTax);
						$tax_string = implode("','",$taxid);

						$taxinfo_arr=array();

						if(count($taxid) > 0)
						{
							$taxresult = $this->chdao->getTaxDetail($this->databasename,$this->hotelcode,$tax_string);
							
							$apprackrate_arr = array();
							
							if(count($taxresult) > 0 )
							{
								foreach($taxresult as $data)
								{
									$taxinfo_arr[$data['taxunkid']]['taxunkid']=$data['taxunkid'];
									$taxinfo_arr[$data['taxunkid']]['taxdate']=$data['taxdate'];
									$taxinfo_arr[$data['taxunkid']]['applyonrackrate']=$data['applyonrackrate'];
									$taxinfo_arr[$data['taxunkid']]['amount']=$data['amount'];
									$taxinfo_arr[$data['taxunkid']]['slab']=$data['slab'];
									$taxinfo_arr[$data['taxunkid']]['tax']=$data['tax'];
									$taxinfo_arr[$data['taxunkid']]['taxapplyafter']=$data['taxapplyafter'];
									$taxinfo_arr[$data['taxunkid']]['postingtype']=$data['postingtype'];
									$taxinfo_arr[$data['taxunkid']]['postingrule']=$data['postingrule'];
									
									array_push($apprackrate_arr,$data['applyonrackrate']);
								}//end foreach($taxrow as $data)
							}//end if(count($result->resultValue['list']) > 0 )
						}
					}	

					$exchangerateInfo = $this->chdao->getcurrencylist($this->databasename,$this->hotelcode);
					$base_currecny = 'USD';
					$base_exchangerate1 = 1;
					$base_exchangerate2 = 1;
										
					if(count($exchangerateInfo) > 0)
					{
						foreach($exchangerateInfo as $exrate)
						{
							$exchangerate1=($exrate['exchange_rate1']!='' || $exrate['exchange_rate1']!='0')?$exrate['exchange_rate1']:'1.0000';
							$exchangerate2=($exrate['exchange_rate2']!='' || $exrate['exchange_rate2']!='0')?$exrate['exchange_rate2']:'1.0000';
							
							if($exrate['isbasecurrency'] == 1)
							{
								$base_currecny = $exrate['currency_code'];								
								$base_exchangerate1 = $exchangerate1; 
								$base_exchangerate2 = $exchangerate2;
							}
						}
					}

					$res = json_decode($res_rec);
					$r = 0;
					//~ echo count($res[0]);
					foreach($res[0] as $rmdetail)
					{
						$inv = 1;
						$stopsell = 1;
						$rate 	= 0;
						$exadultrate = 0;
						$discount = 0;
						
						$rate_string = array();
						$rate_string['date'] = '';		
						$rate_string['rate'] = '';
						$rate_date = array(); 
						$rate_adult_date = array(); 
						
						$taxincexcflag = 0;
						if(isset($rmdetail->ShowTaxInclusiveExclusiveSettings))
						{
							$taxincexcflag = ($rmdetail->ShowTaxInclusiveExclusiveSettings!='')?($rmdetail->ShowTaxInclusiveExclusiveSettings.""):'0';
						}
						$roomtypeunkid = $rmdetail->roomtypeunkid;
						$ratetypeunkid = $rmdetail->ratetypeunkid;
						$roomrateunkid = $rmdetail->roomrateunkid;
						$baserackrate  = $rmdetail->RackRate;
						$refund 	   = $rmdetail->is_prepaid_noncancel_nonrefundabel;
						$room_desc	   = $rmdetail->webdescription;
						$hotel_code_r  = $rmdetail->hotel_code;
						$roomtype	   = $rmdetail->roomtype;
					/* for capture image property*/	
						$src = '';
						$width=0;
						$height=0;
						//~ $roomimg = "<img src='http:\/\/saas.s3.amazonaws.com\/uploads\/26_20120530045933_0934483001338353973_13_1.jpg' width='100px' height='100px'>";
						if(isset($rmdetail->roomimg))
						{
							$roomimg	   = $rmdetail->roomimg;

$string = <<<XML
<x>
 $roomimg</img>
</x> 
XML;

							$xml = simplexml_load_string($string);
							foreach($xml->img[0]->attributes() as $a => $b) {
								${$a} = stripslashes($b);
							}
						}	
				/* End for capture image property*/					
						
/* Need to change accoedint to condition **
 * 
 * Must be one of:

none: indicates no refund provided if cancelled.
partial: indicates after time of booking there is a charge less than the total reservation amount required for cancellation. The difference is then refunded.
full: indicates there exists a time between time of booking and time of arrival where the reservation may be cancelled without any charge to the user. Reservations with free cancellation expiring within N days of time of arrival may still be marked fully refundable unless time of booking is within N days of time of arrival.
						
**/
						$refundstr = '';
						$canstr = '';
						if($refund == 0)
						{
							$refundstr = 'partial';	
							$canstr = 'Cancellation available';
						}	
						else
						{
							$refundstr = 'None';
							$canstr = 'No cancelation';
						}
						
						$EArackrate	  = $rmdetail->EARate;
						$ECrackrate  = $rmdetail->ECRate;
						
						$base_adult = $rmdetail->base_adult;
						$max_adult = $rmdetail->max_adult;
						
						for($i=1;$i<=$number_night;$i++)
						{
							$date = 'day_'.$i;
							$sdate = 'stopsell_'.$i;			
							$baserate = 'o_day_base_'.$i;	
							$extraadult = 'o_day_extra_adult_'.$i;		
							$extrachild = 'o_day_extra_child_'.$i;
							$dateid = 'dt_'.$i;
							
							if($rmdetail->$date == 0)
								$inv = 0;
				//~ echo $inv,'<br>';
							if($rmdetail->$sdate == 1)	
								$stopsell = 0;
								
							$rate =	$rate + $rmdetail->$baserate;
							$exadultrate = $exadultrate + $rmdetail->$extraadult."";
							
							$rate_string['date'] .= $rmdetail->$dateid.',';
							$rate_string['rate'] .= $rmdetail->$baserate."|".$rmdetail->$extraadult."|".$rmdetail->$extrachild.'<--->';
							
							$rate_date[$rmdetail->$dateid] = $rmdetail->$baserate;
							$rate_adult_date[$rmdetail->$dateid] = $rmdetail->$extraadult;
						}
						
						//$this->log->logIt("Values1 :- ".$rate."|".$exadultrate);
								
						$appDate = rtrim($rate_string['date'],',');
						$appRate = rtrim($rate_string['rate'],'<--->');

						$rackrate_arr[$roomrateunkid]['base']=$baserackrate;
						$rackrate_arr[$roomrateunkid]['extadult']=$EArackrate;
						$rackrate_arr[$roomrateunkid]['extchild']=$ECrackrate;

						if($parameterInfo['RoomRevenueTax'] != '')
						{
							if($taxincexcflag == 1){
								 $rateaftertax_arr = $this->chdao->getReverseTaxWithRackRateFromRatePlan_new($appliedTax,$base_exchangerate1."|".$base_exchangerate2,$taxinfo_arr,$appDate,$appRate,$rackrate_arr,$roomrateunkid);
							}
							else{
								$rateaftertax_arr = $this->getTaxWithRackRateFromRatePlan_new('N','S',$appliedTax,$base_exchangerate1."|".$base_exchangerate2,$taxinfo_arr,$appDate,$appRate,$rackrate_arr,$number_adult,$base_adult,$roomrateunkid);
							}
							#$this->log->logIt('Rate after taxes');
							#$this->log->logIt(print_r($rateaftertax_arr,true));
						}
						
						$total_tax = 0;
						$total_adult_tax = 0;
						
						//$this->log->logIt(print_r($rate_date,true));
						//~ $this->log->logIt(print_r($rateaftertax_arr,true));

						foreach($rate_date as $key=>$value)
						{
							if(isset($rateaftertax_arr[$key]))
							{
								$rateafter_tax = explode('|',$rateaftertax_arr[$key]);
								
								//$this->log->logIt("tax Value :- ".$rate_date[$key]."|".$rate_adult_date[$key]."|".$rateafter_tax[0]."|".$rateafter_tax[1]);
								
								if($taxincexcflag == 1){
									$total_tax += ($rate_date[$key]-$rateafter_tax[0]);
									$total_adult_tax += ($rate_adult_date[$key]-$rateafter_tax[1]);
								}
								else{
									$total_tax += ($rateafter_tax[0]-$rate_date[$key]);
									if(!isset($rateafter_tax[1])) $rateafter_tax[1] = 0;
									$total_adult_tax += ($rateafter_tax[1]-$rate_adult_date[$key]);
								}
							}
						}
						
						//~ echo $inv,'--',$stopsell,'---',$number_adult,'>',$max_adult,'<br>';
						
						if($inv == 0)
							continue;
				
						if($stopsell == 0)
							continue;
						
						if($number_adult>$max_adult)
							continue;

						#$roomname = $rmdetail->display_name; 
						
						$room_id = '';
						$roomname = $rmdetail->display_name;
						$room_id = $rmdetail->roomtypeunkid;
						$amnty = '';
						//~ echo $room_id,'<br>';
						
						
						if($room_id)
						{
							$resultnew = $this->getroom_amnities($room_id);
							$resamt = $resultnew->resultValue['record'];
							
							#print_r($resamt);
							$amnty = ($resamt['amenity'] != '')?$resamt['amenity']:'';
							#print_r($amnty);
						}	

						
						if(strtoupper($rmdetail->curr_code) != strtoupper($currency))
							$conversion = $this->currency(strtoupper($rmdetail->curr_code),strtoupper($currency),1);
						else	
							$conversion = 1;
							
						//~ $roomarray['room_types_array'] =  array();
						//~ $roomarray[$roomname]['url'] = $url;
						//~ echo $r,'----',$roomname,'<br>';	

						//~ $roomarray['room_types_array']['name'] = $roomname;
						
							
						//Net Rate Calculation	-	START
						if($taxincexcflag == 1 && $rate>$total_tax){
							//$this->log->logIt("Called");
							$roomarray[$roomname]['price'] = $rate - $total_tax;
						}
						else{
							$roomarray[$roomname]['price'] = $rate;
						}
						
						if($taxincexcflag == 1 && $exadultrate>$total_adult_tax){
							$exadultrate = $exadultrate - $total_adult_tax;
						}
						
						if($number_adult>$base_adult)
							$roomarray[$roomname]['price'] = $roomarray[$roomname]['price'] + (($number_adult-$base_adult)*$exadultrate);
																	
						if($conversion > 0)
							$roomarray[$roomname]['price'] = ($roomarray[$roomname]['price']*$conversion);
							
						$roomarray[$roomname]['price'] = round($roomarray[$roomname]['price']);
						//Net Rate Calculation	-	END
						
						$roomarray[$roomname]['fees'] = 0;

						//Tax Rate Calculation	-	START
						if($number_adult>$base_adult)
							$roomarray[$roomname]['taxes'] = $total_tax + (($number_adult-$base_adult)*$total_adult_tax);
						else
							$roomarray[$roomname]['taxes'] = $total_tax;

						if($conversion > 0)
							$roomarray[$roomname]['taxes'] = ($roomarray[$roomname]['taxes']*$conversion);
							
						$roomarray[$roomname]['taxes'] = round($roomarray[$roomname]['taxes']);
						//Tax Rate Calculation	-	END
						
						$roomarray[$roomname]['taxes_at_checkout'] = 0;
						
						//Sell Rate Calculation	-	START
						if($taxincexcflag == 1)
							$rate = $rate;
						else
							$rate = $rate+$total_tax;
						
						if($taxincexcflag == 1 && $number_adult>$base_adult)
							$rate = $rate + (($number_adult-$base_adult)*$exadultrate);
						else if($taxincexcflag != 1 && $number_adult>$base_adult)
							$rate = $rate + (($number_adult-$base_adult)*$exadultrate) + (($number_adult-$base_adult)*$total_adult_tax);
							
						$roomprice = $rate;
						if($conversion > 0)
							$roomprice = ($rate*$conversion);
						
						$roomprice = round($roomprice);
						//Sell Rate Calculation	-	END

						//~ $roomarray['room_types_array']['final_price_at_checkout']['amount'] = 0;
						$final_price_at_checkout = 0;

						
						//$this->log->logIt("Rate->".round($rate*$conversion));
						//$this->log->logIt("Total Tax->".$roomarray[$roomname]['taxes']."|".$roomarray[$roomname]['price']."|".$roomarray[$roomname]['final_price']."|".$taxincexcflag);
						
						$no_room = 1;
						
						$roomarray[$roomname]['discounts'] = array();
						$currency = strtoupper($currency);
						//~ $roomarray['room_types_array']['final_price_at_checkout']['currency'] = strtoupper($currency);
						$roomarray[$roomname]['num_rooms'] = (int)$no_room;
						$roomarray[$roomname]['room_code'] = $roomtype;
						
						
						//~ $amnty = "BREAKFAST_AND_LUNCH_INCLUDED,ROOM_WITH_A_VIEW";
						
						$amenities = array();
						if($amnty)
							$amenities = explode(',',$amnty);
						
						//~ if($amnty)
							//~ $roomarray[$roomname]['amenities'] = explode(',',$amnty);
						//~ else	
							//~ $roomarray[$roomname]['amenities'] = array();
						if($src == '') $src ='http://saas.s3.amazonaws.com/uploads/';
						$roomarray['room_types_array'][$r] = array(
							'name'=>$roomname,
							'final_price_at_booking'=>array('amount'=>$rate,'currency'=>$currency),
							'final_price_at_checkout'=>array('amount'=>$final_price_at_checkout,'currency'=>$currency),
							'description'=>$room_desc,
							'partner_data'=>array('hotel_code'=>$hotel_code_r,'room_code'=>$roomrateunkid,'rate'=>$rate),
							'line_items'=>array(array('price'=>array('amount'=>$rate,'currency'=>$currency),'type'=>'fee','paid_at_checkout'=>false,'description'=>'')),
							'amenities'=>$amenities,
							'photos'=>array(array('url'=>$src,'caption'=>'','width'=>(int)$width,'height'=>(int)$height)),
							'refundable'=>$refundstr,
							'cancellation_policy'=>$canstr
						);

					++$r;	
										
					}
					//~ print_r($roomarray['room_types_array']);	
				}
			}	
			//~ exec($res);			
			//~ print_r($roomarray);
			#$this->log->logIt("Room array :- ".$roomarray);
			$roomarray['hotel_details'] = $basic_hotel;
			//~ print_r($roomarray);	
			//~ return;
			return $roomarray;
			exit(0);
			
		} catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - GetRoomdetail - " . $e);
            $this->handleException($e);
        }
	}		



/************************** New Code end here *************************************/
	
/************************** New Code for Insert Transaction start here *************************************/

public function insert_trans($param)
{
	try {
			$ObjMasterDao=new masterdao();
			$ObjBookingDao=new bookingdao();
			$ObjRoomTypeDao=new roomtypedao();
			
			$this->log->logIt(' ------------------ insert_trans --------------------');
			$resp_data = array();
			$room_arr = array();
			
			//~ $this->chdao = new channelupdatedao();
			$iserror=0;
			$hotel_code			= $param['hotel_code'];
			$this->hotelcode 	= $hotel_code;
			
			$roomrateunkid  	= (int)$param['room_code'];
			$subno				= '';
			$subreservationno	= 1;
			$mastertranunkid 	= -1;
			$checkin_date 		= $param['checkin_date'];
			$checkout_date 		= $param['checkout_date'];
			
			
			//~ echo $this->databasename,'---->',$hotel_code,'----->',$roomrateunkid,'<br>';

			$result = $this->get_roomtypeid($roomrateunkid,$hotel_code);
			
			//~ print_r($result); exit;
			
			$rec = $result->resultValue['record'];
			$tot = $result->resultValue['total'];
			//~ print_r($result);exit;
			$roomtypeunkid 	= 0;
			$ratetypeunkid 	= 0;
			$extraadultrate = 0;
			$extrachildrate = 0;	

			if($tot > 0)
			{
				$roomtypeunkid 	= $rec['roomtypeunkid'];
				$ratetypeunkid 	= $rec['ratetypeunkid'];
				$extraadultrate = $rec['extraadultrate'];
				$extrachildrate = $rec['extrachildrate'];
			}	
			
			
			$_SESSION['prefix'] = 'hotel';
			$_SESSION[$_SESSION['prefix']]['hotel_code'] = $this->hotelcode;
			
			$userunkids = $this->getUserid_web();
			$_SESSION[$_SESSION['prefix']]['loginuserunkid'] = $userunkids->resultValue['record']['userunkid'];
			$userunkid = $userunkids->resultValue['record']['userunkid'];

			$inv_mode = $this->checkInv_mode($hotel_code);
			$errorcode = array();
			if($inv_mode->resultValue['record']['roominventory'] == 'REGULAR')
			{		
				# Check Nagative Inventory - start 					
				$isnegative=$ObjMasterDao->isNegativeInventoryForDateRange($roomrateunkid,$checkin_date,$checkout_date);
				if($isnegative)
				{
					$this->log->logIt('in negative ...');
						$neginventory=1;
						//~ break;
				}
				# Check Nagative Inventory - end 				
			}#LIVE inventory case

			$source = $this->getResourceId($hotel_code);
			
			$rec_source = $source->resultValue['record'];
			$tot_source = $source->resultValue['total'];
			
			$sourceid = 0;
			if($tot_source > 0)
				$sourceid = $rec_source['contactunkid'];
				
			//~ echo $sourceid;
			
			$rooms = count($param['party_adults']);
		$this->log->logIt('Number of rooms ------> '.$rooms);
		//~ exit;
	
		for($nor = 0;$nor < $rooms; $nor++)	  // number of Rooms
		{
			$this->log->logIt('room array --- room no. ---> '.$nor);
			if(isset($param['party_children_age'][$nor]))
			{
				if($param['party_children_age'][$nor] == '')
					$param['party_children_age'][$nor] = "''";
			}
		
			if($rooms > 1)
				$subno=$subreservationno;
			else
				$subno=NULL;	
			

/*
echo ("
<br>---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------\n
			 |     hotel_code ---> $hotel_code     |     title ---> NULL     |     guestname --->".$param['cus_first_name']." ".$param['cus_last_name']."
			 |     gender ---> NULL     |     address ---> ".$param['pay_bill_address1']."     |     country ---> ".$param['pay_bill_country']."
			 |     state ---> ".$param['pay_bill_state']."     |     city ---> ".$param['pay_bill_city']."     |      postalcode ---> ".$param['pay_bill_postal_code']."
			 |     phone ---> ".$param['cus_phone_number']."     |     mobile ---> ".$param['cus_phone_number']."     |     fax ---> NULL      |     email--->".$param['cus_email']."	
			 |     pickupvehicle ---> ''|     pickupdatetime ---> NULL      |     pickupmodeunkid --->NULL      |     arrivaldate --->".$param['checkin_date']."	
			 |     pickupvehicle ---> ''|     pickupdatetime ---> NULL      |     pickupmodeunkid --->NULL      |     arrivaldate --->".$param['checkin_date']."	
			 |     deptdate ---> ".$param['checkout_date']." |     sourceunkid --->NULL     |     roomtypeunkid--->".$roomtypeunkid."     |     ratetypeunkid ---> ".$ratetypeunkid."
			 |     roomid--->''     		|     adult ---> ".$param['party_adults']."     |     child ---> ".$param['party_children']."     |     childage ---> ".$param['party_children_age']."
			 |     discountedrate ---> 0      |     baserate".$param['rate']."     |     extradultrate".$extraadultrate."     |     extrachildrate ---> ".$extrachildrate."	
			 |     SpRequest ---> ''     |     chargeunkid ---> ''     |     chargerate --->''     |     chargepostingrule ---> ''      |     chargerule ---> ''     |     quantity ---> 0
			 |     chadult ---> 0        |     chchild ---> 0     |     chargecount ---> 0     |     isnewguest ---> 1      |     guestunkid ---> ''
			 |     resguaid ---> 0       |     mastertranunkid ---> -1      |     subreservationno ---> ".$subno."      |     contacttype ---> WEB      |     group_contactunkid ---> --->1
			 |     keepinfo ---> 1       |     settalementtype---> CASH      |     settlementtypeunkid---> ''      |     total_inc_charge ---> 0      |     identitytype ---> ''
			 |     identityno ---> ''    |     identityexpdate ---> ''     |     nationality ---> ".$param['cus_country']."     |     dob ---> ''      |     isspecial ---> ''      |     lang_key ---> en
			 |     packagename ---> ''   |     packagedesc ---> ''     |     bookingfrom ---> DESKTOP      |     promoname ---> ''     |      promodesc ---> ''      |     specialunkid ---> ''
			 |     promotionunkid ---> ''|     preference ---> ''     |     preferencecnt ---> ''     |     webpaymentoption ---> ''     |     affiliateunkid ---> '' </br>
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------");

$this->log->logIt("
\n---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------\n
			 |     hotel_code ---> $hotel_code     |     title ---> NULL     |     guestname --->".$param['cus_first_name']." ".$param['cus_last_name']."
			 |     gender ---> NULL     |     address ---> ".$param['pay_bill_address1']."     |     country ---> ".$param['pay_bill_country']."
			 |     state ---> ".$param['pay_bill_state']."     |     city ---> ".$param['pay_bill_city']."     |      postalcode ---> ".$param['pay_bill_postal_code']."
			 |     phone ---> ".$param['cus_phone_number']."     |     mobile ---> ".$param['cus_phone_number']."     |     fax ---> NULL      |     email--->".$param['cus_email']."	
			 |     pickupvehicle ---> ''|     pickupdatetime ---> NULL      |     pickupmodeunkid --->NULL      |     arrivaldate --->".$param['checkin_date']."	
			 |     deptdate ---> ".$param['checkout_date']." |     sourceunkid --->NULL     |     roomtypeunkid--->".$roomtypeunkid."     |     ratetypeunkid ---> ".$ratetypeunkid."
			 |     roomid--->''     		|     adult ---> ".$param['party_adults'][$nor]."     |     child ---> ".$param['party_children'][$nor]."     |     childage ---> ".$param['party_children_age'][$nor]."
			 |     discountedrate ---> 0      |     baserate".$param['rate']."     |     extradultrate".$extraadultrate."     |     extrachildrate ---> ".$extrachildrate."	
			 |     SpRequest ---> ''     |     chargeunkid ---> ''     |     chargerate --->''     |     chargepostingrule ---> ''      |     chargerule ---> ''     |     quantity ---> 0
			 |     chadult ---> 0        |     chchild ---> 0     |     chargecount ---> 0     |     isnewguest ---> 1      |     guestunkid ---> ''
			 |     resguaid ---> 0       |     mastertranunkid ---> -1      |     subreservationno ---> ".$subno."      |     contacttype ---> WEB      |     group_contactunkid ---> --->1
			 |     keepinfo ---> 1       |     settalementtype---> CASH      |     settlementtypeunkid---> ''      |     total_inc_charge ---> 0      |     identitytype ---> ''
			 |     identityno ---> ''    |     identityexpdate ---> ''     |     nationality ---> ".$param['cus_country']."     |     dob ---> ''      |     isspecial ---> ''      |     lang_key ---> en
			 |     packagename ---> ''   |     packagedesc ---> ''     |     bookingfrom ---> DESKTOP      |     promoname ---> ''     |      promodesc ---> ''      |     specialunkid ---> ''
			 |     promotionunkid ---> ''|     preference ---> ''     |     preferencecnt ---> ''     |     webpaymentoption ---> ''     |     affiliateunkid ---> '' \n
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------");
*/
			$dao = new dao();
			//~ $strSql="CALL    `".$this->databasename."`.webcheckin(:hotel_code,:title,:guestname,        :address,:country,:state,:city,:postalcode,:phone,:mobile,:fax,:email,:pickupvehicle,:pickupdatetime,:pickupmodeunkid,:arrivaldate,:deptdate,:sourceunkid,:roomtypeunkid,:ratetypeunkid,:roomid,:adult,:child,                          :baserate,:extradultrate,:extrachildrate,:SpRequest,:chargeunkid,:chargerate,:chargepostingrule,:chargerule,:quantity,                  :chargecount,:isnewguest,:guestunkid,:resguaid,:mastertranunkid,:subreservationno,:contacttype,:group_contactunkid,:keepinfo,:settalementtype,:settlementtypeunkid,:total_inc_charge,:channelhotelunkid,:last_encrypted_creditcard,:travelagentunkid,:voucherno,:channeltax,:totalamount,                                                                                                                                      @contactunkid,@tranunkid,@retstatus,@errorcode,@resno,@v_retstatus,@loginuserunkid,@groupunkid,@foliodetailunkid)";
			
			$strSql="CALL `".$this->databasename."`.sp_webcheckin(:hotel_code,:title,:guestname,:gender,:address,:country,:state,:city,:postalcode,:phone,:mobile,:fax,:email,:pickupvehicle,:pickupdatetime,:pickupmodeunkid,:arrivaldate,:deptdate,:sourceunkid,:roomtypeunkid,:ratetypeunkid,:roomid,:adult,:child,:childage,:discountedrate,:baserate,:extradultrate,:extrachildrate,:SpRequest,:chargeunkid,:chargerate,:chargepostingrule,:chargerule,:quantity,:chadult,:chchild,:chargecount,:isnewguest,:guestunkid,:resguaid,:mastertranunkid,:subreservationno,:contacttype,:group_contactunkid,:keepinfo,:settalementtype,:settlementtypeunkid,:total_inc_charge,:identitytype,:identityno,:identityexpdate,:nationality,:dob,:isspecial,:lang_key,:packagename,:packagedesc,:bookingfrom,:promoname,:promodesc,:specialunkid,:promotionunkid,:preference,:preferencecnt,:webpaymentoption,:affiliateunkid,@contactunkid,@tranunkid,@retstatus,@errorcode,@resno,@v_retstatus,@loginuserunkid,@groupunkid,@v_foliodetailunkid,@v_error_chk,@v_totalpaybleamt)";

/*
			echo '<br><br>',("CALL sp_webcheckin(".$hotel_code.",NULL,'".$param['cus_first_name'].' '.$param['cus_last_name']."',NULL,'".$param['pay_bill_address1']."','".$param['pay_bill_country']."','".$param['pay_bill_state']."','".$param['pay_bill_city']."','".$param['pay_bill_postal_code']."','".$param['cus_phone_number']."','
							 ".$param['cus_phone_number']."',NULL,'".$param['cus_email']."','',NULL,NULL,'".$param['checkin_date']."','".$param['checkout_date']."',".$sourceid.",".$roomtypeunkid.",".$ratetypeunkid.",'',".$param['party_adults'].",".$param['party_children'].",".$param['party_children_age'].",0,".$param['rate'].",
							 ".$extraadultrate.",".$extrachildrate.",'','',0,'','',0,0,0,0,1,'',0,-1,".$subno.",'WEB',-1,0,'CASH','',0,'','','','".$param['cus_country']."','','','en','','','DESKTOP','','','','','','',0,'',@contactunkid,@tranunkid,@retstatus,@errorcode,@resno,@v_retstatus,@loginuserunkid,@groupunkid,@v_foliodetailunkid,@v_error_chk,@v_totalpaybleamt)");


			$this->log->logIt("CALL sp_webcheckin(".$hotel_code.",NULL,'".$param['cus_first_name'].' '.$param['cus_last_name']."',NULL,'".$param['pay_bill_address1']."','".$param['pay_bill_country']."','".$param['pay_bill_state']."','".$param['pay_bill_city']."','".$param['pay_bill_postal_code']."','".$param['cus_phone_number']."','
							 ".$param['cus_phone_number']."',NULL,'".$param['cus_email']."','',NULL,NULL,'".$param['checkin_date']."','".$param['checkout_date']."',".$sourceid.",".$roomtypeunkid.",".$ratetypeunkid.",'',".$param['party_adults'][$nor].",".$param['party_children'][$nor].",".$param['party_children_age'][$nor].",0,".$param['rate'].",
							 ".$extraadultrate.",".$extrachildrate.",'','',0,'','',0,0,0,0,1,'',0,'-1',".$subno.",'WEB','',1,'CASH','',0,'','','','".$param['cus_country']."','','','en','','','DESKTOP','','','','','','',0,'',@contactunkid,@tranunkid,@retstatus,@errorcode,@resno,@v_retstatus,@loginuserunkid,@groupunkid,@v_foliodetailunkid,@v_error_chk,@v_totalpaybleamt)");
//~ echo '<br><br>end here';
//~ exit;
*/

			$dao->initCommand($strSql);
			$dao->addParameter(":hotel_code",$hotel_code);
			$dao->addParameter(":title",NULL);
			
			if($param['traveler_first_name'][$nor]!='' && $param['traveler_last_name'][$nor]!='')
				$dao->addParameter(":guestname",$param['traveler_first_name'][$nor].' '.$param['traveler_last_name'][$nor]);
			else
				$dao->addParameter(":guestname",$param['cus_first_name'].' '.$param['cus_last_name']);
			
			$dao->addParameter(":gender",NULL);
			$dao->addParameter(":address",$param['pay_bill_address1']!=''?$param['pay_bill_address1']:NULL);
			
			$dao->addParameter(":country",$param['pay_bill_country']!=''?$param['pay_bill_country']:NULL);
			$dao->addParameter(":state",$param['pay_bill_state']!=''?$param['pay_bill_state']:NULL);
			$dao->addParameter(":city",$param['pay_bill_city']!=''?$param['pay_bill_city']:NULL);
			$dao->addParameter(":postalcode",$param['pay_bill_postal_code']!=''?$param['pay_bill_postal_code']:NULL);
			$dao->addParameter(":phone",$param['cus_phone_number']!=''?$param['cus_phone_number']:NULL);
			$dao->addParameter(":mobile",$param['cus_phone_number']!=''?$param['cus_phone_number']:NULL);
			$dao->addParameter(":fax",NULL);	
			$dao->addParameter(":email",$param['cus_email']!=''?$param['cus_email']:NULL);	
			$dao->addParameter(":pickupvehicle",'');	
			$dao->addParameter(":pickupdatetime",NULL);	
			$dao->addParameter(":pickupmodeunkid",NULL);	
			
			$dao->addParameter(":arrivaldate",$param['checkin_date']);	
			$dao->addParameter(":deptdate",$param['checkout_date']);	
			$dao->addParameter(":sourceunkid",$sourceid);

			$dao->addParameter(":roomtypeunkid",$roomtypeunkid);	
			$dao->addParameter(":ratetypeunkid",$ratetypeunkid);	
			$dao->addParameter(":roomid",'');	

			$dao->addParameter(":adult",$param['party_adults'][$nor]!=''?$param['party_adults'][$nor]:0);	
			//~ if(isset($param['party_children'][$nor]) && $param['party_children'][$nor]>0)
				//~ $param['party_children'][$nor] = 0;
			$dao->addParameter(":child",isset($param['party_children'][$nor])?$param['party_children'][$nor]:0);	
			$dao->addParameter(":childage",isset($param['party_children_age'][$nor])?$param['party_children_age'][$nor]:0);
			$dao->addParameter(":discountedrate",0);	
			
			$this->log->logIt('base rate................'.$param['rate']);
			$dao->addParameter(":baserate",$param['rate']);
							     
			$dao->addParameter(":extradultrate",$extraadultrate);	
			$dao->addParameter(":extrachildrate",$extrachildrate);	
			
			$dao->addParameter(":SpRequest",'');
			
			$dao->addParameter(":chargeunkid",'');	
			$dao->addParameter(":chargerate",0);	
			$dao->addParameter(":chargepostingrule",'');	
			$dao->addParameter(":chargerule",'');
			$dao->addParameter(":quantity",0);									
			$dao->addParameter(":chadult",0);									
			$dao->addParameter(":chchild",0);									
			$dao->addParameter(":chargecount",0);
							
			$dao->addParameter(":isnewguest",($rooms>1)?0:1);
			//~ $dao->addParameter(":isnewguest",1);
			
			$dao->addParameter(":guestunkid",''); //channeluserunkid - get channel userunkid here
			$dao->addParameter(":resguaid",0);
			$dao->addParameter(":mastertranunkid",$mastertranunkid);
			$dao->addParameter(":subreservationno",$subno);
			$dao->addParameter(":contacttype",'WEB');
			
			$dao->addParameter(":group_contactunkid",-1); //Empty
			$dao->addParameter(":keepinfo",0); // 1
		
			$dao->addParameter(":settalementtype",'CASH'); 			
			$dao->addParameter(":settlementtypeunkid",'');

			$dao->addParameter(":total_inc_charge",0);
			$dao->addParameter(":identitytype",'');
			$dao->addParameter(":identityno",'');
			$dao->addParameter(":identityexpdate",'');
			$dao->addParameter(":nationality",$param['cus_country']);
			$dao->addParameter(":dob",'');
			$dao->addParameter(":isspecial",'');
			$dao->addParameter(":lang_key",'en');
			$dao->addParameter(":packagename",'');
			$dao->addParameter(":packagedesc",'');
			$dao->addParameter(":bookingfrom",'DESKTOP');
			$dao->addParameter(":promoname",'');
			$dao->addParameter(":promodesc",'');
			$dao->addParameter(":specialunkid",'');
			$dao->addParameter(":promotionunkid",'');
			$dao->addParameter(":preference",'');
			$dao->addParameter(":preferencecnt",'');
			$dao->addParameter(":webpaymentoption",0);
			$dao->addParameter(":affiliateunkid",'');

			++$subreservationno;
			
			$dao->executeNonQuery();
			$dao->initCommand('SELECT @contactunkid,@tranunkid,@retstatus,@errorcode,@resno,@v_retstatus,@loginuserunkid,@groupunkid,@v_foliodetailunkid,@v_totalpaybleamt');
			$row = $dao->executeQuery();
			$errorcode[]=$row[0]['@errorcode'];
			$dash = '';
			if($subno!='')
				$dash='-';
			$group_contactunkid=-1;//$row[0]['@contactunkid'];
			$mastertranunkid=$row[0]['@retstatus'];
			$tranunkid[]=$row[0]['@tranunkid'];				
			$resno=$row[0]['@resno'];
			$res_no[]=$row[0]['@resno'].$dash.$subno;
			$loginuserunkid=$row[0]['@loginuserunkid'];	
			$groupunkid=$row[0]['@groupunkid'];	
			$v_foliodetailunkid=$row[0]['@v_foliodetailunkid'];	
			$totalpaybleamount=$row[0]['@v_totalpaybleamt'];//worked only in payment case else return 0 value	

			$this->log->logIt(get_class($this)." - Error code : ".$row[0]['@errorcode']);		
			$this->log->logIt(get_class($this)." - roomtypeunkid......... : ".$roomtypeunkid);		
			$this->log->logIt(get_class($this)." - ratetypeunkid......... : ".$ratetypeunkid);						
			$this->log->logIt(get_class($this)." - roomlevel  amount................".$row[0]['@v_totalpaybleamt']);
			

			//$this->log->logIt($row);//Jay Raval - 19-03-2021,Purpose:Comment unwanted logs[CEN-1971]
			$this->log->logIt(get_class($this)."-"."room... contactunkid  : ".$row[0]['@contactunkid']);
			$this->log->logIt(get_class($this)."-"."room... status code : ".$row[0]['@retstatus']);
			$this->log->logIt(get_class($this)."-"."room... error code : ".$row[0]['@errorcode']);
			$this->log->logIt(get_class($this)."-"."room... tranunkid : ".$row[0]['@tranunkid']);
			$this->log->logIt(get_class($this)."-"."room... reservation no : ".$row[0]['@resno']);
			$this->log->logIt(get_class($this)."-"."room... loginuserunkid : ".$row[0]['@loginuserunkid']);	
			$this->log->logIt(get_class($this)."-"."room... groupunkid : ".$row[0]['@groupunkid']);	
			$this->log->logIt(get_class($this)."-"."room... foliodetailunkid : ".$row[0]['@v_foliodetailunkid']);
			//~ $this->log->logIt(get_class($this)."-"."room... totalpayableamount : ".$row[0]['@v_totalpaybleamt']);

			$upd_res = $this->updateTrans($row[0]['@tranunkid']);
		
			if(!isset($param['party_children_arr'][$nor]))
				$param['party_children_arr'][$nor] = array();
				
			$room_arr[] = array('party'=>array(
											'adults'=>$param['party_adults'][$nor],
											'children'=>$param['party_children_arr'][$nor]
										),
			'traveler_first_name' =>$param['traveler_first_name'][$nor],
			'traveler_last_name' =>$param['traveler_last_name'][$nor]
			);

		}	

		$status ='';
		$rstatus = '';
		$problem = '';
		$explanation = '';
		
		$result =  $this->getHotelDetail();
		
		$res = $result->resultValue['record'];
		$accountfor = $res['accountfor'];
		
		$this->log->logIt('Account for ------------> '.$accountfor);
		if((in_array('1',$errorcode) || empty($errorcode)) && $neginventory == 1)
		{
			$this->log->logIt('------------ Roll back transaction ------------ ');
			$status ='Failure';
			$rstatus ='Cancelled';
			$problem = 'UnknownPartnerProblem';
			$explanation = 'There is some internal errors.Please try after some time.';
			$TruncateTransactionOnFailBooking=parameter::readParameter(parametername::TruncateTransactionOnFailBooking);	
			if($TruncateTransactionOnFailBooking==0)
			{
				#failure Booking
				foreach($tranunkid as $id)
				{
					$dao = new dao();
					$dao->initCommand("CALL `".$this->databasename."`sp_removeTransaction(:tranunkid)");
					$dao->addParameter(":tranunkid",$id);			
					$dao->executeNonQuery();
				}
			}
			$hotelid=$_SESSION[$_SESSION['prefix']]['hotel_code'];
			//~ $InvMode=$_SESSION[$_SESSION['prefix']]['roominventory'];
			//~ header('Location: paymentprocess.php?HotelId='.$hotelid.'&Action=FailBooking&ordernumber='.$resno.'&InvMode='.$InvMode."&ErrorTxt=SessionIssue");
		}
		else
		{
			//~ $tranunkid = array(104100000000000077,104100000000000078);
			//~ $res_no	   = array('71-1','71-2');
			$auditCnt=0;
			$this->log->logIt('------------ In Audit trail - Trip connect ------------ ');

			$status ='Success';
			$rstatus ='Booked';
			foreach($tranunkid as $id)
			{
				$this->log->logIt('in for loop ---- '.audittrail::TripConnect);
				audittrail::add($id,
				audittrail::TripConnect,
				array(
					'oarrivaldatetime'=>$checkin_date,
					'odeparturedatetime'=>$checkout_date,
					'resno'=>$res_no[$auditCnt]
					),0);
				
				++$auditCnt;
			
				$invflag = 0;
				if($inv_mode->resultValue['record']['roominventory']=='ALLOCATED')
					$invflag=1;

				
				/* For update inventory - Start -------  */

				$iCnt=0;

				$canflag=0;	
				$invarr	= array();
				#generate Date range - Start
				$arrDateRange=$this->generateDateRange($checkin_date,$checkout_date);
				#generate Date range - End
				$rowRoomsData=array();
				$rowRoomsData=$this->getRoomsInfo($id);
				array_push($invarr,$rowRoomsData);
				foreach($arrDateRange as $date)
				{
					$dateArr=explode("-",$date);
					if(substr($dateArr[2],0,1)==0)
						$dateArr[2]=substr($dateArr[2],1);
					
					if(substr($dateArr[1],0,1)==0)
						$dateArr[1]=substr($dateArr[1],1);
					
					if($iCnt < (count($arrDateRange) - 1))
					{
						$ObjBookingDao->day=$dateArr[2];
						$ObjBookingDao->month=$dateArr[1];
						$ObjBookingDao->year=$dateArr[0];	
						
						if(count($invarr) > 0)
						{
								for($key=0;$key<count($invarr);$key++)
								{
									for($j=0;$j<1;$j++)#Main Count
									{
										$ObjBookingDao->roomtypeunkid=$roomtypeunkid;
										$ObjBookingDao->contactunkid=$userunkid;
										$ObjBookingDao->roomrateunkid=$roomrateunkid;
										$ObjBookingDao->totrooms=1;
										#Update Inventory
										$ObjBookingDao->UpdateInventory($canflag);
									}
								}
						}#count array
					}	
					$iCnt++;
				}
				/* For update inventory - End -------  */
			
				$this->log->logIt('Account for ---> '.$accountfor.' | inventory mode ----> '.$inv_mode->resultValue['record']['roominventory']);
			
				if($accountfor == "ABS" && $inv_mode->resultValue['record']['roominventory'] == 'REGULAR')  //Auto Assing Rooms
				{
					$this->log->logIt('In auto assign rooms for regular inventory mode ------ >');
					if(parameter::readParameter(parametername::AutoAssignRoomsResChannel))
					{
						$this->log->logIt(" - Auto Room Assign");
						$this->log->logIt(" - arrivaldatetime - ".$checkin_date);
						$this->log->logIt(" - departuredatetime - ".$checkout_date);
						
						$_SESSION[$_SESSION['prefix']]['gfmt_DateFormat']=parameter::readParameter(parametername::DisDateFormat);
						$_SESSION[$_SESSION['prefix']]['gfmt_DateTimeFormat']=parameter::readParameter(parametername::DisDateFormat)." ".parameter::readParameter(parametername::DisTimeFormat);
					
						$rowRooms=$ObjMasterDao->getAvailableRoomListForCombo($roomtypeunkid,$checkin_date,$checkout_date,'','',1);//Minesh - 19th Aug 2019,Purpose - last 3 flag value added for Discard room for inventory not count for ABS-4190

						$this->roomtypeunkid = $roomtypeunkid;
						$roomunkid = $rowRooms->resultValue['list'][0]['roomunkid'];
						$roomname  = $rowRooms->resultValue['list'][0]['name'];
						
						$roomtype=$ObjRoomTypeDao->getRecord();
						//~ $this->log->logIt(" - roomtype - ".$roomtypeunkid." - ".$roomtype);			

						if(count($rowRooms) > 0)//added by flora
						{
							//~ $this->log->logIt(" - Room Available - ".$rowRooms[0]['roomunkid']);

							$dao = new dao();
							$dao->initCommand("CALL sp_roommove(:tranunkid,:room,:roomtype,:overrideroomrate,0,0,@retstatus)");	 // Umesh Lumbhani - 1.0.45.50.- 5 May 2015 - Add "0,0," in procedure for add menual rates on room move				
							$dao->addParameter(":roomtype",$roomtypeunkid);
							$dao->addParameter(":room",$roomunkid);
							$dao->addParameter(":tranunkid",$id);
							$dao->addParameter(":overrideroomrate",0);			
							$dao->executeNonQuery();
							
							$dao->initCommand("SELECT @retstatus");
							$autoassign = $dao->executeQuery();

							$this->log->logIt(" - After Auto Assing Room");
							$this->log->logIt(print_r($autoassign,true));
							
							audittrail::add($id,
							audittrail::WebAutoRoomAssign,
							array(
								'resno'=>$resno,
								'roomtype'=>$roomtype,
								'roomname'=>$roomname
								),1);
						}
						else
						{
							$this->log->logIt(" - Room Not Available");
							
							if(parameter::readParameter(parametername::EmailForAutoAssignRoomsFailureResChannel))
							{
								$this->log->logIt(" - EmailForAutoAssignRoomsFailureResChannel");											
								$tranrowresult=$ObjMasterDao->getTranRow($id);																					
								//~ $foliono=$tranrowresult['masterfoliounkid'];
								$reservationno=$resno;																											
								//$log->logIt($module." - Roomtype - ".$roomtypeunkid." - ".$roomtype);
								audittrail::add($id,
								audittrail::WebAutoRoomAssignFailure,
								array(
									'resno'=>$resno,
									'roomtype'=>$roomtype																																											
									),1);
								//~ sendEmailAutoAssignRoom($reservationno,$foliono,$rowTranData['name'],$checkin_date,$checkout_date,$roomtype);
							}
						}
					}			
				}  //Auto Assing Rooms- End
			}
		}

	$this->log->logIt('After if else ------------> ');

				//~ $resno=1;	
				$resp_data['reference_id'] = $param['reference_id'];
				
				if($problem)
				{
					$resp_data['problems'] = $problem;
					$resp_data['explanation'] = $explanation;
				}
				$resp_data['status'] = $status;
				$resp_data['reservation'] = array(
												'reservation_id'=>"'".$resno."'",
												'status'=>$rstatus,
												'confirmation_url'=> 'http://www.tripadvisor.com',
												'checkin_date'=> $param['checkin_date'],
												'checkout_date'=> $param['checkout_date'],
												'partner_hotel_code'=> "'".$hotel_code."'"
											);
				
				if($res['country'] != '') $hot_country = $res['country']; else $hot_country = 'IND';
				if(isset($res['checkinpolicy'])) $policy = $res['checkinpolicy']; else $policy = 'no policy defined';
					$resp_data['reservation']['hotel'] = array(
												"name"=>$res['name'],
												"address1"=>$res['address1'],
												"city"=>$res['city'],
												"state"=>$res['state'],
												"postal_code"=>$res['zipcode'],
												"country"=>$hot_country,
												"latitude"=>(int)$res['latitude'],
												"longitude"=>(int)$res['longitude'],
												"phone"=>$res['phone'],
												"url"=>$res['website'],
												"amenities"=>explode(',',$res['amenity']),
												"checkinout_policy" => $policy
										);

				$resp_data['reservation']['customer'] = array(
												'first_name'=> $param['cus_first_name'],
												'last_name'=> $param['cus_last_name'],
												'phone_number'=> $param['cus_phone_number'],
												'email'=> $param['cus_email'],
												'country'=> $param['cus_country']
										);
				
				$resp_data['reservation']['rooms'] = array_values($room_arr);
							
				//~ $resp_data['reservation']['rooms'] = array(array('party'=>array(
														//~ 'adults'=>$param['party_adults'],
														//~ 'children'=>$param['party_children_arr']
													//~ ),
											//~ 'traveler_first_name' =>$param['traveler_first_name'],
											//~ 'traveler_last_name' =>$param['traveler_last_name']
											//~ ));
				
				$resp_data['reservation']['legal_text'] = '';
				$resp_data['reservation']['comments'] = '';
				
				$resp_data['reservation']['receipt'] = array(
											'line_items'=>array(array('price'=>array('amount'=>$param['rate'],'currency'=>$param['currency_at_booking']),'type'=>'rate','paid_at_checkout'=>false,'description'=>'')),
											'final_price_at_booking'=> array('amount'=>$param['amount_at_booking'],'currency'=>$param['currency_at_booking']),
											'final_price_at_checkout'=> array('amount'=>$param['amount_at_checkout'],'currency'=>$param['currency_at_checkout'])
										);
										
			//~ $this->log->logIt('response data ----->'.print_r($resp_data,true));	
		
		return $resp_data;

	} catch (Exception $e) {
		$this->log->logIt("Exception in " . $this->module . " - insert_trans - \n\n" . $e);
		$this->handleException($e);
	}

}

function checkInv_mode($hotel_code)
{
	try {
	
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "checkInv_mode");
			
			$sSQL = "select roominventory from `".$this->databasename."`.trcontact where contacttypeunkid='WEB' and hotel_code = :hotel_code";
			
			//~ $this->log->logIt('sql ----->'.$sSQL);
			
			$dao = new dao();
			$dao->initCommand($sSQL);

			$result = new resultobject();

			$dao->addParameter(":hotel_code",$hotel_code);

			$result->resultValue['record']=$dao->executeRow();
			$result->resultValue['total']=count($dao->executeQuery());
			
	
		} catch (Exception $e) {
		$this->log->logIt("Exception in " . $this->module . " - checkInv_mode - " . $e);
		$this->handleException($e);
	}
	
    return $result;

}

public function getUserid_web()
{
	try {
	
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getUserid_web");
			
			$sSQL = "select userunkid from `".$this->databasename."`.cfuser where username='web' and hotel_code = :hotel_code";
			
			$dao = new dao();
			$dao->initCommand($sSQL);

			$result = new resultobject();

			$dao->addParameter(":hotel_code",$this->hotelcode);

			$result->resultValue['record']=$dao->executeRow();
			$result->resultValue['total']=count($dao->executeQuery());
			
	
		} catch (Exception $e) {
		$this->log->logIt("Exception in " . $this->module . " - getUserid_web - " . $e);
		$this->handleException($e);
	}
	
    return $result;
	
}


/************************** New Code for Insert Transaction End here *************************************/


/************************** New Code for check booking start here *************************************/

public function check_booking($param)
{
	try 
	{
		
		$resp_data = array();
		
		$this->hotel_code = $param['hotel_code'];
		$this->resno 	  = $param['reservation_id'];
		
		$resdata = $this->getReservation_detail();
		//~ $res_tot = $resdata->resultValue['total'];
		
		$this->log->logIt('------- Array of reservation details -------'.print_r($resdata,true));
		
		$reserv = $resdata->resultValue['record'];
		//~ print_r($reserv);exit;
		//~ $resno = str_replace("'", "", $this->resno);
		$cusrname = trim($reserv['GuestName']);
		$name_data = explode(' ',$cusrname);
		
		$resp_data['reference_id'] = $param['reference_id'];
		if($reserv['ChkStatus'] == 'CONFRESERV') $status = 'Success'; else $status = 'Pending';
		$resp_data['status'] = $status;
		
		$rstatus = '';
		if($reserv['ChkStatus'] == 'CONFRESERV') 
			$rstatus = 'Booked'; 
		else if($reserv['ChkStatus'] == 'CANCEL') 
			$rstatus = 'Cancelled';
		else if($reserv['ChkStatus'] == 'ARRIVAL') 
			$rstatus = 'CheckedIn';
		else if($reserv['ChkStatus'] == 'CHECKEDOUT') 
			$rstatus = 'CheckedOut';
		
		$resp_data['reservation'] = array(
										'reservation_id'=>$this->resno,
										'status'=> $rstatus,
										'confirmation_url'=> 'http://www.tripadvisor.com',
										'checkin_date'=> $reserv['ArrivalDate'],
										'checkout_date'=> $reserv['DepartureDate'],
										'partner_hotel_code'=> $this->hotel_code
									);
		/* Get hotel details */
		$result =  $this->getHotelDetail();

		$res = $result->resultValue['record'];
		if($res['country'] != '') $hot_country = $res['country']; else $hot_country = 'IND';
		if(isset($res['checkinpolicy'])) $policy = $res['checkinpolicy']; else $policy = 'no policy defined';
		$resp_data['reservation']['hotel'] = array(
									"name"=>$res['name'],
									"address1"=>$res['address1'],
									"city"=>$res['city'],
									"state"=>$res['state'],
									"postal_code"=>$res['zipcode'],
									"country"=>$hot_country,
									"latitude"=>(int)$res['latitude'],
									"longitude"=>(int)$res['longitude'],
									"phone"=>$res['phone'],
									"url"=>$res['website'],
									"amenities"=>explode(',',$res['amenity']),
									"checkinout_policy" => $policy
							);
								
		$resp_data['reservation']['customer'] = array(
								'first_name'=> $name_data[0],
								'last_name'=> end($name_data),
								'phone_number'=> $reserv['guestphone'],
								'email'=> $reserv['guestcontactemail'],
								'country'=> $reserv['guestcountry']
						);

		$resp_data['reservation']['rooms'] = array(
							array('party'=>
								array(
									'adults'=>(int)$reserv['adult'],
									'children'=>array((int)$reserv['child'])
									),
							'traveler_first_name' =>$name_data[0],
							'traveler_last_name' =>end($name_data)
							));

		$resp_data['reservation']['legal_text'] = '';
		$resp_data['reservation']['comments'] = '';
		
		$resp_data['reservation']['receipt'] = array(
			'line_items'=>array(array('price'=>array('amount'=>(int)$reserv['Total'],'currency'=>$reserv['CurrencyCode']),'type'=>'rate','paid_at_checkout'=>false,'description'=>'')),
			'final_price_at_booking'=> array('amount'=>(int)$reserv['Total'],'currency'=>$reserv['CurrencyCode']),
			'final_price_at_checkout'=> array('amount'=>0,'currency'=>$reserv['CurrencyCode'])
		);
				
		return $resp_data;
		
	} catch (Exception $e) {
		$this->log->logIt("Exception in " . $this->module . " - check_booking - \n\n" . $e);
		$this->handleException($e);
	}
}
/************************** New Code for check booking End here *************************************/


/************************** New Code for remove booking start here *************************************/
public function rem_booking($params)
{
	try 
	{
		$this->log->logIt('---------------- In remove Booking ---------------------');

		$ObjTranProcessDao=new transactionprocessdao();
		$ObjBookingDao=new bookingdao();
		$ObjMasterDao=new masterdao();
		$ObjTranDao = new trandao();

		$adult="adult &";
		$child="child";	
	
		$hotel_result = $this->getHotelDetail();
		$res_hot = $hotel_result->resultValue['record'];
		$this->accountfor = $res_hot['accountfor'];
		
		$this->resno		 = $params['reservation_id'];
		$this->hotel_code	 = $params['hotel_code'];

		$_SESSION['prefix']='SaaS_'.$this->hotel_code;
		$_SESSION[$_SESSION['prefix']]['hotel_code']=$this->hotel_code;
		
		$transdata = $this->getTransId();
		//~ print_r($transdata);

		if($transdata->resultValue['total'] > 0)
		{
			$res = $transdata->resultValue['record'];
			$transid = $res['tranunkid'];
			//~ echo '<br><br><br>',$res['tranunkid'];
			
			$userid = $ObjBookingDao->getUserId($transid);
			$row2 = (array) $userid->resultValue['record'];
		
			audittrail::add($transid,audittrail::CancelReservation);
			
			//~ $ObjTranProcessDao->cancellationno=$ObjMasterDao->getAutoManualNo(parametername::CRNumberType,parametername::CRNumberPrefix,parametername::CRNumberNext);
			$ObjTranProcessDao->cancellationno=$ObjMasterDao->getAutoManualNo(parameter::CRNumberType,parameter::CRNumberPrefix,parameter::CRNumberNext);
			
			$sid = $ObjMasterDao->getFDRentalStatusID('CANCEL');
			$row = (array) $sid->resultValue['record'];
					
			$ObjTranProcessDao->tranunkid		= $transid;
			$ObjTranProcessDao->statusunkid		= $row['statusunkid'];
			
			//~ $this->log->logIt($this->module."-"."fdrental status --"."-".$row['statusunkid']);
			$ObjTranProcessDao->resno			= $this->resno;	
			$ObjTranProcessDao->is_void_cancelled_noshow_unconfirmed	= 1;	
			$ObjTranProcessDao->canceldatetime	= util::getLocalDateTime($this->hotel_code);  //getLocalDate
			//~ echo $ObjTranProcessDao->canceldatetime;
			//~ exit;

			//~ $_SESSION[$_SESSION['prefix']]['HotelDetails']['accountfor'] = $this->accountfor;
			//~ echo $_SESSION[$_SESSION['prefix']]['HotelDetails']['accountfor'];exit;

			$userid 	= $ObjBookingDao->getUserId($transid);
			$row2		= (array) $userid->resultValue['record'];
			
			$ObjTranProcessDao->canceluserunkid=$row2['reservationuserunkid'];
			
			$res = $ObjTranProcessDao->cancelReservation('');
			$result = (array) $res->resultValue['record'];

			//~ echo $this->resno;
			$this->log->logIt('Reservation number ---- '.$this->resno.' , cancellation number ------- '.$ObjTranProcessDao->cancellationno);
			
			$status = 'Failed';
			if($ObjTranProcessDao->cancellationno)
				$status = 'Success';
			
			$cancellationdata = array(
									'partner_hotel_code' => $this->hotel_code,
									'reservation_id'	 => $this->resno,
									'status' 			 => $status,
									'cancellation_number'=> $ObjTranProcessDao->cancellationno
								);
			
			
			return $cancellationdata;
			
			exit(0);
		}
		else
		{
			$this->log->logIt("Exception in " . $this->module . " - remove_booking - Transaction ID not found.. \n\n");
		}

	} catch (Exception $e) {
		$this->log->logIt("Exception in " . $this->module . " - remove_booking - \n\n" . $e);
		$this->handleException($e);
	}	

	$sid = $ObjMasterDao->getFDRentalStatusID('CANCEL');
	$row = (array) $sid->resultValue['record'];
	$statusid=$row['statusunkid'];
	
	$this->log->logIt('------- after exception ----');
	
	if($this->resno!='')
	{
		try
		{
			$tra = $ObjTranProcessDao->getArrivalData('',$this->resno,'','');
			$rowTran = (array) $tra->resultValue['list'];
			
			$DisDateFormat=parameter::readParameter(parametername::DisDateFormat);
			
		  foreach($rowTran as $rowTranData)
		  {
				$nrrooms=$Tax=$RoomCharge=$TotalPayble=$adjustment=0;
				list($chkdate,$chktime)=explode(" ",$rowTranData['arrivaldatetime']);
				list($chkOutdate,$chkOuttime)=explode(" ",$rowTranData['departuredatetime']);			
				list($trandt,$trantime)=explode(" ",$rowTranData['trandate']);
				
				$nights=util::getNights($chkOutdate,$chkdate,'yy-mm-dd');
				
				$chkindt_format=util::Config_Format_Date($chkdate,$DisDateFormat);
				$chkoutdt_format=util::Config_Format_Date($chkOutdate,$DisDateFormat);
				$trandt_format=util::Config_Format_Date($trandt,$DisDateFormat);
				
				$tra = $ObjTranProcessDao->getArrivalData('',$this->resno,'','');
				$rowTran = (array) $tra->resultValue['list'];
				
				$troomdata=$ObjBookingDao->getRoomsInfo($rowTranData['tranunkid']);
				$rowRoomsData= (array) $troomdata->resultValue['list'];
				
				$displayname=$adult=$tadult=$tchild=$is_prepaid_noncancel_nonrefundabel='';
				$titem=0;
				$sub_reservationno=$rowTranData['subreservationno'];
				
				foreach($rowRoomsData as $roomdeatils)
				{
					$displayname=$roomdeatils['display_name'];
					$adult=$roomdeatils['adult']."(A) - ".$roomdeatils['child']."(C)";
					$tadult=$roomdeatils['adult'];
					$tchild=$roomdeatils['child'];
					$is_prepaid_noncancel_nonrefundabel=$roomdeatils['is_prepaid_noncancel_nonrefundabel'];
				}

				#Folio Data 
				$ObjTranDao->tranid=$rowTranData['tranid'];

				#Total Room Charge
				$rec =$ObjTranDao->getFasMasterTypeTotal('Room Charges');
				$record= (array) $rec->resultValue['record'];
				$RoomCharge+=$record['Total'];			


				#Total tax
				$rec =$ObjTranDao->getFasMasterTypeChildTotal('Room Charges','Tax');
				$record= (array) $rec->resultValue['record'];

				$Tax+=$record['Total'];

				#Find Adjustment
				$rec =$ObjTranDao->getFasMasterTypeChildTotal('Room Charges','Adjustments');
				$record= (array) $rec->resultValue['record'];
				//$record=$ObjMasterDao->getFasMasterTypeChildTotal('Room Charges','Adjustments');
				$adjustment+=$record['Total'];

				#Total Inclusion start
				$rec_inc =$ObjBookingDao->getInclusions($rowTranData['tranid']);
				$record_inc= (array) $rec_inc->resultValue['record'];
				//$record_inc=$ObjMasterDao->getInclusions($rowTranData['tranid']);

				$totCharge=0;
				$totCharge_adj=0;

				foreach($record_inc as $extra_charge)
				{
					switch ($extra_charge['chargerule']) 
					{
						case "PERADULT":
							$titem=$extra_charge['adult'];//update accordign to db value
							break;
						case "PERCHILD":
							$titem=$extra_charge['child'];//update accordign to db value
							break;
						case "PERPERSON":
							$titem=$extra_charge['adult']+$extra_charge['child'];//update accordign to db value
							break;
						case "PERINSTANCE":
							$titem=$extra_charge['quantity'];
							break;
						case "PERBOOKING":
							$titem=$extra_charge['quantity'];
							break;	
						case "PERQUANTITY":
							$titem=$extra_charge['quantity'];
							break;	
					}
							
					$rateIncTax=0;
					$InclusionTax=0;

					#Retrieve Date Range Based upon Posting Rule Selected
//bookingdao					$rentaldate=$ObjMasterDao->generateDateRangeTax($chkdate,$chkOutdate,$extra_charge['postingtype']);
					
					if($extra_charge['tax']!='')
					{										
						for($iRange=0;$iRange<count($rentaldate);$iRange++)
						{
							#Get Tax
							$InclusionTax=$ObjBookingDao->getTax(($extra_charge['rate']*$titem),$extra_charge['tax'],$rentaldate[$iRange],1);							
							$rateIncTax = (($extra_charge['rate']*$titem) + $InclusionTax);
							$totCharge+=($rateIncTax);#Total Inclusion Charge Applicable
							$totCharge_adj+=util::getRoundVal($rateIncTax,false);#Total Adjusment on Inclusion
						}
					}	
					else
					{
						$rateIncTax=$extra_charge['rate'];
						$totCharge+=$rateIncTax * $titem * count($rentaldate);	
						$totCharge_adj+=util::getRoundVal($rateIncTax * $titem,false) * count($rentaldate);
					}	
				}
				#Total Inclusion end

				#payble Amount
				$TotalPayble=$RoomCharge + $Tax + $totCharge + $totCharge_adj + $adjustment;
											
				$CancelBookingList[]=array(
							'tranid'=>$rowTranData['tranid'],
							'isposted'=>$rowTranData['isposted'],
							'rateplan'=>$displayname,
							'is_prepaid_noncancel_nonrefundabel'=>$is_prepaid_noncancel_nonrefundabel,
							'totalocc'=>$adult,
							'nights'=> $nights,
							'chkindt'=>$chkindt_format,
							'chkoutdt'=>$chkoutdt_format,
							'subresno'=>($sub_reservationno!='')?$sub_reservationno:'-',
							'statusid'=>$rowTranData['statusid'],
							'roomcharge'=>util::getFormattedNumber($TotalPayble)
							);
			}//foreach


		}
		catch(Exception $e)
		{
			$this->log->logIt($this->module."-"."Exception"."-".$e);
		}
		
		return $CancelBookingList;
	}
}
/************************** New Code for remove booking end here *************************************/

/************************** New Code for Sync booking start here *************************************/

function sync_booking($param)
{
	try 
	{
		$this->log->logIt('---------------- In Sync Booking ---------------------');
		
		$this->hotel_code = $param['hotel_code'];
		$this->resno = $param['reservation_id'];
		$status = '';
		$resdata = $this->getReservation_detail();
		$reserv = $resdata->resultValue['record'];
		$can_flag = 0;
		if($reserv['ChkStatus'] == 'CONFRESERV')
			$status = 'Booked';
		else if($reserv['ChkStatus'] == 'CANCEL')
		{
			$status = 'Cancelled';
			$can_flag = 1;
		}
		else if($reserv['ChkStatus'] == 'ARRIVAL')
		{
			$status = 'CheckedIn';
			$can_flag = 0;
		}
		else if($reserv['ChkStatus'] == 'CHECKEDOUT')
		{
			$status = 'CheckedOut';
			$can_flag = 0;
		}
		else if($reserv['ChkStatus'] == 'NOSHOW')
		{
			$status = 'NoShow';
			$can_flag = 0;
		}
		else
		{
			$status = 'UnknownReference';
			$can_flag = 1;
		}

		/*
		$sync_data = array(
			"partner_hotel_code"=> $this->hotel_code,
			"reservation_id"=> $this->resno,
			"status"=> $status
		);*/	

		
		if($can_flag == 0)
		{
			$sync_data = array(
				"partner_hotel_code"=> $this->hotel_code,
				"reservation_id"=> $this->resno,
				"status"=> $status,
				"checkin_date"=> $reserv['Arrival'],
				"checkout_date"=> $reserv['Departure'],
				"total_rate"=> array("amount"=>(int)$reserv['Total'],"currency"=>$reserv['CurrencyCode']),
				"total_taxes"=> array("amount"=>0,"currency"=>$reserv['CurrencyCode']),
				"total_fees"=>  array("amount"=>(int)$reserv['Total'],"currency"=>$reserv['CurrencyCode'])
			);	

		}
		else
		{

			$sync_data = array(
				"partner_hotel_code"=> $this->hotel_code,
				"reservation_id"=> $this->resno,
				"status"=> $status,
				"cancelled_date"=> $reserv['CanDate'],
				"cancellation_number"=> $reserv['CanNo']
			);	
		}
		
		
	} catch(Exception $e) {
		$this->log->logIt($this->module."-"."Exception"."-".$e);
	}
	return $sync_data;
}

/************************** New Code for Sync booking end here *************************************/

    public function getRoomsInfo($tranunkid='') {
        try {
            $this->log->logIt(get_class($this) . "-" . "getRoomsInfo");
            $dao = new dao();
            $strSql = "SELECT  rental.*,rateplan.display_name,rateplan.webdescription,rateplan.roomrateunkid,rateplan.deposit,tran.remark,tran.sourceunkid,tran.reservationuserunkid " .
			        "".util::generateLangQuery(dbtable::RoomRateType,'rateplan')."".
                    " FROM " . dbtable::FDTranInfo . "  AS tran , " . dbtable::FDRentalInfo . "  AS rental ," . dbtable::RoomRateType . "  AS rateplan " .
                    " WHERE (rental.tranunkid=tran.tranunkid AND rental.statusunkid<>8) AND rateplan.ratetypeunkid=rental.ratetypeunkid " .
                    " AND rateplan.roomtypeunkid=rental.roomtypeunkid " .
                    " AND rental.tranunkid=:tranunkid AND tran.hotel_code=:hotel_code group by tran.tranunkid";
            $dao->initCommand($strSql);
            $dao->addParameter(":hotel_code", $_SESSION[$_SESSION['prefix']]['hotel_code']);
            $dao->addParameter(":tranunkid", $tranunkid);
            $result = $dao->executeQuery();
        } catch (Exception $e) {
            $this->log->logIt(get_class($this) . "-" . "getRoomsInfo" . "-" . $e);
        }
        return $result;
    }



public function generateDateRange($strDateFrom='',$strDateTo='')
{
	$aryRange=array();
	$iDateFrom=mktime(1,0,0,substr($strDateFrom,5,2),     substr($strDateFrom,8,2),substr($strDateFrom,0,4));
	$iDateTo=mktime(1,0,0,substr($strDateTo,5,2),     substr($strDateTo,8,2),substr($strDateTo,0,4));
					
	if ($iDateTo>=$iDateFrom) {
		array_push($aryRange,date('Y-m-d',$iDateFrom)); 
				
		while ($iDateFrom<$iDateTo) {
		  $iDateFrom+=86400; // add 24 hours
		  array_push($aryRange,date('Y-m-d',$iDateFrom));
		  }
	}//if
	return $aryRange;
}


private function getTransId()
{
	try {
	
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getTransId");
			
			$sSQL = "select tranunkid from `".$this->databasename."`.fdtraninfo where reservationno = :resno and hotel_code = :hotel_code";
			
			//~ $this->log->logIt('sql ----->'.$sSQL);
			
			$dao = new dao();
			$dao->initCommand($sSQL);

			$result = new resultobject();

			$dao->addParameter(":resno",$this->resno);
			$dao->addParameter(":hotel_code",$this->hotel_code);

			$result->resultValue['record']=$dao->executeRow();
			$result->resultValue['total']=count($dao->executeQuery());
			
	
		} catch (Exception $e) {
		$this->log->logIt("Exception in " . $this->module . " - getTransId - " . $e);
		$this->handleException($e);
	}
	
    return $result;

}


private function getReservation_detail()
{
	try
	{
		$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getReservation_detail");
		$resno = str_replace("'", "", $this->resno);
		$hotel_code = $this->hotel_code;
		
		$this->log->logIt('Hotel code & resrvation no.  ---> '.$resno.' ---> '.$hotel_code);

		$sSQL="SELECT CASE WHEN FDTI.subreservationno IS NOT NULL THEN CONCAT(FDTI.reservationno,'-',FDTI.subreservationno)
		 ELSE FDTI.reservationno END AS ResNo,FDTI.cancellationno as CanNo,date(canceldatetime) CanDate,FDTI.reservationno AS ReservationNo,
		DATE(FDTI.arrivaldatetime) AS ArrivalDate,DATE(FDTI.departuredatetime)
		 AS DepartureDate, CASE WHEN TRC.salutation IS NOT NULL THEN CONCAT(TRC.salutation,' ',TRC.name) 
		ELSE TRC.name END AS GuestName, CASE WHEN IFNULL(FDRI.roomunkid,0) <> 0 
		THEN CONCAT(CFR.name,' - ',CFRT.shortcode) ELSE CFRT.shortcode END AS Room,
		CONCAT(CFRG.gauranteeunkid,'-',CFRG.confirmed) AS ReservationGuaranteeId, 
		CFRG.name AS ReservationGuarantee, FDRS.displaystatus AS Status, 
		FDRS.status AS ChkStatus,FDTI.tranunkid AS TranId,IFNULL(FDRI.roomunkid,0) As RoomId,
		CASE WHEN FDTI.businesssourceunkid Is NOT NULL THEN 
		(CASE WHEN BusinessSource.shortcode Is NOT NULL THEN BusinessSource.shortcode 
		ELSE BusinessSource.businesssourcename END) ELSE '' END As Source,IFNULL(isgroupowner,0) As Ownership,
		IFNULL(groupunkid,0) As GroupId,FDTI.arrivaldatetime AS Arrival,FDTI.departuredatetime AS Departure,
		 IFNULL(SUM(FD.baseamount),0) AS Total,IFNULL('-1' * SUM(IF(FIND_IN_SET(fasmaster.mastertypeunkid,'6,9,2'),
		FD.baseamount,0)),0) AS Deposit,CFX.currency_code CurrencyCode, CFR.name as roomname,CFRT.roomtype as roomtype,IFNULL(CFRT.roomtypeunkid,0)
		 As RoomTypeId,CFRT1.ratetype as ratetype,IFNULL(FDTI.contactemail,'') as trancontactemail,
		IFNULL(TRC.email,'') as guestcontactemail,IFNULL(TRC.phone,'') guestphone,IFNULL(TRC.country,'') guestcountry,FDTI.transactionflag ,FDRI.adult,FDRI.child,
		(SELECT IF(COUNT(rentalunkid)>0,1,0)
		 FROM `".$this->databasename."`.fdrentalinfo WHERE tranunkid=FDTI.tranunkid AND hotel_code=$hotel_code 
		AND IFNULL(tomoveflag,0)=1 AND !FIND_IN_SET(statusunkid,'8,12') AND IF(FIND_IN_SET(statusunkid,'5,6,7'),
		is_void_cancelled_noshow_unconfirmed=1, is_void_cancelled_noshow_unconfirmed=0)
		 ORDER BY rentalunkid ASC) AS isSplitFlag,IFNULL(FDTI.stoproommoveflag,0) AS StopRoomMove_Flag ,
		 CASE WHEN FDRS.status = 'CONFRESERV' THEN CFU.username WHEN FDRS.status = 'VOID' 
		THEN CFU1.username WHEN FDRS.status = 'NOSHOW' THEN CFU2.username WHEN FDRS.status = 'CANCEL'
		 THEN CFU3.username  END AS User , IFNULL(TRC1.business_name,'') AS Company,
		(SELECT SUM(FD1.baseamount) FROM `".$this->databasename."`.fasfoliodetail as FD1 WHERE FD1.foliounkid=FM.foliounkid AND FD1.
		hotel_code=$hotel_code AND isvoid_cancel_noshow_unconfirmed = 0) as Total1
		FROM `".$this->databasename."`.fdtraninfo AS FDTI  
		INNER JOIN `".$this->databasename."`.fdrentalinfo AS FDRI ON (FDRI.tranunkid = FDTI.tranunkid AND FDRI.hotel_code=$hotel_code)
		 
		LEFT JOIN `".$this->databasename."`.cfroom AS CFR ON (CFR.roomunkid = FDRI.roomunkid AND CFR.hotel_code=$hotel_code)
		 INNER JOIN `".$this->databasename."`.cfroomtype AS CFRT ON (CFRT.roomtypeunkid = FDRI.roomtypeunkid AND CFRT.hotel_code=$hotel_code)
		 INNER JOIN `".$this->databasename."`.cfratetype AS CFRT1 ON (CFRT1.ratetypeunkid = FDRI.ratetypeunkid AND CFRT1.hotel_code=$hotel_code)
		 INNER JOIN `".$this->databasename."`.fdrentalstatus AS FDRS ON (FDRS.statusunkid = FDTI.statusunkid AND FDRS.hotel_code = $hotel_code)
		LEFT JOIN `".$this->databasename."`.trcontact AS TRC1 ON (TRC1.contactunkid = FDTI.companyunkid AND TRC1.contacttypeunkid = 'VENDOR'
		 AND TRC1.hotel_code=$hotel_code) LEFT JOIN `".$this->databasename."`.cfuser AS CFU ON (CFU.userunkid = FDTI.reservationuserunkid
		 AND CFU.hotel_code=$hotel_code)  LEFT JOIN `".$this->databasename."`.cfuser AS CFU1 ON (CFU1.userunkid = FDTI.voiduserunkid 
		AND CFU1.hotel_code=$hotel_code)   LEFT JOIN `".$this->databasename."`.cfuser AS CFU2 ON (CFU2.userunkid = FDTI.noshowuserunkid 
		AND CFU2.hotel_code=$hotel_code)   LEFT JOIN `".$this->databasename."`.cfuser AS CFU3 ON (CFU3.userunkid = FDTI.canceluserunkid 
		AND CFU3.hotel_code=$hotel_code)   LEFT JOIN `".$this->databasename."`.cfbusinesssource AS BusinessSource 
		ON BusinessSource.businesssourceunkid=FDTI.businesssourceunkid 
		INNER JOIN `".$this->databasename."`.fdguesttran AS FDGT ON FDGT.guesttranunkid  = FDTI.masterguesttranunkid 
		INNER JOIN `".$this->databasename."`.trcontact AS TRC ON (TRC.contactunkid = FDGT.guestunkid AND TRC.contacttypeunkid='GUEST' 
		AND TRC.hotel_code=$hotel_code) INNER JOIN `".$this->databasename."`.cfreservationgaurantee AS 
		CFRG ON CFRG.gauranteeunkid = FDTI.reservationgauranteeunkid
		 INNER JOIN `".$this->databasename."`.fasfoliomaster AS FM ON (FM.lnktranunkid=FDTI.tranunkid AND FM.foliotypeunkid=1 
		AND FM.hotel_code=$hotel_code ) LEFT JOIN `".$this->databasename."`.fasfoliodetail AS FD ON (FM.foliounkid = FD.foliounkid 
		AND FD.hotel_code=$hotel_code)
		INNER JOIN `".$this->databasename."`.cfexchangerate CFX on (CFX.exchangerateunkid = FD.currencyunkid)
		 LEFT JOIN `".$this->databasename."`.fasmaster AS fasmaster ON (fasmaster.masterunkid = FD.masterunkid
		 AND fasmaster.hotel_code=$hotel_code) WHERE FDTI.hotel_code = $hotel_code 
		AND FDRS.hotel_code = $hotel_code AND DATE( FDTI.arrivaldatetime ) = DATE( FDRI.rentaldate )  
		AND (FDTI.reservationno = $resno) AND FDTI.transactionflag=1 
		 AND FIND_IN_SET(FDRS.status,'CONFRESERV,UNCONFRESERV,DAYUSERESERV,VOID,CANCEL,NOSHOW')
		 AND !FIND_IN_SET(FDRI.statusunkid,'8,12')  GROUP BY FDTI.tranunkid";
 //~ OR CONCAT(FDTI.reservationno,'-',FDTI.subreservationno) LIKE :resno1
		$dao = new dao();
		$dao->initCommand($sSQL);

		$result = new resultobject();

		//~ $dao->addParameter(":hotel_code",$hotel_code);
		//~ $dao->addParameter(":resno",$resno);
		//~ $dao->addParameter(":resno1",$resno.'%');

		$result->resultValue['record']=$dao->executeRow();
		$result->resultValue['total']=count($dao->executeQuery());		
		
		//~ print_r($result->resultValue);exit;
		
		//~ $this->log->logIt('--------------------Reservation detail query -------------------- '.$sSQL);
		
		
	} catch (Exception $e) {
		$this->log->logIt("Exception in " . $this->module . " - getReservation_detail - " . $e);
		$this->handleException($e);
	}

    return $result;
}


public function updateTrans($transid)
{
	try {
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "updateTrans");

            $dao = new dao();
			$result = new resultobject();	
			
			$sSQL = 'update `'.$this->databasename.'`.fdtraninfo set transactionflag = 1 where tranunkid = :transid';
			$dao->initCommand($sSQL);
			$dao->addParameter(":transid",$transid);
			$dao->executeNonQuery();
	} catch (Exception $e) {
		$this->log->logIt("Exception in " . $this->module . " - updateTrans - " . $e);
		$this->handleException($e);
	}

    return $result;
}


public function getResourceId($hotel_code)
{
	try {
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getResourceId");
			
			$sSQL="SELECT contactunkid,contacttypeunkid FROM `".$this->databasename."`.trcontact WHERE contacttypeunkid='WEB' AND hotel_code =:hotel_code";

			$dao = new dao();
			$dao->initCommand($sSQL);
			$dao->addParameter(":hotel_code",$hotel_code);

			$result->resultValue['record']=$dao->executeRow();
			$result->resultValue['total']=count($dao->executeQuery());		

	} catch (Exception $e) {
		$this->log->logIt("Exception in " . $this->module . " - getResourceId - " . $e);
		$this->handleException($e);
	}

    return $result;
	
}



public function get_roomtypeid($roomrateunkid,$hotel_code)
{
	//~ $roomids = array();

	$result=new resultobject();

    try {
			$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "get_roomtypeid");
		
			$sSQL="select roomtypeunkid,ratetypeunkid,extraadultrate,extrachildrate from `".$this->databasename."`.cfroomrate_setting where roomrateunkid = :roomrateunkid and hotel_code = :hotel_code";

			$dao = new dao();
			$dao->initCommand($sSQL);
			$dao->addParameter(":roomrateunkid",$roomrateunkid);
			$dao->addParameter(":hotel_code",$hotel_code);

			//~ echo '<br><br><br>---',$roomrateunkid,'<br>';
			//~ echo $sSQL;exit;

			$result->resultValue['record']=$dao->executeRow();
			$result->resultValue['total']=count($dao->executeQuery());		
			
		
	} catch (Exception $e) {
		$this->log->logIt("Exception in " . $this->module . " - get_roomtypeid - " . $e);
		$this->handleException($e);
	}
	return $result;
}	


	public function GetHotelInfo()
	{
		$hotel_info = array();
		
        try {
				$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "GetHotelInfo");
				#$this->log->logIt(print_r($detail,true));
				#$hotelcode = $this->hotelcode;
				#$tripid = $this->tripid;
				$hotel_info = array();
				$result = $this->getHotelDetail();
				$rec = $result->resultValue['record'];
				$hotel_info['hotel_rec'] = $rec;

				$result = $this->getRoomDetail();
				$rec = $result->resultValue['record'];
				$hotel_info['roominfo'] = $rec;
			
		} catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - GetHotelInfo - " . $e);
            $this->handleException($e);
        }
		
		return $hotel_info;  		
	}
	
	public function getHotelDetail()
	{
		$result=new resultobject();
	
        try {
				$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getHotelDetail");

				//~ $strSql = "SELECT CFH.*,
				          //~ (SELECT GROUP_CONCAT(amenity) AS amty FROM `".$this->databasename."`.cfamenity WHERE hotel_code=:hotel_code AND isactive=1 GROUP BY hotel_code) AS amenity ,
						  //~ (SELECT alias FROM `".$this->databasename."`.cfcountry WHERE countryunkid=CFH.country) AS country_name,
						  //~ (SELECT keyvalue FROM `".$this->databasename."`.cfparameter WHERE keyname LIKE '%WebAvailabilityCalDateFormat%' AND hotel_code=:hotel_code) as caldateformat
						   //~ FROM `".$this->databasename."`.cfhotel as CFH where hotel_code=:hotel_code";

				$strSql = "SELECT CFH.*,CFC.isocode as country,
				          (SELECT GROUP_CONCAT(amenity) AS amty FROM `".$this->databasename."`.cfamenity WHERE hotel_code=:hotel_code AND isactive=1 GROUP BY hotel_code) AS amenity ,
						  (SELECT alias FROM `".$this->databasename."`.cfcountry WHERE countryunkid=CFH.country) AS country_name,
						  (SELECT keyvalue FROM `".$this->databasename."`.cfparameter WHERE keyname LIKE '%WebAvailabilityCalDateFormat%' AND hotel_code=:hotel_code) as caldateformat,
						  if(CFH.image is not null,(select image_path from `".$this->databasename."`.cfimages where hotel_code=:hotel_code and objectunkid =:hotel_code and locate(CFH.image,image_path) > 0),'') image
						  FROM `".$this->databasename."`.cfhotel as CFH 
						  left join `".$this->databasename."`.cfcountry CFC on (CFH.country = CFC.countryunkid)
						  where hotel_code=:hotel_code";

				#$this->log->logIt($strSql);
				#print 	$strSql."<br>";		
				#print $this->hotelcode."----> <br>";

				$dao = new dao();
				$dao->initCommand($strSql);
				$dao->addParameter(":hotel_code",$this->hotelcode);
				#$dao->addParameter(":database",$this->databasename);

				$result->resultValue['record']=$dao->executeRow();
				$result->resultValue['total']=count($dao->executeQuery());		
			
		} catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - getHotelDetail - " . $e);
            $this->handleException($e);
        }
		
		return $result;
	}

	public function getRoomDetail()
	{
		$result=new resultobject();

        try {
				$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getHotelDetail");

			//$strSql = "SELECT * FROM `".$this->databasename."`.cfhotel where hotel_code=:hotel_code";

			$strSql = "SELECT roomtype,shortcode,
					(SELECT GROUP_CONCAT(amenity) AS amty FROM `".$this->databasename."`.cfamenity WHERE FIND_IN_SET(amenityunkid,amenities) AND isactive=1 GROUP BY hotel_code) AS amenity 
					FROM `".$this->databasename."`.cfroomtype 
					WHERE hotel_code=:hotel_code AND isactive=1 AND publishtoweb=1";
				
				$dao = new dao();
				$dao->initCommand($strSql);
				$dao->addParameter(":hotel_code",$this->hotelcode);
				#$dao->addParameter(":database",$this->databasename);

				$result->resultValue['record']=$dao->executeQuery();
				$result->resultValue['total']=count($dao->executeQuery());		
			
		} catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - getHotelDetail - " . $e);
            $this->handleException($e);
        }
		
		return $result;
	}


	public function getroom_amnities($roomtypeid)
	{
		$result=new resultobject();

        try {
				$this->log->logIt("Hotel_" . $this->hotelcode . " - " . get_class($this) . "-" . "getroom_amnities");

				//$strSql = "SELECT * FROM `".$this->databasename."`.cfhotel where hotel_code=:hotel_code";

				$strSql = "SELECT roomtype,shortcode,
						(SELECT GROUP_CONCAT(amenity) AS amty FROM `".$this->databasename."`.cfamenity WHERE FIND_IN_SET(amenityunkid,amenities) AND isactive=1 GROUP BY hotel_code) AS amenity 
						FROM `".$this->databasename."`.cfroomtype 
						WHERE hotel_code=:hotel_code and roomtypeunkid=:roomtypeunkid AND isactive=1 AND publishtoweb=1";
				
				$dao = new dao();
				$dao->initCommand($strSql);
				$dao->addParameter(":hotel_code",$this->hotelcode);
				$dao->addParameter(":roomtypeunkid",$roomtypeid);

				#$dao->addParameter(":database",$this->databasename);

				$result->resultValue['record']=$dao->executeRow();
				$result->resultValue['total']=count($dao->executeQuery());		
			
		} catch (Exception $e) {
            $this->log->logIt("Exception in " . $this->module . " - getroom_amnities - " . $e);
            $this->handleException($e);
        }
		
		return $result;
	}


    private function handleException($e) {
        try {
            #$this->log->logIt(get_class($this) . "-" . "handleException");
           # $this->generateGeneralErrorMsg('500', "Error occured during processing");
            #echo $this->xmlDoc->saveXML();
            exit;
        } catch (Exception $e) {
            $this->log->logIt(get_class($this) . "-" . "handleException" . "-" . $e);
            exit;
        }
    }


	public function currency($from_Currency,$to_Currency,$amount) {
		$amount = urlencode($amount);
		$from_Currency = urlencode($from_Currency);
		$to_Currency = urlencode($to_Currency);
		
		$fields_string = "template=9a&Amount=1&From=".$from_Currency."&To=".$to_Currency."&submit=Perform+Currency+Conversion";
			
		$url = "http://www.xe.com/ucc/convert.cgi";
		$ch = curl_init();
		$timeout = 0;
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch,CURLOPT_POST, 1);
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
		curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$rawdata = curl_exec($ch);
		curl_close($ch);
	
		$rawdata = preg_replace('/(\s\s+|\t|\n)/',' ', $rawdata);
	
		if(preg_match("#<TABLE CELLPADDING=5 CELLSPACING=0 BORDER=0>(.*?)<\/TABLE>#",$rawdata,$match2))
		{	
			if(preg_match("#<TD ALIGN=LEFT><FONT FACE=\"Arial,Helvetica\"> <FONT SIZE=\+1><B>(.*?)<\/B><\/FONT>#",$match2[1],$match3))
			{
				$rec = explode(" ",$match3[1]);
				return $rec[0];
			}
		}
	}
	
	public function getTaxWithRackRateFromRatePlan_new($propratetype,$chratetype,$appliedTax,$exchange_rate,$taxinfo_arr,$appDate,$appRate,$rackrate_arr,$number_adult,$base_adult,$roomrateunkid='')
	{
		try
		{
			$this->log->logIt($this->module."-"."getTaxWithRackRateFromRatePlan_new");
			
			$date_arr=explode(',',$appDate);
			$rate_arr=explode('<--->',$appRate);
			
			list($exchange_rate1,$exchange_rate2)=explode('|',$exchange_rate);
			
			$taxid=explode(',',$appliedTax);
						
			$finalrate_arr=array();
			$totalcnt=0;
			if(count($date_arr)>0 && count($rate_arr)>0)
			{
				for($d=0;$d<count($date_arr);$d++)
				{
					//$newdt=date('Y-m-d',strtotime($date_arr[$d]));
					
					$checkValue=explode("|",$rate_arr[$d]);

					$rateTax='';
					for($k=0;$k<count($checkValue);$k++)
					{
						if($checkValue[$k]!='' && $checkValue[$k]!='0')
						{
							$baseafterTax=0;
							list($baseTax,$baseTaxInfo,$baseTaxDesc)=array('0','','');
							
							//$this->log->logIt($this->module."-".$number_adult."|".$base_adult."|".$k."|".$checkValue[$k]."|".$checkValue[1]);
							
							if($number_adult>$base_adult && $k==0)
								$checkValue[$k] = $checkValue[$k] + (($number_adult - $base_adult) * $checkValue[1]);
							if($number_adult>$base_adult && $k>0)
								continue;
							
							//$this->log->logIt($this->module."-".$checkValue[$k]);
							$v_amount=$checkValue[$k];
							$callfor='base';
							if($k=='0')
								$callfor='base';
							elseif($k=='1')
								$callfor='extadult';
							elseif($k=='2')
								$callfor='extchild';
							
							$where='';  
							$taxretarr=array();
							$realamt=$v_amount;
							
							//$this->log->logIt($this->module."-"." CheckValue1 -> ".$v_amount);
							
							if(count($taxid) > 0 )
							{
								$totaltax=0; 
								$cnt_tax=0;
								$tax='';					
								$septax='';
								
								for($iCntTax=0;$iCntTax<count($taxid);$iCntTax++)
								{					
									$v_tamount=0;
									$v_camount=0;
									
									//$this->log->logIt($this->module."-"." Dates -> ".$date_arr[$d]."|".$taxinfo_arr[$taxid[$iCntTax]]['taxdate']);
									$applydate=date('Y-m-d',strtotime($taxinfo_arr[$taxid[$iCntTax]]['taxdate']));
									if($date_arr[$d]>=$applydate)
									{
										if($taxinfo_arr[$taxid[$iCntTax]]['applyonrackrate']=="1")
											$v_amount=$rackrate_arr[$roomrateunkid][$callfor];
										else
											$v_amount=$realamt;
										
										//$this->log->logIt($this->module."-"." applyonrackrate -".$taxinfo_arr[$taxid[$iCntTax]]['applyonrackrate']);
										//$this->log->logIt($this->module."-"." CheckValue -".$v_amount);
											
										$v_taxamount=$taxinfo_arr[$taxid[$iCntTax]]['amount'];
										$v_slab=$taxinfo_arr[$taxid[$iCntTax]]['slab'];							
										
										if($tax=='')
											$tax=$taxinfo_arr[$taxid[$iCntTax]]['tax'];
										else
											$tax=$tax.", ".$taxinfo_arr[$taxid[$iCntTax]]['tax'];
										
										$v_btaxapplyafter=0;
										$v_ctaxapplyafter=0;
										if($taxinfo_arr[$taxid[$iCntTax]]["taxapplyafter"]!='')
										{
											foreach($taxretarr as $taxkey=>$taxval)
											{
												if(preg_match(",".$taxkey.",",",".$taxinfo_arr[$taxid[$iCntTax]]["taxapplyafter"].","))
												{
													$v_btaxapplyafter+=$taxval[0];
													$v_ctaxapplyafter+=$taxval[1];
												}
											}
										}//end if($taxinfo_arr[$taxid[$iCntTax]]["taxapplyafter"]!='')
										
										if($taxinfo_arr[$taxid[$iCntTax]]['postingtype']=='FLATPERCENTAGE')
										{
											$v_tamount = (($v_amount+$v_btaxapplyafter) * $exchange_rate2/$exchange_rate1) * $v_taxamount / 100;
											$v_camount = ($v_amount+$v_ctaxapplyafter) * $v_taxamount / 100;	
											$totaltax += $v_camount;
											
											$taxretarr[$taxinfo_arr[$taxid[$iCntTax]]['taxunkid']]=array((($v_amount+$v_btaxapplyafter) * $exchange_rate2/$exchange_rate1) * $v_taxamount / 100,($v_amount+$v_ctaxapplyafter) * $v_taxamount / 100);
																	
										}
										else if($taxinfo_arr[$taxid[$iCntTax]]['postingtype']=='FLATAMOUNT')
										{
											if($taxinfo_arr[$taxid[$iCntTax]]['postingrule']!='')
											{
												$v_taxamount=0;
											}
											
											$v_tamount = $v_taxamount;
											$v_camount = $v_tamount * $exchange_rate1/$exchange_rate2;
											$totaltax += $v_camount;
											
											$taxretarr[$taxinfo_arr[$taxid[$iCntTax]]['taxunkid']]=array($v_taxamount,$v_camount);
										}
										else if($taxinfo_arr[$taxid[$iCntTax]]['postingtype']=='SLAB' && ($callfor!='extadult' && $callfor!='extchild'))
										{
											$v_taxslab = explode(",",$v_slab);	
											for($iSlab=0;$iSlab<count($v_taxslab);$iSlab++)
											{
												$v_taxslab1=explode("-",$v_taxslab[$iSlab]);
												$v_slabstart 		=  $v_taxslab1[0];
												$v_slabend 			=  $v_taxslab1[1];
												$v_slabpercent 		=  $v_taxslab1[2];
												
												if(($v_amount+$v_btaxapplyafter) >= $v_slabstart && ($v_amount+$v_btaxapplyafter) <= $v_slabend)
												{
													$v_tamount = (($v_amount+$v_btaxapplyafter) * $exchange_rate2/$exchange_rate1) * $v_slabpercent / 100;
													$v_camount = ($v_amount+$v_ctaxapplyafter) * $v_slabpercent / 100;
													$totaltax += $v_camount;	
													
													$taxretarr[$taxinfo_arr[$taxid[$iCntTax]]['taxunkid']]=array((($v_amount+$v_btaxapplyafter) * $exchange_rate2/$exchange_rate1) * $v_slabpercent / 100,($v_amount+$v_ctaxapplyafter) * $v_slabpercent / 100);
												}
											}			
										}#slab
									}//end if($date_arr[$d]>=$taxinfo_arr[$taxid[$iCntTax]]['taxdate'])
									$cnt_tax++;
								}//end for($iCntTax=0;$iCntTax<count($taxid);$iCntTax++)
							}//end if(count($taxid) > 0 )
							else
							{
								$totaltax=0; 
							}
							
							$rateTax.=($checkValue[$k]+$totaltax)."|";
						}//end if($checkValue[$k]!='' && $checkValue[$k]!='0')
						else{
							$rateTax.="0|";
						}
					}//end for($k=0;$k<count($checkValue);$k++)
					$rateTax=rtrim($rateTax,'|');
					$rateAfterTax=$rateTax;
					
					//$this->log->logIt($this->module."-Rate Tax String".$rateAfterTax);
					$finalrate_arr[$date_arr[$d]]=$rateAfterTax;
				}//end for($d=0;$d<count($date_arr);$d++)
			}//end if(count($date_arr)>0 && count($rate_arr)>0)
			
			return $finalrate_arr;
		}
		catch(Exception $e)
		{
			$this->log->logIt($this->module."-"."getTaxWithRackRateFromRatePlan_new"."-".$e);	
			return;
		}
	}//end public function getTaxWithRackRateFromRatePlan_new	


	function check_room_availability($roomurl,$out_date,$hotelId,$number_night,$caldateformat,$party_details)
	{
		$cnt = 0;
		foreach($party_details as $party)
		{
			$number_adult = $party->adults;
			
			$number_child = 0;
			if(isset($party->children))
				$number_child = sizeof($party->children);
			
			$data = array("checkin"=>$out_date,
				  "HotelId"=>$hotelId,
				  "nonights"=>$number_night,
				  "calendarDateFormat"=>$caldateformat,
				  "adults"=>$number_adult,
				  "child" =>$number_child,
				  "rooms" =>1,
				  "gridcolumn" => $number_night,
				  "modifysearch" => "false"
				 );
			

			$this->log->logIt("Request Data :- ".print_r($data,true));

			$ch=curl_init();
			curl_setopt($ch, CURLOPT_URL, $roomurl);
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS,  $data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			 
			$res='';
			$res=curl_exec($ch);
			$curlinfo = curl_getinfo($ch);
			$res = preg_replace('/(\s\s+|\t|\n)/', ' ',$res);
			$res = preg_replace('#<script.*</script>#is', '', $res);
			$res = trim(strip_tags($res));
			
			//~ echo $res;
			if($res == 'There are not enough rooms available for the selected dates.')				
				$not_avail_res[$cnt] = -1;
			else
				$not_avail_res[$cnt] = 1;

			++$cnt;
		}
		
		$this->log->logIt(' --------- reservation available array ------ ');
		$this->log->logIt(print_r($not_avail_res,true));
		
		return $not_avail_res;
		exit;
	}
}

?>
