<?php
session_start();
require_once '../configuracion/conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
    exit;
}

$pedidoId = $_GET['id'];
$usuarioEmail = $_SESSION['email'];

try {
    // Verificar que el pedido pertenece al usuario y obtener más campos
    $stmt = $conn->prepare("
        SELECT p.*, u.Direccion, u.Ciudad 
        FROM Pedidos p
        JOIN Usuarios u ON p.UsuarioID = u.UsuarioID
        WHERE u.Email = ? AND p.PedidoID = ?
    ");
    $stmt->bind_param("si", $usuarioEmail, $pedidoId);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    
    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o no autorizado']);
        exit;
    }
    
    // Obtener los artículos del pedido (CORREGIDO: usar pr.Precio en lugar de pr.PrecioUnitario)
    $stmt = $conn->prepare("
        SELECT ap.*, pr.NombreProducto, pr.Precio as PrecioOriginal
        FROM ArticulosPedido ap
        JOIN Productos pr ON ap.ProductoID = pr.ProductoID
        WHERE ap.PedidoID = ?
    ");
    $stmt->bind_param("i", $pedidoId);
    $stmt->execute();
    $articulos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Asegurar que los campos numéricos sean números
    $pedido['MontoTotal'] = (float)$pedido['MontoTotal'];
    foreach ($articulos as &$articulo) {
        $articulo['PrecioUnitario'] = (float)$articulo['PrecioUnitario'];
        $articulo['Subtotal'] = (float)$articulo['Subtotal'];
    }
    
    echo json_encode([
        'success' => true,
        'pedido' => $pedido,
        'articulos' => $articulos
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}

$conn->close();
?>