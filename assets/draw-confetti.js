(() => {
    const canvas = document.getElementById('drawConfetti');
    const winnerCard = document.getElementById('drawWinnerCard');
    const digitDisplay = document.getElementById('drawDigitDisplay');
    const digits = Array.from(document.querySelectorAll('.draw-digit'));
    if (!canvas || !winnerCard) return;

    let celebrated = !winnerCard.hidden;
    let audioContext = null;
    let shuffleTimer = null;
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    const prepareAudio = () => {
        if (!AudioContextClass) return null;
        if (!audioContext) audioContext = new AudioContextClass();
        if (audioContext.state === 'suspended') audioContext.resume().catch(() => {});
        return audioContext;
    };
    const tone = (frequency, duration, volume = .04, type = 'sine', delay = 0) => {
        const context = prepareAudio();
        if (!context) return;
        const startsAt = context.currentTime + delay;
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = type;
        oscillator.frequency.setValueAtTime(frequency, startsAt);
        gain.gain.setValueAtTime(.0001, startsAt);
        gain.gain.exponentialRampToValueAtTime(volume, startsAt + .012);
        gain.gain.exponentialRampToValueAtTime(.0001, startsAt + duration);
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start(startsAt);
        oscillator.stop(startsAt + duration + .03);
    };
    const playShuffleTick = () => tone(170 + Math.random() * 150, .055, .018, 'square');
    const playDigitLocked = lockedCount => {
        tone(420 + lockedCount * 90, .13, .05, 'triangle');
        tone(620 + lockedCount * 110, .16, .035, 'sine', .07);
    };
    const playWinnerFanfare = () => {
        [523.25, 659.25, 783.99, 1046.5].forEach((frequency, index) => {
            tone(frequency, .3, .065, 'triangle', index * .16);
        });
        tone(523.25, .75, .035, 'sine', .68);
        tone(783.99, .75, .035, 'sine', .68);
        tone(1046.5, .75, .045, 'sine', .68);
    };

    document.addEventListener('pointerdown', prepareAudio, {once: true, capture: true});
    if (digitDisplay) {
        new MutationObserver(() => {
            const spinning = digitDisplay.classList.contains('is-spinning');
            if (spinning && !shuffleTimer) shuffleTimer = window.setInterval(playShuffleTick, 145);
            if (!spinning && shuffleTimer) {
                window.clearInterval(shuffleTimer);
                shuffleTimer = null;
            }
        }).observe(digitDisplay, {attributes: true, attributeFilter: ['class']});
    }
    digits.forEach(digit => {
        new MutationObserver(() => {
            if (digit.classList.contains('locked') && digit.dataset.soundLocked !== '1') {
                digit.dataset.soundLocked = '1';
                playDigitLocked(digits.filter(item => item.classList.contains('locked')).length);
            }
        }).observe(digit, {attributes: true, attributeFilter: ['class']});
    });

    const launch = () => {
        if (celebrated || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        celebrated = true;
        const context = canvas.getContext('2d');
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        const colors = ['#dc2626', '#f59e0b', '#facc15', '#22c55e', '#2563eb', '#a855f7', '#ffffff'];
        const pieces = Array.from({length: 180}, () => ({
            x: Math.random() * window.innerWidth,
            y: -20 - Math.random() * window.innerHeight * .35,
            width: 5 + Math.random() * 7,
            height: 8 + Math.random() * 9,
            speedX: -3 + Math.random() * 6,
            speedY: 3 + Math.random() * 5,
            rotation: Math.random() * Math.PI,
            spin: -.18 + Math.random() * .36,
            color: colors[Math.floor(Math.random() * colors.length)]
        }));
        const resize = () => {
            canvas.width = window.innerWidth * ratio;
            canvas.height = window.innerHeight * ratio;
            canvas.style.width = window.innerWidth + 'px';
            canvas.style.height = window.innerHeight + 'px';
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
        };
        resize();
        canvas.classList.add('active');
        const startedAt = performance.now();
        const draw = now => {
            context.clearRect(0, 0, window.innerWidth, window.innerHeight);
            pieces.forEach(piece => {
                piece.x += piece.speedX;
                piece.y += piece.speedY;
                piece.speedY += .035;
                piece.rotation += piece.spin;
                context.save();
                context.translate(piece.x, piece.y);
                context.rotate(piece.rotation);
                context.fillStyle = piece.color;
                context.fillRect(-piece.width / 2, -piece.height / 2, piece.width, piece.height);
                context.restore();
            });
            if (now - startedAt < 6500) requestAnimationFrame(draw);
            else {
                context.clearRect(0, 0, window.innerWidth, window.innerHeight);
                canvas.classList.remove('active');
            }
        };
        requestAnimationFrame(draw);
    };

    new MutationObserver(() => {
        if (!winnerCard.hidden) {
            playWinnerFanfare();
            launch();
        }
    }).observe(winnerCard, {attributes: true, attributeFilter: ['hidden']});
})();
