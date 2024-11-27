<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

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

$missions = $wpdb->get_results($wpdb->prepare(
    "SELECT m.*, 
            COUNT(DISTINCT CASE WHEN i.statut = 'validee' THEN i.id END) as nb_inscrits
     FROM {$wpdb->prefix}pptm_missions m
     LEFT JOIN {$wpdb->prefix}pptm_inscriptions i ON m.id = i.mission_id
     WHERE DATE_FORMAT(m.date_mission, '%Y-%m') = %s 
     AND m.statut = 'active'
     GROUP BY m.id
     ORDER BY m.date_mission ASC",
    $first_day->format('Y-m')
));

$missions_by_date = array();
foreach ($missions as $mission) {
    $date = date('Y-m-d', strtotime($mission->date_mission));
    if (!isset($missions_by_date[$date])) {
        $missions_by_date[$date] = array();
    }
    $missions_by_date[$date][] = $mission;
}

$prev_month = new DateTime($first_day->format('Y-m-d'));
$prev_month->modify('-1 month');
$next_month = new DateTime($first_day->format('Y-m-d'));
$next_month->modify('+1 month');

$main_color = get_option('pptm_association_color', '#2271b1');
?>
<div class="pptm-container">
    <?php if ($logo = get_option('pptm_association_logo')): ?>
        <img src="<?php echo esc_url($logo); ?>" alt="Logo" class="pptm-logo">
    <?php endif; ?>

    <div class="pptm-calendar">
        <div class="calendar-header">
            <form method="get" action="" class="nav-form">
                <input type="hidden" name="month" value="<?php echo $prev_month->format('m'); ?>">
                <input type="hidden" name="year" value="<?php echo $prev_month->format('Y'); ?>">
                <button type="submit" class="nav-button">&larr; <?php echo date_i18n('F', $prev_month->getTimestamp()); ?></button>
            </form>
            
            <div class="current-month">
                <h2><?php echo date_i18n('F Y', $first_day->getTimestamp()); ?></h2>
                <form method="get" action="" class="month-selector">
                    <select name="month" onchange="this.form.submit()">
                        <?php for($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php selected($month, $m); ?>>
                                <?php echo date_i18n('F', strtotime("2024-$m-01")); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <select name="year" onchange="this.form.submit()">
                        <?php 
                        $current_year = intval(date('Y'));
                        for($y = $current_year - 2; $y <= $current_year + 5; $y++): 
                        ?>
                            <option value="<?php echo $y; ?>" <?php selected($year, $y); ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>
            
            <form method="get" action="" class="nav-form">
                <input type="hidden" name="month" value="<?php echo $next_month->format('m'); ?>">
                <input type="hidden" name="year" value="<?php echo $next_month->format('Y'); ?>">
                <button type="submit" class="nav-button"><?php echo date_i18n('F', $next_month->getTimestamp()); ?> &rarr;</button>
            </form>
        </div>

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
                $current_date = clone $first_day;
                $current_date->modify('-' . ($first_day->format('N') - 1) . ' days');

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

                    if (isset($missions_by_date[$date])) {
                        foreach ($missions_by_date[$date] as $mission) {
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

                            $status_class = $complet ? 'complet' : 'available';

                            echo '<div class="mission-card ' . $status_class . '">';
                            echo '<div class="mission-header">';
                            echo '<strong>' . esc_html($mission->secteur) . '</strong>';
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
                            echo '<div class="mission-content">';
                            echo '<span class="benevoles-count">' . $mission->nb_inscrits . '/' . $mission->nb_benevoles_max . ' bénévoles</span>';
                            echo '<div class="mission-buttons">';
                            
                            $link_url = add_query_arg('mission_id', $mission->id, home_url('/inscription/'));
                            echo '<a href="' . esc_url($link_url) . '" class="mission-link">S\'inscrire</a>';

                            if ($mission_date <= $today) {
                                $bilan_url = add_query_arg('mission_id', $mission->id, home_url('/bilan/'));
                                echo '<a href="' . esc_url($bilan_url) . '" class="bilan-link">Bilan</a>';
                            }

                            echo '</div></div></div>';
                        }
                    }

                    echo '</td>';

                    if ($current_date->format('N') == 7) {
                        echo '</tr>';
                    }

                    $current_date->modify('+1 day');
                }
                ?>
            </tbody>
        </table>
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
    max-width: 200px !important;
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
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.nav-form {
    margin: 0;
}

.current-month {
    text-align: center;
}

.current-month h2 {
    margin: 0 0 8px;
    font-size: 24px;
}

.month-selector {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin: 0;
}

.month-selector select {
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid #ddd;
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
}

.nav-button:hover {
    background: <?php echo $main_color; ?>;
    color: white;
}

.calendar-grid {
    width: 100%;
    border-collapse: separate;
    border-spacing: 4px;
}

.calendar-grid th {
    padding: 12px;
    text-align: center;
    font-weight: 600;
    background: #f0f0f1;
    border-radius: 6px;
}

.calendar-day {
    height: 120px;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 8px;
    vertical-align: top;
    background: white;
}

.calendar-day.today {
    background: #f0f7ff;
    border-color: <?php echo $main_color; ?>;
}

.calendar-day.other-month {
    background: #f9f9f9;
    opacity: 0.7;
}

.day-number {
    font-weight: 600;
    margin-bottom: 8px;
}

.mission-card {
    padding: 8px;
    border-radius: 4px;
    margin-bottom: 4px;
    position: relative;
    font-size: 13px;
    transition: transform 0.2s ease;
}

.mission-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.mission-card:hover {
    transform: translateY(-1px);
}

.mission-card.available {
    background: #e6f6ff;
    border: 1px solid <?php echo $main_color; ?>;
}

.mission-card.complet {
    background: #f0f0f1;
    border: 1px solid #646970;
    opacity: 0.7;
}

.mission-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.benevoles-count {
    margin: 4px 0;
}

.info-button {
    background: none;
    border: none;
    padding: 2px;
    cursor: pointer;
    color: rgba(0,0,0,0.5);
    transition: color 0.2s ease;
    z-index: 2;
}

.info-button:hover {
    color: rgba(0,0,0,0.8);
}

.mission-buttons {
    display: flex;
    gap: 8px;
    justify-content: flex-start;
}

.mission-link, .bilan-link {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 12px;
}

.mission-link {
    background: <?php echo $main_color; ?>;
    color: white;
}

.bilan-link {
    background-color: <?php echo $main_color; ?>;
    color: white;
}

.mission-link:hover, .bilan-link:hover {
    opacity: 0.9;
    color: white;
    text-decoration: none;
}
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
    right: 20px;
    top: 20px;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.pptm-modal-close:hover {
    color: #000;
}

.participants-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.participants-list li {
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.participants-list li:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

@media (max-width: 768px) {
    .pptm-calendar {
        padding: 12px;
    }

    .calendar-header {
        flex-direction: column;
        gap: 12px;
    }

    .calendar-grid th {
        padding: 8px 4px;
        font-size: 12px;
    }

    .calendar-day {
        height: auto;
        min-height: 100px;
        padding: 4px;
    }

    .mission-card {
        font-size: 11px;
    }

    .mission-buttons {
        flex-direction: column;
        gap: 4px;
    }

    .mission-link, .bilan-link {
        text-align: center;
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
    } catch (e) {
        console.error('Erreur lors de l\'affichage des participants:', e);
    }
}

document.addEventListener('click', function(e) {
    const modal = document.getElementById('participants-modal');
    if (e.target.matches('.info-button') || e.target.closest('.info-button')) {
        e.preventDefault();
        e.stopPropagation();
    } else if (e.target == modal || e.target.matches('.pptm-modal-close')) {
        modal.style.display = 'none';
    }
});
</script>