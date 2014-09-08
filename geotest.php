<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'vendor/autoload.php';
require_once('functions.php');
session_start();

echo <<<HERE
		<!DOCTYPE html>
		<html lang='en'>
		<head>
		<meta name='viewport' content='width=device-width, initial-scale=1', user-scalable=no'>
		<link href='http://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css' rel='stylesheet'>
		<script src='http://code.jquery.com/jquery.js'></script>
		<script src='http://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js'></script>

		</head>
		</style>
		<body>
		<div class='container'>
			<div class='row text-center'>
			<h1>Some MySQL/Sphinx Geospatial search!</h1>
			</div>
			<div class='row'>
				<div class='col-md-4' style='background-color:#FAFAFA'>
					<h3>Enter an address in Alabama</h3>
					<p class='help-block'>Works: Street+Zip or Street+City+State</p>
					<p class='help-block'>Doesn't: Street+State, Street+City</p>
					<form role='form' name='index' action='geotest.php' method='post'>
						<div class='form-group'>
								<label for='street'>Street</label><br />
								<input type='text' name='street' placeholder='600 Dexter Avenue'>				
						</div>
						<div class='form-group'>
								<label for='city'>City</label><br />
								<input type='text' name='city' placeholder='Montgomery'>				
						</div>
						<div class='form-group'>
								<label for='state'>State</label><br />
								<p class='help-block'>2 letter code</p>
								<input type='text' name='state' placeholder='Al'>				
						</div>
						<div class='form-group'>
								<label for='zip'>State</label><br />
								<input type='text' name='zip' placeholder='36104'>				
						</div>
						<div class='form-group'>
								<input type='submit' value='go'>				
						</div>
					</form>
				</div>
				<div class="col-md-8">
				<h3>This is happening:</h3>
				<p class="help-block">We enter an address and get the lat/long with the <a 
				href="https://www.census.gov/geo/maps-data/data/geocoder.html">Cencus Geocoder</a>.
				With that position we use the <strong><a href="http://dev.mysql.com/doc/refman/5.6/en/spatial-relation-functions.html">
				ST_CONTAINS</a></strong> function, to see which district contains the point.
				Our district shapes come from <a href="http://www2.census.gov/geo/tiger/TIGER2013/SLDL/tl_2013_01_sldl.zip">here</a>.</p>
				<p class="help-block"> In our case, it looks like this: <br /><strong>
				"SELECT namelsad FROM tl_2013_01_sldl <br />
				WHERE ST_CONTAINS(shape, POINT(\$lat, \$long))";</strong></p>
				<p class="help-block">Notice that ST_CONTAINS does what we want, it returns only one district for our point. CONTAINS uses a minimum bounding
				rectangle, so it returns multiple districts.</p>
				</div>		
			<div class="row">
				<div class="col-md-3">
					
HERE;

//if form info has been sent, do:
if (isset($_POST['street'])) {
	 
	 //connect to mysql
	 $mysqli = new mysqli('127.0.0.1', 'root', '', 'gro', '3306');
	 if ($mysqli->connect_errno) {
		  printf("Connect failed: %s\n", $mysqli->connect_error);
		  exit();
	 }
	 
	 //new guzzle object
	 $client = new Guzzle\Http\Client('http://geocoding.geo.census.gov/geocoder/');
	 
	 //build string for get request using form info
	 $get_this = "street=" . str_replace(' ', '+', $_POST['street']);
	 
	 if (!empty($_POST['city'])) {
		  $get_this .= "&city=" . $_POST['city'];
	 }
	 
	 if (!empty($_POST['state'])) {
		  $get_this .= "&state=" . $_POST['state'];
	 }
	 
	 if (!empty($_POST['zip'])) {
		  $get_this .= "&zip=" . $_POST['zip'];
	 }
	 
	 $get_this .= "&benchmark=Public_AR_Census2010&format=json";
			 
	 //if we haven't already hit the census geocoder (if we have, $_SESSION['long'] will be set), 
	 //do so, and put the resulting coordinates into session variables
    if(!isset($_SESSION['long'])){			 
			 $response = $client->get("locations/address?$get_this")->send();
			 
			 $json = $response->json();
			 
			 $_SESSION['long'] = $json["result"]["addressMatches"][0]["coordinates"]["x"];
			 $_SESSION['lat'] = $json["result"]["addressMatches"][0]["coordinates"]["y"];
   }
   
    //forgot why i did this..
    //sometimes the census geocoder doesn't work.. so, in this case, just explicitly set some lat/long here
    $long =  -86.220703;
    $lat  =  32.342223;
    
    //build queries
    $sql_st_contains = "select namelsad, astext(exteriorring(shape)) from tl_2013_01_sldl where ST_CONTAINS(shape, POINT($long, $lat))";
    $sql_contains    = "select namelsad, astext(exteriorring(shape)) from tl_2013_01_sldl where contains(shape, POINT($long, $lat))";
    
    
   //run query, print results (should only be one row.. which is good), shuv the WKT formatted 
   //text and the 'cleanshape' into session variables 
    if ($result = $mysqli->query($sql_st_contains)) {
        printf("<h3>ST_CONTAINS</h3>\n<br>", $result->num_rows);
        while ($row = $result->fetch_row()) {
            printf("%s\n<br>", $row[0]);
            $_SESSION['shape']      = $row[1];
            $_SESSION['cleanshape'] = convert($row[1]);
        }
    
   	  echo "<p class='help-block'>Good. One point, one district. ST_CONTAINS
   	  does what we want.</p>";
        
        /* free result set */
        $result->close();
    }
   
   //this query demonstrates why we don't want to use 'contains'
    echo "</div><div class='col-md-1'></div><div class='col-md-3'>";
    if ($result = $mysqli->query($sql_contains)) {
        printf("<h3>CONTAINS</h3>\n<br>", $result->num_rows);
        while ($row = $result->fetch_row()) {
            
            printf("%s\n<br>", $row[0]);
        }
        
        echo "<p class='help-block'>We have multiple districts because CONTAINS
        is using the minimum bounding rectangle instead of the actual shape. 
        <a href='http://dev.mysql.com/doc/refman/5.6/en/spatial-relation-functions.html'>
        Read more</a>.</p>
		 				\n<br />";
        
        /* free result set */
        $result->close();
    }
    
    //close row and column. open new row, new column
    echo "</div></div>
		<div class='row'>
		<div class='col-md-4 alert alert-info'>";
	
	//if they've entered form info, do all this stuff	
    if (isset($lat)) {
        //convert degrees to radians.. for sphinx
        $radlat  = deg2rad($lat);
        $radlong = deg2rad($long);
    }
    
    //tell them their coordinates and show them what the WKT shape looks like
    echo "<h3>Your position:</h3>latitude= " . $lat . "<br> longitude= " . $long . "<br><br><h4>in radians </h4><h5>..because Sphinx's GEODIST() function wants radians</h5>latitude= " . $radlat . "<br>longitude= " . $radlong . "</div>
	 <div class='col-md-8'><h3>The shape (ExteriorRing) of this district (as WKT):</h3><h4>We'll be using this, after a little cleaning up, to do some Sphinx searching (below).</h4>
	 <div style='word-break:break-all!important; max-height:200px; overflow:scroll;'>";
    $finalpoly = $_SESSION['shape'];
    echo $finalpoly . "</div></div>";
    
    //connect to sphinx
    $sphinx = new mysqli('127.0.0.1', '', '', '', '9306');
    if ($sphinx->connect_errno) {
        printf("Connect failed: %s\n", $sphinx->connect_error);
        exit();
    }

	//print description of sphinx stuff    
    echo "<div class='row'><div class='col-md-8'><h3>Sphinx <strong>fulltext/geodistance</strong> searching a data dump from <a href='http://www.geonames.org/'>geonames.org</a> (using your position)</h3>
    <h4><strong>..because Sphinx is really good at searching text!</strong><br /> geospatial search + flexible text search = awesome.</h4></div><div class='col-md-4'>
    		<div class='alert alert-warning'> <h3>Go <a href= 'https://github.com/adriannuta/SphinxGeoExample'>here</a> to check out Adrian's (more complete) demonstration of Sphinx's geospatial capabilities.</h3>
        		<h5>and, go <a href='http://sphinxsearch.com/blog/2013/07/02/geo-distances-with-sphinx/'>here</a> to read our blog post about all this stuff.</h5></div></div></div>
		<div class='row'>
		<div class='col-md-4'>";
    
    //3 sphinx geo distance queries follow
    if ($result = $sphinx->query("SELECT *, GEODIST($radlat, $radlong, latitude, longitude) as distance FROM geodemo WHERE MATCH('Church') AND distance < 10000 ORDER BY distance ASC LIMIT 0,100")) {
        printf("<h4 class='alert alert-info'>SELECT *, GEODIST($radlat, $radlong, latitude, longitude) as distance FROM geodemo WHERE MATCH('Church') AND distance < 10000 ORDER BY distance ASC LIMIT 0,100</h4><h5>These churches are within 10km of your address</h5>\n<br>");
        echo "<div style='word-break:break-all!important; max-height:200px; overflow:scroll;'>";
        while ($row = $result->fetch_row()) {
            
            printf("%s\n<br>", $row[5]);
            
        }
        echo "</div>";
    }
    
    echo "</div><div class='col-md-4'>";
    
    if ($result = $sphinx->query("SELECT *, GEODIST($radlat, $radlong, latitude, longitude) as distance FROM geodemo WHERE MATCH('hospital') AND distance < 10000 ORDER BY distance ASC LIMIT 0,100")) {
        printf("<h4 class='alert alert-info'>SELECT *, GEODIST($radlat, $radlong, latitude, longitude) as distance FROM geodemo WHERE MATCH('hospital') AND distance < 10000 ORDER BY distance ASC LIMIT 0,100</h4><h5>These hospitals are within 10km of your address</h5>\n<br>");
        echo "<div  style='word-break:break-all!important; max-height:200px; overflow:scroll;'>";
        while ($row = $result->fetch_row()) {
            
            printf("%s\n<br>", $row[5]);
            
        }
        echo "</div>";
    }
    
    echo "</div><div class='col-md-4'>";
    
    if ($result = $sphinx->query("SELECT *, GEODIST($radlat, $radlong, latitude, longitude) as distance FROM geodemo WHERE MATCH('church !baptist') AND distance < 10000 ORDER BY distance ASC LIMIT 0,100")) {
        printf("<h4 class='alert alert-info'>SELECT *, GEODIST($radlat, $radlong, latitude, longitude) as distance FROM geodemo WHERE MATCH('church !baptist') AND distance < 10000 ORDER BY distance ASC LIMIT 0,100</h4><h5>These non-Baptist Churches are within 10km of your address</h5>\n<br>");
        echo "<div style='word-break:break-all!important; max-height:200px; overflow:scroll;'>";
        while ($row = $result->fetch_row()) {
            
            printf("%s\n<br>", $row[5]);
            
        }
        echo "</div></div>";
    }
    
    //this is for sanitizing the WKT shape for sphinx. look at 'functions.php'.
    $f_sphinx = '';
    foreach ($_SESSION['cleanshape'] as $sphinxshape) {
        $f_sphinx .= $sphinxshape;
    }
    
    $coord_array = explode(',', $f_sphinx);
    $last        = array_pop($coord_array);
    
    $first = array_shift($coord_array);
    
    $finale = '';
    
    foreach ($coord_array as $almost) {
        $finale .= $almost . ",";
    }
    $golden = substr($finale, 0, -1);
    
    echo "<div class='row'>
				 <div class='col-md-12'>
				 	<h3>Sphinx searching <strong>within a polygon</strong></h3><h4>(the shape of the district)</h4>
				 </div>
				</div>
			<div class='row'>
    			<div class='col-md-6'>";
    
    //now, some shape searching!
    if ($result = $sphinx->query("SELECT *, CONTAINS(GEOPOLY2D($golden), latitude_deg, longitude_deg)
     as inside FROM geodemo WHERE inside=1 AND MATCH('methodist')")) {
        printf("<h4 class='alert alert-info'>SELECT *, CONTAINS(GEOPOLY2D(<strong>POLYGON COORDINATES
         GO HERE</strong>), latitude_deg, longitude_deg) as distance FROM geodemo WHERE inside=1 AND MATCH('methodist')</h4>
        <h5>These Methodist Churches are within your district.</h5>\n<br>");
        echo "<div style='word-break:break-all!important; max-height:200px; overflow:scroll;'>";
        while ($row = $result->fetch_row()) {
            
            printf("%s\n<br>", $row[5]);
            
        }
     echo "</div></div><div class='col-md-6'>";
      }
     if ($result = $sphinx->query("SELECT *, CONTAINS(GEOPOLY2D($golden), latitude_deg, longitude_deg)
      as inside FROM geodemo WHERE inside=1 AND MATCH('school')")) {
        printf("<h4 class='alert alert-info'>SELECT *, CONTAINS(GEOPOLY2D(<strong>POLYGON COORDINATES 
        GO HERE</strong>), latitude_deg, longitude_deg) as inside FROM geodemo WHERE inside=1 AND MATCH('school')</h4>
        <h5>These schools are within your district.</h5>\n<br>");
        echo "<div style='word-break:break-all!important; max-height:200px; overflow:scroll;'>";
        while ($row = $result->fetch_row()) {
            
            printf("%s\n<br>", $row[5]);
            
        }    
        echo "</div></div></div>";
    }
    
}

?>
