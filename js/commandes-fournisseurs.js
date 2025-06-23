// JS pour PrestaShop 8.2 - Version simplifiée et fonctionnelle
var commandes_fournisseurs = {
  modal: null,
  contenu: null,

  onModalOpen: function() {
    console.log('Modal opened, processing...');

    this.modal = document.getElementById('passer_commande_fournisseur_modal');
    this.contenu = this.modal.querySelector('.modal-body');

    // Collecte des produits sélectionnés depuis notre interface
    const checkedInputs = document.querySelectorAll('#supplier-products-list input[type="checkbox"]:checked');

    if (checkedInputs.length === 0) {
      this.contenu.innerHTML = '<div class="alert alert-warning">Aucun produit sélectionné. Veuillez cocher au moins un produit.</div>';
      return;
    }

    const products = Array.from(checkedInputs).map(function(input) {
      return {
        'id_product': input.dataset.id_product,
        'id_product_attribute': input.dataset.id_product_attribute,
        'reference': input.dataset.reference
      };
    });

    const order_ref = checkedInputs[0].dataset.order_ref;

    console.log('Sending request to:', commandesfournisseur_action_url);
    console.log('Products:', products);
    console.log('Order ref:', order_ref);

    this.contenu.innerHTML = '<div class="text-center"><i class="icon-spinner icon-spin"></i> Génération des emails...</div>';

    // Appel AJAX avec fetch moderne
    fetch(commandesfournisseur_action_url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        'products': JSON.stringify(products),
        'action': 'mailcontents',
        'order_ref': order_ref
      })
    })
    .then(response => {
      console.log('Response status:', response.status);

      if (!response.ok) {
        throw new Error('Erreur HTTP: ' + response.status);
      }

      const contentType = response.headers.get('content-type');
      console.log('Content-Type:', contentType);

      if (!contentType || !contentType.includes('application/json')) {
        return response.text().then(text => {
          console.log('Non-JSON response:', text.substring(0, 500));
          throw new Error('La réponse du serveur n\'est pas du JSON valide');
        });
      }
      return response.json();
    })
    .then(data => {
      console.log('Response data:', data);
      this.drawMails(data);
    })
    .catch(error => {
      console.error('Erreur complète:', error);
      this.contenu.innerHTML = '<div class="alert alert-danger">Erreur AJAX: ' + error.message + '</div>';
    });
  },

  drawMails: function(response) {
    this.contenu.innerHTML = '';

    if (response.erreur && response.erreur.length > 0) {
      this.contenu.innerHTML = '<div class="alert alert-danger">Erreur : ' + response.erreur + '</div>';
      return;
    }

    if (!response.content || response.content.length === 0) {
      this.contenu.innerHTML = '<div class="alert alert-warning">Aucun email à générer.</div>';
      return;
    }

    const self = this;
    const draw_a_mail = function(supplier_products) {
      const supplierDiv = document.createElement('div');
      supplierDiv.className = 'panel panel-default mb-3';
      supplierDiv.dataset.uniqid = 'id_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

      const mailText = supplier_products.supplier.mail || 'MAIL NON RENSEIGNÉ';
      const hasEmail = supplier_products.supplier.mail && supplier_products.supplier.mail.length > 0;

      supplierDiv.innerHTML = `
        <div class="panel-heading">
          <h4 class="panel-title">
            ${supplier_products.supplier.name} 
            <span class="badge ${hasEmail ? 'badge-success' : 'badge-danger'}">${mailText}</span>
          </h4>
        </div>
        <div class="panel-body">
          <div class="form-group">
            <label>Contenu du message :</label>
            <textarea class="form-control" rows="8">${supplier_products.mail}</textarea>
          </div>
          ${hasEmail ? `
          <button type="button" class="btn btn-primary btn-send-mail" 
                  data-destinataire="${supplier_products.supplier.mail}"
                  data-id_supplier="${supplier_products.supplier.id_supplier}"
                  data-products='${JSON.stringify(supplier_products.products)}'
                  data-order_ref="${supplier_products.order.ref}">
            <i class="icon-envelope"></i> Envoyer le mail (${supplier_products.supplier.mail})
          </button>
          ` : `
          <div class="alert alert-warning">
            <i class="icon-warning"></i> Impossible d'envoyer : aucun email configuré pour ce fournisseur
          </div>
          `}
        </div>
      `;

      // Attacher l'événement au bouton
      const sendButton = supplierDiv.querySelector('.btn-send-mail');
      if (sendButton) {
        sendButton.onclick = function(e) {
          self.envoimail(e, supplierDiv);
        };
      }

      self.contenu.appendChild(supplierDiv);
    };

    response.content.forEach(draw_a_mail);
  },

  envoimail: function(e, supplierDiv) {
    e.preventDefault();

    const button = e.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="icon-spinner icon-spin"></i> Envoi en cours...';
    button.disabled = true;

    const textarea = supplierDiv.querySelector('textarea');

    fetch(commandesfournisseur_action_url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        'destinataire': button.dataset.destinataire,
        'contenu_mail': textarea.value,
        'id_mail': supplierDiv.dataset.uniqid,
        'id_supplier': button.dataset.id_supplier,
        'products': button.dataset.products,
        'order_ref': button.dataset.order_ref,
        'action': 'envoimail'
      })
    })
    .then(response => {
      console.log('Send mail response status:', response.status);

      if (!response.ok) {
        throw new Error('Erreur HTTP: ' + response.status);
      }

      const contentType = response.headers.get('content-type');

      if (!contentType || !contentType.includes('application/json')) {
        return response.text().then(text => {
          console.log('Non-JSON response:', text.substring(0, 500));
          throw new Error('La réponse du serveur n\'est pas du JSON valide');
        });
      }
      return response.json();
    })
    .then(data => {
      console.log('Send mail response data:', data);
      this.resultat_envoi_mails(data, supplierDiv, button, originalText);
    })
    .catch(error => {
      console.error('Erreur envoi mail:', error);
      button.innerHTML = originalText;
      button.disabled = false;

      // Afficher l'erreur dans le panel
      const alertDiv = document.createElement('div');
      alertDiv.className = 'alert alert-danger';
      alertDiv.innerHTML = '<i class="icon-exclamation-triangle"></i> Erreur lors de l\'envoi : ' + error.message;
      supplierDiv.querySelector('.panel-body').appendChild(alertDiv);
    });

    return false;
  },

  resultat_envoi_mails: function(response, supplierDiv, button, originalText) {
    if (!response.erreur || response.erreur.length === 0) {
      // Succès
      supplierDiv.style.opacity = '0.7';
      button.className = 'btn btn-success';
      button.innerHTML = '<i class="icon-check"></i> Envoyé avec succès';
      button.disabled = true;

      // Ajouter un message de succès
      const successDiv = document.createElement('div');
      successDiv.className = 'alert alert-success';
      successDiv.innerHTML = '<i class="icon-check"></i> Email envoyé avec succès !';
      supplierDiv.querySelector('.panel-body').appendChild(successDiv);

      return;
    }

    // Erreur
    button.innerHTML = originalText;
    button.disabled = false;

    // Afficher l'erreur
    const errorDiv = document.createElement('div');
    errorDiv.className = 'alert alert-danger';
    errorDiv.innerHTML = '<i class="icon-exclamation-triangle"></i> Erreur: ' + response.erreur;
    supplierDiv.querySelector('.panel-body').appendChild(errorDiv);
  }
};