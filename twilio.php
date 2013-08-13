<?php
	require_once "base.php";
	require_once "support/twilio/Twilio.php";

	$response = new Services_Twilio_Twiml();
	$response->sms("Hi.  A response would go here.");

	echo $response;

//	$_REQUEST["Body"];
?>