-- Données d'exemple pour le développement

USE cartes_visite_db;

-- Insérer des cartes pour le client de test
INSERT INTO client_cards (client_id, product_id, order_number, card_type, design_data, status, quantity, unit_price, total_price) VALUES
(1, 1, 'CMD-20240115-001', 'standard', 
 '{"layout": "classic", "primary_color": "#2c3e50", "secondary_color": "#3498db", "font": "Arial", "logo": "", "contact_info": {"name": "Jean Dupont", "title": "Directeur", "email": "jean@test.com", "phone": "+33 1 23 45 67 89", "website": "www.test.com"}}',
 'active', 100, 49.99, 49.99),
 
(1, 2, 'CMD-20240116-001', 'nfc',
 '{"layout": "modern", "primary_color": "#9b59b6", "secondary_color": "#3498db", "font": "Montserrat", "logo": "/assets/uploads/clients/logo.png", "contact_info": {"name": "Jean Dupont", "title": "Directeur Commercial", "email": "jean@test.com", "phone": "+33 1 23 45 67 89", "website": "www.test.com"}, "nfc_actions": {"website": "https://www.test.com", "email": "contact@test.com", "phone": "+33123456789", "vcard": true}}',
 'active', 50, 149.99, 149.99),

(1, 3, 'CMD-20240117-001', 'premium',
 '{"layout": "creative", "primary_color": "#e74c3c", "secondary_color": "#f39c12", "font": "Georgia", "logo": "/assets/uploads/clients/logo.png", "contact_info": {"name": "Jean Dupont", "title": "CEO", "email": "jean@test.com", "phone": "+33 1 23 45 67 89", "website": "www.test.com"}, "special_finish": "dorure"}',
 'pending', 100, 99.99, 119.99);

-- Insérer des demandes de démo
INSERT INTO demos (client_id, card_type, demo_type, requested_date, status, notes) VALUES
(1, 'nfc', 'online', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'scheduled', 'Découverte des fonctionnalités NFC'),
(1, 'premium', 'in_person', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'pending', 'Présentation des finitions premium');

-- Insérer des demandes de mise à niveau
INSERT INTO upgrade_requests (client_id, card_id, from_type, to_type, status, price_difference, notes) VALUES
(1, 1, 'standard', 'nfc', 'pending', 100.00, 'Souhaite ajouter la fonctionnalité NFC');

-- Insérer des notifications
INSERT INTO notifications (client_id, type, title, message, is_read, priority) VALUES
(1, 'order', 'Commande confirmée', 'Votre commande #CMD-20240115-001 a été confirmée et est en production.', FALSE, 'low'),
(1, 'update', 'Mise à jour disponible', 'Une nouvelle mise à jour de votre application NFC est disponible.', FALSE, 'medium'),
(1, 'system', 'Maintenance planifiée', 'Une maintenance est prévue ce week-end. Le service pourrait être interrompu.', TRUE, 'low');

-- Insérer des activités
INSERT INTO activities (user_type, user_id, type, description, ip_address) VALUES
('client', 1, 'login', 'Connexion réussie', '192.168.1.1'),
('client', 1, 'order', 'Nouvelle commande #CMD-20240115-001', '192.168.1.1'),
('client', 1, 'update', 'Modification de la carte #1', '192.168.1.1'),
('admin', 1, 'login', 'Connexion administrateur', '192.168.1.100'),
('admin', 1, 'settings', 'Modification des paramètres généraux', '192.168.1.100');