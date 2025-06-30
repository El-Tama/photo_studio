<?php
require_once 'config.php';

class AuthSystem {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    // Registrar nuevo usuario
    public function register($email, $password, $nombre, $telefono = '') {
        try {
            // Verificar si el email ya existe
            $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'El email ya está registrado'];
            }
            
            // Hash de la contraseña
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertar nuevo usuario
            $stmt = $this->pdo->prepare("
                INSERT INTO usuarios (email, password, nombre, telefono, rol, fecha_registro) 
                VALUES (?, ?, ?, ?, 'cliente', NOW())
            ");
            
            $stmt->execute([$email, $hashedPassword, $nombre, $telefono]);
            
            return ['success' => true, 'message' => 'Usuario registrado exitosamente'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al registrar usuario: ' . $e->getMessage()];
        }
    }
    
    // Iniciar sesión
    public function login($email, $password) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, email, password, nombre, rol FROM usuarios WHERE email = ? AND activo = 1");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Credenciales incorrectas'];
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['nombre'];
                $_SESSION['user_role'] = $user['rol'];
                
                // Actualizar último acceso
                $updateStmt = $this->pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);

                return ['success' => true, 'message' => 'Inicio de sesión exitoso', 'role' => $user['rol']];
            } else {
                return ['success' => false, 'message' => 'Credenciales incorrectas'];
            }
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()];
        }
    }
    
    // Cerrar sesión
    public function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Sesión cerrada exitosamente'];
    }
    
    // Cambiar contraseña
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $stmt = $this->pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!password_verify($currentPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Contraseña actual incorrecta'];
            }
            
            $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $this->pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $updateStmt->execute([$hashedNewPassword, $userId]);
            
            return ['success' => true, 'message' => 'Contraseña actualizada exitosamente'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al cambiar contraseña: ' . $e->getMessage()];
        }
    }
    
    // Recuperar contraseña
    public function resetPassword($email) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, nombre FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Email no encontrado'];
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Guardar token de reset
            $tokenStmt = $this->pdo->prepare("
                INSERT INTO password_resets (user_id, token, expiry) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE token = VALUES(token), expiry = VALUES(expiry)
            ");
            $tokenStmt->execute([$user['id'], $token, $expiry]);
            
            // Aquí enviarías el email con el token
            // sendPasswordResetEmail($email, $user['nombre'], $token);
            
            return ['success' => true, 'message' => 'Se ha enviado un email con instrucciones para restablecer tu contraseña'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al procesar solicitud: ' . $e->getMessage()];
        }
    }
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthSystem();
    $action = $_POST['action'] ?? '';
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'login':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Email y contraseña son requeridos']);
                exit;
            }
            
            $result = $auth->login($email, $password);
            echo json_encode($result);
            break;
            
        case 'register':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $nombre = sanitize($_POST['nombre'] ?? '');
            $telefono = sanitize($_POST['telefono'] ?? '');
            
            if (empty($email) || empty($password) || empty($nombre)) {
                echo json_encode(['success' => false, 'message' => 'Email, contraseña y nombre son requeridos']);
                exit;
            }
            
            if (!isValidEmail($email)) {
                echo json_encode(['success' => false, 'message' => 'Email inválido']);
                exit;
            }
            
            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
                exit;
            }
            
            $result = $auth->register($email, $password, $nombre, $telefono);
            echo json_encode($result);
            break;
            
        case 'logout':
            $result = $auth->logout();
            echo json_encode($result);
            break;
            
        case 'change_password':
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            
            if (empty($currentPassword) || empty($newPassword)) {
                echo json_encode(['success' => false, 'message' => 'Ambas contraseñas son requeridas']);
                exit;
            }
            
            if (strlen($newPassword) < 6) {
                echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres']);
                exit;
            }
            
            $result = $auth->changePassword($_SESSION['user_id'], $currentPassword, $newPassword);
            echo json_encode($result);
            break;
            
        case 'reset_password':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $email = sanitize($_POST['email'] ?? '');
            
            if (empty($email) || !isValidEmail($email)) {
                echo json_encode(['success' => false, 'message' => 'Email válido requerido']);
                exit;
            }
            
            $result = $auth->resetPassword($email);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    exit;
}
?>
