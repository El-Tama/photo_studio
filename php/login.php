<?php
require_once 'config.php';
require_once 'auth.php';

// Si ya está logueado, redirigir según el rol
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin.php');
    } else {
        redirect('cliente.php');
    }
}

// Si es una solicitud POST, procesar login via AJAX (manejado en auth.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // El auth.php maneja todas las solicitudes POST
    include 'auth.php';
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Iniciar Sesión - Photo Studio</title>
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Bootstrap Icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <link rel="stylesheet" href="../css/styles.css">
        <link rel="stylesheet" href="../css/login.css">
    </head>
    <body>
        <div class="login-container">
            <div class="container-fluid h-100">
                <div class="row h-100 justify-content-center align-items-center">
                    <div class="col-11 col-sm-8 col-md-6 col-lg-5 col-xl-4">
                        <div class="login-box card shadow-lg border-0">
                            <div class="card-body p-4 p-md-5">
                                <div class="login-header text-center mb-4">
                                    <h1 class="login-title h2 fw-bold text-primary mb-2">
                                        <i class="bi bi-camera-fill me-2"></i>Photo Studio
                                    </h1>
                                    <p class="login-subtitle text-muted">Accede a tu cuenta</p>
                                </div>
                                
                                <div class="login-tabs mb-4">
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="loginTabs" id="loginTab" checked>
                                        <label class="btn btn-outline-primary" for="loginTab" onclick="showTab('login')">
                                            <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar Sesión
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="loginTabs" id="registerTab">
                                        <label class="btn btn-outline-primary" for="registerTab" onclick="showTab('register')">
                                            <i class="bi bi-person-plus me-1"></i>Registrarse
                                        </label>
                                    </div>
                                </div>
                                
                                <div id="messageContainer"></div>
                                
                                <!-- Formulario de Login -->
                                <form id="loginForm" class="login-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="login">
                                    
                                    <div class="mb-3">
                                        <label for="loginEmail" class="form-label fw-semibold">
                                            <i class="bi bi-envelope me-1"></i>Email
                                        </label>
                                        <input type="email" class="form-control form-control-lg" id="loginEmail" name="email" required placeholder="tu@email.com">
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="loginPassword" class="form-label fw-semibold">
                                            <i class="bi bi-lock me-1"></i>Contraseña
                                        </label>
                                        <input type="password" class="form-control form-control-lg" id="loginPassword" name="password" required placeholder="••••••••">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-lg w-100 mb-3 submit-btn">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                                    </button>
                                    
                                    <div class="text-center">
                                        <a href="#" class="text-decoration-none forgot-password" onclick="showForgotPassword()">
                                            <i class="bi bi-question-circle me-1"></i>¿Olvidaste tu contraseña?
                                        </a>
                                    </div>
                                </form>
                                
                                <!-- Formulario de Registro -->
                                <form id="registerForm" class="register-form d-none">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="register">
                                    
                                    <div class="mb-3">
                                        <label for="registerNombre" class="form-label fw-semibold">
                                            <i class="bi bi-person me-1"></i>Nombre Completo
                                        </label>
                                        <input type="text" class="form-control form-control-lg" id="registerNombre" name="nombre" required placeholder="Tu nombre completo">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="registerEmail" class="form-label fw-semibold">
                                            <i class="bi bi-envelope me-1"></i>Email
                                        </label>
                                        <input type="email" class="form-control form-control-lg" id="registerEmail" name="email" required placeholder="tu@email.com">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="registerTelefono" class="form-label fw-semibold">
                                            <i class="bi bi-telephone me-1"></i>Teléfono
                                        </label>
                                        <input type="tel" class="form-control form-control-lg" id="registerTelefono" name="telefono" placeholder="(opcional)">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="registerPassword" class="form-label fw-semibold">
                                            <i class="bi bi-lock me-1"></i>Contraseña
                                        </label>
                                        <input type="password" class="form-control form-control-lg" id="registerPassword" name="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="confirmPassword" class="form-label fw-semibold">
                                            <i class="bi bi-lock-fill me-1"></i>Confirmar Contraseña
                                        </label>
                                        <input type="password" class="form-control form-control-lg" id="confirmPassword" required minlength="6" placeholder="Repite tu contraseña">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-lg w-100 submit-btn">
                                        <i class="bi bi-person-plus me-2"></i>Registrarse
                                    </button>
                                </form>
                                
                                <div class="loading text-center d-none" id="loading">
                                    <div class="spinner-border text-primary mb-3" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <p class="text-primary fw-semibold">Procesando...</p>
                                </div>
                                
                                <hr class="my-4">
                                
                                <div class="back-link text-center">
                                    <a href="../html/index.html" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-2"></i>Volver al inicio
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <script>
            // Variables globales
            const messageContainer = document.getElementById('messageContainer');
            const loading = document.getElementById('loading');
            
            // Cambiar entre pestañas
            function showTab(tab) {
                // Ocultar todos los formularios
                document.querySelectorAll('.login-form, .register-form').forEach(form => {
                    form.classList.add('d-none');
                });
                
                // Mostrar formulario seleccionado
                if (tab === 'login') {
                    document.getElementById('loginForm').classList.remove('d-none');
                } else {
                    document.getElementById('registerForm').classList.remove('d-none');
                }
                
                // Limpiar mensajes
                clearMessages();
            }
            
            // Manejar envío del formulario de login
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                submitForm('auth.php', formData);
            });
            
            // Manejar envío del formulario de registro
            document.getElementById('registerForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const password = document.getElementById('registerPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                
                if (password !== confirmPassword) {
                    showMessage('Las contraseñas no coinciden', 'error');
                    return;
                }
                
                const formData = new FormData(this);
                submitForm('auth.php', formData);
            });
            
            // Enviar formulario
            function submitForm(url, formData) {
                showLoading(true);
                clearMessages();
                
                fetch(url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showLoading(false);
                    
                    if (data.success) {
                        showMessage(data.message, 'success');
                        
                        // Redirigir después de un breve delay
                        setTimeout(() => {
                            if (data.role === 'admin') {
                                window.location.href = 'admin.php';
                            } else {
                                window.location.href = 'cliente.php';
                            }
                        }, 1500);
                    } else {
                        showMessage(data.message, 'error');
                    }
                })
                .catch(error => {
                    showLoading(false);
                    console.error('Error:', error);
                    showMessage('Error de conexión. Inténtalo de nuevo.', 'error');
                });
            }
            
            // Mostrar/ocultar loading
            function showLoading(show) {
                const forms = document.querySelectorAll('#loginForm, #registerForm');
                const buttons = document.querySelectorAll('.submit-btn');
                
                if (show) {
                    loading.classList.remove('d-none');
                    forms.forEach(form => form.classList.add('d-none'));
                    buttons.forEach(btn => btn.disabled = true);
                } else {
                    loading.classList.add('d-none');
                    // Mostrar el formulario activo
                    const activeTab = document.querySelector('input[name="loginTabs"]:checked').id;
                    showTab(activeTab === 'loginTab' ? 'login' : 'register');
                    buttons.forEach(btn => btn.disabled = false);
                }
            }
            
            // Mostrar mensaje
            function showMessage(message, type) {
                const alertType = type === 'error' ? 'danger' : 'success';
                const icon = type === 'error' ? 'exclamation-triangle-fill' : 'check-circle-fill';
                
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${alertType} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                    <i class="bi bi-${icon} me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                messageContainer.innerHTML = '';
                messageContainer.appendChild(alertDiv);
                
                // Auto-cerrar después de 5 segundos para mensajes de éxito
                if (type === 'success') {
                    setTimeout(() => {
                        const alert = bootstrap.Alert.getOrCreateInstance(alertDiv);
                        alert.close();
                    }, 5000);
                }
            }
            
            // Limpiar mensajes
            function clearMessages() {
                messageContainer.innerHTML = '';
            }
            
            // Mostrar formulario de recuperación de contraseña
            function showForgotPassword() {
                // Crear modal de Bootstrap para recuperación de contraseña
                const modalHtml = `
                    <div class="modal fade" id="forgotPasswordModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="bi bi-key me-2"></i>Recuperar Contraseña
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="forgotPasswordForm">
                                        <div class="mb-3">
                                            <label for="forgotEmail" class="form-label">Email:</label>
                                            <input type="email" class="form-control" id="forgotEmail" required placeholder="tu@email.com">
                                        </div>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-send me-2"></i>Enviar instrucciones
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Agregar modal al DOM si no existe
                if (!document.getElementById('forgotPasswordModal')) {
                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                    
                    // Manejar envío del formulario
                    document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const email = document.getElementById('forgotEmail').value;
                        const formData = new FormData();
                        formData.append('action', 'reset_password');
                        formData.append('email', email);
                        formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                        
                        fetch('auth.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Cerrar modal
                            const modal = bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal'));
                            modal.hide();
                            
                            // Mostrar mensaje
                            showMessage(data.message, data.success ? 'success' : 'error');
                        })
                        .catch(error => {
                            showMessage('Error de conexión. Inténtalo de nuevo.', 'error');
                        });
                    });
                }
                
                // Mostrar modal
                const modal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
                modal.show();
            }
            
            // Validación en tiempo real con Bootstrap
            function addBootstrapValidation() {
                const forms = document.querySelectorAll('form');
                forms.forEach(form => {
                    form.addEventListener('submit', function(e) {
                        if (!form.checkValidity()) {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    });
                });
            }
            
            // Validación personalizada para confirmación de contraseña
            document.getElementById('registerPassword').addEventListener('input', function() {
                const password = this.value;
                const confirmPassword = document.getElementById('confirmPassword');
                
                // Agregar clases de validación de Bootstrap
                if (password.length > 0 && password.length < 6) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else if (password.length >= 6) {
                    this.classList.add('is-valid');
                    this.classList.remove('is-invalid');
                }
                
                // Validar confirmación si ya tiene contenido
                if (confirmPassword.value.length > 0) {
                    if (password !== confirmPassword.value) {
                        confirmPassword.classList.add('is-invalid');
                        confirmPassword.classList.remove('is-valid');
                    } else {
                        confirmPassword.classList.add('is-valid');
                        confirmPassword.classList.remove('is-invalid');
                    }
                }
            });
            
            document.getElementById('confirmPassword').addEventListener('input', function() {
                const password = document.getElementById('registerPassword').value;
                const confirmPassword = this.value;
                
                if (confirmPassword.length > 0) {
                    if (password !== confirmPassword) {
                        this.classList.add('is-invalid');
                        this.classList.remove('is-valid');
                    } else {
                        this.classList.add('is-valid');
                        this.classList.remove('is-invalid');
                    }
                }
            });
            
            // Inicializar validación al cargar la página
            document.addEventListener('DOMContentLoaded', function() {
                addBootstrapValidation();
            });
        </script>
    </body>
</html>
