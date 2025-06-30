<?php
require_once 'config.php';

class ContactoSystem {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    // Enviar mensaje de contacto
    public function enviarContacto($datos) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO contactos (nombre, email, telefono, asunto, mensaje, fecha_envio, estado) 
                VALUES (?, ?, ?, ?, ?, NOW(), 'nuevo')
            ");
            
            $stmt->execute([
                $datos['nombre'],
                $datos['email'],
                $datos['telefono'],
                $datos['asunto'],
                $datos['mensaje']
            ]);
            
            $contactoId = $this->pdo->lastInsertId();
            
            // Enviar email de notificación al admin (opcional)
            // $this->enviarNotificacionAdmin($datos);
            
            // Enviar email de confirmación al cliente (opcional)
            // $this->enviarConfirmacionCliente($datos);
            
            return [
                'success' => true, 
                'message' => 'Mensaje enviado exitosamente. Te contactaremos pronto.',
                'contacto_id' => $contactoId
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al enviar mensaje: ' . $e->getMessage()];
        }
    }
    
    // Solicitar cotización
    public function solicitarCotizacion($datos) {
        try {
            // Primero insertar el contacto
            $stmt = $this->pdo->prepare("
                INSERT INTO contactos (nombre, email, telefono, asunto, mensaje, fecha_envio, estado) 
                VALUES (?, ?, ?, ?, ?, NOW(), 'nuevo')
            ");
            
            $asunto = "Solicitud de Cotización - " . $datos['tipo_evento'];
            $mensaje = "Tipo de evento: {$datos['tipo_evento']}\n";
            $mensaje .= "Fecha del evento: {$datos['fecha_evento']}\n";
            $mensaje .= "Número de personas: {$datos['numero_personas']}\n";
            $mensaje .= "Ubicación: {$datos['ubicacion']}\n";
            $mensaje .= "Duración estimada: {$datos['duracion']} horas\n";
            $mensaje .= "Paquetes de interés: {$datos['paquetes_interes']}\n";
            $mensaje .= "Presupuesto aproximado: {$datos['presupuesto']}\n";
            if (!empty($datos['comentarios'])) {
                $mensaje .= "Comentarios adicionales: {$datos['comentarios']}\n";
            }
            
            $stmt->execute([
                $datos['nombre'],
                $datos['email'],
                $datos['telefono'],
                $asunto,
                $mensaje
            ]);
            
            $contactoId = $this->pdo->lastInsertId();
            
            // Insertar detalles específicos de la cotización
            $cotizacionStmt = $this->pdo->prepare("
                INSERT INTO cotizaciones (
                    contacto_id, tipo_evento, fecha_evento, numero_personas, 
                    ubicacion, duracion, paquetes_interes, presupuesto, comentarios
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $cotizacionStmt->execute([
                $contactoId,
                $datos['tipo_evento'],
                $datos['fecha_evento'],
                $datos['numero_personas'],
                $datos['ubicacion'],
                $datos['duracion'],
                $datos['paquetes_interes'],
                $datos['presupuesto'],
                $datos['comentarios'] ?? ''
            ]);
            
            return [
                'success' => true, 
                'message' => 'Solicitud de cotización enviada exitosamente. Te enviaremos una propuesta detallada pronto.',
                'contacto_id' => $contactoId
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al enviar cotización: ' . $e->getMessage()];
        }
    }
    
    // Obtener contactos (para admin)
    public function getContactos($filtros = []) {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filtros['estado'])) {
                $where[] = "estado = ?";
                $params[] = $filtros['estado'];
            }
            
            if (!empty($filtros['fecha_desde'])) {
                $where[] = "DATE(fecha_envio) >= ?";
                $params[] = $filtros['fecha_desde'];
            }
            
            if (!empty($filtros['fecha_hasta'])) {
                $where[] = "DATE(fecha_envio) <= ?";
                $params[] = $filtros['fecha_hasta'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM contactos 
                {$whereClause}
                ORDER BY fecha_envio DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Actualizar estado de contacto
    public function updateEstado($contactoId, $estado) {
        try {
            $stmt = $this->pdo->prepare("UPDATE contactos SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $contactoId]);
            return ['success' => true, 'message' => 'Estado actualizado exitosamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al actualizar estado: ' . $e->getMessage()];
        }
    }
    
    // Obtener detalles de cotización
    public function getCotizacion($contactoId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.*, cot.* 
                FROM contactos c
                LEFT JOIN cotizaciones cot ON c.id = cot.contacto_id
                WHERE c.id = ?
            ");
            $stmt->execute([$contactoId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }
    
    // Enviar respuesta a cotización
    public function enviarRespuestaCotizacion($contactoId, $respuesta, $precioPropuesto = null) {
        try {
            // Actualizar estado del contacto
            $stmt = $this->pdo->prepare("UPDATE contactos SET estado = 'respondido' WHERE id = ?");
            $stmt->execute([$contactoId]);
            
            // Insertar respuesta
            $respuestaStmt = $this->pdo->prepare("
                INSERT INTO respuestas_cotizacion (contacto_id, respuesta, precio_propuesto, fecha_respuesta)
                VALUES (?, ?, ?, NOW())
            ");
            $respuestaStmt->execute([$contactoId, $respuesta, $precioPropuesto]);
            
            // Aquí se enviaría el email al cliente con la respuesta
            // $this->enviarEmailRespuesta($contactoId, $respuesta, $precioPropuesto);
            
            return ['success' => true, 'message' => 'Respuesta enviada exitosamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al enviar respuesta: ' . $e->getMessage()];
        }
    }
    
    // Función placeholder para envío de emails
    private function enviarNotificacionAdmin($datos) {
        // Implementar envío de email al admin
        // Usar PHPMailer o función mail() de PHP
    }
    
    private function enviarConfirmacionCliente($datos) {
        // Implementar envío de email de confirmación al cliente
    }
    
    private function enviarEmailRespuesta($contactoId, $respuesta, $precio) {
        // Implementar envío de email con la respuesta de cotización
    }
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contacto = new ContactoSystem();
    $action = $_POST['action'] ?? '';
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'enviar_contacto':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $datos = [
                'nombre' => sanitize($_POST['nombre'] ?? ''),
                'email' => sanitize($_POST['email'] ?? ''),
                'telefono' => sanitize($_POST['telefono'] ?? ''),
                'asunto' => sanitize($_POST['asunto'] ?? ''),
                'mensaje' => sanitize($_POST['mensaje'] ?? '')
            ];
            
            // Validaciones básicas
            if (empty($datos['nombre']) || empty($datos['email']) || empty($datos['mensaje'])) {
                echo json_encode(['success' => false, 'message' => 'Nombre, email y mensaje son requeridos']);
                exit;
            }
            
            if (!isValidEmail($datos['email'])) {
                echo json_encode(['success' => false, 'message' => 'Email inválido']);
                exit;
            }
            
            $result = $contacto->enviarContacto($datos);
            echo json_encode($result);
            break;
            
        case 'solicitar_cotizacion':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $datos = [
                'nombre' => sanitize($_POST['nombre'] ?? ''),
                'email' => sanitize($_POST['email'] ?? ''),
                'telefono' => sanitize($_POST['telefono'] ?? ''),
                'tipo_evento' => sanitize($_POST['tipo_evento'] ?? ''),
                'fecha_evento' => sanitize($_POST['fecha_evento'] ?? ''),
                'numero_personas' => sanitize($_POST['numero_personas'] ?? ''),
                'ubicacion' => sanitize($_POST['ubicacion'] ?? ''),
                'duracion' => sanitize($_POST['duracion'] ?? ''),
                'paquetes_interes' => sanitize($_POST['paquetes_interes'] ?? ''),
                'presupuesto' => sanitize($_POST['presupuesto'] ?? ''),
                'comentarios' => sanitize($_POST['comentarios'] ?? '')
            ];
            
            // Validaciones básicas
            if (empty($datos['nombre']) || empty($datos['email']) || empty($datos['tipo_evento']) || 
                empty($datos['fecha_evento']) || empty($datos['numero_personas'])) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios deben ser completados']);
                exit;
            }
            
            if (!isValidEmail($datos['email'])) {
                echo json_encode(['success' => false, 'message' => 'Email inválido']);
                exit;
            }
            
            $result = $contacto->solicitarCotizacion($datos);
            echo json_encode($result);
            break;
            
        case 'get_contactos':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            $filtros = [
                'estado' => $_POST['estado'] ?? '',
                'fecha_desde' => $_POST['fecha_desde'] ?? '',
                'fecha_hasta' => $_POST['fecha_hasta'] ?? ''
            ];
            
            $contactos = $contacto->getContactos($filtros);
            echo json_encode(['success' => true, 'data' => $contactos]);
            break;
            
        case 'update_estado':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $contactoId = sanitize($_POST['contacto_id'] ?? '');
            $estado = sanitize($_POST['estado'] ?? '');
            
            if (empty($contactoId) || empty($estado)) {
                echo json_encode(['success' => false, 'message' => 'ID de contacto y estado son requeridos']);
                exit;
            }
            
            $result = $contacto->updateEstado($contactoId, $estado);
            echo json_encode($result);
            break;
            
        case 'get_cotizacion':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            $contactoId = sanitize($_POST['contacto_id'] ?? '');
            if (empty($contactoId)) {
                echo json_encode(['success' => false, 'message' => 'ID de contacto requerido']);
                exit;
            }
            
            $cotizacion = $contacto->getCotizacion($contactoId);
            if ($cotizacion) {
                echo json_encode(['success' => true, 'data' => $cotizacion]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Cotización no encontrada']);
            }
            break;
            
        case 'enviar_respuesta':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $contactoId = sanitize($_POST['contacto_id'] ?? '');
            $respuesta = sanitize($_POST['respuesta'] ?? '');
            $precio = sanitize($_POST['precio_propuesto'] ?? '');
            
            if (empty($contactoId) || empty($respuesta)) {
                echo json_encode(['success' => false, 'message' => 'ID de contacto y respuesta son requeridos']);
                exit;
            }
            
            $result = $contacto->enviarRespuestaCotizacion($contactoId, $respuesta, $precio);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    exit;
}
?>
