# Hospital Management Platform

A centralized hospital management platform that supports multiple hospitals with three main user roles: Patients, Doctors, and Admins (including Super Admin).

## Features

### Patient Portal
- User Registration/Login with unique Patient ID
- View medical history (appointments, doctor feedback, prescriptions)
- Hospital and department selection
- Doctor selection and appointment booking
- Symptom description and document attachment
- Online payments (UPI, Card, Net Banking)
- Appointment confirmation
- Chatbot assistant

### Hospital & Doctor Dashboard
- Doctor login system
- Appointment management (view, mark as completed/missed)
- Patient details access
- Notes and prescription management
- Internal doctor chatbot assistant

### Admin Panel
#### Super Admin Dashboard
- Hospital management (add/edit/delete)
- Global analytics and statistics
- Performance graphs and metrics
- Patient review moderation

#### Hospital Admin Dashboard
- Doctor management
- Appointment monitoring
- Hospital-specific statistics
- Department and doctor assignment
- Feedback management

## Technology Stack
- Frontend: HTML, CSS, JavaScript (with animations and responsive design)
- Backend: PHP
- Database: MySQL
- Charts: Chart.js
- Notifications: Email/SMS integration

## Security Features
- Secure login systems
- Data encryption
- SQL injection prevention
- Hospital data isolation
- Patient consent management
- Booking conflict prevention

## Project Structure
```
/
├── assets/            # Static assets (images, fonts, etc.)
│   ├── css/           # CSS files
│   ├── js/            # JavaScript files
│   └── images/        # Image files
├── config/            # Configuration files
├── database/          # Database scripts and migrations
├── includes/          # PHP includes and functions
├── patient/           # Patient portal files
├── doctor/            # Doctor dashboard files
├── admin/             # Admin panel files
│   ├── super/         # Super admin specific files
│   └── hospital/      # Hospital admin specific files
└── index.php          # Entry point
```