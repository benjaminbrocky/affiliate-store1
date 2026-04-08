<?php
require_once __DIR__ . '/config.php';

class AmazonAPI {
    private $access_key;
    private $secret_key;
    private $associate_tag;
    private $region;
    private $endpoint;
    
    public function __construct() {
        $this->access_key = AMAZON_ACCESS_KEY;
        $this->secret_key = AMAZON_SECRET_KEY;
        $this->associate_tag = AMAZON_ASSOCIATE_TAG;
        $this->region = AMAZON_REGION;
        $this->endpoint = "webservices.amazon.{$this->region}";
    }
    
    public function searchProducts($keywords, $searchIndex = 'All', $page = 1) {
        $params = [
            'Service' => 'AWSECommerceService',
            'Operation' => 'ItemSearch',
            'AWSAccessKeyId' => $this->access_key,
            'AssociateTag' => $this->associate_tag,
            'SearchIndex' => $searchIndex,
            'Keywords' => $keywords,
            'ResponseGroup' => 'Large',
            'ItemPage' => $page,
            'Sort' => 'relevancerank'
        ];
        return $this->makeRequest($params);
    }
    
    public function getProductsByCategory($searchIndex, $keywords = '', $limit = 20) {
        $params = [
            'Service' => 'AWSECommerceService',
            'Operation' => 'ItemSearch',
            'AWSAccessKeyId' => $this->access_key,
            'AssociateTag' => $this->associate_tag,
            'SearchIndex' => $searchIndex,
            'Sort' => 'salesrank',
            'ResponseGroup' => 'Large'
        ];
        
        if (!empty($keywords)) {
            $params['Keywords'] = $keywords;
        }
        
        return $this->makeRequest($params);
    }
    
    public function getProductDetails($asin) {
        $params = [
            'Service' => 'AWSECommerceService',
            'Operation' => 'ItemLookup',
            'AWSAccessKeyId' => $this->access_key,
            'AssociateTag' => $this->associate_tag,
            'ItemId' => $asin,
            'ResponseGroup' => 'Large'
        ];
        return $this->makeRequest($params);
    }
    
    private function makeRequest($params) {
        if (!$this->access_key || !$this->secret_key || !$this->associate_tag) {
            error_log("Amazon API credentials are not set");
            return false;
        }
        
        $params['Timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
        ksort($params);
        
        $query = http_build_query($params);
        $string_to_sign = "GET\n{$this->endpoint}\n/onca/xml\n{$query}";
        $signature = base64_encode(hash_hmac("sha256", $string_to_sign, $this->secret_key, true));
        
        $url = "http://{$this->endpoint}/onca/xml?{$query}&Signature=" . urlencode($signature);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Amazon API error: HTTP {$httpCode}");
            return false;
        }
        
        return simplexml_load_string($response);
    }
    
    public function saveProductsToDatabase($response, $category, $subcategory = null) {
        $pdo = getDB();
        $saved_count = 0;
        
        if (!$response || !isset($response->Items->Item)) {
            return 0;
        }
        
        foreach ($response->Items->Item as $item) {
            $asin = (string)$item->ASIN;
            $title = (string)$item->ItemAttributes->Title;
            
            $stmt = $pdo->prepare("SELECT id FROM products WHERE asin = ?");
            $stmt->execute([$asin]);
            
            if (!$stmt->fetch()) {
                $price = null;
                if (isset($item->OfferSummary->LowestNewPrice->Amount)) {
                    $price = (float)$item->OfferSummary->LowestNewPrice->Amount / 100;
                } elseif (isset($item->ItemAttributes->ListPrice->Amount)) {
                    $price = (float)$item->ItemAttributes->ListPrice->Amount / 100;
                }
                
                $image_url = isset($item->LargeImage->URL) ? (string)$item->LargeImage->URL : '';
                $product_url = isset($item->DetailPageURL) ? (string)$item->DetailPageURL : '';
                $brand = isset($item->ItemAttributes->Brand) ? (string)$item->ItemAttributes->Brand : '';
                $rating = isset($item->CustomerReviews->AverageRating) ? (float)$item->CustomerReviews->AverageRating : null;
                $review_count = isset($item->CustomerReviews->TotalReviews) ? (int)$item->CustomerReviews->TotalReviews : 0;
                
                $features = [];
                $feature1 = $feature2 = $feature3 = '';
                if (isset($item->ItemAttributes->Feature)) {
                    $i = 0;
                    foreach ($item->ItemAttributes->Feature as $feature) {
                        if ($i < 3) {
                            ${"feature" . ($i + 1)} = (string)$feature;
                        }
                        $features[] = (string)$feature;
                        $i++;
                    }
                }
                $description = implode("\n", $features);
                
                $stmt = $pdo->prepare("
                    INSERT INTO products 
                    (asin, title, description, price, category, subcategory, image_url, product_url, brand, rating, review_count, feature_1, feature_2, feature_3) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$asin, $title, $description, $price, $category, $subcategory, $image_url, $product_url, $brand, $rating, $review_count, $feature1, $feature2, $feature3])) {
                    $saved_count++;
                }
            }
        }
        
        return $saved_count;
    }
}

// Auto-update products for all categories
function autoUpdateProducts() {
    $api = new AmazonAPI();
    $pdo = getDB();
    
    // Get all categories from database
    $stmt = $pdo->query("SELECT name, amazon_search_index FROM categories");
    $categories = $stmt->fetchAll();
    
    $total_saved = 0;
    
    foreach ($categories as $category) {
        $searchIndex = $category['amazon_search_index'] ?: 'All';
        $categoryName = $category['name'];
        
        error_log("Fetching products for category: {$categoryName} (Search Index: {$searchIndex})");
        
        // Fetch products using the search index
        $response = $api->getProductsByCategory($searchIndex, $categoryName);
        $saved = $api->saveProductsToDatabase($response, $categoryName);
        $total_saved += $saved;
        
        error_log("Saved {$saved} products for {$categoryName}");
        
        // Rate limiting
        sleep(1);
    }
    
    updateSetting('last_auto_update', date('Y-m-d H:i:s'));
    
    // Update total products count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
    $total = $stmt->fetch()['count'];
    updateSetting('total_products', $total);
    
    return $total_saved;
}

// Get products by category with pagination
function getProductsByCategory($category, $limit = 20, $offset = 0, $sort = 'rating') {
    $pdo = getDB();
    
    $orderBy = match($sort) {
        'price_low' => 'price ASC',
        'price_high' => 'price DESC',
        'newest' => 'last_updated DESC',
        default => 'rating DESC, review_count DESC'
    };
    
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE category = ? 
        ORDER BY {$orderBy} 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$category, $limit, $offset]);
    return $stmt->fetchAll();
}

// Get product count by category
function getProductCountByCategory($category) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category = ?");
    $stmt->execute([$category]);
    return $stmt->fetch()['count'];
}

function checkAndRunUpdates() {
    $last_product_update = getSetting('last_auto_update');
    $update_interval = (int)(getenv('AUTO_BLOG_INTERVAL_HOURS') ?: $_ENV['AUTO_BLOG_INTERVAL_HOURS']);
    
    if (!$last_product_update || (strtotime($last_product_update) + ($update_interval * 3600)) < time()) {
        return autoUpdateProducts();
    }
    
    return 0;
}
?>