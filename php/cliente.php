<?php
require_once 'config.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    redirect('../html/index.html');
}

class ClienteArea {
    private $pdo;
    private $userId;
    
    public function __construct($userId) {
        $this->pdo = getDBConnection();
        $this->userId = $userId;
    }
    
    // Obtener información del cliente
    public function getClienteInfo() {
        try {
            $stmt = $this->pdo->prepare("SELECT nombre, email, telefono, fecha_registro FROM usuarios WHERE id = ?");
            $stmt->execute([$this->userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }
    
    // Obtener reservas del cliente
    public function getReservas() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.*, p.nombre as paquete_nombre, p.descripcion as paquete_descripcion,
                       p.fotos_incluidas, p.retoque_incluido
                FROM reservas r
                JOIN paquetes p ON r.paquete_id = p.id
                WHERE r.usuario_id = ?
                ORDER BY r.fecha DESC, r.hora DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Obtener galerías del cliente
    public function getGalerias() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT g.*, COUNT(f.id) as total_fotos
                FROM galerias g
                LEFT JOIN fotos f ON g.id = f.galeria_id
                WHERE g.cliente_id = ? OR g.publica = 1
                GROUP BY g.id
                ORDER BY g.fecha_creacion DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Obtener fotos de una galería específica
    public function getFotosGaleria($galeriaId) {
        try {
            // Verificar que el cliente tenga acceso a esta galería
            $stmt = $this->pdo->prepare("
                SELECT * FROM galerias 
                WHERE id = ? AND (cliente_id = ? OR publica = 1)
            ");
            $stmt->execute([$galeriaId, $this->userId]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Acceso denegado a esta galería'];
            }
            
            $galeria = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener las fotos
            $fotosStmt = $this->pdo->prepare("
                SELECT * FROM fotos 
                WHERE galeria_id = ? 
                ORDER BY orden ASC, fecha_subida DESC
            ");
            $fotosStmt->execute([$galeriaId]);
            $fotos = $fotosStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'galeria' => $galeria,
                'fotos' => $fotos
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al cargar galería'];
        }
    }
    
    // Actualizar información del cliente
    public function updateInfo($datos) {
        try {
            $stmt = $this->pdo->prepare("UPDATE usuarios SET nombre = ?, telefono = ? WHERE id = ?");
            $stmt->execute([$datos['nombre'], $datos['telefono'], $this->userId]);
            
            // Actualizar sesión
            $_SESSION['user_name'] = $datos['nombre'];
            
            return ['success' => true, 'message' => 'Información actualizada exitosamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al actualizar información: ' . $e->getMessage()];
        }
    }
    
    // Marcar foto como favorita
    public function toggleFavorito($fotoId) {
        try {
            // Verificar que la foto pertenezca a una galería accesible
            $stmt = $this->pdo->prepare("
                SELECT f.id FROM fotos f
                JOIN galerias g ON f.galeria_id = g.id
                WHERE f.id = ? AND (g.cliente_id = ? OR g.publica = 1)
            ");
            $stmt->execute([$fotoId, $this->userId]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Acceso denegado'];
            }
            
            // Verificar si ya es favorita
            $favStmt = $this->pdo->prepare("
                SELECT id FROM favoritos WHERE usuario_id = ? AND foto_id = ?
            ");
            $favStmt->execute([$this->userId, $fotoId]);
            
            if ($favStmt->rowCount() > 0) {
                // Remover de favoritos
                $deleteStmt = $this->pdo->prepare("DELETE FROM favoritos WHERE usuario_id = ? AND foto_id = ?");
                $deleteStmt->execute([$this->userId, $fotoId]);
                $message = 'Foto removida de favoritos';
                $isFavorito = false;
            } else {
                // Agregar a favoritos
                $insertStmt = $this->pdo->prepare("INSERT INTO favoritos (usuario_id, foto_id, fecha_agregado) VALUES (?, ?, NOW())");
                $insertStmt->execute([$this->userId, $fotoId]);
                $message = 'Foto agregada a favoritos';
                $isFavorito = true;
            }
            
            return ['success' => true, 'message' => $message, 'is_favorito' => $isFavorito];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al actualizar favorito: ' . $e->getMessage()];
        }
    }
    
    // Obtener fotos favoritas
    public function getFavoritos() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT f.*, g.nombre as galeria_nombre, fav.fecha_agregado
                FROM fotos f
                JOIN galerias g ON f.galeria_id = g.id
                JOIN favoritos fav ON f.id = fav.foto_id
                WHERE fav.usuario_id = ?
                ORDER BY fav.fecha_agregado DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente = new ClienteArea($_SESSION['user_id']);
    $action = $_POST['action'] ?? '';
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'get_info':
            $info = $cliente->getClienteInfo();
            echo json_encode(['success' => true, 'data' => $info]);
            break;
            
        case 'get_reservas':
            $reservas = $cliente->getReservas();
            echo json_encode(['success' => true, 'data' => $reservas]);
            break;
            
        case 'get_galerias':
            $galerias = $cliente->getGalerias();
            echo json_encode(['success' => true, 'data' => $galerias]);
            break;
            
        case 'get_fotos_galeria':
            $galeriaId = sanitize($_POST['galeria_id'] ?? '');
            if (empty($galeriaId)) {
                echo json_encode(['success' => false, 'message' => 'ID de galería requerido']);
                exit;
            }
            
            $result = $cliente->getFotosGaleria($galeriaId);
            echo json_encode($result);
            break;
            
        case 'update_info':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $datos = [
                'nombre' => sanitize($_POST['nombre'] ?? ''),
                'telefono' => sanitize($_POST['telefono'] ?? '')
            ];
            
            if (empty($datos['nombre'])) {
                echo json_encode(['success' => false, 'message' => 'El nombre es requerido']);
                exit;
            }
            
            $result = $cliente->updateInfo($datos);
            echo json_encode($result);
            break;
            
        case 'toggle_favorito':
            $fotoId = sanitize($_POST['foto_id'] ?? '');
            if (empty($fotoId)) {
                echo json_encode(['success' => false, 'message' => 'ID de foto requerido']);
                exit;
            }
            
            $result = $cliente->toggleFavorito($fotoId);
            echo json_encode($result);
            break;
            
        case 'get_favoritos':
            $favoritos = $cliente->getFavoritos();
            echo json_encode(['success' => true, 'data' => $favoritos]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    exit;
}

$cliente = new ClienteArea($_SESSION['user_id']);
$clienteInfo = $cliente->getClienteInfo();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área de Cliente - Photo Studio</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .cliente-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .cliente-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--accent);
        }
        
        .cliente-nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
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
        
        .cliente-section {
            display: none;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .cliente-section.active {
            display: block;
        }
        
        .reserva-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .reserva-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .reserva-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .reserva-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--primary);
        }
        
        .galeria-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .galeria-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .galeria-card:hover {
            transform: translateY(-5px);
        }
        
        .galeria-preview {
            height: 200px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        
        .galeria-info {
            padding: 1rem;
        }
        
        .galeria-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .galeria-meta {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 1rem;
        }
        
        .fotos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .foto-card {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .foto-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }
        
        .foto-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .foto-card:hover .foto-overlay {
            opacity: 1;
        }
        
        .foto-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .action-btn:hover {
            opacity: 0.8;
        }
        
        .favorito-btn {
            background: #ff6b6b;
        }
        
        .favorito-btn.active {
            background: #ff3333;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            max-width: 90%;
            max-height: 90%;
            overflow-y: auto;
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
            color: #666;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .cliente-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .cliente-nav {
                justify-content: center;
            }
            
            .reserva-details {
                grid-template-columns: 1fr;
            }
            
            .galeria-grid {
                grid-template-columns: 1fr;
            }
            
            .fotos-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="cliente-container">
        <div class="cliente-header">
            <h1>Área de Cliente</h1>
            <div>
                <span>Bienvenido, <?php echo htmlspecialchars($clienteInfo['nombre']); ?></span>
                <button onclick="logout()" class="btn-secondary">Cerrar Sesión</button>
            </div>
        </div>
        
        <nav class="cliente-nav">
            <button class="nav-btn active" onclick="showSection('perfil')">Mi Perfil</button>
            <button class="nav-btn" onclick="showSection('reservas')">Mis Reservas</button>
            <button class="nav-btn" onclick="showSection('galerias')">Mis Galerías</button>
            <button class="nav-btn" onclick="showSection('favoritos')">Favoritos</button>
        </nav>
        
        <!-- Perfil Section -->
        <div id="perfil" class="cliente-section active">
            <h2>Mi Perfil</h2>
            <form id="perfilForm" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="nombre">Nombre:</label>
                    <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($clienteInfo['nombre']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($clienteInfo['email']); ?>" disabled>
                    <small>El email no puede ser modificado</small>
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono:</label>
                    <input type="tel" id="telefono" name="telefono" value="<?php echo htmlspecialchars($clienteInfo['telefono']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Cliente desde:</label>
                    <input type="text" value="<?php echo date('d/m/Y', strtotime($clienteInfo['fecha_registro'])); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn-primary">Actualizar Información</button>
                </div>
                
                <div class="form-group">
                    <button type="button" onclick="showChangePasswordModal()" class="btn-secondary">Cambiar Contraseña</button>
                </div>
            </form>
        </div>
        
        <!-- Reservas Section -->
        <div id="reservas" class="cliente-section">
            <h2>Mis Reservas</h2>
            <div id="reservasContainer">
                <!-- Las reservas se cargarán aquí -->
            </div>
        </div>
        
        <!-- Galerías Section -->
        <div id="galerias" class="cliente-section">
            <h2>Mis Galerías</h2>
            <div id="galeriasContainer" class="galeria-grid">
                <!-- Las galerías se cargarán aquí -->
            </div>
        </div>
        
        <!-- Favoritos Section -->
        <div id="favoritos" class="cliente-section">
            <h2>Mis Fotos Favoritas</h2>
            <div id="favoritosContainer" class="fotos-grid">
                <!-- Los favoritos se cargarán aquí -->
            </div>
        </div>
    </div>
    
    <!-- Modal para cambiar contraseña -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal('changePasswordModal')">&times;</button>
            <h3>Cambiar Contraseña</h3>
            <form id="changePasswordForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="currentPassword">Contraseña Actual:</label>
                    <input type="password" id="currentPassword" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="newPassword">Nueva Contraseña:</label>
                    <input type="password" id="newPassword" name="new_password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirmPassword">Confirmar Nueva Contraseña:</label>
                    <input type="password" id="confirmPassword" required minlength="6">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Cambiar Contraseña</button>
                    <button type="button" onclick="closeModal('changePasswordModal')" class="btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para ver galería -->
    <div id="galeriaModal" class="modal">
        <div class="modal-content" style="max-width: 1200px;">
            <button class="close-modal" onclick="closeModal('galeriaModal')">&times;</button>
            <div id="galeriaModalContent">
                <!-- El contenido de la galería se cargará aquí -->
            </div>
        </div>
    </div>
    
    <script src="../js/main.js"></script>
    <script>
        // Variables globales
        let currentSection = 'perfil';
        
        // Inicializar área de cliente
        document.addEventListener('DOMContentLoaded', function() {
            // Ya se carga el perfil por defecto
        });
        
        // Mostrar sección
        function showSection(section) {
            // Ocultar todas las secciones
            document.querySelectorAll('.cliente-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
            
            // Mostrar sección seleccionada
            document.getElementById(section).classList.add('active');
            event.target.classList.add('active');
            
            currentSection = section;
            
            // Cargar datos según la sección
            switch(section) {
                case 'reservas':
                    loadReservas();
                    break;
                case 'galerias':
                    loadGalerias();
                    break;
                case 'favoritos':
                    loadFavoritos();
                    break;
            }
        }
        
        // Cargar reservas
        function loadReservas() {
            fetch('cliente.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_reservas'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('reservasContainer');
                    
                    if (data.data.length === 0) {
                        container.innerHTML = '<p class="text-center">No tienes reservas aún.</p>';
                        return;
                    }
                    
                    container.innerHTML = data.data.map(reserva => `
                        <div class="reserva-card">
                            <div class="reserva-header">
                                <div class="reserva-title">${reserva.nombre_evento}</div>
                                <span class="status-badge status-${reserva.estado}">${reserva.estado}</span>
                            </div>
                            <div class="reserva-details">
                                <div class="detail-item">
                                    <span class="detail-label">Paquete</span>
                                    <span class="detail-value">${reserva.paquete_nombre}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Fecha</span>
                                    <span class="detail-value">${formatDate(reserva.fecha)}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Hora</span>
                                    <span class="detail-value">${reserva.hora}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Personas</span>
                                    <span class="detail-value">${reserva.numero_personas}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Ubicación</span>
                                    <span class="detail-value">${reserva.ubicacion}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Precio Total</span>
                                    <span class="detail-value">$${parseFloat(reserva.precio_total).toLocaleString()}</span>
                                </div>
                            </div>
                            ${reserva.comentarios ? `
                                <div class="detail-item">
                                    <span class="detail-label">Comentarios</span>
                                    <span class="detail-value">${reserva.comentarios}</span>
                                </div>
                            ` : ''}
                        </div>
                    `).join('');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Cargar galerías
        function loadGalerias() {
            fetch('cliente.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_galerias'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('galeriasContainer');
                    
                    if (data.data.length === 0) {
                        container.innerHTML = '<p class="text-center">No hay galerías disponibles.</p>';
                        return;
                    }
                    
                    container.innerHTML = data.data.map(galeria => `
                        <div class="galeria-card">
                            <div class="galeria-preview">
                                📸
                            </div>
                            <div class="galeria-info">
                                <div class="galeria-title">${galeria.nombre}</div>
                                <div class="galeria-meta">
                                    ${galeria.categoria} • ${galeria.total_fotos} fotos
                                </div>
                                <p>${galeria.descripcion}</p>
                                <button onclick="verGaleria(${galeria.id})" class="btn-primary">Ver Galería</button>
                            </div>
                        </div>
                    `).join('');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Ver galería específica
        function verGaleria(galeriaId) {
            fetch('cliente.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_fotos_galeria&galeria_id=${galeriaId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = document.getElementById('galeriaModal');
                    const content = document.getElementById('galeriaModalContent');
                    
                    content.innerHTML = `
                        <h3>${data.galeria.nombre}</h3>
                        <p>${data.galeria.descripcion}</p>
                        <div class="fotos-grid">
                            ${data.fotos.map(foto => `
                                <div class="foto-card">
                                    <img src="${foto.ruta_archivo}" alt="${foto.titulo}" class="foto-img">
                                    <div class="foto-overlay">
                                        <div class="foto-actions">
                                            <button onclick="toggleFavorito(${foto.id})" class="action-btn favorito-btn">❤</button>
                                            <button onclick="descargarFoto('${foto.ruta_archivo}')" class="action-btn">⬇</button>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                    
                    modal.classList.add('active');
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Cargar favoritos
        function loadFavoritos() {
            fetch('cliente.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_favoritos'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('favoritosContainer');
                    
                    if (data.data.length === 0) {
                        container.innerHTML = '<p class="text-center">No tienes fotos favoritas aún.</p>';
                        return;
                    }
                    
                    container.innerHTML = data.data.map(foto => `
                        <div class="foto-card">
                            <img src="${foto.ruta_archivo}" alt="${foto.titulo}" class="foto-img">
                            <div class="foto-overlay">
                                <div class="foto-actions">
                                    <button onclick="toggleFavorito(${foto.id})" class="action-btn favorito-btn active">❤</button>
                                    <button onclick="descargarFoto('${foto.ruta_archivo}')" class="action-btn">⬇</button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Toggle favorito
        function toggleFavorito(fotoId) {
            fetch('cliente.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle_favorito&foto_id=${fotoId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Recargar la sección actual si es favoritos
                    if (currentSection === 'favoritos') {
                        loadFavoritos();
                    }
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Descargar foto
        function descargarFoto(rutaArchivo) {
            const link = document.createElement('a');
            link.href = rutaArchivo;
            link.download = rutaArchivo.split('/').pop();
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Manejar envío del formulario de perfil
        document.getElementById('perfilForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_info');
            
            fetch('cliente.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al actualizar información', 'error');
            });
        });
        
        // Manejar cambio de contraseña
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                showNotification('Las contraseñas no coinciden', 'error');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'change_password');
            
            fetch('../php/auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('changePasswordModal');
                    this.reset();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al cambiar contraseña', 'error');
            });
        });
        
        // Mostrar modal de cambio de contraseña
        function showChangePasswordModal() {
            document.getElementById('changePasswordModal').classList.add('active');
        }
        
        // Cerrar modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
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
        
        // Cerrar modales al hacer clic fuera
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
