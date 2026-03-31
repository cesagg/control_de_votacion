<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['es_admin']) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$error = '';

// ===== CERRAR UNA SESIÓN ESPECÍFICA =====
if (isset($_GET['cerrar'])) {
    $sesion_id = $_GET['cerrar'];
    
    $sql = "DELETE FROM sesiones_activas WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $sesion_id);
    
    if ($stmt->execute()) {
        $mensaje = "✅ Sesión cerrada correctamente";
    } else {
        $error = "❌ Error al cerrar la sesión";
    }
}

// ===== CERRAR TODAS LAS SESIONES DE UN USUARIO =====
if (isset($_GET['cerrar_todas'])) {
    $user_id = $_GET['cerrar_todas'];
    
    $sql = "DELETE FROM sesiones_activas WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $mensaje = "✅ Todas las sesiones del usuario fueron cerradas";
    } else {
        $error = "❌ Error al cerrar las sesiones";
    }
}

// ===== OBTENER LISTA DE SESIONES ACTIVAS =====
$sesiones = $conn->query("
    SELECT 
        s.*,
        u.usuario,
        u.nombre_completo,
        u.es_admin
    FROM sesiones_activas s
    JOIN usuarios_sistema u ON s.user_id = u.id
    ORDER BY s.ultima_actividad DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Sesiones Activas</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🔐 Sesiones Activas en el Sistema</h2>
            <div>
                <a href="index.php" class="btn btn-primary">← Volver al Inicio</a>
                <a href="logout.php" class="btn btn-danger">Cerrar Mi Sesión</a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php
        // Estadísticas
        $stats = $conn->query("
            SELECT 
                COUNT(*) as total_sesiones,
                COUNT(DISTINCT user_id) as usuarios_activos,
                MAX(ultima_actividad) as ultima_actividad
            FROM sesiones_activas
        ")->fetch_assoc();
        ?>

        <div class="stats-grid">
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="numero"><?php echo $stats['total_sesiones']; ?></div>
                <div class="etiqueta">Sesiones Activas</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                <div class="numero"><?php echo $stats['usuarios_activos']; ?></div>
                <div class="etiqueta">Usuarios Conectados</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                <div class="numero">
                    <?php 
                    if ($stats['ultima_actividad']) {
                        $diff = time() - strtotime($stats['ultima_actividad']);
                        echo floor($diff/60) . ' min';
                    } else {
                        echo '-';
                    }
                    ?>
                </div>
                <div class="etiqueta">Última Actividad</div>
            </div>
        </div>

        <h3 style="margin: 20px 0;">📋 Lista de Sesiones Activas</h3>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>IP</th>
                        <th>Dispositivo</th>
                        <th>Inició</th>
                        <th>Última Actividad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sesiones->num_rows > 0): ?>
                        <?php while($sesion = $sesiones->fetch_assoc()): 
                            $ultima_actividad = strtotime($sesion['ultima_actividad']);
                            $ahora = time();
                            $diferencia = $ahora - $ultima_actividad;
                            $minutos_inactivo = floor($diferencia / 60);
                            
                            // Determinar color según inactividad
                            if ($minutos_inactivo < 5) {
                                $estado_color = 'badge-success';
                                $estado_texto = 'Activo';
                            } elseif ($minutos_inactivo < 15) {
                                $estado_color = 'badge-warning';
                                $estado_texto = 'Inactivo';
                            } else {
                                $estado_color = 'badge-danger';
                                $estado_texto = 'Muy inactivo';
                            }
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($sesion['usuario']); ?></strong></td>
                            <td><?php echo htmlspecialchars($sesion['nombre_completo']); ?></td>
                            <td>
                                <?php if($sesion['es_admin']): ?>
                                    <span class="badge badge-success">Admin</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Operador</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($sesion['ip_address'] ?: '-'); ?></td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <?php 
                                $ua = $sesion['user_agent'];
                                if (strpos($ua, 'Windows') !== false) echo '🪟 Windows';
                                elseif (strpos($ua, 'Mac') !== false) echo '🍎 Mac';
                                elseif (strpos($ua, 'Linux') !== false) echo '🐧 Linux';
                                elseif (strpos($ua, 'Android') !== false) echo '📱 Android';
                                elseif (strpos($ua, 'iPhone') !== false) echo '📱 iPhone';
                                else echo '💻 Desconocido';
                                ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($sesion['fecha_inicio'])); ?></td>
                            <td>
                                <?php echo date('H:i', strtotime($sesion['ultima_actividad'])); ?>
                                <div class="tiempo-inactivo"><?php echo $minutos_inactivo; ?> min inactivo</div>
                            </td>
                            <td>
                                <span class="badge <?php echo $estado_color; ?>"><?php echo $estado_texto; ?></span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?cerrar=<?php echo $sesion['id']; ?>" 
                                       class="btn btn-danger btn-small"
                                       onclick="return confirm('¿Cerrar esta sesión?')">
                                        Cerrar
                                    </a>
                                    <a href="?cerrar_todas=<?php echo $sesion['user_id']; ?>" 
                                       class="btn btn-warning btn-small"
                                       onclick="return confirm('¿Cerrar TODAS las sesiones de este usuario?')">
                                        Todas
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px;">
                                🔴 No hay sesiones activas en este momento
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Instrucciones -->
        <div style="background: #d1ecf1; padding: 20px; border-radius: 10px; margin-top: 30px;">
            <h4 style="color: #0c5460;">📌 ¿Cómo usar?</h4>
            <ul style="color: #0c5460; margin-left: 20px;">
                <li><strong>Si no sabes dónde iniciaron sesión</strong> - Puedes cerrar la sesión desde aquí</li>
                <li><strong>Estado "Muy inactivo"</strong> - Usuario cerró el navegador sin cerrar sesión</li>
                <li><strong>Cerrar sesión</strong> - El usuario será desconectado automáticamente</li>
            </ul>
        </div>
    </div>
</body>
</html>