<?php
require_once __DIR__ . '/config.php';

class GeminiAI {
    private $apiKey;
    private $model;
    private $apiUrl;
    
    public function __construct() {
        $this->apiKey = GEMINI_API_KEY;
        $this->model = GEMINI_MODEL;
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
    }
    
    public function generateRoundupPost($category, $productType, $subcategory = null) {
        $context = $subcategory ? "in the {$subcategory} subcategory" : "";
        
        $prompt = "Create a comprehensive 'Best {$productType} for {$category}' buying guide {$context}.
        
Include:
- An engaging, SEO-optimized title like 'The 10 Best {$productType} for {$category} in 2026'
- Introduction explaining what to look for and why these accessories matter
- List 10 products with:
  * Product name
  * Key features (3-5 bullet points)
  * Pros and cons
  * Who it's best for (beginner, enthusiast, professional, budget-conscious)
  * Price range
- Comparison table summarizing all 10 products (price, rating, key feature, best for)
- Detailed buying guide with 5-7 factors to consider when purchasing
- FAQ section with 5 common questions and answers
- Conclusion with final recommendation and call-to-action

Write in HTML format with proper heading structure (H1, H2, H3).
Target word count: 2000-3000 words.
Make it SEO-optimized, conversion-focused, and helpful to readers.
Include natural calls-to-action to check prices on Amazon.";
        
        $response = $this->callGemini($prompt);
        
        if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return [
                'title' => "Best {$productType} for {$category}",
                'content' => $response['candidates'][0]['content']['parts'][0]['text'],
                'excerpt' => "Discover the top {$productType} for your {$category} needs. Expert reviews, buying guide, and recommendations."
            ];
        }
        return false;
    }
    
    public function generateAccessoryGuide($heroProduct, $category) {
        $prompt = "Write a detailed accessory guide for owners of {$heroProduct} in the {$category} niche.
        
Structure:
1. Title: 'Essential Accessories for Your {$heroProduct}: Complete Setup Guide'
2. Introduction about why accessories matter for this specific product
3. 7-10 must-have accessories with:
   * What it does
   * Why you need it
   * Price range
   * Top recommendation
4. Optional upgrades section (3-5 items)
5. Maintenance and care accessories
6. Budget vs. premium recommendations
7. FAQ about compatibility and installation
8. Conclusion

Target 1500-2000 words. Write persuasively. Include affiliate link placeholders.";
        
        $response = $this->callGemini($prompt);
        return $response ? $response['candidates'][0]['content']['parts'][0]['text'] : false;
    }
    
    public function generateComparisonPost($product1, $product2, $category) {
        $prompt = "Write a detailed comparison between {$product1} and {$product2} for {$category}.
        
Structure:
1. Title: '{$product1} vs {$product2}: Which is Better for {$category}?'
2. Introduction explaining both products and who they're for
3. Side-by-side comparison table with: Price, Features, Build Quality, Warranty, Customer Ratings, Ease of Use
4. Detailed breakdown of each product (pros/cons, best use cases)
5. Head-to-head comparison by category (price, performance, durability, value)
6. Winner by use case (Budget pick, Premium pick, Best value, Beginner-friendly)
7. FAQ (5 questions)
8. Final verdict

Target 1500-2000 words. Help readers make an informed decision.";
        
        $response = $this->callGemini($prompt);
        return $response ? $response['candidates'][0]['content']['parts'][0]['text'] : false;
    }
    
    public function generateMaintenanceGuide($category, $productType) {
        $prompt = "Create a comprehensive maintenance and care guide for {$productType} owners in the {$category} niche.
        
Include:
- Title: 'How to Maintain Your {$productType}: Complete Care Guide'
- Why regular maintenance matters
- Daily/weekly maintenance checklist
- Monthly maintenance tasks
- Seasonal/deep cleaning guide
- Essential cleaning products and tools (with affiliate links)
- Signs your {$productType} needs professional service
- Troubleshooting common problems
- When to replace vs. repair
- FAQ about maintenance

Make it practical, actionable, and helpful. Target 1500-2000 words.";
        
        $response = $this->callGemini($prompt);
        return $response ? $response['candidates'][0]['content']['parts'][0]['text'] : false;
    }
    
    private function callGemini($prompt) {
        if (!$this->apiKey) {
            error_log("Gemini API key is not set");
            return false;
        }
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
            ]
        ];
        
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 55);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Gemini API error: HTTP {$httpCode} - " . substr($response, 0, 500));
            return false;
        }
        
        return json_decode($response, true);
    }
}

function generateAutoBlogPosts() {
    if (!AUTO_BLOG_ENABLED) {
        return "Auto-blogging is disabled";
    }
    
    $gemini = new GeminiAI();
    $pdo = getDB();
    
    // Get all categories
    $stmt = $pdo->query("SELECT name, slug FROM categories WHERE featured = 1 ORDER BY display_order");
    $categories = $stmt->fetchAll();
    
    $postsPerCategory = (int)(getenv('POSTS_PER_CATEGORY') ?: $_ENV['POSTS_PER_CATEGORY']);
    $productTypes = ['Accessories', 'Products', 'Gadgets', 'Tools', 'Essentials', 'Gear', 'Equipment', 'Upgrades', 'Must-Haves'];
    $postTypes = ['roundup', 'buying_guide', 'accessory_guide'];
    
    $postsGenerated = 0;
    
    foreach ($categories as $category) {
        $categoryName = $category['name'];
        
        for ($i = 0; $i < $postsPerCategory; $i++) {
            $productType = $productTypes[array_rand($productTypes)];
            $postType = $postTypes[array_rand($postTypes)];
            
            $postData = $gemini->generateRoundupPost($categoryName, $productType);
            
            if ($postData) {
                $slug = createSlug($postData['title']);
                
                $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ?");
                $stmt->execute([$slug]);
                
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO blog_posts 
                        (title, slug, content, excerpt, category, post_type, is_auto_generated, generated_by, word_count) 
                        VALUES (?, ?, ?, ?, ?, ?, TRUE, 'gemini', ?)
                    ");
                    
                    $wordCount = str_word_count(strip_tags($postData['content']));
                    $stmt->execute([
                        $postData['title'],
                        $slug,
                        $postData['content'],
                        $postData['excerpt'],
                        $categoryName,
                        $postType,
                        $wordCount
                    ]);
                    
                    $postsGenerated++;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO auto_blog_queue (category, keyword, status, post_id, post_type) 
                        VALUES (?, ?, 'completed', ?, ?)
                    ");
                    $stmt->execute([$categoryName, $productType, $pdo->lastInsertId(), $postType]);
                }
            }
            
            sleep(2);
        }
    }
    
    updateSetting('last_blog_generation', date('Y-m-d H:i:s'));
    $total = (int)getSetting('total_auto_posts');
    updateSetting('total_auto_posts', $total + $postsGenerated);
    
    return "Generated {$postsGenerated} blog posts across " . count($categories) . " categories";
}
?>