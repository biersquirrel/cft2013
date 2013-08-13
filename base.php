<?php
	require_once "support/mysql.php";
	require_once "support/utf8.php";

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

	function GetNearbyStopsRealtime($lat, $lon, $limit)
	{
		global $db;

		$lat = (double)$lat;
		$lon = (double)$lon;

		$sql = "SELECT *, 3956 * 2 * ASIN(SQRT(POWER(SIN((" . $lat . " - ABS(stops.geo_latitude)) * PI() / 180 / 2), 2) + COS(" . $lat . " * PI() / 180) * COS(ABS(stops.geo_latitude) * PI() / 180) * POWER(SIN((" . $lon . " - stops.geo_longitude) * PI() / 180 / 2), 2))) AS distance FROM stops ORDER BY distance LIMIT " . $limit;
		$result = $db->Query($sql);

		return $result;
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
