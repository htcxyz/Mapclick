<?php
/*
 * File: fetch.php
 * Project: Sky Spy Weather Prediction Ireland
 * Version: 1.0
 * Last Modified:[Date]
 * Description: Fetch API call, collects xml from meteEieann, can be run directly or on a cron.
 */
ini_set('max_execution_time', '600');
date_default_timezone_set('Europe/Dublin');

$lat_min = 51; //Bounding box of points
$lat_max = 56;
$lon_min = -10;
$lon_max = -6;
$spacing = 20.0; //Distance apart in kilometres

function latitude_interval($spacing) {
    return ($spacing / 111.32);
}

function longitude_interval($latitude, $spacing) {
    return ($spacing / (111.32 * cos(deg2rad($latitude))));
}

$gridPoints = array();
$lat_interval = latitude_interval($spacing);

for ($lat = $lat_min; $lat <= $lat_max; $lat += $lat_interval) {
    $lon_interval = longitude_interval($lat, $spacing);
    for ($lon = $lon_min; $lon <= $lon_max; $lon += $lon_interval) {
        $gridPoints[] = array('lat' => $lat, 'long' => $lon);
    }
}

$apiUrl = 'http://openaccess.pf.api.met.ie/metno-wdb2ts/locationforecast?lat={lat};long={long}';

if (!is_dir('metData')) {
    mkdir('metData', 0777, true);
}

foreach ($gridPoints as $point) {
    $url = str_replace(['{lat}', '{long}'], [$point['lat'], $point['long']], $apiUrl);
    $response = @file_get_contents($url);
    if ($response === FALSE) {
        echo "Failed to fetch data for lat: {$point['lat']}, long: {$point['long']}<br>";
        continue;
    }
    $filename = 'metData/lat_' . $point['lat'] . '_lon_' . $point['long'] . '.xml';
    file_put_contents($filename, $response, FILE_USE_INCLUDE_PATH | LOCK_EX);
}

echo "Data fetching completed.\n";
?>
