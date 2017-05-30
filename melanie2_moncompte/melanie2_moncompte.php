<?php
use LibMelanie\Api\Melanie2\Taskslist;

/**
 * Plugin Melanie2 Moncompte
 * plugin melanie2_moncompte pour roundcube
 * Permet de gérer ses informations de compte Mélanie2
 * D'afficher et partager ses ressources Mélanie2 (boites mail, agendas, contacts, tâches)
 * D'afficher les statistiques de synchronisation
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
// Configuration du nom de l'application pour l'ORM
if (! defined('CONFIGURATION_APP_LIBM2')) {
  define('CONFIGURATION_APP_LIBM2', 'roundcube');
}
// Chargement de la librairie Melanie2
@include_once 'includes/libm2.php';

// Chargement des classes externes
include_once 'ressources/calendar.php';
include_once 'ressources/contacts.php';
include_once 'ressources/mailbox.php';
include_once 'ressources/tasks.php';
include_once 'moncompte/moncompte.php';
include_once 'statistiques/mobile.php';

class melanie2_moncompte extends rcube_plugin {
  /**
   *
   * @var string
   */
  public $task = '.*';
  /**
   *
   * @var rcmail
   */
  private $rc;
  /**
   * Stocke le _account passé en get
   *
   * @var string
   */
  private $get_account;
  /**
   * Identifiant de la bal
   *
   * @var string
   */
  private $user_bal;
  /**
   * Username complet bal@host
   *
   * @var string
   */
  private $user_name;
  /**
   * Host de l'utilisateur
   *
   * @var string
   */
  private $user_host;
  /**
   * Objet de partage, en .
   * -. si balp
   *
   * @var string
   */
  private $user_objet_share;

  /**
   * Initialisation du plugin
   *
   * @see rcube_plugin::init()
   */
  public function init() {
    $this->rc = rcmail::get_instance();

    // Restauration calendrier
    $sql_n = rcube_utils::get_input_value('joursvg', rcube_utils::INPUT_GET);

    if (isset($sql_n)) {
      LibMelanie\Config\ConfigSQL::setCurrentBackend($sql_n);
    }

    // Chargement de l'ui
    $this->init_ui();
  }

  /**
   * Initializes plugin's UI (localization, js script)
   */
  private function init_ui() {
    if ($this->ui_initialized) {
      return;
    }
    // Chargement de la conf
    $this->load_config();

    // load localization
    $this->add_texts('localization/', true);
    $this->include_script('moncompte.js');

    if ($this->rc->config->get('enable_moncompte', true)) {
        // Ajout des boutons
        if ($this->rc->config->get('ismobile', false)) {
            $this->include_stylesheet('skins/melanie2_larry_mobile/melanie2.css');
            $this->api->add_content(
                    html::tag('a',
                            array(
                                            "id" => "rcmbtn210",
                                            "class" => "button-melanie2_moncompte ui-link ui-btn ui-corner-all ui-icon-briefcase ui-btn-icon-left",
                                            "data-ajax" => "false",
                                            "href" => "./?_task=settings&_action=plugin.melanie2_moncompte",
                                            "style" => "position: relative;"),
                            html::tag('span', array("class" => "button-inner"),
                                    $this->gettext('moncompte'))),
                    'taskbar_mobile');
                                    
                                    $this->add_hook('settings_actions', array($this, 'settings_actions'));
        }
        else {
            $this->include_stylesheet($this->local_skin_path() . '/melanie2.css');
            $this->api->add_content(
                    html::tag('a',
                            array(
                                            "id" => "rcmbtn210",
                                            "class" => "button-melanie2_moncompte",
                                            "href" => "./?_task=settings&_action=plugin.melanie2_moncompte",
                                            "style" => "position: relative;"),
                            html::tag('span', array("class" => "button-inner"),
                                    $this->gettext('moncompte'))),
                    'taskbar');
        }
    }
    else {
        if ($this->rc->config->get('ismobile', false)) {
            $this->include_stylesheet('skins/melanie2_larry_mobile/melanie2.css');
        }
        else {
            $this->include_stylesheet($this->local_skin_path() . '/melanie2.css');
        }
    }

    if ($this->rc->task == 'settings') {
      // bloquer les refresh
      $this->rc->output->set_env('refresh_interval', 0);
      
      // Ajouter la configuration dans l'environnement
      $this->rc->output->set_env('enable_moncompte',            $this->rc->config->get('enable_moncompte', true));
      $this->rc->output->set_env('enable_mesressources',        $this->rc->config->get('enable_mesressources', true));
      $this->rc->output->set_env('enable_mesressources_mail',   $this->rc->config->get('enable_mesressources_mail', true));
      $this->rc->output->set_env('enable_mesressources_cal',    $this->rc->config->get('enable_mesressources_cal', true));
      $this->rc->output->set_env('enable_mesressources_addr',   $this->rc->config->get('enable_mesressources_addr', true));
      $this->rc->output->set_env('enable_mesressources_task',   $this->rc->config->get('enable_mesressources_task', true));
      $this->rc->output->set_env('enable_messtatistiques',      $this->rc->config->get('enable_messtatistiques', true));
      $this->rc->output->set_env('enable_messtatistiques_mobile', $this->rc->config->get('enable_messtatistiques_mobile', true));

      // http post actions
      $this->register_action('plugin.hide_resource_roundcube', array($this,'hide_resource_roundcube'));
      $this->register_action('plugin.show_resource_roundcube', array($this,'show_resource_roundcube'));
      $this->register_action('plugin.synchro_on_mobile', array($this,'synchro_on_mobile'));
      $this->register_action('plugin.no_synchro_on_mobile', array($this,'no_synchro_on_mobile'));
      $this->register_action('plugin.set_default_resource', array($this,'set_default_resource'));

      // register actions
      $this->register_action('plugin.melanie2_resources_bal', array($this,'resources_bal_init'));
      $this->register_action('plugin.melanie2_resources_agendas', array($this,'resources_agendas_init'));
      $this->register_action('plugin.melanie2_resources_contacts', array($this,'resources_contacts_init'));
      $this->register_action('plugin.melanie2_resources_tasks', array($this,'resources_tasks_init'));

      $this->register_action('plugin.melanie2_moncompte', array(new Moncompte($this),'init'));

      $this->register_action('plugin.melanie2_statistics_mobile', array(new Mobile_Stats($this),'init'));
      $this->register_action('plugin.statistics.zpush_command', array(new Mobile_Stats($this),"zpush_command"));

      $this->register_action('plugin.melanie2_mailbox_acl', array(new M2mailbox($this->rc->user->get_username()),'acl_template'));

      $this->register_action('plugin.melanie2_calendar_acl', array(new M2calendar($this->get_user_bal()),'acl_template'));
      $this->register_action('plugin.melanie2_calendar_acl_group', array(new M2calendargroup($this->get_user_bal()),'acl_template'));

      $this->register_action('plugin.melanie2_contacts_acl', array(new M2contacts($this->get_user_bal()),'acl_template'));
      $this->register_action('plugin.melanie2_contacts_acl_group', array(new M2contactsgroup($this->get_user_bal()),'acl_template'));

      $this->register_action('plugin.melanie2_tasks_acl', array(new M2tasks($this->get_user_bal()),'acl_template'));
      $this->register_action('plugin.melanie2_tasks_acl_group', array(new M2tasksgroup($this->get_user_bal()),'acl_template'));

      // add / delete ressources
      $this->register_action('plugin.melanie2_add_resource', array($this,'add_resource'));
      $this->register_action('plugin.melanie2_delete_resource', array($this,'delete_resource'));

      // Gestion des listes
      $this->register_action('plugin.listes_membres', array(new Moncompte($this),'readListeMembers'));
      $this->register_action('plugin.listes_add_externe', array(new Moncompte($this),'addExterneMember'));
      $this->register_action('plugin.listes_remove', array(new Moncompte($this),'RemoveMember'));
      $this->register_action('plugin.listes_remove_all', array(new Moncompte($this),'RemoveAllMembers'));
      $this->register_action('plugin.listes_export', array(new Moncompte($this),'ExportMembers'));
      $this->register_action('plugin.listes_upload_csv', array(new Moncompte($this),'uploadCSVMembers'));
    }

    $this->ui_initialized = true;
  }

  /**
   * Adds Mon compte section in Settings
   */
  function settings_actions($args)
  {
      $args['actions'][] = array(
              'action' => 'plugin.melanie2_moncompte',
              'class'  => 'melanie2 moncompte',
              'label'  => 'moncompte',
              'domain' => 'melanie2_moncompte',
              'title'  => 'managemoncompte',
      );

    return $args;
  }

  /**
   * ****** ACTIONS ******
   */
  /**
   * Initialisation du menu ressources pour les Bal
   * Affichage du template et gestion de la sélection
   */
  public function resources_bal_init() {
    $id = get_input_value('_id', RCUBE_INPUT_GPC);
    if (isset($id)) {
      $id = str_replace('_-P-_', '.', $id);
      if (strpos($id, '.-.') !== false) {
        $susername = explode('.-.', $id);
        $id = $susername[1];
      }
      // Récupération des informations sur l'utilisateur courant
      $infos = melanie2::get_user_infos($id);
      $acl = $this->rc->get_user_name() == $id || in_array($this->rc->get_user_name() . ':G', $infos['mineqmelpartages']) ? $this->gettext('gestionnaire') : (in_array($this->rc->get_user_name() . ':E', $infos['mineqmelpartages']) ? $this->gettext('write') : (in_array($this->rc->get_user_name() . ':C', $infos['mineqmelpartages']) ? $this->gettext('send') : $this->gettext('read_only')));
      $shared = $this->rc->get_user_name() == $id || (in_array($this->rc->get_user_name() . ':G', $infos['mineqmelpartages']) && isset($infos['mineqtypeentree']) && $infos['mineqtypeentree'][0] != 'BALI' && $infos['mineqtypeentree'][0] != 'BALA');
      $this->rc->output->set_env("resource_id", $id);
      $this->rc->output->set_env("resource_name", $infos['cn'][0]);
      $this->rc->output->set_env("resource_shared", ! $shared);
      $this->rc->output->set_env("resource_acl", $acl);
      if ($shared) {
        $this->rc->output->add_handler('usersaclframe', array(new M2mailbox($this->rc->user->get_username()),'acl_frame'));
        $this->rc->output->add_handler('restore_bal', array(new M2mailbox($this->rc->user->get_username()),'restore_bal'));
        $this->rc->output->add_handler('restore_bal_expl', array($this ,'restore_bal_expl'));

        if (isset($_POST['nbheures'])) {
          M2mailbox::unexpunge();
        }

      }

      $this->rc->output->send('melanie2_moncompte.m2_resource_mailbox');
    }
    else {
      // register UI objects
      $this->rc->output->add_handlers(array('melanie2_resources_elements_list' => array(new M2mailbox($this->rc->user->get_username()),'resources_elements_list'),'melanie2_resources_type_frame' => array($this,'melanie2_resources_type_frame')));
      $this->rc->output->set_env("resources_action", "bal");
      $this->rc->output->include_script('list.js');
      $this->rc->output->set_pagetitle($this->gettext('resources'));
      $this->rc->output->send('melanie2_moncompte.resources_elements');
    }
  }

  public function restore_bal_expl() {
    return $this->gettext('restore_bal_expl');
  }

  /**
   * Initialisation du menu ressources pour les Agendas
   * Affichage du template et gestion de la sélection
   */
  public function resources_agendas_init() {
    try {
      $id = get_input_value('_id', RCUBE_INPUT_GPC);
      if (isset($id)) {
        $id = str_replace('_-P-_', '.', $id);
        // Instancie les objets Mélanie2
        $user = new LibMelanie\Api\Melanie2\User();
        $user->uid = $this->get_user_bal();
        $calendar = new LibMelanie\Api\Melanie2\Calendar($user);
        $calendar->id = $id;
        if ($calendar->load()) {
          // TODO : chargement des preferences en une seule requête (getList)
          $synchro_mobile = array();
          $prefs = new LibMelanie\Api\Melanie2\UserPrefs($user);
          $prefs->name = array('synchro_mobile');
          $prefs->scope = LibMelanie\Config\ConfigMelanie::CALENDAR_PREF_SCOPE;
          foreach ($prefs->getList() as $pref) {
            $value = $pref->value;
            ${$pref->name} = unserialize($value);
            if (${$pref->name} === false) {
              ${$pref->name} = array();
            }
          }

          $default_calendar = $user->getDefaultCalendar();
          $acl = ($calendar->asRight(LibMelanie\Config\ConfigMelanie::WRITE) ? $this->gettext('read_write') : ($calendar->asRight(LibMelanie\Config\ConfigMelanie::READ) ? $this->gettext('read_only') : ($calendar->asRight(LibMelanie\Config\ConfigMelanie::FREEBUSY) ? $this->gettext('show') : $this->gettext('none'))));
          $shared = $user->uid != $calendar->owner;
          $is_default = $default_calendar->id == $calendar->id;
          $this->rc->output->set_env("resource_id", $id);
          $this->rc->output->set_env("resource_name", $shared ? "(" . $calendar->owner . ") " . $calendar->name : $calendar->name);
          $this->rc->output->set_env("resource_shared", $shared);
          $this->rc->output->set_env("resource_acl", $acl);
          $this->rc->output->set_env("resource_owner", $calendar->owner);
          $this->rc->output->set_env("resource_default", $default_calendar->id == $calendar->id);
          if (count($synchro_mobile) == 0) {
            // Si on n'a pas de ressource définie, utilise celle par défaut
            $this->rc->output->set_env("resource_synchro_mobile", $is_default);
            // C'est la ressource par defaut
            if ($is_default)
              $this->rc->output->set_env("resource_synchro_mobile_default", true);
              // Sinon on precise qu'on a aucune ressoure de définie
            else
              $this->rc->output->set_env("resource_synchro_mobile_not_set", true);
          }
          else {
            if (count($synchro_mobile) == 1 && in_array($id, $synchro_mobile) && $is_default) {
              // Si la seule ressource définie est celle par défaut
              $this->rc->output->set_env("resource_synchro_mobile_default", true);
            }
            $this->rc->output->set_env("resource_synchro_mobile", in_array($id, $synchro_mobile));
          }
          if (! $shared) {
            $this->rc->output->add_handler('usersaclframe', array(new M2calendar($this->get_user_bal()),'acl_frame'));
            $this->rc->output->add_handler('groupsaclframe', array(new M2calendargroup($this->get_user_bal()),'acl_frame'));
          }

          $this->rc->output->add_handler('restore_cal', array(new M2calendar($this->get_user_bal()),'restore_cal'));

          $this->rc->output->send('melanie2_moncompte.m2_resource_calendar');
        }
        else {
          $this->rc->output->send();
        }
      }
      else {
        // register UI objects
        $this->rc->output->add_handlers(array('melanie2_resources_elements_list' => array(new M2calendar($this->get_user_bal()),'resources_elements_list'),'melanie2_resources_type_frame' => array($this,'melanie2_resources_type_frame')));
        $this->rc->output->set_env("resources_action", "agendas");
        $this->rc->output->include_script('list.js');
        $this->rc->output->set_pagetitle($this->gettext('resources'));
        $this->rc->output->send('melanie2_moncompte.resources_elements');
      }
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      melanie2_logs::get_instance()->log(melanie2_logs::ERROR, "[Resources] melanie2_moncompte::resources_agendas_init() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
  }
  /**
   * Initialisation du menu ressources pour les Contacts
   * Affichage du template et gestion de la sélection
   */
  public function resources_contacts_init() {
    try {
      $id = get_input_value('_id', RCUBE_INPUT_GPC);
      if (isset($id)) {
        $id = str_replace('_-P-_', '.', $id);
        // Instancie les objets Mélanie2
        $user = new LibMelanie\Api\Melanie2\User();
        $user->uid = $this->get_user_bal();
        $addressbook = new LibMelanie\Api\Melanie2\Addressbook($user);
        $addressbook->id = $id;
        if ($addressbook->load()) {
          // TODO : chargement des preferences en une seule requête (getList)
          $synchro_mobile = array();
          $prefs = new LibMelanie\Api\Melanie2\UserPrefs($user);
          $prefs->name = array('synchro_mobile');
          $prefs->scope = LibMelanie\Config\ConfigMelanie::ADDRESSBOOK_PREF_SCOPE;
          foreach ($prefs->getList() as $pref) {
            $value = $pref->value;
            ${$pref->name} = unserialize($value);
            if (${$pref->name} === false) {
              ${$pref->name} = array();
            }
          }

          $default_addressbook = $user->getDefaultAddressbook();
          $acl = ($addressbook->asRight(LibMelanie\Config\ConfigMelanie::WRITE) ? $this->gettext('read_write') : ($addressbook->asRight(LibMelanie\Config\ConfigMelanie::READ) ? $this->gettext('read_only') : ($addressbook->asRight(LibMelanie\Config\ConfigMelanie::FREEBUSY) ? $this->gettext('show') : $this->gettext('none'))));
          $shared = $user->uid != $addressbook->owner;
          $is_default = $default_addressbook->id == $addressbook->id;
          $this->rc->output->set_env("resource_id", $id);
          $this->rc->output->set_env("resource_name", $shared ? "(" . $addressbook->owner . ") " . $addressbook->name : $addressbook->name);
          $this->rc->output->set_env("resource_shared", $shared);
          $this->rc->output->set_env("resource_acl", $acl);
          $this->rc->output->set_env("resource_owner", $addressbook->owner);
          $this->rc->output->set_env("resource_default", $is_default);
          if (count($synchro_mobile) == 0) {
            // Si on n'a pas de ressource définie, utilise celle par défaut
            $this->rc->output->set_env("resource_synchro_mobile", $is_default);
            // C'est la ressource par defaut
            if ($is_default)
              $this->rc->output->set_env("resource_synchro_mobile_default", true);
              // Sinon on precise qu'on a aucune ressoure de définie
            else
              $this->rc->output->set_env("resource_synchro_mobile_not_set", true);
          }
          else {
            if (count($synchro_mobile) == 1 && in_array($id, $synchro_mobile) && $is_default) {
              // Si la seule ressource définie est celle par défaut
              $this->rc->output->set_env("resource_synchro_mobile_default", true);
            }
            $this->rc->output->set_env("resource_synchro_mobile", in_array($id, $synchro_mobile));
          }
          if (! $shared) {
            $this->rc->output->add_handler('usersaclframe', array(new M2contacts($this->get_user_bal()),'acl_frame'));
            $this->rc->output->add_handler('groupsaclframe', array(new M2contactsgroup($this->get_user_bal()),'acl_frame'));
          }

          $this->rc->output->add_handler('restore_contacts', array(new M2contacts($this->get_user_bal()),'restore_contacts'));

          $this->rc->output->send('melanie2_moncompte.m2_resource_contacts');
        }
        else {
          $this->rc->output->send();
        }
      }
      else {
        // register UI objects
        $this->rc->output->add_handlers(array('melanie2_resources_elements_list' => array(new M2contacts($this->get_user_bal()),'resources_elements_list'),'melanie2_resources_type_frame' => array($this,'melanie2_resources_type_frame')));
        $this->rc->output->set_env("resources_action", "contacts");
        $this->rc->output->include_script('list.js');
        $this->rc->output->set_pagetitle($this->gettext('resources'));
        $this->rc->output->send('melanie2_moncompte.resources_elements');
      }
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      melanie2_logs::get_instance()->log(melanie2_logs::ERROR, "[Resources] melanie2_moncompte::resources_contacts_init() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
  }
  /**
   * Initialisation du menu ressources pour les Tâches
   * Affichage du template et gestion de la sélection
   */
  public function resources_tasks_init() {

    try {
      $id = get_input_value('_id', RCUBE_INPUT_GPC);
      if (isset($id)) {
        $id = str_replace('_-P-_', '.', $id);
        // Instancie les objets Mélanie2
        $user = new LibMelanie\Api\Melanie2\User();
        $user->uid = $this->get_user_bal();
        $taskslist = new LibMelanie\Api\Melanie2\Taskslist($user);
        $taskslist->id = $id;
        if ($taskslist->load()) {
          // TODO : chargement des preferences en une seule requête (getList)
          $synchro_mobile = array();
          $prefs = new LibMelanie\Api\Melanie2\UserPrefs($user);
          $prefs->name = array('synchro_mobile');
          $prefs->scope = LibMelanie\Config\ConfigMelanie::TASKSLIST_PREF_SCOPE;
          foreach ($prefs->getList() as $pref) {
            $value = $pref->value;
            ${$pref->name} = unserialize($value);
            if (${$pref->name} === false) {
              ${$pref->name} = array();
            }
          }

          $default_taskslist = $user->getDefaultTaskslist();
          $acl = ($taskslist->asRight(LibMelanie\Config\ConfigMelanie::WRITE) ? $this->gettext('read_write') : ($taskslist->asRight(LibMelanie\Config\ConfigMelanie::READ) ? $this->gettext('read_only') : ($taskslist->asRight(LibMelanie\Config\ConfigMelanie::FREEBUSY) ? $this->gettext('show') : $this->gettext('none'))));
          $shared = $user->uid != $taskslist->owner;
          $is_default = $default_taskslist->id == $taskslist->id;
          $this->rc->output->set_env("resource_id", $id);
          $this->rc->output->set_env("resource_name", $shared ? "(" . $taskslist->owner . ") " . $taskslist->name : $taskslist->name);
          $this->rc->output->set_env("resource_shared", $shared);
          $this->rc->output->set_env("resource_acl", $acl);
          $this->rc->output->set_env("resource_owner", $taskslist->owner);
          $this->rc->output->set_env("resource_default", $is_default);
          if (count($synchro_mobile) == 0) {
            // Si on n'a pas de ressource définie, utilise celle par défaut
            $this->rc->output->set_env("resource_synchro_mobile", $is_default);
            // C'est la ressource par defaut
            if ($is_default)
              $this->rc->output->set_env("resource_synchro_mobile_default", true);
              // Sinon on precise qu'on a aucune ressoure de définie
            else
              $this->rc->output->set_env("resource_synchro_mobile_not_set", true);
          }
          else {
            if (count($synchro_mobile) == 1 && in_array($id, $synchro_mobile) && $is_default) {
              // Si la seule ressource définie est celle par défaut
              $this->rc->output->set_env("resource_synchro_mobile_default", true);
            }
            $this->rc->output->set_env("resource_synchro_mobile", in_array($id, $synchro_mobile));
          }
          if (! $shared) {
            $this->rc->output->add_handler('usersaclframe', array(new M2tasks($this->get_user_bal()),'acl_frame'));
            $this->rc->output->add_handler('groupsaclframe', array(new M2tasksgroup($this->get_user_bal()),'acl_frame'));
          }
          $this->rc->output->send('melanie2_moncompte.m2_resource_tasks');
        }
        else {
          $this->rc->output->send();
        }
      }
      else {
        // register UI objects
        $this->rc->output->add_handlers(array('melanie2_resources_elements_list' => array(new M2tasks($this->get_user_bal()),'resources_elements_list'),'melanie2_resources_type_frame' => array($this,'melanie2_resources_type_frame')));
        $this->rc->output->set_env("resources_action", "tasks");
        $this->rc->output->include_script('list.js');
        $this->rc->output->set_pagetitle($this->gettext('resources'));
        $this->rc->output->send('melanie2_moncompte.resources_elements');
      }
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      melanie2_logs::get_instance()->log(melanie2_logs::ERROR, "[Resources] melanie2_moncompte::resources_tasks_init() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
  }
  /**
   * Initialisation de la frame pour les ressources
   *
   * @param array $attrib
   * @return string
   */
  public function melanie2_resources_type_frame($attrib) {
    if (! $attrib['id']) {
      $attrib['id'] = 'rcmsharemelanie2typeframe';
    }

    $attrib['name'] = $attrib['id'];

    $this->rc->output->set_env('contentframe', $attrib['name']);
    $this->rc->output->set_env('blankpage', $attrib['src'] ? $this->rc->output->abs_url($attrib['src']) : 'program/resources/blank.gif');

    return $this->rc->output->frame($attrib);
  }
  /**
   * Création d'une nouvelle ressource Agendas/Contacts/Taches
   */
  public function add_resource() {
    $name = get_input_value('_name', RCUBE_INPUT_POST);
    $type = get_input_value('_type', RCUBE_INPUT_POST);
    $ret = false;

    if ($type == 'agendas') {
      $calendar = new M2calendar($this->get_user_bal(), md5($name . time() . $this->get_user_bal()));
      $ret = $calendar->createCalendar($name);
    }
    else if ($type == 'contacts') {
      $contacts = new M2contacts($this->get_user_bal(), md5($name . time() . $this->get_user_bal()));
      $ret = $contacts->createAddressbook($name);
    }
    else if ($type == 'tasks') {
      $tasks = new M2tasks($this->get_user_bal(), md5($name . time() . $this->get_user_bal()));
      $ret = $tasks->createTaskslist($name);
    }
    if ($ret) {
      $this->rc->output->show_message('melanie2_moncompte.add_resource_ok_' . $type, 'confirm');
      $this->rc->output->command('plugin.melanie2_add_resource_success', json_encode(array()));
    }
    else {
      $this->rc->output->show_message('melanie2_moncompte.add_resource_nok_' . $type, 'error');
    }
    return $ret;
  }
  /**
   * Suppression de la ressource sélectionnée Agenda/Contacts/Tâches
   */
  public function delete_resource() {
    $id = get_input_value('_id', RCUBE_INPUT_POST);
    $type = get_input_value('_type', RCUBE_INPUT_POST);

    $ret = false;

    if ($type == 'agendas') {
      $calendar = new M2calendar($this->get_user_bal(), $id);
      $ret = $calendar->deleteCalendar();
    }
    else if ($type == 'contacts') {
      $contacts = new M2contacts($this->get_user_bal(), $id);
      $ret = $contacts->deleteAddressbook();
    }
    else if ($type == 'tasks') {
      $tasks = new M2tasks($this->get_user_bal(), $id);
      $ret = $tasks->deleteTaskslist();
    }
    if ($ret) {
      $this->rc->output->show_message('melanie2_moncompte.delete_resource_ok_' . $type, 'confirm');
      $this->rc->output->command('plugin.melanie2_delete_resource_success', json_encode(array()));
    }
    else {
      $this->rc->output->show_message('melanie2_moncompte.delete_resource_nok_' . $type, 'error');
    }
    return $ret;
  }
  /**
   * Masquer la ressource dans roundcube
   */
  public function hide_resource_roundcube() {
    $mbox = get_input_value('_mbox', RCUBE_INPUT_POST);
    $type = get_input_value('_type', RCUBE_INPUT_POST);

    if (isset($mbox) && isset($type)) {
      $conf_name = 'hidden_' . $type . 's';
      // Récupération des préférences de l'utilisateur
      $hidden = $this->rc->config->get($conf_name, array());
      $hidden[$mbox] = 1;
      if ($this->rc->user->save_prefs(array($conf_name => $hidden)))
        $this->rc->output->show_message('melanie2_moncompte.hide_resource_confirm', 'confirmation');
      else
        $this->rc->output->show_message('melanie2_moncompte.modify_error', 'error');
    }
    else {
      $this->rc->output->show_message('melanie2_moncompte.modify_error', 'error');
    }
  }
  /**
   * Afficher la ressource dans roundcube
   */
  public function show_resource_roundcube() {
    $mbox = get_input_value('_mbox', RCUBE_INPUT_POST);
    $type = get_input_value('_type', RCUBE_INPUT_POST);

    if (isset($mbox) && isset($type)) {
      $conf_name = 'hidden_' . $type . 's';
      // Récupération des préférences de l'utilisateur
      $hidden = $this->rc->config->get($conf_name, array());
      unset($hidden[$mbox]);
      if ($this->rc->user->save_prefs(array($conf_name => $hidden)))
        $this->rc->output->show_message('melanie2_moncompte.show_resource_confirm', 'confirmation');
      else
        $this->rc->output->show_message('melanie2_moncompte.modify_error', 'error');
    }
    else {
      $this->rc->output->show_message('melanie2_moncompte.modify_error', 'error');
    }
  }
  /**
   * Afficher la ressource dans roundcube
   */
  public function no_synchro_on_mobile() {

    try {
      $mbox = get_input_value('_mbox', RCUBE_INPUT_POST);
      $type = get_input_value('_type', RCUBE_INPUT_POST);

      if (isset($mbox) && isset($type)) {
        // Instancie les objets Mélanie2
        $user = new LibMelanie\Api\Melanie2\User();
        $user->uid = $this->get_user_bal();
        $pref = new LibMelanie\Api\Melanie2\UserPrefs($user);
        $pref->name = 'synchro_mobile';
        if ($type == 'calendar')
          $pref->scope = LibMelanie\Config\ConfigMelanie::CALENDAR_PREF_SCOPE;
        elseif ($type == 'contact')
          $pref->scope = LibMelanie\Config\ConfigMelanie::ADDRESSBOOK_PREF_SCOPE;
        else
          $pref->scope = LibMelanie\Config\ConfigMelanie::TASKSLIST_PREF_SCOPE;
        if ($pref->load()) {
          $value = unserialize($pref->value);
          if ($value === false)
            $value = array();
          foreach ($value as $key => $val) {
            if ($val == $mbox)
              unset($value[$key]);
            else {
              // Vérifier que l'on a bien les droits sur l'agenda
              if ($type == 'calendar')
                $sync = new LibMelanie\Api\Melanie2\Calendar($user);
              elseif ($type == 'contact')
                $sync = new LibMelanie\Api\Melanie2\Addressbook($user);
              else
                $sync = new LibMelanie\Api\Melanie2\Taskslist($user);
              $sync->id = $val;
              if (! $sync->load()) {
                unset($value[$key]);
              }
            }
          }
          $pref->value = serialize($value);
          $ret = $pref->save();
          if (! is_null($ret))
            $this->rc->output->show_message('melanie2_moncompte.no_synchro_mobile_confirm', 'confirmation');
          else
            $this->rc->output->show_message('melanie2_moncompte.modify_error', 'error');
        }
        else {
          $this->rc->output->show_message('melanie2_moncompte.modify_error', 'error');
        }
      }
      else {
        $this->rc->output->show_message('melanie2_moncompte.modify_error', 'error');
      }
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      melanie2_logs::get_instance()->log(melanie2_logs::ERROR, "[Resources] melanie2_moncompte::no_synchro_on_mobile() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
  }
  /**
   * Afficher la ressource dans roundcube
   */
  public function synchro_on_mobile() {
    try {
      $mbox = get_input_value('_mbox', RCUBE_INPUT_POST);
      $type = get_input_value('_type', RCUBE_INPUT_POST);

      if (isset($mbox) && isset($type)) {
        // Instancie les objets Mélanie2
        $user = new LibMelanie\Api\Melanie2\User();
        $user->uid = $this->get_user_bal();
        $pref = new LibMelanie\Api\Melanie2\UserPrefs($user);
        $pref->name = 'synchro_mobile';
        if ($type == 'calendar')
          $pref->scope = LibMelanie\Config\ConfigMelanie::CALENDAR_PREF_SCOPE;
        elseif ($type == 'contact')
          $pref->scope = LibMelanie\Config\ConfigMelanie::ADDRESSBOOK_PREF_SCOPE;
        else
          $pref->scope = LibMelanie\Config\ConfigMelanie::TASKSLIST_PREF_SCOPE;
        if ($pref->load()) {
          $value = unserialize($pref->value);
          if ($value === false)
            $value = array();
        }
        else {
          $value = array();
        }
        if (count($value) === 0) {
          if ($type == 'calendar')
            $default = $user->getDefaultCalendar();
          elseif ($type == 'contact')
            $default = $user->getDefaultAddressbook();
          else
            $default = $user->getDefaultTaskslist();
          if (isset($default)) {
            $value[] = $default->id;
          }
        }
        else {
          foreach ($value as $key => $val) {
            // Vérifier que l'on a bien les droits sur l'agenda
            if ($type == 'calendar')
              $sync = new LibMelanie\Api\Melanie2\Calendar($user);
            elseif ($type == 'contact')
              $sync = new LibMelanie\Api\Melanie2\Addressbook($user);
            else
              $sync = new LibMelanie\Api\Melanie2\Taskslist($user);
            $sync->id = $val;
            if (! $sync->load()) {
              unset($value[$key]);
            }
          }
        }
        if (! in_array($mbox, $value)) {
          $value[] = $mbox;
        }
        $pref->value = serialize($value);
        $ret = $pref->save();
        if (! is_null($ret))
          $this->rc->output->show_message('melanie2_moncompte.synchro_mobile_confirm', 'confirmation');
        else
          $this->rc->output->show_message('melanie2_moncompte.modify_error', 'error');
      }
      else {
        $this->rc->output->show_message('melanie2_moncompte.modify_error', 'error');
      }
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      melanie2_logs::get_instance()->log(melanie2_logs::ERROR, "[Resources] melanie2_moncompte::synchro_on_mobile() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
  }
  /**
   * Définir la ressource par défaut
   */
  public function set_default_resource() {
    try {
      $mbox = get_input_value('_mbox', RCUBE_INPUT_POST);
      $type = get_input_value('_type', RCUBE_INPUT_POST);

      if (isset($mbox) && isset($type)) {
        // Instancie les objets Mélanie2
        $user = new LibMelanie\Api\Melanie2\User();
        $user->uid = $this->get_user_bal();
        $pref = new LibMelanie\Api\Melanie2\UserPrefs($user);
        if ($type == 'calendar') {
          $pref->scope = LibMelanie\Config\ConfigMelanie::CALENDAR_PREF_SCOPE;
          $pref->name = LibMelanie\Config\ConfigMelanie::CALENDAR_PREF_DEFAULT_NAME;
        }
        elseif ($type == 'contact') {
          $pref->scope = LibMelanie\Config\ConfigMelanie::ADDRESSBOOK_PREF_SCOPE;
          $pref->name = LibMelanie\Config\ConfigMelanie::ADDRESSBOOK_PREF_DEFAULT_NAME;
        }
        else {
          $pref->scope = LibMelanie\Config\ConfigMelanie::TASKSLIST_PREF_SCOPE;
          $pref->name = LibMelanie\Config\ConfigMelanie::TASKSLIST_PREF_DEFAULT_NAME;
        }
        $pref->value = $mbox;
        $ret = $pref->save();
        if (! is_null($ret))
          $this->rc->output->show_message('melanie2_moncompte.set_default_confirm', 'confirmation');
        else
          $this->rc->output->show_message('melanie2_moncompte.modify_error', 'error');
      }
      else {
        $this->rc->output->show_message('melanie2_moncompte.modify_error', 'error');
      }
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      melanie2_logs::get_instance()->log(melanie2_logs::ERROR, "[Resources] melanie2_moncompte::set_default_resource() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
  }

  /**
   * ****** PRIVATE *********
   */
  /**
   * Récupère le username en fonction du compte dans l'url ou de la session
   *
   * @return string
   */
  private function get_username() {
    if (! isset($this->user_name))
      $this->set_user_properties();

    return $this->user_name;
  }
  /**
   * Récupère l'uid de la boite, sans l'objet de partage si c'est une boite partagée
   *
   * @return string
   */
  private function get_user_bal() {
    if (! isset($this->user_bal))
      $this->set_user_properties();

    return $this->user_bal;
  }
  /**
   * Récupère l'uid de l'objet de partage
   *
   * @return string
   */
  private function get_share_objet() {
    if (! isset($this->user_objet_share))
      $this->set_user_properties();

    return $this->user_objet_share;
  }
  /**
   * Récupère l'host de l'utilisateur
   *
   * @return string
   */
  private function get_host() {
    if (! isset($this->user_host))
      $this->set_user_properties();

    return $this->user_host;
  }
  /**
   * Définition des propriétées de l'utilisateur
   */
  private function set_user_properties() {
    // Chargement de l'account passé en Get
    $this->get_account = melanie2::get_account();
    if (! empty($this->get_account)) {
      // Récupère la liste des bal gestionnaire de l'utilisateur
      $list_balp = melanie2::get_user_balp_gestionnaire($this->rc->get_user_name());
      $is_gestionnaire = false;
      // Récupération du username depuis l'url
      $this->user_name = urldecode($this->get_account);
      $inf = explode('@', $this->user_name);
      $this->user_objet_share = $inf[0];
      $this->user_host = $inf[1];
      if (strpos($this->user_objet_share, '.-.') !== false) {
        $inf = explode('.-.', $this->user_objet_share);
        $this->user_bal = $inf[1];
      }
      else {
        $this->user_bal = $this->user_objet_share;
      }
      // Parcour les bal pour vérifier qu'il est bien gestionnaire
      foreach ($list_balp as $balp) {
        $uid = $balp['uid'][0];
        if ($this->user_objet_share == $uid) {
          // La bal est bien en gestionnaire
          $is_gestionnaire = true;
          break;
        }
      }
      // Si pas de bal gestionnaire on remet les infos de l'utilisateur
      if (! $is_gestionnaire) {
        // Récupération du username depuis la session
        $this->user_name = $this->rc->get_user_name();
        $this->user_objet_share = $this->rc->user->get_username('local');
        $this->user_host = $this->rc->user->get_username('host');
        $this->user_bal = $this->user_objet_share;
      }
    }
    else {
      // Récupération du username depuis la session
      $this->user_name = $this->rc->get_user_name();
      $this->user_objet_share = $this->rc->user->get_username('local');
      $this->user_host = $this->rc->user->get_username('host');
      $this->user_bal = $this->user_objet_share;
    }
  }
}
