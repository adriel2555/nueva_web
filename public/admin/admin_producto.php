<?php
// Iniciar sesión y verificar si es administrador
session_start();
if (!isset($_SESSION['es_admin']) || $_SESSION['es_admin'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Incluir archivo de conexión
require_once '../../configuracion/conexion.php';

// Variables para mensajes
$mensaje = '';
$tipoMensaje = '';

// Procesar nuevo producto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_producto'])) {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $categoria = $_POST['categoria'];
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];
    $imagen = $_POST['imagen'];
    
    $sql = "INSERT INTO Productos (NombreProducto, Descripcion, CategoriaID, Precio, CantidadStock, UrlImagen) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssidss", $nombre, $descripcion, $categoria, $precio, $stock, $imagen);
    
    if ($stmt->execute()) {
        $mensaje = "Producto registrado exitosamente!";
        $tipoMensaje = "success";
        
        // Registrar entrada en inventario
        $productoId = $stmt->insert_id;
        $sqlEntrada = "INSERT INTO EntradasInventario (ProductoID, ProveedorID, Cantidad, PrecioUnitario, UsuarioResponsable, Notas) 
                       VALUES (?, 1, ?, ?, 'Admin Maestro', 'Registro inicial')";
        $stmtEntrada = $conn->prepare($sqlEntrada);
        $stmtEntrada->bind_param("iid", $productoId, $stock, $precio);
        $stmtEntrada->execute();
    } else {
        $mensaje = "Error al registrar producto: " . $stmt->error;
        $tipoMensaje = "error";
    }
    $stmt->close();
}

// Procesar actualización de stock
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_stock'])) {
    $productoId = $_POST['producto_id'];
    $cantidad = $_POST['cantidad'];
    $motivo = $_POST['motivo'];
    $notas = $_POST['notas'];
    
    // Obtener información del producto
    $sqlProducto = "SELECT Precio FROM Productos WHERE ProductoID = ?";
    $stmtProducto = $conn->prepare($sqlProducto);
    $stmtProducto->bind_param("i", $productoId);
    $stmtProducto->execute();
    $resultProducto = $stmtProducto->get_result();
    $producto = $resultProducto->fetch_assoc();
    
    // Actualizar stock
    $sqlUpdate = "UPDATE Productos SET CantidadStock = CantidadStock + ? WHERE ProductoID = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bind_param("ii", $cantidad, $productoId);
    
    if ($stmtUpdate->execute()) {
        $mensaje = "Stock actualizado exitosamente!";
        $tipoMensaje = "success";
        
        // Registrar entrada/salida en inventario
        $tipo = ($cantidad > 0) ? 'Entrada' : 'Salida';
        $precioUnitario = $producto['Precio'];
        
        if ($cantidad > 0) {
            // Entrada de inventario
            $sqlEntrada = "INSERT INTO EntradasInventario (ProductoID, ProveedorID, Cantidad, PrecioUnitario, UsuarioResponsable, Notas) 
                           VALUES (?, 1, ?, ?, 'Admin Maestro', ?)";
            $stmtEntrada = $conn->prepare($sqlEntrada);
            $stmtEntrada->bind_param("iids", $productoId, $cantidad, $precioUnitario, $notas);
            $stmtEntrada->execute();
        } else {
            // Salida de inventario
            $cantidadAbs = abs($cantidad);
            $sqlSalida = "INSERT INTO SalidasInventario (ProductoID, Cantidad, TipoSalida, UsuarioResponsable, Notas) 
                          VALUES (?, ?, ?, 'Admin Maestro', ?)";
            $stmtSalida = $conn->prepare($sqlSalida);
            $stmtSalida->bind_param("iiss", $productoId, $cantidadAbs, $motivo, $notas);
            $stmtSalida->execute();
        }
    } else {
        $mensaje = "Error al actualizar stock: " . $stmtUpdate->error;
        $tipoMensaje = "error";
    }
    $stmtUpdate->close();
}

// Obtener productos existentes
$sqlProductos = "SELECT p.*, c.NombreCategoria 
                 FROM Productos p 
                 JOIN Categorias c ON p.CategoriaID = c.CategoriaID";
$resultProductos = $conn->query($sqlProductos);

// Obtener categorías
$sqlCategorias = "SELECT * FROM Categorias";
$resultCategorias = $conn->query($sqlCategorias);

// Obtener reporte de compras
$sqlCompras = "SELECT e.EntradaID, p.NombreProducto, e.Cantidad, pr.NombreProveedor, e.PrecioUnitario, e.FechaEntrada 
               FROM EntradasInventario e 
               JOIN Productos p ON e.ProductoID = p.ProductoID 
               JOIN Proveedores pr ON e.ProveedorID = pr.ProveedorID 
               ORDER BY e.FechaEntrada DESC 
               LIMIT 10";
$resultCompras = $conn->query($sqlCompras);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos | Aranzábal</title>
    <link rel="stylesheet" href="../../public/css/admin_productos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="contenedor-admin">
        <aside class="sidebar-admin">
            <div class="logo-admin">
                <img src="../../public/img/diamanteblanco.png" alt="Aranzábal">
                <h2>Aranzábal</h2>
                <p>Panel de Administración</p>
            </div>
            <nav class="menu-admin">
                <ul>
                    <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i>Resumen</a></li>
                    <li><a href="admin_producto.php" class="activo"><i class="fas fa-box"></i>Productos</a></li>
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
                <div></div>
                <div class="usuario-admin">
                    <div class="avatar-usuario">A</div>
                    <span>Administrador</span>
                </div>
            </header>

            <div class="contenido-principal-admin">
                <h1>Gestión de Productos</h1>
                <!-- Mensajes de estado -->
                <?php if ($mensaje): ?>
                    <div class="message <?php echo $tipoMensaje; ?>">
                        <div><?php echo $tipoMensaje == 'success' ? '✅' : '❌'; ?></div>
                        <div><?php echo $mensaje; ?></div>
                    </div>
                <?php endif; ?>
                
                <!-- Tabs -->
                <div class="tabs">
                    <div class="tab active" onclick="showTab('products')">Productos Existentes</div>
                    <div class="tab" onclick="showTab('new-product')">Registrar Nuevo Producto</div>
                    <div class="tab" onclick="showTab('update-stock')">Actualizar Stock</div>
                </div>
                
                <!-- Sección de Productos Existentes -->
                <div class="tab-content active" id="products-tab">
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="Buscar productos..." id="search-product">
                    </div>
                    
                    <div class="table-responsive">
                        <table class="product-table">
                            <thead>
                                <tr>
                                    <th>Imagen</th>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($producto = $resultProductos->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if ($producto['UrlImagen']): ?>
                                            <img src="<?php echo $producto['UrlImagen']; ?>" alt="Producto" class="product-img">
                                        <?php else: ?>
                                            <div class="product-img" style="display: flex; align-items: center; justify-content: center; background: #f0f0f0;">📦</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($producto['NombreProducto']); ?></td>
                                    <td><?php echo htmlspecialchars($producto['NombreCategoria']); ?></td>
                                    <td>S/ <?php echo number_format($producto['Precio'], 2); ?></td>
                                    <td class="<?php echo $producto['CantidadStock'] < 20 ? 'stock-low' : 'stock-ok'; ?>">
                                        <?php echo $producto['CantidadStock']; ?>
                                    </td>
                                    <td>
                                        <?php if ($producto['EstaActivo']): ?>
                                            <span style="color: #43a047; font-weight: bold;">Activo</span>
                                        <?php else: ?>
                                            <span style="color: #e53935; font-weight: bold;">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="message info">
                        <div>ℹ️</div>
                        <div>Mostrando todos los productos disponibles. Los productos con stock bajo están resaltados en rojo.</div>
                    </div>
                </div>
                
                <!-- Sección de Nuevo Producto -->
                <div class="tab-content" id="new-product-tab">
                    <div class="message info">
                        <div>ℹ️</div>
                        <div>Completa todos los campos para registrar un nuevo producto en el inventario.</div>
                    </div>
                    
                    <form id="product-form" method="POST" action="">
                        <div class="form-container">
                            <div class="form-section">
                                <div class="form-group">
                                    <label for="product-name">Nombre del Producto *</label>
                                    <input type="text" class="form-control" id="product-name" name="nombre" placeholder="Ej: Perlas de Río Cultivadas 8mm" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="product-category">Categoría *</label>
                                    <select class="form-control" id="product-category" name="categoria" required>
                                        <option value="">Seleccione una categoría</option>
                                        <?php while($categoria = $resultCategorias->fetch_assoc()): ?>
                                            <option value="<?php echo $categoria['CategoriaID']; ?>">
                                                <?php echo htmlspecialchars($categoria['NombreCategoria']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="product-description">Descripción</label>
                                    <textarea class="form-control" id="product-description" name="descripcion" rows="3" placeholder="Descripción detallada del producto"></textarea>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <div class="form-group">
                                    <label for="product-price">Precio (S/) *</label>
                                    <input type="number" class="form-control" id="product-price" name="precio" min="0" step="0.01" placeholder="Ej: 15.50" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="product-stock">Stock Inicial *</label>
                                    <input type="number" class="form-control" id="product-stock" name="stock" min="0" placeholder="Ej: 100" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="product-image">Imagen del Producto (URL)</label>
                                    <input type="text" class="form-control" id="product-image" name="imagen" placeholder="https://...">
                                </div>
                                
                                <button type="submit" name="registrar_producto" class="btn pulse">
                                    <i>➕</i> Registrar Producto
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Sección de Actualizar Stock -->
                <div class="tab-content" id="update-stock-tab">
                    <div class="message info">
                        <div>ℹ️</div>
                        <div>Selecciona un producto existente para actualizar su stock. Usa valores positivos para aumentar stock y negativos para reducirlo.</div>
                    </div>
                    
                    <form id="stock-form" method="POST" action="">
                        <div class="form-container">
                            <div class="form-section">
                                <div class="form-group">
                                    <label for="select-product">Seleccionar Producto *</label>
                                    <select class="form-control" id="select-product" name="producto_id" required>
                                        <option value="">Seleccione un producto</option>
                                        <?php 
                                            $resultProductos->data_seek(0);
                                            while($producto = $resultProductos->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $producto['ProductoID']; ?>">
                                                <?php echo htmlspecialchars($producto['NombreProducto']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="stock-quantity">Cantidad a Añadir/Reducir *</label>
                                    <input type="number" class="form-control" id="stock-quantity" name="cantidad" placeholder="Ej: +50 o -10" required>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <div class="form-group">
                                    <label for="stock-reason">Motivo *</label>
                                    <select class="form-control" id="stock-reason" name="motivo" required>
                                        <option value="">Seleccione un motivo</option>
                                        <option value="compra">Compra a proveedor</option>
                                        <option value="venta">Venta a cliente</option>
                                        <option value="ajuste">Ajuste de inventario</option>
                                        <option value="devolucion">Devolución de cliente</option>
                                        <option value="perdida">Pérdida o daño</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="stock-notes">Notas</label>
                                    <textarea class="form-control" id="stock-notes" name="notas" rows="2" placeholder="Detalles adicionales..."></textarea>
                                </div>
                                
                                <button type="submit" name="actualizar_stock" class="btn pulse">
                                    <i>🔄</i> Actualizar Stock
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Reporte de compras -->
                <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;">
                    <h2 style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                        <i>📊</i> Reporte de Compras Recientes
                    </h2>
                    
                    <div class="table-responsive">
                        <table class="product-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Proveedor</th>
                                    <th>Precio Unitario</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($compra = $resultCompras->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $compra['FechaEntrada']; ?></td>
                                    <td><?php echo htmlspecialchars($compra['NombreProducto']); ?></td>
                                    <td><?php echo $compra['Cantidad']; ?></td>
                                    <td><?php echo htmlspecialchars($compra['NombreProveedor']); ?></td>
                                    <td>S/ <?php echo number_format($compra['PrecioUnitario'], 2); ?></td>
                                    <td>S/ <?php echo number_format($compra['Cantidad'] * $compra['PrecioUnitario'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <button class="btn btn-outline">
                            <i>📥</i> Exportar Reporte Completo
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../../public/js/admin_productos.js"></script>
</body>
</html>