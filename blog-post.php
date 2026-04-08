<?php
require_once __DIR__ . '/config.php';

$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = ?");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: blog.php');
    exit;
}

// Increment view count
$stmt = $pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?");
$stmt->execute([$post['id']]);

// Get linked products
$products = [];
if ($post['product_asins']) {
    $asins = explode(',', $post['product_asins']);
    $placeholders = str_repeat('?,', count($asins) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM products WHERE asin IN ($placeholders)");
    $stmt->execute($asins);
    $products = $stmt->fetchAll();
}

// Get related posts
$stmt = $pdo->prepare("
    SELECT * FROM blog_posts 
    WHERE category = ? AND id != ? 
    ORDER BY created_at DESC 
    LIMIT 3
");
$stmt->execute([$post['category'], $post['id']]);
$related_posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(substr($post['excerpt'] ?? strip_tags($post['content']), 0, 160)); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($post['category']); ?>, accessories, buying guide, reviews">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .blog-content {
            font-size: 1.1rem;
            line-height: 1.8;
        }
        .blog-content h1 {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
        }
        .blog-content h2 {
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        .blog-content h3 {
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        .blog-content ul, .blog-content ol {
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
        }
        .blog-content li {
            margin-bottom: 0.5rem;
        }
        .blog-content p {
            margin-bottom: 1.2rem;
        }
        .blog-content table {
            width: 100%;
            margin-bottom: 1.5rem;
            border-collapse: collapse;
        }
        .blog-content th, .blog-content td {
            border: 1px solid #dee2e6;
            padding: 0.75rem;
        }
        .blog-content th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .blog-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }
        .affiliate-link {
            display: inline-block;
            background: #ff9900;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            margin: 10px 0;
            transition: background 0.3s;
        }
        .affiliate-link:hover {
            background: #cc7a00;
            color: white;
        }
        .badge-ai {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 0.8rem;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-block;
        }
        .table-of-contents {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .table-of-contents ul {
            margin-bottom: 0;
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
        .share-buttons {
            margin: 2rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 12px;
        }
        .share-btn {
            display: inline-block;
            padding: 8px 16px;
            margin-right: 10px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
        }
        .share-twitter { background: #1DA1F2; }
        .share-facebook { background: #4267B2; }
        .share-pinterest { background: #BD081C; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-robot"></i> <?php echo htmlspecialchars(SITE_NAME); ?>
            </a>
            <a href="blog.php" class="btn btn-outline-light">← Back to Blog</a>
        </div>
    </nav>

    <div class="container mt-4">
        <article>
            <header class="mb-4">
                <div class="mb-3">
                    <?php if ($post['is_auto_generated']): ?>
                    <span class="badge-ai">
                        <i class="fas fa-robot"></i> Generated by Google Gemini AI
                    </span>
                    <?php endif; ?>
                </div>
                <h1><?php echo htmlspecialchars($post['title']); ?></h1>
                <div class="text-muted mt-3">
                    <i class="far fa-calendar-alt"></i> <?php echo date('F j, Y', strtotime($post['created_at'])); ?>
                    &nbsp;|&nbsp;
                    <i class="far fa-clock"></i> <?php echo ceil($post['word_count'] / 200); ?> min read
                    &nbsp;|&nbsp;
                    <i class="far fa-eye"></i> <?php echo number_format($post['views']); ?> views
                    &nbsp;|&nbsp;
                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($post['category']); ?>
                </div>
            </header>
            
            <?php if ($post['featured_image']): ?>
            <div class="mb-4">
                <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" 
                     class="img-fluid rounded w-100" 
                     style="max-height: 500px; object-fit: cover;"
                     alt="<?php echo htmlspecialchars($post['title']); ?>">
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="blog-content">
                        <?php echo $post['content']; ?>
                    </div>
                    
                    <div class="share-buttons text-center">
                        <p><strong>Share this guide:</strong></p>
                        <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($post['title']); ?>&url=<?php echo urlencode(SITE_URL . '/blog-post.php?slug=' . $post['slug']); ?>" 
                           class="share-btn share-twitter" target="_blank">
                            <i class="fab fa-twitter"></i> Twitter
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/blog-post.php?slug=' . $post['slug']); ?>" 
                           class="share-btn share-facebook" target="_blank">
                            <i class="fab fa-facebook"></i> Facebook
                        </a>
                        <a href="https://pinterest.com/pin/create/button/?url=<?php echo urlencode(SITE_URL . '/blog-post.php?slug=' . $post['slug']); ?>&description=<?php echo urlencode($post['title']); ?>" 
                           class="share-btn share-pinterest" target="_blank">
                            <i class="fab fa-pinterest"></i> Pinterest
                        </a>
                    </div>
                    
                    <div class="alert alert-warning mt-4">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Disclosure:</strong> As an Amazon Associate, we earn from qualifying purchases. 
                        Prices and availability are subject to change.
                    </div>
                </div>
            </div>
        </article>
        
        <?php if (!empty($related_posts)): ?>
        <div class="mt-5">
            <h3 class="mb-4">You Might Also Like</h3>
            <div class="row">
                <?php foreach ($related_posts as $related): ?>
                <div class="col-md-4 mb-4">
                    <div class="card related-card h-100">
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($related['title']); ?></h6>
                            <small class="text-muted"><?php echo date('M j, Y', strtotime($related['created_at'])); ?></small>
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <a href="blog-post.php?slug=<?php echo $related['slug']; ?>" class="btn btn-sm btn-outline-primary">Read More</a>
                        </div>
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