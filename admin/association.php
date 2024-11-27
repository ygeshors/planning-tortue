<?php
if (!defined('ABSPATH')) {
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pptm_association_settings'])) {
    check_admin_referer('pptm_association_settings');
    
    // Liste des champs de texte
    $text_fields = array(
        'pptm_association_name',
        'pptm_association_rna',
        'pptm_association_siret',
        'pptm_association_rtmmf',
        'pptm_association_address',
        'pptm_association_phone',
        'pptm_association_email',
        'pptm_association_website',
        'pptm_association_color',
        'pptm_association_description'
    );
    
    // Mise à jour des champs de texte
    foreach ($text_fields as $field) {
        if (isset($_POST[$field])) {
            update_option($field, sanitize_text_field($_POST[$field]));
        }
    }
    
    // Traitement du logo
    if (isset($_FILES['pptm_association_logo']) && $_FILES['pptm_association_logo']['error'] === 0) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $attachment_id = media_handle_upload('pptm_association_logo', 0);
        if (!is_wp_error($attachment_id)) {
            update_option('pptm_association_logo', wp_get_attachment_url($attachment_id));
        }
    }

    echo '<div class="notice notice-success"><p>Configuration enregistrée avec succès.</p></div>';
}

// Récupération des valeurs actuelles
$name = get_option('pptm_association_name', '');
$rna = get_option('pptm_association_rna', '');
$siret = get_option('pptm_association_siret', '');
$rtmmf = get_option('pptm_association_rtmmf', '0');
$address = get_option('pptm_association_address', '');
$phone = get_option('pptm_association_phone', '');
$email = get_option('pptm_association_email', '');
$website = get_option('pptm_association_website', '');
$logo = get_option('pptm_association_logo', '');
$color = get_option('pptm_association_color', '#2271b1');
$description = get_option('pptm_association_description', '');
?>

<div class="wrap">
    <h1>Configuration de l'Association</h1>

    <!-- Récapitulatif -->
    <div class="pptm-association-summary">
        <div class="postbox">
            <h2 class="hndle"><span>Récapitulatif</span></h2>
            <div class="inside">
                <div class="pptm-summary-content">
                    <div class="pptm-summary-logo">
                        <?php if ($logo): ?>
                            <img src="<?php echo esc_url($logo); ?>" alt="Logo">
                        <?php else: ?>
                            <div class="pptm-no-logo">
                                <span class="dashicons dashicons-format-image"></span>
                                <p>Aucun logo</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="pptm-summary-info">
                        <h3><?php echo esc_html($name); ?></h3>
                        <?php if ($description): ?>
                            <p class="description"><?php echo esc_html($description); ?></p>
                        <?php endif; ?>
                        <table class="widefat" style="background: none; border: none;">
                            <tr>
                                <td><strong>RNA :</strong></td>
                                <td><?php echo esc_html($rna); ?></td>
                            </tr>
                            <tr>
                                <td><strong>SIRET :</strong></td>
                                <td><?php echo esc_html($siret); ?></td>
                            </tr>
                            <tr>
                                <td><strong>RTMMF :</strong></td>
                                <td><?php echo $rtmmf ? 'Oui' : 'Non'; ?></td>
                            </tr>
                            <?php if ($address): ?>
                            <tr>
                                <td><strong>Adresse :</strong></td>
                                <td><?php echo nl2br(esc_html($address)); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($email): ?>
                            <tr>
                                <td><strong>Email :</strong></td>
                                <td><?php echo esc_html($email); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($phone): ?>
                            <tr>
                                <td><strong>Téléphone :</strong></td>
                                <td><?php echo esc_html($phone); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($website): ?>
                            <tr>
                                <td><strong>Site web :</strong></td>
                                <td><a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire de configuration -->
    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('pptm_association_settings'); ?>
        
        <div class="postbox">
            <h2 class="hndle"><span>Informations générales</span></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="pptm_association_name">Nom de l'association</label></th>
                        <td>
                            <input type="text" name="pptm_association_name" id="pptm_association_name" 
                                   value="<?php echo esc_attr($name); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="pptm_association_description">Description</label></th>
                        <td>
                            <textarea name="pptm_association_description" id="pptm_association_description" 
                                      rows="3" class="large-text"><?php echo esc_textarea($description); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="pptm_association_logo">Logo</label></th>
                        <td>
                            <?php if ($logo): ?>
                                <img src="<?php echo esc_url($logo); ?>" 
                                     alt="Logo actuel" style="max-width: 200px; margin-bottom: 10px;"><br>
                            <?php endif; ?>
                            <input type="file" name="pptm_association_logo" id="pptm_association_logo" accept="image/*">
                            <p class="description">Format recommandé : PNG ou JPEG, maximum 1MB</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="postbox">
            <h2 class="hndle"><span>Identification</span></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="pptm_association_rna">Numéro RNA</label></th>
                        <td>
                            <input type="text" name="pptm_association_rna" id="pptm_association_rna" 
                                   value="<?php echo esc_attr($rna); ?>" class="regular-text" 
                                   pattern="W[0-9]{9}" placeholder="W123456789">
                            <p class="description">Format : W suivi de 9 chiffres</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="pptm_association_siret">Numéro SIRET</label></th>
                        <td>
                            <input type="text" name="pptm_association_siret" id="pptm_association_siret" 
                                   value="<?php echo esc_attr($siret); ?>" class="regular-text"
                                   pattern="[0-9]{14}" placeholder="12345678901234">
                            <p class="description">Format : 14 chiffres</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="pptm_association_rtmmf">Membre RTMMF</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="pptm_association_rtmmf" id="pptm_association_rtmmf" 
                                       value="1" <?php checked($rtmmf, '1'); ?>>
                                L'association est membre du Réseau Tortues Marines de Méditerranée Française
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="postbox">
            <h2 class="hndle"><span>Coordonnées</span></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="pptm_association_address">Adresse</label></th>
                        <td>
                            <textarea name="pptm_association_address" id="pptm_association_address" 
                                      rows="3" class="large-text"><?php echo esc_textarea($address); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="pptm_association_phone">Téléphone</label></th>
                        <td>
                            <input type="tel" name="pptm_association_phone" id="pptm_association_phone" 
                                   value="<?php echo esc_attr($phone); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="pptm_association_email">Email</label></th>
                        <td>
                            <input type="email" name="pptm_association_email" id="pptm_association_email" 
                                   value="<?php echo esc_attr($email); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="pptm_association_website">Site web</label></th>
                        <td>
                            <input type="url" name="pptm_association_website" id="pptm_association_website" 
                                   value="<?php echo esc_attr($website); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="postbox">
            <h2 class="hndle"><span>Personnalisation</span></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="pptm_association_color">Couleur principale</label></th>
                        <td>
                            <input type="color" name="pptm_association_color" id="pptm_association_color" 
                                   value="<?php echo esc_attr($color); ?>">
                            <p class="description">Cette couleur sera utilisée pour les boutons et éléments d'interface</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <input type="hidden" name="pptm_association_settings" value="1">
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Enregistrer les modifications">
        </p>
    </form>
</div>

<style>
.pptm-association-summary {
    margin: 20px 0;
}

.pptm-summary-content {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.pptm-summary-logo {
    flex: 0 0 200px;
}

.pptm-summary-logo img {
    max-width: 100%;
    height: auto;
}

.pptm-no-logo {
    background: #f0f0f1;
    width: 200px;
    height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.pptm-no-logo .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #646970;
}

.pptm-summary-info {
    flex: 1;
}

.pptm-summary-info h3 {
    margin-top: 0;
}

.pptm-summary-info table td {
    padding: 8px 0;
    border: none;
}

.pptm-summary-info table td:first-child {
    width: 100px;
}
</style>