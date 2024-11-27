<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Traitement de la création de mission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pptm_new_mission'])) {
    check_admin_referer('pptm_nouvelle_mission');
    
    $date_mission = sanitize_text_field($_POST['date_mission']);
    $secteur = sanitize_text_field($_POST['secteur']);
    $nb_benevoles_max = intval($_POST['nb_benevoles_max']);
    
    if (empty($date_mission) || empty($secteur) || $nb_benevoles_max < 1) {
        $error_message = 'Tous les champs sont obligatoires.';
    } else {
        $result = $wpdb->insert(
            $wpdb->prefix . 'pptm_missions',
            array(
                'date_mission' => $date_mission,
                'secteur' => $secteur,
                'nb_benevoles_max' => $nb_benevoles_max,
                'statut' => 'active'
            ),
            array('%s', '%s', '%d', '%s')
        );
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=pptm-missions&mission_added=1'));
            exit;
        } else {
            $error_message = 'Erreur lors de la création de la mission.';
        }
    }
}

// Récupération des secteurs
$secteurs = $wpdb->get_results("
    SELECT nom, lat, lng, rayon 
    FROM {$wpdb->prefix}pptm_secteurs 
    ORDER BY nom ASC"
);

// Récupération de l'adresse de l'association
$association_address = get_option('pptm_association_address', '');
$default_lat = get_option('pptm_default_lat', '43.6007');
$default_lng = get_option('pptm_default_lng', '3.8796');
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-plus-alt"></span>
        Nouvelle Mission
    </h1>

    <?php if (isset($error_message)): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="pptm-admin-card">
        <form method="post" action="">
            <?php wp_nonce_field('pptm_nouvelle_mission'); ?>
            <input type="hidden" name="pptm_new_mission" value="1">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="date_mission">Date de la mission</label>
                    </th>
                    <td>
                        <input type="date" name="date_mission" id="date_mission" required 
                               value="<?php echo date('Y-m-d'); ?>" 
                               min="<?php echo date('Y-m-d'); ?>"
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="secteur">Secteur</label>
                    </th>
                    <td>
                        <?php if (!empty($secteurs)): ?>
                            <select name="secteur" id="secteur" required class="regular-text" onchange="showSectorOnMap(this.value)">
                                <option value="">Sélectionnez un secteur</option>
                                <?php foreach ($secteurs as $s): ?>
                                    <option value="<?php echo esc_attr($s->nom); ?>"
                                            data-lat="<?php echo esc_attr($s->lat); ?>"
                                            data-lng="<?php echo esc_attr($s->lng); ?>"
                                            data-rayon="<?php echo esc_attr($s->rayon); ?>">
                                        <?php echo esc_html($s->nom); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="pptm-map" id="map-secteur"></div>
                            <p class="description">
                                <a href="<?php echo admin_url('admin.php?page=pptm-secteurs'); ?>">
                                    <span class="dashicons dashicons-plus-alt"></span>
                                    Gérer les secteurs
                                </a>
                            </p>
                        <?php else: ?>
                            <p>Aucun secteur défini. 
                               <a href="<?php echo admin_url('admin.php?page=pptm-secteurs'); ?>">
                                   Créez d'abord un secteur
                               </a>.
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="nb_benevoles_max">Nombre maximum de bénévoles</label>
                    </th>
                    <td>
                        <input type="number" name="nb_benevoles_max" id="nb_benevoles_max" 
                               required min="1" value="2" class="small-text">
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-saved"></span>
                    Créer la mission
                </button>
                <a href="<?php echo admin_url('admin.php?page=pptm-missions'); ?>" class="button">
                    <span class="dashicons dashicons-dismiss"></span>
                    Annuler
                </a>
            </p>
        </form>
    </div>
</div>

<style>
#map-secteur {
    height: 400px;
    margin: 15px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.button .dashicons {
    vertical-align: middle;
    font-size: 16px;
    width: 16px;
    height: 16px;
    margin: -2px 2px 0 0;
}

.description .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    vertical-align: middle;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialisation de la carte
    let map = L.map('map-secteur').setView([<?php echo $default_lat; ?>, <?php echo $default_lng; ?>], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    let currentCircle = null;

    // Si une adresse d'association est définie, centrer la carte dessus
    <?php if (!empty($association_address)): ?>
        // Utiliser l'API de géocodage pour obtenir les coordonnées
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=<?php echo urlencode($association_address); ?>`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lon = parseFloat(data[0].lon);
                    map.setView([lat, lon], 13);
                }
            });
    <?php endif; ?>

    // Fonction pour afficher le secteur sur la carte
    window.showSectorOnMap = function(sectorName) {
        if (currentCircle) {
            map.removeLayer(currentCircle);
        }

        if (!sectorName) return;

        const option = document.querySelector(`option[value="${sectorName}"]`);
        if (!option) return;

        const lat = parseFloat(option.dataset.lat);
        const lng = parseFloat(option.dataset.lng);
        const rayon = parseInt(option.dataset.rayon);

        if (lat && lng && rayon) {
            currentCircle = L.circle([lat, lng], {
                radius: rayon,
                color: '<?php echo get_option('pptm_association_color', '#2271b1'); ?>',
                fillColor: '<?php echo get_option('pptm_association_color', '#2271b1'); ?>',
                fillOpacity: 0.2
            }).addTo(map);

            map.setView([lat, lng], 13);
        }
    };

    // Mettre à jour la carte quand elle devient visible
    setTimeout(() => map.invalidateSize(), 100);
});
</script>