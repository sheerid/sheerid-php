<?php
/*
 * Copyright 2012 SheerID, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at:
 *
 *  http://www.apache.org/licenses/LICENSE-2.0.html
 *
 * This file is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR 
 * CONDITIONS OF ANY KIND, either express or implied. See the License for
 * the specific language governing permissions and limitations under the
 * License.
 * 
 * For more information, visit:
 *
 *  http://developer.sheerid.com
 *
 */

define('SHEERID_API_VERSION', 0.5);
define('SHEERID_ENDPOINT_SANDBOX', 'https://services-sandbox.sheerid.com');
define('SHEERID_ENDPOINT_PRODUCTION', 'https://services.sheerid.com');

class SheerID {
	
	var $accessToken;
	var $baseUrl;
	var $verbose;
	
	function SheerID($accessToken, $baseUrl=null, $verbose=false){
		$this->accessToken = $accessToken;
		$this->baseUrl = $baseUrl ? $baseUrl : SHEERID_ENDPOINT_SANDBOX;
		$this->verbose = $verbose;
	}
	
	function listFields() {
		return $this->getJson("/field");
	}

	function inquire($requestId) {
		return $this->getJson("/verification/$requestId");
	}
	
	function verify($data, $org_id=null, $config=array()) {
		$config_prefix = "_";
		$post_data = array();
		
		// add config data w/ prefix to request
		foreach ($config as $k => $v) {
			$post_data["${config_prefix}${k}"] = $v;
		}
		
		// add personal info fields
		foreach ($data as $k => $v) {
			$post_data[$k] = $v;
		}
		
		// add organization ID
		if ($org_id) {
			$post_data["organizationId"] = $org_id;
		}
		
		$resp = $this->post("/verification", $post_data);
		return json_decode($resp["responseText"]);
	}
	
	function updateVerification($requestId, $data) {
		$post_data = array();
		foreach ($data as $k => $v) {
			$post_data[$k] = $v;
		}
		$resp = $this->post("/verification/$requestId", $post_data);
		return json_decode($resp["responseText"]);
	}
	
	function listOrganizations($type=null, $name=null) {
		$params = array();
		if ($type) {
			$params["type"] = $type;
		}
		if ($name) {
			$params["name"] = $name;
		}
		return $this->getJson("/organization", $params);
	}
	
	function listAffiliationTypes() {
		return $this->getJson("/affiliationType");
	}
	
	function listAssets($request_id) {
		try {
			$resp = $this->get("/verification/${request_id}/assets");
			return json_decode($resp["responseText"]);
		} catch (Exception $e) {
			return null;
		}
	}
	
	function getAssetToken($request_id) {
		try {
			$resp = $this->post("/asset/token", array("requestId" => $request_id));
			$json = json_decode($resp["responseText"]);
			return $json->token;
		} catch (Exception $e) {
			return null;
		}
	}
	
	function revokeToken($token) {
		try {
			$resp = $this->delete("/token/$token");
			return $resp["status"] == 204;
		} catch (Exception $e) {
			return false;
		}
	}
	
	function updateMetadata($requestId, $meta) {
		try {
			$this->post("/verification/${requestId}/metadata", $meta);
		} catch (Exception $e) {}
	}
	
	function updateOrderId($requestId, $orderId) {
		$this->updateMetadata($requestId, array("orderId" => $orderId));
	}

	// TODO: implement other service methods
	// ...
	
	/* helper methods */

	public function getFields($affiliation_types) {
		//TODO: use service
		$fields = array("FIRST_NAME", "LAST_NAME");
		
		if (count(array_intersect(array('STUDENT_FULL_TIME','STUDENT_PART_TIME','ACTIVE_DUTY','VETERAN','MILITARY_RETIREE','RESERVIST'), $affiliation_types))) {
                        $fields[] = 'BIRTH_DATE';
                }
                if (array_search('FACULTY', $affiliation_types) !== FALSE) {
                        $fields[] = 'POSTAL_CODE';
                }
		if (count(array_intersect(array('VETERAN','MILITARY_RETIREE','RESERVIST'), $affiliation_types))) {
			$fields[] = "STATUS_START_DATE";
		}
		if (array_search('NON_PROFIT', $affiliation_types) !== FALSE) {
			$fields[] = 'ID_NUMBER';
		}
		if (array_search('MILITARY_FAMILY', $affiliation_types) !== FALSE) {
			$fields[] = 'RELATIONSHIP';
		}
 
                return $fields;
	}
	
	public function getOrganizationType($affiliation_types) {
		//TODO: improve / use service
		if (count(array_intersect(array('ACTIVE_DUTY','VETERAN','MILITARY_RETIREE','RESERVIST','MILITARY_FAMILY'), $affiliation_types))) {
			return "MILITARY";
		} else if (count(array_intersect(array('STUDENT_FULL_TIME','STUDENT_PART_TIME','FACULTY'), $affiliation_types))) {
			return "UNIVERSITY";
		}
		// TODO: map other types
		return null;
	}
	
	/* utility methods */
	
	function get($path, $params=array()) {
		$req = new SheerIDRequest($this->accessToken, "GET", $this->url($path), $params, $this->verbose);
		return $req->execute();
	}

	function getJson($path, $params=array()) {
		$resp = $this->get($path, $params);
		return json_decode($resp["responseText"]);
	}
	
	function post($path, $params=array()) {
		$req = new SheerIDRequest($this->accessToken, "POST", $this->url($path), $params, $this->verbose);
		return $req->execute();
	}
	
	function delete($path) {
		$req = new SheerIDRequest($this->accessToken, "DELETE", $this->url($path), array(), $this->verbose);
		return $req->execute();
	}
	
	function url($path='') {
		return sprintf("%s/rest/%s%s", $this->baseUrl, SHEERID_API_VERSION, $path);
	}
}

class SheerIDRequest {
	var $method;
	var $url;
	var $params;
	var $headers;
	var $verbose;
	
	function SheerIDRequest($accessToken, $method, $url, $params=array(), $verbose=false) {
		$this->method = $method;
		$this->url = $url;
		$this->params = $params;
		$this->headers = array("Authorization: Bearer $accessToken");
		$this->verbose = $verbose;
	}
	
	function execute() {
		$ch = curl_init();
		
		$url = $this->url;
		$query = $this->getQueryString();
		if ("GET" === $this->method && $query) {
			$url .= "?$query";
		}
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers); 
		
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSLVERSION, 3);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		
		if ("POST" === $this->method){
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
			
			if ($this->verbose) {
				error_log("[SheerID] POST $url $query");
			}
		} else if ($this->verbose) {
			error_log(sprintf("[SheerID] %s %s", $this->method, $url));
		}
		
		if ("DELETE" === $this->method) {
	        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		}

		$data = curl_exec($ch);
		
		if(curl_errno($ch)){
			$err = curl_error($ch);
			curl_close($ch);
			throw new Exception($err);
		} else {
			$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			
			if ($status != 200 && $status != 204) {
				throw new Exception("Server returned status code: $status for url: $url");
			}

			$response = array(
				"status" => $status,
				"responseText" => $data
			);
			curl_close($ch);
			
			return $response;
		}
	}
	
	function getQueryString() {
		$parts = array();
		foreach ($this->params as $k => $v) {
			$parts[] = urlencode($k) . "=" . urlencode($v);
		}
		return implode("&", $parts);
	}
}
