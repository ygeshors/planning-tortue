<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Désactiver la mise en cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
if (defined('LSCACHE_IS_ESI')) {
    header('X-LiteSpeed-Cache-Control: no-cache');
}

// Gestion des dates
$month = isset($_REQUEST['month']) ? intval($_REQUEST['month']) : intval(date('m'));
$year = isset($_REQUEST['year']) ? intval($_REQUEST['year']) : intval(date('Y'));

if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

$first_day = new DateTime("$year-$month-01");
$last_day = new DateTime($first_day->format('Y-m-t'));

// Debug: Afficher les dates
error_log('Période recherchée : ' . $first_day->format('Y-m'));

// Récupération des missions
$query = $wpdb->prepare(
    "SELECT m.*, 
            COUNT(DISTINCT CASE WHEN i.statut = 'validee' THEN i.id END) as nb_inscrits
     FROM {$wpdb->prefix}pptm_missions m
     LEFT JOIN {$wpdb->prefix}pptm_inscriptions i ON m.id = i.mission_id
     WHERE DATE_FORMAT(m.date_mission, '%Y-%m') = %s 
     GROUP BY m.id
     ORDER BY m.date_mission ASC",
    $first_day->format('Y-m')
);

// Debug: Afficher la requête
error_log('Requête SQL : ' . $query);

$missions = $wpdb->get_results($query);

// Debug: Vérifier les résultats
error_log('Nombre de missions trouvées : ' . count($missions));

$missions_by_date = array();
foreach ($missions as $mission) {
    $date = date('Y-m-d', strtotime($mission->date_mission));
    if (!isset($missions_by_date[$date])) {
        $missions_by_date[$date] = array();
    }
    $missions_by_date[$date][] = $mission;
    // Debug: Afficher les missions par date
    error_log("Mission trouvée pour le $date : {$mission->secteur}");
}

$prev_month = new DateTime($first_day->format('Y-m-d'));
$prev_month->modify('-1 month');
$next_month = new DateTime($first_day->format('Y-m-d'));
$next_month->modify('+1 month');

$main_color = get_option('pptm_association_color', '#2271b1');

// Récupération des pages pour les liens
$bilan_page = get_page_by_path('bilan');
$inscription_page = get_page_by_path('inscription');
$bilan_page_id = $bilan_page ? $bilan_page->ID : 0;
$inscription_page_id = $inscription_page ? $inscription_page->ID : 0;

// Debug: Vérifier les IDs des pages
error_log('ID page bilan : ' . $bilan_page_id);
error_log('ID page inscription : ' . $inscription_page_id);
?>
<div class="pptm-container">
    <?php if ($logo = get_option('pptm_association_logo')): ?>
        <img src="<?php echo esc_url($logo); ?>" alt="Logo" class="pptm-logo">
    <?php endif; ?>

    <div class="pptm-calendar">
        <div class="calendar-header">
            <div class="calendar-nav">
                <!-- Navigation du mois précédent -->
                <form method="get" action="" class="nav-form prev-month">
                    <input type="hidden" name="month" value="<?php echo $prev_month->format('m'); ?>">
                    <input type="hidden" name="year" value="<?php echo $prev_month->format('Y'); ?>">
                    <button type="submit" class="nav-button">&larr; <?php echo date_i18n('M', $prev_month->getTimestamp()); ?></button>
                </form>
                
                <!-- Sélection du mois et de l'année -->
                <div class="current-month">
                    <h2><?php echo date_i18n('F Y', $first_day->getTimestamp()); ?></h2>
                    <form method="get" action="" class="month-selector">
                        <div class="select-wrapper">
                            <!-- Sélection du mois -->
                            <select name="month" onchange="this.form.submit()">
                                <?php for($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php selected($month, $m); ?>>
                                        <?php echo date_i18n('F', strtotime("2024-$m-01")); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>

                            <!-- Sélection de l'année -->
                            <select name="year" onchange="this.form.submit()">
                                <?php 
                                $current_year = intval(date('Y'));
                                // Augmenté à +6 pour inclure 2025
                                for($y = $current_year - 1; $y <= $current_year + 6; $y++): 
                                ?>
                                    <option value="<?php echo $y; ?>" <?php selected($year, $y); ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </form>
                </div>
                
                <!-- Navigation du mois suivant -->
                <form method="get" action="" class="nav-form next-month">
                    <input type="hidden" name="month" value="<?php echo $next_month->format('m'); ?>">
                    <input type="hidden" name="year" value="<?php echo $next_month->format('Y'); ?>">
                    <button type="submit" class="nav-button"><?php echo date_i18n('M', $next_month->getTimestamp()); ?> &rarr;</button>
                </form>
            </div>
        </div>

        <!-- Grille du calendrier -->
        <div class="calendar-wrapper">
            <table class="calendar-grid">
                <thead>
                    <tr>
                        <?php
                        $jours = array('Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim');
                        foreach ($jours as $jour) {
                            echo "<th>$jour</th>";
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Initialisation de la date courante au premier jour de la semaine
                $current_date = clone $first_day;
                $current_date->modify('-' . ($first_day->format('N') - 1) . ' days');

                // Boucle sur les semaines
                while ($current_date <= $last_day || $current_date->format('N') != 1) {
                    if ($current_date->format('N') == 1) {
                        echo '<tr>';
                    }

                    $date = $current_date->format('Y-m-d');
                    $is_today = date('Y-m-d') === $date;
                    $is_other_month = $current_date->format('m') != $month;
                    
                    $cell_classes = array('calendar-day');
                    if ($is_today) $cell_classes[] = 'today';
                    if ($is_other_month) $cell_classes[] = 'other-month';
                    
                    echo '<td class="' . implode(' ', $cell_classes) . '">';
                    echo '<div class="day-number">' . $current_date->format('j') . '</div>';
                    echo '<div class="day-content">';

                    // Debug: Vérifier les missions pour cette date
                    error_log("Vérification des missions pour le $date");
                    
                    if (isset($missions_by_date[$date]) && !empty($missions_by_date[$date])) {
                        foreach ($missions_by_date[$date] as $mission) {
                            // Debug: Afficher les détails de la mission
                            error_log("Traitement de la mission : {$mission->secteur} pour le $date");

                            $inscrits = $wpdb->get_results($wpdb->prepare(
                                "SELECT CONCAT(prenom, ' ', nom) as nom_complet
                                FROM {$wpdb->prefix}pptm_inscriptions 
                                WHERE mission_id = %d AND statut = 'validee'
                                ORDER BY date_inscription ASC",
                                $mission->id
                            ));

                            $complet = $mission->nb_inscrits >= $mission->nb_benevoles_max;
                            $mission_date = strtotime($mission->date_mission);
                            $today = strtotime('today');
                            $is_past = $mission_date < $today;
                            
                            $status_class = $complet ? 'complet' : 'available';
                            if ($is_past) $status_class .= ' past';

                            // Début de la carte de mission
                            echo '<div class="mission-card ' . $status_class . '">';
                            
                            // En-tête de la mission
                            echo '<div class="mission-header">';
                            echo '<div class="mission-title">';
                            echo '<strong>' . esc_html($mission->secteur) . '</strong>';
                            echo '<div class="benevoles-count">' . $mission->nb_inscrits . '/' . $mission->nb_benevoles_max . ' bénévoles</div>';
                            echo '</div>';
                            
                            // Bouton d'information
                            echo '<button type="button" class="info-button" onclick="showParticipants(\'' . 
                                esc_attr(json_encode([
                                    'date' => date_i18n('d/m/Y', strtotime($mission->date_mission)),
                                    'secteur' => $mission->secteur,
                                    'places' => $mission->nb_inscrits . '/' . $mission->nb_benevoles_max,
                                    'participants' => array_map(function($inscrit) {
                                        return ['nom' => $inscrit->nom_complet];
                                    }, $inscrits)
                                ])) . '\')"><span class="dashicons dashicons-info"></span></button>';
                            echo '</div>';
                            
                            // Contenu de la mission
                            echo '<div class="mission-content">';
                            echo '<div class="mission-buttons">';

                            // Bouton d'inscription
                            if (!$complet && !$is_past && $inscription_page_id) {
                                $link_url = add_query_arg('mission_id', $mission->id, get_permalink($inscription_page_id));
                                echo '<a href="' . esc_url($link_url) . '" class="mission-link">S\'inscrire</a>';
                            }

                            // Bouton bilan
                            if ($mission_date <= $today && $bilan_page_id) {
                                $bilan_url = add_query_arg('mission_id', $mission->id, get_permalink($bilan_page_id));
                                echo '<a href="' . esc_url($bilan_url) . '" class="bilan-link">Bilan</a>';
                            }

                            echo '</div>'; // Fin mission-buttons
                            echo '</div>'; // Fin mission-content
                            echo '</div>'; // Fin mission-card
                        }
                    } else {
                        // Debug: Aucune mission pour cette date
                        error_log("Aucune mission pour le $date");
                    }

                    echo '</div>'; // Fin day-content
                    echo '</td>';

                    if ($current_date->format('N') == 7) {
                        echo '</tr>';
                    }

                    $current_date->modify('+1 day');
                }
                ?>
                </tbody>
            </table>
        </div>
<!-- Modal des participants -->
<div id="participants-modal" class="pptm-modal">
            <div class="pptm-modal-content">
                <span class="pptm-modal-close">&times;</span>
                <h3>Détails de la mission</h3>
                <p><strong>Date :</strong> <span id="modal-date"></span></p>
                <p><strong>Secteur :</strong> <span id="modal-secteur"></span></p>
                <p><strong>Places :</strong> <span id="modal-places"></span></p>
                <div id="modal-participants">
                    <h4>Participants :</h4>
                    <ul class="participants-list" id="participants-list"></ul>
                </div>
            </div>
        </div>

        <!-- Légende -->
        <div class="calendar-legend">
            <h3>Légende</h3>
            <div class="legend-items">
                <div class="legend-item">
                    <div class="mission-card available">
                        <div class="mission-content">Mission disponible</div>
                    </div>
                </div>
                <div class="legend-item">
                    <div class="mission-card complet">
                        <div class="mission-content">Mission complète</div>
                    </div>
                </div>
                <div class="legend-item">
                    <div class="mission-card past">
                        <div class="mission-content">Mission passée</div>
                    </div>
                </div>
            </div>
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
    max-width: 150px !important;
    height: auto !important;
    margin: 0 auto 20px !important;
}

.pptm-calendar {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    padding: 24px;
}

.calendar-header {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 24px;
}

.calendar-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    gap: 8px;
}

.nav-button {
    background: #f0f0f1;
    color: #1d2327;
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.nav-button:hover {
    background: <?php echo $main_color; ?>;
    color: white;
}

.calendar-grid {
    width: 100%;
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 4px;
}

.calendar-grid th {
    padding: 8px 4px;
    text-align: center;
    font-weight: 600;
    background: #f0f0f1;
    border-radius: 6px;
    font-size: 14px;
    white-space: nowrap;
}

.calendar-day {
    height: 120px;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 8px 4px;
    vertical-align: top;
    background: white;
    width: calc(100% / 7);
    position: relative;
}

.day-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: calc(100% - 25px);
    overflow-y: auto;
}

.day-number {
    font-weight: 600;
    margin-bottom: 4px;
    text-align: center;
    font-size: 14px;
}

.mission-card {
    margin: 2px 0;
    padding: 6px 4px;
    border-radius: 4px;
    font-size: 11px;
    position: relative;
}

.mission-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.mission-title {
    text-align: center;
    font-size: 11px;
    line-height: 1.2;
}

.mission-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 4px;
}

.benevoles-count {
    font-size: 10px;
    text-align: center;
    color: #666;
    margin-top: 2px;
}

.mission-buttons {
    display: flex;
    gap: 4px;
    justify-content: center;
    flex-wrap: wrap;
}

.mission-link, .bilan-link {
    flex: 1;
    min-width: 60px;
    padding: 2px 6px;
    border-radius: 4px;
    text-decoration: none !important;
    font-size: 10px;
    text-align: center;
    color: white !important;
    white-space: nowrap;
    background: <?php echo $main_color; ?>;
}

.mission-link:hover, .bilan-link:hover {
    opacity: 0.9;
    color: white !important;
    text-decoration: none !important;
}

.mission-card.available {
    background: #e6f6ff;
    border: 1px solid <?php echo $main_color; ?>;
}

.mission-card.complet {
    background: #f0f0f1;
    border: 1px solid #646970;
}

.mission-card.past {
    background: #fff8e6;
    border: 1px solid #996b00;
}

.info-button {
    background: none;
    border: none;
    padding: 2px;
    cursor: pointer;
    color: rgba(0,0,0,0.5);
    transition: color 0.2s ease;
    z-index: 2;
    font-size: 12px;
}

.info-button:hover {
    color: rgba(0,0,0,0.8);
}

.calendar-legend {
    margin-top: 20px;
    padding-top: 10px;
    border-top: 1px solid #ddd;
}

.calendar-legend h3 {
    margin: 0 0 10px;
    font-size: 16px;
}

.legend-items {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.legend-item {
    flex: 0 1 auto;
    min-width: 120px;
}

.legend-item .mission-card {
    margin: 0;
    text-align: center;
}

/* Modal */
.pptm-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
}

.pptm-modal-content {
    background: white;
    width: 90%;
    max-width: 500px;
    margin: 50px auto;
    padding: 20px;
    border-radius: 8px;
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.pptm-modal-close {
    position: absolute;
    right: 15px;
    top: 15px;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    line-height: 1;
}

.participants-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.participants-list li {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.participants-list li:last-child {
    border-bottom: none;
}

@media (max-width: 768px) {
    .pptm-container {
        padding: 10px;
    }

    .pptm-calendar {
        padding: 12px 6px;
    }

    .calendar-grid th {
        font-size: 11px;
        padding: 4px 2px;
    }

    .calendar-day {
        min-height: 80px;
        padding: 4px 2px;
    }

    .day-number {
        font-size: 12px;
    }

    .mission-card {
        margin: 1px 0;
        padding: 4px 2px;
    }

    .mission-title strong {
        font-size: 10px;
    }

    .benevoles-count {
        font-size: 9px;
    }

    .mission-link, .bilan-link {
        font-size: 9px;
        padding: 2px 4px;
        min-width: 50px;
    }
}

@media (max-width: 480px) {
    .calendar-nav {
        flex-wrap: wrap;
    }

    .current-month {
        width: 100%;
        order: -1;
    }

    .nav-button {
        padding: 6px 12px;
        font-size: 12px;
    }

    .mission-card {
        margin: 1px 0;
    }

    .mission-title strong {
        font-size: 9px;
    }

    .benevoles-count {
        font-size: 8px;
    }
}
</style>

<script>
function showParticipants(missionDataStr) {
    try {
        const missionData = JSON.parse(missionDataStr);
        const modal = document.getElementById('participants-modal');
        
        document.getElementById('modal-date').textContent = missionData.date;
        document.getElementById('modal-secteur').textContent = missionData.secteur;
        document.getElementById('modal-places').textContent = missionData.places;
        
        const participantsList = document.getElementById('participants-list');
        participantsList.innerHTML = '';
        
        if (missionData.participants.length > 0) {
            missionData.participants.forEach(p => {
                const li = document.createElement('li');
                li.innerHTML = `<strong>${p.nom}</strong>`;
                participantsList.appendChild(li);
            });
        } else {
            participantsList.innerHTML = '<li>Aucun participant inscrit</li>';
        }
        
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    } catch (e) {
        console.error('Erreur lors de l\'affichage des participants:', e);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('participants-modal');
        if (e.target.matches('.info-button') || e.target.closest('.info-button')) {
            e.preventDefault();
            e.stopPropagation();
        } else if (e.target == modal || e.target.matches('.pptm-modal-close')) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
});
</script>