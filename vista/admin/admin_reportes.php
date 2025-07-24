<?php
// Iniciar sesión y verificar si es administrador
session_start();
if (!isset($_SESSION['es_admin']) || $_SESSION['es_admin'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Incluir archivo de conexión
require_once '../../configuracion/conexion.php';

// Procesamiento de parámetros para reportes
$reporteTipo = filter_input(INPUT_GET, 'reporte', FILTER_SANITIZE_STRING) ?? 'ventas';
$fechaInicio = filter_input(INPUT_GET, 'fecha_inicio', FILTER_SANITIZE_STRING) ?? date('Y-m-01');
$fechaFin = filter_input(INPUT_GET, 'fecha_fin', FILTER_SANITIZE_STRING) ?? date('Y-m-d');
$categoriaId = filter_input(INPUT_GET, 'categoria', FILTER_SANITIZE_STRING) ?? '';

// Obtener categorías para el selector
$categorias = [];
$sqlCategorias = "SELECT CategoriaID, NombreCategoria FROM Categorias";
$resultCategorias = $conn->query($sqlCategorias);
if ($resultCategorias->num_rows > 0) {
    while($row = $resultCategorias->fetch_assoc()) {
        $categorias[$row['CategoriaID']] = $row['NombreCategoria'];
    }
}

// Generar reporte según tipo seleccionado
$reporteData = [];
$reporteTitulo = '';

switch ($reporteTipo) {
    case 'ventas':
        $reporteTitulo = 'Reporte de Ventas';
        $sql = "SELECT DATE(FechaPedido) as Fecha, SUM(MontoTotal) as TotalVentas, COUNT(*) as CantidadPedidos
                FROM Pedidos
                WHERE DATE(FechaPedido) BETWEEN '$fechaInicio' AND '$fechaFin'
                GROUP BY DATE(FechaPedido)
                ORDER BY Fecha DESC";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $reporteData[] = $row;
            }
        }
        break;
        
    case 'productos':
        $reporteTitulo = 'Productos Más Vendidos';
        $sql = "SELECT p.NombreProducto, c.NombreCategoria, SUM(ap.Cantidad) as CantidadVendida, SUM(ap.Subtotal) as TotalVentas
                FROM ArticulosPedido ap
                JOIN Productos p ON ap.ProductoID = p.ProductoID
                JOIN Categorias c ON p.CategoriaID = c.CategoriaID
                JOIN Pedidos pd ON ap.PedidoID = pd.PedidoID
                WHERE pd.FechaPedido BETWEEN '$fechaInicio' AND '$fechaFin'
                GROUP BY p.ProductoID
                ORDER BY CantidadVendida DESC
                LIMIT 10";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $reporteData[] = $row;
            }
        }
        break;
        
    case 'categorias':
        $reporteTitulo = 'Ventas por Categoría';
        $categoriaCondicion = $categoriaId ? " AND p.CategoriaID = '$categoriaId'" : '';
        $sql = "SELECT c.NombreCategoria, SUM(ap.Subtotal) as TotalVentas, COUNT(DISTINCT pd.PedidoID) as Pedidos
                FROM ArticulosPedido ap
                JOIN Productos p ON ap.ProductoID = p.ProductoID
                JOIN Categorias c ON p.CategoriaID = c.CategoriaID
                JOIN Pedidos pd ON ap.PedidoID = pd.PedidoID
                WHERE pd.FechaPedido BETWEEN '$fechaInicio' AND '$fechaFin' $categoriaCondicion
                GROUP BY c.CategoriaID
                ORDER BY TotalVentas DESC";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $reporteData[] = $row;
            }
        }
        break;
        
    case 'clientes':
        $reporteTitulo = 'Clientes Más Valiosos';
        $sql = "SELECT u.Nombre, u.Apellido, u.Email, SUM(pd.MontoTotal) as TotalCompras, COUNT(pd.PedidoID) as Pedidos
                FROM Pedidos pd
                JOIN Usuarios u ON pd.UsuarioID = u.UsuarioID
                WHERE pd.FechaPedido BETWEEN '$fechaInicio' AND '$fechaFin'
                GROUP BY u.UsuarioID
                ORDER BY TotalCompras DESC
                LIMIT 10";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $reporteData[] = $row;
            }
        }
        break;
        
    case 'inventario':
        $reporteTitulo = 'Estado de Inventario';
        $sql = "SELECT p.NombreProducto, c.NombreCategoria, i.CantidadDisponible, i.CantidadReservada, i.CantidadMinima
                FROM Inventario i
                JOIN Productos p ON i.ProductoID = p.ProductoID
                JOIN Categorias c ON p.CategoriaID = c.CategoriaID
                ORDER BY (i.CantidadDisponible - i.CantidadMinima) ASC";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $reporteData[] = $row;
            }
        }
        break;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes | Aranzábal</title>
    <link rel="stylesheet" href="../../archivos_estaticos/css/admin_reportes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        
    </style>
</head>
<body>
    <div class="contenedor-admin">
        <aside class="sidebar-admin">
            <div class="logo-admin">
                <img src="../../archivos_estaticos/img/diamanteblanco.png" alt="Aranzábal">
                <h2>Aranzábal</h2>
                <p>Panel de Administración</p>
            </div>
            <nav class="menu-admin">
                <ul>
                    <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i>Resumen</a></li>
                    <li><a href="admin_producto.php"><i class="fas fa-box"></i>Productos</a></li>
                    <li><a href="admin-pedidos.php" ><i class="fas fa-shopping-cart"></i>Pedidos / Reservas</a></li>
                    <li><a href="admin-clientes.php"><i class="fas fa-users"></i>Clientes</a></li>
                    <li><a href="admin-inventario.php"><i class="fas fa-warehouse"></i>Inventario</a></li>
                    <li><a href="admin_reportes.php" class="activo"><i class="fas fa-chart-bar"></i>Reportes</a></li>
                </ul>
            </nav>
            <div class="cerrar-sesion-admin">
                <a href="../../controladores/cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i>Cerrar Sesión</a>
            </div>
        </aside>

        <main class="contenido-admin">
            <header class="cabecera-admin">
                <div>
                    <h1>Reportes de Administración</h1>
                </div>
                <div class="usuario-admin">
                    <div class="avatar-usuario">A</div>
                    <span>Administrador</span>
                </div>
            </header>

            <div class="contenido-principal-admin">
                <div class="panel-reportes">
                    <form method="GET" action="admin_reportes.php">
                        <div class="filtros-reporte">
                            <div class="filtro-grupo">
                                <label for="reporte">Tipo de Reporte</label>
                                <select id="reporte" name="reporte">
                                    <option value="ventas" <?= $reporteTipo == 'ventas' ? 'selected' : '' ?>>Ventas Diarias</option>
                                    <option value="productos" <?= $reporteTipo == 'productos' ? 'selected' : '' ?>>Productos Más Vendidos</option>
                                    <option value="categorias" <?= $reporteTipo == 'categorias' ? 'selected' : '' ?>>Ventas por Categoría</option>
                                    <option value="clientes" <?= $reporteTipo == 'clientes' ? 'selected' : '' ?>>Clientes Más Valiosos</option>
                                    <option value="inventario" <?= $reporteTipo == 'inventario' ? 'selected' : '' ?>>Estado de Inventario</option>
                                </select>
                            </div>
                            
                            <div class="filtro-grupo">
                                <label for="fecha_inicio">Fecha Inicio</label>
                                <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= $fechaInicio ?>">
                            </div>
                            
                            <div class="filtro-grupo">
                                <label for="fecha_fin">Fecha Fin</label>
                                <input type="date" id="fecha_fin" name="fecha_fin" value="<?= $fechaFin ?>">
                            </div>
                            
                            <?php if($reporteTipo == 'categorias'): ?>
                            <div class="filtro-grupo">
                                <label for="categoria">Categoría</label>
                                <select id="categoria" name="categoria">
                                    <option value="">Todas las categorías</option>
                                    <?php foreach($categorias as $id => $nombre): ?>
                                        <option value="<?= $id ?>" <?= $categoriaId == $id ? 'selected' : '' ?>><?= $nombre ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                                <input type="hidden" name="categoria" value="">
                            <?php endif; ?>
                            
                            <button type="submit" class="boton-generar">Generar Reporte</button>
                        </div>
                    </form>
                    
                    <div class="resultado-reporte">
                        <div class="resultado-titulo">
                            <h2><?= $reporteTitulo ?></h2>
                            <button class="boton-exportar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                                </svg>
                                Exportar a Excel
                            </button>
                        </div>
                        
                        <?php if(empty($reporteData)): ?>
                            <div class="sin-resultados">
                                <p>No se encontraron resultados con los filtros seleccionados.</p>
                            </div>
                        <?php else: ?>
                            <div class="tabla-contenedor">
                                <table class="tabla-reporte">
                                    <thead>
                                        <tr>
                                            <?php 
                                            // Encabezados dinámicos según tipo de reporte
                                            switch ($reporteTipo) {
                                                case 'ventas':
                                                    echo '<th>Fecha</th>
                                                          <th class="numero">Total Ventas</th>
                                                          <th class="numero">Pedidos</th>';
                                                    break;
                                                    
                                                case 'productos':
                                                    echo '<th>Producto</th>
                                                          <th>Categoría</th>
                                                          <th class="numero">Cantidad Vendida</th>
                                                          <th class="numero">Total Ventas</th>';
                                                    break;
                                                    
                                                case 'categorias':
                                                    echo '<th>Categoría</th>
                                                          <th class="numero">Total Ventas</th>
                                                          <th class="numero">Pedidos</th>';
                                                    break;
                                                    
                                                case 'clientes':
                                                    echo '<th>Cliente</th>
                                                          <th>Email</th>
                                                          <th class="numero">Total Compras</th>
                                                          <th class="numero">Pedidos</th>';
                                                    break;
                                                    
                                                case 'inventario':
                                                    echo '<th>Producto</th>
                                                          <th>Categoría</th>
                                                          <th class="numero">Disponible</th>
                                                          <th class="numero">Reservado</th>
                                                          <th class="numero">Mínimo</th>';
                                                    break;
                                            }
                                            ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($reporteData as $fila): ?>
                                            <tr>
                                                <?php 
                                                switch ($reporteTipo) {
                                                    case 'ventas':
                                                        echo "<td>{$fila['Fecha']}</td>
                                                              <td class='numero'>S/ " . number_format($fila['TotalVentas'], 2) . "</td>
                                                              <td class='numero'>{$fila['CantidadPedidos']}</td>";
                                                        break;
                                                        
                                                    case 'productos':
                                                        echo "<td>{$fila['NombreProducto']}</td>
                                                              <td>{$fila['NombreCategoria']}</td>
                                                              <td class='numero'>{$fila['CantidadVendida']}</td>
                                                              <td class='numero'>S/ " . number_format($fila['TotalVentas'], 2) . "</td>";
                                                        break;
                                                        
                                                    case 'categorias':
                                                        echo "<td>{$fila['NombreCategoria']}</td>
                                                              <td class='numero'>S/ " . number_format($fila['TotalVentas'], 2) . "</td>
                                                              <td class='numero'>{$fila['Pedidos']}</td>";
                                                        break;
                                                        
                                                    case 'clientes':
                                                        echo "<td>{$fila['Nombre']} {$fila['Apellido']}</td>
                                                              <td>{$fila['Email']}</td>
                                                              <td class='numero'>S/ " . number_format($fila['TotalCompras'], 2) . "</td>
                                                              <td class='numero'>{$fila['Pedidos']}</td>";
                                                        break;
                                                        
                                                    case 'inventario':
                                                        $claseBajoStock = $fila['CantidadDisponible'] < $fila['CantidadMinima'] ? 'bajo-stock' : '';
                                                        echo "<td>{$fila['NombreProducto']}</td>
                                                              <td>{$fila['NombreCategoria']}</td>
                                                              <td class='numero $claseBajoStock'>{$fila['CantidadDisponible']}</td>
                                                              <td class='numero'>{$fila['CantidadReservada']}</td>
                                                              <td class='numero'>{$fila['CantidadMinima']}</td>";
                                                        break;
                                                }
                                                ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const reporteSelect = document.getElementById('reporte');
        const categoriaGroup = document.querySelector('.filtro-grupo:has(#categoria)');
        const categoriaHidden = document.querySelector('input[name="categoria"]');
        
        // Función para manejar la visibilidad del filtro de categoría
        function toggleCategoriaFiltro() {
            if (reporteSelect.value === 'categorias') {
                if (categoriaGroup) categoriaGroup.style.display = 'flex';
                if (categoriaHidden) categoriaHidden.type = 'hidden';
            } else {
                if (categoriaGroup) categoriaGroup.style.display = 'none';
                if (categoriaHidden) {
                    categoriaHidden.type = 'text';
                    categoriaHidden.value = '';
                }
            }
        }
        
        // Inicializar visibilidad
        toggleCategoriaFiltro();
        
        // Escuchar cambios en el select
        reporteSelect.addEventListener('change', toggleCategoriaFiltro);
        
        // Establecer fechas por defecto si no están definidas
        const fechaFin = document.getElementById('fecha_fin');
        const fechaInicio = document.getElementById('fecha_inicio');
        
        if (fechaFin && !fechaFin.value) {
            fechaFin.valueAsDate = new Date();
        }
        
        if (fechaInicio && !fechaInicio.value) {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            fechaInicio.valueAsDate = firstDay;
        }
    });
    </script>
</body>
</html>