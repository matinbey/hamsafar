-- در phpMyAdmin اجرا کنید (روی دیتابیسی که از قبل ساخته‌اید)
CREATE TABLE users(
 id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(60) NOT NULL, sid CHAR(9) NOT NULL UNIQUE,
 pass VARCHAR(255) NOT NULL, gender ENUM('f','m') NOT NULL, contact VARCHAR(60) NOT NULL,
 role ENUM('user','admin') NOT NULL DEFAULT 'user', tokens INT NOT NULL DEFAULT 10,
 blocked TINYINT NOT NULL DEFAULT 0, created TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4;
CREATE TABLE trips(
 id INT AUTO_INCREMENT PRIMARY KEY, owner_id INT NOT NULL, from_p VARCHAR(60) NOT NULL, to_p VARCHAR(60) NOT NULL,
 depart_at DATETIME NOT NULL, cap TINYINT NOT NULL, gender ENUM('all','f','m') NOT NULL DEFAULT 'all',
 status ENUM('active','cancelled','done') NOT NULL DEFAULT 'active', created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(status,depart_at), FOREIGN KEY(owner_id) REFERENCES users(id)) ENGINE=InnoDB CHARSET=utf8mb4;
CREATE TABLE members(
 trip_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(trip_id,user_id),
 FOREIGN KEY(trip_id) REFERENCES trips(id), FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB CHARSET=utf8mb4;
CREATE TABLE ratings(
 id INT AUTO_INCREMENT PRIMARY KEY, trip_id INT NOT NULL, rater_id INT NOT NULL, ratee_id INT NOT NULL,
 stars TINYINT NOT NULL, tag ENUM('good','late','rude','noshow') NULL, created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(trip_id,rater_id,ratee_id), INDEX(ratee_id)) ENGINE=InnoDB CHARSET=utf8mb4;
CREATE TABLE notes(
 id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, text VARCHAR(255) NOT NULL,
 created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(user_id)) ENGINE=InnoDB CHARSET=utf8mb4;
-- بعد از ثبت‌نام اولین ادمین (خودتان) در سایت، این را اجرا کنید:
-- UPDATE users SET role='admin' WHERE sid='شماره_دانشجویی_شما';
