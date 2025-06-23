$(document).ready(function () {
  commandes_fournisseurs.start()
})

commandes_fournisseurs = {
  modal: null,
  contenu: null,

  start: function xi (e) {
    $('#passer_commande_fournisseur_modal').on('show.bs.modal', function (event) {
      commandes_fournisseurs.modal = $(this)
      commandes_fournisseurs.contenu = $(this).find('.modal-body')
      // let contenu = modal.find('.modal-body')

      // id des produits selectionnés :
      let products = $('#orderProducts').find('input[name^="mail_fournisseur"]:checked').map(function () {
        return {
          'id_product': this.dataset.id_product,
          'id_product_attribute': this.dataset.id_product_attribute,
          'reference': this.dataset.reference
        }
      }).get()
      let order_ref = $('#orderProducts').find('input[name^="mail_fournisseur"]:checked')[0].dataset.order_ref;
      $.ajax({
        // url: url défini dans le template via le module ::hookDisplayBackOfficeHeader()
        url: commandesfournisseur_action_url, //?token='+token,
        data: {"products": products, "action": 'mailcontents', "order_ref": order_ref },
        cache: false,
        dataType: 'json'
      })
        .success(commandes_fournisseurs.drawMails)
        .error(function () {
          commandes_fournisseurs.contenu.html('Erreur')
          // window.console && console.log('Erreur ajax')
        })

      // var button = $(event.relatedTarget) // Button that triggered the modal
      // var recipient = button.data('whatever') // Extract info from data-* attributes
      // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
      // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.

      // modal.find('.modal-title').text('New message to ')
      // modal.find('.modal-body input').val(recipient)
    })
  },

  drawMails: function (response) {

    commandes_fournisseurs.contenu.html('')

    if(response.erreur.length > 0) {
      commandes_fournisseurs.contenu.html('Erreur : ' + response.erreur)
      return
    }

    let draw_a_mail = function(supplier_products) {
      // création et remplissage des différents éléments
      let titre = document.createElement('h4')
      $(titre).text(supplier_products.supplier.name + "(" + supplier_products.supplier.mail + " )")
      // si mail non défini
      if(null === supplier_products.supplier.mail) {
        $(titre).text(supplier_products.supplier.name + "( MAIL NON RENSEIGNÉ )")
      }


      let contenu_mail = document.createElement('textarea')
      $(contenu_mail).text(supplier_products.mail)

      // let joined_products_ids = supplier_products.products.map((i) => encodeURIComponent(i.reference)).join(',')

      let bouton_envoi = document.createElement('button' )
      $(bouton_envoi).text('Envoyer le mail ( '+ supplier_products.supplier.mail + ' )')
      bouton_envoi.setAttribute('data-destinataire', supplier_products.supplier.mail)
      bouton_envoi.setAttribute('data-id_supplier', supplier_products.supplier.id_supplier)
      bouton_envoi.setAttribute('data-products', JSON.stringify(supplier_products.products))
      bouton_envoi.setAttribute('data-order_ref', supplier_products.order.ref)
      bouton_envoi.setAttribute('class', 'passer_commande_fournisseur_submit btn btn-primary')
      bouton_envoi.onclick = commandes_fournisseurs.envoimail

      let contenaire = document.createElement('div')
      contenaire.setAttribute('data-uniqid', 'id_' + Date.now() )
      $(contenaire).append(titre)
      $(contenaire).append(contenu_mail)
      $(contenaire).append(bouton_envoi)

      commandes_fournisseurs.contenu.append(contenaire)
    }

    commandes_fournisseurs.contenu.find('p').remove()

    // @todo utiliser cette forme ?
    // response.content.map(draw_a_mail)

    response.content.map(function(supplier_products) {
      return draw_a_mail(supplier_products)
    })

    // commandes_fournisseurs.modal('handleUpdate') // réajutstement hauteur ne fonctionne pas.
  },

  envoimail: function (e) {
    e.preventDefault()

    $(e.target).text('Patienter ...')

    let parent_div = e.target.parentElement
    $.ajax({
      // url: url défini dans le template via le module ::hookDisplayBackOfficeHeader()
      url: commandesfournisseur_action_url,
      data: {"destinataire": e.target.dataset.destinataire,
             "contenu_mail": $(parent_div).find('textarea').val(),
             "id_mail": parent_div.dataset.uniqid,
             "id_supplier": e.target.dataset.id_supplier,
             "products": e.target.dataset.products,
             "order_ref": e.target.dataset.order_ref,
             "action": 'envoimail'},
      cache: false,
      dataType: 'json',

    })
      .success(commandes_fournisseurs.resultat_envoi_mails)
      .error(function () {
        commandes_fournisseurs.contenu.html('Erreur ajax.')
        // window.console && console.log('Erreur ajax')
      })

    return false
  },

  /** fonction quand il n'y a pas de problème réseaux ou d'erreur 500, bref un code 200 */
  resultat_envoi_mails: function (response) {
    if(response.erreur.length === 0) {
      let id_div_a_effacer = response.id_mail;
      let div = $(`div[data-uniqid="${id_div_a_effacer}"]`)
      let button = div.find('button')
      div.attr('disabled', 'disabled')
      button.addClass('btn-default').removeClass('btn-primary').text('envoyé.').attr('disabled', 'disabled')
      return
    }

    alert('oopus : implementer affichage erreurs logiques ici : ' + response.erreur);
    // @todo cf src/override/controllers/admin/AdminOrdersController.php:41
  }
}

// {"content":
//   {"4":
//     { "products":{"name":"LENTILLE VOYANT D HUILE","reference":"SC-55555-BL9-00","id_supplier":"4"},
//       "supplier":{"name":"DELTAMICS"},"mail":"Bonjour,\n\nNous souhaitons commander les produits suivants :\n\n - L (L)\n - S (S)\n - 4 (4)\n\nCordialement,\nMonsterquad.\n"
//     }
//   },
//   "erreur":""
// }
