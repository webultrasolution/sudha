<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("
    SELECT p.*, c.name as client_name, c.email as client_email 
    FROM proposals p
    JOIN partners c ON p.client_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$proposal = $stmt->fetch();

if (!$proposal) die("Proposal not found.");

// Fetch items for the proposal (One per site, using selected or primary image)
$items = $pdo->prepare("
    SELECT pi.*, COALESCE(pi.custom_site_name, s.name) as site_name, s.site_code, COALESCE(pi.custom_location, s.location) as location, s.city as site_city, s.type as site_type, s.width, s.height, s.light_type,
    COALESCE(NULLIF(TRIM(pi.selected_image), ''), (SELECT filename FROM site_images WHERE site_id = s.id ORDER BY is_primary DESC, id ASC LIMIT 1)) as image
    FROM proposal_items pi
    JOIN sites s ON pi.site_id = s.id
    WHERE pi.proposal_id = ?
    ORDER BY pi.sort_order ASC, pi.id ASC
");
$items->execute([$id]);
$slides_data = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Plan - <?php echo $proposal['campaign_name']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --primary: #dc2626; 
            --accent: #0d9488;
            --dark: #0f172a;
            --slate: #64748b;
        }
        
        * { box-sizing: border-box; }
        
        body, html { 
            margin: 0; padding: 0; height: 100%; width: 100%; 
            font-family: 'Outfit', sans-serif; 
            background: #f1f5f9; 
            color: var(--dark); 
            overflow: hidden; 
        }

        .slide { 
            height: 100vh; 
            width: 100vw; 
            display: none; 
            position: relative; 
            background: white;
            padding: 40px;
            flex-direction: column;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(1.02); }
            to { opacity: 1; transform: scale(1); }
        }

        .slide.active { display: flex; }
        
        /* Intro Slide */
        .intro-slide { 
            justify-content: center; 
            align-items: center; 
            text-align: center; 
            background: #ffffff; 
            color: var(--dark);
            padding: 0;
        }
        .intro-slide .overlay {
            position: absolute;
            inset: 0;
            background: url('../../assets/img/pattern.png');
            opacity: 0.05;
            pointer-events: none;
        }
        .intro-content { position: relative; z-index: 10; max-width: 900px; }
        .intro-slide h1 { font-size: 5rem; margin: 0; font-weight: 900; letter-spacing: -2px; line-height: 1; }
        .intro-slide p { font-size: 1.8rem; margin: 20px 0; font-weight: 300; color: var(--slate); }
        .intro-divider { width: 100px; height: 6px; background: var(--accent); margin: 40px auto; border-radius: 10px; }

        /* Header on Asset Slides */
        .slide-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }
        .slide-header img { height: 50px; width: auto; }
        .campaign-tag { font-weight: 800; font-size: 1.2rem; color: var(--slate); text-transform: uppercase; letter-spacing: 1px; }

        /* Image Display */
        .image-container {
            flex: 1;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f8fafc;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.12);
            position: relative;
            border: 1px solid #e2e8f0;
        }
        .image-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.5s ease;
        }
        .no-image {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            color: #94a3b8;
        }
        .no-image i { font-size: 5rem; opacity: 0.3; }

        /* Caption Styling - MATCHING THE PDF */
        .caption-bar {
            padding: 30px 0 10px 0;
            text-align: center;
            width: 100%;
        }
        .caption-text {
            font-size: 2.2rem;
            font-weight: 800;
            color: #dc2626; /* Red color from PDF */
            text-decoration: underline;
            text-underline-offset: 10px;
            text-decoration-thickness: 3px;
            letter-spacing: -0.5px;
            line-height: 1.4;
        }

        /* Controls */
        .nav-controls {
            position: fixed;
            bottom: 40px;
            right: 40px;
            display: flex;
            gap: 15px;
            z-index: 1000;
        }
        .control-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            border: none;
            color: var(--dark);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .control-btn:hover {
            transform: translateY(-5px);
            background: var(--accent);
            color: white;
        }
        .control-btn.primary {
            background: var(--accent);
            color: white;
            width: 180px;
            border-radius: 50px;
            font-weight: 800;
            gap: 10px;
        }

        .slide-info {
            position: fixed;
            bottom: 40px;
            left: 40px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            color: white;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .slide-info span { opacity: 0.6; font-weight: 400; }

        @media print {
            body, html { 
                overflow: visible !important; 
                height: 100% !important; 
                width: 100% !important; 
                background: white !important;
            }
            .nav-controls, .slide-info { display: none !important; }
            .slide { 
                display: flex !important; 
                page-break-after: always !important; 
                break-after: page !important;
                height: 100% !important; 
                width: 100% !important; 
                box-sizing: border-box !important;
                margin: 0 !important;
                padding: 40px !important;
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                animation: none !important;
                opacity: 1 !important;
                visibility: visible !important;
            }
        }
        @page {
            size: landscape;
            margin: 0;
        }
    </style>
</head>
<body>

    <!-- Intro Slide -->
    <div class="slide intro-slide active">
        <div class="overlay"></div>
        <div class="intro-content">
            <img class="page-logo-img" src="../../assets/img/LOGO.png" style="height: 120px; width: auto; margin-bottom: 40px;" alt="Logo">
            <h1><?php echo $proposal['campaign_name']; ?></h1>
            <div class="intro-divider"></div>
            <p>PREPARED FOR</p>
            <h2 style="font-size: 3rem; margin: 0; font-weight: 800;"><?php echo $proposal['client_name']; ?></h2>
            <div style="margin-top: 60px; font-weight: 700; color: var(--slate); font-size: 1.1rem; text-transform: uppercase; letter-spacing: 2px;">
                PROPOSAL #<?php echo $proposal['proposal_number']; ?> &bull; <?php echo date('F Y'); ?>
            </div>
            <button class="control-btn primary" style="margin: 50px auto 0;" onclick="nextSlide()">
                START VIEWING <i class="fas fa-play"></i>
            </button>
        </div>
    </div>

    <!-- Asset Slides -->
    <?php 
    $total_slides = count($slides_data);
    foreach ($slides_data as $index => $item): 
        $dim = intval($item['width']) . "X" . intval($item['height']);
        $amt = number_format($item['amount'], 0, '.', '');
        $caption = "{$item['site_city']}, {$item['site_name']}. {$dim}. {$item['light_type']} ( {$item['site_type']} ) . Rs.{$amt}/Per Month";
    ?>
    <div class="slide">
        <div class="slide-header">
            <img class="page-logo-img" src="../../assets/img/LOGO.png" alt="Logo">
            <div class="campaign-tag"><?php echo $proposal['campaign_name']; ?></div>
        </div>

        <div class="image-container">
            <?php 
            $images = [];
            if (!empty($item['image'])) {
                if (preg_match_all('/(.+?\.(?:jpe?g|png|webp|gif|bmp|avif))(?:\s*,\s*|$)/i', $item['image'], $imgMatches)) {
                    $images = array_map('trim', $imgMatches[1]);
                } else {
                    $images = array_filter(array_map('trim', explode(',', $item['image'])));
                }
            }
            $imgCount = count($images);
            if ($imgCount > 0): 
                $cols = 1;
                if ($imgCount >= 10) $cols = 4;
                elseif ($imgCount >= 5) $cols = 3;
                elseif ($imgCount >= 2) $cols = 2;
                
                $rows = ceil($imgCount / $cols);
            ?>
                <div style="position: absolute; top: 0; left: 0; display: grid; grid-template-columns: repeat(<?php echo $cols; ?>, 1fr); grid-template-rows: repeat(<?php echo $rows; ?>, 1fr); width: 100%; height: 100%; gap: 15px; padding: 15px; box-sizing: border-box;">
                    <?php foreach ($images as $img): ?>
                        <img class="lazy-slide-img" data-idx="<?php echo $index; ?>" data-img="<?php echo htmlspecialchars(trim($img)); ?>" src="../../uploads/sites/<?php echo htmlspecialchars(trim($img)); ?>" alt="Site" style="width: 100%; height: 100%; object-fit: <?php echo $imgCount === 1 ? 'contain' : 'cover'; ?>; border-radius: 12px;">
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-image">
                    <i class="far fa-image"></i>
                    <span>NO IMAGE AVAILABLE FOR THIS SITE</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="caption-bar">
            <div class="caption-text"><?php echo $caption; ?></div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="slide-info" id="slideInfo">
        SLIDE 1 <span>/ <?php echo $total_slides + 1; ?></span>
    </div>

    <div class="nav-controls">
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <button class="control-btn" title="Email Presentation" onclick="emailPresentation()"><i class="fas fa-envelope" style="color: #0d9488;"></i></button>
        <?php endif; ?>
        <button class="control-btn" title="Print / Save PDF" onclick="printPDF()"><i class="fas fa-file-pdf" style="color: #e11d48;"></i></button>
        <button class="control-btn" title="Download PPTX" onclick="downloadPPTX()"><i class="fas fa-file-powerpoint" style="color: #ea580c;"></i></button>
        <button class="control-btn" onclick="prevSlide()"><i class="fas fa-chevron-left"></i></button>
        <button class="control-btn" onclick="nextSlide()"><i class="fas fa-chevron-right"></i></button>
        <button class="control-btn" title="Full Screen" onclick="toggleFullScreen()"><i class="fas fa-expand"></i></button>
    </div>

    <!-- PPTX Generation Library -->
    <script src="https://cdn.jsdelivr.net/gh/gitbrent/pptxgenjs@3.12.0/dist/pptxgen.bundle.js"></script>

    <script>
        // Data for PPTX
        const campaignName = <?php echo json_encode($proposal['campaign_name']); ?>;
        const clientName = <?php echo json_encode($proposal['client_name']); ?>;
        const clientEmail = <?php echo json_encode($proposal['client_email'] ?? ''); ?>;
        const proposalNumber = <?php echo json_encode($proposal['proposal_number']); ?>;
        const currentDate = <?php echo json_encode(date('F Y')); ?>;
        const slidesData = <?php echo json_encode($slides_data); ?>;

        // Dynamic compression to keep PPTX export size under 5 MB while maintaining crystal clear HD photo quality
        const totalSlidesCount = Math.max(1, slidesData.length);
        const targetPerSlideKb = Math.floor(4200 / totalSlidesCount);

        let maxDim = 1280;
        let imgQuality = 0.75;

        if (targetPerSlideKb >= 250) {
            maxDim = 1600;
            imgQuality = 0.85;
        } else if (targetPerSlideKb >= 130) {
            maxDim = 1400;
            imgQuality = 0.80;
        } else if (targetPerSlideKb >= 65) {
            maxDim = 1280;
            imgQuality = 0.75;
        } else {
            maxDim = 1024;
            imgQuality = 0.70;
        }

        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');
        const slideInfo = document.getElementById('slideInfo');

        function updateSlide() {
            slides.forEach((s, i) => {
                s.classList.toggle('active', i === currentSlide);
            });
            slideInfo.innerHTML = `SLIDE ${currentSlide + 1} <span>/ ${slides.length}</span>`;
            
            // Auto-focus the slide for keyboard events
            window.scrollTo(0, 0);
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

        function toggleFullScreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }

        function parseImageFilenames(imageStr) {
            if (!imageStr) return [];
            const regex = /(.+?\.(?:jpe?g|png|webp|gif|bmp|avif))(?:\s*,\s*|$)/gi;
            const matches = [];
            let match;
            while ((match = regex.exec(imageStr)) !== null) {
                if (match[1] && match[1].trim()) {
                    matches.push(match[1].trim());
                }
            }
            if (matches.length > 0) return matches;
            return imageStr.split(',').map(s => s.trim()).filter(Boolean);
        }

        async function getBase64Image(url) {
            try {
                const response = await fetch(encodeURI(url));
                if (!response.ok) return null;
                const contentType = response.headers.get('content-type') || '';
                if (!contentType.startsWith('image/')) return null;
                
                const blob = await response.blob();
                return new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onloadend = () => {
                        const base64Url = reader.result;
                        
                        const img = new Image();
                        img.onload = () => {
                            const canvas = document.createElement('canvas');
                            let width = img.width;
                            let height = img.height;
                            
                            // Resize image dimensions if they exceed maxDim limit
                            if (width > maxDim || height > maxDim) {
                                if (width > height) {
                                    height = Math.round((height * maxDim) / width);
                                    width = maxDim;
                                } else {
                                    width = Math.round((width * maxDim) / height);
                                    height = maxDim;
                                }
                            }
                            
                            canvas.width = width;
                            canvas.height = height;
                            
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, width, height);
                            
                            // Keep PNG format for transparent logo, compress other images as JPEG to target < 5 MB
                            const isLogo = url.includes('LOGO.png');
                            const format = isLogo ? 'image/png' : 'image/jpeg';
                            const compressedDataUrl = canvas.toDataURL(format, isLogo ? undefined : imgQuality);
                            console.log(`Processed: ${width}x${height}, Format: ${format}, Quality: ${imgQuality}, URL size: ${Math.round(compressedDataUrl.length / 1024)} KB`);
                            resolve(compressedDataUrl);
                        };
                        img.onerror = () => {
                            // Fallback to original base64 URL only if canvas fails
                            resolve(base64Url);
                        };
                        img.src = base64Url;
                    };
                    reader.onerror = () => {
                        resolve(null);
                    };
                    reader.readAsDataURL(blob);
                });
            } catch (err) {
                console.error("Failed to load image: " + url, err);
                return null;
            }
        }

        const base64Cache = {};
        let imageLoadingPromise = null;

        // Initialize parallel loading and compression immediately on page load
        document.addEventListener('DOMContentLoaded', () => {
            const fetchTasks = [];
            fetchTasks.push({
                key: 'logo',
                url: '../../assets/img/LOGO.png'
            });

            // Gather all images directly from slidesData so no image is ever skipped
            slidesData.forEach((item, idx) => {
                if (item.image) {
                    const imgs = parseImageFilenames(item.image);
                    imgs.forEach(file => {
                        if (file) {
                            fetchTasks.push({
                                key: `${idx}_${file}`,
                                url: '../../uploads/sites/' + file
                            });
                        }
                    });
                }
            });

            // Start parallel loading and resizing
            imageLoadingPromise = Promise.all(
                fetchTasks.map(task => 
                    getBase64Image(task.url).then(base64 => {
                        if (base64) {
                            base64Cache[task.key] = base64;
                        }
                    })
                )
            ).then(() => {
                // Update logo images on page
                const logos = document.querySelectorAll('.page-logo-img');
                logos.forEach(logo => {
                    if (base64Cache['logo']) {
                        logo.src = base64Cache['logo'];
                    }
                });
                console.log("All presentation images compressed and cached successfully!");
            });
        });

        // Listen to print completion/cancel to restore high-res images
        window.addEventListener('afterprint', () => {
            const lazyImages = document.querySelectorAll('.lazy-slide-img');
            lazyImages.forEach(img => {
                const file = img.getAttribute('data-img');
                if (file) {
                    img.src = '../../uploads/sites/' + file;
                }
            });
            console.log("Restored original high-resolution images to DOM after printing.");
        });

        async function printPDF() {
            // Check if compression is already finished
            let isLoaded = false;
            if (imageLoadingPromise) {
                await Promise.race([
                    imageLoadingPromise.then(() => { isLoaded = true; }),
                    new Promise(resolve => setTimeout(resolve, 100))
                ]);

                if (!isLoaded) {
                    Swal.fire({
                        title: 'Preparing PDF',
                        html: 'Optimizing and compressing images for PDF export...',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    await imageLoadingPromise;
                    Swal.close();
                }
            }

            // Temporarily replace all DOM images with the compressed base64 versions to prevent print memory crash
            const lazyImages = document.querySelectorAll('.lazy-slide-img');
            lazyImages.forEach(img => {
                const idx = img.getAttribute('data-idx');
                const file = img.getAttribute('data-img');
                const cacheKey = `${idx}_${file}`;
                if (base64Cache[cacheKey]) {
                    img.src = base64Cache[cacheKey];
                }
            });

            // Trigger browser print dialog
            window.print();
        }

        function buildPPTX() {
            let pptx = new PptxGenJS();
            pptx.layout = 'LAYOUT_WIDE'; // 16:9

            // Intro Slide
            let intro = pptx.addSlide();
            intro.background = { color: 'FFFFFF' };
            
            // Add Logo
            try {
                if (base64Cache['logo']) {
                    intro.addImage({ 
                        data: base64Cache['logo'], 
                        x: '35%', y: '15%', w: '30%', h: '15%', 
                        sizing: { type: 'contain' } 
                    });
                }
            } catch (e) { console.warn("Logo not found for PPTX", e); }
            
            intro.addText(campaignName || "Media Plan", { 
                x: 0, y: '40%', w: '100%', 
                align: 'center', fontSize: 44, color: '0D9488', bold: true, fontFace: 'Arial' 
            });
            
            intro.addText('PREPARED FOR', { 
                x: 0, y: '55%', w: '100%', 
                align: 'center', fontSize: 18, color: '64748B', fontFace: 'Arial' 
            });
            
            intro.addText(clientName || "Client", { 
                x: 0, y: '62%', w: '100%', 
                align: 'center', fontSize: 32, color: '0F172A', bold: true, fontFace: 'Arial' 
            });
            
            intro.addText(`PROPOSAL #${proposalNumber} • ${currentDate}`, { 
                x: 0, y: '75%', w: '100%', 
                align: 'center', fontSize: 14, color: '64748B', fontFace: 'Arial' 
            });

            // Asset Slides
            for (let idx = 0; idx < slidesData.length; idx++) {
                const item = slidesData[idx];
                let slide = pptx.addSlide();
                
                // Header Logo
                try {
                    if (base64Cache['logo']) {
                        slide.addImage({ data: base64Cache['logo'], x: 0.4, y: 0.2, w: 1.5, h: 0.5, sizing: { type: 'contain' } });
                    }
                } catch (e) {}
                
                slide.addText(campaignName || "", { x: '70%', y: 0.3, w: '25%', align: 'right', fontSize: 12, color: '64748B', bold: true, fontFace: 'Arial' });

                // Image Area (Grid layout on a single slide)
                if (item.image) {
                    const imgs = parseImageFilenames(item.image);
                    const resolvedImages = [];
                    for (let img of imgs) {
                        const cacheKey = `${idx}_${img}`;
                        if (base64Cache[cacheKey]) {
                            resolvedImages.push(base64Cache[cacheKey]);
                        }
                    }
                    
                    const count = resolvedImages.length;
                    if (count > 0) {
                        let cols = 1;
                        if (count >= 10) cols = 4;
                        else if (count >= 5) cols = 3;
                        else if (count >= 2) cols = 2;
                        
                        const rows = Math.ceil(count / cols);
                        const gap = 0.2;
                        const totalW = 12.33;
                        const totalH = 5.25;
                        const imgW = (totalW - (cols - 1) * gap) / cols;
                        const imgH = (totalH - (rows - 1) * gap) / rows;
                        
                        for (let i = 0; i < count; i++) {
                            const c = i % cols;
                            const r = Math.floor(i / cols);
                            const xCoord = 0.5 + c * (imgW + gap);
                            const yCoord = 1.0 + r * (imgH + gap);
                            slide.addImage({
                                data: resolvedImages[i],
                                x: xCoord, y: yCoord, w: imgW, h: imgH,
                                sizing: { type: count === 1 ? 'contain' : 'cover' }
                            });
                        }
                    } else {
                        slide.addText('NO IMAGE AVAILABLE', { x: 0, y: '40%', w: '100%', align: 'center', fontSize: 24, color: '94A3B8', fontFace: 'Arial' });
                    }
                } else {
                    slide.addText('NO IMAGE AVAILABLE', { x: 0, y: '40%', w: '100%', align: 'center', fontSize: 24, color: '94A3B8', fontFace: 'Arial' });
                }

                // Caption - Matching the Bold Red Underlined style
                const city = item.site_city || "";
                const siteName = item.site_name || "";
                const type = item.site_type || "";
                const light = item.light_type || "";
                const w = parseInt(item.width) || 0;
                const h = parseInt(item.height) || 0;
                const amtVal = parseFloat(item.amount) || 0;
                let dim = w + 'X' + h;
                let amtFormatted = new Intl.NumberFormat('en-IN').format(amtVal);
                let caption = `${city}, ${siteName}. ${dim}. ${light} ( ${type} ) . Rs.${amtFormatted}/Per Month`;
                
                slide.addText(caption, { 
                    x: 0.5, y: '88%', w: 12.33, h: 0.8,
                    align: 'center', 
                    fontSize: 18, 
                    color: 'DC2626', 
                    bold: true,
                    underline: true, // Simplified underline for better compatibility
                    fontFace: 'Arial'
                });
            }

            return pptx;
        }

        async function downloadPPTX() {
            // Show loading state
            const btn = document.querySelector('button[title="Download PPTX"]');
            const originalIcon = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            try {
                // Wait for background image loading and compression to complete
                let isLoaded = false;
                if (imageLoadingPromise) {
                    await Promise.race([
                        imageLoadingPromise.then(() => { isLoaded = true; }),
                        new Promise(resolve => setTimeout(resolve, 100))
                    ]);

                    if (!isLoaded) {
                        Swal.fire({
                            title: 'Preparing Presentation',
                            html: 'Optimizing and compressing images...',
                            allowOutsideClick: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        await imageLoadingPromise;
                    }
                }

                // Update SweetAlert to assembly phase
                Swal.fire({
                    title: 'Generating PPTX',
                    html: 'Assembling slides and writing file...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                let pptx = buildPPTX();

                const fileName = `MediaPlan_${(campaignName || "Export").replace(/[^a-z0-9]/gi, '_')}.pptx`;
                pptx.writeFile({ fileName: fileName })
                    .then(() => {
                        Swal.close();
                        btn.innerHTML = originalIcon;
                        btn.disabled = false;
                    })
                    .catch(err => {
                        console.error("Write error:", err);
                        Swal.fire('Export Error', 'Failed to save PPTX: ' + err.message, 'error');
                        btn.innerHTML = originalIcon;
                        btn.disabled = false;
                    });

            } catch (err) {
                console.error("PPTX Generation Error:", err);
                Swal.fire('Export Error', 'Failed to generate PPTX: ' + err.message, 'error');
                btn.innerHTML = originalIcon;
                btn.disabled = false;
            }
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'PageDown') nextSlide();
            if (e.key === 'ArrowLeft' || e.key === 'PageUp') prevSlide();
            if (e.key === 'f') toggleFullScreen();
        });

        // Touch support
        let touchstartX = 0;
        let touchendX = 0;
        
        document.addEventListener('touchstart', e => {
            touchstartX = e.changedTouches[0].screenX;
        });

        document.addEventListener('touchend', e => {
            touchendX = e.changedTouches[0].screenX;
            handleGesture();
        });

        function handleGesture() {
            if (touchendX < touchstartX - 50) nextSlide();
            if (touchendX > touchstartX + 50) prevSlide();
        }

        function emailPresentation() {
            const defaultEmail = clientEmail || '';
            Swal.fire({
                title: 'Email Presentation',
                html: `
                    <div style="text-align: left; font-family: 'Outfit', sans-serif;">
                        <label style="font-weight: 600; color: #475569; display: block; margin-bottom: 6px; font-size: 14px;">Recipient Email:</label>
                        <input type="email" id="swal-email" class="swal2-input" placeholder="recipient@example.com" value="${defaultEmail}" style="margin: 0 0 15px 0; width: 100%; box-sizing: border-box; height: 42px; font-size: 14px; border-radius: 8px;">
                        
                        <label style="font-weight: 600; color: #475569; display: block; margin-bottom: 6px; font-size: 14px;">Custom Message (Optional):</label>
                        <textarea id="swal-message" class="swal2-textarea" placeholder="Dear Client, please find the proposal attached..." style="margin: 0; width: 100%; height: 120px; box-sizing: border-box; font-size: 14px; border-radius: 8px; padding: 10px;"></textarea>
                    </div>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Send Email',
                confirmButtonColor: '#0d9488',
                showLoaderOnConfirm: true,
                preConfirm: async () => {
                    const email = document.getElementById('swal-email').value.trim();
                    const customMessage = document.getElementById('swal-message').value.trim();
                    
                    if (!email || !/\S+@\S+\.\S+/.test(email)) {
                        Swal.showValidationMessage('Please enter a valid email address');
                        return false;
                    }
                    
                    try {
                        Swal.resetValidationMessage();
                        
                        // 1. Wait for parallel images compression to finish
                        if (imageLoadingPromise) {
                            await imageLoadingPromise;
                        }
                        
                        // 2. Build PPTX in browser memory
                        const pptx = buildPPTX();
                        
                        // 3. Generate Base64 string of the file
                        const base64Data = await pptx.write({ outputType: 'base64' });
                        const fileName = `MediaPlan_${(campaignName || "Export").replace(/[^a-z0-9]/gi, '_')}.pptx`;
                        
                        // 4. Send email request to backend
                        const formData = new FormData();
                        formData.append('id', '<?php echo $id; ?>');
                        formData.append('email', email);
                        formData.append('message', customMessage);
                        formData.append('ppt_file', base64Data);
                        formData.append('file_name', fileName);
                        
                        const response = await fetch('ajax_send_ppt_email.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();
                        if (!data.success) {
                            throw new Error(data.message || 'Failed to send email');
                        }
                        return { email: email };
                    } catch (error) {
                        Swal.showValidationMessage(`Error: ${error.message}`);
                        return false;
                    }
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sent!',
                        text: `Presentation with PPTX attachment has been emailed successfully to ${result.value.email}.`,
                        confirmButtonColor: '#0d9488'
                    });
                }
            });
        }
    </script>
</body>
</html>
