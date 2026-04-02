<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mentor Match — Find Your Perfect Mentor</title>
    <meta name="description" content="Connect with experienced mentors who can guide your academic and career journey. Mentor Match pairs students with the right mentors.">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>

<!-- ── Navbar ── -->
<header class="landing-nav" id="navbar">
    <div class="nav-inner">
        <a href="index.php" class="nav-brand">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="8" r="3" fill="#3b82f6"/>
                <path d="M3 20c0-3.866 3.582-7 9-7s9 3.134 9 7" stroke="#111827" stroke-opacity=".06" stroke-width="1.5"/>
            </svg>
            <span class="nav-brand-text">Mentor Match</span>
        </a>
        <div class="nav-actions">
            <a href="pages/login.php" class="btn-nav btn-nav-ghost">Log in</a>
            <a href="pages/signup.php" class="btn-nav btn-nav-primary">Sign up</a>
        </div>
    </div>
</header>

<!-- ── Hero ── -->
<section class="hero">
    <div class="hero-inner">
        <div class="hero-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            Empowering Students Everywhere
        </div>

        <h1>Find the Mentor Who<br><span class="gradient-text">Shapes Your Future</span></h1>

        <p class="hero-subtitle">
            Connect with experienced mentors who understand your goals.
            Get personalised guidance, schedule sessions, and accelerate your learning journey.
        </p>

        <div class="hero-buttons">
            <a href="pages/signup.php" class="btn-hero btn-hero-primary">
                Get Started
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
            <a href="pages/login.php" class="btn-hero btn-hero-secondary">
                I Have an Account
            </a>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat">
                <div class="stat-number">500+</div>
                <div class="stat-label">Active Mentors</div>
            </div>
            <div class="stat">
                <div class="stat-number">2,000+</div>
                <div class="stat-label">Students Matched</div>
            </div>
            <div class="stat">
                <div class="stat-number">10K+</div>
                <div class="stat-label">Sessions Completed</div>
            </div>
        </div>
    </div>

    <!-- Hero Illustration -->
    <div class="hero-illustration">
        <div class="illustration-card">
            <div class="mentor-scene">
                <!-- Mentor avatar -->
                <div class="person-avatar">
                    <div class="avatar-circle avatar-mentor">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>
                            <path d="M9 3.5L12 1l3 2.5" stroke-width="1.2"/>
                        </svg>
                    </div>
                    <span class="avatar-label">Mentor</span>
                </div>

                <!-- Connection animation -->
                <div class="connection-line">
                    <div class="connection-dots">
                        <span class="dot"></span>
                        <span class="dot"></span>
                        <span class="dot"></span>
                        <span class="dot"></span>
                        <span class="dot"></span>
                    </div>
                    <span class="connection-label">Connected</span>
                </div>

                <!-- Student avatar -->
                <div class="person-avatar">
                    <div class="avatar-circle avatar-student">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#06b6d4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>
                            <rect x="9" y="1" width="6" height="4" rx="1" stroke-width="1.2"/>
                        </svg>
                    </div>
                    <span class="avatar-label">Student</span>
                </div>
            </div>

            <!-- Chat bubbles -->
            <div class="chat-bubbles">
                <div class="chat-bubble chat-bubble-mentor">"Let me walk you through this concept..."</div>
                <div class="chat-bubble chat-bubble-student">"That makes so much sense now!"</div>
            </div>
        </div>
    </div>
</section>

<!-- ── Features ── -->
<section class="features" id="features">
    <div class="section-header">
        <h2>Everything You Need to Succeed</h2>
        <p>Mentor Match gives students and mentors the tools to build meaningful, productive learning relationships.</p>
    </div>

    <div class="features-grid">
        <!-- Feature 1: Smart Matching -->
        <div class="feature-card">
            <div class="feature-icon feature-icon-blue">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                    <path d="M8 11h6"/><path d="M11 8v6"/>
                </svg>
            </div>
            <h3>Smart Mentor Matching</h3>
            <p>Browse and swipe through mentor profiles tailored to your subjects and learning goals. Find your ideal match in seconds.</p>
        </div>

        <!-- Feature 2: Scheduling -->
        <div class="feature-card">
            <div class="feature-icon feature-icon-cyan">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                    <path d="m9 16 2 2 4-4"/>
                </svg>
            </div>
            <h3>Easy Session Booking</h3>
            <p>View mentor availability in real time and book sessions that fit your schedule. No back-and-forth needed.</p>
        </div>

        <!-- Feature 3: Messaging -->
        <div class="feature-card">
            <div class="feature-icon feature-icon-purple">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                </svg>
            </div>
            <h3>Direct Messaging</h3>
            <p>Chat with your mentor directly in the platform. Share questions, follow up on sessions, and stay connected between meetings.</p>
        </div>

        <!-- Feature 4: AI Chatbot -->
        <div class="feature-card">
            <div class="feature-icon feature-icon-amber">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/>
                    <path d="M20 3v4"/><path d="M22 5h-4"/>
                </svg>
            </div>
            <h3>AI Study Assistant</h3>
            <p>Get instant help from our AI-powered chatbot when your mentor isn't available. Study smarter, not harder.</p>
        </div>

        <!-- Feature 5: Progress Tracking -->
        <div class="feature-card">
            <div class="feature-icon feature-icon-green">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="20" x2="18" y2="10"/>
                    <line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>
            </div>
            <h3>Track Your Progress</h3>
            <p>Monitor your mentoring journey with session history, calendars, and milestones all in one place.</p>
        </div>

        <!-- Feature 6: Verified Mentors -->
        <div class="feature-card">
            <div class="feature-icon feature-icon-rose">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
            </div>
            <h3>Verified Mentors</h3>
            <p>Every mentor goes through an application review process, ensuring you get quality guidance from qualified individuals.</p>
        </div>
    </div>
</section>

<!-- ── How It Works ── -->
<section class="how-it-works" id="how">
    <div class="how-inner">
        <div class="section-header">
            <h2>How It Works</h2>
            <p>Getting matched with a mentor is simple. Three steps and you're on your way.</p>
        </div>

        <div class="steps">
            <div class="step">
                <div class="step-number"><span>1</span></div>
                <h3>Create Your Profile</h3>
                <p>Sign up and tell us about your subjects, goals, and what kind of support you're looking for.</p>
            </div>
            <div class="step">
                <div class="step-number"><span>2</span></div>
                <h3>Find Your Mentor</h3>
                <p>Browse mentor profiles, see their expertise and availability, and send a request to the ones that fit.</p>
            </div>
            <div class="step">
                <div class="step-number"><span>3</span></div>
                <h3>Start Learning</h3>
                <p>Once matched, book sessions, chat directly, and begin your personalised mentoring journey.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── Social Proof ── -->
<section class="social-proof">
    <div class="section-header">
        <h2>Trusted by Students and Mentors</h2>
        <p>Hear from the people who use Mentor Match every day.</p>
    </div>

    <div class="proof-grid">
        <div class="proof-card">
            <div class="proof-stars">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </div>
            <p class="proof-text">"My mentor helped me understand data structures in a way my lectures never could. I went from struggling to acing my exams."</p>
            <div class="proof-author">
                <div class="proof-avatar" style="background:linear-gradient(135deg,#3b82f6,#06b6d4)">SK</div>
                <div>
                    <div class="proof-name">Sarah K.</div>
                    <div class="proof-role">Computer Science Student</div>
                </div>
            </div>
        </div>

        <div class="proof-card">
            <div class="proof-stars">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </div>
            <p class="proof-text">"Mentoring on this platform is incredibly rewarding. The scheduling tools make it effortless to manage my availability around my own studies."</p>
            <div class="proof-author">
                <div class="proof-avatar" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)">JM</div>
                <div>
                    <div class="proof-name">James M.</div>
                    <div class="proof-role">Mathematics Mentor</div>
                </div>
            </div>
        </div>

        <div class="proof-card">
            <div class="proof-stars">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </div>
            <p class="proof-text">"The AI chatbot is a lifesaver for late-night study sessions. And when I need deeper help, my mentor is just a message away."</p>
            <div class="proof-author">
                <div class="proof-avatar" style="background:linear-gradient(135deg,#059669,#34d399)">AO</div>
                <div>
                    <div class="proof-name">Aisha O.</div>
                    <div class="proof-role">Engineering Student</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA ── -->
<section class="cta-section">
    <div class="cta-card">
        <h2>Ready to Find Your Mentor?</h2>
        <p>Join hundreds of students already benefiting from one-on-one mentoring. It only takes a minute to get started.</p>
        <div class="cta-buttons">
            <a href="pages/signup.php" class="btn-cta-white">
                Create Free Account
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
            <a href="pages/login.php" class="btn-cta-outline">Sign In</a>
        </div>
    </div>
</section>

<!-- ── Footer ── -->
<footer class="landing-footer">
    <div class="footer-inner">
        <div class="footer-copy">&copy; <?php echo date('Y'); ?> Mentor Match. All rights reserved.</div>
        <div class="footer-links">
            <a href="pages/login.php">Log in</a>
            <a href="pages/signup.php">Sign up</a>
        </div>
    </div>
</footer>

<script>
// Add scrolled class to navbar on scroll
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 10);
}, { passive: true });
</script>

</body>
</html>
