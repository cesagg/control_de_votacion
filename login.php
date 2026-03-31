<?php
require_once 'config.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

// Limpiar sesiones antiguas (5 minutos)
$conn->query("DELETE FROM sesiones_activas WHERE ultima_actividad < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $sql = "SELECT * FROM usuarios_sistema WHERE usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // 🔐 VERIFICAR CON PASSWORD_VERIFY (AHORA CON HASH)
        if (password_verify($password, $user['password'])) {
            
            // ✅ VERIFICAR SI ES ADMIN O OPERADOR
            if ($user['es_admin'] == 1) {
                // ✅ ADMIN - NO TIENE RESTRICCIÓN
                $session_token = bin2hex(random_bytes(32));
                
                $insert = $conn->prepare("INSERT INTO sesiones_activas (user_id, session_token, ip_address, user_agent, ultima_actividad) VALUES (?, ?, ?, ?, NOW())");
                $insert->bind_param("isss", $user['id'], $session_token, $ip_address, $user_agent);
                $insert->execute();
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['es_admin'] = $user['es_admin'];
                $_SESSION['nombre'] = $user['nombre_completo'];
                $_SESSION['session_token'] = $session_token;
                
                header("Location: index.php");
                exit();
                
            } else {
                // ✅ OPERADOR - VERIFICAR SI YA ESTÁ EN USO
                $check = $conn->prepare("SELECT id FROM sesiones_activas WHERE user_id = ?");
                $check->bind_param("i", $user['id']);
                $check->execute();
                $check->store_result();
                
                if ($check->num_rows > 0) {
                    $error = "❌ Este operador ya está siendo usado en otro dispositivo.";
                } else {
                    $session_token = bin2hex(random_bytes(32));
                    
                    $insert = $conn->prepare("INSERT INTO sesiones_activas (user_id, session_token, ip_address, user_agent, ultima_actividad) VALUES (?, ?, ?, ?, NOW())");
                    $insert->bind_param("isss", $user['id'], $session_token, $ip_address, $user_agent);
                    $insert->execute();
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['usuario'] = $user['usuario'];
                    $_SESSION['es_admin'] = $user['es_admin'];
                    $_SESSION['nombre'] = $user['nombre_completo'];
                    $_SESSION['session_token'] = $session_token;
                    
                    header("Location: index.php");
                    exit();
                }
            }
        } else {
            $error = "Usuario o contraseña incorrectos";
        }
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Sistema de Control</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 30px 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        
        h2 {
            text-align: center;
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .input-wrapper {
            position: relative;
            width: 100%;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
        }
        
        input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            background: #fafafa;
            -webkit-appearance: none;
            appearance: none;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        input::placeholder {
            color: #aaa;
            font-size: 15px;
        }
        
        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
            -webkit-appearance: none;
            appearance: none;
        }
        
        button:active {
            transform: scale(0.98);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border: 1px solid #fcc;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .footer {
            text-align: center;
            margin-top: 15px;
            color: rgba(255,255,255,0.8);
            font-size: 12px;
        }
        
        /* Touch optimizations */
        @media (hover: none) {
            button:hover {
                transform: none;
            }
        }
        
        /* Landscape mode */
        @media (max-height: 600px) and (orientation: landscape) {
            body {
                padding: 10px;
            }
            
            .login-card {
                padding: 20px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            input {
                padding: 12px 12px 12px 40px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h2>🗳️ Control Electoral</h2>
            <div class="subtitle">Sistema de Consulta de Mesas</div>
            
            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label>
                        <i class="fas fa-user"></i>
                        Usuario
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input 
                            type="text" 
                            name="usuario" 
                            placeholder="Ingrese su usuario"
                            value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>"
                            required
                            autofocus
                            autocomplete="off"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-lock"></i>
                        Contraseña
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            name="password" 
                            placeholder="Ingrese su contraseña"
                            required
                            autocomplete="off"
                        >
                    </div>
                </div>
                
                <button type="submit">
                    <i class="fas fa-sign-in-alt"></i>
                    Ingresar
                </button>
            </form>
            
        </div>
        
        <div class="footer">
            <i class="fas fa-map-marker-alt"></i>
            CGG
        </div>
    </div>

    <script>
        document.addEventListener('touchstart', function(event) {
            if (event.target.tagName === 'INPUT') {
                event.target.style.fontSize = '16px';
            }
        }, false);
        
        const button = document.querySelector('button');
        if (button) {
            button.addEventListener('touchstart', function() {
                this.style.opacity = '0.8';
            });
            button.addEventListener('touchend', function() {
                this.style.opacity = '1';
            });
        }
    </script>
</body>
</html>