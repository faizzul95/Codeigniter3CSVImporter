<?php

namespace OnlyPHP\Codeigniter3CSVImporter;

use OnlyPHP\Codeigniter3CSVImporter\Traits\BackgroundRunnerTraits;
use function Opis\Closure\{serialize, unserialize};

class CSVImportProcessor
{
    use BackgroundRunnerTraits;

    private $ci;
    private $table = 'csv_process_jobs';
    private $update_interval = 250;
    private $skipHeader = true;
    private $userId = null;
    private $displayId = null;
    private $callback = null;
    private $models = null;

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
     * Initialize CSV processing job
     * @param string $filepath Full path to CSV file
     * @return string Job ID
     * @throws \Exception
     */
    public function process(string $filepath)
    {
        if (!$this->callback) {
            throw new \Exception('Callback function must be set before processing');
        }

        if (!file_exists($filepath)) {
            throw new \Exception('CSV file not found');
        }

        // Generate unique job ID
        $jobId = uniqid('csv_', true);

        // Get file info
        $fileInfo = pathinfo($filepath);

        // Count total rows (excluding header if needed)
        $totalRows = $this->countRows($filepath) - ($this->skipHeader ? 1 : 0);

        // Serialize callback
        $serializedCallback = serialize($this->callback);

        // Insert job record
        $data = [
            'job_id' => $jobId,
            'filepath' => $filepath,
            'filename' => $fileInfo['basename'],
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

        if (!$this->isProcessRunning($jobId)) {
            throw new \Exception("Process with job ID {$jobId} is not running.");
        }

        return $jobId;
    }

    /**
     * Initialize CSV processing job (Used for testing only)
     * @param string $filepath Full path to CSV file
     * @return string Job ID
     * @throws \Exception
     */
    public function processNow(string $filepath)
    {
        if (!$this->callback) {
            throw new \Exception('Callback function must be set before processing');
        }

        if (!file_exists($filepath)) {
            throw new \Exception('CSV file not found');
        }

        // Generate unique job ID
        $jobId = uniqid('csv_', true);

        // Get file info
        $fileInfo = pathinfo($filepath);

        // Count total rows (excluding header if needed)
        $totalRows = $this->countRows($filepath) - ($this->skipHeader ? 1 : 0);

        // Serialize callback
        $serializedCallback = serialize($this->callback);

        // Insert job record
        $data = [
            'job_id' => $jobId,
            'filepath' => $filepath,
            'filename' => $fileInfo['basename'],
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

        if (!$job || $job->status != 1) {
            return;
        }

        // Store the original values
        $originalTimeLimit = ini_get('max_execution_time');
        $originalMemoryLimit = ini_get('memory_limit');

        // Update ini settings for this process
        set_time_limit(0);
        ini_set('memory_limit', '3G');

        try {

            if (!file_exists($job->filepath)) {
                throw new \Exception('CSV file not found');
            }

            // Update status to processing
            $this->updateJob($jobId, ['status' => 2, 'start_time' => date('Y-m-d H:i:s')]);
            $callback = unserialize($job->callback);
            $handle = fopen($job->filepath, 'r');

            if ($job->skip_header) {
                fgetcsv($handle);
            }

            $processed = 0;
            $skip = 0;
            $success = 0;
            $failed = 0;
            $inserted = 0;
            $updated = 0;
            $errors = [];
            $needsUpdate = false;

            $models = json_decode($job->callback_model, true);

            $rowIndex = 0;
            while (($row = fgetcsv($handle)) !== FALSE) {
                $rowIndex++;

                // Skip empty rows
                if (empty(array_filter($row))) {
                    $skip++;
                    continue;
                }

                try {

                    $loadedModels = [];
                    if (!empty($models)) {
                        foreach ($models as $model) {
                            $this->ci->load->model($model);
                            $loadedModels[$model] = $this->ci->$model;
                        }
                    }

                    $result = call_user_func($callback, $row, $rowIndex, $loadedModels);

                    if (isset($result['code']) && $result['code'] === 200) {
                        $success++;
                        if (isset($result['code']) && $result['action'] === 'create') {
                            $inserted++;
                        } elseif (isset($result['code']) && $result['action'] === 'update') {
                            $updated++;
                        }
                    } else {
                        $failed++;
                        if (isset($result['error']) && $result['error']) {
                            $errors[] = $result['error'];
                        }
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = $e->getMessage();
                }

                $processed++;
                $needsUpdate = true;

                // Update status every update_interval records
                if ($processed % $this->update_interval === 0) {
                    $this->updateProgress($jobId, $processed, $skip, $success, $failed, $inserted, $updated, $errors);
                    $needsUpdate = false;

                    // Close the DB connection after each batch of 100 rows
                    $this->ci->db->close();
                    sleep(1); // 1 second delay to avoid overloading the DB

                    // Reopen DB connection for next batch
                    $this->ci->load->database();
                }
            }

            // Final update for remaining records
            if ($needsUpdate) {
                $this->updateProgress($jobId, $processed, $skip, $success, $failed, $inserted, $updated, $errors);
            }

            fclose($handle);

            // Update final status
            $endTime = date('Y-m-d H:i:s');
            $runTime = strtotime($endTime) - strtotime($job->start_time);

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
            $this->updateJob($jobId, [
                'status' => 4,
                'error_message' => $e->getMessage(),
                'end_time' => date('Y-m-d H:i:s')
            ]);
        } finally {
            // Restore the original ini settings
            set_time_limit($originalTimeLimit);
            ini_set('memory_limit', $originalMemoryLimit);
        }
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
     * Count total rows in CSV file
     * @param string $filepath
     * @return int
     */
    private function countRows(string $filepath)
    {
        $linecount = 0;
        $handle = fopen($filepath, "r");
        while (!feof($handle)) {
            $line = fgets($handle);
            if (trim($line) !== '') {
                $linecount++;
            }
        }
        fclose($handle);
        return $linecount;
    }
}
