<?php
// Include PHPMailer for reliable email sending
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Database connection
require 'db_connection.php';

ader('Contee: application/json
// Improved email function using PHPMailer
function sendEmailNotification($to, $toName, $subject, $message) {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'mail.aatcabuja.com.ng';
        $mail->SMTPAuth = true;
        $mail->Username = 'support@aatcabuja.com.ng';
        $mail->Password = 'Dw2bbgvhZmsp7QA';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        
        // Email settings
        $mail->setFrom('support@aatcabuja.com.ng', 'Abuja-AATC');
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Debug logging
    error_log("Notify Employee - Input received: " . $input);
    
    // Validate input
    if (!$data || !isset($data['visitor_id']) || !isset($data['employee_id'])) {
        throw new Exception('Missing required parameters: visitor_id and employee_id');
    }
    
    $visitor_id = intval($data['visitor_id']);
    $employee_id = intval($data['employee_id']);
    
    // Fetch visitor and employee details with better error handling
    $query = "SELECT 
                v.id as visitor_id,
                v.name AS visitor_name,
                v.organization,
                v.reason,
                v.phone,
                v.check_in_time,
                v.time_of_visit,
                v.floor_of_visit,
                e.id as employee_id,
                e.name AS employee_name,
                e.email AS employee_email
              FROM visitors v
              LEFT JOIN employees e ON v.employee_id = e.id
              WHERE v.id = ? AND (v.employee_id = ? OR v.host_name = e.name)
              AND v.status IN ('approved', 'checked_in')";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $visitor_id, $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Try alternative query without employee_id constraint
        $altQuery = "SELECT 
                        v.id as visitor_id,
                        v.name AS visitor_name,
                        v.organization,
                        v.reason,
                        v.phone,
                        v.check_in_time,
                        v.time_of_visit,
                        v.floor_of_visit,
                        v.host_name as employee_name,
                        e.email AS employee_email
                      FROM visitors v
                      LEFT JOIN employees e ON v.host_name = e.name
                      WHERE v.id = ? AND v.status IN ('approved', 'checked_in')";
        
        $altStmt = $conn->prepare($altQuery);
        $altStmt->bind_param("i", $visitor_id);
        $altStmt->execute();
        $altResult = $altStmt->get_result();
        
        if ($altResult->num_rows === 0) {
            throw new Exception('Visitor not found or not approved');
        }
        
        $details = $altResult->fetch_assoc();
    } else {
        $details = $result->fetch_assoc();
    }
    
    // Validate email address
    if (empty($details['employee_email']) || !filter_var($details['employee_email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid or missing employee email address');
    }
    
    // Format check-in time
    $checkin_time = $details['check_in_time'] ? 
        date('M j, Y g:i A', strtotime($details['check_in_time'])) : 
        date('M j, Y g:i A', strtotime($details['time_of_visit']));
    
    // Prepare email content with better formatting
    $subject = "Visitor Arrival Notification - {$details['visitor_name']}";
    $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #07AF8B; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
                .info-row { margin: 10px 0; }
                .label { font-weight: bold; color: #007570; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Visitor Arrival Notification</h2>
                </div>
                <div class='content'>
                    <p>Hello <strong>{$details['employee_name']}</strong>,</p>
                    
                    <p>Your visitor has arrived and checked in. Here are the details:</p>
                    
                    <div class='info-row'>
                        <span class='label'>Visitor Name:</span> {$details['visitor_name']}
                    </div>
                    <div class='info-row'>
                        <span class='label'>Organization:</span> {$details['organization']}
                    </div>
                    <div class='info-row'>
                        <span class='label'>Phone:</span> {$details['phone']}
                    </div>
                    <div class='info-row'>
                        <span class='label'>Purpose:</span> {$details['reason']}
                    </div>
                    <div class='info-row'>
                        <span class='label'>Check-in Time:</span> {$checkin_time}
                    </div>
                    <div class='info-row'>
                        <span class='label'>Floor:</span> {$details['floor_of_visit']}
                    </div>
                </div>
                <div class='footer'>
                    <p>Abuja-AATC<br>
                    This is an automated notification. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
    ";
    
    // Send notification
    $notificationSent = sendEmailNotification(
        $details['employee_email'],
        $details['employee_name'],
        $subject,
        $message
    );
    
    if ($notificationSent) {
        // Log the notification in database
        $logQuery = "UPDATE visitors 
                    SET notification_sent = 1,
                        notification_time = NOW()
                    WHERE id = ?";
        $logStmt = $conn->prepare($logQuery);
        if ($logStmt) {
            $logStmt->bind_param("i", $visitor_id);
            $logStmt->execute();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Host notified successfully',
            'employee' => $details['employee_name'],
            'email' => $details['employee_email']
        ]);
        
        error_log("Email sent successfully to: " . $details['employee_email']);
        
    } else {
        throw new Exception('Email could not be sent. Please check email configuration.');
    }

} catch (Exception $e) {
    error_log("Notify Employee Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>