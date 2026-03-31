<?php
require_once 'config.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
    // Eliminar la sesión activa de la base de datos
    $delete = $conn->prepare("DELETE FROM sesiones_activas WHERE user_id = ? AND session_token = ?");
    $delete->bind_param("is", $_SESSION['user_id'], $_SESSION['session_token']);
    $delete->execute();
}

session_destroy();
header("Location: login.php");
exit();
?>