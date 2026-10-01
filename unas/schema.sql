CREATE TABLE IF NOT EXISTS contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(60) NOT NULL DEFAULT '',
    topic VARCHAR(120) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new','in_progress','done','archived') NOT NULL DEFAULT 'new'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_token CHAR(64) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(60) NOT NULL DEFAULT '',
    topic VARCHAR(120) NOT NULL,
    meeting VARCHAR(120) NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    message TEXT NOT NULL,
    status ENUM('requested','confirmed','completed','cancelled') NOT NULL DEFAULT 'requested',
    active_slot VARCHAR(32) NULL UNIQUE,
    reminder_sent_at DATETIME NULL,
    INDEX bookings_date_status (booking_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content (
    content_key VARCHAR(60) NOT NULL,
    language VARCHAR(10) NOT NULL,
    title VARCHAR(180) NOT NULL,
    payload JSON NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (content_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    expert VARCHAR(120) NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT feedback_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX feedback_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calculations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    calculator_type ENUM('investment','loan','reserve') NOT NULL,
    inputs_json JSON NOT NULL,
    results_json JSON NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT calculations_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX calculations_by_user (user_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    rate_key CHAR(64) PRIMARY KEY,
    window_started DATETIME NOT NULL,
    request_count SMALLINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
