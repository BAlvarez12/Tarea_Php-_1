<?php
session_start();

include_once 'datos_conexion.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$puestos = [];
$empleados = [];
$errorConexion = '';

$registrosPorPagina = 5;
$paginaActual = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$busqueda = (string) trim($_GET['busqueda'] ?? '');
$totalRegistros = 0;
$totalPaginas = 1;

if ($paginaActual < 1) {
    $paginaActual = 1;
}

$tipoMensaje = $_SESSION['flash_tipo'] ?? '';
$mensaje = $_SESSION['flash_mensaje'] ?? '';
unset($_SESSION['flash_tipo'], $_SESSION['flash_mensaje']);

$conexion = mysqli_connect($host, $user, $password, $dbname);

if (!$conexion) {
    $errorConexion = 'No fue posible conectar con la base de datos.';
} else {
    mysqli_set_charset($conexion, 'utf8mb4');

    $sqlPuestos = "SELECT id_puesto, puesto
                   FROM puestos
                   ORDER BY puesto ASC";

    $resultadoPuestos = mysqli_query($conexion, $sqlPuestos);

    if ($resultadoPuestos) {
        while ($fila = mysqli_fetch_assoc($resultadoPuestos)) {
            $puestos[] = $fila;
        }
    }

    $filtroBusqueda = '';
    $parametros = [];
    $tipos = '';

    if ($busqueda !== '') {
        $filtroBusqueda = " WHERE e.codigo LIKE ?
            OR e.nombres LIKE ?
            OR e.apellidos LIKE ?
            OR e.telefono LIKE ?
            OR p.puesto LIKE ?";
        $termino = '%' . $busqueda . '%';
        $parametros = [$termino, $termino, $termino, $termino, $termino];
        $tipos = 'sssss';
    }

    $sqlTotal = "SELECT COUNT(*) AS total
                 FROM empleados e
                 LEFT JOIN puestos p ON e.id_puesto = p.id_puesto"
                 . $filtroBusqueda;

    $stmtTotal = mysqli_prepare($conexion, $sqlTotal);

    if ($stmtTotal) {
        if ($tipos !== '') {
            mysqli_stmt_bind_param($stmtTotal, $tipos, ...$parametros);
        }

        mysqli_stmt_execute($stmtTotal);
        $resultadoTotal = mysqli_stmt_get_result($stmtTotal);

        if ($resultadoTotal) {
            $filaTotal = mysqli_fetch_assoc($resultadoTotal);
            $totalRegistros = (int) $filaTotal['total'];
            $totalPaginas = max(1, (int) ceil($totalRegistros / $registrosPorPagina));
        }

        mysqli_stmt_close($stmtTotal);
    }

    if ($paginaActual > $totalPaginas) {
        $paginaActual = $totalPaginas;
    }

    $inicio = ($paginaActual - 1) * $registrosPorPagina;

    $sqlEmpleados = "SELECT
                        e.id_empleado,
                        e.codigo,
                        e.nombres,
                        e.apellidos,
                        e.direccion,
                        e.telefono,
                        e.fecha_nacimiento,
                        e.id_puesto,
                        e.created_at,
                        e.updated_at,
                        p.puesto
                     FROM empleados e
                     LEFT JOIN puestos p
                        ON e.id_puesto = p.id_puesto"
                     . $filtroBusqueda .
                     " ORDER BY e.id_empleado DESC
                     LIMIT ? OFFSET ?";

    $stmtEmpleados = mysqli_prepare($conexion, $sqlEmpleados);

    if ($stmtEmpleados) {
        if ($tipos !== '') {
            $tiposConsulta = $tipos . 'ii';
            $parametrosConsulta = array_merge($parametros, [$registrosPorPagina, $inicio]);
            mysqli_stmt_bind_param($stmtEmpleados, $tiposConsulta, ...$parametrosConsulta);
        } else {
            mysqli_stmt_bind_param($stmtEmpleados, 'ii', $registrosPorPagina, $inicio);
        }

        mysqli_stmt_execute($stmtEmpleados);
        $resultadoEmpleados = mysqli_stmt_get_result($stmtEmpleados);

        if ($resultadoEmpleados) {
            while ($fila = mysqli_fetch_assoc($resultadoEmpleados)) {
                $empleados[] = $fila;
            }
        } else {
            $errorConexion = 'No fue posible cargar el listado de empleados.';
        }

        mysqli_stmt_close($stmtEmpleados);
    } else {
        $errorConexion = 'No fue posible cargar el listado de empleados.';
    }

    mysqli_close($conexion);
}

function urlPaginacion($pagina, $busqueda)
{
    $params = ['pagina' => $pagina];

    if ($busqueda !== '') {
        $params['busqueda'] = $busqueda;
    }

    return '?' . http_build_query($params);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRUD de empleados</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h1 class="h4 mb-0">Listado de empleados</h1>

                <button
                    type="button"
                    class="btn btn-light"
                    id="btnRegistrarEmpleado"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEmpleado"
                >
                    Registrar empleado
                </button>
            </div>

            <div class="card-body">
                <?php if ($mensaje !== ''): ?>
                    <div class="alert <?= $tipoMensaje === 'exito' ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorConexion !== ''): ?>
                    <div class="alert alert-warning" role="alert">
                        <?= htmlspecialchars($errorConexion, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form class="row g-2 mb-3" method="get" action="index.php">
                    <div class="col-md-8">
                        <input
                            type="search"
                            class="form-control"
                            name="busqueda"
                            value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Buscar por código, nombre, apellido, teléfono o puesto"
                        >
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        <?php if ($busqueda !== ''): ?>
                            <a href="index.php" class="btn btn-outline-secondary">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>

                <p class="text-muted mb-3">
                    Total de registros: <strong><?= $totalRegistros ?></strong>
                    <?php if ($busqueda !== ''): ?>
                        | Búsqueda: <strong><?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php endif; ?>
                    | Página <strong><?= $paginaActual ?></strong> de <strong><?= $totalPaginas ?></strong>
                </p>

                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Código</th>
                                <th>Nombres</th>
                                <th>Apellidos</th>
                                <th>Dirección</th>
                                <th>Teléfono</th>
                                <th>Fecha de nacimiento</th>
                                <th>Puesto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($empleados)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">
                                        No hay empleados registrados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($empleados as $empleado): ?>
                                    <tr>
                                        <td><?= (int) $empleado['id_empleado'] ?></td>
                                        <td><?= htmlspecialchars($empleado['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($empleado['nombres'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($empleado['apellidos'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($empleado['direccion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($empleado['telefono'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($empleado['fecha_nacimiento'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($empleado['puesto'] ?? 'Sin puesto', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-warning btn-sm btnEditar"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalEmpleado"
                                                    data-id="<?= (int) $empleado['id_empleado'] ?>"
                                                    data-codigo="<?= htmlspecialchars($empleado['codigo'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-nombres="<?= htmlspecialchars($empleado['nombres'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-apellidos="<?= htmlspecialchars($empleado['apellidos'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-direccion="<?= htmlspecialchars($empleado['direccion'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-telefono="<?= htmlspecialchars($empleado['telefono'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-fecha="<?= htmlspecialchars($empleado['fecha_nacimiento'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-id-puesto="<?= (int) $empleado['id_puesto'] ?>"
                                                >
                                                    Editar
                                                </button>

                                                <form
                                                    action="crud_empleado.php"
                                                    method="post"
                                                    class="formulario-eliminar"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="txt_id_empleado" value="<?= (int) $empleado['id_empleado'] ?>">
                                                    <input type="hidden" name="pagina" value="<?= $paginaActual ?>">
                                                    <input type="hidden" name="busqueda" value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>">

                                                    <button
                                                        type="submit"
                                                        class="btn btn-danger btn-sm"
                                                        name="btn_eliminar"
                                                        value="1"
                                                    >
                                                        Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación de empleados">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
                                <a
                                    class="page-link"
                                    href="<?= htmlspecialchars(urlPaginacion($paginaActual - 1, $busqueda), ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="Página anterior"
                                >
                                    Anterior
                                </a>
                            </li>

                            <?php for ($pagina = 1; $pagina <= $totalPaginas; $pagina++): ?>
                                <li class="page-item <?= $pagina === $paginaActual ? 'active' : '' ?>">
                                    <a
                                        class="page-link"
                                        href="<?= htmlspecialchars(urlPaginacion($pagina, $busqueda), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <?= $pagina ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                                <a
                                    class="page-link"
                                    href="<?= htmlspecialchars(urlPaginacion($paginaActual + 1, $busqueda), ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="Página siguiente"
                                >
                                    Siguiente
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div
        class="modal fade"
        id="modalEmpleado"
        tabindex="-1"
        aria-labelledby="modalEmpleadoLabel"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalEmpleadoLabel">
                        Registrar empleado
                    </h2>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"
                    ></button>
                </div>

                <form
                    id="formEmpleado"
                    class="needs-validation"
                    action="crud_empleado.php"
                    method="post"
                    novalidate
                >
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="pagina" value="<?= $paginaActual ?>">
                    <input type="hidden" name="busqueda" value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="txt_id_empleado" class="form-label">
                                    ID del empleado
                                </label>

                                <input
                                    type="number"
                                    class="form-control"
                                    id="txt_id_empleado"
                                    name="txt_id_empleado"
                                    placeholder="Automático"
                                    readonly
                                >
                            </div>

                            <div class="col-md-4">
                                <label for="txt_codigo" class="form-label">Código</label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="txt_codigo"
                                    name="txt_codigo"
                                    maxlength="20"
                                    required
                                >

                                <div class="valid-feedback">Código válido.</div>
                                <div class="invalid-feedback">
                                    Ingresa el código del empleado.
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="txt_id_puesto" class="form-label">Puesto</label>

                                <select
                                    class="form-select"
                                    id="txt_id_puesto"
                                    name="txt_id_puesto"
                                    required
                                >
                                    <option selected disabled value="">
                                        Selecciona un puesto...
                                    </option>

                                    <?php foreach ($puestos as $puesto): ?>
                                        <option value="<?= (int) $puesto['id_puesto'] ?>">
                                            <?= htmlspecialchars($puesto['puesto'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <div class="valid-feedback">Puesto seleccionado.</div>
                                <div class="invalid-feedback">
                                    Selecciona un puesto.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="txt_nombre" class="form-label">Nombres</label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="txt_nombre"
                                    name="txt_nombre"
                                    maxlength="60"
                                    pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü ]{2,60}"
                                    required
                                >

                                <div class="valid-feedback">Nombre válido.</div>
                                <div class="invalid-feedback">
                                    Ingresa un nombre válido.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="txt_apellido" class="form-label">Apellidos</label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="txt_apellido"
                                    name="txt_apellido"
                                    maxlength="60"
                                    pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü ]{2,60}"
                                    required
                                >

                                <div class="valid-feedback">Apellido válido.</div>
                                <div class="invalid-feedback">
                                    Ingresa un apellido válido.
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="txt_direccion" class="form-label">Dirección</label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="txt_direccion"
                                    name="txt_direccion"
                                    maxlength="100"
                                >

                                <div class="valid-feedback">Dirección válida.</div>
                                <div class="invalid-feedback">
                                    Ingresa una dirección válida.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="txt_telefono" class="form-label">Teléfono</label>

                                <input
                                    type="tel"
                                    class="form-control"
                                    id="txt_telefono"
                                    name="txt_telefono"
                                    maxlength="12"
                                    pattern="[0-9+() -]{8,12}"
                                    required
                                >

                                <div class="valid-feedback">Teléfono válido.</div>
                                <div class="invalid-feedback">
                                    Ingresa un teléfono válido.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="txt_fecha_nacimiento" class="form-label">
                                    Fecha de nacimiento
                                </label>

                                <input
                                    type="date"
                                    class="form-control"
                                    id="txt_fecha_nacimiento"
                                    name="txt_fecha_nacimiento"
                                    max="<?= date('Y-m-d') ?>"
                                    required
                                >

                                <div class="valid-feedback">Fecha válida.</div>
                                <div class="invalid-feedback">
                                    Selecciona una fecha válida.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            class="btn btn-success"
                            id="btnGuardarEmpleado"
                            name="btn_agregar"
                            value="1"
                        >
                            Guardar empleado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        (() => {
            'use strict';

            const formulario = document.getElementById('formEmpleado');
            const btnRegistrar = document.getElementById('btnRegistrarEmpleado');
            const botonesEditar = document.querySelectorAll('.btnEditar');
            const formulariosEliminar = document.querySelectorAll('.formulario-eliminar');
            const tituloModal = document.getElementById('modalEmpleadoLabel');
            const btnGuardar = document.getElementById('btnGuardarEmpleado');

            const campoId = document.getElementById('txt_id_empleado');
            const campoCodigo = document.getElementById('txt_codigo');
            const campoNombres = document.getElementById('txt_nombre');
            const campoApellidos = document.getElementById('txt_apellido');
            const campoDireccion = document.getElementById('txt_direccion');
            const campoTelefono = document.getElementById('txt_telefono');
            const campoFecha = document.getElementById('txt_fecha_nacimiento');
            const campoPuesto = document.getElementById('txt_id_puesto');

            btnRegistrar.addEventListener('click', () => {
                formulario.reset();
                formulario.classList.remove('was-validated');

                campoId.value = '';
                tituloModal.textContent = 'Registrar empleado';

                btnGuardar.name = 'btn_agregar';
                btnGuardar.textContent = 'Guardar empleado';
                btnGuardar.classList.remove('btn-warning');
                btnGuardar.classList.add('btn-success');
            });

            botonesEditar.forEach(boton => {
                boton.addEventListener('click', () => {
                    formulario.classList.remove('was-validated');

                    campoId.value = boton.dataset.id;
                    campoCodigo.value = boton.dataset.codigo;
                    campoNombres.value = boton.dataset.nombres;
                    campoApellidos.value = boton.dataset.apellidos;
                    campoDireccion.value = boton.dataset.direccion;
                    campoTelefono.value = boton.dataset.telefono;
                    campoFecha.value = boton.dataset.fecha;
                    campoPuesto.value = boton.dataset.idPuesto;

                    tituloModal.textContent = 'Editar empleado';

                    btnGuardar.name = 'btn_modificar';
                    btnGuardar.textContent = 'Guardar cambios';
                    btnGuardar.classList.remove('btn-success');
                    btnGuardar.classList.add('btn-warning');
                });
            });

            formulario.addEventListener('submit', event => {
                formulario.classList.add('was-validated');

                if (!formulario.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            });

            formulariosEliminar.forEach(formularioEliminar => {
                formularioEliminar.addEventListener('submit', event => {
                    const confirmar = window.confirm(
                        '¿Estás seguro de eliminar este empleado?'
                    );

                    if (!confirmar) {
                        event.preventDefault();
                    }
                });
            });
        })();
    </script>
</body>
</html>
