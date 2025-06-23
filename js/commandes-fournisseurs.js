document.addEventListener('DOMContentLoaded', function() {
  commandes_fournisseurs.start();
});

commandes_fournisseurs = {
  modal: null,
  contenu: null,

  start: function() {
    // Attendre que la modal soit créée par le script inline
    setTimeout(() => {
      const modal = document.getElementById('passer_commande_fournisseur_modal');
      if (!modal) {
        console.log('Modal not found, retrying...');
        setTimeout(() => this.start(), 1000);
        return;
      }

      console.log('Modal found, setting up event listeners');

      // Observer pour détecter quand la modal s'ouvre
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
            if (modal.style.display === 'block') {
              this.onModalOpen();
            }
          }
        });
      });

      observer.observe(modal, { attributes: true });
    }, 500);
  },

  onModalOpen: function() {
    console.log('Modal opened, processing...');

    this.modal = document.getElementById('passer_commande_fournisseur_modal');
    this.contenu = this.modal.querySelector('.modal-body');

    // Collecte des produits sélectionnés
    const checkedInputs = document.querySelectorAll('input[name^="mail_fournisseur"]:checked');

    const products = Array.from(checkedInputs).map(function(input) {
      return {
        'id_product': input.dataset.id_product,
        'id_product_attribute': input.dataset.id_product_attribute,
        'reference': input.dataset.reference
      };
    });

    if (products.length === 0) {
      this.contenu.innerHTML = 'Aucun produit sélectionné';
      return;
    }

    const order_ref = checkedInputs[0].dataset.order_ref;

    // Appel AJAX avec fetch (moderne)
    fetch(window.commandesfournisseur_action_url || commandesfournisseur_action_url, {
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
    .then(response => response.json())
    .then(data => this.drawMails(data))
    .catch(error => {
      this.contenu.innerHTML = 'Erreur AJAX: ' + error.message;
      console.error('Erreur:', error);
    });
  },

  drawMails: function(response) {
    this.contenu.innerHTML = '';

    if (response.erreur && response.erreur.length > 0) {
      this.contenu.innerHTML = 'Erreur : ' + response.erreur;
      return;
    }

    const draw_a_mail = (supplier_products) => {
      // Création des éléments
      const titre = document.createElement('h4');
      const mailText = supplier_products.supplier.mail || 'MAIL NON RENSEIGNÉ';
      titre.textContent = supplier_products.supplier.name + " (" + mailText + ")";

      const contenu_mail = document.createElement('textarea');
      contenu_mail.className = 'form-control';
      contenu_mail.rows = 10;
      contenu_mail.value = supplier_products.mail;

      const bouton_envoi = document.createElement('button');
      bouton_envoi.textContent = 'Envoyer le mail (' + supplier_products.supplier.mail + ')';
      bouton_envoi.className = 'passer_commande_fournisseur_submit btn btn-primary mt-2';
      bouton_envoi.dataset.destinataire = supplier_products.supplier.mail;
      bouton_envoi.dataset.id_supplier = supplier_products.supplier.id_supplier;
      bouton_envoi.dataset.products = JSON.stringify(supplier_products.products);
      bouton_envoi.dataset.order_ref = supplier_products.order.ref;
      bouton_envoi.onclick = this.envoimail.bind(this);

      const contenaire = document.createElement('div');
      contenaire.className = 'mb-4';
      contenaire.dataset.uniqid = 'id_' + Date.now();
      contenaire.appendChild(titre);
      contenaire.appendChild(contenu_mail);
      contenaire.appendChild(bouton_envoi);

      this.contenu.appendChild(contenaire);
    };

    // Supprimer les paragraphes existants
    const paragraphs = this.contenu.querySelectorAll('p');
    paragraphs.forEach(p => p.remove());

    // Dessiner chaque mail
    response.content.forEach(draw_a_mail);
  },

  envoimail: function(e) {
    e.preventDefault();

    const button = e.target;
    const originalText = button.textContent;
    button.textContent = 'Patienter...';
    button.disabled = true;

    const parent_div = button.parentElement;
    const textarea = parent_div.querySelector('textarea');

    fetch(window.commandesfournisseur_action_url || commandesfournisseur_action_url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        'destinataire': button.dataset.destinataire,
        'contenu_mail': textarea.value,
        'id_mail': parent_div.dataset.uniqid,
        'id_supplier': button.dataset.id_supplier,
        'products': button.dataset.products,
        'order_ref': button.dataset.order_ref,
        'action': 'envoimail'
      })
    })
    .then(response => response.json())
    .then(data => this.resultat_envoi_mails(data))
    .catch(error => {
      this.contenu.innerHTML = 'Erreur AJAX: ' + error.message;
      console.error('Erreur:', error);
      button.textContent = originalText;
      button.disabled = false;
    });

    return false;
  },

  resultat_envoi_mails: function(response) {
    if (!response.erreur || response.erreur.length === 0) {
      const id_div_a_effacer = response.id_mail;
      const div = document.querySelector(`div[data-uniqid="${id_div_a_effacer}"]`);
      const button = div.querySelector('button');

      div.style.opacity = '0.6';
      button.className = 'btn btn-secondary mt-2';
      button.textContent = 'Envoyé';
      button.disabled = true;
      return;
    }

    alert('Erreur: ' + response.erreur);
  }
};