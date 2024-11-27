<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!$mission || !isset($inscriptions)) {
    wp_die('Données de mission non disponibles');
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-groups"></span>
        Inscriptions pour la mission du <?php echo date_i18n('d/m/Y', strtotime($mission->date_mission)); ?>
        <span class="page-title-action">
            Places : <?php echo $nb_inscrits_valides; ?>/<?php echo $mission->nb_benevoles_max; ?>
        </span>
    </h1>

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

    <!-- Détails de la mission -->
    <div class="pptm-mission-details">
        <div class="pptm-info-grid">
            <div class="pptm-info-card">
                <span class="dashicons dashicons-location"></span>
                <div class="info-title">Secteur</div>
                <div class="info-value"><?php echo esc_html($mission->secteur); ?></div>
            </div>
            <div class="pptm-info-card">
                <span class="dashicons dashicons-groups"></span>
                <div class="info-title">Places disponibles</div>
                <div class="info-value"><?php echo ($mission->nb_benevoles_max - $nb_inscrits_valides); ?></div>
            </div>
            <?php 
            $bilans = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) as total_bilans, SUM(CASE WHEN traces = 1 THEN 1 ELSE 0 END) as nb_traces
                 FROM {$wpdb->prefix}pptm_bilans 
                 WHERE mission_id = %d", 
                $mission->id
            ));
            ?>
            <div class="pptm-info-card">
                <span class="dashicons dashicons-welcome-write-blog"></span>
                <div class="info-title">Bilans reçus</div>
                <div class="info-value"><?php echo intval($bilans->total_bilans); ?></div>
            </div>
            <div class="pptm-info-card">
                <span class="dashicons dashicons-visibility"></span>
                <div class="info-title">Traces observées</div>
                <div class="info-value"><?php echo intval($bilans->nb_traces); ?></div>
            </div>
        </div>
    </div>

    <!-- Liste des inscriptions -->
    <div class="pptm-table-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date d'inscription</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inscriptions)): ?>
                    <tr>
                        <td colspan="7">Aucune inscription</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inscriptions as $inscription): ?>
                        <tr>
                            <td><?php echo date_i18n('d/m/Y H:i', strtotime($inscription->date_inscription)); ?></td>
                            <td><?php echo esc_html($inscription->nom); ?></td>
                            <td><?php echo esc_html($inscription->prenom); ?></td>
                            <td><?php echo esc_html($inscription->email); ?></td>
                            <td><?php echo esc_html($inscription->telephone); ?></td>
                            <td>
                                <?php 
                                switch ($inscription->statut) {
                                    case 'en_attente':
                                        echo '<span class="pptm-status pptm-status-waiting">En attente</span>';
                                        break;
                                    case 'validee':
                                        echo '<span class="pptm-status pptm-status-validated">Validée</span>';
                                        break;
                                    case 'refusee':
                                        echo '<span class="pptm-status pptm-status-refused">Refusée</span>';
                                        break;
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($inscription->statut === 'en_attente'): ?>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('pptm_missions_action'); ?>
                                        <input type="hidden" name="action" value="validate_inscription">
                                        <input type="hidden" name="inscription_id" value="<?php echo $inscription->id; ?>">
                                        <button type="submit" class="button button-small" 
                                                <?php echo ($nb_inscrits_valides >= $mission->nb_benevoles_max) ? 'disabled' : ''; ?>>
                                            <span class="dashicons dashicons-yes"></span>
                                            Valider
                                        </button>
                                    </form>

                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('pptm_missions_action'); ?>
                                        <input type="hidden" name="action" value="refuse_inscription">
                                        <input type="hidden" name="inscription_id" value="<?php echo $inscription->id; ?>">
                                        <button type="submit" class="button button-small">
                                            <span class="dashicons dashicons-no"></span>
                                            Refuser
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($inscription->statut === 'validee'): ?>
                                    <a href="<?php echo admin_url('admin.php?page=pptm-bilans&mission_id=' . $mission->id . '&email=' . urlencode($inscription->email)); ?>" 
                                       class="button button-small">
                                        <span class="dashicons dashicons-welcome-write-blog"></span>
                                        Voir bilan
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p style="margin-top: 20px;">
        <a href="<?php echo admin_url('admin.php?page=pptm-missions'); ?>" class="button">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            Retour à la liste des missions
        </a>
    </p>
</div>

<style>
.pptm-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.pptm-info-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
}

.pptm-info-card .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    color: <?php echo get_option('pptm_association_color', '#2271b1'); ?>;
}

.info-title {
    margin: 10px 0 5px;
    color: #50575e;
    font-size: 13px;
}

.info-value {
    font-size: 20px;
    font-weight: bold;
    color: #1d2327;
}

.pptm-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.pptm-status-waiting {
    background: #f0f0f1;
    color: #50575e;
}

.pptm-status-validated {
    background: #00a32a;
    color: white;
}

.pptm-status-refused {
    background: #d63638;
    color: white;
}

.button .dashicons {
    vertical-align: middle;
    font-size: 16px;
    width: 16px;
    height: 16px;
    margin: -2px 2px 0 0;
}
</style>