<?php

/**
 * CLI Processor for CSV Import
 * This file should be placed in: vendor/onlyphp/codeigniter3-csvimporter/src/CSVProcessorCLI.php
 */

// Ensure the script is being run in the CLI environment
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line');
}

// Set unlimited execution time for long-running processes
set_time_limit(0);

// Include Composer's autoload
require_once __DIR__ . '/../../../../vendor/autoload.php';

// Define BASEPATH, APPPATH, and other constants
if (!defined('BASEPATH')) {
    $system_path = __DIR__ . '/../../../../system';
    if (!is_dir($system_path)) {
        die('Your system folder path does not appear to be set correctly.');
    }
    define('BASEPATH', str_replace("\\", "/", $system_path . '/'));
}
if (!defined('APPPATH')) {
    define('APPPATH', str_replace("\\", "/", __DIR__ . '/../../../../application/'));
}
if (!defined('VIEWPATH')) {
    define('VIEWPATH', APPPATH . 'views/');
}
if (!defined('FCPATH')) {
    define('FCPATH', str_replace("\\", "/", __DIR__ . '/../../../../'));
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'development');
}

// Load core CodeIgniter files
require_once BASEPATH . 'core/Common.php';
require_once APPPATH . 'config/constants.php';
require_once APPPATH . 'config/config.php';
require_once BASEPATH . 'core/Controller.php';

// Load the CodeIgniter Config and Loader classes
require_once BASEPATH . 'core/Config.php';
require_once BASEPATH . 'core/Loader.php';

// Create a mock CLI controller
class CLI_Controller extends CI_Controller
{
    public function __construct()
    {
        $this->initCI();
        parent::__construct();
        $this->load->database(); // Load database library
        $this->uri = $this->mockURI(); // Add a mock URI object
    }

    private function initCI()
    {
        $this->config = new CI_Config();
        $this->load = new CI_Loader();
        $GLOBALS['CI'] = &$this;
    }

    private function mockURI()
    {
        // Mock URI object to simulate functionality
        return (object)[
            'ruri_string' => function () {
                return 'cli_request'; // Return a mock URI string
            }
        ];
    }
}

// Helper function to return the CodeIgniter instance
function &get_instance()
{
    return $GLOBALS['CI'];
}

// Initialize the CLI controller
$CLI = new CLI_Controller();

// Get the job ID from command line arguments
$jobId = $argv[1] ?? null;

if (!$jobId) {
    log_message('error', 'Job ID is required');
    exit(1);
}

// Import the necessary class
use OnlyPHP\Codeigniter3CSVImporter\CSVImportProcessor;

/**
 * Checks if the server's CPU load is above a specified threshold.
 *
 * This function determines the current CPU usage percentage and compares it 
 * against the provided threshold. It supports both Linux and Windows environments.
 *
 * @param int $threshold The CPU usage percentage threshold. Default is 95.
 * @return bool True if the server load exceeds the threshold, false otherwise.
 */
function isServerOverloaded($threshold = 95)
{
    $cpuUsage = 0;

    if (PHP_OS_FAMILY === 'Linux') {
        // Ensure mpstat is available
        $mpstatAvailable = shell_exec('which mpstat');
        if (!$mpstatAvailable) {
            log_message('error', 'mpstat command is not available.');
            return false;
        }

        // Use `mpstat` to calculate idle CPU and derive active CPU usage
        $cpuUsageOutput = shell_exec("mpstat 1 1 | awk '/^Average:/ {print 100 - \$NF}'");

        // If shell execution fails, handle it
        if ($cpuUsageOutput === null) {
            log_message('error', 'Failed to retrieve CPU usage from mpstat.');
            return false;
        }

        $cpuUsage = (float)trim($cpuUsageOutput);
    } elseif (PHP_OS_FAMILY === 'Windows') {
        // Check if WMIC is available
        $wmicAvailable = shell_exec('which wmic');
        if (!$wmicAvailable) {
            log_message('error', 'WMIC command is not available.');
            return false;
        }

        // Use WMIC to fetch CPU load percentage
        $cpuUsageOutput = shell_exec("wmic cpu get loadpercentage /value");

        // If shell execution fails, handle it
        if ($cpuUsageOutput === null) {
            log_message('error', 'Failed to retrieve CPU usage from WMIC.');
            return false;
        }

        preg_match('/\d+/', $cpuUsageOutput, $matches);
        $cpuUsage = isset($matches[0]) ? (float)$matches[0] : 0;
    } else {
        // Unsupported OS
        log_message('error', 'Unsupported OS for load checking.');
        return false;
    }

    return $cpuUsage >= $threshold;
}

try {
    // Initial delay to allow for corrections before processing starts
    sleep(60);

    // Continuously check server load until it is below 90%
    while (isServerOverloaded(90)) {
        log_message('info', 'Server load too high. Waiting before starting job: ' . $jobId);
        sleep(10); // Check every 10 seconds
    }

    log_message('info', 'Starting CSV processing for job: ' . $jobId);

    // Process the CSV file
    $processor = new CSVImportProcessor();
    $processor->processFile($jobId);

    log_message('info', 'Successfully completed CSV processing for job: ' . $jobId);
    exit(0);
} catch (Exception $e) {
    // Handle exceptions gracefully
    log_message('error', 'Error processing CSV job ' . $jobId . ': ' . $e->getMessage());
    exit(1);
}
