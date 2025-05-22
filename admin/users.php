<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: ../index.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                if (isset($_POST['nombre'], $_POST['correo'], $_POST['password'], $_POST['rol'])) {
                    $nombre = trim($_POST['nombre']);
                    $correo = filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL);
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $rol = $_POST['rol'];

                    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, correo, password, rol) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $nombre, $correo, $password, $rol);
                    $stmt->execute();
                }
                break;

            case 'update':
                if (isset($_POST['id'], $_POST['nombre'], $_POST['correo'], $_POST['rol'])) {
                    $id = (int)$_POST['id'];
                    $nombre = trim($_POST['nombre']);
                    $correo = filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL);
                    $rol = $_POST['rol'];

                    $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, correo = ?, rol = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $nombre, $correo, $rol, $id);
                    $stmt->execute();

                    // Update password if provided
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                        $stmt->bind_param("si", $password, $id);
                        $stmt->execute();
                    }
                }
                break;

            case 'delete':
                if (isset($_POST['id'])) {
                    $id = (int)$_POST['id'];
                    // First delete from usuario_departamento
                    $stmt = $conn->prepare("DELETE FROM usuario_departamento WHERE id_usuario = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    
                    // Then delete from usuarios
                    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Sistema de Calidad</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 2rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: white;
        }
        .btn-primary {
            background: #007bff;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-secondary {
            background: #6c757d;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th {
            background: #f8f9fa;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            margin: 2rem auto;
            padding: 2rem;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gestión de Usuarios</h1>
            <div>
                <button class="btn btn-primary" onclick="showModal('createModal')">Nuevo Usuario</button>
                <a href="../dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = $conn->query("SELECT * FROM usuarios ORDER BY nombre");
                while ($user = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($user['nombre']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['correo']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['rol']) . "</td>";
                    echo "<td>";
                    echo "<button class='btn btn-primary' onclick='editUser(" . json_encode($user) . ")'>Editar</button> ";
                    echo "<button class='btn btn-danger' onclick='deleteUser(" . $user['id'] . ")'>Eliminar</button>";
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Create User Modal -->
        <div id="createModal" class="modal">
            <div class="modal-content">
                <h2>Nuevo Usuario</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label>Correo</label>
                        <input type="email" name="correo" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="rol" required>
                            <option value="empleado">Empleado</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="administrador">Administrador</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" onclick="hideModal('createModal')">Cancelar</button>
                </form>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <h2>Editar Usuario</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="editId">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" id="editNombre" required>
                    </div>
                    <div class="form-group">
                        <label>Correo</label>
                        <input type="email" name="correo" id="editCorreo" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña (dejar en blanco para mantener)</label>
                        <input type="password" name="password">
                    </div>
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="rol" id="editRol" required>
                            <option value="empleado">Empleado</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="administrador">Administrador</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <button type="button" class="btn btn-secondary" onclick="hideModal('editModal')">Cancelar</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showModal(id) {
            document.getElementById(id).style.display = 'block';
        }

        function hideModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function editUser(user) {
            document.getElementById('editId').value = user.id;
            document.getElementById('editNombre').value = user.nombre;
            document.getElementById('editCorreo').value = user.correo;
            document.getElementById('editRol').value = user.rol;
            showModal('editModal');
        }

        function deleteUser(id) {
            if (confirm('¿Está seguro de eliminar este usuario?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
