CREATE TABLE IF NOT EXISTS sponsor_applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(32) NOT NULL UNIQUE,
  submission_hash CHAR(64) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL,
  edition_id VARCHAR(80) NOT NULL,
  package_amount INT UNSIGNED NOT NULL,
  snapshot_json TEXT NOT NULL,
  company VARCHAR(160) NOT NULL,
  contact_name VARCHAR(160) NOT NULL,
  email VARCHAR(254) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  notes TEXT NOT NULL,
  logo_file VARCHAR(80) NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sponsor_admin_tickets (
  token_hash CHAR(64) PRIMARY KEY,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  KEY expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sponsor_admin_devices (
  token_hash CHAR(64) PRIMARY KEY,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  KEY expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sponsor_mail (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  recipient_kind VARCHAR(16) NOT NULL,
  state VARCHAR(16) NOT NULL DEFAULT 'pending',
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  attempted_at DATETIME NULL,
  sent_at DATETIME NULL,
  failure_code VARCHAR(40) NULL,
  UNIQUE KEY application_recipient (application_id, recipient_kind),
  CONSTRAINT sponsor_mail_application FOREIGN KEY (application_id) REFERENCES sponsor_applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sponsor_rate_limits (
  bucket CHAR(64) PRIMARY KEY,
  hits INT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  KEY expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sponsor_profiles (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  website VARCHAR(600) NOT NULL DEFAULT '',
  description VARCHAR(600) NOT NULL DEFAULT '',
  edition_id VARCHAR(80) NOT NULL,
  amount INT UNSIGNED NOT NULL DEFAULT 0,
  tier VARCHAR(16) NOT NULL DEFAULT 'friend',
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  visible TINYINT(1) NOT NULL DEFAULT 0,
  logo_asset VARCHAR(200) NOT NULL DEFAULT '',
  logo_file VARCHAR(80) NULL,
  logo_dark TINYINT(1) NOT NULL DEFAULT 0,
  source_note TEXT NOT NULL,
  application_id BIGINT UNSIGNED NULL UNIQUE,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY public_order (edition_id, visible, sort_order),
  CONSTRAINT sponsor_profile_application FOREIGN KEY (application_id) REFERENCES sponsor_applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
