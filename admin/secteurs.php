<?php
// /admin/secteurs.php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug
error_log('PPTM: Chargement de secteurs.php');

// Sécurité
if (!defined('ABSPATH')) {
    error_log('PPTM: Tentative d\'accès direct');
    exit;
}

if (!current_user_can('manage_options')) {
    error_log('PPTM: Permissions insuffisantes');
    wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.'));
}

// Debug des chemins
error_log('PPTM: ABSPATH = ' . ABSPATH);
error_log('PPTM: PPTM_PATH = ' . PPTM_PATH);
error_log('PPTM: PPTM_URL = ' . PPTM_URL);

global $wpdb;

// Paramètres par défaut
$default_lat = get_option('pptm_default_lat', '43.6007');
$default_lng = get_option('pptm_default_lng', '3.8796');
$main_color = get_option('pptm_association_color', '#2271b1');

// Hooks AJAX
add_action('wp_ajax_pptm_add_secteur', 'pptm_ajax_add_secteur');
add_action('wp_ajax_pptm_edit_secteur', 'pptm_ajax_edit_secteur');
add_action('wp_ajax_pptm_delete_secteur', 'pptm_ajax_delete_secteur');

function pptm_ajax_add_secteur() {
    error_log('PPTM: Tentative d\'ajout d\'un secteur');
    check_ajax_referer('pptm_secteurs_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission refusée');
        return;
    }

    global $wpdb;
    
    $nom = sanitize_text_field($_POST['nom'] ?? '');
    $zone = sanitize_text_field($_POST['zone'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? '');

    error_log('PPTM: Données reçues - Nom: ' . $nom);

    if (empty($nom) || empty($zone)) {
        wp_send_json_error('Nom et zone sont requis');
        return;
    }
// Vérification du nom unique
$exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}pptm_secteurs WHERE nom = %s",
    $nom
));

if ($exists) {
    error_log('PPTM: Nom de secteur déjà existant: ' . $nom);
    wp_send_json_error('Un secteur avec ce nom existe déjà');
    return;
}

// Calcul du centre de la zone
$zone_array = json_decode($zone, true);
if (!$zone_array || empty($zone_array)) {
    error_log('PPTM: Zone invalide');
    wp_send_json_error('Zone invalide');
    return;
}

$lat_sum = $lng_sum = 0;
$count = count($zone_array);
foreach ($zone_array as $point) {
    if (!isset($point[0]) || !isset($point[1])) {
        error_log('PPTM: Format de coordonnées invalide');
        wp_send_json_error('Format de coordonnées invalide');
        return;
    }
    $lat_sum += $point[0];
    $lng_sum += $point[1];
}

$result = $wpdb->insert(
    $wpdb->prefix . 'pptm_secteurs',
    array(
        'nom' => $nom,
        'zone' => $zone,
        'description' => $description,
        'lat' => $lat_sum / $count,
        'lng' => $lng_sum / $count,
        'rayon' => 1000
    ),
    array('%s', '%s', '%s', '%f', '%f', '%d')
);

if ($result === false) {
    error_log('PPTM: Erreur SQL lors de l\'ajout: ' . $wpdb->last_error);
    wp_send_json_error('Erreur lors de l\'ajout : ' . $wpdb->last_error);
} else {
    error_log('PPTM: Secteur ajouté avec succès - ID: ' . $wpdb->insert_id);
    wp_send_json_success('Secteur ajouté avec succès');
}
}

function pptm_ajax_edit_secteur() {
error_log('PPTM: Tentative de modification d\'un secteur');
check_ajax_referer('pptm_secteurs_action', 'nonce');

if (!current_user_can('manage_options')) {
    wp_send_json_error('Permission refusée');
    return;
}

global $wpdb;

$secteur_id = intval($_POST['secteur_id'] ?? 0);
$nom = sanitize_text_field($_POST['nom'] ?? '');
$zone = sanitize_text_field($_POST['zone'] ?? '');
$description = sanitize_textarea_field($_POST['description'] ?? '');
$old_nom = sanitize_text_field($_POST['old_nom'] ?? '');

error_log('PPTM: Données reçues - ID: ' . $secteur_id . ', Nom: ' . $nom);
if (!$secteur_id || empty($nom) || empty($zone)) {
    error_log('PPTM: Données manquantes pour la modification');
    wp_send_json_error('Données manquantes');
    return;
}

// Vérification du secteur existant
$exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}pptm_secteurs WHERE id = %d",
    $secteur_id
));

if (!$exists) {
    error_log('PPTM: Secteur non trouvé - ID: ' . $secteur_id);
    wp_send_json_error('Secteur non trouvé');
    return;
}

// Vérification du nom unique si changé
if ($nom !== $old_nom) {
    $name_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}pptm_secteurs WHERE nom = %s AND id != %d",
        $nom, $secteur_id
    ));
    
    if ($name_exists) {
        error_log('PPTM: Nom de secteur déjà existant lors de la modification: ' . $nom);
        wp_send_json_error('Un secteur avec ce nom existe déjà');
        return;
    }
}

// Calcul du centre de la zone
$zone_array = json_decode($zone, true);
if (!$zone_array || empty($zone_array)) {
    error_log('PPTM: Zone invalide lors de la modification');
    wp_send_json_error('Zone invalide');
    return;
}

$lat_sum = $lng_sum = 0;
$count = count($zone_array);
foreach ($zone_array as $point) {
    if (!isset($point[0]) || !isset($point[1])) {
        error_log('PPTM: Format de coordonnées invalide lors de la modification');
        wp_send_json_error('Format de coordonnées invalide');
        return;
    }
    $lat_sum += $point[0];
    $lng_sum += $point[1];
}

// Mise à jour du secteur
$result = $wpdb->update(
    $wpdb->prefix . 'pptm_secteurs',
    array(
        'nom' => $nom,
        'zone' => $zone,
        'description' => $description,
        'lat' => $lat_sum / $count,
        'lng' => $lng_sum / $count
    ),
    array('id' => $secteur_id),
    array('%s', '%s', '%s', '%f', '%f'),
    array('%d')
);

if ($result === false) {
    error_log('PPTM: Erreur SQL lors de la modification: ' . $wpdb->last_error);
    wp_send_json_error('Erreur lors de la modification: ' . $wpdb->last_error);
} else {
    // Mise à jour des missions associées si le nom a changé
    if ($nom !== $old_nom) {
        $wpdb->update(
            $wpdb->prefix . 'pptm_missions',
            array('secteur' => $nom),
            array('secteur' => $old_nom)
        );
        error_log('PPTM: Mise à jour des missions avec le nouveau nom: ' . $nom);
    }
    error_log('PPTM: Secteur modifié avec succès - ID: ' . $secteur_id);
    wp_send_json_success('Secteur modifié avec succès');
}
}
function pptm_ajax_delete_secteur() {
    error_log('PPTM: Tentative de suppression d\'un secteur');
    check_ajax_referer('pptm_secteurs_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission refusée');
        return;
    }

    global $wpdb;
    
    $secteur_id = intval($_POST['secteur_id'] ?? 0);

    if (!$secteur_id) {
        error_log('PPTM: ID manquant pour la suppression');
        wp_send_json_error('ID du secteur manquant');
        return;
    }

    // Vérification des missions liées
    $missions_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}pptm_missions m 
         JOIN {$wpdb->prefix}pptm_secteurs s ON m.secteur = s.nom 
         WHERE s.id = %d",
        $secteur_id
    ));

    if ($missions_count > 0) {
        error_log('PPTM: Suppression impossible - missions liées existantes');
        wp_send_json_error('Impossible de supprimer ce secteur car il est lié à des missions existantes');
        return;
    }

    $result = $wpdb->delete(
        $wpdb->prefix . 'pptm_secteurs',
        array('id' => $secteur_id),
        array('%d')
    );

    if ($result === false) {
        error_log('PPTM: Erreur SQL lors de la suppression: ' . $wpdb->last_error);
        wp_send_json_error('Erreur lors de la suppression: ' . $wpdb->last_error);
    } else {
        error_log('PPTM: Secteur supprimé avec succès - ID: ' . $secteur_id);
        wp_send_json_success('Secteur supprimé avec succès');
    }
}

// Récupération des secteurs avec leurs statistiques
error_log('PPTM: Récupération des secteurs');
$secteurs = $wpdb->get_results("
    SELECT s.*, 
           COUNT(DISTINCT m.id) as nb_missions,
           COUNT(DISTINCT b.id) as nb_bilans,
           SUM(CASE WHEN b.traces = 1 THEN 1 ELSE 0 END) as nb_traces,
           CASE WHEN COUNT(DISTINCT m.id) > 0 THEN 1 ELSE 0 END as has_missions
    FROM {$wpdb->prefix}pptm_secteurs s
    LEFT JOIN {$wpdb->prefix}pptm_missions m ON s.nom = m.secteur
    LEFT JOIN {$wpdb->prefix}pptm_bilans b ON m.id = b.mission_id
    GROUP BY s.id
    ORDER BY s.nom ASC"
);

error_log('PPTM: Nombre de secteurs trouvés: ' . count($secteurs));

// Préparation des données pour JavaScript
$secteurs_js = array_map(function($s) {
    return array(
        'nom' => $s->nom,
        'zone' => $s->zone,
        'nb_missions' => intval($s->nb_missions),
        'nb_traces' => intval($s->nb_traces),
        'has_missions' => (bool)$s->has_missions
    );
}, $secteurs);
?>
<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-location"></span>
        Gestion des Secteurs
    </h1>
    
    <button type="button" class="page-title-action" id="add-secteur-btn">
        <span class="dashicons dashicons-plus-alt"></span>
        Ajouter un secteur
    </button>

    <!-- Message de statut -->
    <div id="pptm-message" style="display:none;" class="notice">
        <p></p>
    </div>

    <!-- Carte principale des secteurs -->
    <div class="pptm-map-container">
        <div id="pptm-map"></div>
        <div class="pptm-map-legend">
            <div class="legend-item">
                <span class="color-box active"></span> Secteurs avec missions
            </div>
            <div class="legend-item">
                <span class="color-box inactive"></span> Secteurs sans mission
            </div>
        </div>
    </div>

    <!-- Liste des secteurs -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Description</th>
                <th>Missions</th>
                <th>Bilans</th>
                <th>Traces</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($secteurs)): ?>
                <tr>
                    <td colspan="6">Aucun secteur défini</td>
                </tr>
            <?php else: ?>
                <?php foreach ($secteurs as $secteur): ?>
                    <tr data-id="<?php echo esc_attr($secteur->id); ?>">
                        <td><?php echo esc_html($secteur->nom); ?></td>
                        <td><?php echo nl2br(esc_html($secteur->description)); ?></td>
                        <td><?php echo intval($secteur->nb_missions); ?></td>
                        <td><?php echo intval($secteur->nb_bilans); ?></td>
                        <td>
                            <?php if ($secteur->nb_traces > 0): ?>
                                <span class="dashicons dashicons-visibility" style="color: #46b450;" 
                                      title="<?php echo intval($secteur->nb_traces); ?> trace(s) observée(s)"></span>
                                <?php echo intval($secteur->nb_traces); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-hidden" style="color: #dc3232;" 
                                      title="Aucune trace observée"></span>
                                0
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small edit-secteur-btn" 
                                    data-secteur='<?php echo json_encode([
                                        "id" => $secteur->id,
                                        "nom" => $secteur->nom,
                                        "description" => $secteur->description,
                                        "zone" => $secteur->zone,
                                        "lat" => $secteur->lat,
                                        "lng" => $secteur->lng
                                    ]); ?>'>
                                <span class="dashicons dashicons-edit"></span>
                                Modifier
                            </button>
                            
                            <?php if ($secteur->nb_missions == 0): ?>
                                <button type="button" class="button button-small button-link-delete delete-secteur-btn" 
                                        data-id="<?php echo $secteur->id; ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                    Supprimer
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
<!-- Modales -->
<div id="pptm-add-secteur" class="pptm-modal">
        <div class="pptm-modal-content">
            <span class="pptm-modal-close">&times;</span>
            <h2>
                <span class="dashicons dashicons-location"></span>
                Nouveau secteur
            </h2>
            <form method="post" id="add-secteur-form" class="secteur-form">
                <?php wp_nonce_field('pptm_secteurs_action'); ?>
                <input type="hidden" name="action" value="pptm_add_secteur">
                <input type="hidden" name="zone" id="add-zone">
                
                <div class="pptm-form-row">
                    <label for="nom">Nom du secteur *</label>
                    <input type="text" id="nom" name="nom" required class="regular-text">
                </div>

                <div class="pptm-form-row">
                    <label>Zone du secteur *</label>
                    <div id="pptm-map-add" class="pptm-map-draw"></div>
                    <p class="description">Utilisez l'outil Polygone pour dessiner la zone</p>
                </div>

                <div class="pptm-form-row">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" class="large-text"></textarea>
                </div>

                <div class="pptm-form-actions">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-saved"></span>
                        Ajouter le secteur
                    </button>
                    <button type="button" class="button cancel-modal">
                        <span class="dashicons dashicons-dismiss"></span>
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="pptm-edit-secteur" class="pptm-modal">
        <div class="pptm-modal-content">
            <span class="pptm-modal-close">&times;</span>
            <h2>
                <span class="dashicons dashicons-edit"></span>
                Modifier le secteur
            </h2>
            <form method="post" id="edit-secteur-form" class="secteur-form">
                <?php wp_nonce_field('pptm_secteurs_action'); ?>
                <input type="hidden" name="action" value="pptm_edit_secteur">
                <input type="hidden" name="secteur_id" id="edit-secteur-id">
                <input type="hidden" name="old_nom" id="edit-old-nom">
                <input type="hidden" name="zone" id="edit-zone">
                
                <div class="pptm-form-row">
                    <label for="edit-nom">Nom du secteur *</label>
                    <input type="text" id="edit-nom" name="nom" required class="regular-text">
                </div>

                <div class="pptm-form-row">
                    <label>Zone du secteur *</label>
                    <div id="pptm-map-edit" class="pptm-map-draw"></div>
                </div>

                <div class="pptm-form-row">
                    <label for="edit-description">Description</label>
                    <textarea id="edit-description" name="description" rows="3" class="large-text"></textarea>
                </div>

                <div class="pptm-form-actions">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-saved"></span>
                        Enregistrer
                    </button>
                    <button type="button" class="button cancel-modal">
                        <span class="dashicons dashicons-dismiss"></span>
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Styles -->
<style>
.pptm-map-container {
    height: 500px;
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
    position: relative;
}

#pptm-map, .pptm-map-draw {
    height: 100%;
    width: 100%;
}

.pptm-map-legend {
    position: absolute;
    bottom: 20px;
    right: 20px;
    background: white;
    padding: 10px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 1000;
}

.legend-item {
    display: flex;
    align-items: center;
    margin: 5px 0;
}

.color-box {
    width: 20px;
    height: 20px;
    margin-right: 8px;
    border-radius: 3px;
}

.color-box.active {
    background-color: <?php echo $main_color; ?>;
    opacity: 0.6;
}

.color-box.inactive {
    background-color: #dc3232;
    opacity: 0.6;
}

.pptm-modal {
    display: none;
    position: fixed;
    z-index: 99999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    overflow: auto;
}

.pptm-modal-content {
    background: white;
    margin: 5% auto;
    padding: 20px;
    width: 90%;
    max-width: 800px;
    border-radius: 8px;
    position: relative;
}

.pptm-modal-close {
    position: absolute;
    right: 20px;
    top: 10px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.pptm-modal-close:hover {
    color: #666;
}

.pptm-form-row {
    margin-bottom: 15px;
}

.pptm-form-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.pptm-help-text {
    color: #666;
    font-size: 13px;
    margin-top: 4px;
}

.pptm-form-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.button .dashicons {
    vertical-align: middle;
    font-size: 16px;
    width: 16px;
    height: 16px;
    margin: -2px 2px 0 0;
}

.pptm-map-draw {
    height: 400px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 10px 0;
}

.leaflet-draw-toolbar {
    display: block !important;
}
</style>

<!-- Initialisation JavaScript -->
<script>
    var pptmSecteurs = <?php echo json_encode($secteurs_js); ?>;
</script>
<?php // Fin du fichier secteurs.php ?>