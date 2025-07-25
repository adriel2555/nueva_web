<?php
require_once '../configuracion/conexion.php';

// Agregar al inicio del archivo
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
        
        // 1. Crear registro en Pedidos con estado "Entregado"
        $sqlPedido = "INSERT INTO Pedidos (UsuarioID, MontoTotal, EstadoPedido, MetodoPago, IDTransaccion, Notas) 
                     VALUES (NULL, ?, 'Entregado', ?, ?, ?)";
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
            $usuarioResponsable = $_SESSION['nombre'] ?? "Administrador";
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
        // Manejar el error
        $error = "Error al registrar la venta directa: " . $e->getMessage();
        header("Location: admin-pedidos.php?error=" . urlencode($error));
        exit();
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    $contrasena = $_POST['contrasena'];
    $confirmar_contrasena = $_POST['confirmarContrasena'];

    // Validar que las contraseñas coincidan
    if ($contrasena !== $confirmar_contrasena) {
        header('Location: ../public/auth/registro.html?error=contrasenas_no_coinciden');
        exit();
    }

    // Verificar si el email ya existe
    try {
        $sql_check = "SELECT Email FROM Usuarios WHERE Email = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            header('Location: ../public/auth/registro.html?error=email_existente');
            exit();
        }
        $stmt_check->close();
    } catch (Exception $e) {
        error_log("Error al verificar email: " . $e->getMessage());
        header('Location: ../public/auth/registro.html?error=error_verificacion');
        exit();
    }

    // Hashear la contraseña
    $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

    // Insertar el nuevo usuario
    try {
        $sql = "INSERT INTO Usuarios (Nombre, Apellido, Email, ContrasenaHash, FechaRegistro) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $nombre, $apellido, $email, $contrasena_hash);
        
        if ($stmt->execute()) {
            header('Location: ../public/auth/registro-exitoso.html');
            exit();
        } else {
            error_log("Error al registrar: " . $stmt->error);
            header('Location: ../public/auth/registro.html?error=error_registro');
            exit();
        }
    } catch (Exception $e) {
        error_log("Error en el registro: " . $e->getMessage());
        header('Location: ../public/auth/registro.html?error=error_registro');
        exit();
    }

    $stmt->close();
    $conn->close();
} else {
    header('Location: ../public/auth/registro.html');
    exit();
}
?>