<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$error = '';
$votante_encontrado = null;
$votante_ya_voto = null;

// ===== REINICIO DE VOTOS (SOLO ADMIN) =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reiniciar_votos']) && $_SESSION['es_admin']) {
    $password_admin = $_POST['password_admin'];
    
    if ($password_admin == 'admin123') {
        $sql = "UPDATE votantes SET ha_votado = FALSE, fecha_voto = NULL, registrado_por = NULL";
        if ($conn->query($sql)) {
            $mensaje = "✅ Todos los votos han sido reiniciados exitosamente";
        } else {
            $error = "Error al reiniciar los votos";
        }
    } else {
        $error = "❌ Contraseña de administrador incorrecta";
    }
}

// ===== REINICIAR VOTO DE UNA SOLA PERSONA (SOLO ADMIN) =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reiniciar_voto_unico']) && $_SESSION['es_admin']) {
    $votante_id = $_POST['votante_id'];
    
    $sql = "UPDATE votantes SET ha_votado = FALSE, fecha_voto = NULL, registrado_por = NULL WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $votante_id);
    
    if ($stmt->execute()) {
        $mensaje = "✅ Voto reiniciado para ese votante";
    } else {
        $error = "Error al reiniciar el voto";
    }
}

// Procesar búsqueda de votante
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buscar'])) {
    $cedula = trim($_POST['cedula']);
    
    if (empty($cedula)) {
        $error = "Por favor ingrese un número de cédula";
    } else {
        $sql = "SELECT v.*, u.nombre_completo as registrado_por_nombre 
                FROM votantes v 
                LEFT JOIN usuarios_sistema u ON v.registrado_por = u.id 
                WHERE v.cedula = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $cedula);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $votante = $result->fetch_assoc();
            
            if ($votante['ha_votado']) {
                $votante_ya_voto = $votante;
            } else {
                $votante_encontrado = $votante;
            }
        } else {
            $error = "❌ Cédula no encontrada en el padrón electoral";
        }
    }
}

// Procesar registro de voto (SOLO PARA OPERADORES)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_voto']) && !$_SESSION['es_admin']) {
    $votante_id = $_POST['votante_id'];
    $user_id = $_SESSION['user_id'];
    
    $check = $conn->prepare("SELECT ha_votado FROM votantes WHERE id = ?");
    $check->bind_param("i", $votante_id);
    $check->execute();
    $check->bind_result($ya_voto);
    $check->fetch();
    $check->close();
    
    if ($ya_voto) {
        $error = "Este votante ya fue registrado anteriormente";
    } else {
        // Hora de Argentina
        $fecha_hora_actual = date('Y-m-d H:i:s');
        
        $sql = "UPDATE votantes SET ha_votado = TRUE, fecha_voto = ?, registrado_por = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $fecha_hora_actual, $user_id, $votante_id);
        
        if ($stmt->execute()) {
            $mensaje = "✅ Voto registrado exitosamente";
            $votante_encontrado = null;
        } else {
            $error = "Error al registrar el voto";
        }
    }
}

// Obtener estadísticas (solo para admin)
if ($_SESSION['es_admin']) {
    $result = $conn->query("SELECT COUNT(*) as total FROM votantes");
    $stats['total'] = $result->fetch_assoc()['total'];
    
    $result = $conn->query("SELECT COUNT(*) as total FROM votantes WHERE ha_votado = TRUE");
    $stats['votaron'] = $result->fetch_assoc()['total'];
    
    $result = $conn->query("
        SELECT 
            u.id,
            u.nombre_completo,
            u.usuario,
            COUNT(v.id) as total_registrados,
            SUM(CASE WHEN DATE(v.fecha_voto) = CURDATE() THEN 1 ELSE 0 END) as registrados_hoy,
            MIN(DATE(v.fecha_voto)) as primer_dia,
            MAX(DATE(v.fecha_voto)) as ultimo_dia,
            COUNT(DISTINCT DATE(v.fecha_voto)) as dias_trabajados
        FROM usuarios_sistema u
        LEFT JOIN votantes v ON u.id = v.registrado_por
        WHERE u.es_admin = 0 OR (u.es_admin = 1 AND u.id != 1)
        GROUP BY u.id
        ORDER BY total_registrados DESC
    ");
    $stats_por_operador = $result->fetch_all(MYSQLI_ASSOC);
    
    $result = $conn->query("
        SELECT COUNT(*) as total_operadores,
               COUNT(DISTINCT registrado_por) as operadores_activos
        FROM votantes 
        WHERE registrado_por IS NOT NULL
    ");
    $total_stats = $result->fetch_assoc();
    
    $locales = $conn->query("SELECT DISTINCT local_votacion FROM votantes ORDER BY local_votacion")->fetch_all(MYSQLI_ASSOC);
    
    $operadores_filtro = $conn->query("
        SELECT id, nombre_completo 
        FROM usuarios_sistema 
        WHERE id IN (SELECT DISTINCT registrado_por FROM votantes WHERE registrado_por IS NOT NULL)
        ORDER BY nombre_completo
    ")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sistema de Control de Votantes</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🗳️ Sistema de Control Electoral</h2>
            <div class="user-info">
                <span class="badge">
                    👤 <?php echo htmlspecialchars($_SESSION['usuario']); ?> 
                    (<?php echo $_SESSION['es_admin'] ? 'Administrador' : 'Operador'; ?>)
                </span>

                <?php if($_SESSION['es_admin']): ?>
                    <a href="admin_usuarios.php" class="btn btn-primary btn-block-mobile">👥 Gestionar Usuarios</a>
                    <a href="sesiones_activas.php" class="btn btn-warning btn-block-mobile">🔐 Sesiones Activas</a>
                <?php endif; ?>
                <a href="logout.php" class="btn-logout btn-block-mobile">🚪 Cerrar Sesión</a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($_SESSION['es_admin']): ?>
            <div class="consulta-message">
                <strong>🔍 Modo Administrador:</strong> Puedes consultar, ver estadísticas y gestionar el sistema. 
                <span style="display: block; margin-top: 5px; font-size: 13px;">
                    📌 Los operadores son los únicos que pueden registrar votos.
                </span>
            </div>
        <?php endif; ?>

        <div class="search-box">
            <h3>🔍 Consultar Votante por Cédula</h3>
            <form method="POST" class="search-form">
                <input type="text" name="cedula" placeholder="Ej: 12345678" value="<?php echo isset($_POST['cedula']) ? htmlspecialchars($_POST['cedula']) : ''; ?>" required autofocus>
                <button type="submit" name="buscar" class="btn btn-primary">Buscar Votante</button>
            </form>
        </div>

        <?php if ($votante_encontrado): ?>
            <div class="votante-card">
                <h3>📋 Datos del Votante (No ha votado aún)</h3>
                <div class="votante-grid">
                    <div class="votante-item">
                        <strong>Cédula</strong>
                        <span><?php echo htmlspecialchars($votante_encontrado['cedula']); ?></span>
                    </div>
                    <div class="votante-item">
                        <strong>Nombre Completo</strong>
                        <span><?php echo htmlspecialchars($votante_encontrado['nombre'] . ' ' . $votante_encontrado['apellido']); ?></span>
                    </div>
                    <div class="votante-item">
                        <strong>Local de Votación</strong>
                        <span><?php echo htmlspecialchars($votante_encontrado['local_votacion']); ?></span>
                    </div>
                    <div class="votante-item">
                        <strong>Mesa</strong>
                        <span><?php echo htmlspecialchars($votante_encontrado['mesa']); ?></span>
                    </div>
                </div>
                
                <?php if (!$_SESSION['es_admin']): ?>
                    <form method="POST">
                        <input type="hidden" name="votante_id" value="<?php echo $votante_encontrado['id']; ?>">
                        <button type="submit" name="registrar_voto" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 18px;">
                            ✓ CONFIRMAR VOTO
                        </button>
                    </form>
                <?php else: ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; color: #6c757d;">
                        <strong>🔍 Modo Consulta:</strong> Los administradores no pueden registrar votos.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($votante_ya_voto): ?>
            <div class="votante-ya-voto-card">
                <h3>⚠️ Esta persona YA PASÓ por el PC</h3>
                <div class="votante-grid">
                    <div class="votante-item">
                        <strong>Cédula</strong>
                        <span><?php echo htmlspecialchars($votante_ya_voto['cedula']); ?></span>
                    </div>
                    <div class="votante-item">
                        <strong>Nombre Completo</strong>
                        <span><?php echo htmlspecialchars($votante_ya_voto['nombre'] . ' ' . $votante_ya_voto['apellido']); ?></span>
                    </div>
                    <div class="votante-item">
                        <strong>Local de Votación</strong>
                        <span><?php echo htmlspecialchars($votante_ya_voto['local_votacion']); ?></span>
                    </div>
                    <div class="votante-item">
                        <strong>Mesa</strong>
                        <span><?php echo htmlspecialchars($votante_ya_voto['mesa']); ?></span>
                    </div>
                    <div class="votante-item">
                        <strong>Fecha de Voto</strong>
                        <span><?php echo date('d/m/Y H:i', strtotime($votante_ya_voto['fecha_voto'])); ?></span>
                    </div>
                    <div class="votante-item">
                        <strong>Registrado por</strong>
                        <span><?php echo $votante_ya_voto['registrado_por_nombre'] ?: 'Desconocido'; ?></span>
                    </div>
                </div>
                
                <?php if ($_SESSION['es_admin']): ?>
                    <form method="POST" onsubmit="return confirm('¿Estás seguro de reiniciar el voto de esta persona?')">
                        <input type="hidden" name="votante_id" value="<?php echo $votante_ya_voto['id']; ?>">
                        <button type="submit" name="reiniciar_voto_unico" class="btn btn-warning" style="width: 100%; padding: 15px; font-size: 18px;">
                            🔄 REINICIAR VOTO DE ESTA PERSONA (Solo Admin)
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($_SESSION['es_admin']): ?>
            <div class="admin-actions">
                <h3>🔧 Acciones de Administrador</h3>
                <div>
                    <button onclick="mostrarModalReinicio()" class="btn btn-danger btn-block-mobile">
                        🔄 REINICIAR TODOS LOS REGISTROS
                    </button>
                </div>
            </div>

            <div id="modalReinicio" class="modal">
                <div class="modal-content">
                    <h3>⚠️ Confirmar Reinicio Total</h3>
                    <p style="margin: 15px 0;">Esta acción borrará TODOS los registros de votos.</p>
                    <form method="POST">
                        <input type="password" name="password_admin" placeholder="Contraseña de Administrador" required style="width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px;">
                        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                            <button type="submit" name="reiniciar_votos" class="btn btn-danger btn-block-mobile" style="flex: 1;">Sí, Reiniciar Todo</button>
                            <button type="button" onclick="cerrarModalReinicio()" class="btn btn-primary btn-block-mobile" style="flex: 1;">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="stats-container" style="background: white; padding: 25px; border-radius: 10px; margin-top: 20px;">
                <h3>📊 Estadísticas Generales</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="numero"><?php echo $stats['total']; ?></div>
                        <div class="etiqueta">Total Votantes</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                        <div class="numero"><?php echo $stats['votaron']; ?></div>
                        <div class="etiqueta">Ya Votaron</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                        <div class="numero"><?php echo $stats['total'] - $stats['votaron']; ?></div>
                        <div class="etiqueta">Faltan Votar</div>
                    </div>
                </div>

                <h4 class="section-title">👥 Personas Registradas por Operador</h4>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Operador</th>
                                <th>Usuario</th>
                                <th>Total Registrados</th>
                                <th>Registrados Hoy</th>
                                <th>Días Trabajados</th>
                                <th>Promedio por Día</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_general = 0;
                            foreach ($stats_por_operador as $operador): 
                                $total_general += $operador['total_registrados'];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($operador['nombre_completo']); ?></strong></td>
                                <td><?php echo htmlspecialchars($operador['usuario']); ?></td>
                                <td><span class="badge badge-primary"><?php echo $operador['total_registrados']; ?></span></td>
                                <td>
                                    <?php if ($operador['registrados_hoy'] > 0): ?>
                                        <span class="badge badge-success"><?php echo $operador['registrados_hoy']; ?> hoy</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $operador['dias_trabajados'] ?: 0; ?> días</td>
                                <td><?php echo $operador['dias_trabajados'] ? round($operador['total_registrados'] / $operador['dias_trabajados'], 1) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ===== FILTROS DE BÚSQUEDA CON BOTÓN IMPRIMIR ===== -->
                <h4 class="section-title">🔍 Buscar en Registros de Votos</h4>
                
                <div class="filter-box">
                    <div class="filter-group">
                        <label>Buscar por Cédula o Nombre</label>
                        <input type="text" id="buscar_voto" placeholder="Ej: 1234567 o ADRIANO">
                    </div>
                    
                    <div class="filter-group">
                        <label>Filtrar por Local</label>
                        <select id="filtro_local">
                            <option value="">Todos los locales</option>
                            <?php foreach ($locales as $local): ?>
                                <option value="<?php echo htmlspecialchars($local['local_votacion']); ?>">
                                    <?php echo htmlspecialchars($local['local_votacion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Filtrar por Operador</label>
                        <select id="filtro_operador">
                            <option value="">Todos los operadores</option>
                            <?php foreach ($operadores_filtro as $op): ?>
                                <option value="<?php echo $op['id']; ?>">
                                    <?php echo htmlspecialchars($op['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button onclick="buscarVotos()" class="btn btn-primary">🔍 Buscar</button>
                        <button onclick="limpiarFiltros()" class="btn btn-warning">🗑️ Limpiar</button>
                        <!-- 🖨️ BOTÓN IMPRIMIR -->
                        <button onclick="imprimirResultados()" class="btn btn-success" style="background: #17a2b8;">
                            🖨️ Imprimir resultados
                        </button>
                    </div>
                </div>

                <div id="loader" class="loader"></div>

                <!-- Resultados de búsqueda -->
                <div id="resultados">
                    <?php
                    $result_inicial = $conn->query("
                SELECT 
                v.cedula,
                v.nombre,
                v.apellido,
                v.local_votacion,
                v.mesa,
                DATE_FORMAT(v.fecha_voto, '%d/%m/%Y') as fecha,
                DATE_FORMAT(v.fecha_voto, '%H:%i') as hora,
                u.nombre_completo as operador
                FROM votantes v
                LEFT JOIN usuarios_sistema u ON v.registrado_por = u.id
                WHERE v.ha_votado = TRUE
                ORDER BY v.apellido ASC, v.nombre ASC
                LIMIT 20
     ");
                    $votos_iniciales = $result_inicial->fetch_all(MYSQLI_ASSOC);
                    ?>
                    
                    <div class="search-stats">
                        <span>Mostrando últimos 20 votos</span>
                        <span>Total: <?php echo count($votos_iniciales); ?> registros</span>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cédula</th>
                                    <th>Votante</th>
                                    <th>Local</th>
                                    <th>Mesa</th>
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th>Registrado por</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-resultados">
                                <?php foreach ($votos_iniciales as $voto): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($voto['cedula']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($voto['nombre'] . ' ' . $voto['apellido']); ?></td>
                                    <td><?php echo htmlspecialchars($voto['local_votacion']); ?></td>
                                    <td><?php echo htmlspecialchars($voto['mesa']); ?></td>
                                    <td><?php echo $voto['fecha']; ?></td>
                                    <td><?php echo $voto['hora']; ?></td>
                                    <td>
                                        <?php if ($voto['operador']): ?>
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($voto['operador']); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Sistema</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function mostrarModalReinicio() {
            document.getElementById('modalReinicio').style.display = 'block';
        }
        
        function cerrarModalReinicio() {
            document.getElementById('modalReinicio').style.display = 'none';
        }
        
        function buscarVotos() {
            document.getElementById('loader').style.display = 'block';
            
            var datos = new FormData();
            datos.append('buscar_voto', document.getElementById('buscar_voto').value);
            datos.append('filtro_local', document.getElementById('filtro_local').value);
            datos.append('filtro_operador', document.getElementById('filtro_operador').value);
            
            fetch('buscar_votos.php', {
                method: 'POST',
                body: datos
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('loader').style.display = 'none';
                document.getElementById('resultados').innerHTML = data;
            });
        }
        
        function limpiarFiltros() {
            document.getElementById('buscar_voto').value = '';
            document.getElementById('filtro_local').value = '';
            document.getElementById('filtro_operador').value = '';
            buscarVotos();
        }
        
    // 🖨️ FUNCIÓN PARA IMPRIMIR RESULTADOS
    function imprimirResultados() {
    var resultados = document.getElementById('resultados').innerHTML;
    var buscar = document.getElementById('buscar_voto').value;
    var localSelect = document.getElementById('filtro_local');
    var local = localSelect.options[localSelect.selectedIndex].text;
    var operadorSelect = document.getElementById('filtro_operador');
    var operador = operadorSelect.options[operadorSelect.selectedIndex].text;
    
    var ventana = window.open('', '_blank');
    
    var contenido = `
        <html>
        <head>
            <title>Reporte de Votos</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 30px; }
                h1 { color: #007bff; text-align: center; }
                h2 { color: #333; text-align: center; margin-bottom: 20px; }
                .filtros { 
                    background: #f8f9fa; 
                    padding: 15px; 
                    border-radius: 8px; 
                    margin: 20px 0;
                    border-left: 4px solid #007bff;
                }
                .botones {
                    text-align: right;
                    margin-bottom: 20px;
                }
                .btn-volver {
                    background: #6c757d;
                    color: white;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 14px;
                    text-decoration: none;
                    display: inline-block;
                }
                .btn-volver:hover {
                    background: #5a6268;
                }
                .btn-imprimir {
                    background: #28a745;
                    color: white;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 14px;
                    margin-left: 10px;
                }
                .btn-imprimir:hover {
                    background: #218838;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-top: 20px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                th { 
                    background: #007bff; 
                    color: white; 
                    padding: 12px; 
                    text-align: left;
                }
                td { 
                    padding: 10px; 
                    border-bottom: 1px solid #ddd;
                }
                .badge-primary { background: #007bff; color: white; padding: 3px 8px; border-radius: 12px; }
                .badge-warning { background: #ffc107; color: #333; padding: 3px 8px; border-radius: 12px; }
                .fecha { 
                    text-align: right; 
                    margin-top: 30px; 
                    color: #666;
                    font-size: 12px;
                }
                @media print {
                    .botones { display: none; }
                }
            </style>
        </head>
        <body>
            <h1>🗳️ SISTEMA DE CONTROL ELECTORAL</h1>
            <h2>Reporte de Votos Registrados</h2>
            
            <div class="botones">
                <button class="btn-volver" onclick="window.close()">← Volver</button>
                <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir</button>
            </div>
            
            <div class="filtros">
                <strong>Filtros aplicados:</strong><br>
                Búsqueda: ${buscar || 'Todos'}<br>
                Local: ${local}<br>
                Operador: ${operador}
            </div>
            
            ${resultados}
            
            <div class="fecha">
                Reporte generado el: ${new Date().toLocaleString('es-AR', { timeZone: 'America/Argentina/Buenos_Aires' })}
            </div>
        </body>
        </html>
    `;
    
    ventana.document.write(contenido);
    ventana.document.close();
}
        
        document.getElementById('buscar_voto').addEventListener('keyup', function() {
            if (this.value.length > 2 || this.value.length === 0) buscarVotos();
        });
        document.getElementById('filtro_local').addEventListener('change', buscarVotos);
        document.getElementById('filtro_operador').addEventListener('change', buscarVotos);
        
        window.onclick = function(event) {
            if (event.target == document.getElementById('modalReinicio')) {
                document.getElementById('modalReinicio').style.display = 'none';
            }
        }
    </script>
</body>
</html>