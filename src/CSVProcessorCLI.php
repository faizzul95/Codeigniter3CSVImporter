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

// Define necessary constants if not already defined
defined('BASEPATH') or define('BASEPATH', dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR);
defined('FCPATH') or define('FCPATH', dirname(BASEPATH) . DIRECTORY_SEPARATOR);
defined('APPPATH') or define('APPPATH', BASEPATH . 'application' . DIRECTORY_SEPARATOR);

// Include Composer autoloader
$autoloaders = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

foreach ($autoloaders as $autoloader) {
    if (file_exists($autoloader)) {
        // Load Composer's autoload file (ensure required libraries are loaded)
        require_once $autoloader;
        break;
    }
}

// Import the necessary class
use OnlyPHP\Codeigniter3CSVImporter\CSVImportProcessor;

// Get the job ID from command line arguments
$jobId = $argv[1] ?? null;

// Ensure a job ID is provided
if (!$jobId) {
    echo "Job ID is required\n";
    exit(1);
}

// Log the start of the processing
error_log('Starting CSV processing for job: ' . $jobId);

try {
    // Initialize the processor and begin processing the file
    $processor = new CSVImportProcessor();
    $processor->processFile($jobId);

    // Log success after processing
    error_log('CSV processing completed for job: ' . $jobId);
    exit(0); // Exit successfully
} catch (Exception $e) {
    // Log error if the processing fails
    error_log("Error processing CSV job $jobId: " . $e->getMessage());
    exit(1); // Exit with error status
}
