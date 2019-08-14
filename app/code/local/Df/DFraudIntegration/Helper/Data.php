<?php

/**
 * DFraud Integration plugin.
 *
 * @category	                Df
 * @package		Df_DFraudIntegration
 * @author		Biju Thajudien <mailtobiju@gmail.com>
 * @version		0.1.0
 */
 
class Df_DFraudIntegration_Helper_Data extends Mage_Core_Helper_Abstract
{
	/**
	 * Return the front end label as defined in config
	 *
	 * @return string
	*/
	public function getFrontendLabel()
	{
		return Mage::getStoreConfig('dfraudintegration_options/basic_settings/frontend_label');
	}
	
	public function getLicenceKey() {
		return Mage::getStoreConfig('dfraudintegration_options/basic_settings/licence_key');
	}
	
	public function getIpLocation($ip){
		$location_details = array();
	
		$geoIpUrl = 'http://www.geobytes.com/IpLocator.htm?GetLocation&template=php3.txt&IpAddress='.$ip;
		$tags = get_meta_tags($geoIpUrl);
		
		$location_details['ip'] = $ip;
		$location_details['known'] = $tags['known'];
		$location_details['country'] = $tags['country'];
		$location_details['region'] = $tags['region'];
		$location_details['regioncode'] = $tags['regioncode'];
		$location_details['city'] = $tags['city'];
		$location_details['latitude'] = $tags['latitude'];
		$location_details['longitude'] = $tags['longitude'];
		$location_details['timezone'] = $tags['timezone'];
		$location_details['country_id'] = $tags['iso2'];
		
		//print_r($tags);  
		
		return $location_details;
	}
	
	public function checkAddress($shippingId, $billingId, $ipLocation, $scores){
		$checks = array('region','postcode','lastname','street','city','email','telephone','country_id','firstname',
						'middlename');
		
		$ipChecks = array('region','city','country_id');
		
		$mismatch = array();
		
		$shippingDetails = Mage::getModel('sales/order_address')->load($shippingId);
		$billingDetails = Mage::getModel('sales/order_address')->load($billingId);
		
		//check if the address exist with google geocode
		//$this->p($scores);
		$shippingGeoResult = $this->checkAddressExist($shippingDetails);
		$billingGeoResult = $this->checkAddressExist($billingDetails);
		
		$mismatch['shippingGeoResult'] = $shippingGeoResult;
		$mismatch['billingGeoResult'] = $billingGeoResult;
		
		//$this->p($shippingGeoResult);$this->p($billingGeoResult);
		//Distance between billing and shipping address
		$mismatch['dis']['bill_ship'] = $this->distance($shippingGeoResult['loc']['location'], $billingGeoResult['loc']['location']);
		
		$ipLatLon['lat'] = $ipLocation['latitude'];
		$ipLatLon['lng'] = $ipLocation['longitude'];
		$mismatch['dis']['ip_ship'] = $this->distance($ipLatLon, $shippingGeoResult['loc']['location']);
		
		$mismatch['dis']['ip_bill'] = $this->distance($ipLatLon, $billingGeoResult['loc']['location']);
				
		$max_distance = Mage::getStoreConfig('dfraudintegration_options/dfraud_module_settings/maximum_distance');
		$mismatch['dis']['score']['total'] = 0;
		if($mismatch['dis']['ip_bill'] > $max_distance) {
			$mismatch['dis']['score']['total'] = $this->getScore($scores,'dis_bill_ip');
			$summary['dis_bill_ip'] = 1;
		}
		$mismatch['dis']['score']['max'] = $this->getModuleTotalScore($scores,array('dis_bill_ip'));
		
		//check if billing and shipping address match
		$addressDiff = false;
		foreach($checks as $param){
			if($shippingDetails[$param] != $billingDetails[$param]) {
				$addressDiff = true;
				$mismatch['address'][$param]['billing'] = $billingDetails[$param];
				$mismatch['address'][$param]['shipping'] = $shippingDetails[$param];
			}
		}
		
		$mismatch['address']['score']['total'] = 0;
		if($addressDiff) {
			$mismatch['address']['score']['total'] = $this->getScore($scores,'address_bill_ship');
			$summary['address_bill_ship'] = 1;
		}
		$mismatch['address']['score']['max'] = $this->getModuleTotalScore($scores,array('address_bill_ship'));
		
		//compare ip address with billing and shipping address
		$mismatch['ip']['location'] = $ipLocation;
		$mismatch['ip']['location']['loc_str'] = $ipLocation['city'].", ".$ipLocation['region'].", ".$ipLocation['country'];
		foreach($ipChecks as $param){
			if(! $this->isStringEqual($ipLocation[$param], $billingDetails[$param])) {
				$mismatch['ip']['ip_bill_diff'][$param] = 1;
				$mismatch['ip']['score'][$param] = $this->getScore($scores,'ip_bill_'.$param);
				$summary['ip_bill_'.$param] = 1;
			}
			if(! $this->isStringEqual($ipLocation[$param], $shippingDetails[$param])) {
				$mismatch['ip']['ip_ship_diff'][$param] = 1;
			}
			//if($ipLocation[$param] != $billingDetails[$param]) {
				$mismatch['ip'][$param]['ip'] = $ipLocation[$param];
				$mismatch['ip'][$param]['billing'] = $billingDetails[$param];
			//}
			//if($ipLocation[$param] != $shippingDetails[$param]) {
				$mismatch['ip'][$param]['ip'] = $ipLocation[$param];
				$mismatch['ip'][$param]['shipping'] = $shippingDetails[$param];
			//}
		}
		
		
		$total = 0;
		foreach($mismatch['ip']['score'] as $ip_scores){
			$total += $ip_scores;
		}
		$module_max = $this->getModuleTotalScore($scores, array('ip_bill_city','ip_bill_region','ip_bill_country_id'));
		$mismatch['ip']['score']['total'] = $total;
		$mismatch['ip']['score']['max'] = $module_max;
		
		
		//check high risk country
		$resource = Mage::getSingleton('core/resource');
		$readConnection = $resource->getConnection('core_read');
		$query = 'SELECT * FROM ' . $resource->getTableName('dfraudintegration/highriskcountries');
		$results = $readConnection->fetchAll($query);
		
		$country_id = 'country_id';
		foreach($results as $highRisk) {
			//	var_dump($highRisk);
			if($ipLocation[$country_id] == $highRisk[$country_id]) {
				$mismatch['hrc']['ip'] = true; 
				$mismatch['hrc']['score']['ip'] = $this->getScore($scores,'hr_ip');
				$summary['hr_ip'] = 1;
			}
			if($shippingDetails[$country_id] == $highRisk[$country_id]) {
				$mismatch['hrc']['shipping'] = true; 
				$mismatch['hrc']['score']['shipping'] = $this->getScore($scores,'hr_ship');
				$summary['hr_ship'] = 1;
			}
			if($billingDetails[$country_id] == $highRisk[$country_id]) {
				$mismatch['hrc']['billing'] = true; 
				$mismatch['hrc']['score']['billing'] = $this->getScore($scores,'hr_bill');
				$summary['hr_bill'] = 1;
			}
		}
		
		$hrctotal = 0;
		foreach($mismatch['hrc']['score'] as $hrcscore) {
			$hrctotal += $hrcscore;
		}
		$mismatch['hrc']['score']['total'] = $hrctotal;
		$mismatch['hrc']['score']['max'] = $this->getModuleTotalScore($scores,array('hr_ip','hr_ship','hr_bill'));
		
		//Email valid check
		$email_result = $this->checkEmailValid($order->customer_email);
		$mismatch['email'] = $email_result;
		
		//Check postal codes
		$post_loc_bill = $this->checkPostalCode($billingDetails['postcode'],$billingDetails['country_id']);
		
		//print_r($post_loc_bill);
		if(!empty($post_loc_bill['postalcodes'])) {
			foreach($post_loc_bill['postalcodes'] as $postCode) {
				if($this->isStringContains($billingDetails['street'], $postCode['placeName'])){
					$loc = $postCode['placeName'];
					$loc .= ", ".$postCode['adminName1'];
					$loc .= ", ".$postCode['countryCode'];
					break;
				}
			}
			if($loc != ""){
				$mismatch['post_loc']['billing'] = $loc;
			} else {
				$loc = "<strong>Address and postal code exact match not found.<br>Matches for postal code (". 
						$post_loc_bill['postalcodes'][0]['postalcode']."):</strong><br>";
				foreach($post_loc_bill['postalcodes'] as $postCode) {
					$loc .= $postCode['placeName'];
					if(!is_null($postCode['adminName1']))
						$loc .= ", ".$postCode['adminName1'];
					$loc .= ", ".$postCode['countryCode'];
					$loc .= "<br>";
				}
				$mismatch['post_loc']['billing'] = $loc;
			}
			
		} else {
			$mismatch['post_loc']['billing'] = "NOT FOUND";
		}
		
		if($mismatch['post_loc']['billing'] == "NOT FOUND") {
			$mismatch['post_loc']['score']['billing'] = $this->getScore($scores, 'zip_bill');
			$summary['zip_bill'] = 1;
		}
		
		if($billingDetails['postcode'] != $shippingDetails['postcode']
			|| $billingDetails['country_id'] != $shippingDetails['country_id']) {
			
			$post_loc_ship = $this->checkPostalCode($shippingDetails['postcode'],$shippingDetails['country_id']);
			//print_r($post_loc_ship);
			$loc = "";
			if(!empty($post_loc_ship['postalcodes'])) {
				foreach($post_loc_ship['postalcodes'] as $postCode) {
					if($this->isStringContains($shippingDetails['street'], $postCode['placeName'])){
						$loc = $postCode['placeName'];
						$loc .= ", ".$postCode['adminName1'];
						$loc .= ", ".$postCode['countryCode'];
						break;
					}
				}
				if($loc != ""){
					$mismatch['post_loc']['shipping'] = $loc;
				} else {
					$loc = "<strong>Address and postal code exact match not found. <br>Matches for postal code (". 
											$post_loc_ship['postalcodes'][0]['postalcode']."):</strong><br>";
					foreach($post_loc_ship['postalcodes'] as $postCode) {
						$loc .= $postCode['placeName'];
						if(!is_null($postCode['adminName1']))
							$loc .= ", ".$postCode['adminName1'];
						$loc .= ", ".$postCode['countryCode'];
						$loc .= "<br>";
					}
					$mismatch['post_loc']['shipping'] = $loc;
				}
			} else {
				$mismatch['post_loc']['shipping'] = "NOT FOUND";
			}
			
		} else {
			$mismatch['post_loc']['shipping'] = $mismatch['post_loc']['billing'];
		}
		
		if($mismatch['post_loc']['shipping'] == "NOT FOUND") {
			$mismatch['post_loc']['score']['shipping'] = $this->getScore($scores, 'zip_ship');
			$summary['zip_ship'] = 1;
		}
		
		$ziptotal = 0;
		foreach($mismatch['post_loc']['score'] as $zipscore) {
			$ziptotal += $zipscore;
		}
		$mismatch['post_loc']['score']['total'] = $ziptotal;
		$mismatch['post_loc']['score']['max'] = $this->getModuleTotalScore($scores,array('zip_bill','zip_ship'));
		
		$mismatch['summary'] = $summary;
		//$this->p($mismatch);exit;
		return $mismatch;
	}
	
	public function getOrderHistory($custId, $ip, $scores){
		//Get the order history of the customer
		$orders = Mage::getResourceModel('sales/order_collection')
   			 	->addFieldToSelect('*')
    			->addFieldToFilter('customer_id', $custId);
		$order_count = count($orders->getItems());
		
		$orders_ip = Mage::getResourceModel('sales/order_collection')
   			 	->addFieldToSelect('*')
    			->addFieldToFilter('remote_ip', $ip);
		$order_count_ip = count($orders_ip->getItems());
		
		$resource = Mage::getSingleton('core/resource');
		$readConnection = $resource->getConnection('core_read');
		$query = 'SELECT * FROM ' . $resource->getTableName('sales/order_status');
		$statuss = $readConnection->fetchAll($query);
		
		
		//$this->p($results);
		$order_status['count'] = $order_count;
		foreach($statuss as $status) {
			$order_status['status'][$status['label']] = 0;
			foreach($orders as $order) {
				if($order->getStatus() == $status['status']) {
					$order_status['status'][$status['label']] ++;
				}
			}
		}
		
		$order_status_ip['count'] = $order_count_ip;
		foreach($statuss as $status) {
			$order_status_ip['status'][$status['label']] = 0;
			foreach($orders_ip as $order) {
				if($order->getStatus() == $status['status']) {
					$order_status_ip['status'][$status['label']] ++;
				}
			}
		}
		
		$user_orders = array("user" => $order_status,"ip"=>$order_status_ip);
		
		$user_orders['count']['score']['order_hist_count_ip_user'] = 0;
		if($order_status_ip['count'] != $order_status['count'] ) {
			$user_orders['count']['score']['order_hist_count_ip_user'] = $this->getScore($scores, 'order_hist_count_ip_user');
		}
							
		$fraudLabels = array('Suspected Fraud');					
		foreach($fraudLabels as $fraudLabel) {
			if($order_status_ip['status'][$fraudLabel] > 0) {
				$user_orders['ip']['score']['order_hist_fraud'] = $this->getScore($scores, 'order_hist_fraud');
				$summary['order_hist_fraud'] = 1;
			}
		}
		
		foreach($fraudLabels as $fraudLabel) {
			if($order_status['status'][$fraudLabel] > 0) {
				$user_orders['user']['score']['order_hist_fraud'] = $this->getScore($scores, 'order_hist_fraud');
				$summary['order_hist_fraud'] = 1;
			}
		}
		
		$total = $user_orders['count']['score']['order_hist_count_ip_user'] +
			( $user_orders['ip']['score']['order_hist_fraud'] > 0 ? $user_orders['ip']['score']['order_hist_fraud'] : 	
			$user_orders['user']['score']['order_hist_fraud'] );
		
		if($order_count == 1) {
			$total += $this->getScore($scores, 'order_hist_first_order');
			$summary['order_hist_first_order'] = 1;
		}
		
		$user_orders['score']['total'] = $total;
		$user_orders['score']['max'] = $this->getModuleTotalScore($scores,array('order_hist_count_ip_user',
																				'order_hist_fraud',
																				'order_hist_first_order'));
		$user_orders['summary'] = $summary;
		//$this->p($user_orders);exit;
		
		return $user_orders;
	}
	
	private function isStringContains($major, $minor){
		$major = str_replace(' ','',$major);
		$minor = str_replace(' ','',$minor);
		
		return strpos(strtoupper($major), strtoupper($minor));
	}
	
	private function isStringEqual($major, $minor){
		$major = str_replace(' ','',$major);
		$minor = str_replace(' ','',$minor);
		
		return strcasecmp($major,$minor) == 0 ? true : false;
	}
	
	public function checkPostalCode($postcode,$country) {
		// Build validation request
		$Params = array('postalcode' => $postcode,
						'country' => $country,
						'username' => 'dfraud');
		$Request = @http_build_query($Params);
		$ctxData = array(
			 'method' => "GET",
			 'header' => "Connection: close\r\n".
			 "Content-Length: ".strlen($Request)."\r\n",
			 'content'=> $Request);
		$ctx = @stream_context_create(array('http' => $ctxData));
		
		$api = "http://api.geonames.org/postalCodeLookupJSON?".$Request;
		$json =  @file_get_contents($api, false, null);
		$result = json_decode($json, true);
		
		return $result;
	}
	
	public function checkEmailValid($email) {
		// Build validation request
		$Params = array('email' => $email,
						'api' => '987588a43b3');
		$Request = @http_build_query($Params);
		$ctxData = array(
			 'method' => "GET",
			 'header' => "Connection: close\r\n".
			 "Content-Length: ".strlen($Request)."\r\n",
			 'content'=> $Request);
		$ctx = @stream_context_create(array('http' => $ctxData));
		
		// Check validation result
		$APIUrl = 'http://123airtime.com/email_verify/email_verifier.php?'.$Request;
		$json =  @file_get_contents($APIUrl, false, null);
		$json = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $json);
		
		$result = json_decode($json, true);
		
		//print_r($result);
		return $result['result'];
	}
	public function p($data){
		echo("<pre>");
		print_r($data);
	}
	
	private function checkAddressExist($address){
		
		$addressStr = $address->street;
		if(!is_null($address->city)) {
			$addressStr .= ','.$address->city;
		}
		if(!is_null($address->region)) {
			$addressStr .= ','.$address->region;
		}
		if(!is_null($address->country_id))	{
			$addressStr .=','.$address->country_id;
		}
		$api = "http://maps.googleapis.com/maps/api/geocode/json?address=";
		$APIUrl = $api.urlencode($addressStr).'&sensor=true';
		$json =  @file_get_contents($APIUrl, false, null);
		$result_loc = json_decode($json,true);
		
		if($result_loc['status'] == 'ZERO_RESULTS') {
			$street = explode("\n", $address->street);
			$addressStr = $street[1].','.$address->city.','.$address->country_id;
			$APIUrl = $api.urlencode($addressStr).'&sensor=true';
			$json =  @file_get_contents($APIUrl, false, null);
			$result_street = json_decode($json,true);
			
			if($result_street['status'] == 'ZERO_RESULTS') {
				$addressStr = $address->city.','.$address->country_id;
				$APIUrl = $api.urlencode($addressStr).'&sensor=true';
				$json =  @file_get_contents($APIUrl, false, null);
				$result_city = json_decode($json,true);
				
				if($result_city['status'] == 'ZERO_RESULTS') {
					$address_result['status'] = 0;
					$address_result['type'] = 'City';
				} else {
					$address_result['status'] = 1;
					$address_result['type'] = 'City';
					$address_result['loc'] = $result_city['results'][0]['geometry']['location'];
					$address_result['formatted_address'] = $result_city['results'][0]['formatted_address'];
					$address_result['url'] = $APIUrl;
				}
			} else {
				$address_result['status'] = 1;
				$address_result['type'] = 'Street';
				$address_result['loc'] = $result_street['results'][0]['geometry']['location'];
				$address_result['formatted_address'] = $result_street['results'][0]['formatted_address'];
				$address_result['url'] = $APIUrl;
			}
		} else {
			$address_result['status'] = 1;
			$address_result['type'] = 'Full';
			$address_result['loc'] = $result_loc['results'][0]['geometry'];
			$address_result['formatted_address'] = $result_loc['results'][0]['formatted_address'];
			$address_result['url'] = $APIUrl;
			
		}
		//$this->p($addressStr);
		return $address_result;
	}
	
	private function distance($loc1, $loc2) {
		$pi80 = M_PI / 180;
		$lat1 = $loc1['lat']; $lng1 = $loc1['lng'];
		$lat2 = $loc2['lat']; $lng2 = $loc2['lng'];
		
		$lat1 *= $pi80;
		$lng1 *= $pi80;
		$lat2 *= $pi80;
		$lng2 *= $pi80;
	
		$r = 6372.797; // mean radius of Earth in km
		$dlat = $lat2 - $lat1;
		$dlng = $lng2 - $lng1;
		$a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
		$c = 2 * atan2(sqrt($a), sqrt(1 - $a));
		$km = $r * $c;
	
		return ceil($km);
	}
	
	private function getScore($scores, $key){
		foreach($scores as $score) {
			if($score['field'] == $key) {
				return $score['score'];
			}
		}
	}
	
	private function getModuleTotalScore($scores, $keys){
		foreach($scores as $score) {
			if(in_array($score['field'],$keys)) {
				$total += $score['score'];
			}
		}
		return $total;
	}
	
	public function getBinData($payments, $ip, $country_id, $scores){
		
		foreach($payments as $pay) {
        	$payData = $pay->getData();
        	$ccEncrypt = $payData['cc_number_enc'];
			if(isset($ccEncrypt)) {
        		$ccBinNo = substr($pay->decrypt($ccEncrypt), 0, 6);    
				$encyptedBin = $this->encrypt($ccBinNo);
				//echo "Encrypted=".$encypted;
				//echo "Decypted=".$this->decrypt($encypted);exit;
			
				$params = array('apikey' => $this->getLicenceKey(),
								'ccBin' => $encyptedBin,
								'ip' => $ip,
								'country' => $country_id);
				$url = 'http://ecomshopsecurity.com/dfraud_checks/bin/BinData.php';
				$result_api = $this->getApiResult($params, $url);
				
				/*$result_api = array('bin'=>
									array('binCountry' => 'US', 'binName' => 'State Bank of India', 
										  'binPhone' => '123456'),
									'lic'=>array('result'=>0, 'err'=>"User request exceeded free limit of 50 requests. <br/>Please update your licence from<br/>
		<a href=\"http://ecomshopsecurity.com/index.php/dfraud-integration.html\" target=\"_blank\">http://ecomshopsecurity.com/index.php/dfraud-integration.html</a>"));*/
				
				$result = $result_api['bin'];
				if($result_api['lic']['result'] == 1) {
					if($country_id != $result['binCountry']) {
						$result['country_match'] = "NO";
						$result['score']['total'] = $this->getScore($scores, 'bin_country');
						$summary['bin_country'] = 1;
					} else {
						$result['score']['total'] = 0;
						$result['country_match'] = "YES";
					}
				} else {
					$result = array('binCountry' => '-', 'binName' => '-', 'binPhone' => '-');
					$result['country_match'] = "-";
					$result['score']['total'] = 0;
					$result['non_cc'] = 1;
					$result['err'] = $result_api['lic']['err'];
				}
				
			} else {
				$result = array('binCountry' => '-', 'binName' => '-', 'binPhone' => '-');
				$result['country_match'] = "-";
				$result['score']['total'] = 0;
				$result['non_cc'] = 1;
			}
		}
		
		$result['score']['max'] = $this->getModuleTotalScore($scores,array('bin_country'));
		$result['summary'] = $summary;
		
		//$this->p($result); exit;
		return $result;
	} 
	
	private function encrypt($str) {
		$key = "80cdc815";
		$block = mcrypt_get_block_size('des', 'ecb');
		$pad = $block - (strlen($str) % $block);
		$str .= str_repeat(chr($pad), $pad);
	
		$ecrypt = mcrypt_encrypt(MCRYPT_DES, $key, $str, MCRYPT_MODE_ECB);
		
		return base64_encode($ecrypt);
	}
	
	public function getApiResult($params, $url) {
		$Request = @http_build_query($params);
		$ctxData = array(
			 'method' => "GET",
			 'header' => "Connection: close\r\n".
			 "Content-Length: ".strlen($Request)."\r\n",
			 'content'=> $Request);
		$ctx = @stream_context_create(array('http' => $ctxData));
			
		$APIUrl = $url.'?'.$Request;
		
		$json =  @file_get_contents($APIUrl, false, null);
		$json = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $json);
			
		return json_decode($json, true);
	
	}
	
	public function getResultTotalScore($results) {
		
		$total = 0; $max = 0;
		foreach($results as $key => $a_result) {
			if(is_array($a_result)) {
				$total += $a_result['score']['total'];
				$max += $a_result['score']['max'];
			}
		}
		$m_result = array();
		$m_result['total'] = $total;
		$m_result['max'] = $max;
		
		return $m_result;
	}
	
	public function getRiskScoreDescription($scores, $fields) {
		$i = 0;
		foreach($fields as $key=>$field) {
			foreach($scores as $score) {
				if($score['field'] == $key) {
					$summary['issues'][$i]['desc'] = $score['description'];
					$summary['issues'][$i++]['risk'] = $score['risk'];
				}
			}
		}
		
		$j = 0;
		foreach($summary['issues'] as $summ) {
			$risks[$j++] =  $summ['risk'];
		}
		
		if(in_array('HIGH', $risks)) {
			$summary['risk'] = 'HIGH';
		} else if(in_array('MEDIUM', $risks)){
			$summary['risk'] = 'MEDIUM';
		} else {
			$summary['risk'] = 'LOW';
		}
		
		
		return $summary;
	}
	
	public function checkOrderAmount($amount, $scores) {
		$max_amount = Mage::getStoreConfig('dfraudintegration_options/dfraud_module_settings/maximum_order_amount');
		$result['amount'] = $amount;
		$result['max_amount'] = $max_amount;
		$result['score']['total'] = 0;
		if($amount > $max_amount) {
			$result['amount_higher'] = "YES";
			$result['score']['total'] = $this->getScore($scores, 'order_amount_avg');
			$summary['order_amount_avg'] = 1;
		} else {
			$result['amount_higher'] = "NO";
		}
		$result['score']['max'] = $this->getModuleTotalScore($scores,array('order_amount_avg'));
		$result['summary'] = $summary;
		
		//$this->p($result);exit;
		return $result;
	}
}