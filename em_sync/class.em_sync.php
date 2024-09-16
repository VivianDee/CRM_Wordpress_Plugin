<?php
/**
 * em_sync Class:
 * Coordinates synchronization between MailerLite, EDD, and a custom CRM. Methods include:
 * __construct($mailerLiteApiKey, $eddApiKey, $eddToken): Constructor to initialize the synchronization object.
 * syncMailerLiteSubscribers(): Synchronizes MailerLite subscribers.
 * syncEDDCustomers(): Synchronizes EDD customers.
 * get_crm_emails($crm_id = null): Retrieves CRM emails based on CRM ID or all CRM emails.
 * get_crm_mailerliteid($crm_id): Retrieves MailerLite Group IDs based on CRM ID.
 * add_crm_emails($data): Adds emails to the CRM.
 * syncAll($data): Synchronizes data from MailerLite, EDD, and the CRM.
 * setPurchaseIndicator($email, $edd_emails): Sets the purchase indicator based on EDD emails.
 * syncMailerlite($prefix, $crm_emails, $mailer_lite_emails, $edd_emails): Synchronizes MailerLite data.
 * sync_tier($ml_groupids, $crm_data): Synchronizes MailerLite tier information.
 * groupIds($prefix): Retrieves MailerLite group IDs based on a campaign prefix.
 * update_crm_user($crm_id, $data): Updates CRM data.
 */

class em_sync {
    private $mailerLiteInstance;
    private $eddInstance;


    public function __construct($mailerLiteApiKey, $eddApiKey, $eddToken) {
        require_once( BEMA_PATH . "em_sync/class.mailerlite.php" );
        $this->mailerLiteInstance = new MailerLite($mailerLiteApiKey);
        require_once( BEMA_PATH . "em_sync/class.edd.php" );
        $this->eddInstance = new EDD($eddApiKey, $eddToken);
    }

    private function syncMailerLiteSubscribers() {
        return $this->mailerLiteInstance->getSubscribers();
    }

    private function syncEDDCustomers($product) {
        return $this->eddInstance->getEDDSales($product);
    }

    private function logToFile($message, $logType = 'info') {
        $logPath = BEMA_PATH . 'em_sync/log/';  // Specify the path where you want to store the log file

        date_default_timezone_set('Africa/Lagos'); // Set timezone to Lagos, Nigeria

        // Create a log file if not exists
        $logFile = $logPath . 'log_' . date('Y-m-d') . '.log';

         // Log timestamp
        $timestamp = date('Y-m-d g:i:s A'); // Format hours in 12-hour time with uppercase AM/PM


        // Log message format
        $logMessage = "[$timestamp] [$logType]:  $message\n";

        // Append the log message to the log file
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    // Get Bema CRM data from the Custom tables
    private function get_crm_emails($crm_id = null) {
        global $wpdb;
    
        if ($crm_id) {
            $query = $wpdb->prepare("SELECT * FROM {$wpdb->bemameta} WHERE bema_id = %d ORDER BY date_added DESC LIMIT 1", $crm_id);
            $result = $wpdb->get_results($query, ARRAY_A);
    
            return json_encode($result, true);
        }

        $query = "
            SELECT * 
            FROM {$wpdb->bemameta} AS bm
            WHERE bm.date_added = (
                SELECT MAX(bm_sub.date_added)
                FROM {$wpdb->bemameta} AS bm_sub
                WHERE bm_sub.bema_id = bm.bema_id
            )
        ";
        $result = $wpdb->get_results($query, ARRAY_A);
    
        if ($result) {
            $crm_data = [];
            foreach ($result as $data) {
                $crm_data[] = [
                    'email' => $data['subscriber'],
                    'id' => $data['bema_id'],
                    'tier' => $data['tier'],
                    'indicator' => $data['purchase_indicator'],
                    'mailerlite_group_id' => $data['mailerlite_group_id'],
                    'campaign' => $data['campaign'],
                ];
            }
            return $crm_data;
        }
    
        return [];
    }

    // Update Bema CRM data
    private function update_crm_user($crm_id, $data) {
        global $wpdb;

        // Fetch existing CRM data for the given ID
        $existing_data = $this->get_crm_emails($crm_id);

        $merged_data = is_string($existing_data) ? json_decode($existing_data, true) : $existing_data; // Start with existing data

        foreach ($merged_data[0] as $key => $value) {
            // Add missing fields to merged_data
            if (!isset($data[$key]) && $key !== 'meta_id') {
                $data[$key] = $value;
            }
        }

        $insert_data = array(
            'bema_id'    => sanitize_text_field($data['bema_id']),
            'tier'  => sanitize_text_field($data['tier']),
            'purchase_indicator' => sanitize_text_field($data['purchase_indicator']),
            'campaign'  => sanitize_text_field($data['campaign']),
            'mailerlite_group_id'  => sanitize_text_field($data['mailerlite_group_id']),
            'candidate'  => sanitize_text_field($data['candidate']),
            'subscriber'  => sanitize_text_field($data['subscriber']),
            'source' => sanitize_text_field($data['source']),
        );
    
        $wpdb->insert(
            $wpdb->bemameta,
            $insert_data,
            array('%d', '%s', '%d', '%s', '%s','%s','%s','%s')
        );
    }

    private function get_site_users() {
        $users = get_users();

        $emails = array_map(function($user) {
            return $user->user_email;
        }, $users);

        return $emails;
    }

    private function add_crm_emails($data, $existingCrmData) {
        global $wpdb;
        $this->logToFile(json_encode($existingCrmData));
        // Check if the subscriber already exists in CRM
        if ($existingCrmData && in_array($data['subscriber'], array_column($existingCrmData, 'subscriber'))) {
            $this->logToFile("Subscriber with email '{$data['subscriber']}' already exists in CRM. Skipping...\n", 'info');
            //echo "Subscriber with email '{$data['subscriber']}' already exists in CRM. Skipping...\n";
            return "Subscriber with email '{$data['subscriber']}' already exists in CRM. Skipping...\n";
        }

        if ($existingCrmData && in_array($data['subscriber'], $existingCrmData)) {
            $this->logToFile("Subscriber with email '{$data['subscriber']}' already exists in CRM. Skipping...\n", 'info');
            //echo "Subscriber with email '{$data['subscriber']}' already exists in CRM. Skipping...\n";
            return "Subscriber with email '{$data['subscriber']}' already exists in CRM. Skipping";
        }

        $post_data = array(
            'post_type'    => 'bema_crm',
            'post_status'  => 'publish',
            'post_title'   => 'Bema User - ' . date('Y-m-d H:i:s'), // Dynamic title with timestamp
        );

        $post_id = wp_insert_post($post_data, true);
    
        // Prepare data for insertion
        $insert_data = array(
            'bema_id'    => $post_id,
            'tier'  => sanitize_text_field($data['tier']),
            'purchase_indicator' => sanitize_text_field($data['purchase_indicator']),
            'campaign'  => sanitize_text_field($data['campaign']),
            'mailerlite_group_id'  => sanitize_text_field($data['mailerlite_group_id']),
            'candidate'  => sanitize_text_field($data['candidate']),
            'subscriber'  => sanitize_text_field($data['subscriber']),
            'source' => sanitize_text_field($data['source']),
        );
    
        // Insert the data into the CRM table
        $wpdb->insert(
            $wpdb->bemameta,
            $insert_data,
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
    }

    // Method to set purchase indicator based on EDD emails
    private function setPurchaseIndicator($email, $edd_emails) {

        // Check if the email is present in EDD emails
        $purchase_indicator = in_array($email, $edd_emails);

        return $purchase_indicator;
    }

    // Synchronize Bema CRM tiers based on MailerLite group IDs and CRM data.
    private function sync_tier($ml_groupids, $crm_data, $crm_ml_ids, $prefix) {
        $updates = [];

        // Extract the candidate from the campaign prefix
        $start = strpos($prefix, '_');
        $end = strrpos($prefix, '_');

        if ($start !== false && $end !== false) {
            // Extract the content between the first and middle underscores in the prefix (i.e., Artist name)
            $candidate = substr($prefix, $start + 1, $end - $start - 1);
        }

        // Iterate through MailerLite group IDs
        foreach ($ml_groupids as $group_name => $group_id) {

            // Get subscribers for the current MailerLite group
            $subscribers = $this->mailerLiteInstance->getGroupsSubscribers($group_id);

            // Replace underscores with dashes in group name
            $group_name = str_replace('_', '-', $group_name);

            // Iterate through Bema CRM data
            foreach ($crm_data as $crm_id => $email) {
                // Check if the user is a subscriber in the current MailerLite group
                if ($crm_ml_ids[$email] !== $group_id && in_array($email, $subscribers)) {

                    // Prepare data for Bema CRM update
                    $updates[$crm_id] = [
                        'tier'              => $group_name,
                        'mailerlite_group_id' => $group_id,
                        'campaign'          => $prefix,
                        'candidate'         => $candidate,
                        'source' => 'Mailerlite'
                    ];
                }
            }
        }

        // Batch update Bema CRM with the prepared data
        foreach ($updates as $crm_id => $data) {
            $this->update_crm_user($crm_id, $data);
        }
    }

    // Retrieve MailerLite group IDs based on a campaign prefix.
    private function groupIds($prefix = null) {
        
        // Check if the prefix is a non-empty string
        if (!is_string($prefix) || empty($prefix)) {
            $this->logToFile("Campaign prefix not found.\ne.g 2024_NBL_1_Gold_purchased, 2024_NBL_1_Bronze \nprefix = 2024_NBL_1 \n", 'error');
            throw new InvalidArgumentException("Campaign prefix not found.\ne.g 2024_NBL_1_Gold_purchased, 2024_NBL_1_Bronze \nprefix = 2024_NBL_1 \n");
            return 1;
        }

        $prefix = $prefix . "_";

        // Retrieve all groups and their IDs from MailerLite
        $groups = $this->mailerLiteInstance->getGroups();

        // Desired group names to filter
        $desiredGroupNames = ['gold', 'gold_purchased', 'silver', 'silver_purchased', 'bronze', 'bronze_purchased', 'wood', 'opt-in'];

        // Array to store filtered group names and IDs
        $filteredGroups = [];

        // Filter out the relevant group IDs
        foreach ($groups as $groupName => $groupId) {

            foreach ($desiredGroupNames as $desiredName) {
                // Compare group names case-insensitively and check for a match with the desired names
                if (strtolower($groupName) === strtolower("{$prefix}{$desiredName}")) {

                    $filteredGroups[$desiredName] = $groupId;
                    break;
                }
            }
        }

        // if number of groups found is < 7 output the groups for debugging and throw an exception
        if (empty($filteredGroups)) {
            // Throw an exception if there are no groups
            $this->logToFile("Invalid campaign prefix", 'error');
            throw new InvalidArgumentException("Invalid campaign prefix\n");
        } elseif (count($filteredGroups) < 7) {
            $message = 'Invalid number of MailerLite Groups ' .json_encode($filteredGroups) . "\n";
            $this->logToFile($message, 'error');
            throw new InvalidArgumentException('Invalid number of groups ' .json_encode($filteredGroups) . "\n");
        } elseif ($filteredGroups) {
            // Return the filtered groups if everything is valid
            return $filteredGroups;
        }
    }

    // Synchronize the Bema CRM, Miailerlite, and EDD
    public function syncAll($data = [], $form_email = null) {

        // Validate input data
        if ((empty($data) || !is_array($data)) && !$form_email) {
            $err = 'Usage: syncAll(["campaign_name" => "Album1"])' . "\n" . $data;
            $this->logToFile($err, 'error');
            throw new InvalidArgumentException('Usage: syncAll(["campaign_name" => "Album1"])');
        } else {
            $data = array_reverse($data);
        }

        // Sync MailerLite subscribers and fetch data
        $ml_subscribers_data = $this->syncMailerLiteSubscribers();
        $mailer_lite_emails = array_column($ml_subscribers_data, 'email');

        /*foreach($ml_subscribers_data as $subscriber) {
            $mailer_lite_emails[] = $subscriber['email'];
        }
         */
        $message = "Number of Mailerlite subscribers: " . count($mailer_lite_emails) . "\n\n";
        $this->logToFile("Information from Mailerlite obtained\n{$message}", 'info');
        //echo "Information from Mailerlite obtained\n";
        //echo $message;

        // Fetch Bema CRM data and extract relevant information
        $crmData = $this->get_crm_emails();
        
        $crm_emails = array_column($crmData, 'email', 'id');
        $crm_info = array_column($crmData, 'tier', 'email');
        $crm_purchases = array_column($crmData, 'indicator', 'email');
        $crm_ml_ids = array_column($crmData, 'mailerlite_group_id', 'email');
        $crm_campaign = array_column($crmData, 'campaign', 'email');

        /*foreach($crmData as $crmEmail => $crmInfo) {
            $crm_emails[$crmInfo['id']] = $crmEmail;
            $crm_info[$crmEmail] =  $crmInfo['tier'];
            $crm_ml_ids[$crmEmail] = $crmInfo['mailerlite_group_id'];
            $crm_purchases[$crmEmail] = $crmInfo['indicator'];
        }*/

        $crm_message = "Number of users in CRM Database " . count($crm_emails) . "\n\n";
        $this->logToFile("Customer Information from CRM Database obtained\n{$crm_message}", 'info');
        //echo "Customer Information from CRM Database obtained\n";
        //echo $crm_message

        if ($form_email) {
            // Collect user data
            $new_crm_data = [
                'tier' => 'unassigned',
                'purchase_indicator' => 0,
                'campaign' => '',
                'mailerlite_group_id' => '0',
                'candidate' => '',
                'subscriber' => $form_email,
                'source' => 'Bema Store',
            ];
            // Add the user to Bema CRM
            $first_message = $this->add_crm_emails($new_crm_data, $crm_emails);

            return $first_message;
            
            
        }

         // Fetch site users and remove edd support email
        $users = $this->get_site_users();
        $site_users = array_diff($users, array('support@easydigitaldownloads.com'));
        $users_message = "Number of registered users on site " . count($site_users) . "\n\n";
        $this->logToFile("Users Information from site obtained\n{$users_message}", 'info');
        //echo "Users Information from site obtained\n";
        //echo $users_message;


        // Check if Bema CRM database is empty
        if (empty($crm_emails)) {
            $this->logToFile("No records found in the CRM database. Initiating synchronization with new data.\n", 'info');
            //echo "No records found in the CRM database. Initiating synchronization with new data.\n";
        }

        // Sync EDD customers and fetch emails
        $edd_data = $this->syncEDDCustomers(null);
        if (isset($edd_data['edd_emails'])) {     
            $edd_emails = $edd_data['edd_emails'];
        } else {
            $edd_emails = [];
        }
        $edd_message = "Number of Customers from EDD: " . count($edd_emails) . "\n\n";
        $this->logToFile("Customer Information from EDD obtained\n{$edd_message}", 'info');
        //echo "Customer Information from EDD obtained\n";
        //echo $edd_message;

        // Merge EDD and MailerLite emails
        $combined_emails = array_unique(array_merge($edd_emails, $mailer_lite_emails, $site_users));

        // Find emails not present in Bema CRM
        if (!empty($crm_emails)) {
            $emails_to_add = array_diff($combined_emails, $crm_emails);
        } else {
            $emails_to_add = $combined_emails;
        }

        // Add missing emails to Bema CRM
        foreach ($emails_to_add as $email) {

            // Collect user data
            $new_crm_data = [
                'tier' => 'unassigned',
                'purchase_indicator' => 0,
                'campaign' => '',
                'mailerlite_group_id' => '0',
                'candidate' => '',
                'subscriber' => $email,
                'source' => 'Bema Store',
            ];

            // Add the user to Bema CRM
            $this->add_crm_emails($new_crm_data, $crm_emails);
        }


        $this->syncMailerlite($crm_emails, $edd_data);

        $next_campaign = null;

        foreach ($data as $prefix => $album) {
            $this->logToFile("Synchronizing campaign {$prefix} \n\n", 'info');
            //echo "Synchronizing campaign {$prefix} \n\n";

            // Get EDD customers that bought Album related to Campaign and fetch emails
            if ($album){
                $album_edd_data = $this->syncEDDCustomers($album);
                $album_emails = isset($album_edd_data['edd_emails']) ? $album_edd_data['edd_emails'] : array();
                $abl_message = "Number of {$album} Customers from EDD: " . count($album_emails) . "\n\n";
                $this->logToFile($abl_message, 'info');
                //echo $abl_message;
            } else {
                $this->logToFile("Missing Album name", 'error');
                throw new InvalidArgumentException('Missing Album name');
            }
    
            // Get group IDs from Campaign from MailerLite 
            $ml_groupids = $this->groupIds($prefix);

            // Synchronize Bema CRM  and MailerLIte databases after adding Subscribers
            $this->sync_tier($ml_groupids, $crm_emails, $crm_ml_ids, $prefix);

            // Update Bema CRM data and extract relevant information
            $crmData = $this->get_crm_emails();
            
            $crm_emails = array_column($crmData, 'email', 'id');
            $crm_info = array_column($crmData, 'tier', 'email');
            $crm_purchases = array_column($crmData, 'indicator', 'email');
            $crm_ml_ids = array_column($crmData, 'mailerlite_group_id', 'email');
            $crm_campaign = array_column($crmData, 'campaign', 'email');

            // Update the Bema CRM so the Purchase indicator of users that purchased can be 1
            $this->updateCRMFields($crm_purchases, $crm_emails, $crm_campaign, $album_edd_data, $prefix);

            // Update Bema CRM data and extract relevant information
            $crmData = $this->get_crm_emails();
            
            $crm_emails = array_column($crmData, 'email', 'id');
            $crm_info = array_column($crmData, 'tier', 'email');
            $crm_purchases = array_column($crmData, 'indicator', 'email');
            $crm_ml_ids = array_column($crmData, 'mailerlite_group_id', 'email');
            $crm_campaign = array_column($crmData, 'campaign', 'email');
            
            // Add new subscribers to Mailerlite Opt-in group 
            $this->addUnassignedToOptIn($crm_info, $ml_groupids, $mailer_lite_emails, $ml_subscribers_data, $next_campaign);

            // Synchronize Bema CRM and MailerLIte databases after adding Subscribers
            $this->sync_tier($ml_groupids, $crm_emails, $crm_ml_ids, $prefix);

            // Add subscribers that purchased to MailerLite purchased groups
            $this->updateMailerLiteGroups($ml_subscribers_data, $ml_groupids, $album_edd_data, $crm_ml_ids, $crm_emails, $next_campaign);

            $this->logToFile("Synchronization for campaign {$prefix} successfully completed!\n\n", 'info');
            //echo "Synchronization for campaign {$prefix} successfully completed!\n\n";
            $next_campaign = $prefix;
        }

    }

    //Update MailerLite groups for subscribers based on their status and purchased groups.
    private function updateMailerLiteGroups($ml_subscribers_data, $ml_groupids, $album_edd_data, $crm_ml_ids, $crm_emails, $next_campaign) {
        // Counter to keep track of the number of subscribers updated
        $count = 0;
        $count2 = 0;
        // Mapping of groups to their corresponding purchased groups
        $upgrades = array(
            'opt-in' => 'gold_purchased',
            'gold' => 'gold_purchased',
            'silver' => 'silver_purchased',
            'bronze' => 'bronze_purchased',
        );
    
        // Mapping of purchased groups to their corresponding campaign upgrade groups
        $campaign_upgrade = array (
            'gold_purchased' => 'gold',
            'silver_purchased' => 'gold',
            'bronze_purchased' => 'silver',
            'wood' => 'bronze',
        );
        
        // Iterate through MailerLite subscribers data
        foreach ($ml_subscribers_data as $ml_data) {
            $subscriber = $ml_data['email'];
            // Upgrade those in current campaign that have purchased to purchased groups
            if (isset($album_edd_data['edd_emails']) && in_array($subscriber, $album_edd_data['edd_emails'])) {
    
                // Iterate through the upgrades to find a match
                foreach ($upgrades as $group => $purchasedGroup) {
                    // Check if CRM to MailerLite mapping contains the subscriber, and the MailerLite group matches
                    if (isset($crm_ml_ids[$subscriber]) && $crm_ml_ids[$subscriber] === $ml_groupids[$group]) {
                        // Add subscriber to the purchased group and delete from the current group
                        $this->mailerLiteInstance->addSubscriberToGroup($subscriber, $ml_groupids[$purchasedGroup]);
                        $this->mailerLiteInstance->deleteSubscriberFromGroup($ml_data['id'], $ml_groupids[$group]);
    
                        $crm_id = array_search($subscriber, $crm_emails, true);
                        
                        $data = [
                            'tier' => $purchasedGroup,
                            'mailerlite_group_id' => $ml_groupids[$purchasedGroup],
                            'date_added'        => date('Y-m-d H:i:s'),
                            'source' => 'EM_Sync',
                        ];

                        // Update the crm_data with the new campaign info
                        if ($crm_id !== false && $data) {
                            $this->update_crm_user($crm_id, $data);
                        }
                        
                        // Increment the counter and break, as we found a match
                        $count++;
                        break;
                    }
                }
            }

            // Upgrade those in current campaign purchased groups to next campaign
            if ($next_campaign) {

                // Get the group IDs for the next Campaign
                $next_campaign_groupids = $this->groupIds($next_campaign);

                // Iterate through the campaign upgrades to find a match
                foreach ($campaign_upgrade as $purchasedGroup => $group_upgrade) {
                    // Check if CRM to MailerLite mapping contains the subscriber, and the MailerLite group matches
                    if (isset($crm_ml_ids[$subscriber]) && $crm_ml_ids[$subscriber] === $ml_groupids[$purchasedGroup]) {
                        // Add subscriber to the next campaigns group and delete from the current campaign group
                        $this->mailerLiteInstance->addSubscriberToGroup($subscriber, $next_campaign_groupids[$group_upgrade]);
                        $this->mailerLiteInstance->deleteSubscriberFromGroup($ml_data['id'], $ml_groupids[$purchasedGroup]);

                        $crm_id = array_search($subscriber, $crm_emails, true);

                        // Extract the Artist name
                        $start = strpos($next_campaign, '_');
                        $end = strrpos($next_campaign, '_');

                        if ($start !== false && $end !== false) {
                            // Extract the content between the first and middle underscores in the prefix (i.e., Artist name)
                            $candidate = substr($next_campaign, $start + 1, $end - $start - 1);
                        }

                        $data = [
                            'tier'              => $group_upgrade,
                            'mailerlite_group_id' => $next_campaign_groupids[$group_upgrade],
                            'campaign'          => $next_campaign,
                            'candidate'         => $candidate,
                            'date_added'        => date('Y-m-d H:i:s'),
                            'purchase_indicator' => 0,
                            'source' => 'EM_Sync',
                        ];

                        // Update the crm_data with the new campaign info
                        if ($crm_id !== false && $data) {
                            $this->update_crm_user($crm_id, $data);
                        }

                        // Increment the counter and break, as we found a match
                        $count2++;
                        break;
                    }
                }
            }
        }
    
        // Output the number of subscribers moved to MailerLite and those upgraded tot the next campaign
        $this->logToFile("Moved {$count} subscribers on MailerLite\n\n", 'info');
        //echo "Moved {$count} subscribers on MailerLite\n\n";
        if ($next_campaign) {
            $this->logToFile("Moved {$count2} subscribers to {$next_campaign} Campaign on MailerLite\n\n", 'info');
            //echo "Moved {$count2} subscribers to {$next_campaign} Campaign on MailerLite\n\n";
        }
    }

    private function syncMailerlite($crm_emails, $edd_data) {

        $edd_emails = $edd_data['edd_emails'];

        $count = 0;

        $mailer_lite_emails = $this->mailerLiteInstance->getAllML();
    
        // Filter out empty values in $crm_emails
        $crm_emails = array_filter($crm_emails);

    
        // Combine EDD and CRM emails without duplicates
        if (!empty($edd_emails)) {
            $combined_emails = array_unique(array_merge($edd_emails, $crm_emails));
        } else {
            $combined_emails = array_unique($crm_emails);
        }

        // Identify emails to add to MailerLite
        $emails_to_add = array_diff($combined_emails, $mailer_lite_emails);

        // Loop through $emails_to_add and add new subscribers to MailerLite
        foreach ($emails_to_add as $email) {
            // Extract the subscribers name from the email
            $name = explode('@', $email)[0];

            $fields = array(
                'name' => $name,
            );

            // Add the subscriber to MailerLite
            $this->mailerLiteInstance->addSubscriber($email, $fields);
            $count++;
        }
    
        $this->logToFile("Added {$count} Mailerlite Subscribers\n\n", 'info');
        //echo "Added {$count} Mailerlite Subscribers\n\n";
    }

    //Update  Bema CRM fields based on EDD purchases and emails.
    private function updateCRMFields($crm_purchases, $all_crm_emails, $crm_campaign, $edd_emails, $prefix) {
        $updates = []; // Associative array to store CRM updates
        $count = 0;
        
        $crm_emails = [];
        foreach ($crm_campaign as $email => $campaign) {
            if ($campaign === $prefix) {
                $crm_emails[] = $email;
            }
        }

        $crm_id = [];

        foreach ($all_crm_emails as $id => $eml) {
            if (in_array($eml, $crm_emails)) {
                $crm_id[$id] = $eml;
            }
        }

        $crm_emails = $crm_id;


        if (!empty($crm_emails)) {
                // Iterate through Bema CRM purchases
            foreach ($crm_purchases as $email => $value) {
                // Check if the email is in EDD emails and has not been marked as purchased in CRM
                if (isset($edd_emails['edd_emails']) && in_array($email, $edd_emails['edd_emails']) && !$value) {
                    // Find the Bema CRM ID for the email
                    $crm_id = array_search($email, $crm_emails, true);

                    // If Bema CRM ID is found, prepare data for update
                    if ($crm_id !== false && $value !== 1) {
                        $updates[$crm_id] = [
                            'purchase_indicator' => 1,
                            'source' => 'EDD'
                        ];
                    }
                }
            }

            // Batch update Bema CRM with the prepared data
            foreach ($updates as $crm_id => $data) {
                $this->update_crm_user($crm_id, $data);
                $count = $count + 1;
            }

            // Display the number of CRM users updated
            $this->logToFile("Updated {$count} CRM users Purchase information\n\n", 'info');
            //echo "Updated {$count} CRM users Purchase information\n\n";
        }
        
    }

    // Add unassigned (New Users) to opt-in group of the current campaign
    public function addUnassignedToOptIn($crm_info, $group_id, $ml_subs, $ml_data, $next_campaign) {
        if ($next_campaign) {
            return null;
        }
        $count = 0;

        // Check if the subscriber is unassigned in Bema CRM and exists in MailerLite subscribers
        foreach ($crm_info as $subscriber => $tier) {

            if ($tier === 'unassigned' && in_array($subscriber, $ml_subs)) {

                // Check if subscriber is in any group
                foreach ($group_id as $group_name => $id) {

                    // Get subscribers for the current MailerLite group
                    $subs = $this->mailerLiteInstance->getGroupsSubscribers($id);
                    // If subscriber is found in a group that is not opt-in, Delete the subscriber from the group
                    if ($group_name !== 'opt-in' && in_array($subscriber,  $subs)) {
                        // Extract the 'email' column from the array
                        $emails = array_column($ml_data, 'email');

                        $index = array_search($subscriber, $emails);
                        
                        $this->mailerLiteInstance->deleteSubscriberFromGroup($ml_data[$index]['id'], $id);
                    }
                }
                // Add subscriber to the Campaign opt-in group on MailerLite
                $this->mailerLiteInstance->addSubscriberToGroup($subscriber, $group_id['opt-in'], null);
                $count = $count + 1;
            }
        }

        $this->logToFile("Added {$count} Subscribers to opt-in\n\n", 'info');
        //echo "Added {$count} Subscribers to opt-in\n\n";
    }
}
?>