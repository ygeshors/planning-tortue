<?php
if (!defined('ABSPATH')) {
    exit;
}

// Liste des modèles d'emails disponibles
$email_templates = array(
    'inscription_confirmation' => array(
        'title' => 'Confirmation d\'inscription',
        'description' => 'Envoyé au bénévole après son inscription',
        'variables' => array(
            '{prenom}' => 'Prénom du bénévole',
            '{nom}' => 'Nom du bénévole',
            '{date_mission}' => 'Date de la mission',
            '{secteur}' => 'Nom du secteur',
            '{association}' => 'Nom de l\'association'
        )
    ),
    'inscription_validation' => array(
        'title' => 'Validation d\'inscription',
        'description' => 'Envoyé au bénévole quand son inscription est validée',
        'variables' => array(
            '{prenom}' => 'Prénom du bénévole',
            '{nom}' => 'Nom du bénévole',
            '{date_mission}' => 'Date de la mission',
            '{secteur}' => 'Nom du secteur'
        )
    ),
    'inscription_refus' => array(
        'title' => 'Refus d\'inscription',
        'description' => 'Envoyé au bénévole quand son inscription est refusée',
        'variables' => array(
            '{prenom}' => 'Prénom du bénévole',
            '{nom}' => 'Nom du bénévole',
            '{date_mission}' => 'Date de la mission',
            '{secteur}' => 'Nom du secteur'
        )
    ),
    'rappel_mission' => array(
        'title' => 'Rappel de mission',
        'description' => 'Envoyé 48h avant la mission',
        'variables' => array(
            '{prenom}' => 'Prénom du bénévole',
            '{nom}' => 'Nom du bénévole',
            '{date_mission}' => 'Date de la mission',
            '{secteur}' => 'Nom du secteur'
        )
    ),
    'nouvelle_inscription_admin' => array(
        'title' => 'Nouvelle inscription (admin)',
        'description' => 'Envoyé aux administrateurs lors d\'une nouvelle inscription',
        'variables' => array(
            '{prenom}' => 'Prénom du bénévole',
            '{nom}' => 'Nom du bénévole',
            '{email}' => 'Email du bénévole',
            '{telephone}' => 'Téléphone du bénévole',
            '{date_mission}' => 'Date de la mission',
            '{secteur}' => 'Nom du secteur',
            '{lien_admin}' => 'Lien vers l\'administration'
        )
    )
);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pptm_email_settings'])) {
    check_admin_referer('pptm_email_settings');
    
    // Mise à jour des templates d'emails
    foreach ($email_templates as $key => $template) {
        if (isset($_POST['email_' . $key . '_subject'])) {
            update_option('pptm_email_' . $key . '_subject', wp_kses_post($_POST['email_' . $key . '_subject']));
        }
        if (isset($_POST['email_' . $key . '_content'])) {
            update_option('pptm_email_' . $key . '_content', wp_kses_post($_POST['email_' . $key . '_content']));
        }
    }

    // Traitement de la signature
    if (isset($_FILES['email_signature']) && $_FILES['email_signature']['error'] === 0) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $attachment_id = media_handle_upload('email_signature', 0);
        if (!is_wp_error($attachment_id)) {
            update_option('pptm_email_signature', wp_get_attachment_url($attachment_id));
        }
    }

    // Autres paramètres
    if (isset($_POST['email_signature_enabled'])) {
        update_option('pptm_email_signature_enabled', '1');
    } else {
        update_option('pptm_email_signature_enabled', '0');
    }

    echo '<div class="notice notice-success"><p>Paramètres des emails enregistrés avec succès.</p></div>';
}

// Récupération de la signature
$signature_enabled = get_option('pptm_email_signature_enabled', '0');
$signature_url = get_option('pptm_email_signature', '');
?>

<div class="wrap">
    <h1>Configuration des Emails</h1>

    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('pptm_email_settings'); ?>

        <!-- Signature -->
        <div class="postbox">
            <h2 class="hndle"><span>Signature des emails</span></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row">Activer la signature</th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_signature_enabled" value="1" 
                                       <?php checked($signature_enabled, '1'); ?>>
                                Ajouter la signature en bas des emails
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Image de signature</th>
                        <td>
                            <?php if ($signature_url): ?>
                                <img src="<?php echo esc_url($signature_url); ?>" 
                                     alt="Signature actuelle" style="max-width: 400px; margin-bottom: 10px;"><br>
                            <?php endif; ?>
                            <input type="file" name="email_signature" accept="image/*">
                            <p class="description">Format recommandé : PNG ou JPEG, largeur maximale 600px</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Templates d'emails -->
        <?php foreach ($email_templates as $key => $template): ?>
            <div class="postbox">
                <h2 class="hndle"><span><?php echo esc_html($template['title']); ?></span></h2>
                <div class="inside">
                    <p class="description"><?php echo esc_html($template['description']); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="email_<?php echo $key; ?>_subject">Objet</label></th>
                            <td>
                                <input type="text" name="email_<?php echo $key; ?>_subject" 
                                       id="email_<?php echo $key; ?>_subject" class="large-text"
                                       value="<?php echo esc_attr(get_option('pptm_email_' . $key . '_subject', '')); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email_<?php echo $key; ?>_content">Contenu</label></th>
                            <td>
                                <?php
                                wp_editor(
                                    get_option('pptm_email_' . $key . '_content', ''),
                                    'email_' . $key . '_content',
                                    array(
                                        'textarea_name' => 'email_' . $key . '_content',
                                        'textarea_rows' => 10,
                                        'media_buttons' => false,
                                        'teeny' => true,
                                        'quicktags' => false
                                    )
                                );
                                ?>
                                <p class="description">
                                    Variables disponibles :<br>
                                    <?php 
                                    foreach ($template['variables'] as $var => $desc) {
                                        echo '<code>' . esc_html($var) . '</code> - ' . esc_html($desc) . '<br>';
                                    }
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <div class="pptm-email-preview">
                        <button type="button" class="button"
                                onclick="previewEmail('<?php echo $key; ?>')">
                            Prévisualiser
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <input type="hidden" name="pptm_email_settings" value="1">
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" 
                   value="Enregistrer tous les emails">
        </p>
    </form>

    <!-- Modal de prévisualisation -->
    <div id="pptm-email-preview-modal" class="pptm-modal">
        <div class="pptm-modal-content">
            <span class="pptm-close">&times;</span>
            <h2>Prévisualisation de l'email</h2>
            <div class="pptm-email-preview-content">
                <div id="pptm-email-preview-subject"></div>
                <div id="pptm-email-preview-body"></div>
            </div>
        </div>
    </div>
</div>

<style>
.pptm-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.pptm-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 800px;
    border-radius: 4px;
}

.pptm-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.pptm-close:hover {
    color: black;
}

.pptm-email-preview {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #dcdcde;
}

.pptm-email-preview-content {
    margin-top: 20px;
    background: white;
    padding: 20px;
    border: 1px solid #dcdcde;
    border-radius: 4px;
}

#pptm-email-preview-subject {
    font-weight: bold;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #dcdcde;
}

#pptm-email-preview-body {
    line-height: 1.5;
}
</style>

<script>
function previewEmail(templateKey) {
    var subject = document.getElementById('email_' + templateKey + '_subject').value;
    var content = tinymce.get('email_' + templateKey + '_content').getContent();
    
    // Remplacer les variables par des exemples
    var testData = {
        '{prenom}': 'Jean',
        '{nom}': 'Dupont',
        '{email}': 'jean.dupont@example.com',
        '{telephone}': '0612345678',
        '{date_mission}': '<?php echo date_i18n('d/m/Y'); ?>',
        '{secteur}': 'Les Orpellières',
        '{association}': '<?php echo esc_js(get_option('pptm_association_name')); ?>',
        '{lien_admin}': '[Lien administration]'
    };
    
    for (var key in testData) {
        subject = subject.replace(new RegExp(key, 'g'), testData[key]);
        content = content.replace(new RegExp(key, 'g'), testData[key]);
    }
    
    // Ajouter la signature si activée
    <?php if ($signature_enabled && $signature_url): ?>
    content += '<br><br><img src="<?php echo esc_url($signature_url); ?>" style="max-width: 100%;">';
    <?php endif; ?>
    
    document.getElementById('pptm-email-preview-subject').textContent = 'Objet : ' + subject;
    document.getElementById('pptm-email-preview-body').innerHTML = content;
    document.getElementById('pptm-email-preview-modal').style.display = 'block';
}

// Fermeture de la modal
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('pptm-email-preview-modal');
    var span = document.getElementsByClassName('pptm-close')[0];
    
    span.onclick = function() {
        modal.style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
});
</script>