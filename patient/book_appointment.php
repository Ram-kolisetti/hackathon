<?php
// Book Appointment Page
session_start();

// Define base path for includes
define('BASE_PATH', dirname(__DIR__));

// Include authentication functions
require_once(BASE_PATH . '/includes/auth.php');

// Check if user is logged in and is a patient
if (!isLoggedIn() || !hasRole('patient')) {
    header('Location: ../login.php');
    exit;
}

// Get patient information
$user_id = $_SESSION['user_id'];
$patient_id = $_SESSION['profile_id'];

// Get list of hospitals
$sql = "SELECT hospital_id, name, city, state FROM hospitals WHERE status = 'active' ORDER BY name ASC";
$hospitals = fetchRows($sql);

$success = '';
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $hospital_id = sanitizeInput($_POST['hospital']);
    $department_id = sanitizeInput($_POST['department']);
    $doctor_id = sanitizeInput($_POST['doctor']);
    $appointment_date = sanitizeInput($_POST['appointment_date']);
    $appointment_time = sanitizeInput($_POST['appointment_time']);
    $symptoms = sanitizeInput($_POST['symptoms']);
    
    // Validate form data
    if (empty($hospital_id) || empty($department_id) || empty($doctor_id) || 
        empty($appointment_date) || empty($appointment_time)) {
        $error = 'Please fill in all required fields';
    } else {
        // Check if the appointment slot is available
        $sql = "SELECT appointment_id FROM appointments 
                WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
                AND status = 'scheduled'";
        $result = executeQuery($sql, "iss", [$doctor_id, $appointment_date, $appointment_time]);
        
        if ($result->num_rows > 0) {
            $error = 'This appointment slot is already booked. Please select a different time.';
        } else {
            // Insert appointment
            $sql = "INSERT INTO appointments (patient_id, doctor_id, hospital_id, department_id, 
                    appointment_date, appointment_time, symptoms, status, payment_status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', 'pending')";
            
            $appointment_id = insertData($sql, "iiissss", [
                $patient_id, $doctor_id, $hospital_id, $department_id, 
                $appointment_date, $appointment_time, $symptoms
            ]);
            
            if ($appointment_id) {
                // Create notification for patient
                $sql = "INSERT INTO notifications (user_id, title, message) 
                        VALUES (?, 'Appointment Scheduled', 'Your appointment has been scheduled successfully for ".
                        date('d M Y', strtotime($appointment_date))." at ".
                        date('h:i A', strtotime($appointment_time)).".')";
                insertData($sql, "i", [$user_id]);
                
                // Get doctor's user_id
                $sql = "SELECT user_id FROM doctor_profiles WHERE doctor_id = ?";
                $doctor = fetchRow($sql, "i", [$doctor_id]);
                
                if ($doctor) {
                    // Create notification for doctor
                    $sql = "INSERT INTO notifications (user_id, title, message) 
                            VALUES (?, 'New Appointment', 'A new appointment has been scheduled for ".
                            date('d M Y', strtotime($appointment_date))." at ".
                            date('h:i A', strtotime($appointment_time)).".')";
                    insertData($sql, "i", [$doctor['user_id']]);
                }
                
                $success = 'Appointment scheduled successfully! Proceed to payment to confirm your appointment.';
                
                // Redirect to payment page
                header("Location: payment.php?id=$appointment_id");
                exit;
            } else {
                $error = 'Failed to schedule appointment. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Hospital Management Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .booking-container {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        
        .booking-header {
            margin-bottom: var(--spacing-lg);
            text-align: center;
        }
        
        .booking-header h1 {
            color: var(--primary-color);
            margin-bottom: var(--spacing-sm);
        }
        
        .booking-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--spacing-xl);
            position: relative;
        }
        
        .booking-steps::before {
            content: '';
            position: absolute;
            top: 24px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: var(--gray-300);
            z-index: 1;
        }
        
        .booking-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--gray-300);
            color: var(--gray-700);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            transition: all var(--transition-normal);
        }
        
        .step-text {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            text-align: center;
            transition: all var(--transition-normal);
        }
        
        .booking-step.active .step-number {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .booking-step.active .step-text {
            color: var(--secondary-color);
            font-weight: 600;
        }
        
        .booking-step.completed .step-number {
            background-color: var(--success-color);
            color: white;
        }
        
        .booking-form-section {
            margin-bottom: var(--spacing-lg);
        }
        
        .section-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            margin-bottom: var(--spacing-md);
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: var(--spacing-sm);
            color: var(--secondary-color);
        }
        
        .form-row {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }
        
        .form-group {
            flex: 1;
        }
        
        .doctor-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
        }
        
        .doctor-card {
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-md);
            padding: var(--spacing-md);
            cursor: pointer;
            transition: all var(--transition-normal);
        }
        
        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--secondary-color);
        }
        
        .doctor-card.selected {
            border-color: var(--secondary-color);
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .doctor-info {
            display: flex;
            align-items: center;
            margin-bottom: var(--spacing-sm);
        }
        
        .doctor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: var(--spacing-md);
            color: var(--gray-600);
            font-size: var(--font-size-xl);
        }
        
        .doctor-name {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .doctor-speciality {
            color: var(--secondary-color);
            font-size: var(--font-size-sm);
        }
        
        .doctor-details {
            margin-top: var(--spacing-sm);
            font-size: var(--font-size-sm);
        }
        
        .doctor-detail {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
        }
        
        .doctor-detail i {
            width: 16px;
            margin-right: var(--spacing-sm);
            color: var(--gray-600);
        }
        
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: var(--spacing-sm);
            margin-top: var(--spacing-md);
        }
        
        .time-slot {
            padding: var(--spacing-sm);
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-md);
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-normal);
        }
        
        .time-slot:hover {
            border-color: var(--secondary-color);
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .time-slot.selected {
            border-color: var(--secondary-color);
            background-color: var(--secondary-color);
            color: white;
        }
        
        .time-slot.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: var(--gray-200);
        }
        
        .booking-summary {
            background-color: var(--gray-100);
            border-radius: var(--border-radius-md);
            padding: var(--spacing-md);
            margin-top: var(--spacing-lg);
        }
        
        .summary-title {
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            color: var(--primary-color);
        }
        
        .summary-item {
            display: flex;
            margin-bottom: var(--spacing-sm);
        }
        
        .summary-label {
            width: 150px;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .summary-value {
            flex: 1;
        }
        
        .booking-actions {
            display: flex;
            justify-content: space-between;
            margin-top: var(--spacing-lg);
        }
        
        @media (max-width: 768px) {
            .booking-steps {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-md);
            }
            
            .booking-steps::before {
                display: none;
            }
            
            .booking-step {
                flex-direction: row;
                width: 100%;
            }
            
            .step-number {
                margin-bottom: 0;
                margin-right: var(--spacing-sm);
                width: 40px;
                height: 40px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .doctor-list {
                grid-template-columns: 1fr;
            }
            
            .time-slots {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">MedConnect</a>
        </div>
        
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="dashboard.php" class="sidebar-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-item">
                <a href="appointments.php" class="sidebar-link">
                    <i class="fas fa-calendar-check"></i> Appointments
                </a>
            </li>
            <li class="sidebar-item">
                <a href="book_appointment.php" class="sidebar-link active">
                    <i class="fas fa-plus-circle"></i> Book Appointment
                </a>
            </li>
            <li class="sidebar-item">
                <a href="medical_history.php" class="sidebar-link">
                    <i class="fas fa-file-medical"></i> Medical History
                </a>
            </li>
            <li class="sidebar-item">
                <a href="hospitals.php" class="sidebar-link">
                    <i class="fas fa-hospital"></i> Hospitals
                </a>
            </li>
            <li class="sidebar-item">
                <a href="doctors.php" class="sidebar-link">
                    <i class="fas fa-user-md"></i> Doctors
                </a>
            </li>
            <li class="sidebar-item">
                <a href="profile.php" class="sidebar-link">
                    <i class="fas fa-user"></i> My Profile
                </a>
            </li>
            <li class="sidebar-divider"></li>
            <li class="sidebar-item">
                <a href="../logout.php" class="sidebar-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    
    <div class="content-wrapper">
        <nav class="navbar">
            <button id="sidebarToggle" class="navbar-toggler">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="navbar-nav ml-auto">
                <div class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user-circle"></i> <?php echo $_SESSION['first_name']; ?>
                    </a>
                </div>
            </div>
        </nav>
        
        <div class="container">
            <div class="booking-container">
                <div class="booking-header">
                    <h1>Book an Appointment</h1>
                    <p>Schedule an appointment with our specialists</p>
                </div>
                
                <div class="booking-steps">
                    <div class="booking-step active" id="step1">
                        <div class="step-number">1</div>
                        <div class="step-text">Select Hospital</div>
                    </div>
                    <div class="booking-step" id="step2">
                        <div class="step-number">2</div>
                        <div class="step-text">Choose Department</div>
                    </div>
                    <div class="booking-step" id="step3">
                        <div class="step-number">3</div>
                        <div class="step-text">Select Doctor</div>
                    </div>
                    <div class="booking-step" id="step4">
                        <div class="step-number">4</div>
                        <div class="step-text">Book Appointment</div>
                    </div>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="appointmentForm">
                    <div class="booking-form-section" id="hospitalSection">
                        <h3 class="section-title">
                            <i class="fas fa-hospital"></i> Select Hospital
                        </h3>
                        
                        <div class="form-group">
                            <label for="hospital" class="form-label">Hospital</label>
                            <select id="hospital" name="hospital" class="form-select" required>
                                <option value="" disabled selected>Select Hospital</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?php echo $hospital['hospital_id']; ?>">
                                        <?php echo $hospital['name'] . ' - ' . $hospital['city'] . ', ' . $hospital['state']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="text-right mt-4">
                            <button type="button" class="btn btn-primary" id="nextToDepartment">
                                Next: Choose Department <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="booking-form-section d-none" id="departmentSection">
                        <h3 class="section-title">
                            <i class="fas fa-stethoscope"></i> Choose Department
                        </h3>
                        
                        <div class="form-group">
                            <label for="department" class="form-label">Department</label>
                            <select id="department" name="department" class="form-select" required disabled>
                                <option value="" disabled selected>Select Department</option>
                                <!-- Departments will be loaded dynamically based on selected hospital -->
                            </select>
                        </div>
                        
                        <div class="text-right mt-4">
                            <button type="button" class="btn btn-secondary" id="backToHospital">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" id="nextToDoctor">
                                Next: Select Doctor <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="booking-form-section d-none" id="doctorSection">
                        <h3 class="section-title">
                            <i class="fas fa-user-md"></i> Select Doctor
                        </h3>
                        
                        <div class="form-group">
                            <label for="doctor" class="form-label">Doctor</label>
                            <select id="doctor" name="doctor" class="form-select d-none" required>
                                <option value="" disabled selected>Select Doctor</option>
                                <!-- Doctors will be loaded dynamically based on selected department -->
                            </select>
                            
                            <div class="doctor-list" id="doctorList">
                                <!-- Doctors will be loaded dynamically -->
                                <div class="text-center py-4" id="doctorLoadingMessage">
                                    <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i>
                                    <p>Loading available doctors...</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-right mt-4">
                            <button type="button" class="btn btn-secondary" id="backToDepartment">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" id="nextToSchedule">
                                Next: Schedule Appointment <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="booking-form-section d-none" id="scheduleSection">
                        <h3 class="section-title">
                            <i class="fas fa-calendar-alt"></i> Schedule Appointment
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="appointment_date" class="form-label">Appointment Date</label>
                                <input type="date" id="appointment_date" name="appointment_date" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="appointment_time" class="form-label">Appointment Time</label>
                                <input type="hidden" id="appointment_time" name="appointment_time" required>
                                <select id="appointment_time_select" class="form-select" disabled>
                                    <option value="" disabled selected>Select Time</option>
                                    <!-- Time slots will be loaded dynamically based on doctor availability -->
                                </select>
                                
                                <div class="time-slots" id="timeSlots">
                                    <!-- Time slots will be loaded dynamically -->
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="symptoms" class="form-label">Symptoms or Reason for Visit</label>
                            <textarea id="symptoms" name="symptoms" class="form-control" rows="4" placeholder="Please describe your symptoms or reason for the appointment"></textarea>
                        </div>
                        
                        <div class="booking-summary" id="bookingSummary">
                            <h4 class="summary-title">Appointment Summary</h4>
                            
                            <div class="summary-item">
                                <div class="summary-label">Hospital:</div>
                                <div class="summary-value" id="summaryHospital">-</div>
                            </div>
                            
                            <div class="summary-item">
                                <div class="summary-label">Department:</div>
                                <div class="summary-value" id="summaryDepartment">-</div>
                            </div>
                            
                            <div class="summary-item">
                                <div class="summary-label">Doctor:</div>
                                <div class="summary-value" id="summaryDoctor">-</div>
                            </div>
                            
                            <div class="summary-item">
                                <div class="summary-label">Date & Time:</div>
                                <div class="summary-value" id="summaryDateTime">-</div>
                            </div>
                        </div>
                        
                        <div class="booking-actions">
                            <button type="button" class="btn btn-secondary" id="backToDoctor">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="submit" class="btn btn-success" id="confirmBooking">
                                <i class="fas fa-check-circle"></i> Confirm & Proceed to Payment
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Chatbot Widget -->
    <div class="chatbot-container">
        <div class="chatbot-header" id="chatbotToggle">
            <div class="chatbot-title">
                <i class="fas fa-robot"></i> Medical Assistant
            </div>
            <i class="fas fa-chevron-up"></i>
        </div>
        <div class="chatbot-body" id="chatbotBody">
            <div class="chat-message bot">
                <div class="chat-bubble">
                    Hello! I'm your medical assistant. How can I help you with booking your appointment today?
                </div>
                <div class="chat-time">Just now</div>
            </div>
        </div>
        <div class="chatbot-footer">
            <input type="text" class="chatbot-input" placeholder="Type your message...">
            <div class="chatbot-send">
                <i class="fas fa-paper-plane"></i>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the booking form steps
            const hospitalSection = document.getElementById('hospitalSection');
            const departmentSection = document.getElementById('departmentSection');
            const doctorSection = document.getElementById('doctorSection');
            const scheduleSection = document.getElementById('scheduleSection');
            
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const step3 = document.getElementById('step3');
            const step4 = document.getElementById('step4');
            
            const hospitalSelect = document.getElementById('hospital');
            const departmentSelect = document.getElementById('department');
            const doctorSelect = document.getElementById('doctor');
            const doctorList = document.getElementById('doctorList');
            const doctorLoadingMessage = document.getElementById('doctorLoadingMessage');
            const appointmentDateInput = document.getElementById('appointment_date');
            const appointmentTimeSelect = document.getElementById('appointment_time');
            const timeSlots = document.getElementById('timeSlots');
            
            const summaryHospital = document.getElementById('summaryHospital');
            const summaryDepartment = document.getElementById('summaryDepartment');
            const summaryDoctor = document.getElementById('summaryDoctor');
            const summaryDateTime = document.getElementById('summaryDateTime');
            
            // Navigation buttons
            const nextToDepartment = document.getElementById('nextToDepartment');
            const backToHospital = document.getElementById('backToHospital');
            const nextToDoctor = document.getElementById('nextToDoctor');
            const backToDepartment = document.getElementById('backToDepartment');
            const nextToSchedule = document.getElementById('nextToSchedule');
            const backToDoctor = document.getElementById('backToDoctor');
            
            // Set min date to today for appointment date
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            appointmentDateInput.min = `${yyyy}-${mm}-${dd}`;
            
            // Step 1 to Step 2
            nextToDepartment.addEventListener('click', function() {
                if (hospitalSelect.value) {
                    hospitalSection.classList.add('d-none');
                    departmentSection.classList.remove('d-none');
                    
                    step1.classList.remove('active');
                    step1.classList.add('completed');
                    step2.classList.add('active');
                    
                    // Update summary
                    const hospitalText = hospitalSelect.options[hospitalSelect.selectedIndex].text;
                    summaryHospital.textContent = hospitalText;
                    
                    // Load departments for selected hospital
                    loadDepartments(hospitalSelect.value);
                } else {
                    alert('Please select a hospital');
                }
            });
            
            // Step 2 back to Step 1
            backToHospital.addEventListener('click', function() {
                departmentSection.classList.add('d-none');
                hospitalSection.classList.remove('d-none');
                
                step2.classList.remove('active');
                step1.classList.remove('completed');
                step1.classList.add('active');
            });
            
            // Step 2 to Step 3
            nextToDoctor.addEventListener('click', function() {
                if (departmentSelect.value) {
                    departmentSection.classList.add('d-none');
                    doctorSection.classList.remove('d-none');
                    
                    step2.classList.remove('active');
                    step2.classList.add('completed');
                    step3.classList.add('active');
                    
                    // Update summary
                    const departmentText = departmentSelect.options[departmentSelect.selectedIndex].text;
                    summaryDepartment.textContent = departmentText;
                    
                    // Load doctors for selected department
                    loadDoctors(hospitalSelect.value, departmentSelect.value);
                } else {
                    alert('Please select a department');
                }
            });
            
            // Step 3 back to Step 2
            backToDepartment.addEventListener('click', function() {
                doctorSection.classList.add('d-none');
                departmentSection.classList.remove('d-none');
                
                step3.classList.remove('active');
                step2.classList.remove('completed');
                step2.classList.add('active');
            });
            
            // Step 3 to Step 4
            nextToSchedule.addEventListener('click', function() {
                if (doctorSelect.value) {
                    doctorSection.classList.add('d-none');
                    scheduleSection.classList.remove('d-none');
                    
                    step3.classList.remove('active');
                    step3.classList.add('completed');
                    step4.classList.add('active');
                    
                    // Update summary
                    const doctorText = doctorSelect.options[doctorSelect.selectedIndex].text;
                    summaryDoctor.textContent = doctorText;
                    
                    // Reset date and time
                    appointmentDateInput.value = '';
                    clearTimeSlots();
                } else {
                    alert('Please select a doctor');
                }
            });
            
            // Step 4 back to Step 3
            backToDoctor.addEventListener('click', function() {
                scheduleSection.classList.add('d-none');
                doctorSection.classList.remove('d-none');
                
                step4.classList.remove('active');
                step3.classList.remove('completed');
                step3.classList.add('active');
            });
            
            // Load departments for selected hospital
            function loadDepartments(hospitalId) {
                // Clear current options
                clearSelect(departmentSelect, 'Select Department');
                departmentSelect.disabled = true;
                
                // Show loading indicator
                departmentSelect.parentNode.insertAdjacentHTML('beforeend', 
                    '<div id="departmentLoading" class="mt-2"><i class="fas fa-spinner fa-spin"></i> Loading departments...</div>');
                
                // Simulate API call (replace with actual AJAX call in production)
                setTimeout(function() {
                    // Remove loading indicator
                    const loadingElement = document.getElementById('departmentLoading');
                    if (loadingElement) loadingElement.remove();
                    
                    // Sample departments data (would come from API)
                    const departments = {
                        '1': [ // City General Hospital
                            {id: '1', name: 'Cardiology'},
                            {id: '2', name: 'Neurology'},
                            {id: '3', name: 'Orthopedics'},
                            {id: '4', name: 'Pediatrics'}
                        ],
                        '2': [ // Memorial Medical Center
                            {id: '5', name: 'Oncology'},
                            {id: '6', name: 'Gynecology'},
                            {id: '7', name: 'Dermatology'},
                            {id: '8', name: 'Psychiatry'}
                        ],
                        '3': [ // Sunshine Healthcare
                            {id: '9', name: 'Ophthalmology'},
                            {id: '10', name: 'ENT'},
                            {id: '11', name: 'Urology'},
                            {id: '12', name: 'Gastroenterology'}
                        ]
                    };
                    
                    // Add options
                    if (departments[hospitalId]) {
                        departments[hospitalId].forEach(function(dept) {
                            const option = document.createElement('option');
                            option.value = dept.id;
                            option.textContent = dept.name;
                            departmentSelect.appendChild(option);
                        });
                    }
                    
                    departmentSelect.disabled = false;
                }, 1000);
            }
            
            // Load doctors for selected department
            function loadDoctors(hospitalId, departmentId) {
                // Clear current doctor list
                doctorList.innerHTML = '';
                doctorList.appendChild(doctorLoadingMessage);
                
                // Clear doctor select
                clearSelect(doctorSelect, 'Select Doctor');
                
                // Simulate API call (replace with actual AJAX call in production)
                setTimeout(function() {
                    // Remove loading message
                    doctorLoadingMessage.remove();
                    
                    // Sample doctors data (would come from API)
                    const doctors = {
                        '1': { // City General Hospital
                            '1': [{ // Cardiology
                                id: '1', 
                                name: 'Dr. John Smith', 
                                specialization: 'Cardiology',
                                experience: '15 years',
                                fee: '$150',
                                rating: '4.8/5'
                            }],
                            '3': [{ // Orthopedics
                                id: '2', 
                                name: 'Dr. Emily Jones', 
                                specialization: 'Orthopedics',
                                experience: '10 years',
                                fee: '$130',
                                rating: '4.7/5'
                            }]
                        },
                        '2': { // Memorial Medical Center
                            '5': [{ // Oncology
                                id: '3', 
                                name: 'Dr. Michael Williams', 
                                specialization: 'Oncology',
                                experience: '20 years',
                                fee: '$200',
                                rating: '4.9/5'
                            }],
                            '7': [{ // Dermatology
                                id: '4', 
                                name: 'Dr. Sarah Brown', 
                                specialization: 'Dermatology',
                                experience: '8 years',
                                fee: '$120',
                                rating: '4.6/5'
                            }]
                        },
                        '3': { // Sunshine Healthcare
                            '9': [{ // Ophthalmology
                                id: '5', 
                                name: 'Dr. Robert Davis', 
                                specialization: 'Ophthalmology',
                                experience: '12 years',
                                fee: '$140',
                                rating: '4.8/5'
                            }],
                            '11': [{ // Urology
                                id: '6', 
                                name: 'Dr. Jennifer Miller', 
                                specialization: 'Urology',
                                experience: '14 years',
                                fee: '$160',
                                rating: '4.7/5'
                            }]
                        }
                    };
                    
                    // Check if doctors exist for this hospital and department
                    if (doctors[hospitalId] && doctors[hospitalId][departmentId]) {
                        const doctorsArray = doctors[hospitalId][departmentId];
                        
                        // Add doctors to the list
                        doctorsArray.forEach(function(doctor) {
                            const doctorCard = document.createElement('div');
                            doctorCard.className = 'doctor-card';
                            doctorCard.dataset.doctorId = doctor.id;
                            doctorCard.innerHTML = `
                                <div class="doctor-info">
                                    <div class="doctor-avatar">
                                        <i class="fas fa-user-md"></i>
                                    </div>
                                    <div>
                                        <div class="doctor-name">${doctor.name}</div>
                                        <div class="doctor-speciality">${doctor.specialization}</div>
                                    </div>
                                </div>
                                <div class="doctor-details">
                                    <div class="doctor-detail">
                                        <i class="fas fa-briefcase"></i>
                                        <span>Experience: ${doctor.experience}</span>
                                    </div>
                                    <div class="doctor-detail">
                                        <i class="fas fa-money-bill-alt"></i>
                                        <span>Consultation Fee: ${doctor.fee}</span>
                                    </div>
                                    <div class="doctor-detail">
                                        <i class="fas fa-star"></i>
                                        <span>Rating: ${doctor.rating}</span>
                                    </div>
                                </div>
                            `;
                            
                            // Add click event to select doctor
                            doctorCard.addEventListener('click', function() {
                                // Remove selected class from all doctor cards
                                document.querySelectorAll('.doctor-card').forEach(function(card) {
                                    card.classList.remove('selected');
                                });
                                
                                // Add selected class to clicked card
                                this.classList.add('selected');
                                
                                // Set doctor select value
                                doctorSelect.value = this.dataset.doctorId;
                                
                                // Add option to doctor select if it doesn't exist
                                if (!doctorSelect.querySelector(`option[value="${this.dataset.doctorId}"]`)) {
                                    const option = document.createElement('option');
                                    option.value = this.dataset.doctorId;
                                    option.textContent = doctor.name;
                                    doctorSelect.appendChild(option);
                                    doctorSelect.value = this.dataset.doctorId;
                                }
                            });
                            
                            doctorList.appendChild(doctorCard);
                        });
                        
                        // If no doctors found
                        if (doctorsArray.length === 0) {
                            doctorList.innerHTML = `
                                <div class="text-center py-4">
                                    <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                                    <p>No doctors available for this department.</p>
                                </div>
                            `;
                        }
                    } else {
                        doctorList.innerHTML = `
                            <div class="text-center py-4">
                                <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                                <p>No doctors available for this department.</p>
                            </div>
                        `;
                    }
                }, 1500);
            }
            
            // Update time slots when date changes
            appointmentDateInput.addEventListener('change', function() {
                if (this.value && doctorSelect.value) {
                    loadTimeSlots(doctorSelect.value, this.value);
                } else {
                    clearTimeSlots();
                }
                
                // Update summary
                updateDateTimeSummary();
            });
            
            // Load time slots for selected doctor and date
            function loadTimeSlots(doctorId, date) {
                // Clear current time slots
                clearTimeSlots();
                
                // Show loading indicator
                timeSlots.innerHTML = '<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Loading available times...</div>';
                
                // Simulate API call (replace with actual AJAX call in production)
                setTimeout(function() {
                    // Clear loading indicator
                    timeSlots.innerHTML = '';
                    
                    // Sample time slots (would come from API based on doctor availability)
                    const availableTimes = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00'];
                    const bookedTimes = ['10:00', '15:00']; // Example of already booked times
                    
                    // Add time slots
                    availableTimes.forEach(function(time) {
                        const timeSlot = document.createElement('div');
                        timeSlot.className = 'time-slot';
                        timeSlot.textContent = time;
                        
                        // Check if time is already booked
                        if (bookedTimes.includes(time)) {
                            timeSlot.classList.add('disabled');
                            timeSlot.title = 'Already booked';
                        } else {
                            // Add click event to select time
                            timeSlot.addEventListener('click', function() {
                                // Remove selected class from all time slots
                                document.querySelectorAll('.time-slot').forEach(function(slot) {
                                    slot.classList.remove('selected');
                                });
                                
                                // Add selected class to clicked slot
                                this.classList.add('selected');
                                
                                // Set time select value
                                appointmentTimeSelect.value = time;
                                
                                // Add option to time select if it doesn't exist
                                if (!appointmentTimeSelect.querySelector(`option[value="${time}"]`)) {
                                    const option = document.createElement('option');
                                    option.value = time;
                                    option.textContent = time;
                                    appointmentTimeSelect.appendChild(option);
                                    appointmentTimeSelect.value = time;
                                }
                                
                                // Update summary
                                updateDateTimeSummary();
                            });
                        }
                        
                        timeSlots.appendChild(timeSlot);
                    });
                }, 1000);
            }
            
            // Clear time slots
            function clearTimeSlots() {
                timeSlots.innerHTML = '';
                clearSelect(appointmentTimeSelect, 'Select Time');
            }
            
            // Clear select options
            function clearSelect(selectElement, placeholderText) {
                selectElement.innerHTML = '';
                
                // Add placeholder
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = placeholderText;
                placeholder.disabled = true;
                placeholder.selected = true;
                selectElement.appendChild(placeholder);
            }
            
            // Update date and time summary
            function updateDateTimeSummary() {
                const date = appointmentDateInput.value;
                const time = appointmentTimeSelect.value;
                
                if (date && time) {
                    const formattedDate = new Date(date).toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    
                    summaryDateTime.textContent = `${formattedDate} at ${time}`;
                } else {
                    summaryDateTime.textContent = '-';
                }
            }
            
            // Form validation before submit
            document.getElementById('appointmentForm').addEventListener('submit', function(event) {
                if (!hospitalSelect.value || !departmentSelect.value || !doctorSelect.value || 
                    !appointmentDateInput.value || !appointmentTimeSelect.value) {
                    event.preventDefault();
                    alert('Please fill in all required fields');
                }
            });
        });
    </script>