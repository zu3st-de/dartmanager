{{-- ============================================================
   SIMPLE DARTBOARD SPIN ANIMATION
   Eine einfache, mobile-freundliche Dartscheibe
============================================================ --}}

<div class="wheel-stage">
    <div class="wheel-card">
        <div class="wheel-pointer"></div>
        <img src="/images/dartboard.svg" alt="Dartscheibe" class="dartboard-image" />
        <div class="wheel-label">Teilnehmer werden ausgelost</div>
    </div>
</div>

@push('styles')
    <style>
        .wheel-stage {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            min-height: 420px;
            padding: 20px;
            background: radial-gradient(circle at center, rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 1) 55%);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: inset 0 0 60px rgba(0, 0, 0, 0.25);
        }

        .wheel-card {
            position: relative;
            width: min(360px, 100%);
            padding: 24px;
            display: grid;
            place-items: center;
            background: rgba(31, 41, 55, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            backdrop-filter: blur(12px);
        }

        .wheel-pointer {
            position: absolute;
            top: 14px;
            left: 50%;
            width: 0;
            height: 0;
            border-left: 18px solid transparent;
            border-right: 18px solid transparent;
            border-bottom: 28px solid #fbbf24;
            transform: translateX(-50%);
            filter: drop-shadow(0 8px 12px rgba(0, 0, 0, 0.45));
        }

        .dartboard-image {
            width: 280px;
            height: 280px;
            border-radius: 50%;
            display: block;
            object-fit: contain;
            box-shadow: inset 0 0 60px rgba(0, 0, 0, 0.35), 0 14px 40px rgba(0, 0, 0, 0.35);
            transition: transform 0.15s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .wheel-label {
            margin-top: 24px;
            font-size: 1rem;
            font-weight: 700;
            color: #f8fafc;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            text-align: center;
        }

        @media (max-width: 640px) {
            .dartboard-image {
                width: 250px;
                height: 250px;
            }

            .wheel-card {
                padding: 18px;
            }

            .wheel-label {
                font-size: 0.95rem;
                margin-top: 18px;
            }

            .wheel-pointer {
                top: 10px;
                border-left-width: 16px;
                border-right-width: 16px;
                border-bottom-width: 24px;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        const dartboard = document.querySelector('.dartboard-image');
        const SEGMENT_ANGLE = 18;
        let currentRotation = 0;

        async function spinWheel() {
            while (true) {
                // Total spin: 720-1080 degrees (2-3 full rotations)
                const totalSpin = 720 + Math.random() * 360;
                const totalSegments = Math.round(totalSpin / SEGMENT_ANGLE);

                const spinStartTime = performance.now();
                const spinDuration = 4500; // 4.5 seconds total spin

                // Smooth deceleration with easing
                while (performance.now() - spinStartTime < spinDuration) {
                    const elapsed = (performance.now() - spinStartTime) / spinDuration;

                    // Smooth deceleration: fast start, smooth end
                    // easeOutQuart for smooth arrival
                    const eased = 1 - Math.pow(1 - elapsed, 4);

                    currentRotation = eased * totalSpin;
                    dartboard.style.transform = `rotate(${currentRotation}deg)`;

                    await new Promise(r => requestAnimationFrame(r));
                }

                // Final snap to nearest segment
                currentRotation = Math.round(currentRotation / SEGMENT_ANGLE) * SEGMENT_ANGLE;
                dartboard.style.transform = `rotate(${currentRotation}deg)`;

                // Pause before next spin
                await new Promise(r => setTimeout(r, 2000));
            }
        }

        document.addEventListener('DOMContentLoaded', spinWheel);
    </script>
@endpush
