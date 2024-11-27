<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Traitement de l'export CSV
if (isset($_POST['action']) && $_POST['action'] === 'export_bilans') {
    check_admin_referer('export_bilans');
    
    // Filtres de date
    $where = "WHERE 1=1";
    if (!empty($_POST['date_debut'])) {
        $where .= $wpdb->prepare(" AND m.date_mission >= %s", $_POST['date_debut']);
    }
    if (!empty($_POST['date_fin'])) {
        $where .= $wpdb->prepare(" AND m.date_mission <= %s", $_POST['date_fin']);
    }

    // Récupération des données
    $bilans = $wpdb->get_results("
        SELECT 
            DATE_FORMAT(m.date_mission, '%d/%m/%Y') as 'Date',
            m.secteur as 'Secteur',
            CONCAT(i.prenom, ' ', i.nom) as 'Bénévole',
            b.email as 'Email',
            CASE WHEN b.traces = 1 THEN 'Oui' ELSE 'Non' END as 'Traces',
            b.commentaire as 'Commentaire',
            b.latitude as 'Latitude',
            b.longitude as 'Longitude',
            b.photo_url as 'Photo'
        FROM {$wpdb->prefix}pptm_bilans b
        JOIN {$wpdb->prefix}pptm_missions m ON b.mission_id = m.id
        LEFT JOIN {$wpdb->prefix}pptm_inscriptions i 
            ON b.mission_id = i.mission_id 
            AND b.email = i.email
        $where
        ORDER BY m.date_mission DESC"
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bilans-' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($bilans)) {
        fputcsv($output, array_keys(get_object_vars($bilans[0])), ';');
        foreach ($bilans as $bilan) {
            fputcsv($output, get_object_vars($bilan), ';');
        }
    }
    
    fclose($output);
    exit;
}

// Récupération des bilans pour l'affichage
$bilans = $wpdb->get_results("
    SELECT 
        b.*, 
        m.date_mission, 
        m.secteur, 
        CONCAT(i.prenom, ' ', i.nom) as nom_complet,
        s.lat as secteur_lat,
        s.lng as secteur_lng,
        s.rayon as secteur_rayon
    FROM {$wpdb->prefix}pptm_bilans b
    JOIN {$wpdb->prefix}pptm_missions m ON b.mission_id = m.id
    LEFT JOIN {$wpdb->prefix}pptm_inscriptions i 
        ON b.mission_id = i.mission_id 
        AND b.email = i.email
    LEFT JOIN {$wpdb->prefix}pptm_secteurs s 
        ON m.secteur = s.nom
    ORDER BY m.date_mission DESC, b.date_creation DESC"
);
?>
<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-chart-bar"></span>
        Bilans des missions
    </h1>

    <div class="tablenav top">
        <form method="post" class="alignleft actions">
            <?php wp_nonce_field('export_bilans'); ?>
            <input type="hidden" name="action" value="export_bilans">
            
            <input type="date" name="date_debut" 
                   value="<?php echo isset($_POST['date_debut']) ? esc_attr($_POST['date_debut']) : ''; ?>"
                   placeholder="Date début">
            
            <input type="date" name="date_fin" 
                   value="<?php echo isset($_POST['date_fin']) ? esc_attr($_POST['date_fin']) : ''; ?>"
                   placeholder="Date fin">
            
            <button type="submit" class="button">
                <span class="dashicons dashicons-download"></span>
                Exporter en CSV
            </button>
        </form>
    </div>

    <!-- Liste des bilans -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Secteur</th>
                <th>Bénévole</th>
                <th>Traces observées</th>
                <th>Commentaire</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bilans)): ?>
                <tr>
                    <td colspan="6">Aucun bilan enregistré</td>
                </tr>
            <?php else: ?>
                <?php foreach ($bilans as $bilan): ?>
                    <tr>
                        <td><?php echo date_i18n('d/m/Y', strtotime($bilan->date_mission)); ?></td>
                        <td><?php echo esc_html($bilan->secteur); ?></td>
                        <td><?php echo esc_html($bilan->nom_complet ?: $bilan->email); ?></td>
                        <td>
                            <?php if ($bilan->traces): ?>
                                <span class="dashicons dashicons-yes" style="color: #46b450;" title="Traces observées"></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-no" style="color: #dc3232;" title="Aucune trace"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php 
                            $commentaire = esc_html($bilan->commentaire);
                            echo strlen($commentaire) > 50 ? substr($commentaire, 0, 47) . '...' : $commentaire;
                        ?></td>
                        <td>
                            <button type="button" class="button button-small view-bilan" 
                                    data-bilan='<?php echo json_encode([
                                        'id' => $bilan->id,
                                        'date' => date_i18n('d/m/Y', strtotime($bilan->date_mission)),
                                        'secteur' => $bilan->secteur,
                                        'benevole' => $bilan->nom_complet ?: $bilan->email,
                                        'traces' => $bilan->traces,
                                        'commentaire' => $bilan->commentaire,
                                        'photo' => $bilan->photo_url,
                                        'lat' => $bilan->latitude,
                                        'lng' => $bilan->longitude,
                                        'secteur_lat' => $bilan->secteur_lat,
                                        'secteur_lng' => $bilan->secteur_lng,
                                        'secteur_rayon' => $bilan->secteur_rayon
                                    ]); ?>'>
                                <span class="dashicons dashicons-visibility"></span>
                                Voir détails
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Modal pour les détails du bilan -->
    <div id="bilan-modal" class="pptm-modal">
        <div class="pptm-modal-content">
            <span class="pptm-modal-close">&times;</span>
            <h2>Détails du bilan</h2>
            <div class="pptm-modal-grid">
                <!-- Informations générales -->
                <div class="pptm-modal-info">
                    <table class="form-table">
                        <tr>
                            <th>Date de la mission</th>
                            <td id="bilan-date"></td>
                        </tr>
                        <tr>
                            <th>Secteur</th>
                            <td id="bilan-secteur"></td>
                        </tr>
                        <tr>
                            <th>Bénévole</th>
                            <td id="bilan-benevole"></td>
                        </tr>
                        <tr>
                            <th>Traces observées</th>
                            <td id="bilan-traces"></td>
                        </tr>
                        <tr>
                            <th>Commentaire</th>
                            <td id="bilan-commentaire"></td>
                        </tr>
                    </table>
                </div>

                <!-- Carte -->
                <div class="pptm-modal-map">
                    <div id="bilan-map"></div>
                </div>
                
                <!-- Photo -->
                <div id="bilan-photo" class="pptm-modal-photo"></div>
            </div>
        </div>
    </div>
    <style>
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
    max-width: 1000px;
    border-radius: 8px;
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.pptm-modal-close {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.pptm-modal-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.pptm-modal-info {
    grid-column: 1 / -1;
}

.pptm-modal-map #bilan-map {
    height: 300px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.pptm-modal-photo {
    grid-column: 1 / -1;
    text-align: center;
}

.pptm-modal-photo img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.form-table th {
    width: 150px;
    padding: 15px 10px 15px 0;
}

.button .dashicons {
    vertical-align: middle;
    font-size: 16px;
    width: 16px;
    height: 16px;
    margin: -2px 2px 0 0;
}

@media (min-width: 1200px) {
    .pptm-modal-info {
        grid-column: 1;
    }
    .pptm-modal-map {
        grid-column: 2;
    }
    .pptm-modal-photo {
        grid-column: 1 / -1;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    let bilanMap = null;

    $('.view-bilan').click(function() {
        const bilan = JSON.parse($(this).data('bilan'));
        
        // Remplir les informations
        $('#bilan-date').text(bilan.date);
        $('#bilan-secteur').text(bilan.secteur);
        $('#bilan-benevole').text(bilan.benevole);
        $('#bilan-traces').html(bilan.traces ? 
            '<span class="dashicons dashicons-yes" style="color: #46b450;"></span> Oui' : 
            '<span class="dashicons dashicons-no" style="color: #dc3232;"></span> Non'
        );
        $('#bilan-commentaire').text(bilan.commentaire || 'Aucun commentaire');
        
        // Afficher la photo si présente
        const photoContainer = $('#bilan-photo');
        if (bilan.photo) {
            photoContainer.html(`<img src="${bilan.photo}" alt="Photo du bilan">`);
        } else {
            photoContainer.html('<p>Aucune photo</p>');
        }
        
        // Initialiser la carte
        if (bilanMap) {
            bilanMap.remove();
        }

        if (bilan.lat && bilan.lng) {
            bilanMap = L.map('bilan-map').setView([bilan.lat, bilan.lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(bilanMap);
            
            // Ajouter le marqueur de position
            L.marker([bilan.lat, bilan.lng]).addTo(bilanMap)
                .bindPopup('Position du bénévole');
            
            // Ajouter le cercle du secteur si les coordonnées sont disponibles
            if (bilan.secteur_lat && bilan.secteur_lng) {
                L.circle([bilan.secteur_lat, bilan.secteur_lng], {
                    radius: bilan.secteur_rayon,
                    color: '<?php echo get_option('pptm_association_color', '#2271b1'); ?>',
                    fillColor: '<?php echo get_option('pptm_association_color', '#2271b1'); ?>',
                    fillOpacity: 0.1
                }).addTo(bilanMap);
            }

            // Attendre que la modal soit visible pour rafraîchir la carte
            setTimeout(() => {
                bilanMap.invalidateSize();
                if (bilan.lat && bilan.lng) {
                    bilanMap.setView([bilan.lat, bilan.lng], 13);
                }
            }, 100);
        } else {
            $('#bilan-map').html('<p>Aucune position GPS enregistrée</p>');
        }
        
        // Afficher la modal
        $('#bilan-modal').show();
    });
    
    // Fermeture de la modal
    $('.pptm-modal-close').click(function() {
        $('#bilan-modal').hide();
        if (bilanMap) {
            bilanMap.remove();
            bilanMap = null;
        }
    });
    
    $(window).click(function(e) {
        if ($(e.target).hasClass('pptm-modal')) {
            $('#bilan-modal').hide();
            if (bilanMap) {
                bilanMap.remove();
                bilanMap = null;
            }
        }
    });

    // Empêcher la fermeture lors du clic dans la modal
    $('.pptm-modal-content').click(function(e) {
        e.stopPropagation();
    });
});
</script>
