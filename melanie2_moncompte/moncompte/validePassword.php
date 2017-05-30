<?php
/**
 * Plugin Melanie2_Moncompte
 *
 * plugin melanie2_Moncompte pour roundcube
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



class validePassword {

	/**
	 * Modification du pwd via le web service (wsamande)
	 */
	static function changePassword() {

		$user_name = trim(rcube_utils::get_input_value('_changepassword_username', rcube_utils::INPUT_POST));
		$old_pwd = trim(rcube_utils::get_input_value('_changepassword_oldpassword', rcube_utils::INPUT_POST));
		$new_pwd = trim(rcube_utils::get_input_value('_changepassword_newpassword', rcube_utils::INPUT_POST));
		$new_pwd2 = trim(rcube_utils::get_input_value('_changepassword_newpassword_confirm', rcube_utils::INPUT_POST));

		if ($new_pwd == $new_pwd2) {

			$config_serveurws = rcmail::get_instance()->config->get('ws_changepass');
			$client_chpwd = new \SoapClient($config_serveurws, array("cache_wsdl" => WSDL_CACHE_NONE));

			try {
				// Appel a la méthode de modification du mot de passe
				$ret = $client_chpwd->changePasswordMelanie2($user_name, $old_pwd, $new_pwd, true);
				if ($ret->code === 0) {
					// Succès du changement de mot de passe
					$_SESSION['password'] = rcmail::get_instance()->encrypt($new_pwd);
					rcmail::get_instance()->output->show_message('melanie2_moncompte.changepassword_confirm', 'confirmation');
					rcmail::get_instance()->output->add_script("parent.$('#changepasswordframe').dialog('close');");
					$res = 'confirmation';
				} else {
					if ($ret->code >= 500 && $ret->code <= 599) {
						// Message d'erreur à l'utilisateur
//						rcmail::get_instance()->output->show_message('melanie2_moncompte.changepassword_convention_error', 'error');
						rcmail::get_instance()->output->show_message($ret->message, 'error');
						melanie2_logs::get_instance()->log(melanie2_logs::ERROR, 'changepassword_convention_error ' . $ret->message);
					} else {
						// Echec du changement du mot de passe
						rcmail::get_instance()->output->show_message($ret->message, 'error');
						melanie2_logs::get_instance()->log(melanie2_logs::ERROR, 'changepassword_modification_error ' . $ret->message);
					}

				}
			} catch (Exception $ex) {
				// Echec du changement du mot de passe
				// Erreur d'appel au service Web
				rcmail::get_instance()->output->show_message('melanie2_moncompte.changepassword_appel_error', 'error');
				melanie2_logs::get_instance()->log(melanie2_logs::ERROR, 'changepassword_appel_error ' . $ex->getMessage() . $ex->getTraceAsString());
			}

		}

	}

}


