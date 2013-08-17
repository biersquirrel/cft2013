<?php
	require_once "base.php";
	require_once "support/twilio/Twilio.php";

	// Process the incoming request.  Stop number is ideal (no geocoding).
	$messages = array();
	if (!isset($_REQUEST["Body"]))  $messages[] = "Unknown request.  Try sending # followed by the stop number (e.g. #2888).";
	else
	{
		$body = trim(preg_replace('/\s+/', " ", $_REQUEST["Body"]));
		if (substr($body, 0, 1) == "#")
		{
			$id = (int)substr($body, 1);
			$result = GetRealtimeStopIDInformation($id);
			if (!$result["success"])  $messages[] = "Error:  " . $result["error"] . " (" . $result["errorcode"] . ")";
			else if (!count($result["info"]))  $messages[] = "The stop #" . $id . " is unable to be found in the database.";
			else
			{
				foreach ($result["info"] as $name => $routes)
				{
					$messages[] = $name;
					if (!count($routes))  $messages[] = "No routes/times";
					else
					{
						$routemap = array_keys($routes);
						natcasesort($routemap);
						foreach ($routemap as $route)
						{
							$messages[] = $route . " @ " . implode(", ", $routes[$route]);
						}
					}
				}
			}
		}
		else if (strtolower($body) == "help" || strtolower($body) == "info" || strtolower($body) == "information")
		{
			$messages[] = "Send a text message with # followed by a stop number to get the next three times (e.g. #2888).";
			$messages[] = "To find the nearest bus stops to a location, send a text message with the address.";
		}
		else
		{
			// Attempt to geocode the request.
			$address = $_REQUEST["Body"];
			if (stripos($address, "Tucson") === false)  $address .= ", Tucson";
			if (stripos($address, " AZ") === false)  $address .= " AZ";
			$result = GeocodeAddress($address);
			if (!$result["success"])  $messages[] = "Error:  " . $result["error"] . " (" . $result["errorcode"] . ")";
			else
			{
				// Find the nearest stops.
				$stops = array();
				$result2 = GetNearbyStopsRealtime($result["geo_latitude"], $result["geo_longitude"], 10);
				while ($row = $result2->NextRow())
				{
					if (!isset($stops[$row->stopid]))  $stops[$row->stopid] = array();
					$stops[$row->stopid][] = $row;
				}

				foreach ($stops as $rows)
				{
					$directions = array();
					foreach ($rows as $row)  $directions[$row->direction] = $row->direction;

					$messages[] = "Stop #" . $row->stopid . ":  " . $row->name . " (" . implode(", ", $directions) . "), " . sprintf("%.2f", $row->distance) . " miles";

					if (count($messages) == 3)  break;
				}

				if (!count($messages))  $messages[] = "Unable to find any stops near the specified address.  Try again later.";
			}
		}
	}

	$response = new Services_Twilio_Twiml();
	$line = "";
	foreach ($messages as $message)
	{
		if (strlen($line) + strlen($message) > 135)
		{
			if ($line != "")  $response->sms($line);
			$line = "";
		}

		if ($line != "")  $line .= " | ";
		$line .= $message;
		$line = trim(preg_replace('/\s+/', " ", $line));
	}
	if ($line != "")  $response->sms($line);

	echo $response;
?>