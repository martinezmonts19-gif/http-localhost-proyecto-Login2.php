<?php
session_start();

// --- Configuración de la BD y Rutas ---
$host = "localhost";
$user = "root";
$pass = "";
$db = "tulipanes_db";
$upload_dir = "uploads/";

// # INICIO DE CÓDIGO QR//
// ######################################################################

/**
 * Función simple para generar un Código QR en formato de imagen.
 * *** AHORA USA QUICKCHART.IO COMO ALTERNATIVA A GOOGLE CHARTS (Obsoleto) ***
 * @param string $data La cadena de texto a codificar (tu URL de escaneo).
 * @return string La etiqueta <img> con la imagen del QR.
 */
function generar_svg_qr($data) {
    // Codificar los datos
    $url_encoded = urlencode($data);

    // **NUEVA URL DE API (QuickChart.io):**
    // chs=100x100: Tamaño (100x100 píxeles)
    // cht=qr: Tipo de gráfico (QR code)
    // chl: Los datos codificados (la URL de tu producto)
    $qr_url = "https://quickchart.io/chart?chs=100x100&cht=qr&chl={$url_encoded}";

    return "<img src='{$qr_url}' alt='Código QR' class='qr-image'>";
}

// 1. Inicializar el contador de escaneos en sesión si no existe
$contadores_escaneo = isset($_SESSION['qr_scans']) ? $_SESSION['qr_scans'] : array(
    'Tulipan_Rojo' => 0,
    'Tulipan_Amarillo' => 0,
    'Tulipan_Rosa' => 0,
    'Tulipan_Morado' => 0,
    'Tulipan_Blanco' => 0,
    'Tulipan_Naranja' => 0,
    'Tulipan_Examen' => 0,
    'Tulipan_Monts' => 0,
);

// ######################################################################
// # CONTINUACIÓN DEL CÓDIGO (El resto se mantiene igual)
// ######################################################################

$productos_tienda = array(
    'Tulipan_Rojo' => array(
        'nombre' => 'Tulipán Rojo Clásico',
        'precio' => 5.00,
        // 'stock' se manejará por separado
        'imagen' => 'images (9).jpeg'
    ),
    'Tulipan_Amarillo' => array(
        'nombre' => 'Tulipán Amarillo Radiante',
        'precio' => 4.50,
        'stock' => 999, // Stock estático
        'imagen' => 'images (1).jpeg'
    ),
    'Tulipan_Rosa' => array(
        'nombre' => 'Tulipán Rosa Elegante',
        'precio' => 5.50,
        'stock' => 20,
        'imagen' => ''
    ),
    'Tulipan_Morado' => array(
        'nombre' => 'Tulipán Morado Encanto',
        'precio' => 6.00,
        'stock' => 15,
        'imagen' => 'user_68dc72457471d.jpeg'
    ),
    'Tulipan_Blanco' => array(
        'nombre' => 'Tulipán Blanco Puro',
        'precio' => 5.75,
        'stock' => 12,
        'imagen' => 'images (5).jpeg'
    ),
    'Tulipan_Naranja' => array(
        'nombre' => 'Tulipán Naranja Vibrante',
        'precio' => 4.80,
        'stock' => 30,
        'imagen' => 'images (8).jpeg'
    ),
    'Tulipan_Examen' => array(
        'nombre' => 'Tulipán Examen',
        'precio' => 4.80,
        'stock' => 30,
        'imagen' => 'user_68dc726a7650c.jpeg'
    ),
    'Tulipan_Monts' => array(
        'nombre' => 'Tulipán Monts',
        'precio' => 4.80,
        'stock' => 30,
        'imagen' => 'images.jpeg'
    ),
);

// Lógica de Stock: Solo 'Tulipan_Rojo' usa el control Rup/G
$productos_tienda['Tulipan_Rojo']['stock'] = isset($_SESSION['stock_tulipan_rojo']) ? $_SESSION['stock_tulipan_rojo'] : 10;

// Conexión a la BD
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Error en la conexión a la base de datos: " . $conn->connect_error);
}

$mensaje = "";
$vista = "login";
$mostrar_ticket = false; // bandera para mostrar tarjeta visual cuando se genere el ticket
$ticket_simulado = array(); // datos para la tarjeta visual
$producto_escaneado_key = null; // NUEVA VARIABLE PARA LA VISTA DE PRODUCTO

// Si ya hay sesión activa, ir al menú
if (isset($_SESSION['user'])) {
    $vista = "menu";
}

// Si la vista viene por GET, la establecemos (esto permite la navegación)
if (isset($_GET['vista'])) {
    $vista = $_GET['vista'];
}


// ######################################################################
// # BLOQUE DE CÓDIGO ACTUALIZADO: Lógica de Escaneo del QR y Vistas
// ######################################################################

// Lógica de simulación de escaneo del QR (CONTADOR Y MOSTRAR DETALLE)
if (isset($_GET['qr_scan_product'])) {
    $scanned_key = $_GET['qr_scan_product'];
    // Validar si la clave existe en los contadores y en los productos
    if (isset($contadores_escaneo[$scanned_key]) && isset($productos_tienda[$scanned_key])) {
        
        // 1. Contar el escaneo (Mantenemos la funcionalidad)
        $contadores_escaneo[$scanned_key]++;
        $_SESSION['qr_scans'] = $contadores_escaneo; 
        
        // 2. Establecer la vista para mostrar los detalles del producto
        $vista = "vista_producto"; 
        $producto_escaneado_key = $scanned_key;
        $mensaje = "✅ Estás viendo los detalles del producto (Escaneo QR).";
        
        // No redirigimos aquí. La ejecución continúa para mostrar la vista "vista_producto".
    } else {
        // Si no es un QR válido, redirige al menú
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . "?vista=menu&mensaje=" . urlencode("❌ Producto QR no encontrado."));
        exit();
    }
}
// ######################################################################
// # FIN BLOQUE DE CÓDIGO ACTUALIZADO
// ######################################################################


// --- FUNCIÓN DE GESTIÓN DE SUBIDA DE ARCHIVOS (CRUD) ---
function upload_image($file, $upload_dir, &$mensaje, $current_image = 'descarga (2).jpeg') {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return $current_image;
    }

    $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
    $max_size = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowed_types)) {
        $mensaje .= "❌ Error: Solo se permiten archivos JPG, PNG o GIF.";
        return false;
    }
    if ($file['size'] > $max_size) {
        $mensaje .= "❌ Error: El archivo es demasiado grande (máx. 2MB).";
        return false;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = uniqid('user_') . '.' . $ext;
    $target_file = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        if ($current_image != 'default_profile.png' && file_exists($upload_dir . $current_image)) {
            @unlink($upload_dir . $current_image);
        }
        return $new_filename;
    } else {
        $mensaje .= "❌ Error al mover el archivo subido.";
        return false;
    }
}

// --- UTIL: obtener URL de imagen o imagen por defecto ---
function img_url_or_default($upload_dir, $imagen_nombre) {
    // imagen_nombre puede venir vacía
    $default = $upload_dir . 'no_disponible.jpg';
    if (empty($imagen_nombre)) {
        return $default;
    }
    $ruta = $upload_dir . $imagen_nombre;
    if (file_exists($ruta) && is_file($ruta)) {
        return $ruta;
    }
    return $default;
}

// --- LÓGICA DE NAVEGACIÓN Y ACCIONES ---

// LOGIN
if (isset($_POST['login']) && $vista == 'login') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']);

    $sql = "SELECT username FROM usuarios WHERE username='$username' AND password='$password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $_SESSION['user'] = $username;
        $vista = "menu";
    } else {
        $mensaje = "❌ Usuario o contraseña incorrectos.";
    }
}

// Redirecciones
if (isset($_POST['admin'])) { $vista = "admin"; }
if (isset($_POST['consultar'])) { $vista = "consultar_articulos"; }
if (isset($_POST['volver_menu'])) { $vista = "menu"; }

// INSERTAR NUEVO USUARIO
if (isset($_POST['registrar_usuario'])) {
    $new_username = $conn->real_escape_string($_POST['new_username']);
    $new_password = $conn->real_escape_string($_POST['new_password']);
    $fecha_registro = date("Y-m-d H:i:s");
    $imagen_name = 'default_profile.png';
    $upload_success = true;

    if (isset($_FILES['imagen_perfil']) && $_FILES['imagen_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploaded_name = upload_image($_FILES['imagen_perfil'], $upload_dir, $mensaje);
        if ($uploaded_name !== false) {
            $imagen_name = $uploaded_name;
        } else {
            $upload_success = false;
        }
    }

    if($upload_success){
        $check = $conn->query("SELECT id FROM usuarios WHERE username='$new_username'");
        if ($check->num_rows > 0) {
            $mensaje = "⚠️ El usuario '$new_username' ya existe.";
        } else {
            $sql = "INSERT INTO usuarios (username, password, fecha_registro, imagen_perfil) VALUES ('$new_username', '$new_password', '$fecha_registro', '$imagen_name')";
            if ($conn->query($sql) === TRUE) {
                $mensaje = "✅ Usuario '$new_username' creado correctamente.";
            } else {
                $mensaje = "❌ Error al crear usuario: " . $conn->error;
            }
        }
    }
    $vista = "admin";
}

// ELIMINAR USUARIO
if (isset($_GET['eliminar_id'])) {
    $id_eliminar = (int)$_GET['eliminar_id'];

    $res_img = $conn->query("SELECT imagen_perfil FROM usuarios WHERE id = $id_eliminar");
    if ($res_img->num_rows > 0) {
        $img_data = $res_img->fetch_assoc();
        $file_to_delete = $img_data['imagen_perfil'];
        if ($file_to_delete != 'default_profile.png' && file_exists($upload_dir . $file_to_delete)) {
            @unlink($upload_dir . $file_to_delete);
        }
    }

    $sql = "DELETE FROM usuarios WHERE id = $id_eliminar";
    if ($conn->query($sql) === TRUE) {
        $mensaje = "🗑️ Usuario ID **$id_eliminar** eliminado correctamente.";
    } else {
        $mensaje = "❌ Error al eliminar usuario: " . $conn->error;
    }
    header("Location: ?vista=admin&mensaje=" . urlencode($mensaje));
    exit();
}

// ACTUALIZAR USUARIO
if (isset($_POST['actualizar_usuario'])) {
    $id_user = (int)$_POST['id_user'];
    $new_password = $conn->real_escape_string($_POST['new_password']);
    $current_image = $conn->real_escape_string($_POST['current_image']);
    $imagen_name = $current_image;
    $upload_success = true;

    if (isset($_FILES['new_imagen_perfil']) && $_FILES['new_imagen_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploaded_name = upload_image($_FILES['new_imagen_perfil'], $upload_dir, $mensaje, $current_image);
        if ($uploaded_name !== false) {
            $imagen_name = $uploaded_name;
        } else {
            $upload_success = false;
        }
    }

    if ($upload_success){
        $sql = "UPDATE usuarios SET password='$new_password', imagen_perfil='$imagen_name' WHERE id=$id_user";
        if ($conn->query($sql) === TRUE) {
            $mensaje = "✏️ Usuario ID **$id_user** actualizado correctamente.";
        } else {
            $mensaje = "❌ Error al actualizar: " . $conn->error;
        }
    }
    $vista = "admin";
}

// LÓGICA DE ARTÍCULOS
if (isset($_POST['guardar_articulo'])) {
    $usuario = $conn->real_escape_string($_POST['usuario']);
    $articulo = $conn->real_escape_string($_POST['articulo']);

    $sql = "INSERT INTO articulos_data (usuario, articulo) VALUES ('$usuario', '$articulo')";
    if ($conn->query($sql) === TRUE) {
        $mensaje = "📝 Artículo guardado correctamente.";
    } else {
        $mensaje = "❌ Error: " . $conn->error;
    }
    $vista = "menu";
}


// --- CARRITO DE COMPRAS y LÓGICA DE STOCK (Rup/G) ---

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

if (isset($_POST['agregar_carrito_tienda'])) {
    $producto_key = $conn->real_escape_string($_POST['producto_key']);
    $cantidad = (int)$_POST['cantidad'];
    if ($cantidad <= 0) $cantidad = 1;

    // Validar si el producto existe
    if (!isset($productos_tienda[$producto_key])) {
        $mensaje = "❌ Error: Producto no válido.";
        $vista = "menu";
    } else {
        $nombre_producto = $productos_tienda[$producto_key]['nombre'];
        $stock_disponible = $productos_tienda[$producto_key]['stock'];

        // Lógica de Stock y Ruptura (Rup/G) - Aplica solo a 'Tulipan_Rojo'
        if ($producto_key === "Tulipan_Rojo" && $cantidad > $stock_disponible) {
            $mensaje = "⚠️ ¡Ruptura de Stock! Solo quedan **$stock_disponible** unidades de '$nombre_producto'.";
        } else {
            if (isset($_SESSION['cart'][$producto_key])) {
                $_SESSION['cart'][$producto_key] += $cantidad;
            } else {
                $_SESSION['cart'][$producto_key] = $cantidad;
            }
            $mensaje = "🛒 Producto '$nombre_producto' agregado al carrito.";

            // Simular la reducción de stock
            if ($producto_key === "Tulipan_Rojo") {
                 $productos_tienda['Tulipan_Rojo']['stock'] -= $cantidad;
                 $_SESSION['stock_tulipan_rojo'] = $productos_tienda['Tulipan_Rojo']['stock']; // Guardar nuevo stock
            }
        }
    }
    $vista = "menu";
}

if (isset($_POST['vaciar_carrito'])) {
    $_SESSION['cart'] = array();
    // Restaurar stock simulado
    $_SESSION['stock_tulipan_rojo'] = 10;
    $productos_tienda['Tulipan_Rojo']['stock'] = 10;
    // Restaurar conteo de escaneo
    $_SESSION['qr_scans'] = array_fill_keys(array_keys($contadores_escaneo), 0);
    $contadores_escaneo = $_SESSION['qr_scans'];


    $mensaje = "🗑️ Carrito vaciado.";
    $vista = "menu";
}



// --- LÓGICA: GENERAR Y ENVIAR TICKET (Total IVA, PDF, Imail) ---
if (isset($_POST['generar_ticket'])) {
    $total_carrito = 0;
    $iva_porcentaje = 0.16; // 16% de IVA (ejemplo)

    // 1. Calcular Total (Total IVA)
    foreach ($_SESSION['cart'] as $producto_key => $cantidad) {
        $precio_unitario = $productos_tienda[$producto_key]['precio'];
        $subtotal_producto = $cantidad * $precio_unitario;
        $total_carrito += $subtotal_producto;
    }

    $iva_monto = $total_carrito * $iva_porcentaje;
    $total_final = $total_carrito + $iva_monto; // Total IVA

    
    $pdf_simulado_path = "c:\wamp2\www\proyecto\uploads\descarga (3).jpeg" . time() . ".pdf";
    $imagen_simulada_path = "c:\wamp2\www\proyecto\uploads\descarga (3).jpeg" . time() . ".jpg";

    $destinatario = $_SESSION['user'] . ",Montstulipanes@.com";
    $asunto = "Ticket de Compra - " . date("Y-m-d");
    $cuerpo = "Estimado/a " . $_SESSION['user'] . ",\n\n";
    $cuerpo .= "Su compra ha sido realizada con éxito. \n";
    $cuerpo .= "RESUMEN DE VENTA:\n";
    $cuerpo .= "- Subtotal: $" . number_format($total_carrito, 2) . "\n";
    $cuerpo .= "- IVA (16%): $" . number_format($iva_monto, 2) . "\n";
    $cuerpo .= "- **Total Final (Total IVA): $" . number_format($total_final, 2) . "**.";
    

    $mensaje = "✅ ¡Venta Realizada! 📤 Ticket generado (PDF, Imagen, Total, IVA) y enviado a **$destinatario**.";

    // Preparar datos para mostrar la tarjeta visual del ticket (no altera la lógica)
    $mostrar_ticket = true;
    $ticket_simulado = array(
        'subtotal' => number_format($total_carrito, 2),
        'iva' => number_format($iva_monto, 2),
        'total' => number_format($total_final, 2),
        'imagen_deco' => 'img/tulipanes/tulipan_ticket_deco.jpg',
        'fecha' => date("Y-m-d H:i:s"),
        'destinatario' => $destinatario
    );

    // Vaciar el carrito después de la venta
    $_SESSION['cart'] = array();

    $vista = "menu";
}


// CERRAR SESIÓN
if (isset($_POST['logout'])) {
    session_destroy();
    $vista = "login";
    $mensaje = "👋 Sesión cerrada.";
}

// Capturar mensajes de redirección (después de eliminar)
if (isset($_GET['vista']) && $_GET['vista'] === 'admin') {
    $vista = 'admin';
    if (isset($_GET['mensaje'])) {
        $mensaje = urldecode($_GET['mensaje']);
    }
}

// --- CALCULAR TOTALES PARA MOSTRAR EN LA VISTA ---
$total_carrito = 0;
$iva_porcentaje = 0.16;
$iva_monto = 0;
$total_final = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $producto_key => $cantidad) {
        if (isset($productos_tienda[$producto_key])) {
            $precio_unitario = $productos_tienda[$producto_key]['precio'];
            $total_carrito += $cantidad * $precio_unitario;
        }
    }
    $iva_monto = $total_carrito * $iva_porcentaje;
    $total_final = $total_carrito + $iva_monto;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🌷 Tienda de Tulipanes - CRUD y Ventas 🌷</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --bg: #fbf7f8;
            --card: #ffffff;
            --accent: #d16b9e;
            --muted: #7a5a73;
            --green: #3cb371;
            --danger: #c94b4b;
            --soft: #fffafc;
            --glass: rgba(255,255,255,0.6);
        }

        *{ box-sizing: border-box; }
        body {
            font-family: 'Nunito', Arial, sans-serif;
            background: linear-gradient(180deg, #fffafc 0%, #fbf7f8 100%);
            color: #3b2f37;
            padding: 20px;
            margin: 0;
        }

        .contenedor {
            width: 95%;
            max-width: 1200px;
            margin: 18px auto;
            background: var(--card);
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(45,30,40,0.06);
            padding: 28px;
            overflow: hidden;
        }

        header.top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid #f3e9ee;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        header.top .title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        header.top h2 {
            color: var(--accent);
            font-size: 22px;
            margin: 0;
            letter-spacing: 0.3px;
        }
        header.top .subtitle {
            color: var(--muted);
            font-size: 13px;
        }

        /* Mensajes */
        .mensajes { margin: 14px 0; text-align: center; }
        .mensaje-exito { color: #186a3b; background: #e9f6ea; padding: 10px 14px; border-radius: 8px; display: inline-block; }
        .mensaje-error { color: #8a2525; background: #fdecea; padding: 10px 14px; border-radius: 8px; display: inline-block; }
        .mensaje-advertencia { color: #7a5a00; background: #fff7e6; padding: 10px 14px; border-radius: 8px; display: inline-block; }

        /* Formularios y botones */
        .form-inline { margin: 10px 0; }
        input[type="text"], input[type="password"], input[type="file"], input[type="number"] {
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #eee2ea;
            width: 100%;
            max-width: 360px;
            display: block;
            margin: 8px 0;
            background: #fff;
        }
        button, .btn-link {
            padding: 10px 14px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: inline-block;
            text-decoration: none;
            color: white;
            margin: 6px 4px;
            transition: transform .08s ease, box-shadow .12s ease;
        }
        button:active { transform: translateY(1px); }
        .btn-primary { background: var(--accent); box-shadow: 0 6px 18px rgba(209,107,158,0.12); }
        .btn-secondary { background: #7b62a3; }
        .btn-danger { background: var(--danger); }
        .btn-warning { background: #f59e0b; color: #222; }
        .btn-green { background: var(--green); }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid #f1e6ea; }

        .imagen-perfil {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f5d9e6;
            display: block;
            margin: 0 auto;
        }

        /* TIENDA: grid moderno */
        .tienda-grid {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 20px;
            margin-top: 16px;
        }

        .productos-wrap {
            background: transparent;
        }

        .productos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
            align-items: stretch;
        }

        .producto-card {
            background: linear-gradient(180deg, #fff 0%, #fff6f8 100%);
            border-radius: 12px;
            padding: 14px;
            text-align: center;
            border: 1px solid #f4e7ef;
            box-shadow: 0 8px 22px rgba(200,150,180,0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .producto-card .thumb {
            width: 120px;
            height: 120px;
            margin: 0 auto 8px;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .producto-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .producto-card h4 {
            color: #6b4a67;
            margin: 8px 0 4px;
            font-size: 16px;
        }
        .producto-card p {
            font-size: 15px;
            color: var(--accent);
            font-weight: 700;
            margin-bottom: 8px;
        }
        .producto-card .controls { margin-top: 8px; }
        .small-stock {
            display:block;
            font-size:12px;
            color:var(--muted);
            margin-top:6px;
        }

        /* QR Styles */
        .qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #f1e6ea;
        }
        .qr-section .qr-image {
            width: 70px; /* Tamaño del QR */
            height: 70px;
            margin-bottom: 5px;
            border: 1px solid #eee;
            padding: 2px;
            border-radius: 4px;
        }
        .qr-counter {
            font-size: 12px;
            color: var(--green);
            font-weight: 600;
        }

        /* CARRITO */
        .carrito {
            background: var(--soft);
            padding: 14px;
            border-radius: 12px;
            border: 1px solid #f1e6ea;
            min-height: 200px;
        }
        .carrito h4 { margin: 0 0 10px 0; color: #6b4a67; }
        .carrito-item {
            display:flex;
            gap:10px;
            padding: 10px 0;
            border-bottom: 1px solid #f6edf3;
            align-items:center;
        }
        .carrito-item img{ width:56px; height:56px; object-fit:cover; border-radius:8px; }
        .carrito-item .meta { flex:1; }
        .carrito-item .meta strong{ display:block; color:#5a3e50; }
        .summary-row { display:flex; justify-content:space-between; padding:6px 0; color:#5a3e50; }
        .total-final { font-size:1.18em; color:var(--danger); font-weight:700; padding-top:8px; border-top:1px dashed #f0d6df; margin-top:8px; }

        /* tablas y admin */
        table { width:100%; border-collapse: collapse; margin-top:10px; font-size:13px; }
        table th, table td { padding:8px 6px; border:1px solid #f1e6ea; text-align:left; }
        table th { background:#fff0f6; color:#6b4a67; }

        /* Ticket visual */
        .ticket-visual { margin: 14px auto; padding: 14px; border-radius: 10px; background: #fffafc; border: 1px solid #f2dfe6; text-align:center; max-width:720px; }
        .ticket-visual img { max-width:220px; height:auto; display:block; margin:0 auto 8px; }

        /* Responsive */
        @media screen and (max-width: 940px){
            .tienda-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <header class="top">
            <div class="title">
                <div style="font-size:30px;">🌷</div>
                <div>
                    <h2>🌷 Tienda de Tulipanes - CRUD y Ventas</h2>
                    <div class="subtitle">Compra fácil y rápida</div>
                </div>
            </div>

            <div style="text-align:right;">
                <?php if (isset($_SESSION['user'])): ?>
                    <div style="font-size:13px; color:var(--muted);">Conectado como <strong><?php echo htmlspecialchars($_SESSION['user']); ?></strong></div>
                <?php else: ?>
                    <div style="font-size:13px; color:var(--muted);">Inicia sesión para acceder al menú</div>
                <?php endif; ?>
            </div>
        </header>

        <div class="mensajes">
        <?php
        $msg_class = '';
        if (strpos($mensaje, '✅') !== false) { $msg_class = 'mensaje-exito'; }
        elseif (strpos($mensaje, '❌') !== false) { $msg_class = 'mensaje-error'; }
        elseif (strpos($mensaje, '⚠️') !== false) { $msg_class = 'mensaje-advertencia'; }

        if (!empty($mensaje)) {
            echo '<div class="'.$msg_class.'">'.nl2br(htmlspecialchars($mensaje)).'</div>';
        }
        ?>
        </div>

        <?php
        // --- LOGIN VIEW ---
        if ($vista == "login") {
        ?>
            <h3 style="text-align:center; color:#6b4a67; margin-top:6px;">👋 Inicia Sesión</h3>
            <form method="POST" class="form-inline" style="text-align:center;">
                <input type="text" name="username" placeholder="Usuario" required><br>
                <input type="password" name="password" placeholder="Contraseña" required><br>
                <button type="submit" name="login" class="btn-primary" style="width:220px;">Ingresar</button>
            </form>
        <?php
        }

        // --- MENU VIEW (Requiere Login) ---
        if ($vista == "menu") {
        ?>
            <h3 style="color:#6b4a67;">Bienvenido <strong><?php echo htmlspecialchars($_SESSION['user']); ?></strong> 👋</h3>

            <form method="POST" style="text-align:center; margin-top:8px;">
                <button type="submit" name="admin" class="btn-secondary">Admin (CRUD Usuarios)</button>
                <button type="submit" name="consultar" class="btn-ghost">Consultar Artículos</button>
                <button type="submit" name="logout" class="btn-danger">Cerrar Sesión</button>
            </form>

<hr>

<h3 style="color:#6b4a67;">🌷 Nuestra Tienda de Tulipanes 🌷</h3>
<p style="color:var(--muted);">
    El <strong>Tulipán Rojo</strong> tiene control de Stock (Rup/G). Quedan: <strong><?php echo $productos_tienda['Tulipan_Rojo']['stock']; ?></strong> unidades.
</p>

<div class="tienda-grid">
    <div class="productos-wrap">
        <div class="productos-grid">
        <?php foreach ($productos_tienda as $key => $producto): ?>
            <div class="producto-card">
                <div class="thumb">
                    <?php $imgsrc = img_url_or_default($upload_dir, $producto['imagen']); ?>
                    <img src="<?php echo htmlspecialchars($imgsrc); ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                </div>

                <div>
                    <h4><?php echo htmlspecialchars($producto['nombre']); ?></h4>
                    <p>$<?php echo number_format($producto['precio'], 2); ?></p>
                </div>

                <div class="controls">
                    <form method="POST" style="display:flex; gap:8px; align-items:center; justify-content:center;">
                        <input type="hidden" name="producto_key" value="<?php echo htmlspecialchars($key); ?>">
                        <input type="number" name="cantidad" placeholder="Cant." required min="1" value="1" style="width:72px;">
                        <button type="submit" name="agregar_carrito_tienda" class="btn-primary">🛒 Añadir</button>
                    </form>
                    
                    <?php if ($key === 'Tulipan_Rojo'): ?>
                        <small class="small-stock">Stock: <strong><?php echo $producto['stock']; ?></strong></small>
                    <?php endif; ?>

                    <div class="qr-section">
                        <?php 
                            // -----------------------------------------------------------
                            // *** MODIFICACIÓN APLICADA AQUÍ: USANDO LA IP LOCAL ***
                            // -----------------------------------------------------------
                            
                            $base_url = "http://172.20.10.3/proyecto/Login2.php"; 
                            $qr_data_url = $base_url . "?qr_scan_product=" . urlencode($key);

                                  // Generar y mostrar el QR
                              echo generar_svg_qr($qr_data_url);
                                               ?>

                        <div class="qr-counter">
                            Escaneos: 
                            <strong style="color:var(--accent);"><?php echo $contadores_escaneo[$key]; ?></strong>
                        </div>
                    </div>
                    </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <aside class="carrito">
        <h4>🛒 Carrito de Compras</h4>

        <?php if (!empty($_SESSION['cart'])) { ?>
            <?php
            foreach ($_SESSION['cart'] as $prod_key => $cant):
                if (isset($productos_tienda[$prod_key])):
                    $item = $productos_tienda[$prod_key];
                    $subtotal_item = $cant * $item['precio'];
                    $item_img = img_url_or_default($upload_dir, $item['imagen']);
            ?>
                <div class="carrito-item">
                    <img src="<?php echo htmlspecialchars($item_img); ?>" alt="<?php echo htmlspecialchars($item['nombre']); ?>">
                    <div class="meta">
                        <strong><?php echo htmlspecialchars($item['nombre']); ?></strong>
                        <span style="font-size:12px; color:var(--muted);">Cantidad: x<?php echo $cant; ?> | Precio Unitario: $<?php echo number_format($item['precio'], 2); ?></span>
                    </div>
                    <div style="font-weight:bold; color:#5a3e50;">
                        $<?php echo number_format($subtotal_item, 2); ?>
                    </div>
                </div>
            <?php
                endif;
            endforeach;
            ?>

            <div style="margin-top:12px;">
                <div class="summary-row"><span>Subtotal</span><span>$<?php echo number_format($total_carrito, 2); ?></span></div>
                <div class="summary-row"><span>IVA (16%)</span><span>$<?php echo number_format($iva_monto, 2); ?></span></div>
                <div class="summary-row total-final"><span>Total Final</span><span>$<?php echo number_format($total_final, 2); ?></span></div>

                <form method="POST" style="margin-top:10px;">
                    <button type="submit" name="generar_ticket" class="btn-green" style="width:100%;">🚀 Generar & Enviar Ticket</button>
                </form>
                <form method="POST" style="margin-top:8px;">
                    <button type="submit" name="vaciar_carrito" class="btn-danger" style="width:100%;">🗑️ Vaciar Carrito</button>
                </form>
            </div>

        <?php } else { ?>
            <p style="color:var(--muted);">🛍️ Tu carrito está vacío. ¡Añade algunos tulipanes!</p>
        <?php } ?>
    </aside>
</div>

<hr>

            <form method="POST">
                <h3 style="color:#6b4a67;">Funciones CRUD/Artículos (Opcional)</h3>
                <input type="text" name="usuario" placeholder="Usuario (Artículo)" required><br>
                <input type="text" name="articulo" placeholder="Artículo" required><br>
                <button type="submit" name="guardar_articulo" class="btn-primary">Guardar Artículo</button>
            </form>

        <?php
        }

        // --- VISTA DE PRODUCTO (Escaneo QR) ---
        if ($vista == "vista_producto" && isset($producto_escaneado_key)) {
            $producto = $productos_tienda[$producto_escaneado_key];
            $producto_key = $producto_escaneado_key;
        ?>
            <h3 style="color:#6b4a67; text-align:center;">🔍 Detalles del Producto Escaneado (QR) 🔍</h3>
            <div style="max-width:500px; margin: 20px auto; padding: 20px; border: 1px solid #f1e6ea; border-radius: 12px; background: #fffafc; text-align:center;">
                
                <div class="thumb" style="width: 200px; height: 200px; margin: 0 auto 15px;">
                    <?php $imgsrc = img_url_or_default($upload_dir, $producto['imagen']); ?>
                    <img src="<?php echo htmlspecialchars($imgsrc); ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:10px;">
                </div>

                <h1 style="color:var(--accent); font-size: 28px; margin-bottom: 5px;">
                    <?php echo htmlspecialchars($producto['nombre']); ?>
                </h1>
                <p style="font-size: 24px; color: #5a3e50; font-weight: 700;">
                    Precio: $<?php echo number_format($producto['precio'], 2); ?>
                </p>

                <?php if ($producto_key === "Tulipan_Rojo"): ?>
                    <p style="color:var(--muted); font-size:16px;">
                        Stock Disponible: <strong><?php echo $producto['stock']; ?></strong> unidades.
                    </p>
                <?php else: ?>
                    <p style="color:var(--muted); font-size:16px;">
                        Stock: <?php echo $producto['stock']; ?> unidades.
                    </p>
                <?php endif; ?>

                <p style="margin-top: 20px; color: #7b62a3;">
                    (Escaneado QR: Se ha registrado <strong><?php echo $contadores_escaneo[$producto_key]; ?></strong> visitas a este producto).
                </p>
                
                <a href="?vista=menu" class="btn-primary btn-link" style="margin-top: 15px;">Volver al Menú Principal</a>

            </div>

        <?php
        }
        // --- FIN VISTA DE PRODUCTO ---


        // --- ADMIN VIEW (Se mantiene) ---
        if ($vista == "admin" && isset($_SESSION['user'])) {
            $usuario_a_actualizar = null;
            if (isset($_GET['actualizar_id'])) {
                $id_actualizar = (int)$_GET['actualizar_id'];
                $res_update = $conn->query("SELECT id, username, password, imagen_perfil FROM usuarios WHERE id = $id_actualizar");
                if ($res_update->num_rows > 0) {
                    $usuario_a_actualizar = $res_update->fetch_assoc();
                }
            }
        ?>
            <h3 style="color:#6b4a67;">🛠️ Administración de Usuarios (CRUD)</h3>
            <form method="POST">
                <button type="submit" name="volver_menu" class="btn-ghost">⬅️ Volver al Menú</button>
            </form>

            <div style="display:flex; gap:20px; margin-top:10px;">
                <div style="flex:1;">
                    <h4>➕ Insertar Nuevo Usuario</h4>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="text" name="new_username" placeholder="Nuevo Usuario" required>
                        <input type="password" name="new_password" placeholder="Contraseña" required>
                        <label for="imagen_perfil" style="display:block; margin-top:10px; color:var(--muted); font-size:14px;">Imagen de Perfil (Opcional):</label>
                        <input type="file" name="imagen_perfil" id="imagen_perfil" accept="image/*" style="max-width:300px;">
                        <button type="submit" name="registrar_usuario" class="btn-primary">Registrar Usuario</button>
                    </form>
                </div>

                <div style="flex:1;">
                    <?php if ($usuario_a_actualizar): ?>
                    <h4>✏️ Actualizar Usuario: <?php echo htmlspecialchars($usuario_a_actualizar['username']); ?></h4>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id_user" value="<?php echo $usuario_a_actualizar['id']; ?>">
                        <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($usuario_a_actualizar['imagen_perfil']); ?>">
                        
                        <div style="text-align:center;">
                            <img src="<?php echo img_url_or_default($upload_dir, $usuario_a_actualizar['imagen_perfil']); ?>" alt="Perfil" class="imagen-perfil" style="width:60px; height:60px;">
                            <p style="font-size:12px; color:var(--muted);"><?php echo htmlspecialchars($usuario_a_actualizar['imagen_perfil']); ?></p>
                        </div>
                        
                        <input type="password" name="new_password" placeholder="Nueva Contraseña" value="<?php echo htmlspecialchars($usuario_a_actualizar['password']); ?>" required>
                        
                        <label for="new_imagen_perfil" style="display:block; margin-top:10px; color:var(--muted); font-size:14px;">Cambiar Imagen:</label>
                        <input type="file" name="new_imagen_perfil" id="new_imagen_perfil" accept="image/*" style="max-width:300px;">

                        <button type="submit" name="actualizar_usuario" class="btn-warning">Actualizar</button>
                        <a href="?vista=admin" class="btn-ghost">Cancelar</a>
                    </form>
                    <?php else: ?>
                    <div class="mensaje-advertencia">Selecciona un usuario de la lista para actualizar.</div>
                    <?php endif; ?>
                </div>
            </div>

            <h4 style="margin-top:20px;">👥 Lista de Usuarios</h4>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Contraseña</th>
                        <th>Imagen</th>
                        <th>Fecha Reg.</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_users = "SELECT id, username, password, imagen_perfil, fecha_registro FROM usuarios ORDER BY id DESC";
                    $result_users = $conn->query($sql_users);
                    if ($result_users->num_rows > 0) {
                        while($row = $result_users->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $row['id'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['password']) . "</td>";
                            echo "<td><img src='" . img_url_or_default($upload_dir, $row['imagen_perfil']) . "' class='imagen-perfil'></td>";
                            echo "<td>" . $row['fecha_registro'] . "</td>";
                            echo "<td>";
                            echo "<a href='?vista=admin&actualizar_id=" . $row['id'] . "' class='btn-warning btn-link' style='margin-right:5px;'>✏️ Editar</a>";
                            echo "<a href='?eliminar_id=" . $row['id'] . "' class='btn-danger btn-link' onclick=\"return confirm('¿Seguro que quieres eliminar al usuario " . $row['username'] . "?');\">🗑️ Eliminar</a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6'>No hay usuarios registrados.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        <?php
        }
        
        // --- CONSULTAR ARTÍCULOS VIEW ---
        if ($vista == "consultar_articulos" && isset($_SESSION['user'])) {
        ?>
            <h3 style="color:#6b4a67;">🔍 Consulta de Artículos Guardados</h3>
            <form method="POST">
                <button type="submit" name="volver_menu" class="btn-ghost">⬅️ Volver al Menú</button>
            </form>

            <h4 style="margin-top:20px;">Historial de Artículos</h4>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Artículo</th>
                        <th>Fecha Guardado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_articulos = "SELECT id, usuario, articulo, fecha_guardado FROM articulos_data ORDER BY id DESC";
                    $result_articulos = $conn->query($sql_articulos);
                    if ($result_articulos->num_rows > 0) {
                        while($row = $result_articulos->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $row['id'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['usuario']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['articulo']) . "</td>";
                            echo "<td>" . $row['fecha_guardado'] . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No hay artículos guardados.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

        <?php
        }

        // --- TARJETA VISUAL DEL TICKET (Opcional, se muestra solo después de generar ticket) ---
        if ($mostrar_ticket) {
            echo '<div class="ticket-visual">';
            echo '<h4>🎟️ Ticket de Compra Simulado 🎟️</h4>';
            echo '<img src="https://i.ibb.co/6P6XyRk/tulipan-ticket-deco.jpg" alt="Decoración">';
            echo '<p>Destinatario (Simulado): <strong>' . htmlspecialchars($ticket_simulado['destinatario']) . '</strong></p>';
            echo '<p style="font-size:14px; color:var(--muted);">Fecha: ' . htmlspecialchars($ticket_simulado['fecha']) . '</p>';
            echo '<hr style="border-style:dashed; border-color:#f1e6ea;">';
            echo '<div class="summary-row"><span>Subtotal</span><span>$' . htmlspecialchars($ticket_simulado['subtotal']) . '</span></div>';
            echo '<div class="summary-row"><span>IVA (16%)</span><span>$' . htmlspecialchars($ticket_simulado['iva']) . '</span></div>';
            echo '<div class="summary-row total-final"><span>TOTAL FINAL (Total IVA)</span><span>$' . htmlspecialchars($ticket_simulado['total']) . '</span></div>';
            echo '<p style="margin-top:15px; font-size:14px;">¡Gracias por tu compra en **Tienda de Tulipanes**!</p>';
            echo '</div>';
        }
        ?>

    </div>
</body>
</html>
<?php
$conn->close();
?>

