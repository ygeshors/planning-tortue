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

// Scripts et styles
function pptm_enqueue_scripts() {
    wp_enqueue_style('marianne-font', PPTM_URL . 'fonts/marianne.css');
    wp_enqueue_style('dashicons');
    wp_enqueue_style('pptm-styles', PPTM_URL . 'css/styles.css', array(), PPTM_VERSION);

    if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'pptm-secteurs') {
        wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
        wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array('jquery'));
        wp_enqueue_style('leaflet-draw', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css');
        wp_enqueue_script('leaflet-draw', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js', array('leaflet'));
        wp_enqueue_script('pptm-secteurs', PPTM_URL . 'js/secteurs.js', array('jquery', 'leaflet', 'leaflet-draw'), PPTM_VERSION, true);
        
        // Variables pour JavaScript
        wp_localize_script('pptm-secteurs', 'pptmParams', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pptm_secteurs_action'),
            'default_lat' => get_option('pptm_default_lat', '43.6007'),
            'default_lng' => get_option('pptm_default_lng', '3.8796'),
            'main_color' => get_option('pptm_association_color', '#2271b1')
        ));
    }

    // Scripts spécifiques aux pages admin
    if (is_admin()) {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'pptm') !== false) {
            if ($screen->id === 'prospections_page_pptm-statistiques') {
                wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js');
            }
            wp_enqueue_style('pptm-admin', PPTM_URL . 'css/admin.css');
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
        'missions' => array('Gestion des Missions', 'Missions'),
        'nouvelle-mission' => array('Nouvelle Mission', 'Nouvelle Mission'),
        'bilans' => array('Bilans des missions', 'Bilans'),
        'statistiques' => array('Statistiques', 'Statistiques'),
        'secteurs' => array('Gestion des Secteurs', 'Secteurs'),
        'association' => array('Configuration Association', 'Association'),
        'emails' => array('Configuration Emails', 'Emails')
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

// Pages d'administration
function pptm_admin_missions_page() {
    if (!current_user_can('manage_options')) wp_die(__('Accès refusé'));
    require_once PPTM_PATH . 'admin/missions.php';
}

function pptm_admin_nouvelle_mission_page() {
    if (!current_user_can('manage_options')) wp_die(__('Accès refusé'));
    require_once PPTM_PATH . 'admin/nouvelle-mission.php';
}

function pptm_admin_bilans_page() {
    if (!current_user_can('manage_options')) wp_die(__('Accès refusé'));
    require_once PPTM_PATH . 'admin/bilans.php';
}

function pptm_admin_statistiques_page() {
    if (!current_user_can('manage_options')) wp_die(__('Accès refusé'));
    require_once PPTM_PATH . 'admin/statistiques.php';
}

function pptm_admin_secteurs_page() {
    if (!current_user_can('manage_options')) wp_die(__('Accès refusé'));
    require_once PPTM_PATH . 'admin/secteurs.php';
}

function pptm_admin_association_page() {
    if (!current_user_can('manage_options')) wp_die(__('Accès refusé'));
    require_once PPTM_PATH . 'admin/association.php';
}

function pptm_admin_emails_page() {
    if (!current_user_can('manage_options')) wp_die(__('Accès refusé'));
    require_once PPTM_PATH . 'admin/emails.php';
}

// Shortcodes
function pptm_shortcode_calendrier($atts = []) {
    ob_start();
    include(PPTM_PATH . 'templates/calendar.php');
    return ob_get_clean();
}
add_shortcode('pptm_calendrier', 'pptm_shortcode_calendrier');

function pptm_shortcode_planning($atts = []) {
    ob_start();
    require_once(PPTM_PATH . 'templates/planning.php');
    return ob_get_clean();
}
add_shortcode('pptm_planning', 'pptm_shortcode_planning');

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
// Fonctions CRUD
function pptm_get_mission($mission_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("
        SELECT m.*, 
            COUNT(DISTINCT CASE WHEN i.statut = 'validee' THEN i.id END) as nb_inscrits_valides
        FROM {$wpdb->prefix}pptm_missions m
        LEFT JOIN {$wpdb->prefix}pptm_inscriptions i ON m.id = i.mission_id
        WHERE m.id = %d
        GROUP BY m.id",
        $mission_id
    ));
}

function pptm_get_inscription($inscription_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("
        SELECT i.*, m.date_mission, m.secteur 
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

// Fonctions Email
function pptm_send_email($to, $subject, $content, $variables = array()) {
    $variables['{association}'] = get_option('pptm_association_name', 'ASHL');
    $subject = strtr($subject, $variables);
    $content = strtr($content, $variables);
    
    if (get_option('pptm_email_signature_enabled', '0') === '1') {
        $signature_url = get_option('pptm_email_signature', '');
        if ($signature_url) {
            $content .= '<br><br><img src="' . esc_url($signature_url) . '" alt="Signature">';
        }
    }
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_option('pptm_association_name') . ' <' . get_option('pptm_association_email') . '>'
    );
    
    $content = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">' . $content . '</div>';
    
    return wp_mail($to, $subject, $content, $headers);
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
// Hooks AJAX pour les secteurs
add_action('wp_ajax_pptm_add_secteur', 'pptm_ajax_add_secteur');
add_action('wp_ajax_pptm_edit_secteur', 'pptm_ajax_edit_secteur');
add_action('wp_ajax_pptm_delete_secteur', 'pptm_ajax_delete_secteur');

// Activation du plugin
register_activation_hook(__FILE__, 'pptm_activate');
function pptm_activate() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $charset_collate = $wpdb->get_charset_collate();
    
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

    // Création dossier uploads
    $upload_dir = wp_upload_dir();
    $pptm_upload_dir = $upload_dir['basedir'] . '/pptm';
    if (!file_exists($pptm_upload_dir)) {
        wp_mkdir_p($pptm_upload_dir);
    }

    flush_rewrite_rules();
}

// Désactivation
register_deactivation_hook(__FILE__, 'pptm_deactivate');
function pptm_deactivate() {
    wp_clear_scheduled_hook('pptm_daily_reminder_check');
    flush_rewrite_rules();
}

// CRON pour les rappels
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

// Fonction utilitaire couleur secteur
function pptm_get_sector_color($secteur) {
    return get_option('pptm_association_color', '#2271b1');
}
