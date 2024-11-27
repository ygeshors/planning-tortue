// secteurs.js
(function($) {
    'use strict';
    
    // Vérification des dépendances
    if (typeof L === 'undefined') {
        console.error('Leaflet n\'est pas chargé !');
        return;
    }

    if (typeof L.Control.Draw === 'undefined') {
        console.error('Leaflet Draw n\'est pas chargé !');
        return;
    }

    // Debug
    console.log('Secteurs.js loaded');
    console.log('pptmParams:', pptmParams);

    // Configuration Leaflet Draw
    L.drawLocal.draw.toolbar.buttons.polygon = 'Dessiner la zone';
    L.drawLocal.draw.toolbar.actions.finish = 'Terminer';
    L.drawLocal.draw.toolbar.actions.cancel = 'Annuler';
    L.drawLocal.draw.toolbar.undo.text = 'Supprimer le dernier point';
    L.drawLocal.edit.toolbar.actions.save.text = 'Sauvegarder';
    L.drawLocal.edit.toolbar.actions.cancel.text = 'Annuler';
    L.drawLocal.edit.toolbar.actions.clearAll.text = 'Tout effacer';

    // Configuration de base pour Leaflet Draw
    const drawOptions = {
        draw: {
            polygon: {
                allowIntersection: false,
                showArea: true
            },
            polyline: false,
            rectangle: false,
            circle: false,
            circlemarker: false,
            marker: false
        },
        edit: {
            featureGroup: new L.FeatureGroup(),
            edit: true,
            remove: true
        }
    };

    // Initialisation de la carte principale
    const mainMap = L.map('pptm-map', {
        center: [pptmParams.default_lat, pptmParams.default_lng],
        zoom: 10
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(mainMap);

    // Variables globales pour les cartes modales
    let addMap = null;
    let editMap = null;
    let addDrawLayer = null;
    let editDrawLayer = null;

    // Fonction d'initialisation de la carte d'ajout
    function initAddMap() {
        console.log('Initialisation de la carte d\'ajout');
        if (addMap) addMap.remove();
        
        addMap = L.map('pptm-map-add').setView([pptmParams.default_lat, pptmParams.default_lng], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(addMap);
        
        addDrawLayer = new L.FeatureGroup().addTo(addMap);
        const addDrawOptions = { ...drawOptions, edit: { featureGroup: addDrawLayer } };
        
        const drawControl = new L.Control.Draw(addDrawOptions);
        addMap.addControl(drawControl);

        addMap.on('draw:created', function(e) {
            console.log('Zone dessinée:', e);
            addDrawLayer.clearLayers();
            const coords = e.layer.getLatLngs()[0].map(p => [p.lat, p.lng]);
            $('#add-zone').val(JSON.stringify(coords));
            addDrawLayer.addLayer(e.layer);
        });

        setTimeout(() => {
            addMap.invalidateSize();
            console.log('Carte d\'ajout redimensionnée');
        }, 100);
    }

    // Fonction d'initialisation de la carte d'édition
    function initEditMap(secteur) {
        console.log('Initialisation de la carte d\'édition:', secteur);
        if (editMap) editMap.remove();
        
        editMap = L.map('pptm-map-edit').setView([
            secteur.lat || pptmParams.default_lat, 
            secteur.lng || pptmParams.default_lng
        ], 10);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(editMap);
        
        editDrawLayer = new L.FeatureGroup().addTo(editMap);
        const editDrawOptions = { ...drawOptions, edit: { featureGroup: editDrawLayer } };
        
        const drawControl = new L.Control.Draw(editDrawOptions);
        editMap.addControl(drawControl);

        if (secteur.zone) {
            try {
                const zone = JSON.parse(secteur.zone);
                console.log('Zone chargée:', zone);
                const polygon = L.polygon(zone).addTo(editDrawLayer);
                editMap.fitBounds(polygon.getBounds());
                $('#edit-zone').val(secteur.zone);
            } catch(e) {
                console.error('Erreur chargement zone:', e);
            }
        }

        editMap.on('draw:created', function(e) {
            console.log('Zone modifiée:', e);
            editDrawLayer.clearLayers();
            const coords = e.layer.getLatLngs()[0].map(p => [p.lat, p.lng]);
            $('#edit-zone').val(JSON.stringify(coords));
            editDrawLayer.addLayer(e.layer);
        });

        setTimeout(() => {
            editMap.invalidateSize();
            console.log('Carte d\'édition redimensionnée');
        }, 100);
    }

    // Gestionnaires d'événements
    $('#add-secteur-btn').on('click', function() {
        console.log('Ouverture modal ajout');
        $('#pptm-add-secteur').show();
        setTimeout(initAddMap, 100);
    });

    $('.edit-secteur-btn').on('click', function() {
        const secteur = JSON.parse($(this).data('secteur'));
        console.log('Ouverture modal édition:', secteur);
        $('#edit-secteur-id').val(secteur.id);
        $('#edit-old-nom').val(secteur.nom);
        $('#edit-nom').val(secteur.nom);
        $('#edit-description').val(secteur.description);
        $('#edit-zone').val(secteur.zone);
        
        $('#pptm-edit-secteur').show();
        setTimeout(() => initEditMap(secteur), 100);
    });

    // Gestion des formulaires AJAX
    $('.secteur-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Soumission formulaire');
        
        const form = $(this);
        const formData = new FormData(form[0]);
        formData.append('_wpnonce', pptmParams.nonce);

        $.ajax({
            url: pptmParams.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Réponse serveur:', response);
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || 'Une erreur est survenue');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur AJAX:', error);
                alert('Erreur de communication avec le serveur');
            }
        });
    });

    // Gestion des modales
    $('.pptm-modal-close, .cancel-modal').on('click', function() {
        $(this).closest('.pptm-modal').hide();
    });

    $(window).on('click', function(e) {
        if ($(e.target).hasClass('pptm-modal')) {
            $('.pptm-modal').hide();
        }
    });

    // Initialisation des secteurs existants sur la carte principale
    if (typeof pptmSecteurs !== 'undefined' && pptmSecteurs.length > 0) {
        console.log('Chargement des secteurs:', pptmSecteurs);
        pptmSecteurs.forEach(function(secteur) {
            if (secteur.zone) {
                try {
                    const zone = JSON.parse(secteur.zone);
                    L.polygon(zone, {
                        color: secteur.has_missions ? pptmParams.main_color : '#dc3232',
                        fillOpacity: 0.2
                    }).addTo(mainMap).bindPopup(`
                        <strong>${secteur.nom}</strong><br>
                        Missions: ${secteur.nb_missions}<br>
                        Traces: ${secteur.nb_traces}
                    `);
                } catch(e) {
                    console.error('Erreur chargement secteur:', secteur.nom, e);
                }
            }
        });
    }

    // Log final
    console.log('Initialisation secteurs.js terminée');
})(jQuery);