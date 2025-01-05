<?php

namespace OnlyPHP\Codeigniter3CSVImporter;

use OnlyPHP\Codeigniter3CSVImporter\Traits\BackgroundRunnerTraits;
use function Opis\Closure\{serialize, unserialize};

class CSVImportProcessor
{
    use BackgroundRunnerTraits;

    private $ci;
    private $table = 'csv_process_jobs';
    private $skipHeader = true;
    private $userId = null;
    private $displayId = null;
    private $callback = null;
    private $models = null;
    private $memory_limit = '1G';
    private $delimiter = ',';
    private $enclosure = '"';
    private $escape = '\\';
    private $chunk_size = 1000;
    private $refresh_data = 200;

    /**
     * Constructor - Initialize CI instance and check table existence
     */
    public function __construct()
    {
        $this->ci = &get_instance();
        $this->ci->load->database('default', TRUE);

        $this->checkAndCreateTable();
    }

    /**
     * Check and create required database table
     * @return void
     */
    private function checkAndCreateTable()
    {
        if (!$this->ci->db->table_exists($this->table)) {
            $this->ci->load->dbforge();

            $fields = [
                'id' => [
                    'type' => 'BIGINT',
                    'unsigned' => TRUE,
                    'auto_increment' => TRUE
                ],
                'job_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => '100',
                    'unique' => TRUE
                ],
                'filepath' => [
                    'type' => 'VARCHAR',
                    'constraint' => '255',
                    'null' => TRUE
                ],
                'filename' => [
                    'type' => 'VARCHAR',
                    'constraint' => '255',
                    'null' => TRUE
                ],
                'user_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => TRUE,
                    'null' => TRUE
                ],
                'total_data' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ],
                'total_processed' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ],
                'total_skip_empty_row' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ],
                'total_success' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ],
                'total_failed' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ],
                'total_inserted' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ],
                'total_updated' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ],
                'callback' => [
                    'type' => 'LONGTEXT',
                    'null' => TRUE
                ],
                'callback_model' => [
                    'type' => 'VARCHAR',
                    'constraint' => '255',
                    'null' => TRUE
                ],
                'error_message' => [
                    'type' => 'LONGTEXT',
                    'null' => TRUE
                ],
                'display_html_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => '255',
                    'null' => TRUE
                ],
                'skip_header' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                    'comment' => '1 - Yes, 0 - No'
                ],
                'status' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                    'comment' => '1 - Pending, 2 - Processing, 3 - Completed, 4 - Failed'
                ],
                'start_time' => [
                    'type' => 'TIMESTAMP',
                    'null' => TRUE
                ],
                'end_time' => [
                    'type' => 'TIMESTAMP',
                    'null' => TRUE
                ],
                'run_time' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0,
                    'comment' => 'in seconds'
                ],
                'created_at' => [
                    'type' => 'TIMESTAMP',
                    'default' => 'CURRENT_TIMESTAMP'
                ],
                'updated_at' => [
                    'type' => 'TIMESTAMP',
                    'null' => TRUE
                ]
            ];

            $this->ci->dbforge->add_field($fields);
            $this->ci->dbforge->add_key('id', TRUE);
            $this->ci->dbforge->create_table($this->table, FALSE, ['ENGINE' => 'InnoDB', 'COLLATE' => 'utf8mb4_general_ci']);
        }
    }

    /**
     * Set user ID for file ownership
     * @param int $userId
     * @return $this
     */
    public function setFileBelongsTo(int $userId)
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Set display HTML ID for frontend tracking
     * @param string $displayId
     * @return $this
     */
    public function setDisplayHTMLId(string $displayId)
    {
        $this->displayId = $displayId;
        return $this;
    }

    /**
     * Set callback function for processing rows
     * @param callable $callback
     * @return $this
     */
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * Set the model to initialize before processing
     * @param bool $skip
     * @return $this
     */
    public function setCallbackModel(array $models = [])
    {
        $this->models = $models;
        return $this;
    }

    /**
     * Set whether to skip header row
     * @param bool $skip
     * @return $this
     */
    public function setSkipHeader(bool $skip)
    {
        $this->skipHeader = $skip ? 1 : 0;
        return $this;
    }

    /**
     * Set memory limit for processing
     * @param string $limit Memory limit (e.g., '2G', '512M')
     * @return $this
     */
    public function setMemoryLimit(string $limit)
    {
        $this->memory_limit = $limit;
        return $this;
    }

    /**
     * Set CSV delimiter character
     * @param string $delimiter
     * @return $this
     */
    public function setDelimiter(string $delimiter)
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * Set CSV enclosure character
     * @param string $enclosure
     * @return $this
     */
    public function setEnclosure(string $enclosure)
    {
        $this->enclosure = $enclosure;
        return $this;
    }

    /**
     * Set CSV escape character
     * @param string $escape
     * @return $this
     */
    public function setEscape(string $escape)
    {
        $this->escape = $escape;
        return $this;
    }

    /**
     * Set chunk size for batch processing
     * @param int $size Number of rows to process in each chunk
     * @return $this
     */
    public function setChunkSize(int $size)
    {
        $this->chunk_size = $size;
        return $this;
    }

    /**
     * Set the interval for updating process records in the database.
     * The interval must be at least 100 to ensure sufficient time between updates.
     *
     * @param int $interval Number of seconds for the interval to update the process records in the database.
     * @throws InvalidArgumentException if the interval is less than 100.
     * @return $this
     */
    public function setRecordUpdateInterval(int $interval)
    {
        if ($interval < 100) {
            throw new \Exception('The interval must be at least 100.');
        }

        $this->refresh_data = $interval;
        return $this;
    }

    /**
     * Initialize CSV processing job
     * @param string $filepath Full path to CSV file
     * @param ?string $filename CSV file name
     * @return string Job ID
     * @throws \Exception
     */
    public function process(string $filepath, ?string $filename = null)
    {
        if (!$this->callback) {
            throw new \Exception('Callback function must be set before processing');
        }

        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw new \Exception("CSV file does not exist or is not readable: {$filepath}");
        }

        // Get file info
        $fileInfo = pathinfo($filepath);
        $filename = $filename ?: $fileInfo['basename'];

        if (strtolower($fileInfo['extension']) !== 'csv') {
            throw new \Exception("The file is not a valid CSV file: {$filepath}");
        }

        // Generate unique job ID
        $jobId = uniqid('csv_', true);

        // Count total rows (excluding header if needed)
        $totalRows = $this->countRows($filepath) - ($this->skipHeader ? 1 : 0);

        // Serialize callback
        $serializedCallback = serialize($this->callback);

        // Insert job record
        $data = [
            'job_id' => $jobId,
            'filepath' => $filepath,
            'filename' => $filename,
            'user_id' => $this->userId,
            'total_data' => $totalRows,
            'skip_header' => $this->skipHeader,
            'callback' => $serializedCallback,
            'callback_model' => json_encode($this->models),
            'display_html_id' => $this->displayId,
            'status' => 1
        ];

        $this->ci->db->insert($this->table, $data);

        // Start background processing
        $this->startBackgroundProcess($jobId);

        return $jobId;
    }

    /**
     * Initialize CSV processing job (Used for testing only)
     * @param string $filepath Full path to CSV file
     * @param ?string $filename CSV file name
     * @return string Job ID
     * @throws \Exception
     */
    public function processNow(string $filepath, ?string $filename = null)
    {
        if (!$this->callback) {
            throw new \Exception('Callback function must be set before processing');
        }

        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw new \Exception("CSV file does not exist or is not readable: {$filepath}");
        }

        // Get file info
        $fileInfo = pathinfo($filepath);
        $filename = $filename ?: $fileInfo['basename'];

        if (strtolower($fileInfo['extension']) !== 'csv') {
            throw new \Exception("The file is not a valid CSV file: {$filepath}");
        }

        // Generate unique job ID
        $jobId = uniqid('csv_', true);

        // Count total rows (excluding header if needed)
        $totalRows = $this->countRows($filepath) - ($this->skipHeader ? 1 : 0);

        // Serialize callback
        $serializedCallback = serialize($this->callback);

        // Insert job record
        $data = [
            'job_id' => $jobId,
            'filepath' => $filepath,
            'filename' => $filename,
            'user_id' => $this->userId,
            'total_data' => $totalRows,
            'skip_header' => $this->skipHeader,
            'callback' => $serializedCallback,
            'callback_model' => json_encode($this->models),
            'display_html_id' => $this->displayId,
            'status' => 1
        ];

        $this->ci->db->insert($this->table, $data);

        $this->processFile($jobId);

        return $jobId;
    }

    /**
     * Process CSV file record by record
     * @param string $jobId
     * @return void
     */
    public function processFile(string $jobId)
    {
        $job = $this->ci->db->get_where($this->table, ['job_id' => $jobId])->row();

        // Create a lock file with process ID
        $lockFile = sys_get_temp_dir() . "/csv_import_{$jobId}.lock";

        if (!$job || $job->status != 1 || file_exists($lockFile)) {
            return;
        }

        file_put_contents($lockFile, getmypid());

        // Store the original values
        $originalTimeLimit = ini_get('max_execution_time');
        $originalMemoryLimit = ini_get('memory_limit');

        // Update ini settings for this process
        set_time_limit(0);
        ini_set('memory_limit', $this->memory_limit);

        try {

            $handle = $this->openCSVWithRetry($job->filepath);

            if (!$handle) {
                throw new \Exception('Unable to open CSV file');
            }

            $callback = unserialize($job->callback);

            if (empty($callback)) {
                throw new \Exception('Callback function must be set before processing');
            }

            // Update status to processing
            $this->updateJob($jobId, ['status' => 2, 'start_time' => date('Y-m-d H:i:s')]);

            // Set CSV reading options
            $this->configureCSVHandle($handle);

            if ($job->skip_header) {
                fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape);
            }

            $processed = 0;
            $skip = 0;
            $success = 0;
            $failed = 0;
            $inserted = 0;
            $updated = 0;
            $errors = [];
            $buffer = [];
            $totalChunkProcess = 0;

            while (!feof($handle)) {
                $row = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape);

                if ($row === false) {
                    continue;
                }

                // Skip empty rows
                if (empty(array_filter($row))) {
                    $skip++;
                    continue;
                }

                $buffer[] = $row;

                if (count($buffer) < $this->chunk_size) {
                    continue;
                }

                // Process in chunks
                $this->processChunk($jobId, $buffer, $callback, $job->callback_model, $processed, $skip, $success, $failed, $inserted, $updated, $errors);
                $buffer = [];

                // Update progress
                $this->updateProgressAndReset($jobId, $processed, $skip, $success, $failed, $inserted, $updated, $errors);

                // Update total chunk
                $totalChunkProcess++;
            }

            // Process remaining buffer
            if (!empty($buffer)) {
                $this->processChunk($jobId, $buffer, $callback, $job->callback_model, $processed, $skip, $success, $failed, $inserted, $updated, $errors);
                $this->updateProgressAndReset($jobId, $processed, $skip, $success, $failed, $inserted, $updated, $errors);
                $totalChunkProcess++;
            }

            fclose($handle);

            // Update final status
            $endTime = date('Y-m-d H:i:s');
            // Check if start_time is set and valid, otherwise set a default or handle gracefully
            $startTime = isset($job->start_time) && !empty($job->start_time) ? $job->start_time : null;

            if ($startTime !== null) {
                $runTime = max(0, (strtotime($endTime) - strtotime($startTime)) - $totalChunkProcess);
            } else {
                // Handle the case where start_time is null or invalid
                $runTime = 0; // Default runtime or appropriate fallback value
            }

            $this->updateJob($jobId, [
                'status' => 3,
                'total_processed' => $processed,
                'total_skip_empty_row' => $skip,
                'total_success' => $success,
                'total_failed' => $failed,
                'total_inserted' => $inserted,
                'total_updated' => $updated,
                'error_message' => json_encode($errors),
                'end_time' => $endTime,
                'run_time' => $runTime
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error during file processing: ' . $e->getMessage());
            $this->updateJob($jobId, [
                'status' => 4,
                'error_message' => $e->getMessage(),
                'end_time' => date('Y-m-d H:i:s')
            ]);
        } finally {
            // Restore the original ini settings
            set_time_limit($originalTimeLimit);
            ini_set('memory_limit', $originalMemoryLimit);

            // Remove the lock file
            @unlink($lockFile);

            // Clear memory
            gc_collect_cycles();
        }
    }

    /**
     * Configure CSV file handle with proper settings
     * @param resource $handle
     * @return void
     */
    private function configureCSVHandle($handle)
    {
        stream_set_read_buffer($handle, 8192); // 8KB read buffer
        stream_set_chunk_size($handle, 8192);  // 8KB chunk size
    }

    /**
     * Open CSV file with retry mechanism
     * @param string $filepath
     * @param int $maxRetries
     * @return resource|false
     */
    private function openCSVWithRetry(string $filepath, int $maxRetries = 3)
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw new \InvalidArgumentException("File does not exist or is not readable: {$filepath}");
        }

        $attempt = 0;
        do {
            $handle = fopen($filepath, 'r');
            if ($handle !== false) {
                return $handle;
            }
            $attempt++;
            if ($attempt < $maxRetries) {
                sleep(1);
            }
        } while ($attempt < $maxRetries);

        return false;
    }

    /**
     * Process a chunk of CSV rows
     * @param string $jobId
     * @param array $chunk
     * @param callable $callback
     * @param string $loadedModels
     * @param int &$processed
     * @param int &$skip
     * @param int &$success
     * @param int &$failed
     * @param int &$inserted
     * @param int &$updated
     * @param array &$errors
     * @return void
     */
    private function processChunk(string $jobId, array $chunk, callable $callback, string $loadedModels, &$processed, &$skip, &$success, &$failed, &$inserted, &$updated, &$errors)
    {
        foreach ($chunk as $rowIndex => $row) {

            // Skip only if row has no content at all
            if ($row === null || !$this->hasContent($row)) {
                $skip++;
                continue;
            }

            try {

                $models = $this->loadModels($loadedModels);

                // Increment processed count and pass to callback
                $currentProcessed = $processed + $rowIndex + 1;  // Update the current processed value

                $result = call_user_func($callback, $row, $currentProcessed, $models);

                if (isset($result['code']) && in_array($result['code'], [200, 201])) {
                    $success++;
                    if (isset($result['action']) && $result['action'] === 'create') {
                        $inserted++;
                    } elseif (isset($result['action']) && $result['action'] === 'update') {
                        $updated++;
                    }
                } else {
                    $failed++;
                    if (isset($result['error']) && $result['error']) {
                        $errors['data'][] = $result['error'];
                    }
                }
            } catch (\Exception $e) {
                $failed++;
                $errors['system'][] = $e->getMessage();
            }

            // Check if it's time to refresh progress
            if ($this->refresh_data != $this->chunk_size && $currentProcessed % $this->refresh_data === 0) {
                $this->updateProgress($jobId, $currentProcessed, $skip, $success, $failed, $inserted, $updated, $errors);
            }
        }

        $processed += count($chunk);
    }

    /**
     * Load required models
     * @param string $models
     * @return array
     */
    private function loadModels(string $models)
    {
        $loadedModels = [];
        $modelsArr = json_decode($models, true);

        if (!empty($modelsArr)) {
            foreach ($modelsArr as $model) {
                $this->ci->load->model($model);
                $loadedModels[$model] = $this->ci->$model;
            }
        }
        return $loadedModels;
    }

    /**
     * Update progress and reset database connection
     * @param string $jobId
     * @param int $processed
     * @param int $skip
     * @param int $success
     * @param int $failed
     * @param int $inserted
     * @param int $updated
     * @param array $errors
     * @return void
     */
    private function updateProgressAndReset($jobId, $processed, $skip, $success, $failed, $inserted, $updated, $errors)
    {
        $this->updateProgress($jobId, $processed, $skip, $success, $failed, $inserted, $updated, $errors);

        // Reset DB connection
        $this->ci->db->close();
        sleep(1);
        $this->ci->load->database();

        // Clear memory
        gc_collect_cycles();
    }

    /**
     * Update progress information
     * @param string $jobId
     * @param int $processed
     * @param int $skip
     * @param int $success
     * @param int $failed
     * @param int $inserted
     * @param int $updated
     * @param array $errors
     * @return void
     */
    private function updateProgress($jobId, $processed, $skip, $success, $failed, $inserted, $updated, $errors)
    {
        $this->updateJob($jobId, [
            'total_processed' => $processed,
            'total_skip_empty_row' => $skip,
            'total_success' => $success,
            'total_failed' => $failed,
            'total_inserted' => $inserted,
            'total_updated' => $updated,
            'error_message' => json_encode($errors)
        ]);
    }

    /**
     * Get job status with detailed statistics by owner
     * @param int $userId
     * @return array
     */
    public function getStatusByOwner(int $userId): array
    {
        $jobs = $this->ci->db->get_where($this->table, ['user_id' => $userId])->result();

        if (empty($jobs)) {
            return [];
        }

        return array_map(function ($job) {
            return $this->formatJobData($job);
        }, $jobs);
    }

    /**
     * Get job status with detailed statistics
     * @param string $jobId
     * @return array|null
     */
    public function getStatus(string $jobId): ?array
    {
        $job = $this->ci->db->get_where($this->table, ['job_id' => $jobId])->row();

        if (!$job) {
            return null;
        }

        return $this->formatJobData($job);
    }

    /**
     * Format job data for response
     * @param object $job
     * @return array
     */
    protected function formatJobData(object $job): array
    {
        $totalProcessed = (int)$job->total_processed;
        $totalData = (int)$job->total_data;

        // Calculate percentage completion based on total_data and total_processed
        $percentageCompletion = $totalProcessed > 0
            ? round(($totalProcessed / $totalData) * 100, 2)
            : 0;

        return [
            'job_id' => $job->job_id,
            'total_process' => $totalProcessed,
            'total_success' => (int)$job->total_success,
            'total_failed' => (int)$job->total_failed,
            'total_inserted' => (int)$job->total_inserted,
            'total_updated' => (int)$job->total_updated,
            'display_id' => $job->display_html_id,
            'estimate_time' => $this->calculateEstimatedTime($job),
            'file_name' => $job->filename,
            'status' => $job->status,
            'error_message' => $job->error_message,
            'last_check' => date('Y-m-d H:i:s'),
            'percentage_completion' => $percentageCompletion
        ];
    }

    /**
     * Calculate estimated time remaining
     * @param object $job
     * @return array
     */
    private function calculateEstimatedTime($job)
    {
        if ($job->total_processed == 0 || $job->status == 3 || $job->status == 4) {
            return ['hours' => 0, 'minutes' => 0, 'seconds' => 0];
        }

        $timeElapsed = time() - strtotime($job->start_time);
        $recordsRemaining = $job->total_data - $job->total_processed;
        $processRate = $job->total_processed / $timeElapsed;
        $estimatedSecondsRemaining = $processRate > 0 ? $recordsRemaining / $processRate : 0;

        return [
            'hours' => floor($estimatedSecondsRemaining / 3600),
            'minutes' => floor(($estimatedSecondsRemaining % 3600) / 60),
            'seconds' => floor($estimatedSecondsRemaining % 60)
        ];
    }

    /**
     * Update job record
     * @param string $jobId
     * @param array $data
     * @return void
     */
    private function updateJob(string $jobId, array $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->ci->db->update($this->table, $data, ['job_id' => $jobId]);
    }

    /**
     * Count total rows in a CSV file, counting any row that has at least one non-empty field
     *
     * @param string $filepath Path to the CSV file.
     * @return int Total number of rows in the CSV file.
     * @throws RuntimeException If the file cannot be opened.
     */
    private function countRows(string $filepath)
    {
        $lineCount = 0;
        $handle = $this->openCSVWithRetry($filepath);

        if ($handle === false) {
            throw new \RuntimeException("Unable to open file: {$filepath}");
        }

        // Set CSV reading options
        $this->configureCSVHandle($handle);

        while (($data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
            // Count row if at least one field has non-empty content
            if ($data !== null && $this->hasContent($data)) {
                $lineCount++;
            }
        }

        fclose($handle);
        return $lineCount;
    }

    /**
     * Check if array has at least one non-empty value
     * 
     * @param array $row
     * @return bool
     */
    private function hasContent(array $row): bool
    {
        foreach ($row as $field) {
            // Check if the field has any content after trimming
            if ($field !== null && trim((string)$field) !== '') {
                return true;
            }
        }
        return false;
    }
}
