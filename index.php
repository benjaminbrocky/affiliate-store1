<!-- Add after hero section, before products -->

<!-- Featured Categories Grid -->
<div class="container mb-5">
    <h2 class="text-center mb-4"><i class="fas fa-grid"></i> Shop by Category</h2>
    <div class="row">
        <?php 
        $categories = getAllCategories(true);
        foreach (array_chunk($categories, 4) as $chunk): 
        ?>
        <div class="col-md-3">
            <div class="list-group">
                <?php foreach ($chunk as $cat): ?>
                <a href="category.php?slug=<?php echo $cat['slug']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <?php echo htmlspecialchars($cat['name']); ?>
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>