<?php

namespace OnlyPHP\Codeigniter3CSVImporter\Traits;

use Exception;
use InvalidArgumentException;

trait BackgroundRunnerTraits
{
    /**
     * Debug and Logging Configuration
     */
    public $debug = false;
    public $logLevel = 'info';

    /**
     * PHP CLI Configuration
     */
    public $phpCommand = 'php';
    public $initFiles = 'index.php';

    /**
     * Process Path Configuration
     */
    private $pathToCLIProcess = 'vendor/onlyphp/codeigniter3-csvimporter/src/CSVProcessorCLI.php';
    public $customCLIPath = false;

    /**
     * Logging Configuration
     */
    public $logPath;
    public $logRotateSize = 5 * 1024 * 1024; // 5MB max log file size

    /**
     * Resource Limits
     */
    public $memory_limit_process = '512M';
    public $max_execution_time_process = 300; // 5 minutes

    /**
     * Error Handling Configuration
     */
    public $retryAttempts = 3;
    public $retryDelay = 5; // seconds between retry attempts

    /**
     * Security Configuration
     */
    private $allowedProcessPaths = [
        'vendor/onlyphp/codeigniter3-csvimporter/src/',
        'application/cli/'
    ];

    /**
     * OS Detection
     */
    private $isWindows = false;

    /**
     * Constructor to set default configurations
     */
    public function __construct()
    {
        $this->isWindows = $this->_detectWindowsOS();

        // Set default log path with OS-specific path separator
        $this->logPath = $this->_getDefaultLogPath();

        $this->_ensureLogDirectorySecurity();
    }

    /**
     * Detect Windows Operating System
     */
    private function _detectWindowsOS(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Get OS-appropriate default log path
     */
    private function _getDefaultLogPath(): string
    {
        $separator = $this->isWindows ? '\\' : DIRECTORY_SEPARATOR;
        return FCPATH . 'application' . $separator . 'logs' . $separator . 'BACKGROUND_PROCESS' . $separator . 'csv_process.log';
    }

    /**
     * Secure log directory creation with restricted permissions
     */
    private function _ensureLogDirectorySecurity()
    {

        $logDir = dirname($this->logPath);

        // Check if directory exists and is writable, else create it
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, $this->isWindows ? 0777 : 0755, true)) {
                throw new Exception("Failed to create log directory: {$logDir}");
            }
        }

        // Ensure the directory is writable
        if (!is_writable($logDir)) {
            throw new Exception("Log directory is not writable: {$logDir}");
        }

        // Create security file for Windows
        if ($this->isWindows) {
            $securityFilePath = $logDir . DIRECTORY_SEPARATOR . 'web.config';
            if (!file_exists($securityFilePath)) {
                $securityContent = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <security>\n      <requestFiltering>\n        <hiddenSegments>\n          <add segment=\"logs\" />\n        </hiddenSegments>\n      </requestFiltering>\n    </security>\n  </system.webServer>\n</configuration>";
                if (file_put_contents($securityFilePath, $securityContent) === false) {
                    throw new Exception("Failed to create security config in: {$logDir}");
                }
            }
        } else {
            // Unix-like .htaccess file
            $htaccessPath = $logDir . DIRECTORY_SEPARATOR . '.htaccess';
            if (!file_exists($htaccessPath)) {
                $htaccessContent = "Deny from all\n";
                if (file_put_contents($htaccessPath, $htaccessContent) === false) {
                    throw new Exception("Failed to create .htaccess file in: {$logDir}");
                }
            }
        }
    }

    /**
     * Validate and sanitize CLI process path
     */
    private function _validateProcessPath(string $path)
    {
        if (in_array($path, ['csvimporter', 'csv_importer'])) {
            return;
        }

        $realPath = realpath($path);

        if (!$realPath) {
            throw new InvalidArgumentException("Invalid process path: {$path}");
        }

        $allowed = array_map(function ($allowedPath) {
            return realpath(FCPATH . $allowedPath);
        }, $this->allowedProcessPaths);

        $isAllowed = array_reduce($allowed, function ($carry, $allowedPath) use ($realPath) {
            return $carry || strpos($realPath, $allowedPath) === 0;
        }, false);

        if (!$isAllowed) {
            throw new InvalidArgumentException("Unauthorized process path: {$path}");
        }
    }

    /**
     * Start a background process for a specific job
     */
    private function startBackgroundProcess(string $jobId)
    {
        if (empty($jobId)) {
            throw new InvalidArgumentException("Invalid job ID");
        }

        $this->isWindows = $this->_detectWindowsOS();

        // Set default log path with OS-specific path separator
        $this->logPath = $this->_getDefaultLogPath();

        $this->_ensureLogDirectorySecurity();
        $this->_validateProcessPath($this->pathToCLIProcess);

        $workerCmd = $this->_prepareSecureWorkerCommand($jobId);

        $lockFile = $this->_generateLockFilePath($jobId);

        if ($this->_isProcessAlreadyRunning($lockFile)) {
            $this->_logMessage("Process for job {$jobId} already running", 'warning');
            return false;
        }

        $this->_logMessage("Start background process using '{$workerCmd}' commands", 'info');

        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                if ($this->isWindows) {
                    $result = $this->_startWindowsProcess($workerCmd, $lockFile);
                } else {
                    $result = $this->_startUnixProcess($workerCmd, $lockFile);
                }

                if ($result) {
                    $this->_logMessage("Background process started for job {$jobId}", 'info');
                    return true;
                }
            } catch (Exception $e) {
                $this->_logMessage("Process start attempt {$attempt} failed: " . $e->getMessage(), 'error');

                if ($attempt < $this->retryAttempts) {
                    sleep($this->retryDelay);
                }
            }
        }

        $this->_logMessage("Failed to start background process for job {$jobId} after {$this->retryAttempts} attempts", 'error');
        return false;
    }

    /**
     * Generate OS-compatible lock file path
     */
    private function _generateLockFilePath(string $jobId): string
    {
        $tempDir = sys_get_temp_dir();
        $lockFileName = "secure_csv_import_{$jobId}.lock";

        return $this->isWindows
            ? $tempDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], '_', $lockFileName)
            : $tempDir . DIRECTORY_SEPARATOR . $lockFileName;
    }

    /**
     * Start process on Windows
     */
    private function _startWindowsProcess(string $cmd, string $lockFile)
    {
        // Create a unique window title
        $windowTitle = 'CSV_Import_' . uniqid();

        // Create a temporary batch file
        $batchFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'csv_import_' . uniqid() . '.bat';
        $batchContent = '@echo off' . PHP_EOL;
        $batchContent .= sprintf(
            'start /B /MIN "%s" %s',
            $windowTitle,
            $cmd
        );

        file_put_contents($batchFile, $batchContent);

        // Log the batch file content for debugging
        $this->_logMessage('Batch file content: ' . $batchContent, 'debug');

        $descriptorspec = $this->_getProcessDescriptors();

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

            // Use existing PID tracking method
            $pid = getmypid();
            file_put_contents($lockFile, $pid, LOCK_EX);
            chmod($lockFile, 0600);

            return $pid;
        }

        // Clean up batch file if the process failed to start
        if (file_exists($batchFile)) {
            unlink($batchFile);
        }

        $this->_logMessage('Failed to start Windows process', 'error');
        return false;
    }

    /**
     * Start process on Unix-like systems
     */
    private function _startUnixProcess(string $cmd, string $lockFile)
    {
        $descriptors = $this->_getProcessDescriptors();
        $env = $this->_prepareProcessEnvironment();

        $process = proc_open(
            $cmd,
            $descriptors,
            $pipes,
            FCPATH,
            $env,
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            throw new Exception("Failed to start process");
        }

        $status = proc_get_status($process);
        $pid = $status['pid'];

        file_put_contents($lockFile, $pid, LOCK_EX);
        chmod($lockFile, 0600);

        if (!empty($pipes)) {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }
        proc_close($process);

        return $pid;
    }

    /**
     * Prepare secure worker command
     */
    private function _prepareSecureWorkerCommand(string $jobId): string
    {
        $workerCmd = $this->phpCommand;

        if ($this->customCLIPath) {
            $workerCmd .= ' ' . FCPATH . $this->initFiles . ' ' . $this->pathToCLIProcess . ' ';
        } else {
            $workerCmd .= ' ' . FCPATH . $this->pathToCLIProcess . ' ';
        }

        $workerCmd .= escapeshellarg($jobId);

        return $workerCmd;
    }

    /**
     * Check if process is already running
     */
    private function _isProcessAlreadyRunning(string $lockFile): bool
    {
        if (file_exists($lockFile)) {
            $pid = (int)file_get_contents($lockFile);
            return $this->_isPidAlive($pid);
        }
        return false;
    }

    /**
     * Prepare process descriptors
     */
    private function _getProcessDescriptors(): array
    {
        $this->_rotateLogFile();

        return [
            0 => ['pipe', 'r'],
            1 => ['file', $this->isWindows ? 'NUL' : $this->logPath, $this->isWindows ? 'w' : 'a'],
            2 => ['file', $this->isWindows ? 'NUL' : $this->logPath, $this->isWindows ? 'w' : 'a']
        ];
    }

    /**
     * Prepare process environment
     */
    private function _prepareProcessEnvironment(): array
    {
        return array_merge($_ENV, [
            'MEMORY_LIMIT' => $this->memory_limit_process,
            'MAX_EXECUTION_TIME' => $this->max_execution_time_process,
            'DEBUG_MODE' => $this->debug ? '1' : '0'
        ]);
    }

    /**
     * Log file rotation mechanism
     */
    private function _rotateLogFile()
    {
        clearstatcache();
        if (file_exists($this->logPath) && filesize($this->logPath) > $this->logRotateSize) {
            $backupFile = "{$this->logPath}.old";
            rename($this->logPath, $backupFile);
        }
    }

    /**
     * Enhanced logging with log levels
     */
    protected function _logMessage(string $message, string $level = 'info')
    {
        $logLevels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $currentLevel = $logLevels[$this->logLevel] ?? 1;

        if ($logLevels[$level] >= $currentLevel) {
            $logEntry = sprintf(
                "[%s] %s - %s\n",
                strtoupper($level),
                date('Y-m-d H:i:s'),
                $message
            );
            file_put_contents($this->logPath, $logEntry, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Check if a PID is alive with multiple verification methods for Windows and Unix
     */
    protected function _isPidAlive(int $pid): bool
    {
        if ($pid <= 0) return false;

        if ($this->isWindows) {
            // Windows process check
            $output = [];
            exec("tasklist /FI \"PID eq {$pid}\"", $output);
            return count($output) > 1;
        } else {
            // Unix-like process check
            return (
                (function_exists('posix_kill') && posix_kill($pid, 0)) ||
                (function_exists('posix_getpgid') && posix_getpgid($pid)) ||
                file_exists("/proc/{$pid}")
            );
        }
    }
}
