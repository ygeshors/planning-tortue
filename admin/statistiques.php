<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Traitement de l'export CSV
if (isset($_POST['action']) && $_POST['action'] === 'export_stats') {
    check_admin_referer('export_stats');
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=statistiques-' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Statistiques générales
    $stats = $wpdb->get_row("
        SELECT 
            COUNT(DISTINCT m.id) as total_missions,
            SUM(CASE WHEN m.date_mission >= CURDATE() THEN 1 ELSE 0 END) as missions_futures,
            COUNT(DISTINCT CASE WHEN i.statut = 'validee' THEN i.id END) as inscriptions_validees,
            COUNT(DISTINCT b.id) as total_bilans,
            SUM(CASE WHEN b.traces = 1 THEN 1 ELSE 0 END) as total_traces
        FROM {$wpdb->prefix}pptm_missions m
        LEFT JOIN {$wpdb->prefix}pptm_inscriptions i ON m.id = i.mission_id
        LEFT JOIN {$wpdb->prefix}pptm_bilans b ON m.id = b.mission_id
        WHERE m.statut = 'active'"
    );
    
    fputcsv($output, array('Statistiques générales'), ';');
    fputcsv($output, array('Type', 'Valeur'), ';');
    fputcsv($output, array('Missions totales', $stats->total_missions), ';');
    fputcsv($output, array('Missions à venir', $stats->missions_futures), ';');
    fputcsv($output, array('Inscriptions validées', $stats->inscriptions_validees), ';');
    fputcsv($output, array('Traces observées', $stats->total_traces), ';');
    fputcsv($output, array(''), ';');
    
    // Stats mensuelles
    fputcsv($output, array('Activité mensuelle'), ';');
    fputcsv($output, array('Mois', 'Missions', 'Inscriptions', 'Traces'), ';');
    foreach ($activite_mensuelle as $am) {
        fputcsv($output, array(
            $am->mois,
            $am->nb_missions,
            $am->nb_inscrits,
            $am->nb_traces
        ), ';');
    }
    
    fclose($output);
    exit();
}

// Statistiques générales
$stats = $wpdb->get_row("
    SELECT 
        COUNT(DISTINCT m.id) as total_missions,
        SUM(CASE WHEN m.date_mission >= CURDATE() THEN 1 ELSE 0 END) as missions_futures,
        COUNT(DISTINCT CASE WHEN i.statut = 'validee' THEN i.id END) as inscriptions_validees,
        COUNT(DISTINCT b.id) as total_bilans,
        SUM(CASE WHEN b.traces = 1 THEN 1 ELSE 0 END) as total_traces
    FROM {$wpdb->prefix}pptm_missions m
    LEFT JOIN {$wpdb->prefix}pptm_inscriptions i ON m.id = i.mission_id
    LEFT JOIN {$wpdb->prefix}pptm_bilans b ON m.id = b.mission_id
    WHERE m.statut = 'active'"
);

// Activité mensuelle depuis 2024
$activite_mensuelle = $wpdb->get_results("
    WITH RECURSIVE dates AS (
        SELECT DATE_FORMAT(CURDATE(), '%Y-%m-01') as date
        UNION ALL
        SELECT DATE_SUB(date, INTERVAL 1 MONTH)
        FROM dates
        WHERE DATE_FORMAT(date, '%Y-%m') >= '2024-01'
    )
    SELECT 
        DATE_FORMAT(d.date, '%b %Y') as mois,
        DATE_FORMAT(d.date, '%Y-%m') as mois_annee,
        COALESCE(COUNT(DISTINCT m.id), 0) as nb_missions,
        COALESCE(COUNT(DISTINCT CASE WHEN i.statut = 'validee' THEN i.id END), 0) as nb_inscrits,
        COALESCE(SUM(CASE WHEN b.traces = 1 THEN 1 ELSE 0 END), 0) as nb_traces
    FROM dates d
    LEFT JOIN {$wpdb->prefix}pptm_missions m 
        ON DATE_FORMAT(m.date_mission, '%Y-%m') = DATE_FORMAT(d.date, '%Y-%m')
    LEFT JOIN {$wpdb->prefix}pptm_inscriptions i ON m.id = i.mission_id
    LEFT JOIN {$wpdb->prefix}pptm_bilans b ON m.id = b.mission_id
    GROUP BY d.date
    ORDER BY d.date DESC
    LIMIT 12"
);
// Statistiques par secteur
$activite_secteurs = $wpdb->get_results("
    SELECT 
        s.nom as secteur,
        s.lat,
        s.lng,
        COUNT(DISTINCT m.id) as nb_missions,
        COUNT(DISTINCT CASE WHEN i.statut = 'validee' THEN i.id END) as nb_inscrits,
        SUM(CASE WHEN b.traces = 1 THEN 1 ELSE 0 END) as nb_traces,
        CASE 
            WHEN COUNT(DISTINCT m.id) > 0 
            THEN ROUND((SUM(CASE WHEN b.traces = 1 THEN 1 ELSE 0 END) * 100.0) / COUNT(DISTINCT m.id))
            ELSE 0 
        END as taux_traces
    FROM {$wpdb->prefix}pptm_secteurs s
    LEFT JOIN {$wpdb->prefix}pptm_missions m ON s.nom = m.secteur AND m.statut = 'active'
    LEFT JOIN {$wpdb->prefix}pptm_inscriptions i ON m.id = i.mission_id
    LEFT JOIN {$wpdb->prefix}pptm_bilans b ON m.id = b.mission_id
    GROUP BY s.id, s.nom, s.lat, s.lng
    HAVING nb_missions > 0
    ORDER BY nb_traces DESC"
);

// Préparation des données pour les graphiques
$chart_data = array(
    'labels' => array(),
    'missions' => array(),
    'inscrits' => array(),
    'traces' => array()
);

foreach ($activite_mensuelle as $am) {
    $chart_data['labels'][] = $am->mois;
    $chart_data['missions'][] = intval($am->nb_missions);
    $chart_data['inscrits'][] = intval($am->nb_inscrits);
    $chart_data['traces'][] = intval($am->nb_traces);
}

$map_data = array();
foreach ($activite_secteurs as $as) {
    if ($as->lat && $as->lng) {
        $map_data[] = array(
            'secteur' => $as->secteur,
            'lat' => $as->lat,
            'lng' => $as->lng,
            'missions' => $as->nb_missions,
            'traces' => $as->nb_traces,
            'taux' => min(100, intval($as->taux_traces))
        );
    }
}

// Start of the HTML
?>
<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-chart-line"></span>
        Statistiques
    </h1>

    <button type="submit" form="export-form" class="page-title-action">
        <span class="dashicons dashicons-download"></span>
        Exporter en CSV
    </button>

    <form id="export-form" method="post" style="display: none;">
        <?php wp_nonce_field('export_stats'); ?>
        <input type="hidden" name="action" value="export_stats">
    </form>

    <!-- Vue d'ensemble -->
    <div class="pptm-stats-grid">
        <div class="pptm-stat-card">
            <span class="dashicons dashicons-calendar-alt"></span>
            <div class="stat-value"><?php echo intval($stats->total_missions); ?></div>
            <div class="stat-label">Missions totales</div>
        </div>

        <div class="pptm-stat-card">
            <span class="dashicons dashicons-clock"></span>
            <div class="stat-value"><?php echo intval($stats->missions_futures); ?></div>
            <div class="stat-label">Missions à venir</div>
        </div>

        <div class="pptm-stat-card">
            <span class="dashicons dashicons-groups"></span>
            <div class="stat-value"><?php echo intval($stats->inscriptions_validees); ?></div>
            <div class="stat-label">Inscriptions validées</div>
        </div>

        <div class="pptm-stat-card">
            <span class="dashicons dashicons-visibility"></span>
            <div class="stat-value"><?php echo intval($stats->total_traces); ?></div>
            <div class="stat-label">Traces observées</div>
        </div>
    </div>
<!-- Graphiques -->
<div class="pptm-chart-grid">
        <!-- Activité mensuelle -->
        <div class="pptm-chart-card">
            <h2>
                <span class="dashicons dashicons-chart-bar"></span>
                Activité mensuelle
            </h2>
            <canvas id="activity-chart" style="width: 100%; height: 300px;"></canvas>
        </div>

        <!-- Carte des secteurs -->
        <div class="pptm-chart-card">
            <h2>
                <span class="dashicons dashicons-location"></span>
                Répartition géographique
            </h2>
            <div id="map-statistiques"></div>
        </div>
    </div>

    <!-- Statistiques par secteur -->
    <div class="pptm-chart-card">
        <h2>
            <span class="dashicons dashicons-analytics"></span>
            Activité par secteur
        </h2>
        <div class="pptm-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Secteur</th>
                        <th>Missions</th>
                        <th>Participants</th>
                        <th>Traces</th>
                        <th>Taux d'observation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activite_secteurs as $as): ?>
                        <tr>
                            <td><?php echo esc_html($as->secteur); ?></td>
                            <td><?php echo intval($as->nb_missions); ?></td>
                            <td><?php echo intval($as->nb_inscrits); ?></td>
                            <td>
                                <?php if ($as->nb_traces > 0): ?>
                                    <span class="dashicons dashicons-visibility" style="color: #46b450;" title="<?php echo $as->nb_traces; ?> trace(s) observée(s)"></span>
                                    <?php echo intval($as->nb_traces); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-hidden" style="color: #dc3232;" title="Aucune trace observée"></span>
                                    0
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="progress-bar-container">
                                    <div class="progress-bar">
                                        <div class="progress" style="width: <?php echo min(100, intval($as->taux_traces)); ?>%;">
                                            <?php echo min(100, intval($as->taux_traces)); ?>%
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<style>
.pptm-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.pptm-stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
    transition: transform 0.2s ease;
}

.pptm-stat-card:hover {
    transform: translateY(-2px);
}

.pptm-stat-card .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: <?php echo get_option('pptm_association_color', '#2271b1'); ?>;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #1d2327;
    margin: 10px 0;
}

.stat-label {
    color: #50575e;
}

.pptm-chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.pptm-chart-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.pptm-chart-card h2 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 0;
    font-size: 18px;
}

.pptm-chart-card h2 .dashicons {
    color: <?php echo get_option('pptm_association_color', '#2271b1'); ?>;
}

#map-statistiques {
    height: 400px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 10px 0;
}

.progress-bar-container {
    width: 100%;
    padding: 5px 0;
}

.progress-bar {
    background: #f0f0f1;
    border-radius: 10px;
    height: 20px;
    overflow: hidden;
    position: relative;
    width: 100%;
}

.progress {
    background: <?php echo get_option('pptm_association_color', '#2271b1'); ?>;
    height: 100%;
    color: white;
    text-align: center;
    line-height: 20px;
    font-size: 12px;
    transition: width 0.3s ease;
    position: absolute;
    left: 0;
    top: 0;
}

.button .dashicons {
    vertical-align: middle;
    font-size: 16px;
    width: 16px;
    height: 16px;
    margin: -2px 2px 0 0;
}

@media (max-width: 768px) {
    .pptm-chart-grid {
        grid-template-columns: 1fr;
    }
    
    #map-statistiques {
        height: 300px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Configuration du graphique d'activité
    const ctx = document.getElementById('activity-chart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_reverse($chart_data['labels'])); ?>,
            datasets: [
                {
                    label: 'Missions',
                    data: <?php echo json_encode(array_reverse($chart_data['missions'])); ?>,
                    backgroundColor: '<?php echo get_option('pptm_association_color', '#2271b1'); ?>',
                    borderRadius: 4,
                    order: 1
                },
                {
                    label: 'Participants',
                    data: <?php echo json_encode(array_reverse($chart_data['inscrits'])); ?>,
                    backgroundColor: '#46b450',
                    borderRadius: 4,
                    order: 2
                },
                {
                    label: 'Traces',
                    data: <?php echo json_encode(array_reverse($chart_data['traces'])); ?>,
                    backgroundColor: '#dc3232',
                    borderRadius: 4,
                    order: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });

    // Configuration de la carte
    const map = L.map('map-statistiques').setView([
        <?php echo get_option('pptm_default_lat', '43.6007'); ?>, 
        <?php echo get_option('pptm_default_lng', '3.8796'); ?>
    ], 10);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Ajout des marqueurs pour chaque secteur
    const markers = [];
    <?php foreach ($map_data as $data): ?>
    const marker = L.circleMarker(
        [<?php echo $data['lat']; ?>, <?php echo $data['lng']; ?>],
        {
            radius: Math.max(8, Math.min(20, <?php echo $data['missions']; ?> * 5)),
            color: '<?php echo get_option('pptm_association_color', '#2271b1'); ?>',
            fillColor: '<?php echo get_option('pptm_association_color', '#2271b1'); ?>',
            fillOpacity: 0.6,
            weight: 2
        }
    ).addTo(map);
    
    marker.bindPopup(`
        <strong><?php echo esc_js($data['secteur']); ?></strong><br>
        Missions: <?php echo $data['missions']; ?><br>
        Traces: <?php echo $data['traces']; ?><br>
        Taux: <?php echo $data['taux']; ?>%
    `);
    
    markers.push(marker);
    <?php endforeach; ?>

    // Ajuster la carte aux limites de tous les marqueurs s'il y en a
    if (markers.length > 0) {
        const group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));
    }

    // S'assurer que la carte est bien rendue
    setTimeout(() => {
        map.invalidateSize();
    }, 100);
});
</script>