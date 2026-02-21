<?php
/**
 * Swipe Card Component
 * Renders a swipeable card for a mentor profile with dynamic data and graceful fallbacks.
 * Includes styles and JavaScript for swipe interactions.

 */

function render_swipe_card($mentor, $index = 0) {
    $mentor_id = htmlspecialchars($mentor['id'] ?? '');
    $first_name = htmlspecialchars($mentor['first_name'] ?? 'Mentor');
    $last_name = htmlspecialchars($mentor['last_name'] ?? '');
    $full_name = trim("$first_name $last_name");

    // Use mentor's profile picture from query
    $profile_picture = isset($mentor['profile_picture']) ? trim($mentor['profile_picture']) : '';
    $fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=3b82f6&color=fff&size=128';

    // Build avatar URL: uploaded image if present, otherwise generated fallback image
    if ($profile_picture !== '' && $profile_picture !== null) {
        
        $avatar_url = '../' . htmlspecialchars($profile_picture);
    } else {
        $avatar_url = $fallback_avatar;
    }
    
    // Year of study
    $year = intval($mentor['year_of_study'] ?? 3);
    $year_suffix = match($year) {
        1 => 'st',
        2 => 'nd',
        3 => 'rd',
        default => 'th'
    };
    
    // Course/Subject
    $course = htmlspecialchars($mentor['course'] ?? 'Computer Science');
    
    // Rating (1-5)
    $rating = floatval($mentor['rating'] ?? 5);
    
    // Subjects/Expertise
    $subjects = $mentor['subjects'] ?? ['Object-oriented programming', 'Web development', 'Database Design'];
    
    // Bio
    $bio = htmlspecialchars($mentor['bio'] ?? 'Passionate about helping students succeed in their academic journey.');
    
    // External links
    $linkedin_url = htmlspecialchars($mentor['linkedin'] ?? '#');
    $github_url = htmlspecialchars($mentor['github'] ?? '#');
    
    $z_index = 100 - $index;
    ?>

    <div class="swipe-card" data-mentor-id="<?= $mentor_id ?>" data-index="<?= $index ?>" style="z-index: <?= $z_index ?>;">
        <div class="swipe-card-accent"></div>
        <div class="swipe-card-inner">
            <!-- Header: Profile Picture + Info side by side -->
            <div class="swipe-card-header">
                <!-- Profile Picture (with automatic fallback URL) -->
                <div class="swipe-card-avatar-container">
                    <img src="<?php echo $avatar_url; ?>" alt="<?php echo $full_name; ?>" class="swipe-card-avatar" onerror="this.onerror=null; this.src='<?php echo $fallback_avatar; ?>';">
                    <h2 class="swipe-card-name"><?php echo $full_name; ?></h2>
                </div>

                <!-- Year and Course -->
                <div class="swipe-card-info">
                    <div class="swipe-card-year">
                        <span class="year-number"><?php echo $year; ?></span><sup class="year-suffix"><?php echo $year_suffix; ?></sup>
                        <span class="year-label">Year</span>
                    </div>
                    <h3 class="swipe-card-course"><?php echo $course; ?></h3>
                    
                    <!-- Star Rating -->
                    <div class="swipe-card-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <svg class="star-icon <?php echo $i <= floor($rating) ? 'star-filled' : 'star-empty'; ?>" viewBox="0 0 24 24" width="24" height="24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="swipe-card-divider"></div>

            <!-- Bio -->
            <div class="swipe-card-section">
                <h4 class="swipe-card-section-title">About me:</h4>
                <p class="swipe-card-bio"><?php echo $bio; ?></p>
            </div>

            <div class="swipe-card-divider"></div>

            <!-- External Links -->
            <?php 
            $has_linkedin = !empty($linkedin_url) && $linkedin_url !== '#';
            $has_github = !empty($github_url) && $github_url !== '#';
            if ($has_linkedin || $has_github): 
            ?>
            <div class="swipe-card-section">
                <h4 class="swipe-card-section-title">External links:</h4>
                <ul class="swipe-card-list">
                    <?php if ($has_linkedin): ?>
                    <li class="swipe-card-list-item">
                        <span class="bullet"></span>
                        <a href="<?php echo $linkedin_url; ?>" class="swipe-card-link" target="_blank" rel="noopener">LinkedIn</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($has_github): ?>
                    <li class="swipe-card-list-item">
                        <span class="bullet"></span>
                        <a href="<?php echo $github_url; ?>" class="swipe-card-link" target="_blank" rel="noopener">GitHub</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <!-- Swipe indicators -->
        <div class="swipe-indicator swipe-like">LIKE</div>
        <div class="swipe-indicator swipe-pass">PASS</div>
    </div>
    <?php
}

/**
 * Render swipe card styles (include once per page)
 */
function render_swipe_card_styles() {
    ?>
    <style>
    /* Swipe Card Container */
    .swipe-container {
        position: relative;
        width: 100%;
        max-width: 480px;
        height: calc(100vh - 240px);
        height: calc(100dvh - 240px);
        max-height: 500px;
        min-height: 380px;
        margin: 0 auto;
    }

    .swipe-card {
        position: absolute;
        width: 100%;
        max-width: 480px;
        background: var(--card, #ffffff);
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        cursor: grab;
        user-select: none;
        touch-action: pan-y;
        transition: transform 0.25s ease-out, opacity 0.25s ease-out;
        overflow: hidden;
        transform-origin: center top;
    }

    /* Stacked card effects - cards behind are slightly larger to peek out */
    .swipe-card.stack-1 {
        transform: scale(1.02) translateY(10px);
        opacity: 0.9;
        cursor: default;
    }

    .swipe-card.stack-2 {
        transform: scale(1.04) translateY(20px);
        opacity: 0.75;
        cursor: default;
    }

    .swipe-card.stack-3 {
        transform: scale(1.06) translateY(30px);
        opacity: 0.6;
        cursor: default;
    }

    .swipe-card-accent {
        height: 6px;
        background: linear-gradient(90deg, #00d4ff, #00b8d4);
        width: 100%;
    }

    .swipe-card:active {
        cursor: grabbing;
    }

    .swipe-card.swiping {
        transition: none;
    }

    .swipe-card.animating {
        transition: transform 0.3s ease-out, opacity 0.3s ease-out;
    }

    .swipe-card-inner {
        padding: 24px;
    }

    /* Header - Avatar + Info side by side */
    .swipe-card-header {
        display: flex;
        align-items: flex-start;
        gap: 32px;
        margin-bottom: 12px;
    }

    /* Avatar */
    .swipe-card-avatar-container {
        flex-shrink: 0;
    }

    .swipe-card-avatar {
        width: 128px;
        height: 128px;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid #00d4ff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .swipe-card-avatar-fallback {
        width: 128px;
        height: 128px;
        border-radius: 50%;
        border: 5px solid #fff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        background: linear-gradient(135deg, #f472b6 0%, #ef4444 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .avatar-emoji {
        font-size: 4rem;
        line-height: 1;
    }

    /* Mentor Name */
    .swipe-card-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: #111827;
        margin: 10px 0 0;
        text-align: center;
    }

    /* Info Section */
    .swipe-card-info {
        text-align: right;
        flex: 1;
        padding-top: 8px;
    }

    .swipe-card-year {
        display: flex;
        align-items: baseline;
        justify-content: flex-end;
        gap: 4px;
        margin-bottom: 6px;
    }

    .year-number {
        font-size: 2.75rem;
        font-weight: 700;
        line-height: 1;
        color: #111827;
    }

    .year-suffix {
        font-size: 1rem;
        font-weight: 600;
        color: #111827;
        margin-right: 6px;
    }

    .year-label {
        font-size: 2.75rem;
        font-weight: 700;
        line-height: 1;
        color: #111827;
    }

    .swipe-card-course {
        font-size: 1.15rem;
        font-weight: 600;
        color: #111827;
        margin: 8px 0 12px;
        border-bottom: 2px solid #111827;
        padding-bottom: 6px;
        display: inline-block;
    }

    /* Star Rating */
    .swipe-card-rating {
        display: flex;
        justify-content: flex-end;
        gap: 6px;
    }

    .star-icon {
        width: 28px;
        height: 28px;
    }

    .star-filled {
        fill: #facc15;
        stroke: #facc15;
    }

    .star-empty {
        fill: none;
        stroke: #d1d5db;
        stroke-width: 1.5;
    }

    /* Divider */
    .swipe-card-divider {
        height: 4px;
        background: #d1d5db;
        margin: 24px 0;
    }

    /* Sections */
    .swipe-card-section {
        margin-bottom: 12px;
    }

    .swipe-card-section-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: #111827;
        margin: 0 0 18px;
    }

    .swipe-card-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .swipe-card-list-item {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 14px;
        font-size: 1.2rem;
        color: #111827;
    }

    .swipe-card-list-item:last-child {
        margin-bottom: 0;
    }

    .bullet {
        width: 14px;
        height: 14px;
        background: #111827;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .swipe-card-link {
        color: var(--accent, #3b82f6);
        text-decoration: underline;
        font-size: 1.2rem;
    }

    .swipe-card-link:hover {
        color: #2563eb;
    }

    /* Bio */
    .swipe-card-bio {
        font-size: 1.1rem;
        line-height: 1.5;
        color: #374151;
        margin: 0;
    }

    /* Swipe Indicators */
    .swipe-indicator {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        padding: 12px 24px;
        font-size: 1.5rem;
        font-weight: 700;
        border-radius: 8px;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.15s ease;
    }

    .swipe-like {
        right: 24px;
        background: #22c55e;
        color: #fff;
        border: 3px solid #16a34a;
    }

    .swipe-pass {
        left: 24px;
        background: #ef4444;
        color: #fff;
        border: 3px solid #dc2626;
    }

    /* Action Buttons */
    .swipe-actions {
        display: flex;
        justify-content: center;
        gap: 48px;
        margin-top: 10px;
        margin-bottom: 0; /* Container handles bottom spacing */
    }

    .swipe-btn {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .swipe-btn:hover {
        transform: scale(1.1);
    }

    .swipe-btn:active {
        transform: scale(0.95);
    }

    .swipe-btn-pass {
        background: #fee2e2;
        color: #ef4444;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
    }

    .swipe-btn-pass:hover {
        box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3);
    }

    .swipe-btn-like {
        background: #dcfce7;
        color: #22c55e;
        box-shadow: 0 4px 12px rgba(34, 197, 94, 0.2);
    }

    .swipe-btn-like:hover {
        box-shadow: 0 6px 16px rgba(34, 197, 94, 0.3);
    }

    /* Empty State */
    .swipe-empty {
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 400px;
        text-align: center;
        color: var(--muted, #6b7280);
    }

    .swipe-empty.visible {
        display: flex;
    }

    .swipe-empty-icon {
        font-size: 4rem;
        margin-bottom: 16px;
    }

    .swipe-empty h3 {
        font-size: 1.25rem;
        margin: 0 0 8px;
        color: #111827;
    }

    .swipe-empty p {
        margin: 0;
        font-size: 0.95rem;
    }

    /* Responsive - Mobile phones */
    @media (max-width: 480px) {
        .swipe-container {
            height: calc(100vh - 200px);
            height: calc(100dvh - 200px);
            max-height: 460px;
            min-height: 340px;
        }

        .swipe-card {
            border-radius: 14px;
        }

        .swipe-card-inner {
            padding: 16px 18px 20px;
        }

        .swipe-card-header {
            gap: 14px;
            margin-bottom: 8px;
        }

        .swipe-card-avatar,
        .swipe-card-avatar-fallback {
            width: 80px;
            height: 80px;
            border-width: 3px;
        }

        .swipe-card-name {
            font-size: 0.95rem;
            margin-top: 6px;
        }

        .avatar-emoji {
            font-size: 2.2rem;
        }

        .year-number,
        .year-label {
            font-size: 1.75rem;
        }

        .year-suffix {
            font-size: 0.7rem;
        }

        .swipe-card-course {
            font-size: 0.9rem;
            margin: 4px 0 8px;
            padding-bottom: 4px;
        }

        .star-icon {
            width: 18px;
            height: 18px;
        }

        .swipe-card-rating {
            gap: 3px;
        }

        .swipe-card-divider {
            margin: 14px 0;
            height: 3px;
        }

        .swipe-card-section-title {
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .swipe-card-bio {
            font-size: 0.95rem;
            line-height: 1.4;
        }

        .swipe-card-list-item {
            font-size: 1rem;
            gap: 10px;
            margin-bottom: 10px;
        }

        .bullet {
            width: 10px;
            height: 10px;
        }

        .swipe-actions {
            margin-top: 8px;
            gap: 40px;
        }

        .swipe-btn {
            width: 60px;
            height: 60px;
            font-size: 1.6rem;
        }

        .swipe-indicator {
            font-size: 1.2rem;
            padding: 10px 18px;
        }
    }

    /* Very small phones */
    @media (max-width: 380px) {
        .swipe-card-inner {
            padding: 14px 16px 18px;
        }

        .swipe-card-avatar,
        .swipe-card-avatar-fallback {
            width: 70px;
            height: 70px;
        }

        .year-number,
        .year-label {
            font-size: 1.5rem;
        }

        .swipe-card-course {
            font-size: 0.85rem;
        }

        .swipe-card-bio {
            font-size: 0.9rem;
        }
    }

    /* Short screens - reduce vertical spacing */
    @media (max-height: 700px) {
        .swipe-container {
            min-height: 320px;
        }

        .swipe-card-inner {
            padding: 14px 16px 16px;
        }

        .swipe-card-divider {
            margin: 10px 0;
        }

        .swipe-card-section {
            margin-bottom: 6px;
        }

        .swipe-card-section-title {
            margin-bottom: 8px;
        }
    }
    </style>
    <?php
}

/**
 * Render swipe card JavaScript (include once per page)
 */
function render_swipe_card_scripts() {
    ?>
    <script>
    (function() {
        const container = document.querySelector('.swipe-container');
        if (!container) return;

        let cards = Array.from(container.querySelectorAll('.swipe-card'));
        let currentCard = null;
        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let isDragging = false;

        const SWIPE_THRESHOLD = 100;
        const ROTATION_FACTOR = 0.1;

        function initCards() {
            cards = Array.from(container.querySelectorAll('.swipe-card:not(.removed)'));
            cards.forEach((card, index) => {
                card.style.zIndex = 100 - index;
                // Remove all stack classes first
                card.classList.remove('stack-1', 'stack-2', 'stack-3');
                // Apply stack class based on position (not for the top card)
                if (index === 0) {
                    attachListeners(card);
                    card.style.transform = '';
                    card.style.opacity = '';
                } else if (index === 1) {
                    card.classList.add('stack-1');
                } else if (index === 2) {
                    card.classList.add('stack-2');
                } else if (index >= 3) {
                    card.classList.add('stack-3');
                }
            });
            updateEmptyState();
        }

        function attachListeners(card) {
            card.addEventListener('mousedown', onDragStart);
            card.addEventListener('touchstart', onDragStart, { passive: true });
        }

        function detachListeners(card) {
            card.removeEventListener('mousedown', onDragStart);
            card.removeEventListener('touchstart', onDragStart);
        }

        function onDragStart(e) {
            if (e.target.closest('a')) return; // Don't drag when clicking links
            
            currentCard = e.currentTarget;
            isDragging = true;
            currentCard.classList.add('swiping');

            const point = e.touches ? e.touches[0] : e;
            startX = point.clientX;
            startY = point.clientY;
            currentX = 0;

            document.addEventListener('mousemove', onDragMove);
            document.addEventListener('mouseup', onDragEnd);
            document.addEventListener('touchmove', onDragMove, { passive: false });
            document.addEventListener('touchend', onDragEnd);
        }

        function onDragMove(e) {
            if (!isDragging || !currentCard) return;

            const point = e.touches ? e.touches[0] : e;
            currentX = point.clientX - startX;
            const currentY = point.clientY - startY;

            // Prevent vertical scrolling when swiping horizontally
            if (Math.abs(currentX) > Math.abs(currentY)) {
                e.preventDefault && e.preventDefault();
            }

            const rotation = currentX * ROTATION_FACTOR;
            currentCard.style.transform = `translateX(${currentX}px) rotate(${rotation}deg)`;

            // Update indicators
            const likeIndicator = currentCard.querySelector('.swipe-like');
            const passIndicator = currentCard.querySelector('.swipe-pass');
            
            if (currentX > 50) {
                likeIndicator.style.opacity = Math.min((currentX - 50) / 50, 1);
                passIndicator.style.opacity = 0;
            } else if (currentX < -50) {
                passIndicator.style.opacity = Math.min((-currentX - 50) / 50, 1);
                likeIndicator.style.opacity = 0;
            } else {
                likeIndicator.style.opacity = 0;
                passIndicator.style.opacity = 0;
            }
        }

        function onDragEnd() {
            if (!isDragging || !currentCard) return;

            document.removeEventListener('mousemove', onDragMove);
            document.removeEventListener('mouseup', onDragEnd);
            document.removeEventListener('touchmove', onDragMove);
            document.removeEventListener('touchend', onDragEnd);

            currentCard.classList.remove('swiping');
            isDragging = false;

            if (Math.abs(currentX) > SWIPE_THRESHOLD) {
                const direction = currentX > 0 ? 'like' : 'pass';
                completeSwipe(currentCard, direction);
            } else {
                resetCard(currentCard);
            }

            currentCard = null;
        }

        function completeSwipe(card, direction) {
            const mentorId = card.dataset.mentorId;
            const flyX = direction === 'like' ? window.innerWidth : -window.innerWidth;
            const rotation = direction === 'like' ? 30 : -30;

            card.classList.add('animating');
            card.style.transform = `translateX(${flyX}px) rotate(${rotation}deg)`;
            card.style.opacity = '0';

            // Trigger callback
            if (typeof window.onMentorSwipe === 'function') {
                window.onMentorSwipe(mentorId, direction);
            }

            setTimeout(() => {
                card.classList.add('removed');
                card.style.display = 'none';
                detachListeners(card);
                initCards();
            }, 300);
        }

        function resetCard(card) {
            card.classList.add('animating');
            card.style.transform = '';
            
            const likeIndicator = card.querySelector('.swipe-like');
            const passIndicator = card.querySelector('.swipe-pass');
            likeIndicator.style.opacity = 0;
            passIndicator.style.opacity = 0;

            setTimeout(() => {
                card.classList.remove('animating');
            }, 300);
        }

        function updateEmptyState() {
            const emptyState = container.querySelector('.swipe-empty');
            const visibleCards = container.querySelectorAll('.swipe-card:not(.removed)');
            
            if (emptyState) {
                if (visibleCards.length === 0) {
                    emptyState.classList.add('visible');
                } else {
                    emptyState.classList.remove('visible');
                }
            }
        }

        // Button handlers
        window.swipePass = function() {
            const topCard = container.querySelector('.swipe-card:not(.removed)');
            if (topCard) {
                completeSwipe(topCard, 'pass');
            }
        };

        window.swipeLike = function() {
            const topCard = container.querySelector('.swipe-card:not(.removed)');
            if (topCard) {
                completeSwipe(topCard, 'like');
            }
        };

        // Initialize
        initCards();
    })();
    </script>
    <?php
}
?>
