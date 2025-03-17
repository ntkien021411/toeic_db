-- Chọn database sử dụng
USE toeic_db;

-- 1. Bảng Account (Bảng gốc chứa thông tin tài khoản)
CREATE TABLE Account (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NULL,
    password VARCHAR(255) NOT NULL,
    active_status BOOLEAN NOT NULL DEFAULT FALSE,
    active_date TIMESTAMP NULL,
    is_first BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE
);
-- 3. Bảng User (Tham chiếu đến Account và Role)
CREATE TABLE User (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNIQUE NOT NULL,
	role ENUM('TEACHER', 'STUDENT','ADMIN') NULL,  -- Cho phép NULL
    first_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NULL,
    full_name VARCHAR(50) NULL,
    birth_date DATE NULL,
    gender ENUM('MALE', 'FEMALE', 'OTHER') NULL,  -- Thêm trường giới tính
    phone VARCHAR(15) UNIQUE NULL,
    image_link VARCHAR(255) NULL,
    facebook_link VARCHAR(255) NULL,
    address VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_user_account FOREIGN KEY (account_id) REFERENCES Account(id) ON DELETE CASCADE
);



-- 4. Bảng Class (Lớp học)
CREATE TABLE Room (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_code VARCHAR(50)  NOT NULL,
    class_type ENUM('Beginner', 'Toeic A', 'Toeic B') NOT NULL DEFAULT 'Beginner',
    class_name VARCHAR(255)  NULL,
    start_date DATE  NULL,
    end_date DATE  NULL,
    start_time TIME  NULL,
    end_time TIME  NULL,
    days VARCHAR(255) NULL, -- Lưu dạng "yy/mm/dddd,yy/mm/dddđ"
    student_count INT DEFAULT 0,
    is_full BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('NOT_STARTED', 'IN_PROGRESS', 'COMPLETED') NOT NULL DEFAULT 'NOT_STARTED',
    teacher_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_class_teacher FOREIGN KEY (teacher_id) REFERENCES User(id) ON DELETE RESTRICT
);

-- 5. Bảng Class_User (Mối quan hệ giữa Class và User, thay thế ENUM role)
CREATE TABLE Class_User (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_class FOREIGN KEY (class_id) REFERENCES Room(id) ON DELETE CASCADE,
    CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE,
    UNIQUE (class_id, user_id)
);

-- 5. Bảng Exam_Section (Các phần thi TOEIC)
CREATE TABLE Exam_Section (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_code VARCHAR(50)  NOT NULL,
    exam_name VARCHAR(255)  NULL,
    section_name ENUM('Listening', 'Reading', 'Full') NOT NULL, 
    part_number ENUM('1', '2', '3', '4', '5', '6', '7', 'Full') NOT NULL, -- Chuyển từ TINYINT sang ENUM
    question_count INT  NULL CHECK (question_count > 0),
    year INT  NULL CHECK (year > 0),
    duration INT  NULL CHECK (duration > 0),
    max_score INT  NULL CHECK (max_score > 0),
    type VARCHAR(255)  NULL,
    is_Free BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE
);

-- 6. Bảng Question (Câu hỏi)
CREATE TABLE Question (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_section_id INT NOT NULL,
    question_number INT  NULL,
    image_url TEXT  NULL,
    audio_url TEXT  NULL,
    part_number ENUM('1', '2', '3', '4', '5', '6', '7') NOT NULL, 
    question_text TEXT  NULL,
    option_a TEXT  NULL,
    option_b TEXT  NULL,
    option_c TEXT  NULL,
    option_d TEXT  NULL,
    correct_answer CHAR(1) CHECK (correct_answer IN ('A', 'B', 'C', 'D')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_exam_section FOREIGN KEY (exam_section_id) REFERENCES Exam_Section(id) ON DELETE CASCADE
);

-- 7. Bảng Exam_Result (Kết quả bài thi)
CREATE TABLE Exam_Result (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exam_section_id INT NOT NULL,
    score INT CHECK (score >= 0),
    correct_answers INT CHECK (correct_answers >= 0) DEFAULT 0,
    wrong_answers INT CHECK (wrong_answers >= 0) DEFAULT 0,
    correct_answers_listening INT CHECK (correct_answers_listening >= 0) DEFAULT 0,
    wrong_answers_listening INT CHECK (wrong_answers_listening >= 0) DEFAULT 0,
    correct_answers_reading INT CHECK (correct_answers_reading >= 0) DEFAULT 0,
    wrong_answers_reading INT CHECK (wrong_answers_reading >= 0) DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_exam_result_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE,
    CONSTRAINT fk_exam_result_exam FOREIGN KEY (exam_section_id) REFERENCES Exam_Section(id) ON DELETE CASCADE
);


-- 8. Bảng Diploma (Chứng chỉ)
CREATE TABLE Diploma (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    certificate_name VARCHAR(255) NOT NULL,
    score DECIMAL(5,2)  NULL,
    level VARCHAR(500)  NULL,  -- Không dùng ENUM nữa
    issued_by VARCHAR(255)  NULL,
    issue_date DATE  NULL,
    expiry_date DATE NULL,
    certificate_image TEXT NULL,  -- Cột mới để lưu URL ảnh chứng chỉ
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_diploma_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE
);
-- 9. Bảng Token (Không cần xóa mềm, nhưng có cập nhật thời gian)
CREATE TABLE Token (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    refresh_token VARCHAR(255) NOT NULL,
    expired_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_token_account FOREIGN KEY (account_id) REFERENCES Account(id) ON DELETE CASCADE
);


-- Thêm 1 tài khoản Admin
INSERT INTO Account (username, email, password, active_status) VALUES
('admin', 'admin@example.com', '$2y$10$3bpAkU1C09o..qM1DBnnQeY8ZU5TdVl2o5q.KMLfegnQ4eAfU2xSq', TRUE);

INSERT INTO User (account_id, role, first_name, last_name) VALUES
((SELECT id FROM Account WHERE username = 'admin'), 'ADMIN', 'Admin', 'User');

-- Thêm 2 tài khoản mới
INSERT INTO Account (username, email, password, active_status) VALUES
('vu.letruong', 'vu.letruong@vti.com.vn', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
('kien.nguyentrung7', 'kien.nguyentrung7@vti.com.vn', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE);

-- Thêm thông tin User tương ứng với 2 tài khoản mới
INSERT INTO User (account_id, role, first_name, last_name) VALUES
((SELECT id FROM Account WHERE username = 'vu.letruong'), 'STUDENT', 'Le Truong', 'Vu'),
((SELECT id FROM Account WHERE username = 'kien.nguyentrung7'), 'STUDENT', 'Nguyen Trung', 'Kien');


-- Thêm 3 tài khoản Giáo viên
INSERT INTO Account (username, email, password, active_status) VALUES
('teacher1', 'teacher1@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
('teacher2', 'teacher2@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
('teacher3', 'teacher3@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE);

INSERT INTO User (account_id, role, first_name, last_name) VALUES
((SELECT id FROM Account WHERE username = 'teacher1'), 'TEACHER', 'John', 'Doe'),
((SELECT id FROM Account WHERE username = 'teacher2'), 'TEACHER', 'Alice', 'Smith'),
((SELECT id FROM Account WHERE username = 'teacher3'), 'TEACHER', 'Bob', 'Brown');

-- Thêm 15 tài khoản Sinh viên
INSERT INTO Account (username, email, password, active_status) 
VALUES ('student1', 'student1@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student2', 'student2@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student3', 'student3@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student4', 'student4@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student5', 'student5@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student6', 'student6@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student7', 'student7@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student8', 'student8@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student9', 'student9@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student10', 'student10@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student11', 'student11@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student12', 'student12@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student13', 'student13@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student14', 'student14@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE),
       ('student15', 'student15@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', TRUE);

INSERT INTO User (account_id, role, first_name, last_name)
SELECT id, 'STUDENT', CONCAT('Student', id), 'User' FROM Account WHERE username LIKE 'student%';

-- Tạo 5 lớp học và gán giáo viên
INSERT INTO Room (class_code, class_name, start_date, end_date, teacher_id) 
VALUES ('C101', 'TOEIC Basic', '2024-01-10', '2024-06-10', (SELECT id FROM User WHERE account_id = (SELECT id FROM Account WHERE username = 'teacher1'))),
       ('C102', 'TOEIC Intermediate', '2024-02-15', '2024-07-15', (SELECT id FROM User WHERE account_id = (SELECT id FROM Account WHERE username = 'teacher1'))),
       ('C103', 'TOEIC Advanced', '2024-03-01', '2024-08-01', (SELECT id FROM User WHERE account_id = (SELECT id FROM Account WHERE username = 'teacher2'))),
       ('C104', 'TOEIC Listening Master', '2024-04-01', '2024-09-01', (SELECT id FROM User WHERE account_id = (SELECT id FROM Account WHERE username = 'teacher2'))),
       ('C105', 'TOEIC Reading Master', '2024-05-01', '2024-10-01', (SELECT id FROM User WHERE account_id = (SELECT id FROM Account WHERE username = 'teacher3')));

-- Gán 3 sinh viên vào mỗi lớp
INSERT INTO Class_User (class_id, user_id)
SELECT (SELECT id FROM Room WHERE class_code = 'C101'), id FROM User 
WHERE account_id IN (SELECT id FROM Account WHERE username IN ('student1', 'student2', 'student3'));

INSERT INTO Class_User (class_id, user_id)
SELECT (SELECT id FROM Room WHERE class_code = 'C102'), id FROM User 
WHERE account_id IN (SELECT id FROM Account WHERE username IN ('student4', 'student5', 'student6'));

INSERT INTO Class_User (class_id, user_id)
SELECT (SELECT id FROM Room WHERE class_code = 'C103'), id FROM User 
WHERE account_id IN (SELECT id FROM Account WHERE username IN ('student7', 'student8', 'student9'));

INSERT INTO Class_User (class_id, user_id)
SELECT (SELECT id FROM Room WHERE class_code = 'C104'), id FROM User 
WHERE account_id IN (SELECT id FROM Account WHERE username IN ('student10', 'student11', 'student12'));

INSERT INTO Class_User (class_id, user_id)
SELECT (SELECT id FROM Room WHERE class_code = 'C105'), id FROM User 
WHERE account_id IN (SELECT id FROM Account WHERE username IN ('student13', 'student14', 'student15'));

-- Thêm dữ liệu mẫu cho bài thi TOEIC
-- Part 1: Photographs (6 câu hỏi)
INSERT INTO Exam_Section (exam_code, exam_name, section_name, part_number, question_count, year, duration, max_score, type, is_Free)
VALUES ('TOEIC2024_P1', 'TOEIC Part 1 - Photographs', 'Listening', '1', 6, 2024, 6, 30, 'Practice Test', TRUE);

-- Thêm câu hỏi cho Part 1
INSERT INTO Question (exam_section_id, question_number, part_number, audio_url, image_url, correct_answer)
VALUES 
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P1'), 1, '1', '/audio/part1/q1.mp3', '/images/part1/q1.jpg', 'A'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P1'), 2, '1', '/audio/part1/q2.mp3', '/images/part1/q2.jpg', 'B'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P1'), 3, '1', '/audio/part1/q3.mp3', '/images/part1/q3.jpg', 'C'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P1'), 4, '1', '/audio/part1/q4.mp3', '/images/part1/q4.jpg', 'A'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P1'), 5, '1', '/audio/part1/q5.mp3', '/images/part1/q5.jpg', 'D'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P1'), 6, '1', '/audio/part1/q6.mp3', '/images/part1/q6.jpg', 'B');

-- Part 2: Question-Response (25 câu hỏi)
INSERT INTO Exam_Section (exam_code, exam_name, section_name, part_number, question_count, year, duration, max_score, type, is_Free)
VALUES ('TOEIC2024_P2', 'TOEIC Part 2 - Question-Response', 'Listening', '2', 25, 2024, 20, 100, 'Practice Test', TRUE);

-- Thêm câu hỏi mẫu cho Part 2 (chỉ hiển thị 5 câu đầu)
INSERT INTO Question (exam_section_id, question_number, part_number, audio_url, correct_answer)
VALUES 
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P2'), 1, '2', '/audio/part2/q1.mp3', 'B'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P2'), 2, '2', '/audio/part2/q2.mp3', 'A'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P2'), 3, '2', '/audio/part2/q3.mp3', 'C'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P2'), 4, '2', '/audio/part2/q4.mp3', 'B'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P2'), 5, '2', '/audio/part2/q5.mp3', 'A');

-- Part 3: Conversations (39 câu hỏi - 13 đoạn hội thoại, mỗi đoạn 3 câu)
INSERT INTO Exam_Section (exam_code, exam_name, section_name, part_number, question_count, year, duration, max_score, type, is_Free)
VALUES ('TOEIC2024_P3', 'TOEIC Part 3 - Conversations', 'Listening', '3', 39, 2024, 30, 120, 'Practice Test', FALSE);

-- Thêm câu hỏi mẫu cho Part 3 (chỉ hiển thị 1 đoạn hội thoại - 3 câu)
INSERT INTO Question (exam_section_id, question_number, part_number, audio_url, question_text, option_a, option_b, option_c, option_d, correct_answer)
VALUES 
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P3'), 1, '3', '/audio/part3/conversation1.mp3', 
'What is the conversation mainly about?', 
'A business meeting schedule', 'A project deadline', 'A client presentation', 'A team lunch', 'B'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P3'), 2, '3', '/audio/part3/conversation1.mp3',
'What does the woman suggest?',
'Postponing the meeting', 'Working overtime', 'Hiring more staff', 'Canceling the project', 'A'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P3'), 3, '3', '/audio/part3/conversation1.mp3',
'When will they meet again?',
'This afternoon', 'Tomorrow morning', 'Next week', 'In two hours', 'C');

-- Part 4: Short Talks (30 câu hỏi - 10 đoạn nói chuyện, mỗi đoạn 3 câu)
INSERT INTO Exam_Section (exam_code, exam_name, section_name, part_number, question_count, year, duration, max_score, type, is_Free)
VALUES ('TOEIC2024_P4', 'TOEIC Part 4 - Short Talks', 'Listening', '4', 30, 2024, 30, 120, 'Practice Test', FALSE);

-- Thêm câu hỏi mẫu cho Part 4 (chỉ hiển thị 1 đoạn nói chuyện - 3 câu)
INSERT INTO Question (exam_section_id, question_number, part_number, audio_url, question_text, option_a, option_b, option_c, option_d, correct_answer)
VALUES 
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P4'), 1, '4', '/audio/part4/talk1.mp3',
'What is the announcement about?',
'A new company policy', 'Office renovation', 'Holiday schedule', 'Safety procedures', 'B'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P4'), 2, '4', '/audio/part4/talk1.mp3',
'When will the work be completed?',
'Next week', 'Next month', 'In two weeks', 'In three days', 'C'),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P4'), 3, '4', '/audio/part4/talk1.mp3',
'What are employees advised to do?',
'Work from home', 'Use another entrance', 'Come in early', 'Take vacation days', 'A');

-- Part 5: Incomplete Sentences (30 câu hỏi)
INSERT INTO Exam_Section (
    exam_code, exam_name, section_name, part_number, 
    question_count, year, duration, max_score, type, is_Free
) VALUES (
    'TOEIC2024_P5', 
    'TOEIC Part 5 - Incomplete Sentences', 
    'Reading', 
    '5', 
    30, 
    2024, 
    25, 
    120, 
    'Practice Test', 
    TRUE
);

-- Thêm câu hỏi mẫu cho Part 5 (5 câu đầu)
INSERT INTO Question (
    exam_section_id, 
    question_number, 
    part_number, 
    question_text, 
    option_a, 
    option_b, 
    option_c, 
    option_d, 
    correct_answer
) VALUES 
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P5'), 1, '5',
    'The company''s new policy requires all employees to _____ their vacation requests at least two weeks in advance.',
    'submit',
    'submitting',
    'submitted',
    'submission',
    'A'
),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P5'), 2, '5',
    '_____ the economic downturn, the company managed to increase its profits last quarter.',
    'Despite',
    'However',
    'Although',
    'Because',
    'A'
),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P5'), 3, '5',
    'The marketing team is working _____ to complete the campaign before the deadline.',
    'diligent',
    'diligently',
    'diligence',
    'diligented',
    'B'
),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P5'), 4, '5',
    'The new software update is _____ with all previous versions of the operating system.',
    'compatible',
    'compatibility',
    'compatibly',
    'compatibilize',
    'A'
),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P5'), 5, '5',
    'All participants must _____ the registration form by Friday.',
    'complete',
    'completing',
    'completed',
    'completion',
    'A'
);

-- Part 6: Text Completion (16 câu hỏi - 4 đoạn văn, mỗi đoạn 4 chỗ trống)
INSERT INTO Exam_Section (
    exam_code, exam_name, section_name, part_number, 
    question_count, year, duration, max_score, type, is_Free
) VALUES (
    'TOEIC2024_P6',
    'TOEIC Part 6 - Text Completion',
    'Reading',
    '6',
    16,
    2024,
    15,
    80,
    'Practice Test',
    FALSE
);

-- Thêm câu hỏi mẫu cho Part 6 (1 đoạn văn - 4 câu)
INSERT INTO Question (
    exam_section_id,
    question_number,
    part_number,
    question_text,
    option_a,
    option_b,
    option_c,
    option_d,
    correct_answer
) VALUES 
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P6'), 1, '6',
    'Dear valued customer, We are writing to inform you about important changes to our service _____ .',
    'policy',
    'policies',
    'policing',
    'policed',
    'B'
),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P6'), 2, '6',
    'These changes will _____ effect starting next month.',
    'take',
    'make',
    'do',
    'get',
    'A'
),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P6'), 3, '6',
    'We appreciate your _____ understanding of these necessary updates.',
    'continue',
    'continues',
    'continuing',
    'continued',
    'C'
),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P6'), 4, '6',
    'Please do not _____ to contact us if you have any questions.',
    'hesitate',
    'hesitates',
    'hesitating',
    'hesitated',
    'A'
);

-- Part 7: Reading Comprehension (54 câu hỏi - bao gồm single passages và double passages)
INSERT INTO Exam_Section (
    exam_code, exam_name, section_name, part_number, 
    question_count, year, duration, max_score, type, is_Free
) VALUES (
    'TOEIC2024_P7',
    'TOEIC Part 7 - Reading Comprehension',
    'Reading',
    '7',
    54,
    2024,
    55,
    180,
    'Practice Test',
    FALSE
);

-- Thêm câu hỏi mẫu cho Part 7 (3 câu cho một đoạn văn)
INSERT INTO Question (
    exam_section_id,
    question_number,
    part_number,
    question_text,
    option_a,
    option_b,
    option_c,
    option_d,
    correct_answer
) VALUES 
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P7'), 1, '7',
    'According to the email, what is the main purpose of the company meeting?',
    'To discuss budget cuts',
    'To announce promotions',
    'To review quarterly results',
    'To introduce new staff',
    'C'
),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P7'), 2, '7',
    'When will the meeting take place?',
    'Monday morning',
    'Tuesday afternoon',
    'Wednesday morning',
    'Thursday afternoon',
    'B'
),
((SELECT id FROM Exam_Section WHERE exam_code = 'TOEIC2024_P7'), 3, '7',
    'What are attendees asked to bring?',
    'Their laptops',
    'Department reports',
    'Sales figures',
    'Business cards',
    'A'
);

-- Thêm Full Test TOEIC
INSERT INTO Exam_Section (exam_code, exam_name, section_name, part_number, question_count, year, duration, max_score, type, is_Free)
VALUES ('TOEIC2024_FULL', 'TOEIC Full Test 2024', 'Full', 'Full', 200, 2024, 120, 990, 'Full Test', FALSE);

-- 3. Thêm chứng chỉ cho mỗi giáo viên
INSERT INTO Diploma (user_id, certificate_name, score, level, issued_by, issue_date, expiry_date)
SELECT id, 'TOEIC Certification', ROUND(RAND() * 200 + 600, 2), 'Advanced', 'ETS', '2024-01-15', '2026-01-15' FROM User WHERE role = 'TEACHER'
UNION ALL
SELECT id, 'English Proficiency', ROUND(RAND() * 100, 2), 'Intermediate', 'British Council', '2023-05-10', '2025-05-10' FROM User WHERE role = 'TEACHER'
UNION ALL
SELECT id, 'Academic IELTS', ROUND(RAND() * 9, 2), 'Upper-Intermediate', 'IDP', '2023-07-20', '2025-07-20' FROM User WHERE role = 'TEACHER';