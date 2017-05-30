/* (Manage) Mon compte */
$(document).on({
  click : function(e) {
    // Toggle les items de la liste
    $('#settingstabpluginmelanie2_resources_bal').toggle();
    $('#settingstabpluginmelanie2_resources_agendas').toggle();
    $('#settingstabpluginmelanie2_resources_contacts').toggle();
    $('#settingstabpluginmelanie2_resources_tasks').toggle();
  }
}, ".tablink.melanie2.resources"); // pass the element as an argument to .on

$(document).on({
  click : function(e) {
    // Toggle les items de la liste
    $('#settingstabpluginmelanie2_statistics_mobile').toggle();
  }
}, ".tablink.melanie2.statistics"); // pass the element as an argument to .on

$(function() {
  $("#resources-details").tabs();
});

$(document)
    .on({
      submit : function(e) {
        // Toggle les items de la liste
        if ($('#rcmfd_changepassword_newpassword').val() != $('#rcmfd_changepassword_newpassword_confirm')
            .val()) {
          // alert('mots de passe différents');
          e.preventDefault();
          rcmail.display_message(rcmail
              .gettext('melanie2_moncompte.error_password_confirm'), 'error');

        }
      }
    }, "#change_password_form"); // pass the element as an argument to .on

$(document).on({
  change : function(e) {
    var url = document.location.href;
    if (url.indexOf("_current_username") == -1 && url.indexOf("#") == -1) {
      // Valeur n'est pas window présente, on l'ajoute
      window.location = url + '&_current_username=' + $(this).val();
    }
    else if (url.indexOf("_current_username") == -1) {
      // La valeur n'existe pas mais l'url fini par #
      window.location = url.replace('#', '&_current_username=' + $(this).val()
          + '#')
    }
    else {
      // La valeur est présente, on la recherche pour faire un replace
      var params = url.split("?")[1].split("&");
      for ( var key in params) {
        if (params[key].indexOf("_current_username") == 0) {
          var value = params[key].split('=')[1];
          window.location = url
              .replace('_current_username=' + value, '_current_username='
                  + $(this).val());
          break;
        }
      }
    }
  }
}, "#rcmmoncomptebalplist"); // pass the element as an argument to .on

// Gestonnaire absence, boutons radio

$(document).on({
  change : function(e) {
    if ($(this).val() == 'abs_texte_nodiff') {
      // var msg = $('#abs_msg_mel').val();
      // alert(msg);
      // $('#abs_msg_inter').val(msg);
      // $('#abs_msg_inter').val($('#abs_msg_mel').val());
      $('#abs_msg_inter').hide();
    }
    else {
      $('#abs_msg_inter').show();
    }
  }
}, "input[name='abs_reponse']");
// -----------------------------------

// Publication photo

$(document).on({
  click : function(e) {
    if ($(this).is(':checked')) {
      $('#photo_ader').parent().show();
    }
    else {
      $('#photo_ader').prop('checked', false);
      $('#photo_ader').parent().hide();
    }
  }
}, "#photo_intra");

// -----------------------------------

// Gestonnaire de listes

var lists_members = [];

$(document).on({
  change : function(e) {
    if (lists_members[$(this).val()]) {
      refreshListMembers($(this).val());
    }
    else {
      var lock = rcmail.display_message(rcmail
          .gettext('melanie2_moncompte.wait'), 'loading');
      var res = rcmail.http_post('plugin.listes_membres', {
        _dn_list : $(this).val(),
        _current_username : $('#rcmmoncomptebalplist option:selected').val()
      }, lock);
    }
  }
}, "#liste_listes");

$(document).on({
  click : function(e) {
    AddExternalMember();
  }
}, "#listes_saisir");

$(document).on({
  click : function(e) {
    RemoveMember();
  }
}, "#listes_retirer");

$(document).on({
  click : function(e) {
    RemoveAllMembers();
  }
}, "#listes_purger");

$(document).on({
  click : function(e) {
    ExportMembers();
  }
}, "#listes_exporter");

$(document).on({
  click : function(e) {
    var dn_list = $('#liste_listes option:selected').val();
    if (dn_list) {
      UI.show_uploadform();
      $('#hidden_dn_list').val(dn_list);
    }
    else {
      alert(rcmail.gettext('melanie2_moncompte.listes_noselect'));
    }
  }
}, "#listes_importer");

// -----------------------------------

if (window.rcmail) {
  rcmail.addEventListener('responseafterplugin.listes_membres', function(evt) {

    lists_members[evt.response.dn_list] = evt.response.data;
    refreshListMembers(evt.response.dn_list);

  });

  rcmail.addEventListener('responseafterplugin.listes_add_externe', function(
      evt) {

    lists_members[evt.response.dn_list] = evt.response.data;
    refreshListMembers(evt.response.dn_list);

  });

  rcmail.addEventListener('responseafterplugin.listes_remove', function(evt) {

    lists_members[evt.response.dn_list] = evt.response.data;
    refreshListMembers(evt.response.dn_list);

  });

  rcmail
      .addEventListener('responseafterplugin.listes_remove_all', function(evt) {

        lists_members[evt.response.dn_list] = evt.response.data;
        refreshListMembers(evt.response.dn_list);

      });

  rcmail
      .addEventListener('init', function(evt) {
        // rcmail.register_command('hide_resource_in_roundcube', function(){
        // rcmail.hide_resource_in_roundcube(); }, true);
        // rcmail.register_command('show_resource_in_roundcube', function(){
        // rcmail.show_resource_in_roundcube(); }, true);
    	var tab;

        // Ajout des resources
    	if (rcmail.env.enable_mesressources) {
    		tab = $('<span>').attr('id', 'settingstabpluginmelanie2_resources')
	            .addClass('tablink melanie2 resources'), button = $('<a>')
	            .attr('title', rcmail.gettext('melanie2_moncompte.manageresources'))
	            .html(rcmail.gettext('melanie2_moncompte.resources')).appendTo(tab);
	        // add tab
	        rcmail.add_element(tab, 'tabs');
    	}
        
    	// Ajout des ressources mails
    	if (rcmail.env.enable_mesressources_mail) {
    		tab = $('<span>').attr('id', 'settingstabpluginmelanie2_resources_bal')
	            .addClass('listitem_melanie2 melanie2'), button = $('<a>')
	            .attr('href', rcmail.env.comm_path
	                + '&_action=plugin.melanie2_resources_bal')
	            .attr('title', rcmail
	                .gettext('melanie2_moncompte.manageresourcesbal')).html(rcmail
	                .gettext('melanie2_moncompte.resourcesbal')).appendTo(tab);
	        // add tab
	        rcmail.add_element(tab, 'tabs');
    	}
        
    	// Ajout des ressources calendar
    	if (rcmail.env.enable_mesressources_cal) {
    		tab = $('<span>')
	            .attr('id', 'settingstabpluginmelanie2_resources_agendas')
	            .addClass('listitem_melanie2 melanie2'), button = $('<a>')
	            .attr('href', rcmail.env.comm_path
	                + '&_action=plugin.melanie2_resources_agendas')
	            .attr('title', rcmail
	                .gettext('melanie2_moncompte.manageresourcesagendas'))
	            .html(rcmail.gettext('melanie2_moncompte.resourcesagendas'))
	            .appendTo(tab);
	        // add tab
	        rcmail.add_element(tab, 'tabs');
    	}
        
    	// Ajout des ressources contacts
    	if (rcmail.env.enable_mesressources_addr) {
    		tab = $('<span>')
	            .attr('id', 'settingstabpluginmelanie2_resources_contacts')
	            .addClass('listitem_melanie2 melanie2'), button = $('<a>')
	            .attr('href', rcmail.env.comm_path
	                + '&_action=plugin.melanie2_resources_contacts')
	            .attr('title', rcmail
	                .gettext('melanie2_moncompte.manageresourcescontacts'))
	            .html(rcmail.gettext('melanie2_moncompte.resourcescontacts'))
	            .appendTo(tab);
	        // add tab
	        rcmail.add_element(tab, 'tabs');
    	}
        

    	// Ajout des ressources tâches
    	if (rcmail.env.enable_mesressources_task) {
    		tab = $('<span>')
	            .attr('id', 'settingstabpluginmelanie2_resources_tasks')
	            .addClass('listitem_melanie2 melanie2'), button = $('<a>')
	            .attr('href', rcmail.env.comm_path
	                + '&_action=plugin.melanie2_resources_tasks')
	            .attr('title', rcmail
	                .gettext('melanie2_moncompte.manageresourcestaches'))
	            .html(rcmail.gettext('melanie2_moncompte.resourcestaches'))
	            .appendTo(tab);
	        // add tab
	        rcmail.add_element(tab, 'tabs');
    	}
        

        var p = rcmail;

        if (rcmail.gui_objects.melanie2_resources_elements_list) {
          rcmail.melanie2_resources_elements_list = new rcube_list_widget(rcmail.gui_objects.melanie2_resources_elements_list, {
            multiselect : false,
            draggable : false,
            keyboard : false
          });
          rcmail.melanie2_resources_elements_list
              .addEventListener('select', function(e) {
                p.melanie2_resources_element_select(e);
              });
          rcmail.melanie2_resources_elements_list.init();
          rcmail.melanie2_resources_elements_list.focus();
        }
        if (rcmail.env.action
            && rcmail.env.action.indexOf('plugin.melanie2_resources') != -1
            && rcmail.env.enable_mesressources) {
          $('#settingstabpluginmelanie2_resources_bal').show();
          $('#settingstabpluginmelanie2_resources_agendas').show();
          $('#settingstabpluginmelanie2_resources_contacts').show();
          $('#settingstabpluginmelanie2_resources_tasks').show();
          // Activation des commandes
          rcmail.enable_command('set_default_resource', true);
          rcmail.enable_command('hide_resource_in_roundcube', true);
          rcmail.enable_command('show_resource_in_roundcube', true);
          rcmail.enable_command('synchro_on_mobile', true);
          rcmail.enable_command('no_synchro_on_mobile', true);
          rcmail.enable_command('plugin.melanie2_moncompte_add_resource', true)
          // register commands
          rcmail
              .register_command('plugin.melanie2_moncompte_add_resource', function() {
                rcmail.add_resource()
              });
          rcmail
              .register_command('plugin.melanie2_moncompte_delete_resource', function() {
                rcmail.delete_resource()
              });

          // general datepicker settings
          var datepicker_settings = {
            // translate from fullcalendar format to datepicker format
            dateFormat : 'dd/mm/yy',
            firstDay : 1,
            changeMonth : false,
            showOtherMonths : true,
            selectOtherMonths : true
          };

          $('#event-export-startdate').datepicker(datepicker_settings);

          $('#event-export-range')
              .change(function(e) {
                var custom = $('option:selected', this).val() == 'custom', input = $('#event-export-startdate')
                input.parent()[(custom ? 'show' : 'hide')]();
                if (custom) input.select();
              });

          $('#submit_restore_cal')
              .click(function() {
                var form = rcmail.gui_objects.exportform;
                if (form) {
                  var start = 0, range = $('#event-export-range option:selected', this).val(), 
                      source = $('#event-export-calendar').val(), 
                      joursvg = $('#event-export-joursvg option:selected').val(),
                      token = $('#rcmExportForm input[name="_token"]').val();

                  if (range == 'custom')
                    start = date2unixtime(parse_datetime('00:00', $('#event-export-startdate')
                        .val()));
                  else if (range > 0) start = 'today -' + range + '^months';
                  
                  // MANTIS 3996: La sauvegarde de l'agenda ne fonctionne pas depuis "Mon compte dans le Courrielleur"
                  if (rcmail.env.courrielleur) {
                    window.location.href = rcmail.url('calendar/export_events', {
                      source : source,
                      start : start,
                      attachments : 0,
                      joursvg : joursvg,
                      _token: token
                    });
                  }
                  else {
                    rcmail.goto_url('calendar/export_events', {
                      source : source,
                      start : start,
                      attachments : 0,
                      joursvg : joursvg,
                      _token: token
                    });  
                  }                 
                }
              });

          $('#submit_restore_contacts').click(function() {
            var form = rcmail.gui_objects.exportform;
            if (form) {
              var source = $('#event-export-contacts').val(),
              joursvg = $('#event-export-contactsvg option:selected').val(),
              token = $('#rcmExportForm input[name="_token"]').val();
              
              // MANTIS 3996: La sauvegarde de l'agenda ne fonctionne pas depuis "Mon compte dans le Courrielleur"
              if (rcmail.env.courrielleur) {
                window.location.href = rcmail.url('addressbook/export', {
                  _source: source,
                  joursvg: joursvg,
                  _token: token
                });
              }
              else {
                rcmail.goto_url('addressbook/export', {
                  _source: source,
                  joursvg: joursvg,
                  _token: token
                });
              }              
            }
          });
        }
        else {
          $('#settingstabpluginmelanie2_resources_bal').hide();
          $('#settingstabpluginmelanie2_resources_agendas').hide();
          $('#settingstabpluginmelanie2_resources_contacts').hide();
          $('#settingstabpluginmelanie2_resources_tasks').hide();
        }

        // Moncompte
        if (rcmail.env.enable_moncompte) {
        	tab = $('<span>').attr('id', 'settingstabpluginmelanie2_moncompte')
	            .addClass('tablink melanie2 moncompte'), button = $('<a>')
	            .attr('href', rcmail.env.comm_path
	                + '&_action=plugin.melanie2_moncompte').attr('title', rcmail
	                .gettext('melanie2_moncompte.managemoncompte')).html(rcmail
	                .gettext('melanie2_moncompte.moncompte')).appendTo(tab);
	
	        // add tab
	        rcmail.add_element(tab, 'tabs');
        }
        

        if (rcmail.env.action.indexOf('plugin.melanie2_moncompte') != -1 && rcmail.env.enable_moncompte) {
          var p = rcmail;

          if (rcmail.gui_objects.melanie2_moncompte_options_list) {
            rcmail.options_list = new rcube_list_widget(rcmail.gui_objects.melanie2_moncompte_options_list, {
              multiselect : false,
              draggable : false,
              keyboard : true
            });
            rcmail.options_list.addEventListener('select', function(e) {
              p.melanie2_moncompte_option_select(e);
            });
            rcmail.options_list.init();
            rcmail.options_list.focus();
          }
        }

        // Statistiques
        if (rcmail.env.enable_messtatistiques) {
        	tab = $('<span>').attr('id', 'settingstabpluginmelanie2_statistics')
            .addClass('tablink melanie2 statistics'), button = $('<a>')
            .attr('title', rcmail
                .gettext('melanie2_moncompte.managestatistics')).html(rcmail
                .gettext('melanie2_moncompte.statistics')).appendTo(tab);

	        // add tab
	        rcmail.add_element(tab, 'tabs');
        }
        
        // Mes statistiques mobile
        if (rcmail.env.enable_messtatistiques_mobile) {
        	tab = $('<span>')
	            .attr('id', 'settingstabpluginmelanie2_statistics_mobile')
	            .addClass('listitem_melanie2 melanie2 statistics mobile'), button = $('<a>')
	            .attr('href', rcmail.env.comm_path
	                + '&_action=plugin.melanie2_statistics_mobile')
	            .attr('title', rcmail
	                .gettext('melanie2_moncompte.managestatisticsmobile'))
	            .html(rcmail.gettext('melanie2_moncompte.statisticsmobile'))
	            .appendTo(tab);
	        // add tab
	        rcmail.add_element(tab, 'tabs');
        }

        if (rcmail.env.action.indexOf('plugin.melanie2_statistics') != -1) {
          $('#settingstabpluginmelanie2_statistics_mobile').show();
        }
        else {
          $('#settingstabpluginmelanie2_statistics_mobile').hide();
        }

        /* dates du gestionnaire absence */

        if (rcmail.env.action.indexOf('plugin.melanie2_moncompte') != -1) {

          $.datepicker.setDefaults({
            dateFormat : "dd/mm/yy"
          });

          var shift_enddate = function(dateText) {
            var start_date = $.datepicker.parseDate("dd/mm/yy", dateText);
            var end_date = $.datepicker
                .parseDate("dd/mm/yy", $('#abs_date_fin').val());

            if (!end_date || start_date.getTime() > end_date.getTime()) {

              $('#abs_date_fin').val(dateText);
              $('#abs_msg_mel').val($('#abs_msg_mel').val()
                  .replace(/jusqu'au [\dj]{1,2}\/[\dm]{1,2}\/[\da]{2,4}/i, "jusqu'au "
                      + dateText));
              $('#abs_msg_inter').val($('#abs_msg_inter').val()
                  .replace(/jusqu'au [\dj]{1,2}\/[\dm]{1,2}\/[\da]{2,4}/i, "jusqu'au "
                      + dateText));

            }
          };

          var shift_startdate = function(dateText) {
            var end_date = $.datepicker.parseDate("dd/mm/yy", dateText);
            var start_date = $.datepicker
                .parseDate("dd/mm/yy", $('#abs_date_debut').val());

            if (!start_date || start_date.getTime() > end_date.getTime()) {

              $('#abs_date_debut').val(dateText);

            }
            $('#abs_msg_mel').val($('#abs_msg_mel').val()
                .replace(/jusqu'au [\dj]{1,2}\/[\dm]{1,2}\/[\da]{2,4}/i, "jusqu'au "
                    + dateText));
            $('#abs_msg_inter').val($('#abs_msg_inter').val()
                .replace(/jusqu'au [\dj]{1,2}\/[\dm]{1,2}\/[\da]{2,4}/i, "jusqu'au "
                    + dateText));

          };

          $('#abs_date_debut').datepicker()
              .datepicker('option', 'onSelect', shift_enddate)
              .change(function() {
                shift_enddate(this.value);
              });
          $('#abs_date_fin').datepicker()
              .datepicker('option', 'onSelect', shift_startdate)
              .change(function() {
                shift_startdate(this.value);
              });

          // gestion des listes - import CSV
          rcmail
              .register_command('upload-listes-csv', function() {
                var form = rcmail.gui_objects.uploadform;
                if (form && form.elements._listes_csv.value) {
                  var p = rcmail;
                  rcmail
                      .async_upload_form(form, 'plugin.listes_upload_csv', function(
                          e) {
                        p.set_busy(false, null, rcmail.file_upload_id);
                      });

                  // display upload indicator
                  rcmail.file_upload_id = rcmail.set_busy(true, 'uploading');
                }
              }, true);

          rcmail
              .addEventListener('plugin.import_listes_csv_success', function(p) {
                lists_members[p.dn_list] = p.data;
                refreshListMembers(p.dn_list);
                UI.show_uploadform();

                rcmail
                    .display_message(rcmail
                        .gettext('melanie2_moncompte.listes_import_success'), 'success');
                if (p.addr_error.length > 0) {
                  alert(rcmail.gettext('melanie2_moncompte.listes_addr_error')
                      + p.addr_error);
                }
              });
        }
        /* -------------------------------- */
      });
};

// Resources selection
rcube_webmail.prototype.melanie2_resources_element_select = function(element) {
  var id = element.get_single_selection();
  if (id != null) {
    this.load_shares_element_frame(id);
  }
};

// load filter frame
rcube_webmail.prototype.load_shares_element_frame = function(id) {
  var has_id = typeof (id) != 'undefined' && id != null;

  if (this.env.contentframe && window.frames
      && window.frames[this.env.contentframe]) {
    if (rcmail.env.resources_action != 'bal'
        && $('#rcmrow' + id).hasClass('personnal')) {
      rcmail.enable_command('plugin.melanie2_moncompte_delete_resource', true);
    }
    else {
      rcmail.enable_command('plugin.melanie2_moncompte_delete_resource', false);
    }

    target = window.frames[this.env.contentframe];
    var msgid = this.set_busy(true, 'loading');
    target.location.href = this.env.comm_path
        + '&_action=plugin.melanie2_resources_' + this.env.resources_action
        + '&_framed=1' + (has_id ? '&_id=' + id : '') + '&_unlock=' + msgid;
  }
};

// Filter selection
rcube_webmail.prototype.melanie2_moncompte_option_select = function(option) {
  var id = option.get_single_selection();
  if (id != null) {
    this.load_moncompte_frame(id);
  }
};

// load filter frame
rcube_webmail.prototype.load_moncompte_frame = function(id) {
  var has_id = typeof (id) != 'undefined' && id != null;

  if (this.env.contentframe && window.frames
      && window.frames[this.env.contentframe]) {
    target = window.frames[this.env.contentframe];
    var msgid = this.set_busy(true, 'loading');
    target.location.href = this.env.comm_path
        + '&_action=plugin.melanie2_moncompte&_framed=1'
        + (has_id ? '&_fid=' + id : '') + '&_unlock=' + msgid;
  }
  else if (rcmail.env.ismobile) {
    window.location.href = this.env.comm_path
        + '&_action=plugin.melanie2_moncompte' + (has_id ? '&_fid=' + id : '');
  }
};

rcube_webmail.prototype.hide_resource_in_roundcube = function(mbox, type) {
  if (mbox && type) {
    var lock = this
        .display_message(rcmail.gettext('melanie2_moncompte.wait'), 'loading');
    this.http_post('plugin.hide_resource_roundcube', {
      _mbox : mbox,
      _type : type
    }, lock);
  }
};

rcube_webmail.prototype.show_resource_in_roundcube = function(mbox, type) {
  if (mbox && type) {
    var lock = this
        .display_message(rcmail.gettext('melanie2_moncompte.wait'), 'loading');
    this.http_post('plugin.show_resource_roundcube', {
      _mbox : mbox,
      _type : type
    }, lock);
  }
};

rcube_webmail.prototype.synchro_on_mobile = function(mbox, type) {
  if (mbox && type) {
    var lock = this
        .display_message(rcmail.gettext('melanie2_moncompte.wait'), 'loading');
    this.http_post('plugin.synchro_on_mobile', {
      _mbox : mbox,
      _type : type
    }, lock);
  }
};

rcube_webmail.prototype.no_synchro_on_mobile = function(mbox, type) {
  if (mbox && type) {
    var lock = this
        .display_message(rcmail.gettext('melanie2_moncompte.wait'), 'loading');
    this.http_post('plugin.no_synchro_on_mobile', {
      _mbox : mbox,
      _type : type
    }, lock);
  }
};

rcube_webmail.prototype.add_resource = function() {
  if (rcmail.env.resources_action != 'bal') {
    var type = rcmail.env.resources_action;
    var name = prompt(rcmail.gettext('melanie2_moncompte.add_resource_prompt_'
        + type));
    if (name) {
      var lock = this
          .display_message(rcmail.gettext('melanie2_moncompte.wait'), 'loading');
      this.http_post('plugin.melanie2_add_resource', {
        _name : name,
        _type : type
      }, lock);

      rcmail
          .addEventListener('plugin.melanie2_add_resource_success', function(p) {
            setTimeout(function() {
              window.location.reload();
              var iframe = document
                  .getElementById('melanie2_resources_type_frame');
              iframe.src = 'skins/melanie2_larry/watermark.html';
            }, 750);
          });
    }
  }
};

rcube_webmail.prototype.delete_resource = function() {
  if (rcmail.env.resources_action != 'bal') {
    var type = rcmail.env.resources_action;
    var id = this.melanie2_resources_elements_list.get_single_selection();
    if (confirm(rcmail.gettext('melanie2_moncompte.delete_resource_confirm_'
        + type))) {
      var lock = this
          .display_message(rcmail.gettext('melanie2_moncompte.wait'), 'loading');
      this.http_post('plugin.melanie2_delete_resource', {
        _id : id,
        _type : type
      }, lock);

      rcmail
          .addEventListener('plugin.melanie2_delete_resource_success', function(
              p) {
            setTimeout(function() {
              window.location.reload();
              var iframe = document
                  .getElementById('melanie2_resources_type_frame');
              iframe.src = 'skins/melanie2_larry/watermark.html';
            }, 750);
          });
    }
  }
};

rcube_webmail.prototype.set_default_resource = function(mbox, type) {
  if (mbox && type) {
    var lock = this
        .display_message(rcmail.gettext('melanie2_moncompte.wait'), 'loading');
    this.http_post('plugin.set_default_resource', {
      _mbox : mbox,
      _type : type
    }, lock);
    $("#rcmfd_default").prop('disabled', true);
    if (rcmail.env.resource_synchro_mobile_not_set) {
      $("#rcmfd_synchronisation").prop('disabled', true);
      $("#rcmfd_synchronisation").prop('checked', 'checked');
    }
  }
};

// gestion des listes
function AddExternalMember() {
  var dn_list = $('#liste_listes option:selected').val();
  if (dn_list) {
    var newSmtp = prompt(rcmail
        .gettext('melanie2_moncompte.listes_memb_externe'), "");
    if (newSmtp) {
      if (isValidEmail(newSmtp)) {
        var lock = rcmail.display_message(rcmail
            .gettext('melanie2_moncompte.wait'), 'loading');
        var res = rcmail.http_post('plugin.listes_add_externe', {
          _dn_list : dn_list,
          _new_smtp : newSmtp,
          _current_username : $('#rcmmoncomptebalplist option:selected').val()
        }, lock);
      }
      else {
        alert(rcmail.gettext('melanie2_moncompte.listes_addr_nok')
            .replace('%%newSMTP%%', newSmtp));
      }
    }
  }
  else {
    alert(rcmail.gettext('melanie2_moncompte.listes_noselect'));
  }
}

function AnaisMemberCallback() {
  var dn_list = $('#liste_listes option:selected').val();
  if (dn_list) {
    var newSmtp = arguments[1];
    if (newSmtp) {
      if (isValidEmail(newSmtp)) {
        var lock = rcmail.display_message(rcmail
            .gettext('melanie2_moncompte.wait'), 'loading');
        var res = rcmail.http_post('plugin.listes_add_externe', {
          _dn_list : dn_list,
          _new_smtp : newSmtp,
          _current_username : $('#rcmmoncomptebalplist option:selected').val()
        }, lock);
      }
      else {
        alert(rcmail.gettext('melanie2_moncompte.listes_addr_nok')
            .replace('%%newSMTP%%', newSmtp));
      }
    }
  }
  else {
    alert(rcmail.gettext('melanie2_moncompte.listes_noselect'));
  }
}

function RemoveMember() {
  var dn_list = $('#liste_listes option:selected').val();
  if (dn_list) {
    var address = $('#idLboxMembers option:selected').val();
    if (address) {
      if (confirm(rcmail.gettext('melanie2_moncompte.listes_addr_del')
          .replace('%%addr_supp%%', address))) {
        var lock = rcmail.display_message(rcmail
            .gettext('melanie2_moncompte.wait'), 'loading');
        var res = rcmail.http_post('plugin.listes_remove', {
          _dn_list : dn_list,
          _address : address,
          _current_username : $('#rcmmoncomptebalplist option:selected').val()
        }, lock);
      }
    }
    else {
      alert(rcmail.gettext('melanie2_moncompte.listes_member_noselect'));
    }
  }
  else {
    alert(rcmail.gettext('melanie2_moncompte.listes_noselect'));
  }
}

function RemoveAllMembers() {
  var dn_list = $('#liste_listes option:selected').val();
  if (dn_list) {
    if (confirm(rcmail.gettext('melanie2_moncompte.listes_addr_del_all'))) {
      ;
      var lock = rcmail.display_message(rcmail
          .gettext('melanie2_moncompte.wait'), 'loading');
      var res = rcmail.http_post('plugin.listes_remove_all', {
        _dn_list : dn_list,
        _current_username : $('#rcmmoncomptebalplist option:selected').val()
      }, lock);
    }
  }
  else {
    alert(rcmail.gettext('melanie2_moncompte.listes_noselect'));
  }
}

function ExportMembers() {
  var dn_list = $('#liste_listes option:selected').val();
  if (dn_list) {
    rcmail.goto_url('settings/plugin.listes_export', {
      _dn_list : dn_list,
      _current_username : $('#rcmmoncomptebalplist option:selected').val()
    });
  }
  else {
    alert(rcmail.gettext('melanie2_moncompte.listes_noselect'));
  }
}

function isValidEmail(email) {
  // var reg = /^[a-zA-Z0-9'._-]+@[a-z0-9'._-]{2,}[.]([a-z]{2,3}|i2)$/
  var reg = /^[a-zA-Z0-9'._-]+@[a-zA-Z0-9'._-]+\.[a-zA-Z0-9]{2,}$/
  return (reg.exec(email) != null);
}

function refreshListMembers(dn_list) {
  var select = $('#idLboxMembers');
  select.html('');
  lists_members[dn_list].forEach(function(entry) {
    select.append('<option value="' + entry + '">' + entry + '</option>');
  });
  $('#members_count').html(lists_members[dn_list].length + ' '
      + rcmail.gettext('melanie2_moncompte.listes_membres'))
}
