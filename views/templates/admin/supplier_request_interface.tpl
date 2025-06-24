<!-- Interface MQ Supplier Request -->
<div id="mq-supplier-request-section" class="panel panel-default" style="margin-top: 20px; background: #f8f9fa; border: 2px solid #007cba;">
    <div class="panel-heading" style="background: #007cba; color: white;">
        <i class="icon-envelope"></i> <strong>Demandes de délai fournisseur</strong>
        <div class="panel-actions">
            <button type="button" 
                    id="mq-send-supplier-requests" 
                    class="btn btn-warning btn-sm" 
                    disabled
                    style="font-weight: bold;">
                <i class="icon-envelope"></i> Envoyer les demandes fournisseur
            </button>
        </div>
    </div>
    <div class="panel-body" style="padding: 15px;">
        <div class="row">
            <div class="col-md-6">
                <label style="font-weight: bold; color: #007cba;">
                    <input type="checkbox" id="mq-select-all" style="margin-right: 8px; transform: scale(1.2);" />
                    Tout sélectionner dans le tableau ci-dessus
                </label>
            </div>
            <div class="col-md-6 text-right">
                <span id="mq-selected-count" class="help-block" style="margin: 0; font-weight: bold; color: #666;">Aucun produit sélectionné</span>
            </div>
        </div>
        
        <div class="row" style="margin-top: 10px;">
            <div class="col-md-12">
                <p class="help-block" style="margin: 0; font-style: italic;">
                    ℹ️ Sélectionnez les produits dans le tableau ci-dessus en cochant les cases de la colonne "Fournisseur", puis cliquez sur le bouton pour envoyer les demandes de délai.
                </p>
            </div>
        </div>
        
        <!-- Zone de messages -->
        <div id="mq-messages" class="alert" style="display: none; margin-top: 15px;"></div>
    </div>
</div>

<!-- Modal pour les demandes fournisseur -->
<div class="modal fade" id="mq_passer_commande_fournisseur_modal" tabindex="-1" role="dialog" aria-labelledby="mqModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="mqModalLabel">Demandes de délai fournisseur</h4>
            </div>
            <div class="modal-body">
                <p><i class="icon-spinner icon-spin"></i> Chargement...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    console.log('MQ Supplier Request module initializing...');
    
    // Attendre que la page soit complètement chargée
    setTimeout(function() {
        initializeMQSupplierInterface();
    }, 1000);
    
    function initializeMQSupplierInterface() {
        // Chercher le tableau des produits existant
        const productTable = findProductTable();
        if (!productTable) {
            console.log('Tableau des produits non trouvé');
            return;
        }
        
        console.log('Tableau des produits trouvé, ajout des cases à cocher...');
        
        // Ajouter la colonne pour les cases à cocher
        addCheckboxColumn(productTable);
        
        // Initialiser les événements
        initializeEvents();
    }
    
    function findProductTable() {
        // Chercher le tableau contenant les produits
        const tables = document.querySelectorAll('table');
        for (let table of tables) {
            const headers = table.querySelectorAll('th');
            for (let header of headers) {
                if (header.textContent.includes('Produit') || header.textContent.includes('Référence')) {
                    return table;
                }
            }
        }
        return null;
    }
    
    function addCheckboxColumn(table) {
        const thead = table.querySelector('thead');
        const tbody = table.querySelector('tbody');
        
        if (!thead || !tbody) return;
        
        // Ajouter l'en-tête pour les cases à cocher
        const headerRow = thead.querySelector('tr');
        if (headerRow) {
            const newHeader = document.createElement('th');
            newHeader.innerHTML = '<input type="checkbox" id="mq-select-all" style="margin-right: 5px;" /> Fournisseur';
            newHeader.style.width = '120px';
            headerRow.insertBefore(newHeader, headerRow.firstChild);
        }
        
        // Ajouter les cases à cocher pour chaque produit
        const rows = tbody.querySelectorAll('tr');
        rows.forEach((row, index) => {
            const referenceCell = findReferenceInRow(row);
            if (referenceCell) {
                const reference = referenceCell.textContent.trim();
                
                const newCell = document.createElement('td');
                newCell.innerHTML = `
                    <input type="checkbox" 
                           class="mq-product-checkbox" 
                           data-reference="${reference}"
                           data-row-index="${index}"
                           style="margin-right: 5px;" />
                    <br><small class="text-muted">Dernière: Jamais</small>
                `;
                row.insertBefore(newCell, row.firstChild);
            }
        });
    }
    
    function findReferenceInRow(row) {
        const cells = row.querySelectorAll('td');
        for (let cell of cells) {
            const text = cell.textContent.trim();
            // Chercher une cellule qui ressemble à une référence produit
            if (text.match(/^[A-Z0-9-]+$/)) {
                return cell;
            }
        }
        return null;
    }
    
    function initializeEvents() {
        const selectAllCheckbox = document.getElementById('mq-select-all');
        const sendButton = document.getElementById('mq-send-supplier-requests');
        const selectedCountSpan = document.getElementById('mq-selected-count');
        const messagesDiv = document.getElementById('mq-messages');
        
            // Gestion de la sélection globale - synchroniser avec celle du tableau
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            // Synchroniser avec la case du tableau
            const tableSelectAll = document.getElementById('mq-select-all-orders');
            if (tableSelectAll) {
                tableSelectAll.checked = this.checked;
                // Déclencher l'événement sur la case du tableau
                const event = new Event('change');
                tableSelectAll.dispatchEvent(event);
            }
            
            // Aussi mettre à jour directement
            const productCheckboxes = document.querySelectorAll('.mq-product-checkbox');
            productCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateUI();
        });
    }
        
        // Gestion de la sélection individuelle
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('mq-product-checkbox')) {
                updateUI();
            }
        });
        
        // Gestion du bouton d'envoi
        if (sendButton) {
            sendButton.addEventListener('click', sendSupplierRequests);
        }
        
        // Mise à jour initiale
        updateUI();
    }
    
    function updateUI() {
        const productCheckboxes = document.querySelectorAll('.mq-product-checkbox');
        const selectedCheckboxes = document.querySelectorAll('.mq-product-checkbox:checked');
        const selectAllCheckbox = document.getElementById('mq-select-all');
        const sendButton = document.getElementById('mq-send-supplier-requests');
        const selectedCountSpan = document.getElementById('mq-selected-count');
        
        const count = selectedCheckboxes.length;
        const total = productCheckboxes.length;
        
        // Mise à jour du bouton
        if (sendButton) {
            sendButton.disabled = count === 0;
        }
        
        // Mise à jour du compteur
        if (selectedCountSpan) {
            if (count === 0) {
                selectedCountSpan.textContent = 'Aucun produit sélectionné';
                selectedCountSpan.className = 'help-block';
            } else {
                selectedCountSpan.textContent = `${count} produit(s) sélectionné(s)`;
                selectedCountSpan.className = 'help-block text-success';
            }
        }
        
        // Mise à jour du checkbox global
        if (selectAllCheckbox && total > 0) {
            selectAllCheckbox.indeterminate = count > 0 && count < total;
            selectAllCheckbox.checked = count === total;
        }
    }
    
    function sendSupplierRequests() {
        console.log('MQ Supplier: sendSupplierRequests appelé');
        
        const selectedCheckboxes = document.querySelectorAll('.mq-product-checkbox:checked');
        if (selectedCheckboxes.length === 0) {
            showMessage('Veuillez sélectionner au moins un produit.', 'warning');
            return;
        }
        
        console.log('MQ Supplier: ' + selectedCheckboxes.length + ' produits sélectionnés');
        
        // Vérifier que la modal existe
        const modal = document.getElementById('mq_passer_commande_fournisseur_modal');
        if (!modal) {
            showMessage('Erreur: Modal non trouvée', 'danger');
            return;
        }
        
        console.log('MQ Supplier: Modal trouvée, ouverture...');
        
        // Ouvrir la modal Bootstrap
        if (typeof $ !== 'undefined' && $.fn.modal) {
            console.log('MQ Supplier: Utilisation de Bootstrap modal');
            $('#mq_passer_commande_fournisseur_modal').modal('show');
        } else {
            console.log('MQ Supplier: Fallback manuel pour la modal');
            // Fallback manuel
            modal.style.display = 'block';
            modal.classList.add('in');
            document.body.classList.add('modal-open');
            
            // Ajouter un backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade in';
            backdrop.id = 'mq-modal-backdrop';
            document.body.appendChild(backdrop);
            
            // Gérer la fermeture
            const closeButtons = modal.querySelectorAll('[data-dismiss="modal"]');
            closeButtons.forEach(button => {
                button.onclick = function() {
                    modal.style.display = 'none';
                    modal.classList.remove('in');
                    document.body.classList.remove('modal-open');
                    const backdrop = document.getElementById('mq-modal-backdrop');
                    if (backdrop) backdrop.remove();
                };
            });
            
            // Déclencher l'événement show.bs.modal manuellement
            if (typeof mq_commandes_fournisseurs !== 'undefined') {
                mq_commandes_fournisseurs.onModalShow();
            } else {
                // Si le JS externe n'est pas chargé, charger les emails directement
                loadEmailTemplatesInModal();
            }
        }
    }
    
    // Fonction pour charger les templates d'emails dans la modal
    function loadEmailTemplatesInModal() {
        console.log('MQ Supplier: Chargement direct des templates email...');
        
        const modal = document.getElementById('mq_passer_commande_fournisseur_modal');
        const modalBody = modal.querySelector('.modal-body');
        
        // Récupérer les produits sélectionnés
        const selectedCheckboxes = document.querySelectorAll('.mq-product-checkbox:checked');
        const products = [];
        
        selectedCheckboxes.forEach(checkbox => {
            const reference = checkbox.getAttribute('data-reference');
            let productData = { id_product: 0, id_product_attribute: 0 };
            
            if (window.mqOrderProducts && reference) {
                for (let j = 0; j < window.mqOrderProducts.length; j++) {
                    if (window.mqOrderProducts[j].reference === reference) {
                        const product = window.mqOrderProducts[j];
                        productData.id_product = parseInt(product.id_product) || 0;
                        productData.id_product_attribute = parseInt(product.id_product_attribute) || 0;
                        break;
                    }
                }
            }
            
            products.push({
                id_product: productData.id_product,
                id_product_attribute: productData.id_product_attribute,
                reference: reference
            });
        });
        
        modalBody.innerHTML = '<p><i class="icon-spinner icon-spin"></i> Génération des emails...</p>';
        
        // Préparer les données pour l'AJAX
        const formData = new FormData();
        formData.append('action', 'mqmailcontents');
        formData.append('products', JSON.stringify(products));
        formData.append('order_ref', window.mqOrderReference || '{$order_reference|escape:'quotes':'UTF-8'}');
        
        // Envoi de la requête AJAX
        fetch(window.mqsupplier_action_url, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('MQ Supplier: Réponse reçue:', response);
            return response.json();
        })
        .then(data => {
            console.log('MQ Supplier: Données JSON:', data);
            
            if (data.erreur && data.erreur.length > 0) {
                modalBody.innerHTML = '<div class="alert alert-danger">Erreur : ' + data.erreur + '</div>';
                return;
            }
            
            if (!data.content || data.content.length === 0) {
                modalBody.innerHTML = '<div class="alert alert-info">Aucun fournisseur trouvé pour les produits sélectionnés.</div>';
                return;
            }
            
            // Afficher les emails par fournisseur
            modalBody.innerHTML = '';
            data.content.forEach(supplierData => {
                const container = createSupplierEmailContainer(supplierData);
                modalBody.appendChild(container);
            });
        })
        .catch(error => {
            console.error('MQ Supplier: Erreur AJAX:', error);
            modalBody.innerHTML = '<div class="alert alert-danger">Erreur de communication avec le serveur.</div>';
        });
    }
    
    // Fonction pour créer le conteneur d'email pour un fournisseur
    function createSupplierEmailContainer(supplierData) {
        const supplier = supplierData.supplier;
        const orderRef = supplierData.order_reference;
        
        const container = document.createElement('div');
        container.className = 'supplier-email-container';
        container.style.marginBottom = '20px';
        container.setAttribute('data-uniqid', 'id_' + Date.now() + '_' + supplier.id_supplier);
        
        // Titre
        const title = document.createElement('h4');
        title.textContent = supplier.name;
        if (supplier.email) {
            title.textContent += ' (' + supplier.email + ')';
        } else {
            title.textContent += ' (EMAIL NON RENSEIGNÉ)';
            title.style.color = '#d9534f';
        }
        
        // Zone de texte
        const textarea = document.createElement('textarea');
        textarea.className = 'form-control';
        textarea.rows = 10;
        textarea.style.marginBottom = '10px';
        textarea.value = generateEmailContent(supplierData);
        
        // Bouton d'envoi
        const sendButton = document.createElement('button');
        sendButton.className = 'btn btn-primary';
        sendButton.style.marginBottom = '10px';
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
        
        sendButton.onclick = function() {
            sendEmailToSupplier(this, container);
        };
        
        // Assemblage
        container.appendChild(title);
        container.appendChild(textarea);
        container.appendChild(sendButton);
        container.appendChild(document.createElement('hr'));
        
        return container;
    }
    
    // Fonction pour générer le contenu de l'email
    function generateEmailContent(supplierData) {
        const supplier = supplierData.supplier;
        const products = supplierData.products;
        const orderRef = supplierData.order_reference;
        
        let content = 'Bonjour ' + supplier.name + ',\n\n';
        content += 'Nous souhaitons connaître vos délais de livraison pour la commande ' + orderRef + ' concernant les produits suivants :\n\n';
        
        products.forEach(product => {
            content += '- ' + (product.name || 'Produit') + ' (Réf: ' + product.reference + ')\n';
        });
        
        content += '\nMerci de nous communiquer vos délais dans les plus brefs délais.\n\n';
        content += 'Cordialement,\n';
        content += 'L\'équipe MonsterQuad';
        
        return content;
    }
    
    // Fonction pour envoyer l'email à un fournisseur
    function sendEmailToSupplier(button, container) {
        console.log('MQ Supplier: Envoi email...');
        
        const textarea = container.querySelector('textarea');
        
        // Désactiver le bouton
        button.disabled = true;
        button.textContent = 'Envoi en cours...';
        
        // Préparer les données
        const formData = new FormData();
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
        .then(response => response.json())
        .then(response => {
            console.log('MQ Supplier: Résultat envoi:', response);
            
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
            textarea.disabled = true;
            textarea.style.backgroundColor = '#f5f5f5';
        })
        .catch(error => {
            console.error('MQ Supplier: Erreur envoi:', error);
            button.textContent = 'Erreur - Réessayer';
            button.disabled = false;
            button.className = 'btn btn-danger';
        });
    }
    
    function showMessage(message, type) {
        const messagesDiv = document.getElementById('mq-messages');
        if (messagesDiv) {
            messagesDiv.className = 'alert alert-' + type;
            messagesDiv.textContent = message;
            messagesDiv.style.display = 'block';
            
            // Masquer après 5 secondes
            setTimeout(() => {
                messagesDiv.style.display = 'none';
            }, 5000);
        }
    }
});
</script> 