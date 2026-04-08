<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables (safeLoad won't crash if .env is missing, e.g. on Vercel)
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Database configuration
function getDB() {
    $database_url = getenv('DATABASE_URL') ?: $_ENV['DATABASE_URL'];
    
    if (!$database_url) {
        // Fallback for local development
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'affiliate_store';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        
        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Connection failed. Please check error logs.");
        }
    }
    
    // Parse PlanetScale URL format
    $parts = parse_url($database_url);
    $host = $parts['host'];
    $port = $parts['port'] ?? 3306;
    $dbname = ltrim($parts['path'], '/');
    $user = $parts['user'];
    $pass = $parts['pass'] ?? '';
    
    $ssl_ca = '/etc/ssl/certs/ca-certificates.crt';
    if (file_exists('/etc/ssl/cert.pem')) {
        $ssl_ca = '/etc/ssl/cert.pem';
    }
    
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_SSL_CA => $ssl_ca,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Connection failed. Please check your DATABASE_URL environment variable.");
    }
}

// Amazon API Configuration
define('AMAZON_ACCESS_KEY', getenv('AMAZON_ACCESS_KEY') ?: $_ENV['AMAZON_ACCESS_KEY']);
define('AMAZON_SECRET_KEY', getenv('AMAZON_SECRET_KEY') ?: $_ENV['AMAZON_SECRET_KEY']);
define('AMAZON_ASSOCIATE_TAG', getenv('AMAZON_ASSOCIATE_TAG') ?: $_ENV['AMAZON_ASSOCIATE_TAG']);
define('AMAZON_REGION', getenv('AMAZON_REGION') ?: $_ENV['AMAZON_REGION']);

// Gemini AI Configuration
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: $_ENV['GEMINI_API_KEY']);
define('GEMINI_MODEL', getenv('GEMINI_MODEL') ?: $_ENV['GEMINI_MODEL']);

// Site Configuration
define('SITE_NAME', getenv('SITE_NAME') ?: $_ENV['SITE_NAME']);
define('SITE_URL', getenv('SITE_URL') ?: $_ENV['SITE_URL']);
define('SITE_DESCRIPTION', getenv('SITE_DESCRIPTION') ?: $_ENV['SITE_DESCRIPTION']);

// Admin Configuration
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: $_ENV['ADMIN_PASSWORD']);
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: $_ENV['ADMIN_EMAIL']);

// Auto Blog Settings
define('AUTO_BLOG_ENABLED', filter_var(getenv('AUTO_BLOG_ENABLED') ?: $_ENV['AUTO_BLOG_ENABLED'], FILTER_VALIDATE_BOOLEAN));
define('AUTO_BLOG_INTERVAL_HOURS', getenv('AUTO_BLOG_INTERVAL_HOURS') ?: $_ENV['AUTO_BLOG_INTERVAL_HOURS']);
define('MIN_WORD_COUNT', getenv('MIN_WORD_COUNT') ?: $_ENV['MIN_WORD_COUNT']);
define('MAX_WORD_COUNT', getenv('MAX_WORD_COUNT') ?: $_ENV['MAX_WORD_COUNT']);

// Get Amazon search indices from env or use defaults
define('AMAZON_SEARCH_INDICES', explode(',', getenv('AMAZON_SEARCH_INDICES') ?: $_ENV['AMAZON_SEARCH_INDICES']));

// Get setting value
function getSetting($key) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['value'] : null;
    } catch (Exception $e) {
        return null;
    }
}

// Update setting
function updateSetting($key, $value) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
        return $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {
        return false;
    }
}

// Generate slug
function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// Get all active categories
function getAllCategories($featured_only = false) {
    $pdo = getDB();
    if ($featured_only) {
        $stmt = $pdo->query("SELECT * FROM categories WHERE featured = 1 ORDER BY display_order, name");
    } else {
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY display_order, name");
    }
    return $stmt->fetchAll();
}

// Get category by slug
function getCategoryBySlug($slug) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

// Get subcategories for a category
function getSubcategories($category_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM subcategories WHERE category_id = ? ORDER BY name");
    $stmt->execute([$category_id]);
    return $stmt->fetchAll();
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>