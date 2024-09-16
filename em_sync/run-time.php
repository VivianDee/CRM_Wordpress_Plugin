<?php 
include 'EDD-MailerLite_Class.php';
 function myecho(){
    echo 'Time ' . time() . "\r\n";
 }



 // Define your custom function to be executed
// function my_custom_function() {
//     // Your code logic here
//     // This function will be executed on the defined timer
// }

// Hook a function to run on WordPress initialization
// add_action('wp_loaded', 'schedule_custom_function');

function schedule_custom_function($mysec, $maxsec, $syncCall, $campaignsToSyncCall) {
    $time = time(); // Get the current timestamp
    $maxtime = time()+$maxsec;
    $interval = $mysec; //12 * HOUR_IN_SECONDS; // Set the interval (twice daily in this example)

    while (true) {
        if ((time() >= $time) & (time() <= $maxtime)) {
            $start = time();
            myecho(); // Call your custom function
            $syncCall->syncAll($campaignsToSyncCall);
            $end = time();
            echo $end - $start . "\n";


            $time = time() + $interval; // Set the next execution time
        }
        sleep(3); // Sleep for 3 seconds to avoid continuous execution
    }
}

$max_time = 3600 * 24;
schedule_custom_function(180, $max_time, $sync, $campaignsToSync);