<?php
/*
Plugin Name: Planning Prospections Tortues Marines
Description: Gestion des plannings de prospection pour les tortues marines
Version: 1.0.2
Author: ASHL - Association Sauvegarde Hérault Littoral
Text Domain: pptm
*/

if (!defined('ABSPATH')) {
    exit;
}

define('PPTM_VERSION', '1.0.2');
define('PPTM_PATH', plugin_dir_path(__FILE__));
define('PPTM_URL', plugin_dir_url(__FILE__));

// Ajout des hooks AJAX
add_action('wp_ajax_pptm_add_secteur', 'pptm_ajax_add_secteur');
add_action('wp_ajax_pptm_edit_secteur', 'pptm_ajax_edit_secteur');
add_action('wp_ajax_pptm_delete_secteur', 'pptm_ajax_delete_secteur');

function pptm_ajax_add_secteur() {
    check_ajax_referer('pptm_secteurs_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    do_action('pptm_before_add_secteur');
    // La logique d'ajout est gérée dans secteurs.php
}

function pptm_ajax_edit_secteur() {
    check_ajax_referer('pptm_secteurs_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    do_action('pptm_before_edit_secteur');
    // La logique d'édition est gérée dans secteurs.php
}

function pptm_ajax_delete_secteur() {
    check_ajax_referer('pptm_secteurs_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    do_action('pptm_before_delete_secteur');
    // La logique de suppression est gérée dans secteurs.php
}

// Scripts et styles
function pptm_enqueue_scripts() {
    // Styles principaux
    wp_enqueue_style('marianne-font', PPTM_URL . 'fonts/marianne.css');
    wp_enqueue_style('dashicons');
    
    // jQuery UI
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-dialog');
    
    // Leaflet pour toutes les pages
    wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
    wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array('jquery'), '1.9.4', true);

    // Scripts spécifiques aux pages admin
    if (is_admin()) {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'pptm') !== false) {
            wp_enqueue_style('pptm-admin', PPTM_URL . 'css/admin.css');
            
            // Variables globales pour JavaScript
            wp_localize_script('jquery', 'pptmParams', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'default_lat' => get_option('pptm_default_lat', '43.6007'),
                'default_lng' => get_option('pptm_default_lng', '3.8796'),
                'main_color' => get_option('pptm_association_color', '#2271b1'),
                'nonce' => wp_create_nonce('pptm_secteurs_action')
            ));

            // Leaflet Draw pour la page secteurs
            if ($screen->id === 'prospections_page_pptm-secteurs') {
                wp_enqueue_style('leaflet-draw', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css', array(), '1.0.4');
                wp_enqueue_script('leaflet-draw', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js', array('leaflet'), '1.0.4', true);
                wp_enqueue_script('pptm-secteurs', PPTM_URL . 'js/secteurs.js', array('jquery', 'leaflet', 'leaflet-draw'), PPTM_VERSION, true);
            }
            
            // Chart.js pour les statistiques
            if ($screen->id === 'prospections_page_pptm-statistiques') {
                wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
            }
            
            wp_enqueue_media();
        }
    }
}

add_action('admin_enqueue_scripts', 'pptm_enqueue_scripts');
add_action('wp_enqueue_scripts', 'pptm_enqueue_scripts');

// Menu d'administration
add_action('admin_menu', 'pptm_admin_menu');
function pptm_admin_menu() {
    add_menu_page(
        'Gestion des Missions', 
        'Prospections',
        'manage_options',
        'pptm-missions',
        'pptm_admin_missions_page',
        'dashicons-calendar-alt',
        30
    );

    $submenus = array(
        'missions' => array(
            'Gestion des Missions',
            'Missions'
        ),
        'nouvelle-mission' => array(
            'Nouvelle Mission',
            'Nouvelle Mission'
        ),
        'bilans' => array(
            'Bilans des missions',
            'Bilans'
        ),
        'statistiques' => array(
            'Statistiques',
            'Statistiques'
        ),
        'secteurs' => array(
            'Gestion des Secteurs',
            'Secteurs'
        ),
        'association' => array(
            'Configuration Association',
            'Association'
        ),
        'emails' => array(
            'Configuration Emails',
            'Emails'
        )
    );

    foreach ($submenus as $slug => $menu) {
        add_submenu_page(
            'pptm-missions',
            $menu[0],
            $menu[1],
            'manage_options',
            'pptm-' . $slug,
            'pptm_admin_' . str_replace('-', '_', $slug) . '_page'
        );
    }
}
// Fonctions des pages d'administration
function pptm_admin_missions_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.'));
    }
    require PPTM_PATH . 'admin/missions.php';
}

function pptm_admin_nouvelle_mission_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.'));
    }
    require PPTM_PATH . 'admin/nouvelle-mission.php';
}

function pptm_admin_bilans_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.'));
    }
    require PPTM_PATH . 'admin/bilans.php';
}

function pptm_admin_statistiques_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.'));
    }
    require PPTM_PATH . 'admin/statistiques.php';
}

function pptm_admin_secteurs_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.'));
    }
    require PPTM_PATH . 'admin/secteurs.php';
}

function pptm_admin_association_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.'));
    }
    require PPTM_PATH . 'admin/association.php';
}

function pptm_admin_emails_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.'));
    }
    require PPTM_PATH . 'admin/emails.php';
}

// Fonctions CRUD
function pptm_get_mission($mission_id) {
    global $wpdb;
    
    $mission = $wpdb->get_row($wpdb->prepare(
        "SELECT m.*, 
                COUNT(DISTINCT CASE WHEN i.statut = 'validee' THEN i.id END) as nb_inscrits_valides
         FROM {$wpdb->prefix}pptm_missions m
         LEFT JOIN {$wpdb->prefix}pptm_inscriptions i ON m.id = i.mission_id
         WHERE m.id = %d
         GROUP BY m.id",
        $mission_id
    ));

    if ($mission) {
        $mission->nb_benevoles_max = intval($mission->nb_benevoles_max);
    }

    return $mission;
}

function pptm_get_inscription($inscription_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT i.*, m.date_mission, m.secteur 
         FROM {$wpdb->prefix}pptm_inscriptions i 
         JOIN {$wpdb->prefix}pptm_missions m ON i.mission_id = m.id 
         WHERE i.id = %d",
        $inscription_id
    ));
}

function pptm_delete_mission($mission_id) {
    global $wpdb;
    
    $wpdb->delete($wpdb->prefix . 'pptm_inscriptions', array('mission_id' => $mission_id));
    $wpdb->delete($wpdb->prefix . 'pptm_bilans', array('mission_id' => $mission_id));
    
    return $wpdb->delete($wpdb->prefix . 'pptm_missions', array('id' => $mission_id));
}

// Gestion des emails
function pptm_send_email($to, $subject, $content, $variables = array()) {
    $variables['{association}'] = get_option('pptm_association_name', 'ASHL');
    $subject = strtr($subject, $variables);
    $content = strtr($content, $variables);
    
    if (get_option('pptm_email_signature_enabled', '0') === '1') {
        $signature_url = get_option('pptm_email_signature', '');
        if ($signature_url) {
            $content .= '<br><br><img src="' . esc_url($signature_url) . '" alt="Signature" style="max-width: 100%;">';
        }
    }
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_option('pptm_association_name') . ' <' . get_option('pptm_association_email') . '>'
    );
    
    $email_style = '
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .email-container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 10px 20px; background-color: #2271b1; 
                 color: white; text-decoration: none; border-radius: 4px; }
        .info { background-color: #f0f7ff; padding: 15px; border-radius: 4px; margin: 10px 0; }
    </style>';
    
    $formatted_content = '<div class="email-container">' . $email_style . $content . '</div>';
    
    return wp_mail($to, $subject, $formatted_content, $headers);
}

function pptm_send_email_confirmation($inscription_id) {
    $inscription = pptm_get_inscription($inscription_id);
    if (!$inscription) return false;
    
    return pptm_send_email(
        $inscription->email,
        get_option('pptm_email_inscription_confirmation_subject'),
        get_option('pptm_email_inscription_confirmation_content'),
        array(
            '{prenom}' => $inscription->prenom,
            '{nom}' => $inscription->nom,
            '{date_mission}' => date_i18n('d/m/Y', strtotime($inscription->date_mission)),
            '{secteur}' => $inscription->secteur
        )
    );
}
function pptm_send_email_validation($inscription_id) {
    $inscription = pptm_get_inscription($inscription_id);
    if (!$inscription) return false;
    
    return pptm_send_email(
        $inscription->email,
        get_option('pptm_email_inscription_validation_subject'),
        get_option('pptm_email_inscription_validation_content'),
        array(
            '{prenom}' => $inscription->prenom,
            '{nom}' => $inscription->nom,
            '{date_mission}' => date_i18n('d/m/Y', strtotime($inscription->date_mission)),
            '{secteur}' => $inscription->secteur
        )
    );
}

function pptm_send_email_refus($inscription_id) {
    $inscription = pptm_get_inscription($inscription_id);
    if (!$inscription) return false;
    
    return pptm_send_email(
        $inscription->email,
        get_option('pptm_email_inscription_refus_subject'),
        get_option('pptm_email_inscription_refus_content'),
        array(
            '{prenom}' => $inscription->prenom,
            '{nom}' => $inscription->nom,
            '{date_mission}' => date_i18n('d/m/Y', strtotime($inscription->date_mission)),
            '{secteur}' => $inscription->secteur
        )
    );
}

function pptm_send_email_rappel($inscription_id) {
    $inscription = pptm_get_inscription($inscription_id);
    if (!$inscription || $inscription->statut !== 'validee') return false;
    
    return pptm_send_email(
        $inscription->email,
        get_option('pptm_email_rappel_mission_subject'),
        get_option('pptm_email_rappel_mission_content'),
        array(
            '{prenom}' => $inscription->prenom,
            '{nom}' => $inscription->nom,
            '{date_mission}' => date_i18n('d/m/Y', strtotime($inscription->date_mission)),
            '{secteur}' => $inscription->secteur
        )
    );
}

// Configuration CRON
add_action('wp', 'pptm_schedule_reminders');
function pptm_schedule_reminders() {
    if (!wp_next_scheduled('pptm_daily_reminder_check')) {
        wp_schedule_event(time(), 'daily', 'pptm_daily_reminder_check');
    }
}

add_action('pptm_daily_reminder_check', 'pptm_check_reminders');
function pptm_check_reminders() {
    global $wpdb;
    
    $inscriptions = $wpdb->get_results($wpdb->prepare("
        SELECT i.id 
        FROM {$wpdb->prefix}pptm_inscriptions i 
        JOIN {$wpdb->prefix}pptm_missions m ON i.mission_id = m.id 
        WHERE i.statut = 'validee' 
        AND DATE(m.date_mission) = DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    "));
    
    foreach ($inscriptions as $inscription) {
        pptm_send_email_rappel($inscription->id);
    }
}

// Shortcodes
function pptm_shortcode_calendrier($atts = []) {
    ob_start();
    include(PPTM_PATH . 'templates/calendar.php');
    return ob_get_clean();
}
add_shortcode('pptm_calendrier', 'pptm_shortcode_calendrier');

function pptm_shortcode_inscription($atts = []) {
    ob_start();
    include(PPTM_PATH . 'templates/inscription-form.php');
    return ob_get_clean();
}
add_shortcode('pptm_inscription', 'pptm_shortcode_inscription');

function pptm_shortcode_bilan($atts = []) {
    ob_start();
    include(PPTM_PATH . 'templates/bilan-form.php');
    return ob_get_clean();
}
add_shortcode('pptm_bilan', 'pptm_shortcode_bilan');

// Styles d'administration
add_action('admin_head', 'pptm_admin_styles');
function pptm_admin_styles() {
    $screen = get_current_screen();
    if (strpos($screen->id, 'pptm') === false) {
        return;
    }
    ?>
    <style>
    .pptm-admin-header {
        margin: 20px 0;
        padding: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .pptm-admin-card {
        background: white;
        padding: 20px;
        margin: 20px 0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .pptm-admin-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }

    .pptm-status {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
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

    .pptm-map {
        height: 400px !important;
        margin: 15px 0;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    @media (max-width: 768px) {
        .pptm-admin-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
    <?php
}

// Activation du plugin
register_activation_hook(__FILE__, 'pptm_activate');
function pptm_activate() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Création des tables
    $sql_missions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pptm_missions (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        date_mission date NOT NULL,
        secteur varchar(100) NOT NULL,
        nb_benevoles_max int(11) NOT NULL DEFAULT 2,
        statut varchar(20) NOT NULL DEFAULT 'active',
        date_creation datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_date_mission (date_mission),
        KEY idx_secteur (secteur)
    ) $charset_collate;";

    $sql_inscriptions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pptm_inscriptions (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        mission_id bigint(20) NOT NULL,
        nom varchar(100) NOT NULL,
        prenom varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        telephone varchar(20) NOT NULL,
        statut varchar(20) NOT NULL DEFAULT 'en_attente',
        date_inscription datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_mission_id (mission_id),
        FOREIGN KEY (mission_id) REFERENCES {$wpdb->prefix}pptm_missions(id) ON DELETE CASCADE
    ) $charset_collate;";

    $sql_bilans = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pptm_bilans (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        mission_id bigint(20) NOT NULL,
        email varchar(100) NOT NULL,
        traces tinyint(1) NOT NULL DEFAULT 0,
        commentaire text,
        photo_url varchar(255),
        latitude decimal(10,8),
        longitude decimal(11,8),
        date_creation datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_mission_id (mission_id),
        FOREIGN KEY (mission_id) REFERENCES {$wpdb->prefix}pptm_missions(id) ON DELETE CASCADE
    ) $charset_collate;";

    $sql_secteurs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pptm_secteurs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        nom varchar(100) NOT NULL,
        zone text NOT NULL,
        description text,
        lat decimal(10,8) NOT NULL,
        lng decimal(11,8) NOT NULL,
        rayon int NOT NULL DEFAULT 1000,
        PRIMARY KEY (id),
        UNIQUE KEY idx_nom (nom)
    ) $charset_collate;";

    dbDelta($sql_missions);
    dbDelta($sql_inscriptions);
    dbDelta($sql_bilans);
    dbDelta($sql_secteurs);

    // Options par défaut
    $default_options = array(
        'pptm_association_name' => 'ASHL',
        'pptm_association_email' => 'contact@ashl.fr',
        'pptm_association_color' => '#2271b1',
        'pptm_version' => PPTM_VERSION
    );

    foreach($default_options as $key => $value) {
        add_option($key, $value);
    }

    // Création dossier uploads
    $upload_dir = wp_upload_dir();
    $pptm_upload_dir = $upload_dir['basedir'] . '/pptm';
    if (!file_exists($pptm_upload_dir)) {
        wp_mkdir_p($pptm_upload_dir);
    }

    flush_rewrite_rules();
}

// Désactivation du plugin
register_deactivation_hook(__FILE__, 'pptm_deactivate');
function pptm_deactivate() {
    wp_clear_scheduled_hook('pptm_daily_reminder_check');
    flush_rewrite_rules();
}

function pptm_get_sector_color($secteur) {
    return get_option('pptm_association_color', '#2271b1');
}