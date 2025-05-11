-- Database per SEO Metadata API

-- Tabella Utenti
CREATE TABLE users (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       email VARCHAR(255) UNIQUE NOT NULL,
                       password VARCHAR(255) NOT NULL,
                       first_name VARCHAR(100) NOT NULL,
                       last_name VARCHAR(100) NOT NULL,
                       fiscal_code VARCHAR(16) UNIQUE NOT NULL, -- Codice fiscale italiano
                       phone VARCHAR(20) NULL,
                       company VARCHAR(100) NULL,
                       vat_number VARCHAR(20) NULL, -- Partita IVA
                       api_key VARCHAR(64) UNIQUE NOT NULL,
                       email_verified TINYINT(1) DEFAULT 0,
                       status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabella Abbonamenti
CREATE TABLE subscriptions (
                               id INT AUTO_INCREMENT PRIMARY KEY,
                               user_id INT NOT NULL,
                               stripe_customer_id VARCHAR(255),
                               stripe_subscription_id VARCHAR(255),
                               plan_type ENUM('free', 'pro', 'premium') DEFAULT 'free',
                               status ENUM('active', 'canceled', 'past_due') DEFAULT 'active',
                               current_period_start TIMESTAMP,
                               current_period_end TIMESTAMP,
                               created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                               updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                               FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabella Utilizzo API
CREATE TABLE api_usage (
                           id INT AUTO_INCREMENT PRIMARY KEY,
                           user_id INT NOT NULL,
                           request_count INT DEFAULT 0,
                           month INT NOT NULL,
                           year INT NOT NULL,
                           created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                           updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                           FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                           UNIQUE KEY user_month_year (user_id, month, year)
);

-- Tabella Log Richieste
CREATE TABLE request_logs (
                              id INT AUTO_INCREMENT PRIMARY KEY,
                              user_id INT NOT NULL,
                              url VARCHAR(512) NOT NULL,
                              response_code INT,
                              execution_time FLOAT,
                              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Aggiungiamo il campo verification_token alla tabella users
ALTER TABLE users
    ADD COLUMN verification_token VARCHAR(100) NULL,
    ADD COLUMN verification_token_expires TIMESTAMP NULL;
