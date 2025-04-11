<?php
// Patient Dashboard
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

// Get upcoming appointments
$sql = "SELECT a.*, h.name as hospital_name, d.name as department_name, 
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name
        FROM appointments a
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        JOIN doctor_profiles dp ON a.doctor_id = dp.doctor_id
        JOIN users u ON dp.user_id = u.user_id
        WHERE a.patient_id = ? AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5";

$upcoming_appointments = fetchRows($sql, "i", [$patient_id]);

// Get past appointments
$sql = "SELECT a.*, h.name as hospital_name, d.name as department_name, 
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        mr.diagnosis, mr.prescription
        FROM appointments a
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        JOIN doctor_profiles dp ON a.doctor_id = dp.doctor_id
        JOIN users u ON dp.user_id = u.user_id
        LEFT JOIN medical_records mr ON a.appointment_id = mr.appointment_id
        WHERE a.patient_id = ? AND a.appointment_date < CURDATE()
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10";

$past_appointments = fetchRows($sql, "i", [$patient_id]);

// Get notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$notifications = fetchRows($sql, "i", [$user_id]);

// Mark notifications as read
$sql = "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE";
executeNonQuery($sql, "i", [$user_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - Hospital Management Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Additional dashboard-specific styles */
        .welcome-banner {
            background-image: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            border-radius: var(--border-radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-md);
        }
        
        .welcome-banner h1 {
            color: white;
            margin-bottom: var(--spacing-sm);
        }
        
        .patient-id-badge {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 0.3rem 0.8rem;
            border-radius: var(--border-radius-full);
            font-size: var(--font-size-sm);
            font-weight: 600;
            display: inline-block;
            margin-bottom: var(--spacing-md);
        }
        
        .quick-actions {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
        }
        
        .quick-action-btn {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-md);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all var(--transition-normal);
            text-decoration: none;
        }
        
        .quick-action-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }
        
        .appointment-list {
            margin-top: var(--spacing-md);
        }
        
        .appointment-item {
            border-left: 4px solid var(--secondary-color);
            padding: var(--spacing-md);
            background-color: white;
            border-radius: var(--border-radius-md);
            margin-bottom: var(--spacing-md);
            box-shadow: var(--shadow-sm);
            transition: transform var(--transition-normal), box-shadow var(--transition-normal);
        }
        
        .appointment-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .appointment-item.completed {
            border-left-color: var(--success-color);
        }
        
        .appointment-item.cancelled {
            border-left-color: var(--error-color);
        }
        
        .appointment-item.missed {
            border-left-color: var(--warning-color);
        }
        
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-sm);
        }
        
        .appointment-title {
            font-weight: 600;
            font-size: var(--font-size-lg);
        }
        
        .appointment-date {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }
        
        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
        }
        
        .appointment-detail {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
        }
        
        .detail-value {
            font-weight: 500;
        }
        
        .appointment-actions {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-sm);
        }
        
        .notification-item {
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--gray-300);
            transition: background-color var(--transition-normal);
        }
        
        .notification-item:hover {
            background-color: var(--gray-100);
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .notification-time {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
        }
        
        .notification-unread {
            position: relative;
        }
        
        .notification-unread::before {
            content: '';
            position: absolute;
            top: 50%;
            right: 1rem;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--secondary-color);
        }
        
        .medical-record-item {
            margin-top: var(--spacing-sm);
            padding: var(--spacing-sm);
            background-color: var(--gray-100);
            border-radius: var(--border-radius-sm);
        }
        
        .medical-record-label {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .quick-actions {
                flex-direction: column;
            }
            
            .appointment-details {
                grid-template-columns: 1fr;
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
                <a href="dashboard.php" class="sidebar-link active">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-item">
                <a href="appointments.php" class="sidebar-link">
                    <i class="fas fa-calendar-check"></i> Appointments
                </a>
            </li>
            <li class="sidebar-item">
                <a href="book_appointment.php" class="sidebar-link">
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
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link">
                        <i class="fas fa-bell"></i>
                        <?php if (count($notifications) > 0): ?>
                            <span class="badge badge-danger"><?php echo count($notifications); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user-circle"></i> <?php echo $_SESSION['first_name']; ?>
                    </a>
                </div>
            </div>
        </nav>
        
        <div class="container">
            <div class="welcome-banner">
                <span class="patient-id-badge">Patient ID: <?php echo $_SESSION['unique_id']; ?></span>
                <h1>Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>!</h1>
                <p>Manage your appointments and medical records all in one place.</p>
                
                <div class="quick-actions">
                    <a href="book_appointment.php" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i> Book Appointment
                    </a>
                    <a href="medical_history.php" class="quick-action-btn">
                        <i class="fas fa-file-medical"></i> View Medical History
                    </a>
                    <a href="hospitals.php" class="quick-action-btn">
                        <i class="fas fa-hospital"></i> Find Hospital
                    </a>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Upcoming Appointments</h3>
                        </div>
                        <div class="card-body">
                            <?php if (count($upcoming_appointments) > 0): ?>
                                <div class="appointment-list">
                                    <?php foreach ($upcoming_appointments as $appointment): ?>
                                        <div class="appointment-item <?php echo $appointment['status']; ?>">
                                            <div class="appointment-header">
                                                <div class="appointment-title">
                                                    Appointment with <?php echo $appointment['doctor_name']; ?>
                                                </div>
                                                <div class="appointment-date">
                                                    <i class="far fa-calendar-alt"></i> 
                                                    <?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?> at 
                                                    <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                                </div>
                                            </div>
                                            
                                            <div class="appointment-details">
                                                <div class="appointment-detail">
                                                    <span class="detail-label">Hospital</span>
                                                    <span class="detail-value"><?php echo $appointment['hospital_name']; ?></span>
                                                </div>
                                                <div class="appointment-detail">
                                                    <span class="detail-label">Department</span>
                                                    <span class="detail-value"><?php echo $appointment['department_name']; ?></span>
                                                </div>
                                                <div class="appointment-detail">
                                                    <span class="detail-label">Status</span>
                                                    <span class="detail-value">
                                                        <?php if ($appointment['status'] == 'scheduled'): ?>
                                                            <span class="badge badge-primary">Scheduled</span>
                                                        <?php elseif ($appointment['status'] == 'completed'): ?>
                                                            <span class="badge badge-success">Completed</span>
                                                        <?php elseif ($appointment['status'] == 'cancelled'): ?>
                                                            <span class="badge badge-danger">Cancelled</span>
                                                        <?php elseif ($appointment['status'] == 'missed'): ?>
                                                            <span class="badge badge-warning">Missed</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="appointment-detail">
                                                    <span class="detail-label">Payment</span>
                                                    <span class="detail-value">
                                                        <?php if ($appointment['payment_status'] == 'pending'): ?>
                                                            <span class="badge badge-warning">Pending</span>
                                                        <?php elseif ($appointment['payment_status'] == 'completed'): ?>
                                                            <span class="badge badge-success">Paid</span>
                                                        <?php elseif ($appointment['payment_status'] == 'refunded'): ?>
                                                            <span class="badge badge-info">Refunded</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="appointment-actions">
                                                <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View Details
                                                </a>
                                                <?php if ($appointment['status'] == 'scheduled'): ?>
                                                    <a href="reschedule_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-calendar-alt"></i> Reschedule
                                                    </a>
                                                    <a href="cancel_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-times-circle"></i> Cancel
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($appointment['payment_status'] == 'pending'): ?>
                                                    <a href="payment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-credit-card"></i> Pay Now
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <a href="appointments.php" class="btn btn-outline-primary">
                                        View All Appointments
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                    <p>You don't have any upcoming appointments.</p>
                                    <a href="book_appointment.php" class="btn btn-primary mt-2">
                                        <i class="fas fa-plus-circle"></i> Book an Appointment
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Recent Medical History</h3>
                        </div>
                        <div class="card-body">
                            <?php if (count($past_appointments) > 0): ?>
                                <div class="appointment-list">
                                    <?php foreach ($past_appointments as $appointment): ?>
                                        <div class="appointment-item <?php echo $appointment['status']; ?>">
                                            <div class="appointment-header">
                                                <div class="appointment-title">
                                                    Appointment with <?php echo $appointment['doctor_name']; ?>
                                                </div>
                                                <div class="appointment-date">
                                                    <i class="far fa-calendar-alt"></i> 
                                                    <?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?> at 
                                                    <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                                </div>
                                            </div>
                                            
                                            <div class="appointment-details">
                                                <div class="appointment-detail">
                                                    <span class="detail-label">Hospital</span>
                                                    <span class="detail-value"><?php echo $appointment['hospital_name']; ?></span>
                                                </div>
                                                <div class="appointment-detail">
                                                    <span class="detail-label">Department</span>
                                                    <span class="detail-value"><?php echo $appointment['department_name']; ?></span>
                                                </div>
                                                <div class="appointment-detail">
                                                    <span class="detail-label">Status</span>
                                                    <span class="detail-value">
                                                        <?php if ($appointment['status'] == 'scheduled'): ?>
                                                            <span class="badge badge-primary">Scheduled</span>
                                                        <?php elseif ($appointment['status'] == 'completed'): ?>
                                                            <span class="badge badge-success">Completed</span>
                                                        <?php elseif ($appointment['status'] == 'cancelled'): ?>
                                                            <span class="badge badge-danger">Cancelled</span>
                                                        <?php elseif ($appointment['status'] == 'missed'): ?>
                                                            <span class="badge badge-warning">Missed</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($appointment['status'] == 'completed' && !empty($appointment['diagnosis'])): ?>
                                                <div class="medical-record-item">
                                                    <div class="medical-record-label">Diagnosis:</div>
                                                    <div><?php echo $appointment['diagnosis']; ?></div>
                                                </div>
                                                
                                                <?php if (!empty($appointment['prescription'])): ?>
                                                    <div class="medical-record-item">
                                                        <div class="medical-record-label">Prescription:</div>
                                                        <div><?php echo $appointment['prescription']; ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <div class="appointment-actions">
                                                <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View Details
                                                </a>
                                                <?php if ($appointment['status'] == 'completed'): ?>
                                                    <a href="add_review.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-star"></i> Add Review
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <a href="medical_history.php" class="btn btn-outline-primary">
                                        View Complete Medical History
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                                    <p>You don't have any past appointments or medical records yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Notifications</h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($notifications) > 0): ?>
                                <div class="notification-list">
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="notification-item <?php echo $notification['is_read'] ? '' : 'notification-unread'; ?>">
                                            <div class="notification-title">
                                                <?php echo $notification['title']; ?>
                                            </div>
                                            <div class="notification-message">
                                                <?php echo $notification['message']; ?>
                                            </div>
                                            <div class="notification-time">
                                                <i class="far fa-clock"></i> 
                                                <?php echo date('d M Y, h:i A', strtotime($notification['created_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                    <p>You don't have any notifications.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Quick Links</h3>
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                <li class="list-group-item">
                                    <a href="book_appointment.php" class="d-flex align-items-center">
                                        <i class="fas fa-calendar-plus text-primary mr-3"></i>
                                        Book New Appointment
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="profile.php" class="d-flex align-items-center">
                                        <i class="fas fa-user-edit text-info mr-3"></i>
                                        Update Profile
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="documents.php" class="d-flex align-items-center">
                                        <i class="fas fa-file-upload text-success mr-3"></i>
                                        Upload Documents
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="payments.php" class="d-flex align-items-center">
                                        <i class="fas fa-credit-card text-warning mr-3"></i>
                                        Payment History
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="help.php" class="d-flex align-items-center">
                                        <i class="fas fa-question-circle text-danger mr-3"></i>
                                        Help & Support
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
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
                    Hello! I'm your medical assistant. How can I help you today?
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
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>