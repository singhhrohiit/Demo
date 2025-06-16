<?php
require_once 'functions.php';

// This script should send GitHub updates to all registered emails every 5 minutes.
$logFile = __DIR__ . '/cron.log';
$timestamp = date('Y-m-d H:i:s');

try {
    sendGitHubUpdatesToSubscribers();
    
    $logMessage = "[$timestamp] CRON job executed successfully - GitHub timeline updates sent to subscribers\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    echo "CRON job completed successfully at $timestamp\n";
    
} catch (Exception $e) {
    $errorMessage = "[$timestamp] CRON job failed: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $errorMessage, FILE_APPEND | LOCK_EX);
    
    echo "CRON job failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>