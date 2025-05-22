<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: ../index.php");
    exit();
}

// Create secure storage directory if it doesn't exist
$storage_path = dirname(__FILE__) . '/../secure_storage/';
if (!file_exists($storage_path)) {
    mkdir($storage_path, 0755, true);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                if (isset($_POST['titulo'], $_POST['departamento_id']) && isset($_FILES['archivo'])) {
                    $titulo = trim($_POST['titulo']);
                    $departamento_id = (int)$_POST['departamento_id'];
                    $file = $_FILES['archivo'];

                    // Validate file type (only PDF)
                    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if ($fileType === 'pdf') {
                        // Generate unique filename
                        $filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $file['name']);
                        $filepath = $storage_path . $filename;

                        if (move_uploaded_file($file['tmp_name'], $filepath)) {
                            $stmt = $conn->prepare("INSERT INTO procedimientos (titulo, departamento_id, archivo) VALUES (?, ?, ?)");
                            $stmt->bind_param("sis", $titulo, $departamento_id, $filename);
                            $stmt->execute();
                        }
                    }
                }
                break;

            case 'update':
                if (isset($_POST['id'], $_POST['titulo'], $_POST['departamento_id'])) {
                    $id = (int)$_POST['id'];
                    $titulo = trim($_POST['titulo']);
                    $departamento_id = (int)$_POST['departamento_id'];

                    if (isset($_FILES['archivo']) && $_FILES['archivo']['size'] > 0) {
                        $file = $_FILES['archivo'];
                        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        
                        if ($fileType === 'pdf') {
                            // Get old file to delete
                            $stmt = $conn->prepare("SELECT archivo FROM procedimientos WHERE id = ?");
                            $stmt->bind_param("i", $id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $old_file = $result->fetch_assoc()['archivo'];

                            // Generate new filename
                            $filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $file['name']);
                            $filepath = $storage_path . $filename;

                            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                                // Update database with new file
                                $stmt = $conn->prepare("UPDATE procedimientos SET titulo = ?, departamento_id = ?, archivo = ? WHERE id = ?");
                                $stmt->bind_param("sisi", $titulo, $departamento_id, $filename, $id);
                                $stmt->execute();

                                // Delete old file
                                if ($old_file && file_exists($storage_path . $old_file)) {
                                    unlink($storage_path . $old_file);
                                }
                            }
                        }
                    } else {
                        // Update without changing file
                        $stmt = $conn->prepare("UPDATE procedimientos SET titulo = ?, departamento_id = ? WHERE id = ?");
                        $stmt->bind_param("sii", $titulo, $departamento_id, $id);
                        $stmt->execute();
                    }
                }
                break;

            case 'delete':
                if (isset($_POST['id'])) {
                    $id = (int)$_POST['id'];
                    
                    // Get file to delete
                    $stmt = $conn->prepare("SELECT archivo FROM procedimientos WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $file = $result->fetch_assoc()['archivo'];

                    // Delete from database
                    $stmt = $conn->prepare("DELETE FROM procedimientos WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();

                    // Delete file
                    if ($file && file_exists($storage_path . $file)) {
                        unlink($storage_path . $file);
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
    <title>Gestión de Procedimientos - Sistema de Calidad</title>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gestión de Procedimientos</h1>
            <div>
                <button class="btn btn-primary" onclick="showModal('createModal')">Nuevo Procedimiento</button>
                <a href="../dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Departamento</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = $conn->query("SELECT p.*, d.nombre as departamento 
                                      FROM procedimientos p 
                                      JOIN departamentos d ON p.departamento_id = d.id 
                                      ORDER BY p.fecha DESC");
                while ($proc = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($proc['titulo']) . "</td>";
                    echo "<td>" . htmlspecialchars($proc['departamento']) . "</td>";
                    echo "<td>" . date('d/m/Y', strtotime($proc['fecha'])) . "</td>";
                    echo "<td>";
                    echo "<button class='btn btn-primary' onclick='editProcedure(" . json_encode($proc) . ")'>Editar</button> ";
                    echo "<button class='btn btn-danger' onclick='deleteProcedure(" . $proc['id'] . ")'>Eliminar</button>";
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Create Procedure Modal -->
        <div id="createModal" class="modal">
            <div class="modal-content">
                <h2>Nuevo Procedimiento</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group">
                        <label>Título</label>
                        <input type="text" name="titulo" required>
                    </div>
                    <div class="form-group">
                        <label>Departamento</label>
                        <select name="departamento_id" required>
                            <?php
                            $depts = $conn->query("SELECT * FROM departamentos ORDER BY nombre");
                            while ($dept = $depts->fetch_assoc()) {
                                echo "<option value='" . $dept['id'] . "'>" . htmlspecialchars($dept['nombre']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Archivo (PDF)</label>
                        <input type="file" name="archivo" accept=".pdf" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" onclick="hideModal('createModal')">Cancelar</button>
                </form>
            </div>
        </div>

        <!-- Edit Procedure Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <h2>Editar Procedimiento</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="editId">
                    <div class="form-group">
                        <label>Título</label>
                        <input type="text" name="titulo" id="editTitulo" required>
                    </div>
                    <div class="form-group">
                        <label>Departamento</label>
                        <select name="departamento_id" id="editDepartamentoId" required>
                            <?php
                            $depts = $conn->query("SELECT * FROM departamentos ORDER BY nombre");
                            while ($dept = $depts->fetch_assoc()) {
                                echo "<option value='" . $dept['id'] . "'>" . htmlspecialchars($dept['nombre']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Archivo (PDF - dejar en blanco para mantener)</label>
                        <input type="file" name="archivo" accept=".pdf">
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

        function editProcedure(proc) {
            document.getElementById('editId').value = proc.id;
            document.getElementById('editTitulo').value = proc.titulo;
            document.getElementById('editDepartamentoId').value = proc.departamento_id;
            showModal('editModal');
        }

        function deleteProcedure(id) {
            if (confirm('¿Está seguro de eliminar este procedimiento?')) {
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
