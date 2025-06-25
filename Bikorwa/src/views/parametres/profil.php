<?php
// Profil utilisateur page for BIKORWA SHOP
session_start();

/* ── CSRF ───────────────────────── */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ── Auth ───────────────────────── */
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

/* ── DB ─────────────────────────── */
require_once __DIR__ . '/../../config/database.php';
$pdo  = (new Database())->getConnection();

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: /login.php'); exit(); }

$page_title = 'Mon Profil';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($page_title) ?> - BIKORWA SHOP</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    .avatar-circle{width:80px;height:80px;border-radius:50%;
      background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
      display:flex;align-items:center;justify-content:center;
      color:#fff;font-size:32px;font-weight:bold}
    .card{transition:.2s transform}
    .card:hover{transform:translateY(-2px)}
  </style>
</head>
<body class="bg-light">
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><?= htmlspecialchars($page_title) ?></h2>
    <a href="../dashboard/index.php" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-2"></i>Retour
    </a>
  </div>

  <div class="row g-4">

    <!---- Profil left column ---->
    <div class="col-lg-4">
      <div class="card shadow-sm border-0 overflow-hidden">
        <div class="card-header bg-primary text-white py-3">
          <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Informations du compte</h5>
        </div>
        <div class="card-body text-center">
          <div class="avatar-circle mx-auto mb-3">
            <?= strtoupper(substr($user['nom'],0,1)) ?>
          </div>
          <h5 class="card-title"><?= htmlspecialchars($user['nom']) ?></h5>
          <p>
            <?php if ($user['role']==='gestionnaire'): ?>
              <span class="badge bg-primary">Gestionnaire</span>
            <?php elseif ($user['role']==='receptionniste'): ?>
              <span class="badge bg-info">Réceptionniste</span>
            <?php else: ?>
              <span class="badge bg-secondary">Utilisateur</span>
            <?php endif; ?>
          </p>

          <ul class="list-group list-group-flush text-start">
            <li class="list-group-item"><strong><i class="fas fa-user me-2"></i>Nom d'utilisateur :</strong>
              <span class="float-end"><?= htmlspecialchars($user['username']) ?></span></li>
            <li class="list-group-item"><strong><i class="fas fa-envelope me-2"></i>Email :</strong>
              <span class="float-end"><?= htmlspecialchars($user['email']) ?></span></li>
            <li class="list-group-item"><strong><i class="fas fa-calendar-alt me-2"></i>Date de création :</strong>
              <span class="float-end"><?= date('d/m/Y',strtotime($user['date_creation'])) ?></span></li>
            <li class="list-group-item"><strong><i class="fas fa-clock me-2"></i>Dernière connexion :</strong>
              <span class="float-end">
                <?= $user['derniere_connexion'] ? date('d/m/Y H:i',strtotime($user['derniere_connexion'])) : 'Jamais' ?>
              </span></li>
          </ul>
        </div>
      </div>
    </div>

    <!---- Right column (edit + password) ---->
    <div class="col-lg-8">

      <!---- Edit profile form ---->
      <div class="card shadow-sm border-0 overflow-hidden mb-4">
        <div class="card-header bg-primary text-white py-3">
          <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier mes informations</h5>
        </div>
        <div class="card-body">

          <!-- alert placeholder JUST for profile messages -->
          <div id="profileAlert" class="mb-3"></div>

          <form id="profileForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="mb-3">
              <label class="form-label" for="nom">Nom complet <span class="text-danger">*</span></label>
              <input id="nom" name="nom" class="form-control" required maxlength="100"
                     value="<?= htmlspecialchars($user['nom']) ?>">
              <div class="invalid-feedback">Veuillez saisir votre nom complet.</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
              <input id="email" name="email" type="email" class="form-control" required maxlength="255"
                     value="<?= htmlspecialchars($user['email']) ?>">
              <div class="invalid-feedback">Veuillez saisir une adresse email valide.</div>
            </div>
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
              <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>Enregistrer</button>
            </div>
          </form>
        </div>
      </div>

      <!---- Password form ---->
      <div class="card shadow-sm border-0 overflow-hidden">
        <div class="card-header bg-warning text-white py-3">
          <h5 class="mb-0"><i class="fas fa-key me-2"></i>Changer mon mot de passe</h5>
        </div>
        <div class="card-body">

          <!-- alert placeholder JUST for password messages -->
          <div id="passwordAlert" class="mb-3"></div>

          <form id="passwordForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
              <label class="form-label" for="current_password">Mot de passe actuel <span class="text-danger">*</span></label>
              <div class="input-group">
                <input id="current_password" name="current_password" type="password" class="form-control" required>
                <button class="btn btn-outline-secondary" type="button" id="toggleCurrent"><i class="fas fa-eye"></i></button>
              </div>
              <div class="invalid-feedback">Veuillez saisir votre mot de passe actuel.</div>
            </div>

            <div class="mb-3">
              <label class="form-label" for="new_password">Nouveau mot de passe <span class="text-danger">*</span></label>
              <div class="input-group">
                <input id="new_password" name="new_password" type="password" class="form-control"
                       required minlength="8" maxlength="255">
                <button class="btn btn-outline-secondary" type="button" id="toggleNew"><i class="fas fa-eye"></i></button>
              </div>
              <div class="form-text">Au moins 8 caractères, majuscule, minuscule, chiffre, caractère spécial.</div>
              <div class="invalid-feedback">Mot de passe trop faible.</div>
            </div>

            <div class="mb-3">
              <label class="form-label" for="confirm_password">Confirmer <span class="text-danger">*</span></label>
              <div class="input-group">
                <input id="confirm_password" name="confirm_password" type="password" class="form-control"
                       required minlength="8" maxlength="255">
                <button class="btn btn-outline-secondary" type="button" id="toggleConfirm"><i class="fas fa-eye"></i></button>
              </div>
              <div class="invalid-feedback">Les mots de passe ne correspondent pas.</div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
              <button class="btn btn-warning" type="submit"><i class="fas fa-key me-1"></i>Changer</button>
            </div>
          </form>
        </div>
      </div>

    </div><!-- /col-lg-8 -->
  </div><!-- /row -->
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ---- generic helpers ------------------------------------------- */
const mapType = t => t === 'error' ? 'danger' : t;

function injectAlert(containerId, rawType, msg){
  const type = mapType(rawType);
  document.getElementById(containerId).innerHTML = `
    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
      <i class="fas ${type==='success'?'fa-check-circle':'fa-exclamation-circle'} me-2"></i>${msg}
      <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

/* ---- visibility toggles ---------------------------------------- */
[['current_password','toggleCurrent'],
 ['new_password','toggleNew'],
 ['confirm_password','toggleConfirm']]
.forEach(([inputId,btnId])=>{
  const inp=document.getElementById(inputId), btn=document.getElementById(btnId);
  btn.addEventListener('click',()=>{
    inp.type = inp.type==='password' ? 'text' : 'password';
    btn.querySelector('i').classList.toggle('fa-eye');
    btn.querySelector('i').classList.toggle('fa-eye-slash');
  });
});

/* ---- profile form ---------------------------------------------- */
const profileForm=document.getElementById('profileForm');
profileForm.addEventListener('submit', async e=>{
  e.preventDefault();
  if(!profileForm.checkValidity()){ profileForm.classList.add('was-validated'); return; }

  try{
    const res  = await fetch('profil_update.php',{
      method:'POST',
      body:new FormData(profileForm),
      credentials:'same-origin'
    });
    const data = await res.json();
    injectAlert('profileAlert', data.status, data.message);
  }catch(err){
    injectAlert('profileAlert','danger','Erreur réseau : '+err.message);
  }
});

/* ---- password form --------------------------------------------- */
const passwordForm=document.getElementById('passwordForm');
passwordForm.addEventListener('submit', async e=>{
  e.preventDefault();
  if(!passwordForm.checkValidity()){ passwordForm.classList.add('was-validated'); return; }

  if(new_password.value !== confirm_password.value){
    confirm_password.setCustomValidity('Mismatch');
    passwordForm.classList.add('was-validated');
    return;
  }
  confirm_password.setCustomValidity('');

  try{
    const res  = await fetch('password_update.php',{
      method:'POST',
      body:new FormData(passwordForm),
      credentials:'same-origin'
    });
    const data = await res.json();
    injectAlert('passwordAlert', data.status, data.message);
    if(data.status==='success') passwordForm.reset();
  }catch(err){
    injectAlert('passwordAlert','danger','Erreur réseau : '+err.message);
  }
});
</script>
</body>
</html>
