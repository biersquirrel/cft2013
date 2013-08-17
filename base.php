<?php
	require_once "support/mysql.php";
	require_once "support/utf8.php";
	require_once "support/http.php";
	require_once "support/web_browser.php";

	$db = new MySQL("localhost", "suntran", "suntran_demo", "unrelated_suntran");

	$rooturl = "http://cubiclesoft.com/Unrelated/suntran/";

	$twilio_phone = "(520) EZ-HI-SUN, (520) 3944-SUN, (520) 394-4786";

	function ParseDate($date)
	{
		return mktime(0, 0, 0, substr($date, 5, 2), substr($date, 8, 2), substr($date, 0, 4));
	}

	function GetNearbyStopsSchedule($lat, $lon, $date, $time, $limit = 2)
	{
		global $db;

		$lat = (double)$lat;
		$lon = (double)$lon;

		$weekday = strtolower(date("D", ParseDate($date)));

		$sql = "SELECT *, 3956 * 2 * ASIN(SQRT(POWER(SIN((" . $lat . " - ABS(stops.geo_latitude)) * PI() / 180 / 2), 2) + COS(" . $lat . " * PI() / 180) * COS(ABS(stops.geo_latitude) * PI() / 180) * POWER(SIN((" . $lon . " - stops.geo_longitude) * PI() / 180 / 2), 2))) AS distance FROM stops ORDER BY distance LIMIT " . $limit;
		$result = $db->Query($sql);

		return $result;
	}

	function GetRealtimeStopIDInformation($id)
	{
		global $db;

		try
		{
			$name = "";
			$dirmap = array();
			$stopinfo = array();
			$id = (int)$id;
			$result = $db->Query("SELECT * FROM stops WHERE stopid = %d", $id);
			while ($row = $result->NextRow())
			{
				$stopinfo[] = array("directionID" => $row->directionid, "stopID" => $row->stopid, "timePointID" => $row->timepointid);
				$dirmap[$row->directionid] = $row->direction;
				$name = $row->name;
			}

			$response = array("success" => true, "info" => array());
			$result = $db->Query("SELECT r.* FROM route_stops AS rs, routes AS r WHERE rs.routeid = r.id AND rs.stopid = %d", $id);
			while ($row = $result->NextRow())
			{
				$web = new WebBrowser();
				$web->SetState(array("referer" => "http://suntran.com/tmwebwatch/GoogleLiveMap", "autoreferer" => false));
				$result2 = $web->Process("http://suntran.com/tmwebwatch/GoogleMap.aspx/getStopTimes", "auto", array("method" => "POST", "body" => json_encode(array("routeID" => $row->id, "stops" => $stopinfo)), "headers" => array("Content-Type" => "application/json; charset=utf-8", "Accept" => "application/json, text/javascript, */*; q=0.01", "X-Requested-With" => "XMLHttpRequest")));

				if (!$result2["success"])  return $result2;
				else if ($result2["response"]["code"] != 200)  return array("success" => false, "error" => "Error retrieving URL.  Server returned:  " . $result2["response"]["code"] . " " . $result2["response"]["meaning"], "errorcode" => "response_failure");

				$data = @json_decode($result2["body"], true);
				if (isset($data["d"]["stops"]))
				{
					foreach ($data["d"]["stops"] as $stop)
					{
						if (isset($stop["crossings"]))
						{
							$name2 = $name . " (" . $dirmap[$stop["directionID"]] . ")";
							if (!isset($response["info"][$name2]))  $response["info"][$name2] = array();
							if (!isset($response["info"][$name2]["Route #" . $row->internalid]))  $response["info"][$name2]["Route #" . $row->internalid] = array();

							foreach ($stop["crossings"] as $crossing)
							{
								if (!$crossing["cancelled"])
								{
									if (isset($crossing["predTime"]))  $response["info"][$name2]["Route #" . $row->internalid][] = $crossing["predTime"] . " " . $crossing["predPeriod"];
									else  $response["info"][$name2]["Route #" . $row->internalid][] = $crossing["schedTime"] . " " . $crossing["schedPeriod"];
								}
							}
						}
					}
				}
			}

			return $response;
		}
		catch (Exception $e)
		{
			return array("success" => false, "error" => "A database query error or other exception occurred.", "errorcode" => "exception");
		}
	}

	function GetNearbyStopsRealtime($lat, $lon, $limit)
	{
		global $db;

		$lat = (double)$lat;
		$lon = (double)$lon;

		$sql = "SELECT *, 3956 * 2 * ASIN(SQRT(POWER(SIN((" . $lat . " - ABS(stops.geo_latitude)) * PI() / 180 / 2), 2) + COS(" . $lat . " * PI() / 180) * COS(ABS(stops.geo_latitude) * PI() / 180) * POWER(SIN((" . $lon . " - stops.geo_longitude) * PI() / 180 / 2), 2))) AS distance FROM stops ORDER BY distance LIMIT " . $limit;
		$result = $db->Query($sql);

		return $result;
	}

	function GeocodeAddress($address)
	{
		$mapquest_key = "Fmjtd%7Cluub25utn1%2C2a%3Do5-9u80qw";

		$web = new WebBrowser();
		$result = $web->Process("http://www.mapquestapi.com/geocoding/v1/address?key=" . $mapquest_key . "&location=" . urlencode($address));

		if (!$result["success"])  return $result;
		else if ($result["response"]["code"] != 200)  return array("success" => false, "error" => "Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"], "errorcode" => "response_failure");

		$data = @json_decode($result["body"], true);

		$bestlocation = false;
		$bestaccuracy = 0;
		foreach ($data["results"] as $result)
		{
			foreach ($result["locations"] as $location)
			{
				$accuracy = substr($location["geocodeQualityCode"], 0, 3);
				switch ($accuracy)
				{
					case "P1A":  $accuracy = 9;  break;
					case "P1B":  $accuracy = 8;  break;
					case "P1C":  $accuracy = 7;  break;

					case "L1A":  $accuracy = 8;  break;
					case "L1B":  $accuracy = 7;  break;
					case "L1C":  $accuracy = 7;  break;

					case "I1A":  $accuracy = 8;  break;
					case "I1B":  $accuracy = 7;  break;
					case "I1C":  $accuracy = 7;  break;

					case "B1A":  $accuracy = 6;  break;
					case "B1B":  $accuracy = 5;  break;
					case "B1C":  $accuracy = 4;  break;
					case "B2A":  $accuracy = 6;  break;
					case "B2B":  $accuracy = 5;  break;
					case "B2C":  $accuracy = 4;  break;
					case "B3A":  $accuracy = 7;  break;
					case "B3B":  $accuracy = 6;  break;
					case "B3C":  $accuracy = 5;  break;

					default:  $accuracy = 0;
				}

				if ($accuracy < 6)  continue;

				if ($bestaccuracy < $accuracy)
				{
					$bestlocation = $location;
					$bestaccuracy = $accuracy;
				}
			}
		}

		if ($bestaccuracy == 0)  return array("success" => false, "error" => "Unable to geocode the address.", "errorcode" => "geocoder_error");
		else if ($bestaccuracy < 6)  return array("success" => false, "error" => "The address was unable to be geocoded with sufficient accuracy.", "errorcode" => "low_accuracy");

		return array("success" => true, "geo_latitude" => $bestlocation["latLng"]["lat"], "geo_longitude" => $bestlocation["latLng"]["lng"], "geo_accuracy" => $bestaccuracy, "info" => $bestlocation);
	}

	function DisplayDateTime($ts, $date)
	{
		if (date("Y-m-d", $ts) == $date)  return date("g:i a", $ts);

		return date("D, g:i a", $ts);
	}

	function DisplayHeader($title)
	{
		global $rooturl;

		$action = $_REQUEST["action"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title><?php echo htmlspecialchars($title); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="" />
<meta name="author" content="" />

<link href="support/bootstrap/css/bootstrap.css" rel="stylesheet" />
<style type="text/css">
body {
	padding-top: 60px;
	padding-bottom: 40px;
}

.sidebar-nav {
	padding: 9px 0;
}

@media (max-width: 980px) {
	/* Enable use of floated navbar text */
	.navbar-text.pull-right {
		float: none;
		padding-left: 5px;
		padding-right: 5px;
	}
}
</style>
<link href="support/bootstrap/css/bootstrap-responsive.css" rel="stylesheet">

<script src="support/jquery-1.7.2.min.js"></script>

<style type="text/css">
#map-canvas {
	border: 1px solid #CCCCCC;
	width: 98%;
	height: 500px;
}

/* Bug fixes for Twitter Bootstrap + Google Maps */
.gmnoprint img { max-width: none; }
.gmnoprint label { width: auto; display: inline; }

#map_canvas img {
	max-width: none;
}

#rightside {
	display: none;
}
</style>
<script src="https://maps.googleapis.com/maps/api/js?sensor=false"></script>

<!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
<!--[if lt IE 9]>
	<script src="../assets/js/html5shiv.js"></script>
<![endif]-->
</head>
<body>
<div class="navbar navbar-inverse navbar-fixed-top">
	<div class="navbar-inner">
		<div class="container-fluid">
			<button type="button" class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="brand" href="<?php echo htmlspecialchars($rooturl); ?>">SunTran</a>
			<div class="nav-collapse collapse">
				<ul class="nav">
					<li<?php if ($action == "")  echo " class=\"active\""; ?>><a href="<?php echo htmlspecialchars($rooturl); ?>">Nearest Stops</a></li>
					<li<?php if ($action == "schedule")  echo " class=\"active\""; ?>><a href="<?php echo htmlspecialchars($rooturl); ?>?action=schedule">Schedule</a></li>
				</ul>
			</div><!--/.nav-collapse -->
		</div>
	</div>
</div>

<div class="container-fluid">
	<div class="row-fluid">
<?php
	}

	function DisplayFooter()
	{
?>
	</div><!--/row-->

	<hr>

	<footer>
		<p>&copy; <?php echo date("Y"); ?></p>
	</footer>

</div><!--/.fluid-container-->

<script src="support/bootstrap/js/bootstrap.min.js"></script>

</body>
</html>
<?php
	}
?>
