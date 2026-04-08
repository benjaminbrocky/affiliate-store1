<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/amazon_api.php';

$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
$pdo = getDB();

// Get category by slug
$stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    header('Location: index.php');
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * 20;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'rating';

// Get products for this category
$products = getProductsByCategory($category['name'], 20, $offset, $sort);
$total_products = getProductCountByCategory($category['name']);
$total_pages = ceil($total_products / 20);

// Get subcategories
$stmt = $pdo->prepare("SELECT * FROM subcategories WHERE category_id = ? ORDER BY name");
$stmt->execute([$category['id']]);
$subcategories = $stmt->fetchAll();

// Get recent blog posts for this category
$stmt = $pdo->prepare("
    SELECT * FROM blog_posts 
    WHERE category = ? 
    ORDER BY created_at DESC 
    LIMIT 3
");
$stmt->execute([$category['name']]);
$recent_posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['name']); ?> - Best Accessories & Reviews | <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($category['description']); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .category-header {
            background: linear-gradient(135deg, #232f3e 0%, #37475a 100%);
            color: white;
            padding: 60px 0 40px;
            margin-bottom: 40px;
        }
        .product-card {
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 30px;
            border-radius: 12px;
            overflow: hidden;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .product-image {
            height: 200px;
            object-fit: contain;
            padding: 20px;
            background: #f8f9fa;
        }
        .price {
            color: #B12704;
            font-size: 1.3rem;
            font-weight: bold;
        }
        .rating {
            color: #ffa41c;
        }
        .affiliate-btn {
            background: #ff9900;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .affiliate-btn:hover {
            background: #cc7a00;
            color: white;
        }
        .subcategory-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .subcategory-badge:hover {
            background: #ff9900;
            color: white;
        }
        .blog-preview {
            background: #f8f9fa;
            border-radius: 12px;
            transition: transform 0.3s;
        }
        .blog-preview:hover {
            transform: translateY(-3px);
        }
        footer {
            background: #232f3e;
            color: white;
            padding: 40px 0;
            margin-top: 50px;
        }
        .sort-select {
            width: auto;
            display: inline-block;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-robot"></i> <?php echo htmlspecialchars(SITE_NAME); ?>
            </a>
            <a href="index.php" class="btn btn-outline-light">← Home</a>
        </div>
    </nav>

    <div class="category-header">
        <div class="container">
            <h1><?php echo htmlspecialchars($category['name']); ?></h1>
            <p class="lead"><?php echo htmlspecialchars($category['description']); ?></p>
            
            <?php if (!empty($subcategories)): ?>
            <div class="mt-4">
                <strong>Browse by subcategory:</strong>
                <div class="mt-2">
                    <?php foreach ($subcategories as $sub): ?>
                    <a href="subcategory.php?slug=<?php echo $sub['slug']; ?>" class="subcategory-badge me-2 mb-2">
                        <?php echo htmlspecialchars($sub['name']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <h2><i class="fas fa-tag"></i> Featured <?php echo htmlspecialchars($category['name']); ?></h2>
            <div>
                <label>Sort by:</label>
                <select class="form-select sort-select ms-2" onchange="window.location.href=this.value">
                    <option value="?sort=rating&page=1" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Top Rated</option>
                    <option value="?sort=price_low&page=1" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="?sort=price_high&page=1" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="?sort=newest&page=1" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                </select>
            </div>
        </div>
        
        <div class="row">
            <?php if (empty($products)): ?>
                <div class="col-12 text-center">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No products found yet. Run product update in admin panel.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <div class="col-md-3 col-sm-6">
                    <div class="card product-card">
                        <?php if ($product['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                             class="card-img-top product-image" 
                             alt="<?php echo htmlspecialchars($product['title']); ?>"
                             loading="lazy">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars(substr($product['title'], 0, 60)); ?>...</h5>
                            
                            <?php if ($product['rating']): ?>
                            <div class="rating mb-2">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <?php if($i <= $product['rating']): ?>
                                        <i class="fas fa-star"></i>
                                    <?php elseif($i - 0.5 <= $product['rating']): ?>
                                        <i class="fas fa-star-half-alt"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <span class="text-muted">(<?php echo number_format($product['review_count']); ?>)</span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($product['price']): ?>
                            <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <a href="product.php?asin=<?php echo $product['asin']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-info-circle"></i> Details
                                </a>
                                <a href="<?php echo htmlspecialchars($product['product_url']); ?>" 
                                   class="btn affiliate-btn btn-sm" 
                                   target="_blank" 
                                   rel="nofollow sponsored">
                                    <i class="fab fa-amazon"></i> Buy Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page-1; ?>&sort=<?php echo $sort; ?>">Previous</a>
                </li>
                <?php for($i = 1; $i <= min(10, $total_pages); $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?>&sort=<?php echo $sort; ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php if (!empty($recent_posts)): ?>
        <div class="mt-5">
            <h3><i class="fas fa-blog"></i> Latest Guides for <?php echo htmlspecialchars($category['name']); ?></h3>
            <div class="row mt-3">
                <?php foreach ($recent_posts as $post): ?>
                <div class="col-md-4 mb-4">
                    <div class="blog-preview p-3">
                        <h5><?php echo htmlspecialchars($post['title']); ?></h5>
                        <p class="small text-muted"><?php echo date('M j, Y', strtotime($post['created_at'])); ?></p>
                        <a href="blog-post.php?slug=<?php echo $post['slug']; ?>" class="btn btn-sm btn-outline-primary">
                            Read Guide <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer>
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?>. All rights reserved.</p>
            <p class="small">As an Amazon Associate, we earn from qualifying purchases.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>