<?php

// no-clubs.php

session_start();
require_once __DIR__ . '/database/db.php';

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get PDO instance
$pdo = getDBConnection();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Load user
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Get Started - Club Hub</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="styles.css">
<style>
/* ---------------------- Styles ---------------------- */
:root {
    --primary: #3b82f6;
    --secondary: #06b6d4;
    --bg-card: #ffffff;
    --bg-tertiary: #f3f4f6;
    --text-primary: #111827;
    --text-secondary: #6b7280;
    --border: #d1d5db;
    --success: #10b981;
    --danger: #ef4444;
    --radius-md: 0.5rem;
    --radius-lg: 0.75rem;
    --radius-xl: 1rem;
    --transition: 0.3s ease;
    --shadow: rgba(0,0,0,0.15);
}

body { margin:0; font-family: 'Inter', sans-serif; background: var(--bg-tertiary); }

.welcome-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    padding: 2rem;
}

.welcome-card {
    background: var(--bg-card);
    border-radius: var(--radius-xl);
    padding: 3rem;
    max-width: 600px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.welcome-header { text-align: center; margin-bottom: 2rem; }

.welcome-logo {
    width: 100px; height: 100px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: var(--radius-lg);
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem; color: white;
}

.welcome-title { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; }
.welcome-subtitle { color: var(--text-secondary); font-size: 1rem; }

.action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem; }

.action-card {
    background: var(--bg-tertiary);
    border-radius: var(--radius-lg);
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all var(--transition);
    border: 2px solid transparent;
}
.action-card:hover {
    border-color: var(--primary);
    transform: translateY(-4px);
    box-shadow: 0 8px 20px var(--shadow);
}
.action-icon {
    width: 60px; height: 60px;
    margin: 0 auto 1rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: var(--radius-lg);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.75rem; color: white;
}
.action-title { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; }
.action-description { font-size: 0.875rem; color: var(--text-secondary); }

/* Modals */
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; padding:2rem; }
.modal.show { display:flex; }
.modal-content { background: var(--bg-card); border-radius: var(--radius-xl); padding:2rem; max-width:500px; width:100%; max-height:90vh; overflow-y:auto; }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; }
.modal-title { font-size:1.5rem; font-weight:700; }
.close-btn { background:none; border:none; font-size:1.5rem; color:var(--text-secondary); cursor:pointer; display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:var(--radius-md); transition:all var(--transition);}
.close-btn:hover { background: var(--bg-tertiary); color: var(--text-primary); }

.form-group { margin-bottom:1.5rem; }
.form-label { display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.5rem; }
.form-input, .form-textarea {
    width:100%; padding:0.875rem 1rem; background: var(--bg-tertiary); border:1px solid var(--border); border-radius:var(--radius-md); font-size:0.875rem; color:var(--text-primary); font-family:inherit; transition: all var(--transition);
}
.form-textarea { resize:vertical; min-height:100px; }
.form-input:focus, .form-textarea:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(59,130,246,0.1); }

.btn { padding:0.875rem 1.5rem; border-radius:var(--radius-md); border:none; font-size:0.875rem; font-weight:600; cursor:pointer; transition:all var(--transition); display:inline-flex; align-items:center; gap:0.5rem; }
.btn-primary { background: linear-gradient(135deg,var(--primary),var(--secondary)); color:white; }
.btn-primary:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(59,130,246,0.3); }
.btn-secondary { background: var(--bg-tertiary); color: var(--text-primary); border:1px solid var(--border); }
.btn-full { width:100%; justify-content:center; }

.alert { padding:1rem; border-radius:var(--radius-md); margin-bottom:1rem; display:none; }
.alert.show { display:block; }
.alert-success { background: rgba(16,185,129,0.1); border:1px solid var(--success); color:var(--success); }
.alert-error { background: rgba(239,68,68,0.1); border:1px solid var(--danger); color:var(--danger); }

.logout-link { text-align:center; margin-top:2rem; padding-top:2rem; border-top:1px solid var(--border);}
.logout-link button { width:100%; }

</style>
</head>
<body>

<div class="welcome-container">
    <div class="welcome-card">
        <div class="welcome-header">
            <div class="welcome-logo"><i class="fas fa-users-cog"></i></div>
            <h1 class="welcome-title">Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
            <p class="welcome-subtitle">You're not a member of any club yet. Let's get you started!</p>
        </div>

        <div class="action-grid">
            <div class="action-card" onclick="showJoinModal()">
                <div class="action-icon"><i class="fas fa-sign-in-alt"></i></div>
                <div class="action-title">Join a Club</div>
                <div class="action-description">Have an access code? Join an existing club</div>
            </div>
            <div class="action-card" onclick="showCreateModal()">
                <div class="action-icon"><i class="fas fa-plus"></i></div>
                <div class="action-title">Create a Club</div>
                <div class="action-description">Request to start a new club</div>
            </div>
        </div>

        <div class="logout-link">
            <button id="logoutBtn" class="btn btn-secondary btn-full">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="joinModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Join a Club</h2>
            <button class="close-btn" onclick="closeJoinModal()"><i class="fas fa-times"></i></button>
        </div>
        <div id="joinAlert" class="alert"></div>
        <form id="joinForm">
            <div class="form-group">
                <label class="form-label" for="accessCode">Access Code</label>
                <input type="text" id="accessCode" class="form-input" placeholder="Enter the club access code" required style="text-transform:uppercase; letter-spacing:2px; font-weight:600;">
                <small style="color: var(--text-secondary); font-size:0.75rem; display:block; margin-top:0.5rem;">Ask a club officer for the access code</small>
            </div>
            <button type="submit" class="btn btn-primary btn-full" id="joinBtn"><i class="fas fa-sign-in-alt"></i> Request to Join</button>
        </form>
    </div>
</div>

<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Request New Club</h2>
            <button class="close-btn" onclick="closeCreateModal()"><i class="fas fa-times"></i></button>
        </div>
        <div id="createAlert" class="alert"></div>
        <form id="createForm">
            <div class="form-group">
                <label class="form-label" for="clubName">Club Name <span style="color: var(--danger);">*</span></label>
                <input type="text" id="clubName" class="form-input" placeholder="e.g., Robotics Club" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="clubDescription">Description</label>
                <textarea id="clubDescription" class="form-textarea" placeholder="What does your club do?"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="staffAdvisor">Staff Advisor</label>
                <input type="text" id="staffAdvisor" class="form-input" placeholder="e.g., Mr. Smith">
            </div>
            <div class="form-group">
                <label class="form-label" for="presidentName">President Name</label>
                <input type="text" id="presidentName" class="form-input" placeholder="Your name" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-full" id="createBtn"><i class="fas fa-paper-plane"></i> Submit Request</button>
        </form>
        <small style="color: var(--text-secondary); font-size:0.75rem; display:block; margin-top:1rem; text-align:center;">A super owner will review and approve your request</small>
    </div>
</div>

<script>
// CSRF token
const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

// Logout button - FIXED VERSION
document.getElementById('logoutBtn').addEventListener('click', async () => {
    if (!confirm('Are you sure you want to logout?')) return;

    try {
        const res = await fetch('api/auth.php?action=logout', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        });
        
        const data = await res.json();
        if (data.success) {
            window.location.href = 'login.php';
        } else {
            alert(data.message || 'Logout failed');
        }
    } catch (err) { 
        console.error('Logout error:', err); 
        // Force redirect to login page even on error
        window.location.href = 'login.php';
    }
});

// Modals
function showJoinModal(){document.getElementById('joinModal').classList.add('show');document.getElementById('accessCode').focus();}
function closeJoinModal(){document.getElementById('joinModal').classList.remove('show');document.getElementById('joinForm').reset();document.getElementById('joinAlert').classList.remove('show');}
function showCreateModal(){document.getElementById('createModal').classList.add('show');document.getElementById('clubName').focus();}
function closeCreateModal(){document.getElementById('createModal').classList.remove('show');document.getElementById('createForm').reset();document.getElementById('createAlert').classList.remove('show');}
window.onclick = function(event){if(event.target.classList.contains('modal')) event.target.classList.remove('show');}

// Alerts
function showAlert(containerId,type,message){
    const alert = document.getElementById(containerId);
    alert.className=`alert alert-${type} show`;
    alert.innerHTML=`<i class="fas fa-${type==='success'?'check':'exclamation'}-circle"></i> ${message}`;
}

// Join form
document.getElementById('joinForm').addEventListener('submit', async e=>{
    e.preventDefault();
    const accessCode = document.getElementById('accessCode').value.trim().toUpperCase();
    const btn = document.getElementById('joinBtn');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Requesting...';
    try{
        const res=await fetch('api/club_join.php?action=request-join',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({access_code:accessCode})});
        const data=await res.json();
        if(data.success){showAlert('joinAlert','success','Join request submitted! Waiting for approval...');setTimeout(()=>window.location.reload(),2000);}
        else{showAlert('joinAlert','error',data.message||'Failed to submit request');btn.disabled=false;btn.innerHTML='<i class="fas fa-sign-in-alt"></i> Request to Join';}
    }catch(err){console.error(err);showAlert('joinAlert','error','An error occurred. Please try again.');btn.disabled=false;btn.innerHTML='<i class="fas fa-sign-in-alt"></i> Request to Join';}
});

// Create form
document.getElementById('createForm').addEventListener('submit', async e=>{
    e.preventDefault();
    const clubName=document.getElementById('clubName').value.trim();
    const description=document.getElementById('clubDescription').value.trim();
    const staffAdvisor=document.getElementById('staffAdvisor').value.trim();
    const presidentName=document.getElementById('presidentName').value.trim();
    const btn=document.getElementById('createBtn');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Submitting...';
    try{
        const res=await fetch('api/club_requests.php?action=create',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({club_name:clubName,description:description,staff_advisor:staffAdvisor,president_name:presidentName})});
        const data=await res.json();
        if(data.success){showAlert('createAlert','success','Club request submitted! Waiting for approval...');setTimeout(()=>window.location.reload(),2000);}
        else{showAlert('createAlert','error',data.message||'Failed to submit request');btn.disabled=false;btn.innerHTML='<i class="fas fa-paper-plane"></i> Submit Request';}
    }catch(err){console.error(err);showAlert('createAlert','error','An error occurred. Please try again.');btn.disabled=false;btn.innerHTML='<i class="fas fa-paper-plane"></i> Submit Request';}
});
</script>
</body>
</html>