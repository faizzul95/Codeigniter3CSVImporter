<?php

namespace OnlyPHP\Codeigniter3CSVImporter\Traits;

trait BackgroundRunnerTraits
{
    // Path to the CSV Processor CLI script
    private $pathToCLIProcess = FCPATH . 'vendor/onlyphp/codeigniter3-csvimporter/src/CSVProcessorCLI.php';

    /**
     * Start a background process for a given job ID.
     *
     * @param string $jobId The job ID to associate with the background process.
     * @return bool Returns true if the background process started successfully, otherwise false.
     */
    private function startBackgroundProcess(string $jobId)
    {
        $phpBinary = 'php';
        $scriptPath = str_replace('/', DIRECTORY_SEPARATOR, $this->pathToCLIProcess);
        $isWindows = PHP_OS_FAMILY === 'Windows';

        log_message('debug', 'Starting background process');
        log_message('debug', 'PHP Binary: ' . $phpBinary);
        log_message('debug', 'Script Path: ' . $scriptPath);
        log_message('debug', 'Job ID: ' . $jobId);
        log_message('debug', 'Is Windows: ' . ($isWindows ? 'Yes' : 'No'));

        // Check if the script exists
        if (!file_exists($scriptPath)) {
            log_message('error', 'CLI Script not found at: ' . $scriptPath);
            return false;
        }

        // Start process based on the operating system
        if ($isWindows) {
            return $this->startWindowsProcess($jobId, $phpBinary, $scriptPath);
        } else {
            return $this->startLinuxProcess($jobId, $phpBinary, $scriptPath);
        }
    }

    /**
     * Start a background process on Windows using a batch file.
     *
     * @param string $jobId The job ID to associate with the background process.
     * @param string $phpBinary The PHP binary to use for running the script.
     * @param string $scriptPath The path to the CSV Processor script.
     * @return bool Returns true if the background process started successfully, otherwise false.
     */
    private function startWindowsProcess(string $jobId, string $phpBinary, string $scriptPath)
    {
        // Create a unique window title
        $windowTitle = 'CSV_Import_' . $jobId;

        // Create a temporary batch file with the necessary command to run the script in the background
        $batchFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'csv_import_' . uniqid() . '.bat';
        $batchContent = '@echo off' . PHP_EOL;
        $batchContent .= sprintf(
            'start /B /MIN "%s" "%s" "%s" "%s"',
            $windowTitle,
            $phpBinary,
            $scriptPath,
            $jobId
        );

        file_put_contents($batchFile, $batchContent);

        // Log the batch file content for debugging
        log_message('debug', 'Batch file content: ' . $batchContent);

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['file', 'NUL', 'w'],
            2 => ['file', 'NUL', 'w']
        ];

        // Open the process using proc_open to execute the batch file
        $process = proc_open($batchFile, $descriptorspec, $pipes, FCPATH, null, [
            'bypass_shell' => true,
            'create_process_group' => true
        ]);

        // Check if the process was started successfully
        if (is_resource($process)) {
            fclose($pipes[0]);
            proc_close($process);
            unlink($batchFile);

            // Give the process time to start
            sleep(1);
            return $this->isProcessRunning($jobId);
        }

        // Clean up batch file if the process failed to start
        if (file_exists($batchFile)) {
            unlink($batchFile);
        }

        log_message('error', 'Failed to start process');
        return false;
    }

    /**
     * Start a background process on Linux using a shell command.
     *
     * @param string $jobId The job ID to associate with the background process.
     * @param string $phpBinary The PHP binary to use for running the script.
     * @param string $scriptPath The path to the CSV Processor script.
     * @return bool Returns true if the background process started successfully, otherwise false.
     */
    private function startLinuxProcess(string $jobId, string $phpBinary, string $scriptPath)
    {
        // Check for active processes before starting a new one (optional for better resource management)
        $runningProcesses = $this->getRunningProcessesCount($jobId);
        if ($runningProcesses >= 5) {
            log_message('error', 'Max concurrent processes reached for job ID: ' . $jobId);
            return false;  // Max limit reached, don't start another process
        }

        // Command to run the process in the background and suppress output
        $cmd = sprintf(
            '%s %s %s > /dev/null 2>&1 & echo $!',
            escapeshellarg($phpBinary),
            escapeshellarg($scriptPath),
            escapeshellarg($jobId)
        );

        exec($cmd, $output, $returnVar);
        if ($returnVar !== 0) {
            log_message('error', 'Failed to start background process on Linux. Error code: ' . $returnVar);
            return false;
        }

        sleep(1);  // Give the process time to start
        return $this->isProcessRunning($jobId);
    }

    /**
     * Check if the background process for a given job ID is still running.
     *
     * @param string $jobId The job ID to check.
     * @return bool Returns true if the process is running, otherwise false.
     */
    private function isProcessRunning(string $jobId)
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';

        if ($isWindows) {
            // Windows tasklist command
            $windowTitle = 'CSV_Import_' . $jobId;
            $cmd = sprintf(
                'tasklist /FI "WINDOWTITLE eq %s" /FI "IMAGENAME eq php.exe" /NH',
                $windowTitle
            );
        } else {
            // Linux/Unix command to check the process
            $cmd = sprintf(
                "ps aux | grep -F '%s' | grep -v grep",
                $jobId
            );
        }

        log_message('debug', 'Process check command: ' . $cmd);

        $output = [];
        exec($cmd, $output, $returnVar);

        log_message('debug', 'Process check output: ' . print_r($output, true));

        if ($isWindows) {
            return !empty(array_filter($output, function ($line) {
                return stripos($line, 'php.exe') !== false;
            }));
        }

        return !empty(array_filter($output));
    }

    /**
     * Helper function to track the count of running processes for a specific job ID.
     *
     * @param string $jobId The job ID to check.
     * @return int The count of running processes associated with the given job ID.
     */
    private function getRunningProcessesCount(string $jobId)
    {
        $cmd = sprintf(
            "ps aux | grep -F '%s' | grep -v grep",
            $jobId
        );
        exec($cmd, $output);
        return count($output);
    }

    /**
     * Kill the background process for a specific job
     * @param string $jobId
     * @return bool
     */
    public function killProcess(string $jobId)
    {
        try {
            // Get the job details
            $job = $this->ci->db->get_where($this->table, ['job_id' => $jobId])->row();

            if (!$job || $job->status != 2) {
                return false; // Job not found or not in processing state
            }

            // Get process ID from the lock file
            $lockFile = sys_get_temp_dir() . "/csv_import_{$jobId}.lock";
            if (!file_exists($lockFile)) {
                return false;
            }

            $pid = trim(file_get_contents($lockFile));
            if (empty($pid)) {
                return false;
            }

            // Kill the process based on operating system
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows
                exec("taskkill /F /PID {$pid} 2>&1", $output, $returnCode);
                $killed = $returnCode === 0;
            } else {
                // Linux/Unix
                $killed = posix_kill((int)$pid, SIGTERM);
                if (!$killed) {
                    // Try force kill if SIGTERM fails
                    $killed = posix_kill((int)$pid, SIGKILL);
                }
            }

            if ($killed) {
                // Update job status to failed
                $this->updateJob($jobId, [
                    'status' => 4,
                    'error_message' => 'Process terminated by user',
                    'end_time' => date('Y-m-d H:i:s')
                ]);

                // Remove the lock file
                @unlink($lockFile);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            log_message('error', 'Error killing process: ' . $e->getMessage());
            return false;
        }
    }
}
