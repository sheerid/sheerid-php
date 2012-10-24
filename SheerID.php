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
		$resp = $this->get("/field");
		return json_decode($resp["responseText"]);
	}

	function inquire($requestId) {
		$resp = $this->get("/verification/$requestId");
		return json_decode($resp["responseText"]);
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
	
	function listOrganizations($type=null, $name=null) {
		$params = array();
		if ($type) {
			$params["type"] = $type;
		}
		if ($name) {
			$params["name"] = $name;
		}
		$resp = $this->get("/organization", $params);
		return json_decode($resp["responseText"]);
	}
	
	function listAffiliationTypes() {
		$resp = $this->get("/affiliationType", array());
		return json_decode($resp["responseText"]);
	}
	
	function getAssetToken($request_id) {
		try {
			$resp = $this->post("/asset/token", array("requestId" => $request_id));
			$json = json_decode($resp["responseText"]);
			return $json->token;
		} catch (Exception $e) {
			var_dump($e);
			return null;
		}
	}

	// TODO: implement other service methods
	// ...
	
	/* helper methods */

	public function getFields($affiliation_types) {
		//TODO: use service
		$fields = array("FIRST_NAME", "LAST_NAME", "BIRTH_DATE");
		
		if (array_search("VETERAN", $affiliation_types) !== false) {
			$fields[] = "STATUS_START_DATE";
		}
		
		return $fields;
	}
	
	public function getOrganizationType($affiliation_types) {
		//TODO: improve / use service
		if (array_search("ACTIVE_DUTY", $affiliation_types) !== false || array_search("VETERAN", $affiliation_types) !== false) {
			return "MILITARY";
		} else {
			return "UNIVERSITY";
		}
	}
	
	/* utility methods */
	
	function get($path, $params=array()) {
		$req = new SheerIDRequest($this->accessToken, "GET", $this->url($path), $params, $this->verbose);
		return $req->execute();
	}
	
	function post($path, $params=array()) {
		$req = new SheerIDRequest($this->accessToken, "POST", $this->url($path), $params, $this->verbose);
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
			error_log("[SheerID] GET $url");
		}
		
		$data = curl_exec($ch);
		
		if(curl_errno($ch)){
			$err = curl_error($ch);
			curl_close($ch);
			
			echo $err;
			throw $err;
		} else {
			$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			
			if ($status != 200) {
				throw new Exception("Server returned status code: $status");
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