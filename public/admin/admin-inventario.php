<?php
session_start();
if (!isset($_SESSION['es_admin']) || $_SESSION['es_admin'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Incluir archivo de conexión
require_once '../../configuracion/conexion.php';

// Variables para manejar acciones
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mensaje = '';
$error = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['agregar'])) {
        // Lógica para agregar nuevo producto al inventario
        $productoID = intval($_POST['producto']);
        $cantidad = intval($_POST['cantidad']);
        $minimo = intval($_POST['minimo']);
        $maximo = intval($_POST['maximo']);
        $ubicacion = $conn->real_escape_string($_POST['ubicacion']);
        
        $sql = "INSERT INTO Inventario (ProductoID, CantidadDisponible, CantidadMinima, CantidadMaxima, Ubicacion) 
                VALUES ($productoID, $cantidad, $minimo, $maximo, '$ubicacion')";
        
        if ($conn->query($sql)) {
            $mensaje = "Producto agregado al inventario correctamente";
        } else {
            $error = "Error al agregar producto: " . $conn->error;
        }
    } elseif (isset($_POST['actualizar'])) {
        // Lógica para actualizar inventario
        $inventarioID = intval($_POST['inventario_id']);
        $cantidad = intval($_POST['cantidad']);
        $minimo = intval($_POST['minimo']);
        $maximo = intval($_POST['maximo']);
        $ubicacion = $conn->real_escape_string($_POST['ubicacion']);
        
        $sql = "UPDATE Inventario SET 
                CantidadDisponible = $cantidad,
                CantidadMinima = $minimo,
                CantidadMaxima = $maximo,
                Ubicacion = '$ubicacion'
                WHERE InventarioID = $inventarioID";
        
        if ($conn->query($sql)) {
            $mensaje = "Inventario actualizado correctamente";
        } else {
            $error = "Error al actualizar inventario: " . $conn->error;
        }
    }
} elseif ($accion == 'eliminar' && $id > 0) {
    $sql = "DELETE FROM Inventario WHERE InventarioID = $id";
    if ($conn->query($sql)) {
        $mensaje = "Registro de inventario eliminado correctamente";
    } else {
        $error = "Error al eliminar registro: " . $conn->error;
    }
}

// Obtener categorías para el filtro
$categorias = [];
$sqlCategorias = "SELECT * FROM Categorias";
$resultCategorias = $conn->query($sqlCategorias);
if ($resultCategorias->num_rows > 0) {
    while ($row = $resultCategorias->fetch_assoc()) {
        $categorias[$row['CategoriaID']] = $row['NombreCategoria'];
    }
}

// Obtener productos para el formulario de agregar
$productos = [];
$sqlProductos = "SELECT ProductoID, NombreProducto FROM Productos";
$resultProductos = $conn->query($sqlProductos);
if ($resultProductos->num_rows > 0) {
    while ($row = $resultProductos->fetch_assoc()) {
        $productos[$row['ProductoID']] = $row['NombreProducto'];
    }
}

// Obtener inventario con filtro de categoría
$filtroCategoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;

$sql = "SELECT i.InventarioID, i.ProductoID, i.CantidadDisponible, i.CantidadReservada, 
        i.CantidadMinima, i.CantidadMaxima, i.Ubicacion, 
        p.NombreProducto, p.Precio, 
        c.NombreCategoria,
        (SELECT pr.NombreProveedor 
         FROM ProductosProveedores pp 
         JOIN Proveedores pr ON pp.ProveedorID = pr.ProveedorID 
         WHERE pp.ProductoID = p.ProductoID AND pp.EsPrincipal = 1 LIMIT 1) AS ProveedorPrincipal
        FROM Inventario i
        JOIN Productos p ON i.ProductoID = p.ProductoID
        JOIN Categorias c ON p.CategoriaID = c.CategoriaID
        WHERE ($filtroCategoria = 0 OR p.CategoriaID = $filtroCategoria)";


// Obtener término de búsqueda
$busqueda = isset($_GET['busqueda']) ? $conn->real_escape_string($_GET['busqueda']) : '';

// Modificar la consulta SQL para incluir la búsqueda
$sql = "SELECT i.InventarioID, i.ProductoID, i.CantidadDisponible, i.CantidadReservada, 
        i.CantidadMinima, i.CantidadMaxima, i.Ubicacion, 
        p.NombreProducto, p.Precio, 
        c.NombreCategoria,
        (SELECT pr.NombreProveedor 
         FROM ProductosProveedores pp 
         JOIN Proveedores pr ON pp.ProveedorID = pr.ProveedorID 
         WHERE pp.ProductoID = p.ProductoID AND pp.EsPrincipal = 1 LIMIT 1) AS ProveedorPrincipal
        FROM Inventario i
        JOIN Productos p ON i.ProductoID = p.ProductoID
        JOIN Categorias c ON p.CategoriaID = c.CategoriaID
        WHERE ($filtroCategoria = 0 OR p.CategoriaID = $filtroCategoria)
        AND (p.NombreProducto LIKE '%$busqueda%' 
             OR c.NombreCategoria LIKE '%$busqueda%'
             OR i.Ubicacion LIKE '%$busqueda%')";


$result = $conn->query($sql);
$inventario = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $inventario[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Inventario | Aranzábal</title>

    <link rel="stylesheet" href="../../public/css/responsivo_admin.css">

    <link rel="stylesheet" href="../../public/css/admin_inventario.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        

    </style>
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
            <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i> <span>Resumen</span></a></li>
            <li><a href="admin_producto.php"><i class="fas fa-box"></i> <span>Productos</span></a></li>
            <li><a href="admin-pedidos.php"><i class="fas fa-shopping-cart"></i> <span>Pedidos / Reservas</span></a></li>
            <li><a href="admin-clientes.php"><i class="fas fa-users"></i> <span>Clientes</span></a></li>
            <li><a href="admin-inventario.php" class="activo"><i class="fas fa-warehouse"></i> <span>Inventario</span></a></li>
            <li><a href="admin_reportes.php"><i class="fas fa-chart-bar"></i> <span>Reportes</span></a></li>
        </ul>
    </nav>
    <div class="cerrar-sesion-admin">
        <a href="../../controladores/cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i> <span>Cerrar Sesión</span></a>
    </div>
</aside>

        <main class="contenido-admin">
            <header class="cabecera-admin">
                <div class="buscador-admin">
                    <form method="GET" action="admin-inventario.php">
                        <input type="text" name="busqueda" placeholder="Buscar producto..." value="<?php echo isset($_GET['busqueda']) ? htmlspecialchars($_GET['busqueda']) : ''; ?>">
                        <button type="submit">
                            Buscar
                        </button>
                    </form>
                </div>
                <div class="usuario-admin">
                    <div class="avatar-usuario">A</div>
                    <span>Administrador</span>
                </div>
            </header>

            <div class="contenido-principal-admin">
                <h1>Administración de Inventario</h1>
                
                <?php if ($error): ?>
                    <div class="mensaje error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($mensaje): ?>
                    <div class="mensaje success"><?php echo $mensaje; ?></div>
                <?php endif; ?>
                
                <div class="filtros">
                    <div class="filtro-categorias">
                        <label for="categoria">Filtrar por categoría:</label>
                        <select id="categoria" onchange="filtrarCategoria()">
                            <option value="0">Todas las categorías</option>
                            <?php foreach ($categorias as $id => $nombre): ?>
                                <option value="<?php echo $id; ?>" <?php echo $filtroCategoria == $id ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn-agregar" onclick="mostrarFormularioAgregar()">+ Agregar Nuevo</button>
                </div>
                
                <div id="formularioAgregar" class="contenedor-formulario">
                    <h2>Agregar Producto al Inventario</h2>
                    <form method="POST" action="admin-inventario.php">
                        <div class="form-group">
                            <label for="producto">Producto</label>
                            <select id="producto" name="producto" required>
                                <option value="">Seleccione un producto</option>
                                <?php foreach ($productos as $id => $nombre): ?>
                                    <option value="<?php echo $id; ?>"><?php echo $nombre; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="cantidad">Cantidad Disponible</label>
                            <input type="number" id="cantidad" name="cantidad" min="0" value="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="minimo">Cantidad Mínima</label>
                            <input type="number" id="minimo" name="minimo" min="0" value="10" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="maximo">Cantidad Máxima</label>
                            <input type="number" id="maximo" name="maximo" min="0" value="100" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="ubicacion">Ubicación en Almacén</label>
                            <input type="text" id="ubicacion" name="ubicacion" required>
                        </div>
                        
                        <button type="submit" name="agregar">Guardar</button>
                        <button type="button" class="btn-cancelar" onclick="ocultarFormulario()">Cancelar</button>
                    </form>
                </div>
                
                <div id="formularioEditar" class="contenedor-formulario">
                    <h2>Editar Registro de Inventario</h2>
                    <form method="POST" action="admin-inventario.php">
                        <input type="hidden" id="inventario_id" name="inventario_id">
                        <div class="form-group">
                            <label>Producto</label>
                            <input type="text" id="editar_producto" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="editar_cantidad">Cantidad Disponible</label>
                            <input type="number" id="editar_cantidad" name="cantidad" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="editar_minimo">Cantidad Mínima</label>
                            <input type="number" id="editar_minimo" name="minimo" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="editar_maximo">Cantidad Máxima</label>
                            <input type="number" id="editar_maximo" name="maximo" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="editar_ubicacion">Ubicación en Almacén</label>
                            <input type="text" id="editar_ubicacion" name="ubicacion" required>
                        </div>
                        
                        <button type="submit" name="actualizar">Actualizar</button>
                        <button type="button" class="btn-cancelar" onclick="ocultarFormulario()">Cancelar</button>
                    </form>
                </div>
                
                <div class="tabla-inventario">
                    <h2>Inventario Actual</h2>
                    <table class="tabla-pedidos">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Proveedor Principal</th>
                                <th>Disponible</th>
                                <th>Reservada</th>
                                <th>Estado</th>
                                <th>Mínima</th>
                                <th>Máxima</th>
                                <th>Ubicación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($inventario) > 0): ?>
                                <?php foreach ($inventario as $item): ?>
                                    <?php
                                    // Determinar estado del inventario
                                    $disponible = $item['CantidadDisponible'];
                                    $minimo = $item['CantidadMinima'];
                                    $maximo = $item['CantidadMaxima'];
                                    
                                    $estado = '';
                                    $claseEstado = '';
                                    
                                    if ($disponible <= $minimo) {
                                        $estado = 'Bajo';
                                        $claseEstado = 'estado-bajo';
                                    } elseif ($disponible >= $maximo) {
                                        $estado = 'Alto';
                                        $claseEstado = 'estado-alto';
                                    } else {
                                        $estado = 'Óptimo';
                                        $claseEstado = 'estado-optimo';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo $item['InventarioID']; ?></td>
                                        <td><?php echo $item['NombreProducto']; ?></td>
                                        <td><?php echo $item['NombreCategoria']; ?></td>
                                        <td><?php echo $item['ProveedorPrincipal'] ?? 'N/A'; ?></td>
                                        <td><?php echo $disponible; ?></td>
                                        <td><?php echo $item['CantidadReservada']; ?></td>
                                        <td><span class="estado-inventario <?php echo $claseEstado; ?>"><?php echo $estado; ?></span></td>
                                        <td><?php echo $minimo; ?></td>
                                        <td><?php echo $maximo; ?></td>
                                        <td><?php echo $item['Ubicacion']; ?></td>
                                        <td class="acciones-inventario">
                                            <button class="boton-accion editar" onclick="editarInventario(
                                                <?php echo $item['InventarioID']; ?>,
                                                '<?php echo $item['NombreProducto']; ?>',
                                                <?php echo $disponible; ?>,
                                                <?php echo $item['CantidadReservada']; ?>,
                                                <?php echo $minimo; ?>,
                                                <?php echo $maximo; ?>,
                                                '<?php echo $item['Ubicacion']; ?>'
                                            )">Editar</button>
                                            <a href="admin-inventario.php?accion=eliminar&id=<?php echo $item['InventarioID']; ?>" class="btn-eliminar" onclick="return confirm('¿Está seguro de eliminar este registro?');">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11">No se encontraron registros de inventario.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../js/admin.js"></script>
    <script>
        function mostrarFormularioAgregar() {
            document.getElementById('formularioAgregar').classList.add('activo');
            document.getElementById('formularioEditar').classList.remove('activo');
        }
        
        function ocultarFormulario() {
            document.getElementById('formularioAgregar').classList.remove('activo');
            document.getElementById('formularioEditar').classList.remove('activo');
        }
        
        function editarInventario(id, producto, disponible, reservada, minimo, maximo, ubicacion) {
            document.getElementById('inventario_id').value = id;
            document.getElementById('editar_producto').value = producto;
            document.getElementById('editar_cantidad').value = disponible;
            document.getElementById('editar_minimo').value = minimo;
            document.getElementById('editar_maximo').value = maximo;
            document.getElementById('editar_ubicacion').value = ubicacion;
            
            document.getElementById('formularioEditar').classList.add('activo');
            document.getElementById('formularioAgregar').classList.remove('activo');
            
            document.getElementById('formularioEditar').scrollIntoView({behavior: 'smooth'});
        }
        
        function filtrarCategoria() {
            const categoriaId = document.getElementById('categoria').value;
            window.location.href = `admin-inventario.php?categoria=${categoriaId}`;
        }
        
        // Ocultar formularios si hay un mensaje de éxito o error
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($mensaje || $error): ?>
                document.getElementById('formularioAgregar').classList.remove('activo');
                document.getElementById('formularioEditar').classList.remove('activo');
            <?php endif; ?>
        });
    </script>
</body>
</html>