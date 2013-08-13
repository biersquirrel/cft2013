<?php
	require_once "base.php";

	if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "schedule_ajax_right")
	{
var_dump($_REQUEST);
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "schedule")
	{
		DisplayHeader("SunTran Schedule");

?>
<link href="support/jquery_ui_themes/smoothness/jquery-ui-1.9.0.css" rel="stylesheet" />
<script type="text/javascript" src="support/jquery-ui-1.9.0.min.js"></script>
<script type="text/javascript">
var map;
var geocoder;
var markers = [];
var gposition, glocation, glocation2;

function GoogleGeocodeAddress(addr, callbackfunc)
{
	geocoder.geocode( { address: addr}, function(results, status) {
		if (status == google.maps.GeocoderStatus.OK) {
			callbackfunc(results[0].geometry.location);
//			DoViewUpdateLatLng(results[0].geometry.location.lat(), results[0].geometry.location.lng());
		} else {
			alert("Geocode was not successful for the following reason: " + status);
		}
	});
}

function CheckUpdateSchedule()
{
	var params = '&date=' + $('#schedule_date').val() + '&depart_arrive=' + $('#schedule_depart_arrive').val() + '&time=' + encodeURIComponent($('#schedule_time').val());
	if (gposition != null && glocation2 != null)
	{
		$('#rightside').load('<?php echo htmlspecialchars($rooturl); ?>?action=schedule_ajax_right&lat=' + gposition.coords.latitude + '&long=' + gposition.coords.longitude + '&lat2=' + glocation2.lat() + '&long2=' + glocation2.lng() + params);
	}
	else if (glocation != null && glocation2 != null)
	{
		$('#rightside').load('<?php echo htmlspecialchars($rooturl); ?>?action=schedule_ajax_right&lat=' + glocation.lat() + '&long=' + glocation.lng() + '&lat2=' + glocation2.lat() + '&long2=' + glocation2.lng() + params);
	}
}

function UpdateSchedule()
{
	gposition = glocation = glocation2 = null;

	if ($('#schedule_to').val() == '' || $('#schedule_date').val() == '' || $('#schedule_time').val() == '')  alert('Please fill in the fields.');
	else
	{
		if ($('#schedule_from').val() != '')
		{
			GoogleGeocodeAddress($('#schedule_from').val(), function(loc) {
				glocation = loc;
				CheckUpdateSchedule();
			});
		}
		else if (navigator.geolocation)
		{
			navigator.geolocation.getCurrentPosition(function(pos) {
				gposition = pos;
				CheckUpdateSchedule();
			});
		}
		else  alert("Geolocation is not supported by this browser.");

		GoogleGeocodeAddress($('#schedule_to').val(), function(loc) {
			glocation2 = loc;
			CheckUpdateSchedule();
		});
	}

	return false;
}

$(function() {
	$('.date').datepicker({ dateFormat: 'yy-mm-dd' });

	var tempheight = $(window).height() - 150;
	if (tempheight < 300)  tempheight = 300;
	$('#map-canvas').height(tempheight);

	geocoder = new google.maps.Geocoder();

	var mapOptions = {
		zoom: 12,
		center: new google.maps.LatLng(32.2217, -110.9258),
		mapTypeId: google.maps.MapTypeId.ROADMAP
	};

	map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);

	$.ajaxSetup ({
		cache: false
	});

});
</script>

<div class="span3" style="margin-left: 0;">
	<h3>Schedule</h3>
	<form onsubmit="return UpdateSchedule();">
		<input id="schedule_from" type="text" class="input-large" placeholder="From Current Location" />
		<input id="schedule_to" type="text" class="input-large" placeholder="To Destination" />
		<input id="schedule_date" type="text" class="date input-large" value="<?php echo date("Y-m-d"); ?>" /><br />
		<select class="input-small" id="schedule_depart_arrive">
			<option value="depart">Depart</option>
			<option value="arrive">Arrive</option>
		<select>
		<input id="schedule_time" type="text" class="input-small" value="<?php echo date("g:i a"); ?>" /><br />
		<button type="submit" class="btn">Go</button>
	</form>
</div>

<div class="span9">
<p>Oh dear, you found something that hasn't been implemented...yet.</p>
	<div id="calculatedpath"></div>
	<div id="map-canvas"></div>
	<div id="rightside" style="display: block;"></div>
</div>
<?php

		DisplayFooter();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "nearest_stop_ajax_right")
	{
?>
<script type="text/javascript">
RemoveMapMarkers();
map.setZoom(16);
map.setCenter(new google.maps.LatLng(<?php echo (double)$_REQUEST["lat"]; ?>, <?php echo (double)$_REQUEST["long"]; ?>));
AddMapMarker(new google.maps.LatLng(<?php echo (double)$_REQUEST["lat"]; ?>, <?php echo (double)$_REQUEST["long"]; ?>), "images/red_MarkerA.png");
<?php
		$lastrow = false;
		$result = GetNearbyStopsRealtime($_REQUEST["lat"], $_REQUEST["long"], 20);
		while ($row = $result->NextRow())
		{
			if ($lastrow !== false && $lastrow->geo_latitude == $row->geo_latitude && $lastrow->geo_longitude == $row->geo_longitude)  continue;

?>
AddMapMarker(new google.maps.LatLng(<?php echo $row->geo_latitude; ?>, <?php echo $row->geo_longitude; ?>), '<?php echo ($lastrow !== false ? "images/yellow_Marker.png" : "images/paleblue_MarkerB.png"); ?>', <?php echo ($lastrow !== false ? "true" : "false"); ?>);
<?php

			$lastrow = $row;
		}
?>
</script>
<?php
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "nearest_stop_ajax_left")
	{
		$result = GetNearbyStopsRealtime($_REQUEST["lat"], $_REQUEST["long"], 2);
		while ($row = $result->NextRow())
		{
			echo "<h4>" . htmlspecialchars($row->name . " [" . $row->direction . "]") . "</h4>";

			$date = date("Y-m-d");
			$lastroute = "";
			$result2 = $db->Query("SELECT d.*, r.* FROM departures AS d, routes AS r WHERE d.rid = r.id AND d.sid = %d", $row->id);
			while ($row2 = $result2->NextRow())
			{
				if ($lastroute != $row2->name)
				{
					if ($lastroute != "")  echo "</ul>";

					echo "<p>Route #" . htmlspecialchars($row2->internalid . " [" . $row2->name . "]");
					$row3 = $db->GetRow("SELECT s.* FROM stops AS s, departures AS d WHERE s.id = d.sid AND d.rid = %d AND d.departure > %s ORDER BY d.departure LIMIT 1", $row2->rid, $row2->departure);
					if ($row3)  echo "<br /><i><font style=\"color: #666666; font-size: 0.8em;\">Next:  " . $row3->name . "</font></i>";
					echo "</p>";
					echo "<ul>";

					$lastroute = $row2->name;
				}

				echo "<li>" . DisplayDateTime(MySQL::ConvertFromDBTime($row2->departure), $date) . "</li>";
			}

			if ($lastroute == "")  echo "<p>No routes.</p>";
			else  echo "</ul>";
		}
	}
	else
	{
		$_REQUEST["action"] = "";
		DisplayHeader("SunTran Nearest Stops");

?>
<script type="text/javascript">
var map;
var geocoder;
var markers = [];
var location_accepted = false;

function UpdateLocation()
{
	if ($('#nearest_stop_location').val() != '')  GoogleGeocodeAddress($('#nearest_stop_location').val());
	else if (navigator.geolocation)  navigator.geolocation.getCurrentPosition(DoViewUpdate);
	else  alert("Geolocation is not supported by this browser.");

	return false;
}

function DoViewUpdate(position)
{
	DoViewUpdateLatLng(position.coords.latitude, position.coords.longitude);
}

function DoViewUpdateLatLng(lat, lng)
{
	location_accepted = true;
	$('#leftside').load('<?php echo htmlspecialchars($rooturl); ?>?action=nearest_stop_ajax_left&lat=' + lat + '&long=' + lng);
	$('#rightside').load('<?php echo htmlspecialchars($rooturl); ?>?action=nearest_stop_ajax_right&lat=' + lat + '&long=' + lng);
}

function AddMapMarker(location, iconurl, handleclick)
{
	var marker = new google.maps.Marker({
		position: location,
		map: map,
		icon: iconurl
	});

	if (handleclick)
	{
		google.maps.event.addListener(marker, 'click', function(event) {
			$('#nearest_stop_location').val(event.latLng.lat() + ", " + event.latLng.lng());
			UpdateLocation();
		});
	}

	markers.push(marker);
}

function RemoveMapMarkers()
{
	for (x in markers)  markers[x].setMap(null);
	markers = [];
}

function GoogleGeocodeAddress(addr)
{
	geocoder.geocode( { address: addr}, function(results, status) {
		if (status == google.maps.GeocoderStatus.OK) {
			DoViewUpdateLatLng(results[0].geometry.location.lat(), results[0].geometry.location.lng());
		} else {
			alert("Geocode was not successful for the following reason: " + status);
		}
	});
}

$(function() {
	var tempheight = $(window).height() - 125;
	if (tempheight < 300)  tempheight = 300;
	$('#map-canvas').height(tempheight);

	geocoder = new google.maps.Geocoder();

	var mapOptions = {
		zoom: 12,
		center: new google.maps.LatLng(32.2217, -110.9258),
		mapTypeId: google.maps.MapTypeId.ROADMAP
	};

	map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);

	$.ajaxSetup ({
		cache: false
	});

	UpdateLocation();

	window.setInterval(function() {
		if (location_accepted)  UpdateLocation();
	}, 60000);

	google.maps.event.addListener(map, 'click', function(event) {
		$('#nearest_stop_location').val(event.latLng.lat() + ", " + event.latLng.lng());
		UpdateLocation();
	});
});
</script>

<div class="span3" style="margin-left: 0;">
	<h3>Nearest Stops</h3>
	<form class="form-inline" onsubmit="return UpdateLocation();">
		<input id="nearest_stop_location" type="text" class="input-large" placeholder="Current Location" />
		<button type="submit" class="btn">Go</button>
	</form>

	<div id="leftside">
		<p>Getting current location...</p>
	</div>
</div>

<div class="span9">
	<div id="map-canvas"></div>
	<div id="rightside"></div>
</div>
<?php

		DisplayFooter();
	}
?>
