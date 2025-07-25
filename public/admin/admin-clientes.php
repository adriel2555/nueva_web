<?php
session_start();
if (!isset($_SESSION['es_admin']) || $_SESSION['es_admin'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Incluir archivo de conexión
require_once '../../configuracion/conexion.php';

// Obtener lista de clientes
$clientes = [];
$sql = "SELECT UsuarioID, Nombre, Apellido, Email, Telefono, EsAdministrador FROM Usuarios";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $clientes[] = $row;
    }
}

// Procesar formulario de cliente
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['guardar_cliente'])) {
        $usuarioId = $_POST['clienteId'] ?? '';
        $nombre = $_POST['nombre'] ?? '';
        $apellido = $_POST['apellido'] ?? '';
        $email = $_POST['email'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $ciudad = $_POST['ciudad'] ?? '';
        $departamento = $_POST['departamento'] ?? '';
        $codigoPostal = $_POST['codigoPostal'] ?? '';
        $estado = $_POST['estado'] ?? 1;
        
        if (empty($usuarioId)) {
            // Insertar nuevo cliente
            $stmt = $conn->prepare("INSERT INTO Usuarios (Nombre, Apellido, Email, Telefono, Direccion, Ciudad, Departamento, CodigoPostal, EsAdministrador) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssi", $nombre, $apellido, $email, $telefono, $direccion, $ciudad, $departamento, $codigoPostal, $estado);
        } else {
            // Actualizar cliente existente
            $stmt = $conn->prepare("UPDATE Usuarios SET Nombre=?, Apellido=?, Email=?, Telefono=?, Direccion=?, Ciudad=?, Departamento=?, CodigoPostal=?, EsAdministrador=? WHERE UsuarioID=?");
            $stmt->bind_param("ssssssssii", $nombre, $apellido, $email, $telefono, $direccion, $ciudad, $departamento, $codigoPostal, $estado, $usuarioId);
        }
        
        if ($stmt->execute()) {
            $mensajeExito = "Cliente " . (empty($usuarioId) ? "creado" : "actualizado") . " correctamente.";
            // Recargar lista de clientes
            $result = $conn->query($sql);
            $clientes = [];
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $clientes[] = $row;
                }
            }
        } else {
            $mensajeError = "Error al guardar el cliente: " . $conn->error;
        }
        $stmt->close();
    } elseif (isset($_POST['eliminar_cliente'])) {
        $usuarioId = $_POST['clienteId'] ?? '';
        $stmt = $conn->prepare("DELETE FROM Usuarios WHERE UsuarioID=?");
        $stmt->bind_param("i", $usuarioId);
        if ($stmt->execute()) {
            $mensajeExito = "Cliente eliminado correctamente.";
            // Recargar lista de clientes
            $result = $conn->query($sql);
            $clientes = [];
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $clientes[] = $row;
                }
            }
        } else {
            $mensajeError = "Error al eliminar el cliente: " . $conn->error;
        }
        $stmt->close();
    } elseif (isset($_POST['cambiar_contrasena'])) {
        $usuarioId = $_POST['clienteId'] ?? '';
        $nuevaContrasena = password_hash($_POST['nueva_contrasena'], PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE Usuarios SET ContrasenaHash=? WHERE UsuarioID=?");
        $stmt->bind_param("si", $nuevaContrasena, $usuarioId);
        if ($stmt->execute()) {
            $mensajeExito = "Contraseña actualizada correctamente.";
        } else {
            $mensajeError = "Error al actualizar la contraseña: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes | Aranzábal</title>
    <link rel="stylesheet" href="../../public/css/admin_clientes.css">
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
                    <li><a href="admin-pedidos.php"><i class="fas fa-shopping-cart"></i> <span>Pedidos  / Reservas</span></a></li>
                    <li><a href="admin-clientes.php" class="activo"><i class="fas fa-users"></i> <span>Clientes</span></a></li>
                    <li><a href="admin-inventario.php"><i class="fas fa-warehouse"></i> <span>Inventario</span></a></li>
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
                    <input type="text" placeholder="Buscar cliente..." id="inputBuscar">
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div class="usuario-admin">
                    <div class="avatar-usuario">A</div>
                    <span>Administrador</span>
                </div>
            </header>

            <div class="contenido-principal-admin">
                <h1>
                    Gestión de Clientes
                    <button class="boton-principal" id="btnNuevoCliente">
                        <i class="fas fa-plus"></i> Nuevo Cliente
                    </button>
                </h1>
                
                <?php if (isset($mensajeExito)): ?>
                <div class="mensaje-exito mensaje mostrar" id="mensajeExito">
                    <?php echo $mensajeExito; ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($mensajeError)): ?>
                <div class="mensaje-error mensaje mostrar" id="mensajeError">
                    <?php echo $mensajeError; ?>
                </div>
                <?php endif; ?>
                
                <div class="formulario-cliente <?php echo isset($_POST['clienteId']) ? '' : 'oculto'; ?>" id="formularioCliente">
                    <form id="clienteForm" method="POST">
                        <input type="hidden" id="clienteId" name="clienteId">
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="nombre">Nombre</label>
                                    <input type="text" id="nombre" name="nombre" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="apellido">Apellido</label>
                                    <input type="text" id="apellido" name="apellido" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Correo Electrónico</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono">
                        </div>
                        
                        <div class="form-group">
                            <label for="direccion">Dirección</label>
                            <input type="text" id="direccion" name="direccion">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="ciudad">Ciudad</label>
                                    <input type="text" id="ciudad" name="ciudad">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="departamento">Departamento</label>
                                    <input type="text" id="departamento" name="departamento">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="codigoPostal">Código Postal</label>
                                    <input type="text" id="codigoPostal" name="codigoPostal">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <select id="estado" name="estado">
                                <option value="1">Admin</option>
                                <option value="0">Cliente</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="boton-principal" name="guardar_cliente">Guardar Cliente</button>
                        <button type="button" class="boton-secundario" id="btnCancelar">Cancelar</button>
                    </form>
                </div>

                <div class="formulario-contrasena" id="formularioContrasena">
                    <form id="contrasenaForm" method="POST">
                        <input type="hidden" id="clienteIdContrasena" name="clienteId">
                        <div class="form-group">
                            <label for="nueva_contrasena">Nueva Contraseña</label>
                            <input type="password" id="nueva_contrasena" name="nueva_contrasena" required>
                        </div>
                        <div class="form-group">
                            <label for="confirmar_contrasena">Confirmar Contraseña</label>
                            <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" required>
                        </div>
                        <button type="submit" class="boton-principal" name="cambiar_contrasena">Cambiar Contraseña</button>
                        <button type="button" class="boton-secundario" id="btnCancelarContrasena">Cancelar</button>
                    </form>
                </div>

                <div class="tabla-clientes">
                    <h2>Lista de Clientes</h2>
                    <table class="tabla-datos">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Apellido</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo $cliente['UsuarioID']; ?></td>
                                <td><?php echo htmlspecialchars($cliente['Nombre']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['Apellido']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['Email']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['Telefono']); ?></td>
                                <td>
                                    <span class="estado-cliente <?php echo $cliente['EsAdministrador'] ? 'Admin' : 'Cliente'; ?>">
                                        <?php echo $cliente['EsAdministrador'] ? 'Admin' : 'Cliente'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="boton-accion editar" title="Editar" data-id="<?php echo $cliente['UsuarioID']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="boton-accion contrasena" title="Cambiar contraseña" data-id="<?php echo $cliente['UsuarioID']; ?>">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button class="boton-accion eliminar" title="Eliminar" data-id="<?php echo $cliente['UsuarioID']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formCliente = document.getElementById('clienteForm');
            const formContrasena = document.getElementById('contrasenaForm');
            const btnNuevoCliente = document.getElementById('btnNuevoCliente');
            const btnCancelar = document.getElementById('btnCancelar');
            const btnCancelarContrasena = document.getElementById('btnCancelarContrasena');
            const mensajeExito = document.getElementById('mensajeExito');
            const mensajeError = document.getElementById('mensajeError');
            const formularioCliente = document.getElementById('formularioCliente');
            const formularioContrasena = document.getElementById('formularioContrasena');
            const inputBuscar = document.getElementById('inputBuscar');
            
            // Ocultar mensajes después de 5 segundos
            setTimeout(() => {
                if (mensajeExito) mensajeExito.classList.remove('mostrar');
                if (mensajeError) mensajeError.classList.remove('mostrar');
            }, 5000);
            
            // Mostrar formulario para nuevo cliente
            btnNuevoCliente.addEventListener('click', function() {
                formCliente.reset();
                document.getElementById('clienteId').value = '';
                formularioContrasena.classList.remove('mostrar');
                formularioCliente.classList.remove('oculto');
                formularioCliente.scrollIntoView({behavior: 'smooth'});
            });
            
            // Cancelar edición
            btnCancelar.addEventListener('click', function() {
                formCliente.reset();
                document.getElementById('clienteId').value = '';
                formularioCliente.classList.add('oculto');
            });
            
            // Cancelar cambio de contraseña
            btnCancelarContrasena.addEventListener('click', function() {
                formContrasena.reset();
                document.getElementById('clienteIdContrasena').value = '';
                formularioContrasena.classList.remove('mostrar');
            });
            
            // Editar cliente
            document.querySelectorAll('.editar').forEach(btn => {
                btn.addEventListener('click', function() {
                    const clienteId = this.getAttribute('data-id');
                    const row = this.closest('tr');
                    document.getElementById('clienteId').value = clienteId;
                    document.getElementById('nombre').value = row.cells[1].textContent;
                    document.getElementById('apellido').value = row.cells[2].textContent;
                    document.getElementById('email').value = row.cells[3].textContent;
                    document.getElementById('telefono').value = row.cells[4].textContent;
                    
                    const estado = row.cells[5].textContent.trim() === 'Admin' ? '1' : '0';
                    document.getElementById('estado').value = estado;
                    
                    formularioContrasena.classList.remove('mostrar');
                    formularioCliente.classList.remove('oculto');
                    formularioCliente.scrollIntoView({behavior: 'smooth'});
                });
            });
            
            // Cambiar contraseña
            document.querySelectorAll('.contrasena').forEach(btn => {
                btn.addEventListener('click', function() {
                    const clienteId = this.getAttribute('data-id');
                    document.getElementById('clienteIdContrasena').value = clienteId;
                    
                    formularioCliente.classList.add('oculto');
                    formularioContrasena.classList.add('mostrar');
                    formularioContrasena.scrollIntoView({behavior: 'smooth'});
                });
            });
            
            // Eliminar cliente
            document.querySelectorAll('.eliminar').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (confirm('¿Está seguro de eliminar este cliente?')) {
                        const clienteId = this.getAttribute('data-id');
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        
                        const inputId = document.createElement('input');
                        inputId.type = 'hidden';
                        inputId.name = 'clienteId';
                        inputId.value = clienteId;
                        form.appendChild(inputId);
                        
                        const inputEliminar = document.createElement('input');
                        inputEliminar.type = 'hidden';
                        inputEliminar.name = 'eliminar_cliente';
                        inputEliminar.value = '1';
                        form.appendChild(inputEliminar);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
            
            // Validar contraseñas coincidan
            formContrasena.addEventListener('submit', function(e) {
                const nuevaContrasena = document.getElementById('nueva_contrasena').value;
                const confirmarContrasena = document.getElementById('confirmar_contrasena').value;
                
                if (nuevaContrasena !== confirmarContrasena) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden');
                }
            });
            
            // Buscar clientes
            inputBuscar.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.tabla-datos tbody tr');
                
                rows.forEach(row => {
                    const nombre = row.cells[1].textContent.toLowerCase();
                    const apellido = row.cells[2].textContent.toLowerCase();
                    const email = row.cells[3].textContent.toLowerCase();
                    
                    if (nombre.includes(searchTerm) || apellido.includes(searchTerm) || email.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>