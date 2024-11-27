<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Activer l'affichage des erreurs pour le débogage
if (WP_DEBUG === true) {
    error_log('Démarrage du formulaire de bilan');
}

$mission_id = isset($_GET['mission_id']) ? intval($_GET['mission_id']) : 0;
$success_message = '';
$error_message = '';

// Styles spécifiques pour le formulaire
?>
<style>
.pptm-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    font-family: 'Marianne', system-ui, -apple-system, sans-serif;
}

.pptm-logo {
    display: block !important;
    max-width: 150px !important;
    height: auto !important;
    margin: 0 auto 20px !important;
}

.pptm-form-row {
    margin-bottom: 20px;
}

.pptm-input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.pptm-map {
    height: 400px !important;
    margin-bottom: 20px !important;
    border: 1px solid #ddd !important;
    border-radius: 4px !important;
}

.pptm-submit-btn {
    background: <?php echo get_option('pptm_association_color', '#2271b1'); ?>;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.pptm-link {
    color: <?php echo get_option('pptm_association_color', '#2271b1'); ?>;
    text-decoration: none;
}

.pptm-message {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
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
    color: #666;
    font-size: 0.9em;
    margin-top: 4px;
}

@media (max-width: 768px) {
    .pptm-container {
        padding: 10px;
    }
    
    .pptm-logo {
        max-width: 120px !important;
    }

    .pptm-map {
        height: 300px !important;
    }
}
</style>

<?php
// Si aucune mission n'est sélectionnée
if ($mission_id === 0) {
    ?>
    <div class="pptm-container">
        <?php if ($logo = get_option('pptm_association_logo')): ?>
            <img src="<?php echo esc_url($logo); ?>" alt="Logo" class="pptm-logo">
        <?php endif; ?>
        <div style="max-width: 600px; margin: 0 auto; text-align: center;">
            <h2>Bilan de mission</h2>
            <p>Pour soumettre votre bilan, veuillez d'abord sélectionner une mission dans le 
                <a href="<?php echo esc_url(home_url('/planning/')); ?>" class="pptm-link">calendrier</a>.
            </p>
        </div>
    </div>
    <?php
    return;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pptm_bilan'])) {
    $email = sanitize_email($_POST['email']);
    $traces = isset($_POST['traces']) ? 1 : 0;
    $commentaire = sanitize_textarea_field($_POST['commentaire']);
    $latitude = !empty($_POST['latitude']) ? sanitize_text_field($_POST['latitude']) : '0';
    $longitude = !empty($_POST['longitude']) ? sanitize_text_field($_POST['longitude']) : '0';
    
    // Vérification de l'inscription
    $inscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pptm_inscriptions 
        WHERE mission_id = %d AND email = %s AND statut = 'validee'",
        $mission_id, $email
    ));
    
    if (!$inscription) {
        $error_message = 'Veuillez utiliser l\'email avec lequel vous vous êtes inscrit à la mission.';
    } else {
        // Vérifier si un bilan existe déjà
        $bilan_existe = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}pptm_bilans 
            WHERE mission_id = %d AND email = %s",
            $mission_id, $email
        ));
        
        if ($bilan_existe) {
            $error_message = 'Vous avez déjà soumis un bilan pour cette mission.';
        } else {
            // Gestion de l'upload de photo
            $photo_url = '';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                
                $attachment_id = media_handle_upload('photo', 0);
                if (!is_wp_error($attachment_id)) {
                    $photo_url = wp_get_attachment_url($attachment_id);
                }
            }
            
            // Insertion du bilan
            $result = $wpdb->insert(
                $wpdb->prefix . 'pptm_bilans',
                array(
                    'mission_id' => $mission_id,
                    'email' => $email,
                    'traces' => $traces,
                    'commentaire' => $commentaire,
                    'photo_url' => $photo_url,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'date_creation' => current_time('mysql')
                ),
                array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result !== false) {
                $success_message = 'Votre bilan a bien été enregistré. Merci de votre participation !';
            } else {
                $error_message = 'Une erreur est survenue lors de l\'enregistrement du bilan.';
            }
        }
    }
}

// Récupération des informations de la mission
$mission = $wpdb->get_row($wpdb->prepare(
    "SELECT m.* FROM {$wpdb->prefix}pptm_missions m WHERE m.id = %d",
    $mission_id
));

if (!$mission) {
    ?>
    <div class="pptm-container">
        <?php if ($logo = get_option('pptm_association_logo')): ?>
            <img src="<?php echo esc_url($logo); ?>" alt="Logo" class="pptm-logo">
        <?php endif; ?>
        <div style="max-width: 600px; margin: 0 auto; text-align: center;">
            <h2>Mission non disponible</h2>
            <p>Cette mission n'existe pas ou n'est plus disponible.</p>
            <p><a href="<?php echo esc_url(home_url('/planning/')); ?>" class="pptm-link">&larr; Retour au calendrier</a></p>
        </div>
    </div>
    <?php
    return;
}

// Définir des valeurs par défaut pour la carte
$default_lat = '43.6047';  // Latitude par défaut (centre de l'Hérault)
$default_lng = '3.8869';   // Longitude par défaut (centre de l'Hérault)
$default_zoom = 13;        // Niveau de zoom par défaut
?>

<div class="pptm-container">
    <?php if ($logo = get_option('pptm_association_logo')): ?>
        <img src="<?php echo esc_url($logo); ?>" alt="Logo" class="pptm-logo">
    <?php endif; ?>

    <h2>Bilan de mission - <?php echo esc_html($mission->secteur); ?></h2>
    <p>Date de la mission : <?php echo date_i18n('d/m/Y', strtotime($mission->date_mission)); ?></p>

    <?php if ($success_message): ?>
        <div class="pptm-message pptm-message-success">
            <?php echo esc_html($success_message); ?>
            <p><a href="<?php echo esc_url(home_url('/planning/')); ?>" class="pptm-link">&larr; Retour au calendrier</a></p>
        </div>
    <?php else: ?>

        <?php if ($error_message): ?>
            <div class="pptm-message pptm-message-error">
                <?php echo esc_html($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="pptm-form-row">
                <label for="email">Email utilisé lors de l'inscription *</label>
                <input type="email" name="email" id="email" class="pptm-input" required>
            </div>

            <div class="pptm-form-row">
                <label>
                    <input type="checkbox" name="traces" id="traces">
                    J'ai observé des traces de tortues marines
                </label>
            </div>

            <div class="pptm-form-row">
                <label for="photo">Photo (optionnelle)</label>
                <input type="file" name="photo" id="photo" accept="image/*" class="pptm-input">
                <div class="pptm-help-text">Format accepté : JPG, PNG. Taille maximum : 5 MB</div>
            </div>

            <div class="pptm-form-row">
                <label for="commentaire">Commentaire (optionnel)</label>
                <textarea name="commentaire" id="commentaire" rows="4" class="pptm-input"></textarea>
            </div>

            <div class="pptm-form-row">
                <label>Position GPS</label>
                <div id="map" class="pptm-map"></div>
                <input type="hidden" name="latitude" id="latitude" value="<?php echo $default_lat; ?>">
                <input type="hidden" name="longitude" id="longitude" value="<?php echo $default_lng; ?>">
                <div class="pptm-help-text">Cliquez sur la carte pour indiquer votre position</div>
            </div>

            <div class="pptm-form-row">
                <button type="submit" name="pptm_bilan" class="pptm-submit-btn">Envoyer le bilan</button>
            </div>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Initialisation de la carte
                var map = L.map('map').setView([<?php echo $default_lat; ?>, <?php echo $default_lng; ?>], <?php echo $default_zoom; ?>);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                var marker;

                // Fonction pour mettre à jour le marqueur
                function updateMarker(latlng) {
                    if (marker) {
                        marker.setLatLng(latlng);
                    } else {
                        marker = L.marker(latlng).addTo(map);
                    }
                    document.getElementById('latitude').value = latlng.lat.toFixed(6);
                    document.getElementById('longitude').value = latlng.lng.toFixed(6);
                }

                // Gestionnaire de clic sur la carte
                map.on('click', function(e) {
                    updateMarker(e.latlng);
                });

                // Géolocalisation
                if ("geolocation" in navigator) {
                    navigator.geolocation.getCurrentPosition(function(position) {
                        var latlng = L.latLng(position.coords.latitude, position.coords.longitude);
                        map.setView(latlng, <?php echo $default_zoom; ?>);
                        updateMarker(latlng);
                    });
                }
            });
        </script>

    <?php endif; ?>
</div>