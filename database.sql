CREATE DATABASE IF NOT EXISTS crud_db;
USE crud_db;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100),
  password VARCHAR(100),
  gender VARCHAR(10),
  dob DATE,
  bio TEXT,
  skills VARCHAR(255),
  profile_image VARCHAR(255)
);
