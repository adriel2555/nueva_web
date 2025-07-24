<?php
session_start();
require_once '../../configuracion/conexion.php';

// Depuración: Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Mostrar errores de reserva si existen
if (isset($_SESSION['error_reserva'])) {
    echo '<div class="error-carrito">' . $_SESSION['error_reserva'] . '</div>';
    unset($_SESSION['error_reserva']);
}

// Depuración: Ver datos del carrito
echo "<!-- Usuario ID: " . ($_SESSION['email'] ?? 'No logueado') . " -->";
$test_query = "SELECT * FROM Carrito WHERE UsuarioID = (SELECT UsuarioID FROM Usuarios WHERE Email = ? LIMIT 1)";
$stmt = $conn->prepare($test_query);
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$result = $stmt->get_result();
echo "<!-- Items en carrito: " . $result->num_rows . " -->";

// Inicializar variables
$usuario_id = null;
$carrito_items = [];
$error = null;

// Verificar sesión y obtener usuario
// Inicializar variables
$usuario_id = null;
$carrito_items = [];
$error = null;

try {
    // Obtener ID de usuario
    $email = $_SESSION['email'];
    $stmt = $conn->prepare("SELECT UsuarioID FROM Usuarios WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        $usuario_id = $usuario['UsuarioID'];
        
        // Consulta optimizada para obtener items del carrito
        $sql = "SELECT 
                c.CarritoID, 
                c.ProductoID, 
                c.Cantidad, 
                c.PrecioUnitario,
                p.NombreProducto, 
                p.Descripcion, 
                COALESCE(p.UrlImagen, '../../archivos_estaticos/img/producto-default.jpg') as Imagen,
                p.CantidadStock
            FROM Carrito c
            JOIN Productos p ON c.ProductoID = p.ProductoID
            WHERE c.UsuarioID = ?";
            
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($item = $result->fetch_assoc()) {
            $carrito_items[] = $item;
        }
    } else {
        $error = "Inicie sesión para mostrar el Carrito";
    }
} catch (Exception $e) {
    $error = "Error al obtener los datos del carrito: " . $e->getMessage();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras | Aranzábal</title>
    <link rel="stylesheet" href="../../archivos_estaticos/css/estilos.css">
    <link rel="stylesheet" href="../../archivos_estaticos/css/carrito.css">
    <link rel="stylesheet" href="../../archivos_estaticos/css/responsivo.css">
</head>
<body>
    <header>
        <div class="contenedor-logo">
            <img src="../../archivos_estaticos/img/diamanteblanco.png" alt="Joyitas Felices" class="logo">
            <h1>Aranzábal</h1>
        </div>

        <button id="boton-menu" class="boton-menu" aria-label="Abrir menú">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav id="nav-principal">
            <ul id="nav-menu">
                <li><a href="../index.php">Inicio</a></li>
                <li><a href="../productos.php">Productos</a></li>
                <li><a href="../nosotros.php">Nosotros</a></li>
                <li><a href="../contacto.php">Contacto</a></li>

                <?php if(isset($_SESSION['email'])): ?>
                <li class="menu-usuario">
                    <a href="../perfil.php" class="enlace-autenticacion">
                        <?php echo $_SESSION['email']; ?>
                    </a>
                    <ul class="submenu">
                        <li><a href="../perfil.php">Mi Perfil</a></li>
                        <li><a href="../../controladores/cerrar_sesion.php">Cerrar Sesión</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li><a href="../autenticacion/iniciar-sesion.html" class="enlace-autenticacion">Iniciar Sesión</a></li>
                <?php endif; ?>

                <li><a href="carrito.php" class="enlace-carrito activo">Carrito (<span id="contador-carrito">0</span>)</a>
                </li>
            </ul>
        </nav>
    </header>

    <main class="contenido-carrito">
        <h2>Tu Carrito de Compras</h2>

        <div class="resumen-carrito">
            <div class="lista-productos-carrito">
                <?php if (!empty($error)): ?>
                    <div class="error-carrito"><?php echo $error; ?></div>
                <?php elseif (empty($carrito_items)): ?>
                    <div class="carrito-vacio">
                        <img src="../../archivos_estaticos/img/carrito.png" alt="Carrito vacío">
                        <h3>Tu carrito está vacío</h3>
                        <p>Agrega algunos productos para comenzar</p>
                        <a href="../productos.php" class="boton-ver-productos">Ver productos</a>
                    </div>
                <?php else: ?>
                    <div class="encabezado-lista">
                        <span>Producto</span>
                        <span>Precio</span>
                        <span>Cantidad</span>
                        <span>Total</span>
                    </div>
                    
                    <?php 
                    $subtotal = 0;
                    foreach ($carrito_items as $item): 
                        $total_producto = $item['PrecioUnitario'] * $item['Cantidad'];
                        $subtotal += $total_producto;
                    ?>
                        <div class="item-carrito" data-id="<?php echo $item['ProductoID']; ?>" data-carrito-id="<?php echo $item['CarritoID']; ?>">
                            <div class="info-producto-carrito">
                                <img src="<?php echo $item['Imagen']; ?>" alt="<?php echo htmlspecialchars($item['NombreProducto']); ?>">
                                <div class="detalles-producto">
                                    <h3><?php echo htmlspecialchars($item['NombreProducto']); ?></h3>
                                    <button class="eliminar-producto" data-producto-id="<?php echo $item['ProductoID']; ?>">Eliminar</button>
                                </div>
                            </div>
                            <div class="precio-producto-carrito">
                                S/ <?php echo number_format($item['PrecioUnitario'], 2); ?>
                            </div>
                            <div class="cantidad-producto-carrito">
                                <button class="disminuir-cantidad" data-producto-id="<?php echo $item['ProductoID']; ?>">-</button>
                                <span class="cantidad"><?php echo $item['Cantidad']; ?></span>
                                <button class="aumentar-cantidad" data-producto-id="<?php echo $item['ProductoID']; ?>">+</button>
                            </div>
                            <div class="total-producto-carrito">
                                S/ <?php echo number_format($total_producto, 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($carrito_items)):?>
            <form id="form-reserva" method="POST" action="../../controladores/procesar_reserva.php" style="display: none;">
                <?php foreach ($carrito_items as $item): ?>
                    <input type="hidden" name="items[<?php echo $item['ProductoID']; ?>][productoId]" value="<?php echo $item['ProductoID']; ?>">
                    <input type="hidden" name="items[<?php echo $item['ProductoID']; ?>][cantidad]" value="<?php echo $item['Cantidad']; ?>">
                    <input type="hidden" name="items[<?php echo $item['ProductoID']; ?>][precio]" value="<?php echo $item['PrecioUnitario']; ?>">
                <?php endforeach; ?>
                <input type="hidden" name="subtotal" value="<?php echo $subtotal; ?>">
                <input type="hidden" name="envio" value="<?php echo ($subtotal > 100) ? 0 : 0; ?>">
                <input type="hidden" name="total" value="<?php echo $subtotal + (($subtotal > 100) ? 0 : 0); ?>">
            </form>

            <div class="resumen-compra">
                <h3>Resumen de Compra</h3>
                <div class="detalle-resumen">
                    <div class="fila-resumen">
                        <span>Subtotal:</span>
                        <span id="subtotal">S/ <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="fila-resumen">
                        <span>Envío:</span>
                        <span id="envio">S/ <?php echo number_format(($subtotal > 100) ? 0 : 0.00, 2); ?></span>
                    </div>
                    <div class="fila-resumen total">
                        <span>Total:</span>
                        <span id="total">S/ <?php echo number_format($subtotal + (($subtotal > 100) ? 0 : 00.00), 2); ?></span>
                    </div>
                </div>
                <button type="button" class="boton-pagar" id="procesar-reserva">Procesar Reserva</button>
                <a href="../productos.php" class="seguir-comprando">Seguir comprando</a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="contenedor-footer">
            <div class="info-contacto">
                <h3>Contacto</h3>
                <p>Calle Tupac Amaru 155-A, Mercado San Pedro,Cusco</p>
                <p>Teléfono: 987 963 921</p>
                <p>Gmail: aranzabal155a@gmail.com</p>
            </div>
            <div class="enlaces-rapidos">
                <h3>Enlaces rápidos</h3>
                <ul>
                    <li><a href="preguntas-frecuentes.html">Preguntas Frecuentes</a></li>
                    <li><a href="../terminos_y_condiciones.html">Términos y Condiciones</a></li>
                    <li><a href="../politica_privacidad.html">Política de Privacidad</a></li>
                </ul>
            </div>
            <div class="redes-sociales">
                <h3>Síguenos</h3>
                <div class="iconos-redes">
                    <a href="#"><img src="../../archivos_estaticos/img/iconfb.png" alt="Facebook"></a>
                    <a href="#"><img src="../../archivos_estaticos/img/iconig.webp" alt="Instagram"></a>
                    <a href="#"><img src="../../archivos_estaticos/img/iconwsp.webp" alt="WhatsApp"></a>
                </div>
            </div>
        </div>
        <div class="derechos-autor">
            <p>2025 Aranzábal. Todos los derechos reservados.</p>
        </div>
    </footer>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const botonMenu = document.getElementById('boton-menu');
        const navMenu = document.getElementById('nav-menu');

        if (botonMenu && navMenu) {
            botonMenu.addEventListener('click', () => {
                // Alterna la clase 'menu-abierto' en la lista del menú
                navMenu.classList.toggle('menu-abierto');
                
                // Opcional: Animar el botón de hamburguesa a una 'X'
                botonMenu.classList.toggle('activo');
            });
        }
    });
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Función para actualizar el contador del header al cargar la página
    function actualizarContadorHeader() {
        let totalItems = 0;
        document.querySelectorAll('.item-carrito .cantidad').forEach(cantidadSpan => {
            totalItems += parseInt(cantidadSpan.textContent);
        });
        const contadorElemento = document.getElementById('contador-carrito');
        if (contadorElemento) {
            contadorElemento.textContent = totalItems;
        }
    }

    // Función unificada para enviar actualizaciones al servidor
    function enviarActualizacion(carritoId, nuevaCantidad) {
        // Muestra un indicador de carga (opcional, pero buena práctica)
        document.body.style.cursor = 'wait';

        fetch('../../controladores/actualizar_carrito.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `carrito_id=${carritoId}&cantidad=${nuevaCantidad}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // La forma más robusta de asegurar que todo esté sincronizado: recargar la página.
                location.reload(); 
            } else {
                // Si el servidor devuelve un error, lo mostramos.
                alert('Error: ' + data.message);
                document.body.style.cursor = 'default';
            }
        })
        .catch(error => {
            console.error('Error de comunicación:', error);
            alert('No se pudo comunicar con el servidor. Inténtalo de nuevo.');
            document.body.style.cursor = 'default';
        });
    }

    // Asignar eventos a los botones de AUMENTAR cantidad
    document.querySelectorAll('.aumentar-cantidad').forEach(button => {
        button.addEventListener('click', function() {
            const itemDiv = this.closest('.item-carrito');
            const carritoId = itemDiv.getAttribute('data-carrito-id');
            const cantidadSpan = itemDiv.querySelector('.cantidad');
            const nuevaCantidad = parseInt(cantidadSpan.textContent) + 1;
            
            enviarActualizacion(carritoId, nuevaCantidad);
        });
    });

    // Asignar eventos a los botones de DISMINUIR cantidad
    document.querySelectorAll('.disminuir-cantidad').forEach(button => {
        button.addEventListener('click', function() {
            const itemDiv = this.closest('.item-carrito');
            const carritoId = itemDiv.getAttribute('data-carrito-id');
            const cantidadSpan = itemDiv.querySelector('.cantidad');
            const nuevaCantidad = parseInt(cantidadSpan.textContent) - 1;

            // Si la cantidad llega a 0, se eliminará
            enviarActualizacion(carritoId, nuevaCantidad);
        });
    });

    // Asignar eventos a los botones de ELIMINAR producto
    document.querySelectorAll('.eliminar-producto').forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('¿Estás seguro de que quieres eliminar este producto del carrito?')) {
                const itemDiv = this.closest('.item-carrito');
                const carritoId = itemDiv.getAttribute('data-carrito-id');
                
                // Para eliminar, enviamos una cantidad de 0
                enviarActualizacion(carritoId, 0);
            }
        });
    });

    // Evento para el botón de procesar la reserva
    const procesarReservaBtn = document.getElementById('procesar-reserva');
    if (procesarReservaBtn) {
        procesarReservaBtn.addEventListener('click', function() {
            if (confirm('¿Estás seguro de que deseas procesar esta reserva?')) {
                this.disabled = true;
                this.textContent = 'Procesando...';
                document.getElementById('form-reserva').submit();
            }
        });
    }

    // Llamada inicial para establecer el contador del header
    actualizarContadorHeader();
});
</script>
</body>
</html>