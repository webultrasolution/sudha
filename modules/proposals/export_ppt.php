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

// Fetch all images for each site in the proposal
$items = $pdo->prepare("
    SELECT pi.*, s.name as site_name, s.site_code, s.location, s.city as site_city, s.type as site_type, s.width, s.height, s.light_type,
    si.filename as image
    FROM proposal_items pi
    JOIN sites s ON pi.site_id = s.id
    LEFT JOIN site_images si ON s.id = si.site_id
    WHERE pi.proposal_id = ?
    ORDER BY pi.id ASC, si.is_primary DESC
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
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: white;
            padding: 0;
        }
        .intro-slide .overlay {
            position: absolute;
            inset: 0;
            background: url('../../assets/img/pattern.png');
            opacity: 0.1;
            pointer-events: none;
        }
        .intro-content { position: relative; z-index: 10; max-width: 900px; }
        .intro-slide h1 { font-size: 5rem; margin: 0; font-weight: 900; letter-spacing: -2px; line-height: 1; }
        .intro-slide p { font-size: 1.8rem; margin: 20px 0; font-weight: 300; color: #94a3b8; }
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
            .nav-controls, .slide-info { display: none; }
            .slide { display: flex !important; page-break-after: always; height: 100vh; width: 100vw; }
        }
    </style>
</head>
<body>

    <!-- Intro Slide -->
    <div class="slide intro-slide active">
        <div class="overlay"></div>
        <div class="intro-content">
            <img src="../../assets/img/LOGO.png" style="height: 120px; width: auto; margin-bottom: 40px; filter: brightness(0) invert(1);" alt="Logo">
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
        // Format the caption as in the PDF: City Location.WidthXHeight. LightType (MediaType). Rs.Rate PM
        $dim = intval($item['width']) . "X" . intval($item['height']);
        $rate = number_format($item['sale_rate'], 0, '.', '');
        $caption = "{$item['site_city']}, {$item['site_name']}. {$dim}. {$item['light_type']} ( {$item['site_type']} ) . Rs.{$rate} PM";
    ?>
    <div class="slide">
        <div class="slide-header">
            <img src="../../assets/img/LOGO.png" alt="Logo">
            <div class="campaign-tag"><?php echo $proposal['campaign_name']; ?></div>
        </div>

        <div class="image-container">
            <?php if ($item['image']): ?>
                <img src="../../uploads/sites/<?php echo $item['image']; ?>" alt="Site">
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
        <button class="control-btn" title="Download PPTX" onclick="downloadPPTX()"><i class="fas fa-file-powerpoint" style="color: #b91c1c;"></i></button>
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
        const proposalNumber = <?php echo json_encode($proposal['proposal_number']); ?>;
        const currentDate = <?php echo json_encode(date('F Y')); ?>;
        const slidesData = <?php echo json_encode($slides_data); ?>;

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

        async function downloadPPTX() {
            // Show loading state
            const btn = document.querySelector('button[title="Download PPTX"]');
            const originalIcon = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            try {
                let pptx = new PptxGenJS();
                pptx.layout = 'LAYOUT_WIDE'; // 16:9

                // Intro Slide
                let intro = pptx.addSlide();
                intro.background = { color: '0F172A' };
                
                // Add Logo
                try {
                    intro.addImage({ 
                        path: '../../assets/img/LOGO.png', 
                        x: '35%', y: '15%', w: '30%', h: '15%', 
                        sizing: { type: 'contain' } 
                    });
                } catch (e) { console.warn("Logo not found for PPTX", e); }
                
                intro.addText(campaignName || "Media Plan", { 
                    x: 0, y: '40%', w: '100%', 
                    align: 'center', fontSize: 44, color: '0D9488', bold: true, fontFace: 'Arial' 
                });
                
                intro.addText('PREPARED FOR', { 
                    x: 0, y: '55%', w: '100%', 
                    align: 'center', fontSize: 18, color: '94A3B8', fontFace: 'Arial' 
                });
                
                intro.addText(clientName || "Client", { 
                    x: 0, y: '62%', w: '100%', 
                    align: 'center', fontSize: 32, color: 'FFFFFF', bold: true, fontFace: 'Arial' 
                });
                
                intro.addText(`PROPOSAL #${proposalNumber} • ${currentDate}`, { 
                    x: 0, y: '75%', w: '100%', 
                    align: 'center', fontSize: 14, color: '94A3B8', fontFace: 'Arial' 
                });

                // Asset Slides
                slidesData.forEach((item, idx) => {
                    let slide = pptx.addSlide();
                    
                    // Header Logo
                    try {
                        slide.addImage({ path: '../../assets/img/LOGO.png', x: 0.4, y: 0.2, w: 1.5, h: 0.5, sizing: { type: 'contain' } });
                    } catch (e) {}
                    
                    slide.addText(campaignName || "", { x: '70%', y: 0.3, w: '25%', align: 'right', fontSize: 12, color: '64748B', bold: true, fontFace: 'Arial' });

                    // Image Area
                    if (item.image) {
                        slide.addImage({ 
                            path: '../../uploads/sites/' + item.image, 
                            x: 0.5, y: 1.0, w: '90%', h: '70%', 
                            sizing: { type: 'contain' } 
                        });
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
                    const rateVal = parseFloat(item.sale_rate) || 0;
                    
                    let dim = w + 'X' + h;
                    let rateFormatted = new Intl.NumberFormat('en-IN').format(rateVal);
                    let caption = `${city}, ${siteName}. ${dim}. ${light} ( ${type} ) . Rs.${rateFormatted} PM`;
                    
                    slide.addText(caption, { 
                        x: 0, y: '85%', w: '100%', 
                        align: 'center', 
                        fontSize: 22, 
                        color: 'DC2626', 
                        bold: true,
                        underline: true, // Simplified underline for better compatibility
                        fontFace: 'Arial'
                    });
                });

                const fileName = `MediaPlan_${(campaignName || "Export").replace(/[^a-z0-9]/gi, '_')}.pptx`;
                pptx.writeFile({ fileName: fileName })
                    .then(() => {
                        btn.innerHTML = originalIcon;
                        btn.disabled = false;
                    })
                    .catch(err => {
                        console.error("Write error:", err);
                        btn.innerHTML = originalIcon;
                        btn.disabled = false;
                    });

            } catch (err) {
                console.error("PPTX Generation Error:", err);
                alert('Error generating PPTX: ' + err.message);
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
    </script>
</body>
</html>
