<?php
session_start();
if (!isset($_SESSION['es_admin']) || $_SESSION['es_admin'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Incluir archivo de conexión
require_once '../../configuracion/conexion.php';


// Procesar cambios de estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    $pedidoID = $_POST['pedido_id'];
    $nuevoEstado = $_POST['nuevo_estado'];
    
    $sql = "UPDATE Pedidos SET EstadoPedido = ? WHERE PedidoID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $nuevoEstado, $pedidoID);
    $stmt->execute();
    
    // Si se cancela, restaurar inventario
    if ($nuevoEstado === 'Cancelado') {
        // Obtener artículos del pedido
        $sqlArticulos = "SELECT ProductoID, Cantidad FROM ArticulosPedido WHERE PedidoID = ?";
        $stmtArticulos = $conn->prepare($sqlArticulos);
        $stmtArticulos->bind_param("i", $pedidoID);
        $stmtArticulos->execute();
        $resultArticulos = $stmtArticulos->get_result();
        
        while ($articulo = $resultArticulos->fetch_assoc()) {
            // Actualizar inventario
            $sqlUpdateInventario = "UPDATE Inventario SET CantidadDisponible = CantidadDisponible + ? WHERE ProductoID = ?";
            $stmtUpdate = $conn->prepare($sqlUpdateInventario);
            $stmtUpdate->bind_param("ii", $articulo['Cantidad'], $articulo['ProductoID']);
            $stmtUpdate->execute();
        }
    }
}

// Procesar salida de inventario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_salida'])) {
    $pedidoID = $_POST['pedido_id'];
    $usuarioResponsable = "Administrador"; // En producción sería $_SESSION['usuario']
    
    // Obtener artículos del pedido
    $sqlArticulos = "SELECT ProductoID, Cantidad FROM ArticulosPedido WHERE PedidoID = ?";
    $stmtArticulos = $conn->prepare($sqlArticulos);
    $stmtArticulos->bind_param("i", $pedidoID);
    $stmtArticulos->execute();
    $resultArticulos = $stmtArticulos->get_result();
    
    while ($articulo = $resultArticulos->fetch_assoc()) {
        // Registrar salida
        $sqlSalida = "INSERT INTO SalidasInventario (ProductoID, Cantidad, FechaSalida, TipoSalida, PedidoID, UsuarioResponsable)
                      VALUES (?, ?, NOW(), 'Venta', ?, ?)";
        $stmtSalida = $conn->prepare($sqlSalida);
        $stmtSalida->bind_param("iiis", $articulo['ProductoID'], $articulo['Cantidad'], $pedidoID, $usuarioResponsable);
        $stmtSalida->execute();
        
        // Actualizar inventario
        $sqlUpdateInventario = "UPDATE Inventario SET CantidadDisponible = CantidadDisponible - ? WHERE ProductoID = ?";
        $stmtUpdate = $conn->prepare($sqlUpdateInventario);
        $stmtUpdate->bind_param("ii", $articulo['Cantidad'], $articulo['ProductoID']);
        $stmtUpdate->execute();
    }
    
    // Actualizar estado del pedido
    $sqlEstado = "UPDATE Pedidos SET EstadoPedido = 'Entregado' WHERE PedidoID = ?";
    $stmtEstado = $conn->prepare($sqlEstado);
    $stmtEstado->bind_param("i", $pedidoID);
    $stmtEstado->execute();
}

// Obtener pedidos
$sql = "SELECT p.PedidoID, u.Nombre, u.Apellido, p.FechaPedido, p.MontoTotal, p.EstadoPedido 
        FROM Pedidos p
        JOIN Usuarios u ON p.UsuarioID = u.UsuarioID
        ORDER BY p.FechaPedido DESC";
$result = $conn->query($sql);

// Obtener parámetros de filtrado
$filtroEstado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtroFecha = isset($_GET['fecha']) ? $_GET['fecha'] : '';

// Construir consulta SQL con filtros
$sql = "SELECT p.PedidoID, u.Nombre, u.Apellido, p.FechaPedido, p.MontoTotal, p.EstadoPedido 
        FROM Pedidos p
        JOIN Usuarios u ON p.UsuarioID = u.UsuarioID";

// Añadir condiciones según los filtros
$condiciones = [];
if ($filtroEstado && $filtroEstado !== 'todos') {
    $condiciones[] = "p.EstadoPedido = '$filtroEstado'";
}
if ($filtroFecha) {
    $condiciones[] = "DATE(p.FechaPedido) = '$filtroFecha'";
}

if (!empty($condiciones)) {
    $sql .= " WHERE " . implode(' AND ', $condiciones);
}

$sql .= " ORDER BY p.FechaPedido DESC";

$result = $conn->query($sql);

// Procesar venta directa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_venta_directa'])) {
    $pedidoID = $_POST['pedido_id'];
    $metodoPago = $_POST['metodo_pago'];
    $referenciaPago = $_POST['referencia_pago'] ?? null;
    $usuarioResponsable = "Administrador"; // En producción sería $_SESSION['usuario']
    
    // Registrar salida de inventario
    $sqlArticulos = "SELECT ProductoID, Cantidad FROM ArticulosPedido WHERE PedidoID = ?";
    $stmtArticulos = $conn->prepare($sqlArticulos);
    $stmtArticulos->bind_param("i", $pedidoID);
    $stmtArticulos->execute();
    $resultArticulos = $stmtArticulos->get_result();
    
    while ($articulo = $resultArticulos->fetch_assoc()) {
        // Registrar salida
        $sqlSalida = "INSERT INTO SalidasInventario (ProductoID, Cantidad, FechaSalida, TipoSalida, PedidoID, UsuarioResponsable, Notas)
                      VALUES (?, ?, NOW(), 'Venta Directa', ?, ?, ?)";
        $stmtSalida = $conn->prepare($sqlSalida);
        $notas = "Método de pago: $metodoPago" . ($referenciaPago ? ", Referencia: $referenciaPago" : "");
        $stmtSalida->bind_param("iiiss", $articulo['ProductoID'], $articulo['Cantidad'], $pedidoID, $usuarioResponsable, $notas);
        $stmtSalida->execute();
        
        // Actualizar inventario
        $sqlUpdateInventario = "UPDATE Inventario SET CantidadDisponible = CantidadDisponible - ? WHERE ProductoID = ?";
        $stmtUpdate = $conn->prepare($sqlUpdateInventario);
        $stmtUpdate->bind_param("ii", $articulo['Cantidad'], $articulo['ProductoID']);
        $stmtUpdate->execute();
    }
    
    // Actualizar estado del pedido y método de pago
    $sqlEstado = "UPDATE Pedidos SET EstadoPedido = 'Venta Directa', MetodoPago = ?, IDTransaccion = ? WHERE PedidoID = ?";
    $stmtEstado = $conn->prepare($sqlEstado);
    $stmtEstado->bind_param("ssi", $metodoPago, $referenciaPago, $pedidoID);
    $stmtEstado->execute();
}

// Obtener estadísticas de pedidos hoy
$hoy = date('Y-m-d');
$sqlPedidosHoy = "SELECT COUNT(*) as total FROM Pedidos WHERE DATE(FechaPedido) = '$hoy'";
$resultPedidosHoy = $conn->query($sqlPedidosHoy);
$pedidosHoy = $resultPedidosHoy->fetch_assoc()['total'];

// Obtener ventas hoy
$sqlVentasHoy = "SELECT SUM(MontoTotal) as total FROM Pedidos WHERE DATE(FechaPedido) = '$hoy' AND EstadoPedido != 'Cancelado'";
$resultVentasHoy = $conn->query($sqlVentasHoy);
$ventasHoy = $resultVentasHoy->fetch_assoc()['total'] ?? 0;

// Obtener nuevos clientes hoy
$sqlNuevosClientes = "SELECT COUNT(*) as total FROM Usuarios WHERE DATE(FechaRegistro) = '$hoy' AND EsAdministrador = 0";
$resultNuevosClientes = $conn->query($sqlNuevosClientes);
$nuevosClientes = $resultNuevosClientes->fetch_assoc()['total'];

// Obtener productos vendidos hoy
$sqlProductosVendidos = "SELECT SUM(ap.Cantidad) as total 
                         FROM ArticulosPedido ap
                         JOIN Pedidos p ON ap.PedidoID = p.PedidoID
                         WHERE DATE(p.FechaPedido) = '$hoy' AND p.EstadoPedido != 'Cancelado'";
$resultProductosVendidos = $conn->query($sqlProductosVendidos);
$productosVendidos = $resultProductosVendidos->fetch_assoc()['total'] ?? 0;
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

// Procesar venta directa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_venta_directa'])) {
    try {
        $conn->begin_transaction();
        
        // Obtener datos del formulario
        $cliente = $_POST['cliente_venta'] ?? 'Venta Directa';
        $metodoPago = $_POST['metodo_pago'];
        $referenciaPago = $_POST['referencia_pago'] ?? null;
        $notas = $_POST['notas_venta'] ?? null;
        $productos = $_POST['productos'];
        $cantidades = $_POST['cantidades'];
        $precios = $_POST['precios'];
        
        // Calcular total
        $total = 0;
        foreach ($precios as $index => $precio) {
            $total += $precio * $cantidades[$index];
        }
        
        // 1. Crear registro en Pedidos
        $sqlPedido = "INSERT INTO Pedidos (UsuarioID, MontoTotal, EstadoPedido, MetodoPago, IDTransaccion, Notas) 
                     VALUES (NULL, ?, 'Venta Directa', ?, ?, ?)";
        $stmtPedido = $conn->prepare($sqlPedido);
        $stmtPedido->bind_param("dsss", $total, $metodoPago, $referenciaPago, $notas);
        $stmtPedido->execute();
        $pedidoID = $conn->insert_id;
        
        // 2. Registrar artículos del pedido y actualizar inventario
        foreach ($productos as $index => $productoID) {
            $cantidad = $cantidades[$index];
            $precioUnitario = $precios[$index];
            $subtotal = $cantidad * $precioUnitario;
            
            // Registrar artículo
            $sqlArticulo = "INSERT INTO ArticulosPedido (PedidoID, ProductoID, Cantidad, PrecioUnitario, Subtotal) 
                           VALUES (?, ?, ?, ?, ?)";
            $stmtArticulo = $conn->prepare($sqlArticulo);
            $stmtArticulo->bind_param("iiidd", $pedidoID, $productoID, $cantidad, $precioUnitario, $subtotal);
            $stmtArticulo->execute();
            
            // Registrar salida de inventario
            $sqlSalida = "INSERT INTO SalidasInventario (ProductoID, Cantidad, FechaSalida, TipoSalida, PedidoID, UsuarioResponsable, Notas)
                         VALUES (?, ?, NOW(), 'Venta Directa', ?, ?, ?)";
            $stmtSalida = $conn->prepare($sqlSalida);
            $notasSalida = "Venta directa - Cliente: $cliente";
            $usuarioResponsable = "Administrador"; // En producción sería $_SESSION['usuario']
            $stmtSalida->bind_param("iiiss", $productoID, $cantidad, $pedidoID, $usuarioResponsable, $notasSalida);
            $stmtSalida->execute();
            
            // Actualizar inventario
            $sqlUpdate = "UPDATE Inventario SET CantidadDisponible = CantidadDisponible - ? WHERE ProductoID = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param("ii", $cantidad, $productoID);
            $stmtUpdate->execute();
        }
        
        $conn->commit();
        
        // Redirigir para evitar reenvío del formulario
        header("Location: admin-pedidos.php?success=venta_directa");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        // Manejar el error (puedes mostrar un mensaje al usuario)
        $error = "Error al registrar la venta directa: " . $e->getMessage();
    }
}

// Mostrar mensaje de éxito si existe
if (isset($_GET['success']) && $_GET['success'] === 'venta_directa') {
    echo '<script>alert("Venta directa registrada correctamente");</script>';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Pedidos | Aranzábal</title>
    <link rel="stylesheet" href="../../archivos_estaticos/css/admin.css">
    <link rel="stylesheet" href="../../archivos_estaticos/css/admin:p.css">
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
                    <li><a href="admin-pedidos.php" class="activo"><i class="fas fa-shopping-cart"></i>Pedidos / Reservas</a></li>
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
                <h1><i class="fas fa-shopping-cart"></i> Administración de Pedidos / Reservas</h1>
                <div class="usuario-admin">
                    <div class="avatar-usuario">A</div>
                    <span>Administrador</span>
                </div>
            </header>

            <div class="resumen-estadisticas">
                <div class="tarjeta-estadistica pedidos">
                    <div class="icono-estadistica">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="info-estadistica">
                        <h3>Pedidos Hoy</h3>
                        <p class="valor"><?= $pedidosHoy ?></p>
                    </div>
                </div>

                <div class="tarjeta-estadistica ventas">
                    <div class="icono-estadistica">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="info-estadistica">
                        <h3>Ventas Hoy</h3>
                        <p class="valor">S/ <?= number_format($ventasHoy, 2) ?></p>
                    </div>
                </div>

                <div class="tarjeta-estadistica clientes">
                    <div class="icono-estadistica">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="info-estadistica">
                        <h3>Nuevos Clientes</h3>
                        <p class="valor"><?= $nuevosClientes ?></p>
                    </div>
                </div>

                <div class="tarjeta-estadistica productos">
                    <div class="icono-estadistica">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div class="info-estadistica">
                        <h3>Productos Vendidos / Reservados</h3>
                        <p class="valor"><?= $productosVendidos ?></p>
                    </div>
                </div>
            </div>

            <!-- Filtros funcionales -->
            <form method="GET" class="filtros" id="filtroForm">
                <div>
                    <label for="filtro-estado">Estado:</label>
                    <select id="filtro-estado" name="estado">
                        <option value="todos">Todos los estados</option>
                        <option value="Pendiente" <?=$filtroEstado==='Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="Procesando" <?=$filtroEstado==='Procesando' ? 'selected' : '' ?>>Procesando
                        </option>
                        <option value="Enviado" <?=$filtroEstado==='Enviado' ? 'selected' : '' ?>>Enviado</option>
                        <option value="Entregado" <?=$filtroEstado==='Entregado' ? 'selected' : '' ?>>Entregado</option>
                        <option value="Cancelado" <?=$filtroEstado==='Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>

                <div>
                    <label for="filtro-fecha">Fecha:</label>
                    <input type="date" id="filtro-fecha" name="fecha" value="<?= $filtroFecha ?>">
                </div>

                <div>
                    <button type="submit" id="btn-filtrar">
                        <i class="fas fa-filter"></i> Aplicar Filtros
                    </button>
                </div>

                <div>
                    <button type="button" id="btn-limpiar" class="btn-limpiar">
                        <i class="fas fa-times"></i> Limpiar Filtros
                    </button>
                </div>


                <div>
                    <button class="btn-accion btn-registrar" onclick="abrirModalVentaDirecta(event)">
                        <i class="fas fa-cash-register"></i> Venta Directa
                    </button>
                </div>

            </form>

            <div class="contador-resultados">
                Mostrando
                <?= $result->num_rows ?> pedido(s)
            </div>

            <div class="lista-pedidos">
                <div class="lista-pedidos">
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
                        if ($result->num_rows > 0) {
                            while ($pedido = $result->fetch_assoc()) {
                                $nombreCliente = $pedido['Nombre'] . ' ' . $pedido['Apellido'];
                                $fecha = date('d/m/Y', strtotime($pedido['FechaPedido']));
                                $total = number_format($pedido['MontoTotal'], 2);
                                $estado = $pedido['EstadoPedido'];
                                
                                // Clase CSS según el estado
                                $claseEstado = strtolower($estado);
                                
                                echo "<tr>
                                    <td>#{$pedido['PedidoID']}</td>
                                    <td>{$nombreCliente}</td>
                                    <td>{$fecha}</td>
                                    <td>S/ {$total}</td>
                                    <td><span class=\"estado {$claseEstado}\">{$estado}</span></td>
                                    <td class=\"acciones\">
                                        <button class=\"btn-accion btn-ver\" onclick=\"verPedido({$pedido['PedidoID']})\">
                                            <i class=\"fas fa-eye\"></i> Detalles
                                        </button>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan=\"6\" style=\"text-align: center; padding: 20px;\">No se encontraron pedidos con los filtros seleccionados</td></tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
        </main>
    </div>

    <!-- Agregar esto en el modal-cuerpo, después de la tabla de productos -->
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

    <!-- Modal para Venta Directa -->
    <div class="modal" id="modalVentaDirecta">
        <div class="modal-contenido" style="max-width: 800px;">
            <div class="modal-cabecera">
                <h2>Registrar Venta Directa</h2>
                <button class="cerrar-modal" onclick="cerrarModalVentaDirecta()">&times;</button>
            </div>
            <div class="modal-cuerpo">
                <form id="formVentaDirecta" method="post" onsubmit="return confirmarVentaDirecta()">
                    <div class="info-pedido">
                        <div class="info-item">
                            <label for="cliente_venta">Cliente (opcional):</label>
                            <input type="text" id="cliente_venta" name="cliente_venta" placeholder="Nombre del cliente">
                        </div>
                        
                        <div class="info-item">
                            <label for="metodo_pago">Método de Pago:</label>
                            <select id="metodo_pago" name="metodo_pago" required>
                                <option value="">Seleccionar...</option>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Pago Móvil">Pago Móvil</option>
                            </select>
                        </div>
                        
                        <div class="info-item">
                            <label for="referencia_pago">Referencia (opcional):</label>
                            <input type="text" id="referencia_pago" name="referencia_pago" placeholder="Número de referencia">
                        </div>
                    </div>
                    
                    <h3>Productos</h3>
                    <div id="productosVentaContainer">
                        <div class="producto-venta">
                            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <select class="producto-select" name="productos[]" required>
                                    <option value="">Seleccionar producto</option>
                                    <?php
                                    $sqlProductos = "SELECT ProductoID, NombreProducto FROM Productos WHERE EstaActivo = 1 ORDER BY NombreProducto";
                                    $resultProductos = $conn->query($sqlProductos);
                                    while ($producto = $resultProductos->fetch_assoc()) {
                                        echo "<option value='{$producto['ProductoID']}'>{$producto['NombreProducto']}</option>";
                                    }
                                    ?>
                                </select>
                                <input type="number" class="cantidad" name="cantidades[]" min="1" value="1" required>
                                <input type="number" class="precio" name="precios[]" min="0.01" step="0.01" placeholder="Precio" required>
                                <button type="button" class="btn-accion btn-cancelar" onclick="eliminarProducto(this)" style="width: auto;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn-accion btn-editar" onclick="agregarProducto()" style="margin-bottom: 15px;">
                        <i class="fas fa-plus"></i> Añadir Producto
                    </button>
                    
                    <div class="info-item">
                        <label for="notas_venta">Notas:</label>
                        <textarea id="notas_venta" name="notas_venta" rows="2" placeholder="Observaciones sobre la venta"></textarea>
                    </div>
                    
                    <div class="acciones-modal">
                        <button type="submit" name="registrar_venta_directa" class="btn-accion btn-registrar">
                            <i class="fas fa-check-circle"></i> Registrar Venta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Limpiar filtros
        document.getElementById('btn-limpiar').addEventListener('click', function () {
            document.getElementById('filtro-estado').value = 'todos';
            document.getElementById('filtro-fecha').value = '';
            document.getElementById('filtroForm').submit();
        });

        // Función para abrir modal con detalles del pedido
// Función para abrir modal con detalles del pedido
function verPedido(pedidoId) {
    // Mostrar cargando mientras se obtienen los datos
    document.getElementById('modalContenido').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Cargando detalles del pedido...</div>';
    document.getElementById('modalTitulo').textContent = `Pedido #${pedidoId}`;
    document.getElementById('modalPedido').style.display = 'flex';
    
    // Obtener datos del pedido via AJAX
    fetch(`admin-pedidos.php?obtener_pedido=1&pedido_id=${pedidoId}`)
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

        // Nueva función para mostrar el formulario de venta directa
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
document.addEventListener('click', function(event) {
    const modalPedido = document.getElementById('modalPedido');
    const modalVentaDirecta = document.getElementById('modalVentaDirecta');
    const modalContenidoVenta = document.querySelector('#modalVentaDirecta .modal-contenido');
    
    // Cerrar modal de pedido si se hace clic fuera
    if (event.target === modalPedido) {
        cerrarModal();
    }
    
    // Cerrar modal de venta directa solo si se hace clic fuera del contenido
    if (event.target === modalVentaDirecta && !modalContenidoVenta.contains(event.target)) {
        cerrarModalVentaDirecta();
    }
});


 // funciones ventas directa
function abrirModalVentaDirecta(event) {
    // Prevenir el comportamiento predeterminado y la propagación del evento
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Cerrar otros modales que puedan estar abiertos
    cerrarModal();
    
    // Mostrar el modal de venta directa
    document.getElementById('modalVentaDirecta').style.display = 'flex';
}

function cerrarModalVentaDirecta(event) {
    // Prevenir la propagación si el evento existe
    if (event) {
        event.stopPropagation();
    }
    document.getElementById('modalVentaDirecta').style.display = 'none';
}

function agregarProducto() {
    const container = document.getElementById('productosVentaContainer');
    const nuevoProducto = document.createElement('div');
    nuevoProducto.className = 'producto-venta';
    nuevoProducto.innerHTML = `
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
            <select class="producto-select" name="productos[]" required>
                <option value="">Seleccionar producto</option>
                <?php
                $resultProductos = $conn->query($sqlProductos);
                while ($producto = $resultProductos->fetch_assoc()) {
                    echo "<option value='{$producto['ProductoID']}'>{$producto['NombreProducto']}</option>";
                }
                ?>
            </select>
            <input type="number" class="cantidad" name="cantidades[]" min="1" value="1" required>
            <input type="number" class="precio" name="precios[]" min="0.01" step="0.01" placeholder="Precio" required>
            <button type="button" class="btn-accion btn-cancelar" onclick="eliminarProducto(this)" style="width: auto;">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(nuevoProducto);
}

function eliminarProducto(boton) {
    const productoDiv = boton.closest('.producto-venta');
    if (document.querySelectorAll('.producto-venta').length > 1) {
        productoDiv.remove();
    } else {
        alert('Debe haber al menos un producto en la venta.');
    }
}

function confirmarVentaDirecta() {
    return confirm('¿Está seguro de registrar esta venta directa? Esta acción reducirá el stock de los productos.');
}       
        
    </script>
</body>

</html>