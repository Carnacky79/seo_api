-- Tabella amministratori
CREATE TABLE administrators (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                username VARCHAR(50) UNIQUE NOT NULL,
                                email VARCHAR(255) UNIQUE NOT NULL,
                                password VARCHAR(255) NOT NULL,
                                first_name VARCHAR(100) NOT NULL,
                                last_name VARCHAR(100) NOT NULL,
                                role ENUM('admin', 'super_admin') DEFAULT 'admin',
                                last_login TIMESTAMP NULL,
                                status ENUM('active', 'inactive') DEFAULT 'active',
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabella delle azioni amministrative (audit log)
CREATE TABLE admin_actions (
                               id INT AUTO_INCREMENT PRIMARY KEY,
                               admin_id INT NOT NULL,
                               action_type VARCHAR(50) NOT NULL,
                               entity_type VARCHAR(50) NOT NULL,
                               entity_id INT NOT NULL,
                               description TEXT NOT NULL,
                               ip_address VARCHAR(45) NOT NULL,
                               user_agent TEXT NOT NULL,
                               created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                               FOREIGN KEY (admin_id) REFERENCES administrators(id) ON DELETE CASCADE
);

-- Inserimento di un amministratore predefinito (password: admin123)
-- In produzione, cambia queste credenziali!
INSERT INTO administrators (username, email, password, first_name, last_name, role)
VALUES ('admin', 'admin@seotools.com', '$2y$10$YourHashedPasswordHere', 'Admin', 'User', 'super_admin');
