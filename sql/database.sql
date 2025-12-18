-- Création de la base de données
CREATE DATABASE IF NOT EXISTS cartes_visite_db;
USE cartes_visite_db;

-- Table des administrateurs
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des clients
CREATE TABLE clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    postal_code VARCHAR(10),
    country VARCHAR(50) DEFAULT 'France',
    vat_number VARCHAR(50),
    subscription_type ENUM('free', 'basic', 'premium', 'enterprise') DEFAULT 'free',
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(100),
    reset_token VARCHAR(100),
    reset_token_expiry DATETIME,
    last_login DATETIME,
    preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des produits/services
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category ENUM('standard', 'nfc', 'premium') NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    features JSON,
    options JSON,
    is_active BOOLEAN DEFAULT TRUE,
    stock_quantity INT DEFAULT 0,
    delivery_days INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des cartes des clients
CREATE TABLE client_cards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    product_id INT NOT NULL,
    order_number VARCHAR(20) UNIQUE,
    card_type ENUM('standard', 'nfc', 'premium') NOT NULL,
    design_data JSON,
    nfc_data JSON NULL,
    status ENUM('draft', 'pending', 'processing', 'shipped', 'delivered', 'active', 'expired', 'upgrading', 'cancelled') DEFAULT 'draft',
    quantity INT DEFAULT 100,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    vat_amount DECIMAL(10,2) DEFAULT 0,
    delivery_address TEXT,
    tracking_number VARCHAR(100),
    estimated_delivery DATE,
    actual_delivery DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Table des démonstrations demandées
CREATE TABLE demos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    card_type ENUM('standard', 'nfc', 'premium') NOT NULL,
    demo_type ENUM('online', 'in_person', 'phone') DEFAULT 'online',
    requested_date DATE NOT NULL,
    demo_date DATE,
    duration INT DEFAULT 30 COMMENT 'Duration in minutes',
    status ENUM('pending', 'scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    notes TEXT,
    feedback TEXT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Table des demandes de mise à niveau
CREATE TABLE upgrade_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    card_id INT NOT NULL,
    from_type ENUM('standard', 'nfc', 'premium') NOT NULL,
    to_type ENUM('standard', 'nfc', 'premium') NOT NULL,
    status ENUM('pending', 'approved', 'processing', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
    price_difference DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    admin_notes TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES client_cards(id) ON DELETE CASCADE
);

-- Table des notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NULL,
    admin_id INT NULL,
    type ENUM('order', 'update', 'support', 'system', 'warning', 'info') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(255),
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

-- Table des tentatives de connexion
CREATE TABLE login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des activités
CREATE TABLE activities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_type ENUM('client', 'admin') NOT NULL,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des paramètres
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json', 'array') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Index pour les performances
CREATE INDEX idx_clients_email ON clients(email);
CREATE INDEX idx_clients_company ON clients(company_name);
CREATE INDEX idx_client_cards_client ON client_cards(client_id);
CREATE INDEX idx_client_cards_status ON client_cards(status);
CREATE INDEX idx_demos_client ON demos(client_id);
CREATE INDEX idx_demos_status ON demos(status);
CREATE INDEX idx_upgrade_requests_status ON upgrade_requests(status);
CREATE INDEX idx_notifications_user ON notifications(client_id, admin_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
CREATE INDEX idx_activities_user ON activities(user_type, user_id);

-- Insertion des données initiales avec mots de passe CHIFFRÉS
INSERT INTO admins (username, password, email, full_name) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@cartes-visite.com', 'Administrateur Principal');

-- Mot de passe: "password" (sera chiffré automatiquement)

INSERT INTO products (name, description, category, base_price, features, options, delivery_days) VALUES
('Carte Standard', 'Carte de visite classique avec impression recto', 'standard', 49.99, 
 '["Recto seulement", "Papier 300g", "Impression haute qualité"]',
 '{"paper_types": ["mat", "brillant", "recycle"], "finishes": ["coins_droits", "coins_ronds"], "quantities": [100, 250, 500, 1000]}',
 5),
 
('Carte NFC', 'Carte de visite avec puce NFC intégrée', 'nfc', 149.99,
 '["Puce NFC", "Application mobile", "Dashboard en ligne", "Mises à jour en temps réel"]',
 '{"nfc_types": ["standard", "premium", "advanced"], "memory": [144, 504, 888], "quantities": [50, 100, 200, 500]}',
 10),
 
('Carte Premium', 'Carte de visite haut de gamme avec options personnalisées', 'premium', 99.99,
 '["Recto-verso", "Papier 400g", "Finition brillante", "Options spéciales"]',
 '{"special_finishes": ["none", "dorure", "gaufrage", "vernis"], "paper_types": ["mat_400g", "brillant_400g", "recycle_400g"], "quantities": [100, 250, 500]}',
 7);

-- Insertion des paramètres par défaut
INSERT INTO settings (setting_key, setting_value, setting_type, category, description) VALUES
('site_name', 'CartesVisitePro', 'string', 'general', 'Nom du site'),
('site_email', 'contact@cartesvisitepro.com', 'string', 'general', 'Email de contact'),
('site_phone', '+33 1 23 45 67 89', 'string', 'general', 'Téléphone de contact'),
('site_address', '123 Rue des Entreprises, Paris', 'string', 'general', 'Adresse de l\'entreprise'),
('vat_rate', '20', 'number', 'billing', 'Taux de TVA en pourcentage'),
('currency', 'EUR', 'string', 'billing', 'Devise utilisée'),
('order_prefix', 'CMD', 'string', 'orders', 'Préfixe des numéros de commande'),
('demo_duration', '30', 'number', 'demos', 'Durée par défaut des démos (minutes)'),
('max_upload_size', '5', 'number', 'files', 'Taille maximale d\'upload (MB)'),
('session_timeout', '3600', 'number', 'security', 'Timeout de session (secondes)');

-- Insertion d'un client de test avec mot de passe CHIFFRÉ
INSERT INTO clients (company_name, contact_person, email, password, phone, address, is_active, email_verified) VALUES
('Entreprise Test', 'Jean Dupont', 'test@entreprise.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+33 1 23 45 67 89', '123 Rue de Test, Paris', TRUE, TRUE);
-- Mot de passe: "password"