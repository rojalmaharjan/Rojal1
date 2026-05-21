-- NabilBank Database Setup
-- Run this in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS nabilbank CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE nabilbank;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    full_name   VARCHAR(100) NOT NULL,
    password    VARCHAR(255) NOT NULL,
    balance     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    card_number VARCHAR(19)  NOT NULL DEFAULT '0000 0000 0000 0000',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    txn_ref     VARCHAR(20)  NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount      DECIMAL(15,2) NOT NULL,
    type        ENUM('credit','debit') NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed admin user  (password: 1234)
-- password_hash('1234', PASSWORD_BCRYPT)
INSERT INTO users (username, full_name, password, balance, card_number) VALUES
(
    'admin',
    'Rojal Maharjan',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    240000.00,
    '9765 3401 4621 8810'
)
ON DUPLICATE KEY UPDATE username = username;

-- Seed some initial transactions for admin (user_id = 1)
INSERT IGNORE INTO transactions (user_id, txn_ref, description, amount, type, created_at) VALUES
(1, 'TXN1029', 'Salary Deposit',        260000.00, 'credit', '2023-10-20 09:00:00'),
(1, 'TXN6782', 'Netflix Subscription',    1500.00, 'debit',  '2023-10-24 14:30:00');
