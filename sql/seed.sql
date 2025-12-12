INSERT INTO users (email, password_hash, role) VALUES
('owner@example.com', '$2y$12$P8oKkV7Kos9yuFm86x3w2e0dYZoM3hV1xgN74iSKV.Rb.GHE0HmQm', 'owner');

INSERT INTO quests (name, duration, price_9_12, price_13_17, price_18_21, tea_room_price, tea_room_duration, is_active)
VALUES ('Тестовый квест', 60, 5000, 6000, 7000, 2000, 60, 1);
