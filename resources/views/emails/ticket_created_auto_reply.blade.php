<!DOCTYPE html>
<html>
<head>
    <title>Ticket Received</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6;">
    <h2>Hello, we have received your support request!</h2>
    
    <p>Your ticket was successfully created. The registered title is:</p>
    <blockquote style="background: #f4f4f4; padding: 10px; border-left: 5px solid #ccc;">
        {{ $ticket->title }}
    </blockquote>

    <p><strong>⚠️ IMPORTANT:</strong><br>
    Any question or update related to this request should be made by <strong>replying directly to this email</strong>. Do not change the Subject of the email to ensure the message remains associated with your original ticket.</p>

    <p>Our team will contact you shortly.</p>
    
    <p>Thank you,<br>
    Support Team</p>
</body>
</html>