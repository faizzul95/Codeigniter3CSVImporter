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

// Include Composer's autoloader (ensure 'vendor' directory exists)
require_once __DIR__ . '/../../../../vendor/autoload.php';

// Include CodeIgniter's index.php (bootstrap) file to initialize the framework
require_once __DIR__ . '/../../../../index.php';

// Import the necessary class
use OnlyPHP\Codeigniter3CSVImporter\CSVImportProcessor;

// Get the job ID from command line arguments
$jobId = $argv[1] ?? null;

// Ensure a job ID is provided
if (!$jobId) {
    echo "Job ID is required\n";
    exit(1);
}

try {
    // Initialize the processor and begin processing the file
    $processor = new CSVImportProcessor();
    $processor->processFile($jobId);

    exit(0); // Exit successfully
} catch (Exception $e) {
    // Log error if the processing fails
    error_log("Error processing CSV job $jobId: " . $e->getMessage());
    exit(1); // Exit with error status
}
