<?php
	require_once "support/http.php";
	require_once "support/web_browser.php";
	require_once "base.php";

	function DisplayError($msg)
	{
		echo $msg . "\n";

		exit();
	}

	$web = new WebBrowser();
	$result = $web->Process("http://busted.kitplummer.apigee.com/beta/routes.json");

	if (!$result["success"])  DisplayError("Error retrieving URL.  " . $result["error"]);
	else if ($result["response"]["code"] != 200)  DisplayError("Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"]);

	$data = @json_decode($result["body"], true);

	foreach ($data["routes"] as $item)
	{
		try
		{
			$db->Query("INSERT INTO routes SET internalid = %s, name = %s", $item["id"], $item["name"]);
		}
		catch (Exception $e)
		{
		}
	}

	function ParseTime($date, $time)
	{
		$date = explode("-", $date);

		$pos = strpos($time, ":");
		$hour = (int)substr($time, 0, $pos);
		$time = substr($time, $pos + 1);
		$pos = strpos($time, " ");
		$min = (int)substr($time, 0, $pos);
		$ampm = strtolower(substr($time, $pos + 1));

		if ($ampm == "am" && $hour == 12)  $hour = 0;
		else if ($ampm == "pm" && $hour != 12)  $hour += 12;

		return mktime($hour, $min, 0, $date[1], $date[2], $date[0]);
	}

	$routemap = array();
	$result = $db->Query("SELECT * FROM routes");
	while ($row = $result->NextRow())
	{
		$url = "http://busted.kitplummer.apigee.com/beta/route/" . urlencode($row->internalid) . ".json";
		echo $url . "\n";

		$result2 = $web->Process($url);
		if (!$result2["success"])  DisplayError("Error retrieving URL.  " . $result2["error"]);
		else if ($result2["response"]["code"] != 200)  DisplayError("Error retrieving URL.  Server returned:  " . $result2["response"]["code"] . " " . $result2["response"]["meaning"]);

		$data = @json_decode($result2["body"], true);

		$stops = array();
		$result2 = $db->Query("SELECT * FROM stops");
		while ($row2 = $result2->NextRow())
		{
			$stops[$row2->name . "|" . $row2->type . "|" . $row2->direction] = $row2->id;
		}

		try
		{
			$db->Query("DELETE FROM departures WHERE rid = %d", $row->id);
		}
		catch (Exception $e)
		{
		}

		// Why are Stations and Stops separate?  (Should be a single array with a 'type'.)
		foreach ($data["Route"]["stations"] as $station)
		{
			$station = $station["Station"];

			if (!isset($stops[$station["name"] . "|Station|" . $station["direction"]]))
			{
				try
				{
					$db->Query("INSERT INTO stops SET internalid = 0, name = %s, type = 'Station', direction = %s, ordernum = 0, geo_latitude = %s, geo_longitude = %s", $station["name"], $station["direction"], $station["lat"], $station["lng"]);
					$stops[$station["name"] . "|Station|" . $station["direction"]] = $db->GetInsertID();
				}
				catch (Exception $e)
				{
				}
			}

			foreach ($station["departures"] as $time)
			{
				try
				{
					$db->Query("INSERT INTO departures SET rid = %d, sid = %d, departure = %s", $row->id, $stops[$station["name"] . "|Station|" . $station["direction"]], MySQL::ConvertToDBTime(ParseTime(date("Y-m-d"), $time)));
				}
				catch (Exception $e)
				{
				}
			}
		}

		foreach ($data["Route"]["stops"] as $stop)
		{
			$stop = $stop["Spot"];

			if (!isset($stops[$stop["name"] . "|Stop|" . $stop["direction"]]))
			{
				try
				{
					$db->Query("INSERT INTO stops SET internalid = 0, name = %s, type = 'Stop', direction = %s, ordernum = 0, geo_latitude = %s, geo_longitude = %s", $stop["name"], $stop["direction"], $stop["lat"], $stop["lng"]);
					$stops[$stop["name"] . "|Stop|" . $stop["direction"]] = $db->GetInsertID();
				}
				catch (Exception $e)
				{
				}
			}

			foreach ($stop["departures"] as $time)
			{
				try
				{
					$db->Query("INSERT INTO departures SET rid = %d, sid = %d, departure = %s", $row->id, $stops[$stop["name"] . "|Stop|" . $stop["direction"]], MySQL::ConvertToDBTime(ParseTime(date("Y-m-d"), $time)));
				}
				catch (Exception $e)
				{
				}
			}
		}
	}
?>
