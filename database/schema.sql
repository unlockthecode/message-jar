-- =============================================================
--  Message Jar — Database Schema
--  MySQL 8.x / MariaDB 10.4+
--  Charset: utf8mb4 (required for emoji)
--  Engine:  InnoDB (required for foreign keys, transactions)
-- =============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- -------------------------------------------------------------
--  users
--  Exactly two rows in practice: one admin, one user.
--  The 'role' column enforces who can do what at the app layer.
-- -------------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username        VARCHAR(50)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin','user') NOT NULL DEFAULT 'user',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at   DATETIME     NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------
--  jars
--  Each jar is a themed collection of messages.
--  Ordering is done with an integer; enough for a private app.
-- -------------------------------------------------------------
CREATE TABLE jars (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100) NOT NULL,
    category        VARCHAR(50)  NULL DEFAULT NULL,
    description     VARCHAR(255) NULL DEFAULT NULL,
    emoji           VARCHAR(16)  NULL DEFAULT NULL,
    theme_color     CHAR(7)      NULL DEFAULT NULL,      -- e.g. #ff8fab
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    display_order   INT          NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_jars_active_order (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------
--  messages
--  Belongs to exactly one jar. All optional media stored as URLs/IDs.
--  unlock_at / expires_at are enforced server-side.
-- -------------------------------------------------------------
CREATE TABLE messages (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    jar_id          INT UNSIGNED NOT NULL,
    body            TEXT         NOT NULL,
    image_url       VARCHAR(500) NULL DEFAULT NULL,      -- ImageKit URL
    youtube_id      VARCHAR(20)  NULL DEFAULT NULL,      -- just the 11-char ID
    external_url    VARCHAR(500) NULL DEFAULT NULL,      -- HTTPS only
    unlock_at       DATETIME     NULL DEFAULT NULL,
    expires_at      DATETIME     NULL DEFAULT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_messages_jar_active (jar_id, is_active),
    KEY idx_messages_window (unlock_at, expires_at),

    CONSTRAINT fk_messages_jar
        FOREIGN KEY (jar_id) REFERENCES jars(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------
--  message_views
--  Records that a user saw a message. Used to avoid repeats
--  and to give the admin stats later.
-- -------------------------------------------------------------
CREATE TABLE message_views (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    message_id      INT UNSIGNED NOT NULL,
    viewed_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_views_user_msg_time (user_id, message_id, viewed_at),

    CONSTRAINT fk_views_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_views_message
        FOREIGN KEY (message_id) REFERENCES messages(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------
--  login_attempts
--  Used for rate limiting. Kept simple: IP + time + success.
-- -------------------------------------------------------------
CREATE TABLE login_attempts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address      VARCHAR(45)  NOT NULL,      -- IPv6-safe
    username        VARCHAR(50)  NULL DEFAULT NULL,
    attempted_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success         TINYINT(1)   NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    KEY idx_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;