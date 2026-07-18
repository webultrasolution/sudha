<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Resolve company info
$comp = resolveCompanyDetails();

// Dynamic login context
$isLoggedIn = isset($_SESSION['user_id']);
$portalUrl = $isLoggedIn ? 'dashboard.php' : 'login.php';
$portalText = $isLoggedIn ? 'Go to Dashboard' : 'CRM Login';

// Create contact_leads table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50),
        subject VARCHAR(255),
        message TEXT,
        status VARCHAR(50) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
    // Fail silently in production or log error
    error_log("Failed to create contact_leads table: " . $e->getMessage());
}

$successMsg = '';
$errorMsg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_lead') {
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $subject = clean($_POST['subject'] ?? '');
    $message = clean($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $errorMsg = 'Please fill in all required fields (Name, Email, and Message).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_leads (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $subject, $message]);
            $successMsg = 'Thank you! Your message has been sent successfully. We will get back to you shortly.';
        } catch (Exception $e) {
            $errorMsg = 'Something went wrong. Please try again later.';
            error_log("Database insertion failed for contact_lead: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suudha Creative & Advertising | Premium OOH & DOOH Media Agency</title>
    
    <!-- PWA manifest -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#7b161c">
    
    <!-- CSS Styles -->
    <link rel="stylesheet" href="assets/css/landing.css">
    
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Header / Navigation -->
    <header class="landing-header" id="header">
        <div class="nav-container">
            <a href="#" class="brand-logo">
                <img src="assets/images/sudha_logo.jpg" alt="Suudha Creative & Advertising Logo">
            </a>
            
            <button class="menu-toggle" id="menuToggle" aria-label="Toggle Navigation">
                <i class="fas fa-bars"></i>
            </button>
            
            <ul class="nav-menu" id="navMenu">
                <li><a href="#home" class="active">Home</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#offerings">Offerings</a></li>
                <li><a href="#portfolio">Portfolio</a></li>
                <li><a href="#presence">Presence</a></li>
                <li><a href="#testimonials">Testimonials</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="<?php echo $portalUrl; ?>" class="nav-cta"><i class="fas fa-user-lock"></i> <?php echo $portalText; ?></a></li>
            </ul>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="hero-container">
            <div class="hero-content animate-slide">
                <span class="hero-tag">Welcome to Suudha Creative</span>
                <h1 class="hero-title">Elevate Your Brand with <span>Premium Out-Of-Home</span> Advertising</h1>
                <p class="hero-desc">We connect brands with target audiences through high-impact outdoor media solutions, including state-of-the-art DOOH, airport branding, transit media, and traditional billboards.</p>
                <div class="hero-actions">
                    <a href="#portfolio" class="btn btn-primary">Explore Media Sites</a>
                    <a href="#contact" class="btn btn-outline">Request a Proposal</a>
                </div>
            </div>
            <div class="hero-visual animate-fade">
                <div class="hero-visual-bg"></div>
                <img src="assets/images/billboard_mockup_1.png" alt="OOH Advertising Billboard Mockup" class="hero-image">
            </div>
        </div>
    </section>

    <!-- Vision & About Section -->
    <section class="section" id="about">
        <div class="section-container">
            <div class="vision-grid">
                <div class="vision-image-box">
                    <img src="assets/images/billboard_mockup_2.png" alt="DOOH Media Screen Showcase" class="vision-img">
                    <div class="experience-badge">
                        <h4>15+</h4>
                        <p>Years of Media Excellence</p>
                    </div>
                </div>
                <div class="vision-content">
                    <span class="section-tag">About Our Company</span>
                    <h3>Creating High Impact Campaigns Across Eastern India</h3>
                    <p>Suudha Creative & Advertising (OPC) Private Limited is a leading media agency specialized in Out-of-Home (OOH) marketing, printing operations, and retail branding. Our mission is to provide businesses of all scales with the visibility they need to succeed in a highly competitive market.</p>
                    <p>With deep regional expertise and prime site holdings across West Bengal, Bihar, and Jharkhand, we design, produce, print, and install advertisements that command attention and drive measurable brand recall.</p>
                    
                    <ul class="value-list">
                        <li class="value-item">
                            <div class="value-icon"><i class="fas fa-check"></i></div>
                            <div class="value-text">
                                <h5>Prime Media Locations</h5>
                                <p>We offer media slots at high-traffic nodes, highways, airports, and major retail hubs.</p>
                            </div>
                        </li>
                        <li class="value-item">
                            <div class="value-icon"><i class="fas fa-check"></i></div>
                            <div class="value-text">
                                <h5>In-House Print Operations</h5>
                                <p>End-to-end production control using top-tier printing machinery ensures maximum color fidelity and durability.</p>
                            </div>
                        </li>
                        <li class="value-item">
                            <div class="value-icon"><i class="fas fa-check"></i></div>
                            <div class="value-text">
                                <h5>Digital Innovation (DOOH)</h5>
                                <p>Vibrant digital screens offering scheduled, high-frequency slots with smart scheduling analytics.</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Offerings Section -->
    <section class="section section-bg" id="offerings">
        <div class="section-container">
            <div class="section-header">
                <span class="section-tag">Our Services</span>
                <h2 class="section-title">Comprehensive Advertising Solutions</h2>
                <p class="section-desc">From strategy to installation, we handle all facets of outdoor media deployment to deliver a seamless brand campaign.</p>
            </div>
            
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-plane-departure"></i></div>
                    <h4>Airport Advertising</h4>
                    <p>Reach premium business and leisure travelers through high-dwell-time assets, lounge branding, baggage belt ads, and digital displays inside airport terminals.</p>
                </div>
                
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-tv"></i></div>
                    <h4>Digital Out-of-Home (DOOH)</h4>
                    <p>Dynamic digital displays that command high attention, allowing programmatic shifts, dayparting, and multi-creative syncs in urban marketplaces.</p>
                </div>
                
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-road"></i></div>
                    <h4>Billboards & Unipoles</h4>
                    <p>Classic high-impact traditional and back-lit billboards placed at critical traffic intersections, flyovers, and major state and national highways.</p>
                </div>
                
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-train"></i></div>
                    <h4>Transit & Metro Media</h4>
                    <p>Capture moving commuters with wrap-around branding on trains, buses, and metro platforms, ensuring continuous exposure.</p>
                </div>
                
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-print"></i></div>
                    <h4>Creative Printing & Production</h4>
                    <p>Flawless in-house print production of banners, vinyl sheets, flexes, and mounting structures utilizing strict quality testing protocols.</p>
                </div>
                
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-bullhorn"></i></div>
                    <h4>Exhibition & Event Branding</h4>
                    <p>Custom fabrication, exhibition stalls, kiosks, and venue branding solutions to provide an immersive experience during major corporate events.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Portfolio Section -->
    <section class="section" id="portfolio">
        <div class="section-container">
            <div class="section-header">
                <span class="section-tag">Media Gallery</span>
                <h2 class="section-title">Featured Media Deployments</h2>
                <p class="section-desc">Take a look at some of our actual billboard installations and premium advertising placements.</p>
            </div>
            
            <div class="portfolio-grid">
                <div class="portfolio-card">
                    <img src="assets/images/billboard_mockup_1.png" alt="Highway Unipole Billboard Placement">
                    <div class="portfolio-overlay">
                        <span class="portfolio-tag">Billboard</span>
                        <h4>Premium National Highway Unipole</h4>
                        <p>High-visibility outdoor structure capturing regional interstate traffic.</p>
                    </div>
                </div>
                
                <div class="portfolio-card">
                    <img src="assets/images/billboard_mockup_2.png" alt="Urban DOOH Screen Installation">
                    <div class="portfolio-overlay">
                        <span class="portfolio-tag">DOOH</span>
                        <h4>Urban Digital LED Screen</h4>
                        <p>Lively, high-frequency digital screen located in the city center retail district.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Presence / Reach Section -->
    <section class="section section-bg" id="presence">
        <div class="section-container">
            <div class="presence-grid">
                <div class="presence-info-box">
                    <span class="section-tag">Our Coverage</span>
                    <h3 class="section-title" style="font-size: 2.25rem;">Extensive Regional Footprint</h3>
                    <p class="section-desc" style="margin-bottom: 2rem;">We boast an extensive network of advertising sites across key commercial zones in Eastern India, giving your brand maximum reach and regional impact.</p>
                    
                    <div class="presence-list">
                        <div class="presence-item">
                            <div class="presence-info">
                                <h5>North Bengal & Siliguri</h5>
                                <p>Gateways to Northeast India and international trade channels.</p>
                            </div>
                            <span class="presence-count">120+ Sites</span>
                        </div>
                        
                        <div class="presence-item">
                            <div class="presence-info">
                                <h5>Malda & Central Bengal</h5>
                                <p>Major commercial hubs and bypass expressways.</p>
                            </div>
                            <span class="presence-count">80+ Sites</span>
                        </div>
                        
                        <div class="presence-item">
                            <div class="presence-info">
                                <h5>Kolkata & South Bengal</h5>
                                <p>Corporate networks, metro grids, and high-street shopping zones.</p>
                            </div>
                            <span class="presence-count">150+ Sites</span>
                        </div>
                        
                        <div class="presence-item">
                            <div class="presence-info">
                                <h5>Bihar & Jharkhand</h5>
                                <p>Key railway junctions and high-traffic national highways.</p>
                            </div>
                            <span class="presence-count">95+ Sites</span>
                        </div>
                    </div>
                </div>
                
                <div class="presence-map">
                    <div style="background: rgba(123, 22, 28, 0.05); padding: 4rem 2rem; border-radius: 16px; border: 2px dashed rgba(123, 22, 28, 0.2);">
                        <i class="fas fa-map-location-dot" style="font-size: 4rem; color: var(--primary); margin-bottom: 1.5rem;"></i>
                        <h4 style="margin-bottom: 1rem;">OOH & DOOH Inventory Mapping</h4>
                        <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 2rem;">Access interactive GIS map coordinates and real-time site availability reports inside our CRM workspace.</p>
                        <a href="<?php echo $portalUrl; ?>" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-size: 0.9rem;"><i class="fas fa-search-location"></i> View Live Site Inventory</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="section" id="testimonials">
        <div class="section-container">
            <div class="section-header">
                <span class="section-tag">Client Feedback</span>
                <h2 class="section-title">Trusted by Top Brands</h2>
                <p class="section-desc">Read why businesses trust Suudha Creative to execute their high-impact marketing campaigns.</p>
            </div>
            
            <div class="testimonials-slider">
                <div class="testimonial-card">
                    <div class="stars">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"Suudha Creative helped us execute a complex, multi-city OOH campaign seamlessly. Their sites are in premium locations, and their in-house print quality is exceptional."</p>
                    <div class="client-profile">
                        <div class="client-name">
                            <h5>Rajesh Sharma</h5>
                            <p>Regional Marketing Head, Telecom Enterprise</p>
                        </div>
                    </div>
                </div>
                
                <div class="testimonial-card">
                    <div class="stars">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"The digital billboard network (DOOH) provided by Suudha Creative delivered outstanding brand recall. The scheduling process was highly flexible and analytics were accurate."</p>
                    <div class="client-profile">
                        <div class="client-name">
                            <h5>Ananya Sen</h5>
                            <p>Brand Manager, Premium FMCG Group</p>
                        </div>
                    </div>
                </div>
                
                <div class="testimonial-card">
                    <div class="stars">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"Outstanding client support! They managed our airport advertising rollout from creative design checks to installation with absolute professionalism. Highly recommended."</p>
                    <div class="client-profile">
                        <div class="client-name">
                            <h5>Vikram Adhikari</h5>
                            <p>Director, Real Estate Development Corp</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact & Lead Form Section -->
    <section class="section section-bg" id="contact">
        <div class="section-container">
            <div class="contact-grid">
                <div class="contact-info-box">
                    <div>
                        <span class="section-tag">Get In Touch</span>
                        <h2 class="section-title">Ready to Start Your Campaign?</h2>
                        <p class="section-desc" style="margin-bottom: 2rem;">Contact our media planners today to draft a tailored media strategy for your brand's growth.</p>
                    </div>
                    
                    <div class="contact-method">
                        <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="contact-text">
                            <h5>Registered Address</h5>
                            <p><?php echo htmlspecialchars($comp['address']); ?></p>
                        </div>
                    </div>
                    
                    <div class="contact-method">
                        <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                        <div class="contact-text">
                            <h5>Call Us</h5>
                            <p><a href="tel:<?php echo htmlspecialchars($comp['phone']); ?>">+91 <?php echo htmlspecialchars($comp['phone']); ?></a></p>
                            <p>Operational Hours: Mon - Sat (10:00 AM - 7:00 PM)</p>
                        </div>
                    </div>
                    
                    <div class="contact-method">
                        <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                        <div class="contact-text">
                            <h5>Email Inquiries</h5>
                            <p><a href="mailto:<?php echo htmlspecialchars($comp['email']); ?>"><?php echo htmlspecialchars($comp['email']); ?></a></p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1rem; padding-top: 2rem; border-top: 1px solid var(--border-color); font-size: 0.85rem; color: var(--text-muted);">
                        <?php if (!empty($comp['gstin'])): ?>
                            <p><strong>GSTIN:</strong> <?php echo htmlspecialchars($comp['gstin']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($comp['pan'])): ?>
                            <p><strong>PAN:</strong> <?php echo htmlspecialchars($comp['pan']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($comp['msme_number'])): ?>
                            <p><strong>MSME Reg No:</strong> <?php echo htmlspecialchars($comp['msme_number']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="contact-form-box">
                    <h4 style="font-size: 1.5rem; margin-bottom: 2rem;">Send Us a Message</h4>
                    
                    <form action="#contact" method="POST">
                        <input type="hidden" name="action" value="submit_lead">
                        
                        <div class="form-group">
                            <label class="form-label" for="name">Your Name *</label>
                            <input type="text" name="name" id="name" class="form-control" placeholder="Enter full name" required>
                        </div>
                        
                        <div class="form-group-row">
                            <div class="form-group">
                                <label class="form-label" for="email">Email Address *</label>
                                <input type="email" name="email" id="email" class="form-control" placeholder="name@company.com" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="phone">Phone Number</label>
                                <input type="tel" name="phone" id="phone" class="form-control" placeholder="10-digit mobile number">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="subject">Subject</label>
                            <input type="text" name="subject" id="subject" class="form-control" placeholder="E.g., Highway hoardings inquiry">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="message">Message *</label>
                            <textarea name="message" id="message" class="form-control" placeholder="Describe your advertising campaign details..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary submit-btn">
                            <span>Send Message</span>
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer class="landing-footer">
        <div class="footer-container">
            <div class="footer-about">
                <img src="assets/images/sudha_logo.jpg" alt="Suudha Creative & Advertising Footer Logo">
                <p>Suudha Creative & Advertising (OPC) Private Limited is Eastern India's premium OOH & DOOH network partner, helping brands build connections that last.</p>
            </div>
            <div class="footer-links">
                <h5>Core Offerings</h5>
                <ul>
                    <li><a href="#offerings">Airport Branding</a></li>
                    <li><a href="#offerings">Digital OOH (DOOH)</a></li>
                    <li><a href="#offerings">Highway Billboards</a></li>
                    <li><a href="#offerings">Transit Advertising</a></li>
                    <li><a href="#offerings">Flex & Banner Printing</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h5>Quick Navigation</h5>
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#portfolio">Our Portfolio</a></li>
                    <li><a href="#presence">Media Reach</a></li>
                    <li><a href="<?php echo $portalUrl; ?>"><?php echo $portalText; ?></a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Suudha Creative & Advertising (OPC) Private Limited. All Rights Reserved.</p>
            <p>Designed with <i class="fas fa-heart" style="color: var(--primary);"></i> | <a href="<?php echo $portalUrl; ?>" style="color: var(--accent); text-decoration: none; font-weight: bold;"><?php echo $portalText; ?></a></p>
        </div>
    </footer>

    <!-- Form Notification Alerts -->
    <?php if ($successMsg): ?>
        <div class="toast-msg" id="toastAlert">
            <i class="fas fa-circle-check" style="font-size: 1.5rem;"></i>
            <span><?php echo htmlspecialchars($successMsg); ?></span>
        </div>
    <?php elseif ($errorMsg): ?>
        <div class="toast-msg toast-msg-error" id="toastAlert">
            <i class="fas fa-circle-exclamation" style="font-size: 1.5rem;"></i>
            <span><?php echo htmlspecialchars($errorMsg); ?></span>
        </div>
    <?php endif; ?>

    <!-- JS Logic -->
    <script>
        // Header styling on scroll
        const header = document.getElementById('header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const navMenu = document.getElementById('navMenu');
        
        menuToggle.addEventListener('click', () => {
            navMenu.classList.toggle('open');
            const icon = menuToggle.querySelector('i');
            if (navMenu.classList.contains('open')) {
                icon.className = 'fas fa-times';
            } else {
                icon.className = 'fas fa-bars';
            }
        });

        // Close menu when clicking links
        const navLinks = document.querySelectorAll('.nav-menu a');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('open');
                const icon = menuToggle.querySelector('i');
                icon.className = 'fas fa-bars';
            });
        });

        // Auto-dismiss toast alerts after 5 seconds
        const toastAlert = document.getElementById('toastAlert');
        if (toastAlert) {
            setTimeout(() => {
                toastAlert.style.transition = 'opacity 0.5s ease-out';
                toastAlert.style.opacity = '0';
                setTimeout(() => {
                    toastAlert.remove();
                }, 500);
            }, 5000);
        }
    </script>
</body>
</html>
