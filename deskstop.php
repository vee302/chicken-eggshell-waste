<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$target_link = "login.php";
if (isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true) {
    if (isset($_SESSION["user_email"]) && ($_SESSION["user_email"] === 'admin@greenforensics.com' || $_SESSION["user_email"] === 'admin@greenforensics.edu.ph')) {
        $target_link = "admin/admin_dashboard.php";
    } else {
        $target_link = "dashboard.php";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Forensics - Sustainable Fingerprint Powder</title>
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <!-- GSAP, ScrollTrigger, and ScrollToPlugin CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollToPlugin.min.js"></script>
    <script>
        if (window.innerWidth <= 768) {
            window.location.replace("mobile.php");
        }
    </script>
</head>

<body>
    <!-- Intro Loader Popup -->
    <div id="introLoader">
        <div class="intro-loader-content">
            <div class="intro-product-wrap" style="height: 250px; width: 250px; margin-bottom: 1.5rem;">
                <!-- Empty space for the single mainProductVisual jar to be positioned and animated smoothly -->
            </div>

            <div class="intro-text">
                <span class="intro-label">GREEN Technology</span>
                <h1>Eco Fingerprint Powder</h1>
                <p>Sustainable Fingerprint Powder Using Chicken Eggshell Waste</p>
            </div>
        </div>
    </div>

    <!-- Floating Particles Background -->
    <div class="particles-container" id="particles"></div>

    <!-- Main Product Visual Container (Fixed & Controlled by GSAP) -->
    <div id="mainProductVisual">
        <!-- Premium Glass Jar Image -->
        <div class="product-jar">
            <div class="jar-shadow"></div>
            <img src="images/eco-powder-jar.png" alt="Eco Fingerprint Powder" class="jar-image">
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero-section" id="hero">
        <div class="container hero-container-layout">
            <div class="hero-content">
                <div class="hero-label">INNOVATIVE FORENSIC SCIENCE</div>
                <h1 class="hero-title">
                    <span class="title-line">GREEN</span>
                    <span class="title-line">FORENSICS</span>
                </h1>
                <p class="hero-subtitle">Sustainable Fingerprint Powder Using Chicken Eggshell Waste</p>
                <p class="hero-desc" style="max-width: 600px; margin: 1rem auto 2.5rem; font-size: 1.1rem; opacity: 0.9; line-height: 1.6;">
                    A green forensic innovation that transforms chicken eggshell waste into a sustainable fingerprint powder for education, training, and forensic evaluation.
                </p>
                <a href="<?php echo $target_link; ?>" class="hero-btn">
                    <span>Access Evaluating System</span>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </a>
            </div>
        </div>

        <!-- Floating forensic elements -->
        <div class="floating-elements">
            <div class="float-element fingerprint-mark"></div>
            <div class="float-element grid-line"></div>
            <div class="float-element eggshell-piece"></div>
        </div>
    </section>

    <!-- Problem Section -->
    <section class="problem-section" id="problem">
        <div class="container">
            <div class="section-header-left">
                <span class="section-label">The Challenge</span>
                <h2 class="section-title">Traditional Methods <span class="highlight">Are Harmful</span></h2>
                <p class="section-description-main">Traditional carbon black and heavy-metal based forensic dusting
                    powders contain carcinogenic, hazardous chemicals that pose acute health risks to forensic
                    practitioners and contaminate active crime scene ecosystems.</p>
            </div>

            <div class="problem-content">
                <!-- Upgraded: Visual Problem Cards Column -->
                <div class="problem-cards-container">
                    <!-- Card 1: Harmful Chemicals -->
                    <div class="problem-card">
                        <div class="card-icon-box">
                            <!-- Warning Triangle SVG -->
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
                                stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                <line x1="12" y1="9" x2="12" y2="13" />
                                <line x1="12" y1="17" x2="12.01" y2="17" />
                            </svg>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Harmful Chemicals</h3>
                            <p class="card-desc">Conventional powders rely on toxic heavy metals like <strong>lead,
                                    mercury, and carbon black</strong>, introducing volatile organic hazards to active
                                crime scenes.</p>
                        </div>
                    </div>

                    <!-- Card 2: Health Risks -->
                    <div class="problem-card">
                        <div class="card-icon-box">
                            <!-- Heart SVG -->
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
                                stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                            </svg>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Health Risks</h3>
                            <p class="card-desc">Daily inhalation of <strong>ultra-fine synthetic dust
                                    particles</strong> poses chronic respiratory hazards and long-term carcinogen
                                exposure for forensic experts.</p>
                        </div>
                    </div>

                    <!-- Card 3: Environmental Concerns -->
                    <div class="problem-card">
                        <div class="card-icon-box">
                            <!-- Globe SVG -->
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
                                stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <path
                                    d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10M12 2a15.3 15.3 0 0 0-4 10 15.3 15.3 0 0 0 4 10M2 12h20" />
                            </svg>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Environmental Concerns</h3>
                            <p class="card-desc">Mass production and chemical runoff of <strong>non-biodegradable
                                    petroleum-based dusting agents</strong> accumulate in ecosystems, harming soil and
                                water grids.</p>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Jar Space with high-tech Pedestal and Warning Badges -->
                <div class="problem-jar-space" aria-hidden="true">
                    <!-- Glowing Forensic Pedestal -->
                    <div class="jar-landing-pedestal">
                        <div class="pedestal-glow"></div>
                        <div class="pedestal-ring pedestal-ring-1"></div>
                        <div class="pedestal-ring pedestal-ring-2"></div>
                        <div class="pedestal-ring pedestal-ring-3"></div>
                        <div class="pedestal-plate"></div>
                        <div class="pedestal-scanner"></div>
                        <div class="pedestal-label">
                            <span class="pulse-dot"></span>
                            <span>SAFETY STORAGE TARGET</span>
                        </div>
                    </div>

                    <!-- Floating Hazard Warning Badges -->
                    <div class="jar-warning-badge badge-unsafe" style="top: 15%; left: 8%;">
                        <span class="warning-pulse-dot"></span>
                        <span>UNSAFE TOXINS</span>
                    </div>
                    <div class="jar-warning-badge badge-costly" style="top: 48%; left: -6%;">
                        <span class="warning-pulse-dot"></span>
                        <span>COSTLY HAZARDS</span>
                    </div>
                    <div class="jar-warning-badge badge-unsustainable" style="bottom: 15%; right: 4%;">
                        <span class="warning-pulse-dot"></span>
                        <span>UNSUSTAINABLE</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Solution Section / Our Process -->
    <section class="solution-section" id="solution">
        <div class="container">
            <div class="section-header">
                <span class="section-label">Our Process</span>
                <h2 class="section-title">From Waste to<br>Forensic Innovation</h2>
                <p class="section-subtitle">A sustainable four-step transformation process that converts organic waste
                    into cutting-edge forensic technology.</p>
            </div>

            <div class="process-timeline">
                <!-- Step 1 -->
                <div class="process-step" data-step="1">
                    <span class="step-number">01</span>
                    <div class="step-image-wrap">
                        <img src="images/eggshell-waste.png" alt="Eggshell Waste" class="step-img">
                    </div>
                    <h3 class="step-title">Eggshell Waste</h3>
                    <p class="step-desc">Collection of organic chicken eggshells from the local food industry.</p>
                </div>

                <div class="process-arrow">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12" />
                        <polyline points="12 5 19 12 12 19" />
                    </svg>
                </div>

                <!-- Step 2 -->
                <div class="process-step" data-step="2">
                    <span class="step-number">02</span>
                    <div class="step-image-wrap">
                        <img src="images/clean-dry.png" alt="Cleaned & Dried" class="step-img">
                    </div>
                    <h3 class="step-title">Cleaned & Dried</h3>
                    <p class="step-desc">Thorough sanitization, chemical washing, and high-temperature oven drying.</p>
                </div>

                <div class="process-arrow">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12" />
                        <polyline points="12 5 19 12 12 19" />
                    </svg>
                </div>

                <!-- Step 3 -->
                <div class="process-step" data-step="3">
                    <span class="step-number">03</span>
                    <div class="step-image-wrap">
                        <img src="images/crushed-powder.png" alt="Fine Powder" class="step-img">
                    </div>
                    <h3 class="step-title">Fine Powder</h3>
                    <p class="step-desc">Mechanical ball-milling pulverization to achieve sub-micron forensic-grade
                        fineness.</p>
                </div>

                <div class="process-arrow">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12" />
                        <polyline points="12 5 19 12 12 19" />
                    </svg>
                </div>

                <!-- Step 4 (Landed Target Slot) -->
                <div class="process-step process-final-card" id="processFinalJarTarget" data-step="4">
                    <span class="step-number">04</span>
                    <div class="step-image-wrap">
                        <!-- Empty placeholder target slot for the main dynamic jar (#mainProductVisual) -->
                    </div>
                    <h3 class="step-title">Certified Solution</h3>
                    <p class="step-desc">High-contrast, non-toxic, and biodegradable latent print developing powder
                        ready for field work.</p>
                </div>
            </div>

        </div>
    </section>

    <!-- Fingerprint Application Section -->
    <section class="fingerprint-section" id="fingerprint">
        <div class="container">
            <div class="section-header">
                <span class="section-label">Forensic Application</span>
                <h2 class="section-title">Latent Print Development</h2>
            </div>

            <style>
                /* Scoped Redesign for Latent Print Section */
                .latent-redesign {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 4rem;
                    align-items: center;
                    margin-top: 2rem;
                }
                .latent-col-left, .latent-col-right {
                    display: flex;
                    flex-direction: column;
                    gap: 2rem;
                }
                /* Clean Glass Image Card */
                .glass-card-clean {
                    background: #fff;
                    border-radius: 20px;
                    overflow: hidden;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.04);
                    border: 1px solid rgba(0,0,0,0.04);
                    position: relative;
                }
                .glass-img-bg {
                    width: 100%;
                    height: 280px;
                    background-image: url('images/glass-surface.png');
                    background-size: cover;
                    background-position: center;
                }
                .glass-label-badge {
                    position: absolute;
                    top: 1.5rem;
                    left: 1.5rem;
                    background: rgba(255,255,255,0.95);
                    padding: 6px 14px;
                    border-radius: 20px;
                    font-size: 0.75rem;
                    font-weight: 700;
                    letter-spacing: 1px;
                    color: var(--dark-green);
                    text-transform: uppercase;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
                }
                /* Minimal Camera Preview Card */
                .camera-preview-clean {
                    background: #fafaf8;
                    border-radius: 20px;
                    padding: 2.5rem;
                    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.05);
                    text-align: center;
                }
                .camera-preview-clean h3 {
                    color: var(--dark-green);
                    font-size: 1.3rem;
                    margin-bottom: 0.5rem;
                }
                .camera-preview-clean > p {
                    color: #666;
                    font-size: 0.95rem;
                    margin-bottom: 2rem;
                }
                .preview-box-ui {
                    background: #fff;
                    border: 2px dashed rgba(0,0,0,0.1);
                    border-radius: 12px;
                    padding: 2.5rem 1rem;
                    margin-bottom: 2rem;
                    color: #888;
                }
                .preview-btns {
                    display: flex;
                    gap: 1rem;
                    margin-bottom: 1rem;
                }
                .preview-btns button {
                    flex: 1;
                    padding: 12px;
                    border-radius: 8px;
                    font-weight: 600;
                    font-size: 0.8rem;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                    transition: all 0.2s ease;
                }
                .btn-green-light {
                    background: rgba(46, 125, 50, 0.1);
                    color: var(--dark-green);
                    border: none;
                }
                .btn-green-light:hover {
                    background: rgba(46, 125, 50, 0.15);
                }
                .btn-outline {
                    background: transparent;
                    border: 1px solid rgba(0,0,0,0.15);
                    color: #555;
                }
                .btn-outline:hover {
                    background: rgba(0,0,0,0.03);
                }
                .btn-solid-green {
                    width: 100%;
                    background: var(--dark-green);
                    color: #fff;
                    border: none;
                    padding: 14px;
                    border-radius: 8px;
                    font-weight: 600;
                    font-size: 0.9rem;
                    cursor: pointer;
                    margin-bottom: 1.5rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    text-decoration: none;
                    transition: all 0.2s ease;
                }
                .btn-solid-green:hover {
                    opacity: 0.9;
                }
                /* Info Cards on Right */
                .intro-text-clean {
                    font-size: 1.1rem;
                    line-height: 1.6;
                    color: #555;
                    margin-bottom: 0.5rem;
                }
                .info-card-clean {
                    background: #fff;
                    border-radius: 16px;
                    padding: 1.8rem;
                    box-shadow: 0 8px 30px rgba(0,0,0,0.03);
                    border: 1px solid rgba(0,0,0,0.04);
                    border-left: 4px solid var(--soft-green);
                    transition: transform 0.3s ease;
                }
                .info-card-clean:hover {
                    transform: translateX(5px);
                }
                .info-card-clean h4 {
                    color: var(--dark-green);
                    font-size: 1.1rem;
                    margin-bottom: 0.5rem;
                }
                .info-card-clean p {
                    color: #666;
                    font-size: 0.95rem;
                    line-height: 1.5;
                    margin: 0;
                }
                @media(max-width: 992px) {
                    .latent-redesign {
                        grid-template-columns: 1fr;
                        gap: 3rem;
                    }
                }
            </style>

            <div class="latent-redesign">
                <!-- Left Column -->
                <div class="latent-col-left">
                    <!-- Glass Surface Image Card -->
                    <div class="glass-card-clean">
                        <div class="glass-label-badge">GLASS SURFACE</div>
                        <div class="glass-img-bg"></div>
                    </div>

                    <!-- Camera Preview Card -->
                    <div class="camera-preview-clean">
                        <span style="display: inline-block; padding: 4px 12px; background: rgba(46, 125, 50, 0.1); color: var(--dark-green); border-radius: 20px; font-size: 0.75rem; font-weight: 700; margin-bottom: 1rem;">PREVIEW ONLY</span>
                        <h3>Camera-Based Evaluation Preview</h3>
                        <p>Visual preview of the secured student dashboard feature.</p>
                        
                        <div class="preview-box-ui">
                            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 10px; opacity: 0.6;">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                            <div style="font-weight: 600; color: #555; margin-bottom: 6px; font-size: 0.95rem;">Camera Preview / Uploaded Image</div>
                            <div style="font-size: 0.8rem; color: #999;">Preview Mode Only</div>
                        </div>

                        <div class="preview-btns">
                            <button class="btn-green-light" onclick="alert('Please login to access the secured camera-based evaluation feature.')">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                                START CAMERA
                            </button>
                            <button class="btn-outline" onclick="alert('Please login to access the secured camera-based evaluation feature.')">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                UPLOAD FILE
                            </button>
                        </div>

                        <button class="btn-solid-green" onclick="alert('Please login to access the secured camera-based evaluation feature.')">
                            EVALUATE PRINT CLARITY
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                        </button>

                        <div style="border-top: 1px solid rgba(0,0,0,0.06); padding-top: 1.5rem; margin-top: 1rem;">
                            <p style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                Fingerprint images are protected through login authentication and role-based access control.
                            </p>
                            <a href="login.php" class="btn-solid-green" style="margin-bottom: 0; margin-top: 1.5rem; background: #222;">
                                LOGIN TO USE CAMERA-BASED EVALUATION
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="latent-col-right">
                    <p class="intro-text-clean">
                        Our sustainable eggshell-based powder is designed to be applied on common test surfaces to help reveal latent fingerprints for classroom, laboratory, and simulation-based evaluation.
                    </p>

                    <div class="info-card-clean">
                        <h4>Application Method</h4>
                        <p>Eggshell powder is gently applied to surfaces containing latent fingerprints.</p>
                    </div>

                    <div class="info-card-clean">
                        <h4>Visibility</h4>
                        <p>Fine calcium carbonate particles adhere to fingerprint residues, revealing ridge patterns.</p>
                    </div>

                    <div class="info-card-clean">
                        <h4>Effectiveness</h4>
                        <p>Results can be compared across glass, paper, wood, plastic, and metal surfaces.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="benefits-section" id="benefits">
        <div class="container">
            <div class="section-header">
                <span class="section-label">Impact & Advantages</span>
                <h2 class="section-title">Why It Matters</h2>
            </div>

            <div class="benefits-grid">
                <div class="benefit-card">
                    <h3>Eco-Friendly & Biodegradable</h3>
                    <p>Natural calcium carbonate breaks down safely without environmental harm</p>
                </div>

                <div class="benefit-card">
                    <h3>Cost-Effective Alternative</h3>
                    <p>Utilizes waste material, reducing production costs significantly</p>
                </div>

                <div class="benefit-card">
                    <h3>Safer for Forensic Users</h3>
                    <p>Non-toxic composition eliminates health risks from heavy metals</p>
                </div>

                <div class="benefit-card">
                    <h3>Supports Waste Reduction</h3>
                    <p>Transforms food industry waste into valuable forensic resource</p>
                </div>

                <div class="benefit-card">
                    <h3>Criminology Training Tool</h3>
                    <p>Ideal for educational institutions and student practice</p>
                </div>

                <div class="benefit-card">
                    <h3>Supports SDG 12 & 13</h3>
                    <p>Aligns with sustainable consumption and climate action goals</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Implementation Timeline Section -->
    <section class="timeline-section" id="timeline">
        <div class="container-full">
            <div class="section-header">
                <span class="section-label">Roadmap</span>
                <h2 class="section-title">Project Implementation</h2>
            </div>

            <div class="timeline-container">
                <div class="timeline-track">
                    <div class="timeline-item" data-phase="1">
                        <div class="timeline-marker">
                            <span class="phase-number">01</span>
                        </div>
                        <div class="timeline-content">
                            <h3>Research & Planning</h3>
                            <p>Team organization, budget mapping, and primary raw material extraction</p>
                            <span class="timeline-duration">Month 1-3</span>
                        </div>
                    </div>

                    <div class="timeline-item" data-phase="2">
                        <div class="timeline-marker">
                            <span class="phase-number">02</span>
                        </div>
                        <div class="timeline-content">
                            <h3>Curriculum Integration</h3>
                            <p>Criminology course module updates and forensic lab manual guides</p>
                            <span class="timeline-duration">Month 4-6</span>
                        </div>
                    </div>

                    <div class="timeline-item" data-phase="3">
                        <div class="timeline-marker">
                            <span class="phase-number">03</span>
                        </div>
                        <div class="timeline-content">
                            <h3>Community Extension</h3>
                            <p>Local police department training, field testing, and forensic workshops</p>
                            <span class="timeline-duration">Month 7-9</span>
                        </div>
                    </div>

                    <div class="timeline-item" data-phase="4">
                        <div class="timeline-marker">
                            <span class="phase-number">04</span>
                        </div>
                        <div class="timeline-content">
                            <h3>Monitoring & Sustainability</h3>
                            <p>Quality testing feedback loop, policy recommendations, and expansions</p>
                            <span class="timeline-duration">Month 10+</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Expected Results Section -->
    <section class="results-section" id="results">
        <div class="container">
            <div class="section-header">
                <span class="section-label">Outcomes</span>
                <h2 class="section-title">Expected Impact</h2>
            </div>

            <div class="results-grid">
                <div class="result-card">
                    <h3>Effective Fingerprint Powder</h3>
                    <p>High-quality eggshell-based powder with proven forensic application</p>
                </div>

                <div class="result-card">
                    <h3>Waste Reduction</h3>
                    <div class="counter" data-target="500">0</div>
                    <p>kg of eggshell waste diverted annually</p>
                </div>

                <div class="result-card">
                    <h3>Cost Savings</h3>
                    <div class="counter" data-target="70">0</div>
                    <p>% lower cost vs commercial powder</p>
                </div>

                <div class="result-card">
                    <h3>Safer Laboratory</h3>
                    <p>Reduced exposure to toxic substances for students and faculty</p>
                </div>

                <div class="result-card">
                    <h3>Enhanced Training</h3>
                    <div class="counter" data-target="200">0</div>
                    <p>students trained annually</p>
                </div>

                <div class="result-card">
                    <h3>Community Partnership</h3>
                    <p>Stronger collaboration with law enforcement and local agencies</p>
                </div>

                <div class="result-card">
                    <h3>Print Visibility</h3>
                    <div class="counter" data-target="95">0</div>
                    <p>% success rate on various surfaces</p>
                </div>

                <div class="result-card">
                    <h3>Environmental Impact</h3>
                    <p>Measurable reduction in carbon footprint and chemical waste</p>
                </div>
            </div>
        </div>
    </section>



    <!-- Stakeholders Section -->
    <section class="stakeholders-section" id="stakeholders">
        <div class="container">
            <div class="section-header-left">
                <span class="section-label">Collaboration</span>
                <h2 class="section-title">Roles & Responsibilities</h2>
                <p class="section-description-main">A network of stakeholders working together to build, sustain, and
                    elevate the program — each contributing a distinct piece of the whole.</p>
            </div>

            <div class="stakeholders-network">
                <!-- Card 1 -->
                <div class="stakeholder-card" data-role="admin">
                    <span class="card-number">01</span>
                    <div class="card-image-wrap">
                        <img src="images/admin-placeholder.png" alt="LSPU Administration" class="card-img">
                    </div>
                    <div class="card-content">
                        <h3>LSPU Administration</h3>
                        <p>Policy support, budget allocation, and institutional backing</p>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="stakeholder-card" data-role="ccje">
                    <span class="card-number">02</span>
                    <div class="card-content">
                        <h3>CCJE San Pablo City Campus</h3>
                        <p>Program implementation and curriculum integration</p>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="stakeholder-card" data-role="faculty">
                    <span class="card-number">03</span>
                    <div class="card-image-wrap">
                        <img src="images/faculty-placeholder.png" alt="Faculty Researchers" class="card-img">
                    </div>
                    <div class="card-content">
                        <h3>Faculty Researchers</h3>
                        <p>Research leadership, quality control, and innovation</p>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="stakeholder-card" data-role="students">
                    <span class="card-number">04</span>
                    <div class="card-content">
                        <h3>Criminology Students</h3>
                        <p>Active participation, learning, and skill development</p>
                    </div>
                </div>

                <!-- Card 5 -->
                <div class="stakeholder-card" data-role="support">
                    <span class="card-number">05</span>
                    <div class="card-content">
                        <h3>Support Staff</h3>
                        <p>Laboratory maintenance and technical assistance</p>
                    </div>
                </div>

                <!-- Card 6 -->
                <div class="stakeholder-card" data-role="community">
                    <span class="card-number">06</span>
                    <div class="card-content">
                        <h3>Community & Law Enforcement</h3>
                        <p>External collaboration and real-world application</p>
                    </div>
                </div>

                <!-- Card 7 -->
                <div class="stakeholder-card" data-role="committee">
                    <span class="card-number">07</span>
                    <div class="card-content">
                        <h3>Project Committee</h3>
                        <p>Monitoring, evaluation, and sustainability planning</p>
                    </div>
                </div>
            </div>

            <!-- Stakeholders Footer Decoration -->
            <div class="stakeholders-footer">
                <hr class="footer-divider">
                <div class="footer-meta">
                    <span class="meta-left">SEVEN PILLARS — ONE MISSION</span>
                    <span class="meta-right">01 — 07</span>
                </div>
            </div>
        </div>
    </section>



    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2026 Green Forensics Project | LSPU CCJE San Pablo City Campus</p>
            <p>Sustainable Innovation in Criminal Justice Education</p>
        </div>
    </footer>

    <!-- Biometric Scan Transition Overlay -->
    <div id="scanOverlay" class="scan-overlay">
        <div class="scan-content">
            <div class="scanner-ring">
                <svg class="fingerprint-scan-icon" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                    <ellipse cx="100" cy="100" rx="70" ry="85" fill="none" stroke="var(--dark-green)" stroke-width="3"
                        stroke-linecap="round" class="scan-ridge" />
                    <ellipse cx="100" cy="100" rx="60" ry="75" fill="none" stroke="var(--soft-green)" stroke-width="3"
                        stroke-linecap="round" class="scan-ridge" />
                    <ellipse cx="100" cy="100" rx="50" ry="65" fill="none" stroke="var(--soft-green)" stroke-width="3"
                        stroke-linecap="round" class="scan-ridge" />
                    <ellipse cx="100" cy="100" rx="40" ry="55" fill="none" stroke="var(--soft-green)" stroke-width="3"
                        stroke-linecap="round" class="scan-ridge" />
                    <ellipse cx="100" cy="100" rx="30" ry="45" fill="none" stroke="var(--soft-green)" stroke-width="3"
                        stroke-linecap="round" class="scan-ridge" />
                    <ellipse cx="100" cy="100" rx="20" ry="35" fill="none" stroke="var(--soft-green)" stroke-width="3"
                        stroke-linecap="round" class="scan-ridge" />
                    <ellipse cx="100" cy="100" rx="10" ry="25" fill="none" stroke="var(--soft-green)" stroke-width="3"
                        stroke-linecap="round" class="scan-ridge" />
                </svg>
                <div class="scanner-bar"></div>
            </div>
            <div class="scan-text">Scanning Biometrics...</div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const heroBtn = document.querySelector(".hero-btn");
            const scanOverlay = document.getElementById("scanOverlay");

            if (heroBtn && scanOverlay) {
                heroBtn.addEventListener("click", (e) => {
                    e.preventDefault();
                    const targetUrl = heroBtn.getAttribute("href");

                    // Show overlay and start transition animation
                    scanOverlay.classList.add("active");

                    const tl = gsap.timeline({
                        onComplete: () => {
                            window.location.href = targetUrl;
                        }
                    });

                    // Reset ridges state for drawing
                    gsap.set(".scan-ridge", { strokeDasharray: 600, strokeDashoffset: 600 });

                    // 1. Draw fingerprint ridges in a beautiful staggered sequence (takes approx 2.76s to finish all ridges)
                    tl.to(".scan-ridge", {
                        strokeDashoffset: 0,
                        duration: 2.4,
                        stagger: 0.06,
                        ease: "power2.out"
                    });

                    // 2. Scan animation bar sweeping down & up 2 times (duration 1.3s * 2 runs = 2.6s)
                    tl.fromTo(".scanner-bar",
                        { top: "10%", opacity: 0 },
                        { top: "90%", opacity: 1, duration: 1.3, repeat: 1, yoyo: true, ease: "power1.inOut" },
                        "<0.1"
                    );

                    // 3. Fade out overlay
                    tl.to(scanOverlay, {
                        opacity: 0,
                        duration: 0.4,
                        ease: "power2.in"
                    }, "-=0.2");
                });
            }
        });
    </script>
</body>

</html>