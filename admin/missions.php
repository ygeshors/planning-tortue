<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_admin_referer('pptm_missions_action');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_mission':
                if (isset($_POST['mission_id'])) {
                    pptm_delete_mission(intval($_POST['mission_id']));
                    $success_message = 'Mission supprimée avec succès.';
                }
                break;
            
            case 'validate_inscription':
                if (isset($_POST['inscription_id'])) {
                    $inscription_id = intval($_POST['inscription_id']);
                    $inscription = pptm_get_inscription($inscription_id);
                    
                    if ($inscription) {
                        $mission = pptm_get_mission($inscription->mission_id);
                        
                        if (!$mission) {
                            $error_message = 'Mission introuvable.';
                            break;
                        }

                        $nb_inscrits = intval($wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) 
                             FROM {$wpdb->prefix}pptm_inscriptions 
                             WHERE mission_id = %d 
                             AND statut = 'validee'",
                            $inscription->mission_id
                        )));
                        
                        if ($nb_inscrits < intval($mission->nb_benevoles_max)) {
                            $result = $wpdb->update(
                                $wpdb->prefix . 'pptm_inscriptions',
                                array('statut' => 'validee'),
                                array('id' => $inscription_id)
                            );
                            
                            if ($result !== false) {
                                pptm_send_email_validation($inscription_id);
                                $success_message = 'Inscription validée avec succès.';
                            }
                        } else {
                            $error_message = 'La mission est complète.';
                        }
                    }
                }
                break;

            case 'refuse_inscription':
                if (isset($_POST['inscription_id'])) {
                    $inscription_id = intval($_POST['inscription_id']);
                    $result = $wpdb->update(
                        $wpdb->prefix . 'pptm_inscriptions',
                        array('statut' => 'refusee'),
                        array('id' => $inscription_id)
                    );
                    
                    if ($result !== false) {
                        pptm_send_email_refus($inscription_id);
                        $success_message = 'Inscription refusée.';
                    }
                }
                break;
        }
    }
}
    
// Vue détaillée d'une mission
if (isset($_GET['view']) && $_GET['view'] === 'inscriptions' && isset($_GET['mission_id'])) {
    $mission_id = intval($_GET['mission_id']);
    $mission = pptm_get_mission($mission_id);

    if (!$mission) {
        wp_die('Mission non trouvée');
    }

    $inscriptions = $wpdb->get_results($wpdb->prepare(
        "SELECT i.*, m.date_mission 
         FROM {$wpdb->prefix}pptm_inscriptions i
         JOIN {$wpdb->prefix}pptm_missions m ON i.mission_id = m.id
         WHERE i.mission_id = %d 
         ORDER BY i.date_inscription ASC",
        $mission_id
    ));

    $nb_inscrits_valides = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) 
         FROM {$wpdb->prefix}pptm_inscriptions 
         WHERE mission_id = %d 
         AND statut = 'validee'",
        $mission_id
    )));
    
    include(PPTM_PATH . 'admin/views/mission-detail.php');
    return;
}

// Récupération de toutes les missions (futures et passées)
$missions = $wpdb->get_results(
    "SELECT m.*, 
            COUNT(DISTINCT CASE WHEN i.statut = 'validee' THEN i.id END) as nb_inscrits,
            COUNT(DISTINCT CASE WHEN i.statut = 'en_attente' THEN i.id END) as nb_en_attente,
            COUNT(DISTINCT b.id) as nb_bilans
     FROM {$wpdb->prefix}pptm_missions m 
     LEFT JOIN {$wpdb->prefix}pptm_inscriptions i ON m.id = i.mission_id
     LEFT JOIN {$wpdb->prefix}pptm_bilans b ON m.id = b.mission_id
     WHERE m.statut = 'active'
     GROUP BY m.id 
     ORDER BY m.date_mission DESC"
);

// Séparer les missions futures et passées
$missions_futures = array();
$missions_passees = array();
$today = date('Y-m-d');

foreach ($missions as $mission) {
    if ($mission->date_mission >= $today) {
        $missions_futures[] = $mission;
    } else {
        $missions_passees[] = $mission;
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-calendar-alt"></span>
        Gestion des Missions
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=pptm-nouvelle-mission'); ?>" class="page-title-action">
        <span class="dashicons dashicons-plus-alt"></span>
        Nouvelle mission
    </a>

    <?php if (isset($success_message)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($success_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Missions à venir -->
    <h2>
        <span class="dashicons dashicons-clock"></span>
        Missions à venir
    </h2>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Secteur</th>
                <th>Inscrits</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($missions_futures)): ?>
                <tr>
                    <td colspan="4">Aucune mission à venir</td>
                </tr>
            <?php else: ?>
                <?php foreach ($missions_futures as $mission): ?>
                    <tr>
                        <td><?php echo date_i18n('d/m/Y', strtotime($mission->date_mission)); ?></td>
                        <td><?php echo esc_html($mission->secteur); ?></td>
                        <td>
                            <?php 
                            echo intval($mission->nb_inscrits) . '/' . intval($mission->nb_benevoles_max);
                            if ($mission->nb_en_attente > 0) {
                                echo ' <span class="awaiting-mod">' . $mission->nb_en_attente . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=pptm-missions&view=inscriptions&mission_id=' . $mission->id); ?>" 
                               class="button button-small">
                                <span class="dashicons dashicons-groups"></span>
                                Voir inscriptions
                            </a>
                            
                            <form method="post" style="display: inline;" 
                                  onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette mission ?');">
                                <?php wp_nonce_field('pptm_missions_action'); ?>
                                <input type="hidden" name="action" value="delete_mission">
                                <input type="hidden" name="mission_id" value="<?php echo $mission->id; ?>">
                                <button type="submit" class="button button-small button-link-delete">
                                    <span class="dashicons dashicons-trash"></span>
                                    Supprimer
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Missions passées -->
    <h2>
        <span class="dashicons dashicons-backup"></span>
        Missions passées
    </h2>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Secteur</th>
                <th>Participants</th>
                <th>Bilans</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($missions_passees)): ?>
                <tr>
                    <td colspan="5">Aucune mission passée</td>
                </tr>
            <?php else: ?>
                <?php foreach ($missions_passees as $mission): ?>
                    <tr>
                        <td><?php echo date_i18n('d/m/Y', strtotime($mission->date_mission)); ?></td>
                        <td><?php echo esc_html($mission->secteur); ?></td>
                        <td><?php echo intval($mission->nb_inscrits); ?></td>
                        <td>
                            <?php if ($mission->nb_bilans > 0): ?>
                                <span class="dashicons dashicons-yes" style="color: #46b450;"></span>
                                <?php echo $mission->nb_bilans; ?> bilan(s)
                            <?php else: ?>
                                <span class="dashicons dashicons-no" style="color: #dc3232;"></span>
                                Aucun bilan
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=pptm-missions&view=inscriptions&mission_id=' . $mission->id); ?>" 
                               class="button button-small">
                                <span class="dashicons dashicons-groups"></span>
                                Voir inscriptions
                            </a>
                            
                            <?php if ($mission->nb_bilans > 0): ?>
                                <a href="<?php echo admin_url('admin.php?page=pptm-bilans&mission_id=' . $mission->id); ?>" 
                                   class="button button-small">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                    Voir bilans
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.awaiting-mod {
    display: inline-block;
    vertical-align: top;
    margin: 1px 0 0 2px;
    padding: 0 5px;
    min-width: 7px;
    height: 17px;
    border-radius: 11px;
    background-color: #ca4a1f;
    color: #fff;
    font-size: 9px;
    line-height: 17px;
    text-align: center;
}

.button .dashicons {
    vertical-align: middle;
    font-size: 16px;
    width: 16px;
    height: 16px;
    margin: -2px 2px 0 0;
}

h2 .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
    margin-right: 5px;
    vertical-align: middle;
}

.wp-heading-inline .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    margin-right: 5px;
    vertical-align: middle;
}
</style>