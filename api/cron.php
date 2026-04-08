<?php
// Cron job runner - set this to run every 6 hours
// For Vercel: This runs automatically via vercel.json
// For traditional hosting: Set up cron job to hit this URL

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../amazon_api.php';
require_once __DIR__ . '/../gemini_api.php';

// Set execution time limit to unlimited for cron jobs
set_time_limit(0);

function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[CRON][$timestamp] $message");
    
    // Also log to file if possible
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/cron_' . date('Y-m-d') . '.log';
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Check if running via CLI or web
$is_cli = (php_sapi_name() === 'cli');
$is_vercel_cron = isset($_SERVER['HTTP_VERCEL_CRON']) || isset($_GET['secret']) && $_GET['secret'] === 'your-secret-key';

if (!$is_cli && !$is_vercel_cron) {
    // Optional: Add secret key protection
    if (!isset($_GET['secret']) || $_GET['secret'] !== getenv('CRON_SECRET')) {
        http_response_code(403);
        die('Access denied');
    }
}

log_message("Starting cron job...");

try {
    // Update products from Amazon
    log_message("Updating products from Amazon...");
    $products_updated = autoUpdateProducts();
    log_message("Updated $products_updated products");
    
    // Generate AI blog posts if enabled
    if (AUTO_BLOG_ENABLED) {
        log_message("Generating AI blog posts with Gemini...");
        $posts_generated = generateAutoBlogPosts();
        log_message($posts_generated);
    }
    
    // Clean up old logs (keep last 30 days)
    $log_dir = __DIR__ . '/../logs';
    if (is_dir($log_dir)) {
        $files = glob($log_dir . '/cron_*.log');
        foreach ($files as $file) {
            if (filemtime($file) < strtotime('-30 days')) {
                unlink($file);
                log_message("Deleted old log: " . basename($file));
            }
        }
    }
    
    log_message("Cron job completed successfully");
    
    if (!$is_cli) {
        echo json_encode(['success' => true, 'products' => $products_updated, 'posts' => $posts_generated ?? 0]);
    }
    
} catch (Exception $e) {
    log_message("ERROR: " . $e->getMessage());
    log_message("Stack trace: " . $e->getTraceAsString());
    
    if (!$is_cli) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>