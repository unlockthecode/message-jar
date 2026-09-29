-- =============================================================
--  Message Jar — Demo seed
--
--  Fictional jars and messages for demonstration purposes only.
--  Do NOT put real personal content in this file.
--
--  Usage (after loading schema.sql and creating at least one user):
--    mysql -u root message_jar < database/seed.sql
-- =============================================================

INSERT INTO jars (name, description, emoji, theme_color, is_active, display_order) VALUES
    ('When You Miss Me',   'Open this when you miss me.',      '❤️', '#ff8fab', 1, 0),
    ('When You''re Sad',   'A little pick-me-up.',             '😢', '#a2d2ff', 1, 1),
    ('Good Morning',       'For the start of your day.',       '☀️', '#ffd6a5', 1, 2),
    ('Good Night',         'Sweet dreams, my love.',           '🌙', '#bdb2ff', 1, 3),
    ('When You Need a Hug','Wrap yourself in these words.',    '🫂', '#ffc6ff', 1, 4);

-- Example messages — deliberately generic so no real content is included.
INSERT INTO messages (jar_id, body, is_active) VALUES
    (1, 'Imagine me giving you the biggest hug right now. That feeling? That''s real. ❤️', 1),
    (1, 'I was thinking about you just now. That happens a lot, actually.', 1),
    (2, 'Whatever''s weighing on you, you don''t have to carry it alone. I''m here.', 1),
    (3, 'Good morning! Today is going to be a good day, and you''re the reason.', 1),
    (4, 'Sleep well. Dream of something soft. I''ll see you tomorrow.', 1),
    (5, 'Consider yourself hugged. Warmly. For a while. Until you feel better.', 1);