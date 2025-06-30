<?php
require_once 'config.php';

class ReservasSystem {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    // Obtener paquetes disponibles
    public function getPaquetes() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM paquetes WHERE activo = 1 ORDER BY precio ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Obtener disponibilidad para una fecha
    public function getDisponibilidad($fecha) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT fecha, hora_inicio, hora_fin, disponible 
                FROM disponibilidad 
                WHERE fecha = ? AND disponible = 1
                ORDER BY hora_inicio
            ");
            $stmt->execute([$fecha]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Crear nueva reserva
    public function crearReserva($datos) {
        try {
            $this->pdo->beginTransaction();
            
            // Verificar disponibilidad
            $stmt = $this->pdo->prepare("
                SELECT id FROM disponibilidad 
                WHERE fecha = ? AND hora_inicio = ? AND disponible = 1
            ");
            $stmt->execute([$datos['fecha'], $datos['hora']]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('La fecha y hora seleccionada no está disponible');
            }
            
            $disponibilidadId = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
            
            // Crear la reserva
            $stmt = $this->pdo->prepare("
                INSERT INTO reservas (
                    usuario_id, paquete_id, fecha, hora, nombre_evento, 
                    numero_personas, ubicacion, precio_total, 
                    comentarios, estado, fecha_creacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())
            ");
            
            $stmt->execute([
                $datos['usuario_id'],
                $datos['paquete_id'],
                $datos['fecha'],
                $datos['hora'],
                $datos['nombre_evento'],
                $datos['numero_personas'],
                $datos['ubicacion'],
                $datos['precio_total'],
                $datos['comentarios'] ?? ''
            ]);
            
            $reservaId = $this->pdo->lastInsertId();
            
            // Marcar la disponibilidad como ocupada
            $updateStmt = $this->pdo->prepare("UPDATE disponibilidad SET disponible = 0 WHERE id = ?");
            $updateStmt->execute([$disponibilidadId]);
            
            $this->pdo->commit();
            
            return [
                'success' => true, 
                'message' => 'Reserva creada exitosamente',
                'reserva_id' => $reservaId
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Obtener reservas del usuario
    public function getReservasUsuario($usuarioId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.*, p.nombre as paquete_nombre, p.descripcion as paquete_descripcion
                FROM reservas r
                JOIN paquetes p ON r.paquete_id = p.id
                WHERE r.usuario_id = ?
                ORDER BY r.fecha DESC, r.hora DESC
            ");
            $stmt->execute([$usuarioId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Obtener todas las reservas (admin)
    public function getAllReservas($filtros = []) {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filtros['estado'])) {
                $where[] = "r.estado = ?";
                $params[] = $filtros['estado'];
            }
            
            if (!empty($filtros['fecha_desde'])) {
                $where[] = "r.fecha >= ?";
                $params[] = $filtros['fecha_desde'];
            }
            
            if (!empty($filtros['fecha_hasta'])) {
                $where[] = "r.fecha <= ?";
                $params[] = $filtros['fecha_hasta'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $stmt = $this->pdo->prepare("
                SELECT r.*, u.nombre as cliente_nombre, u.email as cliente_email, 
                       p.nombre as paquete_nombre
                FROM reservas r
                JOIN usuarios u ON r.usuario_id = u.id
                JOIN paquetes p ON r.paquete_id = p.id
                {$whereClause}
                ORDER BY r.fecha DESC, r.hora DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Actualizar estado de reserva
    public function updateEstadoReserva($reservaId, $estado) {
        try {
            $stmt = $this->pdo->prepare("UPDATE reservas SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $reservaId]);
            
            // Si se cancela la reserva, liberar la disponibilidad
            if ($estado === 'cancelada') {
                $reservaStmt = $this->pdo->prepare("SELECT fecha, hora FROM reservas WHERE id = ?");
                $reservaStmt->execute([$reservaId]);
                $reserva = $reservaStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($reserva) {
                    $disponibilidadStmt = $this->pdo->prepare("
                        UPDATE disponibilidad 
                        SET disponible = 1 
                        WHERE fecha = ? AND hora_inicio = ?
                    ");
                    $disponibilidadStmt->execute([$reserva['fecha'], $reserva['hora']]);
                }
            }
            
            return ['success' => true, 'message' => 'Estado actualizado exitosamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al actualizar estado: ' . $e->getMessage()];
        }
    }
    
    // Obtener calendario de disponibilidad
    public function getCalendarioDisponibilidad($mes, $año) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DATE(fecha) as fecha, COUNT(*) as slots_disponibles
                FROM disponibilidad 
                WHERE MONTH(fecha) = ? AND YEAR(fecha) = ? AND disponible = 1
                GROUP BY DATE(fecha)
                ORDER BY fecha
            ");
            $stmt->execute([$mes, $año]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservas = new ReservasSystem();
    $action = $_POST['action'] ?? '';
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'get_paquetes':
            $paquetes = $reservas->getPaquetes();
            echo json_encode(['success' => true, 'data' => $paquetes]);
            break;
            
        case 'get_disponibilidad':
            $fecha = $_POST['fecha'] ?? '';
            if (empty($fecha)) {
                echo json_encode(['success' => false, 'message' => 'Fecha requerida']);
                exit;
            }
            
            $disponibilidad = $reservas->getDisponibilidad($fecha);
            echo json_encode(['success' => true, 'data' => $disponibilidad]);
            break;
            
        case 'crear_reserva':
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión para hacer una reserva']);
                exit;
            }
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $datos = [
                'usuario_id' => $_SESSION['user_id'],
                'paquete_id' => sanitize($_POST['paquete_id'] ?? ''),
                'fecha' => sanitize($_POST['fecha'] ?? ''),
                'hora' => sanitize($_POST['hora'] ?? ''),
                'nombre_evento' => sanitize($_POST['nombre_evento'] ?? ''),
                'numero_personas' => sanitize($_POST['numero_personas'] ?? ''),
                'ubicacion' => sanitize($_POST['ubicacion'] ?? ''),
                'precio_total' => sanitize($_POST['precio_total'] ?? ''),
                'comentarios' => sanitize($_POST['comentarios'] ?? '')
            ];
            
            // Validaciones básicas
            if (empty($datos['paquete_id']) || empty($datos['fecha']) || empty($datos['hora']) || 
                empty($datos['nombre_evento']) || empty($datos['numero_personas'])) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios deben ser completados']);
                exit;
            }
            
            $result = $reservas->crearReserva($datos);
            echo json_encode($result);
            break;
            
        case 'get_mis_reservas':
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            $misReservas = $reservas->getReservasUsuario($_SESSION['user_id']);
            echo json_encode(['success' => true, 'data' => $misReservas]);
            break;
            
        case 'get_all_reservas':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            $filtros = [
                'estado' => $_POST['estado'] ?? '',
                'fecha_desde' => $_POST['fecha_desde'] ?? '',
                'fecha_hasta' => $_POST['fecha_hasta'] ?? ''
            ];
            
            $todasReservas = $reservas->getAllReservas($filtros);
            echo json_encode(['success' => true, 'data' => $todasReservas]);
            break;
            
        case 'update_estado_reserva':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $reservaId = sanitize($_POST['reserva_id'] ?? '');
            $estado = sanitize($_POST['estado'] ?? '');
            
            if (empty($reservaId) || empty($estado)) {
                echo json_encode(['success' => false, 'message' => 'ID de reserva y estado son requeridos']);
                exit;
            }
            
            $result = $reservas->updateEstadoReserva($reservaId, $estado);
            echo json_encode($result);
            break;
            
        case 'get_calendario':
            $mes = $_POST['mes'] ?? date('n');
            $año = $_POST['año'] ?? date('Y');
            
            $calendario = $reservas->getCalendarioDisponibilidad($mes, $año);
            echo json_encode(['success' => true, 'data' => $calendario]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    exit;
}
?>
