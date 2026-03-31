<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['es_admin']) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$error = '';

// ===== CREAR NUEVO USUARIO =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_usuario'])) {
    $usuario = trim($_POST['usuario']);
    $password = $_POST['password'];
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $nombre = trim($_POST['nombre_completo']);
    $local = !empty($_POST['local_asignado']) ? trim($_POST['local_asignado']) : NULL;
    $es_admin = isset($_POST['es_admin']) ? 1 : 0;
    
    // Verificar si el usuario ya existe
    $check = $conn->prepare("SELECT id FROM usuarios_sistema WHERE usuario = ?");
    $check->bind_param("s", $usuario);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        $error = "❌ El nombre de usuario ya existe";
    } else {
        $sql = "INSERT INTO usuarios_sistema (usuario, password, nombre_completo, local_asignado, es_admin) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $usuario, $password_hash, $nombre, $local, $es_admin);
        
        if ($stmt->execute()) {
            $mensaje = "✅ Usuario creado exitosamente";
        } else {
            $error = "❌ Error al crear usuario: " . $conn->error;
        }
    }
}

// ===== ELIMINAR USUARIO =====
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    // No permitir eliminar al admin principal (ID 1)
    if ($id == 1) {
        $error = "❌ No se puede eliminar al administrador principal";
    } else {
        // Verificar si el usuario tiene votos registrados
        $check = $conn->prepare("SELECT COUNT(*) as total FROM votantes WHERE registrado_por = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $result = $check->get_result();
        $votos = $result->fetch_assoc()['total'];
        
        if ($votos > 0) {
            $error = "❌ No se puede eliminar: El usuario tiene $votos votos registrados";
        } else {
            // Eliminar primero las sesiones activas del usuario
            $delete_sessions = $conn->prepare("DELETE FROM sesiones_activas WHERE user_id = ?");
            $delete_sessions->bind_param("i", $id);
            $delete_sessions->execute();
            
            // Luego eliminar el usuario
            $sql = "DELETE FROM usuarios_sistema WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $mensaje = "✅ Usuario eliminado exitosamente";
            } else {
                $error = "❌ Error al eliminar usuario: " . $conn->error;
            }
        }
    }
}

// ===== OBTENER LISTA DE USUARIOS =====
$usuarios = $conn->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM votantes WHERE registrado_por = u.id) as total_votos 
    FROM usuarios_sistema u 
    ORDER BY u.id
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Gestión de Usuarios</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>👥 Gestión de Usuarios del Sistema</h2>
            <div>
                <a href="index.php" class="btn btn-primary">← Volver al Inicio</a>
                <a href="logout.php" class="btn btn-danger">Cerrar Sesión</a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- FORMULARIO PARA CREAR USUARIO -->
        <div class="form-container">
            <h3 style="margin-bottom: 20px;">➕ Crear Nuevo Usuario</h3>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Usuario</label>
                        <input type="text" name="usuario" required placeholder="Ej: juan.perez">
                    </div>
                    
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="text" name="password" required placeholder="Ej: clave123">
                    </div>
                    
                    <div class="form-group">
                        <label>Nombre Completo</label>
                        <input type="text" name="nombre_completo" required placeholder="Ej: Juan Pérez">
                    </div>
                    
                    <div class="form-group">
                        <label>Local Asignado</label>
                        <select name="local_asignado">
                            <option value="">-- Todos los locales --</option>
                            <option value="Escuela Emilio Gómez Zelada">Escuela Emilio Gómez Zelada</option>
                            <option value="Escuela de Navidad">Escuela de Navidad</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="es_admin" id="es_admin">
                    <label for="es_admin">¿Es Administrador?</label>
                </div>
                
                <button type="submit" name="crear_usuario" class="btn btn-success">Crear Usuario</button>
            </form>
        </div>

        <!-- LISTA DE USUARIOS -->
        <h3 style="margin: 30px 0 15px;">📋 Usuarios Registrados</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombre Completo</th>
                        <th>Local Asignado</th>
                        <th>Rol</th>
                        <th>Votos Registrados</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = $usuarios->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?php echo $user['id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($user['usuario']); ?></td>
                        <td><?php echo htmlspecialchars($user['nombre_completo']); ?></td>
                        <td>
                            <?php if($user['local_asignado']): ?>
                                <?php echo htmlspecialchars($user['local_asignado']); ?>
                            <?php else: ?>
                                <span class="badge badge-primary">Todos</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($user['es_admin']): ?>
                                <span class="badge badge-success">Administrador</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Operador</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($user['total_votos'] > 0): ?>
                                <span class="badge badge-primary"><?php echo $user['total_votos']; ?> votos</span>
                            <?php else: ?>
                                <span class="badge">0 votos</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($user['fecha_registro'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <?php if($user['id'] != 1): // No permitir eliminar al admin principal ?>
                                    <a href="?eliminar=<?php echo $user['id']; ?>" 
                                       class="btn btn-danger btn-small"
                                       onclick="return confirm('¿Estás seguro de eliminar a <?php echo htmlspecialchars($user['nombre_completo']); ?>?\n\nNOTA: Solo se pueden eliminar usuarios sin votos registrados.')">
                                        Eliminar
                                    </a>
                                <?php else: ?>
                                    <span class="badge badge-success" style="background: #6c757d;">Principal</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- ESTADÍSTICAS RÁPIDAS -->
        <?php
        $stats = $conn->query("
            SELECT 
                COUNT(*) as total_usuarios,
                SUM(es_admin) as total_admins,
                (SELECT COUNT(*) FROM usuarios_sistema WHERE es_admin = 0) as total_operadores
            FROM usuarios_sistema
        ")->fetch_assoc();
        ?>
        
        <div class="stats-grid">
            <div class="stat-card" style="background: #e3f2fd; color: #007bff;">
                <div class="numero"><?php echo $stats['total_usuarios']; ?></div>
                <div class="etiqueta">Total Usuarios</div>
            </div>
            <div class="stat-card" style="background: #e8f5e8; color: #28a745;">
                <div class="numero"><?php echo $stats['total_admins']; ?></div>
                <div class="etiqueta">Administradores</div>
            </div>
            <div class="stat-card" style="background: #fff3cd; color: #ffc107;">
                <div class="numero"><?php echo $stats['total_operadores']; ?></div>
                <div class="etiqueta">Operadores</div>
            </div>
        </div>

        <!-- INSTRUCCIONES -->
        <div style="background: #d1ecf1; padding: 20px; border-radius: 10px; margin-top: 30px;">
            <h4 style="color: #0c5460; margin-bottom: 10px;">📌 Instrucciones:</h4>
            <ul style="color: #0c5460; margin-left: 20px;">
                <li><strong>Para eliminar un usuario:</strong> No debe tener votos registrados</li>
                <li><strong>Administradores:</strong> Tienen acceso a todas las funciones</li>
                <li><strong>Operadores:</strong> Solo pueden buscar y registrar votos</li>
                <li><strong>El administrador principal (ID 1)</strong> no se puede eliminar</li>
            </ul>
        </div>
    </div>
</body>
</html>