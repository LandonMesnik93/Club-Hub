<?php
session_start();
require_once __DIR__ . '/database/db.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT is_system_owner FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            header('Location: ' . ($user['is_system_owner'] ? 'super-owner-dashboard.php' : 'index.php'));
            exit;
        }
    } catch (PDOException $e) {
        // Continue to login page
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Club Hub</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 2rem;
        }
        .auth-card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 3rem;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .auth-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
        }
        .auth-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .auth-subtitle {
            color: var(--text-secondary);
            font-size: 0.9375rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            color: var(--text-primary);
            font-family: inherit;
            transition: all var(--transition);
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn-auth {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
        }
        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }
        .btn-auth:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .auth-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .auth-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        .auth-link a:hover {
            text-decoration: underline;
        }
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: none;
            align-items: center;
            gap: 0.75rem;
        }
        .alert.show {
            display: flex;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-users-cog"></i>
                </div>
                <h1 class="auth-title">Welcome Back</h1>
                <p class="auth-subtitle">Sign in to your Club Hub account</p>
            </div>

            <div id="alertMessage" class="alert"></div>

            <form id="loginForm">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        class="form-input" 
                        placeholder="Enter your email" 
                        required 
                        autocomplete="email"
                        autofocus
                    />
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        class="form-input" 
                        placeholder="Enter your password" 
                        required 
                        autocomplete="current-password"
                    />
                </div>

                <button type="submit" class="btn-auth" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="auth-link">
                Don't have an account? <a href="register.php">Sign up</a>
            </div>

            <center>
            <br>
            <div style="text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; font-size: 16px; font-weight: 700; background: linear-gradient(90deg, #ffd700, #ffed4e, #ffd700, #ff69b4, #87ceeb, #ffd700); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; animation: shine 3s linear infinite;">
                Powered by Escape Room Club
            </div>

            <style>
            @keyframes shine {
                0% { background-position: 0% center; }
                100% { background-position: 200% center; }
            }
            </style>
            </center>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const btn = document.getElementById('loginBtn');
            const alert = document.getElementById('alertMessage');
            
            // Validation
            if (!email || !password) {
                showAlert('error', 'Please fill in all fields');
                return;
            }
            
            // Disable button and show loading
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
            
            try {
                const response = await fetch('api/auth.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('success', 'Login successful! Redirecting...');
                    setTimeout(() => {
                        window.location.href = data.data.is_system_owner ? 
                            'super-owner-dashboard.php' : 'index.php';
                    }, 1000);
                } else {
                    showAlert('error', data.message || 'Invalid email or password');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
                }
            } catch (error) {
                console.error('Login error:', error);
                showAlert('error', 'An error occurred. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
            }
        });
        
        function showAlert(type, message) {
            const alert = document.getElementById('alertMessage');
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            alert.className = `alert alert-${type} show`;
            alert.innerHTML = `<i class="fas ${icon}"></i><span>${message}</span>`;
        }
    </script>
</body>
</html>