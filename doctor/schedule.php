<?php
// Doctor Schedule Page
session_start();

// Define base path for includes
define('BASE_PATH', dirname(__DIR__));

// Include authentication functions
require_once(BASE_PATH . '/includes/auth.php');

// Check if user is logged in and is a doctor
if (!isLoggedIn() || !hasRole('doctor')) {
    header('Location: ../login.php');
    exit;
}

// Get doctor information
$user_id = $_SESSION['user_id'];
$doctor_id = $_SESSION['profile_id'];

// Get doctor details including schedule
$sql = "SELECT dp.*, h.name as hospital_name, d.name as department_name 
        FROM doctor_profiles dp 
        JOIN hospitals h ON dp.hospital_id = h.hospital_id 
        JOIN departments d ON dp.department_id = d.department_id 
        WHERE dp.doctor_id = ?";
$doctor = fetchRow($sql, "i", [$doctor_id]);

// Process form submission for updating schedule
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $available_days = isset($_POST['available_days']) ? $_POST['available_days'] : [];
    $available_time_start = sanitizeInput($_POST['available_time_start']);
    $available_time_end = sanitizeInput($_POST['available_time_end']);
    
    // Validate form data
    if (empty($available_days) || empty($available_time_start) || empty($available_time_end)) {
        $error = 'Please fill in all required fields';
    } elseif (strtotime($available_time_start) >= strtotime($available_time_end)) {
        $error = 'End time must be after start time';
    } else {
        // Format available days as comma-separated string
        $available_days_str = implode(',', $available_days);
        
        // Update doctor schedule
        $sql = "UPDATE doctor_profiles 
                SET available_days = ?, available_time_start = ?, available_time_end = ? 
                WHERE doctor_id = ?";
        $result = executeNonQuery($sql, "sssi", [$available_days_str, $available_time_start, $available_time_end, $doctor_id]);
        
        if ($result) {
            $success = 'Schedule updated successfully';
            
            // Refresh doctor data
            $sql = "SELECT dp.*, h.name as hospital_name, d.name as department_name 
                    FROM doctor_profiles dp 
                    JOIN hospitals h ON dp.hospital_id = h.hospital_id 
                    JOIN departments d ON dp.department_id = d.department_id 
                    WHERE dp.doctor_id = ?";
            $doctor = fetchRow($sql, "i", [$doctor_id]);
        } else {
            $error = 'Failed to update schedule';
        }
    }
}

// Get upcoming appointments for the next 7 days
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));

$sql = "SELECT a.*, 
        CONCAT(u.first_name, ' ', u.last_name) as patient_name,
        pp.patient_unique_id
        FROM appointments a
        JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        JOIN users u ON pp.user_id = u.user_id
        WHERE a.doctor_id = ? AND a.appointment_date BETWEEN ? AND ? AND a.status = 'scheduled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$upcoming_appointments = fetchRows($sql, "iss", [$doctor_id, $today, $next_week]);

// Parse available days into array
$available_days_array = !empty($doctor['available_days']) ? explode(',', $doctor['available_days']) : [];

// Define days of the week for form
$days_of_week = [
    'Mon' => 'Monday',
    'Tue' => 'Tuesday',
    'Wed' => 'Wednesday',
    'Thu' => 'Thursday',
    'Fri' => 'Friday',
    'Sat' => 'Saturday',
    'Sun' => 'Sunday'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Doctor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .schedule-card {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--gray-300);
        }
        
        .schedule-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .schedule-info {
            margin-bottom: var(--spacing-md);
        }
        
        .schedule-row {
            display: flex;
            margin-bottom: var(--spacing-sm);
        }
        
        .schedule-label {
            width: 150px;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .schedule-value {
            flex: 1;
        }
        
        .days-checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }
        
        .day-checkbox {
            display: flex;
            align-items: center;
        }
        
        .day-checkbox input {
            margin-right: var(--spacing-xs);
        }
        
        .time-inputs {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }
        
        .time-input {
            flex: 1;
        }
        
        .appointment-list {
            margin-top: var(--spacing-md);
        }
        
        .appointment-item {
            display: flex;
            padding: var(--spacing-sm);
            border-bottom: 1px solid var(--gray-200);
            align-items: center;
        }
        
        .appointment-date {
            width: 120px;
            font-weight: 500;
        }
        
        .appointment-time {
            width: 100px;
        }
        
        .appointment-patient {
            flex: 1;
        }
        
        .appointment-actions {
            width: 100px;
            text-align: right;
        }
        
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background-color: var(--gray-300);
            border: 1px solid var(--gray-300);
            margin-top: var(--spacing-md);
        }
        
        .calendar-header {
            background-color: var(--primary-color);
            color: white;
            text-align: center;
            padding: var(--spacing-sm);
            font-weight: 500;
        }
        
        .calendar-day {
            background-color: white;
            min-height: 100px;
            padding: var(--spacing-sm);
        }
        
        .calendar-day.inactive {
            background-color: var(--gray-100);
            color: var(--gray-500);
        }
        
        .calendar-day.today {
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .day-number {
            font-weight: 500;
            margin-bottom: var(--spacing-sm);
        }
        
        .day-appointments {
            font-size: var(--font-size-sm);
        }
        
        .day-appointment {
            background-color: var(--primary-color);
            color: white;
            padding: 2px var(--spacing-xs);
            border-radius: var(--border-radius-sm);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
                <a href="patients.php" class="sidebar-link">
                    <i class="fas fa-user-injured"></i> Patients
                </a>
            </li>
            <li class="sidebar-item">
                <a href="medical_records.php" class="sidebar-link">
                    <i class="fas fa-file-medical"></i> Medical Records
                </a>
            </li>
            <li class="sidebar-item">
                <a href="schedule.php" class="sidebar-link active">
                    <i class="fas fa-clock"></i> My Schedule
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
                        <i class="fas fa-user-circle"></i> Dr. <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                    </a>
                </div>
            </div>
        </nav>
        
        <div class="container">
            <div class="page-header">
                <h1>My Schedule</h1>
                <p>Manage your availability and view upcoming appointments</p>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="schedule-card">
                        <div class="schedule-header">
                            <div class="schedule-title">Current Schedule</div>
                        </div>
                        <div class="schedule-info">
                            <div class="schedule-row">
                                <div class="schedule-label">Hospital:</div>
                                <div class="schedule-value"><?php echo $doctor['hospital_name']; ?></div>
                            </div>
                            <div class="schedule-row">
                                <div class="schedule-label">Department:</div>
                                <div class="schedule-value"><?php echo $doctor['department_name']; ?></div>
                            </div>
                            <div class="schedule-row">
                                <div class="schedule-label">Available Days:</div>
                                <div class="schedule-value">
                                    <?php 
                                    if (!empty($doctor['available_days'])) {
                                        $days = explode(',', $doctor['available_days']);
                                        $full_days = array_map(function($day) use ($days_of_week) {
                                            return $days_of_week[$day] ?? $day;
                                        }, $days);
                                        echo implode(', ', $full_days);
                                    } else {
                                        echo 'Not set';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="schedule-row">
                                <div class="schedule-label">Working Hours:</div>
                                <div class="schedule-value">
                                    <?php 
                                    if (!empty($doctor['available_time_start']) && !empty($doctor['available_time_end'])) {
                                        echo date('h:i A', strtotime($doctor['available_time_start'])) . ' - ' . 
                                             date('h:i A', strtotime($doctor['available_time_end']));
                                    } else {
                                        echo 'Not set';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="schedule-card">
                        <div class="schedule-header">
                            <div class="schedule-title">Update Schedule</div>
                        </div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label">Available Days</label>
                                <div class="days-checkboxes">
                                    <?php foreach ($days_of_week as $code => $day): ?>
                                        <div class="day-checkbox">
                                            <input type="checkbox" id="day_<?php echo $code; ?>" name="available_days[]" value="<?php echo $code; ?>" <?php echo in_array($code, $available_days_array) ? 'checked' : ''; ?>>
                                            <label for="day_<?php echo $code; ?>"><?php echo $day; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Working Hours</label>
                                <div class="time-inputs">
                                    <div class="time-input">
                                        <label for="available_time_start" class="form-label">Start Time</label>
                                        <input type="time" id="available_time_start" name="available_time_start" class="form-control" value="<?php echo $doctor['available_time_start']; ?>" required>
                                    </div>
                                    <div class="time-input">
                                        <label for="available_time_end" class="form-label">End Time</label>
                                        <input type="time" id="available_time_end" name="available_time_end" class="form-control" value="<?php echo $doctor['available_time_end']; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Schedule
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="schedule-card">
                        <div class="schedule-header">
                            <div class="schedule-title">Upcoming Appointments</div>
                        </div>
                        
                        <?php if (count($upcoming_appointments) > 0): ?>
                            <div class="appointment-list">
                                <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <div class="appointment-item">
                                        <div class="appointment-date">
                                            <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                        </div>
                                        <div class="appointment-time">
                                            <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                        </div>
                                        <div class="appointment-patient">
                                            <?php echo $appointment['patient_name']; ?>
                                            <small>(<?php echo $appointment['patient_unique_id']; ?>)</small>
                                        </div>
                                        <div class="appointment-actions">
                                            <a href="appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No upcoming appointments for the next 7 days.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="schedule-card">
                        <div class="schedule-header">
                            <div class="schedule-title">Weekly Calendar</div>
                        </div>
                        
                        <div class="calendar">
                            <?php 
                            // Calendar headers (days of week)
                            foreach (array_values($days_of_week) as $day) {
                                echo "<div class='calendar-header'>" . substr($day, 0, 3) . "</div>";
                            }
                            
                            // Get the start of the current week (Monday)
                            $current_date = new DateTime();
                            $current_date->modify('this week');
                            
                            // Store today's date for highlighting
                            $today = date('Y-m-d');
                            
                            // Create array of appointments indexed by date
                            $appointments_by_date = [];
                            foreach ($upcoming_appointments as $appointment) {
                                $date = $appointment['appointment_date'];
                                if (!isset($appointments_by_date[$date])) {
                                    $appointments_by_date[$date] = [];
                                }
                                $appointments_by_date[$date][] = $appointment;
                            }
                            
                            // Generate calendar days
                            for ($i = 0; $i < 7; $i++) {
                                $date_str = $current_date->format('Y-m-d');
                                $day_class = 'calendar-day';
                                
                                // Check if this day is today
                                if ($date_str == $today) {
                                    $day_class .= ' today';
                                }
                                
                                // Check if this day is in doctor's available days
                                $day_code = $current_date->format('D');
                                if (!in_array($day_code, $available_days_array)) {
                                    $day_class .= ' inactive';
                                }
                                
                                echo "<div class='$day_class'>";
                                echo "<div class='day-number'>" . $current_date->format('j') . "</div>";
                                
                                // Show appointments for this day
                                if (isset($appointments_by_date[$date_str])) {
                                    echo "<div class='day-appointments'>";
                                    foreach ($appointments_by_date[$date_str] as $appointment) {
                                        echo "<div class='day-appointment' title='" . $appointment['patient_name'] . "'>";
                                        echo date('h:i A', strtotime($appointment['appointment_time']));
                                        echo "</div>";
                                    }
                                    echo "</div>";
                                }
                                
                                echo "</div>";
                                
                                // Move to next day
                                $current_date->modify('+1 day');
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>