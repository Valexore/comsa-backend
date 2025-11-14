INSERT INTO `students` (`id`, `student_number`, `name`, `email`, `password`, `profile_photo`, `role`,`created_at`) VALUES
(1, '234-999923', 'Kileoma', 'untalan.j.bscs22@gmail.com', '123', NULL, 'student', '2023-04-28 16:15:22');

INSERT INTO `students` (`id`, `student_number`, `name`, `email`, `password`, `profile_photo`, `role`,`created_at`) VALUES
(3, '234-9999233', 'Kuro', 'untalan.j.bscs123@gmail.com', '123', NULL, 'student', '2023-04-28 16:15:22');

INSERT INTO `students` (`id`, `student_number`, `name`, `email`, `password`, `profile_photo`, `role`,`created_at`) VALUES
(2, '234-9992923', 'Neko', 'untalan.j.bscs@gmail.com', '123', NULL, 'student', '2023-04-28 16:15:22');





-----new tables for chat system-----
---- under maintenance ----


-- Conversations table (to track chat sessions between students)
CREATE TABLE conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Conversation participants table (to track who is in each conversation)
CREATE TABLE conversation_participants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  student_id INT NOT NULL,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_read_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY unique_participant (conversation_id, student_id)
);

-- Messages table (to store the actual chat messages)
CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender_id INT NOT NULL,
  message_text TEXT NOT NULL,
  message_type ENUM('text', 'image', 'file') DEFAULT 'text',
  attachment_path VARCHAR(255) NULL,
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_conversation_created (conversation_id, created_at)
);

-- Online status table (to track which students are currently online)
CREATE TABLE user_online_status (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL UNIQUE,
  status ENUM('online', 'offline', 'away', 'busy') DEFAULT 'offline',
  last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  socket_id VARCHAR(255) NULL,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Message read receipts table (to track who has read which messages)
CREATE TABLE message_read_receipts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL,
  student_id INT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES admin_post(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

---------------------------------------NEW TABLE TO ADD----------------------------------------
---------------------------------------NEW TABLE TO ALTER--------------------------------------
ALTER TABLE projects
ADD COLUMN visibility ENUM('public', 'hidden') NOT NULL DEFAULT 'public',
ADD COLUMN featured BOOLEAN NOT NULL DEFAULT FALSE;


-- For MySQL 8.0.16+ you can add constraints for the nickname
-- ALTER TABLE students 
-- ADD CONSTRAINT chk_nickname_letters_only 
-- CHECK (nickname REGEXP '^[A-Za-z]+$' OR nickname IS NULL);