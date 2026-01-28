CREATE DATABASE mentor_match;
USE mentor_match;

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
    role ENUM('student','tutor','admin') NOT NULL,
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
        'visual',
        'auditory',
        'reading_writing',
        'kinaesthetic',
        'mixed'
    ) NOT NULL,
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =======================
-- INTERESTS
-- =======================
CREATE TABLE interests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
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
-- TUTOR PROFILES
-- =======================
CREATE TABLE tutor_profiles (
    tutor_id INT PRIMARY KEY,
    bio TEXT,
    experience_years INT,
    hourly_rate DECIMAL(6,2),
    verified BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =======================
-- SUBJECTS
-- =======================
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- =======================
-- TUTOR SUBJECTS (M:N)
-- =======================
CREATE TABLE tutor_subjects (
    tutor_id INT NOT NULL,
    subject_id INT NOT NULL,
    PRIMARY KEY (tutor_id, subject_id),
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- =======================
-- AVAILABILITY
-- =======================
CREATE TABLE availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    available_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =======================
-- SESSIONS (BOOKINGS)
-- =======================
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    tutor_id INT NOT NULL,
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
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE,
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
    tutor_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE
);
