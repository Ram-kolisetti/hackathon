-- Hospital Management Platform Sample Data
-- Run this script after schema.sql to populate the database with sample data

USE hospital_management;

-- Sample Hospitals
INSERT INTO hospitals (name, address, city, state, zip_code, phone, email, website) VALUES
('City General Hospital', '123 Main Street', 'New York', 'NY', '10001', '212-555-1000', 'info@citygeneral.com', 'www.citygeneral.com'),
('Memorial Medical Center', '456 Park Avenue', 'Chicago', 'IL', '60601', '312-555-2000', 'contact@memorialmed.com', 'www.memorialmed.com'),
('Sunshine Healthcare', '789 Beach Road', 'Miami', 'FL', '33101', '305-555-3000', 'info@sunshinehealthcare.com', 'www.sunshinehealthcare.com');

-- Sample Departments for each hospital
INSERT INTO departments (hospital_id, name, description) VALUES
-- City General Hospital Departments
(1, 'Cardiology', 'Heart and cardiovascular system specialists'),
(1, 'Neurology', 'Brain, spinal cord, and nervous system specialists'),
(1, 'Orthopedics', 'Bone, joint, ligament, tendon, and muscle specialists'),
(1, 'Pediatrics', 'Medical care for infants, children, and adolescents'),
-- Memorial Medical Center Departments
(2, 'Oncology', 'Cancer diagnosis and treatment specialists'),
(2, 'Gynecology', 'Female reproductive system specialists'),
(2, 'Dermatology', 'Skin, hair, and nail specialists'),
(2, 'Psychiatry', 'Mental health specialists'),
-- Sunshine Healthcare Departments
(3, 'Ophthalmology', 'Eye and vision specialists'),
(3, 'ENT', 'Ear, nose, and throat specialists'),
(3, 'Urology', 'Urinary tract and male reproductive system specialists'),
(3, 'Gastroenterology', 'Digestive system specialists');

-- Sample Super Admin
INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, status, email_verified) VALUES
(4, 'superadmin', 'admin@hospitalsystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', '555-123-4567', 'active', TRUE);

-- Get the super admin user ID
SET @super_admin_id = LAST_INSERT_ID();

-- Create super admin profile
INSERT INTO admin_profiles (user_id, hospital_id, admin_type) VALUES
(@super_admin_id, NULL, 'super_admin');

-- Sample Hospital Admins (one for each hospital)
INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, status, email_verified) VALUES
(3, 'cityadmin', 'admin@citygeneral.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'City', 'Admin', '212-555-1001', 'active', TRUE),
(3, 'memorialadmin', 'admin@memorialmed.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Memorial', 'Admin', '312-555-2001', 'active', TRUE),
(3, 'sunshineadmin', 'admin@sunshinehealthcare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sunshine', 'Admin', '305-555-3001', 'active', TRUE);

-- Get the hospital admin user IDs
SET @city_admin_id = LAST_INSERT_ID();
SET @memorial_admin_id = @city_admin_id + 1;
SET @sunshine_admin_id = @city_admin_id + 2;

-- Create hospital admin profiles
INSERT INTO admin_profiles (user_id, hospital_id, admin_type) VALUES
(@city_admin_id, 1, 'hospital_admin'),
(@memorial_admin_id, 2, 'hospital_admin'),
(@sunshine_admin_id, 3, 'hospital_admin');

-- Sample Doctors
INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, status, email_verified) VALUES
-- City General Hospital Doctors
(2, 'drsmith', 'smith@citygeneral.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Smith', '212-555-1100', 'active', TRUE),
(2, 'drjones', 'jones@citygeneral.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Emily', 'Jones', '212-555-1101', 'active', TRUE),
-- Memorial Medical Center Doctors
(2, 'drwilliams', 'williams@memorialmed.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Michael', 'Williams', '312-555-2100', 'active', TRUE),
(2, 'drbrown', 'brown@memorialmed.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah', 'Brown', '312-555-2101', 'active', TRUE),
-- Sunshine Healthcare Doctors
(2, 'drdavis', 'davis@sunshinehealthcare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Robert', 'Davis', '305-555-3100', 'active', TRUE),
(2, 'drmiller', 'miller@sunshinehealthcare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jennifer', 'Miller', '305-555-3101', 'active', TRUE);

-- Get the doctor user IDs
SET @dr_smith_id = LAST_INSERT_ID();
SET @dr_jones_id = @dr_smith_id + 1;
SET @dr_williams_id = @dr_smith_id + 2;
SET @dr_brown_id = @dr_smith_id + 3;
SET @dr_davis_id = @dr_smith_id + 4;
SET @dr_miller_id = @dr_smith_id + 5;

-- Create doctor profiles
INSERT INTO doctor_profiles (user_id, hospital_id, department_id, doctor_unique_id, specialization, qualification, experience_years, license_number, consultation_fee, available_days, available_time_start, available_time_end, bio) VALUES
(@dr_smith_id, 1, 1, 'DOC001', 'Cardiology', 'MD, Cardiology', 15, 'NY12345', 150.00, 'Mon,Tue,Wed,Thu', '09:00:00', '17:00:00', 'Dr. Smith is a renowned cardiologist with over 15 years of experience.'),
(@dr_jones_id, 1, 3, 'DOC002', 'Orthopedics', 'MD, Orthopedic Surgery', 10, 'NY67890', 130.00, 'Mon,Wed,Fri', '10:00:00', '18:00:00', 'Dr. Jones specializes in sports injuries and joint replacements.'),
(@dr_williams_id, 2, 5, 'DOC003', 'Oncology', 'MD, PhD in Oncology', 20, 'IL12345', 200.00, 'Tue,Thu,Sat', '08:00:00', '16:00:00', 'Dr. Williams is a leading oncologist with expertise in innovative cancer treatments.'),
(@dr_brown_id, 2, 7, 'DOC004', 'Dermatology', 'MD, Dermatology', 8, 'IL67890', 120.00, 'Mon,Tue,Thu,Fri', '09:00:00', '17:00:00', 'Dr. Brown specializes in cosmetic dermatology and skin cancer treatments.'),
(@dr_davis_id, 3, 9, 'DOC005', 'Ophthalmology', 'MD, Ophthalmology', 12, 'FL12345', 140.00, 'Wed,Thu,Fri', '08:30:00', '16:30:00', 'Dr. Davis is an expert in cataract surgery and retinal disorders.'),
(@dr_miller_id, 3, 11, 'DOC006', 'Urology', 'MD, Urology', 14, 'FL67890', 160.00, 'Mon,Tue,Wed', '10:00:00', '18:00:00', 'Dr. Miller specializes in minimally invasive urological procedures.');

-- Sample Patients
INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, status, email_verified) VALUES
(1, 'patient1', 'patient1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'David', 'Johnson', '555-111-2222', 'active', TRUE),
(1, 'patient2', 'patient2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lisa', 'Anderson', '555-333-4444', 'active', TRUE),
(1, 'patient3', 'patient3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'James', 'Wilson', '555-555-6666', 'active', TRUE),
(1, 'patient4', 'patient4@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria', 'Garcia', '555-777-8888', 'active', TRUE);

-- Get the patient user IDs
SET @patient1_id = LAST_INSERT_ID();
SET @patient2_id = @patient1_id + 1;
SET @patient3_id = @patient1_id + 2;
SET @patient4_id = @patient1_id + 3;

-- Create patient profiles
INSERT INTO patient_profiles (user_id, patient_unique_id, date_of_birth, gender, blood_group, address, city, state, zip_code, emergency_contact_name, emergency_contact_phone) VALUES
(@patient1_id, 'PAT001', '1985-06-15', 'male', 'O+', '123 Oak Street', 'New York', 'NY', '10002', 'Susan Johnson', '555-999-0000'),
(@patient2_id, 'PAT002', '1990-03-22', 'female', 'A-', '456 Maple Avenue', 'Chicago', 'IL', '60602', 'Mark Anderson', '555-888-9999'),
(@patient3_id, 'PAT003', '1978-11-30', 'male', 'B+', '789 Pine Road', 'Miami', 'FL', '33102', 'Emma Wilson', '555-777-6666'),
(@patient4_id, 'PAT004', '1995-09-10', 'female', 'AB+', '101 Cedar Lane', 'New York', 'NY', '10003', 'Carlos Garcia', '555-666-5555');

-- Sample Appointments
INSERT INTO appointments (patient_id, doctor_id, hospital_id, department_id, appointment_date, appointment_time, status, symptoms, payment_status) VALUES
(1, 1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '10:00:00', 'scheduled', 'Chest pain and shortness of breath', 'completed'),
(2, 3, 2, 5, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:30:00', 'scheduled', 'Follow-up for treatment plan', 'completed'),
(3, 5, 3, 9, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:15:00', 'scheduled', 'Blurred vision and eye pain', 'pending'),
(4, 2, 1, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:45:00', 'scheduled', 'Knee pain after sports injury', 'completed'),
(1, 4, 2, 7, DATE_ADD(CURDATE(), INTERVAL -5 DAY), '13:00:00', 'completed', 'Skin rash and itching', 'completed');

-- Sample Medical Records for completed appointments
INSERT INTO medical_records (appointment_id, patient_id, doctor_id, diagnosis, prescription, notes, follow_up_date) VALUES
(5, 1, 4, 'Contact dermatitis', 'Topical corticosteroid cream to be applied twice daily for 7 days', 'Advised to avoid potential allergens and use hypoallergenic products', DATE_ADD(CURDATE(), INTERVAL 14 DAY));

-- Sample Payments
INSERT INTO payments (appointment_id, patient_id, amount, payment_method, transaction_id, status) VALUES
(1, 1, 150.00, 'card', 'TXN123456789', 'completed'),
(2, 2, 200.00, 'upi', 'TXN987654321', 'completed'),
(4, 4, 130.00, 'net_banking', 'TXN456789123', 'completed'),
(5, 1, 120.00, 'card', 'TXN789123456', 'completed');

-- Sample Reviews
INSERT INTO reviews (patient_id, doctor_id, hospital_id, appointment_id, rating, review_text, is_approved) VALUES
(1, 4, 2, 5, 5, 'Dr. Brown was very thorough and explained everything clearly. The treatment worked perfectly!', TRUE);

-- Sample Notifications
INSERT INTO notifications (user_id, title, message) VALUES
(@patient1_id, 'Appointment Reminder', 'You have an appointment with Dr. Smith tomorrow at 10:00 AM.'),
(@patient2_id, 'Appointment Reminder', 'You have an appointment with Dr. Williams tomorrow at 2:30 PM.'),
(@dr_smith_id, 'New Appointment', 'You have a new appointment scheduled with David Johnson.'),
(@dr_williams_id, 'New Appointment', 'You have a new appointment scheduled with Lisa Anderson.');

-- Note: All passwords in this sample data are set to 'password' using bcrypt hash
-- The hash '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' is the bcrypt hash for 'password'