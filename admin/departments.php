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
                if (isset($_POST['nombre'])) {
                    $nombre = trim($_POST['nombre']);
                    $stmt = $conn->prepare("INSERT INTO departamentos (nombre) VALUES (?)");
                    $stmt->bind_param("s", $nombre);
                    $stmt->execute();
                }
                break;

            case 'update':
                if (isset($_POST['id'], $_POST['nombre'])) {
                    $id = (int)$_POST['id'];
                    $nombre = trim($_POST['nombre']);
                    $stmt = $conn->prepare("UPDATE departamentos SET nombre = ? WHERE id = ?");
                    $stmt->bind_param("si", $nombre, $id);
                    $stmt->execute();
                }
                break;

            case 'delete':
                if (isset($_POST['id'])) {
                    $id = (int)$_POST['id'];
                    
                    // First check if department has any procedures
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM procedimientos WHERE departamento_id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count = $result->fetch_assoc()['count'];

                    if ($count == 0) {
                        // Delete from usuario_departamento first
                        $stmt = $conn->prepare("DELETE FROM usuario_departamento WHERE id_departamento = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();

                        // Then delete the department
                        $stmt = $conn->prepare("DELETE FROM departamentos WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                    }
                }
                break;

            case 'assign_users':
                if (isset($_POST['department_id'], $_POST['user_ids'])) {
                    $dept_id = (int)$_POST['department_id'];
                    $user_ids = $_POST['user_ids'];

                    // First remove all existing assignments for this department
                    $stmt = $conn->prepare("DELETE FROM usuario_departamento WHERE id_departamento = ?");
                    $stmt->bind_param("i", $dept_id);
                    $stmt->execute();

                    // Then add new assignments
                    if (!empty($user_ids)) {
                        $stmt = $conn->prepare("INSERT INTO usuario_departamento (id_usuario, id_departamento) VALUES (?, ?)");
                        foreach ($user_ids as $user_id) {
                            $stmt->bind_param("ii", $user_id, $dept_id);
                            $stmt->execute();
                        }
                    }
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
    <title>Gestión de Departamentos - Sistema de Calidad</title>
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
        .btn-primary { background: #007bff; }
        .btn-danger { background: #dc3545; }
        .btn-secondary { background: #6c757d; }
        .btn-info { background: #17a2b8; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th { background: #f8f9fa; }
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
        .checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .checkbox-item {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gestión de Departamentos</h1>
            <div>
                <button class="btn btn-primary" onclick="showModal('createModal')">Nuevo Departamento</button>
                <a href="../dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuarios Asignados</th>
                    <th>Procedimientos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = $conn->query("SELECT d.*, 
                                      (SELECT COUNT(*) FROM usuario_departamento WHERE id_departamento = d.id) as user_count,
                                      (SELECT COUNT(*) FROM procedimientos WHERE departamento_id = d.id) as proc_count
                                      FROM departamentos d ORDER BY nombre");
                while ($dept = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($dept['nombre']) . "</td>";
                    echo "<td>" . $dept['user_count'] . " usuarios</td>";
                    echo "<td>" . $dept['proc_count'] . " procedimientos</td>";
                    echo "<td>";
                    echo "<button class='btn btn-primary' onclick='editDepartment(" . json_encode($dept) . ")'>Editar</button> ";
                    echo "<button class='btn btn-info' onclick='assignUsers(" . $dept['id'] . ")'>Asignar Usuarios</button> ";
                    if ($dept['proc_count'] == 0) {
                        echo "<button class='btn btn-danger' onclick='deleteDepartment(" . $dept['id'] . ")'>Eliminar</button>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Create Department Modal -->
        <div id="createModal" class="modal">
            <div class="modal-content">
                <h2>Nuevo Departamento</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" onclick="hideModal('createModal')">Cancelar</button>
                </form>
            </div>
        </div>

        <!-- Edit Department Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <h2>Editar Departamento</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="editId">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" id="editNombre" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <button type="button" class="btn btn-secondary" onclick="hideModal('editModal')">Cancelar</button>
                </form>
            </div>
        </div>

        <!-- Assign Users Modal -->
        <div id="assignModal" class="modal">
            <div class="modal-content">
                <h2>Asignar Usuarios</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="assign_users">
                    <input type="hidden" name="department_id" id="assignDeptId">
                    <div class="checkbox-group">
                        <?php
                        $users = $conn->query("SELECT id, nombre, correo FROM usuarios ORDER BY nombre");
                        while ($user = $users->fetch_assoc()) {
                            echo '<div class="checkbox-item">';
                            echo '<input type="checkbox" name="user_ids[]" value="' . $user['id'] . '" id="user' . $user['id'] . '">';
                            echo '<label for="user' . $user['id'] . '">' . htmlspecialchars($user['nombre']) . 
                                 ' (' . htmlspecialchars($user['correo']) . ')</label>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar Asignaciones</button>
                    <button type="button" class="btn btn-secondary" onclick="hideModal('assignModal')">Cancelar</button>
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

        function editDepartment(dept) {
            document.getElementById('editId').value = dept.id;
            document.getElementById('editNombre').value = dept.nombre;
            showModal('editModal');
        }

        function assignUsers(deptId) {
            document.getElementById('assignDeptId').value = deptId;
            
            // Clear previous selections
            document.querySelectorAll('input[name="user_ids[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });

            // Get current assignments
            fetch(`get_department_users.php?id=${deptId}`)
                .then(response => response.json())
                .then(userIds => {
                    userIds.forEach(userId => {
                        const checkbox = document.querySelector(`input[value="${userId}"]`);
                        if (checkbox) checkbox.checked = true;
                    });
                });

            showModal('assignModal');
        }

        function deleteDepartment(id) {
            if (confirm('¿Está seguro de eliminar este departamento?')) {
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
