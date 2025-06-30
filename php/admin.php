<?php
require_once 'config.php';

// Verificar que el usuario sea admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../html/index.html');
}

class AdminPanel {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    // Dashboard - estadísticas generales
    public function getDashboardStats() {
        try {
            $stats = [];
            
            // Total de usuarios
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'cliente'");
            $stats['total_clientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Reservas del mes actual
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as total 
                FROM reservas 
                WHERE MONTH(fecha_creacion) = MONTH(NOW()) 
                AND YEAR(fecha_creacion) = YEAR(NOW())
            ");
            $stats['reservas_mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Ingresos del mes
            $stmt = $this->pdo->query("
                SELECT SUM(precio_total) as total 
                FROM reservas 
                WHERE estado = 'confirmada' 
                AND MONTH(fecha_creacion) = MONTH(NOW()) 
                AND YEAR(fecha_creacion) = YEAR(NOW())
            ");
            $stats['ingresos_mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Reservas pendientes
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM reservas WHERE estado = 'pendiente'");
            $stats['reservas_pendientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Próximas sesiones (próximos 7 días)
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as total 
                FROM reservas 
                WHERE fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                AND estado = 'confirmada'
            ");
            $stats['proximas_sesiones'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return $stats;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Gestión de paquetes
    public function getPaquetes() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM paquetes ORDER BY precio ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function savePaquete($datos) {
        try {
            if (isset($datos['id']) && !empty($datos['id'])) {
                // Actualizar paquete existente
                $stmt = $this->pdo->prepare("
                    UPDATE paquetes 
                    SET nombre = ?, descripcion = ?, precio = ?, duracion = ?, 
                        fotos_incluidas = ?, retoque_incluido = ?, activo = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $datos['nombre'], $datos['descripcion'], $datos['precio'],
                    $datos['duracion'], $datos['fotos_incluidas'], 
                    $datos['retoque_incluido'], $datos['activo'], $datos['id']
                ]);
                $message = 'Paquete actualizado exitosamente';
            } else {
                // Crear nuevo paquete
                $stmt = $this->pdo->prepare("
                    INSERT INTO paquetes (nombre, descripcion, precio, duracion, 
                                        fotos_incluidas, retoque_incluido, activo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $datos['nombre'], $datos['descripcion'], $datos['precio'],
                    $datos['duracion'], $datos['fotos_incluidas'], 
                    $datos['retoque_incluido'], $datos['activo']
                ]);
                $message = 'Paquete creado exitosamente';
            }
            
            return ['success' => true, 'message' => $message];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al guardar paquete: ' . $e->getMessage()];
        }
    }
    
    public function deletePaquete($id) {
        try {
            $stmt = $this->pdo->prepare("UPDATE paquetes SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Paquete eliminado exitosamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al eliminar paquete: ' . $e->getMessage()];
        }
    }
    
    // Gestión de disponibilidad
    public function getDisponibilidad($fecha = null) {
        try {
            $where = $fecha ? "WHERE fecha = ?" : "WHERE fecha >= CURDATE()";
            $stmt = $this->pdo->prepare("SELECT * FROM disponibilidad {$where} ORDER BY fecha, hora_inicio");
            
            if ($fecha) {
                $stmt->execute([$fecha]);
            } else {
                $stmt->execute();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function saveDisponibilidad($datos) {
        try {
            if (isset($datos['id']) && !empty($datos['id'])) {
                // Actualizar disponibilidad existente
                $stmt = $this->pdo->prepare("
                    UPDATE disponibilidad 
                    SET fecha = ?, hora_inicio = ?, hora_fin = ?, disponible = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $datos['fecha'], $datos['hora_inicio'], $datos['hora_fin'],
                    $datos['disponible'], $datos['id']
                ]);
                $message = 'Disponibilidad actualizada exitosamente';
            } else {
                // Crear nueva disponibilidad
                $stmt = $this->pdo->prepare("
                    INSERT INTO disponibilidad (fecha, hora_inicio, hora_fin, disponible) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $datos['fecha'], $datos['hora_inicio'], $datos['hora_fin'], $datos['disponible']
                ]);
                $message = 'Disponibilidad creada exitosamente';
            }
            
            return ['success' => true, 'message' => $message];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al guardar disponibilidad: ' . $e->getMessage()];
        }
    }
    
    // Gestión de galerías
    public function getGalerias() {
        try {
            $stmt = $this->pdo->query("
                SELECT g.*, COUNT(f.id) as total_fotos 
                FROM galerias g 
                LEFT JOIN fotos f ON g.id = f.galeria_id 
                GROUP BY g.id 
                ORDER BY g.fecha_creacion DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function saveGaleria($datos) {
        try {
            if (isset($datos['id']) && !empty($datos['id'])) {
                // Actualizar galería existente
                $stmt = $this->pdo->prepare("
                    UPDATE galerias 
                    SET nombre = ?, descripcion = ?, categoria = ?, publica = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $datos['nombre'], $datos['descripcion'], 
                    $datos['categoria'], $datos['publica'], $datos['id']
                ]);
                $message = 'Galería actualizada exitosamente';
            } else {
                // Crear nueva galería
                $stmt = $this->pdo->prepare("
                    INSERT INTO galerias (nombre, descripcion, categoria, publica, fecha_creacion) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $datos['nombre'], $datos['descripcion'], 
                    $datos['categoria'], $datos['publica']
                ]);
                $message = 'Galería creada exitosamente';
            }
            
            return ['success' => true, 'message' => $message];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al guardar galería: ' . $e->getMessage()];
        }
    }
    
    // Gestión de contactos
    public function getContactos($estado = null) {
        try {
            $where = $estado ? "WHERE estado = ?" : "";
            $stmt = $this->pdo->prepare("SELECT * FROM contactos {$where} ORDER BY fecha_envio DESC");
            
            if ($estado) {
                $stmt->execute([$estado]);
            } else {
                $stmt->execute();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function updateContactoEstado($id, $estado) {
        try {
            $stmt = $this->pdo->prepare("UPDATE contactos SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $id]);
            return ['success' => true, 'message' => 'Estado actualizado exitosamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al actualizar estado: ' . $e->getMessage()];
        }
    }
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin = new AdminPanel();
    $action = $_POST['action'] ?? '';
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'get_dashboard_stats':
            $stats = $admin->getDashboardStats();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'get_paquetes':
            $paquetes = $admin->getPaquetes();
            echo json_encode(['success' => true, 'data' => $paquetes]);
            break;
            
        case 'save_paquete':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $datos = [
                'id' => sanitize($_POST['id'] ?? ''),
                'nombre' => sanitize($_POST['nombre'] ?? ''),
                'descripcion' => sanitize($_POST['descripcion'] ?? ''),
                'precio' => sanitize($_POST['precio'] ?? ''),
                'duracion' => sanitize($_POST['duracion'] ?? ''),
                'fotos_incluidas' => sanitize($_POST['fotos_incluidas'] ?? ''),
                'retoque_incluido' => isset($_POST['retoque_incluido']) ? 1 : 0,
                'activo' => isset($_POST['activo']) ? 1 : 0
            ];
            
            $result = $admin->savePaquete($datos);
            echo json_encode($result);
            break;
            
        case 'delete_paquete':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $id = sanitize($_POST['id'] ?? '');
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'ID requerido']);
                exit;
            }
            
            $result = $admin->deletePaquete($id);
            echo json_encode($result);
            break;
            
        case 'get_disponibilidad':
            $fecha = sanitize($_POST['fecha'] ?? '');
            $disponibilidad = $admin->getDisponibilidad($fecha);
            echo json_encode(['success' => true, 'data' => $disponibilidad]);
            break;
            
        case 'save_disponibilidad':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $datos = [
                'id' => sanitize($_POST['id'] ?? ''),
                'fecha' => sanitize($_POST['fecha'] ?? ''),
                'hora_inicio' => sanitize($_POST['hora_inicio'] ?? ''),
                'hora_fin' => sanitize($_POST['hora_fin'] ?? ''),
                'disponible' => isset($_POST['disponible']) ? 1 : 0
            ];
            
            $result = $admin->saveDisponibilidad($datos);
            echo json_encode($result);
            break;
            
        case 'get_galerias':
            $galerias = $admin->getGalerias();
            echo json_encode(['success' => true, 'data' => $galerias]);
            break;
            
        case 'save_galeria':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $datos = [
                'id' => sanitize($_POST['id'] ?? ''),
                'nombre' => sanitize($_POST['nombre'] ?? ''),
                'descripcion' => sanitize($_POST['descripcion'] ?? ''),
                'categoria' => sanitize($_POST['categoria'] ?? ''),
                'publica' => isset($_POST['publica']) ? 1 : 0
            ];
            
            $result = $admin->saveGaleria($datos);
            echo json_encode($result);
            break;
            
        case 'get_contactos':
            $estado = sanitize($_POST['estado'] ?? '');
            $contactos = $admin->getContactos($estado);
            echo json_encode(['success' => true, 'data' => $contactos]);
            break;
            
        case 'update_contacto_estado':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $id = sanitize($_POST['id'] ?? '');
            $estado = sanitize($_POST['estado'] ?? '');
            
            if (empty($id) || empty($estado)) {
                echo json_encode(['success' => false, 'message' => 'ID y estado son requeridos']);
                exit;
            }
            
            $result = $admin->updateContactoEstado($id, $estado);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Photo Studio</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--accent);
        }
        
        .admin-nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .nav-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .nav-btn:hover, .nav-btn.active {
            background: var(--accent);
        }
        
        .admin-section {
            display: none;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .admin-section.active {
            display: block;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 1rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }
        
        .action-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            margin: 0 0.25rem;
        }
        
        .action-btn:hover {
            opacity: 0.8;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pendiente { background: #fff3cd; color: #856404; }
        .status-confirmada { background: #d1edff; color: #0c5460; }
        .status-completada { background: #d4edda; color: #155724; }
        .status-cancelada { background: #f8d7da; color: #721c24; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            border-radius: 10px;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Panel de Administración</h1>
            <div>
                <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <button onclick="logout()" class="btn-secondary">Cerrar Sesión</button>
            </div>
        </div>
        
        <nav class="admin-nav">
            <button class="nav-btn active" onclick="showSection('dashboard')">Dashboard</button>
            <button class="nav-btn" onclick="showSection('reservas')">Reservas</button>
            <button class="nav-btn" onclick="showSection('paquetes')">Paquetes</button>
            <button class="nav-btn" onclick="showSection('disponibilidad')">Disponibilidad</button>
            <button class="nav-btn" onclick="showSection('galerias')">Galerías</button>
            <button class="nav-btn" onclick="showSection('contactos')">Contactos</button>
        </nav>
        
        <!-- Dashboard Section -->
        <div id="dashboard" class="admin-section active">
            <h2>Dashboard</h2>
            <div class="stats-grid" id="statsGrid">
                <!-- Las estadísticas se cargarán aquí via JavaScript -->
            </div>
        </div>
        
        <!-- Reservas Section -->
        <div id="reservas" class="admin-section">
            <h2>Gestión de Reservas</h2>
            <div class="form-grid">
                <div>
                    <label>Filtrar por estado:</label>
                    <select id="filtroEstado" onchange="loadReservas()">
                        <option value="">Todos</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="confirmada">Confirmadas</option>
                        <option value="completada">Completadas</option>
                        <option value="cancelada">Canceladas</option>
                    </select>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Paquete</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Estado</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="reservasTable">
                        <!-- Las reservas se cargarán aquí -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Paquetes Section -->
        <div id="paquetes" class="admin-section">
            <h2>Gestión de Paquetes</h2>
            <button onclick="showPaqueteModal()" class="btn-primary">Nuevo Paquete</button>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Precio</th>
                            <th>Duración</th>
                            <th>Fotos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="paquetesTable">
                        <!-- Los paquetes se cargarán aquí -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Disponibilidad Section -->
        <div id="disponibilidad" class="admin-section">
            <h2>Gestión de Disponibilidad</h2>
            <button onclick="showDisponibilidadModal()" class="btn-primary">Nueva Disponibilidad</button>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Hora Inicio</th>
                            <th>Hora Fin</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="disponibilidadTable">
                        <!-- La disponibilidad se cargará aquí -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Galerías Section -->
        <div id="galerias" class="admin-section">
            <h2>Gestión de Galerías</h2>
            <button onclick="showGaleriaModal()" class="btn-primary">Nueva Galería</button>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Fotos</th>
                            <th>Pública</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="galeriasTable">
                        <!-- Las galerías se cargarán aquí -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Contactos Section -->
        <div id="contactos" class="admin-section">
            <h2>Gestión de Contactos</h2>
            <div class="form-grid">
                <div>
                    <label>Filtrar por estado:</label>
                    <select id="filtroContactoEstado" onchange="loadContactos()">
                        <option value="">Todos</option>
                        <option value="nuevo">Nuevos</option>
                        <option value="leido">Leídos</option>
                        <option value="respondido">Respondidos</option>
                    </select>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Asunto</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="contactosTable">
                        <!-- Los contactos se cargarán aquí -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modales -->
    <div id="paqueteModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal('paqueteModal')">&times;</button>
            <h3 id="paqueteModalTitle">Nuevo Paquete</h3>
            <form id="paqueteForm">
                <input type="hidden" id="paqueteId" name="id">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="paqueteNombre">Nombre:</label>
                    <input type="text" id="paqueteNombre" name="nombre" required>
                </div>
                
                <div class="form-group">
                    <label for="paqueteDescripcion">Descripción:</label>
                    <textarea id="paqueteDescripcion" name="descripcion" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="paquetePrecio">Precio:</label>
                    <input type="number" id="paquetePrecio" name="precio" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="paqueteDuracion">Duración (horas):</label>
                    <input type="number" id="paqueteDuracion" name="duracion" step="0.5" required>
                </div>
                
                <div class="form-group">
                    <label for="paqueteFotos">Fotos incluidas:</label>
                    <input type="number" id="paqueteFotos" name="fotos_incluidas" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="paqueteRetoque" name="retoque_incluido">
                        Retoque incluido
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="paqueteActivo" name="activo" checked>
                        Activo
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Guardar</button>
                    <button type="button" onclick="closeModal('paqueteModal')" class="btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../js/main.js"></script>
    <script>
        // Variables globales
        let currentSection = 'dashboard';
        
        // Inicializar panel de administración
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardStats();
        });
        
        // Mostrar sección
        function showSection(section) {
            // Ocultar todas las secciones
            document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
            
            // Mostrar sección seleccionada
            document.getElementById(section).classList.add('active');
            event.target.classList.add('active');
            
            currentSection = section;
            
            // Cargar datos según la sección
            switch(section) {
                case 'dashboard':
                    loadDashboardStats();
                    break;
                case 'reservas':
                    loadReservas();
                    break;
                case 'paquetes':
                    loadPaquetes();
                    break;
                case 'disponibilidad':
                    loadDisponibilidad();
                    break;
                case 'galerias':
                    loadGalerias();
                    break;
                case 'contactos':
                    loadContactos();
                    break;
            }
        }
        
        // Cargar estadísticas del dashboard
        function loadDashboardStats() {
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_dashboard_stats'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const statsGrid = document.getElementById('statsGrid');
                    statsGrid.innerHTML = `
                        <div class="stat-card">
                            <span class="stat-number">${data.data.total_clientes}</span>
                            <span class="stat-label">Total Clientes</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-number">${data.data.reservas_mes}</span>
                            <span class="stat-label">Reservas Este Mes</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-number">$${parseFloat(data.data.ingresos_mes).toLocaleString()}</span>
                            <span class="stat-label">Ingresos Este Mes</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-number">${data.data.reservas_pendientes}</span>
                            <span class="stat-label">Reservas Pendientes</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-number">${data.data.proximas_sesiones}</span>
                            <span class="stat-label">Próximas Sesiones</span>
                        </div>
                    `;
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Cargar reservas
        function loadReservas() {
            const estado = document.getElementById('filtroEstado')?.value || '';
            
            fetch('../php/reservas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_all_reservas&estado=${estado}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('reservasTable');
                    tbody.innerHTML = data.data.map(reserva => `
                        <tr>
                            <td>${reserva.cliente_nombre}<br><small>${reserva.cliente_email}</small></td>
                            <td>${reserva.paquete_nombre}</td>
                            <td>${formatDate(reserva.fecha)}</td>
                            <td>${reserva.hora}</td>
                            <td><span class="status-badge status-${reserva.estado}">${reserva.estado}</span></td>
                            <td>$${parseFloat(reserva.precio_total).toLocaleString()}</td>
                            <td>
                                <select onchange="updateReservaEstado(${reserva.id}, this.value)">
                                    <option value="">Cambiar estado</option>
                                    <option value="pendiente" ${reserva.estado === 'pendiente' ? 'selected' : ''}>Pendiente</option>
                                    <option value="confirmada" ${reserva.estado === 'confirmada' ? 'selected' : ''}>Confirmada</option>
                                    <option value="completada" ${reserva.estado === 'completada' ? 'selected' : ''}>Completada</option>
                                    <option value="cancelada" ${reserva.estado === 'cancelada' ? 'selected' : ''}>Cancelada</option>
                                </select>
                            </td>
                        </tr>
                    `).join('');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Cargar paquetes
        function loadPaquetes() {
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_paquetes'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('paquetesTable');
                    tbody.innerHTML = data.data.map(paquete => `
                        <tr>
                            <td>${paquete.nombre}</td>
                            <td>$${parseFloat(paquete.precio).toLocaleString()}</td>
                            <td>${paquete.duracion}h</td>
                            <td>${paquete.fotos_incluidas}</td>
                            <td><span class="status-badge ${paquete.activo ? 'status-confirmada' : 'status-cancelada'}">${paquete.activo ? 'Activo' : 'Inactivo'}</span></td>
                            <td>
                                <button class="action-btn" onclick="editPaquete(${paquete.id})">Editar</button>
                                <button class="action-btn" onclick="deletePaquete(${paquete.id})">Eliminar</button>
                            </td>
                        </tr>
                    `).join('');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Mostrar modal de paquete
        function showPaqueteModal(paqueteId = null) {
            const modal = document.getElementById('paqueteModal');
            const title = document.getElementById('paqueteModalTitle');
            const form = document.getElementById('paqueteForm');
            
            if (paqueteId) {
                title.textContent = 'Editar Paquete';
                // Cargar datos del paquete para editar
                // ... implementar carga de datos
            } else {
                title.textContent = 'Nuevo Paquete';
                form.reset();
                document.getElementById('paqueteActivo').checked = true;
            }
            
            modal.style.display = 'block';
        }
        
        // Cerrar modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Manejar envío del formulario de paquete
        document.getElementById('paqueteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'save_paquete');
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('paqueteModal');
                    loadPaquetes();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al guardar paquete', 'error');
            });
        });
        
        // Actualizar estado de reserva
        function updateReservaEstado(reservaId, estado) {
            if (!estado) return;
            
            const formData = new FormData();
            formData.append('action', 'update_estado_reserva');
            formData.append('reserva_id', reservaId);
            formData.append('estado', estado);
            formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
            
            fetch('../php/reservas.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    loadReservas();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al actualizar estado', 'error');
            });
        }
        
        // Función auxiliar para formatear fechas
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES');
        }
        
        // Cerrar sesión
        function logout() {
            fetch('../php/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=logout'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '../html/index.html';
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Placeholder functions for other sections
        function loadDisponibilidad() { /* Implementar */ }
        function loadGalerias() { /* Implementar */ }
        function loadContactos() { /* Implementar */ }
        function editPaquete(id) { /* Implementar */ }
        function deletePaquete(id) { /* Implementar */ }
        function showDisponibilidadModal() { /* Implementar */ }
        function showGaleriaModal() { /* Implementar */ }
    </script>
</body>
</html>
