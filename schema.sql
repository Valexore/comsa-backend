




--- 13 tables


-- Students table
CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_number VARCHAR(50) NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL, -- hashed
  profile_photo VARCHAR(255), -- optional path to profile image 
  role ENUM('student', 'admin') NOT NULL DEFAULT 'student', -- user role
  nickname VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Letters only, no spaces/numbers/special chars',
  bio TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Max 100 words, allows special chars/numbers',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_nickname_letters_only CHECK (nickname REGEXP '^[A-Za-z]+$' OR nickname IS NULL)
);

-- Projects table
CREATE TABLE projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  project_title VARCHAR(255) NOT NULL,
  project_description TEXT NOT NULL,
  project_category ENUM('Games', 'Websites', 'Mobile Apps', 'Console Apps', 'AI/ML', 'Databases') NOT NULL,
  download_link VARCHAR(255), -- optional (.exe)
  live_link VARCHAR(255),     -- optional live site
  github_link VARCHAR(255),   -- optional GitHub link
  visibility ENUM('public', 'hidden') NOT NULL DEFAULT 'public',
  featured BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Project images table
CREATE TABLE project_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

k-- Project technologies table
CREATE TABLE project_technologies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  technology_name VARCHAR(100) NOT NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Project team members table
CREATE TABLE project_team_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  member_name VARCHAR(100) NOT NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Project likes table
CREATE TABLE project_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  student_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (project_id, student_id), -- prevent multiple likes from same user
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Project comments table
CREATE TABLE project_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  student_id INT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Quick links table
CREATE TABLE quick_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  url VARCHAR(100) NOT NULL,
  category ENUM('academic', 'opportunity', 'support', 'resource') NOT NULL,
  remix_icon VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Events table
CREATE TABLE events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  status ENUM('active', 'upcoming', 'ended', 'draft') NOT NULL,
  start_date DATETIME NOT NULL,
  end_date DATETIME NOT NULL,
  event_image VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  carousel_status BOOLEAN NOT NULL
);

-- Admin posts table
CREATE TABLE admin_post (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NULL,
  title VARCHAR(100) NOT NULL,
  post_image VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  post_status ENUM('published', 'draft', 'archived') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_post_admin
    FOREIGN KEY (admin_id) REFERENCES students(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);

-- Admin tags table
CREATE TABLE admin_tags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  tag_name VARCHAR(100) NOT NULL,
  FOREIGN KEY (post_id) REFERENCES admin_post(id) ON DELETE CASCADE
);

-- Post likes table
CREATE TABLE post_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  student_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (post_id, student_id), -- prevent multiple likes from same user
  FOREIGN KEY (post_id) REFERENCES admin_post(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Post comments table
CREATE TABLE post_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  student_id INT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES admin_post(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);