# CodeIgniter 3 CSV Importer 📊

[![Latest Version](https://img.shields.io/packagist/v/onlyphp/codeigniter3-csvimporter.svg?style=flat-square)](https://packagist.org/packages/onlyphp/codeigniter3-csvimporter)
[![Total Downloads](https://img.shields.io/packagist/dt/onlyphp/codeigniter3-csvimporter.svg?style=flat-square)](https://packagist.org/packages/onlyphp/codeigniter3-csvimporter)
[![License](https://img.shields.io/packagist/l/onlyphp/codeigniter3-csvimporter.svg?style=flat-square)](LICENSE.md)

A robust CSV importer library for CodeIgniter 3 with background processing support, progress tracking, and detailed statistics. Perfect for handling large CSV files without timeouts or memory issues.

## ✨ Features

- 🚀 Background processing support
- 📊 Real-time progress tracking
- 📈 Detailed statistics (inserts/updates/errors)
- ⚠️ Comprehensive error handling
- 💻 Shared hosting compatible
- ⏰ No cron job required
- 🛠️ Customizable processing logic

## 📦 Installation

Install via Composer:

```bash
composer require onlyphp/codeigniter3-csvimporter
```

## 📝 Usage

### Basic Usage

```php
// Initialize the processor
$processor = new \OnlyPHP\CSVSimpleImporter\CSVImportProcessor();

// Set callback function for processing each row
$processor->setCallback(function($row, $rowIndex, $models) {
    try {
        // Process your row data here
        return [
            'code' => 200,
            'action' => 'create',
            'message' => 'Success'
        ];
    } catch (\Exception $e) {
        return [
            'code' => 500,
            'error' => $e->getMessage()
        ];
    }
});

// Start processing
$jobId = $processor->process('/path/to/your/file.csv');
```

### Advanced Usage

```php
$processor = new \OnlyPHP\CSVSimpleImporter\CSVImportProcessor();

// Set user ID for file ownership
$processor->setFileBelongsTo(1);

// Set HTML element ID for frontend progress tracking
$processor->setDisplayHTMLId('progress-bar-1');

// Set whether to skip header row
$processor->setSkipHeader(true);

// Load specific models for processing
$processor->setCallbackModel(['User_model', 'Product_model']);

// Set callback with loaded models
$processor->setCallback(function($row, $rowIndex, $models) {
    $userModel = $models['User_model'];
    $productModel = $models['Product_model'];

    try {
        // Your processing logic here
        $result = $userModel->createFromCSV($row);

        return [
            'code' => 200,
            'action' => 'create',
            'message' => 'User created successfully'
        ];
    } catch (\Exception $e) {
        return [
            'code' => 500,
            'error' => 'Row ' . $rowIndex . ': ' . $e->getMessage()
        ];
    }
});

// Start processing
$jobId = $processor->process('/path/to/your/file.csv');
```

### Tracking Progress

```php
// Get processing status
$status = $processor->getStatus($jobId);

// Get processing status owner user id (return collection of array for all process)
$status = $processor->getStatusByOwner($userid);

// Status contains:
[
    'total_process' => 100,
    'total_success' => 95,
    'total_failed' => 5,
    'total_inserted' => 80,
    'total_updated' => 15,
    'display_id' => 'progress-bar-1',
    'estimate_time' => [
        'hours' => 0,
        'minutes' => 5,
        'seconds' => 30
    ],
    'file_name' => 'users.csv',
    'status' => 2, // 1=Pending, 2=Processing, 3=Completed, 4=Failed
    'error_message' => '[]',
    'percentage_completion' => '10'
]
```

### Frontend Integration Example

```javascript
function checkProgress(jobId) {
  $.ajax({
    url: "/your-controller/check-progress",
    data: { job_id: jobId },
    success: function (response) {
      if (response.status == 3) {
        // Process completed
        $("#progress-bar-1").html("Import completed!");
      } else if (response.status == 4) {
        // Process failed
        $("#progress-bar-1").html("Import failed: " + response.error_message);
      } else {
        // Update progress
        let progress = (response.total_process / response.total_data) * 100;
        $("#progress-bar-1").css("width", progress + "%");

        // Check again in 2 seconds
        setTimeout(() => checkProgress(jobId), 2000);
      }
    },
  });
}
```

## 📊 Status Codes Reference 

| Code | Status     | Description                      |
|------|------------|----------------------------------|
| 1    | Pending    | Job created, waiting to start    |
| 2    | Processing | Currently processing the file    |
| 3    | Completed  | Processing finished successfully |
| 4    | Failed     | Processing encountered an error  |

## ⚙️ Callback Response Format

The callback function should return an array with the following structure:

```php
// Success response
return [
    'code' => 200,
    'action' => 'create', // or 'update'
    'error' => ''
];

// Error response
return [
    'code' => 500,
    'error' => 'Error message'
];
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

The MIT License (MIT). Please see [License File](license.md) for more information.

## 💖 Support

If you find this library helpful, please consider giving it a star on GitHub!
