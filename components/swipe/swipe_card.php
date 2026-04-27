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
    
    // Average rating
    $avg_rating = isset($mentor['avg_rating']) ? round((float)$mentor['avg_rating'], 1) : null;
    
    // Subjects/Expertise
    $subjects = $mentor['subjects'] ?? ['Object-oriented programming', 'Web development', 'Database Design'];
    
    // Bio
    $bio = htmlspecialchars($mentor['bio'] ?? 'Passionate about helping students succeed in their academic journey.');
    
    // External links
    $linkedin_url = htmlspecialchars($mentor['linkedin'] ?? '#');
    $github_url = htmlspecialchars($mentor['github'] ?? '#');
    
    $z_index = 100 - $index;
    ?>

    <?php $subjects_lower = array_map(fn($s) => strtolower(trim($s)), $subjects); ?>
    <div class="swipe-card" data-mentor-id="<?= $mentor_id ?>" data-index="<?= $index ?>" data-subjects="<?= htmlspecialchars(implode('|||', $subjects_lower)) ?>" style="z-index: <?= $z_index ?>;">
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
                    <?php if ($avg_rating !== null): ?>
                    <div class="swipe-card-rating">
                        <?php for ($i = 1; $i <= 5; $i++):
                            $fill = min(1, max(0, $avg_rating - ($i - 1)));
                        ?>
                            <?php if ($fill >= 1): ?>
                                <svg class="star-icon star-filled" viewBox="0 0 24 24" width="24" height="24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                            <?php elseif ($fill > 0): ?>
                                <svg class="star-icon" viewBox="0 0 24 24" width="24" height="24">
                                    <defs>
                                        <clipPath id="half-<?= $mentor_id ?>-<?= $i ?>">
                                            <rect x="0" y="0" width="<?= round($fill * 24, 2) ?>" height="24"/>
                                        </clipPath>
                                    </defs>
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="none" stroke="#d1d5db" stroke-width="1.5"/>
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="#facc15" stroke="#facc15" clip-path="url(#half-<?= $mentor_id ?>-<?= $i ?>)"/>
                                </svg>
                            <?php else: ?>
                                <svg class="star-icon star-empty" viewBox="0 0 24 24" width="24" height="24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
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
        max-height: 640px;
        min-height: 420px;
        margin: 0 auto;
    }

    .swipe-card {
        position: absolute;
        width: 100%;
        max-width: 480px;
        background: var(--card, #ffffff);
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(15,23,42,0.07), 0 1px 3px rgba(15,23,42,0.04);
        border: 1px solid rgba(0,0,0,0.04);
        cursor: grab;
        user-select: none;
        touch-action: pan-y;
        transition: transform 0.25s ease-out, opacity 0.25s ease-out;
        overflow-y: auto;
        overflow-x: hidden;
        max-height: 100%;
        transform-origin: center top;
    }

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
        height: 4px;
        background: linear-gradient(90deg, #3b82f6, #06b6d4);
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
        gap: 24px;
        margin-bottom: 12px;
    }

    /* Avatar */
    .swipe-card-avatar-container {
        flex: 0 0 110px;
        max-width: 110px;
    }

    .swipe-card-avatar {
        width: 110px;
        height: 110px;
        box-sizing: border-box;
        border-radius: 50%;
        object-fit: cover;
        object-position: center;
        border: 3px solid #dbeafe;
        box-shadow: 0 4px 14px rgba(59,130,246,0.12);
        transition: border-color 0.2s;
    }

    .swipe-card-avatar-fallback {
        width: 110px;
        height: 110px;
        box-sizing: border-box;
        border-radius: 50%;
        border: 3px solid #dbeafe;
        box-shadow: 0 4px 14px rgba(59,130,246,0.12);
        background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .avatar-emoji {
        font-size: 3.5rem;
        line-height: 1;
    }

    /* Mentor Name */
    .swipe-card-name {
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
        margin: 10px 0 0;
        text-align: center;
        word-break: break-word;
        letter-spacing: -0.01em;
    }

    /* Info Section */
    .swipe-card-info {
        text-align: right;
        flex: 1;
        min-width: 0;
        padding-top: 4px;
    }

    .swipe-card-year {
        display: flex;
        align-items: baseline;
        justify-content: flex-end;
        gap: 4px;
        margin-bottom: 6px;
    }

    .year-number {
        font-size: 2.5rem;
        font-weight: 800;
        line-height: 1;
        color: #111827;
    }

    .year-suffix {
        font-size: 0.95rem;
        font-weight: 600;
        color: #6b7280;
        margin-right: 4px;
    }

    .year-label {
        font-size: 2.5rem;
        font-weight: 800;
        line-height: 1;
        color: #111827;
    }

    .swipe-card-course {
        font-size: 1.05rem;
        font-weight: 700;
        color: #111827;
        margin: 6px 0 10px;
        padding-bottom: 6px;
        border-bottom: 2px solid #e2e8f0;
        display: inline-block;
        letter-spacing: -0.01em;
    }

    /* Star Rating */
    .swipe-card-rating {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: nowrap;
        gap: 3px;
        margin-top: 4px;
    }

    .star-icon {
        width: 20px;
        height: 20px;
    }

    .star-filled {
        fill: #f59e0b;
        stroke: #f59e0b;
    }

    .star-empty {
        fill: none;
        stroke: #d1d5db;
        stroke-width: 1.5;
    }

    .rating-number {
        font-size: 0.85rem;
        font-weight: 600;
        color: #6b7280;
        margin-left: 4px;
    }

    /* Divider */
    .swipe-card-divider {
        height: 1px;
        background: #e2e8f0;
        margin: 20px 0;
    }

    /* Sections */
    .swipe-card-section {
        margin-bottom: 12px;
    }

    .swipe-card-section-title {
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
        margin: 0 0 12px;
        letter-spacing: -0.01em;
    }

    .swipe-card-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .swipe-card-list-item {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
        font-size: 0.95rem;
        color: #111827;
    }

    .swipe-card-list-item:last-child {
        margin-bottom: 0;
    }

    .bullet {
        width: 8px;
        height: 8px;
        background: #3b82f6;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .swipe-card-link {
        color: #3b82f6;
        text-decoration: none;
        font-size: 0.95rem;
        font-weight: 500;
        transition: color 0.15s;
    }

    .swipe-card-link:hover {
        color: #2563eb;
        text-decoration: underline;
    }

    /* Bio */
    .swipe-card-bio {
        font-size: 0.95rem;
        line-height: 1.55;
        color: #374151;
        margin: 0;
    }

    /* Swipe Indicators */
    .swipe-indicator {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        padding: 10px 22px;
        font-size: 1.3rem;
        font-weight: 800;
        border-radius: 10px;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.15s ease;
        letter-spacing: 0.05em;
    }

    .swipe-like {
        right: 20px;
        background: #22c55e;
        color: #fff;
        border: 2px solid #16a34a;
    }

    .swipe-pass {
        left: 20px;
        background: #ef4444;
        color: #fff;
        border: 2px solid #dc2626;
    }

    /* Action Buttons */
    .swipe-actions {
        display: flex;
        justify-content: center;
        gap: 48px;
        margin-top: 32px;
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
        background: #fef2f2;
        color: #ef4444;
        box-shadow: 0 4px 14px rgba(239,68,68,0.15);
        border: 1.5px solid #fecaca;
    }

    .swipe-btn-pass:hover {
        box-shadow: 0 8px 20px rgba(239,68,68,0.25);
    }

    .swipe-btn-like {
        background: #f0fdf4;
        color: #22c55e;
        box-shadow: 0 4px 14px rgba(34,197,94,0.15);
        border: 1.5px solid #bbf7d0;
    }

    .swipe-btn-like:hover {
        box-shadow: 0 8px 20px rgba(34,197,94,0.25);
    }

    /* Empty State */
    .swipe-empty {
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 400px;
        text-align: center;
        color: #6b7280;
    }

    .swipe-empty.visible {
        display: flex;
    }

    .swipe-empty-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: #dbeafe;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }

    .swipe-empty-icon svg {
        width: 32px;
        height: 32px;
        stroke: #3b82f6;
    }

    .swipe-empty h3 {
        font-size: 1.15rem;
        font-weight: 700;
        margin: 0 0 6px;
        color: #111827;
    }

    .swipe-empty p {
        margin: 0;
        font-size: 0.92rem;
    }

    /* Responsive - Mobile */
    @media (max-width: 480px) {
        .swipe-container {
            height: calc(100vh - 200px);
            height: calc(100dvh - 200px);
            max-height: 580px;
            min-height: 380px;
        }

        .swipe-card-inner {
            padding: 18px 16px 20px;
        }

        .swipe-card-header {
            gap: 14px;
            margin-bottom: 8px;
        }

        .swipe-card-avatar,
        .swipe-card-avatar-fallback {
            width: 80px;
            height: 80px;
        }

        .swipe-card-avatar-container {
            flex-basis: 80px;
            max-width: 80px;
        }

        .swipe-card-name {
            font-size: 0.9rem;
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
            font-size: 0.88rem;
            margin: 4px 0 8px;
            padding-bottom: 4px;
        }

        .star-icon {
            width: 16px;
            height: 16px;
        }

        .swipe-card-divider {
            margin: 14px 0;
        }

        .swipe-card-section-title {
            font-size: 0.92rem;
            margin-bottom: 8px;
        }

        .swipe-card-bio {
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .swipe-card-list-item {
            font-size: 0.9rem;
            gap: 10px;
            margin-bottom: 8px;
        }

        .bullet {
            width: 7px;
            height: 7px;
        }

        .swipe-actions {
            margin-top: 12px;
            gap: 40px;
        }

        .swipe-btn {
            width: 58px;
            height: 58px;
            font-size: 1.5rem;
        }

        .swipe-indicator {
            font-size: 1.1rem;
            padding: 8px 16px;
        }
    }

    /* Very small phones */
    @media (max-width: 380px) {
        .swipe-card-inner {
            padding: 14px 14px 16px;
        }

        .swipe-card-avatar,
        .swipe-card-avatar-fallback {
            width: 68px;
            height: 68px;
        }

        .swipe-card-avatar-container {
            flex-basis: 68px;
            max-width: 68px;
        }

        .year-number,
        .year-label {
            font-size: 1.5rem;
        }

        .swipe-card-course {
            font-size: 0.82rem;
        }
    }

    /* Short screens */
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
            const swipeActions = document.querySelector('.swipe-actions');
            
            if (visibleCards.length === 0) {
                if (emptyState) {
                    emptyState.classList.add('visible');
                }
                if (swipeActions) {
                    swipeActions.style.display = 'none';
                }
            } else {
                if (emptyState) {
                    emptyState.classList.remove('visible');
                }
                if (swipeActions) {
                    swipeActions.style.display = 'flex';
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
