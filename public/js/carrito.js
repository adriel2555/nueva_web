document.addEventListener('DOMContentLoaded', function() {
    // Obtener elementos del DOM
    const listaProductosCarrito = document.querySelector('.lista-productos-carrito');
    const resumenCompra = document.querySelector('.resumen-compra');
    const contadorCarrito = document.getElementById('contador-carrito');

    // Obtener carrito de localStorage (para invitados) o usar el de la BD (para logueados)
    let carrito = [];

    // La variable 'isUserLoggedIn' y 'carritoDesdeBD' ahora vienen del HTML/PHP.
    if (isUserLoggedIn && carritoDesdeBD && carritoDesdeBD.length > 0) {
        // Si el usuario está logueado y tiene datos en la BD, esos son los datos principales.
        carrito = carritoDesdeBD;
    } else if (!isUserLoggedIn) {
        // Si es un invitado, usamos localStorage.
        carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    }

    // Mostrar productos en el carrito
    function mostrarCarrito() {
        if (carrito.length === 0) {
            listaProductosCarrito.innerHTML = `
                <div class="carrito-vacio">
                    <img src="../../archivos_estaticos/img/carrito.png" alt="Carrito vacío">
                    <h3>Tu carrito está vacío</h3>
                    <p>Agrega algunos productos para comenzar</p>
                    <a href="../productos.php" class="boton-ver-productos">Ver productos</a>
                </div>
            `;
            resumenCompra.style.display = 'none';
            actualizarContadorCarrito();
            return;
        }

        listaProductosCarrito.innerHTML = `
            <div class="encabezado-lista">
                <span>Producto</span>
                <span>Precio</span>
                <span>Cantidad</span>
                <span>Total</span>
            </div>
        `;

        let subtotal = 0;

        carrito.forEach(item => {
            const totalProducto = item.precio * item.cantidad;
            subtotal += totalProducto;

            const itemHTML = `
                <div class="item-carrito" data-id="${item.id}" data-carrito-id="${item.carrito_id || ''}">
                    <div class="info-producto-carrito">
                        <img src="${item.imagen}" alt="${item.nombre}" onerror="this.src='../../archivos_estaticos/img/producto-default.jpg'">
                        <div class="detalles-producto">
                            <h3>${item.nombre}</h3>
                            <button class="eliminar-producto">Eliminar</button>
                        </div>
                    </div>
                    <div class="precio-producto-carrito">
                        S/ ${item.precio.toFixed(2)}
                    </div>
                    <div class="cantidad-producto-carrito">
                        <button class="disminuir-cantidad">-</button>
                        <span class="cantidad">${item.cantidad}</span>
                        <button class="aumentar-cantidad">+</button>
                    </div>
                    <div class="total-producto-carrito">
                        S/ ${totalProducto.toFixed(2)}
                    </div>
                </div>
            `;
            listaProductosCarrito.insertAdjacentHTML('beforeend', itemHTML);
        });

        const envio = calcularEnvio(subtotal);
        const total = subtotal + envio;

        resumenCompra.innerHTML = `
            <h3>Resumen de Compra</h3>
            <div class="detalle-resumen">
                <div class="fila-resumen">
                    <span>Subtotal:</span>
                    <span>S/ ${subtotal.toFixed(2)}</span>
                </div>
                <div class="fila-resumen">
                    <span>Envío:</span>
                    <span>S/ ${envio.toFixed(2)}</span>
                </div>
                <div class="fila-resumen total">
                    <span>Total:</span>
                    <span>S/ ${total.toFixed(2)}</span>
                </div>
            </div>
            <button class="boton-pagar">Proceder Pedido</button>
            <a href="../productos.php" class="seguir-comprando">Seguir comprando</a>
        `;

        resumenCompra.style.display = 'block';
        agregarEventListenersCarrito();
        actualizarContadorCarrito();
    }

    function calcularEnvio(subtotal) {
        return subtotal > 100 ? 0 : 10.00;
    }

    function agregarEventListenersCarrito() {
    // 1. Listener para el botón ELIMINAR (este ya estaba bien)
    document.querySelectorAll('.eliminar-producto').forEach(boton => {
        boton.addEventListener('click', function() {
            const itemElement = this.closest('.item-carrito');
            const itemId = itemElement.dataset.id;
            const carritoId = itemElement.dataset.carritoId;
            eliminarDelCarrito(itemId, carritoId);
        });
    });

    // 2. Listener para el botón DISMINUIR (corregido)
    document.querySelectorAll('.disminuir-cantidad').forEach(boton => {
        boton.addEventListener('click', function() {
            const itemElement = this.closest('.item-carrito');
            const itemId = itemElement.dataset.id;
            const carritoId = itemElement.dataset.carritoId;
            // Asegúrate de que aquí se llama a actualizarCantidad con -1
            actualizarCantidad(itemId, -1, carritoId);
        });
    });

    // 3. Listener para el botón AUMENTAR (corregido)
    document.querySelectorAll('.aumentar-cantidad').forEach(boton => {
        boton.addEventListener('click', function() {
            const itemElement = this.closest('.item-carrito');
            const itemId = itemElement.dataset.id;
            const carritoId = itemElement.dataset.carritoId;
            // Asegúrate de que aquí se llama a actualizarCantidad con 1
            actualizarCantidad(itemId, 1, carritoId);
        });
    });

    // Listener para el botón de pagar (este ya estaba bien)
    const botonPagar = document.querySelector('.boton-pagar');
    if (botonPagar) {
        botonPagar.addEventListener('click', function() {
            if (isUserLoggedIn) {
                window.location.href = 'checkout.php';
            } else {
                sessionStorage.setItem('urlRedireccion', 'carrito.php');
                window.location.href = '../autenticacion/iniciar-sesion.html';
            }
        });
    }
}

    function eliminarDelCarrito(productoId, carritoId) {
    if (isUserLoggedIn) {
        fetch('../../controladores/eliminar_del_carrito.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `carrito_id=${carritoId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                carrito = carrito.filter(item => item.carrito_id != carritoId);
                actualizarCarrito();
                mostrarNotificacion(data.message);
            } else {
                mostrarNotificacion(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al comunicarse con el servidor');
        });
    } else {
        carrito = carrito.filter(item => item.id != productoId);
        actualizarCarrito();
        mostrarNotificacion('Producto eliminado del carrito');
    }
}

function actualizarCantidad(productoId, cambio, carritoId) {
    const item = carrito.find(item => item.id == productoId);
    if (!item) return;

    let nuevaCantidad = item.cantidad + cambio;

    if (nuevaCantidad < 1) {
        eliminarDelCarrito(productoId, carritoId);
        return;
    }

    if (isUserLoggedIn) {
        fetch('../../controladores/actualizar_carrito.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `carrito_id=${carritoId}&cantidad=${nuevaCantidad}`
        })
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta del servidor');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                item.cantidad = data.nueva_cantidad;
                actualizarCarrito();
                mostrarNotificacion(data.message);
            } else {
                mostrarNotificacion(data.message || 'No se pudo actualizar la cantidad');
                // Recargar el carrito para mostrar valores actuales
                if (typeof carritoDesdeBD !== 'undefined') {
                    carrito = carritoDesdeBD;
                    mostrarCarrito();
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al comunicarse con el servidor');
        });
    } else {
        // Lógica para invitados
        item.cantidad = nuevaCantidad;
        actualizarCarrito();
    }
}

    function actualizarCantidad(productoId, cambio, carritoId) {
    const item = carrito.find(item => item.id == productoId);
    if (!item) return;

    let nuevaCantidad = item.cantidad + cambio;

    // Validación básica de cantidad mínima
    if (nuevaCantidad < 1) {
        eliminarDelCarrito(productoId, carritoId);
        return;
    }

    if (isUserLoggedIn) {
        // Usuario logueado - actualizar en la base de datos
        fetch('../../controladores/actualizar_carrito.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `carrito_id=${carritoId}&cantidad=${nuevaCantidad}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Actualizar el carrito local con los datos del servidor
                item.cantidad = data.nueva_cantidad || nuevaCantidad;
                actualizarCarrito();
            } else {
                // Mostrar error y revertir cambio en la UI
                mostrarNotificacion(data.message || 'Error al actualizar la cantidad');
                mostrarCarrito(); // Forzar recarga para mostrar valores correctos
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al comunicarse con el servidor');
            mostrarCarrito(); // Forzar recarga para mostrar valores correctos
        });
    } else {
        // Usuario no logueado - actualizar solo en localStorage
        item.cantidad = nuevaCantidad;
        actualizarCarrito();
    }
}

    function actualizarCarrito() {
        // Solo guardamos en localStorage si el usuario NO está logueado
        if (!isUserLoggedIn) {
            localStorage.setItem('carrito', JSON.stringify(carrito));
        }
        mostrarCarrito();
    }

    function actualizarContadorCarrito() {
        const totalItems = carrito.reduce((total, item) => total + item.cantidad, 0);
        contadorCarrito.textContent = totalItems;
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

    // Inicializar carrito
    mostrarCarrito();
});