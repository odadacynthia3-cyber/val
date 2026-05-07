// hearts.js
// Handles typewriter effect, reveal interaction, music fade-in, and synced romantic visuals.

(function () {
    const body = document.body;
    const ambientHeartLayer = document.getElementById('ambientHeartLayer');
    const sparkleLayer = document.getElementById('sparkleLayer');
    const heartContainer = document.getElementById('heartContainer');
    const revealBtn = document.getElementById('revealBtn');
    const revealText = document.getElementById('revealText');
    const ownMessageCta = document.getElementById('ownMessageCta');
    const bgMusic = document.getElementById('bgMusic');
    const musicToggle = document.getElementById('musicToggle');

    let heartsStarted = false;
    let musicStarted = false;
    let fastHeartMode = false;
    let heartTimer = null;
    let fadeInterval = null;
    let ambientHeartTimer = null;
    let sparkleTimer = null;

    // Floating heart generation (background):
    // creates soft hearts across random x-positions with varied speed/size.
    function createAmbientHeart() {
        if (!ambientHeartLayer) return;

        const heart = document.createElement('span');
        heart.className = 'ambient-heart';
        heart.innerHTML = '&#10084;';

        const duration = 11 + Math.random() * 9;
        const size = 10 + Math.random() * 18;
        heart.style.left = (Math.random() * 100) + 'vw';
        heart.style.fontSize = size + 'px';
        heart.style.opacity = (0.15 + Math.random() * 0.28).toString();
        heart.style.animationDuration = duration + 's';

        ambientHeartLayer.appendChild(heart);
        setTimeout(function () {
            heart.remove();
        }, duration * 1000 + 220);
    }

    // Sparkle effect:
    // tiny glowing particles gently drift upward and flicker for depth.
    function createSparkle() {
        if (!sparkleLayer) return;

        const dot = document.createElement('span');
        dot.className = 'sparkle-dot';

        const duration = 6 + Math.random() * 7;
        const delay = Math.random() * 2;
        const size = 3 + Math.random() * 4;

        dot.style.left = (Math.random() * 100) + 'vw';
        dot.style.top = (45 + Math.random() * 55) + 'vh';
        dot.style.width = size + 'px';
        dot.style.height = size + 'px';
        dot.style.animationDuration = duration + 's, ' + (2 + Math.random() * 2.6) + 's';
        dot.style.animationDelay = delay + 's, 0s';
        dot.style.opacity = (0.26 + Math.random() * 0.4).toString();

        sparkleLayer.appendChild(dot);
        setTimeout(function () {
            dot.remove();
        }, (duration + delay) * 1000 + 260);
    }

    function startAmbientBackground() {
        const isMobile = window.innerWidth <= 640;
        const heartMin = isMobile ? 1200 : 760;
        const heartRange = isMobile ? 700 : 480;
        const sparkleMin = isMobile ? 900 : 560;
        const sparkleRange = isMobile ? 500 : 340;

        function loopAmbientHearts() {
            createAmbientHeart();
            if (!isMobile && Math.random() > 0.62) createAmbientHeart();
            ambientHeartTimer = setTimeout(loopAmbientHearts, heartMin + Math.random() * heartRange);
        }

        function loopSparkles() {
            createSparkle();
            if (Math.random() > 0.55) createSparkle();
            sparkleTimer = setTimeout(loopSparkles, sparkleMin + Math.random() * sparkleRange);
        }

        loopAmbientHearts();
        loopSparkles();
    }

    function createHeart() {
        if (!heartContainer) return;

        const heart = document.createElement('span');
        heart.className = 'heart';
        heart.innerHTML = '&#10084;';

        const durationBase = fastHeartMode ? 2.6 : 4.4;
        const durationRange = fastHeartMode ? 2.4 : 4.2;
        const duration = durationBase + Math.random() * durationRange;

        heart.style.left = (Math.random() * 100) + 'vw';
        heart.style.fontSize = (12 + Math.random() * 20) + 'px';
        heart.style.animationDuration = duration + 's';
        heart.style.opacity = (0.22 + Math.random() * 0.55).toString();

        heartContainer.appendChild(heart);
        setTimeout(function () {
            heart.remove();
        }, duration * 1000 + 220);
    }

    // Synced heart animation changes: hearts start on reveal and get faster when music starts.
    function startHearts() {
        if (!heartContainer || heartsStarted) return;

        heartsStarted = true;
        heartContainer.classList.add('active');

        function spawnLoop() {
            const interval = fastHeartMode
                ? 170 + Math.random() * 120
                : 340 + Math.random() * 180;

            createHeart();
            if (Math.random() > (fastHeartMode ? 0.3 : 0.58)) {
                createHeart();
            }

            heartTimer = setTimeout(spawnLoop, interval);
        }

        spawnLoop();
    }

    // Audio setup + fade-in logic:
    // starts at volume 0 and smoothly fades to 0.3 over ~2.4 seconds.
    function startMusicWithFadeIn() {
        if (!bgMusic || musicStarted) return;

        musicStarted = true;
        bgMusic.volume = 0;
        bgMusic.loop = true;

        // User-click triggered play() keeps it compliant with autoplay policies.
        bgMusic.play().then(function () {
            let step = 0;
            const steps = 24;
            const maxVolume = 0.3;
            const tickMs = 100; // 24 * 100ms = 2400ms fade-in.

            clearInterval(fadeInterval);
            fadeInterval = setInterval(function () {
                step += 1;
                bgMusic.volume = Math.min(maxVolume, (step / steps) * maxVolume);

                if (step >= steps) {
                    clearInterval(fadeInterval);
                }
            }, tickMs);

            // Background glow effect + faster hearts once music starts.
            body.classList.add('romantic-glow');
            if (heartContainer) {
                heartContainer.classList.add('music-on');
            }
            fastHeartMode = true;

            // Show the floating music control only after playback has started.
            if (musicToggle) {
                musicToggle.classList.remove('hidden');
                musicToggle.classList.add('show');
                musicToggle.textContent = '🎵 Pause Music';
                musicToggle.setAttribute('aria-label', 'Pause music');
            }
        }).catch(function () {
            musicStarted = false;
        });
    }

    // Pause/play toggle logic for the floating music button.
    function bindMusicToggle() {
        if (!bgMusic || !musicToggle) return;

        musicToggle.addEventListener('click', function () {
            if (bgMusic.paused) {
                bgMusic.play().then(function () {
                    musicToggle.classList.remove('paused');
                    musicToggle.textContent = '🎵 Pause Music';
                    musicToggle.setAttribute('aria-label', 'Pause music');
                }).catch(function () {});
            } else {
                bgMusic.pause();
                musicToggle.classList.add('paused');
                musicToggle.textContent = '🎵 Play Music';
                musicToggle.setAttribute('aria-label', 'Play music');
            }
        });
    }

    // Typewriter effect for the secret message.
    const typedMessage = document.getElementById('typedMessage');
    if (typedMessage) {
        const fullText = typedMessage.getAttribute('data-message') || '';
        let i = 0;
        const speed = 32;

        function typeStep() {
            if (i <= fullText.length) {
                typedMessage.textContent = fullText.slice(0, i);
                i += 1;
                setTimeout(typeStep, speed);
            }
        }

        typeStep();
    }

    // Keep existing reveal text behavior and add synced music/animation actions.
    if (revealBtn && revealText) {
        revealBtn.addEventListener('click', function () {
            revealText.classList.remove('hidden');
            revealText.classList.add('show');

            startHearts();
            startMusicWithFadeIn();

            // New section animation: reveal CTA after sender message appears.
            if (ownMessageCta) {
                setTimeout(function () {
                    ownMessageCta.classList.remove('hidden');
                    setTimeout(function () {
                        ownMessageCta.classList.add('show');
                    }, 20);
                }, 350);
            }

            revealBtn.disabled = true;
            revealBtn.textContent = 'Sender Revealed';
        });
    }

    bindMusicToggle();
    startAmbientBackground();

    window.addEventListener('beforeunload', function () {
        clearTimeout(heartTimer);
        clearTimeout(ambientHeartTimer);
        clearTimeout(sparkleTimer);
        clearInterval(fadeInterval);
    });
})();
