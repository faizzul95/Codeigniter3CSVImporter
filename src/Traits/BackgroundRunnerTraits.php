<?php

namespace OnlyPHP\Codeigniter3CSVImporter\Traits;

trait BackgroundRunnerTraits
{
    private $pathToCLIProcess = FCPATH . 'vendor/onlyphp/codeigniter3-csvimporter/src/CSVProcessorCLI.php';

    /**
     * Start background processing using proc_open (supports both Windows and Linux)
     * @param string $jobId
     * @return bool
     */
    private function startBackgroundProcess(string $jobId)
    {
        $phpBinary = PHP_BINARY ?: 'php';
        $scriptPath = str_replace('/', '\\', $this->pathToCLIProcess);
        $isWindows = PHP_OS_FAMILY === 'Windows';

        // Debug logging
        log_message('debug', 'Starting background process');
        log_message('debug', 'PHP Binary: ' . $phpBinary);
        log_message('debug', 'Script Path: ' . $scriptPath);
        log_message('debug', 'Job ID: ' . $jobId);
        log_message('debug', 'Is Windows: ' . ($isWindows ? 'Yes' : 'No'));

        if (!file_exists($scriptPath)) {
            log_message('error', 'CLI Script not found at: ' . $scriptPath);
            return false;
        }

        if ($isWindows) {
            // Create a temporary batch file to run the PHP script
            $batchFile = sys_get_temp_dir() . '\\csv_import_' . uniqid() . '.bat';
            $batchContent = '@echo off' . PHP_EOL;
            $batchContent .= sprintf(
                'start /B "CSV_Import_%s" "%s" "%s" "%s"',
                $jobId,
                $phpBinary,
                $scriptPath,
                $jobId
            );

            file_put_contents($batchFile, $batchContent);

            // Set up process descriptor
            $descriptorspec = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['file', 'NUL', 'w'],  // stdout -> NUL
                2 => ['file', 'NUL', 'w']   // stderr -> NUL
            ];

            // Set up process options
            $options = [
                'bypass_shell' => true,
                'create_process_group' => true
            ];

            // Log the command that will be executed
            log_message('debug', 'Batch file content: ' . $batchContent);

            // Start the process
            $process = proc_open($batchFile, $descriptorspec, $pipes, null, null, $options);

            if (is_resource($process)) {
                // Close stdin pipe
                if (isset($pipes[0])) {
                    fclose($pipes[0]);
                }

                // Get process status
                $status = proc_get_status($process);
                proc_close($process);

                // Delete the temporary batch file
                unlink($batchFile);

                // Wait briefly and verify the process is running
                sleep(1);
                return $this->isProcessRunning($jobId);
            }

            // Clean up batch file if process failed to start
            if (file_exists($batchFile)) {
                unlink($batchFile);
            }

            log_message('error', 'Failed to start process using proc_open');
            return false;
        } else {
            // Unix/Linux handling
            $cmd = sprintf('%s %s %s', $phpBinary, $scriptPath, $jobId);

            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['file', '/dev/null', 'w'],
                2 => ['file', '/dev/null', 'w']
            ];

            $process = proc_open($cmd, $descriptorspec, $pipes, null, null, ['bypass_shell' => true]);

            if (is_resource($process)) {
                fclose($pipes[0]);
                proc_close($process);
                sleep(1);
                return $this->isProcessRunning($jobId);
            }

            return false;
        }
    }

    /**
     * Check if the process is already running
     * @param string $jobId
     * @return bool
     */
    private function isProcessRunning(string $jobId): bool
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';

        if ($isWindows) {
            // Use more reliable Windows command to find the process
            $cmd = sprintf(
                'tasklist /FI "WINDOWTITLE eq CSV_Import_%s" /FI "IMAGENAME eq php.exe" /NH',
                $jobId
            );
        } else {
            $cmd = sprintf(
                "ps aux | grep '%s' | grep -v grep",
                escapeshellarg($jobId)
            );
        }

        log_message('debug', 'Process check command: ' . $cmd);

        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);

        log_message('debug', 'Process check output: ' . print_r($output, true));

        if ($isWindows) {
            // Check if the output contains the php.exe process with our window title
            foreach ($output as $line) {
                if (stripos($line, 'php.exe') !== false) {
                    return true;
                }
            }
            return false;
        }

        return !empty(array_filter($output));
    }
}
