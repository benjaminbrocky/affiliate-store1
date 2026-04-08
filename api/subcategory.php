<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/amazon_api.php';

$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
$pdo = getDB();

// Get subcategory
$stmt = $pdo->prepare("
    SELECT s.*, c.name as category_name, c.slug as category_slug 
    FROM subcategories s
    JOIN categories c ON s.category_id = c.id
    WHERE s.slug = ?
");
$stmt->execute([$slug]);
$subcategory = $stmt->fetch();

if (!$subcategory) {
    header('Location: index.php');
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * 20;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'rating';

// Get products for this subcategory
$stmt = $pdo->prepare("
    SELECT * FROM products 
    WHERE category = ? AND subcategory = ?
    ORDER BY rating DESC
    LIMIT 20 OFFSET ?
");
$stmt->execute([$subcategory['category_name'], $subcategory['name'], $offset]);
$products = $stmt->fetchAll();

// Get total count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM products 
    WHERE category = ? AND subcategory = ?
");
$stmt->execute([$subcategory['category_name'], $subcategory['name']]);
$total_products = $stmt->fetch()['count'];
$total_pages = ceil($total_products / 20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subcategory['name']); ?> - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .product-card {
            transition: transform 0.3s;
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
            font-weight: bold;
        }
        .affiliate-btn {
            background: #ff9900;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
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
            <a href="category.php?slug=<?php echo $subcategory['category_slug']; ?>" class="btn btn-outline-light">
                ← Back to <?php echo htmlspecialchars($subcategory['category_name']); ?>
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <h1><?php echo htmlspecialchars($subcategory['name']); ?></h1>
        <p class="lead"><?php echo htmlspecialchars($subcategory['description']); ?></p>
        
        <div class="row mt-4">
            <?php if (empty($products)): ?>
                <div class="col-12 text-center">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No products found in this subcategory yet.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <div class="col-md-3 col-sm-6">
                    <div class="card product-card">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                             class="card-img-top product-image" 
                             alt="<?php echo htmlspecialchars($product['title']); ?>">
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars(substr($product['title'], 0, 50)); ?>...</h6>
                            <?php if ($product['price']): ?>
                            <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                            <?php endif; ?>
                            <div class="mt-2">
                                <a href="product.php?asin=<?php echo $product['asin']; ?>" class="btn btn-outline-primary btn-sm w-100 mb-2">
                                    Details
                                </a>
                                <a href="<?php echo htmlspecialchars($product['product_url']); ?>" 
                                   class="btn affiliate-btn btn-sm w-100" 
                                   target="_blank">
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
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <footer>
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?>. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>