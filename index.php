<?php

date_default_timezone_set('CET'); // modify if not Europe/Berlin
$title = "=== watchpower-voltronic-log-web-charts v1.5 ===";

/* what is it?
 * nice graphical web view of voltronic-masterpower-watchpower output logs
 * requirements:
 *  o have watchpower running on GNU linux
 *  o enable top right corner -> debug mode -> it will output log files
 *  o softlink data directory to the dir where the log files are
 *  o browser -> index.php
 *  there should be nice graphical output of current power values
 *  refreshrate is per default 2sec
 *  :D
 */

// === config === please: user modify

$path2data = "./data";
$input_skip = 5; // show every nth datapoint (if too much data in the logs (millions of recrods)

$refresh_auto = 5; // refresh automatically with same parameter every 5min
$auto_reload_string = "on"; // default

$watts_used_by_inverter = 50; // how much Watts the inverter itself uses to keep itself operational (especially in winter times of low light energy this value matters)
$stats_kWh_used_by_inverter = 0;

// === startup defaults values === DO NOT modify
$parameter_last = ""; // save last used parameter for to refresh with the same parameters
$input_show = "ShowAll"; // holds the date to show 2023-12-12 or ShowAll (all dates from all logs = can be A LOT OF DATAPOINTS (like millions) = heavy on CPU client side js)

$array_stats = array(); // hold all timestamps with same index positions as in $lines_less
$stats_kWh_produced = 0;
$stats_kWh_used = 0;

/* hold the data */
$chart_data_string_date = "[";
$chart_data_string_solar_input_watts = "[";
$chart_data_string_used_watts = "[";
$chart_data_string_batt_volt = "[";

// iterate over all files in the ./data directory
$array_files = array();
$array_files_show = Array();

if(isset($_COOKIE['auto_reload_string']))
{
    $auto_reload_string = json_decode($_COOKIE['auto_reload_string'], true);
}
if($handle = opendir($path2data))
{
    while (false !== ($filename = readdir($handle)))
    {
        if ('.' === $filename) continue;
        if ('..' === $filename) continue;
        // do something with the file
        $array_filename_segments = explode('.',$filename);

        if(end($array_filename_segments) == "log") // if file ending is log proceed
        {
            if(str_contains($filename, " USB-QPIGS.log"))
            {
                array_push($array_files,$filename);
            }
        }
    }
    closedir($handle);
}

/* sort filenames by date  */
$order = array();

// sanitize input
if(isset($_REQUEST["button"]))
{
    $input_show = htmlspecialchars($_REQUEST["button"]);
    if(isset($_REQUEST["auto_reload"])) $auto_reload_string = htmlspecialchars($_REQUEST["auto_reload"]);
    setcookie('auto_reload_string', json_encode("on"), time()+99999);
    $parameter_last = $input_show; // auto refresh with same parameter
}

$target = count($array_files);
for($i=0;$i<$target;$i++)
{
    $filename = $array_files[$i];
    $array_filename_segments = explode(" ", $filename);
    $logfile_date_string = $array_filename_segments[0];
    $logfile_date_string = str_replace("_","-",$logfile_date_string);
    $array_files[$i] = $logfile_date_string; // changes "2023-10-30 USB-QPIGS.log" to simply "2023-10-30" otherwise no sort possible
    
    $logfile_date = date( 'Y-m-d', strtotime( $logfile_date_string ) );
    $order[] = strtotime($logfile_date);
}

array_multisort($order, SORT_ASC, $array_files); // does the sorting


if($input_show == "ShowAll")
{
    $array_files_show = $array_files; // show all
}
else if($input_show == "today")
{
    array_push($array_files_show,end($array_files)); // show all
}
else // it will be a date
{
    foreach ($array_files as $key => $value)
    {
        if($value == $input_show)
        {
            array_push($array_files_show,$input_show);
            break;
        }
    }
}


// iterate over all lines and extract values
foreach ($array_files_show as $key => $value)
{
    $fileName = $value;
    $fileName_and_path = $path2data."/".$fileName." USB-QPIGS.log";
    $lines = file($fileName_and_path) or die("can't open ".$filename_and_path." file"); // changes "2023-10-30" back to full filename "2023-10-30 USB-QPIGS.log"

    // reduce dataset if too large
    $lines_less = array();
    $i = 0;
    foreach($lines as $value) {
        if ($i++ % $input_skip == 0) {
            $lines_less[] = $value;
        }
    }

    // generate chart.js strings
    $target = count($lines_less);
    for($i=0;$i<$target;$i++)
    {
        $line = $lines_less[$i];

        $line = trim($line, " \t."); // remove trailing newline
        $line = trim($line, " \n.");
        $line = trim($line, " \r.");
        $line = trim($line, " \v.");
        $line = trim($line, " \x00.");
        
        if(empty($line)) continue; // if line is empty, skip it
        
        $line = htmlspecialchars($line); // escape potential malicious input
        $line = str_replace("[", "",$line); // remove all [
        $line = str_replace("]", "",$line); // remove all ]
        $line = str_replace("(", " ",$line); // remove all ( and replace with " " because it is needed as separator
        
        $array_line = explode(' ',$line);
        
        if(isset($array_line[0]))
        {
            if(!empty($array_line[0]))
            {
                $date = $array_line[0]." ".$array_line[1]; // get date and time in one string
                
                // format in log files is 2023-10-30 07:06:55
                $date_parsed = DateTime::createFromFormat('Y-m-d H:i:s', $date);
                if ($date_parsed === false)
                {
                    die('PHP Error: could not parse date '.$date.' to timestamp, format should be like "2023-10-30 07:06:55"');
                }
                else
                {
                    $date_timestamp = $date_parsed->getTimestamp(); // ms since 1970-01-01
                }
                
                // write back to array in use for kWh calc
                $array_stats[$i]["timestamp"] = $date_timestamp;

                // search all elements of this array // replace by nothing  // in this string
                $chart_data_string_date = $chart_data_string_date.'"'.$date_timestamp.'", ';
            }
            else
            {
                $what_is_going_on = $array_line[0]; // for debugging purposes only :D
            }
        }
        
        // calc time difference since last datapoint, it is assumed that wattage stayed the same in this period
        if(isset($array_stats[$i-1])) // if there is no such element in the array this means the array is empty and currently processing the first element
        {
            if(!isset($array_stats[$i]["timestamp"]))
            {
                $time_diff_ms = 0;
            }
            else
            {
                $time_diff_ms = $array_stats[$i]["timestamp"] - $array_stats[$i-1]["timestamp"];
            }
        }
        else
        {
            $time_diff_ms = 0;
        }
        
        $time_diff_h = $time_diff_ms / 3600000;

        if(isset($array_line[7])) // field 7 = watts_used?
        {
            $watts = $array_line[7];
            $watts = ltrim($watts, '0'); // delete all leading 000123
            if(empty($watts)) $watts = "0"; // if value was 0000 it would be empty now so assign minimum value
            // search all elements of this array // replace by nothing  // in this string
            $chart_data_string_used_watts = $chart_data_string_used_watts.'"'.$watts.'", ';
            
            $stats_kWh_used = $stats_kWh_used + ($watts * $time_diff_h); // calc kWh
            $stats_kWh_used_by_inverter = $stats_kWh_used_by_inverter + ($watts_used_by_inverter * $time_diff_h); // calc kWh
        }
        
        if(isset($array_line[10])) // field 10 = batt voltage
        {
            $chart_data_string_batt_volt = $chart_data_string_batt_volt.'"'.$array_line[10].'", ';
        }

        if(isset($array_line[21])) // field 21 = watts PV input?
        {
            $watts = $array_line[21];
            $watts = ltrim($watts, '0'); // delete all leading 000123
            if(empty($watts)) $watts = "0"; // if value was 0000 it would be empty now so assign minimum value
            // search all elements of this array // replace by nothing  // in this string
            $chart_data_string_solar_input_watts = $chart_data_string_solar_input_watts.'"'.$watts.'", ';

            $stats_kWh_produced = $stats_kWh_produced + ($watts * $time_diff_h); // calc kWh
        }
    }
}

// remove last ,
$chart_data_string_date = substr($chart_data_string_date, 0, -1);
$chart_data_string_date = substr($chart_data_string_date, 0, -1);

$chart_data_string_used_watts = substr($chart_data_string_used_watts, 0, -1);
$chart_data_string_used_watts = substr($chart_data_string_used_watts, 0, -1);

$chart_data_string_solar_input_watts = substr($chart_data_string_solar_input_watts, 0, -1);
$chart_data_string_solar_input_watts = substr($chart_data_string_solar_input_watts, 0, -1);

$chart_data_string_batt_volt = substr($chart_data_string_batt_volt, 0, -1);
$chart_data_string_batt_volt = substr($chart_data_string_batt_volt, 0, -1);

// close the braket
$chart_data_string_date = $chart_data_string_date."]";
$chart_data_string_solar_input_watts = $chart_data_string_solar_input_watts."]";
$chart_data_string_used_watts = $chart_data_string_used_watts."]";
$chart_data_string_batt_volt = $chart_data_string_batt_volt."]";

$stats_kWh_produced = number_format((float)$stats_kWh_produced, 3, '.', '');
$stats_kWh_used = number_format((float)$stats_kWh_used, 3, '.', '');
$stats_kWh_used_by_inverter = number_format((float)$stats_kWh_used_by_inverter, 3, '.', '');

/* manual modifications

<canvas id="myChart" width="100%" height="100%" style="background-color: #444;"></canvas>


// Data
const data = [
<?php echo $chart_data_string_date.","; ?>
<?php echo $chart_data_string_used_watts; ?>
<?php echo $chart_data_string_solar_input_watts; ?>
];

/*
// Data
const data = [
[1702213297, 1702213308, 1702213317, 1702213327, 1699939996, 1699940001, 1699940016, 1699940021, 1698645904, 1698645917, 1698645926, 1698645936],
[122, 122, 123, 128, 159, 159, 158, 157, 35, 63, 68, 74],
[122, 122, 123, 128, 159, 159, 158, 157, 35, 63, 68, 74],
[122, 122, 123, 128, 159, 159, 158, 157, 35, 63, 68, 74],
[122, 122, 123, 128, 159, 159, 158, 157, 35, 63, 68, 74]
];
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <meta http-equiv="refresh" content="<?php if($auto_reload_string == "on"){ echo ($refresh_auto*60).";URL=index.php?button=".$parameter_last; } ?>"/>
  <title><?php echo $title; ?></title>
  <!-- Include Chart.js library -->
  <script src="js/chart.js"></script>
  <link rel="stylesheet" type="text/css" href="./css/style.css">
</head>
<body>
	<div id="div1" style="position: absolute; left: 0px; top: 0px; width: 100%; height: 100%;">
    	<div id="div2" style="position: relative; float: left; min-width: 100%;">
    		<?php echo "stats for selected dates: ".$stats_kWh_produced." kWh produced, ".$stats_kWh_used." kWh used by AC Out, inverter used: ".$stats_kWh_used_by_inverter." kWh "; ?>
    	</div>
    	<div id="div3" style="position: relative; float: left; min-width: 100%;">
    		<!-- <form class="form" action="index.php" method="post"><button name="button" value="ShowAll" type="submit" class="btn btn-primary">ShowAll</button></form>  -->
    		<a class="link_button_orange" href="./index.php?button=ShowAll">ShowAll</a>
    		<?php
    		$target = count($array_files);
    		$target = $target - 1; // do not show last button, as "today" is last button
    		for($i=0;$i<$target;$i++)
    		{
    		    $array_filename_segments = explode(" ", $array_files[$i]);
    		    // work with form buttons
    		    // echo '<form class="form" action="index.php" method="post"><button name="button" value="'.$array_filename_segments[0].'" type="submit" class="btn btn-primary">'.$array_filename_segments[0].'</button></form>';
    		    // work with links
    		    echo '<a class="link_button_orange" href="./index.php?button='.$array_filename_segments[0].'">'.$array_filename_segments[0].'</a>';
    		}
    		?>
			<a class="link_button_orange" href="./index.php?button=today">today</a>
			<?php
                if($auto_reload_string == "on")
                {
                    echo '<a title="turn auto reload every '.$refresh_auto.'min on or off" id="button_enabled" class="link_button_orange" href="./index.php?button='.$parameter_last.'&auto_reload=off">auto_reload_on</a>';
                } else
                {
			         echo '<a title="turn auto reload every '.$refresh_auto.'min on or off" id="button_disabled" class="link_button_orange" href="./index.php?button='.$parameter_last.'&auto_reload=on">auto_reload_off</a>';
                }
			?>

		</div>
    <!-- Create a canvas element to render the chart -->
	<canvas id="myChart" width="2048" height="1024" style="background-color: #444;"></canvas>

  <script>
    // Data
    const data = [
    <?php echo $chart_data_string_date.",\n"; ?>
    <?php echo $chart_data_string_solar_input_watts.",\n"; ?>
    <?php echo $chart_data_string_used_watts.",\n"; ?>
    <?php echo $chart_data_string_batt_volt.",\n"; ?>
    ];

    // Extract x and y coordinates from the data
    const xValues = data[0];
    const yValues1 = data[1];
    const yValues2 = data[2];
    const yValues3 = data[3];
/*
    const yValues4 = data[4];
*/
    // Function to format timestamp to YYYY-MM-DD hh:mm:ss
    const formatTimestamp = (timestamp) => {
      const date = new Date(timestamp * 1000);
      const year = date.getFullYear();
      const month = (date.getMonth() + 1).toString().padStart(2, '0');
      const day = date.getDate().toString().padStart(2, '0');
      const hours = date.getHours().toString().padStart(2, '0');
      const minutes = date.getMinutes().toString().padStart(2, '0');
      // if user wants with seconds uncomment next 2 lines
      // const seconds = date.getSeconds().toString().padStart(2, '0');
      // return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
      return `${year}-${month}-${day} ${hours}:${minutes}`;
    };

    // Get the canvas element
    const ctx = document.getElementById('myChart').getContext('2d');

    // Create the chart
    const myChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: xValues.map(value => formatTimestamp(value)),
        datasets: [
          {
			label: 'Input Solar Watt',
            data: yValues1,
            borderColor: '#ffa500', /* dark orange */
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: '#c27e00', /* orange */
          },
          {
            label: 'Out AC Watt used',
            data: yValues2,
            borderColor: 'red', /* 'rgba(255, 99, 132, 1)' */
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: 'darkred', /* red: rgba(54, 162, 235, 1) */
          },
          {
            label: 'Battery V',
            data: yValues3,
            borderColor: '#19ff00', /* dark green */
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: '#298f1f', /* bright green */
          },
/*
          {
            label: 'Line 4',
            data: yValues4,
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 2,
            pointRadius: 5,
            pointBackgroundColor: 'rgba(75, 192, 192, 1)',
          },
*/
        ]
      },
      options: {
    	  scales: {
    	    xAxes: [{
    	      type: 'linear',
    	      position: 'bottom'
    	    }],
    	    /*
    	    yAxes: [{
    	      ticks: {
    	        min: 0,
    	      }
    	    }]
    	    */
    	  },
    	  /* does not look better with many datapoints while slowing down render performance 
    	  elements: {
    	    line: {
				cubicInterpolationMode: 'monotone',
				tension: 0.8, // level of interpolation (between 0 and 1)
    	    }
    	  }
    	  */
    	}
    });
  </script>
  </div>
</body>
</html>