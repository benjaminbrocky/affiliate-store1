<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/amazon_api.php';

$pdo = getDB();
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * 24;
$category = isset($_GET['category']) ? $_GET['category'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'rating';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT * FROM products";
$params = [];

if ($category) {
    $query .= " WHERE category = ?";
    $params[] = $category;
} elseif ($search) {
    $query .= " WHERE title LIKE ? OR brand LIKE ?";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

switch ($sort) {
    case 'price_low':
        $query .= " ORDER BY price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY price DESC";
        break;
    case 'rating':
    default:
        $query .= " ORDER BY rating DESC, review_count DESC";
        break;
}

$query .= " LIMIT 24 OFFSET ?";
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get total count
$countQuery = str_replace("SELECT *", "SELECT COUNT(*) as count", $query);
$countQuery = preg_replace('/ ORDER BY .*$/', '', $countQuery);
$countQuery = preg_replace('/ LIMIT .*$/', '', $countQuery);

$stmt = $pdo->prepare($countQuery);
$stmt->execute(array_slice($params, 0, -1));
$total_products = $stmt->fetch()['count'];
$total_pages = ceil($total_products / 24);

// Get categories for filter
$stmt = $pdo->query("SELECT name, slug FROM categories ORDER BY name");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Products - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
            height: 180px;
            object-fit: contain;
            padding: 20px;
            background: #f8f9fa;
        }
        .price {
            color: #B12704;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .rating {
            color: #ffa41c;
        }
        .affiliate-btn {
            background: #ff9900;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            transition: background 0.3s;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .affiliate-btn:hover {
            background: #cc7a00;
            color: white;
        }
        .filter-sidebar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            position: sticky;
            top: 80px;
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-robot"></i> <?php echo htmlspecialchars(SITE_NAME); ?>
            </a>
            <a href="index.php" class="btn btn-outline-light">← Back to Home</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Sidebar Filters -->
            <div class="col-md-3">
                <div class="filter-sidebar">
                    <h5><i class="fas fa-filter"></i> Filters</h5>
                    <hr>
                    
                    <h6>Categories</h6>
                    <div class="list-group mb-4">
                        <a href="products.php" class="list-group-item list-group-item-action <?php echo !$category ? 'active' : ''; ?>">
                            All Products
                        </a>
                        <?php foreach ($categories as $cat): ?>
                        <a href="products.php?category=<?php echo urlencode($cat['name']); ?>&sort=<?php echo $sort; ?>" 
                           class="list-group-item list-group-item-action <?php echo $category == $cat['name'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <h6>Sort By</h6>
                    <div class="btn-group-vertical w-100">
                        <a href="?sort=rating&category=<?php echo urlencode($category); ?>" class="btn btn-outline-secondary btn-sm <?php echo $sort == 'rating' ? 'active' : ''; ?>">
                            <i class="fas fa-star"></i> Top Rated
                        </a>
                        <a href="?sort=price_low&category=<?php echo urlencode($category); ?>" class="btn btn-outline-secondary btn-sm <?php echo $sort == 'price_low' ? 'active' : ''; ?>">
                            <i class="fas fa-dollar-sign"></i> Price: Low to High
                        </a>
                        <a href="?sort=price_high&category=<?php echo urlencode($category); ?>" class="btn btn-outline-secondary btn-sm <?php echo $sort == 'price_high' ? 'active' : ''; ?>">
                            <i class="fas fa-dollar-sign"></i> Price: High to Low
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="col-md-9">
                <h1 class="mb-3">All Products</h1>
                <p class="text-muted mb-4">Showing <?php echo count($products); ?> of <?php echo number_format($total_products); ?> products</p>
                
                <div class="row">
                    <?php if (empty($products)): ?>
                        <div class="col-12 text-center">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No products found.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="card product-card">
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     class="card-img-top product-image" 
                                     alt="<?php echo htmlspecialchars($product['title']); ?>"
                                     loading="lazy">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars(substr($product['title'], 0, 50)); ?>...</h6>
                                    
                                    <?php if ($product['rating']): ?>
                                    <div class="rating mb-2 small">
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
                                    
                                    <div class="mt-2">
                                        <a href="product.php?asin=<?php echo $product['asin']; ?>" class="btn btn-outline-primary btn-sm w-100 mb-2">
                                            View Details
                                        </a>
                                        <a href="<?php echo htmlspecialchars($product['product_url']); ?>" 
                                           class="btn affiliate-btn btn-sm w-100" 
                                           target="_blank" 
                                           rel="nofollow sponsored">
                                            <i class="fab fa-amazon"></i> Buy on Amazon
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
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo $sort; ?>">Previous</a>
                        </li>
                        <?php for($i = 1; $i <= min(10, $total_pages); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo $sort; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo $sort; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
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