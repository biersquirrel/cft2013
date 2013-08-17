<?php
	require_once "support/http.php";
	require_once "support/web_browser.php";
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

	function DropTable($tablename)
	{
		global $db;

		try
		{
			$db->Query("DROP TABLE `%b`", "gtfs_" . $tablename);
		}
		catch (Exception $e)
		{
		}
	}

	$tableinfo = array(
		"agency" => array("agency_id" => true),
		"stops" => array("stop_id" => true, "zone_id" => true),
		"routes" => array("route_id" => true, "agency_id" => true, "route_short_name" => true, "route_type" => "int(2)"),
		"trips" => array("route_id" => true, "service_id" => true, "trip_id" => true, "direction_id" => "int(1)", "block_id" => true, "shape_id" => true),
		"stop_times" => array("trip_id" => true, "arrival_time" => "time", "departure_time" => "time", "stop_id" => true, "stop_sequence" => "int(11)", "pickup_type" => "int(1)", "drop_off_type" => "int(1)"),
		"calendar" => array("service_id" => true, "monday" => "int(1)", "monday" => "int(1)", "tuesday" => "int(1)", "wednesday" => "int(1)", "thursday" => "int(1)", "friday" => "int(1)", "saturday" => "int(1)", "start_date" => "date", "end_date" => "date"),
		"calendar_dates" => array("service_id" => true, "date" => "date", "exception_type" => "int(1)"),
		"fare_attributes" => array("fare_id" => true, "payment_method" => "int(1)", "transfers" => "int(1)"),
		"fare_rules" => array("fare_id" => true, "route_id" => true, "origin_id" => true, "destination_id" => true, "contains_id" => true),
		"shapes" => array("shape_id" => true, "shape_pt_sequence" => "int(4)"),
		"frequencies" => array("trip_id" => true, "start_time" => "time", "end_time" => "time", "headway_secs" => "int(5)", "exact_times" => "int(1)"),
		"transfers" => array("from_stop_id" => true, "to_stop_id" => true, "transfer_type" => "int(1)", "min_transfer_time" => "int(5)"),
	);

	foreach ($tableinfo as $name => $info)  DropTable($name);

	$web = new WebBrowser();
	$result = $web->Process("http://www.suntran.com/gtfs/SunTranGTFS.zip");

	if (!$result["success"])  DisplayError("Error retrieving URL.  " . $result["error"]);
	else if ($result["response"]["code"] != 200)  DisplayError("Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"]);

	file_put_contents("gtfs.zip", $result["body"]);

	@mkdir("gtfs");
	$zip = zip_open("gtfs.zip");
	if (!is_resource($zip))  DownloadFailed("The ZIP file 'gtfs.zip" . "' was unable to be opened for reading.");
	while (($zipentry = zip_read($zip)) !== false)
	{
		$name = str_replace("\\", "/", zip_entry_name($zipentry));
		$name = str_replace("../", "/", $name);
		$name = str_replace("./", "/", $name);
		$name = preg_replace("/\/+/", "/", $name);

		$size = zip_entry_filesize($zipentry);

		if (!zip_entry_open($zip, $zipentry, "rb"))  DisplayError("Error opening the ZIP file entry '" . zip_entry_name($zipentry) . "' for reading.");
		$fp = fopen("gtfs/" . $name, "wb");
		while ($size > 1000000)
		{
			fwrite($fp, zip_entry_read($zipentry, $size));
			$size -= 1000000;
		}
		if ($size > 0)  fwrite($fp, zip_entry_read($zipentry, $size));
		fclose($fp);
		zip_entry_close($zipentry);
	}
	zip_close($zip);

	@unlink("gtfs.zip");

	function CreateTable($tablename, $info, $line)
	{
		global $db;

		$keys = array();
		$sql = array();
		foreach ($line as $col)
		{
			if (isset($info[$col]) && $info[$col] === true)  $keys[] = "`" . $col . "`";

			if (isset($info[$col]) && $info[$col] !== true)  $sql[] = "`" . $col . "` " . $info[$col] . " not null";
			else  $sql[] = "`" . $col . "` varchar(255) not null";
		}

		$sql = "CREATE TABLE `%b` (" . implode(",\n", $sql) . ",\nKEY main (" . implode(", ", $keys) . "))";
		$db->Query($sql, "gtfs_" . $tablename);
	}

	$dir = opendir("gtfs");
	if ($dir)
	{
		while (($file = readdir($dir)) !== false)
		{
			$name = substr($file, 0, -4);
			if ($file != "." && $file != ".." && isset($tableinfo[$name]))
			{
				$fp = fopen("gtfs/" . $file, "rb");
				$line = fgetcsv($fp);

				echo "Processing '" . $name . "'...\n";
				CreateTable($name, $tableinfo[$name], $line);

				while (($line2 = fgetcsv($fp)) !== false)
				{
					if (count($line) == count($line2))
					{
						$sql = array();
						$sqlvars = array("gtfs_" . $name);
						foreach ($line as $num => $col)
						{
							$sql[] = "`" . $col . "` = %s";
							$sqlvars[] = $line2[$num];
						}
						$sql = "INSERT INTO `%b` SET " . implode(", ", $sql);
						$db->Query($sql, $sqlvars);
					}
				}
			}
		}

		closedir($dir);
	}
?>