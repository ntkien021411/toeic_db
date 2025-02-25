-- Chọn database sử dụng
USE toeic_db;

-- 1. Bảng Account (Bảng gốc chứa thông tin tài khoản)
CREATE TABLE Account (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    active_status BOOLEAN NOT NULL DEFAULT FALSE,
    is_active_date TIMESTAMP NULL,
    active_date TIMESTAMP NULL,
    is_first BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE
);

-- 2. Bảng User (Tham chiếu đến Account)
CREATE TABLE User (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNIQUE NOT NULL,
    role ENUM('TEACHER', 'STUDENT', 'ADMIN') NOT NULL,
    first_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NULL,
    birth_of_date DATE NULL,
    phone VARCHAR(15) UNIQUE NULL,
    facebook_link VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    INDEX idx_account_id (account_id),
    CONSTRAINT fk_user_account FOREIGN KEY (account_id) REFERENCES Account(id) ON DELETE CASCADE
);

-- 3. Bảng Class (Lớp học)
CREATE TABLE Class (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_code VARCHAR(50) UNIQUE NOT NULL,
    class_name VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    student_count INT DEFAULT 0,
    teacher_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE
);

-- 4. Bảng Class_User (Mối quan hệ giữa Class và User)
CREATE TABLE Class_User (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('TEACHER', 'STUDENT') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_class FOREIGN KEY (class_id) REFERENCES Class(id) ON DELETE CASCADE,
    CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE,
    UNIQUE (class_id, user_id)
);

-- 5. Bảng Exam_Section (Các phần thi TOEIC)
CREATE TABLE Exam_Section (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_code VARCHAR(50) UNIQUE NOT NULL,
    exam_name VARCHAR(255) NOT NULL,
    section_name ENUM('Listening', 'Reading') NOT NULL,
    part_number TINYINT NOT NULL CHECK (part_number BETWEEN 1 AND 7), 
    question_count INT NOT NULL CHECK (question_count > 0),
    duration INT NOT NULL CHECK (duration > 0),
    max_score INT NOT NULL CHECK (max_score > 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE
);

-- 6. Bảng Question (Câu hỏi)
CREATE TABLE Question (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_section_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
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
    score DECIMAL(5,2) NOT NULL,
    level ENUM('A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'TOEIC', 'IELTS', 'TOEFL') NOT NULL,
    issued_by VARCHAR(255) NOT NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE NULL,
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

-- MẬT KHẨU ADMIN : ADMIN , MẬT KHẨU USER : 123456
-- Thêm tài khoản Admin vào bảng Account
INSERT INTO Account (username, email, password, active_status, is_active_date, active_date) VALUES
('admin', 'admin@example.com', '$2y$10$3bpAkU1C09o..qM1DBnnQeY8ZU5TdVl2o5q.KMLfegnQ4eAfU2xSq', true, NOW(), NOW());

-- Thêm tài khoản giáo viên vào bảng Account
INSERT INTO Account (username, email, password, active_status, is_active_date, active_date) VALUES
('teacher1', 'teacher1@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('teacher2', 'teacher2@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('teacher3', 'teacher3@example.com', '$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL);

-- Thêm Admin vào bảng User
INSERT INTO User (account_id, role, first_name, last_name, birth_of_date, phone, facebook_link) VALUES
((SELECT id FROM Account WHERE username = 'admin'), 'ADMIN', 'System', 'Admin', '1980-01-01', '0909123456', 'https://facebook.com/admin');

-- Thêm giáo viên vào bảng User
INSERT INTO User (account_id, role, first_name, last_name, birth_of_date, phone, facebook_link) VALUES
((SELECT id FROM Account WHERE username = 'teacher1'), 'TEACHER', 'John', 'Doe', '1980-01-15', '0987654321', 'https://facebook.com/john.doe'),
((SELECT id FROM Account WHERE username = 'teacher2'), 'TEACHER', 'Jane', 'Smith', '1985-06-20', '0977123456', 'https://facebook.com/jane.smith'),
((SELECT id FROM Account WHERE username = 'teacher3'), 'TEACHER', 'Alice', 'Brown', '1990-09-10', '0966987456', 'https://facebook.com/alice.brown');

-- Thêm tài khoản học viên
INSERT INTO Account (username, email, password, active_status, is_active_date, active_date) VALUES
('student1', 'student1@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student2', 'student2@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student3', 'student3@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student4', 'student4@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student5', 'student5@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student6', 'student6@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student7', 'student7@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student8', 'student8@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student9', 'student9@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student10', 'student10@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student11', 'student11@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student12', 'student12@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student13', 'student13@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student14', 'student14@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL),
('student15', 'student15@example.com','$2y$10$1YiNWOwLxs6v7L1LuVU9vO4MeyJVOIwtGSWngvooxfH5Pmw.SXBIy', false, NULL, NULL);
-- Thêm học viên vào bảng User
INSERT INTO User (account_id, role, first_name, last_name, birth_of_date, phone, facebook_link) VALUES

(5, 'STUDENT', 'Sara', 'White', '2006-07-22', '0934567892', 'https://facebook.com/sara.white'),
(6, 'STUDENT', 'Chris', 'Green', '2007-02-14', '0934567893', 'https://facebook.com/chris.green'),
(7, 'STUDENT', 'David', 'Blue', '2005-12-01', '0934567894', 'https://facebook.com/david.blue'),
(8, 'STUDENT', 'Emily', 'Black', '2006-03-18', '0934567895', 'https://facebook.com/emily.black'),
(9, 'STUDENT', 'Daniel', 'Brown', '2005-09-25', '0934567896', 'https://facebook.com/daniel.brown'),
(10, 'STUDENT', 'Sophia', 'Wilson', '2007-04-30', '0934567897', 'https://facebook.com/sophia.wilson'),
(11, 'STUDENT', 'Olivia', 'Taylor', '2006-10-05', '0934567898', 'https://facebook.com/olivia.taylor'),
(12, 'STUDENT', 'James', 'Anderson', '2007-06-20', '0934567899', 'https://facebook.com/james.anderson'),
(13, 'STUDENT', 'Mia', 'Thomas', '2006-11-11', '0934567800', 'https://facebook.com/mia.thomas'),
(14, 'STUDENT', 'William', 'Martinez', '2005-08-29', '0934567801', 'https://facebook.com/william.martinez'),
(15, 'STUDENT', 'Charlotte', 'Garcia', '2006-01-16', '0934567802', 'https://facebook.com/charlotte.garcia'),
(16, 'STUDENT', 'Benjamin', 'Rodriguez', '2007-05-07', '0934567803', 'https://facebook.com/benjamin.rodriguez'),
(17, 'STUDENT', 'Lucas', 'Hernandez', '2005-12-23', '0934567804', 'https://facebook.com/lucas.hernandez'),
(18, 'STUDENT', 'Isabella', 'Lopez', '2006-09-15', '0934567805', 'https://facebook.com/isabella.lopez'),
(19, 'STUDENT', 'Mike', 'Johnson', '2005-05-10', '0934567891', 'https://facebook.com/mike.johnson');

-- Thêm bằng cấp cho giáo viên
INSERT INTO Diploma (user_id, certificate_name, score, level, issued_by, issue_date, expiry_date) VALUES
(4, 'TOEIC Certification', 950, 'TOEIC', 'ETS', '2020-05-10', '2022-05-10'),
(4, 'IELTS Certification', 7.5, 'IELTS', 'British Council', '2019-11-15', '2021-11-15'),
(2, 'TOEFL Certification', 110, 'TOEFL', 'ETS', '2021-03-20', '2023-03-20'),
(3, 'TOEIC Certification', 900, 'TOEIC', 'ETS', '2020-07-22', '2022-07-22');

-- Thêm lớp học
INSERT INTO Class (class_code, class_name, start_date, end_date, student_count, teacher_name) VALUES
('CLASS101', 'English Beginner', '2024-01-15', '2024-06-15', 5, 'John Doe'),
('CLASS102', 'Intermediate English', '2024-02-10', '2024-07-10', 5, 'Jane Smith'),
('CLASS103', 'Advanced English', '2024-03-05', '2024-08-05', 5, 'Alice Brown');

-- Gán giáo viên vào lớp học
INSERT INTO Class_User (class_id, user_id) VALUES
(1, 4),
(2, 2),
(3, 3);

-- Gán học sinh vào lớp học
INSERT INTO Class_User (class_id, user_id, role) VALUES
(1, 5, 'STUDENT'), (1, 6, 'STUDENT'), (1, 7, 'STUDENT'), (1, 8, 'STUDENT'), (1, 9, 'STUDENT'),
(2, 10, 'STUDENT'), (2, 19, 'STUDENT'), (2, 11, 'STUDENT'), (2, 12, 'STUDENT'), (2, 13, 'STUDENT'),
(3, 14, 'STUDENT'), (3, 15, 'STUDENT'), (3, 16, 'STUDENT'), (3, 17, 'STUDENT'), (3, 18, 'STUDENT');

