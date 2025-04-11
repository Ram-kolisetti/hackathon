<?php
require_once('../includes/auth.php');
require_once('../config/database.php');

// Ensure user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

$patient_id = $_SESSION['user_id'];
$page_title = 'My Documents';
$success_message = '';
$error_message = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document'];
        $document_type = $_POST['document_type'];
        $description = trim($_POST['description']);
        
        // Validate file type
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $error_message = 'Invalid file type. Only PDF, JPEG, and PNG files are allowed.';
        } else {
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('doc_') . '.' . $extension;
            $upload_path = '../uploads/documents/' . $filename;
            
            // Create directory if it doesn't exist
            if (!file_exists('../uploads/documents/')) {
                mkdir('../uploads/documents/', 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Save document information to database
                $sql = "INSERT INTO patient_documents (patient_id, document_type, filename, description, uploaded_at) 
                        VALUES (?, ?, ?, ?, NOW())";
                        
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('isss', 
                    $patient_id,
                    $document_type,
                    $filename,
                    $description
                );
                
                if ($stmt->execute()) {
                    $success_message = 'Document uploaded successfully!';
                } else {
                    $error_message = 'Error saving document information. Please try again.';
                }
            } else {
                $error_message = 'Error uploading file. Please try again.';
            }
        }
    }
}

// Fetch patient documents
$sql = "SELECT * FROM patient_documents WHERE patient_id = ? ORDER BY uploaded_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$documents = $result->fetch_all(MYSQLI_ASSOC);

// Include header
include_once('../includes/header.php');
?>

<div class="container my-4">
    <h2 class="mb-4">My Documents</h2>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success">
        <?php echo $success_message; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Upload New Document</h5>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="document" class="form-label">Select Document</label>
                            <input type="file" class="form-control" id="document" name="document" required 
                                   accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        
                        <div class="mb-3">
                            <label for="document_type" class="form-label">Document Type</label>
                            <select class="form-select" id="document_type" name="document_type" required>
                                <option value="">Choose type...</option>
                                <option value="Test Report">Test Report</option>
                                <option value="Prescription">Prescription</option>
                                <option value="Insurance">Insurance Document</option>
                                <option value="X-Ray">X-Ray</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="2" placeholder="Brief description of the document..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Upload Document</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <?php if (empty($documents)): ?>
            <div class="alert alert-info">
                No documents uploaded yet.
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach ($documents as $document): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($document['document_type']); ?></h5>
                            <p class="card-text">
                                <?php echo htmlspecialchars($document['description'] ?: 'No description provided'); ?>
                            </p>
                            <div class="text-muted small mb-2">
                                Uploaded: <?php echo date('M d, Y', strtotime($document['uploaded_at'])); ?>
                            </div>
                            <a href="../uploads/documents/<?php echo htmlspecialchars($document['filename']); ?>" 
                               class="btn btn-sm btn-primary" target="_blank">
                                View Document
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once('../includes/footer.php'); ?>