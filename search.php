<?php
require_once __DIR__ . '/config.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$pdo = getDB();

$products = [];
$blog_posts = [];

if ($query) {
    // Search products
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE title LIKE ? OR brand LIKE ? OR description LIKE ? 
        LIMIT 20
    ");
    $searchTerm = "%{$query}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $products = $stmt->fetchAll();
    
    // Search blog posts
    $stmt = $pdo->prepare("
        SELECT * FROM blog_posts 
        WHERE title LIKE ? OR content LIKE ? OR category LIKE ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $blog_posts = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results for "<?php echo htmlspecialchars($query); ?>" - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .result-card {
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .result-card:hover {
            transform: translateX(5px);
        }
        footer {
            background: #232f3e;
            color: white;
            padding: 40px 0;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-robot"></i> <?php echo htmlspecialchars(SITE_NAME); ?>
            </a>
            <a href="index.php" class="btn btn-outline-light">← Back to Home</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Search Results for "<?php echo htmlspecialchars($query); ?>"</h1>
        
        <?php if (empty($products) && empty($blog_posts)): ?>
            <div class="alert alert-info mt-4">
                <i class="fas fa-search"></i> No results found for "<?php echo htmlspecialchars($query); ?>". Try different keywords.
            </div>
        <?php else: ?>
            
            <?php if (!empty($blog_posts)): ?>
            <div class="mt-4">
                <h3><i class="fas fa-blog"></i> Blog Posts (<?php echo count($blog_posts); ?>)</h3>
                <?php foreach ($blog_posts as $post): ?>
                <div class="card result-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="blog-post.php?slug=<?php echo $post['slug']; ?>">
                                <?php echo htmlspecialchars($post['title']); ?>
                            </a>
                        </h5>
                        <p class="card-text text-muted">
                            <?php echo htmlspecialchars(substr(strip_tags($post['content']), 0, 200)); ?>...
                        </p>
                        <small class="text-muted">
                            <i class="far fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                            &nbsp;|&nbsp;
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($post['category']); ?>
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($products)): ?>
            <div class="mt-4">
                <h3><i class="fas fa-box"></i> Products (<?php echo count($products); ?>)</h3>
                <div class="row">
                    <?php foreach ($products as $product): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                 class="card-img-top" 
                                 style="height: 150px; object-fit: contain; padding: 10px;"
                                 alt="<?php echo htmlspecialchars($product['title']); ?>">
                            <div class="card-body">
                                <h6 class="card-title"><?php echo htmlspecialchars(substr($product['title'], 0, 50)); ?>...</h6>
                                <?php if ($product['price']): ?>
                                <div class="text-success fw-bold">$<?php echo number_format($product['price'], 2); ?></div>
                                <?php endif; ?>
                                <a href="product.php?asin=<?php echo $product['asin']; ?>" class="btn btn-primary btn-sm mt-2 w-100">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>

    <footer>
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>