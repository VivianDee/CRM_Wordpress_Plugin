<?php

if (!class_exists('Bema_Settings')) {
    class Bema_Settings
    {
        private $custom_event_name = '';
        public static $options;

        public function __construct() {
            try {
            self::$options = get_option('bema_crm_options');
            add_action('admin_init', array($this, 'admin_init'));
            add_action('sync_all_campaigns_event', array($this, 'sync_all_campaigns'));
            add_action('sync_selected_campaigns_event', array($this, 'sync_all_campaigns'));
            add_action('wp_ajax_sync_all_campaigns_action', array($this, 'start_sync'));
            add_action('wp_ajax_sync_selected_campaigns_action', array($this, 'start_selected_sync'));
            add_action('wp_ajax_stop_sync_action', array($this, 'stop_sync'));
            add_action('wp_ajax_delete_campaign_action', array($this, 'delete_campaign_callback'));
            add_action('wp_ajax_sync_campaigns_action', array($this, 'sync_all_campaigns'));
            add_action('wp_ajax_update_sync_status_action', array($this, 'sync_status'));
            add_action('wp_ajax_em_sync_form_new_subscriber', array($this, 'sync_all_campaigns'));
            add_action('wp_ajax_nopriv_em_sync_form_new_subscriber', array($this, 'sync_all_campaigns'));
            



            // Schedule the recurring event on activation
            register_activation_hook(__FILE__, array($this, 'schedule_recurring_sync'));
            // Remove the recurring event on deactivation
            register_deactivation_hook(__FILE__, array($this, 'unschedule_recurring_sync'));
            } catch (Exception $e) {
                $this->logToFile('Error in constructor: ' . $e->getMessage(), 'error');
            }

        }

        public function admin_init() {
            // Register settings
            register_setting('bema_crm_group', 'bema_crm_options', array($this, 'bema_crm_validate'));

            // Add settings section
            add_settings_section(
                'bema_crm_main_section',
                'EM_Sync Settings',
                null,
                'bema_crm_page1'
            );

            // EM_Sync Campaign Prefix Setting
            add_settings_field(
                'bema_crm_new_campaign',
                'New Campaign Name',
                array($this, 'bema_crm_campaign_callback'),
                'bema_crm_page1',
                'bema_crm_main_section',
                array(
                    'label_for' => 'bema_crm_new_campaign'
                )
            );

            add_settings_field(
                'bema_crm_new_album',
                'New Album Name',
                array($this, 'bema_crm_album_callback'),
                'bema_crm_page1',
                'bema_crm_main_section',
                array(
                    'label_for' => 'bema_crm_new_album'
                )
            );

            add_settings_section(
                'bema_crm_saved_section',
                'Saved Campaigns and Prefixes',
                array($this, 'display_saved_campaigns'),
                'bema_crm_page1',
            );

            // Add a button to trigger synchronization
            add_settings_field(
                'em_sync_trigger_button',
                'Sync All Campaigns',
                array($this, 'em_sync_trigger_button_callback'),
                'bema_crm_page1',
                'bema_crm_main_section',
                array(
                    'label_for' => 'em_sync_trigger_button'
                )
            );

        }

        private function logToFile($message, $logType = 'info') {
            date_default_timezone_set('Africa/Lagos'); // Set timezone to Lagos, Nigeria
            $logPath = BEMA_PATH . 'log/';  // Specify the path where you want to store the log file
    
            // Create a log file if not exists
            $logFile = $logPath . 'log_' . date('Y-m-d') . '.log';
    
            // Log timestamp
            $timestamp = date('Y-m-d H:i:s');
    
            // Log message format
            $logMessage = "[$timestamp] [$logType]:  $message\n";
    
            // Append the log message to the log file
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }

        public function sync_status() {
            if (isset($_POST['sync_status']) && isset($_POST['button2'])) {
                self::$options['em_sync_selected_status'] = sanitize_text_field($_POST['sync_status']);

                // Save the updated options
                update_option('bema_crm_options', self::$options);

            } elseif (isset($_POST['sync_status'])) {
                self::$options['em_sync_run_status'] = sanitize_text_field($_POST['sync_status']);

                // Save the updated options
                update_option('bema_crm_options', self::$options);
            } else {
                $this->logToFile('Failed to set sync status in class.bema-settings.php', 'error');
            }
        }

        public function bema_crm_campaign_callback() {
            ?>
            <div>
                <input
                    type="text"
                    name="bema_crm_options[bema_crm_new_campaign]"
                    id="bema_crm_new_campaign_input"
                    placeholder="Enter new campaign name"
                >
            </div>
            <?php
        }

        public function bema_crm_album_callback() {
            ?>
            <div>
                <input
                    type="text"
                    name="bema_crm_options[bema_crm_new_album]"
                    id="bema_crm_new_album_input"
                    placeholder="Enter new album name"
                >
            </div>
            <?php
        }


        public function schedule_recurring_sync($campaigns = null) {
            if (!wp_next_scheduled($this->custom_event_name)) {
                wp_schedule_event(time(), 'every_five_minutes', $this->custom_event_name, array($campaigns));
                return;
            }
        }

        public function unschedule_recurring_sync() {
            $event = $this->custom_event_name;
            if ($event) {
                wp_clear_scheduled_hook($this->custom_event_name);
                wp_unschedule_hook($event);
            } else {
                $message = 'Error unscheduling the reoccuring event ' . $event;
                $this->logToFile($message, 'error');
            }
            
        }

        public function stop_sync() {
            if (isset($_POST['event'])) {
                $this->custom_event_name = $_POST['event'];
                // Clear the scheduled event
                $this->unschedule_recurring_sync();
            } else {
                $this->logToFile('Specify event to unschedule in stop_sync()  class.bema-settings.php', 'error');
            }
        }

        public function start_sync() {
            if (isset($_POST['event'])) {
                $this->custom_event_name = sanitize_text_field($_POST['event']);
                // Clear the scheduled event
                $this->schedule_recurring_sync();
            } else {
                $this->logToFile('Specify event to schedule in start_sync()  class.bema-settings.php', 'error');
            }
            
        }

        public function start_selected_sync() {
            if (isset($_POST['event'])) {
                $this->custom_event_name = sanitize_text_field($_POST['event']);
            } else {
                $this->logToFile('Specify event to schedule in start_selected_sync()  class.bema-settings.php', 'error');
            }
            $campaigns = isset($_POST['selectedCampaigns']) ? $_POST['selectedCampaigns'] : null;
            // Clear the scheduled event
            if ($campaigns) {
                $this->schedule_recurring_sync($campaigns);
            } else {
                $this->logToFile('Specify Campaign to sync start_selected_sync()  class.bema-settings.php', 'error');
            }
        }
            

        // Function to execute when the action is triggered
        public function sync_all_campaigns($selectedCampaigns) {
            // API keys for MailerLite and EDD
            $mailerLiteApiKey = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI0IiwianRpIjoiNDc1ZmYwZTYxMWViYjMyMzY4MDk0MGRjOGM0ZDU4ZGJmZDRkNzJiMzM2Yzk5MDAyOTVjNTVmODA5ZTEyMmJiOTE3ZTZmNDczNDViOWUxMDciLCJpYXQiOjE2Nzc4NTk0NjEuNDc0NjAxLCJuYmYiOjE2Nzc4NTk0NjEuNDc0NjAzLCJleHAiOjQ4MzM1MzMwNjEuNDcwNTI5LCJzdWIiOiIzNzM1ODciLCJzY29wZXMiOltdfQ.KTc4oAfVXKtoe-kDhFWLoJ3_914_8jLBfGRCs99A9OCAm4jpJaiKONYbDX3yiSUVL7dAfCsEL5U1F_E02lo8uZqaR0OzLS9-yutT9jAxha6zyS8oZ5rL3ObftGP5EK6US1jQjw-mdtXYK_Q46eDDJ9bx8n6lGCUugcubT_tMQO5MXKzb_ENBOB9wOGCPeM6F3KkC3C_Wm_1B9Rzh6xXHRpFGt0Vr2SSCi2DlyC2UOX352SIwr5O-Z0XFSBazbfJtWpZ11QPEJuoGEWA7pgpXihZTzipK9yCtbtjzr0VxDOEX6ze344lKNda6aX33kJc-j3Iq1LuT0LlUl7sQseUixvlp3GXzODWhUi3wePEWm2S2X10AFi33OQPnCjtMeUN6Nr2ZToeMpwo5ZmkLjRP3LY-BNYqHzBqFQhD_ao8SGx12AmupOr9x6VhF2QAYWetaHnvoXBhJ9NOSHhVoDNsYrvBX9KkpGwpNVmySD4lpjBFPs7kxt6pcjw3hbe-hNHqlEX3ICrObYtONuLU6uRNJZ2krQexHxWS6DrKfR2JdsvI_mV9fhoV9LSxHwo-gpNziHm0vW4hCo7MBGbQednlZxN9BYZAVCxBaLVJb3V9oP991fDthWziJveI7x3gsoWSnVfuXVJltp_lYolsxsrT9Tk68if6QxzV78AEBz1fkAD8';
            $eddApiKey = '1efc9adf670dca5378684327884b0f9d';
            $eddToken = 'aec529b9dca4e419212e80d7e6d3ad08';


            // Create an instance of the em_sync class
            require_once(BEMA_PATH . 'em_sync/class.em_sync.php');
            $sync = new em_sync($mailerLiteApiKey, $eddApiKey, $eddToken);


            // Add a new subscriber from bema store
            if (isset($_POST['bema_crm_new_subscriber'])) {
                $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
                $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

                if (!$email) {
                    wp_send_json_error(array('message' => 'Missing email field.'));
                    return;
                } else if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    

                    // Call the add_crm_emails method to add the new CRM entry
                    $message_from_sync = $sync->syncAll([], $email);

                    $message = "class.bema-settings.php ->  added a new email from the Bema store" . json_encode($email);
                    if ($message_from_sync) {
                        $this->logToFile($message_from_sync, 'info');
                        // Send error response
                        wp_send_json_error(array('message' => "You are already subscribed. Thank you!"));
                    } else {
                        $this->logToFile($message, 'info');
                        // Send success response
                        wp_send_json_success(array('message' => 'Subscriber added successfully.'));
                    }

                    
                    return;

                }
                wp_send_json_error(array('message' => 'Invalid email format'));
                return;

            }

            //Run sync on a number of selected campaigns
            if ($selectedCampaigns) {
                $campaignsToSync = $selectedCampaigns;
                $sync->syncAll($campaignsToSync);
                $message = "class.bema-settings.php ->  Ran sync for " . json_encode($campaignsToSync);
                $this->logToFile($message, 'info');
                return;
            }

            //Run sync on 1 campaign
            if (isset($_POST['prefix']) && isset($_POST['album'])) {
                $prefix = sanitize_text_field($_POST['prefix']);

                $album = sanitize_text_field($_POST['album']);
                $sync->syncAll([$prefix => $album]);
                $message = "class.bema-settings.php ->  Ran sync once for " . json_encode($prefix);
                $this->logToFile($message, 'info');
                return;
            }

            // Retrieve saved options
            $campaignsToSync = self::$options['sync_arr'];
            // Call the syncAll method
            $sync->syncAll($campaignsToSync);
            $message = "class.bema-settings.php ->  Ran sync for all saved campaigns";
            $this->logToFile($message, 'info');
        }


        public function em_sync_trigger_button_callback() {
            $this->custom_event_name = 'sync_all_campaigns_event';
            // Retrieve sync status from the options or transient
            $sync_status = isset(self::$options['em_sync_run_status']) ? self::$options['em_sync_run_status'] : "";
            ?>
            <button type="button" id="sync-all-campaigns" class="button">
            <?php
                // Set button text based on sync status
                echo ($sync_status === 'Syncing...') ? 'Syncing...' : 'Sync All Campaigns';
            ?>
            </button>
            <button type="button" id="stop-sync" class="button" style="display: none;">Stop Sync</button>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    // Check sync status when the page loads
                    var syncStatus = '<?php echo $sync_status; ?>';

                    if (syncStatus === 'Syncing...') {
                        // If syncing, set the button text and show the "Stop Sync" button
                        document.getElementById('sync-all-campaigns').innerHTML = 'Syncing...';
                        document.getElementById('stop-sync').style.display = 'inline-block';
                        document.getElementById('sync-all-campaigns').disabled = true;
                    }
                });

                document.getElementById('sync-all-campaigns').addEventListener('click', async function () {
                    // Change button text to "Syncing..."
                    this.innerHTML = 'Syncing...';

                    // Disable the button to prevent multiple clicks
                    this.disabled = true;
                    // Show the "Stop Sync" button
                    document.getElementById('stop-sync').style.display = 'inline-block';
                    document.getElementById('stop-sync').style.marginLeft = '10px';

                    // Set the sync status to 'syncing' using AJAX
                    var sync_data = {
                        'action': 'update_sync_status_action',
                        'sync_status': 'Syncing...',
                    };

                    // Make the AJAX request
                    await new Promise(resolve => {
                        jQuery.post(ajaxurl, sync_data, function (response) {
                            // Handle the response if needed
                            console.log(response);
                            resolve();
                        });
                    });

                    // Trigger synchronization using AJAX
                    var data = {
                        'action': 'sync_all_campaigns_action',
                        'event' : 'sync_all_campaigns_event',
                    };
                    

                    // Make the AJAX request
                    await new Promise(resolve => {
                        jQuery.post(ajaxurl, data, function(response) {
                            // Handle the response if needed
                            console.log(response);
                            resolve();
                        });
                    });
                    // Reload the page
                    location.reload();
                });

                document.getElementById('stop-sync').addEventListener('click', async function () {
                    // Change button text to "Stopping..."
                    this.innerHTML = 'Stopping...';
                    
                    // Set the sync status to 'syncing' using AJAX
                    var sync_data = {
                        'action': 'update_sync_status_action',
                        'sync_status': 'Not Syncing',
                    };

                    // Make the AJAX request
                    await new Promise(resolve => {
                        jQuery.post(ajaxurl, sync_data, function (response) {
                            // Handle the response if needed
                            console.log(response);
                            resolve();
                        });
                    });


                    var data = {
                            'action': 'stop_sync_action',
                            'event' : 'sync_all_campaigns_event',
                        };

                    // Disable the button to prevent multiple clicks
                    this.disabled = true;

                    // Hide the "Stop Sync" button
                    this.style.display = 'none';

                    // Make the AJAX request
                    await new Promise(resolve => {
                        jQuery.post(ajaxurl, data, function(response) {
                            // Handle the response if needed
                            console.log(response);
                            resolve();
                        });
                    });
                    // Reset the "Sync All Campaigns" button
                    document.getElementById('sync-all-campaigns').innerHTML = 'Sync All Campaigns';
                    document.getElementById('sync-all-campaigns').disabled = false;
                    // Reload the page
                    location.reload();

                    
                });
            </script>
            <?php
        }

        public function display_saved_campaigns() {
            // Retrieve saved options
            $em_sync = self::$options['sync_arr'];
            $row = 0;
            // Retrieve sync status from the options or transient
            $sync_status = isset(self::$options['em_sync_selected_status']) ? self::$options['em_sync_selected_status'] : "";

            if (!empty($em_sync)) {
                ?>
                <style>
                    .table1 {
                        border-collapse: collapse;
                        width: 70%;
                    }
                    .table2 {
                        border: 1px solid black;
                    }
        
                    .table3 {
                        padding: 10px;
                        text-align: left;
                    }
                </style>
                <div>
                <table class='table1 table2'>
                    <thead>
                        <tr>
                            <th class='table3'>Campaign Prefix</th>
                            <th class='table3'>Campaign Album</th>
                            <th class='table3'>Selected</th>
                            <th class='table3'>Sync/Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($em_sync as $campaign_prefix => $album) {
                            ?>
                            <tr>
                                <td class='table3'><?php echo esc_html($campaign_prefix); ?></td>
                                <td class='table3'><?php echo esc_html($album); ?></td>
                                <td class='table3'>
                                <input type="checkbox" class="campaign-checkbox" data-prefix="<?php echo esc_attr($campaign_prefix); ?>" data-album="<?php echo esc_attr($album); ?>" />
                                </td>
                                <td class='table3'>
                                    <button class="button sync" data-prefix="<?php echo esc_attr($campaign_prefix); ?>" data-album="<?php echo esc_attr($album); ?>">Sync</button>
                                    <button class="button delete" id="<?php echo $row + 1; $row = $row + 1;?>" style="margin-left: 5px" data-prefix="<?php echo esc_attr($campaign_prefix); ?>">Delete</button>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
                <button class="button sync-selected" id="sync-selected" style="margin-top: 20px;">
                <?php
                    // Set button text based on sync status
                    echo ($sync_status === 'Syncing...') ? 'Syncing...' : 'Sync Selected Campaigns';
                ?>
                </button>
                <button type="button" id="stop-selected_sync" class="button stop-selected_sync" style="display: none; margin-top: 20px; margin-left: 10px;">Stop Sync</button>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var syncStatus = '<?php echo $sync_status; ?>';

                        if (syncStatus === 'Syncing...') {
                            // If syncing, set the button text and show the "Stop Sync" button
                            document.getElementById('sync-selected').innerHTML = 'Syncing...';
                            document.getElementById('stop-selected_sync').style.display = 'inline-block';
                            document.getElementById('sync-selected').disabled = true;
                        }
                        count = 0;
                        var syncButtons = document.querySelectorAll('.sync');
                        let deleteButtons = document.querySelectorAll('.delete');
                        let syncSelectedButton = document.querySelector('.sync-selected');
                        let syncStopButton = document.querySelector('.stop-selected_sync');

                        syncButtons.forEach(function (button) {
                            button.addEventListener('click', async function () {
                                this.innerHTML = 'Sync';

                                // Disable the button to prevent multiple clicks
                                this.disabled = true;
                                var prefix = this.getAttribute('data-prefix');
                                var album = this.getAttribute('data-album');

                                


                               // AJAX request to trigger synchronization
                                var data = {
                                    'action': 'sync_campaigns_action',
                                    'prefix': prefix,
                                    'album': album,
                                };

                                await new Promise(resolve => {
                                    jQuery.post(ajaxurl, data, function(response) {
                                        // Handle the response if needed
                                        console.log(response);

                                        // Enable the button after 3 minutes
                                        setTimeout(function () {
                                            this.disabled = false;
                                            resolve();
                                        }.bind(this), 60 * 1000);
                                    }.bind(this));
                                });
                            });
                        });

                        deleteButtons.forEach(function (button) {
                            button.addEventListener('click', async function () {
                                var prefix = this.getAttribute('data-prefix');

                                // AJAX request to trigger deletion
                                var data = {
                                    'action': 'delete_campaign_action',
                                    'prefix': prefix,
                                };

                                // Make the AJAX request
                                await new Promise(resolve => {
                                    jQuery.post(ajaxurl, data, function(response) {
                                        // Handle the response if needed
                                        console.log(response);
                                        resolve();
                                    });
                                });
                            });
                        });

                        syncSelectedButton.addEventListener('click', async function () {
                            // Handle synchronization of selected campaigns here
                            var selectedCheckboxes = document.querySelectorAll('.campaign-checkbox:checked');
                            var selectedCampaigns = {};

                            // Check if any checkboxes are checked
                            if (selectedCheckboxes.length === 0) {
                                // Display a message or take any other action as needed
                                console.log('No checkboxes are checked.');
                                alert('No checkboxes are checked.');
                                return;
                            }

                            selectedCheckboxes.forEach(function (checkbox) {
                                var prefix = checkbox.getAttribute('data-prefix');
                                var album = checkbox.getAttribute('data-album');
                                selectedCampaigns[prefix] = album;
                            });

                            // Change button text to "Syncing..."
                            this.innerHTML = 'Syncing...';

                            // Disable the button to prevent multiple clicks
                            this.disabled = true;
                            // Show the "Stop Sync" button
                            document.getElementById('stop-selected_sync').style.display = 'inline-block';
                            document.getElementById('stop-selected_sync').style.marginLeft = '10px';

                            // Set the sync status to 'syncing' using AJAX
                            var data = {
                                'action': 'update_sync_status_action',
                                'button2': 'button2',
                                'sync_status': 'Syncing...',
                            };

                            // Make the AJAX request
                            await new Promise(resolve => {
                                jQuery.post(ajaxurl, data, function (response) {
                                    // Handle the response if needed
                                    console.log(response);
                                    resolve();
                                });
                            });



                            // AJAX request to trigger synchronization for selected campaigns
                            var sync_data = {
                                'action': 'sync_selected_campaigns_action',
                                'event' : 'sync_selected_campaigns_event',
                                'selectedCampaigns': selectedCampaigns,
                            };

                            await new Promise(resolve => {
                                jQuery.post(ajaxurl, sync_data, function(response) {
                                    // Handle the response if needed
                                    console.log(response);
                                    resolve();
                                });
                            });

                            // Reload the page
                            location.reload();
                        });

                        syncStopButton.addEventListener('click', async function () {
                            // Change button text to "Stopping..."
                            this.innerHTML = 'Stopping...';
                        

                             // Set the sync status to 'syncing' using AJAX
                             var sync_data = {
                                'action': 'update_sync_status_action',
                                'button2': 'button2',
                                'sync_status': 'Not Syncing',
                            };

                            // Make the AJAX request
                            await new Promise(resolve => {
                                jQuery.post(ajaxurl, sync_data, function (response) {
                                    // Handle the response if needed
                                    console.log(response);
                                    resolve();
                                });
                            });



                            // AJAX request to trigger synchronization for selected campaigns
                            var data = {
                                'action': 'stop_sync_action',
                                'event' : 'sync_selected_campaigns_event',
                            };

                            // Disable the button to prevent multiple clicks
                            this.disabled = true;

                            // Hide the "Stop Sync" button
                            this.style.display = 'none';
                            
                            // Make the AJAX request
                            await new Promise(resolve => {
                                jQuery.post(ajaxurl, data, function(response) {
                                    // Handle the response if needed
                                    console.log(response);
                                    resolve();
                                });
                            });

                            // Reset the "Sync All Campaigns" button
                            document.getElementById('sync-selected').innerHTML = 'Sync Selected Campaigns';
                            document.getElementById('sync-selected').disabled = false;
                            // Reload the page
                            location.reload();

                            
                        });
                    });

                    function syncAllCampaigns(prefix, album) {
                        
                    }
                </script>
                <?php
            } else {
                echo '<p>No campaigns and prefixes saved yet.</p>';
            }
        }

        public function delete_campaign_callback() {
            if (isset($_POST['prefix'])) {
                $prefix_to_delete = sanitize_text_field($_POST['prefix']);

                // Retrieve saved options
                $em_sync = self::$options['sync_arr'];
                // Remove the campaign with the specified prefix from the array
                if (isset($em_sync[$prefix_to_delete])) {
                    unset($em_sync[$prefix_to_delete]);

                    self::$options['sync_arr'] =  $em_sync;

                    // Save the updated options
                    update_option('bema_crm_options', self::$options);
                }

                // Send a response (if needed)
                $message = "class.bema-settings.php ->  Campaign ". $prefix_to_delete . " was deleted in delete_campaign_callback()";
                $this->logToFile($message, 'info');
            } else {
                $message = "class.bema-settings.php ->  Campaign was not deleted in delete_campaign_callback()";
                $this->logToFile($message, 'error');
            }
        }

        public function bema_crm_validate($input) {
            $new_input = array();
            
            // Retrieve existing values
            $saved_campaigns = isset(self::$options['bema_crm_saved_campaigns']) ? self::$options['bema_crm_saved_campaigns'] : array();
            $saved_albums = isset(self::$options['bema_crm_saved_albums']) ? self::$options['bema_crm_saved_albums'] : array();
            $em_sync = isset(self::$options['sync_arr']) ? self::$options['sync_arr'] : array();
            $em_sync_selected = isset(self::$options['sync_selected']) ? self::$options['sync_selected'] : array();

            foreach ($input as $key => $value) {
                switch ($key) {
                    case 'bema_crm_new_campaign':
                        if (!empty($value)) {
                            // Add the new campaign to the saved campaigns list
                            $saved_campaigns[] = sanitize_text_field($value);
                            $em_sync[sanitize_text_field($value)] = sanitize_text_field($input['bema_crm_new_album']);

                            // Log the new input for debugging
                            $message = 'New Campaign Added: ' . $value;
                            $this->logToFile($message, 'info');
                        }
                        break;
                    case 'bema_crm_new_album':
                        if (!empty($value)) {
                            // Add the new album to the saved albums list
                            $saved_albums[] = sanitize_text_field($value);

                            // Log the new input for debugging
                            $message = 'New Album Added: ' . $value;
                            $this->logToFile($message, 'info');
                        }
                        break;
                    case 'sync_selected':
                        if (!empty($value)) {
                            // Validate and sanitize run status
                            $selected[] = sanitize_text_field($input['sync_selected']);

                            // Log the new input for debugging
                            $message = 'New Selected Ststus: ' . $value;
                            $this->logToFile($message, 'info');
                        }
                        break;
                    default:
                        $new_input[$key] = sanitize_text_field($value);
                        break;
                }
            }

            // Store the updated lists
            $new_input['bema_crm_saved_campaigns'] = $saved_campaigns;
            $new_input['bema_crm_saved_albums'] = $saved_albums;
            $new_input['sync_arr'] = $em_sync;
            $new_input['sync_selected'] = $selected;
            $new_input['em_sync_selected_status'] = isset(self::$options['em_sync_selected_status']) ? self::$options['em_sync_selected_status'] : '';
            $new_input['em_sync_run_status'] = isset(self::$options['em_sync_run_status']) ? self::$options['em_sync_run_status'] : '';

            return $new_input;
        }

    }
}
