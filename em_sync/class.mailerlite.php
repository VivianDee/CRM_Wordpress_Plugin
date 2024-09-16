<?php 
/**
 *  MailerLite Class:
 * Handles interactions with the MailerLite API. Methods include:
 * __construct($apiKey): Constructor to initialize the MailerLite object with the API key.
 * setHeaders(): Private method to set headers for API requests.
 * getSubscribers(): Retrieves subscribers from MailerLite.
 * getGroups(): Retrieves groups from MailerLite.
 * getGroupsSubscribers($groupId): Retrieves subscribers from a specific group in MailerLite.
 * addSubscriber($emails, $group_id): Adds subscribers to MailerLite.
 * addSubscriberToGroup($subscriber_id, $group_id, $optin_id): Adds subscribers to a specific group in MailerLite.
 * deleteSubscriberFromGroup($email): Deletes a subscriber from MailerLite based on the email.
 * updateSubscriber($subscriberId, $fields): Updates a MailerLite subscriber based on the subscriber ID and fields.
 **/
 
 class MailerLite {

    private $apiKey;
    private $headers;

    public function __construct($apiKey = null) {
        if (!$apiKey) {
            $this->logToFile('Usage: new MailerLite(your_api_key);', 'info');
            throw new InvalidArgumentException('Usage: new MailerLite(your_api_key);');
        }
        $this->apiKey = $apiKey;
        $this->setHeaders();
    }


    private function setHeaders() {
        $this->headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3',
            'Accept: application/json',
            'Content-Type: application/json',
            "Authorization: Bearer {$this->apiKey}",
        ];
    }

    private function logToFile($message, $logType = 'info') {
        $logPath = BEMA_PATH . 'em_sync/log/';  // Specify the path where you want to store the log file

        // Create a log file if not exists
        $logFile = $logPath . 'log_' . date('Y-m-d') . '.log';

        // Log timestamp
        $timestamp = date('Y-m-d H:i:s');

        // Log message format
        $logMessage = "[$timestamp] [$logType]:  $message\n";

        // Append the log message to the log file
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    private function makeRequest($endpoint, $method, $data = null) {
        $url = "https://connect.mailerlite.com/api/{$endpoint}";

        $options = [
            'http' => [
                'header' => implode("\r\n", $this->headers),
                'method' => $method,
            ],
        ];

        // Add data to the request if provided
        if ($data !== null) {
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response !== false || $method === 'DELETE') {
            return json_decode($response, true);
        } else {
            // Handle the case where the request fails
            $this->logToFile("Error making HTTP request to $url\n", 'error');
            throw new RuntimeException("Error making HTTP request to $url\n");
        }
    }

    public function getSubscribers($status = 'active') {
        $data = $this->makeRequest('subscribers', 'GET');
    
        if (isset($data['data'])) {
            // Filter subscribers based on status
            $subscribers = array_filter($data['data'], function ($subscriber) use ($status) {
                return isset($subscriber['status']) && $subscriber['status'] === $status;
            });

            $result = [];

            foreach ($subscribers as $subscriber) {
                $name = $subscriber['fields']['name'];

                $result[] = array(
                    'email' => $subscriber['email'],
                    'id' => $subscriber['id'],
                    'name' => $name,
                );

            }
    
            // Return only emails from the filtered subscribers
            return $result;
        }
    
        return [];
    }

    public function getAllML() {
        $data = $this->makeRequest('subscribers', 'GET');

        $emails = [];

        if (isset($data['data'])) {
            $subscribers = $data['data'];

            $emails = array_column($subscribers, 'email');

            return $emails;
        }

        return null;
    }
    

    public function getGroups() {
        $data = $this->makeRequest('groups', 'GET');

        return isset($data['data']) ? array_column($data['data'], 'id', 'name') : [];
    }

    public function getGroupsSubscribers($groupId) {
        $data = $this->makeRequest("groups/{$groupId}/subscribers", 'GET');

        return isset($data['data']) ? array_column($data['data'], 'email') : [];
    }

    public function addSubscriber($emails, $edd_data = null) {

        if (!$emails || empty($emails)) {
            return [];
        }

        $data = [
            'email' => $emails,
            'status' => 'active',
            ];

        if ($edd_data) {
            $data['fields'] = array(
                'name' => $edd_data['name'],
            );
        }

        $response = $this->makeRequest('subscribers', 'POST', $data);

        return isset($response['data']['id']) ? $response['data']['id'] : null;
    }

    public function updateSubscriber($subscriberId, $fields) {
        $url = "subscribers/{$subscriberId}";

        // Create the update data
        $updateData = [
            'fields' => $fields,
        ];

        // Make the request to update the subscriber
        $this->makeRequest($url, 'PUT', $updateData);
    }

    public function addSubscriberToGroup($subscriberId, $groupId, $optin_id = null) {
        // Create the update data
        $data = [
            "status" => "active",
        ];

        if ($groupId > 0) {
            $this->makeRequest("subscribers/{$subscriberId}/groups/{$groupId}", 'POST', $data);
            return "success";
        } else {
            $this->makeRequest("subscribers/{$subscriberId}/groups/{$optin_id}", 'POST', $data);
            return "success";
        }
        return null;
    }

    public function deleteSubscriberFromGroup($subscriber_id, $group) {
        if ($subscriber_id) {
            $response = $this->makeRequest("subscribers/{$subscriber_id}/groups/{$group}", 'DELETE');
            return true;
        } else {
            $this->logToFile("Subscriber with email not found.", 'error');
            throw new InvalidArgumentException("Subscriber with email not found.");
        }
    }
}
?>