CREATE DATABASE IF NOT EXISTS victory_mmls
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE victory_mmls;


-- ============================================================
-- USERS
-- ============================================================

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(150) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    password_hash VARCHAR(255) NOT NULL,

    role ENUM(
        'admin',
        'instructor',
        'learner'
    ) NOT NULL DEFAULT 'learner',

    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- VOCATIONAL SKILLS
-- ============================================================

CREATE TABLE skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(150) NOT NULL,

    description TEXT NULL,

    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,

    created_by INT UNSIGNED NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_skills_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    UNIQUE KEY uq_skill_name (name)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- LESSONS
-- ============================================================

CREATE TABLE lessons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    skill_id INT UNSIGNED NOT NULL,

    title VARCHAR(200) NOT NULL,

    description TEXT NULL,

    lesson_order INT UNSIGNED NOT NULL DEFAULT 1,

    is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,

    created_by INT UNSIGNED NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_lessons_skill
        FOREIGN KEY (skill_id)
        REFERENCES skills(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_lessons_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    UNIQUE KEY uq_skill_lesson_order
        (skill_id, lesson_order)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- LESSON MULTIMEDIA CONTENT
-- ============================================================

CREATE TABLE lesson_contents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    lesson_id INT UNSIGNED NOT NULL,

    content_type ENUM(
        'text',
        'image',
        'video'
    ) NOT NULL,

    title VARCHAR(200) NULL,

    content TEXT NULL,

    file_path VARCHAR(500) NULL,

    content_order INT UNSIGNED NOT NULL DEFAULT 1,

    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_lesson_contents_lesson
        FOREIGN KEY (lesson_id)
        REFERENCES lessons(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- ASSESSMENT QUESTIONS
-- ============================================================

CREATE TABLE questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    lesson_id INT UNSIGNED NOT NULL,

    question_text TEXT NOT NULL,

    question_order INT UNSIGNED NOT NULL DEFAULT 1,

    marks INT UNSIGNED NOT NULL DEFAULT 1,

    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_questions_lesson
        FOREIGN KEY (lesson_id)
        REFERENCES lessons(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- QUESTION OPTIONS
-- ============================================================

CREATE TABLE question_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    question_id INT UNSIGNED NOT NULL,

    option_text VARCHAR(500) NOT NULL,

    option_order INT UNSIGNED NOT NULL DEFAULT 1,

    is_correct TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,

    CONSTRAINT fk_question_options_question
        FOREIGN KEY (question_id)
        REFERENCES questions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY uq_question_option_order
        (question_id, option_order)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- ASSESSMENT ATTEMPTS
-- ============================================================

CREATE TABLE assessment_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    learner_id INT UNSIGNED NOT NULL,

    lesson_id INT UNSIGNED NOT NULL,

    started_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    submitted_at TIMESTAMP NULL,

    score DECIMAL(6,2) NULL,

    total_marks DECIMAL(6,2) NULL,

    status ENUM(
        'in_progress',
        'submitted'
    ) NOT NULL DEFAULT 'in_progress',

    CONSTRAINT fk_attempts_learner
        FOREIGN KEY (learner_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_attempts_lesson
        FOREIGN KEY (lesson_id)
        REFERENCES lessons(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- ASSESSMENT ANSWERS
-- ============================================================

CREATE TABLE assessment_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    attempt_id INT UNSIGNED NOT NULL,

    question_id INT UNSIGNED NOT NULL,

    option_id INT UNSIGNED NULL,

    is_correct TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,

    marks_awarded DECIMAL(6,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_answers_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES assessment_attempts(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_answers_question
        FOREIGN KEY (question_id)
        REFERENCES questions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_answers_option
        FOREIGN KEY (option_id)
        REFERENCES question_options(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    UNIQUE KEY uq_attempt_question
        (attempt_id, question_id)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- RESULTS
-- ============================================================

CREATE TABLE results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    attempt_id INT UNSIGNED NOT NULL UNIQUE,

    learner_id INT UNSIGNED NOT NULL,

    lesson_id INT UNSIGNED NOT NULL,

    score DECIMAL(6,2) NOT NULL DEFAULT 0,

    total_marks DECIMAL(6,2) NOT NULL DEFAULT 0,

    percentage DECIMAL(6,2) NOT NULL DEFAULT 0,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_results_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES assessment_attempts(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_results_learner
        FOREIGN KEY (learner_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_results_lesson
        FOREIGN KEY (lesson_id)
        REFERENCES lessons(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- LEARNER PROGRESS
-- ============================================================

CREATE TABLE learner_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    learner_id INT UNSIGNED NOT NULL,

    lesson_id INT UNSIGNED NOT NULL,

    status ENUM(
        'not_started',
        'in_progress',
        'completed'
    ) NOT NULL DEFAULT 'not_started',

    started_at TIMESTAMP NULL,

    completed_at TIMESTAMP NULL,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_progress_learner
        FOREIGN KEY (learner_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_progress_lesson
        FOREIGN KEY (lesson_id)
        REFERENCES lessons(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY uq_learner_lesson
        (learner_id, lesson_id)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;