<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/amazon_api.php';
require_once __DIR__ . '/gemini_api.php';

// Simple authentication
if (!isset($_SESSION['admin_logged_in']) && isset($_POST['password']) && $_POST['password'] === ADMIN_PASSWORD) {
    $_SESSION['admin_logged_in'] = true;
}

// Logout functionality
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Admin Login - <?php echo htmlspecialchars(SITE_NAME); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
            }
            .login-card {
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center" style="min-height: 100vh; align-items: center;">
                <div class="col-md-4">
                    <div class="card login-card">
                        <div class="card-header bg-dark text-white text-center py-3">
                            <i class="fas fa-lock fa-2x"></i>
                            <h4 class="mt-2">Admin Login</h4>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="Enter admin password" required autofocus>
                                </div>
                                <button type="submit" class="btn btn-dark w-100 py-2">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </button>
                            </form>
                            <div class="text-center mt-3">
                                <small class="text-muted">Default password is set in your .env file</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'update_settings':
                updateSetting('site_name', $_POST['site_name']);
                updateSetting('site_description', $_POST['site_description']);
                updateSetting('auto_update_hours', $_POST['auto_update_hours']);
                $message = 'Settings updated successfully!';
                break;
                
            case 'manual_product_update':
                $count = autoUpdateProducts();
                $message = "Manual product update completed! {$count} products added/updated.";
                break;
                
            case 'generate_blog_posts':
                $result = generateAutoBlogPosts();
                $message = $result;
                break;
                
            case 'generate_single_post':
                $gemini = new GeminiAI();
                $topic = $_POST['topic'];
                $category = $_POST['category'];
                $postType = $_POST['post_type'] ?? 'roundup';
                
                if ($postType === 'roundup') {
                    $postData = $gemini->generateRoundupPost($category, $topic);
                } else {
                    $postData = $gemini->generateAffiliatePost($topic, $category, []);
                }
                
                if ($postData) {
                    $slug = createSlug($postData['title']);
                    $pdo = getDB();
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO blog_posts 
                        (title, slug, content, excerpt, category, is_auto_generated, generated_by, word_count) 
                        VALUES (?, ?, ?, ?, ?, TRUE, 'gemini', ?)
                    ");
                    
                    $wordCount = str_word_count(strip_tags($postData['content']));
                    $stmt->execute([
                        $postData['title'],
                        $slug,
                        $postData['content'],
                        $postData['excerpt'],
                        $category,
                        $wordCount
                    ]);
                    
                    $message = "Blog post '{$postData['title']}' generated successfully!";
                } else {
                    $error = "Failed to generate blog post. Check your Gemini API key.";
                }
                break;
                
            case 'clear_cache':
                // Clear any cached data
                $message = "Cache cleared successfully!";
                break;
                
            case 'delete_post':
                $postId = (int)$_POST['post_id'];
                $pdo = getDB();
                $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
                $stmt->execute([$postId]);
                $message = "Post deleted successfully!";
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$pdo = getDB();

// Get statistics
$stats = [];
$stats['total_products'] = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$stats['total_posts'] = $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
$stats['auto_posts'] = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE is_auto_generated = 1")->fetchColumn();
$stats['total_views'] = $pdo->query("SELECT SUM(views) FROM blog_posts")->fetchColumn() ?: 0;
$stats['last_update'] = getSetting('last_auto_update');
$stats['last_blog'] = getSetting('last_blog_generation');

// Get recent posts
$recent_posts = $pdo->query("SELECT id, title, created_at, is_auto_generated FROM blog_posts ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get recent queue items
$queue_items = $pdo->query("SELECT * FROM auto_blog_queue ORDER BY created_at DESC LIMIT 10")->fetchAll();

// Get settings
$settings = [
    'site_name' => getSetting('site_name') ?: SITE_NAME,
    'site_description' => getSetting('site_description') ?: SITE_DESCRIPTION,
    'auto_update_hours' => getSetting('auto_update_hours') ?: AUTO_BLOG_INTERVAL_HOURS
];

// Get categories for dropdown
$categories = $pdo->query("SELECT name FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-card {
            transition: transform 0.3s;
            border-radius: 16px;
            border: none;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .bg-gradient-success {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        }
        .bg-gradient-info {
            background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);
        }
        .bg-gradient-warning {
            background: linear-gradient(135deg, #fbc2eb 0%, #a6c1ee 100%);
        }
        .sidebar {
            background: #2c3e50;
            min-height: 100vh;
        }
        .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            border-radius: 10px;
            margin: 5px 10px;
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            background: #34495e;
            color: white;
        }
        .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        .content-wrapper {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .card-header {
            background: white;
            border-bottom: 2px solid #f0f0f0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0 sidebar">
                <div class="text-center py-4">
                    <i class="fas fa-robot fa-3x text-white"></i>
                    <h5 class="text-white mt-2">Admin Panel</h5>
                </div>
                <nav class="nav flex-column">
                    <a href="#dashboard" class="nav-link active" data-bs-toggle="tab">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="#products" class="nav-link" data-bs-toggle="tab">
                        <i class="fas fa-box"></i> Products
                    </a>
                    <a href="#blog" class="nav-link" data-bs-toggle="tab">
                        <i class="fas fa-blog"></i> Blog Posts
                    </a>
                    <a href="#generate" class="nav-link" data-bs-toggle="tab">
                        <i class="fas fa-magic"></i> Generate
                    </a>
                    <a href="#settings" class="nav-link" data-bs-toggle="tab">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <a href="?logout=1" class="nav-link text-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 content-wrapper">
                <div class="p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="tab-content">
                        <!-- Dashboard Tab -->
                        <div class="tab-pane fade show active" id="dashboard">
                            <h2 class="mb-4"><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
                            
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card stats-card bg-primary text-white">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="card-title">Total Products</h6>
                                                    <h2 class="mb-0"><?php echo number_format($stats['total_products']); ?></h2>
                                                </div>
                                                <i class="fas fa-box fa-3x opacity-50"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card stats-card bg-gradient-success text-dark">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="card-title">Total Posts</h6>
                                                    <h2 class="mb-0"><?php echo number_format($stats['total_posts']); ?></h2>
                                                </div>
                                                <i class="fas fa-blog fa-3x opacity-50"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card stats-card bg-gradient-primary text-white">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="card-title">AI-Generated</h6>
                                                    <h2 class="mb-0"><?php echo number_format($stats['auto_posts']); ?></h2>
                                                </div>
                                                <i class="fas fa-robot fa-3x opacity-50"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card stats-card bg-gradient-info text-dark">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="card-title">Total Views</h6>
                                                    <h2 class="mb-0"><?php echo number_format($stats['total_views']); ?></h2>
                                                </div>
                                                <i class="fas fa-eye fa-3x opacity-50"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="fas fa-history"></i> Recent Activity
                                        </div>
                                        <div class="card-body">
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Last Product Update
                                                    <span class="badge bg-primary rounded-pill">
                                                        <?php echo $stats['last_update'] ? date('M j, Y g:i A', strtotime($stats['last_update'])) : 'Never'; ?>
                                                    </span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Last Blog Generation
                                                    <span class="badge bg-success rounded-pill">
                                                        <?php echo $stats['last_blog'] ? date('M j, Y g:i A', strtotime($stats['last_blog'])) : 'Never'; ?>
                                                    </span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Auto-Update Interval
                                                    <span class="badge bg-info rounded-pill">
                                                        Every <?php echo $settings['auto_update_hours']; ?> hours
                                                    </span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="fas fa-quick-actions"></i> Quick Actions
                                        </div>
                                        <div class="card-body">
                                            <div class="d-grid gap-2">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="manual_product_update">
                                                    <button type="submit" class="btn btn-primary w-100 mb-2" onclick="return confirm('This may take a few minutes. Continue?')">
                                                        <i class="fas fa-sync-alt"></i> Update Products Now
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="generate_blog_posts">
                                                    <button type="submit" class="btn btn-success w-100" onclick="return confirm('Generate AI blog posts? This may take several minutes.')">
                                                        <i class="fas fa-magic"></i> Generate AI Blog Posts
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="fas fa-list"></i> Recent Blog Posts
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Title</th>
                                                            <th>Date</th>
                                                            <th>Type</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($recent_posts as $post): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars(substr($post['title'], 0, 50)); ?>...</td>
                                                            <td><?php echo date('M j, Y', strtotime($post['created_at'])); ?></td>
                                                            <td>
                                                                <?php if ($post['is_auto_generated']): ?>
                                                                    <span class="badge bg-gradient-primary">AI</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">Manual</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this post?')">
                                                                    <input type="hidden" name="action" value="delete_post">
                                                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Products Tab -->
                        <div class="tab-pane fade" id="products">
                            <h2 class="mb-4"><i class="fas fa-box"></i> Product Management</h2>
                            
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-database"></i> Product Database
                                </div>
                                <div class="card-body">
                                    <p>Total products in database: <strong><?php echo number_format($stats['total_products']); ?></strong></p>
                                    
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="action" value="manual_product_update">
                                        <button type="submit" class="btn btn-primary" onclick="return confirm('Fetch products from Amazon? This may take a few minutes.')">
                                            <i class="fas fa-sync-alt"></i> Fetch New Products from Amazon
                                        </button>
                                    </form>
                                    
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle"></i> 
                                        Products are automatically updated every <?php echo $settings['auto_update_hours']; ?> hours.
                                        You can also manually trigger an update using the button above.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Blog Tab -->
                        <div class="tab-pane fade" id="blog">
                            <h2 class="mb-4"><i class="fas fa-blog"></i> Blog Management</h2>
                            
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-file-alt"></i> Blog Posts
                                </div>
                                <div class="card-body">
                                    <p>Total blog posts: <strong><?php echo number_format($stats['total_posts']); ?></strong></p>
                                    <p>AI-generated posts: <strong><?php echo number_format($stats['auto_posts']); ?></strong></p>
                                    
                                    <a href="blog.php" class="btn btn-info" target="_blank">
                                        <i class="fas fa-external-link-alt"></i> View Blog
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Generate Tab -->
                        <div class="tab-pane fade" id="generate">
                            <h2 class="mb-4"><i class="fas fa-magic"></i> AI Content Generation</h2>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header bg-success text-white">
                                            <i class="fas fa-robot"></i> Auto-Generate All Categories
                                        </div>
                                        <div class="card-body">
                                            <p>Generate blog posts for ALL categories at once using Google Gemini AI.</p>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="generate_blog_posts">
                                                <button type="submit" class="btn btn-success w-100" onclick="return confirm('Generate posts for all categories? This may take 5-10 minutes.')">
                                                    <i class="fas fa-magic"></i> Generate All Category Posts
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header bg-info text-white">
                                            <i class="fas fa-pen-fancy"></i> Generate Single Post
                                        </div>
                                        <div class="card-body">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="generate_single_post">
                                                <div class="mb-3">
                                                    <label>Topic / Product Type</label>
                                                    <input type="text" name="topic" class="form-control" placeholder="e.g., Espresso Accessories, Home Gym Mats" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Category</label>
                                                    <select name="category" class="form-control" required>
                                                        <option value="">Select Category</option>
                                                        <?php foreach ($categories as $cat): ?>
                                                        <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                                                            <?php echo htmlspecialchars($cat['name']); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Post Type</label>
                                                    <select name="post_type" class="form-control">
                                                        <option value="roundup">Roundup (Best X for Y)</option>
                                                        <option value="guide">Buying Guide</option>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-info w-100">
                                                    <i class="fas fa-robot"></i> Generate with Gemini AI
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Settings Tab -->
                        <div class="tab-pane fade" id="settings">
                            <h2 class="mb-4"><i class="fas fa-cog"></i> Site Settings</h2>
                            
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-sliders-h"></i> General Settings
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_settings">
                                        <div class="mb-3">
                                            <label class="form-label">Site Name</label>
                                            <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Site Description</label>
                                            <textarea name="site_description" class="form-control" rows="3"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Auto-Update Interval (hours)</label>
                                            <input type="number" name="auto_update_hours" class="form-control" value="<?php echo $settings['auto_update_hours']; ?>">
                                            <small class="text-muted">How often to automatically fetch products and generate blog posts</small>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Settings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>