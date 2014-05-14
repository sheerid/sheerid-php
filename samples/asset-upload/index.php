<?php
/*
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

include_once '../../SheerID.php';
include_once '../cookie.php';

$token = getAccessTokenForSampleApp('sheerid_access_token'); // this value should be hard-coded in your application or set via properties files e.g.
$sheer = new SheerID($token, SHEERID_ENDPOINT_SANDBOX);

if ($_GET['uploadStatus']) {
	if ('success' == $_GET['uploadStatus']) {
		$requestId = $_GET['requestId'];
		echo "<h1>Upload success</h1>";
		echo "<h3>Checking request...</h3>";
		echo "<meta http-equiv='refresh' content='3;url=?checkRequestId=$requestId' />";
		die();
	} else {
		echo "<h1>Upload failed</h1>";
		echo "<h3>Error Code: ". $_GET['error'] ."</h3>";
	}
} else if ($_GET['checkRequestId']) {
	$response = $sheer->inquire($_GET['checkRequestId']);
	if ('PENDING' == $response->status) {
?>
	<h1>Document review is still pending...</h1>
	<h3>Will check again shortly...</h3>
	<p>You may want to <a target="_blank" href="<?php echo SHEERID_ENDPOINT_SANDBOX; ?>/asset-review/">go review it now</a>.</p>
	<meta http-equiv='refresh' content='5' />
<?php
	} else {
		echo "<h1>".($response->result ? 'Verified via document review!' : 'Unable to verify via document review')."</h1>";
	}
	die();
} else if ($_POST) {
	$PARAMS = array('FIRST_NAME', 'LAST_NAME', 'BIRTH_DATE');
	$person = array();
	foreach ($PARAMS as $k) {
		$person[$k] = $_POST[$k];
	}
	try {
		$response = $sheer->verify($person, $_POST['organizationId'], array('affiliationTypes' => 'STUDENT_FULL_TIME,STUDENT_PART_TIME'));
	} catch (Exception $e) {
		echo "<h1>Error</h1>";
		echo $e->getMessage();
	}
	if ($response->result) { ?>
		<h1>Verified!</h1>
		<ul>
			<?php foreach ($response->affiliations as $aff) { ?>
				<li><?php echo $aff->type; ?></li>
			<?php } ?>
		</ul>
<?php
	} else {
		$asset_token = $sheer->getAssetToken($response->requestId);
		$currentUrl = ($_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
		<h1>Unable to verify</h1>
		<h3>Please upload supporting documentation</h3>
		<form enctype="multipart/form-data" method="POST" action="<?php echo $sheer->url('/asset'); ?>">
			<input type="hidden" name="token" value="<?php echo $asset_token; ?>"/>
			<input name="success" type="hidden" value="<?php echo $currentUrl.'?uploadStatus=success&requestId='.$response->requestId; ?>"/>
			<input name="failure" type="hidden" value="<?php echo $currentUrl.'?uploadStatus=failure'; ?>"/>
			<label>File: </label><input type="file" name="file"/>
			<button type="submit">Submit</button>
		</form>
<?php
		die();
	}
}?>

<form method="POST">
	<p><label for="organizationId">School:</label><br/>
	<select name="organizationId">
		<?php foreach ($sheer->listOrganizations("UNIVERSITY", "Oregon") as $org) { ?>
			<option value="<?php echo $org->id; ?>"><?php echo $org->name; ?></option>
		<?php } ?>
	</select></p>
	<p><label for="FIRST_NAME">First Name:</label><br/><input name="FIRST_NAME" id="FIRST_NAME" /></p>
	<p><label for="LAST_NAME">Last Name:</label><br/><input name="LAST_NAME" id="LAST_NAME" /></p>
	<p><label for="BIRTH_DATE">Birth Date (YYYY-MM-DD format):</label><br/><input name="BIRTH_DATE" id="BIRTH_DATE" /></p>
	<button type="submit">Submit</button>
</form>
