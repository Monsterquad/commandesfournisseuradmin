console.log('MQ Supplier JS: Fichier chargé');

document.addEventListener('DOMContentLoaded', function() {
    console.log('MQ Supplier JS: DOM chargé, initialisation...');
    mq_commandes_fournisseurs.start();
});

var mq_commandes_fournisseurs = {
    modal: null,
    contenu: null,

    start: function() {
        console.log('MQ Supplier JS: start() appelé');
        
        // Vérifier si la modal existe déjà, sinon la créer
        this.ensureModalExists();
        
        // Écouter l'événement d'ouverture de la modal
        var modal = document.getElementById('mq_passer_commande_fournisseur_modal');
        if (modal) {
            console.log('MQ Supplier JS: Modal trouvée, ajout des événements');
            modal.addEventListener('show.bs.modal', this.onModalShow.bind(this));
        } else {
            console.log('MQ Supplier JS: Modal non trouvée après création');
        }
        
        // Aussi écouter les clics sur le bouton pour debug
        var sendButton = document.getElementById('mq-send-supplier-requests');
        if (sendButton) {
            console.log('MQ Supplier JS: Bouton trouvé, ajout du listener de debug');
            sendButton.addEventListener('click', function(e) {
                console.log('MQ Supplier JS: Clic sur le bouton détecté', e);
            });
        } else {
            console.log('MQ Supplier JS: Bouton non trouvé');
        }
        
        console.log('MQ Supplier JS: Initialization terminée');
    },

    ensureModalExists: function() {
        console.log('MQ Supplier JS: Vérification existence modal...');
        
        // Vérifier si la modal existe déjà
        if (document.getElementById('mq_passer_commande_fournisseur_modal')) {
            console.log('MQ Supplier JS: Modal existe déjà');
            return;
        }

        console.log('MQ Supplier JS: Création de la modal...');
        
        // Créer la modal
        var modalHTML = `
            <div class="modal fade" id="mq_passer_commande_fournisseur_modal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <h4 class="modal-title">Demandes de délai fournisseur</h4>
                        </div>
                        <div class="modal-body">
                            <p>Chargement...</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Ajouter la modal au DOM
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        console.log('MQ Supplier JS: Modal créée et ajoutée au DOM');
    },

    onModalShow: function(event) {
        console.log('MQ Supplier JS: Modal en cours d\'ouverture');
        
        this.modal = document.getElementById('mq_passer_commande_fournisseur_modal');
        this.contenu = this.modal.querySelector('.modal-body');

        // Récupérer les produits sélectionnés
        var checkboxes = document.querySelectorAll('.mq-product-checkbox:checked');
        console.log('MQ Supplier JS: Checkboxes sélectionnées:', checkboxes.length);
        
        if (checkboxes.length === 0) {
            this.contenu.innerHTML = '<div class="alert alert-warning">Aucun produit sélectionné.</div>';
            return;
        }

        var products = [];
        var orderRef = '';
        
        for (var i = 0; i < checkboxes.length; i++) {
            var checkbox = checkboxes[i];
            products.push({
                'id_product': checkbox.getAttribute('data-product-id') || '0',
                'id_product_attribute': checkbox.getAttribute('data-product-attribute-id') || '0',
                'reference': checkbox.getAttribute('data-reference') || ''
            });
            
            if (!orderRef) {
                orderRef = checkbox.getAttribute('data-order_ref') || window.mqOrderReference || '';
            }
        }

        console.log('MQ Supplier JS: Produits sélectionnés:', products);

        // Requête AJAX pour récupérer les templates d'emails
        this.loadEmailTemplates(products, orderRef);
    },

    loadEmailTemplates: function(products, orderRef) {
        console.log('MQ Supplier JS: Chargement des templates email...');
        this.contenu.innerHTML = '<p><i class="icon-spinner icon-spin"></i> Génération des emails...</p>';

        // Préparer les données pour l'AJAX
        var formData = new FormData();
        formData.append('action', 'mqmailcontents');
        formData.append('products', JSON.stringify(products));
        formData.append('order_ref', orderRef);

        console.log('MQ Supplier JS: URL AJAX:', window.mqsupplier_action_url);
        console.log('MQ Supplier JS: Données envoyées:', {
            action: 'mqmailcontents',
            products: products,
            order_ref: orderRef
        });

        fetch(window.mqsupplier_action_url, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { 
            console.log('MQ Supplier JS: Réponse reçue:', response);
            return response.json(); 
        })
        .then(this.drawMails.bind(this))
        .catch(function(error) {
            console.error('MQ Supplier JS: Erreur AJAX:', error);
            this.contenu.innerHTML = '<div class="alert alert-danger">Erreur de communication avec le serveur.</div>';
        }.bind(this));
    },

    drawMails: function(response) {
        console.log('MQ Supplier JS: Réponse JSON:', response);
        this.contenu.innerHTML = '';

        if (response.erreur && response.erreur.length > 0) {
            this.contenu.innerHTML = '<div class="alert alert-danger">Erreur : ' + response.erreur + '</div>';
            return;
        }

        if (!response.content || response.content.length === 0) {
            this.contenu.innerHTML = '<div class="alert alert-info">Aucun fournisseur trouvé pour les produits sélectionnés.</div>';
            return;
        }

        var self = this;
        response.content.forEach(function(supplierData) {
            self.drawSingleMail(supplierData);
        });
    },

    drawSingleMail: function(supplierData) {
        console.log('MQ Supplier JS: Création email pour fournisseur:', supplierData);
        
        var supplier = supplierData.supplier;
        var orderRef = supplierData.order_reference;

        // Créer le conteneur pour ce fournisseur
        var container = document.createElement('div');
        container.className = 'supplier-email-container';
        container.setAttribute('data-uniqid', 'id_' + Date.now() + '_' + supplier.id_supplier);

        // Titre avec nom et email du fournisseur
        var title = document.createElement('h4');
        title.textContent = supplier.name;
        if (supplier.email) {
            title.textContent += ' (' + supplier.email + ')';
        } else {
            title.textContent += ' (EMAIL NON RENSEIGNÉ)';
            title.style.color = '#d9534f';
        }

        // Zone de texte pour le contenu de l'email
        var textarea = document.createElement('textarea');
        textarea.className = 'form-control';
        textarea.rows = 10;
        textarea.style.marginBottom = '10px';
        textarea.value = this.generateEmailContent(supplierData);

        // Bouton d'envoi
        var sendButton = document.createElement('button');
        sendButton.className = 'btn btn-primary mq-send-email-btn';
        sendButton.textContent = 'Envoyer le mail';
        if (supplier.email) {
            sendButton.textContent += ' (' + supplier.email + ')';
        } else {
            sendButton.disabled = true;
            sendButton.className = 'btn btn-default';
            sendButton.textContent = 'Email non configuré';
        }

        // Données pour l'envoi
        sendButton.setAttribute('data-destinataire', supplier.email || '');
        sendButton.setAttribute('data-id_supplier', supplier.id_supplier);
        sendButton.setAttribute('data-products', JSON.stringify(supplierData.products));
        sendButton.setAttribute('data-order_ref', orderRef);
        sendButton.onclick = this.envoyerMail.bind(this);

        // Assemblage
        container.appendChild(title);
        container.appendChild(textarea);
        container.appendChild(sendButton);
        container.appendChild(document.createElement('hr'));

        this.contenu.appendChild(container);
    },

    generateEmailContent: function(supplierData) {
        var supplier = supplierData.supplier;
        var products = supplierData.products;
        var orderRef = supplierData.order_reference;

        var content = 'Bonjour ' + supplier.name + ',\n\n';
        content += 'Nous souhaitons connaître vos délais de livraison pour la commande ' + orderRef + ' concernant les produits suivants :\n\n';
        
        products.forEach(function(product) {
            content += '- ' + (product.name || 'Produit') + ' (Réf: ' + product.reference + ')\n';
        });

        content += '\nMerci de nous communiquer vos délais dans les plus brefs délais.\n\n';
        content += 'Cordialement,\n';
        content += (window.mqShopName || 'L\'équipe MonsterQuad');

        return content;
    },

    envoyerMail: function(event) {
        event.preventDefault();
        console.log('MQ Supplier JS: Envoi email...');

        var button = event.target;
        var container = button.closest('.supplier-email-container');
        var textarea = container.querySelector('textarea');
        
        // Désactiver le bouton et changer le texte
        button.disabled = true;
        button.textContent = 'Envoi en cours...';

        // Préparer les données
        var formData = new FormData();
        formData.append('action', 'mqenvoimail');
        formData.append('destinataire', button.getAttribute('data-destinataire'));
        formData.append('contenu_mail', textarea.value);
        formData.append('id_mail', container.getAttribute('data-uniqid'));
        formData.append('id_supplier', button.getAttribute('data-id_supplier'));
        formData.append('products', button.getAttribute('data-products'));
        formData.append('order_ref', button.getAttribute('data-order_ref'));

        fetch(window.mqsupplier_action_url, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(this.resultatEnvoiMail.bind(this, container))
        .catch(function(error) {
            console.error('MQ Supplier JS: Erreur envoi:', error);
            button.textContent = 'Erreur - Réessayer';
            button.disabled = false;
            button.className = 'btn btn-danger';
        });
    },

    resultatEnvoiMail: function(container, response) {
        console.log('MQ Supplier JS: Résultat envoi:', response);
        var button = container.querySelector('button');
        
        if (response.erreur && response.erreur.length > 0) {
            button.textContent = 'Erreur - Réessayer';
            button.disabled = false;
            button.className = 'btn btn-danger';
            alert('Erreur : ' + response.erreur);
            return;
        }

        // Succès
        button.textContent = 'Envoyé ✓';
        button.className = 'btn btn-success';
        button.disabled = true;
        
        // Désactiver aussi la zone de texte
        var textarea = container.querySelector('textarea');
        textarea.disabled = true;
        textarea.style.backgroundColor = '#f5f5f5';
    }
};

console.log('MQ Supplier JS: Fichier entièrement chargé'); 