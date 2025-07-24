<?php
session_start();
if (!isset($_SESSION['es_admin']) || $_SESSION['es_admin'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Incluir archivo de conexión
require_once '../../configuracion/conexion.php';

// Consulta mejorada para obtener los últimos 6 meses completos
$sqlVentasMensuales = "SELECT 
    DATE_FORMAT(FechaPedido, '%Y-%m') AS Mes,
    DATE_FORMAT(FechaPedido, '%b %Y') AS MesFormateado,
    SUM(MontoTotal) AS TotalVentas
FROM Pedidos
WHERE 
    EstadoPedido != 'Cancelado' AND
    FechaPedido >= DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH), '%Y-%m-01') AND
    FechaPedido < DATE_FORMAT(DATE_ADD(CURRENT_DATE, INTERVAL 1 MONTH), '%Y-%m-01')
GROUP BY Mes, MesFormateado
ORDER BY Mes ASC";  // Orden ascendente para mostrar de más antiguo a más reciente

$resultVentasMensuales = $conn->query($sqlVentasMensuales);
$meses = [];
$ventasMensuales = [];
$mesesFormateados = [];

// Rellenar con ceros los últimos 6 meses
$fechasEsperadas = [];
for ($i = 5; $i >= 0; $i--) {
    $fecha = date('Y-m', strtotime("-$i months"));
    $fechasEsperadas[$fecha] = [
        'formateado' => date('M Y', strtotime("-$i months")),
        'ventas' => 0
    ];
}

if ($resultVentasMensuales->num_rows > 0) {
    while ($row = $resultVentasMensuales->fetch_assoc()) {
        if (array_key_exists($row['Mes'], $fechasEsperadas)) {
            $fechasEsperadas[$row['Mes']]['ventas'] = $row['TotalVentas'];
        }
    }
}

// Preparar datos para el gráfico
foreach ($fechasEsperadas as $mes => $datos) {
    $meses[] = $mes;
    $mesesFormateados[] = $datos['formateado'];
    $ventasMensuales[] = $datos['ventas'];
}

// Consulta para obtener productos más vendidos
$sqlProductosVendidos = "SELECT 
    p.NombreProducto,
    SUM(ap.Cantidad) AS TotalVendido,
    SUM(ap.Subtotal) AS TotalIngresos
FROM ArticulosPedido ap
JOIN Productos p ON ap.ProductoID = p.ProductoID
GROUP BY p.ProductoID, p.NombreProducto
ORDER BY TotalVendido DESC
LIMIT 10";

$resultProductosVendidos = $conn->query($sqlProductosVendidos);
$productos = [];
$cantidades = [];
$ingresos = [];

if ($resultProductosVendidos->num_rows > 0) {
    while ($row = $resultProductosVendidos->fetch_assoc()) {
        // Limitar el nombre del producto para que no sea demasiado largo
        $nombreCorto = (strlen($row['NombreProducto'])) > 30 ? 
                      substr($row['NombreProducto'], 0, 30).'...' : 
                      $row['NombreProducto'];
        $productos[] = $nombreCorto;
        $cantidades[] = $row['TotalVendido'];
        $ingresos[] = $row['TotalIngresos'];
    }
}


// Obtener estadísticas
$ventasHoy = 0;
$pedidosHoy = 0;
$totalProductos = 0;
$nuevosClientes = 0;

// Consulta para ventas de hoy
$hoy = date('Y-m-d');
$sqlVentas = "SELECT SUM(MontoTotal) AS total FROM Pedidos WHERE DATE(FechaPedido) = '$hoy'";
$resultVentas = $conn->query($sqlVentas);
if ($row = $resultVentas->fetch_assoc()) {
    $ventasHoy = $row['total'] ?? 0;
}

// Consulta para pedidos de hoy
$sqlPedidos = "SELECT COUNT(*) AS total FROM Pedidos WHERE DATE(FechaPedido) = '$hoy'";
$resultPedidos = $conn->query($sqlPedidos);
if ($row = $resultPedidos->fetch_assoc()) {
    $pedidosHoy = $row['total'] ?? 0;
}

// Consulta para total de productos
$sqlProductos = "SELECT COUNT(*) AS total FROM Productos WHERE EstaActivo = 1";
$resultProductos = $conn->query($sqlProductos);
if ($row = $resultProductos->fetch_assoc()) {
    $totalProductos = $row['total'] ?? 0;
}

// Consulta para nuevos clientes hoy
$sqlClientes = "SELECT COUNT(*) AS total FROM Usuarios WHERE DATE(FechaRegistro) = '$hoy'";
$resultClientes = $conn->query($sqlClientes);
if ($row = $resultClientes->fetch_assoc()) {
    $nuevosClientes = $row['total'] ?? 0;
}

// Consulta para los últimos pedidos
$sqlUltimosPedidos = "SELECT p.PedidoID, u.Nombre, u.Apellido, p.FechaPedido, p.MontoTotal, p.EstadoPedido 
                      FROM Pedidos p
                      JOIN Usuarios u ON p.UsuarioID = u.UsuarioID
                      ORDER BY p.FechaPedido DESC
                      LIMIT 5";
$resultUltimosPedidos = $conn->query($sqlUltimosPedidos);

// Agregar esto al inicio del archivo, después de las consultas existentes
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['obtener_pedido'])) {
    $pedidoID = $_GET['pedido_id'];
    
    // Obtener información del pedido
    $sqlPedido = "SELECT p.*, u.Nombre, u.Apellido, u.Email, u.Telefono, u.Direccion, u.Ciudad, u.Departamento, u.CodigoPostal 
                 FROM Pedidos p
                 JOIN Usuarios u ON p.UsuarioID = u.UsuarioID
                 WHERE p.PedidoID = ?";
    $stmtPedido = $conn->prepare($sqlPedido);
    $stmtPedido->bind_param("i", $pedidoID);
    $stmtPedido->execute();
    $resultPedido = $stmtPedido->get_result();
    $pedido = $resultPedido->fetch_assoc();
    
    // Obtener productos del pedido
    $sqlProductos = "SELECT ap.*, pr.NombreProducto, pr.UrlImagen 
                    FROM ArticulosPedido ap
                    JOIN Productos pr ON ap.ProductoID = pr.ProductoID
                    WHERE ap.PedidoID = ?";
    $stmtProductos = $conn->prepare($sqlProductos);
    $stmtProductos->bind_param("i", $pedidoID);
    $stmtProductos->execute();
    $resultProductos = $stmtProductos->get_result();
    $productos = [];
    while ($producto = $resultProductos->fetch_assoc()) {
        $productos[] = $producto;
    }
    
    // Devolver los datos en formato JSON
    header('Content-Type: application/json');
    echo json_encode([
        'pedido' => $pedido,
        'productos' => $productos
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="300">
    <title>Panel de Administración | Aranzábal</title>
    <link rel="stylesheet" href="../../archivos_estaticos/css/administracion.css">
    <link rel="stylesheet" href="../../archivos_estaticos/css/responsivo_admin.css">
    <link rel="stylesheet" href="../../archivos_estaticos/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        
    </style>
</head>

<body>
    <div class="contenedor-admin">
        <aside class="sidebar-admin">
            <div class="logo-admin">
                <img src="../../archivos_estaticos/img/diamanteblanco.png" alt="ARANZABAL">
                <h2>Aranzábal</h2>
                <p>Panel de Administración</p>
            </div>
            <nav class="menu-admin">
                <ul>
                    <li><a href="admin.php" class="activo"><i class="fas fa-tachometer-alt"></i>Resumen</a></li>
                    <li><a href="admin_producto.php"><i class="fas fa-box"></i>Productos</a></li>
                    <li><a href="admin-pedidos.php" ><i class="fas fa-shopping-cart"></i>Pedidos / Reservas</a></li>
                    <li><a href="admin-clientes.php"><i class="fas fa-users"></i>Clientes</a></li>
                    <li><a href="admin-inventario.php"><i class="fas fa-warehouse"></i>Inventario</a></li>
                    <li><a href="admin_reportes.php"><i class="fas fa-chart-bar"></i>Reportes</a></li>
                </ul>
            </nav>
            <div class="cerrar-sesion-admin">
                <a href="../../controladores/cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i>Cerrar Sesión</a>
            </div>
        </aside>

        <main class="contenido-admin">
            <header class="cabecera-admin">
                
                <div class="usuario-admin">
                    <div class="avatar-usuario">A</div>
                    <span>Administrador</span>
                </div>
            </header>

            <div class="contenido-principal-admin">
                <h1>Resumen</h1>

                <div class="resumen-estadisticas">
                    <div class="tarjeta-estadistica">
                        <div class="icono-estadistica ventas">
                            Ve
                        </div>
                        <div class="info-estadistica">
                            <h3>Ventas Hoy</h3>
                            <p class="valor">S/ <?php echo number_format($ventasHoy, 2); ?></p>
                            <p class="variacion positivo">+12%</p>
                        </div>
                    </div>

                    <div class="tarjeta-estadistica">
                        <div class="icono-estadistica pedidos">
                            Pe
                        </div>
                        <div class="info-estadistica">
                            <h3>Pedidos Hoy</h3>
                            <p class="valor"><?php echo $pedidosHoy; ?></p>
                            <p class="variacion positivo">+5%</p>
                        </div>
                    </div>

                    <div class="tarjeta-estadistica">
                        <div class="icono-estadistica productos">
                            Pr
                        </div>
                        <div class="info-estadistica">
                            <h3>Total Productos</h3>
                            <p class="valor"><?php echo $totalProductos; ?></p>
                            <p class="variacion neutro">0%</p>
                        </div>
                    </div>

                    <div class="tarjeta-estadistica">
                        <div class="icono-estadistica clientes">
                            Cl
                        </div>
                        <div class="info-estadistica">
                            <h3>Nuevos Clientes</h3>
                            <p class="valor"><?php echo $nuevosClientes; ?></p>
                            <p class="variacion negativo">-2%</p>
                        </div>
                    </div>
                </div>

                <div class="graficos-admin">
                    <div class="grafico-ventas">
                        <h2>Ventas Mensuales</h2>
                        <div class="contenedor-grafico">
                            <canvas id="graficoVentas"></canvas>
                        </div>
                    </div>

                    <div class="grafico-productos">
                        <h2>Productos Más Vendidos</h2>
                        <div class="selector-grafico">
                            <button id="btnCantidad" class="active">Por Cantidad</button>
                            <button id="btnIngresos">Por Ingresos</button>
                        </div>
                        <div class="contenedor-grafico">
                            <canvas id="graficoProductos"></canvas>
                        </div>
                    </div>
                </div>

                <div class="ultimos-pedidos">
                    <h2>Últimos Pedidos</h2>
                    <table class="tabla-pedidos">
                        <thead>
                            <tr>
                                <th>ID Pedido</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($resultUltimosPedidos->num_rows > 0) {
                                while ($pedido = $resultUltimosPedidos->fetch_assoc()) {
                                    $nombreCliente = $pedido['Nombre'] . ' ' . $pedido['Apellido'];
                                    $fecha = date('d/m/Y', strtotime($pedido['FechaPedido']));
                                    $total = number_format($pedido['MontoTotal'], 2);
                                    $estado = $pedido['EstadoPedido'];
                                    
                                    // Clase CSS según el estado
                                    $claseEstado = '';
                                    if ($estado == 'Enviado') {
                                        $claseEstado = 'enviado';
                                    } elseif ($estado == 'Procesando') {
                                        $claseEstado = 'procesando';
                                    } elseif ($estado == 'Entregado') {
                                        $claseEstado = 'entregado';
                                    } elseif ($estado == 'Cancelado') {
                                        $claseEstado = 'cancelado';
                                    }
                                    
                                    echo "<tr>
                                        <td>#{$pedido['PedidoID']}</td>
                                        <td>{$nombreCliente}</td>
                                        <td>{$fecha}</td>
                                        <td>S/ {$total}</td>
                                        <td><span class=\"estado {$claseEstado}\">{$estado}</span></td>
                                        <td>
                                            <button class=\"btn-accion btn-ver\" onclick=\"verPedido({$pedido['PedidoID']})\">
                                                <i class=\"fas fa-eye\"></i> Ver
                                            </button>
                                        </td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan=\"6\">No hay pedidos recientes</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

<!-- Modal para ver detalles del pedido -->
<div class="modal" id="modalPedido">
    <div class="modal-contenido">
        <div class="modal-cabecera">
            <h2 id="modalTitulo">Detalles del Pedido</h2>
            <button class="cerrar-modal" onclick="cerrarModal()">&times;</button>
        </div>
        <div class="modal-cuerpo" id="modalContenido">
            <!-- El contenido se cargará dinámicamente aquí -->
        </div>
    </div>
</div>

<!-- Formulario para venta directa (oculto inicialmente) -->
<div id="ventaDirectaForm" style="display: none; margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
    <h3>Registrar Venta Directa</h3>
    <form method="post" onsubmit="return confirm('¿Registrar esta venta directa?')">
        <input type="hidden" name="pedido_id" id="ventaPedidoId">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label for="metodo_pago">Método de Pago:</label>
                <select name="metodo_pago" id="metodo_pago" required style="width: 100%; padding: 8px;">
                    <option value="Efectivo">Efectivo</option>
                    <option value="Transferencia">Transferencia</option>
                    <option value="Pago Móvil">Pago Móvil</option>
                    <option value="Tarjeta de Débito">Tarjeta de Débito</option>
                    <option value="Tarjeta de Crédito">Tarjeta de Crédito</option>
                </select>
            </div>
            <div>
                <label for="referencia_pago">Referencia (opcional):</label>
                <input type="text" name="referencia_pago" id="referencia_pago" style="width: 100%; padding: 8px;">
            </div>
        </div>
        <button type="submit" name="registrar_venta_directa" class="btn-accion btn-registrar" style="width: 100%;">
            <i class="fas fa-cash-register"></i> Registrar Venta Directa
        </button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../archivos_estaticos/js/admin.js"></script>
    <script src="../../archivos_estaticos/js/admin_II.js"></script>

    <script>
    // Gráfico de ventas mensuales (últimos 6 meses)
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('graficoVentas').getContext('2d');
        
        // Datos desde PHP
        const meses = <?php echo json_encode($mesesFormateados); ?>;
        const ventas = <?php echo json_encode($ventasMensuales); ?>;
        
        const graficoVentas = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: meses,
                datasets: [{
                    label: 'Ventas Mensuales (S/)',
                    data: ventas,
                    backgroundColor: meses.map((mes, index) => 
                        index === meses.length - 1 ? 'rgba(75, 192, 192, 0.7)' : 'rgba(54, 162, 235, 0.7)'
                    ),
                    borderColor: meses.map((mes, index) => 
                        index === meses.length - 1 ? 'rgba(75, 192, 192, 1)' : 'rgba(54, 162, 235, 1)'
                    ),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Monto en Soles (S/)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Mes'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'S/ ' + context.raw.toFixed(2);
                            },
                            title: function(context) {
                                return 'Ventas ' + context[0].label;
                            }
                        }
                    }
                }
            }
        });
    });

    // Gráfico de productos más vendidos
    document.addEventListener('DOMContentLoaded', function() {
        const ctxProductos = document.getElementById('graficoProductos').getContext('2d');
        
        // Datos desde PHP
        const productos = <?php echo json_encode($productos); ?>;
        const cantidades = <?php echo json_encode($cantidades); ?>;
        const ingresos = <?php echo json_encode($ingresos); ?>;
        
        let chartProductos = new Chart(ctxProductos, {
            type: 'bar',
            data: {
                labels: productos,
                datasets: [{
                    label: 'Cantidad Vendida',
                    data: cantidades,
                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y', // Barras horizontales
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Unidades Vendidas'
                        }
                    },
                    y: {
                        ticks: {
                            autoSkip: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw;
                            }
                        }
                    }
                }
            }
        });
        
        // Alternar entre cantidad e ingresos
        document.getElementById('btnCantidad').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('btnIngresos').classList.remove('active');
            
            chartProductos.data.datasets[0].label = 'Cantidad Vendida';
            chartProductos.data.datasets[0].data = cantidades;
            chartProductos.options.scales.x.title.text = 'Unidades Vendidas';
            chartProductos.update();
        });
        
        document.getElementById('btnIngresos').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('btnCantidad').classList.remove('active');
            
            chartProductos.data.datasets[0].label = 'Ingresos Generados (S/)';
            chartProductos.data.datasets[0].data = ingresos;
            chartProductos.options.scales.x.title.text = 'Monto en Soles (S/)';
            chartProductos.update();
        });
    });

    // Función para abrir modal con detalles del pedido
function verPedido(pedidoId) {
    // Mostrar cargando mientras se obtienen los datos
    document.getElementById('modalContenido').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Cargando detalles del pedido...</div>';
    document.getElementById('modalTitulo').textContent = `Pedido #${pedidoId}`;
    document.getElementById('modalPedido').style.display = 'flex';
    
    // Obtener datos del pedido via AJAX
    fetch(`admin.php?obtener_pedido=1&pedido_id=${pedidoId}`)
        .then(response => response.json())
        .then(data => {
            const pedido = data.pedido;
            const productos = data.productos;
            
            // Construir el contenido HTML con los datos reales
            let contenido = `
                <div class="info-pedido">
                    <div class="info-item">
                        <strong>Cliente</strong>
                        <div>${pedido.Nombre} ${pedido.Apellido}</div>
                    </div>
                    <div class="info-item">
                        <strong>Email</strong>
                        <div>${pedido.Email}</div>
                    </div>
                    <div class="info-item">
                        <strong>Teléfono</strong>
                        <div>${pedido.Telefono}</div>
                    </div>
                    <div class="info-item">
                        <strong>Dirección de Envío</strong>
                        <div>${pedido.DireccionEnvio || pedido.Direccion}, ${pedido.CiudadEnvio || pedido.Ciudad}, ${pedido.DepartamentoEnvio || pedido.Departamento}</div>
                    </div>
                    <div class="info-item">
                        <strong>Fecha del Pedido</strong>
                        <div>${new Date(pedido.FechaPedido).toLocaleDateString()}</div>
                    </div>
                    <div class="info-item">
                        <strong>Total</strong>
                        <div>S/ ${parseFloat(pedido.MontoTotal).toFixed(2)}</div>
                    </div>
                    <div class="info-item">
                        <strong>Estado</strong>
                        <div><span class="estado ${pedido.EstadoPedido.toLowerCase()}">${pedido.EstadoPedido}</span></div>
                    </div>
                </div>
                
                <h3>Productos</h3>
                <table class="tabla-productos">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Precio Unitario</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>`;

            productos.forEach(producto => {
                // Usar imagen del producto o una imagen por defecto si no hay
                const imagen = producto.UrlImagen ? 
                    `<img src="${producto.UrlImagen}" alt="${producto.NombreProducto}" style="width:50px;height:50px;object-fit:cover;">` : 
                    `<div style="width:50px;height:50px;background:#eee;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-box" style="color:#999;"></i>
                    </div>`;
                
                contenido += `
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                ${imagen}
                                <span>${producto.NombreProducto}</span>
                            </div>
                        </td>
                        <td>S/ ${parseFloat(producto.PrecioUnitario).toFixed(2)}</td>
                        <td>${producto.Cantidad}</td>
                        <td>S/ ${parseFloat(producto.Subtotal).toFixed(2)}</td>
                    </tr>`;
            });

            contenido += `
                    </tbody>
                </table>
                
                <div class="acciones-modal">
                    <form class="form-cambiar-estado" method="post" onsubmit="return confirm('¿Estás seguro de cambiar el estado de este pedido?')">
                        <input type="hidden" name="pedido_id" value="${pedido.PedidoID}">
                        <select name="nuevo_estado">
                            <option value="Pendiente" ${pedido.EstadoPedido === 'Pendiente' ? 'selected' : ''}>Pendiente</option>
                            <option value="Procesando" ${pedido.EstadoPedido === 'Procesando' ? 'selected' : ''}>Procesando</option>
                            <option value="Enviado" ${pedido.EstadoPedido === 'Enviado' ? 'selected' : ''}>Enviado</option>
                            <option value="Entregado" ${pedido.EstadoPedido === 'Entregado' ? 'selected' : ''}>Entregado</option>
                            <option value="Cancelado" ${pedido.EstadoPedido === 'Cancelado' ? 'selected' : ''}>Cancelado</option>
                        </select>
                        <button type="submit" name="cambiar_estado" class="btn-accion btn-editar">
                            <i class="fas fa-sync-alt"></i> Cambiar Estado
                        </button>
                    </form>
                    
                    <form method="post" onsubmit="return confirm('¿Registrar salida de inventario para este pedido? Esta acción disminuirá el stock de los productos.')">
                        <input type="hidden" name="pedido_id" value="${pedido.PedidoID}">
                        <button type="submit" name="registrar_salida" class="btn-accion btn-registrar">
                            <i class="fas fa-check-circle"></i> Registrar Salida
                        </button>
                    </form>
                </div>`;
                
                // Mostrar opción de venta directa solo si el pedido está cancelado
                if (pedido.EstadoPedido === 'Cancelado') {
                    contenido += `
                        <div class="acciones-modal">
                            <button onclick="mostrarVentaDirecta(${pedido.PedidoID})" class="btn-accion btn-registrar">
                                <i class="fas fa-cash-register"></i> Registrar Venta Directa
                            </button>
                        </div>
                    `;
                }

            // Actualizar modal con el contenido real
            document.getElementById('modalContenido').innerHTML = contenido;
        })
        .catch(error => {
            console.error('Error al obtener detalles del pedido:', error);
            document.getElementById('modalContenido').innerHTML = `
                <div style="text-align: center; padding: 20px; color: #dc3545;">
                    <i class="fas fa-exclamation-circle"></i> Error al cargar los detalles del pedido.
                </div>
                <div style="text-align: center;">
                    <button onclick="verPedido(${pedidoId})" class="btn-accion btn-ver">
                        <i class="fas fa-sync-alt"></i> Intentar nuevamente
                    </button>
                </div>
            `;
        });
}

// Función para mostrar el formulario de venta directa
function mostrarVentaDirecta(pedidoId) {
    document.getElementById('ventaPedidoId').value = pedidoId;
    document.getElementById('ventaDirectaForm').style.display = 'block';
    window.scrollTo(0, document.body.scrollHeight);
}

// Función para cerrar modal
function cerrarModal() {
    document.getElementById('modalPedido').style.display = 'none';
}

// Cerrar modal al hacer clic fuera del contenido
window.onclick = function (event) {
    const modal = document.getElementById('modalPedido');
    if (event.target === modal) {
        cerrarModal();
    }
};
    </script>

</body>
</html>