<?php

/**
 * VeximAccountAdmin
 *
 * Plugin that covers the non-admin part of Vexim web interface.
 *
 * @date 2009-11-12
 * @author Axel Sjostedt
 * @url http://axel.sjostedt.no/misc/dev/roundcube/
 * @licence GNU GPL
 */
 
class veximaccountadmin extends rcube_plugin
{
	
	public $task = 'settings';
	private $config;
	private $db;
	private $sections = array();
	
	function init()
	{
		$rcmail = rcmail::get_instance();
		$this->add_texts('localization/', array('accountadmin'));
	
		$this->register_action('plugin.veximaccountadmin', array($this, 'veximaccountadmin_init'));
		$this->register_action('plugin.veximaccountadmin-save', array($this, 'veximaccountadmin_save'));
		
		$this->include_script('veximaccountadmin.js');
		$this->include_stylesheet('veximaccountadmin.css');
	}

	function veximaccountadmin_init()
	{
		$this->add_texts('localization/');
		$this->register_handler('plugin.body', array($this, 'veximaccountadmin_form'));

		$rcmail = rcmail::get_instance();
		$rcmail->output->set_pagetitle($this->gettext('accountadministration'));
	    $rcmail->output->send('plugin');
	}


	private function _load_config()
	{
		
		$fpath_config_dist	= $this->home . '/config.inc.php.dist';
		$fpath_config 		= $this->home . '/config.inc.php';
		
		if (is_file($fpath_config_dist) and is_readable($fpath_config_dist))
			$found_config_dist = true;
		if (is_file($fpath_config) and is_readable($fpath_config))
			$found_config = true;
		
		if ($found_config_dist or $found_config) {
			ob_start();

			if ($found_config_dist) {
				include($fpath_config_dist);
				$veximaccountadmin_config_dist = $veximaccountadmin_config;
			}
			if ($found_config) {
				include($fpath_config);
			}
			
			$config_array = array_merge($veximaccountadmin_config_dist, $veximaccountadmin_config);
			$this->config = $config_array;
			ob_end_clean();
		} else {
			raise_error(array(
				'code' => 527,
				'type' => 'php',
				'message' => "Failed to load VeximAccountAdmin plugin config"), true, true);
		}
	}

	private function _db_connect($mode)
	{
		$this->db = rcube_db::factory($this->config['db_dsn'], '', false);
		$this->db->db_connect($mode);

		// check DB connections and exit on failure
		if ($err_str = $this->db->is_error()) {
		  raise_error(array(
		    'code' => 603,
		    'type' => 'db',
		    'message' => $err_str), FALSE, TRUE);
		}
	}

	function veximaccountadmin_save()
	{
	
		$this->add_texts('localization/');
		$this->register_handler('plugin.body', array($this, 'veximaccountadmin_form'));
	
		$rcmail = rcmail::get_instance();	
		$this->_load_config();
		$rcmail->output->set_pagetitle($this->gettext('accountadministration'));
	
		// Set variables and make them ready to be put into DB
		$user = $rcmail->user->data['username'];
	
	
		$on_avscan = get_input_value('on_avscan', RCUBE_INPUT_POST);
		if(!$on_avscan)
			$on_avscan = 0;
		  
		$on_spamassassin = get_input_value('on_spamassassin', RCUBE_INPUT_POST);
		if(!$on_spamassassin)
			$on_spamassassin = 0;
	
		$sa_tag = get_input_value('sa_tag', RCUBE_INPUT_POST);
		$sa_refuse = get_input_value('sa_refuse', RCUBE_INPUT_POST);
	
		$axel_on_movespam = get_input_value('axel_on_movespam', RCUBE_INPUT_POST);
		if(!$axel_on_movespam)
			$axel_on_movespam = 0;
	
		$on_vacation = get_input_value('on_vacation', RCUBE_INPUT_POST);
		if(!$on_vacation)
			$on_vacation = 0;
	
		$vacation = get_input_value('vacation', RCUBE_INPUT_POST);
		
		// In case someone bypass the javascript maxlength, we make vacation message
		// shorter if above treshold
		if (strlen($vacation) > $this->config['vexim_vacation_maxlength']) {
			$vacation = substr($vacation, 0, $this->config['vexim_vacation_maxlength']);
		}
		
		$on_forward = get_input_value('on_forward', RCUBE_INPUT_POST);
		if(!$on_forward)
			$on_forward = 0;
		  
		$forward = get_input_value('forward', RCUBE_INPUT_POST);
		
		$unseen = get_input_value('unseen', RCUBE_INPUT_POST);
		if(!$unseen)
			$unseen = 0;
		
		$maxmsgsize = get_input_value('maxmsgsize', RCUBE_INPUT_POST);
	
		// Using $_POST here bacause get_input_value seems to not work with arrays
		$acts = $_POST['_headerblock_rule_act'];
		$prefs = $_POST['_headerblock_rule_field'];
		$vals = $_POST['_headerblock_rule_value'];
		 
		$res = $this->_save($user,$on_avscan,$on_spamassassin,$sa_tag,$sa_refuse,$axel_on_movespam,$on_vacation,$vacation,$on_forward,$forward,$unseen,$maxmsgsize,$acts,$prefs,$vals);
		  
		if (!$res) {
			$rcmail->output->command('display_message', $this->gettext('savesuccess-config'), 'confirmation');
		} else {
			$rcmail->output->command('display_message', $res, 'error');
		}
		
		rcmail_overwrite_action('plugin.veximaccountadmin');
	
		$this->veximaccountadmin_init();
	}
  
	function veximaccountadmin_form()
	{

		$rcmail = rcmail::get_instance();
		$this->_load_config();
	
		// add labels to client - to be used in JS alerts
		$rcmail->output->add_label(
			'veximaccountadmin.enterallpassfields',
			'veximaccountadmin.passwordinconsistency',
			'veximaccountadmin.autoresponderlong',
			'veximaccountadmin.autoresponderlongnum',
			'veximaccountadmin.autoresponderlongmax',
			'veximaccountadmin.headerblockdelete',
			'veximaccountadmin.headerblockdeleteall',
			'veximaccountadmin.headerblockexists',
			'veximaccountadmin.headerblockentervalue'
		);
	
		$rcmail->output->set_env('product_name', $rcmail->config->get('product_name'));
	 
		$settings = $this->_get_configuration();
		
		$on_avscan			= $settings['on_avscan'];
		$on_spamassassin	= $settings['on_spamassassin'];
		$sa_tag				= $settings['sa_tag'];
		$sa_refuse			= $settings['sa_refuse'];
		$axel_on_movespam	= $settings['axel_on_movespam'];
		$on_vacation		= $settings['on_vacation'];
		$vacation 			= $settings['vacation'];
		$on_forward			= $settings['on_forward'];
		$forward			= $settings['forward'];
		$unseen				= $settings['unseen'];
		$maxmsgsize			= $settings['maxmsgsize'];
		$user_id			= $settings['user_id'];
		$domain_id			= $settings['domain_id'];
		
		$domain_settings = $this->_get_domain_configuration($domain_id);
		
		$default_sa_tag		= $domain_settings['sa_tag'];
		$default_sa_refuse	= $domain_settings['sa_refuse'];
		$default_maxmsgsize	= $domain_settings['maxmsgsize'];
		$active_domain		= $domain_settings['domain'];
		
		$rcmail->output->set_env('vacation_maxlength', $this->config['vexim_vacation_maxlength']);
		
	
		$out .= '<p class="introtext">' . $this->gettext('introtext') . '</p>' . "\n";


		if ($this->config['show_admin_link'] == true and $settings['admin'] == true) {
			$out .= '<p class="adminlink">';
			$out .= sprintf($this->gettext('adminlinktext'), '<a href="' . $this->config['vexim_url'] . '" target="_blank">', '</a>');
			$out .= "</p>\n";
		}

		// =====================================================================================================
		// Password
		$out .= '<fieldset><legend>' . $this->gettext('password') . '</legend>' . "\n";
		$out .= '<div class="fieldset-content">';
		$out .= '<p>' . $this->gettext('passwordcurrentexplanation') . '</p>';
		$out .= '<table class="vexim-settings" cellpadding="0" cellspacing="0">';
	
		$field_id = 'curpasswd';
		$input_passwordcurrent = new html_passwordfield(array('name' => '_curpasswd', 'id' => $field_id, 'class' => 'text-long', 'autocomplete' => 'off'));
	
		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('passwordcurrent')),
					$input_passwordcurrent->show(),
					'');
	
		$field_id = 'newpasswd';
		$input_passwordnew = new html_passwordfield(array('name' => '_newpasswd', 'id' => $field_id, 'class' => 'text-long', 'autocomplete' => 'off'));
	
		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('passwordnew')),
					$input_passwordnew->show(),
					'');
	
		$field_id = 'confpasswd';
		$input_passwordconf = new html_passwordfield(array('name' => '_confpasswd', 'id' => $field_id, 'class' => 'text-long', 'autocomplete' => 'off'));
	
		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('passwordconfirm')),
					$input_passwordconf->show(),
					'');
	
	
		$out .= '</table>';
		
		$out .= '</div></fieldset>' . "\n\n";     
	
	
		// =====================================================================================================
		// Spam/Virus
		$out .= '<fieldset><legend>' . $this->gettext('spamvirus') . '</legend>' . "\n";
		$out .= '<div class="fieldset-content">';
		$out .= '<table class="vexim-settings" cellpadding="0" cellspacing="0">';
		
		$field_id = 'on_avscan';
		$input_virusenabled = new html_checkbox(array('name' => 'on_avscan', 'id' => $field_id, 'value' => 1));
	
		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('virusenabled')),
					$input_virusenabled->show($on_avscan?1:0), 
					'<br /><span class="vexim-explanation">' . $this->gettext('virusenabledexplanation') . '</span>');
	
	
		$field_id = 'on_spamassassin';
		$input_spamenabled = new html_checkbox(array('name' => 'on_spamassassin', 'id' => $field_id, 'value' => 1));
	
		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('spamenabled')),
					$input_spamenabled->show($on_spamassassin?1:0),
					'<br /><span class="vexim-explanation">' . $this->gettext('spamenabledexplanation') . '</span>');

		$field_id = 'sa_tag';
		$input_spamscoretag = new html_select(array('name' => 'sa_tag', 'id' => $field_id, 'class' => 'select'));

		$decPlaces = 0;
		$found_number = false;
		for ($i = 1; $i <= 20; $i = $i + 1) {
			$i = number_format($i, $decPlaces);
			$input_spamscoretag->add($i, $i);
			if ($sa_tag == $i)
				$found_number = true;
		}
		for ($i = 25; $i <= 100; $i = $i + 5) {
			$i = number_format($i, $decPlaces);
			$input_spamscoretag->add($i, $i);
			if ($sa_tag == $i)
				$found_number = true;
}

		// If the value from database cannot be choosed among the list we present,
		// add it to the end of the list. This may happen because Vexim lets the
		// user write in a number in a textbox.
		if (!$found_number)
			$input_spamscoretag->add($sa_tag, $sa_tag);

		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('spamscoretag')),
					$input_spamscoretag->show($sa_tag),
					'<br /><span class="vexim-explanation">' . $this->gettext('spamscoretagexplanation') . '. <span class="sameline">' . $this->gettext('domaindefault') . ': ' . $default_sa_tag . '.</span></span>');		

		$field_id = 'sa_refuse';
		$input_spamscorerefuse = new html_select(array('name' => 'sa_refuse', 'id' => $field_id, 'class' => 'select'));

		$found_number = false;
		for ($i = 1; $i <= 20; $i = $i + 1) {
			$i = number_format($i, $decPlaces);
			$input_spamscorerefuse->add($i, $i);
			if ($sa_refuse == $i)
				$found_number = true;
		}
		for ($i = 25; $i <= 200; $i = $i + 5) {
			$i = number_format($i, $decPlaces);
			$input_spamscorerefuse->add($i, $i);
			if ($sa_refuse == $i)
				$found_number = true;
		}
		for ($i = 300; $i <= 900; $i = $i + 100) {
			$i = number_format($i, $decPlaces);
			$input_spamscorerefuse->add($i, $i);
			if ($sa_refuse == $i)
				$found_number = true;
		}
		$i = number_format(999, $decPlaces);
		$input_spamscorerefuse->add($i, $i);
		if ($sa_refuse == $i)
			$found_number = true;
		
		// If the value from database cannot be choosed among the list we present,
		// add it to the end of the list. This may happen because Vexim lets the
		// user write in a number in a textbox.
		if (!$found_number)
			$input_spamscorerefuse->add($sa_refuse, $sa_refuse);
		
		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('spamscorerefuse')),
					$input_spamscorerefuse->show($sa_refuse),
					'<br /><span class="vexim-explanation">' . $this->gettext('spamscorerefuseexplanation') . '. <span class="sameline">' . $this->gettext('domaindefault') . ': ' . $default_sa_refuse . '.</span></span>');		

		if ($this->config['movespam_transporter']) {
			
			$spammoveexplanation = '<br /><span class="vexim-explanation">' . str_replace("%italicstart", "<i>", str_replace("%italicend", "</i>", $this->gettext('spammoveexplanation_part1')));
			if ($this->config['parsefolders_script'])
				$spammoveexplanation .= ' ' . $this->gettext('spammoveexplanation_part2');
			$spammoveexplanation .= ' ' . $this->gettext('spammoveexplanation_part3');

			$field_id = 'axel_on_movespam';
			$input_spammove = new html_checkbox(array('name' => 'axel_on_movespam', 'id' => $field_id, 'value' => 1));
	
			$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('spammove')),
						$input_spammove->show($axel_on_movespam?1:0),
						$spammoveexplanation);

		}
	
		$out .= '</table>';
		
		if ($this->config['parsefolders_script'] and $this->config['parsefolders_script_show_tip'])
			$out .= '<p class="vexim-explanation">' . str_replace('%italicstart', '<i>', str_replace('%italicend', '</i>', $this->gettext('spamtip'))) . '</p>';
		
		$out .= '</div></fieldset>' . "\n\n";     
	
		// =====================================================================================================
		// Autoresponder
		$out .= '<fieldset><legend>' . $this->gettext('autoresponder') . '</legend>' . "\n";
		$out .= '<div class="fieldset-content">';
		$out .= '<table class="vexim-settings" cellpadding="0" cellspacing="0">';
		
		$field_id = 'on_vacation';
		$input_autoresponderenabled = new html_checkbox(array('name' => 'on_vacation', 'id' => $field_id, 'value' => 1));
	
		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('autoresponderenabled')),
					$input_autoresponderenabled->show($on_vacation?1:0),
					'');
	
	
		$field_id = 'vacation';
		$input_autorespondermessage = new html_textarea(array('name' => 'vacation', 'id' => $field_id, 'class' => 'textarea'));
	
		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('autorespondermessage')),
					$input_autorespondermessage->show($vacation),
					'<br /><span class="vexim-explanation">' . $this->gettext('autorespondermessageexplanation') . '</span>');
					
		$out .= '</table>';
		
		$out .= '</div></fieldset>' . "\n\n";
	
		// =====================================================================================================
		// Forward
		$out .= '<fieldset><legend>' . $this->gettext('forwarding') . '</legend>' . "\n";
		$out .= '<div class="fieldset-content">';
		$out .= '<table class="vexim-settings" cellpadding="0" cellspacing="0">';
		
		$field_id = 'on_forward';
		$input_forwardingenabled = new html_checkbox(array('name' => 'on_forward', 'id' => $field_id, 'value' => 1));
	
		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('forwardingenabled')),
					$input_forwardingenabled->show($on_forward?1:0));                                                
	
	
		 $field_id = 'forward';
		$input_forwardingaddress = new html_inputfield(array('name' => 'forward', 'id' => $field_id, 'maxlength' => 255, 'class' => 'text-long'));
	
		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('forwardingaddress')),
					$input_forwardingaddress->show($forward));
	
		 $field_id = 'unseen';
		$input_forwardinglocal = new html_checkbox(array('name' => 'unseen', 'id' => $field_id, 'value' => 1));
	
		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('forwardinglocal')),
					$input_forwardinglocal->show($unseen?1:0));                                           

		$out .= '</table>';
		$out .= '</div></fieldset>' . "\n\n";     
		
		
		// =====================================================================================================
		// Header blocks (based on code from Philip Weir's sauserprefs plugin
		//                http://roundcube.net/plugins/sauserprefs)
	
		$out .= '<fieldset><legend>' . $this->gettext('blockbyheader') . '</legend>' . "\n";
		
		$out .= '<div class="fieldset-content">';
		$out .= '<p>' . $this->gettext('blockbyheaderexplanation') . '</p>';

		$table = new html_table(array('class' => 'headerblockprefstable', 'cols' => 3));
		$field_id = 'rcmfd_headerblockrule';
		$input_headerblockrule = new html_select(array('name' => '_headerblockrule', 'id' => $field_id));
		$input_headerblockrule->add($this->gettext('headerfrom'),'From');
		$input_headerblockrule->add($this->gettext('headerto'), 'To');
		$input_headerblockrule->add($this->gettext('headersubject'), 'Subject');
		$input_headerblockrule->add($this->gettext('headerxmailer'), 'X-Mailer');

		$field_id = 'rcmfd_headerblockvalue';
		$input_headerblockvalue = new html_inputfield(array('name' => '_headerblockvalue', 'id' => $field_id, 'style' => 'width:270px;'));

		$field_id = 'rcmbtn_add_address';
		$button_addaddress = $this->api->output->button(array('command' => 'plugin.veximaccountadmin.headerblock_add', 'type' => 'input', 'class' => 'button', 'label' => 'veximaccountadmin.addrule', 'style' => 'width: 130px;'));

		$table->add(null, $input_headerblockrule->show());
		$table->add(null, $input_headerblockvalue->show());
		$table->add(array('align' => 'right'), $button_addaddress);

		$delete_all = $this->api->output->button(array('command' => 'plugin.veximaccountadmin.headerblock_delete_all', 'type' => 'link', 'label' => 'veximaccountadmin.deleteall'));

		$table->add(array('colspan' => 3, 'id' => 'listcontrols'), $delete_all);
		$table->add_row();

		$address_table = new html_table(array('id' => 'headerblock-rules-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 3));
		$address_table->add_header(array('width' => '120px'), $this->gettext('field'));
		$address_table->add_header(null, $this->gettext('value'));
		$address_table->add_header(array('width' => '40px'), '&nbsp;');

		$this->_address_row($address_table, null, null, $attrib);

		// Get the header rules from DB. Should probably be put in a function.
		$this->_load_config();
		$this->_db_connect('r');

		$sql_result = $this->db->query(
		  "SELECT blockhdr, blockval 
		   FROM   blocklists
		   WHERE  user_id = '$user_id'
		   AND    domain_id = '$domain_id'
		   ORDER BY block_id;"
		  );

		if ($sql_result && $this->db->num_rows($sql_result) > 0)
			$norules = 'display: none;';

		$address_table->set_row_attribs(array('style' => $norules));
		$address_table->add(array('colspan' => '3'), rep_specialchars_output($this->gettext('noaddressrules')));
		$address_table->add_row();

		$this->api->output->set_env('address_rule_count', $this->db->num_rows());

		while ($sql_result && $sql_arr = $this->db->fetch_assoc($sql_result)) {
			$field = $sql_arr['blockhdr'];
			$value = $sql_arr['blockval'];

			$this->_address_row($address_table, $field, $value, $attrib);
		}

		$table->add(array('colspan' => 3), html::div(array('id' => 'headerblock-rules-cont'), $address_table->show()));
		$table->add_row();

		if ($table->size())
			$out .= $table->show();
	
		$out .= '</div></fieldset>' . "\n\n";   
		
	
		// =====================================================================================================
		// Parameters
		$out .= '<fieldset><legend>' . $this->gettext('parameters') . '</legend>' . "\n";
		
		$out .= '<div class="fieldset-content">';
		$out .= '<table class="vexim-settings" cellpadding="0" cellspacing="0">';
		
		$field_id = 'maxmsgsize';
		$input_messagesize = new html_inputfield(array('name' => 'maxmsgsize', 'id' => $field_id, 'maxlength' => 3, 'size' => 4));
	
		if ($default_maxmsgsize == 0)
			$default_maxmsgsize = $this->gettext('unlimited');
		else
			$default_maxmsgsize = $default_maxmsgsize . ' kb';
	
		$out .= sprintf("<tr><th><label for=\"%s\">%s</label>:</th><td>%s%s</td></tr>\n",
					$field_id,
					rep_specialchars_output($this->gettext('messagesize')),
					$input_messagesize->show($maxmsgsize),
					'<br /><span class="vexim-explanation">' . str_replace('%d', $active_domain, str_replace('%m', $default_maxmsgsize, $this->gettext('messagesizeexplanation'))) . '</span>');
	
		$out .= '</table>';
		$out .= '</div></fieldset>' . "\n\n";     
	
	
		// =====================================================================================================
	
		$out .= html::p(null,
			$rcmail->output->button(array(
			'command' => 'plugin.veximaccountadmin-save',
			'type' => 'input',
			'class' => 'button mainaction',
			'label' => 'save'
			)));
	
		$rcmail->output->add_gui_object('veximform', 'veximaccountadminform');
	
		$out = $rcmail->output->form_tag(array('id' => 'veximaccountadminform', 'name' => 'veximaccountadminform', 'method' => 'post', 'action' => './?_task=settings&_action=plugin.veximaccountadmin-save'), $out);

	
		$out = html::div(array('class' => 'settingsbox', 'style' => 'margin:0 0 15px 0;'), html::div(array('class' => 'boxtitle'), $this->gettext('accountadministration')) . html::div(array('style' => 'padding:15px'), $outtop . "\n" . $out . "\n" . $outbottom));
	
		return $out;
	 
	  }
	  
	  
	private function _get_configuration()
	{
		$this->_load_config();
		$rcmail = rcmail::get_instance();		
		$this->_db_connect('r');
		
		$sql = 'SELECT * FROM `users` WHERE `username` = ' . $this->db->quote($rcmail->user->data['username'],'text') . ' LIMIT 1;';
		$res = $this->db->query($sql);
					 
		if ($err = $this->db->is_error()){
		   return $err;
		}
		$ret = $this->db->fetch_assoc($res);
	
		return $ret;  
	}
	

	private function _get_domain_configuration($domain_id)
	{
		$this->_load_config();
		$rcmail = rcmail::get_instance();		
		$this->_db_connect('r');
		
		$sql = 'SELECT * FROM `domains` WHERE `domain_id` = ' . $this->db->quote($domain_id) . ' LIMIT 1;';
		$res = $this->db->query($sql);
					 
		if ($err = $this->db->is_error()){
		   return $err;
		}
		$ret = $this->db->fetch_assoc($res);
	
		return $ret;  
	}

	private function _save($user,$on_avscan,$on_spamassassin,$sa_tag,$sa_refuse,$axel_on_movespam,$on_vacation,$vacation,$on_forward,$forward,$unseen,$maxmsgsize,$acts,$prefs,$vals)
	{
		$rcmail = rcmail::get_instance();
	
		$this->_load_config();
		$this->_db_connect('w');
		$settings = $this->_get_configuration();
		$user_id			= $settings['user_id'];
		$domain_id			= $settings['domain_id'];
	
			foreach ($acts as $idx => $act){
				if ($act == "DELETE") {
					$result = false;
		
					$this->db->query(
					  "DELETE FROM blocklists
					   WHERE  user_id = '$user_id' 
					   AND    domain_id = '$domain_id' 
					   AND    blockhdr = '". $prefs[$idx] ."'
					   AND    blockval = '". $vals[$idx] . "';"
					  );
					$result = $this->db->affected_rows();
		
					if (!$result)
						break;
				}
				elseif ($act == "INSERT") {
					$result = false;
		
					$this->db->query(
					  "INSERT INTO blocklists
					   (user_id, domain_id, blockhdr,blockval,color)
					   VALUES ('". $user_id. "', '". $domain_id. "', '". $prefs[$idx] . "', '". $vals[$idx] ."', 'black')"
					  );
		
					$result = $this->db->affected_rows();
		
					if (!$result)
						break;
				}
			}

		if ($this->config['movespam_transporter']) {
			$add_sql = '`axel_on_movespam` = ' . $this->db->quote($axel_on_movespam,'text') . ', ';
		}
	
		$sql = 'UPDATE `users` SET ' . $add_sql . '`on_avscan` = ' . $this->db->quote($on_avscan,'text') . ', `on_spamassassin` = ' . $this->db->quote($on_spamassassin,'text') . ', `sa_tag` = ' . $this->db->quote($sa_tag,'text') . ', `sa_refuse` = ' . $this->db->quote($sa_refuse,'text') . ', `on_vacation` = ' . $this->db->quote($on_vacation,'text') . ', `vacation` = ' . $this->db->quote($vacation,'text') . ', `on_forward` = ' . $this->db->quote($on_forward,'text') . ', `forward` = ' . $this->db->quote($forward,'text') . ', `unseen` = ' . $this->db->quote($unseen,'text') . ', `maxmsgsize` = ' . $this->db->quote($maxmsgsize,'text') . '  WHERE `username` = ' . $this->db->quote($user,'text') . ' LIMIT 1;';
	
		$config_error = 0;
		$res = $this->db->query($sql);
		if ($err = $this->db->is_error()) {
			$config_error = 1;
		}
		$res = $this->db->affected_rows($res);
	
		$curpwd = get_input_value('_curpasswd', RCUBE_INPUT_POST);
		$newpwd = get_input_value('_newpasswd', RCUBE_INPUT_POST);
		  
		if ($curpwd != '' and $newpwd != '') {
			  
			$trytochangepass = 1;
			$password_change_error = 0;
	  
			if ($rcmail->decrypt($_SESSION['password']) != $curpwd) {
				// Current password was not correct.
				// Note that we check against the password saved in RoundCube.
				// Alternatively we can to a:
				// 		if (_crypt_password($curpwd, $settings['domain_id'])
				$password_change_error = 1;
				$addtomessage .= '. ' . $this->gettext('saveerror-pass-mismatch');
			} else {
				
				if ($this->config['crypted_password_hack'] == true) {
					$crypted_password = $this->_crypt_password($newpwd);
					$sql_pass = "UPDATE users SET crypt=" . $this->db->quote($crypted_password) . ", clear=" . $this->db->quote($crypted_password) . " WHERE username=" . $this->db->quote($user,'text') . " LIMIT 1";
				} else {
					$crypted_password = $this->_crypt_password($newpwd);
					$sql_pass = "UPDATE users SET crypt=" . $this->db->quote($crypted_password) . ", clear=" . $this->db->quote($newpwd) . " WHERE username=" . $this->db->quote($user,'text') . " LIMIT 1";
				}
				
				$res_pass = $this->db->query($sql_pass);
				if ($err = $this->db->is_error()) {
					$password_change_error = 2;
					$addtomessage .= '.' . $this->gettext('saveerror-pass-database');
				} else {

					$res_pass = $this->db->affected_rows($res_pass);
					if ($res_pass == 0) {
						$password_change_error = 3;
						$addtomessage .= '. ' . $this->gettext('saveerror-pass-norows');
					} elseif ($res_pass == 1) {
						$password_change_success = 1;
						$_SESSION['password'] = $rcmail->encrypt($newpwd);
					}
				}
			}
		}
		
		// This error handling is a bit messy, should be improved!
		
		// We may altso want to check for $res and $res_pass to see if changes were done or not

	
		if ($config_error == 1) {
			// Mysql error on config update. Also print any errors from password.
			return $this->gettext('saveerror-config-database')  . $addtomessage;
		}
		if ($config_error == 0 and $trytochangepass == 1 and $password_change_error == 1) {
			// Config updated, but error in password saving due to mismatch
			return $this->gettext('savesuccess-config-saveerror-pass-mismatch');
		}		
		if ($config_error == 0 and $trytochangepass == 1 and $password_change_error) {
			// Config updated, but other error in password saving
			return $this->gettext('savesuccess-config') . $addtomessage;
		}

		if ($config_error == 0) {
			// Best case, no trouble reported
			return false;
		}
				
		// If still here - send all error messages.
		return $this->gettext('saveerror-internalerror') . $addtomessage;

	}
  
  	private function _address_row($address_table, $field, $value, $attrib)
	{
		if (!isset($field))
			$address_table->set_row_attribs(array('style' => 'display: none;'));

		$hidden_action = new html_hiddenfield(array('name' => '_headerblock_rule_act[]', 'value' => ''));
		$hidden_field = new html_hiddenfield(array('name' => '_headerblock_rule_field[]', 'value' => $field));
		$hidden_text = new html_hiddenfield(array('name' => '_headerblock_rule_value[]', 'value' => $value));

		switch ($field) {
			case "From":
				$fieldtxt = rep_specialchars_output($this->gettext('headerfrom'));
				break;
			case "To":
				$fieldtxt = rep_specialchars_output($this->gettext('headerto'));
				break;
			case "Subject":
				$fieldtxt = rep_specialchars_output($this->gettext('headersubject'));
				break;
			case "X-Mailer":
				$fieldtxt = rep_specialchars_output($this->gettext('headerxmailer'));
				break;
		}

		$address_table->add(array('class' => 'field'), $fieldtxt);
		$address_table->add(array('class' => 'email'), $value);
		$del_button = $this->api->output->button(array('command' => 'plugin.veximaccountadmin.addressrule_del', 'type' => 'image', 'image' => 'plugins/veximaccountadmin/delete.png', 'alt' => 'delete', 'title' => 'delete'));
		$address_table->add('control', '&nbsp;' . $del_button . $hidden_action->show() . $hidden_field->show() . $hidden_text->show());

		return $address_table;
	}

	private function _crypt_password($clear, $salt = '')
	{
		// Function from Vexim.
		$settings = $this->_get_configuration();
		$cryptscheme = $this->config['vexim_cryptscheme'];

		if ($cryptscheme == 'sha')
		{
			$hash = sha1($clear);
			$cryptedpass = '{SHA}' . base64_encode(pack('H*', $hash));
		}
		else
		{
			if ($cryptscheme == 'des')
			{
				if ($salt != '')
				{
					$salt = substr($salt, 0, 2);
				}
				else
				{
					$salt = substr(uniqid(), 0, 2);
				}
			}
			else
			if ($cryptscheme == 'md5')
			{
				if ($salt != '')
				{
					$salt = substr($salt, 0, 12);
				}
				else
				{
					$salt = '$1$'.substr(uniqid(), 0, 8).'$';
				}
			}
			else
			{
				$salt = '';
			}
			$cryptedpass = crypt($clear, $salt);
		}

		return $cryptedpass;
	}
	
}
