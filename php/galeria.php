<?php
require_once 'config.php';

class GaleriaSystem {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    // Obtener galerías públicas
    public function getGaleriasPublicas($categoria = null, $limit = null) {
        try {
            $where = "WHERE publica = 1";
            $params = [];
            
            if ($categoria) {
                $where .= " AND categoria = ?";
                $params[] = $categoria;
            }
            
            $limitClause = $limit ? "LIMIT " . intval($limit) : "";
            
            $stmt = $this->pdo->prepare("
                SELECT g.*, COUNT(f.id) as total_fotos,
                       (SELECT ruta_archivo FROM fotos WHERE galeria_id = g.id ORDER BY orden ASC, id ASC LIMIT 1) as foto_portada
                FROM galerias g
                LEFT JOIN fotos f ON g.id = f.galeria_id
                {$where}
                GROUP BY g.id
                ORDER BY g.fecha_creacion DESC
                {$limitClause}
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Obtener todas las galerías (admin)
    public function getAllGalerias() {
        try {
            $stmt = $this->pdo->query("
                SELECT g.*, COUNT(f.id) as total_fotos,
                       u.nombre as cliente_nombre
                FROM galerias g
                LEFT JOIN fotos f ON g.id = f.galeria_id
                LEFT JOIN usuarios u ON g.cliente_id = u.id
                GROUP BY g.id
                ORDER BY g.fecha_creacion DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Obtener galería específica
    public function getGaleria($id, $includePrivate = false) {
        try {
            $where = $includePrivate ? "" : "AND publica = 1";
            
            $stmt = $this->pdo->prepare("
                SELECT g.*, u.nombre as cliente_nombre
                FROM galerias g
                LEFT JOIN usuarios u ON g.cliente_id = u.id
                WHERE g.id = ? {$where}
            ");
            $stmt->execute([$id]);
            
            $galeria = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$galeria) {
                return null;
            }
            
            // Obtener fotos de la galería
            $fotosStmt = $this->pdo->prepare("
                SELECT * FROM fotos 
                WHERE galeria_id = ? 
                ORDER BY orden ASC, fecha_subida ASC
            ");
            $fotosStmt->execute([$id]);
            $galeria['fotos'] = $fotosStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $galeria;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    // Crear nueva galería
    public function crearGaleria($datos) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO galerias (nombre, descripcion, categoria, cliente_id, publica, fecha_creacion)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $datos['nombre'],
                $datos['descripcion'],
                $datos['categoria'],
                $datos['cliente_id'] ?? null,
                $datos['publica'] ?? 1
            ]);
            
            $galeriaId = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Galería creada exitosamente',
                'galeria_id' => $galeriaId
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al crear galería: ' . $e->getMessage()];
        }
    }
    
    // Actualizar galería
    public function actualizarGaleria($id, $datos) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE galerias 
                SET nombre = ?, descripcion = ?, categoria = ?, cliente_id = ?, publica = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $datos['nombre'],
                $datos['descripcion'],
                $datos['categoria'],
                $datos['cliente_id'] ?? null,
                $datos['publica'] ?? 1,
                $id
            ]);
            
            return ['success' => true, 'message' => 'Galería actualizada exitosamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al actualizar galería: ' . $e->getMessage()];
        }
    }
    
    // Subir foto a galería
    public function subirFoto($galeriaId, $archivo, $datos = []) {
        try {
            // Verificar que la galería existe
            $stmt = $this->pdo->prepare("SELECT id FROM galerias WHERE id = ?");
            $stmt->execute([$galeriaId]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Galería no encontrada'];
            }
            
            // Validar archivo
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($archivo['type'], $allowedTypes)) {
                return ['success' => false, 'message' => 'Tipo de archivo no permitido'];
            }
            
            if ($archivo['size'] > MAX_FILE_SIZE) {
                return ['success' => false, 'message' => 'El archivo es demasiado grande'];
            }
            
            // Crear directorio si no existe
            $uploadDir = '../uploads/galerias/' . $galeriaId . '/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generar nombre único para el archivo
            $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
            $nombreArchivo = uniqid() . '.' . $extension;
            $rutaCompleta = $uploadDir . $nombreArchivo;
            $rutaRelativa = 'uploads/galerias/' . $galeriaId . '/' . $nombreArchivo;
            
            // Mover archivo
            if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
                return ['success' => false, 'message' => 'Error al subir archivo'];
            }
            
            // Obtener el próximo orden
            $ordenStmt = $this->pdo->prepare("SELECT COALESCE(MAX(orden), 0) + 1 as siguiente_orden FROM fotos WHERE galeria_id = ?");
            $ordenStmt->execute([$galeriaId]);
            $orden = $ordenStmt->fetch(PDO::FETCH_ASSOC)['siguiente_orden'];
            
            // Insertar registro en la base de datos
            $fotoStmt = $this->pdo->prepare("
                INSERT INTO fotos (galeria_id, titulo, descripcion, ruta_archivo, orden, fecha_subida)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $fotoStmt->execute([
                $galeriaId,
                $datos['titulo'] ?? basename($archivo['name'], '.' . $extension),
                $datos['descripcion'] ?? '',
                $rutaRelativa,
                $orden
            ]);
            
            $fotoId = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Foto subida exitosamente',
                'foto_id' => $fotoId,
                'ruta' => $rutaRelativa
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al subir foto: ' . $e->getMessage()];
        }
    }
    
    // Eliminar foto
    public function eliminarFoto($fotoId) {
        try {
            // Obtener información de la foto
            $stmt = $this->pdo->prepare("SELECT ruta_archivo FROM fotos WHERE id = ?");
            $stmt->execute([$fotoId]);
            $foto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$foto) {
                return ['success' => false, 'message' => 'Foto no encontrada'];
            }
            
            // Eliminar archivo físico
            $rutaCompleta = '../' . $foto['ruta_archivo'];
            if (file_exists($rutaCompleta)) {
                unlink($rutaCompleta);
            }
            
            // Eliminar registro de la base de datos
            $deleteStmt = $this->pdo->prepare("DELETE FROM fotos WHERE id = ?");
            $deleteStmt->execute([$fotoId]);
            
            // Eliminar favoritos relacionados
            $favStmt = $this->pdo->prepare("DELETE FROM favoritos WHERE foto_id = ?");
            $favStmt->execute([$fotoId]);
            
            return ['success' => true, 'message' => 'Foto eliminada exitosamente'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al eliminar foto: ' . $e->getMessage()];
        }
    }
    
    // Reordenar fotos
    public function reordenarFotos($galeriaId, $nuevosOrdenes) {
        try {
            $this->pdo->beginTransaction();
            
            foreach ($nuevosOrdenes as $fotoId => $orden) {
                $stmt = $this->pdo->prepare("UPDATE fotos SET orden = ? WHERE id = ? AND galeria_id = ?");
                $stmt->execute([$orden, $fotoId, $galeriaId]);
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Orden actualizado exitosamente'];
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Error al reordenar fotos: ' . $e->getMessage()];
        }
    }
    
    // Obtener categorías disponibles
    public function getCategorias() {
        try {
            $stmt = $this->pdo->query("SELECT DISTINCT categoria FROM galerias WHERE publica = 1 AND categoria IS NOT NULL ORDER BY categoria");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Búsqueda de galerías
    public function buscarGalerias($termino, $categoria = null) {
        try {
            $where = "WHERE publica = 1 AND (nombre LIKE ? OR descripcion LIKE ?)";
            $params = ["%{$termino}%", "%{$termino}%"];
            
            if ($categoria) {
                $where .= " AND categoria = ?";
                $params[] = $categoria;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT g.*, COUNT(f.id) as total_fotos,
                       (SELECT ruta_archivo FROM fotos WHERE galeria_id = g.id ORDER BY orden ASC, id ASC LIMIT 1) as foto_portada
                FROM galerias g
                LEFT JOIN fotos f ON g.id = f.galeria_id
                {$where}
                GROUP BY g.id
                ORDER BY g.fecha_creacion DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $galeria = new GaleriaSystem();
    $action = $_POST['action'] ?? '';
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'get_galerias_publicas':
            $categoria = sanitize($_POST['categoria'] ?? '');
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : null;
            
            $galerias = $galeria->getGaleriasPublicas($categoria ?: null, $limit);
            echo json_encode(['success' => true, 'data' => $galerias]);
            break;
            
        case 'get_all_galerias':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            $galerias = $galeria->getAllGalerias();
            echo json_encode(['success' => true, 'data' => $galerias]);
            break;
            
        case 'get_galeria':
            $id = sanitize($_POST['id'] ?? '');
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'ID de galería requerido']);
                exit;
            }
            
            $includePrivate = isAdmin() || (isLoggedIn() && isset($_POST['include_private']));
            $galeriaData = $galeria->getGaleria($id, $includePrivate);
            
            if ($galeriaData) {
                echo json_encode(['success' => true, 'data' => $galeriaData]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Galería no encontrada']);
            }
            break;
            
        case 'crear_galeria':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $datos = [
                'nombre' => sanitize($_POST['nombre'] ?? ''),
                'descripcion' => sanitize($_POST['descripcion'] ?? ''),
                'categoria' => sanitize($_POST['categoria'] ?? ''),
                'cliente_id' => sanitize($_POST['cliente_id'] ?? '') ?: null,
                'publica' => isset($_POST['publica']) ? 1 : 0
            ];
            
            if (empty($datos['nombre'])) {
                echo json_encode(['success' => false, 'message' => 'El nombre de la galería es requerido']);
                exit;
            }
            
            $result = $galeria->crearGaleria($datos);
            echo json_encode($result);
            break;
            
        case 'actualizar_galeria':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $id = sanitize($_POST['id'] ?? '');
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'ID de galería requerido']);
                exit;
            }
            
            $datos = [
                'nombre' => sanitize($_POST['nombre'] ?? ''),
                'descripcion' => sanitize($_POST['descripcion'] ?? ''),
                'categoria' => sanitize($_POST['categoria'] ?? ''),
                'cliente_id' => sanitize($_POST['cliente_id'] ?? '') ?: null,
                'publica' => isset($_POST['publica']) ? 1 : 0
            ];
            
            $result = $galeria->actualizarGaleria($id, $datos);
            echo json_encode($result);
            break;
            
        case 'subir_foto':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $galeriaId = sanitize($_POST['galeria_id'] ?? '');
            if (empty($galeriaId)) {
                echo json_encode(['success' => false, 'message' => 'ID de galería requerido']);
                exit;
            }
            
            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Error al subir archivo']);
                exit;
            }
            
            $datos = [
                'titulo' => sanitize($_POST['titulo'] ?? ''),
                'descripcion' => sanitize($_POST['descripcion'] ?? '')
            ];
            
            $result = $galeria->subirFoto($galeriaId, $_FILES['foto'], $datos);
            echo json_encode($result);
            break;
            
        case 'eliminar_foto':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $fotoId = sanitize($_POST['foto_id'] ?? '');
            if (empty($fotoId)) {
                echo json_encode(['success' => false, 'message' => 'ID de foto requerido']);
                exit;
            }
            
            $result = $galeria->eliminarFoto($fotoId);
            echo json_encode($result);
            break;
            
        case 'get_categorias':
            $categorias = $galeria->getCategorias();
            echo json_encode(['success' => true, 'data' => $categorias]);
            break;
            
        case 'buscar_galerias':
            $termino = sanitize($_POST['termino'] ?? '');
            $categoria = sanitize($_POST['categoria'] ?? '');
            
            if (empty($termino)) {
                echo json_encode(['success' => false, 'message' => 'Término de búsqueda requerido']);
                exit;
            }
            
            $resultados = $galeria->buscarGalerias($termino, $categoria ?: null);
            echo json_encode(['success' => true, 'data' => $resultados]);
            break;
            
        case 'reordenar_fotos':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
            
            $galeriaId = sanitize($_POST['galeria_id'] ?? '');
            $nuevosOrdenes = $_POST['ordenes'] ?? [];
            
            if (empty($galeriaId) || empty($nuevosOrdenes)) {
                echo json_encode(['success' => false, 'message' => 'Datos insuficientes']);
                exit;
            }
            
            $result = $galeria->reordenarFotos($galeriaId, $nuevosOrdenes);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    exit;
}

// Si es una solicitud GET, servir página de galería
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $galeriaSystem = new GaleriaSystem();
    $galeriaId = sanitize($_GET['id']);
    $galeriaData = $galeriaSystem->getGaleria($galeriaId);
    
    if (!$galeriaData) {
        header('HTTP/1.0 404 Not Found');
        echo "Galería no encontrada";
        exit;
    }
    
    // Renderizar página de galería individual
    include 'galeria_view.php';
    exit;
}
?>
