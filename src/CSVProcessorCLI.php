<?php

/**
 * CLI Processor for CSV Import
 * This file should be placed in: vendor/onlyphp/codeigniter3-csvimporter/src/CSVProcessorCLI.php
 */

// Ensure running in CLI
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line');
}

// Set unlimited time limit for long processes
set_time_limit(0);

// Define constants if not already defined
defined('BASEPATH') or define('BASEPATH', dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR);
defined('FCPATH') or define('FCPATH', dirname(BASEPATH) . DIRECTORY_SEPARATOR);
defined('APPPATH') or define('APPPATH', BASEPATH . 'application' . DIRECTORY_SEPARATOR);

// Include CodeIgniter bootstrap file
require_once FCPATH . 'index.php';

// Get job ID from command line argument
$jobId = $argv[1] ?? null;

if (!$jobId) {
    die("Job ID is required\n");
}

// Log the start of processing
log_message('debug', 'Starting CSV processing for job: ' . $jobId);

try {
    // Load necessary dependencies
    require_once FCPATH . 'vendor/autoload.php';

    // Initialize processor and process file
    $processor = new \OnlyPHP\Codeigniter3CSVImporter\CSVImportProcessor();
    $processor->processFile($jobId);

    log_message('debug', 'CSV processing completed for job: ' . $jobId);
    exit(0);
} catch (Exception $e) {
    log_message('error', 'CSV processing failed for job ' . $jobId . ': ' . $e->getMessage());
    error_log("Error processing CSV job $jobId: " . $e->getMessage());
    exit(1);
}
