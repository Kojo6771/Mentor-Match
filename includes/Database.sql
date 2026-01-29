CREATE DATABASE mentormatch;
USE mentormatch;

-- =======================
-- USERS
-- =======================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20), 
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','mentor','admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =======================
-- STUDENTS
-- =======================
CREATE TABLE students (
    student_id INT PRIMARY KEY,
    profile_picture VARCHAR(255),
    course VARCHAR(150) NOT NULL,
    year_of_study INT NOT NULL,
    learning_preference ENUM(
        'Videos',
        'In person sessions',
        'Quizzes'
    ) NOT NULL,
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =======================
-- INTERESTS
-- =======================
CREATE TABLE interests (
    interests_id INT AUTO_INCREMENT PRIMARY KEY,
    interests_name VARCHAR(100) NOT NULL UNIQUE
);

-- =======================
-- STUDENT INTERESTS (M:N)
-- =======================
CREATE TABLE student_interests (
    student_id INT NOT NULL,
    interest_id INT NOT NULL,
    PRIMARY KEY (student_id, interest_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (interest_id) REFERENCES interests(id) ON DELETE CASCADE
);

-- =======================
-- mentor PROFILES
-- =======================
CREATE TABLE mentor_profiles (
    mentor_id INT PRIMARY KEY,
    bio TEXT,
    experience_years INT,
    hourly_rate DECIMAL(6,2),
    verified BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =======================
-- SUBJECTS
-- =======================
CREATE TABLE subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL UNIQUE
);

-- =======================
-- mentor SUBJECTS (M:N)
-- =======================
CREATE TABLE mentor_subjects (
    mentor_id INT NOT NULL,
    subject_id INT NOT NULL,
    PRIMARY KEY (mentor_id, subject_id),
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- =======================
-- AVAILABILITY
-- =======================
CREATE TABLE availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT NOT NULL,
    available_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =======================
-- SESSIONS (BOOKINGS)
-- =======================
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    mentor_id INT NOT NULL,
    subject_id INT NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    status ENUM(
        'pending',
        'confirmed',
        'completed',
        'cancelled'
    ) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- =======================
-- MESSAGES
-- =======================
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =======================
-- REVIEWS
-- =======================
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNIQUE NOT NULL,
    student_id INT NOT NULL,
    mentor_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE
);
