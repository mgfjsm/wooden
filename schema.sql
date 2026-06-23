CREATE DATABASE IF NOT EXISTS `maison_noire` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `maison_noire`;

-- Admins
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admins` (`username`,`password_hash`,`display_name`) VALUES
('admin','$2y$12$LJ3m4ys3Lk0v8sJ7GxZxUuWBVTlKx5yXQN5RJfHcF2p8bW4KmYZe','Administrator');

-- Site data — single row, JSON blobs for each section
CREATE TABLE IF NOT EXISTS `site_data` (
  `key_name` VARCHAR(100) PRIMARY KEY,
  `data_json` JSON NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Collections — separate table for relational queries
CREATE TABLE IF NOT EXISTS `collections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `subtitle` VARCHAR(255) DEFAULT NULL,
  `year` VARCHAR(50) DEFAULT '2024',
  `description` TEXT DEFAULT NULL,
  `long_description` TEXT DEFAULT NULL,
  `pieces_count` INT DEFAULT 0,
  `is_limited` TINYINT(1) DEFAULT 0,
  `image_url` VARCHAR(500) DEFAULT NULL,
  `gallery` JSON DEFAULT NULL,
  `materials` VARCHAR(500) DEFAULT NULL,
  `lead_time` VARCHAR(100) DEFAULT NULL,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate limiting
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `ip_hash` VARCHAR(64) PRIMARY KEY,
  `requests` INT DEFAULT 1,
  `window_start` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity log
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` JSON DEFAULT NULL,
  `ip_hash` VARCHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default site data
INSERT INTO `site_data` (`key_name`, `data_json`) VALUES
('hero', '{"image":"https://picsum.photos/seed/darkfurniture-hero/1920/1080.jpg","label":"Established 1987","title":["Where Wood","Whispers","Stories"],"titleAccent":"Whispers","subtitle":"Each piece in our collection carries the soul of century-old forests, shaped by hands that understand that true luxury is not seen — it is felt.","ctaPrimary":"Explore Collections","ctaSecondary":"Our Story"}'),
('marquee', '{"items":["Handcrafted in Tuscany","Sustainable Hardwoods","Since 1987","Limited Editions","Bespoke Commissions"]}'),
('story', '{"image":"https://picsum.photos/seed/woodgrain-story/800/1000.jpg","imageQuote":"The tree does not rush to become a table.","heading":["A Legacy Born","From Patience"],"headingAccent":"Patience","paragraphs":["In the autumn of 1987, master craftsman Émile Renaud walked away from a flourishing career at one of Paris\'s most prestigious ateliers. He carried with him nothing but a set of hand-forged chisels and a singular conviction: that furniture should not merely occupy a room — it should anchor a life.","He settled in a converted stone barn in the Tuscan hills, where the light fell differently on every surface and the air smelled of cedar and earth. Here, surrounded by forests of walnut, cherry, and oak, Maison Noire found its first breath.","Three generations later, every piece still begins the same way — with a conversation between the maker and the wood. We listen to the grain, respect the knots, and honor the time it took for a seed to become something worthy of your home."],"stats":[{"value":"37","label":"Years of Craft"},{"value":"12k","label":"Pieces Created"},{"value":"48","label":"Artisans"}]}'),
('craft', '{"steps":[{"num":"01","icon":"lucide:trees","title":"Selection","desc":"Our forester walks the forests for weeks, marking only trees that have lived a full century. Each selection is documented — its position, its neighbors, the quality of light it received."},{"num":"02","icon":"lucide:wind","title":"Seasoning","desc":"Wood air-dries for a minimum of two years in our open-sided barns. It breathes with the seasons, releasing moisture slowly until it reaches perfect equilibrium."},{"num":"03","icon":"lucide:pen-tool","title":"Shaping","desc":"Hand tools only. No CNC, no routers. The craftsman reads the grain direction with his fingertips and cuts with the flow, never against it."},{"num":"04","icon":"lucide:droplets","title":"Finishing","desc":"Seven coats of hand-rubbed tung oil, applied over three weeks. Between each coat, the surface is sanded with progressively finer abrasives."}]}'),
('quote', '{"image":"https://picsum.photos/seed/workshop-panorama/1920/800.jpg","text":"The hand knows what the mind forgets","attribution":"Émile Renaud, Founder"}'),
('gallery', '{"items":[{"image":"https://picsum.photos/seed/livingroom-wide/900/1200.jpg","label":"Living Room","title":"The Walnu\'t Saga","span":"col-span-2 row-span-2"},{"image":"https://picsum.photos/seed/bedroom-cozy/500/600.jpg","label":"Bedroom","title":"Cherry Reverie","span":"col-span-1"},{"image":"https://picsum.photos/seed/study-dark/500/600.jpg","label":"Study","title":"Oak Silence","span":"col-span-1"},{"image":"https://picsum.photos/seed/dining-elegant/1000/600.jpg","label":"Dining Room","title":"Ebony Noir","span":"col-span-2"}]}'),
('testimonials', '{"items":[{"quote":"I ran my hand across the table\'s surface and understood something I never had about furniture — it can have a pulse. This piece breathes.","name":"Isabelle Moreau","role":"Architect, Paris","photo":"https://picsum.photos/seed/person-architect/100/100.jpg"},{"quote":"We commissioned a dining table for our family home. Three generations now gather around it. The wood seems to absorb our laughter and hold it.","name":"Henrik Lindqvist","role":"Collector, Stockholm","photo":"https://picsum.photos/seed/person-collector/100/100.jpg"},{"quote":"In a world of disposable everything, Maison Noire makes objects that refuse to be forgotten. My bookshelf will outlive me, and I find that deeply comforting.","name":"Clara Ashworth","role":"Author, London","photo":"https://picsum.photos/seed/person-writer/100/100.jpg"},{"quote":"The waiting list was nine months. When the chair finally arrived, I understood why. Some things cannot — should not — be rushed.","name":"Yuki Tanaka","role":"Designer, Tokyo","photo":"https://picsum.photos/seed/person-designer/100/100.jpg"}]}'),
('contact', '{"heading":"Begin Your","headingAccent":"Story","description":"Join a private circle of those who understand that the best things in life are not bought — they are chosen, with care. Receive our seasonal journal, early access to limited editions, and invitations to private viewings.","buttonText":"Subscribe","placeholder":"Your email address","address":"Via del Legno 14\n53100 Siena, Italy","phone":"+39 0577 284 193\nMon–Sat, 10am–6pm CET","email":"atelier@maisonnoire.it\ncommissions@maisonnoire.it","disclaimer":"We respect your privacy. Unsubscribe anytime."}'),
('footer', '{"description":"Furniture that carries the weight of time and the lightness of beauty.","columns":[{"title":"Collections","links":["The Walnu\'t Saga","Cherry Reverie","Oak Silence","Ebony Noir","Archive"]},{"title":"Atelier","links":["Our Story","Craftsmanship","The Artisans","Sustainability","Bespoke Commissions"]},{"title":"Support","links":["Care Guide","Shipping & Delivery","Returns Policy","Warranty","Contact Us"]}],"copyright":"© 2024 Maison Noire. All rights reserved.","privacyLinks":["Privacy Policy","Terms of Service","Cookie Preferences"]}');
