// ===================================
// GREEN FORENSICS - MOBILE JAVASCRIPT
// Cinematic GSAP ScrollTrigger Animations for Mobile

if (window.innerWidth > 768) {
    // Desktop viewport: exit immediately to prevent conflicts
} else {
    // Register GSAP ScrollTrigger and ScrollTo plugins
    gsap.registerPlugin(ScrollTrigger, ScrollToPlugin);

    // ===================================
    // FLOATING PARTICLES BACKGROUND
    // ===================================

    function createFloatingParticles() {
        const particlesContainer = document.getElementById('particles');
        if (!particlesContainer) return;

        const particleCount = 20; // Reduced for mobile performance optimization

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle-dot';

            // Random position
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = Math.random() * 100 + '%';

            // Random size
            const size = Math.random() * 3 + 1.5;
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';

            particlesContainer.appendChild(particle);

            // Animate with GSAP
            gsap.to(particle, {
                y: -80 - Math.random() * 150,
                x: (Math.random() - 0.5) * 50,
                opacity: Math.random() * 0.35,
                duration: 5 + Math.random() * 5,
                repeat: -1,
                yoyo: true,
                ease: "sine.inOut",
                delay: Math.random() * 2
            });
        }
    }

    // ===================================
    // IDLE HOVER ANIMATIONS
    // ===================================

    function initIdleHoverAnimations() {
        // Jar body slow idle breathe
        gsap.to(".product-jar", {
            y: "-=6",
            duration: 4.0,
            repeat: -1,
            yoyo: true,
            ease: "sine.inOut"
        });

        // Shadow breath response
        gsap.to(".jar-shadow", {
            scaleX: 0.92,
            opacity: 0.65,
            duration: 4.0,
            repeat: -1,
            yoyo: true,
            ease: "sine.inOut"
        });
    }

    // ===================================
    // SHARED HERO JAR STATE POSITION
    // ===================================

    function getHeroJarState() {
        const width = window.innerWidth;
        const height = window.innerHeight;

        if (height <= 550) {
            return {
                x: width / 2 - 50,
                y: height * -0.02,
                scale: 0.38,
                rotation: 8
            };
        }

        if (width <= 480) {
            return {
                x: 0,
                y: height * -0.27,
                scale: 0.62,
                rotation: 0
            };
        }

        return {
            x: 0,
            y: height * -0.25,
            scale: 0.72,
            rotation: 0
        };
    }

    // ===================================
    // CINEMATIC SCROLL-LINKED TIMELINE
    // ===================================

    function initCinematicScroll() {
        // 1. Lock scroll during preloader
        document.body.classList.add("is-loading");

        // Calculate dynamic landing position relative to viewport center in real-time
        function getTargetPosition(targetSelector) {
            const target = document.querySelector(targetSelector);
            const product = document.querySelector("#mainProductVisual");

            if (!target || !product) return { x: 0, y: 0 };

            const rect = target.getBoundingClientRect();
            const targetCenterX = rect.left + rect.width / 2;
            const targetCenterY = rect.top + rect.height / 2;

            const viewportCenterX = window.innerWidth / 2;
            const viewportCenterY = window.innerHeight / 2;

            return {
                x: targetCenterX - viewportCenterX,
                y: targetCenterY - viewportCenterY
            };
        }

        function getIntroJarScale(defaultScale = 0.92) {
            if (window.innerHeight > 550) return defaultScale;

            const target = document.querySelector(".intro-product-wrap");
            const jar = document.querySelector(".product-jar");
            if (!target || !jar) return Math.min(defaultScale, 0.58);

            const targetRect = target.getBoundingClientRect();
            const jarSize = Math.max(jar.offsetWidth, jar.offsetHeight);
            const targetSize = Math.min(targetRect.width, targetRect.height);

            if (!targetSize || !jarSize) return Math.min(defaultScale, 0.58);

            const fittedScale = (targetSize / jarSize) * 0.86;
            return Math.min(defaultScale, Math.max(0.44, fittedScale));
        }

        // Get exact viewport coordinates for loader placeholder
        const loaderPos = getTargetPosition(".intro-product-wrap");
        const loaderScale = getIntroJarScale();
        const initialLoaderScale = window.innerHeight <= 550 ? Math.min(0.82, loaderScale + 0.14) : 0.82;

        // Calculate hero section starting coordinates using the shared function
        const heroState = getHeroJarState();
        const heroX = heroState.x;
        const heroY = heroState.y;
        const heroScale = heroState.scale;
        const heroRotation = heroState.rotation;

        // Set initial centered states to ensure transform origin is perfect
        gsap.set("#mainProductVisual", {
            position: "fixed",
            top: "50%",
            left: "50%",
            xPercent: -50,
            yPercent: -50,
            transformOrigin: "center center",
            x: loaderPos.x,
            y: loaderPos.y + 40,
            scale: initialLoaderScale,
            rotation: 0,
            opacity: 0,
            visibility: "visible",
            zIndex: 10000,
            overwrite: "auto"
        });

        // 2. Main Preloader Timeline
        const introTL = gsap.timeline({
            defaults: {
                ease: "power3.out"
            },
            onComplete: () => {
                document.body.classList.remove("is-loading");
                const loader = document.querySelector("#introLoader");
                if (loader) loader.style.display = "none";

                // Reset z-index of the jar to normal so it doesn't float over other sections as you scroll
                gsap.set("#mainProductVisual", { zIndex: 999 });

                // Refresh ScrollTrigger to recalculate pins/scroll heights
                ScrollTrigger.refresh();

                // Explicitly sync the jar's position to current scroll progress
                updateJarPosition(jarTrigger.progress);

                const initialTarget = getHashTarget();
                if (initialTarget) {
                    scrollToElement(initialTarget, 1.1);
                }
            }
        });

        // 3. Preloader Steps using the single mainProductVisual jar!
        introTL
            .to("#mainProductVisual", {
                opacity: 1,
                y: loaderPos.y,
                scale: loaderScale,
                duration: 1.2,
                overwrite: "auto"
            })
            .from(".intro-label", {
                opacity: 0,
                y: 20,
                duration: 0.6
            }, "-=0.8")
            .from(".intro-text h1", {
                opacity: 0,
                y: 30,
                duration: 0.8
            }, "-=0.6")
            .from(".intro-text p", {
                opacity: 0,
                y: 20,
                duration: 0.6
            }, "-=0.5")
            .to(".intro-text", {
                opacity: 0,
                y: -20,
                duration: 0.6,
                delay: 0.5,
                ease: "power2.in"
            })
            .to("#mainProductVisual", {
                x: heroX,
                y: heroY,
                scale: heroScale,
                rotation: heroRotation,
                duration: 1.2,
                ease: "power3.inOut",
                overwrite: "auto"
            }, "-=0.3")
            .to("#introLoader", {
                opacity: 0,
                duration: 0.8,
                ease: "power2.inOut"
            }, "-=1.0")
            .from('.hero-label', { opacity: 0, y: 30, duration: 0.8 }, "-=0.6")
            .from('.title-line', { opacity: 0, y: 80, duration: 1.0, stagger: 0.15, ease: "power3.out" }, "-=0.5")
            .from('.hero-subtitle', { opacity: 0, y: 30, duration: 0.8 }, "-=0.4");

        // Function to calculate and update jar coordinates dynamically based on timeline progress
        function updateJarPosition(progress) {
            if (document.body.classList.contains("is-loading")) return;

            const product = document.querySelector("#mainProductVisual");
            const targetContainer = document.querySelector("#processFinalJarTarget .step-image-wrap");

            if (!product || !targetContainer) return;

            // Calculate perfect dynamic responsive scale when landed in the final 100px target card slot
            const width = window.innerWidth;
            let finalLandedScale = 0.64;
            if (width <= 480) {
                finalLandedScale = 0.94;
            } else if (width <= 768) {
                finalLandedScale = 0.88;
            } else if (width <= 1024) {
                finalLandedScale = 0.78;
            }

            let x, y, scale, rotation;

            // Get the real-time positions
            const targetPos = getTargetPosition("#processFinalJarTarget .step-image-wrap");
            const problemTargetPos = getTargetPosition(".problem-section .pedestal-plate");
            const heroState = getHeroJarState();

            if (progress < 1.0) {
                // Return to body as a fixed element if it's currently nested in the card
                if (product.parentElement !== document.body) {
                    document.body.appendChild(product);
                }

                // =======================================================
                // MOBILE PREMIUM PHYSICS TRAJECTORY (Pedestal Lock + Zig-Zag!)
                // =======================================================
                const heroX = heroState.x;
                const heroY = heroState.y;
                const heroScale = heroState.scale;
                const heroRotation = heroState.rotation;

                // Problem Pedestal coordinates on mobile
                const problemX = problemTargetPos.x;
                const problemY = problemTargetPos.y;
                const problemScale = 0.75;

                // End target landed coordinates
                const landedX = targetPos.x;
                const landedY = targetPos.y;

                if (progress < 0.15) {
                    // 1st Page: "GREEN FORENSICS" (Hero)
                    x = heroX;
                    y = heroY;
                    scale = heroScale;
                    rotation = heroRotation;
                } else if (progress < 0.55) {
                    // Transition: Hero to Problem Pedestal
                    const t = (progress - 0.15) / 0.40;

                    const baseX = heroX + (problemX - heroX) * t;
                    const baseY = heroY + (problemY - heroY) * t;
                    scale = heroScale + (problemScale - heroScale) * t;

                    // Sway and roll on the way down to the pedestal
                    const envelope = Math.sin(t * Math.PI);
                    const zigZagOffset = Math.sin(t * Math.PI * 2.0) * (window.innerWidth * 0.32) * envelope;
                    x = baseX + zigZagOffset;

                    const bounceOffset = Math.abs(Math.cos(t * Math.PI * 2.0)) * -20 * envelope;
                    y = baseY + bounceOffset;

                    rotation = Math.cos(t * Math.PI * 2.0) * 35 * envelope;
                } else if (progress < 0.75) {
                    // 2nd Page: Locked exactly on the Glowing Pedestal center on mobile!
                    x = problemX;
                    y = problemY;
                    scale = problemScale;
                    rotation = -2;
                } else {
                    // Transition: Problem Pedestal to Card 4
                    const t = (progress - 0.75) / 0.25;

                    const baseX = problemX + (landedX - problemX) * t;
                    const baseY = problemY + (landedY - problemY) * t;
                    scale = problemScale + (finalLandedScale - problemScale) * t;

                    // Landing dampening envelope
                    const envelope = (t >= 0.92) ? (1.0 - (t - 0.92) / 0.08) : 1.0;

                    // Perfect 2 sweeps down to Card 4
                    const zigZagOffset = Math.sin(t * Math.PI * 2.0) * (window.innerWidth * 0.35) * envelope;
                    x = baseX + zigZagOffset;

                    const bounceOffset = Math.abs(Math.cos(t * Math.PI * 2.0)) * -20 * envelope;
                    y = baseY + bounceOffset;

                    rotation = -2 + (0 - (-2)) * t + Math.cos(t * Math.PI * 2.0) * 40 * envelope;
                }

                // Dynamic Stacking: Float behind text content (z-index: 10) during the main scroll, 
                // but switch to the absolute front (z-index: 999) as it approaches its landing place (progress >= 0.82)!
                let currentZIndex = (progress < 0.82) ? 10 : 999;

                // Apply dynamic fixed calculations
                gsap.set(product, {
                    position: "fixed",
                    top: "50%",
                    left: "50%",
                    xPercent: -50,
                    yPercent: -50,
                    x: x,
                    y: y,
                    scale: scale,
                    rotation: rotation,
                    opacity: 1,
                    visibility: "visible",
                    zIndex: currentZIndex,
                    overwrite: "auto"
                });
            } else {
                // LANDED & LOCKED STATE: Physically nest the product visual container inside the target card's .step-image-wrap!
                if (!targetContainer.contains(product)) {
                    targetContainer.appendChild(product);
                }

                // Force target container relative positioning and visible overflow
                targetContainer.style.position = "relative";
                targetContainer.style.overflow = "visible";

                // Position it natively centered inside the box
                gsap.set(product, {
                    position: "absolute",
                    top: "50%",
                    left: "50%",
                    xPercent: -50,
                    yPercent: -50,
                    x: 0,
                    y: 0,
                    scale: finalLandedScale,
                    rotation: 0,
                    opacity: 1,
                    visibility: "visible",
                    zIndex: 5
                });
            }
        }

        // Set initial centered states to ensure transform origin is perfect
        gsap.set("#mainProductVisual", {
            xPercent: -50,
            yPercent: -50,
            transformOrigin: "center center"
        });

        // Single ScrollTrigger to drive the jar position dynamically in real-time
        const jarTrigger = ScrollTrigger.create({
            trigger: "body",
            start: "top, bottom",
            end: () => {
                const target = document.querySelector("#processFinalJarTarget");
                if (!target) return "bottom center";

                let top = 0;
                let curr = target;
                while (curr) {
                    top += curr.offsetTop;
                    curr = curr.offsetParent;
                }

                return top + target.offsetHeight / 2 - window.innerHeight / 2;
            },
            scrub: 0.25, // Snappy mobile scrub
            invalidateOnRefresh: true,
            onUpdate: (self) => {
                updateJarPosition(self.progress);
            },
            onRefresh: (self) => {
                updateJarPosition(self.progress);
            }
        });

        // Initialize the jar's position based on the current scroll state immediately
        updateJarPosition(jarTrigger.progress);

        // Secondary Effects
        gsap.to("#maskRect", {
            attr: { height: 200 },
            scrollTrigger: {
                trigger: ".fingerprint-demo",
                start: "top 50%",
                end: "center 20%",
                scrub: 1
            }
        });
    }

    // ===================================
    // SECTION STAGGER & CARD REVEALS
    // ===================================

    function initSectionReveals() {
        gsap.from('.problem-section .section-label, .problem-section .section-title', {
            opacity: 0,
            y: 40,
            duration: 1,
            stagger: 0.15,
            scrollTrigger: {
                trigger: '.problem-section',
                start: 'top 80%',
                toggleActions: 'play none none none'
            }
        });

        gsap.from('.problem-description', {
            opacity: 0,
            x: -40,
            duration: 1.2,
            stagger: 0.25,
            scrollTrigger: {
                trigger: '.problem-content',
                start: 'top 75%',
                toggleActions: 'play none none none'
            }
        });

        gsap.from('.toxic-icon', {
            opacity: 0,
            scale: 0.7,
            y: 30,
            duration: 1,
            stagger: 0.15,
            scrollTrigger: {
                trigger: '.toxic-visual',
                start: 'top 70%',
                toggleActions: 'play none none reverse'
            }
        });

        gsap.from('.eco-icon', {
            opacity: 0,
            scale: 0.7,
            y: 30,
            duration: 1,
            stagger: 0.15,
            scrollTrigger: {
                trigger: '.toxic-visual',
                start: 'center 60%',
                toggleActions: 'play none none reverse'
            }
        });

        gsap.fromTo('.solution-section .section-label, .solution-section .section-title',
            { opacity: 0, y: 40 },
            {
                opacity: 1,
                y: 0,
                duration: 1,
                stagger: 0.15,
                scrollTrigger: {
                    trigger: '.solution-section',
                    start: 'top 80%',
                    toggleActions: 'play none none none'
                }
            }
        );

        gsap.fromTo('.solution-intro',
            { opacity: 0, y: 30 },
            {
                opacity: 1,
                y: 0,
                duration: 1.2,
                scrollTrigger: {
                    trigger: '.solution-intro',
                    start: 'top 80%',
                    toggleActions: 'play none none none'
                }
            }
        );

        gsap.fromTo('.process-step',
            { opacity: 0, y: 40 },
            {
                opacity: 1,
                y: 0,
                duration: 0.8,
                stagger: 0.12,
                ease: "power2.out",
                scrollTrigger: {
                    trigger: '.process-timeline',
                    start: 'top 80%',
                    toggleActions: 'play none none none'
                }
            }
        );

        gsap.fromTo('.process-arrow',
            { opacity: 0, scale: 0 },
            {
                opacity: 1,
                scale: 1,
                duration: 0.6,
                stagger: 0.15,
                scrollTrigger: {
                    trigger: '.process-timeline',
                    start: 'top 75%',
                    toggleActions: 'play none none none'
                }
            }
        );

        gsap.from('.fingerprint-section .section-label, .fingerprint-section .section-title', {
            opacity: 0,
            y: 40,
            duration: 1,
            stagger: 0.15,
            scrollTrigger: {
                trigger: '.fingerprint-section',
                start: 'top 80%',
                toggleActions: 'play none none none'
            }
        });

        gsap.from('.info-card', {
            opacity: 0,
            x: 40,
            duration: 1,
            stagger: 0.2,
            scrollTrigger: {
                trigger: '.demo-info',
                start: 'top 75%',
                toggleActions: 'play none none none'
            }
        });

        gsap.from('.benefits-section .section-label, .benefits-section .section-title', {
            opacity: 0,
            y: 40,
            duration: 1,
            stagger: 0.15,
            scrollTrigger: {
                trigger: '.benefits-section',
                start: 'top 80%',
                toggleActions: 'play none none none'
            }
        });

        gsap.from('.benefit-card', {
            opacity: 0,
            y: 50,
            duration: 1,
            stagger: 0.1,
            ease: "power2.out",
            scrollTrigger: {
                trigger: '.benefits-grid',
                start: 'top 70%',
                toggleActions: 'play none none none'
            }
        });

        gsap.from('.stakeholders-section .section-label, .stakeholders-section .section-title', {
            opacity: 0,
            y: 40,
            duration: 1,
            stagger: 0.15,
            scrollTrigger: {
                trigger: '.stakeholders-section',
                start: 'top 80%',
                toggleActions: 'play none none none'
            }
        });

        gsap.from('.stakeholder-card', {
            opacity: 0,
            scale: 0.85,
            duration: 0.8,
            stagger: 0.08,
            ease: "back.out(1.2)",
            scrollTrigger: {
                trigger: '.stakeholders-network',
                start: 'top 70%',
                toggleActions: 'play none none none'
            }
        });

        gsap.from('.results-section .section-label, .results-section .section-title', {
            opacity: 0,
            y: 40,
            duration: 1,
            stagger: 0.15,
            scrollTrigger: {
                trigger: '.results-section',
                start: 'top 80%',
                toggleActions: 'play none none none'
            }
        });

    }

    // ===================================
    // SMOOTH SCROLL FOR BUTTONS
    // ===================================

    function getHashTarget(hash = window.location.hash) {
        if (!hash) return null;

        try {
            return document.querySelector(hash);
        } catch (error) {
            return null;
        }
    }

    function scrollToElement(target, duration = 1.35, onComplete) {
        const targetY = target.getBoundingClientRect().top + window.pageYOffset;

        gsap.to(window, {
            duration,
            scrollTo: { y: targetY, autoKill: false },
            ease: "power3.inOut",
            onUpdate: () => ScrollTrigger.update(),
            onComplete: () => {
                ScrollTrigger.update();
                if (onComplete) onComplete();
            }
        });
    }

    function initSmoothScroll() {
        const scrollLinks = document.querySelectorAll('.js-scroll-link[href^="#"]');

        scrollLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                const targetId = link.getAttribute('href');
                const target = getHashTarget(targetId);

                if (!target) return;

                e.preventDefault();
                scrollToElement(target, 1.35, () => {
                    history.replaceState(null, '', targetId);
                });
            });
        });
    }

    // ===================================
    // CAMERA CAPTURE CONTROLLER
    // ===================================

    function initCameraCapture() {
        const btnToggleCamera = document.getElementById('btnToggleCamera');
        const btnCapturePhoto = document.getElementById('btnCapturePhoto');
        const btnUploadTrigger = document.getElementById('btnUploadTrigger');
        const cameraFileInput = document.getElementById('cameraFileInput');
        const btnStartEvaluation = document.getElementById('btnStartEvaluation');
        const cameraPlaceholder = document.getElementById('cameraPlaceholder');
        const cameraStream = document.getElementById('cameraStream');
        const photoPreviewCanvas = document.getElementById('photoPreviewCanvas');
        const analysisScannerLine = document.getElementById('analysisScannerLine');
        const evaluationResultContainer = document.getElementById('evaluationResultContainer');

        let stream = null;
        let imageCaptured = false;

        // Toggle camera stream
        if (btnToggleCamera) {
            btnToggleCamera.addEventListener('click', async () => {
                if (stream) {
                    stopCamera();
                } else {
                    try {
                        stream = await navigator.mediaDevices.getUserMedia({
                            video: { facingMode: 'environment' }
                        });
                        cameraStream.srcObject = stream;
                        cameraStream.style.display = 'block';
                        photoPreviewCanvas.style.display = 'none';
                        cameraPlaceholder.style.display = 'none';
                        btnCapturePhoto.style.display = 'inline-flex';
                        btnToggleCamera.innerHTML = `
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line></svg>
                            <span>Stop Camera</span>
                        `;
                        evaluationResultContainer.style.display = 'none';
                        imageCaptured = false;
                        btnStartEvaluation.disabled = true;
                    } catch (err) {
                        console.error("Camera access error:", err);
                        alert("Could not access camera. Please make sure camera permission is granted or upload a file instead.");
                    }
                }
            });
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            cameraStream.style.display = 'none';
            btnCapturePhoto.style.display = 'none';
            btnToggleCamera.innerHTML = `
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                <span>Start Camera</span>
            `;
            if (!imageCaptured) {
                cameraPlaceholder.style.display = 'flex';
            }
        }

        // Capture snapshot from stream
        if (btnCapturePhoto) {
            btnCapturePhoto.addEventListener('click', () => {
                if (stream) {
                    const ctx = photoPreviewCanvas.getContext('2d');
                    photoPreviewCanvas.width = cameraStream.videoWidth || 640;
                    photoPreviewCanvas.height = cameraStream.videoHeight || 480;

                    // Draw current video frame to canvas
                    ctx.drawImage(cameraStream, 0, 0, photoPreviewCanvas.width, photoPreviewCanvas.height);

                    stopCamera();

                    photoPreviewCanvas.style.display = 'block';
                    imageCaptured = true;
                    btnStartEvaluation.disabled = false;
                }
            });
        }

        // Upload trigger button
        if (btnUploadTrigger) {
            btnUploadTrigger.addEventListener('click', () => {
                stopCamera();
                cameraFileInput.click();
            });
        }

        // Handle file selected
        if (cameraFileInput) {
            cameraFileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (event) {
                        const img = new Image();
                        img.onload = function () {
                            const ctx = photoPreviewCanvas.getContext('2d');
                            photoPreviewCanvas.width = img.width;
                            photoPreviewCanvas.height = img.height;
                            ctx.drawImage(img, 0, 0, img.width, img.height);

                            cameraPlaceholder.style.display = 'none';
                            cameraStream.style.display = 'none';
                            photoPreviewCanvas.style.display = 'block';
                            imageCaptured = true;
                            btnStartEvaluation.disabled = false;
                            evaluationResultContainer.style.display = 'none';
                        };
                        img.src = event.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Simulate evaluation
        if (btnStartEvaluation) {
            btnStartEvaluation.addEventListener('click', () => {
                if (!imageCaptured) return;

                // Disable controls during sweep
                btnStartEvaluation.disabled = true;
                btnToggleCamera.disabled = true;
                btnUploadTrigger.disabled = true;
                evaluationResultContainer.style.display = 'none';

                // Show laser scanner
                analysisScannerLine.style.display = 'block';

                // Simulate scan duration
                setTimeout(() => {
                    analysisScannerLine.style.display = 'none';
                    btnToggleCamera.disabled = false;
                    btnUploadTrigger.disabled = false;
                    btnStartEvaluation.disabled = false;

                    // Generate random but high-quality-like scores
                    const ridgeContrast = Math.floor(Math.random() * 15) + 78; // 78 - 92%
                    const minutiaePoints = Math.floor(Math.random() * 20) + 30; // 30 - 50
                    const clarityRating = Math.floor(Math.random() * 12) + 82; // 82 - 93%

                    let ratingText = "Excellent";
                    let feedbackText = "Highly clear ridge details. Calcium carbonate powder adhered perfectly to latent print oils with minimal background noise.";

                    if (clarityRating < 85) {
                        ratingText = "Good Quality";
                        feedbackText = "Clear ridge details visible. Mild background noise detected, but minutiae points are verifiable.";
                    }

                    document.getElementById('metricRidgeContrast').textContent = ridgeContrast + '%';
                    document.getElementById('metricMinutiae').textContent = minutiaePoints;
                    document.getElementById('metricClarityRating').textContent = ratingText + ' (' + clarityRating + '%)';
                    document.getElementById('evaluationFeedback').textContent = feedbackText;

                    // Show results card
                    evaluationResultContainer.style.display = 'flex';
                }, 2500);
            });
        }
    }

    // ===================================
    // BENEFITS ACCORDION
    // ===================================

    function initBenefitsAccordion() {
        const triggers = document.querySelectorAll('.benefit-accordion-trigger');

        triggers.forEach(trigger => {
            trigger.addEventListener('click', () => {
                const item = trigger.closest('.benefit-accordion-item');
                const isOpen = item.classList.contains('is-open');

                // Close all items first (single-open accordion behavior)
                document.querySelectorAll('.benefit-accordion-item.is-open').forEach(openItem => {
                    openItem.classList.remove('is-open');
                });

                // Toggle clicked item
                if (!isOpen) {
                    item.classList.add('is-open');
                }
            });
        });
    }

    // ===================================
    // INITIALIZE ALL SYSTEMS
    // ===================================

    function init() {
        createFloatingParticles();
        initIdleHoverAnimations();
        initCinematicScroll();
        initSectionReveals();
        initSmoothScroll();
        initCameraCapture();
        initBenefitsAccordion();
        ScrollTrigger.refresh();
    }


    // ===================================
    // WINDOW LOAD EVENT
    // ===================================

    window.addEventListener('load', () => {
        init();
        ScrollTrigger.refresh();
    });

    // ===================================
    // WINDOW RESIZE EVENT
    // ===================================

    window.addEventListener('resize', () => {
        ScrollTrigger.refresh();
    });

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        gsap.globalTimeline.timeScale(0.35);
        ScrollTrigger.config({
            autoRefreshEvents: "visibilitychange,DOMContentLoaded,load"
        });
    }

    console.log('%c🌱 Green Forensics - Mobile Showcase Active',
        'color: #2F4F3A; font-size: 14px; font-weight: bold;');

    window.addEventListener("load", () => {
        ScrollTrigger.refresh();
    });

    window.addEventListener("resize", () => {
        ScrollTrigger.refresh();
    });
}
