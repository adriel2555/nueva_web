<?php
require_once '../configuracion/conexion.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT ProductoID, NombreProducto, Precio FROM Productos WHERE EstaActivo = 1 ORDER BY NombreProducto";
    $result = $conn->query($sql);
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
    
    echo json_encode($productos);
} catch (Exception $e) {
    echo json_encode([]);
}

$conn->close();
?>