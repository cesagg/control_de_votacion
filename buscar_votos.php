<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    exit();
}

$buscar_voto = isset($_POST['buscar_voto']) ? trim($_POST['buscar_voto']) : '';
$filtro_local = isset($_POST['filtro_local']) ? $_POST['filtro_local'] : '';
$filtro_operador = isset($_POST['filtro_operador']) ? $_POST['filtro_operador'] : '';

// PAGINACIÓN - Número de página
$pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
$resultados_por_pagina = 50;
$offset = ($pagina - 1) * $resultados_por_pagina;

// Construir consulta para contar TOTAL de registros
$sql_count = "
    SELECT COUNT(*) as total
    FROM votantes v
    LEFT JOIN usuarios_sistema u ON v.registrado_por = u.id
    WHERE v.ha_votado = TRUE
";

$params = [];
$types = "";

if (!empty($buscar_voto)) {
    if (is_numeric($buscar_voto)) {
        $sql_count .= " AND v.cedula LIKE ?";
        $params[] = "$buscar_voto%";
        $types .= "s";
    } else {
        $sql_count .= " AND (v.nombre LIKE ? OR v.apellido LIKE ?)";
        $search_term = "$buscar_voto%";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }
}

if (!empty($filtro_local)) {
    $sql_count .= " AND v.local_votacion = ?";
    $params[] = $filtro_local;
    $types .= "s";
}

if (!empty($filtro_operador)) {
    $sql_count .= " AND v.registrado_por = ?";
    $params[] = $filtro_operador;
    $types .= "i";
}

$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_registros = $stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $resultados_por_pagina);

// Construir consulta para MOSTRAR resultados con PAGINACIÓN
$sql = "
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
";

// Reiniciamos los parámetros para la consulta de resultados
$params_result = [];
$types_result = "";

if (!empty($buscar_voto)) {
    if (is_numeric($buscar_voto)) {
        $sql .= " AND v.cedula LIKE ?";
        $params_result[] = "$buscar_voto%";
        $types_result .= "s";
    } else {
        $sql .= " AND (v.nombre LIKE ? OR v.apellido LIKE ?)";
        $search_term = "$buscar_voto%";
        $params_result[] = $search_term;
        $params_result[] = $search_term;
        $types_result .= "ss";
    }
}

if (!empty($filtro_local)) {
    $sql .= " AND v.local_votacion = ?";
    $params_result[] = $filtro_local;
    $types_result .= "s";
}

if (!empty($filtro_operador)) {
    $sql .= " AND v.registrado_por = ?";
    $params_result[] = $filtro_operador;
    $types_result .= "i";
}

// ORDEN ALFABÉTICO + PAGINACIÓN
$sql .= " ORDER BY v.apellido ASC, v.nombre ASC LIMIT ? OFFSET ?";
$params_result[] = $resultados_por_pagina;
$params_result[] = $offset;
$types_result .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params_result)) {
    $stmt->bind_param($types_result, ...$params_result);
}
$stmt->execute();
$result = $stmt->get_result();
$votos = $result->fetch_all(MYSQLI_ASSOC);
?>

<style>
.search-stats {
    display: flex; justify-content: space-between; align-items: center;
    margin: 10px 0; color: #6c757d; font-size: 14px; flex-wrap: wrap; gap: 10px;
}
.badge-primary { background: #007bff; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
.badge-warning { background: #ffc107; color: #333; padding: 3px 8px; border-radius: 12px; font-size: 12px; }

/* Estilos para la paginación */
.paginacion {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin: 30px 0 20px;
    flex-wrap: wrap;
}
.btn-pagina {
    background: white;
    color: #007bff;
    border: 2px solid #007bff;
    padding: 8px 12px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    min-width: 40px;
    transition: all 0.3s;
}
.btn-pagina:hover {
    background: #007bff;
    color: white;
}
.btn-pagina.activa {
    background: #007bff;
    color: white;
    border-color: #0056b3;
}
.btn-pagina:disabled {
    background: #e9ecef;
    color: #6c757d;
    border-color: #dee2e6;
    cursor: not-allowed;
}
</style>

<!-- Información de resultados -->
<div class="search-stats">
    <?php if (!empty($buscar_voto) || !empty($filtro_local) || !empty($filtro_operador)): ?>
        <span>Mostrando resultados para: 
            <?php if (!empty($buscar_voto)) echo "búsqueda '" . htmlspecialchars($buscar_voto) . "' "; ?>
            <?php if (!empty($filtro_local)) echo "- Local: " . htmlspecialchars($filtro_local) . " "; ?>
            <?php if (!empty($filtro_operador)) echo "- Operador filtrado"; ?>
        </span>
    <?php else: ?>
        <span>Mostrando <?php echo count($votos); ?> votos (orden alfabético)</span>
    <?php endif; ?>
    <span>Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?> | Total: <?php echo $total_registros; ?> registros</span>
</div>

<!-- Tabla de resultados -->
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
        <tbody>
            <?php if (count($votos) > 0): ?>
                <?php foreach ($votos as $voto): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($voto['cedula']); ?></strong></td>
                    <td><?php echo htmlspecialchars($voto['nombre'] . ' ' . $voto['apellido']); ?></td>
                    <td><?php echo htmlspecialchars($voto['local_votacion']); ?></td>
                    <td><?php echo htmlspecialchars($voto['mesa']); ?></td>
                    <td><?php echo $voto['fecha']; ?></td>
                    <td><?php echo $voto['hora']; ?></td>
                    <td>
                        <?php if ($voto['operador']): ?>
                            <span class="badge-primary"><?php echo htmlspecialchars($voto['operador']); ?></span>
                        <?php else: ?>
                            <span class="badge-warning">Sistema</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 30px; color: #6c757d;">
                        No se encontraron votos con los filtros seleccionados
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- PAGINACIÓN - BOTONES 1,2,3... -->
<?php if ($total_paginas > 1): ?>
<div class="paginacion">
    <!-- Botón Anterior -->
    <?php if ($pagina > 1): ?>
        <button class="btn-pagina" onclick="cargarPagina(<?php echo $pagina - 1; ?>)">← Anterior</button>
    <?php else: ?>
        <button class="btn-pagina" disabled>← Anterior</button>
    <?php endif; ?>
    
    <!-- Botones de números -->
    <?php
    $rango = 3; // Mostrar 3 números antes y después
    $inicio = max(1, $pagina - $rango);
    $fin = min($total_paginas, $pagina + $rango);
    
    for ($i = $inicio; $i <= $fin; $i++):
    ?>
        <button class="btn-pagina <?php echo $i == $pagina ? 'activa' : ''; ?>" 
                onclick="cargarPagina(<?php echo $i; ?>)">
            <?php echo $i; ?>
        </button>
    <?php endfor; ?>
    
    <!-- Botón Siguiente -->
    <?php if ($pagina < $total_paginas): ?>
        <button class="btn-pagina" onclick="cargarPagina(<?php echo $pagina + 1; ?>)">Siguiente →</button>
    <?php else: ?>
        <button class="btn-pagina" disabled>Siguiente →</button>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
function cargarPagina(pagina) {
    var buscar = document.getElementById('buscar_voto').value;
    var local = document.getElementById('filtro_local').value;
    var operador = document.getElementById('filtro_operador').value;
    
    // Mostrar loader
    if (document.getElementById('loader')) {
        document.getElementById('loader').style.display = 'block';
    }
    
    var datos = new FormData();
    datos.append('buscar_voto', buscar);
    datos.append('filtro_local', local);
    datos.append('filtro_operador', operador);
    datos.append('pagina', pagina);
    
    fetch('buscar_votos.php', {
        method: 'POST',
        body: datos
    })
    .then(response => response.text())
    .then(data => {
        if (document.getElementById('loader')) {
            document.getElementById('loader').style.display = 'none';
        }
        document.getElementById('resultados').innerHTML = data;
    })
    .catch(error => {
        console.error('Error:', error);
        if (document.getElementById('loader')) {
            document.getElementById('loader').style.display = 'none';
        }
    });
}
</script>