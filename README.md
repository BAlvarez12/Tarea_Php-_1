Mejora integral de un sistema CRUD en PHP y MySQL

Utilizar como punto de partida el siguiente repositorio:

https://github.com/acardonap5/php_empresa 

0. Modificar Base de Datos: Agregar campos de fecha de creación y modificación

Antes de realizar las demás mejoras, se deberán agregar a las tablas empleados y puestos los siguientes campos:

created_at: almacenará la fecha y hora en que fue creado el registro.
updated_at: almacenará la fecha y hora de la última modificación del registro.
Los campos deberán ser de tipo fecha y hora, preferiblemente DATETIME.

Al registrar un nuevo empleado o puesto:

created_at deberá guardar la fecha y hora actual.
updated_at deberá guardar inicialmente la misma fecha y hora.
Al modificar un registro:

created_at deberá conservar su valor original.
updated_at deberá actualizarse con la fecha y hora de la modificación.
1. Validar los datos desde PHP

El sistema deberá validar todos los datos recibidos desde los formularios, aunque ya existan validaciones en HTML.

Se deberá verificar como mínimo:

Que el código del empleado no esté vacío.
Que el código no se encuentre repetido.
Que los nombres y apellidos contengan únicamente caracteres permitidos.
Que el teléfono tenga una longitud válida.
Que la fecha de nacimiento sea válida.
Que la fecha de nacimiento no sea futura.
Que el puesto seleccionado exista en la base de datos.
Que el identificador utilizado para modificar o eliminar sea válido.
Que no se registren campos obligatorios vacíos.
Cuando exista un error, el sistema deberá mostrar un mensaje comprensible para el usuario.

2. Mejorar los mensajes del sistema

Después de registrar, modificar o eliminar un empleado, el sistema deberá mostrar mensajes claros, por ejemplo:

Empleado registrado correctamente.
Empleado modificado correctamente.
Empleado eliminado correctamente.
El código ingresado ya existe.
No se pudo completar la operación.
Los datos ingresados no son válidos.
Los mensajes deberán mostrarse utilizando alertas de Bootstrap.

Se recomienda utilizar sesiones para conservar los mensajes después de una redirección.

No se deberán mostrar directamente al usuario errores técnicos internos de MySQL.

3. Implementar protección contra ataques CSRF

Los formularios para agregar, modificar y eliminar deberán incluir un token de seguridad CSRF.

El token deberá:

Generarse mediante PHP.
Guardarse en una sesión.
Incluirse como campo oculto dentro del formulario.
Validarse antes de procesar cualquier operación.
Rechazar la solicitud cuando el token no sea válido.
4. Implementar paginación

La tabla de empleados deberá mostrar los registros de forma paginada.

Cada página deberá presentar entre 5 y 10 empleados.

La paginación deberá incluir:

Botón anterior.
Botón siguiente.
Número de página actual.
Cantidad total de registros.
Conservación del término de búsqueda al cambiar de página.
