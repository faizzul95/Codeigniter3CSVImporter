<?php

namespace OnlyPHP\Codeigniter3CSVImporter\Traits;

trait BackgroundRunnerTableTraits
{
    private $table = 'system_csv_process_jobs';

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
}
