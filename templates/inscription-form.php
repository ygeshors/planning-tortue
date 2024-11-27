<?php
if (!defined('ABSPATH')) {
    exit;
}

// Déclaration de la variable globale wpdb
global $wpdb;

// Récupération de l'ID de la mission depuis l'URL
$mission_id = isset($_GET['mission_id']) ? intval($_GET['mission_id']) : 0;

// Messages de retour
$success_message = '';
$error_message = '';

// Si pas de mission_id, afficher un message et retourner
if ($mission_id === 0) {
    ?>
    <div class="pptm-container">
        <div style="max-width: 600px; margin: 0 auto; text-align: center;">
            <?php if ($logo = get_option('pptm_association_logo')): ?>
                <img src="<?php echo esc_url($logo); ?>" alt="Logo" class="pptm-logo">
            <?php endif; ?>
            <h2>Inscription à une mission</h2>
            <p>Pour vous inscrire, veuillez d'abord sélectionner une mission dans le 
                <a href="<?php echo esc_url(home_url('/planning/')); ?>" class="pptm-link">calendrier</a>.
            </p>
        </div>
    </div>
    <?php
    return;
}

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pptm_inscription'])) {
    // Récupération et nettoyage des données
    $nom = sanitize_text_field($_POST['nom']);
    $prenom = sanitize_text_field($_POST['prenom']);
    $email = sanitize_email($_POST['email']);
    $telephone = sanitize_text_field($_POST['telephone']);
    
    // Vérifications
    if (empty($nom) || empty($prenom) || empty($email) || empty($telephone)) {
        $error_message = 'Tous les champs sont obligatoires.';
    } elseif (!is_email($email)) {
        $error_message = 'L\'adresse email n\'est pas valide.';
    } elseif (!preg_match('/^[0-9]{10}$/', $telephone)) {
        $error_message = 'Le numéro de téléphone doit contenir 10 chiffres.';
    } else {
        // Vérifier si déjà inscrit
        $existe = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pptm_inscriptions 
            WHERE mission_id = %d AND email = %s",
            $mission_id, $email
        ));
        
        if ($existe) {
            $error_message = 'Vous êtes déjà inscrit pour cette mission.';
        } else {
            // Vérifier le nombre d'inscrits et la date de la mission
            $mission = $wpdb->get_row($wpdb->prepare(
                "SELECT m.*, COUNT(i.id) as nb_inscrits 
                FROM {$wpdb->prefix}pptm_missions m 
                LEFT JOIN {$wpdb->prefix}pptm_inscriptions i ON m.id = i.mission_id AND i.statut != 'refusee'
                WHERE m.id = %d AND m.date_mission >= CURDATE()
                GROUP BY m.id",
                $mission_id
            ));
            
            if (!$mission) {
                $error_message = 'Cette mission n\'existe pas ou est déjà passée.';
            } elseif ($mission->nb_inscrits >= $mission->nb_benevoles_max) {
                $error_message = 'Désolé, cette mission est complète.';
            } else {
                // Insérer l'inscription
                $result = $wpdb->insert(
                    $wpdb->prefix . 'pptm_inscriptions',
                    array(
                        'mission_id' => $mission_id,
                        'nom' => $nom,
                        'prenom' => $prenom,
                        'email' => $email,
                        'telephone' => $telephone,
                        'statut' => 'en_attente'
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%s')
                );
                
                if ($result !== false) {
                    pptm_send_email_confirmation($wpdb->insert_id);
                    pptm_send_email_admin($wpdb->insert_id);
                    $success_message = 'Votre inscription a bien été enregistrée. Vous recevrez bientôt un email de confirmation.';
                } else {
                    $error_message = 'Une erreur est survenue lors de l\'inscription.';
                }
            }
        }
    }
}

// Récupération des informations de la mission
$mission = $wpdb->get_row($wpdb->prepare(
    "SELECT m.*, COUNT(i.id) as nb_inscrits 
    FROM {$wpdb->prefix}pptm_missions m 
    LEFT JOIN {$wpdb->prefix}pptm_inscriptions i ON m.id = i.mission_id AND i.statut != 'refusee'
    WHERE m.id = %d AND m.date_mission >= CURDATE()
    GROUP BY m.id",
    $mission_id
));

// Récupération des inscrits validés
$inscrits_valides = $wpdb->get_results($wpdb->prepare(
    "SELECT CONCAT(prenom, ' ', nom) as nom_complet
     FROM {$wpdb->prefix}pptm_inscriptions 
     WHERE mission_id = %d 
     AND statut = 'validee'
     ORDER BY date_inscription ASC",
    $mission_id
));

if (!$mission) {
    ?>
    <div class="pptm-container">
        <div style="max-width: 600px; margin: 0 auto; text-align: center;">
            <?php if ($logo = get_option('pptm_association_logo')): ?>
                <img src="<?php echo esc_url($logo); ?>" alt="Logo" class="pptm-logo">
            <?php endif; ?>
            <h2>Mission non disponible</h2>
            <p>Cette mission n'existe pas ou a déjà eu lieu. 
            Veuillez consulter le <a href="<?php echo esc_url(home_url('/planning/')); ?>" class="pptm-link">calendrier</a> 
            pour voir les missions disponibles.</p>
        </div>
    </div>
    <?php
    return;
}

$places_restantes = $mission->nb_benevoles_max - $mission->nb_inscrits;
?>

<div class="pptm-container">
    <?php if ($logo = get_option('pptm_association_logo')): ?>
        <img src="<?php echo esc_url($logo); ?>" alt="Logo" class="pptm-logo">
    <?php endif; ?>

    <div style="max-width: 600px; margin: 0 auto;">
        <h2>Inscription à la mission du <?php echo date_i18n('d/m/Y', strtotime($mission->date_mission)); ?></h2>
        <p>
            Secteur : <?php echo esc_html($mission->secteur); ?><br>
            Places disponibles : <?php echo $places_restantes; ?>
        </p>

        <?php if ($success_message): ?>
            <div class="pptm-message pptm-message-success">
                <?php echo esc_html($success_message); ?>
            </div>
        <?php elseif ($error_message): ?>
            <div class="pptm-message pptm-message-error">
                <?php echo esc_html($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($inscrits_valides)): ?>
            <div class="pptm-inscrits-valides">
                <h3>Participants inscrits :</h3>
                <ul>
                    <?php foreach ($inscrits_valides as $inscrit): ?>
                        <li><?php echo esc_html($inscrit->nom_complet); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($places_restantes > 0 && !$success_message): ?>
            <form method="post" action="">
                <div class="pptm-form-row">
                    <label for="nom">Nom *</label>
                    <input type="text" id="nom" name="nom" required maxlength="100" 
                           class="pptm-input" value="<?php echo isset($_POST['nom']) ? esc_attr($_POST['nom']) : ''; ?>">
                </div>

                <div class="pptm-form-row">
                    <label for="prenom">Prénom *</label>
                    <input type="text" id="prenom" name="prenom" required maxlength="100" 
                           class="pptm-input" value="<?php echo isset($_POST['prenom']) ? esc_attr($_POST['prenom']) : ''; ?>">
                </div>

                <div class="pptm-form-row">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required maxlength="100" 
                           class="pptm-input" value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>">
                </div>

                <div class="pptm-form-row">
                    <label for="telephone">Téléphone *</label>
                    <input type="tel" id="telephone" name="telephone" required maxlength="10" 
                           class="pptm-input" value="<?php echo isset($_POST['telephone']) ? esc_attr($_POST['telephone']) : ''; ?>"
                           pattern="[0-9]{10}" placeholder="0612345678">
                    <p class="pptm-help-text">Format : 10 chiffres sans espaces</p>
                </div>

                <input type="hidden" name="pptm_inscription" value="1">
                
                <div class="pptm-form-row">
                    <button type="submit" class="pptm-submit-btn">S'inscrire</button>
                </div>
            </form>
        <?php elseif (!$success_message): ?>
            <div class="pptm-message pptm-message-error">
                Désolé, cette mission est complète.
            </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <a href="<?php echo esc_url(home_url('/planning/')); ?>" class="pptm-link">&larr; Retour au calendrier</a>
        </div>
    </div>
</div>

<style>
.pptm-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: 'Marianne', system-ui, -apple-system, sans-serif;
}

.pptm-logo {
    display: block !important;
    max-width: 300px !important;
    width: 300px !important;
    height: auto !important;
    margin: 0 auto 30px !important;
}

.pptm-form-row {
    margin-bottom: 20px;
}

.pptm-input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}

.pptm-submit-btn {
    width: 100%;
    padding: 12px;
    background: <?php echo get_option('pptm_association_color', '#2271b1'); ?>;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    font-family: 'Marianne', sans-serif;
    font-weight: 600;
    transition: background-color 0.2s ease;
}

.pptm-submit-btn:hover {
    filter: brightness(90%);
}

.pptm-message {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.pptm-message-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.pptm-message-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.pptm-help-text {
    font-size: 0.9em;
    color: #666;
    margin-top: 4px;
}

.pptm-link {
    color: <?php echo get_option('pptm_association_color', '#2271b1'); ?>;
    text-decoration: none;
    transition: color 0.2s ease;
}

.pptm-link:hover {
    text-decoration: underline;
}

.pptm-inscrits-valides {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.pptm-inscrits-valides h3 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #333;
}

.pptm-inscrits-valides ul {
    margin: 0;
    padding-left: 20px;
    list-style-type: disc;
}

.pptm-inscrits-valides li {
    margin-bottom: 5px;
    color: #666;
}

@media screen and (max-width: 768px) {
    .pptm-container {
        padding: 10px;
    }
    
    .pptm-logo {
        max-width: 200px !important;
        width: 200px !important;
    }
}
</style>