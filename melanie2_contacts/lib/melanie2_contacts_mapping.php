<?php
/**
 * Melanie2 driver for the contacts plugin
 *
 * Mapping file for Melanie2 backend
 *
 * @version @package_version@
 */
@include_once 'includes/libm2.php';

use LibMelanie\Api\Melanie2;

/**
 * Classe de mapping vers Melanie2 (vers la librairie ORM M2)
 * Permet le mapping des données contacts de roundcube vers l'ORM Mélanie2
 * Les méthodes sont statiques et publiques
 * Format de nom de méthode <rc ou m2>_to_<m2 ou rc>_<champ a mapper>
 * Format de nom de paramètre $<champ a mapper>_<m2 ou rc>
 *
 * @author Thomas Payen <thomas.payen@i-carre.net> PNE Annuaire et Messagerie/MEDDE
 */
class melanie2_contacts_mapping {
  public static $mapping_contact_cols = array("name" => "name","firstname" => "firstname","surname" => "lastname","email" => "email","email:work" => "email1","email:other" => "email2",
            /*"email:internet" => "email3",*/
            "ID" => "id","middlename" => "middlenames","prefix" => "nameprefix","suffix" => "namesuffix","nickname" => "alias","organization" => "company","jobtitle" => "role","birthday" => "birthday","phone:mobile" => "cellphone","phone:home" => "homephone","phone:work" => "workphone","phone:fax" => "fax","phone:pager" => "pager","website:homepage" => "url","website:freebusy" => "freebusyurl","cuid" => "uid","notes" => "notes");
  
  private static $_countries_list;
  /**
   * Converti un contact roundcube pour melanie2
   *
   * @param array $_contact_rc
   * @param LibMelanie\Api\Melanie2\Contact $_contact_m2
   * @return LibMelanie\Api\Melanie2\Contact
   */
  public static function rc_to_m2_contact($_contact_rc, LibMelanie\Api\Melanie2\Contact $_contact_m2) {
    if (melanie2_logs::is(melanie2_logs::TRACE))
      melanie2_logs::get_instance()->log(melanie2_logs::TRACE, "[calendar] melanie2_contacts::rc_to_m2_contact() : " . var_export($_contact_rc, true));
      // Parcour les données de contact
    foreach ($_contact_rc as $key => $value) {
      if (isset($value) && isset(self::$mapping_contact_cols[$key])) {
        if (is_array($value)) {
          if (isset($value[0]))
            $_contact_m2->{self::$mapping_contact_cols[$key]} = $value[0];
        }
        else
          $_contact_m2->{self::$mapping_contact_cols[$key]} = $value;
      }
    }
    // Liste tous les champs du contact
    foreach (self::$mapping_contact_cols as $key => $col) {
      if (! isset($_contact_rc[$key]) && isset($_contact_m2->$col) && $key != 'ID' && $key != 'cuid')
        $_contact_m2->$col = '';
    }
    // Email home
    if (isset($_contact_rc['email:home'])) {
      if (is_array($_contact_rc['email:home']))
        $_contact_m2->email = $_contact_rc['email:home'][0];
      else
        $_contact_m2->email = $_contact_rc['email:home'];
    }
    // Address home
    if (isset($_contact_rc['address:home']) && is_array($_contact_rc['address:home'])) {
      foreach ($_contact_rc['address:home'] as $address_home) {
        if (isset($address_home['street']))
          $_contact_m2->homestreet = $address_home['street'];
        if (isset($address_home['locality']))
          $_contact_m2->homecity = $address_home['locality'];
        if (isset($address_home['zipcode']))
          $_contact_m2->homepostalcode = $address_home['zipcode'];
        if (isset($address_home['region']))
          $_contact_m2->homeprovince = $address_home['region'];
        if (isset($address_home['country']))
          $_contact_m2->homecountry = self::rc_to_m2_country($address_home['country']);
      }
    }
    else {
    		$_contact_m2->homestreet = null;
    		$_contact_m2->homecity = null;
    		$_contact_m2->homepostalcode = null;
    		$_contact_m2->homeprovince = null;
    		$_contact_m2->homecountry = null;
    }
    // Address work
    if (isset($_contact_rc['address:work']) && is_array($_contact_rc['address:work'])) {
      foreach ($_contact_rc['address:work'] as $address_work) {
        if (isset($address_work['street']))
          $_contact_m2->workstreet = $address_work['street'];
        if (isset($address_work['locality']))
          $_contact_m2->workcity = $address_work['locality'];
        if (isset($address_work['zipcode']))
          $_contact_m2->workpostalcode = $address_work['zipcode'];
        if (isset($address_work['region']))
          $_contact_m2->workprovince = $address_work['region'];
        if (isset($address_work['country']))
          $_contact_m2->workcountry = self::rc_to_m2_country($address_work['country']);
      }
    }
    else {
	    	$_contact_m2->workstreet = null;
	    	$_contact_m2->workcity = null;
	    	$_contact_m2->workpostalcode = null;
	    	$_contact_m2->workprovince = null;
	    	$_contact_m2->workcountry = null;
    }
    // Photo
    if (isset($_contact_rc['photo'])) {
      if ($_contact_rc['photo'] == "") {
        $_contact_m2->photo = "";
        $_contact_m2->phototype = "";
      }
      else {
        $_contact_m2->photo = bin2hex($_contact_rc['photo']);
        $_contact_m2->phototype = 'image/jpeg';
      }
    }
    return $_contact_m2;
  }

  /**
   * Converti un contact melanie2 en contact attendu par roundcube
   *
   * @param LibMelanie\Api\Melanie2\Contact $_contact
   * @return array:
   */
  public static function m2_to_rc_contact($cols = null, $_contact) {
    $contact = array();
    $all = false;
    if (! isset($cols)) {
      $all = true;
      $cols = array_keys(self::$mapping_contact_cols);
    }
    foreach ($cols as $col) {
      if (isset($_contact->{self::$mapping_contact_cols[$col]}) && $_contact->{self::$mapping_contact_cols[$col]} != "")
        $contact[$col] = $_contact->{self::$mapping_contact_cols[$col]};
    }
    // Si on récupère toutes les données
    if ($all) {
      // Address
      // Home
      $address = array();
      if (isset($_contact->homestreet) && $_contact->homestreet != "")
        $address['street'] = $_contact->homestreet;
      if (isset($_contact->homecity) && $_contact->homecity != "")
        $address['locality'] = $_contact->homecity;
      if (isset($_contact->homepostalcode) && $_contact->homepostalcode != "")
        $address['zipcode'] = $_contact->homepostalcode;
      if (isset($_contact->homecountry) && $_contact->homecountry != "")
        $address['country'] = self::m2_to_rc_country($_contact->homecountry);
      if (isset($_contact->homeprovince) && $_contact->homeprovince != "")
        $address['region'] = $_contact->homeprovince;
      if (count($address) > 0) {
        $contact['address:home'] = array();
        $contact['address:home'][] = $address;
      }
      // Work
      $address = array();
      if (isset($_contact->workstreet) && $_contact->workstreet != "")
        $address['street'] = $_contact->workstreet;
      if (isset($_contact->workcity) && $_contact->workcity != "")
        $address['locality'] = $_contact->workcity;
      if (isset($_contact->workpostalcode) && $_contact->workpostalcode != "")
        $address['zipcode'] = $_contact->workpostalcode;
      if (isset($_contact->workcountry) && $_contact->workcountry != "")
        $address['country'] = self::m2_to_rc_country($_contact->workcountry);
      if (isset($_contact->workprovince) && $_contact->workprovince != "")
        $address['region'] = $_contact->workprovince;
      if (count($address) > 0) {
        $contact['address:work'] = array();
        $contact['address:work'][] = $address;
      }
      // Name
      if (! isset($contact['name'])) {
        if ($_contact->type == Melanie2\Contact::TYPE_LIST) {
          $contact['name'] = isset($contact['surname']) ? $contact['surname'] : $contact['firstname'];
        }
        else {
          $contact['name'] = $contact['firstname'] . (isset($contact['surname']) ? ' ' . $contact['surname'] : '');
        }
      }
        
        // Photo
      if (isset($_contact->photo)) {
        $contact['photo'] = base64_encode(pack('H' . strlen($_contact->photo), $_contact->photo));
      }
    }
    return $contact;
  }


  private static function m2_to_rc_country($m2_country) {
  	if (!isset(self::$_countries_list)) {
  		self::$_countries_list = require_once 'horde_countries.php';
  	}
    if (isset(self::$_countries_list[strtoupper($m2_country)])) {
      return self::$_countries_list[strtoupper($m2_country)];
    }
    else {
      return $m2_country;
    }
  }

  private static function rc_to_m2_country($rc_country) {
  	if (!isset(self::$_countries_list)) {
  		self::$_countries_list = require_once 'horde_countries.php';
  	}
    $countries = array_map('strtolower', self::$_countries_list);
    if (in_array(strtolower($rc_country), $countries)) {
      return array_keys($countries, strtolower($rc_country))[0];
    }
    else {
      return $rc_country;
    }
  }
}