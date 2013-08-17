<?php
	require_once "base.php";

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	function DisplayError($msg)
	{
		echo $msg . "\n";

		exit();
	}

	$web = new WebBrowser();
	$web->SetState(array("referer" => "http://suntran.com/tmwebwatch/GoogleLiveMap", "autoreferer" => false));
	$result = $web->Process("http://suntran.com/tmwebwatch/Arrivals.aspx/getRoutes", "auto", array("method" => "POST", "body" => "", "headers" => array("Content-Type" => "application/json; charset=utf-8", "Accept" => "application/json, text/javascript, */*; q=0.01", "X-Requested-With" => "XMLHttpRequest")));

	if (!$result["success"])  DisplayError("Error retrieving URL.  " . $result["error"]);
	else if ($result["response"]["code"] != 200)  DisplayError("Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"]);

	$data = @json_decode($result["body"], true);

	$db->Query("TRUNCATE TABLE routes");

	foreach ($data["d"] as $item)
	{
		try
		{
			$name = preg_replace('/\s+/', " ", $item["name"]);
			$pos = strpos($name, "-");
			$rid = trim(substr($name, 0, $pos));
			$name = trim(substr($name, $pos + 1));
			$db->Query("INSERT INTO routes SET id = %d, internalid = %s, name = %s", $item["id"], $rid, $name);
		}
		catch (Exception $e)
		{
		}
	}

	$db->Query("TRUNCATE TABLE stops");

	$result = $db->Query("SELECT * FROM routes");
	while ($row = $result->NextRow())
	{
		echo "Processing '" . $row->internalid . " - " . $row->name . "'...\n";
		$result2 = $web->Process("http://suntran.com/tmwebwatch/GoogleMap.aspx/getStops", "auto", array("method" => "POST", "body" => json_encode(array("routeID" => $row->id)), "headers" => array("Content-Type" => "application/json; charset=utf-8", "Accept" => "application/json, text/javascript, */*; q=0.01", "X-Requested-With" => "XMLHttpRequest")));

		if (!$result2["success"])  DisplayError("Error retrieving URL.  " . $result2["error"]);
		else if ($result2["response"]["code"] != 200)  DisplayError("Error retrieving URL.  Server returned:  " . $result2["response"]["code"] . " " . $result2["response"]["meaning"]);

		$data = @json_decode($result2["body"], true);

		foreach ($data["d"] as $item)
		{
			try
			{
				$db->Query("INSERT INTO stops SET stopid = %d, timepointid = %d, directionid = %d, name = %s, type = %s, direction = %s, ordernum = %d, geo_latitude = %s, geo_longitude = %s", $item["stopID"], $item["timePointID"], $item["directionID"], trim($item["stopName"]), ($item["timePointID"] > 0 ? "Time point" : "Stop"), trim($item["directionName"]), $item["stopNumber"], (string)$item["lat"], (string)$item["lon"]);
			}
			catch (Exception $e)
			{
			}

			try
			{
				$db->Query("INSERT INTO route_stops SET routeid = %d, stopid = %d", $row->id, $item["stopID"]);
			}
			catch (Exception $e)
			{
			}
		}
	}
?>