<?php
/**
 * EDD Class:
 * Handles interactions with the Easy Digital Downloads (EDD) API. Methods include:
 * __construct($apiKey, $token): Constructor to initialize the EDD object with the API key and token.
 * getEDDSales($page = 1, $number = 100): Retrieves customer information from EDD.
 **/

 // EDD class
class EDD {
    // Private properties for API key, site URL, token, and headers
    private $apiKey;
    private $siteUrl = '';
    private $token;
    private $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3',
        'Accept-Encoding: gzip, deflate, br',
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    // Constructor to initialize EDD object with API key and token.
    public function __construct($apiKey = null, $token = null) {
        if (!($apiKey) || !($token)) {
            throw new InvalidArgumentException('Usage: new EDD(your_edd_api_key, your_edd_token);');
        }

        $this->apiKey = $apiKey;
        $this->token = $token;
        $this->siteUrl = 'site_url()';
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

    // Get customer information from EDD.
    public function getEDDSales($product = null, $page = 1, $number = 100) {
        $endpoint = "{$this->siteUrl}/edd-api/v2/sales/?key={$this->apiKey}&token={$this->token}&page={$page}&number{$number}";

        // HTTP request options
        $options = [
            'http' => [
                'header' => implode("\r\n", $this->headers),
                'method' => 'GET',
            ],
        ];

        // Create HTTP context and make the request
        $context = stream_context_create($options);
        $response = file_get_contents($endpoint, false, $context);

        // Process the response
        if ($response !== false) {
            $decoded_content = gzdecode($response);
            $data = json_decode($decoded_content, true);

            // Extract sales information
            if (isset($data['sales'])) {
                $sales = $data['sales'];

                $edd_data = array(
                    'edd_emails' => array(),
                    'sales_data' => array(),
                );

                foreach ($sales as $sale) {
                    if ($sale['status'] === "complete") {

                        if(in_array($product, array_column($sale['products'], 'name')) || !$product) {
                            $sale_data = array(
                                'email' => $sale['email'],
                                'purchase_amount' => $sale['total'],
                            );

                            $edd_data['edd_emails'][] = $sale['email'];
                            $edd_data['sales_data'][] = $sale_data;
                        }
                    }
                }

                if (empty($edd_data['edd_emails'])) {
                    return [];
                }

                // Recursive call for the next page if available
                $nextPage = $page + 1;
                $nextData = $this->getEDDSales($product, $nextPage);

                // Merge the results from the next page
                $edd_data['edd_emails'] = array_merge($edd_data['edd_emails'], $nextData['edd_emails'] ?? []);
                $edd_data['sales_data'] = array_merge($edd_data['sales_data'], $nextData['sales_data'] ?? []);

                // Return the combined array of sales emails and data
                return $edd_data;
            } else {
                // Handle the case where the request fails
                return [];
            }
        } else {
            // Handle the case where the request fails
            $this->logToFile("No EDD purchases found", 'Error');
        }
    }
}
?>