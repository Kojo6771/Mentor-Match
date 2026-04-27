# Mentor Match

A web-based mentor-student matching platform built as a final-year project at Aston University. Students can browse and connect with mentors, schedule sessions, and get help through a built-in AI chatbot. Mentors apply to join the platform and manage their own availability. Admins oversee applications, users, and platform health.

---

## Features

| Area | What it does |
|---|---|
| **Authentication** | Email/password sign-up and login, plus OAuth via Google and Microsoft (PKCE flow for Microsoft) |
| **Roles** | Three roles — `student`, `mentor`, and `admin` — each with their own dashboard and permissions |
| **Mentor applications** | Students can apply to become mentors; admins review and approve/reject applications |
| **Swipe matching** | Tinder-style card swipe for students to browse mentor profiles |
| **Session scheduling** | Students send session requests; mentors accept or decline; both see sessions on a shared calendar |
| **Availability** | Mentors set recurring weekly slots or one-off dates |
| **Chat** | Real-time-style messaging between matched students and mentors |
| **AI Chatbot** | GPT-4o-mini powered assistant available to all users |
| **Profile management** | Students and mentors each have a dedicated profile setup/edit page |
| **Admin panel** | Manage users, review mentor applications, monitor sessions, and view platform reports |
| **Legal pages** | Privacy Policy and Terms of Service linked from sign-up |

---

## Tech Stack

- **Backend:** PHP 8.2 (no framework — plain PHP with PDO)
- **Database:** MySQL / MariaDB 10.4
- **Frontend:** Vanilla HTML, CSS, and JavaScript (no build step required)
- **Auth providers:** Google OAuth 2.0, Microsoft OAuth 2.0 (PKCE)
- **AI:** OpenAI API — `gpt-4o-mini`
- **Local server:** XAMPP (Apache + MySQL + PHP)

---

## Prerequisites

- [XAMPP](https://www.apachefriends.org/) (or any Apache + MySQL + PHP 8.x stack)
- PHP 8.0 or higher
- A MySQL/MariaDB instance
- An [OpenAI API key](https://platform.openai.com/api-keys) (for the chatbot)
- Google and/or Microsoft OAuth credentials (optional, but needed for social login)

---

## Installation

1. **Clone or copy the project** into your XAMPP `htdocs` folder:
   ```
   C:\xampp\htdocs\Mentor-Match\
   ```

2. **Import the database schema.** Open phpMyAdmin (or the MySQL CLI) and run:
   ```sql
   CREATE DATABASE mentormatch CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
   Then import `includes/Database.sql` into the `mentormatch` database.

3. **Configure the database connection** in `includes/db.php`:
   ```php
   $pdo = new PDO('mysql:host=localhost;port=3306;dbname=mentormatch', 'root', '');
   ```
   Update the host, port, username, and password if your setup differs from the XAMPP defaults.

4. **Configure OAuth** in `includes/oauth_config.php`:
   - Replace `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` with your Google Cloud credentials.
   - Replace `MICROSOFT_CLIENT_ID` and `MICROSOFT_CLIENT_SECRET` with your Azure app credentials.
   - Make sure the redirect URI registered with both providers matches:
     ```
     http://localhost/mentor-match/pages/oauth_callback.php
     ```

5. **Configure the chatbot** in `includes/chatbot_config.php`:
   ```php
   define('OPENAI_API_KEY', 'sk-...');
   ```
   Replace the placeholder key with your own OpenAI API key.

6. **Set upload permissions.** The `uploads/profile_pictures/` directory needs to be writable by the web server. On Windows with XAMPP this is usually handled automatically.

7. **Start XAMPP** (Apache + MySQL) and visit:
   ```
   http://localhost/Mentor-Match/
   ```

---

## Project Structure

```
Mentor-Match/
├── index.php                  # Public landing page
├── assets/
│   ├── css/                   # Per-page stylesheets
│   ├── js/                    # Client-side scripts
│   └── images/
├── components/
│   └── swipe/
│       └── swipe_card.php     # Reusable mentor card for the swipe view
├── includes/
│   ├── db.php                 # PDO database connection
│   ├── Database.sql           # Full schema + seed data
│   ├── nav.php                # Shared navigation bar
│   ├── oauth_config.php       # OAuth credentials and endpoints
│   └── chatbot_config.php     # OpenAI API key and model settings
├── pages/                     # Main application pages (login, signup, dashboard, etc.)
├── uploads/
│   └── profile_pictures/      # User-uploaded avatars
└── users/
    ├── admin/                 # Admin-only pages
    ├── mentor/                # Mentor-only pages
    └── student/               # Student-only pages
```

---

## User Roles

| Role | How to get it | Access |
|---|---|---|
| **Student** | Register normally | Dashboard, swipe, chat, calendar, chatbot, own profile |
| **Mentor** | Register, then submit a mentor application and get approved by an admin | All student access + availability management, incoming session requests |
| **Admin** | Set manually in the database (`role = 'admin'`) | Full platform — manage users, applications, sessions, and reports |

---

## Security Notes

- Passwords are hashed with `password_hash()` (bcrypt by default in PHP).
- All database queries use PDO prepared statements to prevent SQL injection.
- Uploaded profile pictures are validated for MIME type and file size before saving.
- The Microsoft OAuth flow uses PKCE to avoid exposing the client secret in the browser.
- **Do not commit real API keys or OAuth secrets to a public repository.** Move credentials to environment variables or a config file excluded from version control before deploying.

---

## Author

Kwadwo Antwi-Adarkwah — Aston University Final Year Project (2025–2026)

