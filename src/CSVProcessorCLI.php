<?php

/**
 * CLI Processor for CSV Import
 * This file should be placed in: vendor/onlyphp/codeigniter3-csvimporter/src/CSVProcessorCLI.php
 */

// Ensure the script runs only from the CLI
if (php_sapi_name() !== 'cli') {
    exit("This script can only be run from the command line.\n");
}

// Define constants and include required files
define('BASEPATH', true);
define('APPPATH', dirname(dirname(dirname(dirname(__DIR__)))) . '/application/');
define('FCPATH', dirname(APPPATH) . '/');

// Include CodeIgniter's index.php to bootstrap the framework
require_once FCPATH . 'index.php';

// Get job ID from command line argument
$jobId = $argv[1] ?? null;

if (!$jobId) {
    exit("Job ID required\n");
}

// Log the start of processing
log_message('debug', 'Starting CSV processing for job: ' . $jobId);

try {
    // Initialize CSVProcessor and process file
    $ci = &get_instance();

    // Load the composer autoloader if not already loaded
    require_once FCPATH . 'vendor/autoload.php';

    $processor = new \OnlyPHP\Codeigniter3CSVImporter\CSVImportProcessor();
    $processor->processFile($jobId);

    log_message('debug', 'CSV processing completed for job: ' . $jobId);
} catch (\Exception $e) {
    log_message('error', 'CSV processing failed for job ' . $jobId . ': ' . $e->getMessage());
    exit(1);
}
