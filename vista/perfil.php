<?php
session_start();
require_once '../configuracion/conexion.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['email'])) {
    header("Location: autenticacion/iniciar-sesion.html");
    exit();
}

// Procesar cambio de contraseña si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $email = $_SESSION['email'];
    $password_actual = $_POST['password_actual'];
    $nueva_password = $_POST['nueva_password'];
    $confirmar_password = $_POST['confirmar_password'];
    
    // Validaciones
    if ($nueva_password !== $confirmar_password) {
        $error_password = "Las contraseñas nuevas no coinciden";
    } else {
        // Verificar contraseña actual
        $stmt = $conn->prepare("SELECT ContrasenaHash FROM Usuarios WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuario = $result->fetch_assoc();
        
        if (password_verify($password_actual, $usuario['ContrasenaHash'])) {
            // Actualizar contraseña
            $nueva_password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE Usuarios SET ContrasenaHash = ? WHERE Email = ?");
            $stmt->bind_param("ss", $nueva_password_hash, $email);
            
            if ($stmt->execute()) {
                $success_password = "Contraseña actualizada correctamente";
            } else {
                $error_password = "Error al actualizar la contraseña";
            }
        } else {
            $error_password = "La contraseña actual es incorrecta";
        }
    }
}

// Obtener datos del usuario
$email = $_SESSION['email'];
$usuario = [];
$pedidos = [];

try {
    // Consulta para obtener información del usuario
    $stmt = $conn->prepare("SELECT * FROM Usuarios WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    
    // Consulta para obtener los pedidos del usuario
    $stmt = $conn->prepare("SELECT p.* FROM Pedidos p 
                          JOIN Usuarios u ON p.UsuarioID = u.UsuarioID 
                          WHERE u.Email = ? 
                          ORDER BY p.FechaPedido DESC");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Consulta para obtener el carrito del usuario
    $stmt = $conn->prepare("SELECT c.*, p.NombreProducto, p.UrlImagen 
                          FROM Carrito c 
                          JOIN Productos p ON c.ProductoID = p.ProductoID 
                          WHERE c.UsuarioID = ?");
    $stmt->bind_param("i", $usuario['UsuarioID']);
    $stmt->execute();
    $carrito = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calcular total del carrito
    $totalCarrito = 0;
    foreach ($carrito as $item) {
        $totalCarrito += $item['PrecioUnitario'] * $item['Cantidad'];
    }
    
} catch (Exception $e) {
    $error = "Error al cargar los datos del perfil: " . $e->getMessage();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Aranzábal</title>
    <link rel="stylesheet" href="../archivos_estaticos/css/estilos.css">
    <link rel="stylesheet" href="../archivos_estaticos/css/responsivo.css">
    <style>
        /* Estilos específicos para la página de perfil */
        .contenedor-perfil {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
        }
        
        .menu-perfil {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        
        .menu-perfil ul {
            list-style: none;
            padding: 0;
        }
        
        .menu-perfil li {
            margin-bottom: 1rem;
        }
        
        .menu-perfil a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4a148c;
            padding: 0.5rem;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .menu-perfil a:hover, .menu-perfil a.activo {
            background: #f3e5f5;
            color: #7b1fa2;
            text-decoration: none;
        }
        
        .menu-perfil img {
            width: 20px;
            height: 20px;
        }
        
        .contenido-perfil {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        
        .seccion-perfil {
            display: none;
        }
        
        .seccion-perfil.activo {
            display: block;
        }
        
        .info-usuario {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e1bee7;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4a148c;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .datos-usuario h2 {
            margin: 0;
            color: #4a148c;
        }
        
        .datos-usuario p {
            margin: 0.5rem 0 0;
            color: #666;
        }
        
        .tarjeta-info {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .tarjeta-info h3 {
            margin-top: 0;
            color: #4a148c;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #4a148c;
            font-weight: bold;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
        }
        
        .btn-guardar {
            background: #7e57c2;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 30px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .btn-guardar:hover {
            background: #5e35b1;
        }
        
        .tabla-pedidos {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tabla-pedidos th, .tabla-pedidos td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .tabla-pedidos th {
            background: #f3e5f5;
            color: #4a148c;
        }
        
        .estado-pedido {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .estado-pendiente {
            background: #fff3e0;
            color: #e65100;
        }

        .estado-procesando {
            background: #e3f2fd;
            color: #1565c0;
        }

        .estado-enviado {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .estado-entregado {
            background: #f1f8e9;
            color: #558b2f;
        }

        .estado-cancelado {
            background: #ffebee;
            color: #c62828;
        }

        .estado-en-camino {
            background: #fff8e1;
            color: #ff8f00;
        }
        
        .item-carrito {
            display: grid;
            grid-template-columns: 100px 1fr 100px 100px 100px;
            gap: 1rem;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .imagen-producto-carrito {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .cantidad-producto-carrito input {
            width: 50px;
            text-align: center;
            padding: 0.25rem;
        }
        
        .eliminar-producto {
            color: #c62828;
            cursor: pointer;
        }
        
        .resumen-carrito {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .total-carrito {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 8px;
        }
        
        .total-carrito h3 {
            margin-top: 0;
            color: #4a148c;
        }
        
        .btn-pagar {
            background: #4caf50;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 30px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            margin-top: 1rem;
            transition: background 0.3s;
        }
        
        .btn-pagar:hover {
            background: #388e3c;
        }
        
        @media (max-width: 768px) {
            .contenedor-perfil {
                grid-template-columns: 1fr;
            }
            
            .resumen-carrito {
                grid-template-columns: 1fr;
            }
            
            .item-carrito {
                grid-template-columns: 1fr;
            }
        }
        
    .seccion-perfil {
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease-in-out;
    }
    
    .seccion-perfil.activo {
        display: block;
        opacity: 1;
    }
    
    .menu-perfil a.activo {
        background: #f3e5f5;
        color: #7b1fa2;
        text-decoration: none;
        font-weight: bold;
    }

    /* Asegurar que el footer esté en la parte inferior */
body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}



footer {
    margin-top: auto;
    padding: 2rem 0;
}

/* Estilos para el modal de detalles de pedido */
.modal-pedido {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-pedido {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-pedido.mostrar {
    display: block;
    opacity: 1;
}

.modal-contenido {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    width: 80%;
    max-width: 800px;
    position: relative;
    animation: animarModal 0.3s;
}

@keyframes animarModal {
    from {transform: translateY(-50px); opacity: 0;}
    to {transform: translateY(0); opacity: 1;}
}

.cerrar-modal {
    position: absolute;
    right: 1.5rem;
    top: 1rem;
    font-size: 2rem;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    transition: color 0.3s;
}

.cerrar-modal:hover {
    color: #333;
}

.info-pedido {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.info-pedido p {
    margin: 0.5rem 0;
}

.tabla-articulos {
    margin: 1.5rem 0;
    overflow-x: auto;
}

.tabla-articulos table {
    width: 100%;
    border-collapse: collapse;
}

.tabla-articulos th, .tabla-articulos td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.tabla-articulos th {
    background-color: #f3e5f5;
    color: #4a148c;
}

.acciones-pedido {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}

@media (max-width: 768px) {
    .modal-contenido {
        width: 95%;
        margin: 10% auto;
        padding: 1.5rem;
    }
    
    .acciones-pedido {
        flex-direction: column;
    }
    
    .acciones-pedido button {
        width: 100%;
    }
}

</style>
</head>
<body>
        <header>
        <div class="contenedor-logo">
            <img src="../archivos_estaticos/img/diamanteblanco.png" alt="Joyitas Felices" class="logo">
            <h1>Aranzábal</h1>
        </div>
        <button id="boton-menu" class="boton-menu" aria-label="Abrir menú">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav id="nav-principal">
            <ul id="nav-menu">
                <li><a href="index.php">Inicio</a></li>
                <li><a href="productos.php">Productos</a></li>
                <li><a href="nosotros.php">Nosotros</a></li>
                <li><a href="contacto.php">Contacto</a></li>

                <?php if(isset($_SESSION['email'])): ?>
                <li class="menu-usuario">
                    <a href="perfil.php" class="enlace-autenticacion">
                        <?php echo $_SESSION['email']; ?>
                    </a>
                    <ul class="submenu">
                        <li><a href="perfil.php">Mi Perfil</a></li>
                        <li><a href="../controladores/cerrar_sesion.php">Cerrar Sesión</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li><a href="autenticacion/iniciar-sesion.html" class="enlace-autenticacion">Iniciar Sesión</a></li>
                <?php endif; ?>

                <li><a href="carrito/carrito.php" class="enlace-carrito">Carrito (<span id="contador-carrito">0</span>)</a>
                </li>
            </ul>
        </nav>
    </header>
    
    <main class="contenedor-perfil">
        <aside class="menu-perfil">
            <div class="info-usuario">
                <div class="avatar">
                    <?php echo strtoupper(substr($usuario['Nombre'], 0, 1)) . strtoupper(substr($usuario['Apellido'], 0, 1)); ?>
                </div>
                <div class="datos-usuario">
                    <h2><?php echo htmlspecialchars($usuario['Nombre'] ). ' ' . htmlspecialchars($usuario['Apellido']); ?></h2>
                    <p><?php echo htmlspecialchars($usuario['Email']); ?></p>
                </div>
            </div>
            
            <ul>
                <li><a href="#informacion" class="activo" data-seccion="informacion">Información personal</a></li>
                <li><a href="#pedido" data-seccion="pedidos">Mis pedido / reservas</a></li>
                <li><a href="#seguridad" data-seccion="seguridad">Seguridad</a></li>
                <li><a href="../controladores/cerrar_sesion.php">Cerrar sesión</a></li>
            </ul>
        </aside>
        
        <div class="contenido-perfil">
            <!-- Sección de Información Personal -->
            <section id="informacion" class="seccion-perfil activo">
                <h2>Información personal</h2>
                <form id="form-info-personal" action="actualizar_perfil.php" method="POST">
                    <div class="tarjeta-info">
                        <h3>Datos básicos</h3>
                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($usuario['Nombre']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="apellido">Apellido</label>
                            <input type="text" id="apellido" name="apellido" value="<?php echo htmlspecialchars($usuario['Apellido']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Correo electrónico</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario['Email']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" value="<?php echo htmlspecialchars($usuario['Telefono']); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-guardar">Guardar cambios</button>
                </form>
            </section>
            
            <!-- Sección de Pedidos -->
            <section id="pedidos" class="seccion-perfil">
                <h2>Mis pedidos reservas</h2>
                <?php if (count($pedidos) > 0): ?>
                    <table class="tabla-pedidos">
                        <thead>
                            <tr>
                                <th>N° Pedido</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td>#<?php echo $pedido['PedidoID']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($pedido['FechaPedido'])); ?></td>
                                    <td>S/ <?php echo number_format($pedido['MontoTotal'], 2); ?></td>
                                    <td>
                                        <span class="estado-pedido estado-<?php echo strtolower($pedido['EstadoPedido']); ?>">
                                            <?php echo $pedido['EstadoPedido']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="#" class="btn-guardar ver-detalle-pedido" style="padding: 0.25rem 0.5rem;" data-pedido-id="<?php echo $pedido['PedidoID']; ?>">Ver detalle</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No tienes pedidos realizados aún.</p>
                    <a href="productos.php" class="boton-principal">Ver productos</a>
                <?php endif; ?>
            </section>
            
            
            <!-- Sección de Seguridad -->
<section id="seguridad" class="seccion-perfil">
                <h2>Seguridad</h2>
                <div class="tarjeta-info">
                    <h3>Cambiar contraseña</h3>
                    
                    <?php if (isset($error_password)): ?>
                        <div class="mensaje-error" style="color: red; margin-bottom: 1rem;">
                            <?php echo $error_password; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success_password)): ?>
                        <div class="mensaje-exito" style="color: green; margin-bottom: 1rem;">
                            <?php echo $success_password; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form id="form-cambiar-password" method="POST">
                        <input type="hidden" name="cambiar_password" value="1">
                        
                        <div class="form-group">
                            <label for="password_actual">Contraseña actual</label>
                            <input type="password" id="password_actual" name="password_actual" required 
                                   minlength="8" placeholder="Ingresa tu contraseña actual">
                        </div>
                        
                        <div class="form-group">
                            <label for="nueva_password">Nueva contraseña</label>
                            <input type="password" id="nueva_password" name="nueva_password" required
                                   minlength="8" placeholder="Mínimo 8 caracteres">
                            <small style="display: block; margin-top: 5px; color: #666;">
                                La contraseña debe tener al menos 8 caracteres
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmar_password">Confirmar nueva contraseña</label>
                            <input type="password" id="confirmar_password" name="confirmar_password" required
                                   minlength="8" placeholder="Repite tu nueva contraseña">
                        </div>
                        
                        <button type="submit" class="btn-guardar">Cambiar contraseña</button>
                    </form>
            </section>
        </div>
    </main>  

    <footer>
        <div class="contenedor-footer">
            <div class="info-contacto">
                <h3>Contacto</h3>
                <p>Calle Tupac Amaru 155-A, Mercado San Pedro, Cusco</p>
                <p>Teléfono: 987 963 921</p>
                <p>Gmail:</p>
            </div>
            <div class="enlaces-rapidos">
                <h3>Enlaces rápidos</h3>
                <ul>
                    <li><a href="preguntas-frecuentes.html" target="_blank">Términos de reserva</a></li>
                    <li><a href="terminos_y_condiciones.html" target="_blank">Términos y Condiciones</a></li>
                    <li><a href="politica_privacidad.html" target="_blank">Política de Privacidad</a></li>
                </ul>
            </div>
            <div class="redes-sociales">
                <h3>Síguenos</h3>
                <div class="iconos-redes">
                    <a href="#"><img src="../archivos_estaticos/img/iconfb.png" alt="Facebook"></a>
                    <a href="#"><img src="../archivos_estaticos/img/iconig.webp" alt="Instagram"></a>
                    <a href="#"><img src="../archivos_estaticos/img/iconwsp.webp" alt="WhatsApp"></a>
                </div>
            </div>
        </div>
        <div class="derechos-autor">
            <p>2025 Aranzábal. Todos los derechos reservados.</p>
        </div>
    </footer>

<div id="modal-detalle-pedido" class="modal-pedido">
    <div class="modal-contenido">
        <span class="cerrar-modal">&times;</span>
        <h2>Detalles del Pedido #<span id="pedido-id"></span></h2>
        <div class="info-pedido">
            <p><strong>Fecha:</strong> <span id="pedido-fecha"></span></p>
            <p><strong>Estado:</strong> <span id="pedido-estado" class="estado-pedido"></span></p>
            <p><strong>Total:</strong> S/ <span id="pedido-total"></span></p>
            <p><strong>Dirección de envío:</strong> <span id="pedido-direccion"></span></p>
            <p><strong>Método de pago:</strong> <span id="pedido-pago"></span></p>
        </div>
        
        <h3>Artículos del pedido</h3>
        <div class="tabla-articulos">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio unitario</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody id="pedido-articulos">
                    <!-- Los artículos se cargarán aquí -->
                </tbody>
            </table>
        </div>
        
        <div class="acciones-pedido">
            <a href="contacto.php"><button id="imprimir-pedido" class="btn-guardar">Ubicanos</button></a>
        </div>
    </div>
</div>
    <script src="../archivos_estaticos/js/principal.js"></script>
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
    // Versión mejorada con navegación tipo Dota 2
    document.addEventListener('DOMContentLoaded', function() {
        const menuLinks = document.querySelectorAll('.menu-perfil a[data-seccion]');
        const sections = document.querySelectorAll('.seccion-perfil');
        let currentSectionIndex = 0;
        let isScrolling = false;
        
        // Mapeo de secciones a índices
        const sectionIds = Array.from(sections).map(section => section.id);
        
        // Función para cambiar de sección
        function showSection(sectionId) {
            if (isScrolling) return;
            isScrolling = true;
            
            // Encontrar índice de la sección
            const sectionIndex = sectionIds.indexOf(sectionId);
            if (sectionIndex === -1) return;
            
            currentSectionIndex = sectionIndex;
            
            // Ocultar todas las secciones
            sections.forEach(section => {
                section.classList.remove('activo');
            });
            
            // Mostrar la sección seleccionada
            sections[sectionIndex].classList.add('activo');
            
            // Actualizar estado activo en los enlaces del menú
            menuLinks.forEach(link => {
                link.classList.remove('activo');
                if (link.getAttribute('data-seccion') === sectionId) {
                    link.classList.add('activo');
                }
            });
            
            // Actualizar la URL sin recargar
            history.pushState(null, null, `#${sectionId}`);
            
            // Pequeño retraso para evitar scroll rápido
            setTimeout(() => {
                isScrolling = false;
            }, 500);
        }
        
        // Manejar clics en los enlaces del menú
        menuLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const sectionId = this.getAttribute('data-seccion');
                showSection(sectionId);
            });
        });
        
        // Manejar scroll de rueda del mouse
        document.querySelector('.contenido-perfil').addEventListener('wheel', function(e) {
            if (isScrolling) {
                e.preventDefault();
                return;
            }
            
            // Determinar dirección del scroll
            if (e.deltaY > 0) {
                // Scroll hacia abajo - siguiente sección
                if (currentSectionIndex < sections.length - 1) {
                    showSection(sectionIds[currentSectionIndex + 1]);
                    e.preventDefault();
                }
            } else {
                // Scroll hacia arriba - sección anterior
                if (currentSectionIndex > 0) {
                    showSection(sectionIds[currentSectionIndex - 1]);
                    e.preventDefault();
                }
            }
        });
        
        // Cargar sección basada en el hash de la URL al inicio
        const initialSection = window.location.hash.substring(1) || 'informacion';
        showSection(initialSection);
        
        // Manejar cambios en el historial (botones atrás/adelante)
        window.addEventListener('popstate', function() {
            const sectionFromHash = window.location.hash.substring(1);
            if (sectionFromHash) {
                showSection(sectionFromHash);
            }
        });
        
        // Manejar teclas de flecha
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown' && currentSectionIndex < sections.length - 1) {
                showSection(sectionIds[currentSectionIndex + 1]);
                e.preventDefault();
            } else if (e.key === 'ArrowUp' && currentSectionIndex > 0) {
                showSection(sectionIds[currentSectionIndex - 1]);
                e.preventDefault();
            }
        });
    });

    // Resto del código (validación de contraseña, carrito, etc.) se mantiene igual
    document.getElementById('form-cambiar-password')?.addEventListener('submit', function(e) {
        const nueva = document.getElementById('nueva_password').value;
        const confirmar = document.getElementById('confirmar_password').value;
        
        if (nueva !== confirmar) {
            e.preventDefault();
            alert('Las contraseñas nuevas no coinciden');
            return false;
        }
        
        if (nueva.length < 8) {
            e.preventDefault();
            alert('La contraseña debe tener al menos 8 caracteres');
            return false;
        }
        
        return true;
    });



            document.addEventListener('DOMContentLoaded', function() {
        // Agregar productos al carrito (solo para usuarios logueados)
        document.querySelectorAll('.boton-agregar-carrito:not(.deshabilitado)').forEach(boton => {
            boton.addEventListener('click', function() {
                const productoId = this.dataset.id;
                const productoNombre = this.dataset.nombre;
                const productoPrecio = parseFloat(this.dataset.precio);
                const productoImagen = this.dataset.imagen;
                
                agregarAlCarrito(
                    productoId,
                    productoNombre,
                    productoPrecio,
                    productoImagen,
                    1
                );
                
                mostrarNotificacion(`${productoNombre} agregado al carrito`);
            });
        });
        
       /* // Para botones deshabilitados (redirigir a login)
        document.querySelectorAll('.boton-agregar-carrito.deshabilitado').forEach(boton => {
            boton.addEventListener('click', function(e) {
                e.preventDefault();
                // Redirigir a login con parámetro para volver después
                window.location.href = 'autenticacion/iniciar-sesion.html?redirect=' + 
                    encodeURIComponent(window.location.pathname + window.location.search);
            });
        });*/
        
        actualizarContadorCarrito();
    });
    
    // En productos.php, reemplaza la función agregarAlCarrito con esta versión mejorada:
    function agregarAlCarrito(productoId, nombre, precio, imagen, cantidad = 1) {
        // Verificar si el usuario está logueado (sesión PHP)
        <?php if(isset($_SESSION['email'])): ?>
            // Hacer llamada AJAX para guardar en la base de datos
            fetch('../controladores/agregar_carrito.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `producto_id=${productoId}&cantidad=${cantidad}`
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Actualizar el carrito en localStorage
                    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
                    
                    const productoExistente = carrito.find(item => item.id === productoId);
                    
                    if (productoExistente) {
                        productoExistente.cantidad += cantidad;
                    } else {
                        carrito.push({
                            id: productoId,
                            nombre: nombre,
                            precio: precio,
                            imagen: imagen,
                            cantidad: cantidad
                        });
                    }
                    
                    localStorage.setItem('carrito', JSON.stringify(carrito));
                    actualizarContadorCarrito();
                    mostrarNotificacion(`${nombre} agregado al carrito`);
                } else {
                    mostrarNotificacion('Error al agregar al carrito: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error al comunicarse con el servidor');
            });
        <?php else: ?>
            // Para usuarios no logueados, seguir usando solo localStorage
            let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
            
            const productoExistente = carrito.find(item => item.id === productoId);
            
            if (productoExistente) {
                productoExistente.cantidad += cantidad;
            } else {
                carrito.push({
                    id: productoId,
                    nombre: nombre,
                    precio: precio,
                    imagen: imagen,
                    cantidad: cantidad
                });
            }
            
            localStorage.setItem('carrito', JSON.stringify(carrito));
            actualizarContadorCarrito();
            mostrarNotificacion(`${nombre} agregado al carrito (local)`);
        <?php endif; ?>
    }
    
    function actualizarContadorCarrito() {
        <?php if(isset($_SESSION['email'])): ?>
            // Para usuarios logueados, obtener el conteo de la base de datos
            fetch('../controladores/contar_carrito.php')
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        const contador = document.getElementById('contador-carrito');
                        if (contador) {
                            contador.textContent = data.count;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error al obtener conteo del carrito:', error);
                    // Fallback a localStorage si hay error
                    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
                    const contador = document.getElementById('contador-carrito');
                    if (contador) {
                        contador.textContent = carrito.reduce((total, item) => total + item.cantidad, 0);
                    }
                });
        <?php else: ?>
            // Para usuarios no logueados, usar solo localStorage
            const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
            const contador = document.getElementById('contador-carrito');
            if (contador) {
                contador.textContent = carrito.reduce((total, item) => total + item.cantidad, 0);
            }
        <?php endif; ?>
    }
    
    function mostrarNotificacion(mensaje) {
        const notificacion = document.createElement('div');
        notificacion.className = 'notificacion';
        notificacion.textContent = mensaje;
        
        document.body.appendChild(notificacion);
        
        setTimeout(() => {
            notificacion.classList.add('mostrar');
        }, 10);
        
        setTimeout(() => {
            notificacion.classList.remove('mostrar');
            setTimeout(() => {
                document.body.removeChild(notificacion);
            }, 300);
        }, 3000);
    }
    
    // Función para mostrar el modal con los detalles del pedido
function mostrarDetallePedido(pedidoId) {
    const modal = document.getElementById('modal-detalle-pedido');
    modal.classList.add('mostrar');
    
    // Mostrar mensaje de carga
    document.getElementById('pedido-articulos').innerHTML = '<tr><td colspan="4" style="text-align: center;">Cargando detalles del pedido...</td></tr>';
    
    // Hacer petición AJAX al servidor
    fetch(`../controladores/obtener_detalle_pedido_usuario.php?id=${pedidoId}`)
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        if(!data.success) {
            throw new Error(data.message || 'Error al obtener los datos');
        }
        
        // Validar que los datos necesarios existen
        if(!data.pedido || !data.articulos) {
            throw new Error('Datos incompletos recibidos del servidor');
        }
        
        const pedido = data.pedido;
        const articulos = data.articulos;
        
        // Actualizar la información del pedido
        document.getElementById('pedido-id').textContent = pedido.PedidoID || 'N/A';
        
        // Formatear fecha
        const fechaPedido = pedido.FechaPedido ? new Date(pedido.FechaPedido) : null;
        document.getElementById('pedido-fecha').textContent = fechaPedido ? fechaPedido.toLocaleDateString() : 'N/A';
        
        // Actualizar estado con la clase adecuada
        const estadoElement = document.getElementById('pedido-estado');
        estadoElement.textContent = pedido.EstadoPedido || 'Desconocido';
        estadoElement.className = 'estado-pedido estado-' + (pedido.EstadoPedido ? pedido.EstadoPedido.toLowerCase().replace(' ', '-') : 'desconocido');
        
        // Monto total (asegurarse que es número)
        const montoTotal = typeof pedido.MontoTotal === 'number' ? pedido.MontoTotal.toFixed(2) : '0.00';
        document.getElementById('pedido-total').textContent = montoTotal;
        
        // Dirección de envío
        const direccion = pedido.DireccionEnvio || pedido.Direccion || 'No especificada';
        const ciudad = pedido.CiudadEnvio || pedido.Ciudad || '';
        document.getElementById('pedido-direccion').textContent = `${direccion}, ${ciudad}`.trim();
        
        // Método de pago
        document.getElementById('pedido-pago').textContent = pedido.MetodoPago || 'No especificado';
        
        // Actualizar los artículos del pedido
        const tbody = document.getElementById('pedido-articulos');
        tbody.innerHTML = '';
        
        if (articulos && articulos.length > 0) {
            articulos.forEach(articulo => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${articulo.NombreProducto || 'Producto no disponible'}</td>
                    <td>S/ ${typeof articulo.PrecioUnitario === 'number' ? articulo.PrecioUnitario.toFixed(2) : '0.00'}</td>
                    <td>${articulo.Cantidad || 0}</td>
                    <td>S/ ${typeof articulo.Subtotal === 'number' ? articulo.Subtotal.toFixed(2) : '0.00'}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">No se encontraron artículos en este pedido.</td></tr>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('pedido-articulos').innerHTML = `
            <tr>
                <td colspan="4" style="text-align: center; color: red;">
                    Error al cargar los detalles: ${error.message}
                </td>
            </tr>`;
    });
}

// Cerrar el modal al hacer clic en la X
document.querySelector('.cerrar-modal').addEventListener('click', function() {
    document.getElementById('modal-detalle-pedido').classList.remove('mostrar');
});

// Cerrar al hacer clic fuera del modal
window.addEventListener('click', function(event) {
    const modal = document.getElementById('modal-detalle-pedido');
    if (event.target === modal) {
        modal.classList.remove('mostrar');
    }
});

// Botón de imprimir
document.getElementById('imprimir-pedido').addEventListener('click', function() {
    window.print();
});

// Manejar clic en los botones "Ver detalle"
document.addEventListener('DOMContentLoaded', function() {
    // Delegación de eventos para manejar dinámicamente los botones
    document.querySelector('.contenido-perfil').addEventListener('click', function(e) {
        if (e.target.classList.contains('ver-detalle-pedido') || 
            e.target.closest('.ver-detalle-pedido')) {
            e.preventDefault();
            const boton = e.target.classList.contains('ver-detalle-pedido') ? 
                         e.target : e.target.closest('.ver-detalle-pedido');
            const pedidoId = boton.getAttribute('data-pedido-id');
            mostrarDetallePedido(pedidoId);
        }
    });
});
</script>

</body>
</html>