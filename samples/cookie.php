<?php
/*
 * Simple helper to ensure we have a SheerID API Access Token for sample code/demos
 *
 * Copyright 2014 SheerID, Inc. or its affiliates. All Rights Reserved.
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

function getAccessTokenForSampleApp($cookie_name) {
	if ($_COOKIE[$cookie_name]) {
		return $_COOKIE[$cookie_name];
	} else if ($_POST[$cookie_name]) {
		$token = $_POST[$cookie_name];
		$sheer = new SheerID($token);
		if (!$sheer->isAccessible()) {
			echo "Token is not valid";
			die();
		}
		setcookie($cookie_name, $token);
		echo "Set cookie for sample app, refreshing...";
		echo "<meta http-equiv='refresh' content='1' />";
	} else { ?>
		<form method="POST">
			<label for="<?php echo $cookie_name; ?>">Enter your SheerID API access token:</label>
			<input id="<?php echo $cookie_name; ?>" name="<?php echo $cookie_name; ?>" />
			<button type="submit">Save</button>
		</form>
<?php
	}
	die();
}