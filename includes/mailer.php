<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}

// Verificar si PHPMailer está disponible
$autoloadPath = BASE_PATH . '/vendor/autoload.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    define('PHPMAILER_AVAILABLE', true);
} else {
    define('PHPMAILER_AVAILABLE', false);
}

/**
 * Clase para manejo de emails con PHPMailer
 */
class Mailer {
    private $mail;
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        
        if (PHPMAILER_AVAILABLE) {
            $this->mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $this->setupSMTP();
        } else {
            $this->mail = null;
        }
    }
    
    /**
     * Configura SMTP según la configuración en BD
     */
    private function setupSMTP() {
        if (!PHPMAILER_AVAILABLE || !$this->mail) {
            return;
        }
        
        try {
            // Obtener configuración SMTP de la BD
            $smtpEnabled = getSetting('enable_smtp', '0') === '1';
            
            if ($smtpEnabled) {
                // Configuración SMTP desde BD
                $this->mail->isSMTP();
                $this->mail->Host = getSetting('smtp_host', 'smtp.gmail.com');
                $this->mail->SMTPAuth = true;
                $this->mail->Username = getSetting('smtp_username', '');
                $this->mail->Password = getSetting('smtp_password', '');
                $this->mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $this->mail->Port = (int)getSetting('smtp_port', 587);
                
                // Configuración adicional
                $this->mail->CharSet = 'UTF-8';
                $this->mail->Encoding = 'base64';
                
                // Remitente
                $senderEmail = getSetting('smtp_username', '');
                $senderName = getSetting('site_name', 'Portal de Noticias');
                
                if ($senderEmail) {
                    $this->mail->setFrom($senderEmail, $senderName);
                }
            }
            
        } catch (Exception $e) {
            error_log('Error configurando SMTP: ' . $e->getMessage());
        }
    }
    
    /**
     * Envía un correo electrónico
     */
    public function send($to, $subject, $body, $altBody = '', $attachments = []) {
        if (!PHPMAILER_AVAILABLE || !$this->mail) {
            return $this->sendWithMailFunction($to, $subject, $body);
        }
        
        try {
            // Limpiar destinatarios previos
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            // Configurar destinatario
            $this->mail->addAddress($to);
            
            // Configurar contenido
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            
            if (!empty($altBody)) {
                $this->mail->AltBody = $altBody;
            }
            
            // Enviar
            $result = $this->mail->send();
            
            if ($result) {
                $this->logEmail($to, $subject, 'sent');
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logEmail($to, $subject, 'failed', $e->getMessage());
            error_log('Error enviando email: ' . $e->getMessage());
            
            // Fallback a mail() básico
            return $this->sendWithMailFunction($to, $subject, $body);
        }
    }
    
    /**
     * Envío con función mail() básica como fallback
     */
    private function sendWithMailFunction($to, $subject, $body) {
        $fromEmail = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@' . $_SERVER['HTTP_HOST'];
        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Portal de Noticias';
        
        $headers = [
            'From' => $fromName . ' <' . $fromEmail . '>',
            'Reply-To' => $fromEmail,
            'Content-Type' => 'text/html; charset=UTF-8',
            'MIME-Version' => '1.0',
            'X-Mailer' => 'PHP/' . phpversion()
        ];
        
        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= "$key: $value\r\n";
        }
        
        $result = mail($to, $subject, $body, trim($headerString));
        
        if ($result) {
            $this->logEmail($to, $subject, 'sent');
        } else {
            $this->logEmail($to, $subject, 'failed', 'mail() function failed');
        }
        
        return $result;
    }
    
    /**
     * Registra el envío de email en log
     */
    private function logEmail($to, $subject, $status, $error = '') {
        try {
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'email_logs'");
            $tableExists = $tableCheck->rowCount() > 0;
            
            if ($tableExists) {
                $this->db->query(
                    "INSERT INTO email_logs (recipient, subject, status, error_message, sent_at) 
                     VALUES (?, ?, ?, ?, NOW())",
                    [$to, $subject, $status, $error]
                );
            } else {
                $logMessage = date('Y-m-d H:i:s') . " - Email: $to - $subject - $status";
                if ($error) $logMessage .= " - Error: $error";
                error_log($logMessage);
            }
        } catch (Exception $e) {
            error_log('Error logging email: ' . $e->getMessage());
        }
    }
    
    /**
     * Verifica la configuración SMTP
     */
    public function testConnection() {
        $result = [
            'success' => false,
            'message' => '',
            'details' => []
        ];
        
        if (!PHPMAILER_AVAILABLE) {
            $result['message'] = 'PHPMailer no está instalado.';
            $result['success'] = false;
            return $result;
        }
        
        try {
            $smtpHost = getSetting('smtp_host', '');
            $smtpUsername = getSetting('smtp_username', '');
            
            if (empty($smtpHost) || empty($smtpUsername)) {
                $result['message'] = 'Configuración SMTP incompleta';
                return $result;
            }
            
            $testMail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $testMail->isSMTP();
            $testMail->Host = $smtpHost;
            $testMail->SMTPAuth = true;
            $testMail->Username = $smtpUsername;
            $testMail->Password = getSetting('smtp_password', '');
            $testMail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $testMail->Port = (int)getSetting('smtp_port', 587);
            
            if ($testMail->smtpConnect()) {
                $result['success'] = true;
                $result['message'] = 'Conexión SMTP exitosa';
                $testMail->smtpClose();
            } else {
                $result['message'] = 'No se pudo conectar al servidor SMTP';
            }
            
        } catch (Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }
}

// Funciones helper
function sendEmailSMTP($to, $subject, $body, $altBody = '', $attachments = []) {
    $mailer = new Mailer();
    return $mailer->send($to, $subject, $body, $altBody, $attachments);
}

function testSMTPConnection() {
    $mailer = new Mailer();
    return $mailer->testConnection();
}
?>