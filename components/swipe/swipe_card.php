<?php
/**
 * Swipe Card Component
 * 
 * Usage: Include this file and call render_swipe_card($mentor) 
 * Required: $mentor array with keys: id, first_name, last_name, profile_picture, year_of_study, course, rating, subjects, linkedin_url, github_url
 */

function render_swipe_card($mentor, $index = 0) {
    $mentor_id = htmlspecialchars($mentor['id'] ?? '');
    $first_name = htmlspecialchars($mentor['first_name'] ?? 'Mentor');
    $last_name = htmlspecialchars($mentor['last_name'] ?? '');
    $full_name = trim("$first_name $last_name");
    
    // Profile picture with fallback
    $profile_picture = $mentor['profile_picture'] ?? '';
    $fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=3b82f6&color=fff&size=128';
    $avatar_url = !empty($profile_picture) ? '../../' . htmlspecialchars($profile_picture) : $fallback_avatar;
    $has_custom_avatar = !empty($profile_picture);
    
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
    
    // External links
    $linkedin_url = htmlspecialchars($mentor['linkedin_url'] ?? '#');
    $github_url = htmlspecialchars($mentor['github_url'] ?? '#');
    
    $z_index = 100 - $index;
    ?>
    <div class="swipe-card" data-mentor-id="<?= $mentor_id ?>" data-index="<?= $index ?>" style="z-index: <?= $z_index ?>;">
        <div class="swipe-card-inner">
            <!-- Profile Picture -->
            <div class="swipe-card-avatar-container">
                <?php if ($has_custom_avatar): ?>
                    <img src="<?= $avatar_url ?>" alt="<?= $full_name ?>" class="swipe-card-avatar">
                <?php else: ?>
                    <div class="swipe-card-avatar swipe-card-avatar-fallback">
                        <span class="avatar-emoji">👨‍🏫</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Year and Course -->
            <div class="swipe-card-info">
                <div class="swipe-card-year">
                    <span class="year-number"><?= $year ?></span><sup class="year-suffix"><?= $year_suffix ?></sup>
                    <span class="year-label">Year</span>
                </div>
                <h3 class="swipe-card-course"><?= $course ?></h3>
                
                <!-- Star Rating -->
                <div class="swipe-card-rating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <svg class="star-icon <?= $i <= floor($rating) ? 'star-filled' : 'star-empty' ?>" viewBox="0 0 24 24" width="28" height="28">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="swipe-card-divider"></div>

            <!-- Areas of Expertise -->
            <div class="swipe-card-section">
                <h4 class="swipe-card-section-title">Area's of expertise:</h4>
                <ul class="swipe-card-list">
                    <?php 
                    $displayed_subjects = array_slice($subjects, 0, 3);
                    foreach ($displayed_subjects as $subject): 
                    ?>
                        <li class="swipe-card-list-item">
                            <span class="bullet"></span>
                            <span><?= htmlspecialchars($subject) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="swipe-card-divider"></div>

            <!-- External Links -->
            <div class="swipe-card-section">
                <h4 class="swipe-card-section-title">External links:</h4>
                <ul class="swipe-card-list">
                    <li class="swipe-card-list-item">
                        <span class="bullet"></span>
                        <a href="<?= $linkedin_url ?>" class="swipe-card-link" target="_blank" rel="noopener">LinkedIn</a>
                    </li>
                    <li class="swipe-card-list-item">
                        <span class="bullet"></span>
                        <a href="<?= $github_url ?>" class="swipe-card-link" target="_blank" rel="noopener">GitHub</a>
                    </li>
                </ul>
            </div>
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
        max-width: 420px;
        height: 620px;
        margin: 0 auto;
    }

    .swipe-card {
        position: absolute;
        width: 100%;
        max-width: 420px;
        background: #f3f4f6;
        border-radius: 24px;
        box-shadow: 0 8px 30px rgba(15, 23, 42, 0.12);
        cursor: grab;
        user-select: none;
        touch-action: pan-y;
        transition: transform 0.1s ease-out;
        overflow: hidden;
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
        padding: 32px;
    }

    /* Avatar */
    .swipe-card-avatar-container {
        display: flex;
        justify-content: center;
        margin-bottom: 24px;
    }

    .swipe-card-avatar {
        width: 128px;
        height: 128px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #fff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .swipe-card-avatar-fallback {
        background: linear-gradient(135deg, #f472b6 0%, #ef4444 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .avatar-emoji {
        font-size: 3rem;
    }

    /* Info Section */
    .swipe-card-info {
        text-align: center;
        margin-bottom: 8px;
    }

    .swipe-card-year {
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: 4px;
        margin-bottom: 8px;
    }

    .year-number {
        font-size: 3.5rem;
        font-weight: 700;
        line-height: 1;
        color: #111827;
    }

    .year-suffix {
        font-size: 1.5rem;
        font-weight: 600;
        color: #111827;
    }

    .year-label {
        font-size: 3.5rem;
        font-weight: 700;
        line-height: 1;
        color: #111827;
    }

    .swipe-card-course {
        font-size: 1.5rem;
        font-weight: 700;
        color: #111827;
        margin: 8px 0;
    }

    /* Star Rating */
    .swipe-card-rating {
        display: flex;
        justify-content: center;
        gap: 4px;
        margin-top: 12px;
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
        height: 2px;
        background: #d1d5db;
        margin: 20px 0;
    }

    /* Sections */
    .swipe-card-section {
        margin-bottom: 8px;
    }

    .swipe-card-section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #111827;
        margin: 0 0 16px;
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
        margin-bottom: 12px;
        font-size: 1.1rem;
        color: #111827;
    }

    .swipe-card-list-item:last-child {
        margin-bottom: 0;
    }

    .bullet {
        width: 12px;
        height: 12px;
        background: #111827;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .swipe-card-link {
        color: var(--accent, #3b82f6);
        text-decoration: underline;
        font-size: 1.1rem;
    }

    .swipe-card-link:hover {
        color: #2563eb;
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
        margin-top: 24px;
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

    /* Responsive */
    @media (max-width: 480px) {
        .swipe-container {
            height: 580px;
        }

        .swipe-card-inner {
            padding: 24px;
        }

        .swipe-card-avatar {
            width: 100px;
            height: 100px;
        }

        .year-number,
        .year-label {
            font-size: 2.75rem;
        }

        .year-suffix {
            font-size: 1.25rem;
        }

        .swipe-card-course {
            font-size: 1.25rem;
        }

        .star-icon {
            width: 24px;
            height: 24px;
        }

        .swipe-btn {
            width: 56px;
            height: 56px;
            font-size: 1.5rem;
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
                if (index === 0) {
                    attachListeners(card);
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
