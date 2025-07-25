<?php
session_start();
header('Content-Type: application/json');

require_once '../configuracion/conexion.php';

// Respuesta por defecto en caso de error
$response = ['success' => false, 'message' => 'Petición inválida.'];

// 1. Verificar que el usuario está logueado
if (!isset($_SESSION['email'])) {
    $response['message'] = 'Acceso denegado. Por favor, inicie sesión.';
    echo json_encode($response);
    exit;
}

// 2. Verificar que recibimos los datos necesarios (carrito_id y cantidad)
if (isset($_POST['carrito_id']) && isset($_POST['cantidad'])) {
    
    $carrito_id = filter_var($_POST['carrito_id'], FILTER_VALIDATE_INT);
    $cantidad = filter_var($_POST['cantidad'], FILTER_VALIDATE_INT);
    
    if ($carrito_id === false || $cantidad === false) {
        $response['message'] = 'Datos inválidos.';
        echo json_encode($response);
        exit;
    }

    // Obtener el ID de usuario desde la sesión para seguridad
    $stmt_user = $conn->prepare("SELECT UsuarioID FROM Usuarios WHERE Email = ?");
    $stmt_user->bind_param("s", $_SESSION['email']);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows === 0) {
         $response['message'] = 'Usuario no encontrado.';
         echo json_encode($response);
         exit;
    }
    $usuario = $result_user->fetch_assoc();
    $usuario_id = $usuario['UsuarioID'];

    try {
        // 3. Decidir si actualizar o eliminar
        if ($cantidad <= 0) {
            // Eliminar el producto del carrito
            $stmt = $conn->prepare("DELETE FROM Carrito WHERE CarritoID = ? AND UsuarioID = ?");
            $stmt->bind_param("ii", $carrito_id, $usuario_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $response = ['success' => true, 'message' => 'Producto eliminado.'];
            } else {
                $response['message'] = 'No se pudo eliminar el producto o no te pertenece.';
            }

        } else {
            // Actualizar la cantidad del producto
            $stmt_stock = $conn->prepare(
                "SELECT p.CantidadStock FROM Carrito c JOIN Productos p ON c.ProductoID = p.ProductoID WHERE c.CarritoID = ? AND c.UsuarioID = ?"
            );
            $stmt_stock->bind_param("ii", $carrito_id, $usuario_id);
            $stmt_stock->execute();
            $result_stock = $stmt_stock->get_result();
            
            if($row = $result_stock->fetch_assoc()){
                if($cantidad > $row['CantidadStock']){
                    $response['message'] = 'No hay suficiente stock. Disponible: ' . $row['CantidadStock'];
                    echo json_encode($response);
                    exit;
                }
            } else {
                 $response['message'] = 'Producto no encontrado en tu carrito.';
                 echo json_encode($response);
                 exit;
            }

            // Si hay stock, actualizamos
            $stmt = $conn->prepare("UPDATE Carrito SET Cantidad = ? WHERE CarritoID = ? AND UsuarioID = ?");
            $stmt->bind_param("iii", $cantidad, $carrito_id, $usuario_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $response = ['success' => true, 'message' => 'Cantidad actualizada.'];
            } else {
                $response = ['success' => true, 'message' => 'No hubo cambios.'];
            }
        }

    } catch (Exception $e) {
        $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
    }
    
    $stmt->close();
    $conn->close();

} else {
    $response['message'] = 'Faltan parámetros necesarios.';
}

echo json_encode($response);
?>