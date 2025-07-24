<?php
session_start();
require_once '../configuracion/conexion.php';

// Verificar sesión
if (!isset($_SESSION['email'])) {
    $_SESSION['error_reserva'] = 'Debes iniciar sesión para realizar una reserva';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Obtener datos del POST
$items = $_POST['items'] ?? [];
$subtotal = $_POST['subtotal'] ?? 0;
$envio = $_POST['envio'] ?? 0;
$total = $_POST['total'] ?? 0;

// Validar datos
if (empty($items) || $total <= 0) {
    $_SESSION['error_reserva'] = 'El carrito está vacío o los datos son inválidos';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Obtener ID del usuario logueado
$stmt = $conn->prepare("SELECT UsuarioID, Direccion, Ciudad, Departamento, CodigoPostal FROM Usuarios WHERE Email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_reserva'] = 'Usuario no encontrado en la base de datos';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$usuario = $result->fetch_assoc();
$usuarioId = $usuario['UsuarioID'];

// Iniciar transacción
$conn->begin_transaction();

try {
    // 1. Crear el pedido
    $stmt = $conn->prepare("
        INSERT INTO Pedidos (
            UsuarioID, MontoTotal, EstadoPedido, 
            DireccionEnvio, CiudadEnvio, DepartamentoEnvio, CodigoPostalEnvio
        ) VALUES (?, ?, 'Procesando', ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "idssss", 
        $usuarioId, $total,
        $usuario['Direccion'], $usuario['Ciudad'], $usuario['Departamento'], $usuario['CodigoPostal']
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Error al crear el pedido: ' . $stmt->error);
    }
    
    $pedidoId = $conn->insert_id;
    
    // 2. Procesar cada item del carrito
    foreach ($items as $item) {
        $productoId = $item['productoId'];
        $cantidad = $item['cantidad'];
        $precio = $item['precio'];
        
        // Validar item
        if (empty($productoId) || empty($cantidad) || empty($precio)) {
            throw new Exception('Datos de producto incompletos');
        }
        
        // Insertar en ArticulosPedido
        $stmt = $conn->prepare("
            INSERT INTO ArticulosPedido (
                PedidoID, ProductoID, Cantidad, PrecioUnitario, Subtotal
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $subtotalItem = $precio * $cantidad;
        $stmt->bind_param(
            "iiidd", 
            $pedidoId, $productoId, $cantidad, $precio, $subtotalItem
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Error al agregar producto al pedido: ' . $stmt->error);
        }
        
        // Registrar salida de inventario
        $stmt = $conn->prepare("
            INSERT INTO SalidasInventario (
                ProductoID, Cantidad, TipoSalida, PedidoID, UsuarioResponsable
            ) VALUES (?, ?, 'Venta', ?, ?)
        ");
        
        $usuarioEmail = $_SESSION['email'];
        $stmt->bind_param(
            "iiis", 
            $productoId, $cantidad, $pedidoId, $usuarioEmail
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Error al registrar movimiento de inventario: ' . $stmt->error);
        }
        
        // Actualizar stock
        $stmt = $conn->prepare("
            UPDATE Productos 
            SET CantidadStock = CantidadStock - ? 
            WHERE ProductoID = ? AND CantidadStock >= ?
        ");
        
        $stmt->bind_param("iii", $cantidad, $productoId, $cantidad);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar inventario: ' . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("No hay suficiente stock para el producto ID: $productoId");
        }
    }
    
    // 3. Vaciar el carrito del usuario
    $stmt = $conn->prepare("DELETE FROM Carrito WHERE UsuarioID = ?");
    $stmt->bind_param("i", $usuarioId);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al vaciar carrito: ' . $stmt->error);
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Redirigir a página de éxito
    header("Location: ../vista/carrito/reserva_exitosa.php?pedido_id=$pedidoId");
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_reserva'] = $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$conn->close();
?>