<?php
/*
 * File: index.php
 * Project: Sky Spy Weather Prediction Ireland
 * Version: 1.0
 * Last Modified:[Date]
 * Description: Main application file handling weather data processing and display.
 */


// Function to sanitize and validate date input
function sanitize_date($date_str) {
    $dt = DateTime::createFromFormat('Y-m-d', $date_str);
    if ($dt && $dt->format('Y-m-d') === $date_str) {
        return $date_str;
    }
    return false;
}

// Function to sanitize and validate time input
function sanitize_time($time_str) {
    $dt = DateTime::createFromFormat('H:i', $time_str);
    if ($dt && $dt->format('H:i') === $time_str) {
        return $time_str;
    }
    return false;
}

// Initialize variables with default values
$selectedDate = gmdate('Y-m-d'); // Default to today in UTC
$selectedTime = gmdate('H:00');   // Default to the nearest current hour in UTC

// Check if form is submitted via GET
if (isset($_GET['selectedDate']) && isset($_GET['selectedTime'])) {
    $tempDate = sanitize_date($_GET['selectedDate']);
    $tempTime = sanitize_time($_GET['selectedTime']);

    if ($tempDate && $tempTime) {
        $selectedDate = $tempDate;
        $selectedTime = $tempTime;
    }
}

// Combine date and time into desiredDatetime in ISO 8601 format (UTC)
$desiredDatetime = $selectedDate . 'T' . $selectedTime . ':00Z';

// Convert the desired datetime to a timestamp
$desiredTimestamp = strtotime($desiredDatetime);

// Directory containing XML files
$mapDataDir = __DIR__ . '/metData/';

// Initialize an array to hold all map points' weather data
$allWeatherData = [];

$lastUpdated = null;

// Check if the directory exists
if (is_dir($mapDataDir)) {
    $files = scandir($mapDataDir);

    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'xml') {
            $filePath = $mapDataDir . $file;
            
            if ($lastUpdated === null) {
                $lastUpdatedTimestamp = filemtime($filePath);
                $lastUpdated = gmdate('F j, Y, H:i \U\T\C', $lastUpdatedTimestamp);
            }

            $xml = simplexml_load_file($filePath);

            if ($xml === false) {
                error_log("Failed to parse XML file: $filePath");
                continue;
            }

            $pointData = [
                'file' => $file,
                'latitude' => null,
                'longitude' => null,
                'altitude' => null,
                'weather' => [],
            ];

            foreach ($xml->product->time as $time) {
                $from = (string) $time['from'];
                $to = (string) $time['to'];

                $fromTimestamp = strtotime($from);
                $toTimestamp = strtotime($to);

                if ($desiredTimestamp >= $fromTimestamp && $desiredTimestamp <= $toTimestamp) {
                    $location = $time->location;

                    if ($pointData['latitude'] === null) {
                        $pointData['latitude'] = isset($location['latitude']) ? (float) $location['latitude'] : null;
                    }
                    if ($pointData['longitude'] === null) {
                        $pointData['longitude'] = isset($location['longitude']) ? (float) $location['longitude'] : null;
                    }
                    if ($pointData['altitude'] === null) {
                        $pointData['altitude'] = isset($location['altitude']) ? (float) $location['altitude'] : 0;
                    }

                    if (isset($location->temperature)) {
                        $pointData['weather']['temperature'] = (float) $location->temperature['value'];
                    }

                    if (isset($location->windSpeed)) {
                        $pointData['weather']['windSpeed'] = (float) $location->windSpeed['mps'];
                    }

                    if (isset($location->windDirection)) {
                        $pointData['weather']['windDirection'] = (float) $location->windDirection['deg'];
                    }

                    if (isset($location->humidity)) {
                        $pointData['weather']['humidity'] = (float) $location->humidity['value'];
                    }

                    if (isset($location->pressure)) {
                        $pointData['weather']['pressure'] = (float) $location->pressure['value'];
                    }

                    if (isset($location->cloudiness)) {
                        $pointData['weather']['cloudiness'] = (float) $location->cloudiness['percent'];
                    }

                    if (isset($location->lowClouds)) {
                        $pointData['weather']['lowClouds'] = (float) $location->lowClouds['percent'];
                    }

                    if (isset($location->dewpointTemperature)) {
                        $pointData['weather']['dewPoint'] = (float) $location->dewpointTemperature['value'];
                    }

                    if (isset($location->precipitation)) {
                        $pointData['weather']['precipitation'] = (float) $location->precipitation['value'];
                        $pointData['weather']['probability'] = (float) $location->precipitation['probability'];
                    }

                    if (isset($location->symbol)) {
                        $pointData['weather']['symbol'] = (string) $location->symbol['number'];
                    }
                }
            }

            if (!empty($pointData['weather'])) {
                $allWeatherData[] = $pointData;
            }
        }
    }
} else {
    die("Directory 'metData' does not exist.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sky Spy | Weather Prediction Ireland</title>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <!-- Bootstrap CSS for Responsiveness and Styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding-top: 20px;
            padding-bottom: 20px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
		a.story {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #0056b3;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
        }

        a.story:hover {
            background-color: #004494;
        }
        .container {
            flex-grow: 1;
        }
        h1 {
            color: #343a40;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-container {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto 30px auto;
        }
        label {
            font-weight: bold;
        }
        #last-updated {
            text-align: center;
            color: #6c757d;
            margin-bottom: 20px;
        }
        #map {
            height: 800px;/* calc(100vh - 200px); Adjust based on your header, footer, or other fixed elements */
            width: 100%;
            border-radius: 8px;
        }
        @media (max-width: 768px) {
            #map {
                height: 800px;
            }
            .form-container {
                padding: 10px;
            }
            h1, h3 {
                font-size: 1.2em;
            }
        }
    </style>
</head>
<body>

    <div class="container">
	
	        <!-- Header Displaying the Selected Date and Time -->
        <h1>  Sky Spy Weather Prediction Ireland</h1>
		

        
		
        <!-- Weather Selection Form -->
        <div class="form-container">
		<p>Pick a date and Sky Spy will show you where it will be	dry.</p>
            <form method="get" action="">
                <div class="mb-3 row">
                    <label for="selectedDate" class="col-sm-4 col-form-label">Select Date:</label>
                    <div class="col-sm-8">
                        <select id="selectedDate" name="selectedDate" class="form-select" required>
                            <?php
                            $today = new DateTime('now', new DateTimeZone('UTC'));
                            for ($i = 0; $i < 15; $i++) {
                                $date = clone $today;
                                $date->modify("+$i day");
                                $dateValue = $date->format('Y-m-d');
                                $dateDisplay = $date->format('l, F j, Y');

                                $selectedAttr = ($dateValue === $selectedDate) ? 'selected' : '';

                                echo "<option value=\"$dateValue\" $selectedAttr>$dateDisplay</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3 row">
                    <label for="selectedTime" class="col-sm-4 col-form-label">Select Time:</label>
                    <div class="col-sm-8">
                        <select id="selectedTime" name="selectedTime" class="form-select" required>
                            <?php
                            for ($hour = 0; $hour < 24; $hour++) {
                                $time = sprintf('%02d:00', $hour);
                                $timeDisplay = sprintf('%02d:00', $hour);

                                $selectedAttr = ($time === $selectedTime) ? 'selected' : '';

                                echo "<option value=\"$time\" $selectedAttr>$timeDisplay</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary">Go</button>
                </div>
            </form>
			Currently Displaying: 
			<?php 
                $displayDatetime = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $desiredDatetime, new DateTimeZone('UTC'));
                echo $displayDatetime ? $displayDatetime->format('l, F j, Y \a\t H:i \G\M\T') : 'Invalid Date/Time';
            ?>. <b>TIP:</b>Tap a location icon for weather detail.
        </div>



        <!-- Leaflet Map Container -->
        <div id="map"></div>
		<!-- Display the last updated timestamp -->
        <p id="last-updated">Data last updated from Met Éireann: <?php echo htmlspecialchars($lastUpdated); ?></p>
		
		<div class="map">
		 <p><b>The nearer you get to the date the more accurate the prediction, so keep an eye on the Sky!</b>
		 <br><br>
		 How it works. Set the date and time to a future point, Sky Spy will check the weather at all locations at that time. The map is divided into a grid at 20Km intervals. The data is updated from MET Éireann every hour. MET Éireann's models frequently change their mind, the closer the event date the higher the accuracy.
		 
		 </p>
		 
		 <a class="story" href="https://mapclick.com">Mapclick Homepage</a>
		 
		 
		 <p>
		 <small><b>Sky Spy V1.0:</b>
		 Copyright &copy; <a href="https://www.howtocompany.xyz" target="_blank">HTC How to Company XYZ Limited (HTC)</a>. HTC 
		 does not accept any liability for errors or omissions in the data, their availability, or for any loss or damage arising from their use.
		 </p>
		 <p>
		 
		 
		 <b>Weather Data accreditation:</b>
    Copyright &copy; <a href="https://www.met.ie/" target="_blank">Met Éireann</a>.
    This data is sourced from <a href="https://www.met.ie/" target="_blank">met.ie</a> and is published under a 
    <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank">Creative Commons Attribution 4.0 International (CC BY 4.0)</a> license.
    Met Éireann does not accept any liability for errors or omissions in the data, their availability, or for any loss or damage arising from their use.
    </small>

		 </p>
		 </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <!-- Bootstrap JS for Responsive Components -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
    const map = L.map('map').setView([53, -8], 7);
	
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: 'Map data &copy; OpenStreetMap contributors'
    }).addTo(map);
	
	// Assuming you have already initialized your map as 'map'

// Create a control for toggling drag functionality

        // Disable dragging on mobile devices by default
        if (L.Browser.mobile) {
            map.dragging.disable();
        }


    const iconCache = {};

    function getIcon(symbol) {
        if (!symbol) {
            symbol = 'default';
        }

        if (!iconCache[symbol]) {
            iconCache[symbol] = L.icon({
                iconUrl: `icon/${symbol}.svg`,
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32],
            });
        }

        return iconCache[symbol];
    }

    const weatherData = <?php echo json_encode($allWeatherData); ?>;

    weatherData.forEach(point => {
        const lat = point.latitude;
        const lon = point.longitude;
        const weather = point.weather;
        const symbol = weather.symbol || 'default';

        const customIcon = getIcon(symbol);

        let popupContent = `<strong>Point: ${point.file}</strong><br>`;
        popupContent += `Temperature: ${weather.temperature !== undefined ? weather.temperature + '°C' : 'N/A'}<br>`;
        popupContent += `Wind: ${weather.windSpeed !== undefined ? weather.windSpeed + ' m/s' : 'N/A'} at ${weather.windDirection !== undefined ? weather.windDirection + '°' : 'N/A'}<br>`;
        popupContent += `Humidity: ${weather.humidity !== undefined ? weather.humidity + '%' : 'N/A'}<br>`;
        popupContent += `Pressure: ${weather.pressure !== undefined ? weather.pressure + ' hPa' : 'N/A'}<br>`;
        popupContent += `Cloudiness: ${weather.cloudiness !== undefined ? weather.cloudiness + '%' : 'N/A'}<br>`;
        popupContent += `Low Clouds: ${weather.lowClouds !== undefined ? weather.lowClouds + '%' : 'N/A'}<br>`;
        popupContent += `Dew Point: ${weather.dewPoint !== undefined ? weather.dewPoint + '°C' : 'N/A'}<br>`;
        popupContent += `Precipitation: ${weather.precipitation !== null && weather.precipitation !== undefined ? weather.precipitation + ' mm' : 'N/A'}<br>`;
        popupContent += `Precipitation Probability: ${weather.probability !== null && weather.probability !== undefined ? weather.probability + '%' : 'N/A'}<br>`;
        popupContent += `Symbol: ${weather.symbol !== null && weather.symbol !== undefined ? weather.symbol : 'N/A'}<br>`;

        L.marker([lat, lon], { icon: customIcon }).addTo(map)
            .bindPopup(popupContent);
    });

    // Handle window resize for better mobile experience
    function onResize() {
        setTimeout(function(){ map.invalidateSize() }, 100);
    }

    // Listen for window resize events
    window.addEventListener('resize', onResize);

    if (window.ResizeObserver) {
        const resizeObserver = new ResizeObserver(() => {
            setTimeout(() => {
                map.invalidateSize();
            }, 100);
        });
        resizeObserver.observe(document.querySelector('#map'));
    }
    </script>

</body>
</html>