CREATE DATABASE IF NOT EXISTS coreforge_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE coreforge_db;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS products;
CREATE TABLE products (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(255)   NOT NULL,
  category    VARCHAR(100)   NOT NULL,
  sku         VARCHAR(50)    NOT NULL UNIQUE,
  brand       VARCHAR(100)   NOT NULL,
  price       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  stock       INT            NOT NULL DEFAULT 0,
  description TEXT,
  image       VARCHAR(120)   NOT NULL DEFAULT 'cpu-7600.jpg',
  created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO products (name, category, sku, brand, price, stock, description, image) VALUES
-- Processors
('Ryzen 7 7800X3D','Processors','CPU-7800X3D','AMD',399.00,14,'8 cores / 16 threads with 104MB of 3D V-Cache. The gaming CPU to beat on socket AM5.','cpu-7800x3d.jpg'),
('Core i5-14600K','Processors','CPU-14600K','Intel',289.00,22,'14 cores (6P + 8E) boosting to 5.3GHz. The value sweet spot for LGA1700 builds.','cpu-14600k.jpg'),
('Ryzen 5 7600','Processors','CPU-7600','AMD',199.00,31,'6 cores / 12 threads at 5.1GHz with a bundled cooler. Excellent entry point into AM5.','cpu-7600.jpg'),
('Core i9-14900K','Processors','CPU-14900K','Intel',549.00,7,'24 cores (8P + 16E) peaking at 6.0GHz. Built for heavy multi-threaded workloads.','cpu-14900k.jpg'),
('Ryzen 9 7950X','Processors','CPU-7950X','AMD',549.00,5,'16 cores / 32 threads at up to 5.7GHz. A workstation-class chip for rendering and compiling.','cpu-7950x.jpg'),

-- Graphics Cards
('GeForce RTX 4070 Ti Super 16GB','Graphics Cards','GPU-4070TS','NVIDIA',799.00,9,'16GB GDDR6X on a 256-bit bus. Comfortable 1440p ultra and capable 4K performance.','gpu-4070ts.jpg'),
('GeForce RTX 4060 8GB','Graphics Cards','GPU-4060','NVIDIA',299.00,26,'8GB GDDR6 with DLSS 3 frame generation. A tidy 1080p card that sips power.','gpu-4060.jpg'),
('Radeon RX 7800 XT 16GB','Graphics Cards','GPU-7800XT','AMD',499.00,12,'16GB GDDR6 of raw raster performance. Strong price-per-frame at 1440p.','gpu-7800xt.jpg'),
('GeForce RTX 4090 24GB','Graphics Cards','GPU-4090','NVIDIA',1799.00,0,'24GB GDDR6X flagship. Uncompromised 4K ray tracing and serious AI compute.','gpu-4090.jpg'),
('Radeon RX 7600 8GB','Graphics Cards','GPU-7600','AMD',259.00,18,'8GB GDDR6 built for high-refresh 1080p esports on a tight budget.','gpu-7600.jpg'),

-- Memory
('Vengeance 32GB DDR5-6000','Memory','RAM-VEN32','Corsair',114.00,40,'2 x 16GB DDR5-6000 CL30 with EXPO. The default choice for a modern AM5 build.','ram-ven32.jpg'),
('Trident Z5 RGB 32GB DDR5-6400','Memory','RAM-TZ32','G.Skill',139.00,17,'2 x 16GB DDR5-6400 CL32 with addressable RGB and a brushed aluminium heatspreader.','ram-tz32.jpg'),
('Fury Beast 16GB DDR5-5200','Memory','RAM-FB16','Kingston',59.00,45,'2 x 8GB DDR5-5200 CL40. Plug-and-play Plug N Play speeds with a low-profile heatsink.','ram-fb16.jpg'),
('Vengeance LPX 32GB DDR4-3600','Memory','RAM-LPX32','Corsair',74.00,33,'2 x 16GB DDR4-3600 CL18. Keeps an older AM4 or LGA1200 platform relevant.','ram-lpx32.jpg'),
('Ripjaws S5 64GB DDR5-6000','Memory','RAM-RJ64','G.Skill',219.00,8,'2 x 32GB DDR5-6000 CL30. Headroom for virtual machines and large timelines.','ram-rj64.jpg'),

-- Storage
('990 Pro 2TB NVMe','Storage','SSD-990P2T','Samsung',179.00,21,'PCIe 4.0 x4 at up to 7,450 MB/s read. Samsung''s fastest consumer drive.','ssd-990p.jpg'),
('WD_BLACK SN850X 1TB NVMe','Storage','SSD-SN850X1T','Western Digital',109.00,29,'PCIe 4.0 x4 at 7,300 MB/s with a Game Mode 2 low-latency profile.','ssd-sn850x.jpg'),
('P3 Plus 4TB NVMe','Storage','SSD-P3P4T','Crucial',249.00,11,'4TB of PCIe 4.0 storage at up to 5,000 MB/s. Bulk capacity without the flagship price.','ssd-p3p.jpg'),
('BarraCuda 4TB HDD','Storage','HDD-BC4T','Seagate',84.00,37,'5,400 RPM SATA drive with 256MB cache. Cheap, dependable bulk archive storage.','hdd-barracuda.jpg'),
('870 EVO 1TB SATA SSD','Storage','SSD-870E1T','Samsung',89.00,24,'2.5in SATA III at 560 MB/s. A straightforward upgrade for any older system.','ssd-870evo.jpg'),

-- Motherboards & Power
('ROG Strix B650E-F Gaming','Motherboards & Power','MB-B650EF','ASUS',269.00,10,'AM5 ATX board with PCIe 5.0, DDR5, Wi-Fi 6E and a beefy 12+2 power stage.','mb-b650ef.jpg'),
('MAG Z790 Tomahawk Wi-Fi','Motherboards & Power','MB-Z790TK','MSI',299.00,6,'LGA1700 ATX board with DDR5, four M.2 slots and 2.5G LAN.','mb-z790tk.jpg'),
('RM850e 850W 80+ Gold','Motherboards & Power','PSU-RM850E','Corsair',129.00,19,'Fully modular ATX 3.0 supply with a native 12VHPWR connector for modern GPUs.','psu-rm850e.jpg'),
('Focus GX-1000 1000W 80+ Gold','Motherboards & Power','PSU-GX1000','Seasonic',189.00,4,'1000W fully modular supply with a fanless low-load mode and a 10-year warranty.','psu-gx1000.jpg');
 
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;

CREATE TABLE orders (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  order_ref      VARCHAR(24)   NOT NULL UNIQUE,
  customer_name  VARCHAR(120)  NOT NULL,
  email          VARCHAR(160)  NOT NULL,
  phone          VARCHAR(40)   NOT NULL,
  address        VARCHAR(255)  NOT NULL,
  city           VARCHAR(80)   NOT NULL,
  postcode       VARCHAR(24)   NOT NULL,
  card_name      VARCHAR(120)  NOT NULL,
  card_brand     VARCHAR(20)   NOT NULL,
  card_last4     CHAR(4)       NOT NULL,
  card_exp_month TINYINT       NOT NULL,
  card_exp_year  SMALLINT      NOT NULL,
  subtotal       DECIMAL(10,2) NOT NULL,
  shipping       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total          DECIMAL(10,2) NOT NULL,
  status         VARCHAR(20)   NOT NULL DEFAULT 'paid',
  created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  order_id   INT           NOT NULL,
  product_id INT           NULL,
  name       VARCHAR(255)  NOT NULL,
  sku        VARCHAR(50)   NOT NULL,
  brand      VARCHAR(100)  NOT NULL,
  image      VARCHAR(120)  NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  qty        INT           NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_oi_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Admin accounts for the /manage_products.php back office.
--
-- Passwords are bcrypt hashes -- never plain text. Add an account with:
--   php -r 'echo password_hash("their-password", PASSWORD_DEFAULT);'
-- then INSERT the result below.
--
-- Seeded account:  admin / coreforge
-- ------------------------------------------------------------
CREATE TABLE admins (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)   NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  display_name  VARCHAR(120)  NOT NULL,
  role          VARCHAR(30)   NOT NULL DEFAULT 'Administrator',
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  last_login_at DATETIME      NULL,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admins (username, password_hash, display_name, role) VALUES
('admin', '$2y$12$cKksimZrv0beKKN5fIziGO/Aq7sv1vqogDfS2lYJgGSXpmL0preyK', 'Store Admin', 'Administrator');
