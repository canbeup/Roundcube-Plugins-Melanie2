<?php
/**
 * Plugin Melanie2
 *
 * plugin melanie2 pour roundcube
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/**
 * Classes de gestion de partage des ressources d'agendas Mélanie2 dans Roundcube
 *
 * @author Thomas Payen <thomas.payen@i-carre.net> / PNE Messagerie MEDDE
 */
class M2calendar {
  /**
   *
   * @var LibMelanie\Api\Melanie2\User Utilisateur mélanie2
   */
  protected $user;
  /**
   *
   * @var LibMelanie\Api\Melanie2\Calendar Calendrier mélanie2
   */
  protected $calendar;
  /**
   *
   * @var rcmail The one and only instance
   */
  protected $rc;
  /**
   *
   * @var bool Groupe
   */
  protected $group = false;
  /**
   *
   * @var string Identifiant de la boite (uid)
   */
  protected $mbox;
  /**
   * Constructeur
   *
   * @param string $user
   * @param string $mbox
   */
  public function __construct($user = null, $mbox = null) {
    // Chargement de l'instance rcmail
    $this->rc = rcmail::get_instance();
    // User Melanie2
    $this->user = new LibMelanie\Api\Melanie2\User();
    if (! empty($user)) {
      $user = str_replace('_-P-_', '.', $user);
      if (strpos($user, '.-.') !== false) {
        $susername = explode('.-.', $user);
        $user = $susername[1];
      }
      $this->user->uid = $user;
    }
    try {
      // Calendar Melanie2
      if (isset($mbox)) {
        $mbox = str_replace('_-P-_', '.', $mbox);
        if (strpos($mbox, '.-.') !== false) {
          $susername = explode('.-.', $mbox);
          $mbox = $susername[1];
        }
        $this->mbox = $mbox;
        $this->calendar = new LibMelanie\Api\Melanie2\Calendar($this->user);
        $this->calendar->id = $mbox;
        if (! $this->calendar->load())
          $this->calendar = null;
      }
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      melanie2_logs::get_instance()->log(melanie2_logs::ERROR, "[Resources] M2calendar::__construct() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
  }
  /**
   * Récupération de l'acl
   *
   * @return array
   */
  public function getAcl() {
    if (! isset($this->calendar) || $this->calendar->owner != $this->user->uid)
      return false;
    try {
      $_share = new LibMelanie\Api\Melanie2\Share($this->calendar);
      $_share->type = $this->group === true ? LibMelanie\Api\Melanie2\Share::TYPE_GROUP : LibMelanie\Api\Melanie2\Share::TYPE_USER;
      $acl = array();
      foreach ($_share->getList() as $share) {
        $acl[$share->name] = array();
        if ($share->asRight(LibMelanie\Config\ConfigMelanie::WRITE)) {
          $acl[$share->name][] = "w";
        }
        if ($share->asRight(LibMelanie\Config\ConfigMelanie::READ)) {
          $acl[$share->name][] = "r";
        }
        if ($share->asRight(LibMelanie\Config\ConfigMelanie::FREEBUSY)) {
          $acl[$share->name][] = "l";
        }
        if ($share->asRight(LibMelanie\Config\ConfigMelanie::DELETE)) {
          $acl[$share->name][] = "d";
        }
        if ($share->asRight(LibMelanie\Config\ConfigMelanie::PRIV)) {
          $acl[$share->name][] = "p";
        }
      }
      return $acl;
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      melanie2_logs::get_instance()->log(melanie2_logs::ERROR, "[Resources] M2calendar::getAcl() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
  }
  /**
   * Position l'acl pour l'utilisateur
   *
   * @param string $user
   * @param array $rights
   * @return boolean
   */
  public function setAcl($user, $rights) {
    if (! isset($this->calendar) && ! $this->createCalendar()) {
      return false;
    }
    if ($this->calendar->owner != $this->user->uid) {
      return false;
    }
    try {
      // MANTIS 3939: Partage d'un carnet d'adresses : il est possible de saisir l'uid d'une bali dans "Partager à un groupe"
      // Vérifier que les données partagées existent dans l'annuaire
      if ($this->group === true) {
        // Valide que le droit concerne bien un groupe
        if (strpos($user, "mineqRDN=") !== 0 || strpos($user, "ou=organisation,dc=equipement,dc=gouv,dc=fr") === false) {
          return false;
        }
        // MANTIS 4093: Problème de partage à une liste
        $user = urldecode($user);
      }
      else {
        // Valide que le droit concerne bien un utilisateur
        $infos = melanie2::get_user_infos($user);
        if (! isset($infos)) {
          return false;
        }
      }
      $share = new LibMelanie\Api\Melanie2\Share($this->calendar);
      $share->type = $this->group === true ? LibMelanie\Api\Melanie2\Share::TYPE_GROUP : LibMelanie\Api\Melanie2\Share::TYPE_USER;
      $share->name = $user;
      $share->acl = 0;
      // Compléter automatiquement les droits
      if (in_array('w', $rights)) {
        // Ecriture + Lecture + Freebusy
        $share->acl |= LibMelanie\Api\Melanie2\Share::ACL_WRITE
        | LibMelanie\Api\Melanie2\Share::ACL_DELETE
        | LibMelanie\Api\Melanie2\Share::ACL_READ
        | LibMelanie\Api\Melanie2\Share::ACL_FREEBUSY;
      }
      else if (in_array('r', $rights)) {
        // Lecture + Freebusy
        $share->acl |= LibMelanie\Api\Melanie2\Share::ACL_READ
        | LibMelanie\Api\Melanie2\Share::ACL_FREEBUSY;
      }
      else if (in_array('l', $rights)) {
        // Freebusy
        $share->acl |= LibMelanie\Api\Melanie2\Share::ACL_FREEBUSY;
      }
      if (in_array('p', $rights)) {
        // Droit privé
        $share->acl |= LibMelanie\Api\Melanie2\Share::ACL_PRIVATE;
      }

      if ($share->save() === null) {
        return false;
      }
      return true;
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      melanie2_logs::get_instance()->log(melanie2_logs::ERROR, "[Resources] M2calendar::setAcl() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
  }
  /**
   * Suppression de l'acl pour l'utilisateur
   *
   * @param string $user
   * @return boolean
   */
  public function deleteAcl($user) {
    if (! isset($this->calendar) || $this->calendar->owner != $this->user->uid)
      return false;
    try {
      $share = new LibMelanie\Api\Melanie2\Share($this->calendar);
      $share->type = $this->group === true ? LibMelanie\Api\Melanie2\Share::TYPE_GROUP : LibMelanie\Api\Melanie2\Share::TYPE_USER;
      $share->name = $user;
      return $share->delete();
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      melanie2_logs::get_instance()->log(melanie2_logs::ERROR, "[Resources] M2calendar::deleteAcl() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
  }

  /**
   * Méthode pour la création du calendrier
   *
   * @param string $name [optionnel]
   * @return boolean
   */
  public function createCalendar($name = null) {
    try {
      $this->calendar = new LibMelanie\Api\Melanie2\Calendar($this->user);
      if (! isset($name)) {
        $infos = melanie2::get_user_infos($this->user->uid);
        $this->calendar->name = $infos['cn'][0];
      }
      else {
        $this->calendar->name = $name;
      }
      $this->calendar->id = $this->mbox ?  : $this->user->uid;
      $this->calendar->owner = $this->user->uid;
      if (! is_null($this->calendar->save())) {
        return $this->calendar->load();
      }
      else {
        return false;
      }
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      melanie2_logs::get_instance()->log(melanie2_logs::ERROR, "[Resources] M2calendar::createCalendar() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
    return false;
  }

  /**
   * Suppression du calendar
   */
  public function deleteCalendar() {
    if (isset($this->calendar) && isset($this->user) && $this->calendar->owner == $this->user->uid && $this->calendar->id != $this->user->uid) {
      // Parcour les évènements pour les supprimer
      $events = $this->calendar->getAllEvents();
      foreach ($events as $event) {
        $event->delete();
      }
      // Supprime le calendrier
      return $this->calendar->delete();
    }
    return false;
  }

  /**
   * Affiche la liste des éléments
   *
   * @param array $attrib
   * @return string
   */
  public function resources_elements_list($attrib) {
    // add id to message list table if not specified
    if (! strlen($attrib['id']))
      $attrib['id'] = 'rcmresourceselementslist';

    try {
      $result = array();

      // Récupération des préférences de l'utilisateur
      $hidden_calendars = $this->rc->config->get('hidden_calendars', array());
      // Parcour la liste des agendas
      $calendars = $this->user->getSharedCalendars();
      $calendar_owner = array();
      $calendars_owner = array();
      $calendars_shared = array();
      foreach ($calendars as $calendar) {
        if ($calendar->owner == $this->user->uid) {
          if ($calendar->id == $this->user->uid)
            $calendar_owner[$calendar->id] = $calendar->name;
          else
            $calendars_owner[$calendar->id] = $calendar->name;
        }
        else {
          $calendars_shared[$calendar->id] = "(" . $calendar->owner . ") " . $calendar->name;
        }

      }
      // MANTIS 0003913: Création automatique des objets dans Mes ressources
      if (count($calendar_owner) == 0 && $this->createCalendar()) {
        $calendar_owner[$this->calendar->id] = $this->calendar->name;
      }
      // Objet HTML
      $table = new html_table();
      $checkbox_subscribe = new html_checkbox(array('name' => '_show_resource_rc[]','title' => $this->rc->gettext('changesubscription'),'onclick' => "rcmail.command(this.checked ? 'show_resource_in_roundcube' : 'hide_resource_in_roundcube', this.value, 'calendar')"));

      // Calendrier principal
      foreach ($calendar_owner as $id => $name) {
        $table->add_row(array('id' => 'rcmrow' . str_replace(".", "_-P-_", $id),'class' => 'calendar','foldername' => str_replace(".", "_-P-_", $id)));

        $table->add('name', $name);
        $table->add('subscribed', $checkbox_subscribe->show((! isset($hidden_calendars[$id]) ? $id : ''), array('value' => $id)));
      }
      // Calendriers de l'utilisateurs
      asort($calendars_owner);
      foreach ($calendars_owner as $id => $name) {
        $table->add_row(array('id' => 'rcmrow' . str_replace(".", "_-P-_", $id),'class' => 'calendar personnal','foldername' => str_replace(".", "_-P-_", $id)));

        $table->add('name', $name);
        $table->add('subscribed', $checkbox_subscribe->show((! isset($hidden_calendars[$id]) ? $id : ''), array('value' => $id)));
      }
      // Calendriers partagés
      asort($calendars_shared);
      foreach ($calendars_shared as $id => $name) {
        $table->add_row(array('id' => 'rcmrow' . str_replace(".", "_-P-_", $id),'class' => 'calendar','foldername' => str_replace(".", "_-P-_", $id)));

        $table->add('name', $name);
        $table->add('subscribed', $checkbox_subscribe->show((! isset($hidden_calendars[$id]) ? $id : ''), array('value' => $id)));
      }
      // set client env
      $this->rc->output->add_gui_object('melanie2_resources_elements_list', $attrib['id']);

      return $table->show($attrib);
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      melanie2_logs::get_instance()->log(melanie2_logs::ERROR, "[Resources] M2calendar::createCalendar() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
  }

  /**
   * Handler to render ACL form for a calendar folder
   */
  public function acl_template() {
    $this->rc->output->add_handler('folderacl', array($this,'acl_form'));
    $this->rc->output->send('melanie2_moncompte.acl_frame');
  }

  /**
   * Handler for ACL form template object
   */
  public function acl_form() {
    $calid = get_input_value('_id', RCUBE_INPUT_GPC);
    $options = array('type' => 'm2calendar','name' => $calid,'attributes' => array(0 => '\\HasNoChildren'),'namespace' => 'personal','special' => false,'rights' => array(0 => 'l',1 => 'r',2 => 's',3 => 'w',4 => 'i',5 => 'p',6 => 'k',7 => 'x',8 => 't',9 => 'e',10 => 'c',11 => 'd',12 => 'a'),'norename' => false,'noselect' => false,'protected' => true);

    $form = array();

    // Allow plugins to modify the form content (e.g. with ACL form)
    $plugin = $this->rc->plugins->exec_hook('acl_form_melanie2', array('form' => $form,'options' => $options,'name' => $cal->name));

    if (! $plugin['form']['sharing']['content'])
      $plugin['form']['sharing']['content'] = html::div('hint', $this->rc->gettext('aclnorights'));

    return $plugin['form']['sharing']['content'];
  }

  /**
   * Affichage des données dans la frame
   *
   * @param array $attrib
   * @return string
   */
  public function acl_frame($attrib) {
    $id = get_input_value('_id', RCUBE_INPUT_GPC);
    if (! $attrib['id'])
      $attrib['id'] = 'rcmusersaclframe';

    $attrib['name'] = $attrib['id'];
    $attrib['src'] = $this->rc->url(array('_action' => 'plugin.melanie2_calendar_acl','id' => $id,'framed' => 1));
    $attrib['width'] = '100%';
    $attrib['height'] = 275;
    $attrib['border'] = 0;
    $attrib['border'] = 'border:0';

    return $this->rc->output->frame($attrib);
  }

  public function restore_cal($attrib) {
    /*
     * $html = '';
     * $html .= html::span(array(), $this->rc->gettext('cal_periode_label', 'melanie2_moncompte'));
     * $radio = new html_radiobutton(array('name' => 'all_events', 'value' => '1'));
     * $html .= html::div(array(), $radio->show('1', array('id' => 'radio_cal_all'))
     * . html::label(array('for' => 'radio_cal_all'), $this->rc->gettext('radio_cal_all_label', 'melanie2_moncompte')));
     * $input_start_date = new html_inputfield(array('id' => 'cal_start_date', 'name' => 'cal_start_date' ));
     *
     * $input_end_date = new html_inputfield(array('id' => 'cal_end_date', 'name' => 'cal_end_date' ));
     * $html .= html::div(array(), $radio->show('0', array('id' => 'radio_cal_some'))
     * . html::label(array('for' => 'radio_cal_some'), $this->rc->gettext('radio_cal_some_label', 'melanie2_moncompte')
     * . $input_start_date->show()
     * . html::div('cal_end_date_div', html::span(array(), $this->rc->gettext('radio_cal_end_label', 'melanie2_moncompte'))
     * . $input_end_date->show()
     * )));
     * $select = new html_select(array('name' => 'joursvg'));
     * $select->add($this->rc->gettext('cal_j-1', 'melanie2_moncompte'), 'sql-1');
     * $select->add($this->rc->gettext('cal_j-2', 'melanie2_moncompte'), 'sql-2');
     * $select->add('', 'sql-n');
     * $html .= html::br();
     * $html .= html::span(array(), $this->rc->gettext('cal_bdd_label', 'melanie2_moncompte'));
     * $html .= html::div(array(), $select->show());
     * return $html;
     */
    if (! $attrib['id'])
      $attrib['id'] = 'rcmExportForm';

    $id = get_input_value('_id', RCUBE_INPUT_GPC);
    $hidden = new html_hiddenfield(array('name' => 'calendar','id' => 'event-export-calendar','value' => $id));
    $html .= $hidden->show();

    $select = new html_select(array('name' => 'range','id' => 'event-export-range'));
    $select->add(array($this->rc->gettext('all', 'calendar'),$this->rc->gettext('onemonthback', 'calendar'),$this->rc->gettext(array('name' => 'nmonthsback','vars' => array('nr' => 2)), 'calendar'),$this->rc->gettext(array('name' => 'nmonthsback','vars' => array('nr' => 3)), 'calendar'),$this->rc->gettext(array('name' => 'nmonthsback','vars' => array('nr' => 6)), 'calendar'),$this->rc->gettext(array('name' => 'nmonthsback','vars' => array('nr' => 12)), 'calendar'),$this->rc->gettext('customdate', 'calendar')), array(0,'1','2','3','6','12','custom'));

    $startdate = new html_inputfield(array('name' => 'start','size' => 11,'id' => 'event-export-startdate'));
    $html .= html::br();

    $html .= html::div('form-section', html::label('event-export-range', $this->rc->gettext('exportrange', 'calendar')) . $select->show(0) . html::span(array('style' => 'display:none'), $startdate->show()));

    $select = new html_select(array('name' => 'joursvg','id' => 'event-export-joursvg'));
    $select->add($this->rc->gettext('cal_j-1', 'melanie2_moncompte'), 'horde_1');
    $select->add($this->rc->gettext('cal_j-2', 'melanie2_moncompte'), 'horde_2');
    $select->add('', 'horde_n');
    $html .= html::br();
    $html .= html::div('form-section', html::label('event-export-bdd', $this->rc->gettext('cal_bdd_label', 'melanie2_moncompte')) . $select->show());

    $this->rc->output->add_gui_object('exportform', $attrib['id']);

    return html::tag('form', array('action' => $this->rc->url(array('task' => 'calendar','action' => 'export_events')),'method' => "post",'id' => $attrib['id']), $html);

  }

}

/**
 * Classes de gestion des ressources d'agendas Mélanie2 dans Roundcube
 *
 * @author Thomas Payen <thomas.payen@i-carre.net> / PNE Messagerie MEDDE
 */
class M2calendargroup extends M2calendar {
  /**
   * Constructeur
   *
   * @param string $user
   * @param string $mbox
   */
  public function __construct($user = null, $mbox = null) {
    $this->group = true;
    parent::__construct($user, $mbox);
  }

  /**
   * Handler for ACL form template object
   */
  public function acl_form() {
    $calid = get_input_value('_id', RCUBE_INPUT_GPC);
    $options = array('type' => 'm2calendargroup','name' => $calid,'attributes' => array(0 => '\\HasNoChildren'),'namespace' => 'personal','special' => false,'rights' => array(0 => 'l',1 => 'r',2 => 's',3 => 'w',4 => 'i',5 => 'p',6 => 'k',7 => 'x',8 => 't',9 => 'e',10 => 'c',11 => 'd',12 => 'a'),'norename' => false,'noselect' => false,'protected' => true);

    $form = array();

    // Allow plugins to modify the form content (e.g. with ACL form)
    $plugin = $this->rc->plugins->exec_hook('acl_form_melanie2', array('form' => $form,'options' => $options,'name' => $cal->name));

    if (! $plugin['form']['sharing']['content'])
      $plugin['form']['sharing']['content'] = html::div('hint', $this->rc->gettext('aclnorights'));

    return $plugin['form']['sharing']['content'];
  }

  /**
   * Affichage des données dans la frame
   *
   * @param array $attrib
   * @return string
   */
  public function acl_frame($attrib) {
    $id = get_input_value('_id', RCUBE_INPUT_GPC);
    if (! $attrib['id'])
      $attrib['id'] = 'rcmusersaclframe';

    $attrib['name'] = $attrib['id'];
    $attrib['src'] = $this->rc->url(array('_action' => 'plugin.melanie2_calendar_acl_group','id' => $id,'framed' => 1));
    $attrib['width'] = '100%';
    $attrib['height'] = 275;
    $attrib['border'] = 0;
    $attrib['border'] = 'border:0';

    return $this->rc->output->frame($attrib);
  }
}
