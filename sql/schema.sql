CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('owner','admin') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE quests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration INT NOT NULL,
    price_9_12 INT NOT NULL,
    price_13_17 INT NOT NULL,
    price_18_21 INT NOT NULL,
    tea_room_price INT NOT NULL,
    tea_room_duration INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quest_id INT NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    players INT NOT NULL,
    age_info VARCHAR(255) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    phone VARCHAR(64) NOT NULL,
    tea_room TINYINT(1) NOT NULL DEFAULT 0,
    tea_start_at DATETIME NULL,
    tea_end_at DATETIME NULL,
    status ENUM('new','confirmed','completed','canceled','no_show') NOT NULL DEFAULT 'new',
    comment TEXT,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_quest FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE,
    INDEX idx_start_at (start_at),
    INDEX idx_tea_start (tea_start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL UNIQUE,
    base_amount INT NOT NULL,
    extra_players_amount INT NOT NULL,
    tea_room_amount INT NOT NULL,
    total_amount INT NOT NULL,
    payment_type ENUM('cash','card','transfer') NOT NULL,
    paid_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
