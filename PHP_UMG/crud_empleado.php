<?php
session_start();

if (empty($_POST)) {
    header('Location: index.php');
    exit();
}

$txt_id_empleado = (int) trim($_POST['txt_id_empleado'] ?? 0);
$txt_codigo = (string) trim($_POST['txt_codigo'] ?? '');
$txt_nombre = (string) trim($_POST['txt_nombre'] ?? '');
$txt_apellido = (string) trim($_POST['txt_apellido'] ?? '');
$txt_direccion = (string) trim($_POST['txt_direccion'] ?? '');
$txt_telefono = (string) trim($_POST['txt_telefono'] ?? '');
$txt_fecha_nacimiento = (string) trim($_POST['txt_fecha_nacimiento'] ?? '');
$txt_id_puesto = (int) trim($_POST['txt_id_puesto'] ?? 0);
$pagina = max(1, (int) ($_POST['pagina'] ?? 1));
$busqueda = (string) trim($_POST['busqueda'] ?? '');
$token = (string) ($_POST['csrf_token'] ?? '');

include 'datos_conexion.php';

function regresarAlListado($tipo, $mensaje, $pagina, $busqueda = '')
{
    $_SESSION['flash_tipo'] = $tipo;
    $_SESSION['flash_mensaje'] = $mensaje;

    $params = ['pagina' => $pagina];

    if ($busqueda !== '') {
        $params['busqueda'] = $busqueda;
    }

    header('Location: index.php?' . http_build_query($params));
    exit();
}

function tokenCsrfValido($token)
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && $token !== ''
        && hash_equals($_SESSION['csrf_token'], $token);
}

function datosEmpleadoValidos(
    $codigo,
    $nombre,
    $apellido,
    $direccion,
    $telefono,
    $fecha_nacimiento,
    $id_puesto
) {
    if ($codigo === '' || strlen($codigo) > 20) {
        return false;
    }

    if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü ]{2,60}$/u', $nombre)) {
        return false;
    }

    if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü ]{2,60}$/u', $apellido)) {
        return false;
    }

    if (strlen($direccion) > 100) {
        return false;
    }

    if (!preg_match('/^[0-9+() -]{8,12}$/', $telefono)) {
        return false;
    }

    $fecha = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
    if (
        !$fecha ||
        $fecha->format('Y-m-d') !== $fecha_nacimiento ||
        $fecha_nacimiento > date('Y-m-d')
    ) {
        return false;
    }

    if ($id_puesto <= 0) {
        return false;
    }

    return true;
}

function codigoEmpleadoExiste($conexion, $codigo, $id_empleado = 0)
{
    if ($id_empleado > 0) {
        $sql = 'SELECT id_empleado FROM empleados
                WHERE codigo = ? AND id_empleado <> ? LIMIT 1';
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $codigo, $id_empleado);
    } else {
        $sql = 'SELECT id_empleado FROM empleados WHERE codigo = ? LIMIT 1';
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 's', $codigo);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $existe = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $existe;
}

function puestoExiste($conexion, $id_puesto)
{
    $sql = 'SELECT id_puesto FROM puestos WHERE id_puesto = ? LIMIT 1';
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id_puesto);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $existe = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $existe;
}

function empleadoExiste($conexion, $id_empleado)
{
    $sql = 'SELECT id_empleado FROM empleados WHERE id_empleado = ? LIMIT 1';
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id_empleado);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $existe = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $existe;
}

if (!tokenCsrfValido($token)) {
    regresarAlListado(
        'error',
        'No se pudo completar la operación.',
        $pagina,
        $busqueda
    );
}

try {
    $conexion = mysqli_connect($host, $user, $password, $dbname);

    if (!$conexion) {
        regresarAlListado(
            'error',
            'No se pudo completar la operación.',
            $pagina,
            $busqueda
        );
    }

    $conexion->set_charset('utf8mb4');

    if (isset($_POST['btn_agregar'])) {
        if (
            !datosEmpleadoValidos(
                $txt_codigo,
                $txt_nombre,
                $txt_apellido,
                $txt_direccion,
                $txt_telefono,
                $txt_fecha_nacimiento,
                $txt_id_puesto
            )
        ) {
            mysqli_close($conexion);
            regresarAlListado(
                'error',
                'Los datos ingresados no son válidos.',
                $pagina,
                $busqueda
            );
        }

        if (!puestoExiste($conexion, $txt_id_puesto)) {
            mysqli_close($conexion);
            regresarAlListado(
                'error',
                'Los datos ingresados no son válidos.',
                $pagina,
                $busqueda
            );
        }

        if (codigoEmpleadoExiste($conexion, $txt_codigo)) {
            mysqli_close($conexion);
            regresarAlListado(
                'error',
                'El código ingresado ya existe.',
                $pagina,
                $busqueda
            );
        }

        $ahora = date('Y-m-d H:i:s');

        $sql = "INSERT INTO empleados
                (
                    codigo,
                    nombres,
                    apellidos,
                    direccion,
                    telefono,
                    fecha_nacimiento,
                    id_puesto,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            'ssssssiss',
            $txt_codigo,
            $txt_nombre,
            $txt_apellido,
            $txt_direccion,
            $txt_telefono,
            $txt_fecha_nacimiento,
            $txt_id_puesto,
            $ahora,
            $ahora
        );

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            mysqli_close($conexion);
            regresarAlListado(
                'error',
                'No se pudo completar la operación.',
                $pagina,
                $busqueda
            );
        }

        mysqli_stmt_close($stmt);
        mysqli_close($conexion);

        regresarAlListado(
            'exito',
            'Empleado registrado correctamente.',
            1,
            $busqueda
        );
    }

    if (isset($_POST['btn_modificar'])) {
        if ($txt_id_empleado <= 0 || !empleadoExiste($conexion, $txt_id_empleado)) {
            mysqli_close($conexion);
            regresarAlListado(
                'error',
                'Los datos ingresados no son válidos.',
                $pagina,
                $busqueda
            );
        }

        if (
            !datosEmpleadoValidos(
                $txt_codigo,
                $txt_nombre,
                $txt_apellido,
                $txt_direccion,
                $txt_telefono,
                $txt_fecha_nacimiento,
                $txt_id_puesto
            )
        ) {
            mysqli_close($conexion);
            regresarAlListado(
                'error',
                'Los datos ingresados no son válidos.',
                $pagina,
                $busqueda
            );
        }

        if (!puestoExiste($conexion, $txt_id_puesto)) {
            mysqli_close($conexion);
            regresarAlListado(
                'error',
                'Los datos ingresados no son válidos.',
                $pagina,
                $busqueda
            );
        }

        if (codigoEmpleadoExiste($conexion, $txt_codigo, $txt_id_empleado)) {
            mysqli_close($conexion);
            regresarAlListado(
                'error',
                'El código ingresado ya existe.',
                $pagina,
                $busqueda
            );
        }

        $ahora = date('Y-m-d H:i:s');

        $sql = "UPDATE empleados
                SET codigo = ?,
                    nombres = ?,
                    apellidos = ?,
                    direccion = ?,
                    telefono = ?,
                    fecha_nacimiento = ?,
                    id_puesto = ?,
                    updated_at = ?
                WHERE id_empleado = ?";

        $stmt = mysqli_prepare($conexion, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            'ssssssisi',
            $txt_codigo,
            $txt_nombre,
            $txt_apellido,
            $txt_direccion,
            $txt_telefono,
            $txt_fecha_nacimiento,
            $txt_id_puesto,
            $ahora,
            $txt_id_empleado
        );

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            mysqli_close($conexion);
            regresarAlListado(
                'error',
                'No se pudo completar la operación.',
                $pagina,
                $busqueda
            );
        }

        mysqli_stmt_close($stmt);
        mysqli_close($conexion);

        regresarAlListado(
            'exito',
            'Empleado modificado correctamente.',
            $pagina,
            $busqueda
        );
    }

    if (isset($_POST['btn_eliminar'])) {
        if ($txt_id_empleado <= 0 || !empleadoExiste($conexion, $txt_id_empleado)) {
            mysqli_close($conexion);
            regresarAlListado(
                'error',
                'Los datos ingresados no son válidos.',
                $pagina,
                $busqueda
            );
        }

        $sql = 'DELETE FROM empleados WHERE id_empleado = ?';
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $txt_id_empleado);

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            mysqli_close($conexion);
            regresarAlListado(
                'error',
                'No se pudo completar la operación.',
                $pagina,
                $busqueda
            );
        }

        mysqli_stmt_close($stmt);
        mysqli_close($conexion);

        regresarAlListado(
            'exito',
            'Empleado eliminado correctamente.',
            $pagina,
            $busqueda
        );
    }

    mysqli_close($conexion);

    regresarAlListado(
        'error',
        'No se pudo completar la operación.',
        $pagina,
        $busqueda
    );
} catch (mysqli_sql_exception $error) {
    if ((int) $error->getCode() === 1062) {
        regresarAlListado(
            'error',
            'El código ingresado ya existe.',
            $pagina,
            $busqueda
        );
    }

    regresarAlListado(
        'error',
        'No se pudo completar la operación.',
        $pagina,
        $busqueda
    );
}
?>
