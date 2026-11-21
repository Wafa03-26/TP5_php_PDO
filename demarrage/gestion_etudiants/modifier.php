<?php
/**
 * BONUS 5.3 : modification d'un étudiant.
 * GET  ?id=N : pré-remplit le formulaire (SELECT préparé par id)
 * POST       : UPDATE préparé, valeurs passées directement à execute([...])
 */
$titre = 'Modifier un étudiant';
require_once __DIR__ . '/config/connexion.php';

$pdo = getConnexion();

// L'id arrive en GET (lien depuis liste.php) ou en POST (champ caché du formulaire)
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
}
if (!$id) {
    header('Location: liste.php');
    exit;
}

// 1) Lecture de l'étudiant à modifier (requête préparée : l'id vient de l'utilisateur)
$stmt = $pdo->prepare('SELECT id, cne, nom, prenom, email, filiere, date_naissance
                       FROM etudiants WHERE id = :id');
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$etudiant = $stmt->fetch();

if (!$etudiant) {
    header('Location: liste.php');
    exit;
}

// 2) Valeurs du formulaire (celles de la base, puis celles saisies en cas d'erreur)
$donnees = [
    'cne'            => $etudiant['cne'],
    'nom'            => $etudiant['nom'],
    'prenom'         => $etudiant['prenom'],
    'email'          => $etudiant['email'],
    'filiere'        => $etudiant['filiere'],
    'date_naissance' => $etudiant['date_naissance'] ?? '',
];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($donnees as $champ => $v) {
        $donnees[$champ] = trim($_POST[$champ] ?? '');
    }
    $donnees['cne'] = strtoupper($donnees['cne']);

    if (!preg_match('/^[A-Z0-9]{6,20}$/', $donnees['cne'])) {
        $erreurs['cne'] = 'Le CNE doit contenir 6 à 20 lettres majuscules ou chiffres.';
    }
    if ($donnees['nom'] === '' || mb_strlen($donnees['nom']) > 50) {
        $erreurs['nom'] = 'Le nom est obligatoire (50 caractères max).';
    }
    if ($donnees['prenom'] === '' || mb_strlen($donnees['prenom']) > 50) {
        $erreurs['prenom'] = 'Le prénom est obligatoire (50 caractères max).';
    }
    if (!filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = "L'adresse e-mail n'est pas valide.";
    }
    if (!in_array($donnees['filiere'], FILIERES, true)) {
        $erreurs['filiere'] = 'Veuillez choisir une filière dans la liste.';
    }
    if ($donnees['date_naissance'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $donnees['date_naissance']);
        if (!$d || $d->format('Y-m-d') !== $donnees['date_naissance']) {
            $erreurs['date_naissance'] = 'Date de naissance invalide.';
        }
    }

    if (empty($erreurs)) {
        // 3) UPDATE préparé : on peut aussi passer les valeurs à execute() sous forme de tableau
        $sql = 'UPDATE etudiants
                   SET cne = :cne,
                       nom = :nom,
                       prenom = :prenom,
                       email = :email,
                       filiere = :filiere,
                       date_naissance = :date_naissance
                 WHERE id = :id';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cne'            => $donnees['cne'],
                ':nom'            => $donnees['nom'],
                ':prenom'         => $donnees['prenom'],
                ':email'          => $donnees['email'],
                ':filiere'        => $donnees['filiere'],
                ':date_naissance' => $donnees['date_naissance'] !== '' ? $donnees['date_naissance'] : null,
                ':id'             => $id,
            ]);

            header('Location: liste.php?modifie=1');
            exit;
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                $erreurs['global'] = 'Un étudiant avec ce CNE ou cet e-mail existe déjà.';
            } else {
                $erreurs['global'] = 'Erreur SQL (code ' . $e->getCode() . ') : ' . $e->getMessage();
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<h1>Modifier un étudiant</h1>

<?php if (!empty($erreurs)): ?>
    <div class="alerte alerte-erreur">
        <strong>Le formulaire contient des erreurs :</strong>
        <ul>
            <?php foreach ($erreurs as $msg): ?>
                <li><?= e($msg) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="carte">
    <form method="post" action="modifier.php?id=<?= (int) $id ?>" novalidate>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="grille">
            <div class="champ">
                <label for="cne">CNE <span class="obligatoire">*</span></label>
                <input type="text" id="cne" name="cne" maxlength="20" value="<?= e($donnees['cne']) ?>">
                <?php if (isset($erreurs['cne'])): ?><div class="erreur-champ"><?= e($erreurs['cne']) ?></div><?php endif; ?>
            </div>
            <div class="champ">
                <label for="email">E-mail <span class="obligatoire">*</span></label>
                <input type="email" id="email" name="email" maxlength="100" value="<?= e($donnees['email']) ?>">
                <?php if (isset($erreurs['email'])): ?><div class="erreur-champ"><?= e($erreurs['email']) ?></div><?php endif; ?>
            </div>
            <div class="champ">
                <label for="nom">Nom <span class="obligatoire">*</span></label>
                <input type="text" id="nom" name="nom" maxlength="50" value="<?= e($donnees['nom']) ?>">
                <?php if (isset($erreurs['nom'])): ?><div class="erreur-champ"><?= e($erreurs['nom']) ?></div><?php endif; ?>
            </div>
            <div class="champ">
                <label for="prenom">Prénom <span class="obligatoire">*</span></label>
                <input type="text" id="prenom" name="prenom" maxlength="50" value="<?= e($donnees['prenom']) ?>">
                <?php if (isset($erreurs['prenom'])): ?><div class="erreur-champ"><?= e($erreurs['prenom']) ?></div><?php endif; ?>
            </div>
            <div class="champ">
                <label for="filiere">Filière <span class="obligatoire">*</span></label>
                <select id="filiere" name="filiere">
                    <option value="">— Choisir —</option>
                    <?php foreach (FILIERES as $f): ?>
                        <option value="<?= e($f) ?>" <?= $donnees['filiere'] === $f ? 'selected' : '' ?>><?= e($f) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($erreurs['filiere'])): ?><div class="erreur-champ"><?= e($erreurs['filiere']) ?></div><?php endif; ?>
            </div>
            <div class="champ">
                <label for="date_naissance">Date de naissance</label>
                <input type="date" id="date_naissance" name="date_naissance" value="<?= e($donnees['date_naissance']) ?>">
                <?php if (isset($erreurs['date_naissance'])): ?><div class="erreur-champ"><?= e($erreurs['date_naissance']) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="actions">
            <button type="submit" class="btn">Enregistrer les modifications</button>
            <a href="liste.php" class="btn btn-secondaire">Annuler</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>