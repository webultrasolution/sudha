<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("
    SELECT p.*, c.name as client_name 
    FROM proposals p
    JOIN partners c ON p.client_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$proposal = $stmt->fetch();

if (!$proposal) die("Proposal not found.");

$items = $pdo->prepare("
    SELECT pi.*, s.site_code, s.location, s.city as site_city, s.type as site_type, s.width, s.height, s.light_type,
    (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1) as image
    FROM proposal_items pi
    JOIN sites s ON pi.site_id = s.id
    WHERE pi.proposal_id = ?
");
$items->execute([$id]);
$items = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Presentation - <?php echo $proposal['campaign_name']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #0d9488; --dark: #0f172a; }
        body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; font-family: 'Outfit', sans-serif; background: #000; color: #fff; }
        .slide { height: 100vh; width: 100vw; display: none; position: relative; }
        .slide.active { display: flex; flex-direction: column; }
        
        .slide-content { display: flex; height: 100%; }
        .image-side { flex: 1.5; background: #1e293b; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .image-side img { max-width: 100%; max-height: 100%; object-fit: contain; }
        
        .info-side { flex: 1; padding: 60px; background: #fff; color: var(--dark); display: flex; flex-direction: column; justify-content: center; }
        .info-side h1 { font-size: 3rem; margin: 0 0 1rem 0; color: var(--primary); }
        .info-side h2 { font-size: 1.5rem; margin: 0 0 2rem 0; color: #64748b; }
        
        .spec-item { margin-bottom: 20px; display: flex; gap: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; }
        .spec-label { font-weight: 800; color: #94a3b8; text-transform: uppercase; font-size: 0.8rem; width: 120px; }
        .spec-val { font-weight: 600; font-size: 1.2rem; }

        .nav-btn { position: fixed; bottom: 40px; right: 40px; display: flex; gap: 20px; z-index: 100; }
        .btn { background: var(--primary); color: #fff; border: none; padding: 15px 30px; border-radius: 50px; cursor: pointer; font-weight: 800; box-shadow: 0 10px 30px rgba(13, 148, 136, 0.4); transition: transform 0.2s; }
        .btn:hover { transform: translateY(-3px); }

        .slide-number { position: fixed; bottom: 40px; left: 40px; font-size: 1.2rem; font-weight: 800; color: #94a3b8; }

        /* Intro Slide */
        .intro-slide { justify-content: center; align-items: center; text-align: center; background: linear-gradient(135deg, var(--dark) 0%, #1e293b 100%); }
        .intro-slide h1 { font-size: 5rem; margin: 0; letter-spacing: -2px; }
        .intro-slide p { font-size: 1.5rem; opacity: 0.7; margin-top: 10px; }
    </style>
</head>
<body>

    <!-- Intro Slide -->
    <div class="slide intro-slide active">
        <img src="../../assets/img/LOGO.png" style="height: 100px; width: auto; margin-bottom: 40px;" alt="Logo">
        <h1 style="color: var(--primary);"><?php echo $proposal['campaign_name']; ?></h1>
        <p>Advertising Proposal Prepared For <strong><?php echo $proposal['client_name']; ?></strong></p>
        <p style="margin-top: 40px; font-size: 1rem; color: #94a3b8;">PROPOSAL #<?php echo $proposal['proposal_number']; ?> • <?php echo date('F Y'); ?></p>
        <button class="btn" style="margin-top: 50px;" onclick="nextSlide()">Start Presentation <i class="fas fa-play" style="margin-left: 10px;"></i></button>
    </div>

    <!-- Asset Slides -->
    <?php foreach ($items as $item): ?>
    <div class="slide">
        <div class="slide-content">
            <div class="image-side">
                <?php if ($item['image']): ?>
                    <img src="../../uploads/sites/<?php echo $item['image']; ?>" alt="Site">
                <?php else: ?>
                    <div style="font-size: 2rem; color: #64748b;">No Image Available</div>
                <?php endif; ?>
                <div style="position: absolute; top: 30px; left: 30px; background: var(--primary); color: #fff; padding: 10px 20px; border-radius: 50px; font-weight: 800;">
                    <?php echo $item['site_type']; ?>
                </div>
            </div>
            <div class="info-side">
                <h1><?php echo $item['site_city']; ?></h1>
                <h2><?php echo $item['location']; ?></h2>

                <div class="spec-item">
                    <div class="spec-label">Site Code</div>
                    <div class="spec-val"><?php echo $item['site_code']; ?></div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Dimensions</div>
                    <div class="spec-val"><?php echo $item['width']; ?>' (W) x <?php echo $item['height']; ?>' (H)</div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Lighting</div>
                    <div class="spec-val"><?php echo $item['light_type']; ?></div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Media Type</div>
                    <div class="spec-val"><?php echo $item['site_type']; ?></div>
                </div>

                <div style="margin-top: auto; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 5px solid var(--primary);">
                    <div style="font-size: 0.8rem; color: #64748b; font-weight: 800; text-transform: uppercase;">Proposal Rate</div>
                    <div style="font-size: 2rem; font-weight: 800; color: var(--dark);">₹<?php echo number_format($item['amount'], 2); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="slide-number" id="slideNum">1 / <?php echo count($items) + 1; ?></div>

    <div class="nav-btn">
        <button class="btn" style="background: #334155;" onclick="prevSlide()"><i class="fas fa-chevron-left"></i></button>
        <button class="btn" onclick="nextSlide()"><i class="fas fa-chevron-right"></i></button>
    </div>

    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');
        const slideNum = document.getElementById('slideNum');

        function updateSlide() {
            slides.forEach((s, i) => {
                s.classList.toggle('active', i === currentSlide);
            });
            slideNum.innerText = `${currentSlide + 1} / ${slides.length}`;
        }

        function nextSlide() {
            if (currentSlide < slides.length - 1) {
                currentSlide++;
                updateSlide();
            }
        }

        function prevSlide() {
            if (currentSlide > 0) {
                currentSlide--;
                updateSlide();
            }
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === ' ') nextSlide();
            if (e.key === 'ArrowLeft') prevSlide();
        });
    </script>
</body>
</html>
