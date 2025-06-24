// ATTENTION : TOUS les appels AJAX du module doivent utiliser window.mqsupplier_action_url injectée par le template !
// Ne jamais utiliser window.location ou une autre logique pour l'URL AJAX.

document.addEventListener('DOMContentLoaded', function () {
  mqcommandes_fournisseurs.start();
});

const mqcommandes_fournisseurs = {
  modal: null,
  contenu: null,

  start: function () {
    const modalElem = document.getElementById('passer_commande_fournisseur_modal');
    if (modalElem) {
      modalElem.addEventListener('show.bs.modal', function (event) {
        mqcommandes_fournisseurs.modal = modalElem;
        mqcommandes_fournisseurs.contenu = modalElem.querySelector('.modal-body');
        // id des produits selectionnés :
        const checkedInputs = document.querySelectorAll('#orderProducts input[name^="mail_fournisseur"]:checked');
        let products = Array.from(checkedInputs).map(function (input) {
          return {
            'id_product': input.dataset.id_product,
            'id_product_attribute': input.dataset.id_product_attribute,
            'reference': input.dataset.reference
          };
        });
        let order_ref = checkedInputs[0] ? checkedInputs[0].dataset.order_ref : null;
        console.log('[MQ Supplier] URL AJAX utilisée :', window.mqsupplier_action_url);
        fetch(window.mqsupplier_action_url, {
          method: 'POST',
          headers: { 'Accept': 'application/json' },
          body: mqcommandes_fournisseurs.toFormData({
            products: JSON.stringify(products),
            action: 'mailcontents',
            order_ref: order_ref
          })
        })
        .then(response => response.json())
        .then(mqcommandes_fournisseurs.drawMails)
        .catch(() => {
          mqcommandes_fournisseurs.contenu.innerHTML = 'Erreur';
        });
      });
    }
  },

  drawMails: function (response) {
    mqcommandes_fournisseurs.contenu.innerHTML = '';
    if(response.erreur && response.erreur.length > 0) {
      mqcommandes_fournisseurs.contenu.innerHTML = 'Erreur : ' + response.erreur;
      return;
    }
    const draw_a_mail = function(supplier_products) {
      let titre = document.createElement('h4');
      titre.textContent = supplier_products.supplier.name + "(" + supplier_products.supplier.mail + " )";
      if (supplier_products.supplier.mail === null) {
        titre.textContent = supplier_products.supplier.name + "( MAIL NON RENSEIGNÉ )";
      }
      let contenu_mail = document.createElement('textarea');
      contenu_mail.value = supplier_products.mail;
      let bouton_envoi = document.createElement('button');
      bouton_envoi.textContent = 'Envoyer le mail ( '+ supplier_products.supplier.mail + ' )';
      bouton_envoi.setAttribute('data-destinataire', supplier_products.supplier.mail);
      bouton_envoi.setAttribute('data-id_supplier', supplier_products.supplier.id_supplier);
      bouton_envoi.setAttribute('data-products', JSON.stringify(supplier_products.products));
      bouton_envoi.setAttribute('data-order_ref', supplier_products.order.ref);
      bouton_envoi.setAttribute('class', 'passer_commande_fournisseur_submit btn btn-primary');
      bouton_envoi.addEventListener('click', mqcommandes_fournisseurs.envoimail);
      let contenaire = document.createElement('div');
      contenaire.setAttribute('data-uniqid', 'id_' + Date.now());
      contenaire.appendChild(titre);
      contenaire.appendChild(contenu_mail);
      contenaire.appendChild(bouton_envoi);
      mqcommandes_fournisseurs.contenu.appendChild(contenaire);
    };
    mqcommandes_fournisseurs.contenu.querySelectorAll('p').forEach(p => p.remove());
    if (response.content) {
      response.content.forEach(draw_a_mail);
    }
  },

  envoimail: function (e) {
    e.preventDefault();
    e.target.textContent = 'Patienter ...';
    let parent_div = e.target.parentElement;
    console.log('[MQ Supplier] URL AJAX utilisée (envoi mail) :', window.mqsupplier_action_url);
    const textarea = parent_div.querySelector('textarea');
    fetch(window.mqsupplier_action_url, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      body: mqcommandes_fournisseurs.toFormData({
        destinataire: e.target.dataset.destinataire,
        contenu_mail: textarea.value,
        id_mail: parent_div.dataset.uniqid,
        id_supplier: e.target.dataset.id_supplier,
        products: e.target.dataset.products,
        order_ref: e.target.dataset.order_ref,
        action: 'envoimail'
      })
    })
    .then(response => response.json())
    .then(mqcommandes_fournisseurs.resultat_envoi_mails)
    .catch(() => {
      mqcommandes_fournisseurs.contenu.innerHTML = 'Erreur ajax.';
    });
    return false;
  },

  resultat_envoi_mails: function (response) {
    if(response.erreur && response.erreur.length === 0) {
      let id_div_a_effacer = response.id_mail;
      let div = document.querySelector("div[data-uniqid='"+id_div_a_effacer+"']");
      let button = div.querySelector('button');
      div.setAttribute('disabled', 'disabled');
      button.classList.add('btn-default');
      button.classList.remove('btn-primary');
      button.textContent = 'envoyé.';
      button.setAttribute('disabled', 'disabled');
      return;
    }
    alert('oopus : implementer affichage erreurs logiques ici : ' + response.erreur);
  },

  toFormData: function(obj) {
    const formData = new FormData();
    for (let key in obj) {
      formData.append(key, obj[key]);
    }
    return formData;
  }
};
// Fin du fichier : NE PAS redéfinir window.mqsupplier_action_url ailleurs ! 