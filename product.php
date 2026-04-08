<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/amazon_api.php';

$asin = isset($_GET['asin']) ? $_GET['asin'] : '';
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM products WHERE asin = ?");
$stmt->execute([$asin]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit;
}

// Get related products from same category
$stmt = $pdo->prepare("
    SELECT * FROM products 
    WHERE category = ? AND asin != ? 
    ORDER BY rating DESC 
    LIMIT 4
");
$stmt->execute([$product['category'], $product['asin']]);
$related_products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['title']); ?> - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <meta name="description" content="Buy <?php echo htmlspecialchars($product['title']); ?> at the best price. Check price, reviews, and specifications on Amazon.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .product-image {
            max-height: 400px;
            object-fit: contain;
            width: 100%;
        }
        .price {
            color: #B12704;
            font-size: 2rem;
            font-weight: bold;
        }
        .rating {
            color: #ffa41c;
            font-size: 1.2rem;
        }
        .affiliate-btn {
            background: #ff9900;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.2rem;
            transition: background 0.3s;
        }
        .affiliate-btn:hover {
            background: #cc7a00;
            color: white;
        }
        .feature-list {
            list-style: none;
            padding-left: 0;
        }
        .feature-list li {
            margin-bottom: 10px;
            padding-left: 25px;
            position: relative;
        }
        .feature-list li:before {
            content: "✓";
            color: #28a745;
            position: absolute;
            left: 0;
            font-weight: bold;
        }
        .related-card {
            transition: transform 0.3s;
            border-radius: 12px;
            overflow: hidden;
            height: 100%;
        }
        .related-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
            <a href="index.php" class="btn btn-outline-light">← Back to Store</a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-5">
                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                     class="product-image img-fluid rounded" 
                     alt="<?php echo htmlspecialchars($product['title']); ?>">
            </div>
            <div class="col-md-7">
                <h1><?php echo htmlspecialchars($product['title']); ?></h1>
                
                <?php if ($product['brand']): ?>
                <p class="text-muted">Brand: <?php echo htmlspecialchars($product['brand']); ?></p>
                <?php endif; ?>
                
                <?php if ($product['rating']): ?>
                <div class="rating mb-3">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <?php if($i <= $product['rating']): ?>
                            <i class="fas fa-star"></i>
                        <?php elseif($i - 0.5 <= $product['rating']): ?>
                            <i class="fas fa-star-half-alt"></i>
                        <?php else: ?>
                            <i class="far fa-star"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <span class="text-muted ms-2"><?php echo number_format($product['review_count']); ?> customer reviews</span>
                </div>
                <?php endif; ?>
                
                <?php if ($product['price']): ?>
                <div class="price mt-3 mb-3">
                    $<?php echo number_format($product['price'], 2); ?>
                </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="<?php echo htmlspecialchars($product['product_url']); ?>" 
                       class="btn affiliate-btn btn-lg w-100" 
                       target="_blank" 
                       rel="nofollow sponsored">
                        <i class="fab fa-amazon"></i> Buy on Amazon
                    </a>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <small>
                        <i class="fas fa-info-circle"></i> 
                        As an Amazon Associate, we earn from qualifying purchases. 
                        Price and availability are accurate as of the date/time indicated.
                    </small>
                </div>
            </div>
        </div>
        
        <?php if ($product['description']): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3>Product Description</h3>
                <div class="card">
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($product['feature_1'] || $product['feature_2'] || $product['feature_3']): ?>
        <div class="row mt-4">
            <div class="col-12">
                <h3>Key Features</h3>
                <ul class="feature-list">
                    <?php if ($product['feature_1']): ?>
                    <li><?php echo htmlspecialchars($product['feature_1']); ?></li>
                    <?php endif; ?>
                    <?php if ($product['feature_2']): ?>
                    <li><?php echo htmlspecialchars($product['feature_2']); ?></li>
                    <?php endif; ?>
                    <?php if ($product['feature_3']): ?>
                    <li><?php echo htmlspecialchars($product['feature_3']); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($related_products)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3>Related Products You Might Like</h3>
                <div class="row mt-3">
                    <?php foreach ($related_products as $related): ?>
                    <div class="col-md-3 col-sm-6 mb-4">
                        <div class="card related-card h-100">
                            <img src="<?php echo htmlspecialchars($related['image_url']); ?>" 
                                 class="card-img-top" 
                                 style="height: 120px; object-fit: contain; padding: 10px;"
                                 alt="<?php echo htmlspecialchars($related['title']); ?>">
                            <div class="card-body">
                                <h6 class="card-title"><?php echo htmlspecialchars(substr($related['title'], 0, 40)); ?>...</h6>
                                <?php if ($related['price']): ?>
                                <div class="text-success fw-bold">$<?php echo number_format($related['price'], 2); ?></div>
                                <?php endif; ?>
                                <a href="product.php?asin=<?php echo $related['asin']; ?>" class="btn btn-sm btn-outline-primary mt-2 w-100">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
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