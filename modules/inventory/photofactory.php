<?php
$activePage = 'photofactory';
$pageTitle = 'Photofactory - Site Gallery';
include_once __DIR__ . '/../../includes/header.php';

// Fetch all sites with photos
$sites = $pdo->query("SELECT site_code, name, location, image_url FROM sites WHERE image_url IS NOT NULL")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Proof of Display Gallery</h2>
        <div class="search-box">
            <input type="text" placeholder="Filter by Site ID..." class="p-input">
        </div>
    </div>

    <div class="photo-grid">
        <?php foreach ($sites as $s): ?>
        <div class="photo-card">
            <div class="photo-container">
                <img src="<?php echo BASE_URL . $s['image_url']; ?>" alt="Site Photo" onerror="this.src='https://via.placeholder.com/400x300?text=No+Image'">
            </div>
            <div class="photo-info">
                <strong><?php echo $s['site_code']; ?></strong>
                <p><?php echo $s['name']; ?></p>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($sites)): ?>
            <div style="grid-column: span 4; text-align: center; padding: 5rem; color: var(--secondary);">
                <i class="fas fa-camera" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.2;"></i>
                <p>No photos uploaded to Photofactory yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.photo-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }
.photo-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; transition: transform 0.2s; cursor: pointer; }
.photo-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }
.photo-container { height: 180px; background: #f8fafc; overflow: hidden; }
.photo-container img { width: 100%; height: 100%; object-fit: cover; }
.photo-info { padding: 1rem; }
.photo-info strong { font-size: 0.9rem; color: var(--primary); }
.photo-info p { font-size: 0.75rem; color: var(--secondary); margin-top: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.p-input { padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
